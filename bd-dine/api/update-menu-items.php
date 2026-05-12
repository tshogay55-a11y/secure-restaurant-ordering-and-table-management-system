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

    if (!$session || !isset($session['admin_id'])) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit();
    }

    $data = json_decode(file_get_contents('php://input'), true);

    $itemId = $data['item_id'] ?? null;
    $price = $data['price'] ?? null;
    $isAvailable = $data['is_available'] ?? null;

    if (!$itemId || $price === null || $isAvailable === null) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }

    $stmt = $db->prepare("
        UPDATE menu_items
        SET price = :price,
            is_available = :is_available
        WHERE item_id = :item_id
    ");

    $stmt->execute([
        ':price' => $price,
        ':is_available' => $isAvailable,
        ':item_id' => $itemId
    ]);

    echo json_encode(['success' => true, 'message' => 'Menu item updated']);

} catch (Exception $e) {
    error_log('Update Menu Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>