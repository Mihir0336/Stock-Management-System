<?php
// Start output buffering
ob_start();

// Enable error reporting but don't display errors
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set content type to JSON
header('Content-Type: application/json');

session_start();
require_once '../config/database.php';

// Function to send JSON response and exit
function sendResponse($success, $message, $data = null) {
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response['customer'] = $data;
    }
    echo json_encode($response);
    ob_end_flush();
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendResponse(false, 'Unauthorized');
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method');
}

// Get and validate input
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$village = trim($_POST['village'] ?? '');

// Validate required fields
if (empty($name)) {
    sendResponse(false, 'Customer name is required');
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Check if customer with same name and phone already exists
    $checkQuery = "SELECT id FROM customers WHERE name = ? AND phone = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("ss", $name, $phone);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows > 0) {
        $conn->rollback();
        sendResponse(false, 'A customer with this name and phone number already exists');
    }

    // Insert new customer
    $query = "INSERT INTO customers (name, phone, village) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $name, $phone, $village);
    
    if (!$stmt->execute()) {
        throw new Exception("Error inserting customer: " . $stmt->error);
    }

    $customerId = $conn->insert_id;

    // Commit transaction
    $conn->commit();

    // Return success response with customer data
    sendResponse(true, 'Customer added successfully', [
        'id' => $customerId,
        'name' => $name,
        'phone' => $phone,
        'village' => $village
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Log the error
    error_log("Error adding customer: " . $e->getMessage());
    
    // Return error response
    sendResponse(false, 'Error adding customer: ' . $e->getMessage());
} 