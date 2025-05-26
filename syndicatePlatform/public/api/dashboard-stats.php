<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== ROLE_SYNDIC) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    $syndic_id = $_SESSION['syndic_id'];
    
    // Get total residents
    $query = "SELECT COUNT(*) as total FROM residents WHERE syndic_id = :syndic_id AND is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':syndic_id', $syndic_id);
    $stmt->execute();
    $total_residents = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get total apartments
    $query = "SELECT COUNT(*) as total FROM appartement WHERE syndic_id = :syndic_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':syndic_id', $syndic_id);
    $stmt->execute();
    $total_apartments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get pending maintenance requests
    $query = "SELECT COUNT(*) as total FROM demandes_maintenance WHERE syndic_id = :syndic_id AND statut = 'nouvelle'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':syndic_id', $syndic_id);
    $stmt->execute();
    $pending_requests = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get unpaid invoices
    $query = "SELECT COUNT(*) as total FROM factures WHERE syndic_id = :syndic_id AND statut_paiement = 'impayee'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':syndic_id', $syndic_id);
    $stmt->execute();
    $unpaid_invoices = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    echo json_encode([
        'total_residents' => $total_residents,
        'total_apartments' => $total_apartments,
        'pending_requests' => $pending_requests,
        'unpaid_invoices' => $unpaid_invoices
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>