<?php
/**
 * BD Dine Restaurant - 2FA Verification API
 * Step 2: Verify 2FA code and complete login
 */

define('BD_DINE_SECURE', true);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../config/database.php';
require_once '../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!isset($data['code'])) {
        echo json_encode(['success' => false, 'message' => 'Verification code required']);
        exit();
    }
    
    if (!isset($data['user_id']) || empty($data['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        exit();
    }
    
    $database = new Database();
    $auth = new Auth($database);
    
    $result = $auth->completeUserLogin($data['user_id'], $data['code']);
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("2FA Verification API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'System error. Please try again.']);
}