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
    $bookingId = $data['booking_id'] ?? null;

    if (!$bookingId) {
        echo json_encode(['success' => false, 'message' => 'Booking ID required']);
        exit();
    }

    // Verify booking belongs to this user and is confirmed
    $checkStmt = $db->prepare("
        SELECT booking_id, booking_date, booking_time, checked_in
        FROM bookings 
        WHERE booking_id = :booking_id 
        AND user_id = :user_id
        AND status = 'confirmed'
        AND booking_date = CURDATE()
    ");
    $checkStmt->bindParam(':booking_id', $bookingId);
    $checkStmt->bindParam(':user_id', $session['user_id']);
    $checkStmt->execute();
    $booking = $checkStmt->fetch();

    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found or not eligible for check-in']);
        exit();
    }

    if ($booking['checked_in']) {
        echo json_encode(['success' => false, 'message' => 'Already checked in']);
        exit();
    }

    // Update check-in status
    $updateStmt = $db->prepare("
        UPDATE bookings 
        SET checked_in = 1, checked_in_at = NOW()
        WHERE booking_id = :booking_id
    ");
    $updateStmt->bindParam(':booking_id', $bookingId);
    $updateStmt->execute();

    echo json_encode(['success' => true, 'message' => 'Checked in successfully!']);

} catch (Exception $e) {
    error_log("Check-in Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>