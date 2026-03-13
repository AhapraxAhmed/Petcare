<?php
require_once "config/config.php";
require_once "config/database.php";

$db = Database::getInstance();
$conn = $db->getConnection();

$res = $conn->query("SELECT name, image FROM pets");
while($row = $res->fetch_assoc()) {
    echo "PET: " . $row['name'] . " | IMAGE: " . ($row['image'] === null ? "NULL" : "'" . $row['image'] . "'") . "\n";
}
?>
