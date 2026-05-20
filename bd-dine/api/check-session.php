<?php
/**
 * BD Dine Restaurant - Session Check API
 */

define('BD_DINE_SECURE', true);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/encryption.php';

try {
    $sessionId = $_COOKIE['BD_DINE_SESSION'] ?? null;
    
    if (!$sessionId) {
        echo json_encode(['valid' => false, 'message' => 'No session found']);
        exit();
    }
    
    $database = new Database();
    $security = new Security($database);
    
    $session = $security->validateSession($sessionId);
    
    if ($session) {
        $userDetails = null;
        if ($session['user_id']) {
            $query = "SELECT first_name, last_name, email, phone FROM users WHERE user_id = :user_id";
            $db = $database->getConnection();
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $session['user_id']);
            $stmt->execute();
            $userDetails = $stmt->fetch();
        }

        // Generate CSRF token tied to session
        $csrfToken = hash_hmac('sha256', $sessionId, 'bd_dine_csrf_secret');
        
        echo json_encode([
            'valid' => true,
            'user_type' => $session['data']['user_type'] ?? 'unknown',
            'user_id' => $session['user_id'],
            'admin_id' => $session['admin_id'],
            'csrf_token' => $csrfToken,
            'user' => [
                'first_name' => $userDetails['first_name'] ?? '',
                'last_name' => $userDetails['last_name'] ?? '',
                'email' => $userDetails['email'] ?? '',
                'phone' => $userDetails['phone'] ?? ''
            ]
        ]);
    } else {
        echo json_encode(['valid' => false, 'message' => 'Session invalid or expired']);
    }
} catch (Exception $e) {
    echo json_encode(['valid' => false, 'message' => 'Server error']);
}
?>