<?php
require 'auth.php';
require 'mydb.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

// Pagination settings
$limit = 100;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Search filters
$searchOrderId  = trim($_GET['order_id'] ?? '');
$searchCustomer = trim($_GET['customer'] ?? '');
$searchStatus   = $_GET['status'] ?? '';

// Build WHERE clause
$where  = [];
$params = [];
$types  = "";

if ($searchOrderId !== '') {
    $where[]  = "o.order_id = ?";
    $params[] = intval($searchOrderId);
    $types   .= "i";
}
if ($searchCustomer !== '') {
    $where[]  = "(o.customer_name LIKE ? OR o.customer_phone LIKE ?)";
    $params[] = "%$searchCustomer%";
    $params[] = "%$searchCustomer%";
    $types   .= "ss";
}
if ($searchStatus !== '' && in_array($searchStatus, ['paid', 'pending', 'cancelled'])) {
    $where[]  = "o.status = ?";
    $params[] = $searchStatus;
    $types   .= "s";
}

$whereClause = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

// Count total records
$countSql  = "SELECT COUNT(DISTINCT o.order_id) as total FROM orders o" . $whereClause;
$countStmt = mysqli_prepare($conn, $countSql);
if (!empty($params)) mysqli_stmt_bind_param($countStmt, $types, ...$params);
mysqli_stmt_execute($countStmt);
$totalRecords = mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['total'];
mysqli_stmt_close($countStmt);

$totalPages = ceil($totalRecords / $limit);

// Fetch orders
$paginationParams   = $params;
$paginationTypes    = $types;
$paginationParams[] = $limit;
$paginationParams[] = $offset;
$paginationTypes   .= "ii";

$sql = "SELECT 
            o.order_id, o.customer_name, o.customer_phone,
            o.status, o.created_at,
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
if (!empty($paginationParams)) mysqli_stmt_bind_param($stmt, $paginationTypes, ...$paginationParams);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$orders = [];
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $row['items'] = [];
        if (!empty($row['items_data'])) {
            foreach (explode('|||', $row['items_data']) as $item_str) {
                $p = explode('|', $item_str);
                if (count($p) >= 5) {
                    $row['items'][] = [
                        'item_name'    => $p[0],
                        'service_name' => $p[1],
                        'quantity'     => $p[2],
                        'unit_price'   => $p[3],
                        'total_price'  => $p[4],
                    ];
                }
            }
        }
        unset($row['items_data']);
        $orders[] = $row;
    }
}
mysqli_stmt_close($stmt);

$startRecord = $offset + 1;
$endRecord   = min($offset + $limit, $totalRecords);

$hasFilter = $searchOrderId !== '' || $searchCustomer !== '' || $searchStatus !== '';

include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Orders — Rajaiswari</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:       #f1f3f6;
            --surface:  #ffffff;
            --s2:       #fafbfc;
            --border:   #e4e7ec;
            --bsoft:    #f0f1f3;
            --t1:       #111827;
            --t2:       #374151;
            --t3:       #6b7280;
            --t4:       #9ca3af;
            --blue:     #2563eb; --blue-bg: #eff6ff; --blue-b: #bfdbfe;
            --green:    #059669; --green-bg:#ecfdf5; --green-b:#a7f3d0;
            --amber:    #d97706; --amber-bg:#fffbeb; --amber-b:#fde68a;
            --red:      #dc2626; --red-bg:  #fef2f2; --red-b:  #fecaca;
            --violet:   #7c3aed; --violet-bg:#f5f3ff;
            --cyan:     #0891b2; --cyan-bg: #ecfeff; --cyan-b: #a5f3fc;
            --r: 10px; --rs: 6px;
            --sh: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', -apple-system, sans-serif;
            font-size: 14px;
            background: var(--bg);
            color: var(--t1);
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
        }

        .page-shell { margin-left: 200px; min-height: 100vh; display: flex; flex-direction: column; }

        .top-bar {
            position: sticky; top: 0; z-index: 200;
            height: 54px; background: var(--surface);
            border-bottom: 1px solid var(--border); box-shadow: var(--sh);
            display: flex; align-items: center; padding: 0 22px; gap: 12px; flex-shrink: 0;
        }
        .tb-ico {
            width: 32px; height: 32px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; background: var(--violet-bg); color: var(--violet); flex-shrink: 0;
        }
        .tb-title { font-size: 1.0625rem; font-weight: 700; color: var(--t1); }
        .tb-sub   { font-size: .78rem; color: var(--t4); }
        .tb-right { margin-left: auto; display: flex; gap: 8px; align-items: center; }

        .btn-pos {
            display: inline-flex; align-items: center; gap: 6px;
            height: 34px; padding: 0 14px;
            border: none; border-radius: var(--rs);
            font-family: inherit; font-size: .8125rem; font-weight: 600;
            cursor: pointer; transition: all .15s; text-decoration: none; white-space: nowrap;
        }
        .btn-blue       { background: var(--blue); color: #fff; }
        .btn-blue:hover { background: #1d4ed8; color: #fff; }
        .btn-ghost { background: var(--surface); color: var(--t2); border: 1.5px solid var(--border); }
        .btn-ghost:hover { background: var(--s2); border-color: #9ca3af; color: var(--t1); }

        .main { flex: 1; padding: 20px 22px 60px; display: flex; flex-direction: column; gap: 14px; }

        /* Search card */
        .search-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--r); box-shadow: var(--sh); overflow: hidden; }
        .search-head { display: flex; align-items: center; gap: 9px; padding: 11px 18px; background: var(--s2); border-bottom: 1px solid var(--bsoft); }
        .sh-ico { width: 26px; height: 26px; border-radius: var(--rs); display: flex; align-items: center; justify-content: center; font-size: 11px; background: var(--blue-bg); color: var(--blue); flex-shrink: 0; }
        .sh-title { font-size: .875rem; font-weight: 700; color: var(--t1); }
        .search-body { padding: 16px 18px; }
        .filter-grid { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 12px; align-items: end; }
        @media (max-width: 900px) { .filter-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 540px) { .filter-grid { grid-template-columns: 1fr; } }

        .lbl { display: block; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--t3); margin-bottom: 5px; }
        .fc { width: 100%; height: 36px; padding: 0 10px; border: 1.5px solid var(--border); border-radius: var(--rs); font-family: inherit; font-size: .875rem; color: var(--t2); background: var(--surface); outline: none; transition: border-color .15s, box-shadow .15s; appearance: none; }
        .fc:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
        select.fc { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='7' viewBox='0 0 10 7'%3E%3Cpath fill='%239ca3af' d='M0 0l5 7 5-7z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 9px center; padding-right: 28px; }
        .filter-actions { display: flex; gap: 7px; align-items: center; }

        .filter-strip { display: flex; align-items: center; gap: 8px; padding: 8px 18px; background: var(--blue-bg); border-bottom: 1px solid var(--blue-b); font-size: .8rem; flex-wrap: wrap; }
        .filter-tag { display: inline-flex; align-items: center; gap: 5px; background: var(--surface); border: 1px solid var(--blue-b); border-radius: 5px; padding: 2px 9px; font-size: .76rem; font-weight: 600; color: var(--blue); }

        /* Table card */
        .pos-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--r); box-shadow: var(--sh); overflow: hidden; }
        .info-bar { display: flex; align-items: center; justify-content: space-between; padding: 10px 18px; background: var(--s2); border-bottom: 1px solid var(--bsoft); font-size: .8rem; color: var(--t3); flex-wrap: wrap; gap: 6px; }
        .total-pill { display: inline-flex; align-items: center; gap: 5px; background: var(--violet-bg); border: 1px solid #ddd6fe; border-radius: 20px; padding: 3px 12px; font-size: .76rem; font-weight: 700; color: var(--violet); }

        .pos-tbl { width: 100%; border-collapse: collapse; }
        .pos-tbl thead th { padding: 9px 12px; font-size: .71rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: var(--t4); background: var(--s2); border-bottom: 1px solid var(--border); white-space: nowrap; text-align: left; }
        .pos-tbl thead th:first-child { padding-left: 18px; }
        .pos-tbl thead th.r { text-align: right; padding-right: 16px; }
        .pos-tbl thead th.c { text-align: center; }
        .pos-tbl tbody td { padding: 10px 12px; font-size: .8375rem; color: var(--t2); border-bottom: 1px solid var(--bsoft); vertical-align: top; }
        .pos-tbl tbody td:first-child { padding-left: 18px; }
        .pos-tbl tbody tr:last-child td { border-bottom: none; }
        .pos-tbl tbody tr:hover td { background: #f8faff; }

        .order-id { display: inline-flex; align-items: center; background: var(--s2); border: 1px solid var(--border); border-radius: 5px; padding: 2px 9px; font-size: .82rem; font-weight: 700; color: var(--t1); font-family: 'DM Mono', monospace; }
        .cust-name  { font-weight: 600; color: var(--t1); display: block; }
        .cust-phone { font-family: 'DM Mono', monospace; font-size: .79rem; color: var(--t3); }

        .s-badge { display: inline-flex; align-items: center; gap: 4px; border-radius: 5px; padding: 2px 9px; font-size: .74rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }
        .s-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
        .s-paid      { background: var(--green-bg); color: var(--green); border: 1px solid var(--green-b); }
        .s-pending   { background: var(--amber-bg); color: var(--amber); border: 1px solid var(--amber-b); }
        .s-cancelled { background: var(--red-bg);   color: var(--red);   border: 1px solid var(--red-b);   }

        .items-list { list-style: none; padding: 0; margin: 0; }
        .items-list li { font-size: .79rem; color: var(--t2); padding: 3px 0; border-bottom: 1px solid var(--bsoft); display: flex; align-items: baseline; gap: 5px; flex-wrap: wrap; }
        .items-list li:last-child { border-bottom: none; }
        .item-name  { font-weight: 600; color: var(--t1); }
        .item-svc   { display: inline-block; background: var(--blue-bg); color: var(--blue); border-radius: 3px; padding: 0 5px; font-size: .72rem; font-weight: 600; }
        .item-total { margin-left: auto; font-weight: 700; color: var(--t1); font-family: 'DM Mono', monospace; font-size: .79rem; }
        .item-badge { display: inline-flex; align-items: center; justify-content: center; background: var(--s2); border: 1px solid var(--border); border-radius: 5px; padding: 1px 8px; font-size: .78rem; font-weight: 600; color: var(--t3); font-family: 'DM Mono', monospace; }
        .amount-val { font-family: 'DM Mono', monospace; font-size: .9rem; font-weight: 700; color: var(--t1); }
        .date-val  { font-size: .8rem; color: var(--t2); }
        .time-val  { font-size: .76rem; color: var(--t4); }

        /* ── Action buttons ────────────────────── */
        .act-group { display: flex; gap: 5px; justify-content: center; }
        .act-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 30px; height: 30px; border-radius: var(--rs);
            border: 1.5px solid; font-size: .75rem; cursor: pointer;
            transition: all .15s; text-decoration: none; background: var(--surface);
        }
        .act-view  { border-color: var(--cyan-b);  color: var(--cyan);  }
        .act-view:hover  { background: var(--cyan-bg); }
        .act-print { border-color: #a5f3fc; color: var(--cyan); }
        .act-print:hover { background: var(--cyan-bg); }
        .act-edit  { border-color: var(--blue-b);  color: var(--blue);  }
        .act-edit:hover  { background: var(--blue-bg); }

        /* Empty state */
        .empty-state { text-align: center; padding: 56px 20px; color: var(--t4); }
        .empty-state i { font-size: 2rem; display: block; margin-bottom: 10px; opacity: .2; }
        .empty-state p { font-size: .875rem; }

        /* Pagination */
        .pager { display: flex; align-items: center; justify-content: space-between; padding: 12px 18px; background: var(--s2); border-top: 1px solid var(--border); flex-wrap: wrap; gap: 12px; }
        .pager-left  { display: flex; flex-direction: column; gap: 6px; }
        .pager-info  { font-size: .8rem; color: var(--t3); font-weight: 500; }
        .pager-right { display: flex; flex-direction: column; gap: 8px; align-items: flex-end; }
        .pager-nav   { display: flex; gap: 3px; align-items: center; }
        .pager-btn { display: inline-flex; align-items: center; justify-content: center; min-width: 30px; height: 30px; padding: 0 8px; border: 1.5px solid var(--border); border-radius: var(--rs); font-size: .8rem; font-weight: 600; color: var(--t2); background: var(--surface); text-decoration: none; transition: all .15s; }
        .pager-btn:hover   { border-color: var(--blue); color: var(--blue); background: var(--blue-bg); }
        .pager-btn.active  { background: var(--blue); border-color: var(--blue); color: #fff; pointer-events: none; }
        .pager-btn.disabled{ opacity: .35; pointer-events: none; }
        .jump-form { display: flex; align-items: center; gap: 7px; font-size: .8rem; color: var(--t3); }
        .jump-input { width: 68px; height: 30px; padding: 0 8px; border: 1.5px solid var(--border); border-radius: var(--rs); font-family: inherit; font-size: .8rem; color: var(--t2); outline: none; text-align: center; }
        .jump-input:focus { border-color: var(--blue); }
        .jump-btn { height: 30px; padding: 0 12px; background: var(--s2); color: var(--t2); border: 1.5px solid var(--border); border-radius: var(--rs); font-family: inherit; font-size: .8rem; font-weight: 600; cursor: pointer; transition: all .15s; }
        .jump-btn:hover { background: var(--blue-bg); border-color: var(--blue-b); color: var(--blue); }

        @media (max-width: 991.98px) {
            .page-shell { margin-left: 0; }
            .top-bar    { top: 52px; }
            .main       { padding: 14px 14px 50px; }
        }
    </style>
</head>
<body>

<div class="page-shell">

    <header class="top-bar">
        <div class="tb-ico"><i class="fas fa-pen-to-square"></i></div>
        <div>
            <div class="tb-title">Manage Orders</div>
            <div class="tb-sub">Admin — Edit and manage all orders</div>
        </div>
        <div class="tb-right">
            <a href="order.php" class="btn-pos btn-ghost">
                <i class="fas fa-plus" style="font-size:.6rem;"></i> New Order
            </a>
            <a href="dashboard.php" class="btn-pos btn-ghost">
                <i class="fas fa-gauge-high" style="font-size:.6rem;"></i> Dashboard
            </a>
        </div>
    </header>

    <div class="main">

        <!-- Search / Filter card -->
        <div class="search-card">
            <div class="search-head">
                <span class="sh-ico"><i class="fas fa-magnifying-glass"></i></span>
                <span class="sh-title">Filter Orders</span>
                <?php if ($hasFilter): ?>
                <a href="edit_bills.php" class="btn-pos btn-ghost" style="margin-left:auto;height:28px;font-size:.76rem;">
                    <i class="fas fa-rotate-left" style="font-size:.6rem;"></i> Reset
                </a>
                <?php endif; ?>
            </div>
            <div class="search-body">
                <form method="get">
                    <input type="hidden" name="page" value="1">
                    <div class="filter-grid">
                        <div>
                            <label class="lbl">Order ID</label>
                            <input type="number" name="order_id" class="fc"
                                   placeholder="e.g. 1042"
                                   value="<?= htmlspecialchars($searchOrderId) ?>">
                        </div>
                        <div>
                            <label class="lbl">Customer</label>
                            <input type="text" name="customer" class="fc"
                                   placeholder="Name or phone"
                                   value="<?= htmlspecialchars($searchCustomer) ?>">
                        </div>
                        <div>
                            <label class="lbl">Status</label>
                            <select name="status" class="fc">
                                <option value="">All Statuses</option>
                                <option value="paid"      <?= $searchStatus === 'paid'      ? 'selected' : '' ?>>Paid</option>
                                <option value="pending"   <?= $searchStatus === 'pending'   ? 'selected' : '' ?>>Pending</option>
                                <option value="cancelled" <?= $searchStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn-pos btn-blue" style="height:36px;">
                                <i class="fas fa-magnifying-glass" style="font-size:.6rem;"></i> Search
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <?php if ($hasFilter): ?>
            <div class="filter-strip">
                <span style="font-size:.76rem;font-weight:600;color:var(--blue);">Active filters:</span>
                <?php if ($searchOrderId !== ''): ?>
                    <span class="filter-tag"><i class="fas fa-hashtag" style="font-size:.6rem;"></i> ID: <?= htmlspecialchars($searchOrderId) ?></span>
                <?php endif; ?>
                <?php if ($searchCustomer !== ''): ?>
                    <span class="filter-tag"><i class="fas fa-user" style="font-size:.6rem;"></i> <?= htmlspecialchars($searchCustomer) ?></span>
                <?php endif; ?>
                <?php if ($searchStatus !== ''): ?>
                    <span class="filter-tag"><i class="fas fa-circle" style="font-size:.5rem;"></i> <?= ucfirst($searchStatus) ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Orders table card -->
        <div class="pos-card">

            <div class="info-bar">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span class="total-pill">
                        <i class="fas fa-receipt" style="font-size:.6rem;"></i>
                        <?= number_format($totalRecords) ?> Orders
                    </span>
                    <?php if ($totalRecords > 0): ?>
                    <span>
                        Showing <strong><?= number_format($startRecord) ?></strong>–<strong><?= number_format($endRecord) ?></strong>
                    </span>
                    <?php endif; ?>
                </div>
                <span>Page <strong><?= $page ?></strong> / <strong><?= max(1,$totalPages) ?></strong></span>
            </div>

            <?php if (empty($orders)): ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <p>No orders match your search criteria.</p>
            </div>

            <?php else: ?>

            <div style="overflow-x:auto;">
                <table class="pos-tbl">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Items & Services</th>
                            <th class="c">Count</th>
                            <th class="r">Total (৳)</th>
                            <th class="c">Status</th>
                            <th>Date</th>
                            <th class="c">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td style="white-space:nowrap;">
                            <span class="order-id">#<?= htmlspecialchars($order['order_id']) ?></span>
                        </td>
                        <td style="white-space:nowrap;">
                            <span class="cust-name"><?= htmlspecialchars($order['customer_name']) ?></span>
                            <span class="cust-phone"><?= htmlspecialchars($order['customer_phone']) ?></span>
                        </td>
                        <td style="min-width:260px;">
                            <ul class="items-list">
                                <?php if (!empty($order['items'])): ?>
                                    <?php foreach ($order['items'] as $item): ?>
                                    <li>
                                        <span class="item-name"><?= htmlspecialchars($item['item_name']) ?></span>
                                        <span class="item-svc"><?= htmlspecialchars($item['service_name']) ?></span>
                                        <span style="font-size:.76rem;color:var(--t3);">
                                            <?= number_format(floatval($item['quantity']), 0) ?> ×
                                            ৳<?= number_format(floatval($item['unit_price']), 2) ?>
                                        </span>
                                        <span class="item-total">৳<?= number_format(floatval($item['total_price']), 2) ?></span>
                                    </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li><span style="color:var(--t4);font-style:italic;font-size:.79rem;">No items</span></li>
                                <?php endif; ?>
                            </ul>
                        </td>
                        <td style="text-align:center;">
                            <span class="item-badge"><?= $order['item_count'] ?></span>
                        </td>
                        <td style="text-align:right;padding-right:16px;">
                            <span class="amount-val">৳<?= number_format($order['calculated_total'], 2) ?></span>
                        </td>
                        <td style="text-align:center;white-space:nowrap;">
                            <?php if ($order['status'] === 'paid'): ?>
                                <span class="s-badge s-paid"><span class="s-dot"></span>Paid</span>
                            <?php elseif ($order['status'] === 'pending'): ?>
                                <span class="s-badge s-pending"><span class="s-dot"></span>Pending</span>
                            <?php elseif ($order['status'] === 'cancelled'): ?>
                                <span class="s-badge s-cancelled"><span class="s-dot"></span>Cancelled</span>
                            <?php else: ?>
                                <span class="s-badge" style="background:var(--s2);color:var(--t3);border:1px solid var(--border);">
                                    <span class="s-dot"></span><?= ucfirst($order['status']) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;">
                            <span class="date-val"><?= date('d M Y', strtotime($order['created_at'])) ?></span><br>
                            <span class="time-val"><?= date('h:i A', strtotime($order['created_at'])) ?></span>
                        </td>
                        <td style="text-align:center;">
                            <div class="act-group">
                                <a href="bill.php?id=<?= $order['order_id'] ?>"
                                   class="act-btn act-view" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button type="button"
                                        class="act-btn act-print"
                                        title="Print"
                                        onclick="printOrder(<?= $order['order_id'] ?>)">
                                    <i class="fas fa-print"></i>
                                </button>
                                <a href="edit_bill_form.php?id=<?= $order['order_id'] ?>"
                                   class="act-btn act-edit" title="Edit">
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
            <div class="pager">
                <div class="pager-left">
                    <div class="pager-info">
                        Page <?= $page ?> of <?= $totalPages ?> &nbsp;·&nbsp; <?= number_format($totalRecords) ?> total orders
                    </div>
                    <form method="get" class="jump-form">
                        <?php foreach ($_GET as $k => $v): ?>
                            <?php if ($k !== 'page'): ?>
                            <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <span>Go to page</span>
                        <input type="number" name="page" class="jump-input"
                               min="1" max="<?= $totalPages ?>" value="<?= $page ?>">
                        <button type="submit" class="jump-btn">Go</button>
                    </form>
                </div>
                <div class="pager-right">
                    <div class="pager-nav">
                        <a class="pager-btn <?= $page <= 1 ? 'disabled' : '' ?>"
                           href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">
                            <i class="fas fa-angles-left" style="font-size:.6rem;"></i>
                        </a>
                        <a class="pager-btn <?= $page <= 1 ? 'disabled' : '' ?>"
                           href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                            <i class="fas fa-angle-left" style="font-size:.6rem;"></i>
                        </a>
                        <?php
                            $sp = max(1, $page - 3);
                            $ep = min($totalPages, $page + 3);
                            for ($i = $sp; $i <= $ep; $i++):
                        ?>
                        <a class="pager-btn <?= $i === $page ? 'active' : '' ?>"
                           href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <a class="pager-btn <?= $page >= $totalPages ? 'disabled' : '' ?>"
                           href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                            <i class="fas fa-angle-right" style="font-size:.6rem;"></i>
                        </a>
                        <a class="pager-btn <?= $page >= $totalPages ? 'disabled' : '' ?>"
                           href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>">
                            <i class="fas fa-angles-right" style="font-size:.6rem;"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php endif; ?>

        </div><!-- /pos-card -->
    </div><!-- /main -->
</div><!-- /page-shell -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
function printOrder(orderId) {
    const w = window.open(
        'print_order.php?id=' + orderId,
        'Print Order #' + orderId,
        'width=400,height=600,scrollbars=yes,resizable=yes'
    );
    if (w) w.focus();
    else   alert('Please allow popups to print receipts.');
}
</script>
</body>
</html>