<?php
define('BD_DINE_SECURE', true);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/auth.php';
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

    if (!$session || !isset($session['admin_id'])) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit();
    }

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $firstName = trim($data['first_name'] ?? '');
    $lastName = trim($data['last_name'] ?? '');
    $email = trim($data['email'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $password = $data['password'] ?? '';

    if (!$firstName || !$lastName || !$email || !$password) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        exit();
    }

    $checkStmt = $db->prepare("SELECT user_id FROM users WHERE email = :email");
    $checkStmt->bindParam(':email', $email);
    $checkStmt->execute();
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        exit();
    }

    $passwordHash = Encryption::hashPassword($password);
    $encryptionKey = Encryption::generateToken(32);

    $stmt = $db->prepare("INSERT INTO users (email, password_hash, first_name, last_name, phone, encryption_key) 
                          VALUES (:email, :password_hash, :first_name, :last_name, :phone, :encryption_key)");
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':password_hash', $passwordHash);
    $stmt->bindParam(':first_name', $firstName);
    $stmt->bindParam(':last_name', $lastName);
    $stmt->bindParam(':phone', $phone);
    $stmt->bindParam(':encryption_key', $encryptionKey);

    if ($stmt->execute()) {
        $userId = $db->lastInsertId();
        $security->logAudit(null, $session['admin_id'], 'user_created_by_admin', 'users', $userId);

        // Send welcome email with credentials
        try {
            require_once '../vendor/autoload.php';
            require_once '../config/mail.php';

            if (defined('MAIL_HOST') && !empty(MAIL_HOST) && MAIL_HOST !== 'smtp.example.com') {
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
                $mail->Subject = 'BD Dine - Your Account Has Been Created';
                $mail->Body = "
                    <h2>Welcome to BD Dine!</h2>
                    <p>Dear {$firstName},</p>
                    <p>An account has been created for you at BD Dine Restaurant.</p>
                    <p><strong>Your login credentials:</strong></p>
                    <ul>
                        <li><strong>Email:</strong> {$email}</li>
                        <li><strong>Password:</strong> {$password}</li>
                    </ul>
                    <p>Please log in and change your password as soon as possible.</p>
                    <p>BD Dine Restaurant<br>(02) 6234 5678</p>
                ";
                $mail->send();
            }
        } catch (Exception $e) {
            error_log("Welcome email error: " . $e->getMessage());
        }

        echo json_encode(['success' => true, 'message' => 'User created successfully and welcome email sent']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create user']);
    }

} catch (Exception $e) {
    error_log("Admin Add User Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>