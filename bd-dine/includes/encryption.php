<?php
/**
 * BD Dine Restaurant - Encryption Utility
 * AES-256-GCM encryption for sensitive data
 */

if (!defined('BD_DINE_SECURE')) {
    die('Direct access not permitted');
}

class Encryption {
    private $method = 'aes-256-gcm';
    private $key;
    
    public function __construct() {
        $this->key = ENCRYPTION_KEY;
        
        // Ensure key is 32 bytes for AES-256
        if (strlen($this->key) !== 32) {
            $this->key = hash('sha256', $this->key, true);
        }
    }
    
    /**
     * Encrypt data
     * @param string $data Data to encrypt
     * @return string|false Encrypted data or false on failure
     */
    public function encrypt($data) {
        if (empty($data)) {
            return false;
        }
        
        try {
            // Generate random IV
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->method));
            
            // Encrypt the data
            $tag = '';
            $encrypted = openssl_encrypt(
                $data,
                $this->method,
                $this->key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                '',
                16
            );
            
            if ($encrypted === false) {
                return false;
            }
            
            // Combine IV, tag, and encrypted data
            $result = base64_encode($iv . $tag . $encrypted);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Encryption error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Decrypt data
     * @param string $encryptedData Encrypted data
     * @return string|false Decrypted data or false on failure
     */
    public function decrypt($encryptedData) {
        if (empty($encryptedData)) {
            return false;
        }
        
        try {
            // Decode base64
            $decoded = base64_decode($encryptedData);
            
            if ($decoded === false) {
                return false;
            }
            
            // Extract IV length
            $ivLength = openssl_cipher_iv_length($this->method);
            
            // Extract components
            $iv = substr($decoded, 0, $ivLength);
            $tag = substr($decoded, $ivLength, 16);
            $encrypted = substr($decoded, $ivLength + 16);
            
            // Decrypt
            $decrypted = openssl_decrypt(
                $encrypted,
                $this->method,
                $this->key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );
            
            if ($decrypted === false) {
                return false;
            }
            
            return $decrypted;
            
        } catch (Exception $e) {
            error_log("Decryption error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Hash password using bcrypt
     * @param string $password Plain text password
     * @return string Hashed password
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * Verify password against hash
     * @param string $password Plain text password
     * @param string $hash Hashed password
     * @return bool True if password matches
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate secure random token
     * @param int $length Token length
     * @return string Random token
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Generate 6-digit 2FA code
     * @return string 6-digit code
     */
    public static function generate2FACode() {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Generate secure session ID
     * @return string Session ID
     */
    public static function generateSessionId() {
        return hash('sha512', random_bytes(64) . microtime(true) . $_SERVER['REMOTE_ADDR']);
    }
    
    /**
     * Sanitize input data
     * @param string $data Input data
     * @return string Sanitized data
     */
    public static function sanitizeInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
    
    /**
     * Validate email address
     * @param string $email Email address
     * @return bool True if valid
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate phone number (Australian format)
     * @param string $phone Phone number
     * @return bool True if valid
     */
    public static function validatePhone($phone) {
        // Remove spaces and special characters
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Check Australian mobile format
        return preg_match('/^(\+61|0)[4-5][0-9]{8}$/', $phone) === 1;
    }
    
    /**
     * Validate password strength
     * @param string $password Password
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validatePassword($password) {
        $result = ['valid' => true, 'message' => ''];
        
        if (strlen($password) < 8) {
            $result['valid'] = false;
            $result['message'] = 'Password must be at least 8 characters long';
            return $result;
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $result['valid'] = false;
            $result['message'] = 'Password must contain at least one uppercase letter';
            return $result;
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $result['valid'] = false;
            $result['message'] = 'Password must contain at least one lowercase letter';
            return $result;
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $result['valid'] = false;
            $result['message'] = 'Password must contain at least one number';
            return $result;
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $result['valid'] = false;
            $result['message'] = 'Password must contain at least one special character';
            return $result;
        }
        
        return $result;
    }
}

?>
// ===== CART MENU TOGGLE =====
const menuToggle = document.getElementById("cart-menu-toggle");
const dropdown = document.getElementById("cart-menu-dropdown");

if (menuToggle && dropdown) {
    menuToggle.addEventListener("click", () => {
        dropdown.style.display =
            dropdown.style.display === "none" ? "block" : "none";
    });
}