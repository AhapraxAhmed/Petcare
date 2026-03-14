<?php
require_once(__DIR__ . "/../../config/config.php");
require "../../includes/functions.php";

if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

if (isset($_GET['vet_id'])) {
    $vet_id = intval($_GET['vet_id']);
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT available_hours FROM veterinarians WHERE vet_id = ?");
    $stmt->bind_param("i", $vet_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $hours = json_decode($row['available_hours'], true);
        echo json_encode(['success' => true, 'available_hours' => $hours]);
    } else {
        echo json_encode(['error' => 'Veterinarian not found']);
    }
    $stmt->close();
} else {
    echo json_encode(['error' => 'Missing vet_id']);
}
?>
