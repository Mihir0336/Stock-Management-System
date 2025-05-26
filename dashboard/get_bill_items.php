<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Bill ID not provided']);
    exit();
}

$bill_id = (int)$_GET['id'];

// Get bill items with product details
$stmt = $conn->prepare("
    SELECT bi.id, bi.quantity, bi.price_per_unit, bi.total_price, p.name, p.quantity as available_stock
    FROM bill_items bi
    JOIN products p ON bi.product_id = p.id
    WHERE bi.bill_id = ?
");
$stmt->bind_param("i", $bill_id);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'quantity' => $row['quantity'],
        'price' => $row['price_per_unit'],
        'total' => $row['total_price'],
        'available_stock' => $row['available_stock']
    ];
}

echo json_encode(['success' => true, 'items' => $items]);
?> 