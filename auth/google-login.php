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

        $db = Database::getInstance();
        $conn = $db->getConnection();

        // Check if user exists
        $stmt = $conn->prepare("SELECT id, email, role, name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            // User exists, log them in
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];

            echo json_encode(['success' => true, 'role' => $user['role']]);
        } else {
            // User doesn't exist, we might need to register them
            // For simplicity, let's assume we use the role saved in session from register.php
            $role = $_SESSION['oauth_role'] ?? 'owner';
            
            $stmt = $conn->prepare("INSERT INTO users (name, email, role, password, created_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)");
            $dummy_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $stmt->bind_param("ssss", $name, $email, $role, $dummy_password);
            
            if ($stmt->execute()) {
                $user_id = $conn->insert_id;
                
                // Initialize role-specific tables
                if ($role === 'vet') {
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

                echo json_encode(['success' => true, 'role' => $role]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create user']);
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
