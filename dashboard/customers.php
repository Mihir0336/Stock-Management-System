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
            background-color: #34495e;
            border: 1px solid #2c3e50;
            border-radius: 0.5rem;
            color: white;
            transition: transform 0.2s;
        }
        .customer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .customer-header {
            background-color: #2c3e50;
            padding: 1rem;
            border-radius: 0.5rem 0.5rem 0 0;
        }
        .customer-body {
            padding: 1rem;
        }
        .customer-stats {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #2c3e50;
        }
        .stat-item {
            text-align: center;
        }
        .stat-value {
            font-size: 1.2rem;
            font-weight: bold;
            color: #3498db;
        }
        .stat-label {
            font-size: 0.8rem;
            color: #bdc3c7;
        }
        .search-section {
            background-color: #2c3e50;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
        }
        .search-section .form-control {
            background-color: #34495e;
            border: 1px solid #2c3e50;
            color: white;
        }
        .search-section .btn {
            background-color: #3498db;
            border-color: #3498db;
        }
        .search-section .btn:hover {
            background-color: #2980b9;
            border-color: #2980b9;
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
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                <i class="bi bi-plus"></i> Add Customer
            </button>
        </div>

        <!-- Search Form -->
        <div class="search-section">
            <div class="row">
                <div class="col-md-6">
                    <form method="GET" class="row g-2" id="searchForm">
                        <div class="col-md-10">
                            <input type="text" class="form-control" id="searchInput" name="search" 
                                   placeholder="Search by name, phone or village" 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Search</button>
                        </div>
                    </form>
                </div>
            </div>
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

    <!-- Add Customer Modal -->
    <div class="modal fade" id="addCustomerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addCustomerForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Customer Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Village</label>
                            <input type="text" class="form-control" name="village">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Customer</button>
                    </div>
                </form>
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