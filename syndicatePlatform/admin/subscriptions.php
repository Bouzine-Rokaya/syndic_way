<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../utils/helpers.php';

// Check if user is logged in and is admin
checkAuthentication();
checkRole(ROLE_ADMIN);

$page_title = "Manage Subscriptions";

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $name = sanitizeInput($_POST['name']);
                $description = sanitizeInput($_POST['description']);
                $price = floatval($_POST['price']);
                $duration_months = intval($_POST['duration_months']);
                $max_residents = intval($_POST['max_residents']);
                $max_apartments = intval($_POST['max_apartments']);
                
                $stmt = $db->prepare("INSERT INTO subscriptions (name, description, price, duration_months, max_residents, max_apartments) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$name, $description, $price, $duration_months, $max_residents, $max_apartments])) {
                    $_SESSION['success'] = 'Subscription plan created successfully!';
                } else {
                    $_SESSION['error'] = 'Failed to create subscription plan.';
                }
                break;
                
            case 'update':
                $id = intval($_POST['id']);
                $name = sanitizeInput($_POST['name']);
                $description = sanitizeInput($_POST['description']);
                $price = floatval($_POST['price']);
                $duration_months = intval($_POST['duration_months']);
                $max_residents = intval($_POST['max_residents']);
                $max_apartments = intval($_POST['max_apartments']);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                $stmt = $db->prepare("UPDATE subscriptions SET name=?, description=?, price=?, duration_months=?, max_residents=?, max_apartments=?, is_active=? WHERE id_subscription=?");
                if ($stmt->execute([$name, $description, $price, $duration_months, $max_residents, $max_apartments, $is_active, $id])) {
                    $_SESSION['success'] = 'Subscription plan updated successfully!';
                } else {
                    $_SESSION['error'] = 'Failed to update subscription plan.';
                }
                break;
        }
        header('Location: subscriptions.php');
        exit();
    }
}

// Get all subscriptions
$stmt = $db->query("SELECT * FROM subscriptions ORDER BY price ASC");
$subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get subscription for editing (if edit parameter is set)
$edit_subscription = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $db->prepare("SELECT * FROM subscriptions WHERE id_subscription = ?");
    $stmt->execute([$edit_id]);
    $edit_subscription = $stmt->fetch(PDO::FETCH_ASSOC);
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
            <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-logout">
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
                    <li>
                        <a href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="active">
                        <a href="subscriptions.php">
                            <i class="fas fa-tags"></i> Subscriptions
                        </a>
                    </li>
                    <li>
                        <a href="syndic-accounts.php">
                            <i class="fas fa-building"></i> Syndic Accounts
                        </a>
                    </li>
                    <li>
                        <a href="users.php">
                            <i class="fas fa-users"></i> Users
                        </a>
                    </li>
                    <li>
                        <a href="purchases.php">
                            <i class="fas fa-shopping-cart"></i> Purchases
                        </a>
                    </li>
                    <li>
                        <a href="reports.php">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                    </li>
                    <li>
                        <a href="settings.php">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <h1><i class="fas fa-tags"></i> Manage Subscriptions</h1>
                <p>Create and manage subscription plans</p>
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

            <!-- Add/Edit Subscription Form -->
            <div class="content-section">
                <h2>
                    <?php echo $edit_subscription ? 'Edit Subscription Plan' : 'Add New Subscription Plan'; ?>
                </h2>
                
                <form method="POST" class="subscription-form">
                    <input type="hidden" name="action" value="<?php echo $edit_subscription ? 'update' : 'create'; ?>">
                    <?php if ($edit_subscription): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_subscription['id_subscription']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Plan Name *</label>
                            <input type="text" id="name" name="name" 
                                   value="<?php echo $edit_subscription ? htmlspecialchars($edit_subscription['name']) : ''; ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="price">Monthly Price (€) *</label>
                            <input type="number" id="price" name="price" step="0.01" min="0"
                                   value="<?php echo $edit_subscription ? $edit_subscription['price'] : ''; ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3"><?php echo $edit_subscription ? htmlspecialchars($edit_subscription['description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="duration_months">Duration (Months) *</label>
                            <input type="number" id="duration_months" name="duration_months" min="1"
                                   value="<?php echo $edit_subscription ? $edit_subscription['duration_months'] : '12'; ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="max_residents">Max Residents *</label>
                            <input type="number" id="max_residents" name="max_residents" min="1"
                                   value="<?php echo $edit_subscription ? $edit_subscription['max_residents'] : ''; ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="max_apartments">Max Apartments *</label>
                            <input type="number" id="max_apartments" name="max_apartments" min="1"
                                   value="<?php echo $edit_subscription ? $edit_subscription['max_apartments'] : ''; ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <?php if ($edit_subscription): ?>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_active" <?php echo $edit_subscription['is_active'] ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            Active Plan
                        </label>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-actions">
                        <?php if ($edit_subscription): ?>
                        <a href="subscriptions.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            <?php echo $edit_subscription ? 'Update Plan' : 'Create Plan'; ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Existing Subscriptions -->
            <div class="content-section">
                <h2>Existing Subscription Plans</h2>
                
                <?php if (!empty($subscriptions)): ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Plan Name</th>
                                <th>Price</th>
                                <th>Duration</th>
                                <th>Max Residents</th>
                                <th>Max Apartments</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($subscriptions as $subscription): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($subscription['name']); ?></strong>
                                    <?php if($subscription['description']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($subscription['description']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>€<?php echo number_format($subscription['price'], 2); ?>/month</td>
                                <td><?php echo $subscription['duration_months']; ?> months</td>
                                <td><?php echo number_format($subscription['max_residents']); ?></td>
                                <td><?php echo number_format($subscription['max_apartments']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $subscription['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $subscription['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="subscriptions.php?edit=<?php echo $subscription['id_subscription']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-tags"></i>
                    <h3>No subscription plans</h3>
                    <p>Create your first subscription plan to get started.</p>
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
        
        .subscription-form {
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
        
        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            margin-bottom: 1rem;
        }
        
        .checkbox-label input[type="checkbox"] {
            margin-right: 0.75rem;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>

    <script src="<?php echo BASE_URL; ?>public/assets/js/dashboard.js"></script>
</body>
</html>