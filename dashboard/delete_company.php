<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['company_id'])) {
    $_SESSION['error'] = "Invalid request";
    header("Location: companies.php");
    exit();
}

$company_id = (int)$_POST['company_id'];

// Start transaction
$conn->begin_transaction();

try {
    // Get company name first
    $stmt = $conn->prepare("SELECT name FROM companies WHERE id = ?");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $company = $result->fetch_assoc();

    if (!$company) {
        throw new Exception("Company not found");
    }

    // 1. Delete bill items associated with this company's products
    $stmt = $conn->prepare("
        DELETE bi FROM bill_items bi
        JOIN products p ON bi.product_id = p.id
        WHERE p.company = ?
    ");
    $stmt->bind_param("s", $company['name']);
    $stmt->execute();

    // 2. Delete products associated with this company
    $stmt = $conn->prepare("DELETE FROM products WHERE company = ?");
    $stmt->bind_param("s", $company['name']);
    $stmt->execute();

    // 3. Finally, delete the company
    $stmt = $conn->prepare("DELETE FROM companies WHERE id = ?");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();

    // Commit transaction
    $conn->commit();
    $_SESSION['success'] = "Company and all associated products have been deleted successfully";
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $_SESSION['error'] = "Error deleting company: " . $e->getMessage();
}

header("Location: companies.php");
exit(); 