<?php
/**
 * BD Dine Restaurant - Security Utility
 * Secure session management and authentication
 */

if (!defined('BD_DINE_SECURE')) {
    die('Direct access not permitted');
}

require_once 'encryption.php';

class Security {
    private $db;
    private $encryption;
    
    public function __construct($database) {
        $this->db = $database->getConnection();
        $this->encryption = new Encryption();
    }
    
    /**
     * Create secure session
     * @param int $userId User ID (null for admin)
     * @param int $adminId Admin ID (null for user)
     * @param array $sessionData Session data to store
     * @return string|false Session ID or false on failure
     */
    public function createSession($userId = null, $adminId = null, $sessionData = []) {
        try {
            $sessionId = Encryption::generateSessionId();
            $sessionDataJson = json_encode($sessionData);
            $encryptedData = $this->encryption->encrypt($sessionDataJson);
            
            $expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            $query = "INSERT INTO secure_sessions 
                      (session_id, user_id, admin_id, session_data, ip_address, user_agent, expires_at) 
                      VALUES (:session_id, :user_id, :admin_id, :session_data, :ip_address, :user_agent, :expires_at)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':session_id', $sessionId);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':admin_id', $adminId);
            $stmt->bindParam(':session_data', $encryptedData);
            $stmt->bindParam(':ip_address', $ipAddress);
            $stmt->bindParam(':user_agent', $userAgent);
            $stmt->bindParam(':expires_at', $expiresAt);
            
            if ($stmt->execute()) {
                // Set cookie
                setcookie('BD_DINE_SESSION', $sessionId, [
                    'expires' => time() + SESSION_LIFETIME,
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
                
                // Log the session creation
                $this->logAudit($userId, $adminId, 'session_created', 'secure_sessions', null);
                
                return $sessionId;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Session creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate session
     * @param string $sessionId Session ID
     * @return array|false Session data or false if invalid
     */
    public function validateSession($sessionId) {
        try {
            $query = "SELECT * FROM secure_sessions 
                      WHERE session_id = :session_id 
                      AND is_valid = 1 
                      AND expires_at > NOW()";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':session_id', $sessionId);
            $stmt->execute();
            
            $session = $stmt->fetch();
            
            if (!$session) {
                return false;
            }
            
            // Validate IP address
            $currentIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            if ($session['ip_address'] !== $currentIp) {
                $this->invalidateSession($sessionId);
                return false;
            }
            
            // Decrypt session data
            $decryptedData = $this->encryption->decrypt($session['session_data']);
            $sessionData = json_decode($decryptedData, true);
            
            // Update last activity
            $updateQuery = "UPDATE secure_sessions SET last_activity = NOW() WHERE session_id = :session_id";
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->bindParam(':session_id', $sessionId);
            $updateStmt->execute();
            
            return [
                'user_id' => $session['user_id'],
                'admin_id' => $session['admin_id'],
                'data' => $sessionData
            ];
            
        } catch (Exception $e) {
            error_log("Session validation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Invalidate session
     * @param string $sessionId Session ID
     * @return bool Success
     */
    public function invalidateSession($sessionId) {
        try {
            $query = "UPDATE secure_sessions SET is_valid = 0 WHERE session_id = :session_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':session_id', $sessionId);
            return $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Session invalidation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean expired sessions
     * @return int Number of sessions cleaned
     */
    public function cleanExpiredSessions() {
        try {
            $query = "DELETE FROM secure_sessions WHERE expires_at < NOW() OR is_valid = 0";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->rowCount();
            
        } catch (Exception $e) {
            error_log("Session cleanup error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Generate and send 2FA code
     * @param int $userId User ID (null for admin)
     * @param int $adminId Admin ID (null for user)
     * @param string $phone Phone number
     * @return bool Success
     */
    public function send2FACode($userId = null, $adminId = null, $phone) {
        try {
            // Generate 6-digit code
            $code = Encryption::generate2FACode();
            $expiresAt = date('Y-m-d H:i:s', time() + TWO_FACTOR_EXPIRY);
            
            // Store code in database
            $query = "INSERT INTO two_factor_codes (user_id, admin_id, code, phone, expires_at) 
                      VALUES (:user_id, :admin_id, :code, :phone, :expires_at)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':admin_id', $adminId);
            $stmt->bindParam(':code', $code);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':expires_at', $expiresAt);
            
            if (!$stmt->execute()) {
                return false;
            }
            
            // Send SMS (simulated - replace with actual SMS API)
            $this->sendSMS($phone, "Your BD Dine verification code is: $code. Valid for 5 minutes.");
            
            // Log the 2FA code generation
            $this->logAudit($userId, $adminId, '2fa_code_sent', 'two_factor_codes', null);
            
            return true;
            
        } catch (Exception $e) {
            error_log("2FA code generation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify 2FA code
     * @param int $userId User ID (null for admin)
     * @param int $adminId Admin ID (null for user)
     * @param string $code 6-digit code
     * @return bool Valid
     */
    public function verify2FACode($userId = null, $adminId = null, $code) {
        try {
            $query = "SELECT * FROM two_factor_codes 
                      WHERE code = :code 
                      AND verified = 0 
                      AND expires_at > NOW()";
            
            if ($userId) {
                $query .= " AND user_id = :user_id";
            } else {
                $query .= " AND admin_id = :admin_id";
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':code', $code);
            
            if ($userId) {
                $stmt->bindParam(':user_id', $userId);
            } else {
                $stmt->bindParam(':admin_id', $adminId);
            }
            
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result) {
                // Mark as verified
                $updateQuery = "UPDATE two_factor_codes SET verified = 1 WHERE code_id = :code_id";
                $updateStmt = $this->db->prepare($updateQuery);
                $updateStmt->bindParam(':code_id', $result['code_id']);
                $updateStmt->execute();
                
                // Log successful verification
                $this->logAudit($userId, $adminId, '2fa_verified', 'two_factor_codes', $result['code_id']);
                
                return true;
            }
            
            // Log failed verification
            $this->logAudit($userId, $adminId, '2fa_failed', 'two_factor_codes', null);
            
            return false;
            
        } catch (Exception $e) {
            error_log("2FA verification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send SMS (simulated - replace with actual SMS API)
     * @param string $phone Phone number
     * @param string $message Message
     * @return bool Success
     */
    private function sendSMS($phone, $message) {
        // In production, integrate with SMS API like Twilio, AWS SNS, etc.
        // For demonstration, log the SMS
        error_log("SMS to $phone: $message");
        
        /*
        // Example Twilio integration:
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => SMS_API_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'api_key' => SMS_API_KEY,
                'to' => $phone,
                'from' => SMS_FROM_NUMBER,
                'message' => $message
            ])
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        */
        
        return true;
    }
    
    /**
     * Log audit trail
     * @param int $userId User ID
     * @param int $adminId Admin ID
     * @param string $action Action performed
     * @param string $tableName Table name
     * @param int $recordId Record ID
     * @return bool Success
     */
    public function logAudit($userId, $adminId, $action, $tableName, $recordId) {
        try {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $details = json_encode([
                'timestamp' => time(),
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
            ]);
            
            $query = "INSERT INTO audit_log 
                      (user_id, admin_id, action, table_name, record_id, ip_address, user_agent, details) 
                      VALUES (:user_id, :admin_id, :action, :table_name, :record_id, :ip_address, :user_agent, :details)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':admin_id', $adminId);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':table_name', $tableName);
            $stmt->bindParam(':record_id', $recordId);
            $stmt->bindParam(':ip_address', $ipAddress);
            $stmt->bindParam(':user_agent', $userAgent);
            $stmt->bindParam(':details', $details);
            
            return $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Audit log error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check rate limiting for login attempts
     * @param string $identifier Email or username
     * @return bool Is rate limited
     */
    public function isRateLimited($identifier) {
        // Implementation would track login attempts in a separate table
        // For simplicity, this is a placeholder
        return false;
    }
    
    /**
     * Validate CSRF token
     * @param string $token Token to validate
     * @return bool Valid
     */
    public function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Generate CSRF token
     * @return string Token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = Encryption::generateToken(32);
        }
        
        return $_SESSION['csrf_token'];
    }
}

?>
