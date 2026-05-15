<?php

define('BD_DINE_SECURE', true);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("
        SELECT 
            mi.item_id,
            mi.item_name,
            mi.description,
            mi.price,
            mi.is_available,
            mi.image_url,
            mc.category_name
        FROM menu_items mi
        LEFT JOIN menu_categories mc ON mi.category_id = mc.category_id
        ORDER BY mc.category_id, mi.item_name
    ");

    $stmt->execute();

    echo json_encode([
        'success' => true,
        'items' => $stmt->fetchAll()
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error'
    ]);
}
?>