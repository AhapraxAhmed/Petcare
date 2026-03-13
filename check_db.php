<?php
require_once "config/config.php";
require_once "config/database.php";

$db = Database::getInstance();
$conn = $db->getConnection();

echo "PETS:\n";
$res = $conn->query("SELECT pet_id, name, image FROM pets");
while($row = $res->fetch_assoc()) {
    print_r($row);
}

echo "PRODUCTS:\n";
$res = $conn->query("SELECT product_id, name, image_url FROM products");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
