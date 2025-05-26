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
    
    $query = "SELECT dm.*, a.numero_appartement, u.nom_complet as resident_name
              FROM demandes_maintenance dm
              LEFT JOIN appartement a ON dm.apartment_id = a.id_appartement
              LEFT JOIN residents r ON dm.resident_id = r.id_resident
              LEFT JOIN utilisateur u ON r.user_id = u.id_utilisateur
              WHERE dm.syndic_id = :syndic_id
              ORDER BY dm.date_demande DESC
              LIMIT 5";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':syndic_id', $syndic_id);
    $stmt->execute();
    
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($requests);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>