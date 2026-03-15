<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if it's a POST request (from GSI client)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id_token = $input['token'] ?? '';

    if (empty($id_token)) {
        echo json_encode(['success' => false, 'message' => 'No token provided']);
        exit;
    }

    // Verify token with Google
    $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $id_token;
    $response = file_get_contents($url);
    $payload = json_decode($response, true);

    if (isset($payload['aud']) && $payload['aud'] === GOOGLE_CLIENT_ID) {
        $email = $payload['email'];
        $name = $payload['name'];
        $google_id = $payload['sub'];
        $avatar = $payload['picture'] ?? null; // Capture Google profile picture

        $db = Database::getInstance();
        $conn = $db->getConnection();

        // Check if user exists
        $stmt = $conn->prepare("SELECT id, email, role, name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            // User exists, update avatar if changed and log them in
            if ($avatar) {
                $update_avatar = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $update_avatar->bind_param("si", $avatar, $user['id']);
                $update_avatar->execute();
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];

            echo json_encode(['success' => true, 'role' => $user['role']]);
        } else {
            // User doesn't exist, we need to register them
            // Pull role from session (saved in save-role-session.php via register.php)
            $role = $_SESSION['oauth_role'] ?? 'owner';
            
            // Password handling: For social login, we use a random dummy password.
            // Big websites often leave this unusable unless a 'reset' is performed.
            $dummy_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (name, email, role, password, avatar, created_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
            $stmt->bind_param("sssss", $name, $email, $role, $dummy_password, $avatar);
            
            if ($stmt->execute()) {
                $user_id = $conn->insert_id;
                
                // Initialize role-specific tables
                if ($role === 'vet') {
                    // Check if veterinarians table exists and initialize
                    $stmt_vet = $conn->prepare("INSERT INTO veterinarians (user_id) VALUES (?)");
                    $stmt_vet->bind_param("i", $user_id);
                    $stmt_vet->execute();
                } elseif ($role === 'shelter') {
                    $shelter_name = $_SESSION['oauth_shelter_name'] ?? ($name . "'s Shelter");
                    $stmt_shelter = $conn->prepare("INSERT INTO shelters (user_id, shelter_name) VALUES (?, ?)");
                    $stmt_shelter->bind_param("is", $user_id, $shelter_name);
                    $stmt_shelter->execute();
                }

                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_role'] = $role;
                $_SESSION['user_name'] = $name;

                // Clear OAuth session data
                unset($_SESSION['oauth_role']);
                unset($_SESSION['oauth_shelter_name']);

                echo json_encode(['success' => true, 'role' => $role]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create user account.']);
            }
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid token']);
    }
    exit;
}

// If GET request, redirect to Google OAuth (or handle differently)
// Since the project uses GSI (Google Identity Services) client-side, 
// a GET request to this page might be a mistake or an old implementation.
header("Location: login.php");
exit;
