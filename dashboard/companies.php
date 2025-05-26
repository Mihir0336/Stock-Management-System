<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Get all companies with their statistics
$query = "
    SELECT 
        c.*,
        COUNT(p.id) as total_products,
        COALESCE(SUM(p.quantity), 0) as total_stock,
        COALESCE(SUM(p.quantity * p.price_per_unit), 0) as total_value,
        COUNT(CASE WHEN p.quantity <= p.low_stock_threshold THEN 1 END) as low_stock_count
    FROM companies c
    LEFT JOIN products p ON c.name = p.company
    GROUP BY c.id
    ORDER BY c.name
";
$companies = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Companies - Shiv Agro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .company-card {
            background-color: #34495e;
            border: 1px solid #2c3e50;
            border-radius: 0.5rem;
            color: white;
            margin-bottom: 1rem;
            transition: transform 0.2s;
        }
        .company-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .company-header {
            background-color: #2c3e50;
            padding: 1rem;
            border-radius: 0.5rem 0.5rem 0 0;
        }
        .company-body {
            padding: 1rem;
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
            <h2>Companies</h2>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCompanyModal">
                <i class="bi bi-plus"></i> Add Company
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

        <div class="row">
            <?php while ($company = $companies->fetch_assoc()): ?>
            <div class="col-md-4">
                <div class="company-card">
                    <div class="company-header">
                        <h5 class="mb-0"><?php echo htmlspecialchars($company['name']); ?></h5>
                    </div>
                    <div class="company-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <div class="stat-value"><?php echo $company['total_products']; ?></div>
                                <div class="stat-label">Products</div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="stat-value"><?php echo number_format($company['total_stock']); ?></div>
                                <div class="stat-label">Total Stock</div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="stat-value">₹<?php echo number_format($company['total_value'], 0); ?></div>
                                <div class="stat-label">Total Value</div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="stat-value <?php echo $company['low_stock_count'] > 0 ? 'stock-low' : 'stock-ok'; ?>">
                                    <?php echo $company['low_stock_count']; ?>
                                </div>
                                <div class="stat-label">Low Stock</div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <a href="company_products.php?company=<?php echo urlencode($company['name']); ?>" 
                               class="btn btn-sm btn-primary">
                                <i class="bi bi-box"></i> View Products
                            </a>
                            <form method="POST" action="delete_company.php" class="d-inline" 
                                  onsubmit="return confirm('Are you sure you want to delete this company? This will also delete all its products.');">
                                <input type="hidden" name="company_id" value="<?php echo $company['id']; ?>">
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

    <!-- Add Company Modal -->
    <div class="modal fade" id="addCompanyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Company</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="add_company.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Company Name</label>
                            <input type="text" class="form-control" name="company_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Company</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 