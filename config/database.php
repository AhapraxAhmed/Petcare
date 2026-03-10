<?php
// Database configuration for FurShield
require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $conn;

    public function __construct() {
        try {
            $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($this->conn->connect_error) {
                throw new Exception("Connection failed: " . $this->conn->connect_error);
            }
            
            $this->conn->set_charset("utf8mb4");
            
        } catch(Exception $e) {
            // Log error instead of echoing in production
            error_log("Database Connection Error: " . $e->getMessage());
            die("A database connection error occurred. Please try again later.");
        }
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }
    
    public function closeConnection() {
        if ($this->conn) {
            $this->conn->close();
            $this->conn = null;
            self::$instance = null;
        }
    }
}
?>
