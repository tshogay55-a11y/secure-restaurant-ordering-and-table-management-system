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
    
    // Get user details
    $userStmt = $db->prepare("SELECT first_name, last_name, email, created_at FROM users WHERE user_id = :user_id");
    $userStmt->bindParam(':user_id', $userId);
    $userStmt->execute();
    $user = $userStmt->fetch();
    
    // Get upcoming bookings
    $upcomingStmt = $db->prepare("
        SELECT booking_id, booking_date, booking_time, number_of_guests, table_number, status 
        FROM bookings 
        WHERE user_id = :user_id 
        AND booking_date >= CURDATE() 
        AND status != 'cancelled'
        ORDER BY booking_date ASC, booking_time ASC
    ");
    $upcomingStmt->bindParam(':user_id', $userId);
    $upcomingStmt->execute();
    $upcomingBookings = $upcomingStmt->fetchAll();
    
    // Get total visits (completed bookings)
    $visitsStmt = $db->prepare("SELECT COUNT(*) as visits FROM bookings WHERE user_id = :user_id AND status = 'completed'");
    $visitsStmt->bindParam(':user_id', $userId);
    $visitsStmt->execute();
    $totalVisits = $visitsStmt->fetch()['visits'];

    // Member status based on visits
    $memberStatus = 'Regular';
    if ($totalVisits >= 10) $memberStatus = 'VIP';
    elseif ($totalVisits >= 5) $memberStatus = 'Gold';
    elseif ($totalVisits >= 2) $memberStatus = 'Silver';
    
    echo json_encode([
        'success' => true,
        'user' => [
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email' => $user['email'],
            'member_since' => date('F Y', strtotime($user['created_at']))
        ],
        'stats' => [
            'upcoming_bookings' => count($upcomingBookings),
            'total_visits' => $totalVisits,
            'member_status' => $memberStatus
        ],
        'upcoming_bookings' => $upcomingBookings
    ]);

} catch (Exception $e) {
    error_log("Dashboard API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>