<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: bills.php");
    exit();
}

$bill_id = (int)$_GET['id'];

// Get bill details
$stmt = $conn->prepare("
    SELECT b.*, 
           c.name as customer_name, 
           c.phone as customer_phone,
           c.village as customer_village,
           u.username as created_by_name,
           GROUP_CONCAT(
               CONCAT(
                   p.name, ' (', p.size, ') - ',
                   bi.quantity, ' x ₹', bi.price_per_unit, ' = ₹', bi.total_price,
                   ' - ', p.company
               ) SEPARATOR '\n'
           ) as items_list,
           SUM(bi.total_price) as subtotal
    FROM bills b
    JOIN customers c ON b.customer_id = c.id
    JOIN users u ON b.created_by = u.id
    JOIN bill_items bi ON b.id = bi.bill_id
    JOIN products p ON bi.product_id = p.id
    WHERE b.id = ?
    GROUP BY b.id
");
$stmt->bind_param("i", $bill_id);
$stmt->execute();
$bill = $stmt->get_result()->fetch_assoc();

if (!$bill) {
    header("Location: bills.php");
    exit();
}

// Get bill items with product and category details
$query = "
    SELECT 
        bi.*,
        p.name as product_name,
        p.size,
        cat.name as category_name
    FROM bill_items bi
    JOIN products p ON bi.product_id = p.id
    JOIN categories cat ON p.category_id = cat.id
    WHERE bi.bill_id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $bill_id);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
$subtotal = 0;

while ($item = $result->fetch_assoc()) {
    $item_subtotal = $item['quantity'] * $item['price_per_unit'];
    $subtotal += $item_subtotal;
    
        $items[] = [
        'name' => $item['product_name'],
        'quantity' => $item['quantity'],
        'price' => $item['price_per_unit'],
        'subtotal' => $item_subtotal,
        'category' => $item['category_name'],
        'size' => $item['size']
    ];
}

$total_amount = $subtotal;

// Get payment history
$payments_query = "
    SELECT p.*, u.username as recorded_by 
    FROM payments p 
    JOIN users u ON p.created_by = u.id 
    WHERE p.bill_id = ? 
    ORDER BY p.payment_date DESC
";
$stmt = $conn->prepare($payments_query);
$stmt->bind_param("i", $bill_id);
$stmt->execute();
$payments = $stmt->get_result();

// Calculate total payments
$total_payments = 0;
while ($payment = $payments->fetch_assoc()) {
    $total_payments += $payment['amount'];
}
$payments->data_seek(0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill #<?php echo $bill['bill_number']; ?> - Shiv Agro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            .container {
                width: 100%;
                max-width: none;
                padding: 0;
                margin: 0;
            }
            .card {
                border: none !important;
                box-shadow: none !important;
            }
            .card-body {
                padding: 0 !important;
            }
            .bill-header {
                margin-bottom: 30px;
                border-bottom: 2px solid #000;
                padding-bottom: 20px;
            }
            .bill-header h2 {
                font-size: 24px;
                margin-bottom: 10px;
            }
            .bill-header p {
                margin: 5px 0;
                font-size: 14px;
            }
            .bill-details {
                margin-bottom: 30px;
            }
            .bill-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 30px;
            }
            .bill-table td {
                border: 1px solid #000;
                padding: 8px;
                font-size: 12px;
            }
            .bill-table tfoot td {
                padding: 5px 8px;
                border: none;
            }
            .bill-table tfoot tr {
                border: none;
            }
            .bill-table tfoot tr:last-child td {
                border-top: 1px solid #000;
                font-size: 14px;
                font-weight: bold;
            }
            .bill-table th {
                background-color: #f8f9fa !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .bill-footer {
                margin-top: 30px;
                border-top: 1px solid #000;
                padding-top: 20px;
            }
            .bill-footer ul {
                padding-left: 20px;
                margin: 10px 0;
            }
            .bill-footer li {
                font-size: 12px;
                margin-bottom: 5px;
            }
            .text-end {
                text-align: right !important;
            }
            .bill-footer .col-md-6 {
                width: 50%;
                flex: 0 0 auto;
            }
            .bill-footer .row {
                display: flex;
            }
            .bill-details .row {
                display: flex;
                flex-wrap: wrap;
            }
            .bill-details .col-md-6 {
                flex: 0 0 auto;
                width: 50%;
            }
            .bill-details {
                padding-top: 0;
                margin-bottom: 30px;
            }
            .customer-details,
            .bill-info {
                padding-top: 0;
                padding-bottom: 0;
            }
            .customer-details h5,
            .bill-info h5 {
                font-size: 14px;
                margin-bottom: 10px;
            }
            .bill-header .row {
                display: flex;
                flex-wrap: wrap;
            }
            .bill-header .col-6 {
                flex: 0 0 auto;
                width: 50%;
                text-align: left !important;
            }
            .bill-header h2 {
                margin-top: 0;
                margin-bottom: 5px;
            }
            .bill-header p {
                margin: 0 0 3px 0;
            }
            .bill-header .col-6.text-end {
                text-align: right !important;
            }
        }

        /* Screen styles */
        .bill-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #dee2e6;
        }
        .bill-header .row {
            display: flex;
            align-items: center;
        }
        .bill-header .col-6 {
            flex: 0 0 auto;
            width: 50%;
            text-align: left;
        }
        .bill-header .col-6.text-end {
            text-align: right;
        }
        .bill-header h2 {
            color: #2c3e50;
            margin-top: 0;
            margin-bottom: 5px;
        }
        .bill-header p {
            margin: 0 0 3px 0;
        }
        .bill-details {
            margin-bottom: 20px;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        .bill-table {
            margin-bottom: 20px;
        }
        .bill-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .bill-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #dee2e6;
        }
        .customer-details h5,
        .bill-info h5 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        .bill-footer ul {
            list-style-type: none;
            padding-left: 0;
        }
        .bill-footer li {
            margin-bottom: 5px;
            color: #6c757d;
        }
        .signature-space {
            margin-top: 50px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="bill-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1">Bill #<?php echo htmlspecialchars($bill['bill_number']); ?></h2>
                    <div class="text-muted">
                        Date: <?php echo date('d M Y', strtotime($bill['created_at'])); ?>
                    </div>
                </div>
            <div>
                    <a href="bills.php" class="btn btn-primary">
                    <i class="bi bi-arrow-left"></i> Back to Bills
                </a>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-8">
                <!-- Customer Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Customer Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($bill['customer_name']); ?></p>
                                <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($bill['customer_phone'] ?? 'N/A'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Village:</strong> <?php echo htmlspecialchars($bill['customer_village'] ?? 'N/A'); ?></p>
                                <p class="mb-1"><strong>Created By:</strong> <?php echo htmlspecialchars($bill['created_by_name']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bill Items -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Bill Items</h5>
                    </div>
                    <div class="card-body">
                        <div class="items-list">
                            <?php 
                            $items = explode("\n", $bill['items_list']);
                            foreach ($items as $item) {
                                echo htmlspecialchars($item) . "<br>";
                            }
                            ?>
                        </div>
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
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span>₹<?php echo number_format($bill['subtotal'], 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Amount:</span>
                            <span>₹<?php echo number_format($bill['total_amount'], 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Amount Paid:</span>
                            <span>₹<?php echo number_format($total_payments, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Amount Due:</span>
                            <span>₹<?php echo number_format($bill['amount_due'], 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Payment Status:</span>
                            <span class="badge bg-<?php 
                                echo $bill['payment_status'] === 'paid' ? 'success' : 
                                    ($bill['payment_status'] === 'partial' ? 'warning' : 'danger'); 
                            ?>">
                                <?php echo ucfirst($bill['payment_status']); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <?php if ($bill['payment_status'] !== 'paid'): ?>
                <!-- Payment Button -->
                <div class="mt-3">
                    <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#settlementModal">
                        Record Payment
                    </button>
                </div>
                <?php endif; ?>

                <!-- Payment History -->
                <?php if ($payments->num_rows > 0): ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Payment History</h5>
                    </div>
                    <div class="card-body p-0">
                <div class="table-responsive">
                            <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Recorded By</th>
                            </tr>
                        </thead>
                        <tbody>
                                    <?php while ($payment = $payments->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('d M Y, h:i A', strtotime($payment['payment_date'])); ?></td>
                                        <td>₹<?php echo number_format($payment['amount'], 2); ?></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                                        <td><?php echo htmlspecialchars($payment['recorded_by']); ?></td>
                            </tr>
                                    <?php endwhile; ?>
                        </tbody>
                    </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
                </div>

    <!-- Settlement Modal -->
    <div class="modal fade" id="settlementModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Record Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="settlementForm">
                    <div class="modal-body">
                        <input type="hidden" name="bill_id" value="<?php echo $bill_id; ?>">
                        <input type="hidden" name="customer_id" value="<?php echo $bill['customer_id']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Total Amount Due</label>
                            <input type="text" class="form-control" value="₹<?php echo number_format($bill['amount_due'], 2); ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Amount</label>
                            <input type="number" class="form-control" name="amount" id="settlementAmount" required 
                                   min="0" step="0.01" max="<?php echo $bill['amount_due']; ?>"
                                   onchange="validateSettlementAmount(this)">
                            <div class="form-text">Enter the amount to pay</div>
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
                            <textarea class="form-control" name="notes" rows="2" 
                                      placeholder="Enter any notes about this payment"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Record Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function validateSettlementAmount(input) {
            const maxAmount = parseFloat(input.getAttribute('max'));
            const enteredAmount = parseFloat(input.value);
            
            if (enteredAmount > maxAmount) {
                alert('Payment amount cannot exceed the total amount due');
                input.value = maxAmount;
            }
        }

        // Payment form handler
        document.getElementById('settlementForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const amount = parseFloat(formData.get('amount'));
            const maxAmount = parseFloat(document.getElementById('settlementAmount').getAttribute('max'));

            if (amount > maxAmount) {
                alert('Payment amount cannot exceed the total amount due');
                return;
            }

            fetch('record_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Error recording payment');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error recording payment');
            });
        });

        // Automatically trigger print dialog if print=true parameter is present
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('print') && urlParams.get('print') === 'true') {
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            }
        }
    </script>
</body>
</html> 