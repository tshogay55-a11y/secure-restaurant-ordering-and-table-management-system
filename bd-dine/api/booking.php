<?php
/**
 * BD Dine Restaurant - Booking API
 * Create table reservations with security
 */

define('BD_DINE_SECURE', true);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT');

require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/encryption.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    // Check authentication
    $sessionId = $_COOKIE['BD_DINE_SESSION'] ?? null;
    
    if (!$sessionId) {
        echo json_encode(['success' => false, 'message' => 'Authentication required', 'redirect' => 'login.html']);
        exit();
    }
    
    $database = new Database();
    $db = $database->getConnection();
    $security = new Security($database);
    
    // Validate session
    $session = $security->validateSession($sessionId);
    if (!$session || !isset($session['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid session', 'redirect' => 'login.html']);
        exit();
    }
    
    $userId = $session['user_id'];
    
    // Get booking data
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Validate required fields
    $required = ['booking_date', 'booking_time', 'number_of_guests'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            echo json_encode(['success' => false, 'message' => "Field $field is required"]);
            exit();
        }
    }
    
    // Validate booking date (must be today or future)
    $bookingDate = $data['booking_date'];
    if (strtotime($bookingDate) < strtotime('today')) {
        echo json_encode(['success' => false, 'message' => 'Booking date must be today or in the future']);
        exit();
    }
    
    // Validate number of guests
    $guests = intval($data['number_of_guests']);
    if ($guests < 1 || $guests > 20) {
        echo json_encode(['success' => false, 'message' => 'Number of guests must be between 1 and 20']);
        exit();
    }
    
    // Find available table
    $query = "SELECT table_id, table_number, capacity 
              FROM restaurant_tables 
              WHERE capacity >= :guests AND is_available = 1 
              ORDER BY capacity ASC 
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':guests', $guests);
    $stmt->execute();
    $table = $stmt->fetch();
    
    if (!$table) {
        echo json_encode(['success' => false, 'message' => 'No available tables for your party size. Please contact us.']);
        exit();
    }
    
    // Encrypt special requests if provided
    $encryption = new Encryption();
    $specialRequests = $data['special_requests'] ?? '';
    $encryptedData = null;
    
    if (!empty($specialRequests)) {
        $encryptedData = $encryption->encrypt($specialRequests);
    }
    
    // Create booking
    $insertQuery = "INSERT INTO bookings 
                    (user_id, booking_date, booking_time, number_of_guests, table_number, special_requests, encrypted_data, status) 
                    VALUES (:user_id, :booking_date, :booking_time, :guests, :table_number, :special_requests, :encrypted_data, 'pending')";
    
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->bindParam(':user_id', $userId);
    $insertStmt->bindParam(':booking_date', $bookingDate);
    $insertStmt->bindParam(':booking_time', $data['booking_time']);
    $insertStmt->bindParam(':guests', $guests);
    $insertStmt->bindParam(':table_number', $table['table_number']);
    $insertStmt->bindParam(':special_requests', $specialRequests);
    $insertStmt->bindParam(':encrypted_data', $encryptedData);
    
    if ($insertStmt->execute()) {
        $bookingId = $db->lastInsertId();
        
        // Log the booking
        $security->logAudit($userId, null, 'booking_created', 'bookings', $bookingId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Booking created successfully!',
            'booking_id' => $bookingId,
            'table_number' => $table['table_number'],
            'booking_date' => $bookingDate,
            'booking_time' => $data['booking_time']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create booking']);
    }
    
} catch (Exception $e) {
    error_log("Booking API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'System error. Please try again.']);
}
