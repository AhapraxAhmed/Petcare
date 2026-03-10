<?php
// Authentication middleware
require_once '../../config/config.php';
require_once '../../includes/functions.php';

function requireAuth($required_role = null) {
    if (!is_logged_in()) {
        redirect('../auth/login.php');
    }
    
    if ($required_role && !has_role($required_role)) {
        redirect('../auth/login.php');
    }
}

function requireRole($role) {
    requireAuth();
    
    if (!has_role($role)) {
        // Redirect to appropriate dashboard based on user's actual role
        $user = a();
        redirect('../dashboard/' . $user['role'] . '/index.php');
    }
}

// Check session timeout
function checkSessionTimeout() {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_destroy();
        redirect('../auth/login.php?timeout=1');
    }
    $_SESSION['last_activity'] = time();
}

// CSRF Protection
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generate_token();
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
