<?php
session_start();

// Include configuration files with correct paths
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../controllers/AuthController.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    switch($_SESSION['user_role']) {
        case ROLE_ADMIN:
            redirectTo(BASE_URL . 'admin/dashboard.php');
            break;
        case ROLE_SYNDIC:
            redirectTo(BASE_URL . 'syndic/dashboard.php');
            break;
        case ROLE_RESIDENT:
            redirectTo(BASE_URL . 'resident/dashboard.php');
            break;
    }
}

$controller = new AuthController();
$controller->login();
?>