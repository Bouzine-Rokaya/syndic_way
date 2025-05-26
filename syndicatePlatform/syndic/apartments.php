<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../utils/helpers.php';

checkAuthentication();
checkRole(ROLE_SYNDIC);

$page_title = "Manage Apartments";

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
            case 'add_apartment':
                $result = addApartment($db, $syndic_id, $_POST);
                if ($result['success']) {
                    $_SESSION['success'] = $result['message'];
                } else {
                    $_SESSION['error'] = $result['message'];
                }
                break;
                
            case 'update_apartment':
                $result = updateApartment($db, $_POST);
                if ($result['success']) {
                    $_SESSION['success'] = $result['message'];
                } else {
                    $_SESSION['error'] = $result['message'];
                }
                break;
                
            case 'delete_apartment':
                $apartment_id = intval($_POST['apartment_id']);
                $result = deleteApartment($db, $apartment_id, $syndic_id);
                if ($result['success']) {
                    $_SESSION['success'] = $result['message'];
                } else {
                    $_SESSION['error'] = $result['message'];
                }
                break;
        }
        header('Location: apartments.php');
        exit();
    }
}

// Get all apartments for this syndic
$stmt = $db->prepare("SELECT a.*, r.id_resident, u.nom_complet as resident_name
                      FROM appartement a
                      LEFT JOIN residents r ON a.resident_id = r.id_resident
                      LEFT JOIN utilisateur u ON r.user_id = u.id_utilisateur
                      WHERE a.syndic_id = ?
                      ORDER BY a.numero_appartement ASC");
$stmt->execute([$syndic_id]);
$apartments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get apartment for editing
$edit_apartment = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $db->prepare("SELECT * FROM appartement WHERE id_appartement = ? AND syndic_id = ?");
    $stmt->execute([$edit_id, $syndic_id]);
    $edit_apartment = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Helper functions
function addApartment($db, $syndic_id, $data) {
    try {
        $numero = sanitizeInput($data['numero_appartement']);
        $etage = intval($data['etage']);
        $surface = !empty($data['surface']) ? floatval($data['surface']) : null;
        $nombre_pieces = !empty($data['nombre_pieces']) ? intval($data['nombre_pieces']) : null;
        $nombre_chambres = !empty($data['nombre_chambres']) ? intval($data['nombre_chambres']) : null;
        $type_appartement = sanitizeInput($data['type_appartement']);
        $balcon = isset($data['balcon']) ? 1 : 0;
        $parking = isset($data['parking']) ? 1 : 0;
        $cave = isset($data['cave']) ? 1 : 0;
       $loyer_mensuel = !empty($data['loyer_mensuel']) ? floatval($data['loyer_mensuel']) : null;
       $charges_mensuelles = !empty($data['charges_mensuelles']) ? floatval($data['charges_mensuelles']) : null;
       
       if (empty($numero)) {
           throw new Exception('Apartment number is required');
       }
       
       // Check if apartment number already exists for this syndic
       $stmt = $db->prepare("SELECT COUNT(*) as count FROM appartement WHERE syndic_id = ? AND numero_appartement = ?");
       $stmt->execute([$syndic_id, $numero]);
       if ($stmt->fetch()['count'] > 0) {
           throw new Exception('Apartment number already exists');
       }
       
       // Create apartment
       $stmt = $db->prepare("INSERT INTO appartement (syndic_id, numero_appartement, etage, surface, nombre_pieces, nombre_chambres, type_appartement, balcon, parking, cave, loyer_mensuel, charges_mensuelles) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
       $stmt->execute([$syndic_id, $numero, $etage, $surface, $nombre_pieces, $nombre_chambres, $type_appartement, $balcon, $parking, $cave, $loyer_mensuel, $charges_mensuelles]);
       
       return ['success' => true, 'message' => 'Apartment added successfully!'];
       
   } catch (Exception $e) {
       return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
   }
}

function updateApartment($db, $data) {
   try {
       $apartment_id = intval($data['apartment_id']);
       $numero = sanitizeInput($data['numero_appartement']);
       $etage = intval($data['etage']);
       $surface = !empty($data['surface']) ? floatval($data['surface']) : null;
       $nombre_pieces = !empty($data['nombre_pieces']) ? intval($data['nombre_pieces']) : null;
       $nombre_chambres = !empty($data['nombre_chambres']) ? intval($data['nombre_chambres']) : null;
       $type_appartement = sanitizeInput($data['type_appartement']);
       $balcon = isset($data['balcon']) ? 1 : 0;
       $parking = isset($data['parking']) ? 1 : 0;
       $cave = isset($data['cave']) ? 1 : 0;
       $loyer_mensuel = !empty($data['loyer_mensuel']) ? floatval($data['loyer_mensuel']) : null;
       $charges_mensuelles = !empty($data['charges_mensuelles']) ? floatval($data['charges_mensuelles']) : null;
       
       $stmt = $db->prepare("UPDATE appartement SET numero_appartement = ?, etage = ?, surface = ?, nombre_pieces = ?, nombre_chambres = ?, type_appartement = ?, balcon = ?, parking = ?, cave = ?, loyer_mensuel = ?, charges_mensuelles = ? WHERE id_appartement = ?");
       $stmt->execute([$numero, $etage, $surface, $nombre_pieces, $nombre_chambres, $type_appartement, $balcon, $parking, $cave, $loyer_mensuel, $charges_mensuelles, $apartment_id]);
       
       return ['success' => true, 'message' => 'Apartment updated successfully!'];
       
   } catch (Exception $e) {
       return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
   }
}

function deleteApartment($db, $apartment_id, $syndic_id) {
   try {
       // Check if apartment is occupied
       $stmt = $db->prepare("SELECT is_occupied FROM appartement WHERE id_appartement = ? AND syndic_id = ?");
       $stmt->execute([$apartment_id, $syndic_id]);
       $apartment = $stmt->fetch();
       
       if (!$apartment) {
           throw new Exception('Apartment not found');
       }
       
       if ($apartment['is_occupied']) {
           throw new Exception('Cannot delete occupied apartment');
       }
       
       $stmt = $db->prepare("DELETE FROM appartement WHERE id_appartement = ? AND syndic_id = ?");
       $stmt->execute([$apartment_id, $syndic_id]);
       
       return ['success' => true, 'message' => 'Apartment deleted successfully!'];
       
   } catch (Exception $e) {
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
                   <li class="active"><a href="apartments.php"><i class="fas fa-home"></i> Apartments</a></li>
                   <li><a href="maintenance.php"><i class="fas fa-tools"></i> Maintenance</a></li>
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
               <h1><i class="fas fa-home"></i> Manage Apartments</h1>
               <p>Add, edit, and manage building apartments (<?php echo count($apartments); ?> total)</p>
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

           <!-- Add/Edit Apartment Form -->
           <div class="content-section">
               <h2><?php echo $edit_apartment ? 'Edit Apartment' : 'Add New Apartment'; ?></h2>
               
               <form method="POST" class="apartment-form">
                   <input type="hidden" name="action" value="<?php echo $edit_apartment ? 'update_apartment' : 'add_apartment'; ?>">
                   <?php if ($edit_apartment): ?>
                   <input type="hidden" name="apartment_id" value="<?php echo $edit_apartment['id_appartement']; ?>">
                   <?php endif; ?>
                   
                   <div class="form-row">
                       <div class="form-group">
                           <label for="numero_appartement">Apartment Number *</label>
                           <input type="text" id="numero_appartement" name="numero_appartement" 
                                  value="<?php echo $edit_apartment ? htmlspecialchars($edit_apartment['numero_appartement']) : ''; ?>" 
                                  required>
                       </div>
                       
                       <div class="form-group">
                           <label for="etage">Floor</label>
                           <input type="number" id="etage" name="etage" 
                                  value="<?php echo $edit_apartment ? $edit_apartment['etage'] : '0'; ?>">
                       </div>
                       
                       <div class="form-group">
                           <label for="type_appartement">Apartment Type</label>
                           <select id="type_appartement" name="type_appartement">
                               <option value="studio" <?php echo ($edit_apartment && $edit_apartment['type_appartement'] == 'studio') ? 'selected' : ''; ?>>Studio</option>
                               <option value="F1" <?php echo ($edit_apartment && $edit_apartment['type_appartement'] == 'F1') ? 'selected' : ''; ?>>F1</option>
                               <option value="F2" <?php echo ($edit_apartment && $edit_apartment['type_appartement'] == 'F2') ? 'selected' : ''; ?>>F2</option>
                               <option value="F3" <?php echo ($edit_apartment && $edit_apartment['type_appartement'] == 'F3') ? 'selected' : ''; ?>>F3</option>
                               <option value="F4" <?php echo ($edit_apartment && $edit_apartment['type_appartement'] == 'F4') ? 'selected' : ''; ?>>F4</option>
                               <option value="F5+" <?php echo ($edit_apartment && $edit_apartment['type_appartement'] == 'F5+') ? 'selected' : ''; ?>>F5+</option>
                           </select>
                       </div>
                   </div>
                   
                   <div class="form-row">
                       <div class="form-group">
                           <label for="surface">Surface (m²)</label>
                           <input type="number" step="0.01" id="surface" name="surface" 
                                  value="<?php echo $edit_apartment ? $edit_apartment['surface'] : ''; ?>">
                       </div>
                       
                       <div class="form-group">
                           <label for="nombre_pieces">Number of Rooms</label>
                           <input type="number" id="nombre_pieces" name="nombre_pieces" 
                                  value="<?php echo $edit_apartment ? $edit_apartment['nombre_pieces'] : ''; ?>">
                       </div>
                       
                       <div class="form-group">
                           <label for="nombre_chambres">Number of Bedrooms</label>
                           <input type="number" id="nombre_chambres" name="nombre_chambres" 
                                  value="<?php echo $edit_apartment ? $edit_apartment['nombre_chambres'] : ''; ?>">
                       </div>
                   </div>
                   
                   <div class="form-row">
                       <div class="form-group">
                           <label for="loyer_mensuel">Monthly Rent (€)</label>
                           <input type="number" step="0.01" id="loyer_mensuel" name="loyer_mensuel" 
                                  value="<?php echo $edit_apartment ? $edit_apartment['loyer_mensuel'] : ''; ?>">
                       </div>
                       
                       <div class="form-group">
                           <label for="charges_mensuelles">Monthly Charges (€)</label>
                           <input type="number" step="0.01" id="charges_mensuelles" name="charges_mensuelles" 
                                  value="<?php echo $edit_apartment ? $edit_apartment['charges_mensuelles'] : ''; ?>">
                       </div>
                   </div>
                   
                   <div class="form-group">
                       <label>Amenities</label>
                       <div class="checkbox-group">
                           <label class="checkbox-label">
                               <input type="checkbox" name="balcon" <?php echo ($edit_apartment && $edit_apartment['balcon']) ? 'checked' : ''; ?>>
                               <span class="checkmark"></span>
                               Balcony
                           </label>
                           <label class="checkbox-label">
                               <input type="checkbox" name="parking" <?php echo ($edit_apartment && $edit_apartment['parking']) ? 'checked' : ''; ?>>
                               <span class="checkmark"></span>
                               Parking
                           </label>
                           <label class="checkbox-label">
                               <input type="checkbox" name="cave" <?php echo ($edit_apartment && $edit_apartment['cave']) ? 'checked' : ''; ?>>
                               <span class="checkmark"></span>
                               Storage/Cellar
                           </label>
                       </div>
                   </div>
                   
                   <div class="form-actions">
                       <?php if ($edit_apartment): ?>
                       <a href="apartments.php" class="btn btn-secondary">Cancel</a>
                       <?php endif; ?>
                       <button type="submit" class="btn btn-primary">
                           <i class="fas fa-save"></i>
                           <?php echo $edit_apartment ? 'Update Apartment' : 'Add Apartment'; ?>
                       </button>
                   </div>
               </form>
           </div>

           <!-- Existing Apartments -->
           <div class="content-section">
               <h2>Current Apartments</h2>
               
               <?php if (!empty($apartments)): ?>
               <div class="apartments-grid">
                   <?php foreach($apartments as $apartment): ?>
                   <div class="apartment-card <?php echo $apartment['is_occupied'] ? 'occupied' : 'vacant'; ?>">
                       <div class="apartment-header">
                           <div class="apartment-number">
                               <i class="fas fa-home"></i>
                               Apt <?php echo htmlspecialchars($apartment['numero_appartement']); ?>
                           </div>
                           <div class="apartment-status">
                               <span class="status-badge status-<?php echo $apartment['is_occupied'] ? 'occupied' : 'vacant'; ?>">
                                   <?php echo $apartment['is_occupied'] ? 'Occupied' : 'Vacant'; ?>
                               </span>
                           </div>
                       </div>
                       
                       <div class="apartment-details">
                           <div class="detail-row">
                               <strong>Type:</strong> <?php echo htmlspecialchars($apartment['type_appartement']); ?>
                               <?php if($apartment['etage']): ?>| Floor <?php echo $apartment['etage']; ?><?php endif; ?>
                           </div>
                           
                           <?php if($apartment['surface']): ?>
                           <div class="detail-row">
                               <strong>Surface:</strong> <?php echo $apartment['surface']; ?> m²
                           </div>
                           <?php endif; ?>
                           
                           <?php if($apartment['nombre_pieces']): ?>
                           <div class="detail-row">
                               <strong>Rooms:</strong> <?php echo $apartment['nombre_pieces']; ?>
                               <?php if($apartment['nombre_chambres']): ?>
                               | Bedrooms: <?php echo $apartment['nombre_chambres']; ?>
                               <?php endif; ?>
                           </div>
                           <?php endif; ?>
                           
                           <?php if($apartment['loyer_mensuel']): ?>
                           <div class="detail-row">
                               <strong>Rent:</strong> €<?php echo number_format($apartment['loyer_mensuel'], 2); ?>/month
                               <?php if($apartment['charges_mensuelles']): ?>
                               + €<?php echo number_format($apartment['charges_mensuelles'], 2); ?> charges
                               <?php endif; ?>
                           </div>
                           <?php endif; ?>
                           
                           <?php if($apartment['resident_name']): ?>
                           <div class="detail-row">
                               <strong>Resident:</strong> <?php echo htmlspecialchars($apartment['resident_name']); ?>
                           </div>
                           <?php endif; ?>
                           
                           <?php if($apartment['balcon'] || $apartment['parking'] || $apartment['cave']): ?>
                           <div class="amenities">
                               <?php if($apartment['balcon']): ?><span class="amenity-badge"><i class="fas fa-leaf"></i> Balcony</span><?php endif; ?>
                               <?php if($apartment['parking']): ?><span class="amenity-badge"><i class="fas fa-car"></i> Parking</span><?php endif; ?>
                               <?php if($apartment['cave']): ?><span class="amenity-badge"><i class="fas fa-archive"></i> Storage</span><?php endif; ?>
                           </div>
                           <?php endif; ?>
                       </div>
                       
                       <div class="apartment-actions">
                           <a href="apartments.php?edit=<?php echo $apartment['id_appartement']; ?>" 
                              class="btn btn-sm btn-primary">
                               <i class="fas fa-edit"></i> Edit
                           </a>
                           
                           <?php if (!$apartment['is_occupied']): ?>
                           <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this apartment?')">
                               <input type="hidden" name="action" value="delete_apartment">
                               <input type="hidden" name="apartment_id" value="<?php echo $apartment['id_appartement']; ?>">
                               <button type="submit" class="btn btn-sm btn-danger">
                                   <i class="fas fa-trash"></i> Delete
                               </button>
                           </form>
                           <?php endif; ?>
                       </div>
                   </div>
                   <?php endforeach; ?>
               </div>
               <?php else: ?>
               <div class="empty-state">
                   <i class="fas fa-home"></i>
                   <h3>No apartments yet</h3>
                   <p>Add your first apartment to start managing your building units.</p>
               </div>
               <?php endif; ?>
           </div>
       </main>
   </div>

   <style>
       .form-row {
           display: grid;
           grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
           gap: 1rem;
           margin-bottom: 1rem;
       }
       
       .apartment-form {
           background: #f8f9fa;
           padding: 2rem;
           border-radius: 8px;
           margin-bottom: 2rem;
       }
       
       .form-actions {
           display: flex;
           gap: 1rem;
           justify-content: flex-end;
           margin-top: 2rem;
           padding-top: 2rem;
           border-top: 1px solid #dee2e6;
       }
       
       .checkbox-group {
           display: flex;
           gap: 2rem;
           flex-wrap: wrap;
       }
       
       .checkbox-label {
           display: flex;
           align-items: center;
           cursor: pointer;
           margin-bottom: 0.5rem;
       }
       
       .checkbox-label input[type="checkbox"] {
           margin-right: 0.75rem;
       }
       
       .apartments-grid {
           display: grid;
           grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
           gap: 1.5rem;
       }
       
       .apartment-card {
           background: white;
           border-radius: 10px;
           box-shadow: 0 4px 6px rgba(0,0,0,0.1);
           overflow: hidden;
           transition: transform 0.2s, box-shadow 0.2s;
           border-left: 4px solid #28a745;
       }
       
       .apartment-card.occupied {
           border-left-color: #007bff;
       }
       
       .apartment-card:hover {
           transform: translateY(-2px);
           box-shadow: 0 8px 15px rgba(0,0,0,0.15);
       }
       
       .apartment-header {
           background: #f8f9fa;
           padding: 1rem;
           display: flex;
           justify-content: space-between;
           align-items: center;
           border-bottom: 1px solid #e9ecef;
       }
       
       .apartment-number {
           font-size: 1.1rem;
           font-weight: bold;
           color: #495057;
       }
       
       .apartment-number i {
           color: #007bff;
           margin-right: 0.5rem;
       }
       
       .apartment-details {
           padding: 1rem;
       }
       
       .detail-row {
           margin-bottom: 0.75rem;
           font-size: 0.9rem;
           color: #495057;
       }
       
       .detail-row:last-child {
           margin-bottom: 0;
       }
       
       .amenities {
           margin-top: 1rem;
           display: flex;
           gap: 0.5rem;
           flex-wrap: wrap;
       }
       
       .amenity-badge {
           background: #e3f2fd;
           color: #1976d2;
           padding: 0.25rem 0.5rem;
           border-radius: 12px;
           font-size: 0.75rem;
           font-weight: 500;
       }
       
       .amenity-badge i {
           margin-right: 0.25rem;
       }
       
       .apartment-actions {
           padding: 1rem;
           border-top: 1px solid #e9ecef;
           display: flex;
           gap: 0.5rem;
       }
       
       .status-occupied {
           background: #cce5ff;
           color: #004085;
       }
       
       .status-vacant {
           background: #d4edda;
           color: #155724;
       }
       
       @media (max-width: 768px) {
           .form-row {
               grid-template-columns: 1fr;
           }
           
           .apartments-grid {
               grid-template-columns: 1fr;
           }
           
           .checkbox-group {
               flex-direction: column;
               gap: 1rem;
           }
           
           .form-actions {
               flex-direction: column;
           }
       }
   </style>

   <script src="<?php echo PUBLIC_URL; ?>assets/js/dashboard.js"></script>
</body>
</html>