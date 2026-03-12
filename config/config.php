<?php
// FurShield Application Configuration

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'furshield'); // Fixed database name to match furshield.sql
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
define('UPLOAD_DIR_PETS', '../../uploads/pets/'); // Consistent path

// Security Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 8);
define('BCRYPT_COST', 12);

// CSRF Protection
define('CSRF_ENABLED', true);

// Pagination
define('ITEMS_PER_PAGE', 10);
define('OPENROUTER_API_KEY', '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');

// Timezone
date_default_timezone_set('Asia/Karachi');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
