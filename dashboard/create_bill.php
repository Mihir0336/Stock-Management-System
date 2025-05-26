<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['customer_id']) || !isset($data['items']) || empty($data['items'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

try {
    $conn->begin_transaction();

    // Calculate totals
    $subtotal = 0;
    $items_data = [];

    foreach ($data['items'] as $item) {
        // Get product details
        $stmt = $conn->prepare("
            SELECT * FROM products WHERE id = ?
        ");
        $stmt->bind_param("i", $item['productId']);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();

        if (!$product || $product['quantity'] < $item['quantity']) {
            throw new Exception("Insufficient stock for product: " . $item['name']);
        }

        $item_total = $item['quantity'] * $item['price'];
        $subtotal += $item_total;

        $items_data[] = [
            'product_id' => $item['productId'],
            'quantity' => $item['quantity'],
            'price' => $item['price'],
            'total' => $item_total
        ];
    }

    $total_amount = $subtotal;
    $amount_paid = floatval($data['amount_paid'] ?? 0);
    $amount_due = $total_amount - $amount_paid;

    // Generate bill number (format: BILL-YYYYMMDD-XXX)
    $date = date('Ymd');
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM bills 
        WHERE bill_number LIKE ?
    ");
    $bill_prefix = "BILL-$date-%";
    $stmt->bind_param("s", $bill_prefix);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['count'];
    $bill_number = sprintf("BILL-%s-%03d", $date, $count + 1);

    // Determine payment status
    $payment_status = 'unpaid';
    if ($amount_paid >= $total_amount) {
        $payment_status = 'paid';
    } elseif ($amount_paid > 0) {
        $payment_status = 'partial';
    }

    // Insert bill
    $stmt = $conn->prepare("
        INSERT INTO bills (
            bill_number, customer_id, created_by, 
            subtotal, total_amount,
            payment_status, amount_paid, amount_due
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "siidddsd",
        $bill_number,
        $data['customer_id'],
        $_SESSION['user_id'],
        $subtotal,
        $total_amount,
        $payment_status,
        $amount_paid,
        $amount_due
    );
    $stmt->execute();
    $bill_id = $conn->insert_id;

    // Insert bill items and update stock
    foreach ($items_data as $item) {
        // Insert bill item
        $stmt = $conn->prepare("
            INSERT INTO bill_items (
                bill_id, product_id, quantity, 
                price_per_unit, total_price
            ) VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "iiidd",
            $bill_id,
            $item['product_id'],
            $item['quantity'],
            $item['price'],
            $item['total']
        );
        $stmt->execute();

        // Update product stock
        $stmt = $conn->prepare("
            UPDATE products 
            SET quantity = quantity - ? 
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $item['quantity'], $item['product_id']);
        $stmt->execute();
    }

    // If payment was made, record it
    if ($amount_paid > 0) {
        $stmt = $conn->prepare("
            INSERT INTO payments (
                bill_id, customer_id, amount, 
                payment_method, notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        $payment_method = $data['payment_method'] ?? 'cash';
        $payment_notes = $data['payment_notes'] ?? '';
        $stmt->bind_param(
            "iidssi",
            $bill_id,
            $data['customer_id'],
            $amount_paid,
            $payment_method,
            $payment_notes,
            $_SESSION['user_id']
        );
        $stmt->execute();
    }

    $conn->commit();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'bill_id' => $bill_id,
        'message' => 'Bill created successfully'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 