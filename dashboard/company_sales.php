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
$company = isset($_GET['company']) ? $_GET['company'] : '';

// Get all companies
$companies = $conn->query("
    SELECT DISTINCT p.company 
    FROM products p 
    JOIN bill_items bi ON p.id = bi.product_id 
    JOIN bills b ON bi.bill_id = b.id 
    ORDER BY p.company
");

// Build the sales query
$query = "
    SELECT 
        p.id, p.name, p.size, c.name as category_name,
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

if (!empty($company)) {
    $query .= " AND p.company = ?";
    $params[] = $company;
    $types .= 's';
}

$query .= " GROUP BY p.id, p.name, p.size, c.name ORDER BY total_amount DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$sales = $stmt->get_result();

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
    <title>Company Sales Report - Shiv Agro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @media print {
            @page {
                margin: 1cm;
                size: A4;
            }
            body {
                font-size: 12pt;
                line-height: 1.3;
            }
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            /* Hide the navbar in print */
            .navbar {
                display: none !important;
            }
            /* Hide the main page heading in print */
            .company-header h2 {
                display: none !important;
            }
            .card {
                border: none !important;
                box-shadow: none !important;
            }
            .table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin-bottom: 1.5rem !important;
                border: 1px solid #000 !important;
            }
            .table th {
                background-color: #f2f2f2 !important;
                border: 1px solid #000 !important;
                padding: 8px !important;
                font-weight: bold !important;
                text-align: left !important;
                font-size: 10pt !important;
            }
            .table td {
                padding: 6px 8px !important;
                border: 1px solid #000 !important;
                font-size: 10pt !important;
            }
            .table-striped tbody tr:nth-of-type(odd) {
                background-color: #ffffff !important;
            }
            .summary-card {
                border: 1px solid #dee2e6 !important;
                padding: 15px !important;
                margin-bottom: 20px !important;
                page-break-inside: avoid !important;
            }
            .summary-card h5 {
                margin: 0 0 10px 0 !important;
                font-size: 14pt !important;
            }
            .summary-card h2 {
                margin: 0 !important;
                font-size: 18pt !important;
            }
            .report-header {
                text-align: center !important;
                margin-bottom: 30px !important;
                page-break-after: avoid !important;
            }
            .report-header h1 {
                font-size: 24pt !important;
                margin: 0 0 10px 0 !important;
            }
            .report-header h2 {
                font-size: 18pt !important;
                margin: 0 0 20px 0 !important;
                color: #666 !important;
            }
            .report-info {
                display: flex !important;
                justify-content: space-between !important;
                margin-bottom: 20px !important;
                font-size: 11pt !important;
            }
            .report-info p {
                margin: 0 !important;
            }
            .text-primary { color: #0d6efd !important; }
            .text-success { color: #198754 !important; }
            .text-info { color: #0dcaf0 !important; }
            .bg-primary { background-color: #0d6efd !important; }
            .bg-success { background-color: #198754 !important; }
            .bg-info { background-color: #0dcaf0 !important; }
            .text-white { color: #fff !important; }
        }
        .print-only {
            display: none;
        }
        .company-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .company-header h2 {
            margin: 0;
        }
        .company-actions {
            display: flex;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="company-header">
            <h2>Company Sales Report</h2>
            <div class="company-actions no-print">
                <?php if (isset($_GET['company']) && !empty($_GET['company'])): ?>
                    <button onclick="printCompanyReport()" class="btn btn-primary">
                        <i class="bi bi-printer"></i> Print <?php echo htmlspecialchars($_GET['company']); ?> Report
                    </button>
                <?php else: ?>
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="bi bi-printer"></i> Print All Companies
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="card mb-4 no-print">
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
                        <label class="form-label">Company</label>
                        <select class="form-select" name="company" onchange="updateData()" autocomplete="off">
                            <option value="">All Companies</option>
                            <?php while ($comp = $companies->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($comp['company']); ?>" 
                                        <?php echo isset($_GET['company']) && $_GET['company'] === $comp['company'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($comp['company']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <!-- Report Header -->
        <div class="print-only">
            <div class="report-header">
                <h1><?php echo isset($_GET['company']) && !empty($_GET['company']) ? htmlspecialchars($_GET['company']) : 'SHIV AGRO'; ?></h1>
                <h2>Sales Report</h2>
            </div>
            <div class="report-info">
                <div>
                    <p><strong>Period:</strong> <?php echo date('d M Y', strtotime($date_from)); ?> to <?php echo date('d M Y', strtotime($date_to)); ?></p>
                    <?php if (isset($_GET['company']) && !empty($_GET['company'])): ?>
                        <p><strong>Company:</strong> <?php echo htmlspecialchars($_GET['company']); ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <p><strong>Generated:</strong> <?php echo date('d M Y H:i'); ?></p>
                </div>
            </div>
            <div class="summary-section">
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <td><strong>Total Sales</strong></td>
                            <td class="text-end">₹<?php echo number_format($totals['amount'], 2); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Total Quantity Sold</strong></td>
                            <td class="text-end"><?php echo number_format($totals['quantity']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Total Bills</strong></td>
                            <td class="text-end"><?php echo number_format($totals['bills']); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4 no-print">
            <div class="col-md-4">
                <div class="card bg-primary text-white summary-card">
                    <div class="card-body">
                        <h5 class="card-title">Total Sales</h5>
                        <h2 class="card-text">₹<span id="totalSales"><?php echo number_format($totals['amount'], 2); ?></span></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white summary-card">
                    <div class="card-body">
                        <h5 class="card-title">Total Quantity Sold</h5>
                        <h2 class="card-text"><span id="totalQuantity"><?php echo number_format($totals['quantity']); ?></span></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white summary-card">
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
                                    <?php echo htmlspecialchars($sale['name']); ?>
                                    <?php if (!empty($sale['size'])): ?>
                                        <span class="text-muted">(<?php echo htmlspecialchars($sale['size']); ?>)</span>
                                    <?php endif; ?>
                                </td>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function printCompanyReport() {
            window.print();
        }

        function updateData() {
            const form = document.getElementById('filterForm');
            const formData = new FormData(form);
            const params = new URLSearchParams(formData);
            
            // Show loading state
            document.getElementById('salesTableBody').innerHTML = '<tr><td colspan="5" class="text-center">Loading...</td></tr>';
            
            fetch('company_sales.php?' + params.toString())
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

                    // Update print button
                    const companyActions = document.querySelector('.company-actions');
                    const company = formData.get('company');
                    if (company) {
                        companyActions.innerHTML = `
                            <button onclick="printCompanyReport()" class="btn btn-primary">
                                <i class="bi bi-printer"></i> Print ${company} Report
                            </button>
                        `;
                    } else {
                        companyActions.innerHTML = `
                            <button onclick="window.print()" class="btn btn-primary">
                                <i class="bi bi-printer"></i> Print All Companies
                            </button>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('salesTableBody').innerHTML = 
                        '<tr><td colspan="5" class="text-center text-danger">Error loading data</td></tr>';
                });
        }
    </script>
</body>
</html> 