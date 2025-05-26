<?php
session_start();

// Include configuration files with correct paths
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/helpers.php';

// Simple routing
$page = $_GET['page'] ?? 'home';

switch($page) {
    case 'home':
        include __DIR__ . '/../views/landing/index.php';
        break;
    case 'subscriptions':
        include __DIR__ . '/../views/landing/subscriptions.php';
        break;
    case 'purchase':
        require_once __DIR__ . '/../controllers/SubscriptionController.php';
        $controller = new SubscriptionController();
        $controller->purchase();
        break;
    case 'purchase-success':
        require_once __DIR__ . '/../controllers/SubscriptionController.php';
        $controller = new SubscriptionController();
        $controller->purchaseSuccess();
        break;
    default:
        http_response_code(404);
        include __DIR__ . '/../views/errors/404.php';
}
?>