<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

// Get summary statistics
$stats = [
    'total_products' => 0,
    'low_stock' => 0,
    'recent_sales' => 0
];

// Total products
$result = $conn->query("SELECT COUNT(*) as count FROM products");
$stats['total_products'] = $result->fetch_assoc()['count'];

// Total customers
$result = $conn->query("SELECT COUNT(*) as count FROM customers");
$total_customers = $result->fetch_assoc()['count'];

// Total bills
$result = $conn->query("SELECT COUNT(*) as count FROM bills");
$total_bills = $result->fetch_assoc()['count'];

// Total sales
$result = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM bills");
$total_sales = $result->fetch_assoc()['total'];

// Low stock products
$result = $conn->query("SELECT COUNT(*) as count FROM products WHERE quantity <= low_stock_threshold");
$stats['low_stock'] = $result->fetch_assoc()['count'];

// Recent sales (last 7 days)
$result = $conn->query("SELECT COUNT(*) as count FROM bills WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['recent_sales'] = $result->fetch_assoc()['count'];

// Get recent transactions
$transactions = $conn->query("
    SELECT b.*, COUNT(bi.id) as items_count 
    FROM bills b 
    LEFT JOIN bill_items bi ON b.id = bi.bill_id 
    GROUP BY b.id 
    ORDER BY b.created_at DESC 
    LIMIT 10
");

// Get all categories with their products
$categories = $conn->query("
    SELECT c.id, c.name, 
           COUNT(p.id) as product_count,
           SUM(p.quantity) as total_quantity
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id
    WHERE c.name IN ('Fertilizers', 'Pesticides', 'Seeds')
    GROUP BY c.id
    ORDER BY FIELD(c.name, 'Fertilizers', 'Pesticides', 'Seeds')
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Shiv Agro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .dashboard-card {
            background-color: #ffffff;
            border: none;
            border-radius: 1rem;
            padding: 1.5rem;
            height: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #007bff, #00bcd4);
        }
        .stats-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #007bff, #00bcd4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-block;
        }
        .card-title {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: #6c757d;
            font-weight: 600;
        }
        .card-text {
            font-size: 1.8rem;
            font-weight: 700;
            color: #2c3e50;
            margin: 0;
        }
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }
        .card-header {
            background-color: #ffffff;
            border-bottom: 1px solid #e9ecef;
            padding: 1rem 1.5rem;
            border-radius: 1rem 1rem 0 0 !important;
        }
        .card-title {
            color: #2c3e50;
            font-weight: 600;
        }
        .table {
            margin-bottom: 0;
        }
        .table th {
            background-color: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
            border-top: none;
        }
        .table td {
            vertical-align: middle;
            color: #2c3e50;
        }
        .badge {
            padding: 0.5em 0.8em;
            font-weight: 500;
        }
        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            padding: 0.5rem 1rem;
            font-weight: 500;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #0056b3, #004494);
            transform: translateY(-1px);
        }
        .btn-warning {
            background: linear-gradient(135deg, #ffc107, #ff9800);
            border: none;
            color: #fff;
            padding: 0.5rem 1rem;
            font-weight: 500;
        }
        .btn-warning:hover {
            background: linear-gradient(135deg, #ff9800, #f57c00);
            color: #fff;
            transform: translateY(-1px);
        }
        .stock-low {
            color: #dc3545;
            font-weight: 600;
        }
        .stock-ok {
            color: #28a745;
            font-weight: 600;
        }
        /* Products Modal Styles */
        .products-modal .modal-content {
            border: none;
            border-radius: 1rem;
        }
        .products-modal .modal-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border-radius: 1rem 1rem 0 0;
            padding: 1.5rem;
        }
        .products-modal .modal-title {
            font-weight: 600;
        }
        .products-modal .btn-close {
            filter: brightness(0) invert(1);
        }
        .products-modal .table th {
            background-color: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
        }
        .products-modal .table td {
            vertical-align: middle;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="dashboard-card" onclick="window.location.href='dashboard/customers.php'">
                    <div class="stats-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <h5 class="card-title">Total Customers</h5>
                    <p class="card-text"><?php echo $total_customers; ?></p>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="dashboard-card" data-bs-toggle="modal" data-bs-target="#productsModal">
                    <div class="stats-icon">
                        <i class="bi bi-box"></i>
                    </div>
                    <h5 class="card-title">Total Products</h5>
                    <p class="card-text"><?php echo $stats['total_products']; ?></p>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="dashboard-card" onclick="window.location.href='dashboard/bills.php'">
                    <div class="stats-icon">
                        <i class="bi bi-receipt"></i>
                    </div>
                    <h5 class="card-title">Total Bills</h5>
                    <p class="card-text"><?php echo $total_bills; ?></p>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="dashboard-card" onclick="window.location.href='dashboard/company_sales.php'">
                    <div class="stats-icon">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <h5 class="card-title">Total Sales</h5>
                    <p class="card-text">₹<?php echo number_format($total_sales, 0); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="container mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Recent Transactions</h5>
                <a href="dashboard/bills.php" class="btn btn-primary btn-sm">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Bill Number</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Total Amount</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $recent_bills = $conn->query("
                                SELECT b.*, c.name as customer_name, COUNT(bi.id) as items_count 
                                FROM bills b 
                                LEFT JOIN customers c ON b.customer_id = c.id
                                LEFT JOIN bill_items bi ON b.id = bi.bill_id 
                                GROUP BY b.id 
                                ORDER BY b.created_at DESC 
                                LIMIT 10
                            ");
                            while ($bill = $recent_bills->fetch_assoc()): 
                            ?>
                            <tr>
                                <td class="ps-4"><?php echo htmlspecialchars($bill['bill_number']); ?></td>
                                <td><?php echo date('d M Y, h:i A', strtotime($bill['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($bill['customer_name']); ?></td>
                                <td>
                                    <span class="badge bg-light text-dark">
                                        <?php echo $bill['items_count']; ?> items
                                    </span>
                                </td>
                                <td class="fw-bold">₹<?php echo number_format($bill['total_amount'], 2); ?></td>
                                <td class="text-end pe-4">
                                    <a href="dashboard/view_bill.php?id=<?php echo $bill['id']; ?>" 
                                       class="btn btn-primary btn-sm">
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

    <!-- Low Stock Alert -->
    <div class="container mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Low Stock Alert</h5>
                <a href="dashboard/company_products.php?filter=low_stock" class="btn btn-warning btn-sm">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Product</th>
                                <th>Category</th>
                                <th>Company</th>
                                <th>Available Stock</th>
                                <th>Low Stock Threshold</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $low_stock_products = $conn->query("
                                SELECT p.*, c.name as category_name 
                                FROM products p 
                                JOIN categories c ON p.category_id = c.id 
                                WHERE p.quantity <= p.low_stock_threshold 
                                ORDER BY p.quantity ASC 
                                LIMIT 10
                            ");
                            while ($product = $low_stock_products->fetch_assoc()): 
                            ?>
                            <tr>
                                <td class="ps-4"><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                <td><?php echo htmlspecialchars($product['company']); ?></td>
                                <td>
                                    <span class="badge bg-danger">
                                        <?php echo $product['quantity']; ?> units
                                    </span>
                                </td>
                                <td><?php echo $product['low_stock_threshold']; ?> units</td>
                                <td class="text-end pe-4">
                                    <a href="dashboard/company_products.php?edit=<?php echo $product['id']; ?>" 
                                       class="btn btn-warning btn-sm">
                                        <i class="bi bi-pencil"></i> Update Stock
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

    <!-- Products Modal -->
    <div class="modal fade products-modal" id="productsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">All Products</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Company</th>
                                    <th>Size</th>
                                    <th>Stock</th>
                                    <th>Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $products_query = "
                                    SELECT p.*, c.name as category_name 
                                    FROM products p 
                                    JOIN categories c ON p.category_id = c.id 
                                    ORDER BY p.company, p.name, p.size
                                ";
                                $products_result = $conn->query($products_query);
                                while ($product = $products_result->fetch_assoc()):
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['company']); ?></td>
                                    <td><?php echo htmlspecialchars($product['size']); ?></td>
                                    <td>
                                        <span class="<?php echo $product['quantity'] <= 10 ? 'stock-low' : 'stock-ok'; ?>">
                                            <?php echo $product['quantity']; ?>
                                        </span>
                                    </td>
                                    <td>₹<?php echo number_format($product['price_per_unit'], 2); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="dashboard/company_products.php" class="btn btn-primary">Manage Products</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 