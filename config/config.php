<?php
// FurShield Application Configuration

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'furshield_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application Configuration
define('APP_NAME', 'FurShield');
define('APP_URL', 'http://localhost/furshield');

define('APP_VERSION', '1.0.0');

// File Upload Configuration
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

// Security Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 8);
define('BCRYPT_COST', 12);

// Pagination
define('ITEMS_PER_PAGE', 10);

// Timezone
date_default_timezone_set('Asia/Karachi');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
