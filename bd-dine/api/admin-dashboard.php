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

    // Today's bookings
    $todayStmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE DATE(booking_date) = CURDATE() AND status != 'cancelled'");
    $todayStmt->execute();
    $todaysBookings = $todayStmt->fetch()['total'];

    // Total active users
    $usersStmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
    $usersStmt->execute();
    $activeUsers = $usersStmt->fetch()['total'];

    // Pending bookings
    $pendingStmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE status = 'pending'");
    $pendingStmt->execute();
    $pendingBookings = $pendingStmt->fetch()['total'];

    // Total bookings
    $totalStmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE status != 'cancelled'");
    $totalStmt->execute();
    $totalBookings = $totalStmt->fetch()['total'];

    // Recent bookings with customer names
    $recentStmt = $db->prepare("
        SELECT b.booking_id, b.booking_date, b.booking_time, b.number_of_guests, 
               b.table_number, b.status, u.first_name, u.last_name, u.email
        FROM bookings b
        JOIN users u ON b.user_id = u.user_id
        ORDER BY b.created_at DESC
        LIMIT 10
    ");
    $recentStmt->execute();
    $recentBookings = $recentStmt->fetchAll();

    echo json_encode([
        'success' => true,
        'stats' => [
            'todays_bookings' => $todaysBookings,
            'active_users' => $activeUsers,
            'pending_bookings' => $pendingBookings,
            'total_bookings' => $totalBookings
        ],
        'recent_bookings' => $recentBookings
    ]);

} catch (Exception $e) {
    error_log("Admin Dashboard Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>