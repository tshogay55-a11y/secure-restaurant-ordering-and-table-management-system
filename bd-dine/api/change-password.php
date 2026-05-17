<?php
define('BD_DINE_SECURE', true);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/encryption.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

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

    $userId = $session['user_id'];
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $currentPassword = $data['current_password'] ?? '';
    $newPassword = $data['new_password'] ?? '';

    if (!$currentPassword || !$newPassword) {
        echo json_encode(['success' => false, 'message' => 'All fields required']);
        exit();
    }

    // Get current password hash
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $user = $stmt->fetch();

    if (!$user || !Encryption::verifyPassword($currentPassword, $user['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit();
    }

    // Validate new password
    $validation = Encryption::validatePassword($newPassword);
    if (!$validation['valid']) {
        echo json_encode(['success' => false, 'message' => $validation['message']]);
        exit();
    }

    // Update password
    $newHash = Encryption::hashPassword($newPassword);
    $updateStmt = $db->prepare("UPDATE users SET password_hash = :hash WHERE user_id = :user_id");
    $updateStmt->bindParam(':hash', $newHash);
    $updateStmt->bindParam(':user_id', $userId);
    $updateStmt->execute();

    $security->logAudit($userId, null, 'password_changed', 'users', $userId);
    echo json_encode(['success' => true, 'message' => 'Password updated successfully']);

} catch (Exception $e) {
    error_log("Change Password Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>