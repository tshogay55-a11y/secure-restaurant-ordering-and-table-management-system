<?php
/**
 * BD Dine Restaurant - Authentication Utility
 * User and admin authentication with 2FA
 */

if (!defined('BD_DINE_SECURE')) {
    die('Direct access not permitted');
}

require_once 'encryption.php';
require_once 'security.php';

class Auth {
    private $db;
    private $encryption;
    private $security;
    
    public function __construct($database) {
        $this->db = $database->getConnection();
        $this->encryption = new Encryption();
        $this->security = new Security($database);
    }
    
    /**
     * Register new user
     * @param array $userData User data
     * @return array ['success' => bool, 'message' => string, 'user_id' => int]
     */
    public function registerUser($userData) {
        try {
            // Validate input
            if (!Encryption::validateEmail($userData['email'])) {
                return ['success' => false, 'message' => 'Invalid email address'];
            }
            
            if (!Encryption::validatePhone($userData['phone'])) {
                return ['success' => false, 'message' => 'Invalid phone number (Australian format required)'];
            }
            
            $passwordValidation = Encryption::validatePassword($userData['password']);
            if (!$passwordValidation['valid']) {
                return ['success' => false, 'message' => $passwordValidation['message']];
            }
            
            // Check if email already exists
            $checkQuery = "SELECT user_id FROM users WHERE email = :email";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':email', $userData['email']);
            $checkStmt->execute();
            
            if ($checkStmt->fetch()) {
                return ['success' => false, 'message' => 'Email already registered'];
            }
            
            // Hash password
            $passwordHash = Encryption::hashPassword($userData['password']);
            
            // Generate encryption key for user
            $encryptionKey = Encryption::generateToken(32);
            
            // Insert user
            $query = "INSERT INTO users (email, password_hash, first_name, last_name, phone, encryption_key) 
                      VALUES (:email, :password_hash, :first_name, :last_name, :phone, :encryption_key)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':email', $userData['email']);
            $stmt->bindParam(':password_hash', $passwordHash);
            $stmt->bindParam(':first_name', $userData['first_name']);
            $stmt->bindParam(':last_name', $userData['last_name']);
            $stmt->bindParam(':phone', $userData['phone']);
            $stmt->bindParam(':encryption_key', $encryptionKey);
            
            if ($stmt->execute()) {
                $userId = $this->db->lastInsertId();
                
                // Log registration
                $this->security->logAudit($userId, null, 'user_registered', 'users', $userId);
                
                return [
                    'success' => true,
                    'message' => 'Registration successful',
                    'user_id' => $userId
                ];
            }
            
            return ['success' => false, 'message' => 'Registration failed'];
            
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error. Please try again.'];
        }
    }
    
    /**
     * Authenticate user (step 1 - password)
     * @param string $email Email
     * @param string $password Password
     * @return array ['success' => bool, 'message' => string, 'user_id' => int, 'requires_2fa' => bool]
     */
    public function authenticateUser($email, $password) {
        try {
            // Get user
            $query = "SELECT user_id, email, password_hash, first_name, last_name, phone, is_active FROM users WHERE email = :email";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            $user = $stmt->fetch();
            
            if (!$user) {
                $this->security->logAudit(null, null, 'login_failed_user_not_found', 'users', null);
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Check if account is active
            if (!$user['is_active']) {
                return ['success' => false, 'message' => 'Account is inactive. Please contact support.'];
            }
            
            // Verify password
            if (!Encryption::verifyPassword($password, $user['password_hash'])) {
                $this->security->logAudit($user['user_id'], null, 'login_failed_wrong_password', 'users', $user['user_id']);
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Update last login
        $updateQuery = "UPDATE users SET last_login = NOW() WHERE user_id = :user_id";
        $updateStmt = $this->db->prepare($updateQuery);
        $updateStmt->bindParam(':user_id', $user['user_id']);
        $updateStmt->execute();

        // Create secure session
        $sessionData = [
            'user_id' => $user['user_id'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'user_type' => 'customer'
        ];

        $sessionId = $this->security->createSession($user['user_id'], null, $sessionData);

        if ($sessionId) {
            $this->security->logAudit($user['user_id'], null, 'login_successful', 'users', $user['user_id']);

            return [
                'success' => true,
                'message' => 'Login successful',
                'session_id' => $sessionId,
                'user_data' => $sessionData,
                'requires_2fa' => false
            ];
        }

        return ['success' => false, 'message' => 'Failed to create session'];
            
        } catch (Exception $e) {
            error_log("User authentication error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error. Please try again.'];
        }
    }
    
    /**
     * Complete user login with 2FA (step 2)
     * @param int $userId User ID
     * @param string $code 2FA code
     * @return array ['success' => bool, 'message' => string, 'session_id' => string]
     */
    public function completeUserLogin($userId, $code) {
        try {
            // Verify 2FA code
            if (!$this->security->verify2FACode($userId, null, $code)) {
                return ['success' => false, 'message' => 'Invalid or expired verification code'];
            }
            
            // Get user data
            $query = "SELECT user_id, email, first_name, last_name FROM users WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'message' => 'User not found'];
            }
            
            // Update last login
            $updateQuery = "UPDATE users SET last_login = NOW() WHERE user_id = :user_id";
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->bindParam(':user_id', $userId);
            $updateStmt->execute();
            
            // Create secure session
            $sessionData = [
                'user_id' => $user['user_id'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'user_type' => 'customer'
            ];
            
            $sessionId = $this->security->createSession($userId, null, $sessionData);
            
            if ($sessionId) {
                $this->security->logAudit($userId, null, 'login_successful', 'users', $userId);
                
                return [
                    'success' => true,
                    'message' => 'Login successful',
                    'session_id' => $sessionId,
                    'user_data' => $sessionData
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to create session'];
            
        } catch (Exception $e) {
            error_log("User login completion error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error. Please try again.'];
        }
    }
    
    /**
     * Authenticate admin (step 1 - password)
     * @param string $username Username
     * @param string $password Password
     * @return array ['success' => bool, 'message' => string, 'admin_id' => int, 'requires_2fa' => bool]
     */
    public function authenticateAdmin($username, $password) {
        try {
            // Get admin
            $query = "SELECT admin_id, username, password_hash, email, is_active FROM admin_users WHERE username = :username";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            $admin = $stmt->fetch();
            
            if (!$admin) {
                $this->security->logAudit(null, null, 'admin_login_failed_user_not_found', 'admin_users', null);
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Check if account is active
            if (!$admin['is_active']) {
                return ['success' => false, 'message' => 'Account is inactive. Please contact system administrator.'];
            }
            
            // Verify password
            if (!Encryption::verifyPassword($password, $admin['password_hash'])) {
                $this->security->logAudit(null, $admin['admin_id'], 'admin_login_failed_wrong_password', 'admin_users', $admin['admin_id']);
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // For admin, we'll use email as phone for 2FA (in production, add phone field to admin_users)
            // Using a placeholder phone for demonstration
            $adminPhone = '+61412345678';
            
            // Send 2FA code
            if ($this->security->send2FACode(null, $admin['admin_id'], $adminPhone)) {
                return [
                    'success' => true,
                    'message' => '2FA code sent',
                    'admin_id' => $admin['admin_id'],
                    'requires_2fa' => true
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to send verification code'];
            
        } catch (Exception $e) {
            error_log("Admin authentication error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error. Please try again.'];
        }
    }
    
    /**
     * Complete admin login with 2FA (step 2)
     * @param int $adminId Admin ID
     * @param string $code 2FA code
     * @return array ['success' => bool, 'message' => string, 'session_id' => string]
     */
    public function completeAdminLogin($adminId, $code) {
        try {
            // Verify 2FA code
            if (!$this->security->verify2FACode(null, $adminId, $code)) {
                return ['success' => false, 'message' => 'Invalid or expired verification code'];
            }
            
            // Get admin data
            $query = "SELECT admin_id, username, email, full_name, role FROM admin_users WHERE admin_id = :admin_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':admin_id', $adminId);
            $stmt->execute();
            
            $admin = $stmt->fetch();
            
            if (!$admin) {
                return ['success' => false, 'message' => 'Admin not found'];
            }
            
            // Update last login
            $updateQuery = "UPDATE admin_users SET last_login = NOW() WHERE admin_id = :admin_id";
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->bindParam(':admin_id', $adminId);
            $updateStmt->execute();
            
            // Create secure session
            $sessionData = [
                'admin_id' => $admin['admin_id'],
                'username' => $admin['username'],
                'email' => $admin['email'],
                'full_name' => $admin['full_name'],
                'role' => $admin['role'],
                'user_type' => 'admin'
            ];
            
            $sessionId = $this->security->createSession(null, $adminId, $sessionData);
            
            if ($sessionId) {
                $this->security->logAudit(null, $adminId, 'admin_login_successful', 'admin_users', $adminId);
                
                return [
                    'success' => true,
                    'message' => 'Login successful',
                    'session_id' => $sessionId,
                    'admin_data' => $sessionData
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to create session'];
            
        } catch (Exception $e) {
            error_log("Admin login completion error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error. Please try again.'];
        }
    }
    
    /**
     * Logout user or admin
     * @param string $sessionId Session ID
     * @return bool Success
     */
    public function logout($sessionId) {
        if ($this->security->invalidateSession($sessionId)) {
            setcookie('BD_DINE_SESSION', '', time() - 3600, '/');
            return true;
        }
        return false;
    }
    
    /**
     * Get current user from session
     * @return array|false User/Admin data or false
     */
    public function getCurrentUser() {
        $sessionId = $_COOKIE['BD_DINE_SESSION'] ?? null;
        
        if (!$sessionId) {
            return false;
        }
        
        return $this->security->validateSession($sessionId);
    }
        }