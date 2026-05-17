<?php
define('BD_DINE_SECURE', true);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';
require_once '../includes/security.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("
        SELECT rt.table_number, rt.capacity, rt.location
        FROM restaurant_tables rt
        WHERE rt.is_available = 1
        AND rt.table_number NOT IN (
            SELECT b.table_number FROM bookings b
            WHERE b.status = 'confirmed'
            AND b.booking_date = CURDATE()
            AND b.checked_in = 1
        )
        AND rt.table_number NOT IN (
            SELECT b.table_number FROM bookings b
            WHERE b.status = 'confirmed'
            AND b.booking_date = CURDATE()
            AND TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(b.booking_date, ' ', b.booking_time)) BETWEEN 0 AND 180
        )
        ORDER BY rt.table_number ASC
    ");
    $stmt->execute();
    $tables = $stmt->fetchAll();

    echo json_encode(['success' => true, 'tables' => $tables]);

} catch (Exception $e) {
    error_log("Available Tables Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>