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

    if (!$session || !isset($session['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid session']);
        exit();
    }

    $userId = $session['user_id'];

    $stmt = $db->prepare("
        SELECT 
            o.order_id,
            o.order_date,
            o.total_amount,
            o.payment_status,
            o.order_status,
            o.payment_method,
            GROUP_CONCAT(mi.item_name, ' x', oi.quantity SEPARATOR ', ') as items
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        LEFT JOIN menu_items mi ON oi.item_id = mi.item_id
        WHERE o.user_id = :user_id
        GROUP BY o.order_id
        ORDER BY o.order_date DESC
    ");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $orders = $stmt->fetchAll();

    echo json_encode(['success' => true, 'orders' => $orders]);

} catch (Exception $e) {
    error_log("My Orders Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>