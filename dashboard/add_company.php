<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name']);
    
    if (empty($company_name)) {
        $_SESSION['error'] = "Company name cannot be empty";
        header("Location: companies.php");
        exit();
    }

    // Check if company already exists
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM companies WHERE name = ?");
    $check_stmt->bind_param("s", $company_name);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $count = $result->fetch_assoc()['count'];

    if ($count > 0) {
        $_SESSION['error'] = "Company already exists";
        header("Location: companies.php");
        exit();
    }

    // Add the company
    $stmt = $conn->prepare("INSERT INTO companies (name) VALUES (?)");
    $stmt->bind_param("s", $company_name);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Company added successfully";
    } else {
        $_SESSION['error'] = "Error adding company";
    }
    
    header("Location: companies.php");
    exit();
} else {
    header("Location: companies.php");
    exit();
} 