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

function sendBookingEmail($booking, $status) {
    try {
        require_once '../vendor/autoload.php';
        require_once '../config/mail.php';

        if (!defined('MAIL_HOST') || empty(MAIL_HOST) || MAIL_HOST === 'smtp.example.com') {
            return; // SMTP not configured, skip silently
        }
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port = MAIL_PORT;
        
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($booking['email']);
        
        $mail->isHTML(true);
        
        $date = date('l, d F Y', strtotime($booking['booking_date']));
        $time = date('g:i A', strtotime($booking['booking_time']));
        
        if ($status === 'confirmed') {
            $mail->Subject = 'BD Dine - Booking Confirmed!';
            $mail->Body = "
                <h2>Booking Confirmed!</h2>
                <p>Dear {$booking['first_name']},</p>
                <p>Your booking at BD Dine has been <strong style='color: green;'>confirmed</strong>!</p>
                <p><strong>Details:</strong></p>
                <ul>
                    <li>Date: {$date}</li>
                    <li>Time: {$time}</li>
                    <li>Guests: {$booking['number_of_guests']}</li>
                    <li>Table: {$booking['table_number']}</li>
                </ul>
                <p>We look forward to seeing you!</p>
                <p>BD Dine Restaurant</p>
            ";
        } elseif ($status === 'cancelled') {
            $mail->Subject = 'BD Dine - Booking Cancelled';
            $mail->Body = "
                <h2>Booking Cancelled</h2>
                <p>Dear {$booking['first_name']},</p>
                <p>Your booking on <strong>{$date}</strong> at <strong>{$time}</strong> has been cancelled.</p>
                <p>If you have any questions, please contact us at (02) 6234 5678.</p>
                <p>BD Dine Restaurant</p>
            ";
        } elseif ($status === 'completed') {
            $mail->Subject = 'BD Dine - Thank You!';
            $mail->Body = "
                <h2>Thank You for Visiting!</h2>
                <p>Dear {$booking['first_name']},</p>
                <p>Thank you for dining with us at BD Dine! We hope you had a wonderful experience.</p>
                <p>We look forward to welcoming you again soon!</p>
                <p>BD Dine Restaurant</p>
            ";
        }
        
        $mail->send();
    } catch (Exception $e) {
        error_log("Booking email error: " . $e->getMessage());
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

    if (!isset($data['booking_id']) || !isset($data['status'])) {
        echo json_encode(['success' => false, 'message' => 'Booking ID and status required']);
        exit();
    }

    $allowedStatuses = ['confirmed', 'cancelled', 'completed', 'pending'];
    if (!in_array($data['status'], $allowedStatuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit();
    }

    $stmt = $db->prepare("UPDATE bookings SET status = :status WHERE booking_id = :booking_id");
    $stmt->bindParam(':status', $data['status']);
    $stmt->bindParam(':booking_id', $data['booking_id']);

   if ($stmt->execute()) {
    $security->logAudit(null, $session['admin_id'], 'booking_status_updated', 'bookings', $data['booking_id']);
    
    // Get booking and customer details to send email
    $bookingStmt = $db->prepare("
        SELECT b.*, u.email, u.first_name, u.last_name 
        FROM bookings b 
        JOIN users u ON b.user_id = u.user_id 
        WHERE b.booking_id = :booking_id
    ");
    $bookingStmt->bindParam(':booking_id', $data['booking_id']);
    $bookingStmt->execute();
    $booking = $bookingStmt->fetch();
    
    if ($booking) {
        sendBookingEmail($booking, $data['status']);
    }
    
    echo json_encode(['success' => true, 'message' => 'Booking status updated']);
} else {
        echo json_encode(['success' => false, 'message' => 'Failed to update booking']);
    }

} catch (Exception $e) {
    error_log("Update Booking Status Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>