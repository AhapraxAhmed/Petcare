<?php
require_once "config/config.php";
require_once "config/database.php";
$db = Database::getInstance();
$conn = $db->getConnection();
$res = $conn->query("SHOW COLUMNS FROM appointments");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
