<?php
require_once "config/config.php";
require_once "config/database.php";
$db = Database::getInstance();
$conn = $db->getConnection();
$res = $conn->query("SHOW TABLES LIKE 'veterinarians'");
echo "Veterinarians table exists: " . ($res->num_rows > 0 ? "YES" : "NO") . "\n";
?>
