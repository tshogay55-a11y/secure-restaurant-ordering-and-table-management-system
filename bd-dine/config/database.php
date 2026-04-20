<?php
/**
 * BD Dine Restaurant - Database Configuration
 * Secure database connection with prepared statements
 */

// Prevent direct access
if (!defined('BD_DINE_SECURE')) {
    die('Direct access not permitted');
}

class Database {
    private $host = 'localhost';
    private $db_name = 'bd_dine_restaurant';
    private $username = 'root';  // Change in production
    private $password = '';      // Change in production
    private $charset = 'utf8mb4';
    private $conn;
    
    /**
     * Get database connection
     * @return PDO|null
     */
    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            return null;
        }
        
        return $this->conn;
    }
    
    /**
     * Close database connection
     */
    public function closeConnection() {
        $this->conn = null;
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        if ($this->conn) {
            return $this->conn->beginTransaction();
        }
        return false;
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        if ($this->conn) {
            return $this->conn->commit();
        }
        return false;
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        if ($this->conn) {
            return $this->conn->rollBack();
        }
        return false;
    }
}

/**
 * Configuration Constants
 */

// Security
define('ENCRYPTION_KEY', 'your-256-bit-encryption-key-here-change-in-production');
define('SESSION_LIFETIME', 3600); // 1 hour
define('TWO_FACTOR_EXPIRY', 300); // 5 minutes
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Application
define('SITE_NAME', 'BD Dine Restaurant');
define('SITE_URL', 'http://localhost/secure-restaurant-ordering-and-table-management-system/bd-dine/');
define('CURRENCY', 'AUD');
define('TIMEZONE', 'Australia/Canberra');

// Email settings (for 2FA and notifications)
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'noreply@bddine.com.au');
define('SMTP_PASSWORD', 'your-smtp-password');
define('FROM_EMAIL', 'noreply@bddine.com.au');
define('FROM_NAME', 'BD Dine Restaurant');

// SMS API settings (for 2FA)
define('SMS_API_KEY', 'your-sms-api-key');
define('SMS_API_URL', 'https://api.sms-provider.com/send');
define('SMS_FROM_NUMBER', '+61412345678');

// Payment Gateway (Stripe example - change as needed)
define('STRIPE_PUBLIC_KEY', 'pk_test_your_stripe_public_key');
define('STRIPE_SECRET_KEY', 'sk_test_your_stripe_secret_key');
define('PAYMENT_CURRENCY', 'AUD');

// File paths
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/uploads/');
define('LOG_PATH', BASE_PATH . '/logs/');

// Set timezone
date_default_timezone_set(TIMEZONE);

// Error reporting (disable in production)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', LOG_PATH . 'php_errors.log');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Enable in production with HTTPS
ini_set('session.cookie_samesite', 'Strict');

