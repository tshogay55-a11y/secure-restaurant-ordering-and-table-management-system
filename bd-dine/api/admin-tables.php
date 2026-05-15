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

    // Get all tables with today's booking info
    $stmt = $db->prepare("
        SELECT 
            t.table_id,
            t.table_number,
            t.capacity,
            t.location,
            t.is_available,
            b.booking_id,
            b.booking_time,
            b.number_of_guests,
            b.status as booking_status,
            b.checked_in,
            b.checked_in_at,
            u.first_name,
            u.last_name,
            u.phone
        FROM restaurant_tables t
        LEFT JOIN bookings b ON t.table_number = b.table_number
            AND b.booking_date = CURDATE()
            AND b.status IN ('confirmed', 'pending')
        LEFT JOIN users u ON b.user_id = u.user_id
        ORDER BY t.table_number ASC
    ");
    $stmt->execute();
    $tables = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'tables' => $tables
    ]);

} catch (Exception $e) {
    error_log("Admin Tables Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>