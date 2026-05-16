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

    // Get all customers with their booking counts and last login
   $stmt = $db->prepare("
    SELECT 
        u.user_id,
        u.first_name,
        u.last_name,
        u.email,
        u.created_at,
        u.last_login,
        u.phone,
        u.is_active,

        COUNT(DISTINCT b.booking_id) as total_bookings,
        SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
        SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,

        COALESCE(o.total_orders, 0) as total_orders,
        COALESCE(o.total_spent, 0) as total_spent

    FROM users u
    LEFT JOIN bookings b ON u.user_id = b.user_id
    LEFT JOIN (
        SELECT 
            user_id,
            COUNT(order_id) as total_orders,
            SUM(total_amount) as total_spent
        FROM orders
        GROUP BY user_id
    ) o ON u.user_id = o.user_id

    GROUP BY u.user_id
    ORDER BY u.created_at DESC
");
    $stmt->execute();
    $customers = $stmt->fetchAll();

    // Get login/logout activity from audit log
    $activityStmt = $db->prepare("
        SELECT a.user_id, a.action, a.created_at, a.ip_address
        FROM audit_log a
        WHERE a.user_id IS NOT NULL 
        AND a.action IN ('login_successful', 'session_created')
        ORDER BY a.created_at DESC
        LIMIT 20
    ");
    $activityStmt->execute();
    $recentActivity = $activityStmt->fetchAll();

    echo json_encode([
        'success' => true,
        'customers' => $customers,
        'recent_activity' => $recentActivity
    ]);

} catch (Exception $e) {
    error_log("Admin Customers Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>