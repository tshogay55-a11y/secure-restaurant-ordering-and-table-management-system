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

    $stmt = $db->prepare("
        SELECT 
            o.order_id,
            o.table_number,
            o.reservation_name,
            o.reservation_phone,
            o.total_amount,
            o.order_date,
            o.order_status,
            u.first_name,
            u.last_name,
            u.email,
            GROUP_CONCAT(mi.item_name, ' x', oi.quantity SEPARATOR ', ') as items
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        LEFT JOIN menu_items mi ON oi.item_id = mi.item_id
        GROUP BY o.order_id
        ORDER BY o.order_date ASC
    ");
    $stmt->execute();
    $orders = $stmt->fetchAll();

    echo json_encode(['success' => true, 'orders' => $orders]);

} catch (Exception $e) {
    error_log("Admin Orders Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>