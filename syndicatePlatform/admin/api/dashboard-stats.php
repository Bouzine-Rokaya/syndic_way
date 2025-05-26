<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../utils/helpers.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== ROLE_ADMIN) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    $stats = [];
    
    // Get total syndics
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM syndic");
        $stats['total_syndics'] = $stmt->fetch()['count'] ?? 0;
    } catch (Exception $e) {
        $stats['total_syndics'] = 0;
    }
    
    // Get total users
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM utilisateur WHERE is_active = 1");
        $stats['total_users'] = $stmt->fetch()['count'] ?? 0;
    } catch (Exception $e) {
        $stats['total_users'] = 0;
    }
    
    // Get pending purchases
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM subscription_purchases WHERE is_processed = 0 AND payment_status = 'completed'");
        $stats['pending_purchases'] = $stmt->fetch()['count'] ?? 0;
    } catch (Exception $e) {
        $stats['pending_purchases'] = 0;
    }
    
    // Get total active subscriptions
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM subscriptions WHERE is_active = 1");
        $stats['total_subscriptions'] = $stmt->fetch()['count'] ?? 0;
    } catch (Exception $e) {
        $stats['total_subscriptions'] = 0;
    }
    
    // Get recent activity count
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM subscription_purchases WHERE purchase_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['recent_purchases'] = $stmt->fetch()['count'] ?? 0;
    } catch (Exception $e) {
        $stats['recent_purchases'] = 0;
    }
    
    // Get total revenue (last 30 days)
    try {
        $stmt = $db->query("SELECT SUM(amount_paid) as total FROM subscription_purchases WHERE payment_status = 'completed' AND purchase_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $result = $stmt->fetch();
        $stats['monthly_revenue'] = $result['total'] ?? 0;
    } catch (Exception $e) {
        $stats['monthly_revenue'] = 0;
    }
    
    // Add timestamp
    $stats['updated_at'] = date('Y-m-d H:i:s');
    $stats['success'] = true;
    
    echo json_encode($stats);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error: ' . $e->getMessage(),
        'success' => false
    ]);
}
?>