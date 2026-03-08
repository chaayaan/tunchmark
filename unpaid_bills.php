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

include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Orders — Rajaiswari</title>
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
            --cyan:     #0891b2; --cyan-bg: #ecfeff;
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

        /* ── Shell ─────────────────────────────── */
        .page-shell {
            margin-left: 200px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Top bar ───────────────────────────── */
        .top-bar {
            position: sticky; top: 0; z-index: 200;
            height: 54px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            box-shadow: var(--sh);
            display: flex; align-items: center;
            padding: 0 22px; gap: 12px; flex-shrink: 0;
        }
        .tb-ico {
            width: 32px; height: 32px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; background: var(--amber-bg); color: var(--amber);
            flex-shrink: 0;
        }
        .tb-title { font-size: 1.0625rem; font-weight: 700; color: var(--t1); }
        .tb-sub   { font-size: .78rem; color: var(--t4); }
        .tb-right { margin-left: auto; display: flex; gap: 8px; align-items: center; }

        /* ── Main ──────────────────────────────── */
        .main {
            flex: 1;
            padding: 20px 22px 60px;
            display: flex; flex-direction: column; gap: 14px;
        }

        /* ── Alert ─────────────────────────────── */
        .pos-alert {
            display: flex; align-items: center; gap: 10px;
            padding: 12px 16px; border-radius: var(--rs);
            font-size: .875rem; font-weight: 500;
            position: relative;
        }
        .pos-alert.success {
            background: var(--green-bg); border: 1px solid var(--green-b);
            border-left: 3px solid var(--green); color: #065f46;
        }
        .pos-alert.warning {
            background: var(--amber-bg); border: 1px solid var(--amber-b);
            border-left: 3px solid var(--amber); color: #92400e;
        }
        .alert-close {
            margin-left: auto; background: none; border: none;
            font-size: 1rem; cursor: pointer; opacity: .5;
            color: inherit; line-height: 1; padding: 0;
        }
        .alert-close:hover { opacity: 1; }

        /* ── Stat cards ────────────────────────── */
        .stat-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 14px 16px;
            box-shadow: var(--sh);
            position: relative; overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 3px;
            border-radius: var(--r) var(--r) 0 0;
        }
        .stat-card.amber::before { background: var(--amber); }
        .stat-card.blue::before  { background: var(--blue); }
        .stat-card.cyan::before  { background: var(--cyan); }

        .stat-ico {
            width: 34px; height: 34px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; margin-bottom: 10px;
        }
        .stat-card.amber .stat-ico { background: var(--amber-bg); color: var(--amber); }
        .stat-card.blue  .stat-ico { background: var(--blue-bg);  color: var(--blue);  }
        .stat-card.cyan  .stat-ico { background: var(--cyan-bg);  color: var(--cyan);  }

        .stat-lbl { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--t4); margin-bottom: 3px; }
        .stat-val { font-size: 1.4rem; font-weight: 800; color: var(--t1); letter-spacing: -.02em; line-height: 1; }
        .stat-card.amber .stat-val { color: var(--amber); }
        .stat-card.blue  .stat-val { color: var(--blue);  }

        /* ── Main card ─────────────────────────── */
        .pos-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            box-shadow: var(--sh);
            overflow: hidden;
        }

        /* ── Bulk bar ──────────────────────────── */
        .bulk-bar {
            display: none;
            align-items: center; justify-content: space-between;
            padding: 10px 18px;
            background: var(--blue-bg);
            border-bottom: 1px solid var(--blue-b);
            gap: 10px; flex-wrap: wrap;
        }
        .bulk-bar.active { display: flex; }
        .bulk-bar-left {
            display: flex; align-items: center; gap: 8px;
            font-size: .875rem; font-weight: 600; color: var(--blue);
        }
        .bulk-bar-right { display: flex; gap: 7px; }

        /* ── Buttons ───────────────────────────── */
        .btn-pos {
            display: inline-flex; align-items: center; gap: 6px;
            height: 32px; padding: 0 14px;
            border: none; border-radius: var(--rs);
            font-family: inherit; font-size: .8125rem; font-weight: 600;
            cursor: pointer; transition: all .15s; text-decoration: none;
            white-space: nowrap;
        }
        .btn-green  { background: var(--green); color: #fff; }
        .btn-green:hover  { background: #047857; color: #fff; }
        .btn-ghost {
            background: var(--surface); color: var(--t2);
            border: 1.5px solid var(--border);
        }
        .btn-ghost:hover { background: var(--s2); border-color: #9ca3af; color: var(--t1); }
        .btn-ghost-blue {
            background: var(--blue-bg); color: var(--blue);
            border: 1.5px solid var(--blue-b);
        }
        .btn-ghost-blue:hover { background: #dbeafe; }

        /* ── Table toolbar ─────────────────────── */
        .tbl-toolbar {
            display: flex; align-items: center;
            justify-content: space-between;
            padding: 12px 18px;
            border-bottom: 1px solid var(--bsoft);
            flex-wrap: wrap; gap: 8px;
        }
        .tbl-toolbar-left { display: flex; align-items: center; gap: 10px; }

        .pending-pill {
            display: inline-flex; align-items: center; gap: 5px;
            background: var(--amber-bg); border: 1px solid var(--amber-b);
            border-radius: 20px; padding: 3px 12px;
            font-size: .76rem; font-weight: 700; color: var(--amber);
        }
        .pending-dot {
            width: 7px; height: 7px; border-radius: 50%;
            background: var(--amber);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%,100% { opacity:1; transform:scale(1); }
            50%      { opacity:.5; transform:scale(1.3); }
        }

        /* ── Table ─────────────────────────────── */
        .pos-tbl { width: 100%; border-collapse: collapse; }
        .pos-tbl thead th {
            padding: 9px 14px;
            font-size: .72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .07em;
            color: var(--t4); background: var(--s2);
            border-bottom: 1px solid var(--border);
            white-space: nowrap; text-align: left;
        }
        .pos-tbl thead th:first-child { padding-left: 18px; }
        .pos-tbl thead th.text-right  { text-align: right; }
        .pos-tbl thead th.text-center { text-align: center; }

        .pos-tbl tbody td {
            padding: 11px 14px;
            font-size: .875rem; color: var(--t2);
            border-bottom: 1px solid var(--bsoft);
            vertical-align: middle;
        }
        .pos-tbl tbody td:first-child { padding-left: 18px; }
        .pos-tbl tbody tr:last-child td { border-bottom: none; }
        .pos-tbl tbody tr:hover td { background: #fafbff; }
        .pos-tbl tbody tr.row-selected td { background: var(--blue-bg); }

        /* ── Checkbox ──────────────────────────── */
        .cb-wrap {
            width: 18px; height: 18px;
            accent-color: var(--blue);
            cursor: pointer;
        }

        /* ── Order ID ──────────────────────────── */
        .order-id {
            display: inline-flex; align-items: center;
            background: var(--s2); border: 1px solid var(--border);
            border-radius: 5px; padding: 2px 9px;
            font-size: .82rem; font-weight: 700; color: var(--t1);
            font-family: 'DM Mono', monospace;
        }

        .cust-name  { font-weight: 600; color: var(--t1); }
        .cust-phone { font-family: 'DM Mono', monospace; font-size: .8rem; color: var(--t3); }

        .item-badge {
            display: inline-flex; align-items: center; justify-content: center;
            background: var(--s2); border: 1px solid var(--border);
            border-radius: 5px; padding: 1px 8px;
            font-size: .78rem; font-weight: 600; color: var(--t3);
            font-family: 'DM Mono', monospace;
        }

        .amount-val {
            font-family: 'DM Mono', monospace;
            font-size: .9rem; font-weight: 700; color: var(--t1);
        }

        .date-val { font-size: .8rem; color: var(--t3); }

        /* ── Action buttons ────────────────────── */
        .act-group { display: flex; gap: 5px; justify-content: flex-end; }

        .act-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 30px; height: 30px;
            border-radius: var(--rs);
            border: 1.5px solid;
            font-size: .75rem; cursor: pointer;
            transition: all .15s; text-decoration: none;
            background: var(--surface);
        }
        .act-btn.view  { border-color: var(--blue-b);  color: var(--blue);  }
        .act-btn.view:hover  { background: var(--blue-bg);  }
        .act-btn.print { border-color: #a5f3fc; color: var(--cyan); }
        .act-btn.print:hover { background: var(--cyan-bg); }
        .act-btn.paid  { border-color: var(--green-b); color: var(--green); }
        .act-btn.paid:hover  { background: var(--green-bg); }

        /* ── Empty state ───────────────────────── */
        .empty-state {
            text-align: center; padding: 64px 20px; color: var(--t4);
        }
        .empty-ico {
            width: 64px; height: 64px; border-radius: 50%;
            background: var(--green-bg); border: 2px solid var(--green-b);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px; font-size: 1.6rem; color: var(--green);
        }
        .empty-state h4 { font-size: 1.0625rem; font-weight: 700; color: var(--green); margin-bottom: 6px; }
        .empty-state p  { font-size: .875rem; margin-bottom: 20px; }

        /* ── Card footer ───────────────────────── */
        .card-foot {
            display: flex; align-items: center;
            padding: 12px 18px;
            background: var(--s2);
            border-top: 1px solid var(--border);
        }

        /* ── Responsive ────────────────────────── */
        @media (max-width: 991.98px) {
            .page-shell { margin-left: 0; }
            .top-bar    { top: 52px; }
            .main       { padding: 14px 14px 50px; }
            .stat-row   { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 540px) {
            .stat-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="page-shell">

    <!-- Top Bar -->
    <header class="top-bar">
        <div class="tb-ico"><i class="fas fa-clock"></i></div>
        <div>
            <div class="tb-title">Pending Orders</div>
            <div class="tb-sub">Orders awaiting payment confirmation</div>
        </div>
        <div class="tb-right">
            <a href="order.php" class="btn-pos btn-ghost" style="height:34px;">
                <i class="fas fa-plus" style="font-size:.65rem;"></i> New Order
            </a>
            <a href="dashboard.php" class="btn-pos btn-ghost" style="height:34px;">
                <i class="fas fa-gauge-high" style="font-size:.65rem;"></i> Dashboard
            </a>
        </div>
    </header>

    <div class="main">

        <!-- Flash alerts -->
        <?php if (isset($_GET['success'])): ?>
        <div class="pos-alert success" id="flashAlert">
            <i class="fas fa-circle-check" style="font-size:.9rem;flex-shrink:0;"></i>
            <span><strong><?= intval($_GET['success']) ?> order(s)</strong> successfully marked as paid.</span>
            <button class="alert-close" onclick="this.closest('.pos-alert').remove()">&times;</button>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'noselection'): ?>
        <div class="pos-alert warning" id="flashAlert">
            <i class="fas fa-triangle-exclamation" style="font-size:.9rem;flex-shrink:0;"></i>
            <span>Please select at least one order to continue.</span>
            <button class="alert-close" onclick="this.closest('.pos-alert').remove()">&times;</button>
        </div>
        <?php endif; ?>

        <?php if (empty($unpaidOrders)): ?>

        <!-- Empty state -->
        <div class="pos-card">
            <div class="empty-state">
                <div class="empty-ico"><i class="fas fa-circle-check"></i></div>
                <h4>All Clear!</h4>
                <p>No pending orders at the moment. Everything is settled.</p>
                <a href="dashboard.php" class="btn-pos btn-green" style="height:36px;display:inline-flex;margin:0 auto;">
                    <i class="fas fa-gauge-high" style="font-size:.65rem;"></i> Go to Dashboard
                </a>
            </div>
        </div>

        <?php else: ?>

        <!-- Stat cards -->
        <div class="stat-row">
            <div class="stat-card amber">
                <div class="stat-ico"><i class="fas fa-clock"></i></div>
                <div class="stat-lbl">Pending Orders</div>
                <div class="stat-val"><?= count($unpaidOrders) ?></div>
            </div>
            <div class="stat-card blue">
                <div class="stat-ico"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-lbl">Total Pending Amount</div>
                <div class="stat-val">৳<?= number_format(array_sum(array_column($unpaidOrders, 'calculated_total')), 0) ?></div>
            </div>
            <div class="stat-card cyan">
                <div class="stat-ico"><i class="fas fa-calculator"></i></div>
                <div class="stat-lbl">Average Order Value</div>
                <div class="stat-val" style="color:var(--cyan);">
                    ৳<?= number_format(array_sum(array_column($unpaidOrders, 'calculated_total')) / count($unpaidOrders), 0) ?>
                </div>
            </div>
        </div>

        <!-- Orders table card -->
        <div class="pos-card">

            <form method="POST" id="bulkPaymentForm">

                <!-- Bulk actions bar -->
                <div class="bulk-bar" id="bulkActionsBar">
                    <div class="bulk-bar-left">
                        <i class="fas fa-square-check" style="font-size:.85rem;"></i>
                        <span id="selectedCount">0</span> order(s) selected
                    </div>
                    <div class="bulk-bar-right">
                        <button type="button" class="btn-pos btn-ghost" onclick="clearSelection()">
                            <i class="fas fa-times" style="font-size:.6rem;"></i> Clear
                        </button>
                        <button type="submit" name="bulk_mark_paid"
                                class="btn-pos btn-green"
                                onclick="return confirmBulkPayment()">
                            <i class="fas fa-check-circle" style="font-size:.65rem;"></i> Mark as Paid
                        </button>
                    </div>
                </div>

                <!-- Toolbar -->
                <div class="tbl-toolbar">
                    <div class="tbl-toolbar-left">
                        <span class="pending-pill">
                            <span class="pending-dot"></span>
                            <?= count($unpaidOrders) ?> Pending
                        </span>
                        <span style="font-size:.8rem;color:var(--t4);">Select orders to bulk mark as paid</span>
                    </div>
                </div>

                <!-- Table -->
                <div style="overflow-x:auto;">
                    <table class="pos-tbl">
                        <thead>
                            <tr>
                                <th style="width:40px;">
                                    <input type="checkbox" class="cb-wrap" id="selectAll"
                                           onchange="toggleSelectAll(this)">
                                </th>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th class="text-center">Items</th>
                                <th class="text-right" style="text-align:right;padding-right:18px;">Amount (৳)</th>
                                <th>Date</th>
                                <th style="text-align:right;padding-right:18px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($unpaidOrders as $order): ?>
                        <tr id="row-<?= $order['order_id'] ?>">
                            <td>
                                <input type="checkbox"
                                       class="cb-wrap order-select"
                                       name="order_ids[]"
                                       value="<?= $order['order_id'] ?>"
                                       onchange="updateBulkActions()">
                            </td>
                            <td>
                                <span class="order-id">#<?= htmlspecialchars($order['order_id']) ?></span>
                            </td>
                            <td><span class="cust-name"><?= htmlspecialchars($order['customer_name']) ?></span></td>
                            <td><span class="cust-phone"><?= htmlspecialchars($order['customer_phone']) ?></span></td>
                            <td style="text-align:center;">
                                <span class="item-badge"><?= $order['item_count'] ?></span>
                            </td>
                            <td style="text-align:right;padding-right:18px;">
                                <span class="amount-val"><?= number_format($order['calculated_total'], 2) ?></span>
                            </td>
                            <td>
                                <span class="date-val"><?= date('d M Y', strtotime($order['created_at'])) ?></span>
                            </td>
                            <td style="padding-right:18px;">
                                <div class="act-group">
                                    <a href="bill.php?id=<?= $order['order_id'] ?>"
                                       class="act-btn view" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button onclick="printOrder(<?= $order['order_id'] ?>)"
                                            type="button"
                                            class="act-btn print" title="Print Receipt">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <a href="unpaid_bills.php?mark_paid=<?= $order['order_id'] ?>"
                                       class="act-btn paid"
                                       title="Mark as Paid"
                                       onclick="return confirm('Mark order #<?= $order['order_id'] ?> as paid?');">
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

            <!-- Footer -->
            <div class="card-foot">
                <a href="reports.php" class="btn-pos btn-ghost" style="height:34px;">
                    <i class="fas fa-chart-bar" style="font-size:.65rem;"></i> Sales Reports
                </a>
            </div>

        </div><!-- /pos-card -->
        <?php endif; ?>

    </div><!-- /main -->
</div><!-- /page-shell -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleSelectAll(checkbox) {
        document.querySelectorAll('.order-select').forEach(cb => {
            cb.checked = checkbox.checked;
            cb.closest('tr').classList.toggle('row-selected', checkbox.checked);
        });
        updateBulkActions();
    }

    function updateBulkActions() {
        const selected = document.querySelectorAll('.order-select:checked');
        const total    = document.querySelectorAll('.order-select').length;
        const count    = selected.length;
        const bar      = document.getElementById('bulkActionsBar');
        const selectAll = document.getElementById('selectAll');

        document.getElementById('selectedCount').textContent = count;
        bar.classList.toggle('active', count > 0);

        // Row highlight
        document.querySelectorAll('.order-select').forEach(cb => {
            cb.closest('tr').classList.toggle('row-selected', cb.checked);
        });

        if (count === 0) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        } else if (count === total) {
            selectAll.checked = true;
            selectAll.indeterminate = false;
        } else {
            selectAll.checked = false;
            selectAll.indeterminate = true;
        }
    }

    function clearSelection() {
        document.querySelectorAll('.order-select').forEach(cb => {
            cb.checked = false;
            cb.closest('tr').classList.remove('row-selected');
        });
        document.getElementById('selectAll').checked = false;
        updateBulkActions();
    }

    function confirmBulkPayment() {
        const count = document.querySelectorAll('.order-select:checked').length;
        if (count === 0) { alert('Please select at least one order.'); return false; }
        return confirm(`Mark ${count} order(s) as paid?`);
    }

    function printOrder(orderId) {
        const w = window.open(
            'print_order.php?id=' + orderId,
            'Print Order #' + orderId,
            'width=400,height=600,scrollbars=yes,resizable=yes'
        );
        if (w) { w.focus(); }
        else   { alert('Please allow popups to print receipts'); }
    }

    // Auto-dismiss flash alert after 5s
    setTimeout(() => {
        const a = document.getElementById('flashAlert');
        if (a) a.style.transition = 'opacity .4s', a.style.opacity = '0',
               setTimeout(() => a.remove(), 400);
    }, 5000);
</script>
</body>
</html>