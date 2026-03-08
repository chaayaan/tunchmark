<?php
require 'auth.php';
require 'mydb.php';

// Handle bulk mark as paid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_mark_paid'])) {
    if (!empty($_POST['order_ids']) && is_array($_POST['order_ids'])) {
        $orderIds = array_map('intval', $_POST['order_ids']);
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        
        $stmt = mysqli_prepare($conn, "UPDATE orders SET status = 'paid' WHERE order_id IN ($placeholders) AND status = 'pending'");
        $types = str_repeat('i', count($orderIds));
        mysqli_stmt_bind_param($stmt, $types, ...$orderIds);
        
        if (mysqli_stmt_execute($stmt)) {
            $affectedRows = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            header("Location: unpaid_bills.php?success=$affectedRows");
            exit;
        }
        mysqli_stmt_close($stmt);
    } else {
        header("Location: unpaid_bills.php?error=noselection");
        exit;
    }
}

// Handle single mark as paid
if (isset($_GET['mark_paid'])) {
    $orderId = intval($_GET['mark_paid']);
    $stmt = mysqli_prepare($conn, "UPDATE orders SET status = 'paid' WHERE order_id = ? AND status = 'pending'");
    mysqli_stmt_bind_param($stmt, "i", $orderId);
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        header("Location: unpaid_bills.php?success=1");
        exit;
    }
    mysqli_stmt_close($stmt);
}

// Fetch pending orders with item counts and totals
$query = "
    SELECT o.*, 
           COUNT(bi.bill_item_id) as item_count,
           SUM(bi.total_price) as calculated_total
    FROM orders o
    LEFT JOIN bill_items bi ON o.order_id = bi.order_id
    WHERE o.status = 'pending'
    GROUP BY o.order_id
    ORDER BY o.order_id DESC
";

$res = mysqli_query($conn, $query);
$unpaidOrders = [];
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $unpaidOrders[] = $row;
    }
}
?>
<?php include 'navbar.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Orders - Payment Processing</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .main-card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background-color: #ffc107;
            color: #000;
            border: none;
            padding: 1.25rem 1.5rem;
        }
        
        .card-header h3 {
            margin: 0;
            font-weight: 600;
            font-size: 1.5rem;
        }
        
        .bulk-actions-bar {
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: none;
        }
        
        .bulk-actions-bar.active {
            display: flex;
        }
        
        .selected-count {
            font-weight: 600;
            color: #495057;
        }
        
        .order-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .table thead th {
            background-color: #f8f9fa;
            color: #495057;
            font-weight: 600;
            font-size: 0.875rem;
            border: 1px solid #dee2e6;
            white-space: nowrap;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .table tbody td {
            vertical-align: middle;
            font-size: 0.875rem;
        }
        
        .action-btn {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            border-radius: 4px;
        }
        
        .total-summary {
            background-color: #e7f3ff;
            border-left: 4px solid #0d6efd;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .table {
                font-size: 0.8rem;
            }
            
            .action-btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid py-4" style="max-width: 1400px;">
    <!-- Success/Error Messages -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> 
            <strong>Success!</strong> <?= intval($_GET['success']) ?> order(s) marked as paid.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error']) && $_GET['error'] === 'noselection'): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i> 
            <strong>Warning!</strong> Please select at least one order.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="main-card">
        <!-- Header -->
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h3><i class="fas fa-clock"></i> Pending Orders</h3>
                <small class="text-dark">Orders awaiting payment confirmation</small>
            </div>
            <div>
                <span class="badge bg-dark fs-6">
                    <?= count($unpaidOrders) ?> Pending
                </span>
            </div>
        </div>

        <div class="card-body p-4">
            <?php if (empty($unpaidOrders)): ?>
                <!-- No Pending Orders -->
                <div class="text-center py-5">
                    <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                    <h4 class="mt-3 text-success">All Clear!</h4>
                    <p class="text-muted">No pending orders at the moment.</p>
                    <a href="dashboard.php" class="btn btn-primary mt-3">
                        <i class="fas fa-home"></i> Go to Dashboard
                    </a>
                </div>
            <?php else: ?>
                <!-- Total Summary -->
                <div class="total-summary">
                    <div class="row">
                        <div class="col-md-4">
                            <small class="text-muted d-block">Total Pending Orders</small>
                            <strong class="fs-5"><?= count($unpaidOrders) ?></strong>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block">Total Pending Amount</small>
                            <strong class="fs-5 text-primary">
                                ৳<?= number_format(array_sum(array_column($unpaidOrders, 'calculated_total')), 2) ?>
                            </strong>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block">Average Order Value</small>
                            <strong class="fs-5">
                                ৳<?= number_format(array_sum(array_column($unpaidOrders, 'calculated_total')) / count($unpaidOrders), 2) ?>
                            </strong>
                        </div>
                    </div>
                </div>

                <form method="POST" id="bulkPaymentForm">
                    <!-- Bulk Actions Bar -->
                    <div class="bulk-actions-bar align-items-center justify-content-between" id="bulkActionsBar">
                        <div>
                            <span class="selected-count" id="selectedCount">0 selected</span>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearSelection()">
                                <i class="fas fa-times"></i> Clear Selection
                            </button>
                            <button type="submit" name="bulk_mark_paid" class="btn btn-success btn-sm" onclick="return confirmBulkPayment()">
                                <i class="fas fa-check-circle"></i> Mark Selected as Paid
                            </button>
                        </div>
                    </div>

                    <!-- Orders Table -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" class="order-checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                                    </th>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Phone</th>
                                    <th class="text-center">Items</th>
                                    <th class="text-end">Amount (৳)</th>
                                    <th>Date</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($unpaidOrders as $order): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" 
                                               class="order-checkbox order-select" 
                                               name="order_ids[]" 
                                               value="<?= $order['order_id'] ?>"
                                               onchange="updateBulkActions()">
                                    </td>
                                    <td>
                                        <strong>#<?= htmlspecialchars($order['order_id']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                    <td>
                                        <small><?= htmlspecialchars($order['customer_phone']) ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary"><?= $order['item_count'] ?></span>
                                    </td>
                                    <td class="text-end">
                                        <strong><?= number_format($order['calculated_total'], 2) ?></strong>
                                    </td>
                                    <td>
                                        <small><?= date('d M Y', strtotime($order['created_at'])) ?></small>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="bill.php?id=<?= $order['order_id'] ?>" 
                                               class="btn btn-outline-primary action-btn"
                                               title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button onclick="printOrder(<?= $order['order_id'] ?>)" 
                                                    type="button"
                                                    class="btn btn-outline-info action-btn" 
                                                    title="Print Receipt">
                                                <i class="fas fa-print"></i>
                                            </button>
                                            <a href="unpaid_bills.php?mark_paid=<?= $order['order_id'] ?>" 
                                               class="btn btn-outline-success action-btn"
                                               onclick="return confirm('Mark order #<?= $order['order_id'] ?> as paid?');"
                                               title="Mark as Paid">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="card-footer bg-light text-center">
            <a href="reports.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Reports
            </a>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle select all checkboxes
    function toggleSelectAll(checkbox) {
        const orderCheckboxes = document.querySelectorAll('.order-select');
        orderCheckboxes.forEach(cb => {
            cb.checked = checkbox.checked;
        });
        updateBulkActions();
    }

    // Update bulk actions bar visibility and count
    function updateBulkActions() {
        const selectedCheckboxes = document.querySelectorAll('.order-select:checked');
        const count = selectedCheckboxes.length;
        const bulkActionsBar = document.getElementById('bulkActionsBar');
        const selectedCount = document.getElementById('selectedCount');
        const selectAllCheckbox = document.getElementById('selectAll');
        const totalCheckboxes = document.querySelectorAll('.order-select').length;

        // Update count display
        selectedCount.textContent = `${count} selected`;

        // Show/hide bulk actions bar
        if (count > 0) {
            bulkActionsBar.classList.add('active');
        } else {
            bulkActionsBar.classList.remove('active');
        }

        // Update select all checkbox state
        if (count === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else if (count === totalCheckboxes) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true;
        }
    }

    // Clear all selections
    function clearSelection() {
        document.querySelectorAll('.order-select').forEach(cb => {
            cb.checked = false;
        });
        document.getElementById('selectAll').checked = false;
        updateBulkActions();
    }

    // Confirm bulk payment
    function confirmBulkPayment() {
        const count = document.querySelectorAll('.order-select:checked').length;
        if (count === 0) {
            alert('Please select at least one order.');
            return false;
        }
        return confirm(`Are you sure you want to mark ${count} order(s) as paid?`);
    }

    // Print order using print_order.php
    function printOrder(orderId) {
        const printWindow = window.open(
            'print_order.php?id=' + orderId, 
            'Print Order #' + orderId,
            'width=400,height=600,scrollbars=yes,resizable=yes'
        );
        
        if (printWindow) {
            printWindow.focus();
        } else {
            alert('Please allow popups to print receipts');
        }
    }

    // Auto-dismiss alerts after 5 seconds
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
</script>
</body>
</html>