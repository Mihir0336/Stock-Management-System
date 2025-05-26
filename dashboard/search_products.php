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
        $response['products'] = $data;
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

// Get the search term from POST data
$data = json_decode(file_get_contents('php://input'), true);
$search = isset($data['search']) ? $data['search'] : '';

if (empty($search)) {
    sendResponse(true, 'No search term provided', []);
}

try {
    // Search products by name, company, or category
    $query = "SELECT p.*, c.name as category_name 
              FROM products p 
              JOIN categories c ON p.category_id = c.id 
              WHERE p.quantity > 0 
              AND (
                  p.name LIKE ? 
                  OR p.company LIKE ? 
                  OR c.name LIKE ?
              )
              ORDER BY p.company, p.name, p.size 
              LIMIT 20";

    $searchTerm = "%{$search}%";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }

    sendResponse(true, 'Products found', $products);

} catch (Exception $e) {
    // Log the error
    error_log("Error searching products: " . $e->getMessage());
    
    // Return error response
    sendResponse(false, 'Error searching products: ' . $e->getMessage());
} 