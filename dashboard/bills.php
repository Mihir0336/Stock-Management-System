<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Get all products with their categories and companies
$products_query = "
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.quantity > 0 
    ORDER BY p.company, p.name, p.size
";
$products = $conn->query($products_query);

// Group products by company
$grouped_products = [];
while ($product = $products->fetch_assoc()) {
    $company = $product['company'];
    if (!isset($grouped_products[$company])) {
        $grouped_products[$company] = [];
    }
    $grouped_products[$company][] = $product;
}

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Build the query with search and date filters
$query = "
    SELECT b.*, c.name as customer_name, c.phone as customer_phone, c.village as customer_village,
           COUNT(bi.id) as items_count,
           GROUP_CONCAT(DISTINCT p.company) as companies
    FROM bills b 
    LEFT JOIN customers c ON b.customer_id = c.id
    LEFT JOIN bill_items bi ON b.id = bi.bill_id 
    LEFT JOIN products p ON bi.product_id = p.id
    WHERE 1=1
";

$params = [];
$types = '';

if (!empty($search)) {
    $search_param = "%$search%";
    $query .= " AND (b.bill_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR c.village LIKE ? OR p.company LIKE ?)";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    $types .= 'sssss';
}

if (!empty($date_from)) {
    $query .= " AND DATE(b.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $query .= " AND DATE(b.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$query .= " GROUP BY b.id ORDER BY b.created_at DESC LIMIT 50";

// Prepare and execute the query
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$bills = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bills - Shiv Agro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <!-- <style>
        .search-section {
            background-color: #f8f9fa;
            padding: 0.25rem 0.5rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            border: 1px solid #e0e0e0;
        }
        .search-section .row,
        .search-section .col-md-6,
        .search-section .col-md-10,
        .search-section .col-md-2,
        .search-section form {
            margin: 0 !important;
            padding: 0 !important;
        }
        .search-section .g-2,
        .search-section .g-3 {
            --bs-gutter-x: 0.25rem;
            --bs-gutter-y: 0.25rem;
        }
    </style> -->
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Bills Management</h2>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBillModal">
                <i class="bi bi-plus"></i> Create New Bill
            </button>
        </div>

        <!-- Search and Date Filter Forms -->
        <div class="search-section">
            <form method="GET" id="searchForm">
                <div class="d-flex gap-2 align-items-center">
                    <input type="text" class="form-control" id="searchInput" name="search" 
                        placeholder="Search by bill number, customer name, phone or village" 
                        value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                    <input type="date" class="form-control" id="dateFrom" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" autocomplete="off">
                    <input type="date" class="form-control" id="dateTo" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" autocomplete="off">
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped" id="billsTable">
                        <thead>
                            <tr>
                                <th>Bill Number</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Subtotal</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($bill = $bills->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($bill['bill_number']); ?></td>
                                <td><?php echo htmlspecialchars($bill['customer_name']); ?></td>
                                <td><?php echo date('d M Y', strtotime($bill['created_at'])); ?></td>
                                <td>₹<?php echo number_format($bill['subtotal'], 2); ?></td>
                                <td>₹<?php echo number_format($bill['total_amount'], 2); ?></td>
                                <td>
                                    <?php if ($bill['payment_status'] == 'paid'): ?>
                                        <span class="badge bg-success">Paid</span>
                                    <?php elseif ($bill['payment_status'] == 'partial'): ?>
                                        <span class="badge bg-warning">Partial</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Unpaid</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="view_bill.php?id=<?php echo $bill['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    <button type="button" class="btn btn-sm btn-warning edit-bill" 
                                            data-id="<?php echo $bill['id']; ?>"
                                            data-bill-number="<?php echo htmlspecialchars($bill['bill_number']); ?>">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Bill Modal -->
    <div class="modal fade" id="createBillModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Bill</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="createBillForm" action="create_bill.php" method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8">
                                <!-- Products Section -->
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Select Products</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php foreach ($grouped_products as $company => $company_products): ?>
                                        <div class="company-section">
                                            <div class="company-header">
                                                <h5 class="mb-0"><?php echo htmlspecialchars($company); ?></h5>
                                            </div>
                                            <ul class="product-list">
                                                <?php foreach ($company_products as $product): ?>
                                                <li class="product-item">
                                                    <div class="product-info">
                                                        <h6 class="product-name">
                                                            <?php echo htmlspecialchars($product['name']); ?>
                                                            <?php if (!empty($product['size'])): ?>
                                                                <span class="product-size">(<?php echo htmlspecialchars($product['size']); ?>)</span>
                                                            <?php endif; ?>
                                                        </h6>
                                                        <div class="product-details">
                                                            <span class="product-price">₹<?php echo number_format($product['price_per_unit'], 2); ?></span>
                                                            <span class="product-stock <?php echo $product['quantity'] <= $product['low_stock_threshold'] ? 'low' : ''; ?>">
                                                                Stock: <?php echo $product['quantity']; ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="product-quantity">
                                                        <div class="input-group">
                                                            <button type="button" class="btn btn-outline-secondary quantity-btn minus-btn" 
                                                                    data-product-id="<?php echo $product['id']; ?>"
                                                                    data-max="<?php echo $product['quantity']; ?>">-</button>
                                                            <input type="number" class="form-control product-quantity" 
                                                                   name="products[<?php echo $product['id']; ?>][quantity]" 
                                                                   min="0" max="<?php echo $product['quantity']; ?>" 
                                                                   value="0" autocomplete="off"
                                                                   data-price="<?php echo $product['price_per_unit']; ?>"
                                                                   data-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                                   data-size="<?php echo htmlspecialchars($product['size']); ?>"
                                                                   data-product-id="<?php echo $product['id']; ?>">
                                                            <button type="button" class="btn btn-outline-secondary quantity-btn plus-btn"
                                                                    data-product-id="<?php echo $product['id']; ?>"
                                                                    data-max="<?php echo $product['quantity']; ?>">+</button>
                                                        </div>
                                                    </div>
                                                </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <!-- Bill Summary -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Bill Summary</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Customer Name</label>
                                            <input type="text" class="form-control" name="customer_name" required autocomplete="off">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Phone Number</label>
                                            <input type="tel" class="form-control" name="phone_number" autocomplete="off">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Address</label>
                                            <textarea class="form-control" name="address" rows="3" autocomplete="off"></textarea>
                                        </div>
                                        <hr>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Subtotal:</span>
                                            <span id="subtotal">₹0.00</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-3">
                                            <strong>Total:</strong>
                                            <strong id="total">₹0.00</strong>
                                        </div>
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="bi bi-save"></i> Save Bill
                                        </button>
                                    </div>
                            </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="createBillBtn" disabled>Create Bill</button>
                    </div>
                </form>
            </div>
                            </div>
                        </div>

    <!-- Edit Bill Modal -->
    <div class="modal fade" id="editBillModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Bill</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editBillForm" action="update_bill.php" method="POST">
                    <input type="hidden" name="bill_id" id="edit_bill_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Bill Number</label>
                            <input type="text" class="form-control" id="edit_bill_number" readonly>
                        </div>
                        <div class="table-responsive">
                            <table class="table" id="editBillItemsTable">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Price</th>
                                        <th>Total</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let billItems = [];

        function updateTotals() {
            let subtotal = 0;

            billItems.forEach(item => {
                const itemSubtotal = item.quantity * item.price;
                subtotal += itemSubtotal;
            });

            document.getElementById('subtotal').textContent = subtotal.toFixed(2);
            document.getElementById('total').textContent = subtotal.toFixed(2);
            document.getElementById('createBillBtn').disabled = billItems.length === 0;
        }

        function addBillItem(productId, name, quantity, price, category) {
            const subtotal = quantity * price;
            
            billItems.push({ 
                productId, 
                name, 
                quantity, 
                price, 
                category,
                subtotal 
            });
            
            const tbody = document.querySelector('#billItemsTable tbody');
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${name}</td>
                <td>${quantity}</td>
                <td>₹${price.toFixed(2)}</td>
                <td>₹${subtotal.toFixed(2)}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger remove-item" data-index="${billItems.length - 1}">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
            updateTotals();
        }

        document.getElementById('createBillForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get all products with quantity > 0
            const items = [];
            document.querySelectorAll('.product-quantity').forEach(input => {
                const quantity = parseInt(input.value) || 0;
                if (quantity > 0) {
                    items.push({
                        productId: input.dataset.productId,
                        name: input.dataset.name,
                        quantity: quantity,
                        price: parseFloat(input.dataset.price),
                        size: input.dataset.size
                    });
                }
            });

            if (items.length === 0) {
                alert('Please select at least one product');
                return;
            }

            const formData = new FormData();
            formData.append('items', JSON.stringify(items));
            formData.append('customer_name', document.querySelector('input[name="customer_name"]').value);
            formData.append('customer_phone', document.querySelector('input[name="phone_number"]').value);
            formData.append('customer_village', document.querySelector('textarea[name="address"]').value);
            
            fetch('create_bill.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'view_bill.php?id=' + data.bill_id;
                } else {
                    alert('Error creating bill: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error creating bill. Please try again.');
            });
        });

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-item')) {
                const index = parseInt(e.target.dataset.index);
                billItems.splice(index, 1);
                e.target.closest('tr').remove();
                updateTotals();
            }
        });

        // Handle edit bill button clicks
        document.querySelectorAll('.edit-bill').forEach(button => {
            button.addEventListener('click', function() {
                const billId = this.dataset.id;
                const billNumber = this.dataset.billNumber;
                
                // Set bill details in modal
                document.getElementById('edit_bill_id').value = billId;
                document.getElementById('edit_bill_number').value = billNumber;
                
                // Fetch bill items
                fetch(`get_bill_items.php?id=${billId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const tbody = document.querySelector('#editBillItemsTable tbody');
                            tbody.innerHTML = '';
                            
                            data.items.forEach(item => {
                                const tr = document.createElement('tr');
                                tr.innerHTML = `
                                    <td>${item.name}</td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm" 
                                               name="quantities[${item.id}]" value="${item.quantity}" min="1" autocomplete="off">
                                    </td>
                                    <td>₹${parseFloat(item.price).toFixed(2)}</td>
                                    <td>₹${parseFloat(item.subtotal).toFixed(2)}</td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-danger remove-item">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                `;
                                tbody.appendChild(tr);
                            });
                            
                            const modal = new bootstrap.Modal(document.getElementById('editBillModal'));
                            modal.show();
                        } else {
                            alert('Error loading bill items: ' + data.message);
                        }
                    });
            });
        });

        // Handle edit bill form submission
        document.getElementById('editBillForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('update_bill.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error updating bill: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating bill. Please try again.');
            });
        });

        // Add live search functionality
        let searchTimeout;
        const searchInput = document.getElementById('searchInput');
        const searchForm = document.getElementById('searchForm');
        const dateFrom = document.getElementById('dateFrom');
        const dateTo = document.getElementById('dateTo');
        const billsTable = document.getElementById('billsTable');

        function updateResults() {
            const searchValue = searchInput.value;
            const dateFromValue = dateFrom.value;
            const dateToValue = dateTo.value;
            
            // Build URL with parameters
            const params = new URLSearchParams();
            if (searchValue) params.append('search', searchValue);
            if (dateFromValue) params.append('date_from', dateFromValue);
            if (dateToValue) params.append('date_to', dateToValue);
            
            // Update URL without reloading the page
            const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
            window.history.pushState({}, '', newUrl);
            
            fetch(newUrl)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newTable = doc.querySelector('#billsTable');
                    if (newTable) {
                        billsTable.innerHTML = newTable.innerHTML;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }

        // Search input handler
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(updateResults, 300);
        });

        // Date input handlers
        dateFrom.addEventListener('change', updateResults);
        dateTo.addEventListener('change', updateResults);

        // Prevent form submission on enter key
        searchForm.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });

        // Prevent default form submission and handle it with JavaScript
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            updateResults();
        });

        // Add quantity button functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Function to update quantity
            function updateQuantity(productId, change) {
                const input = document.querySelector(`input[data-product-id="${productId}"]`);
                const currentValue = parseInt(input.value) || 0;
                const maxValue = parseInt(input.getAttribute('max'));
                const newValue = Math.max(0, Math.min(maxValue, currentValue + change));
                
                input.value = newValue;
                // Trigger input event to update totals
                input.dispatchEvent(new Event('input'));
            }

            // Add click handlers for plus buttons
            document.querySelectorAll('.plus-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.dataset.productId;
                    updateQuantity(productId, 1);
                });
            });

            // Add click handlers for minus buttons
            document.querySelectorAll('.minus-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.dataset.productId;
                    updateQuantity(productId, -1);
                });
            });

            // Add keyboard support for quantity inputs
            document.querySelectorAll('.product-quantity').forEach(input => {
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        updateQuantity(this.dataset.productId, 1);
                    } else if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        updateQuantity(this.dataset.productId, -1);
                    }
                });
            });
        });
    </script>
</body>
</html> 