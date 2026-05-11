<?php
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
    
    $database = new Database();
    $auth = new Auth($database);
    
    // Check if admin or user
    if (isset($data['admin_id']) && !empty($data['admin_id'])) {
        $result = $auth->completeAdminLogin($data['admin_id'], $data['code']);
    } elseif (isset($data['user_id']) && !empty($data['user_id'])) {
        $result = $auth->completeUserLogin($data['user_id'], $data['code']);
    } else {
        echo json_encode(['success' => false, 'message' => 'User ID or Admin ID required']);
        exit();
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("2FA Verification API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'System error. Please try again.']);
}
?>