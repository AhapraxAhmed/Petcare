<?php
require_once "config/config.php";
require_once "config/database.php";
$db = Database::getInstance();
$conn = $db->getConnection();
$res = $conn->query("SELECT * FROM appointments LIMIT 1");
print_r($res->fetch_assoc());
?>
