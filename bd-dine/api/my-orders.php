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
        mi.item_name,
        oi.quantity,
        oi.unit_price,
        oi.subtotal
    FROM orders o
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    LEFT JOIN menu_items mi ON oi.item_id = mi.item_id
    WHERE o.user_id = :user_id
    ORDER BY o.order_date DESC, o.order_id DESC
");
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$rows = $stmt->fetchAll();

// Group by order
$orders = [];
foreach ($rows as $row) {
    $orderId = $row['order_id'];
    if (!isset($orders[$orderId])) {
        $orders[$orderId] = [
            'order_id' => $row['order_id'],
            'order_date' => $row['order_date'],
            'total_amount' => $row['total_amount'],
            'payment_status' => $row['payment_status'],
            'order_status' => $row['order_status'],
            'items' => []
        ];
    }
    if ($row['item_name']) {
        $orders[$orderId]['items'][] = [
            'name' => $row['item_name'],
            'quantity' => $row['quantity'],
            'unit_price' => $row['unit_price'],
            'subtotal' => $row['subtotal']
        ];
    }
}

echo json_encode(['success' => true, 'orders' => array_values($orders)]);

} catch (Exception $e) {
    error_log("My Orders Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>