<?php
require_once "config/config.php";
require_once "config/database.php";

$db = Database::getInstance();
$conn = $db->getConnection();

$res = $conn->query("SELECT name, image FROM pets");
$output = "";
while($row = $res->fetch_assoc()) {
    $output .= "PET: " . $row['name'] . " | IMAGE: " . var_export($row['image'], true) . "\n";
}
file_put_contents("pet_debug_output.txt", $output);
?>
