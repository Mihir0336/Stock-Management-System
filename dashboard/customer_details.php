<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Check if customer ID is provided
if (!isset($_GET['id'])) {
    header("Location: customers.php");
    exit();
}

$customer_id = $_GET['id'];

// Get customer details
$stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();

if (!$customer) {
    header("Location: customers.php");
    exit();
}

// Get customer's bills
$stmt = $conn->prepare("
    SELECT 
        b.*,
        COUNT(bi.id) as total_items,
        GROUP_CONCAT(
            CONCAT(p.name, ' (', p.size, ') - ', bi.quantity, ' units - ', p.company)
            SEPARATOR '\n'
        ) as items_list
    FROM bills b
    LEFT JOIN bill_items bi ON b.id = bi.bill_id
    LEFT JOIN products p ON bi.product_id = p.id
    WHERE b.customer_id = ?
    GROUP BY b.id
    ORDER BY b.created_at DESC
");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$bills = $stmt->get_result();

// Get customer statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_bills,
        SUM(total_amount) as total_spent,
        AVG(total_amount) as average_bill,
        MIN(created_at) as first_purchase,
        MAX(created_at) as last_purchase,
        SUM(amount_due) as total_due,
        SUM(amount_paid) as total_paid,
        SUM(discount_amount) as total_discounts
    FROM bills
    WHERE customer_id = ?
");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get payment history
$stmt = $conn->prepare("
    SELECT 
        p.*,
        b.bill_number,
        u.username as created_by_name
    FROM payments p
    JOIN bills b ON p.bill_id = b.id
    JOIN users u ON p.created_by = u.id
    WHERE p.customer_id = ?
    ORDER BY p.payment_date DESC
");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$payments = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Details - Shiv Agro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .customer-header {
            background-color: #ffffff;
            color: #333333;
            padding: 2rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            border: 1px solid #e0e0e0;
        }
        .stat-card {
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 0.5rem;
            color: #333333;
            padding: 1.5rem;
            height: 100%;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #007bff;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .bill-card {
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 0.5rem;
            color: #333333;
            margin-bottom: 1rem;
        }
        .bill-header {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 0.5rem 0.5rem 0 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .bill-body {
            padding: 1rem;
        }
        .items-list {
            white-space: pre-line;
            color: #6c757d;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="customer-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1"><?php echo htmlspecialchars($customer['name']); ?></h2>
                    <div class="mb-2">
                        <i class="bi bi-telephone"></i> 
                        <?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?>
                    </div>
                    <div>
                        <i class="bi bi-geo-alt"></i> 
                        <?php echo htmlspecialchars($customer['village'] ?? 'N/A'); ?>
                    </div>
                </div>
                <a href="customers.php" class="btn btn-outline-light">
                    <i class="bi bi-arrow-left"></i> Back to Customers
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-2 mb-3">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_bills']; ?></div>
                    <div class="stat-label">Total Bills</div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="stat-card">
                    <div class="stat-value">₹<?php echo number_format($stats['total_spent'], 0); ?></div>
                    <div class="stat-label">Total Spent</div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="stat-card">
                    <div class="stat-value">₹<?php echo number_format($stats['total_paid'], 0); ?></div>
                    <div class="stat-label">Total Paid</div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="stat-card">
                    <div class="stat-value text-success">₹<?php echo number_format($stats['total_discounts'], 0); ?></div>
                    <div class="stat-label">Total Discounts</div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="stat-card">
                    <div class="stat-value <?php echo $stats['total_due'] > 0 ? 'text-danger' : 'text-success'; ?>">
                        ₹<?php echo number_format($stats['total_due'], 0); ?>
                    </div>
                    <div class="stat-label">Amount Due</div>
                </div>
            </div>
        </div>

        <?php if ($stats['total_due'] > 0): ?>
        <!-- Total Settlement Button -->
        <div class="mb-4">
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#totalSettlementModal">
                <i class="bi bi-cash-coin"></i> Total Settlement
            </button>
        </div>
        <?php endif; ?>

        <!-- Payment History -->
        <h3 class="mb-3">Payment History</h3>
        <?php if ($payments->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Bill Number</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Notes</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($payment = $payments->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo date('d M Y, h:i A', strtotime($payment['payment_date'])); ?></td>
                        <td>
                            <a href="view_bill.php?id=<?php echo $payment['bill_id']; ?>" class="text-primary">
                                <?php echo $payment['bill_number']; ?>
                            </a>
                        </td>
                        <td>₹<?php echo number_format($payment['amount'], 2); ?></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                        <td><?php echo htmlspecialchars($payment['notes'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($payment['created_by_name']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="alert alert-info">No payment history found.</div>
        <?php endif; ?>

        <!-- Bill History -->
        <h3 class="mb-3 mt-4">Bill History</h3>
        <?php while ($bill = $bills->fetch_assoc()): ?>
        <div class="bill-card">
            <div class="bill-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Bill #<?php echo $bill['bill_number']; ?></h5>
                        <small><?php echo date('d M Y, h:i A', strtotime($bill['created_at'])); ?></small>
                    </div>
                    <div class="text-end">
                        <div class="h5 mb-0">₹<?php echo number_format($bill['total_amount'], 0); ?></div>
                        <small>
                            <?php echo $bill['total_items']; ?> items
                            <?php if ($bill['amount_due'] > 0): ?>
                            <span class="text-danger">(Due: ₹<?php echo number_format($bill['amount_due'], 0); ?>)</span>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>
            <div class="bill-body">
                <div class="items-list">
                    <?php 
                    $items = explode("\n", $bill['items_list']);
                    foreach ($items as $item) {
                        echo htmlspecialchars($item) . "<br>";
                    }
                    ?>
                </div>
                <div class="mt-3">
                    <a href="view_bill.php?id=<?php echo $bill['id']; ?>" class="btn btn-primary btn-sm">
                        View Bill
                    </a>
                    <?php if ($bill['amount_due'] > 0): ?>
                    <button type="button" class="btn btn-success btn-sm" 
                            onclick="showPaymentModal(<?php echo $bill['id']; ?>, <?php echo $bill['amount_due']; ?>)">
                        Record Payment
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Record Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="paymentForm">
                    <div class="modal-body">
                        <input type="hidden" id="billId" name="bill_id">
                        <div class="mb-3">
                            <label class="form-label">Amount Due</label>
                            <input type="text" class="form-control" id="amountDue" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Amount</label>
                            <input type="number" class="form-control" name="amount" required min="0" step="0.01" autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <select class="form-select" name="payment_method" required autocomplete="off">
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="upi">UPI</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2" autocomplete="off"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Record Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Total Settlement Modal -->
    <div class="modal fade" id="totalSettlementModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Total Settlement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="totalSettlementForm">
                    <div class="modal-body">
                        <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                        <div class="mb-3">
                            <label class="form-label">Total Amount Due</label>
                            <input type="text" class="form-control" id="totalDues" value="₹<?php echo number_format($stats['total_due'], 2); ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Discount Amount</label>
                            <input type="number" class="form-control" id="discountAmount" name="discount_amount" min="0" step="0.01" value="0" max="<?php echo $stats['total_due']; ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount to be Settled</label>
                            <input type="number" class="form-control" id="settlementAmount" name="amount" required min="0" step="0.01" max="<?php echo $stats['total_due']; ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <select class="form-select" name="payment_method" required>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="upi">UPI</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2" placeholder="Enter any notes about this settlement"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Process Settlement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showPaymentModal(billId, amountDue) {
            document.getElementById('billId').value = billId;
            document.getElementById('amountDue').value = '₹' + amountDue.toFixed(2);
            document.querySelector('#paymentForm input[name="amount"]').max = amountDue;
            new bootstrap.Modal(document.getElementById('paymentModal')).show();
        }

        function calculateTotalSettlement() {
            let totalDues = 0;
            let totalSettled = 0;
            
            // Calculate total dues and settled amount
            document.querySelectorAll('.bill-row').forEach(row => {
                const billAmount = parseFloat(row.querySelector('.bill-amount').textContent);
                const settledAmount = parseFloat(row.querySelector('.settled-amount').textContent);
                totalDues += billAmount;
                totalSettled += settledAmount;
            });
            
            const remainingDues = totalDues - totalSettled;
            document.getElementById('totalDues').value = remainingDues.toFixed(2);
            document.getElementById('settlementAmount').value = remainingDues.toFixed(2);
            document.getElementById('remainingDues').value = '0.00';
        }

        function updateRemainingDues() {
            const totalDues = parseFloat(document.getElementById('totalDues').value) || 0;
            const discountAmount = parseFloat(document.getElementById('discountAmount').value) || 0;
            const settlementAmount = parseFloat(document.getElementById('settlementAmount').value) || 0;
            
            // Calculate remaining dues after discount and settlement
            const remainingDues = totalDues - discountAmount - settlementAmount;
            document.getElementById('remainingDues').value = remainingDues.toFixed(2);
        }

        // Update settlement amount when discount changes
        document.getElementById('discountAmount').addEventListener('input', function() {
            const totalDues = <?php echo $stats['total_due']; ?>;
            const discountAmount = parseFloat(this.value) || 0;
            
            if (discountAmount > totalDues) {
                alert('Discount amount cannot exceed total dues');
                this.value = totalDues;
                return;
            }
            
            // Calculate remaining amount after discount
            const remainingAmount = totalDues - discountAmount;
            document.getElementById('settlementAmount').value = remainingAmount.toFixed(2);
        });

        // Validate settlement amount
        document.getElementById('settlementAmount').addEventListener('input', function() {
            const totalDues = <?php echo $stats['total_due']; ?>;
            const discountAmount = parseFloat(document.getElementById('discountAmount').value) || 0;
            const settlementAmount = parseFloat(this.value) || 0;
            const remainingAmount = totalDues - discountAmount;
            
            if (settlementAmount > remainingAmount) {
                alert('Settlement amount cannot exceed the remaining amount after discount');
                this.value = remainingAmount.toFixed(2);
            }
        });

        // Total Settlement form handler
        document.getElementById('totalSettlementForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const amount = parseFloat(formData.get('amount'));
            const discountAmount = parseFloat(formData.get('discount_amount'));
            const totalDues = <?php echo $stats['total_due']; ?>;
            const remainingAmount = totalDues - discountAmount;

            if (amount > remainingAmount) {
                alert('Settlement amount cannot exceed the remaining amount after discount');
                return;
            }

            // Add settlement flag and total amount
            formData.append('is_settlement', '1');
            formData.append('is_total_settlement', '1');
            formData.append('total_amount', totalDues);
            formData.append('discount_amount', discountAmount);

            // Show loading state
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';

            fetch('record_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Settlement processed successfully!');
                    location.reload();
                } else {
                    alert(data.message || 'Error processing settlement');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error processing settlement. Please try again.');
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.innerHTML = originalText;
            });
        });

        // Existing payment form handler
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            // Show loading state
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';

            fetch('record_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Error recording payment');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error recording payment. Please try again.');
            })
            .finally(() => {
                // Reset button state
                submitButton.disabled = false;
                submitButton.innerHTML = originalText;
            });
        });
    </script>
</body>
</html> 