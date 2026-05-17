<?php
define('BD_DINE_SECURE', true);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../config/database.php';
require_once '../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

function sendUnlockEmail($email, $firstName) {
    try {
        require_once '../vendor/autoload.php';
        require_once '../config/mail.php';

        if (!defined('MAIL_HOST') || empty(MAIL_HOST) || MAIL_HOST === 'smtp.example.com') return;

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port = MAIL_PORT;
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'BD Dine - Account Unlocked';
        $mail->Body = "
            <h2>Account Unlocked</h2>
            <p>Dear {$firstName},</p>
            <p>Your account has been <strong style='color:green;'>unlocked</strong> by our team.</p>
            <p>You can now log in again. If you did not request this, please contact us immediately at (02) 6234 5678.</p>
            <p>BD Dine Restaurant</p>
        ";
        $mail->send();
    } catch (Exception $e) {
        error_log("Unlock email error: " . $e->getMessage());
    }
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

    if (!$session || !isset($session['admin_id'])) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit();
    }

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $userId = intval($data['user_id'] ?? 0);
    $action = $data['action'] ?? '';

    if (!$userId || !$action) {
        echo json_encode(['success' => false, 'message' => 'User ID and action required']);
        exit();
    }

    $userStmt = $db->prepare("SELECT first_name, last_name, email, is_active, locked_at, failed_login_attempts FROM users WHERE user_id = :user_id");
    $userStmt->bindParam(':user_id', $userId);
    $userStmt->execute();
    $user = $userStmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }

    switch ($action) {
        case 'unlock':
            $stmt = $db->prepare("UPDATE users SET locked_at = NULL, failed_login_attempts = 0 WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            sendUnlockEmail($user['email'], $user['first_name']);
            $security->logAudit(null, $session['admin_id'], 'user_unlocked', 'users', $userId);
            echo json_encode(['success' => true, 'message' => 'Account unlocked and user notified by email']);
            break;

        case 'lock':
            // Admin manual lock — do NOT set failed_login_attempts
            // so the "5 failed attempts" warning won't show in dashboard
            $stmt = $db->prepare("UPDATE users SET locked_at = NOW() WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $security->logAudit(null, $session['admin_id'], 'user_locked_by_admin', 'users', $userId);
            echo json_encode(['success' => true, 'message' => 'Account locked']);
            break;

        case 'deactivate':
            $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $security->logAudit(null, $session['admin_id'], 'user_deactivated', 'users', $userId);
            echo json_encode(['success' => true, 'message' => 'Account deactivated']);
            break;

        case 'activate':
            $stmt = $db->prepare("UPDATE users SET is_active = 1 WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $security->logAudit(null, $session['admin_id'], 'user_activated', 'users', $userId);
            echo json_encode(['success' => true, 'message' => 'Account activated']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    error_log("Manage User Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>