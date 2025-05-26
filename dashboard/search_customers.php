<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get the search term from POST data
$data = json_decode(file_get_contents('php://input'), true);
$search = $data['search'] ?? '';

if (empty($search)) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

// Search for customers with due amount
$query = "
    SELECT c.*, 
           COALESCE(SUM(b.total_amount - b.amount_paid), 0) as due_amount
    FROM customers c
    LEFT JOIN bills b ON c.id = b.customer_id
    WHERE c.name LIKE ? OR c.phone LIKE ? OR c.village LIKE ?
    GROUP BY c.id
    ORDER BY c.name
    LIMIT 10
";

$stmt = $conn->prepare($query);
$searchTerm = "%$search%";
$stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$customers = [];
while ($row = $result->fetch_assoc()) {
    $customers[] = $row;
}

header('Content-Type: application/json');
echo json_encode($customers); 