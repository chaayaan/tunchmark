<?php include 'navbar.php'; ?>
<?php
require 'auth.php';
if (!in_array($_SESSION['role'], ['admin','employee'])) {
    header("Location: dashboard.php");
    exit;
}

// Database
include 'mydb.php';

// Fetch all active services from database
$servicesListQuery = "SELECT id, name FROM services WHERE is_active = 1 ORDER BY name ASC";
$servicesListResult = mysqli_query($conn, $servicesListQuery);
$servicesList = [];
while ($row = mysqli_fetch_assoc($servicesListResult)) {
    $servicesList[] = $row;
}

// Set default date range to current month if no filters are applied
$isDefaultFilter = false;
if (empty($_GET['order_id']) && empty($_GET['from_date']) && empty($_GET['to_date']) && empty($_GET['year']) && empty($_GET['service'])) {
    $_GET['from_date'] = date('Y-m-01'); // First day of current month
    $_GET['to_date'] = date('Y-m-t');     // Last day of current month
    $isDefaultFilter = true;
}

// Pagination settings
$records_per_page = 50;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $records_per_page;

// Filters
$where = [];
$params = [];
$types = "";

if (!empty($_GET['order_id'])) {
    $where[] = "o.order_id = ?";
    $params[] = intval($_GET['order_id']);
    $types .= "i";
}

// Date range takes priority over year filter
if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $where[] = "DATE(o.created_at) BETWEEN ? AND ?";
    $params[] = $_GET['from_date'];
    $params[] = $_GET['to_date'];
    $types .= "ss";
} elseif (!empty($_GET['year'])) {
    $where[] = "YEAR(o.created_at) = ?";
    $params[] = intval($_GET['year']);
    $types .= "i";
}

// Service filter
$serviceFilter = !empty($_GET['service']) ? intval($_GET['service']) : 0;
if ($serviceFilter > 0) {
    $where[] = "bi.service_id = ?";
    $params[] = $serviceFilter;
    $types .= "i";
}

// Build WHERE clause
$whereClause = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

// Count total unique orders
$count_sql = "SELECT COUNT(DISTINCT o.order_id) as total 
              FROM orders o 
              JOIN bill_items bi ON o.order_id = bi.order_id" . $whereClause;

$count_stmt = mysqli_prepare($conn, $count_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_records = mysqli_fetch_assoc($count_result)['total'];
mysqli_stmt_close($count_stmt);

$total_pages = ceil($total_records / $records_per_page);

// Get orders for current page with all details using GROUP_CONCAT
$pagination_params = $params;
$pagination_types = $types;
$pagination_params[] = $records_per_page;
$pagination_params[] = $offset;
$pagination_types .= "ii";

$sql = "SELECT 
            o.order_id,
            o.customer_name,
            o.customer_phone,
            o.status,
            o.created_at,
            SUM(bi.total_price) AS total_amount,
            GROUP_CONCAT(
                CONCAT(
                    COALESCE(i.name, '-'), '|',
                    COALESCE(s.name, '-'), '|',
                    bi.quantity, '|',
                    bi.unit_price, '|',
                    bi.total_price
                ) 
                SEPARATOR '|||'
            ) AS items_data
        FROM orders o
        JOIN bill_items bi ON o.order_id = bi.order_id
        LEFT JOIN items i ON bi.item_id = i.id
        LEFT JOIN services s ON bi.service_id = s.id"
        . $whereClause . 
        " GROUP BY o.order_id
        ORDER BY o.order_id DESC 
        LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($pagination_params)) {
    mysqli_stmt_bind_param($stmt, $pagination_types, ...$pagination_params);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$orders = [];
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
mysqli_stmt_close($stmt);

// Get summary data (all records, not just current page)
$summary_sql = "SELECT o.order_id, o.status,
                       bi.service_id, bi.quantity, bi.total_price,
                       s.name as service_name
                FROM orders o
                JOIN bill_items bi ON o.order_id = bi.order_id
                LEFT JOIN services s ON bi.service_id = s.id"
                . $whereClause;

$summary_stmt = mysqli_prepare($conn, $summary_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($summary_stmt, $types, ...$params);
}
mysqli_stmt_execute($summary_stmt);
$summary_res = mysqli_stmt_get_result($summary_stmt);

$totalPaid = $totalPending = $totalCancelled = $grandTotal = 0;
$countPaid = $countPending = $countCancelled = $countTotal = 0;

// Initialize service summary
$servicesSummary = [];
foreach ($servicesList as $service) {
    $servicesSummary[$service['name']] = ['qty' => 0, 'total' => 0];
}

$processedOrders = [];

while ($row = mysqli_fetch_assoc($summary_res)) {
    $orderId = $row['order_id'];
    
    // Count each order only once
    if (!isset($processedOrders[$orderId])) {
        $processedOrders[$orderId] = [
            'status' => $row['status'],
            'total' => 0
        ];
        $countTotal++;
        
        if ($row['status'] === 'paid') {
            $countPaid++;
        } elseif ($row['status'] === 'pending') {
            $countPending++;
        } elseif ($row['status'] === 'cancelled') {
            $countCancelled++;
        }
    }
    
    // Accumulate order total
    $itemTotal = floatval($row['total_price'] ?? 0);
    $processedOrders[$orderId]['total'] += $itemTotal;
    
    // Service counts only for paid orders
    if ($row['status'] === 'paid') {
        $serviceName = $row['service_name'] ?? '';
        $qty = floatval($row['quantity'] ?? 0);
        
        if (!empty($serviceName)) {
            $matched = false;
            foreach ($servicesSummary as $key => &$summary) {
                if (strcasecmp($key, $serviceName) === 0) {
                    $summary['qty'] += $qty;
                    $summary['total'] += $itemTotal;
                    $matched = true;
                    break;
                }
            }
            
            if (!$matched) {
                if (!isset($servicesSummary['Others'])) {
                    $servicesSummary['Others'] = ['qty' => 0, 'total' => 0];
                }
                $servicesSummary['Others']['qty'] += $qty;
                $servicesSummary['Others']['total'] += $itemTotal;
            }
        }
    }
}
mysqli_stmt_close($summary_stmt);

// Calculate totals from processed orders
foreach ($processedOrders as $orderData) {
    $grandTotal += $orderData['total'];
    
    if ($orderData['status'] === 'paid') {
        $totalPaid += $orderData['total'];
    } elseif ($orderData['status'] === 'pending') {
        $totalPending += $orderData['total'];
    } elseif ($orderData['status'] === 'cancelled') {
        $totalCancelled += $orderData['total'];
    }
}

// Get daily expenses total for the filtered period
$dailyExpensesTotal = 0;
$expenses_where = [];
$expenses_params = [];
$expenses_types = "";

if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $expenses_where[] = "DATE(created_time) BETWEEN ? AND ?";
    $expenses_params[] = $_GET['from_date'];
    $expenses_params[] = $_GET['to_date'];
    $expenses_types .= "ss";
} elseif (!empty($_GET['year'])) {
    $expenses_where[] = "YEAR(created_time) = ?";
    $expenses_params[] = intval($_GET['year']);
    $expenses_types .= "i";
}

$expenses_sql = "SELECT SUM(amount) as total FROM daily_expenses";
if (!empty($expenses_where)) {
    $expenses_sql .= " WHERE " . implode(" AND ", $expenses_where);
}

$expenses_stmt = mysqli_prepare($conn, $expenses_sql);
if (!empty($expenses_params)) {
    mysqli_stmt_bind_param($expenses_stmt, $expenses_types, ...$expenses_params);
}
mysqli_stmt_execute($expenses_stmt);
$expenses_result = mysqli_stmt_get_result($expenses_stmt);
$dailyExpensesTotal = mysqli_fetch_assoc($expenses_result)['total'] ?? 0;
mysqli_stmt_close($expenses_stmt);

// Calculate net profit
$netProfit = $totalPaid - $dailyExpensesTotal;

// Service icons mapping
$serviceIcons = [
    'Hallmark' => 'fa-stamp text-primary',
    'Purity Test' => 'fa-vial text-success',
    'Welding' => 'fa-fire text-danger',
    'Melting' => 'fa-burn text-warning',
    'Polishing' => 'fa-star text-info',
    'Engraving' => 'fa-pen text-secondary',
    'Others' => 'fa-ellipsis-h text-secondary'
];

// Function to generate pagination URL
function getPaginationUrl($page_num) {
    $params = $_GET;
    $params['page'] = $page_num;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Billing Reports</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background: #f5f7fa;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        
        /* Modern Stat Cards */
        .modern-stat-card {
            border-radius: 12px;
            border: 1px solid #e8ecf1;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
            background: white;
        }
        
        .modern-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border-color: #d1d9e6;
        }
        
        .stat-icon-wrapper {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .stat-icon-wrapper i {
            font-size: 20px;
        }
        
        .stat-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .stat-title {
            font-size: 0.875rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
            line-height: 1.2;
        }
        
        /* Professional color variations */
        .stat-blue { 
            background: white;
            border-left: 4px solid #3b82f6;
        }
        .stat-blue .stat-icon-wrapper { 
            background: #eff6ff; 
            color: #3b82f6; 
        }
        .stat-blue .stat-value { color: #1e293b; }
        .stat-blue .stat-amount { color: #3b82f6; }
        
        .stat-green { 
            background: white;
            border-left: 4px solid #10b981;
        }
        .stat-green .stat-icon-wrapper { 
            background: #ecfdf5; 
            color: #10b981; 
        }
        .stat-green .stat-value { color: #1e293b; }
        .stat-green .stat-amount { color: #10b981; }
        
        .stat-orange { 
            background: white;
            border-left: 4px solid #f59e0b;
        }
        .stat-orange .stat-icon-wrapper { 
            background: #fffbeb; 
            color: #f59e0b; 
        }
        .stat-orange .stat-value { color: #1e293b; }
        .stat-orange .stat-amount { color: #f59e0b; }
        
        .stat-red { 
            background: white;
            border-left: 4px solid #ef4444;
        }
        .stat-red .stat-icon-wrapper { 
            background: #fef2f2; 
            color: #ef4444; 
        }
        .stat-red .stat-value { color: #1e293b; }
        .stat-red .stat-amount { color: #ef4444; }
        
        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 4px;
        }
        
        .stat-amount {
            font-size: 1.25rem;
            font-weight: 600;
            line-height: 1.2;
        }
        
        /* Info Cards */
        .info-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #e8ecf1;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
        }
        
        .info-card-header {
            border-bottom: 1px solid #e8ecf1;
            padding: 20px 24px;
            background: #fafbfc;
            border-radius: 12px 12px 0 0;
        }
        
        .info-card-header h5 {
            font-size: 1.125rem;
            font-weight: 600;
            margin: 0;
            color: #1e293b;
        }
        
        /* Table Styling */
        .modern-table {
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .modern-table thead th {
            background: #fafbfc;
            border: none;
            border-bottom: 2px solid #e8ecf1;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            padding: 12px 16px;
        }
        
        .modern-table tbody td {
            border-top: 1px solid #f1f3f5;
            padding: 12px 16px;
            vertical-align: middle;
            color: #1e293b;
        }
        
        .modern-table tbody tr:hover {
            background: #fafbfc;
        }
        
        .modern-table tfoot td {
            background: #fafbfc;
            font-weight: 600;
            padding: 12px 16px;
            border-top: 2px solid #e8ecf1;
        }
        
        .search-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
            margin-bottom: 24px;
            border: 1px solid #e8ecf1;
        }
        
        .page-header {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
            margin-bottom: 24px;
            border: 1px solid #e8ecf1;
        }
        
        .page-header h3 {
            font-size: 1.75rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }

        .filter-badge {
            display: inline-block;
            background: #eff6ff;
            color: #3b82f6;
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-right: 8px;
            margin-bottom: 8px;
            border: 1px solid #dbeafe;
        }

        .filter-badge i {
            margin-left: 6px;
            cursor: pointer;
        }

        .filter-badge.default-filter {
            background: #f3e5f5;
            color: #7b1fa2;
            border-color: #e1bee7;
        }
        
        .filter-badge a {
            color: inherit;
        }
        
        .filter-badge a:hover {
            color: #1e293b;
        }
        
        @media print { 
            .no-print { display: none; }
            body { background: white; }
        }
        
        @media (max-width: 768px) {
            .stat-value { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
<div class="container-fluid p-4">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h3><i class="fas fa-chart-line me-2"></i>Billing Reports</h3>
            
            <!-- Active Filters Display -->
            <?php if (!empty($_GET['order_id']) || !empty($_GET['from_date']) || !empty($_GET['year']) || !empty($_GET['service'])): ?>
            <div class="mt-2">
                <small class="text-muted me-2">Active Filters:</small>
                <?php if (!empty($_GET['order_id'])): ?>
                    <span class="filter-badge">
                        Order: #<?= htmlspecialchars($_GET['order_id']) ?>
                        <a href="?<?= http_build_query(array_diff_key($_GET, ['order_id' => ''])) ?>" class="text-decoration-none">
                            <i class="fas fa-times"></i>
                        </a>
                    </span>
                <?php endif; ?>
                
                <?php if (!empty($_GET['from_date']) && !empty($_GET['to_date'])): ?>
                    <span class="filter-badge <?= $isDefaultFilter ? 'default-filter' : '' ?>">
                        <?= $isDefaultFilter ? '<i class="fas fa-calendar-check me-1"></i>' : '' ?>
                        Date: <?= htmlspecialchars($_GET['from_date']) ?> to <?= htmlspecialchars($_GET['to_date']) ?>
                        <?= $isDefaultFilter ? ' (Current Month)' : '' ?>
                        <?php if (!$isDefaultFilter): ?>
                        <a href="?<?= http_build_query(array_diff_key($_GET, ['from_date' => '', 'to_date' => ''])) ?>" class="text-decoration-none">
                            <i class="fas fa-times"></i>
                        </a>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
                
                <?php if (!empty($_GET['year'])): ?>
                    <span class="filter-badge">
                        Year: <?= htmlspecialchars($_GET['year']) ?>
                        <a href="?<?= http_build_query(array_diff_key($_GET, ['year' => ''])) ?>" class="text-decoration-none">
                            <i class="fas fa-times"></i>
                        </a>
                    </span>
                <?php endif; ?>

                <?php if (!empty($_GET['service'])): ?>
                    <?php
                    $selectedServiceName = '';
                    foreach ($servicesList as $svc) {
                        if ($svc['id'] == $_GET['service']) {
                            $selectedServiceName = $svc['name'];
                            break;
                        }
                    }
                    ?>
                    <span class="filter-badge">
                        Service: <?= htmlspecialchars($selectedServiceName) ?>
                        <a href="?<?= http_build_query(array_diff_key($_GET, ['service' => ''])) ?>" class="text-decoration-none">
                            <i class="fas fa-times"></i>
                        </a>
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div>
            <span class="badge bg-secondary me-2">
                Page <?= $page ?> of <?= max(1, $total_pages) ?> (<?= $total_records ?> total)
            </span>
            <div class="btn-group me-2 no-print" role="group">
                <a href="export_report_csv.php?<?= http_build_query($_GET) ?>" class="btn btn-sm btn-success">
                    <i class="fas fa-file-csv"></i> Export CSV
                </a>
            </div>
            <a href="reports.php" class="btn btn-sm btn-outline-secondary no-print">
                <i class="fas fa-undo"></i> Reset All
            </a>
        </div>
    </div>

    <!-- Search Form -->
    <div class="search-card no-print">
        <form class="row g-3" method="get">
            <div class="col-md-2">
                <label class="form-label">Order ID</label>
                <input type="number" name="order_id" class="form-control" placeholder="Enter Order ID" 
                       value="<?= htmlspecialchars($_GET['order_id'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Service</label>
                <select name="service" class="form-select">
                    <option value="">All Services</option>
                    <?php foreach ($servicesList as $service): ?>
                        <option value="<?= $service['id'] ?>" 
                                <?= (isset($_GET['service']) && $_GET['service'] == $service['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($service['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">From Date</label>
                <input type="date" name="from_date" class="form-control" 
                       value="<?= htmlspecialchars($_GET['from_date'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To Date</label>
                <input type="date" name="to_date" class="form-control" 
                       value="<?= htmlspecialchars($_GET['to_date'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Year</label>
                <input type="number" name="year" class="form-control" placeholder="e.g. <?= date('Y') ?>" 
                       value="<?= htmlspecialchars($_GET['year'] ?? '') ?>" min="2000" max="<?= date('Y') + 1 ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="modern-stat-card stat-green">
                <div class="card-body p-4">
                    <div class="stat-header">
                        <div class="stat-icon-wrapper">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-title">Paid Orders</div>
                    </div>
                    <div class="stat-value"><?= $countPaid ?></div>
                    <div class="stat-amount">৳<?= number_format($totalPaid, 0) ?></div>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-md-3">
            <div class="modern-stat-card stat-orange">
                <div class="card-body p-4">
                    <div class="stat-header">
                        <div class="stat-icon-wrapper">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-title">Pending Orders</div>
                    </div>
                    <div class="stat-value"><?= $countPending ?></div>
                    <div class="stat-amount">৳<?= number_format($totalPending, 0) ?></div>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-md-3">
            <div class="modern-stat-card stat-red">
                <div class="card-body p-4">
                    <div class="stat-header">
                        <div class="stat-icon-wrapper">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-title">Cancelled Orders</div>
                    </div>
                    <div class="stat-value"><?= $countCancelled ?></div>
                    <div class="stat-amount">৳<?= number_format($totalCancelled, 0) ?></div>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-md-3">
            <div class="modern-stat-card stat-blue">
                <div class="card-body p-4">
                    <div class="stat-header">
                        <div class="stat-icon-wrapper">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="stat-title">All Orders</div>
                    </div>
                    <div class="stat-value"><?= $countTotal ?></div>
                    <div class="stat-amount">৳<?= number_format($grandTotal, 0) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Summary -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="info-card">
                <div class="info-card-header">
                    <h5><i class="fas fa-chart-pie me-2"></i>Service Summary (Paid Only)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table modern-table mb-0">
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Total (৳)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $totalQty = 0;
                                $totalAmount = 0;
                                foreach ($servicesSummary as $serviceName => $data): 
                                    if ($data['qty'] == 0 && $data['total'] == 0) continue;
                                    $totalQty += $data['qty'];
                                    $totalAmount += $data['total'];
                                    $iconClass = $serviceIcons[$serviceName] ?? 'fa-cog text-secondary';
                                ?>
                                <tr>
                                    <td><i class="fas <?= $iconClass ?> me-2"></i><?= htmlspecialchars($serviceName) ?></td>
                                    <td class="text-center"><?= $data['qty'] ?></td>
                                    <td class="text-end"><?= number_format($data['total'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if ($totalQty == 0): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">
                                        <i class="fas fa-info-circle me-2"></i>No paid services for this date
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                            <?php if ($totalQty > 0): ?>
                            <tfoot>
                                <tr>
                                    <td><strong>Total</strong></td>
                                    <td class="text-center"><strong><?= $totalQty ?></strong></td>
                                    <td class="text-end"><strong>৳<?= number_format($totalAmount, 2) ?></strong></td>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="info-card">
                <div class="info-card-header">
                    <h5><i class="fas fa-calculator me-2"></i>Financial Summary</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table modern-table mb-0">
                            <tbody>
                                <tr>
                                    <td><i class="fas fa-arrow-up text-success me-2"></i><strong>Total Revenue (Paid Orders)</strong></td>
                                    <td class="text-end text-success"><strong>৳<?= number_format($totalPaid, 2) ?></strong></td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-arrow-down text-danger me-2"></i><strong>Daily Expenses</strong></td>
                                    <td class="text-end text-danger"><strong>৳<?= number_format($dailyExpensesTotal, 2) ?></strong></td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="<?= $netProfit >= 0 ? 'table-success' : 'table-danger' ?>">
                                    <td><i class="fas fa-chart-line me-2"></i><strong>Net Profit</strong></td>
                                    <td class="text-end"><strong>৳<?= number_format($netProfit, 2) ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Orders Table -->
    <div class="info-card">
        <div class="info-card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-list me-2"></i>Orders List</h5>
            <small class="text-muted no-print">
                Showing <?= count($orders) ?> of <?= $total_records ?> orders
            </small>
        </div>
        <div class="card-body p-0">
            <?php if (empty($orders)): ?>
                <div class="p-5 text-center">
                    <i class="fas fa-info-circle text-muted fa-3x mb-3"></i>
                    <p class="text-muted">No orders found for the selected filter.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th>Items & Services</th>
                                <th class="text-end">Total (৳)</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th class="no-print">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><strong>#<?= htmlspecialchars($order['order_id']) ?></strong></td>
                                <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                <td><?= htmlspecialchars($order['customer_phone']) ?></td>
                                <td>
                                    <ul class="mb-0" style="font-size: 0.85em; list-style: none; padding-left: 0;">
                                        <?php if (!empty($order['items'])): ?>
                                            <?php foreach ($order['items'] as $item): ?>
                                                <li class="mb-1">
                                                    <small>
                                                        <?= htmlspecialchars($item['item_name']) ?> — 
                                                        <?= htmlspecialchars($item['service_name']) ?> — 
                                                        <?= number_format((float)$item['quantity'], 0) ?> × 
                                                        ৳<?= number_format((float)$item['unit_price'], 2) ?> = 
                                                        <strong>৳<?= number_format((float)$item['total_price'], 2) ?></strong>
                                                    </small>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <li><em class="text-muted">No items</em></li>
                                        <?php endif; ?>
                                    </ul>
                                </td>
                                <td class="text-end"><strong>৳<?= number_format($order['total_amount'], 2) ?></strong></td>
                                <td>
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
                                <td><small><?= date('M d, Y', strtotime($order['created_at'])) ?></small></td>
                                <td class="no-print">
                                    <div class="btn-group" role="group">
                                        <a href="bill.php?id=<?= $order['order_id'] ?>" 
                                        class="btn btn-sm btn-outline-primary" 
                                        title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button onclick="printOrder(<?= $order['order_id'] ?>)" 
                                                class="btn btn-sm btn-outline-success" 
                                                title="Print Receipt">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="p-3 border-top no-print">
                    <nav aria-label="Orders pagination">
                        <ul class="pagination justify-content-center mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= getPaginationUrl(1) ?>">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="<?= getPaginationUrl($page - 1) ?>">
                                        <i class="fas fa-angle-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= getPaginationUrl($i) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= getPaginationUrl($page + 1) ?>">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="<?= getPaginationUrl($total_pages) ?>">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
function printOrder(orderId) {
    // Open print page in new window
    const printWindow = window.open(
        'print_order.php?id=' + orderId, 
        'Print Order #' + orderId,
        'width=400,height=600,scrollbars=yes,resizable=yes'
    );
    
    // Focus on the new window
    if (printWindow) {
        printWindow.focus();
    } else {
        alert('Please allow popups to print receipts');
    }
}
</script>
</body>
</html>