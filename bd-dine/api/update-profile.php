<?php
define('BD_DINE_SECURE', true);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../includes/security.php';

try {
    $sessionId = $_COOKIE['BD_DINE_SESSION'] ?? null;
    if (!$sessionId) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit();
    }

    $database = new Database();
    $db = $database->getConnection();
    $security = new Security($database);
    $session = $security->validateSession($sessionId);

    if (!$session || !isset($session['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid session']);
        exit();
    }

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $first_name = trim($data['first_name'] ?? '');
    $last_name = trim($data['last_name'] ?? '');
    $phone = trim($data['phone'] ?? '');

    if (!$first_name || !$last_name) {
        echo json_encode(['success' => false, 'message' => 'First and last name are required']);
        exit();
    }

    $stmt = $db->prepare("UPDATE users SET first_name = :first_name, last_name = :last_name, phone = :phone WHERE user_id = :user_id");
    $stmt->bindParam(':first_name', $first_name);
    $stmt->bindParam(':last_name', $last_name);
    $stmt->bindParam(':phone', $phone);
    $stmt->bindParam(':user_id', $session['user_id']);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>