<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['company_id'])) {
    $company_id = (int)$_POST['company_id'];
    
    // Get company name before deletion for redirect
    $stmt = $conn->prepare("SELECT name FROM companies WHERE id = ?");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $company = $stmt->get_result()->fetch_assoc();
    
    if ($company) {
        // Delete the company (products will be deleted automatically due to foreign key constraint)
        $stmt = $conn->prepare("DELETE FROM companies WHERE id = ?");
        $stmt->bind_param("i", $company_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Company deleted successfully";
        } else {
            $_SESSION['error'] = "Error deleting company";
        }
    } else {
        $_SESSION['error'] = "Company not found";
    }
}

header("Location: companies.php");
exit(); 