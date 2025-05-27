<?php
// Prevent any output before JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set JSON header
header('Content-Type: application/json');

// Function to send JSON response and exit
function sendJsonResponse($success, $message) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

try {
    session_start();
    require_once '../config/database.php';

    // Check database connection
    if (!isset($conn) || $conn->connect_error) {
        sendJsonResponse(false, 'Database connection failed: ' . ($conn->connect_error ?? 'Unknown error'));
    }

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(false, 'Not authorized');
    }

    // Debug information
    $debug_info = [
        'post_data' => $_POST,
        'session_user' => $_SESSION['user_id'] ?? null,
        'db_connected' => isset($conn) && !$conn->connect_error
    ];

    // Validate input
    if (!isset($_POST['amount']) || !isset($_POST['payment_method'])) {
        sendJsonResponse(false, 'Missing required fields. Debug: ' . json_encode($debug_info));
    }

    $amount = (float)$_POST['amount'];
    $payment_method = $_POST['payment_method'];
    $notes = $_POST['notes'] ?? '';
    $is_settlement = isset($_POST['is_settlement']) && $_POST['is_settlement'] === '1';
    $is_total_settlement = isset($_POST['is_total_settlement']) && $_POST['is_total_settlement'] === '1';

    // Validate amount
    if ($amount <= 0) {
        sendJsonResponse(false, 'Amount must be greater than 0');
    }

    // Start transaction
    if (!$conn->begin_transaction()) {
        sendJsonResponse(false, 'Failed to start transaction: ' . $conn->error);
    }

    if ($is_total_settlement) {
        // Handle total settlement for all bills
        if (!isset($_POST['customer_id'])) {
            $conn->rollback();
            sendJsonResponse(false, 'Customer ID is required for total settlement');
        }

        $customer_id = (int)$_POST['customer_id'];
        $discount_amount = isset($_POST['discount_amount']) ? (float)$_POST['discount_amount'] : 0;
        $settlement_amount = $amount;

        // Get all unpaid bills for the customer
        $stmt = $conn->prepare("
            SELECT id, amount_due, amount_paid, total_amount
            FROM bills 
            WHERE customer_id = ? AND amount_due > 0 
            ORDER BY created_at ASC
        ");
        
        if (!$stmt) {
            $conn->rollback();
            sendJsonResponse(false, 'Database error in prepare: ' . $conn->error);
        }

        $stmt->bind_param("i", $customer_id);
        
        if (!$stmt->execute()) {
            $conn->rollback();
            sendJsonResponse(false, 'Database error in execute: ' . $stmt->error);
        }

        $bills = $stmt->get_result();

        if (!$bills) {
            $conn->rollback();
            sendJsonResponse(false, 'Database error in get_result: ' . $stmt->error);
        }

        // Calculate total due
        $total_due = 0;
        $bills_array = [];
        while ($bill = $bills->fetch_assoc()) {
            $total_due += $bill['amount_due'];
            $bills_array[] = $bill;
        }

        // Calculate total payment and remaining due
        $total_payment = $discount_amount + $settlement_amount;
        $remaining_due = $total_due - $total_payment;

        // Validate total payment
        if ($total_payment > $total_due) {
            $conn->rollback();
            sendJsonResponse(false, 'Total payment (discount + settlement) cannot exceed total due amount');
        }

        // Update bills with discount amount
        if ($discount_amount > 0) {
            $stmt = $conn->prepare("
                UPDATE bills 
                SET discount_amount = discount_amount + ?,
                    amount_due = amount_due - ?
                WHERE customer_id = ? AND amount_due > 0
            ");
            
            if (!$stmt) {
                $conn->rollback();
                sendJsonResponse(false, 'Database error in prepare discount update: ' . $conn->error);
            }

            $stmt->bind_param("ddi", $discount_amount, $discount_amount, $customer_id);
            
            if (!$stmt->execute()) {
                $conn->rollback();
                sendJsonResponse(false, 'Database error in execute discount update: ' . $stmt->error);
            }
        }

        // Make a single payment entry for the total amount
        $stmt = $conn->prepare("
            INSERT INTO payments (
                bill_id,
                customer_id,
                amount,
                payment_method,
                notes,
                payment_date,
                created_by
            ) VALUES (?, ?, ?, ?, ?, NOW(), ?)
        ");

        if (!$stmt) {
            $conn->rollback();
            sendJsonResponse(false, 'Database error in prepare payment: ' . $conn->error);
        }

        $payment_notes = $notes;
        if ($discount_amount > 0) {
            $payment_notes .= " (Discount: ₹" . number_format($discount_amount, 2) . ")";
        }
        $payment_notes .= " (Settlement: ₹" . number_format($settlement_amount, 2) . ")";
        
        $stmt->bind_param("iidssi", $bills_array[0]['id'], $customer_id, $total_payment, $payment_method, $payment_notes, $_SESSION['user_id']);
        
        if (!$stmt->execute()) {
            $conn->rollback();
            sendJsonResponse(false, 'Database error in execute payment: ' . $stmt->error);
        }

        // Update all bills with settlement amount
        foreach ($bills_array as $bill) {
            // Update bill
            $new_amount_paid = $bill['amount_paid'] + $total_payment;
            $new_amount_due = $bill['total_amount'] - $new_amount_paid;
            $payment_status = $new_amount_due <= 0 ? 'paid' : 'partial';

            $stmt = $conn->prepare("
                UPDATE bills 
                SET amount_paid = ?, 
                    amount_due = ?, 
                    payment_status = ?
                WHERE id = ?
            ");

            if (!$stmt) {
                $conn->rollback();
                sendJsonResponse(false, 'Database error in prepare update: ' . $conn->error);
            }

            $stmt->bind_param("ddsi", $new_amount_paid, $new_amount_due, $payment_status, $bill['id']);
            
            if (!$stmt->execute()) {
                $conn->rollback();
                sendJsonResponse(false, 'Database error in execute update: ' . $stmt->error);
            }
        }
    } else {
        // Handle single bill payment
        if (!isset($_POST['bill_id'])) {
            $conn->rollback();
            sendJsonResponse(false, 'Bill ID is required for single bill payment');
        }

        $bill_id = (int)$_POST['bill_id'];

        // Get bill details
        $stmt = $conn->prepare("SELECT total_amount, amount_paid, amount_due FROM bills WHERE id = ?");
        
        if (!$stmt) {
            $conn->rollback();
            sendJsonResponse(false, 'Database error in prepare bill: ' . $conn->error);
        }

        $stmt->bind_param("i", $bill_id);
        
        if (!$stmt->execute()) {
            $conn->rollback();
            sendJsonResponse(false, 'Database error in execute bill: ' . $stmt->error);
        }

        $bill = $stmt->get_result()->fetch_assoc();

        if (!$bill) {
            $conn->rollback();
            sendJsonResponse(false, 'Bill not found');
        }

        if ($amount > $bill['amount_due']) {
            $conn->rollback();
            sendJsonResponse(false, 'Amount cannot exceed the amount due');
        }

        // Calculate new amounts
        $new_amount_paid = $bill['amount_paid'] + $amount;
        $new_amount_due = $bill['total_amount'] - $new_amount_paid;
        
        // Determine payment status
        $payment_status = 'partial';
        if ($new_amount_due <= 0) {
            $payment_status = 'paid';
            $new_amount_due = 0;
        }

        // If this is a settlement, mark it as paid regardless of amount
        if ($is_settlement) {
            $payment_status = 'paid';
            $new_amount_due = 0;
            $notes = "SETTLEMENT: " . $notes;
        }

        // Record payment
        $stmt = $conn->prepare("
            INSERT INTO payments (
                bill_id,
                customer_id,
                amount,
                payment_method,
                notes,
                payment_date,
                created_by
            ) VALUES (?, ?, ?, ?, ?, NOW(), ?)
        ");

        if (!$stmt) {
            $conn->rollback();
            sendJsonResponse(false, 'Database error in prepare payment: ' . $conn->error);
        }

        // Get customer_id from bill
        $stmt2 = $conn->prepare("SELECT customer_id FROM bills WHERE id = ?");
        if (!$stmt2) {
            $conn->rollback();
            sendJsonResponse(false, 'Database error in prepare customer query: ' . $conn->error);
        }

        $stmt2->bind_param("i", $bill_id);
        if (!$stmt2->execute()) {
            $conn->rollback();
            sendJsonResponse(false, 'Database error in execute customer query: ' . $stmt2->error);
        }

        $customer_result = $stmt2->get_result();
        $customer_data = $customer_result->fetch_assoc();
        if (!$customer_data) {
            $conn->rollback();
            sendJsonResponse(false, 'Could not find customer for bill');
        }

        $customer_id = $customer_data['customer_id'];
        $stmt->bind_param("iidssi", $bill_id, $customer_id, $amount, $payment_method, $notes, $_SESSION['user_id']);
        
        if (!$stmt->execute()) {
            $conn->rollback();
            sendJsonResponse(false, 'Database error in execute payment: ' . $stmt->error);
        }

        // Update bill
        $stmt = $conn->prepare("
            UPDATE bills 
            SET amount_paid = ?, 
                amount_due = ?, 
                payment_status = ?
            WHERE id = ?
        ");

        if (!$stmt) {
            $conn->rollback();
            sendJsonResponse(false, 'Database error in prepare update: ' . $conn->error);
        }

        $stmt->bind_param("ddsi", $new_amount_paid, $new_amount_due, $payment_status, $bill_id);
        
        if (!$stmt->execute()) {
            $conn->rollback();
            sendJsonResponse(false, 'Database error in execute update: ' . $stmt->error);
        }
    }

    // Commit transaction
    if (!$conn->commit()) {
        $conn->rollback();
        sendJsonResponse(false, 'Failed to commit transaction: ' . $conn->error);
    }
    
    sendJsonResponse(true, 'Payment recorded successfully');
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    sendJsonResponse(false, 'An error occurred: ' . $e->getMessage() . '. Debug: ' . json_encode($debug_info ?? []));
}
?> 