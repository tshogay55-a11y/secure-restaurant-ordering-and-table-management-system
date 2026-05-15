<?php
define('BD_DINE_SECURE', true);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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
    
    if (!$session || !isset($session['admin_id'])) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit();
    }

    $status = $_GET['status'] ?? 'all';
    $date = $_GET['date'] ?? '';

    $query = "
        SELECT b.booking_id, b.booking_date, b.booking_time, b.number_of_guests,
               b.table_number, b.status, b.special_requests, b.created_at,
               u.first_name, u.last_name, u.email, u.phone
        FROM bookings b
        JOIN users u ON b.user_id = u.user_id
        WHERE 1=1
    ";

    $params = [];

    if ($status !== 'all') {
        $query .= " AND b.status = :status";
        $params[':status'] = $status;
    }

    if (!empty($date)) {
        $query .= " AND b.booking_date = :date";
        $params[':date'] = $date;
    }

    $query .= " ORDER BY b.booking_date DESC, b.booking_time DESC";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    $bookings = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'bookings' => $bookings
    ]);

} catch (Exception $e) {
    error_log("Admin Bookings Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>