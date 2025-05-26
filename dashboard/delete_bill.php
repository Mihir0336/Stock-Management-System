<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bill_id'])) {
    $bill_id = (int)$_POST['bill_id'];

    // Start transaction
    $conn->begin_transaction();

    try {
        // Get bill items to restore stock
        $stmt = $conn->prepare("
            SELECT product_id, quantity 
            FROM bill_items 
            WHERE bill_id = ?
        ");
        $stmt->bind_param("i", $bill_id);
        $stmt->execute();
        $result = $stmt->get_result();

        // Restore product quantities
        while ($item = $result->fetch_assoc()) {
            $stmt = $conn->prepare("
                UPDATE products 
                SET quantity = quantity + ? 
                WHERE id = ?
            ");
            $stmt->bind_param("ii", $item['quantity'], $item['product_id']);
            $stmt->execute();
        }

        // Delete bill items
        $stmt = $conn->prepare("DELETE FROM bill_items WHERE bill_id = ?");
        $stmt->bind_param("i", $bill_id);
        $stmt->execute();

        // Delete bill
        $stmt = $conn->prepare("DELETE FROM bills WHERE id = ?");
        $stmt->bind_param("i", $bill_id);
        $stmt->execute();

        $conn->commit();
        header("Location: bills.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        die("Error deleting bill: " . $e->getMessage());
    }
} else {
    header("Location: bills.php");
    exit();
}
?> 