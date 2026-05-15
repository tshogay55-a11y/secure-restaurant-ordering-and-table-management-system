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

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    $totalAmount = floatval($data['total_amount'] ?? 0);

    if ($totalAmount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid amount']);
        exit();
    }

    // Load Stripe
    require_once '../vendor/stripe/stripe-php/init.php';
    \Stripe\Stripe::setApiKey('sk_test_51TXH3RQm0jhlnCnDcWdBkgthJ4krp9L7FGtjZ2Kij6rNezqtLHlRWBhI4hYstM3Gldqj1TTbERE5aDLSmt1RISmC00kMJ6LzwY');

    $amountInCents = intval($totalAmount * 100);

    $paymentIntent = \Stripe\PaymentIntent::create([
        'amount' => $amountInCents,
        'currency' => 'aud',
        'description' => 'BD Dine Restaurant Order',
        'metadata' => ['user_id' => $session['user_id']]
    ]);

    echo json_encode([
        'success' => true,
        'client_secret' => $paymentIntent->client_secret
    ]);

} catch (Exception $e) {
    error_log("Payment Intent Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Payment setup failed']);
}
?>