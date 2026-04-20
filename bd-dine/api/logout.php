<?php
/**
 * BD Dine Restaurant - Logout API
 */

define('BD_DINE_SECURE', true);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../config/database.php';
require_once '../includes/auth.php';

try {
    $sessionId = $_COOKIE['BD_DINE_SESSION'] ?? null;
    
    if ($sessionId) {
        $database = new Database();
        $auth = new Auth($database);
        
        if ($auth->logout($sessionId)) {
            echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Logout failed']);
        }
    } else {
        echo json_encode(['success' => true, 'message' => 'No active session']);
    }
    
} catch (Exception $e) {
    error_log("Logout API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'System error']);
}
