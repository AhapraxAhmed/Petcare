<?php
// Common utility functions for FurShield

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';


// Sanitize input data for preventing XSS and injection
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Sanitize for DB (though prepared statements are better)
function sanitize_db($conn, $data) {
    return $conn->real_escape_string($data);
}

// Validate email
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Hash password
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
}

// Verify password
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Generate random token
function generate_token($length = 32) {
    try {
        return bin2hex(random_bytes($length));
    } catch (Exception $e) {
        // Fallback for systems where random_bytes might fail
        return bin2hex(openssl_random_pseudo_bytes($length));
    }
}

// CSRF Protection Functions
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generate_token();
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Get current user data
function getCurrentUser() {
    if (!is_logged_in()) {
        return null;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?"); // Fixed column name 'id' from users table
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $user = $result->fetch_assoc();
    return $user;
}

// Alias for compatibility if needed, but we should update calls
function a() {
    return getCurrentUser();
}

// Check user role
function has_role($required_role) {
    $user = getCurrentUser();
    if (!$user) return false;
    
    $actual_role = $user['role'];
    
    // Allow aliases between database roles and dashboard directory names
    if ($required_role === 'pet_owner' && $actual_role === 'owner') return true;
    if ($required_role === 'veterinarian' && $actual_role === 'vet') return true;
    
    return $actual_role === $required_role;
}

// Redirect function
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Get the correct dashboard path based on user role
 */
function get_dashboard_url($role) {
    $mapping = [
        'owner' => 'pet_owner',
        'vet' => 'veterinarian',
        'shelter' => 'shelter',
        'admin' => 'admin'
    ];
    
    $folder = $mapping[$role] ?? $role;
    return APP_URL . '/dashboard/' . $folder . '/index.php';
}

// Format date
function format_date($date, $format = 'M j, Y') {
    if (!$date) return 'N/A';
    return date($format, strtotime($date));
}

// Format currency
function format_currency($amount) {
    return '$' . number_format($amount, 2);
}

// Secure upload file function
function upload_file($file, $upload_dir = '../../uploads/') {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file parameters'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload failed with error code: ' . $file['error']];
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File size exceeds maximum allowed size'];
    }

    // Check actual MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($mime, $allowed_mimes)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF and WEBP are allowed.'];
    }

    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = bin2hex(random_bytes(16)) . '.' . $file_extension;
    $filepath = rtrim($upload_dir, '/') . '/' . $filename;

    // Ensure directory exists
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }

    return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
}

// Send notification
function send_notification($user_id, $title, $message, $type = 'general') {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $title, $message, $type);
    $result = $stmt->execute();
    
    return $result;
}

// Get user notifications
function get_user_notifications($user_id, $limit = 10) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT id as notification_id, title, message, type, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    return $notifications;
}

// Mark notification as read
function mark_notification_read($notification_id) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt->bind_param("i", $notification_id);
    $result = $stmt->execute();
    
    return $result;
}

// Generate breadcrumb
function generate_breadcrumb($items) {
    $breadcrumb = '<nav class="flex mb-6" aria-label="Breadcrumb">';
    $breadcrumb .= '<ol class="inline-flex items-center space-x-1 md:space-x-3">';
    
    foreach ($items as $index => $item) {
        $isLast = ($index === count($items) - 1);
        
        $breadcrumb .= '<li class="inline-flex items-center">';
        
        if ($index > 0) {
            $breadcrumb .= '<svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>';
        }
        
        if ($isLast) {
            $breadcrumb .= '<span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">' . $item['title'] . '</span>';
        } else {
            $breadcrumb .= '<a href="' . $item['url'] . '" class="ml-1 text-sm font-medium text-blue-600 hover:text-blue-800 md:ml-2">' . $item['title'] . '</a>';
        }
        
        $breadcrumb .= '</li>';
    }
    
    $breadcrumb .= '</ol></nav>';
    return $breadcrumb;
}
?>
