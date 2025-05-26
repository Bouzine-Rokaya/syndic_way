<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../utils/helpers.php';
require_once '../utils/Mailer.php';

checkAuthentication();
checkRole(ROLE_ADMIN);

$page_title = "Users Management";

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_user':
                $result = createUser($db, $_POST);
                if ($result['success']) {
                    $_SESSION['success'] = $result['message'];
                } else {
                    $_SESSION['error'] = $result['message'];
                }
                break;
                
            case 'update_user':
                $result = updateUser($db, $_POST);
                if ($result['success']) {
                    $_SESSION['success'] = $result['message'];
                } else {
                    $_SESSION['error'] = $result['message'];
                }
                break;
                
            case 'toggle_status':
                $user_id = intval($_POST['user_id']);
                $is_active = intval($_POST['is_active']);
                $stmt = $db->prepare("UPDATE utilisateur SET is_active = ? WHERE id_utilisateur = ?");
                if ($stmt->execute([$is_active, $user_id])) {
                    $_SESSION['success'] = 'User status updated successfully!';
                } else {
                    $_SESSION['error'] = 'Failed to update user status.';
                }
                break;
                
            case 'reset_password':
                $user_id = intval($_POST['user_id']);
                $result = resetUserPassword($db, $user_id);
                if ($result['success']) {
                    $_SESSION['success'] = $result['message'];
                } else {
                    $_SESSION['error'] = $result['message'];
                }
                break;
                
            case 'delete_user':
                $user_id = intval($_POST['user_id']);
                $result = deleteUser($db, $user_id);
                if ($result['success']) {
                    $_SESSION['success'] = $result['message'];
                } else {
                    $_SESSION['error'] = $result['message'];
                }
                break;
        }
        header('Location: users.php');
        exit();
    }
}

// Get filter parameters
$filter_role = $_GET['role'] ?? 'all';
$filter_status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = ["1=1"];
$params = [];

if ($filter_role !== 'all') {
    $where_conditions[] = "u.role = ?";
    $params[] = $filter_role;
}

if ($filter_status !== 'all') {
    $is_active = ($filter_status === 'active') ? 1 : 0;
    $where_conditions[] = "u.is_active = ?";
    $params[] = $is_active;
}

if (!empty($search)) {
    $where_conditions[] = "(u.nom_complet LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = implode(' AND ', $where_conditions);

// Get all users
$stmt = $db->prepare("SELECT u.*, s.nom_syndic, s.code_syndic
                      FROM utilisateur u
                      LEFT JOIN syndic s ON u.id_utilisateur = s.id_admin_syndic
                      WHERE $where_clause
                      ORDER BY u.date_creation DESC");
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user for editing
$edit_user = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $db->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = ?");
    $stmt->execute([$edit_id]);
    $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get statistics
$stmt = $db->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
    SUM(CASE WHEN role = 'syndic' THEN 1 ELSE 0 END) as syndics,
    SUM(CASE WHEN role = 'resident' THEN 1 ELSE 0 END) as residents,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
    SUM(CASE WHEN must_change_password = 1 THEN 1 ELSE 0 END) as pending_password_change
FROM utilisateur");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Helper functions
function createUser($db, $data) {
    try {
        $name = sanitizeInput($data['name']);
        $email = sanitizeInput($data['email']);
        $role = sanitizeInput($data['role']);
        $phone = sanitizeInput($data['phone']);
        
        if (empty($name) || empty($email) || empty($role)) {
            throw new Exception('Name, email, and role are required');
        }
        
        if (!isValidEmail($email)) {
            throw new Exception('Invalid email address');
        }
        
        if (!in_array($role, ['admin', 'syndic', 'resident'])) {
            throw new Exception('Invalid role selected');
        }
        
        // Check if email already exists
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM utilisateur WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()['count'] > 0) {
            throw new Exception('User with this email already exists');
        }
        
        // Generate temporary password
        $temp_password = generateRandomPassword();
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
        
        // Create user
        $stmt = $db->prepare("INSERT INTO utilisateur (nom_complet, email, mot_de_passe, telephone, role, must_change_password, created_by) 
                              VALUES (?, ?, ?, ?, ?, 1, ?)");
        $stmt->execute([$name, $email, $hashed_password, $phone, $role, $_SESSION['user_id']]);
        
        // Send welcome email
        $mailer = new Mailer();
        $mailer->sendWelcomeEmail($email, $name, $temp_password);
        
        return ['success' => true, 'message' => 'User created successfully! Welcome email sent.'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function updateUser($db, $data) {
    try {
        $user_id = intval($data['user_id']);
        $name = sanitizeInput($data['name']);
        $email = sanitizeInput($data['email']);
        $role = sanitizeInput($data['role']);
        $phone = sanitizeInput($data['phone']);
        
        // Check if email exists for other users
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM utilisateur WHERE email = ? AND id_utilisateur != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()['count'] > 0) {
            throw new Exception('Email already exists for another user');
        }
        
        $stmt = $db->prepare("UPDATE utilisateur SET nom_complet = ?, email = ?, telephone = ?, role = ? WHERE id_utilisateur = ?");
        $stmt->execute([$name, $email, $phone, $role, $user_id]);
        
        return ['success' => true, 'message' => 'User updated successfully!'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function resetUserPassword($db, $user_id) {
    try {
        // Get user info
        $stmt = $db->prepare("SELECT nom_complet, email FROM utilisateur WHERE id_utilisateur = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Generate new password
        $new_password = generateRandomPassword();
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password
        $stmt = $db->prepare("UPDATE utilisateur SET mot_de_passe = ?, must_change_password = 1 WHERE id_utilisateur = ?");
        $stmt->execute([$hashed_password, $user_id]);
        
        // Send email with new password
        $mailer = new Mailer();
        $mailer->sendPasswordResetEmail($user['email'], $user['nom_complet'], $new_password);
        
        return ['success' => true, 'message' => 'Password reset successfully! New password sent to user.'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function deleteUser($db, $user_id) {
    try {
        // Check if user can be deleted (not admin, no dependencies)
        $stmt = $db->prepare("SELECT role FROM utilisateur WHERE id_utilisateur = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        if ($user['role'] === 'admin') {
            throw new Exception('Cannot delete admin users');
        }
        
        // Check for dependencies
        if ($user['role'] === 'syndic') {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM syndic WHERE id_admin_syndic = ?");
            $stmt->execute([$user_id]);
            if ($stmt->fetch()['count'] > 0) {
                throw new Exception('Cannot delete syndic user with active syndicate');
            }
        }
        
        // Soft delete - just deactivate
        $stmt = $db->prepare("UPDATE utilisateur SET is_active = 0, email = CONCAT(email, '_deleted_', NOW()) WHERE id_utilisateur = ?");
        $stmt->execute([$user_id]);
        
        return ['success' => true, 'message' => 'User deleted successfully!'];
        
    } catch (Exception $e) {
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
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-brand">
            <h2><i class="fas fa-shield-alt"></i> Admin Panel</h2>
        </div>
        <div class="nav-user">
            <span><i class="fas fa-user"></i> <?php echo $_SESSION['user_name']; ?></span>
            <a href="<?php echo BASE_URL; ?>public/logout.php" class="btn btn-logout">
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
                    <li><a href="subscriptions.php"><i class="fas fa-tags"></i> Subscriptions</a></li>
                    <li><a href="syndic-accounts.php"><i class="fas fa-building"></i> Syndic Accounts</a></li>
                    <li class="active"><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
                    <li><a href="purchases.php"><i class="fas fa-shopping-cart"></i> Purchases</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="view-emails.php"><i class="fas fa-envelope"></i> Email Debug</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <h1><i class="fas fa-users"></i> Users Management</h1>
                <p>Manage system users and their permissions</p>
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

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Users</h3>
                        <div class="stat-number"><?php echo $stats['total']; ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: #17a2b8;">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Admins</h3>
                        <div class="stat-number"><?php echo $stats['admins']; ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: #28a745;">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Syndics</h3>
                        <div class="stat-number"><?php echo $stats['syndics']; ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: #ffc107;">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Residents</h3>
                        <div class="stat-number"><?php echo $stats['residents']; ?></div>
                    </div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="content-section">
                <div class="filters-section">
                    <form method="GET" class="filter-form">
                        <div class="search-group">
                            <div class="form-group">
                                <input type="text" name="search" placeholder="Search users..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                        
                        <div class="filter-group">
                            <select name="role" onchange="this.form.submit()">
                                <option value="all" <?php echo $filter_role === 'all' ? 'selected' : ''; ?>>All Roles</option>
                                <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>Admins</option>
                                <option value="syndic" <?php echo $filter_role === 'syndic' ? 'selected' : ''; ?>>Syndics</option>
                                <option value="resident" <?php echo $filter_role === 'resident' ? 'selected' : ''; ?>>Residents</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <select name="status" onchange="this.form.submit()">
                                <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <?php if ($filter_role !== 'all' || $filter_status !== 'all' || !empty($search)): ?>
                        <a href="users.php" class="btn btn-secondary">Clear Filters</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Add/Edit User Form -->
            <div class="content-section">
                <h2><?php echo $edit_user ? 'Edit User' : 'Add New User'; ?></h2>
                
                <form method="POST" class="user-form">
                    <input type="hidden" name="action" value="<?php echo $edit_user ? 'update_user' : 'create_user'; ?>">
                    <?php if ($edit_user): ?>
                    <input type="hidden" name="user_id" value="<?php echo $edit_user['id_utilisateur']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" 
                                   value="<?php echo $edit_user ? htmlspecialchars($edit_user['nom_complet']) : ''; ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo $edit_user ? htmlspecialchars($edit_user['email']) : ''; ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="role">Role *</label>
                            <select id="role" name="role" required>
                                <option value="">Select role...</option>
                                <option value="admin" <?php echo ($edit_user && $edit_user['role'] == 'admin') ? 'selected' : ''; ?>>Administrator</option>
                                <option value="syndic" <?php echo ($edit_user && $edit_user['role'] == 'syndic') ? 'selected' : ''; ?>>Syndic</option>
                                <option value="resident" <?php echo ($edit_user && $edit_user['role'] == 'resident') ? 'selected' : ''; ?>>Resident</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo $edit_user ? htmlspecialchars($edit_user['telephone']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <?php if ($edit_user): ?>
                        <a href="users.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            <?php echo $edit_user ? 'Update User' : 'Create User'; ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Users List -->
            <div class="content-section">
                <h2>All Users (<?php echo count($users); ?>)</h2>
                
                <?php if (!empty($users)): ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>User Details</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <strong><?php echo htmlspecialchars($user['nom_complet']); ?></strong>
                                        <br><small><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></small>
                                        <?php if($user['telephone']): ?>
                                        <br><small><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['telephone']); ?></small>
                                        <?php endif; ?>
                                        <?php if($user['nom_syndic']): ?>
                                        <br><small><i class="fas fa-building"></i> <?php echo htmlspecialchars($user['nom_syndic']); ?> (<?php echo $user['code_syndic']; ?>)</small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                    <?php if($user['must_change_password']): ?>
                                    <br><small class="text-warning">Must change password</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($user['last_login']): ?>
                                    <?php echo date('M j, Y H:i', strtotime($user['last_login'])); ?>
                                    <?php else: ?>
                                    <span class="text-muted">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($user['date_creation'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="users.php?edit=<?php echo $user['id_utilisateur']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <?php if($user['id_utilisateur'] != $_SESSION['user_id']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id_utilisateur']; ?>">
                                            <input type="hidden" name="is_active" value="<?php echo $user['is_active'] ? 0 : 1; ?>">
                                            <button type="submit" class="btn btn-sm <?php echo $user['is_active'] ? 'btn-warning' : 'btn-success'; ?>"
                                                    onclick="return confirm('<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?> this user?')">
                                                <i class="fas fa-<?php echo $user['is_active'] ? 'pause' : 'play'; ?>"></i>
                                            </button>
                                        </form>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id_utilisateur']; ?>">
                                            <button type="submit" class="btn btn-sm btn-info"
                                                    onclick="return confirm('Reset password for this user?')">
                                                <i class="fas fa-key"></i>
                                            </button>
                                        </form>
                                        
                                        <?php if($user['role'] !== 'admin'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id_utilisateur']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"
                                                    onclick="return confirm('Delete this user? This action cannot be undone.')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        <?php endif; ?>
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
                    <h3>No users found</h3>
                    <p>No users match your current filters.</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <style>
        .filters-section {
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
        
        .search-group {
            display: flex;
            gap: 0.5rem;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .user-form {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #dee2e6;
        }
        
        .user-info {
            line-height: 1.4;
        }
        
        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .role-admin { background: #dc3545; color: white; }
        .role-syndic { background: #28a745; color: white; }
        .role-resident { background: #ffc107; color: #212529; }
        
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        
        .action-buttons {
            display: flex;
            gap: 0.25rem;
            flex-wrap: wrap;
        }
        
        .text-warning {
            color: #856404;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-group {
                flex-direction: column;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .action-buttons {
                justify-content: center;
            }
        }
    </style>

    <script src="<?php echo BASE_URL; ?>public/assets/js/dashboard.js"></script>
</body>
</html>