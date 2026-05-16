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
    
    if (!isset($data['booking_id'])) {
        echo json_encode(['success' => false, 'message' => 'Booking ID required']);
        exit();
    }
    
    $bookingId = intval($data['booking_id']);
    
    // Get booking details
    $checkStmt = $db->prepare("SELECT * FROM bookings WHERE booking_id = :booking_id AND user_id = :user_id AND status NOT IN ('cancelled', 'completed')");
    $checkStmt->bindParam(':booking_id', $bookingId);
    $checkStmt->bindParam(':user_id', $userId);
    $checkStmt->execute();
    $booking = $checkStmt->fetch();
    
    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found or already cancelled']);
        exit();
    }
    
    // Check 24 hour policy
    $bookingDateTime = strtotime($booking['booking_date'] . ' ' . $booking['booking_time']);
    $now = time();
    $hoursUntilBooking = ($bookingDateTime - $now) / 3600;

    if ($hoursUntilBooking < 24) {
        // Less than 24 hours — request cancellation, notify admin
        $cancelStmt = $db->prepare("UPDATE bookings SET status = 'cancel_requested' WHERE booking_id = :booking_id AND user_id = :user_id");
        $cancelStmt->bindParam(':booking_id', $bookingId);
        $cancelStmt->bindParam(':user_id', $userId);
        $cancelStmt->execute();

        $security->logAudit($userId, null, 'cancellation_requested', 'bookings', $bookingId);

        echo json_encode([
            'success' => true,
            'late_cancellation' => true,
            'message' => 'Your booking is within 24 hours. A cancellation request has been sent to our team. We will contact you shortly.'
        ]);
    } else {
        // More than 24 hours — cancel immediately
        $cancelStmt = $db->prepare("UPDATE bookings SET status = 'cancelled' WHERE booking_id = :booking_id AND user_id = :user_id");
        $cancelStmt->bindParam(':booking_id', $bookingId);
        $cancelStmt->bindParam(':user_id', $userId);
        $cancelStmt->execute();

        $security->logAudit($userId, null, 'booking_cancelled', 'bookings', $bookingId);

        echo json_encode([
            'success' => true,
            'late_cancellation' => false,
            'message' => 'Booking cancelled successfully'
        ]);
    }

} catch (Exception $e) {
    error_log("Cancel Booking Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>