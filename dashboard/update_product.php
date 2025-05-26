<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Handle GET request - display edit form
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['id'])) {
        header("Location: companies.php");
        exit();
    }

    $product_id = (int)$_GET['id'];
    
    // Get product details
    $stmt = $conn->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.id = ?
    ");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();

    if (!$product) {
        $_SESSION['error'] = "Product not found";
        header("Location: companies.php");
        exit();
    }

    // Get all categories for dropdown
    $categories = $conn->query("
        SELECT * FROM categories 
        WHERE name IN ('Fertilizers', 'Pesticides', 'Seeds')
        ORDER BY FIELD(name, 'Fertilizers', 'Pesticides', 'Seeds')
    ");

    // Display edit form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Edit Product - Shiv Agro</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    </head>
    <body>
        <?php include '../includes/navbar.php'; ?>

        <div class="container mt-4">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0">Edit Product</h4>
                        </div>
                        <div class="card-body">
                            <form action="update_product.php" method="POST">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                
                                <div class="mb-3">
                                    <label for="name" class="form-label">Product Name</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($product['name']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="company" class="form-label">Company</label>
                                    <input type="text" class="form-control" id="company" name="company" 
                                           value="<?php echo htmlspecialchars($product['company']); ?>" readonly>
                                </div>

                                <div class="mb-3">
                                    <label for="category_id" class="form-label">Category</label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <?php while ($category = $categories->fetch_assoc()): ?>
                                            <option value="<?php echo $category['id']; ?>" 
                                                    <?php echo $category['id'] == $product['category_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="size" class="form-label">Size</label>
                                    <input type="text" class="form-control" id="size" name="size" 
                                           value="<?php echo htmlspecialchars($product['size']); ?>">
                                </div>

                                <div class="mb-3">
                                    <label for="price_per_unit" class="form-label">Price per Unit (₹)</label>
                                    <input type="number" step="0.01" class="form-control" id="price_per_unit" 
                                           name="price_per_unit" value="<?php echo $product['price_per_unit']; ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="quantity" class="form-label">Quantity</label>
                                    <input type="number" step="0.01" class="form-control" id="quantity" 
                                           name="quantity" value="<?php echo $product['quantity']; ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="low_stock_threshold" class="form-label">Low Stock Threshold</label>
                                    <input type="number" class="form-control" id="low_stock_threshold" 
                                           name="low_stock_threshold" value="<?php echo $product['low_stock_threshold']; ?>" required>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="company_products.php?company=<?php echo urlencode($product['company']); ?>" 
                                       class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary">Update Product</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit();
}

// Handle POST request - process update
try {
    // Validate input
    $product_id = (int)$_POST['product_id'];
    $name = trim($_POST['name']);
    $category_id = (int)$_POST['category_id'];
    $size = trim($_POST['size']);
    $quantity = (float)$_POST['quantity'];
    $price_per_unit = (float)$_POST['price_per_unit'];
    $low_stock_threshold = (int)$_POST['low_stock_threshold'];

    if (empty($name)) {
        throw new Exception('Product name is required');
    }

    if ($quantity < 0) {
        throw new Exception('Quantity cannot be negative');
    }

    if ($price_per_unit <= 0) {
        throw new Exception('Price must be greater than zero');
    }

    if ($low_stock_threshold < 0) {
        throw new Exception('Low stock threshold cannot be negative');
    }

    // Get current product details
    $stmt = $conn->prepare("SELECT company FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();

    if (!$product) {
        throw new Exception('Product not found');
    }

    // Update product
    $stmt = $conn->prepare("
        UPDATE products 
        SET name = ?, 
            category_id = ?, 
            size = ?, 
            quantity = ?, 
            price_per_unit = ?, 
            low_stock_threshold = ? 
        WHERE id = ?
    ");
    
    $stmt->bind_param(
        "sisidii",
        $name,
        $category_id,
        $size,
        $quantity,
        $price_per_unit,
        $low_stock_threshold,
        $product_id
    );
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Product updated successfully";
        header("Location: company_products.php?company=" . urlencode($product['company']));
    } else {
        throw new Exception('Error updating product: ' . $conn->error);
    }

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: update_product.php?id=" . $product_id);
}
exit(); 