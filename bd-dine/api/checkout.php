<?php
define('BD_DINE_SECURE', true);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    $bookingId = intval($data['booking_id'] ?? 0);

    if (!$bookingId) {
        echo json_encode(['success' => false, 'message' => 'Invalid booking']);
        exit();
    }

    // Verify booking belongs to this user and is checked in
    $checkStmt = $db->prepare("
        SELECT booking_id FROM bookings 
        WHERE booking_id = :booking_id 
        AND user_id = :user_id 
        AND checked_in = 1
        AND status = 'confirmed'
    ");
    $checkStmt->bindParam(':booking_id', $bookingId);
    $checkStmt->bindParam(':user_id', $session['user_id']);
    $checkStmt->execute();

    if ($checkStmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Booking not found or not checked in']);
        exit();
    }

    // Mark as completed
    $stmt = $db->prepare("UPDATE bookings SET status = 'completed' WHERE booking_id = :booking_id");
    $stmt->bindParam(':booking_id', $bookingId);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Checked out successfully']);

} catch (Exception $e) {
    error_log("Checkout Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>