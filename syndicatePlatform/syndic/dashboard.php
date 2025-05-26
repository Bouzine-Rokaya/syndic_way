<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../utils/helpers.php';

// Check if user is logged in and is syndic
checkAuthentication();
checkRole(ROLE_SYNDIC);

$page_title = "Syndic Dashboard";

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get syndic information
$syndic_info = null;
$subscription_info = null;
try {
    // Get syndic details
    $stmt = $db->prepare("SELECT s.*, ss.end_date, ss.payment_status, sub.name as subscription_name, sub.max_residents, sub.max_apartments
                          FROM syndic s 
                          LEFT JOIN syndic_subscriptions ss ON s.subscription_id = ss.id
                          LEFT JOIN subscriptions sub ON ss.subscription_id = sub.id_subscription
                          WHERE s.id_admin_syndic = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $syndic_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($syndic_info) {
        // Get statistics
        $syndic_id = $syndic_info['id_syndic'];
        
        // Get total residents
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM residents WHERE syndic_id = ? AND is_active = 1");
        $stmt->execute([$syndic_id]);
        $total_residents = $stmt->fetch()['count'];
        
        // Get total apartments
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM appartement WHERE syndic_id = ?");
        $stmt->execute([$syndic_id]);
        $total_apartments = $stmt->fetch()['count'];
        
        // Get occupied apartments
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM appartement WHERE syndic_id = ? AND is_occupied = 1");
        $stmt->execute([$syndic_id]);
        $occupied_apartments = $stmt->fetch()['count'];
        
        // Get pending maintenance requests
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM demandes_maintenance WHERE syndic_id = ? AND statut IN ('nouvelle', 'en_cours')");
        $stmt->execute([$syndic_id]);
        $pending_maintenance = $stmt->fetch()['count'];
        
        // Get unpaid invoices
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM factures WHERE syndic_id = ? AND statut_paiement = 'impayee'");
        $stmt->execute([$syndic_id]);
        $unpaid_invoices = $stmt->fetch()['count'];
        
        // Get recent maintenance requests
        $stmt = $db->prepare("SELECT dm.*, a.numero_appartement, u.nom_complet as resident_name
                              FROM demandes_maintenance dm
                              LEFT JOIN appartement a ON dm.apartment_id = a.id_appartement
                              LEFT JOIN residents r ON dm.resident_id = r.id_resident
                              LEFT JOIN utilisateur u ON r.user_id = u.id_utilisateur
                              WHERE dm.syndic_id = ?
                              ORDER BY dm.date_demande DESC
                              LIMIT 5");
        $stmt->execute([$syndic_id]);
        $recent_maintenance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    error_log("Error loading syndic dashboard: " . $e->getMessage());
    $total_residents = 0;
    $total_apartments = 0;
    $occupied_apartments = 0;
    $pending_maintenance = 0;
    $unpaid_invoices = 0;
    $recent_maintenance = [];
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
            <?php if($syndic_info): ?>
            <small style="color: #bdc3c7;"><?php echo htmlspecialchars($syndic_info['nom_syndic']); ?></small>
            <?php endif; ?>
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
                    <li class="active">
                        <a href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="residents.php">
                            <i class="fas fa-users"></i> Residents
                        </a>
                    </li>
                    <li>
                        <a href="apartments.php">
                            <i class="fas fa-home"></i> Apartments
                        </a>
                    </li>
                    <li>
                        <a href="maintenance.php">
                            <i class="fas fa-tools"></i> Maintenance
                        </a>
                    </li>
                    <li>
                        <a href="invoices.php">
                            <i class="fas fa-file-invoice-dollar"></i> Invoices
                        </a>
                    </li>
                    <li>
                        <a href="announcements.php">
                            <i class="fas fa-bullhorn"></i> Announcements
                        </a>
                    </li>
                    <li>
                        <a href="reports.php">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                    </li>
                    <li>
                        <a href="profile.php">
                            <i class="fas fa-user-cog"></i> Profile
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <h1>Welcome to Your Dashboard</h1>
                <p>Manage your building operations efficiently</p>
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

            <?php if($syndic_info): ?>
            <!-- Subscription Status -->
            <?php if($syndic_info['subscription_name']): ?>
            <div class="content-section" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; margin-bottom: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3><i class="fas fa-crown"></i> <?php echo htmlspecialchars($syndic_info['subscription_name']); ?></h3>
                        <p style="margin: 0; opacity: 0.9;">
                            Valid until: <?php echo date('F j, Y', strtotime($syndic_info['end_date'])); ?> 
                            | Status: <strong><?php echo ucfirst($syndic_info['payment_status']); ?></strong>
                        </p>
                    </div>
                    <div style="text-align: right;">
                        <small>Limits: <?php echo $syndic_info['max_residents']; ?> residents, <?php echo $syndic_info['max_apartments']; ?> apartments</small>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #3498db;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Residents</h3>
                        <div class="stat-number"><?php echo $total_residents; ?></div>
                        <?php if($syndic_info['max_residents']): ?>
                        <small class="text-muted">of <?php echo $syndic_info['max_residents']; ?> allowed</small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: #27ae60;">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Apartments</h3>
                        <div class="stat-number"><?php echo $total_apartments; ?></div>
                        <small class="text-muted"><?php echo $occupied_apartments; ?> occupied</small>
                    </div>
                </div>

                <div class="stat-card <?php echo $pending_maintenance > 0 ? 'pending' : ''; ?>">
                    <div class="stat-icon" style="background: <?php echo $pending_maintenance > 0 ? '#f39c12' : '#95a5a6'; ?>;">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Pending Maintenance</h3>
                        <div class="stat-number"><?php echo $pending_maintenance; ?></div>
                        <small class="text-muted">requests waiting</small>
                    </div>
                </div>

                <div class="stat-card <?php echo $unpaid_invoices > 0 ? 'pending' : ''; ?>">
                    <div class="stat-icon" style="background: <?php echo $unpaid_invoices > 0 ? '#e74c3c' : '#95a5a6'; ?>;">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Unpaid Invoices</h3>
                        <div class="stat-number"><?php echo $unpaid_invoices; ?></div>
                        <small class="text-muted">need attention</small>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="content-section">
                <h2>Quick Actions</h2>
                <div class="quick-actions">
                    <a href="residents.php?action=add" class="action-card">
                        <i class="fas fa-user-plus"></i>
                        <h3>Add Resident</h3>
                        <p>Register a new resident to your building</p>
                    </a>
                    
                    <a href="apartments.php?action=add" class="action-card">
                        <i class="fas fa-plus-square"></i>
                        <h3>Add Apartment</h3>
                        <p>Add a new apartment to your building</p>
                    </a>
                    
                    <a href="maintenance.php" class="action-card">
                        <i class="fas fa-wrench"></i>
                        <h3>View Maintenance</h3>
                        <p>Check and manage maintenance requests</p>
                    </a>
                    
                    <a href="announcements.php?action=add" class="action-card">
                        <i class="fas fa-megaphone"></i>
                        <h3>Send Announcement</h3>
                        <p>Notify all residents about important updates</p>
                    </a>
                </div>
            </div>

            <!-- Recent Maintenance Requests -->
            <div class="content-section">
                <h2>Recent Maintenance Requests</h2>
                <?php if (!empty($recent_maintenance)): ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Request</th>
                                <th>Resident</th>
                                <th>Apartment</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_maintenance as $request): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($request['titre'] ?? 'Maintenance Request'); ?></strong><br>
                                    <small><?php echo htmlspecialchars(substr($request['description'], 0, 50)); ?>...</small>
                                </td>
                                <td><?php echo htmlspecialchars($request['resident_name'] ?? 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars($request['numero_appartement'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="priority-badge priority-<?php echo $request['priorite']; ?>">
                                        <?php echo ucfirst($request['priorite']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $request['statut']; ?>">
                                        <?php echo ucfirst($request['statut']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($request['date_demande'])); ?></td>
                                <td>
                                    <a href="maintenance.php?view=<?php echo $request['id_demande']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="maintenance.php" class="btn btn-secondary">View All Maintenance Requests</a>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-tools"></i>
                    <h3>No maintenance requests</h3>
                    <p>All maintenance requests will appear here.</p>
                </div>
                <?php endif; ?>
            </div>

            <?php else: ?>
            <!-- No Syndic Info Found -->
            <div class="content-section">
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Syndic Information Not Found</h3>
                    <p>There seems to be an issue with your syndic account setup. Please contact the administrator.</p>
                    <a href="<?php echo PUBLIC_URL; ?>logout.php" class="btn btn-secondary">Logout</a>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <style>
        .priority-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .priority-basse { background: #d1ecf1; color: #0c5460; }
        .priority-normale { background: #d4edda; color: #155724; }
        .priority-haute { background: #fff3cd; color: #856404; }
        .priority-urgente { background: #f8d7da; color: #721c24; }
        
        .status-nouvelle { background: #cce5ff; color: #004085; }
        .status-vue { background: #e2e3e5; color: #383d41; }
        .status-en_cours { background: #fff3cd; color: #856404; }
        .status-terminee { background: #d4edda; color: #155724; }
        .status-annulee { background: #f8d7da; color: #721c24; }
    </style>

    <script src="<?php echo PUBLIC_URL; ?>assets/js/dashboard.js"></script>
</body>
</html>