<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $company = trim($_POST['company']);
    $category_id = (int)$_POST['category_id'];
    $size = trim($_POST['size']);
    $price_per_unit = (float)$_POST['price_per_unit'];
    $quantity = (int)$_POST['quantity'];
    $low_stock_threshold = (int)$_POST['low_stock_threshold'];

    // Validate input
    if (empty($name)) {
        $_SESSION['error'] = "Product name is required";
        header("Location: company_products.php?company=" . urlencode($company));
        exit();
    }

    try {
        // Insert new product
        $stmt = $conn->prepare("
            INSERT INTO products (name, company, category_id, size, price_per_unit, quantity, low_stock_threshold) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssisidi", $name, $company, $category_id, $size, $price_per_unit, $quantity, $low_stock_threshold);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Product added successfully";
        } else {
            throw new Exception("Error adding product: " . $conn->error);
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }

    header("Location: company_products.php?company=" . urlencode($company));
    exit();
} else {
    header("Location: companies.php");
    exit();
}
?> 