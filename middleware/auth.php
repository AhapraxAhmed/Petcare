<?php
// Authentication middleware
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

function requireAuth($required_role = null) {
    if (!is_logged_in()) {
        redirect(APP_URL . '/auth/login.php');
    }
    
    if ($required_role && !has_role($required_role)) {
        redirect(APP_URL . '/auth/login.php');
    }
}

function requireRole($role) {
    requireAuth();
    
    if (!has_role($role)) {
        // Redirect to appropriate dashboard based on user's actual role
        $user = a();
        redirect(get_dashboard_url($user['role']));
    }
}

// Check session timeout
function checkSessionTimeout() {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_destroy();
        redirect(APP_URL . '/auth/login.php?timeout=1');
    }
    $_SESSION['last_activity'] = time();
}
?>
