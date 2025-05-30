<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the query with search
$query = "
    SELECT 
        c.*,
        COUNT(DISTINCT b.id) as total_bills,
        SUM(b.total_amount) as total_spent,
        MAX(b.created_at) as last_purchase_date,
        COALESCE(SUM(b.total_amount - b.amount_paid), 0) as due_amount
    FROM customers c
    LEFT JOIN bills b ON c.id = b.customer_id
    WHERE 1=1
";

if (!empty($search)) {
    $query .= " AND (c.name LIKE ? OR c.phone LIKE ? OR c.village LIKE ?)";
}

$query .= " GROUP BY c.id ORDER BY c.name ASC";

$stmt = $conn->prepare($query);

if (!empty($search)) {
    $search_param = "%$search%";
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
}

$stmt->execute();
$customers = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - Shiv Agro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .customer-card {
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 0.5rem;
            color: #333333;
            transition: transform 0.2s;
        }
        .customer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .customer-header {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 0.5rem 0.5rem 0 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .customer-body {
            padding: 1rem;
        }
        .customer-stats {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e0e0e0;
        }
        .stat-item {
            text-align: center;
        }
        .stat-value {
            font-size: 1.2rem;
            font-weight: bold;
            color: #007bff;
        }
        .stat-label {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .search-section {
            background-color: #f8f9fa;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            border: 1px solid #e0e0e0;
        }
        .search-section .form-control {
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            color: #333333;
        }
        .search-section .btn {
            background-color: #007bff;
            border-color: #007bff;
        }
        .search-section .btn:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .due-amount {
            color: #e74c3c;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Customers</h2>
        </div>

        <!-- Search Form -->
        <div class="search-section">
            <form method="GET" id="searchForm">
                <div class="d-flex gap-2 align-items-center">
                    <input type="text" class="form-control" id="searchInput" name="search" 
                        placeholder="Search by name, phone or village" 
                        value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                </div>
            </form>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped" id="customersTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Village</th>
                                <th>Total Bills</th>
                                <th>Total Spent</th>
                                <th>Last Purchase</th>
                                <th>Amount Due</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($customer = $customers->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($customer['name']); ?></td>
                                <td><?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($customer['village'] ?? 'N/A'); ?></td>
                                <td><?php echo $customer['total_bills']; ?></td>
                                <td>₹<?php echo number_format($customer['total_spent'] ?? 0, 2); ?></td>
                                <td>
                                    <?php 
                                    if ($customer['last_purchase_date']) {
                                        echo date('d M Y', strtotime($customer['last_purchase_date']));
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($customer['due_amount'] > 0): ?>
                                        <span class="text-danger">₹<?php echo number_format($customer['due_amount'], 2); ?></span>
                                    <?php else: ?>
                                        <span class="text-success">₹0.00</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="customer_details.php?id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add live search functionality
        let searchTimeout;
        const searchInput = document.getElementById('searchInput');
        const searchForm = document.getElementById('searchForm');
        const customersTable = document.getElementById('customersTable');

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const formData = new FormData(searchForm);
                const searchParams = new URLSearchParams(formData);
                
                fetch('customers.php?' + searchParams.toString())
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newTable = doc.querySelector('#customersTable');
                        if (newTable) {
                            customersTable.innerHTML = newTable.innerHTML;
                        }
                    });
            }, 300);
        });

        // Prevent form submission on enter key
        searchForm.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });

        // Prevent default form submission and handle it with JavaScript
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const searchParams = new URLSearchParams(formData);
            
            fetch('customers.php?' + searchParams.toString())
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newTable = doc.querySelector('#customersTable');
                    if (newTable) {
                        customersTable.innerHTML = newTable.innerHTML;
                    }
                });
        });
    </script>
</body>
</html> 