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
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <div class="row">
            <div class="col-md-4">
                <a href="dashboard/products.php" class="text-decoration-none">
                    <div class="card dashboard-card bg-primary text-white">
                        <div class="card-body">
                            <i class="bi bi-box-seam stats-icon"></i>
                            <h5 class="card-title">Total Products</h5>
                            <h2 class="card-text"><?php echo $stats['total_products']; ?></h2>
                            <small class="opacity-75">View all products</small>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="dashboard/products.php?filter=low_stock" class="text-decoration-none">
                    <div class="card dashboard-card bg-warning text-white">
                        <div class="card-body">
                            <i class="bi bi-exclamation-triangle stats-icon"></i>
                            <h5 class="card-title">Low Stock Items</h5>
                            <h2 class="card-text"><?php echo $stats['low_stock']; ?></h2>
                            <small class="opacity-75">Items need attention</small>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="dashboard/sales.php" class="text-decoration-none">
                    <div class="card dashboard-card bg-success text-white">
                        <div class="card-body">
                            <i class="bi bi-graph-up stats-icon"></i>
                            <h5 class="card-title">Recent Sales</h5>
                            <h2 class="card-text"><?php echo $stats['recent_sales']; ?></h2>
                            <small class="opacity-75">Last 7 days</small>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div class="card recent-transactions">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title">Recent Transactions</h5>
                <a href="dashboard/bills.php" class="btn btn-primary btn-sm">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Bill Number</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Total Amount</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($transaction = $transactions->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4"><?php echo htmlspecialchars($transaction['bill_number']); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($transaction['created_at'])); ?></td>
                                <td>
                                    <span class="badge bg-light text-dark">
                                        <?php echo $transaction['items_count']; ?> items
                                    </span>
                                </td>
                                <td class="fw-bold">₹<?php echo number_format($transaction['total_amount'], 2); ?></td>
                                <td class="text-end pe-4">
                                    <a href="dashboard/view_bill.php?id=<?php echo $transaction['id']; ?>" 
                                       class="btn btn-primary btn-view">
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

    <!-- Products Modal -->
    <div class="modal fade" id="productsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Products by Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="accordion" id="categoriesAccordion">
                        <?php while ($category = $categories->fetch_assoc()): 
                            // Get products for this category
                            $products = $conn->query("
                                SELECT * FROM products 
                                WHERE category_id = {$category['id']} 
                                ORDER BY name
                            ");
                        ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                        data-bs-target="#category<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?> 
                                    <span class="badge bg-primary ms-2"><?php echo $category['product_count']; ?> products</span>
                                    <span class="badge bg-info ms-2"><?php echo $category['total_quantity']; ?> total units</span>
                                </button>
                            </h2>
                            <div id="category<?php echo $category['id']; ?>" class="accordion-collapse collapse" data-bs-parent="#categoriesAccordion">
                                <div class="accordion-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Product Name</th>
                                                    <th>Quantity</th>
                                                    <th>Price per Unit</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($product = $products->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                    <td><?php echo $product['quantity']; ?></td>
                                                    <td>₹<?php echo number_format($product['price_per_unit'], 2); ?></td>
                                                    <td>
                                                        <?php if ($product['quantity'] <= $product['low_stock_threshold']): ?>
                                                            <span class="badge bg-warning">Low Stock</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success">In Stock</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 