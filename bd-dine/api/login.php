<?php
/**
 * BD Dine Restaurant - User Login API
 * Step 1: Authenticate with email and password
 */

define('BD_DINE_SECURE', true);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/encryption.php';

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    // Get JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!isset($data['email']) || !isset($data['password'])) {
        echo json_encode(['success' => false, 'message' => 'Email and password required']);
        exit();
    }
    
    // Sanitize input
    $email = Encryption::sanitizeInput($data['email']);
    $password = $data['password']; // Don't sanitize passwords
    
    // Initialize database and auth
    $database = new Database();
    $auth = new Auth($database);
    
    // Authenticate user
    $result = $auth->authenticateUser($email, $password);
    
    // Return result
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Login API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'System error. Please try again later.'
    ]);
}
