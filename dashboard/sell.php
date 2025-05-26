<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Get all products with their categories and companies
$query = "
    SELECT p.*, c.name as category_name
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.quantity > 0 
    ORDER BY p.company, p.name, p.size
";
$products = $conn->query($query);

// Get all customers
$customers = $conn->query("SELECT * FROM customers ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sell Products - Shiv Agro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .product-card {
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 0.5rem;
            color: #333333;
            margin-bottom: 1rem;
            transition: transform 0.2s;
        }
        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .product-header {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 0.5rem 0.5rem 0 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .product-body {
            padding: 1rem;
        }
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .quantity-btn {
            width: 30px;
            height: 30px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: bold;
        }
        .quantity-input {
            width: 70px !important;
            text-align: center;
        }
        .customer-section {
            background-color: #f8f9fa;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            color: #333333;
            border: 1px solid #e0e0e0;
        }
        .payment-section {
            background-color: #f8f9fa;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-top: 2rem;
            color: #333333;
            border: 1px solid #e0e0e0;
        }
        .summary-card {
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 0.5rem;
            color: #333333;
            padding: 1.5rem;
            position: sticky;
            top: 1rem;
        }
        .summary-value {
            font-size: 1.2rem;
            font-weight: bold;
            color: #007bff;
        }
        .summary-label {
            color: #6c757d;
        }
        .product-filters {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 0.5rem;
            border: 1px solid #e0e0e0;
        }
        .selected-items-summary {
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1rem;
            border: 1px solid #e0e0e0;
        }
        .selected-item {
            background-color: #ffffff;
            border-radius: 0.25rem;
            padding: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #e0e0e0;
        }
        .quick-add-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }
        .quick-add-buttons .btn {
            flex: 1;
        }
        .table {
            margin-bottom: 0;
        }
        .table td {
            vertical-align: middle;
        }
        .btn-group-sm .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        /* Add new styles for selected products table */
        .selected-products .table {
            background-color: #ffffff;
            border-radius: 0.5rem;
            overflow: hidden;
            border: 1px solid #e0e0e0;
        }
        .selected-products .table thead th {
            background-color: #f8f9fa;
            color: #333333;
            border-bottom: 2px solid #e0e0e0;
            padding: 1rem;
            font-weight: 600;
        }
        .selected-products .table tbody td {
            padding: 1rem;
            border-bottom: 1px solid #e0e0e0;
            color: #333333;
        }
        .selected-products .table tbody tr:last-child td {
            border-bottom: none;
        }
        .selected-products .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        .selected-products .input-group-sm {
            width: auto;
        }
        .selected-products .input-group-sm .form-control {
            background-color: #ffffff;
            border-color: #e0e0e0;
            color: #333333;
        }
        .selected-products .input-group-sm .form-control:focus {
            background-color: #ffffff;
            border-color: #007bff;
            color: #333333;
            box-shadow: none;
        }
        /* Remove number input arrows */
        .selected-products input[type="number"]::-webkit-inner-spin-button,
        .selected-products input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .selected-products input[type="number"] {
            -moz-appearance: textfield;
        }
        /* Price input specific styles */
        .selected-products .price-input {
            width: 50px;
            text-align: center;
            padding-right: 8px;
        }
        .selected-products .quantity-btn {
            background-color: #f8f9fa;
            border-color: #e0e0e0;
            color: #333333;
        }
        .selected-products .quantity-btn:hover {
            background-color: #007bff;
            border-color: #0056b3;
            color: #ffffff;
        }
        .selected-products .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .selected-products .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        #liveSearchResults {
            margin-top: 2px;
            max-height: 300px;
            overflow-y: auto;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0 !important;
            background-color: #ffffff;
        }
        .customer-result-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #e0e0e0;
            transition: background-color 0.2s;
            color: #333333;
        }
        .customer-result-item:hover {
            background-color: #f8f9fa;
        }
        .customer-result-item:last-child {
            border-bottom: none;
        }
        .customer-result-name {
            font-weight: bold;
            color: #007bff;
        }
        .customer-result-details {
            font-size: 0.85rem;
            color: #bdc3c7;
        }
        .customer-due-amount {
            color: #e74c3c;
            font-weight: bold;
        }
        /* Search Results */
        #searchResults {
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 0.5rem;
            margin-top: 0.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        }
        #searchResults .list-group-item {
            background-color: #ffffff;
            border-color: #e0e0e0;
            color: #333333;
            cursor: pointer;
            transition: background 0.18s;
        }
        #searchResults .list-group-item:hover {
            background-color: #f8f9fa;
        }
        #searchResults .list-group-item.active {
            background-color: #007bff;
            color: #ffffff;
        }
        #searchResults .list-group-item small {
            color: #6c757d;
        }
        .product-search-section {
            background-color: #f8f9fa;
            padding: 2rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            border: 1px solid #e0e0e0;
        }
        .product-search-section .form-control {
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            color: #333333;
        }
        .product-search-section .btn {
            background-color: #007bff;
            border-color: #007bff;
        }
        .product-search-section .btn:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .search-section {
            background-color: #f8f9fa;
            padding: 0.75rem 1rem !important;
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
        .search-section .input-group {
            gap: 1rem;
        }
        .search-section .input-group .form-control,
        .search-section .input-group .btn {
            border-radius: 1rem !important;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <!-- Customer Selection -->
                <div class="search-section position-relative">
                    <h4 class="mb-3">Select Customer</h4>
                    <div class="search-input-container position-relative">
                        <div class="input-group">
                            <input type="text" class="form-control" id="customerSearch" placeholder="Search customer by name or phone..." oninput="handleCustomerSearch(this.value)" autocomplete="off">
                            <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                                <i class="bi bi-plus"></i> Add Customer
                            </button>
                        </div>
                        <div id="liveSearchResults" class="live-search-results"></div>
                    </div>
                    <div id="selectedCustomer" class="mt-2" style="display: none;">
                        <div class="alert alert-info d-flex justify-content-between align-items-center">
                            <div>
                                <strong id="selectedCustomerName"></strong>
                                <br>
                                <small id="selectedCustomerDetails"></small>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearCustomerSelection()">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Products List -->
                <div class="search-section position-relative mb-4">
                    <h4 class="mb-3">Select Product</h4>
                    <div class="search-input-container position-relative">
                        <div class="input-group">
                            <input type="text" class="form-control" id="productSearch" placeholder="Search products by name, company, or category..." oninput="handleProductSearch(this.value)" autocomplete="off">
                            <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                <i class="bi bi-plus"></i> Add Product
                            </button>
                        </div>
                        <div id="searchResults" class="live-search-results"></div>
                    </div>
                </div>

                <!-- Selected Products -->
                <div class="selected-products mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Selected Products</h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Category</th>
                                            <th>Company</th>
                                            <th>Available</th>
                                            <th>Price</th>
                                            <th>Quantity</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="selectedProductsList">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Section -->
                <div class="payment-section">
                    <h4 class="mb-3">Payment Details</h4>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Method</label>
                            <select class="form-select" id="paymentMethod">
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="upi">UPI</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount Paid</label>
                            <input type="number" class="form-control" id="amountPaid" value="0" min="0" step="0.01" autocomplete="off">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Notes</label>
                        <textarea class="form-control" id="paymentNotes" rows="2" autocomplete="off"></textarea>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-md-4">
                <div class="summary-card">
                    <h4 class="mb-4">Order Summary</h4>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="summary-label">Subtotal</span>
                            <span class="summary-value" id="subtotal">₹0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="summary-label">Total</span>
                            <span class="summary-value" id="total">₹0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="summary-label">Amount Paid</span>
                            <span class="summary-value" id="paid">₹0.00</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="summary-label">Amount Due</span>
                            <span class="summary-value" id="due">₹0.00</span>
                        </div>
                    </div>
                    <button class="btn btn-primary w-100" onclick="createBill()">Create Bill</button>
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
                            <input type="text" class="form-control" name="name" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone" autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Village</label>
                            <input type="text" class="form-control" name="village" autocomplete="off">
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
        let products = <?php 
            $products->data_seek(0);
            echo json_encode($products->fetch_all(MYSQLI_ASSOC)); 
        ?>;
        let selectedProducts = {};
        let selectedCustomerId = null;
        let searchTimeout = null;
        let searchProductTimeout = null;

        function updateQuantity(productId) {
            const input = document.getElementById(`quantity_${productId}`);
            const quantity = parseInt(input.value) || 0;
            const product = selectedProducts[productId];
            
            if (quantity > product.available) {
                input.value = product.available;
                selectedProducts[productId].quantity = product.available;
            } else if (quantity < 0) {
                input.value = 0;
                selectedProducts[productId].quantity = 0;
            } else {
                selectedProducts[productId].quantity = quantity;
            }
            
            // Update individual product total
            const totalElement = document.getElementById(`total_${productId}`);
            const total = selectedProducts[productId].quantity * selectedProducts[productId].price;
            totalElement.textContent = `₹${total.toFixed(2)}`;
            
            updateSummary();
            updateSelectedProductsList();
        }

        function updateCustomPrice(productId) {
            const input = document.getElementById(`custom_price_${productId}`);
            const customPrice = parseFloat(input.value) || 0;
            const product = selectedProducts[productId];
            
            if (customPrice < 0) {
                input.value = product.price;
                selectedProducts[productId].price = product.price;
            } else {
                selectedProducts[productId].price = customPrice;
            }
            
            // Update individual product total
            const quantity = selectedProducts[productId].quantity;
            const totalElement = document.getElementById(`total_${productId}`);
            const total = quantity * selectedProducts[productId].price;
            totalElement.textContent = `₹${total.toFixed(2)}`;
            
            updateSummary();
            updateSelectedProductsList();
        }

        function incrementQuantity(productId) {
            const input = document.getElementById(`quantity_${productId}`);
            input.value = parseInt(input.value) + 1;
            updateQuantity(productId);
        }

        function decrementQuantity(productId) {
            const input = document.getElementById(`quantity_${productId}`);
            input.value = parseInt(input.value) - 1;
            updateQuantity(productId);
        }

        function updateSummary() {
            let subtotal = 0;

            for (const [productId, data] of Object.entries(selectedProducts)) {
                if (data.quantity > 0) {
                    const itemTotal = data.price * data.quantity;
                    subtotal += itemTotal;
                }
            }

            const total = subtotal;
            const amountPaid = parseFloat(document.getElementById('amountPaid').value) || 0;
            const amountDue = total - amountPaid;

            document.getElementById('subtotal').textContent = `₹${subtotal.toFixed(2)}`;
            document.getElementById('total').textContent = `₹${total.toFixed(2)}`;
            document.getElementById('paid').textContent = `₹${amountPaid.toFixed(2)}`;
            document.getElementById('due').textContent = `₹${amountDue.toFixed(2)}`;
        }

        document.getElementById('amountPaid').addEventListener('input', updateSummary);

        function createBill() {
            if (!selectedCustomerId) {
                alert('Please select a customer');
                return;
            }

            const items = [];
            for (const [productId, data] of Object.entries(selectedProducts)) {
                if (data.quantity > 0) {
                    const product = products.find(p => p.id == productId);
                    items.push({
                        productId: productId,
                        name: product.name,
                        quantity: data.quantity,
                        price: data.price,
                        size: product.size
                    });
                }
            }

            if (items.length === 0) {
                alert('Please select at least one product');
                return;
            }

            const paymentMethod = document.getElementById('paymentMethod').value;
            const amountPaid = parseFloat(document.getElementById('amountPaid').value) || 0;
            const paymentNotes = document.getElementById('paymentNotes').value;

            const formData = {
                customer_id: selectedCustomerId,
                items: items,
                payment_method: paymentMethod,
                amount_paid: amountPaid,
                payment_notes: paymentNotes
            };

            fetch('create_bill.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = `view_bill.php?id=${data.bill_id}`;
                } else {
                    alert(data.message || 'Error creating bill');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error creating bill');
            });
        }

        // Add Customer Form Handler
        document.getElementById('addCustomerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            
            // Disable submit button and show loading state
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...';

            fetch('add_customer.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear the form
                    this.reset();
                    
                    // Close the modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addCustomerModal'));
                    modal.hide();
                    
                    // Show success message
                    alert('Customer added successfully!');
                    
                    // Update the search input with the new customer's name
                    document.getElementById('customerSearch').value = data.customer.name;
                    
                    // Trigger search to show the new customer
                    handleCustomerSearch(data.customer.name);
                } else {
                    // Show error message
                    alert(data.message || 'Error adding customer');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding customer. Please try again.');
            })
            .finally(() => {
                // Re-enable submit button
                submitButton.disabled = false;
                submitButton.innerHTML = 'Add Customer';
            });
        });

        function setQuantity(productId, quantity) {
            const input = document.getElementById(`quantity_${productId}`);
            input.value = quantity;
            updateQuantity(productId);
        }

        function updateSelectedProductsList() {
            const selectedProductsList = document.getElementById('selectedProductsList');
            const selectedItems = Object.entries(selectedProducts).filter(([_, data]) => data.quantity > 0);
            
            if (selectedItems.length === 0) {
                selectedProductsList.innerHTML = '<tr><td colspan="7" class="text-center">No products selected</td></tr>';
                return;
            }

            selectedProductsList.innerHTML = selectedItems.map(([productId, data]) => {
                return `
                    <tr>
                        <td>
                            <strong>${data.name}${data.size ? ` (${data.size})` : ''}</strong>
                        </td>
                        <td>${data.category}</td>
                        <td>${data.company}</td>
                        <td>${data.available}</td>
                        <td>
                            <input type="number" 
                                   class="form-control form-control-sm price-input" 
                                   id="custom_price_${productId}"
                                   value="${data.price}"
                                   min="0"
                                   step="0.01"
                                   onchange="updateCustomPrice(${productId})">
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <button class="btn btn-sm btn-secondary quantity-btn" onclick="decrementQuantity(${productId})">-</button>
                                <input type="number" class="form-control form-control-sm quantity-input" 
                                       id="quantity_${productId}" 
                                       value="${data.quantity}" 
                                       min="0" 
                                       max="${data.available}"
                                       onchange="updateQuantity(${productId})"
                                       style="width: 70px;">
                                <button class="btn btn-sm btn-secondary quantity-btn" onclick="incrementQuantity(${productId})">+</button>
                            </div>
                        </td>
                        <td>
                            <span id="total_${productId}">₹${(data.quantity * data.price).toFixed(2)}</span>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function removeProduct(productId) {
            delete selectedProducts[productId];
            updateSelectedProductsList();
            updateSummary();
        }

        function handleCustomerSearch(value) {
            // Clear any existing timeout
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }

            // Hide results if search is empty
            if (!value.trim()) {
                document.getElementById('liveSearchResults').style.display = 'none';
                return;
            }

            // Set a new timeout to search after 300ms of no typing
            searchTimeout = setTimeout(() => {
                searchCustomers(value);
            }, 300);
        }

        function searchCustomers(searchTerm) {
            if (!searchTerm.trim()) {
                document.getElementById('liveSearchResults').style.display = 'none';
                return;
            }

            fetch('search_customers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ search: searchTerm })
            })
            .then(response => response.json())
            .then(data => {
                const resultsDiv = document.getElementById('liveSearchResults');
                if (data.length === 0) {
                    resultsDiv.innerHTML = '<div class="live-search-item text-center">No customers found</div>';
                } else {
                    resultsDiv.innerHTML = data.map(customer => `
                        <div class="live-search-item" onclick="selectCustomer(${customer.id}, '${customer.name}', '${customer.phone || ''}', '${customer.village || ''}', ${customer.due_amount || 0})">
                            <div class="item-title">${customer.name}</div>
                            <div class="item-details">
                                ${customer.phone ? 'Phone: ' + customer.phone : ''}
                                ${customer.village ? ' | Village: ' + customer.village : ''}
                                ${customer.due_amount > 0 ? `<span class='text-danger ms-2'>Due: ₹${parseFloat(customer.due_amount).toFixed(2)}</span>` : ''}
                            </div>
                        </div>
                    `).join('');
                }
                resultsDiv.style.display = 'block';
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        function selectCustomer(id, name, phone, village, dueAmount) {
            selectedCustomerId = id;
            document.getElementById('selectedCustomerName').textContent = name;
            let details = [];
            if (phone) details.push('Phone: ' + phone);
            if (village) details.push('Village: ' + village);
            if (dueAmount > 0) details.push(`Due Amount: ₹${parseFloat(dueAmount).toFixed(2)}`);
            
            document.getElementById('selectedCustomerDetails').innerHTML = details.join('<br>');
            document.getElementById('selectedCustomer').style.display = 'block';
            document.getElementById('customerSearch').value = name;
            document.getElementById('liveSearchResults').style.display = 'none';
        }

        function clearCustomerSelection() {
            selectedCustomerId = null;
            document.getElementById('selectedCustomer').style.display = 'none';
            document.getElementById('customerSearch').value = '';
            document.getElementById('liveSearchResults').style.display = 'none';
        }

        // Close search results when clicking outside
        document.addEventListener('click', function(event) {
            const searchResults = document.getElementById('liveSearchResults');
            const searchInput = document.getElementById('customerSearch');
            
            if (!searchResults.contains(event.target) && event.target !== searchInput) {
                searchResults.style.display = 'none';
            }
        });

        function handleProductSearch(value) {
            // Clear any existing timeout
            if (searchProductTimeout) {
                clearTimeout(searchProductTimeout);
            }

            // Hide results if search is empty
            if (!value.trim()) {
                document.getElementById('searchResults').style.display = 'none';
                return;
            }

            // Set a new timeout to search after 300ms of no typing
            searchProductTimeout = setTimeout(() => {
                searchProducts(value);
            }, 300);
        }

        function searchProducts(searchTerm) {
            if (!searchTerm.trim()) {
                document.getElementById('searchResults').style.display = 'none';
                return;
            }

            fetch('search_products.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ search: searchTerm })
            })
            .then(response => response.json())
            .then(data => {
                const searchResults = document.getElementById('searchResults');
                if (!data.success) {
                    searchResults.innerHTML = '<div class="live-search-item">Error: ' + data.message + '</div>';
                    searchResults.style.display = 'block';
                    return;
                }
                if (!data.products || data.products.length === 0) {
                    searchResults.innerHTML = '<div class="live-search-item">No products found</div>';
                    searchResults.style.display = 'block';
                    return;
                }
                searchResults.innerHTML = data.products.map(product => {
                    const isSelected = selectedProducts[product.id]?.quantity > 0;
                    return `
                        <div class="live-search-item${isSelected ? ' active' : ''}"
                             onclick="selectProduct(${product.id}, '${product.name}', '${product.category_name}', '${product.company}', ${product.quantity}, ${product.price_per_unit}, '${product.size || ''}')">
                            <div class="item-title">${product.name}${product.size ? ` (${product.size})` : ''}</div>
                            <div class="item-details">
                                ${product.category_name} - ${product.company} | Available: ${product.quantity} | Price: ₹${parseFloat(product.price_per_unit).toFixed(2)}
                            </div>
                        </div>
                    `;
                }).join('');
                searchResults.style.display = 'block';
            })
            .catch(error => {
                console.error('Error:', error);
                const searchResults = document.getElementById('searchResults');
                searchResults.innerHTML = '<div class="live-search-item">Error loading products. Please try again.</div>';
                searchResults.style.display = 'block';
            });
        }

        function selectProduct(id, name, category, company, available, defaultPrice, size) {
            // Initialize the product in selectedProducts if not exists
            if (!selectedProducts[id]) {
                selectedProducts[id] = {
                    quantity: 1, // Set initial quantity to 1
                    price: defaultPrice,
                    name: name,
                    category: category,
                    company: company,
                    available: available,
                    size: size
                };
            } else {
                // If product exists, increment quantity if it's 0
                if (selectedProducts[id].quantity === 0) {
                    selectedProducts[id].quantity = 1;
                }
            }
            
            // Update the selected products list
            updateSelectedProductsList();
            // Update the summary
            updateSummary();
            // Hide search results
            document.getElementById('searchResults').style.display = 'none';
            // Clear search input
            document.getElementById('productSearch').value = '';
        }
    </script>
</body>
</html> 