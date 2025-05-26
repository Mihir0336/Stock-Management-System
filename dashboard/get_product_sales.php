<?php
session_start();
require_once '../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get parameters
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-7 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

if (!$product_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid product ID']);
    exit();
}

try {
    // Get detailed sales data
    $query = "
        SELECT 
            b.bill_number,
            DATE(b.created_at) as date,
            c.name as customer_name,
            bi.quantity,
            (bi.quantity * bi.price_per_unit) as amount
        FROM bill_items bi
        JOIN bills b ON bi.bill_id = b.id
        LEFT JOIN customers c ON b.customer_id = c.id
        WHERE bi.product_id = ?
        AND DATE(b.created_at) BETWEEN ? AND ?
        ORDER BY b.created_at DESC
    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    if (!$stmt->bind_param('iss', $product_id, $date_from, $date_to)) {
        throw new Exception("Binding parameters failed: " . $stmt->error);
    }

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception("Getting result failed: " . $stmt->error);
    }

    $sales = [];
    while ($row = $result->fetch_assoc()) {
        $sales[] = [
            'bill_number' => $row['bill_number'],
            'date' => $row['date'],
            'customer_name' => $row['customer_name'],
            'quantity' => $row['quantity'],
            'amount' => $row['amount']
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($sales);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
} 