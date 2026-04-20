<?php
/**
 * BD Dine Restaurant - Admin Login API
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
    
    if (!isset($data['username']) || !isset($data['password'])) {
        echo json_encode(['success' => false, 'message' => 'Username and password required']);
        exit();
    }
    
    $database = new Database();
    $auth = new Auth($database);
    
    $result = $auth->authenticateAdmin($data['username'], $data['password']);
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Admin Login API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'System error. Please try again.']);
}
<? 