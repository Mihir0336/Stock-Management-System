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
function sendJsonResponse($success, $message, $data = null) {
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response['customer'] = $data;
    }
    echo json_encode($response);
    exit();
}

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(false, 'Not authorized');
    }

    // Validate input
    if (!isset($_POST['name']) || empty(trim($_POST['name']))) {
        sendJsonResponse(false, 'Customer name is required');
    }

    // Sanitize input
    $name = trim($_POST['name']);
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : null;
    $village = isset($_POST['village']) ? trim($_POST['village']) : null;

    // Start transaction
    $conn->begin_transaction();

    // Check if customer with same name or phone already exists
    $checkQuery = "SELECT id, name, phone FROM customers WHERE name = ? OR phone = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("ss", $name, $phone);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows > 0) {
        $existingCustomer = $result->fetch_assoc();
        $conn->rollback();
        
        // Provide specific message based on what matched
        if ($existingCustomer['name'] === $name && $existingCustomer['phone'] === $phone) {
            sendJsonResponse(false, 'A customer with this name and phone number already exists');
        } elseif ($existingCustomer['name'] === $name) {
            sendJsonResponse(false, 'A customer with this name already exists');
        } else {
            sendJsonResponse(false, 'A customer with this phone number already exists');
        }
    }

    // Insert customer
    $stmt = $conn->prepare("INSERT INTO customers (name, phone, village) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $phone, $village);

    if (!$stmt->execute()) {
        throw new Exception("Error inserting customer: " . $stmt->error);
    }

    $customerId = $conn->insert_id;

    // Commit transaction
    $conn->commit();

    // Return success response with customer data
    sendJsonResponse(true, 'Customer added successfully', [
        'id' => $customerId,
        'name' => $name,
        'phone' => $phone,
        'village' => $village
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    // Log the error
    error_log("Error adding customer: " . $e->getMessage());
    
    // Return error response
    sendJsonResponse(false, 'Error adding customer: ' . $e->getMessage());
} 