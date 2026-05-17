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
    
    public function registerUser($userData) {
        try {
            if (!Encryption::validateEmail($userData['email'])) {
                return ['success' => false, 'message' => 'Invalid email address'];
            }
    
            $passwordValidation = Encryption::validatePassword($userData['password']);
            if (!$passwordValidation['valid']) {
                return ['success' => false, 'message' => $passwordValidation['message']];
            }
            
            $checkQuery = "SELECT user_id FROM users WHERE email = :email";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':email', $userData['email']);
            $checkStmt->execute();
            
            if ($checkStmt->fetch()) {
                return ['success' => false, 'message' => 'Email already registered'];
            }
            
            $passwordHash = Encryption::hashPassword($userData['password']);
            $encryptionKey = Encryption::generateToken(32);
            
            $query = "INSERT INTO users (email, password_hash, first_name, last_name, encryption_key) 
                      VALUES (:email, :password_hash, :first_name, :last_name, :encryption_key)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':email', $userData['email']);
            $stmt->bindParam(':password_hash', $passwordHash);
            $stmt->bindParam(':first_name', $userData['first_name']);
            $stmt->bindParam(':last_name', $userData['last_name']);
            $stmt->bindParam(':encryption_key', $encryptionKey);
            
            if ($stmt->execute()) {
                $userId = $this->db->lastInsertId();
                try {
                    $this->security->logAudit($userId, null, 'user_registered', 'users', $userId);
                } catch (Exception $e) {
                    error_log("Audit log error: " . $e->getMessage());
                }
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
    
    public function authenticateUser($email, $password) {
        try {
            $query = "SELECT user_id, email, password_hash, first_name, last_name, is_active, failed_login_attempts, locked_at FROM users WHERE email = :email";
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

            // Check if account is locked — distinguish between auto-lock and admin-lock
            if ($user['locked_at']) {
                if ($user['failed_login_attempts'] >= 5) {
                    // Auto-locked due to too many failed attempts
                    return ['success' => false, 'message' => 'Your account has been locked due to too many failed login attempts. Please contact us at (02) 6234 5678.'];
                } else {
                    // Manually locked by admin
                    return ['success' => false, 'message' => 'Your account has been locked. Please contact us at (02) 6234 5678 for assistance.'];
                }
            }
            
            // Verify password
            if (!Encryption::verifyPassword($password, $user['password_hash'])) {
                $attempts = $user['failed_login_attempts'] + 1;
                
                if ($attempts >= 5) {
                    $lockStmt = $this->db->prepare("UPDATE users SET failed_login_attempts = :attempts, locked_at = NOW() WHERE user_id = :user_id");
                    $lockStmt->bindParam(':attempts', $attempts);
                    $lockStmt->bindParam(':user_id', $user['user_id']);
                    $lockStmt->execute();

                    $this->sendLockEmail($user['email'], $user['first_name']);
                    $this->security->logAudit($user['user_id'], null, 'account_locked', 'users', $user['user_id']);
                    return ['success' => false, 'message' => 'Your account has been locked after 5 failed attempts. Please check your email.'];
                } else {
                    $updateStmt = $this->db->prepare("UPDATE users SET failed_login_attempts = :attempts WHERE user_id = :user_id");
                    $updateStmt->bindParam(':attempts', $attempts);
                    $updateStmt->bindParam(':user_id', $user['user_id']);
                    $updateStmt->execute();

                    $remaining = 5 - $attempts;
                    $this->security->logAudit($user['user_id'], null, 'login_failed_wrong_password', 'users', $user['user_id']);
                    return ['success' => false, 'message' => "Invalid credentials. $remaining attempt(s) remaining before lockout."];
                }
            }
            
            // Reset failed attempts on successful login
            $resetStmt = $this->db->prepare("UPDATE users SET failed_login_attempts = 0, locked_at = NULL WHERE user_id = :user_id");
            $resetStmt->bindParam(':user_id', $user['user_id']);
            $resetStmt->execute();

            // Update last login
            $updateQuery = "UPDATE users SET last_login = NOW() WHERE user_id = :user_id";
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->bindParam(':user_id', $user['user_id']);
            $updateStmt->execute();

            // Send 2FA code
            if ($this->security->send2FACode($user['user_id'], null, $user['email'])) {
                return [
                    'success' => true,
                    'message' => '2FA code sent to your email',
                    'user_id' => $user['user_id'],
                    'requires_2fa' => true
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to send verification code'];
            
        } catch (Exception $e) {
            error_log("User authentication error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error. Please try again.'];
        }
    }
    
    public function completeUserLogin($userId, $code) {
        try {
            if (!$this->security->verify2FACode($userId, null, $code)) {
                return ['success' => false, 'message' => 'Invalid or expired verification code'];
            }
            
            $query = "SELECT user_id, email, first_name, last_name FROM users WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'message' => 'User not found'];
            }
            
            $updateQuery = "UPDATE users SET last_login = NOW() WHERE user_id = :user_id";
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->bindParam(':user_id', $userId);
            $updateStmt->execute();
            
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
    
    public function authenticateAdmin($username, $password) {
        try {
            $query = "SELECT admin_id, username, password_hash, email, is_active FROM admin_users WHERE username = :username";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            $admin = $stmt->fetch();
            
            if (!$admin) {
                $this->security->logAudit(null, null, 'admin_login_failed_user_not_found', 'admin_users', null);
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            if (!$admin['is_active']) {
                return ['success' => false, 'message' => 'Account is inactive. Please contact system administrator.'];
            }
            
            if (!Encryption::verifyPassword($password, $admin['password_hash'])) {
                $this->security->logAudit(null, $admin['admin_id'], 'admin_login_failed_wrong_password', 'admin_users', $admin['admin_id']);
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            if ($this->security->send2FACode(null, $admin['admin_id'], $admin['email'])) {
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
    
    public function completeAdminLogin($adminId, $code) {
        try {
            if (!$this->security->verify2FACode(null, $adminId, $code)) {
                return ['success' => false, 'message' => 'Invalid or expired verification code'];
            }
            
            $query = "SELECT admin_id, username, email, full_name, role FROM admin_users WHERE admin_id = :admin_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':admin_id', $adminId);
            $stmt->execute();
            
            $admin = $stmt->fetch();
            
            if (!$admin) {
                return ['success' => false, 'message' => 'Admin not found'];
            }
            
            $updateQuery = "UPDATE admin_users SET last_login = NOW() WHERE admin_id = :admin_id";
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->bindParam(':admin_id', $adminId);
            $updateStmt->execute();
            
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
    
    public function logout($sessionId) {
        if ($this->security->invalidateSession($sessionId)) {
            setcookie('BD_DINE_SESSION', '', time() - 3600, '/');
            return true;
        }
        return false;
    }
    
    public function getCurrentUser() {
        $sessionId = $_COOKIE['BD_DINE_SESSION'] ?? null;
        if (!$sessionId) {
            return false;
        }
        return $this->security->validateSession($sessionId);
    }

    private function sendLockEmail($email, $firstName) {
        try {
            require_once '../vendor/autoload.php';
            require_once '../config/mail.php';

            if (!defined('MAIL_HOST') || empty(MAIL_HOST) || MAIL_HOST === 'smtp.example.com') return;

            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->SMTPSecure = MAIL_ENCRYPTION;
            $mail->Port = MAIL_PORT;
            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'BD Dine - Account Locked';
            $mail->Body = "
                <h2>Account Locked</h2>
                <p>Dear {$firstName},</p>
                <p>Your account has been <strong style='color:red;'>locked</strong> due to 5 consecutive failed login attempts.</p>
                <p>Please contact us at (02) 6234 5678 or email info@bddine.com.au to unlock your account.</p>
                <p>BD Dine Restaurant</p>
            ";
            $mail->send();
        } catch (Exception $e) {
            error_log("Lock email error: " . $e->getMessage());
        }
    }
}
?>