<?php require 'auth.php'; ?>
<?php include 'navbar.php'; ?>
<?php include 'mydb.php'; ?>
<?php

$orderId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($orderId <= 0) {
    die("Invalid Order ID.");
}

// Fetch order
$stmt = mysqli_prepare($conn, "SELECT * FROM orders WHERE order_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $orderId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$order) {
    die("Order not found.");
}

// Fetch bill items with item and service details
$itemsStmt = mysqli_prepare($conn, "
    SELECT bi.*, 
           i.name as item_name,
           s.name as service_name,
           s.price as service_price
    FROM bill_items bi
    LEFT JOIN items i ON bi.item_id = i.id
    LEFT JOIN services s ON bi.service_id = s.id
    WHERE bi.order_id = ?
    ORDER BY bi.bill_item_id ASC
");
mysqli_stmt_bind_param($itemsStmt, "i", $orderId);
mysqli_stmt_execute($itemsStmt);
$itemsRes = mysqli_stmt_get_result($itemsStmt);

$items = [];
$totalAmount = 0;
while ($itemRow = mysqli_fetch_assoc($itemsRes)) {
    $items[] = $itemRow;
    $totalAmount += floatval($itemRow['total_price']);
}
mysqli_stmt_close($itemsStmt);

// Get previous order id
$prevRes = mysqli_query($conn, "SELECT order_id FROM orders WHERE order_id < $orderId ORDER BY order_id DESC LIMIT 1");
$prevOrder = mysqli_fetch_assoc($prevRes);
$prevId = $prevOrder['order_id'] ?? null;

// Get next order id
$nextRes = mysqli_query($conn, "SELECT order_id FROM orders WHERE order_id > $orderId ORDER BY order_id ASC LIMIT 1");
$nextOrder = mysqli_fetch_assoc($nextRes);
$nextId = $nextOrder['order_id'] ?? null;

// Function to convert gram to vori-ana-roti-point
function convertGramToVoriAna($gram) {
    if (!$gram || $gram <= 0) return '0V 0A 0R 0P';
    
    $totalPoints = round(($gram / 11.664) * 16 * 6 * 10);
    $bhori = floor($totalPoints / 960);
    $remainingPoints = $totalPoints % 960;
    $ana = floor($remainingPoints / 60);
    $remainingAfterAna = $remainingPoints % 60;
    $roti = floor($remainingAfterAna / 10);
    $point = $remainingAfterAna % 10;
    
    return "{$bhori}V {$ana}A {$roti}R {$point}P";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= htmlspecialchars($order['order_id']) ?> - Details</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
        }

        .main-card {
            border: none;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background-color: #495057;
            border: none;
            padding: 1.25rem 1.5rem;
        }

        .card-header h3 {
            margin: 0;
            font-weight: 600;
            font-size: 1.5rem;
        }

        .order-badge {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            display: inline-block;
            margin-top: 0.5rem;
        }

        .info-card {
            background-color: white;
            border-radius: 6px;
            padding: 1.25rem;
            border: 1px solid #dee2e6;
            height: 100%;
        }

        .info-card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 0.5rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #f8f9fa;
        }

        .info-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .info-label {
            font-weight: 500;
            color: #6c757d;
            font-size: 0.875rem;
        }

        .info-value {
            color: #212529;
            font-size: 0.875rem;
            font-weight: 500;
            text-align: right;
        }

        .items-table {
            margin-top: 0;
            font-size: 0.875rem;
        }

        .items-table thead th {
            background-color: #f8f9fa;
            color: #495057;
            font-weight: 600;
            padding: 0.75rem;
            border: 1px solid #dee2e6;
            white-space: nowrap;
        }

        .items-table tbody tr {
            border-bottom: 1px solid #dee2e6;
        }

        .items-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .items-table tbody td {
            padding: 0.75rem;
            vertical-align: middle;
        }

        .nav-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background-color: white;
            color: #495057;
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-btn:hover {
            background-color: #495057;
            color: white;
            border-color: #495057;
        }

        .total-section {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }

        .total-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #6c757d;
            margin-bottom: 0.25rem;
        }

        .total-amount {
            font-size: 1.75rem;
            font-weight: 700;
            color: #212529;
        }

        @media (max-width: 768px) {
            .info-row {
                flex-direction: column;
                gap: 0.25rem;
            }

            .info-value {
                text-align: left;
            }

            .items-table {
                font-size: 0.8rem;
            }

            .items-table thead th,
            .items-table tbody td {
                padding: 0.5rem 0.25rem;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid py-4" style="max-width: 1400px;">
    <div class="main-card">
        <!-- Header -->
        <div class="card-header text-white">
            <div class="d-flex justify-content-between align-items-start flex-wrap">
                <div>
                    <h3><i class="fas fa-file-invoice"></i> Order Details</h3>
                    <div class="order-badge">Order #<?= htmlspecialchars($order['order_id']) ?></div>
                </div>
                <small class="text-white-50">
                    Created: <?= date("d M Y, h:i A", strtotime($order['created_at'])) ?>
                </small>
            </div>
        </div>

        <!-- Content -->
        <div class="card-body p-4">
            <div class="row g-4">
                <!-- Left Sidebar - Customer & Order Info -->
                <div class="col-lg-4 col-xl-3">
                    <!-- Customer Information -->
                    <div class="info-card mb-3">
                        <div class="info-card-title">
                            <i class="fas fa-user"></i> Customer
                        </div>
                        <div class="info-row">
                            <span class="info-label">Name:</span>
                            <span class="info-value"><?= htmlspecialchars($order['customer_name']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Phone:</span>
                            <span class="info-value"><?= htmlspecialchars($order['customer_phone']) ?></span>
                        </div>
                        <?php if (!empty($order['customer_address'])): ?>
                        <div class="info-row">
                            <span class="info-label">Address:</span>
                            <span class="info-value"><?= htmlspecialchars($order['customer_address']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Additional Information -->
                    <div class="info-card mb-3">
                        <div class="info-card-title">
                            <i class="fas fa-info-circle"></i> Additional Info
                        </div>
                        <?php if (!empty($order['manufacturer'])): ?>
                        <div class="info-row">
                            <span class="info-label">Manufacturer:</span>
                            <span class="info-value"><?= htmlspecialchars($order['manufacturer']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($order['box_no'])): ?>
                        <div class="info-row">
                            <span class="info-label">Box No:</span>
                            <span class="info-value"><?= htmlspecialchars($order['box_no']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (empty($order['manufacturer']) && empty($order['box_no'])): ?>
                        <div class="text-muted text-center py-2">
                            <small><i>No additional information</i></small>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Payment Status -->
                    <div class="info-card mb-3">
                        <div class="info-card-title">
                            <i class="fas fa-credit-card"></i> Payment Status
                        </div>
                        <div class="text-center">
                            <?php if ($order['status'] === 'paid'): ?>
                                <span class="badge bg-success px-3 py-2">
                                    <i class="fas fa-check-circle"></i> PAID
                                </span>
                            <?php elseif ($order['status'] === 'cancelled'): ?>
                                <span class="badge bg-danger px-3 py-2">
                                    <i class="fas fa-times-circle"></i> CANCELLED
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark px-3 py-2">
                                    <i class="fas fa-clock"></i> PENDING
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Total Amount -->
                    <div class="info-card">
                        <div class="info-card-title">
                            <i class="fas fa-calculator"></i> Total Amount
                        </div>
                        <div class="total-section text-center">
                            <div class="total-label">Total</div>
                            <div class="total-amount">৳<?= number_format($totalAmount, 2) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Right Content - Items Table -->
                <div class="col-lg-8 col-xl-9">
                    <div class="info-card">
                        <div class="info-card-title">
                            <i class="fas fa-list"></i> Order Items (<?= count($items) ?>)
                        </div>
                        
                        <?php if (!empty($items)): ?>
                        <div class="table-responsive">
                            <table class="table items-table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Item Name</th>
                                        <th>Service</th>
                                        <th class="text-center">Karat</th>
                                        <th class="text-end">Weight</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-end">Unit Price</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $index => $item): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?= htmlspecialchars($item['service_name'] ?? 'N/A') ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?= htmlspecialchars($item['karat'] ?? '-') ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if (!empty($item['weight']) && floatval($item['weight']) > 0): ?>
                                                <div><?= number_format(floatval($item['weight']), 2) ?> gm</div>
                                                <small class="text-muted">
                                                    <?= convertGramToVoriAna(floatval($item['weight'])) ?>
                                                </small>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?= number_format(floatval($item['quantity']), 0) ?>
                                        </td>
                                        <td class="text-end">
                                            ৳<?= number_format(floatval($item['unit_price']), 2) ?>
                                        </td>
                                        <td class="text-end">
                                            <strong>৳<?= number_format(floatval($item['total_price']), 2) ?></strong>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-secondary">
                                        <td colspan="7" class="text-end"><strong>Grand Total:</strong></td>
                                        <td class="text-end">
                                            <strong class="fs-5">৳<?= number_format($totalAmount, 2) ?></strong>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle"></i> No items found in this order.
                        </div>
                        <?php endif; ?>
                        
                        <!-- Navigation Buttons -->
                        <div class="mt-4 pt-3 border-top d-flex gap-2 justify-content-between flex-wrap">
                            <div class="d-flex gap-2">
                                <?php if ($prevId): ?>
                                    <a href="bill.php?id=<?= $prevId ?>" class="nav-btn">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                <?php endif; ?>
                                <?php if ($nextId): ?>
                                    <a href="bill.php?id=<?= $nextId ?>" class="nav-btn">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div>
                                <a href="reports.php" class="nav-btn">
                                    <i class="fas fa-arrow-left"></i> Back to Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>