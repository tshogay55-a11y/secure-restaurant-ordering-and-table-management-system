<?php
define('BD_DINE_SECURE', true);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';
require_once '../includes/security.php';

session_start();

try {
    $date = $_GET['date'] ?? '';

    if (empty($date)) {
        echo json_encode(['success' => false, 'booked_slots' => []]);
        exit();
    }

    $database = new Database();
    $db = $database->getConnection();
    $security = new Security($database);

    $sessionId = $_COOKIE['BD_DINE_SESSION'] ?? null;
    $currentUserId = null;

    if ($sessionId) {
        $session = $security->validateSession($sessionId);
        if ($session && isset($session['user_id'])) {
            $currentUserId = $session['user_id'];
        }
    }

    $query = "SELECT booking_time, user_id
              FROM bookings
              WHERE booking_date = :booking_date
              AND status != 'cancelled'";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':booking_date', $date);
    $stmt->execute();

    $bookedSlots = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $bookedSlots[] = [
            'booking_time' => $row['booking_time'],
            'is_mine' => ($currentUserId && $row['user_id'] == $currentUserId)
        ];
    }

    echo json_encode([
        'success' => true,
        'booked_slots' => $bookedSlots
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'booked_slots' => []]);
}
?>