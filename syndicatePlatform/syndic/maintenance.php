<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../utils/helpers.php';
require_once '../utils/Mailer.php';

checkAuthentication();
checkRole(ROLE_SYNDIC);

$page_title = "Maintenance Management";

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get syndic information
$stmt = $db->prepare("SELECT * FROM syndic WHERE id_admin_syndic = ?");
$stmt->execute([$_SESSION['user_id']]);
$syndic_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$syndic_info) {
    $_SESSION['error'] = 'Syndic information not found';
    header('Location: dashboard.php');
    exit();
}

$syndic_id = $syndic_info['id_syndic'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_status':
                $request_id = intval($_POST['request_id']);
                $new_status = sanitizeInput($_POST['new_status']);
                $notes = sanitizeInput($_POST['notes']);
                $result = updateMaintenanceStatus($db, $request_id, $new_status, $notes, $syndic_id);
                if ($result['success']) {
                    $_SESSION['success'] = $result['message'];
                } else {
                    $_SESSION['error'] = $result['message'];
                }
                break;
                
            case 'assign_maintenance':
                $request_id = intval($_POST['request_id']);
                $prestataire_nom = sanitizeInput($_POST['prestataire_nom']);
                $prestataire_telephone = sanitizeInput($_POST['prestataire_telephone']);
                $prestataire_email = sanitizeInput($_POST['prestataire_email']);
                $date_intervention = $_POST['date_intervention'];
                $result = assignMaintenance($db, $request_id, $prestataire_nom, $prestataire_telephone, $prestataire_email, $date_intervention, $syndic_id);
                if ($result['success']) {
                    $_SESSION['success'] = $result['message'];
                } else {
                    $_SESSION['error'] = $result['message'];
                }
                break;
                
            case 'complete_maintenance':
                $request_id = intval($_POST['request_id']);
                $cout_final = floatval($_POST['cout_final']);
                $rapport = sanitizeInput($_POST['rapport']);
                $result = completeMaintenance($db, $request_id, $cout_final, $rapport, $syndic_id);
                if ($result['success']) {
                    $_SESSION['success'] = $result['message'];
                } else {
                    $_SESSION['error'] = $result['message'];
                }
                break;
        }
        header('Location: maintenance.php');
        exit();
    }
}

// Get filter parameters
$filter_status = $_GET['status'] ?? 'all';
$filter_priority = $_GET['priority'] ?? 'all';

// Build query based on filters
$where_conditions = ["dm.syndic_id = ?"];
$params = [$syndic_id];

if ($filter_status !== 'all') {
    $where_conditions[] = "dm.statut = ?";
    $params[] = $filter_status;
}

if ($filter_priority !== 'all') {
    $where_conditions[] = "dm.priorite = ?";
    $params[] = $filter_priority;
}

$where_clause = implode(' AND ', $where_conditions);

// Get maintenance requests
$stmt = $db->prepare("SELECT dm.*, a.numero_appartement, u.nom_complet as resident_name, u.email as resident_email,
                             am.prestataire_nom, am.prestataire_telephone, am.date_intervention_prevue, am.rapport_intervention, am.cout_final
                      FROM demandes_maintenance dm
                      LEFT JOIN appartement a ON dm.apartment_id = a.id_appartement
                      LEFT JOIN residents r ON dm.resident_id = r.id_resident
                      LEFT JOIN utilisateur u ON r.user_id = u.id_utilisateur
                      LEFT JOIN affectations_maintenance am ON dm.id_demande = am.demande_id
                      WHERE $where_clause
                      ORDER BY 
                        CASE dm.priorite 
                          WHEN 'urgente' THEN 1 
                          WHEN 'haute' THEN 2 
                          WHEN 'normale' THEN 3 
                          WHEN 'basse' THEN 4 
                        END ASC, 
                        dm.date_demande DESC");
$stmt->execute($params);
$maintenance_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get request details for modal
$request_details = null;
if (isset($_GET['view'])) {
    $view_id = intval($_GET['view']);
    $stmt = $db->prepare("SELECT dm.*, a.numero_appartement, u.nom_complet as resident_name, u.email as resident_email, u.telephone as resident_phone,
                                 am.prestataire_nom, am.prestataire_telephone, am.prestataire_email, am.date_intervention_prevue, am.date_intervention_reelle, am.rapport_intervention, am.cout_final, am.notes_technicien
                          FROM demandes_maintenance dm
                          LEFT JOIN appartement a ON dm.apartment_id = a.id_appartement
                          LEFT JOIN residents r ON dm.resident_id = r.id_resident
                          LEFT JOIN utilisateur u ON r.user_id = u.id_utilisateur
                          LEFT JOIN affectations_maintenance am ON dm.id_demande = am.demande_id
                          WHERE dm.id_demande = ? AND dm.syndic_id = ?");
    $stmt->execute([$view_id, $syndic_id]);
    $request_details = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Helper functions
function updateMaintenanceStatus($db, $request_id, $new_status, $notes, $syndic_id) {
    try {
        $stmt = $db->prepare("UPDATE demandes_maintenance SET statut = ?, notes_syndic = ?, date_vue = CASE WHEN statut = 'nouvelle' THEN NOW() ELSE date_vue END WHERE id_demande = ? AND syndic_id = ?");
        $stmt->execute([$new_status, $notes, $request_id, $syndic_id]);
        
        // If marking as in progress, update affectation date
        if ($new_status === 'en_cours') {
            $stmt = $db->prepare("UPDATE demandes_maintenance SET date_affectation = NOW() WHERE id_demande = ? AND syndic_id = ?");
            $stmt->execute([$request_id, $syndic_id]);
        }
        
        // If marking as completed, update resolution date
        if ($new_status === 'terminee') {
            $stmt = $db->prepare("UPDATE demandes_maintenance SET date_resolution = NOW() WHERE id_demande = ? AND syndic_id = ?");
            $stmt->execute([$request_id, $syndic_id]);
        }
        
        return ['success' => true, 'message' => 'Maintenance status updated successfully!'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function assignMaintenance($db, $request_id, $prestataire_nom, $prestataire_telephone, $prestataire_email, $date_intervention, $syndic_id) {
   try {
       $db->beginTransaction();
       
       // Update maintenance request status to in progress
       $stmt = $db->prepare("UPDATE demandes_maintenance SET statut = 'en_cours', date_affectation = NOW() WHERE id_demande = ? AND syndic_id = ?");
       $stmt->execute([$request_id, $syndic_id]);
       
       // Delete existing assignment if any
       $stmt = $db->prepare("DELETE FROM affectations_maintenance WHERE demande_id = ?");
       $stmt->execute([$request_id]);
       
       // Create new assignment
       $stmt = $db->prepare("INSERT INTO affectations_maintenance (demande_id, assigned_by, prestataire_nom, prestataire_telephone, prestataire_email, date_intervention_prevue, statut) 
                             VALUES (?, ?, ?, ?, ?, ?, 'assignee')");
       $stmt->execute([$request_id, $_SESSION['user_id'], $prestataire_nom, $prestataire_telephone, $prestataire_email, $date_intervention]);
       
       $db->commit();
       
       return ['success' => true, 'message' => 'Maintenance assigned successfully!'];
       
   } catch (Exception $e) {
       $db->rollBack();
       return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
   }
}

function completeMaintenance($db, $request_id, $cout_final, $rapport, $syndic_id) {
   try {
       $db->beginTransaction();
       
       // Update maintenance request as completed
       $stmt = $db->prepare("UPDATE demandes_maintenance SET statut = 'terminee', date_resolution = NOW(), cout_reparation = ? WHERE id_demande = ? AND syndic_id = ?");
       $stmt->execute([$cout_final, $request_id, $syndic_id]);
       
       // Update assignment
       $stmt = $db->prepare("UPDATE affectations_maintenance SET statut = 'terminee', cout_final = ?, rapport_intervention = ?, date_intervention_reelle = NOW() WHERE demande_id = ?");
       $stmt->execute([$cout_final, $rapport, $request_id]);
       
       $db->commit();
       
       return ['success' => true, 'message' => 'Maintenance completed successfully!'];
       
   } catch (Exception $e) {
       $db->rollBack();
       return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
   }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title><?php echo $page_title; ?></title>
   <link rel="stylesheet" href="<?php echo PUBLIC_URL; ?>assets/css/style.css">
   <link rel="stylesheet" href="<?php echo PUBLIC_URL; ?>assets/css/dashboard.css">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
   <!-- Navigation -->
   <nav class="navbar">
       <div class="nav-brand">
           <h2><i class="fas fa-building"></i> Syndic Panel</h2>
           <small style="color: #bdc3c7;"><?php echo htmlspecialchars($syndic_info['nom_syndic']); ?></small>
       </div>
       <div class="nav-user">
           <span><i class="fas fa-user"></i> <?php echo $_SESSION['user_name']; ?></span>
           <a href="<?php echo PUBLIC_URL; ?>logout.php" class="btn btn-logout">
               <i class="fas fa-sign-out-alt"></i> Logout
           </a>
       </div>
   </nav>

   <div class="dashboard-container">
       <!-- Sidebar -->
       <aside class="sidebar">
           <div class="sidebar-header">
               <h3>Navigation</h3>
           </div>
           <nav class="sidebar-nav">
               <ul>
                   <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                   <li><a href="residents.php"><i class="fas fa-users"></i> Residents</a></li>
                   <li><a href="apartments.php"><i class="fas fa-home"></i> Apartments</a></li>
                   <li class="active"><a href="maintenance.php"><i class="fas fa-tools"></i> Maintenance</a></li>
                   <li><a href="invoices.php"><i class="fas fa-file-invoice-dollar"></i> Invoices</a></li>
                   <li><a href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
                   <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                   <li><a href="profile.php"><i class="fas fa-user-cog"></i> Profile</a></li>
               </ul>
           </nav>
       </aside>

       <!-- Main Content -->
       <main class="main-content">
           <div class="content-header">
               <h1><i class="fas fa-tools"></i> Maintenance Management</h1>
               <p>Track and manage maintenance requests (<?php echo count($maintenance_requests); ?> total)</p>
           </div>

           <!-- Alert Messages -->
           <?php if(isset($_SESSION['success'])): ?>
           <div class="alert alert-success">
               <i class="fas fa-check-circle"></i>
               <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
           </div>
           <?php endif; ?>

           <?php if(isset($_SESSION['error'])): ?>
           <div class="alert alert-error">
               <i class="fas fa-exclamation-triangle"></i>
               <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
           </div>
           <?php endif; ?>

           <!-- Filters -->
           <div class="content-section">
               <div class="filters">
                   <form method="GET" class="filter-form">
                       <div class="filter-group">
                           <label for="status">Status:</label>
                           <select name="status" id="status" onchange="this.form.submit()">
                               <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                               <option value="nouvelle" <?php echo $filter_status === 'nouvelle' ? 'selected' : ''; ?>>New</option>
                               <option value="vue" <?php echo $filter_status === 'vue' ? 'selected' : ''; ?>>Viewed</option>
                               <option value="en_cours" <?php echo $filter_status === 'en_cours' ? 'selected' : ''; ?>>In Progress</option>
                               <option value="terminee" <?php echo $filter_status === 'terminee' ? 'selected' : ''; ?>>Completed</option>
                               <option value="annulee" <?php echo $filter_status === 'annulee' ? 'selected' : ''; ?>>Cancelled</option>
                           </select>
                       </div>
                       
                       <div class="filter-group">
                           <label for="priority">Priority:</label>
                           <select name="priority" id="priority" onchange="this.form.submit()">
                               <option value="all" <?php echo $filter_priority === 'all' ? 'selected' : ''; ?>>All Priority</option>
                               <option value="urgente" <?php echo $filter_priority === 'urgente' ? 'selected' : ''; ?>>Urgent</option>
                               <option value="haute" <?php echo $filter_priority === 'haute' ? 'selected' : ''; ?>>High</option>
                               <option value="normale" <?php echo $filter_priority === 'normale' ? 'selected' : ''; ?>>Normal</option>
                               <option value="basse" <?php echo $filter_priority === 'basse' ? 'selected' : ''; ?>>Low</option>
                           </select>
                       </div>
                       
                       <?php if ($filter_status !== 'all' || $filter_priority !== 'all'): ?>
                       <a href="maintenance.php" class="btn btn-sm btn-secondary">Clear Filters</a>
                       <?php endif; ?>
                   </form>
               </div>
           </div>

           <!-- Maintenance Requests -->
           <div class="content-section">
               <h2>Maintenance Requests</h2>
               
               <?php if (!empty($maintenance_requests)): ?>
               <div class="maintenance-grid">
                   <?php foreach($maintenance_requests as $request): ?>
                   <div class="maintenance-card priority-<?php echo $request['priorite']; ?> status-<?php echo $request['statut']; ?>">
                       <div class="maintenance-header">
                           <div class="request-info">
                               <h3><?php echo htmlspecialchars($request['titre'] ?? 'Maintenance Request'); ?></h3>
                               <div class="request-meta">
                                   <span class="priority-badge priority-<?php echo $request['priorite']; ?>">
                                       <?php echo ucfirst($request['priorite']); ?>
                                   </span>
                                   <span class="status-badge status-<?php echo $request['statut']; ?>">
                                       <?php echo ucfirst(str_replace('_', ' ', $request['statut'])); ?>
                                   </span>
                               </div>
                           </div>
                           <div class="request-date">
                               <?php echo date('M j, Y', strtotime($request['date_demande'])); ?>
                           </div>
                       </div>
                       
                       <div class="maintenance-body">
                           <div class="description">
                               <p><?php echo htmlspecialchars(substr($request['description'], 0, 150)); ?>
                               <?php if(strlen($request['description']) > 150): ?>...<?php endif; ?></p>
                           </div>
                           
                           <div class="request-details">
                               <div class="detail-item">
                                   <i class="fas fa-user"></i>
                                   <span><?php echo htmlspecialchars($request['resident_name'] ?? 'Unknown Resident'); ?></span>
                               </div>
                               <div class="detail-item">
                                   <i class="fas fa-home"></i>
                                   <span>Apt <?php echo htmlspecialchars($request['numero_appartement'] ?? 'N/A'); ?></span>
                               </div>
                               <div class="detail-item">
                                   <i class="fas fa-wrench"></i>
                                   <span><?php echo ucfirst($request['type_probleme']); ?></span>
                               </div>
                           </div>
                           
                           <?php if($request['prestataire_nom']): ?>
                           <div class="assignment-info">
                               <strong>Assigned to:</strong> <?php echo htmlspecialchars($request['prestataire_nom']); ?>
                               <?php if($request['date_intervention_prevue']): ?>
                               <br><small>Scheduled: <?php echo date('M j, Y H:i', strtotime($request['date_intervention_prevue'])); ?></small>
                               <?php endif; ?>
                           </div>
                           <?php endif; ?>
                       </div>
                       
                       <div class="maintenance-actions">
                           <button class="btn btn-sm btn-primary" onclick="viewRequest(<?php echo $request['id_demande']; ?>)">
                               <i class="fas fa-eye"></i> View Details
                           </button>
                           
                           <?php if($request['statut'] === 'nouvelle'): ?>
                           <button class="btn btn-sm btn-success" onclick="updateStatus(<?php echo $request['id_demande']; ?>, 'vue')">
                               <i class="fas fa-check"></i> Mark as Viewed
                           </button>
                           <?php endif; ?>
                           
                           <?php if($request['statut'] === 'vue'): ?>
                           <button class="btn btn-sm btn-warning" onclick="showAssignModal(<?php echo $request['id_demande']; ?>)">
                               <i class="fas fa-user-plus"></i> Assign
                           </button>
                           <?php endif; ?>
                           
                           <?php if($request['statut'] === 'en_cours'): ?>
                           <button class="btn btn-sm btn-success" onclick="showCompleteModal(<?php echo $request['id_demande']; ?>)">
                               <i class="fas fa-check-circle"></i> Complete
                           </button>
                           <?php endif; ?>
                       </div>
                   </div>
                   <?php endforeach; ?>
               </div>
               <?php else: ?>
               <div class="empty-state">
                   <i class="fas fa-tools"></i>
                   <h3>No maintenance requests</h3>
                   <p>All maintenance requests will appear here when residents submit them.</p>
               </div>
               <?php endif; ?>
           </div>
       </main>
   </div>

   <!-- View Request Modal -->
   <?php if($request_details): ?>
   <div id="viewModal" class="modal" style="display: block;">
       <div class="modal-content modal-large">
           <div class="modal-header">
               <h3><i class="fas fa-tools"></i> Maintenance Request Details</h3>
               <span class="close" onclick="closeModal()">&times;</span>
           </div>
           <div class="modal-body">
               <div class="request-detail-grid">
                   <div class="detail-section">
                       <h4>Request Information</h4>
                       <div class="detail-row">
                           <strong>Title:</strong> <?php echo htmlspecialchars($request_details['titre'] ?? 'Maintenance Request'); ?>
                       </div>
                       <div class="detail-row">
                           <strong>Description:</strong><br>
                           <?php echo nl2br(htmlspecialchars($request_details['description'])); ?>
                       </div>
                       <div class="detail-row">
                           <strong>Type:</strong> <?php echo ucfirst($request_details['type_probleme']); ?>
                       </div>
                       <div class="detail-row">
                           <strong>Priority:</strong> 
                           <span class="priority-badge priority-<?php echo $request_details['priorite']; ?>">
                               <?php echo ucfirst($request_details['priorite']); ?>
                           </span>
                       </div>
                       <div class="detail-row">
                           <strong>Status:</strong> 
                           <span class="status-badge status-<?php echo $request_details['statut']; ?>">
                               <?php echo ucfirst(str_replace('_', ' ', $request_details['statut'])); ?>
                           </span>
                       </div>
                   </div>
                   
                   <div class="detail-section">
                       <h4>Resident & Location</h4>
                       <div class="detail-row">
                           <strong>Resident:</strong> <?php echo htmlspecialchars($request_details['resident_name']); ?>
                       </div>
                       <div class="detail-row">
                           <strong>Email:</strong> <?php echo htmlspecialchars($request_details['resident_email']); ?>
                       </div>
                       <?php if($request_details['resident_phone']): ?>
                       <div class="detail-row">
                           <strong>Phone:</strong> <?php echo htmlspecialchars($request_details['resident_phone']); ?>
                       </div>
                       <?php endif; ?>
                       <div class="detail-row">
                           <strong>Apartment:</strong> <?php echo htmlspecialchars($request_details['numero_appartement'] ?? 'N/A'); ?>
                       </div>
                   </div>
                   
                   <div class="detail-section">
                       <h4>Timeline</h4>
                       <div class="detail-row">
                           <strong>Submitted:</strong> <?php echo date('M j, Y H:i', strtotime($request_details['date_demande'])); ?>
                       </div>
                       <?php if($request_details['date_vue']): ?>
                       <div class="detail-row">
                           <strong>Viewed:</strong> <?php echo date('M j, Y H:i', strtotime($request_details['date_vue'])); ?>
                       </div>
                       <?php endif; ?>
                       <?php if($request_details['date_affectation']): ?>
                       <div class="detail-row">
                           <strong>Assigned:</strong> <?php echo date('M j, Y H:i', strtotime($request_details['date_affectation'])); ?>
                       </div>
                       <?php endif; ?>
                       <?php if($request_details['date_resolution']): ?>
                       <div class="detail-row">
                           <strong>Completed:</strong> <?php echo date('M j, Y H:i', strtotime($request_details['date_resolution'])); ?>
                       </div>
                       <?php endif; ?>
                   </div>
                   
                   <?php if($request_details['prestataire_nom']): ?>
                   <div class="detail-section">
                       <h4>Assignment Details</h4>
                       <div class="detail-row">
                           <strong>Service Provider:</strong> <?php echo htmlspecialchars($request_details['prestataire_nom']); ?>
                       </div>
                       <?php if($request_details['prestataire_telephone']): ?>
                       <div class="detail-row">
                           <strong>Phone:</strong> <?php echo htmlspecialchars($request_details['prestataire_telephone']); ?>
                       </div>
                       <?php endif; ?>
                       <?php if($request_details['prestataire_email']): ?>
                       <div class="detail-row">
                           <strong>Email:</strong> <?php echo htmlspecialchars($request_details['prestataire_email']); ?>
                       </div>
                       <?php endif; ?>
                       <?php if($request_details['date_intervention_prevue']): ?>
                       <div class="detail-row">
                           <strong>Scheduled:</strong> <?php echo date('M j, Y H:i', strtotime($request_details['date_intervention_prevue'])); ?>
                       </div>
                       <?php endif; ?>
                       <?php if($request_details['date_intervention_reelle']): ?>
                       <div class="detail-row">
                           <strong>Completed:</strong> <?php echo date('M j, Y H:i', strtotime($request_details['date_intervention_reelle'])); ?>
                       </div>
                       <?php endif; ?>
                       <?php if($request_details['cout_final']): ?>
                       <div class="detail-row">
                           <strong>Final Cost:</strong> €<?php echo number_format($request_details['cout_final'], 2); ?>
                       </div>
                       <?php endif; ?>
                   </div>
                   <?php endif; ?>
               </div>
               
               <?php if($request_details['notes_syndic']): ?>
               <div class="notes-section">
                   <h4>Syndic Notes</h4>
                   <p><?php echo nl2br(htmlspecialchars($request_details['notes_syndic'])); ?></p>
               </div>
               <?php endif; ?>
               
               <?php if($request_details['rapport_intervention']): ?>
               <div class="notes-section">
                   <h4>Work Report</h4>
                   <p><?php echo nl2br(htmlspecialchars($request_details['rapport_intervention'])); ?></p>
               </div>
               <?php endif; ?>
           </div>
           <div class="modal-footer">
               <button class="btn btn-secondary" onclick="closeModal()">Close</button>
           </div>
       </div>
   </div>
   <?php endif; ?>

   <!-- Assign Maintenance Modal -->
   <div id="assignModal" class="modal" style="display: none;">
       <div class="modal-content">
           <div class="modal-header">
               <h3>Assign Maintenance</h3>
               <span class="close" onclick="closeAssignModal()">&times;</span>
           </div>
           <form method="POST" id="assignForm">
               <input type="hidden" name="action" value="assign_maintenance">
               <input type="hidden" name="request_id" id="assign_request_id">
               
               <div class="form-group">
                   <label for="prestataire_nom">Service Provider Name *</label>
                   <input type="text" id="prestataire_nom" name="prestataire_nom" required>
               </div>
               
               <div class="form-row">
                   <div class="form-group">
                       <label for="prestataire_telephone">Phone</label>
                       <input type="tel" id="prestataire_telephone" name="prestataire_telephone">
                   </div>
                   <div class="form-group">
                       <label for="prestataire_email">Email</label>
                       <input type="email" id="prestataire_email" name="prestataire_email">
                   </div>
               </div>
               
               <div class="form-group">
                   <label for="date_intervention">Scheduled Date/Time</label>
                   <input type="datetime-local" id="date_intervention" name="date_intervention">
               </div>
               
               <div class="modal-actions">
                   <button type="button" class="btn btn-secondary" onclick="closeAssignModal()">Cancel</button>
                   <button type="submit" class="btn btn-primary">Assign Maintenance</button>
               </div>
           </form>
       </div>
   </div>

   <!-- Complete Maintenance Modal -->
   <div id="completeModal" class="modal" style="display: none;">
       <div class="modal-content">
           <div class="modal-header">
               <h3>Complete Maintenance</h3>
               <span class="close" onclick="closeCompleteModal()">&times;</span>
           </div>
           <form method="POST" id="completeForm">
               <input type="hidden" name="action" value="complete_maintenance">
               <input type="hidden" name="request_id" id="complete_request_id">
               
               <div class="form-group">
                   <label for="cout_final">Final Cost (€)</label>
                   <input type="number" step="0.01" id="cout_final" name="cout_final" required>
               </div>
               
               <div class="form-group">
                   <label for="rapport">Work Report *</label>
                   <textarea id="rapport" name="rapport" rows="4" required placeholder="Describe the work completed..."></textarea>
               </div>
               
               <div class="modal-actions">
                   <button type="button" class="btn btn-secondary" onclick="closeCompleteModal()">Cancel</button>
                   <button type="submit" class="btn btn-success">Mark as Completed</button>
               </div>
           </form>
       </div>
   </div>

   <style>
       .filters {
           background: #f8f9fa;
           padding: 1.5rem;
           border-radius: 8px;
           margin-bottom: 2rem;
       }
       
       .filter-form {
           display: flex;
           gap: 1rem;
           align-items: end;
           flex-wrap: wrap;
       }
       
       .filter-group {
           display: flex;
           flex-direction: column;
           gap: 0.5rem;
       }
       
       .filter-group label {
           font-weight: 500;
           color: #495057;
       }
       
       .maintenance-grid {
           display: grid;
           grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
           gap: 1.5rem;
       }
       
       .maintenance-card {
           background: white;
           border-radius: 10px;
           box-shadow: 0 4px 6px rgba(0,0,0,0.1);
           overflow: hidden;
           transition: transform 0.2s, box-shadow 0.2s;
           border-left: 4px solid #6c757d;
       }
       
       .maintenance-card.priority-urgente {
           border-left-color: #dc3545;
       }
       
       .maintenance-card.priority-haute {
           border-left-color: #fd7e14;
       }
       
       .maintenance-card.priority-normale {
           border-left-color: #28a745;
       }
       
       .maintenance-card.priority-basse {
           border-left-color: #6c757d;
       }
       
       .maintenance-card:hover {
           transform: translateY(-2px);
           box-shadow: 0 8px 15px rgba(0,0,0,0.15);
       }
       
       .maintenance-header {
           background: #f8f9fa;
           padding: 1rem;
           border-bottom: 1px solid #e9ecef;
           display: flex;
           justify-content: space-between;
           align-items: flex-start;
       }
       
       .request-info h3 {
           margin: 0 0 0.5rem 0;
           font-size: 1.1rem;
           color: #495057;
       }
       
       .request-meta {
           display: flex;
           gap: 0.5rem;
           flex-wrap: wrap;
       }
       
       .request-date {
           color: #6c757d;
           font-size: 0.9rem;
           white-space: nowrap;
       }
       
       .maintenance-body {
           padding: 1rem;
       }
       
       .description {
           margin-bottom: 1rem;
       }
       
       .description p {
           margin: 0;
           color: #495057;
           line-height: 1.5;
       }
       
       .request-details {
           display: flex;
           flex-direction: column;
           gap: 0.5rem;
           margin-bottom: 1rem;
       }
       
       .detail-item {
           display: flex;
           align-items: center;
           gap: 0.5rem;
           font-size: 0.9rem;
           color: #6c757d;
       }
       
       .detail-item i {
           width: 16px;
           color: #495057;
       }
       
       .assignment-info {
           background: #e3f2fd;
           padding: 0.75rem;
           border-radius: 4px;
           font-size: 0.9rem;
           margin-bottom: 1rem;
       }
       
       .maintenance-actions {
           padding: 1rem;
           border-top: 1px solid #e9ecef;
           display: flex;
           gap: 0.5rem;
           flex-wrap: wrap;
       }
       
       .priority-badge, .status-badge {
           padding: 0.25rem 0.5rem;
           border-radius: 12px;
           font-size: 0.75rem;
           font-weight: 500;
           text-transform: uppercase;
       }
       
       .priority-urgente { background: #f8d7da; color: #721c24; }
       .priority-haute { background: #fff3cd; color: #856404; }
       .priority-normale { background: #d4edda; color: #155724; }
       .priority-basse { background: #e2e3e5; color: #383d41; }
       
       .status-nouvelle { background: #cce5ff; color: #004085; }
       .status-vue { background: #e2e3e5; color: #383d41; }
       .status-en_cours { background: #fff3cd; color: #856404; }
       .status-terminee { background: #d4edda; color: #155724; }
       .status-annulee { background: #f8d7da; color: #721c24; }
       
       /* Modal Styles */
       .modal {
           position: fixed;
           z-index: 1000;
           left: 0;
           top: 0;
           width: 100%;
           height: 100%;
           background-color: rgba(0,0,0,0.5);
           overflow-y: auto;
       }
       
       .modal-content {
           background-color: white;
           margin: 5% auto;
           border-radius: 8px;
           width: 90%;
           max-width: 600px;
           box-shadow: 0 4px 6px rgba(0,0,0,0.1);
           max-height: 90vh;
           overflow-y: auto;
       }
       
       .modal-large {
           max-width: 900px;
       }
       
       .modal-header {
           padding: 1.5rem;
           border-bottom: 1px solid #dee2e6;
           display: flex;
           justify-content: space-between;
           align-items: center;
           background: #f8f9fa;
       }
       
       .modal-header h3 {
           margin: 0;
           color: #495057;
       }
       
       .modal-body {
           padding: 1.5rem;
       }
       
       .modal-footer {
           padding: 1rem 1.5rem;
           border-top: 1px solid #dee2e6;
           display: flex;
           justify-content: flex-end;
           gap: 1rem;
       }
       
       .close {
           font-size: 1.5rem;
           cursor: pointer;
           color: #6c757d;
       }
       
       .close:hover {
           color: #343a40;
       }
       
       .request-detail-grid {
           display: grid;
           grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
           gap: 2rem;
           margin-bottom: 2rem;
       }
       
       .detail-section {
           background: #f8f9fa;
           padding: 1.5rem;
           border-radius: 8px;
       }
       
       .detail-section h4 {
           color: #495057;
           margin-bottom: 1rem;
           font-size: 1.1rem;
           border-bottom: 2px solid #e9ecef;
           padding-bottom: 0.5rem;
       }
       
       .detail-row {
           margin-bottom: 1rem;
           line-height: 1.5;
       }
       
       .detail-row:last-child {
           margin-bottom: 0;
       }
       
       .notes-section {
           background: #e3f2fd;
           padding: 1.5rem;
           border-radius: 8px;
           margin-bottom: 1rem;
       }
       
       .notes-section h4 {
           color: #1976d2;
           margin-bottom: 1rem;
           font-size: 1.1rem;
       }
       
       .notes-section p {
           margin: 0;
           line-height: 1.6;
           color: #424242;
       }
       
       .form-row {
           display: grid;
           grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
           gap: 1rem;
           margin-bottom: 1rem;
       }
       
       .modal form {
           padding: 1.5rem;
       }
       
       .modal-actions {
           display: flex;
           gap: 1rem;
           justify-content: flex-end;
           margin-top: 2rem;
       }
       
       @media (max-width: 768px) {
           .maintenance-grid {
               grid-template-columns: 1fr;
           }
           
           .filter-form {
               flex-direction: column;
               align-items: stretch;
           }
           
           .request-detail-grid {
               grid-template-columns: 1fr;
           }
           
           .maintenance-header {
               flex-direction: column;
               align-items: flex-start;
               gap: 1rem;
           }
           
           .maintenance-actions {
               flex-direction: column;
           }
       }
   </style>

   <script>
       function viewRequest(requestId) {
           window.location.href = 'maintenance.php?view=' + requestId;
       }
       
       function closeModal() {
           window.location.href = 'maintenance.php';
       }
       
       function updateStatus(requestId, newStatus) {
           if (confirm('Update maintenance status?')) {
               const form = document.createElement('form');
               form.method = 'POST';
               form.innerHTML = `
                   <input type="hidden" name="action" value="update_status">
                   <input type="hidden" name="request_id" value="${requestId}">
                   <input type="hidden" name="new_status" value="${newStatus}">
                   <input type="hidden" name="notes" value="">
               `;
               document.body.appendChild(form);
               form.submit();
           }
       }
       
       function showAssignModal(requestId) {
           document.getElementById('assign_request_id').value = requestId;
           document.getElementById('assignModal').style.display = 'block';
       }
       
       function closeAssignModal() {
           document.getElementById('assignModal').style.display = 'none';
       }
       
       function showCompleteModal(requestId) {
           document.getElementById('complete_request_id').value = requestId;
           document.getElementById('completeModal').style.display = 'block';
       }
       
       function closeCompleteModal() {
           document.getElementById('completeModal').style.display = 'none';
       }
       
       // Close modals when clicking outside
       window.onclick = function(event) {
           const assignModal = document.getElementById('assignModal');
           const completeModal = document.getElementById('completeModal');
           const viewModal = document.getElementById('viewModal');
           
           if (event.target == assignModal) {
               assignModal.style.display = 'none';
           }
           if (event.target == completeModal) {
               completeModal.style.display = 'none';
           }
           if (event.target == viewModal) {
               window.location.href = 'maintenance.php';
           }
       }
       
       // Auto-refresh page every 2 minutes to show new requests
       setInterval(() => {
           if (!document.querySelector('.modal[style*="block"]')) {
               location.reload();
           }
       }, 120000);
   </script>

   <script src="<?php echo PUBLIC_URL; ?>assets/js/dashboard.js"></script>
</body>
</html>