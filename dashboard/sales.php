<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Get filter parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-7 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$category = isset($_GET['category']) ? $_GET['category'] : '';

// Build the sales query
$query = "
    SELECT p.id, p.name, p.company, c.name as category_name,
           SUM(bi.quantity) as total_quantity,
           SUM(bi.quantity * bi.price_per_unit) as total_amount,
           COUNT(DISTINCT b.id) as bill_count
    FROM bill_items bi
    JOIN products p ON bi.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    JOIN bills b ON bi.bill_id = b.id
    WHERE DATE(b.created_at) BETWEEN ? AND ?
";

$params = [$date_from, $date_to];
$types = 'ss';

if (!empty($category)) {
    $query .= " AND c.name = ?";
    $params[] = $category;
    $types .= 's';
}

$query .= " GROUP BY p.id, p.name, p.company, c.name ORDER BY total_amount DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$sales = $stmt->get_result();

// Get categories for filter
$categories = $conn->query("
    SELECT DISTINCT c.name 
    FROM categories c 
    JOIN products p ON c.id = p.category_id 
    JOIN bill_items bi ON p.id = bi.product_id 
    WHERE c.name IN ('Fertilizers', 'Pesticides', 'Seeds')
    ORDER BY FIELD(c.name, 'Fertilizers', 'Pesticides', 'Seeds')
");

// Calculate totals
$totals = [
    'quantity' => 0,
    'amount' => 0,
    'bills' => 0
];

while ($sale = $sales->fetch_assoc()) {
    $totals['quantity'] += $sale['total_quantity'];
    $totals['amount'] += $sale['total_amount'];
    $totals['bills'] += $sale['bill_count'];
}
$sales->data_seek(0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report - Shiv Agro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container mt-4">
        <h2 class="mb-4">Sales Report</h2>

        <!-- Filter Form -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3" id="filterForm">
                    <div class="col-md-3">
                        <label class="form-label">From Date</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>" onchange="updateData()" autocomplete="off">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To Date</label>
                        <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>" onchange="updateData()" autocomplete="off">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" name="category" onchange="updateData()" autocomplete="off">
                            <option value="">All Categories</option>
                            <?php while ($cat = $categories->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($cat['name']); ?>" 
                                        <?php echo $category === $cat['name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Sales</h5>
                        <h2 class="card-text">₹<span id="totalSales"><?php echo number_format($totals['amount'], 2); ?></span></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Quantity Sold</h5>
                        <h2 class="card-text"><span id="totalQuantity"><?php echo number_format($totals['quantity']); ?></span></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Bills</h5>
                        <h2 class="card-text"><span id="totalBills"><?php echo number_format($totals['bills']); ?></span></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Company</th>
                                <th>Category</th>
                                <th>Quantity Sold</th>
                                <th>Total Amount</th>
                                <th>Bills</th>
                            </tr>
                        </thead>
                        <tbody id="salesTableBody">
                            <?php while ($sale = $sales->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <a href="#" class="product-details" 
                                       data-id="<?php echo $sale['id']; ?>"
                                       data-name="<?php echo htmlspecialchars($sale['name']); ?>"
                                       data-date-from="<?php echo $date_from; ?>"
                                       data-date-to="<?php echo $date_to; ?>">
                                        <?php echo htmlspecialchars($sale['name']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($sale['company']); ?></td>
                                <td><?php echo htmlspecialchars($sale['category_name']); ?></td>
                                <td><?php echo number_format($sale['total_quantity']); ?></td>
                                <td>₹<?php echo number_format($sale['total_amount'], 2); ?></td>
                                <td><?php echo number_format($sale['bill_count']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Details Modal -->
    <div class="modal fade" id="productDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Product Sales Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h4 id="modalProductName" class="mb-4"></h4>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Bill Number</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Quantity</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody id="productDetailsBody">
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateData() {
            const form = document.getElementById('filterForm');
            const formData = new FormData(form);
            const params = new URLSearchParams(formData);
            
            // Show loading state
            document.getElementById('salesTableBody').innerHTML = '<tr><td colspan="6" class="text-center">Loading...</td></tr>';
            
            fetch('sales.php?' + params.toString())
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    // Update table
                    const newTableBody = doc.querySelector('#salesTableBody');
                    if (newTableBody) {
                        document.getElementById('salesTableBody').innerHTML = newTableBody.innerHTML;
                    }
                    
                    // Update summary cards
                    const totalSales = doc.querySelector('#totalSales');
                    const totalQuantity = doc.querySelector('#totalQuantity');
                    const totalBills = doc.querySelector('#totalBills');
                    
                    if (totalSales) document.getElementById('totalSales').textContent = totalSales.textContent;
                    if (totalQuantity) document.getElementById('totalQuantity').textContent = totalQuantity.textContent;
                    if (totalBills) document.getElementById('totalBills').textContent = totalBills.textContent;
                    
                    // Reattach event listeners to product details links
                    attachProductDetailsListeners();
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('salesTableBody').innerHTML = 
                        '<tr><td colspan="6" class="text-center text-danger">Error loading data</td></tr>';
                });
        }

        function attachProductDetailsListeners() {
            document.querySelectorAll('.product-details').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const productId = this.dataset.id;
                    const productName = this.dataset.name;
                    const dateFrom = this.dataset.dateFrom;
                    const dateTo = this.dataset.dateTo;
                    
                    // Set modal title
                    document.getElementById('modalProductName').textContent = productName;
                    
                    // Show loading state
                    const tbody = document.getElementById('productDetailsBody');
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center">Loading...</td></tr>';
                    productDetailsModal.show();
                    
                    // Fetch product details
                    fetch(`get_product_sales.php?id=${productId}&date_from=${dateFrom}&date_to=${dateTo}`)
                        .then(response => {
                            if (!response.ok) {
                                return response.json().then(err => Promise.reject(err));
                            }
                            return response.json();
                        })
                        .then(data => {
                            tbody.innerHTML = '';
                            
                            if (data.length === 0) {
                                tbody.innerHTML = '<tr><td colspan="5" class="text-center">No sales found for this period</td></tr>';
                                return;
                            }
                            
                            data.forEach(sale => {
                                const tr = document.createElement('tr');
                                tr.innerHTML = `
                                    <td>${sale.bill_number}</td>
                                    <td>${sale.date}</td>
                                    <td>${sale.customer_name}</td>
                                    <td>${sale.quantity}</td>
                                    <td>₹${parseFloat(sale.amount).toFixed(2)}</td>
                                `;
                                tbody.appendChild(tr);
                            });
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">
                                Error loading data: ${error.message || 'Unknown error'}
                            </td></tr>`;
                        });
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const productDetailsModal = new bootstrap.Modal(document.getElementById('productDetailsModal'));
            attachProductDetailsListeners();
        });
    </script>
</body>
</html> 