<?php
session_start();

// Include configuration files with correct paths
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../controllers/AuthController.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if user must change password
if (!$_SESSION['must_change_password']) {
    // User doesn't need to change password, redirect to dashboard
    switch ($_SESSION['user_role']) {
        case ROLE_ADMIN:
            header('Location: ' . BASE_URL . 'admin/dashboard.php');
            break;
        case ROLE_SYNDIC:
            header('Location: ' . BASE_URL . 'syndic/dashboard.php');
            break;
        case ROLE_RESIDENT:
            header('Location: ' . BASE_URL . 'resident/dashboard.php');
            break;
    }
    exit();
}

$controller = new AuthController();
$controller->changePassword();
?>