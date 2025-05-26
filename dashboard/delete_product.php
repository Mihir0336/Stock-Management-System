<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['product_id'])) {
    $_SESSION['error'] = "Invalid request";
    header("Location: companies.php");
    exit();
}

$product_id = (int)$_POST['product_id'];

// Start transaction
$conn->begin_transaction();

try {
    // Get product details first
    $stmt = $conn->prepare("SELECT company FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    if (!$product) {
        throw new Exception("Product not found");
    }

    // 1. Delete bill items associated with this product
    $stmt = $conn->prepare("DELETE FROM bill_items WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();

    // 2. Delete the product
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();

    // Commit transaction
    $conn->commit();
    $_SESSION['success'] = "Product has been deleted successfully";
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $_SESSION['error'] = "Error deleting product: " . $e->getMessage();
}

// Redirect back to the company products page
header("Location: company_products.php?company=" . urlencode($product['company']));
exit();
?> 