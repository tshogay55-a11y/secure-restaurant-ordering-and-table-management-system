<?php
/**
 * BD Dine Restaurant - User Registration API
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
    
    $required = ['email', 'password', 'first_name', 'last_name'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            echo json_encode(['success' => false, 'message' => "Field $field is required"]);
            exit();
        }
    }
    
    $database = new Database();
    $auth = new Auth($database);
    
    $result = $auth->registerUser($data);
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Registration API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'System error. Please try again.']);
}
