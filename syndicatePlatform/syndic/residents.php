<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../utils/helpers.php';
require_once '../utils/Mailer.php';

checkAuthentication();
checkRole(ROLE_SYNDIC);

$page_title = "Manage Residents";

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
            case 'add_resident':
                $result = addResident($db, $syndic_id, $_POST);
                if ($result['success']) {
                    $_SESSION['success'] = $result['message'];
                } else {
                    $_SESSION['error'] = $result['message'];
                }
                break;
                
            case 'update_resident':
                $result = updateResident($db, $_POST);
                if ($result['success']) {
                    $_SESSION['success'] = $result['message'];
                } else {
                    $_SESSION['error'] = $result['message'];
                }
                break;
                
            case 'toggle_status':
                $resident_id = intval($_POST['resident_id']);
                $is_active = intval($_POST['is_active']);
                $stmt = $db->prepare("UPDATE residents SET is_active = ? WHERE id_resident = ? AND syndic_id = ?");
                if ($stmt->execute([$is_active, $resident_id, $syndic_id])) {
                    $_SESSION['success'] = 'Resident status updated successfully!';
                } else {
                    $_SESSION['error'] = 'Failed to update resident status.';
                }
                break;
                
            case 'assign_apartment':
                $resident_id = intval($_POST['resident_id']);
                $apartment_id = intval($_POST['apartment_id']);
                $result = assignApartment($db, $resident_id, $apartment_id, $syndic_id);
                if ($result['success']) {
                    $_SESSION['success'] = $result['message'];
                } else {
                    $_SESSION['error'] = $result['message'];
                }
                break;
        }
        header('Location: residents.php');
        exit();
    }
}

// Get all residents for this syndic
$stmt = $db->prepare("SELECT r.*, u.nom_complet, u.email, u.telephone, a.numero_appartement
                      FROM residents r
                      JOIN utilisateur u ON r.user_id = u.id_utilisateur
                      LEFT JOIN appartement a ON r.apartment_id = a.id_appartement
                      WHERE r.syndic_id = ?
                      ORDER BY r.created_at DESC");
$stmt->execute([$syndic_id]);
$residents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available apartments
$stmt = $db->prepare("SELECT * FROM appartement WHERE syndic_id = ? AND (is_occupied = 0 OR is_occupied IS NULL)");
$stmt->execute([$syndic_id]);
$available_apartments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get resident for editing
$edit_resident = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $db->prepare("SELECT r.*, u.nom_complet, u.email, u.telephone
                          FROM residents r
                          JOIN utilisateur u ON r.user_id = u.id_utilisateur
                          WHERE r.id_resident = ? AND r.syndic_id = ?");
    $stmt->execute([$edit_id, $syndic_id]);
    $edit_resident = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Helper functions
function addResident($db, $syndic_id, $data) {
    try {
        $db->beginTransaction();
        
        $name = sanitizeInput($data['name']);
        $email = sanitizeInput($data['email']);
        $phone = sanitizeInput($data['phone']);
        $type_resident = sanitizeInput($data['type_resident']);
        $apartment_id = !empty($data['apartment_id']) ? intval($data['apartment_id']) : null;
        
        if (empty($name) || empty($email)) {
            throw new Exception('Name and email are required');
        }
        
        if (!isValidEmail($email)) {
            throw new Exception('Invalid email address');
        }
        
        // Check if email already exists
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM utilisateur WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()['count'] > 0) {
            throw new Exception('User with this email already exists');
        }
        
        // Generate temporary password
        $temp_password = generateRandomPassword();
        
        // Create user account
        $stmt = $db->prepare("INSERT INTO utilisateur (nom_complet, email, mot_de_passe, telephone, role, must_change_password, created_by) 
                              VALUES (?, ?, ?, ?, 'resident', 1, ?)");
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
        $stmt->execute([$name, $email, $hashed_password, $phone, $_SESSION['user_id']]);
        $user_id = $db->lastInsertId();
        
        // Create resident record
        $stmt = $db->prepare("INSERT INTO residents (user_id, syndic_id, apartment_id, type_resident) 
                              VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $syndic_id, $apartment_id, $type_resident]);
        $resident_id = $db->lastInsertId();
        
        // Update apartment if assigned
        if ($apartment_id) {
            $stmt = $db->prepare("UPDATE appartement SET is_occupied = 1, resident_id = ? WHERE id_appartement = ?");
            $stmt->execute([$resident_id, $apartment_id]);
        }
        
        $db->commit();
        
        // Send welcome email
        $mailer = new Mailer();
        $mailer->sendResidentWelcomeEmail($email, $name, $temp_password);
        
        return ['success' => true, 'message' => 'Resident added successfully! Welcome email sent.'];
        
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function updateResident($db, $data) {
    try {
        $resident_id = intval($data['resident_id']);
        $name = sanitizeInput($data['name']);
        $email = sanitizeInput($data['email']);
        $phone = sanitizeInput($data['phone']);
        $type_resident = sanitizeInput($data['type_resident']);
        
        // Update user
        $stmt = $db->prepare("UPDATE utilisateur u 
                              JOIN residents r ON u.id_utilisateur = r.user_id 
                              SET u.nom_complet = ?, u.email = ?, u.telephone = ? 
                              WHERE r.id_resident = ?");
        $stmt->execute([$name, $email, $phone, $resident_id]);
        
        // Update resident
        $stmt = $db->prepare("UPDATE residents SET type_resident = ? WHERE id_resident = ?");
        $stmt->execute([$type_resident, $resident_id]);
        
        return ['success' => true, 'message' => 'Resident updated successfully!'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function assignApartment($db, $resident_id, $apartment_id, $syndic_id) {
    try {
        $db->beginTransaction();
        
        // Get current apartment of resident
        $stmt = $db->prepare("SELECT apartment_id FROM residents WHERE id_resident = ?");
        $stmt->execute([$resident_id]);
        $current_apt = $stmt->fetch()['apartment_id'];
        
        // Free current apartment if exists
        if ($current_apt) {
            $stmt = $db->prepare("UPDATE appartement SET is_occupied = 0, resident_id = NULL WHERE id_appartement = ?");
            $stmt->execute([$current_apt]);
        }
        
        // Assign new apartment
        $stmt = $db->prepare("UPDATE residents SET apartment_id = ? WHERE id_resident = ?");
        $stmt->execute([$apartment_id, $resident_id]);
        
        $stmt = $db->prepare("UPDATE appartement SET is_occupied = 1, resident_id = ? WHERE id_appartement = ?");
        $stmt->execute([$resident_id, $apartment_id]);
        
        $db->commit();
        
        return ['success' => true, 'message' => 'Apartment assigned successfully!'];
        
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    return substr(str_shuffle($chars), 0, $length);
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
                    <li class="active"><a href="residents.php"><i class="fas fa-users"></i> Residents</a></li>
                    <li><a href="apartments.php"><i class="fas fa-home"></i> Apartments</a></li>
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
                <h1><i class="fas fa-users"></i> Manage Residents</h1>
                <p>Add, edit, and manage building residents (<?php echo count($residents); ?> total)</p>
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

            <!-- Add/Edit Resident Form -->
            <div class="content-section">
                <h2><?php echo $edit_resident ? 'Edit Resident' : 'Add New Resident'; ?></h2>
                
                <form method="POST" class="resident-form">
                    <input type="hidden" name="action" value="<?php echo $edit_resident ? 'update_resident' : 'add_resident'; ?>">
                    <?php if ($edit_resident): ?>
                    <input type="hidden" name="resident_id" value="<?php echo $edit_resident['id_resident']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" 
                                   value="<?php echo $edit_resident ? htmlspecialchars($edit_resident['nom_complet']) : ''; ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo $edit_resident ? htmlspecialchars($edit_resident['email']) : ''; ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo $edit_resident ? htmlspecialchars($edit_resident['telephone']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="type_resident">Resident Type</label>
                            <select id="type_resident" name="type_resident">
                                <option value="locataire" <?php echo ($edit_resident && $edit_resident['type_resident'] == 'locataire') ? 'selected' : ''; ?>>Tenant</option>
                                <option value="proprietaire" <?php echo ($edit_resident && $edit_resident['type_resident'] == 'proprietaire') ? 'selected' : ''; ?>>Owner</option>
                                <option value="gerant" <?php echo ($edit_resident && $edit_resident['type_resident'] == 'gerant') ? 'selected' : ''; ?>>Manager</option>
                            </select>
                        </div>
                        
                        <?php if (!$edit_resident && !empty($available_apartments)): ?>
                        <div class="form-group">
                            <label for="apartment_id">Assign Apartment (Optional)</label>
                            <select id="apartment_id" name="apartment_id">
                                <option value="">No apartment</option>
                                <?php foreach($available_apartments as $apt): ?>
                                <option value="<?php echo $apt['id_appartement']; ?>">
                                    Apartment <?php echo htmlspecialchars($apt['numero_appartement']); ?>
                                    <?php if($apt['etage']): ?> - Floor <?php echo $apt['etage']; ?><?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-actions">
                        <?php if ($edit_resident): ?>
                        <a href="residents.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            <?php echo $edit_resident ? 'Update Resident' : 'Add Resident'; ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Existing Residents -->
            <div class="content-section">
                <h2>Current Residents</h2>
                
                <?php if (!empty($residents)): ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Resident Details</th>
                                <th>Type</th>
                                <th>Apartment</th>
                                <th>Move-in Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($residents as $resident): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($resident['nom_complet']); ?></strong><br>
                                    <small><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($resident['email']); ?></small><br>
                                    <?php if($resident['telephone']): ?>
                                    <small><i class="fas fa-phone"></i> <?php echo htmlspecialchars($resident['telephone']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="type-badge type-<?php echo $resident['type_resident']; ?>">
                                        <?php echo ucfirst($resident['type_resident']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($resident['numero_appartement']): ?>
                                    <strong>Apt <?php echo htmlspecialchars($resident['numero_appartement']); ?></strong>
                                    <?php else: ?>
                                    <span class="text-muted">Not assigned</span>
                                    <br>
                                    <?php if(!empty($available_apartments)): ?>
                                    <button class="btn btn-sm btn-secondary" onclick="showAssignModal(<?php echo $resident['id_resident']; ?>)">
                                        <i class="fas fa-home"></i> Assign
                                    </button>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($resident['date_adhesion'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $resident['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $resident['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="residents.php?edit=<?php echo $resident['id_resident']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="resident_id" value="<?php echo $resident['id_resident']; ?>">
                                            <input type="hidden" name="is_active" value="<?php echo $resident['is_active'] ? 0 : 1; ?>">
                                            <button type="submit" class="btn btn-sm <?php echo $resident['is_active'] ? 'btn-warning' : 'btn-success'; ?>"
                                                    onclick="return confirm('<?php echo $resident['is_active'] ? 'Deactivate' : 'Activate'; ?> this resident?')">
                                                <i class="fas fa-<?php echo $resident['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                <?php echo $resident['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No residents yet</h3>
                    <p>Add your first resident to get started managing your building.</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Apartment Assignment Modal -->
    <div id="assignModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Assign Apartment</h3>
                <span class="close" onclick="closeAssignModal()">&times;</span>
            </div>
            <form method="POST" id="assignForm">
                <input type="hidden" name="action" value="assign_apartment">
                <input type="hidden" name="resident_id" id="assign_resident_id">
                
                <div class="form-group">
                    <label for="assign_apartment_id">Select Apartment:</label>
                    <select id="assign_apartment_id" name="apartment_id" required>
                        <option value="">Choose apartment...</option>
                        <?php foreach($available_apartments as $apt): ?>
                        <option value="<?php echo $apt['id_appartement']; ?>">
                            Apartment <?php echo htmlspecialchars($apt['numero_appartement']); ?>
                            <?php if($apt['etage']): ?> - Floor <?php echo $apt['etage']; ?><?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeAssignModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Apartment</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .resident-form {
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
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .type-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .type-locataire { background: #cce5ff; color: #004085; }
        .type-proprietaire { background: #d4edda; color: #155724; }
        .type-gerant { background: #fff3cd; color: #856404; }
        
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        
        /* Modal Styles */
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
        }
        
        .close {
            font-size: 1.5rem;
            cursor: pointer;
            color: #6c757d;
        }
        
        .close:hover {
            color: #343a40;
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
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>

    <script>
        function showAssignModal(residentId) {
            document.getElementById('assign_resident_id').value = residentId;
            document.getElementById('assignModal').style.display = 'block';
        }
        
        function closeAssignModal() {
            document.getElementById('assignModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('assignModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>

    <script src="<?php echo PUBLIC_URL; ?>assets/js/dashboard.js"></script>
</body>
</html>