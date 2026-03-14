<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Create shelters table
$conn->query("CREATE TABLE IF NOT EXISTS shelters (
    shelter_id bigint(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id bigint(20) UNSIGNED NOT NULL UNIQUE,
    shelter_name varchar(255) NOT NULL,
    address text,
    description text,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP
)");
echo "Ensured shelters table exists.\n";

// Fix registration script to include vet/shelter initialization
$register_path = 'auth/register.php';
$content = file_get_contents($register_path);

$old_block = 'if ($stmt->execute()) {
                $user_id = $conn->insert_id;
                
                // If special role requirements (e.g. bio, address) were needed, they\'d go here. 
                // Based on furshield.sql, users table already has bio, address, specialization, etc.

                $success_message = \'Account created successfully! You can now log in.\';
                send_notification($user_id, \'Welcome to FurShield!\', \'Thanks for joining. Complete your profile to get started.\');
            }';

$new_block = 'if ($stmt->execute()) {
                $user_id = $conn->insert_id;
                
                // Initialize role-specific tables
                if ($role === \'vet\') {
                    $stmt_vet = $conn->prepare("INSERT INTO veterinarians (user_id) VALUES (?)");
                    $stmt_vet->bind_param("i", $user_id);
                    $stmt_vet->execute();
                } elseif ($role === \'shelter\') {
                    $stmt_shelter = $conn->prepare("INSERT INTO shelters (user_id, shelter_name) VALUES (?, ?)");
                    $stmt_shelter->bind_param("is", $user_id, $shelter_name);
                    $stmt_shelter->execute();
                }

                $success_message = \'Account created successfully! You can now log in.\';
                send_notification($user_id, \'Welcome to FurShield!\', \'Thanks for joining. Complete your profile to get started.\');
            }';

if (strpos($content, $old_block) !== false) {
    file_put_contents($register_path, str_replace($old_block, $new_block, $content));
    echo "Updated register.php\n";
} else {
    echo "Could not find target block in register.php\n";
}

// Fix existing vets/owners
$vets = $conn->query("SELECT id FROM users WHERE role = 'vet' AND id NOT IN (SELECT user_id FROM veterinarians)");
while($v = $vets->fetch_assoc()) {
    $uid = $v['id'];
    $conn->query("INSERT INTO veterinarians (user_id) VALUES ($uid)");
}
echo "Synced missing veterinarian records.\n";

// Auto-verify all vets for the user's testing convenience
$conn->query("UPDATE users SET is_verified = 1 WHERE role = 'vet'");
echo "All veterinarians have been marked as verified for testing purposes.\n";
?>
