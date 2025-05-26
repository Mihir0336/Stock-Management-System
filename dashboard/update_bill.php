<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$bill_id = (int)$_POST['bill_id'];
$quantities = $_POST['quantities'] ?? [];

if (empty($quantities)) {
    echo json_encode(['success' => false, 'message' => 'No items to update']);
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // Get current bill items
    $stmt = $conn->prepare("
        SELECT bi.id, bi.product_id, bi.quantity as old_quantity, p.quantity as available_stock
        FROM bill_items bi
        JOIN products p ON bi.product_id = p.id
        WHERE bi.bill_id = ?
    ");
    $stmt->bind_param("i", $bill_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_items = [];
    while ($row = $result->fetch_assoc()) {
        $current_items[$row['id']] = $row;
    }

    // Calculate totals
    $subtotal = 0;
    foreach ($quantities as $item_id => $new_quantity) {
        $item_id = (int)$item_id;
        $new_quantity = (int)$new_quantity;
        
        if (!isset($current_items[$item_id])) {
            throw new Exception("Invalid item ID");
        }

        $old_quantity = $current_items[$item_id]['old_quantity'];
        $product_id = $current_items[$item_id]['product_id'];
        $available_stock = $current_items[$item_id]['available_stock'] + $old_quantity;

        if ($new_quantity > $available_stock) {
            throw new Exception("Quantity exceeds available stock");
        }

        // Get product's price
        $stmt = $conn->prepare("
            SELECT price_per_unit 
            FROM products p 
            WHERE p.id = ?
        ");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();

        $item_subtotal = $new_quantity * $product['price_per_unit'];
        $subtotal += $item_subtotal;

        // Update bill item
        $stmt = $conn->prepare("
            UPDATE bill_items 
            SET quantity = ?, total_price = price_per_unit * ?
            WHERE id = ?
        ");
        $stmt->bind_param("iii", $new_quantity, $new_quantity, $item_id);
        $stmt->execute();

        // Update product stock
        $stmt = $conn->prepare("
            UPDATE products 
            SET quantity = quantity + ? - ?
            WHERE id = ?
        ");
        $stmt->bind_param("iii", $old_quantity, $new_quantity, $product_id);
        $stmt->execute();
    }

    // Update bill totals
    $total_amount = $subtotal;

    // Get current payment status and amounts
    $stmt = $conn->prepare("
        SELECT amount_paid, amount_due, payment_status 
        FROM bills 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $bill_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $bill = $result->fetch_assoc();

    // Calculate new amounts
    $new_amount_due = $total_amount - $bill['amount_paid'];
    $new_payment_status = ($new_amount_due <= 0) ? 'paid' : 
                         (($bill['amount_paid'] > 0) ? 'partial' : 'unpaid');

    $stmt = $conn->prepare("
        UPDATE bills 
        SET subtotal = ?, 
            total_amount = ?, 
            amount_due = ?,
            payment_status = ?
        WHERE id = ?
    ");
    $stmt->bind_param("dddsi", $subtotal, $total_amount, $new_amount_due, $new_payment_status, $bill_id);

    if (!$stmt->execute()) {
        throw new Exception("Error updating bill: " . $stmt->error);
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 