<?php
define('BD_DINE_SECURE', true);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $sessionId = $_COOKIE['BD_DINE_SESSION'] ?? null;

    if (!$sessionId) {
        echo json_encode(['success' => false, 'message' => 'Please login before payment']);
        exit();
    }

    $database = new Database();
    $db = $database->getConnection();
    $security = new Security($database);

    $session = $security->validateSession($sessionId);

    if (!$session || !isset($session['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid session. Please login again.']);
        exit();
    }

    $userId = $session['user_id'];

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $cart = $data['cart'] ?? [];
    $totalAmount = floatval($data['total_amount'] ?? 0);
    $paymentMethod = $data['payment_method'] ?? 'simulated_card';

    if (empty($cart) || $totalAmount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Cart is empty']);
        exit();
    }

    $db->beginTransaction();

    // Create order
    $orderQuery = "INSERT INTO orders 
        (user_id, total_amount, encrypted_payment_data, payment_method, payment_status, order_status, delivery_type)
        VALUES 
        (:user_id, :total_amount, :encrypted_payment_data, :payment_method, 'completed', 'processing', 'dine_in')";

    $encryptedPaymentData = base64_encode('Simulated secure payment - no real card stored');

    $orderStmt = $db->prepare($orderQuery);
    $orderStmt->bindParam(':user_id', $userId);
    $orderStmt->bindParam(':total_amount', $totalAmount);
    $orderStmt->bindParam(':encrypted_payment_data', $encryptedPaymentData);
    $orderStmt->bindParam(':payment_method', $paymentMethod);
    $orderStmt->execute();

    $orderId = $db->lastInsertId();

    // Create order items
    foreach ($cart as $item) {
        $itemName = $item['name'] ?? '';
        $quantity = intval($item['quantity'] ?? 1);
        $unitPrice = floatval($item['price'] ?? 0);
        $subtotal = $quantity * $unitPrice;

        if (empty($itemName) || $quantity <= 0 || $unitPrice <= 0) {
            continue;
        }

        // Find menu item id by name
        $findItemQuery = "SELECT item_id FROM menu_items WHERE item_name = :item_name LIMIT 1";
        $findItemStmt = $db->prepare($findItemQuery);
        $findItemStmt->bindParam(':item_name', $itemName);
        $findItemStmt->execute();
        $menuItem = $findItemStmt->fetch();

        // If item does not exist in menu_items, skip order_items insert
        // Order itself and payment transaction will still be saved.
        if (!$menuItem) {
            continue;
        }

        $itemId = $menuItem['item_id'];

        $itemQuery = "INSERT INTO order_items
            (order_id, item_id, quantity, unit_price, subtotal)
            VALUES
            (:order_id, :item_id, :quantity, :unit_price, :subtotal)";

        $itemStmt = $db->prepare($itemQuery);
        $itemStmt->bindParam(':order_id', $orderId);
        $itemStmt->bindParam(':item_id', $itemId);
        $itemStmt->bindParam(':quantity', $quantity);
        $itemStmt->bindParam(':unit_price', $unitPrice);
        $itemStmt->bindParam(':subtotal', $subtotal);
        $itemStmt->execute();
    }

    // Create payment transaction
    $paymentIntentId = $data['payment_intent_id'] ?? ('SIM-' . time() . '-' . $orderId);
    $encryptedCardData = base64_encode('Stripe payment processed. Intent: ' . $paymentIntentId);

    $paymentQuery = "INSERT INTO payment_transactions
        (order_id, amount, currency, payment_gateway, transaction_reference, encrypted_card_data, payment_status)
        VALUES
        (:order_id, :amount, 'AUD', 'Stripe', :transaction_reference, :encrypted_card_data, 'captured')";

    $paymentStmt = $db->prepare($paymentQuery);
    $paymentStmt->bindParam(':order_id', $orderId);
    $paymentStmt->bindParam(':amount', $totalAmount);
    $paymentStmt->bindParam(':transaction_reference', $paymentIntentId);
    $paymentStmt->bindParam(':encrypted_card_data', $encryptedCardData);
    $paymentStmt->execute();

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Payment successful! Order saved.',
        'order_id' => $orderId,
        'transaction_reference' => $paymentIntentId
    ]);

} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }

    error_log("Payment Error: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Payment failed. Please try again.'
    ]);
}
?>