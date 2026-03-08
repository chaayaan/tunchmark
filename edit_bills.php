<?php include 'navbar.php'; ?>
<?php include 'mydb.php'; ?>
<?php
// edit_orders.php
if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}
// Pagination settings
$limit = 100; // 100 orders per page
$page = max(1, intval($_GET['page'] ?? 1)); // Current page, minimum 1
$offset = ($page - 1) * $limit;

// Search filters
$searchOrderId = trim($_GET['order_id'] ?? '');
$searchCustomer = trim($_GET['customer'] ?? '');
$searchStatus = $_GET['status'] ?? '';

// Build WHERE clause
$where = [];
$params = [];
$types = "";

if ($searchOrderId !== '') {
    $where[] = "o.order_id = ?";
    $params[] = intval($searchOrderId);
    $types .= "i";
}

if ($searchCustomer !== '') {
    $where[] = "(o.customer_name LIKE ? OR o.customer_phone LIKE ?)";
    $params[] = "%$searchCustomer%";
    $params[] = "%$searchCustomer%";
    $types .= "ss";
}

if ($searchStatus !== '' && in_array($searchStatus, ['paid', 'pending', 'cancelled'])) {
    $where[] = "o.status = ?";
    $params[] = $searchStatus;
    $types .= "s";
}

$whereClause = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

// Count total records for pagination
$countSql = "SELECT COUNT(DISTINCT o.order_id) as total FROM orders o" . $whereClause;

$countStmt = mysqli_prepare($conn, $countSql);
if (!empty($params)) {
    mysqli_stmt_bind_param($countStmt, $types, ...$params);
}
mysqli_stmt_execute($countStmt);
$countRes = mysqli_stmt_get_result($countStmt);
$totalRecords = mysqli_fetch_assoc($countRes)['total'];
mysqli_stmt_close($countStmt);

$totalPages = ceil($totalRecords / $limit);

// Fetch orders with GROUP_CONCAT to avoid duplicates
$paginationParams = $params;
$paginationTypes = $types;
$paginationParams[] = $limit;
$paginationParams[] = $offset;
$paginationTypes .= "ii";

$sql = "SELECT 
            o.order_id,
            o.customer_name,
            o.customer_phone,
            o.status,
            o.created_at,
            COUNT(bi.bill_item_id) as item_count,
            SUM(bi.total_price) AS calculated_total,
            GROUP_CONCAT(
                CONCAT(
                    COALESCE(i.name, '-'), '|',
                    COALESCE(s.name, '-'), '|',
                    bi.quantity, '|',
                    bi.unit_price, '|',
                    bi.total_price
                ) 
                ORDER BY bi.bill_item_id ASC
                SEPARATOR '|||'
            ) AS items_data
        FROM orders o
        LEFT JOIN bill_items bi ON o.order_id = bi.order_id
        LEFT JOIN items i ON bi.item_id = i.id
        LEFT JOIN services s ON bi.service_id = s.id
        $whereClause
        GROUP BY o.order_id
        ORDER BY o.order_id DESC 
        LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($paginationParams)) {
    mysqli_stmt_bind_param($stmt, $paginationTypes, ...$paginationParams);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$orders = [];
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        // Parse the items_data into array
        $row['items'] = [];
        if (!empty($row['items_data'])) {
            $items_raw = explode('|||', $row['items_data']);
            foreach ($items_raw as $item_str) {
                $item_parts = explode('|', $item_str);
                if (count($item_parts) >= 5) {
                    $row['items'][] = [
                        'item_name' => $item_parts[0],
                        'service_name' => $item_parts[1],
                        'quantity' => $item_parts[2],
                        'unit_price' => $item_parts[3],
                        'total_price' => $item_parts[4]
                    ];
                }
            }
        }
        unset($row['items_data']); // Remove raw data
        $orders[] = $row;
    }
}
mysqli_stmt_close($stmt);

// Calculate record range for display
$startRecord = $offset + 1;
$endRecord = min($offset + $limit, $totalRecords);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Admin</title>
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
            background-color: #495057;
            color: white;
            border: none;
            padding: 1.25rem 1.5rem;
        }
        
        .search-card {
            background-color: white;
            border-radius: 6px;
            padding: 1.25rem;
            border: 1px solid #dee2e6;
            margin-bottom: 1.5rem;
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
        
        .items-list {
            list-style: none;
            padding-left: 0;
            margin-bottom: 0;
            font-size: 0.8rem;
        }
        
        .items-list li {
            padding: 0.25rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .items-list li:last-child {
            border-bottom: none;
        }
        
        @media (max-width: 768px) {
            .table {
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid py-4" style="max-width: 1600px;">
    <div class="main-card">
        <!-- Header -->
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h3 class="mb-0"><i class="fas fa-edit"></i> Manage Orders</h3>
                <small class="text-white-50">Edit and manage all orders</small>
            </div>
            <a href="dashboard.php" class="btn btn-light btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <div class="card-body p-4">
            <!-- Search Form -->
            <div class="search-card">
                <form method="get" class="row g-3">
                    <input type="hidden" name="page" value="1">
                    
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Order ID</label>
                        <input type="number" name="order_id" class="form-control" 
                               placeholder="Enter Order ID"
                               value="<?= htmlspecialchars($searchOrderId) ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Customer</label>
                        <input type="text" name="customer" class="form-control" 
                               placeholder="Name or Phone"
                               value="<?= htmlspecialchars($searchCustomer) ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="paid" <?= $searchStatus === 'paid' ? 'selected' : '' ?>>Paid</option>
                            <option value="pending" <?= $searchStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="cancelled" <?= $searchStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button class="btn btn-primary flex-fill" type="submit">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <?php if (!empty($searchOrderId) || !empty($searchCustomer) || !empty($searchStatus)): ?>
                            <a href="edit_bills.php" class="btn btn-outline-secondary">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Records Info -->
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div class="text-muted">
                    <?php if ($totalRecords > 0): ?>
                        Showing <strong><?= number_format($startRecord) ?></strong> to 
                        <strong><?= number_format($endRecord) ?></strong> of 
                        <strong><?= number_format($totalRecords) ?></strong> orders
                    <?php else: ?>
                        No orders found
                    <?php endif; ?>
                </div>
                <div class="text-muted">
                    Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong>
                </div>
            </div>

            <?php if (empty($orders)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No orders found matching your search criteria.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-bordered align-middle">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Order ID</th>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th>Items & Services</th>
                                <th class="text-center">Count</th>
                                <th class="text-end">Total (৳)</th>
                                <th class="text-center">Status</th>
                                <th>Date</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>
                                    <strong>#<?= htmlspecialchars($order['order_id']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                <td>
                                    <small><?= htmlspecialchars($order['customer_phone']) ?></small>
                                </td>
                                <td>
                                    <ul class="items-list">
                                        <?php if (!empty($order['items'])): ?>
                                            <?php foreach ($order['items'] as $item): ?>
                                                <li>
                                                    <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                                                    — <?= htmlspecialchars($item['service_name']) ?>
                                                    — <?= number_format(floatval($item['quantity']), 0) ?> × 
                                                    ৳<?= number_format(floatval($item['unit_price']), 2) ?> = 
                                                    <strong>৳<?= number_format(floatval($item['total_price']), 2) ?></strong>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <li><em class="text-muted">No items</em></li>
                                        <?php endif; ?>
                                    </ul>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?= $order['item_count'] ?></span>
                                </td>
                                <td class="text-end">
                                    <strong>৳<?= number_format($order['calculated_total'], 2) ?></strong>
                                </td>
                                <td class="text-center">
                                    <?php if ($order['status'] === 'paid'): ?>
                                        <span class="badge bg-success">Paid</span>
                                    <?php elseif ($order['status'] === 'pending'): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php elseif ($order['status'] === 'cancelled'): ?>
                                        <span class="badge bg-danger">Cancelled</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Unknown</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?= date('d M Y', strtotime($order['created_at'])) ?></small>
                                    <br>
                                    <small class="text-muted"><?= date('h:i A', strtotime($order['created_at'])) ?></small>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="bill.php?id=<?= $order['order_id'] ?>" 
                                           class="btn btn-outline-info"
                                           title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_bill_form.php?id=<?= $order['order_id'] ?>" 
                                           class="btn btn-outline-primary"
                                           title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Orders pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <!-- First and Previous buttons -->
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                        <i class="fas fa-angle-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <!-- Page numbers -->
                            <?php
                            $startPage = max(1, $page - 5);
                            $endPage = min($totalPages, $page + 5);
                            
                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <!-- Next and Last buttons -->
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>

                    <!-- Quick page jump -->
                    <div class="d-flex justify-content-center mt-3">
                        <form method="get" class="d-flex align-items-center gap-2">
                            <?php foreach ($_GET as $key => $value): ?>
                                <?php if ($key !== 'page'): ?>
                                    <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <label class="mb-0">Jump to page:</label>
                            <input type="number" name="page" class="form-control form-control-sm" 
                                   style="width: 80px;" 
                                   min="1" max="<?= $totalPages ?>" value="<?= $page ?>">
                            <button type="submit" class="btn btn-sm btn-outline-primary">Go</button>
                        </form>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>