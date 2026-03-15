<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if we have code from GitHub
if (!isset($_GET['code'])) {
    // Stage 1: Redirect to GitHub
    $url = "https://github.com/login/oauth/authorize?client_id=" . GITHUB_CLIENT_ID . "&scope=user:email";
    header("Location: " . $url);
    exit;
}

// Stage 2: Exchange code for access token
$code = $_GET['code'];
$token_url = "https://github.com/login/oauth/access_token";
$data = [
    'client_id' => GITHUB_CLIENT_ID,
    'client_secret' => GITHUB_CLIENT_SECRET,
    'code' => $code
];

$options = [
    'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\nAccept: application/json",
        'method'  => 'POST',
        'content' => http_build_query($data),
    ],
];

$context  = stream_context_create($options);
$result = file_get_contents($token_url, false, $context);
$response = json_decode($result, true);

if (isset($response['access_token'])) {
    $access_token = $response['access_token'];

    // Stage 3: Get user info
    $user_url = "https://api.github.com/user";
    $options = [
        'http' => [
            'header' => "Authorization: Bearer $access_token\r\nUser-Agent: FurShield-App",
            'method' => 'GET',
        ],
    ];
    $context = stream_context_create($options);
    $user_info = json_decode(file_get_contents($user_url, false, $context), true);

    // Get email (might be private/separate endpoint)
    $email = $user_info['email'] ?? '';
    if (empty($email)) {
        $email_url = "https://api.github.com/user/emails";
        $context = stream_context_create($options);
        $emails = json_decode(file_get_contents($email_url, false, $context), true);
        foreach ($emails as $e) {
            if ($e['primary'] && $e['verified']) {
                $email = $e['email'];
                break;
            }
        }
    }

    $name = $user_info['name'] ?? $user_info['login'];
    $avatar = $user_info['avatar_url'] ?? null;

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Check if user exists
    $stmt = $conn->prepare("SELECT id, email, role, name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        // User exists, update avatar and log in
        if ($avatar) {
            $update_avatar = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $update_avatar->bind_param("si", $avatar, $user['id']);
            $update_avatar->execute();
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];

        redirect(get_dashboard_url($user['role']));
    } else {
        // Create user
        $role = $_SESSION['oauth_role'] ?? 'owner';
        $dummy_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (name, email, role, password, avatar, created_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
        $stmt->bind_param("sssss", $name, $email, $role, $dummy_password, $avatar);
        
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

            // Clear OAuth session data
            unset($_SESSION['oauth_role']);
            unset($_SESSION['oauth_shelter_name']);

            redirect(get_dashboard_url($role));
        } else {
            die("Error creating account via GitHub.");
        }
    }
} else {
    die("Error getting access token from GitHub.");
}
