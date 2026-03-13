<?php
$conn = new mysqli('localhost', 'root', '', 'furshield');
$res = $conn->query("SELECT email FROM users WHERE role = 'pet_owner' LIMIT 1");
if ($row = $res->fetch_assoc()) {
    echo "Found: " . $row['email'] . "\n";
    $conn->query("UPDATE users SET password = '" . password_hash('password123', PASSWORD_BCRYPT) . "' WHERE email = '" . $row['email'] . "'");
    echo "Set password to password123\n";
} else {
    $email = 'testowner@example.com';
    $pwd = password_hash('password123', PASSWORD_BCRYPT);
    $conn->query("INSERT INTO users (name, email, password, role) VALUES ('Test Owner', '$email', '$pwd', 'pet_owner')");
    echo "Created: $email / password123\n";
}
