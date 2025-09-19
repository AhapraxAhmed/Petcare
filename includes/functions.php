<?php
// Common utility functions for FurShield

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';


// Sanitize input data
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
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
    return bin2hex(random_bytes($length));
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Get current user data
function a() {
    if (!is_logged_in()) {
        return null;
    }
    
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $user = $result->fetch_assoc();
    $db->closeConnection();
    
    return $user;
}

// Check user role
function has_role($required_role) {
    $user = a();
    return $user && $user['role'] === $required_role;
}

// Redirect function
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Format date
function format_date($date, $format = 'M j, Y') {
    return date($format, strtotime($date));
}

// Format currency
function format_currency($amount) {
    return '$' . number_format($amount, 2);
}

// Upload file function
function upload_file($file, $upload_dir = 'uploads/') {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file parameters'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload failed with error code: ' . $file['error']];
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File size exceeds maximum allowed size'];
    }

    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, ALLOWED_IMAGE_TYPES)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }

    $filename = uniqid() . '.' . $file_extension;
    $filepath = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }

    return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
}

// Send notification
function send_notification($user_id, $title, $message, $type = 'general') {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $title, $message, $type);
    $result = $stmt->execute();
    
    $db->closeConnection();
    return $result;
}

// Get user notifications
function get_user_notifications($user_id, $limit = 10) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    $db->closeConnection();
    return $notifications;
}

// Mark notification as read
function mark_notification_read($notification_id) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?");
    $stmt->bind_param("i", $notification_id);
    $result = $stmt->execute();
    
    $db->closeConnection();
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
