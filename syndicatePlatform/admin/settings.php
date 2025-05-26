<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../utils/helpers.php';

checkAuthentication();
checkRole(ROLE_ADMIN);

$page_title = "Settings";
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
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>Navigation</h3>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="subscriptions.php"><i class="fas fa-tags"></i> Subscriptions</a></li>
                    <li><a href="syndic-accounts.php"><i class="fas fa-building"></i> Syndic Accounts</a></li>
                    <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
                    <li><a href="purchases.php"><i class="fas fa-shopping-cart"></i> Purchases</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li class="active"><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="content-header">
                <h1><i class="fas fa-cog"></i> Settings</h1>
                <p>System configuration and settings</p>
            </div>

            <div class="content-section">
                <h2>Coming Soon</h2>
                <p>This page will allow you to configure system settings and preferences.</p>
            </div>
        </main>
    </div>

    <script src="<?php echo BASE_URL; ?>public/assets/js/dashboard.js"></script>
</body>
</html>