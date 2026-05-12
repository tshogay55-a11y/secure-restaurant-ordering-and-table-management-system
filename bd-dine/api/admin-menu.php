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
            m.item_id,
            m.item_name,
            m.description,
            m.price,
            m.is_available,
            c.category_name
        FROM menu_items m
        JOIN menu_categories c ON m.category_id = c.category_id
        ORDER BY c.display_order, m.item_name
    ");

    $stmt->execute();
    $items = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'items' => $items
    ]);

} catch (Exception $e) {
    error_log('Admin Menu Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>