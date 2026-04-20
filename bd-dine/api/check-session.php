<?php
/**
 * BD Dine Restaurant - Session Check API
 */

define('BD_DINE_SECURE', true);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';
require_once '../includes/security.php';

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
        echo json_encode([
            'valid' => true,
            'user_type' => $session['data']['user_type'] ?? 'unknown',
            'user_id' => $session['user_id'],
            'admin_id' => $session['admin_id']
        ]);
    } else {
        echo json_encode(['valid' => false, 'message' => 'Session invalid or expired']);
    }
    
} catch (Exception $e) {
    error_log("Session Check API Error: " . $e->getMessage());
    echo json_encode(['valid' => false, 'message' => 'System error']);
}
