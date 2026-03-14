<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Add available_hours to veterinarians if it doesn't exist
$res = $conn->query("SHOW COLUMNS FROM veterinarians LIKE 'available_hours'");
if ($res->num_rows == 0) {
    $conn->query("ALTER TABLE veterinarians ADD COLUMN available_hours text DEFAULT NULL");
    echo "Added available_hours to veterinarians table.\n";
} else {
    echo "available_hours already exists in veterinarians table.\n";
}

// Create treatments table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS treatments (
    id bigint(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id bigint(20) UNSIGNED NOT NULL,
    vet_id bigint(20) UNSIGNED NOT NULL,
    details text NOT NULL,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP
)");
echo "Ensured treatments table exists.\n";
?>
