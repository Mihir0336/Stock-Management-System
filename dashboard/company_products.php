<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_GET['company'])) {
    header("Location: companies.php");
    exit();
}

$company = $_GET['company'];

// Get all products for this company
$query = "
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.company = ?
    ORDER BY p.name, p.size
";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $company);
$stmt->execute();
$products = $stmt->get_result();

// Calculate company statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_products,
        COALESCE(SUM(quantity), 0) as total_stock,
        COALESCE(SUM(quantity * price_per_unit), 0) as total_value,
        COUNT(CASE WHEN quantity <= low_stock_threshold THEN 1 END) as low_stock_count
    FROM products 
    WHERE company = ?
";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("s", $company);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($company); ?> - Products - Shiv Agro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .stats-card {
            background-color: #34495e;
            border: 1px solid #2c3e50;
            border-radius: 0.5rem;
            color: white;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #3498db;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #bdc3c7;
        }
        .product-card {
            background-color: #34495e;
            border: 1px solid #2c3e50;
            border-radius: 0.5rem;
            color: white;
            margin-bottom: 1rem;
            transition: transform 0.2s;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .product-header {
            background-color: #2c3e50;
            padding: 1rem;
            border-radius: 0.5rem 0.5rem 0 0;
        }
        .product-body {
            padding: 1rem;
        }
        .stock-low {
            color: #e74c3c;
        }
        .stock-ok {
            color: #2ecc71;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><?php echo htmlspecialchars($company); ?></h2>
                <a href="companies.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Companies
                </a>
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                <i class="bi bi-plus"></i> Add Product
            </button>
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

        <!-- Company Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="stat-value"><?php echo $stats['total_products']; ?></div>
                    <div class="stat-label">Total Products</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="stat-value"><?php echo number_format($stats['total_stock']); ?></div>
                    <div class="stat-label">Total Stock</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="stat-value">₹<?php echo number_format($stats['total_value'], 0); ?></div>
                    <div class="stat-label">Total Value</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="stat-value <?php echo $stats['low_stock_count'] > 0 ? 'stock-low' : 'stock-ok'; ?>">
                        <?php echo $stats['low_stock_count']; ?>
                    </div>
                    <div class="stat-label">Low Stock Items</div>
                </div>
            </div>
        </div>

        <!-- Products List -->
        <div class="row">
            <?php while ($product = $products->fetch_assoc()): ?>
            <div class="col-md-4">
                <div class="product-card">
                    <div class="product-header">
                        <h5 class="mb-0">
                            <?php echo htmlspecialchars($product['name']); ?>
                            <?php if (!empty($product['size'])): ?>
                                <span class="text-muted">(<?php echo htmlspecialchars($product['size']); ?>)</span>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="product-body">
                        <div class="mb-2">
                            <strong>Category:</strong> <?php echo htmlspecialchars($product['category_name']); ?>
                        </div>
                        <div class="mb-2">
                            <strong>Price:</strong> ₹<?php echo number_format($product['price_per_unit'], 2); ?>
                        </div>
                        <div class="mb-2">
                            <strong>Stock:</strong> 
                            <span class="<?php echo $product['quantity'] <= $product['low_stock_threshold'] ? 'stock-low' : 'stock-ok'; ?>">
                                <?php echo number_format($product['quantity']); ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between mt-3">
                            <a href="update_product.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <form method="POST" action="delete_product.php" class="d-inline" 
                                  onsubmit="return confirm('Are you sure you want to delete this product?');">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="add_product.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="company" value="<?php echo htmlspecialchars($company); ?>">
                        <div class="mb-3">
                            <label class="form-label">Product Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Size</label>
                            <input type="text" class="form-control" name="size" placeholder="e.g., 500ml, 1L, 5kg">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-control" name="category_id" required>
                                <?php
                                $categories = $conn->query("
                                    SELECT * FROM categories 
                                    WHERE name IN ('Fertilizers', 'Pesticides', 'Seeds')
                                    ORDER BY FIELD(name, 'Fertilizers', 'Pesticides', 'Seeds')
                                ");
                                while ($category = $categories->fetch_assoc()) {
                                    echo "<option value='{$category['id']}'>{$category['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Price per Unit</label>
                            <input type="number" class="form-control" name="price_per_unit" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control" name="quantity" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Low Stock Threshold</label>
                            <input type="number" class="form-control" name="low_stock_threshold" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 