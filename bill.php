<?php
require 'auth.php';
require 'mydb.php';

$orderId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($orderId <= 0) die("Invalid Order ID.");

// Fetch order
$stmt = mysqli_prepare($conn, "SELECT * FROM orders WHERE order_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $orderId);
mysqli_stmt_execute($stmt);
$res   = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$order) die("Order not found.");

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
    $items[]      = $itemRow;
    $totalAmount += floatval($itemRow['total_price']);
}
mysqli_stmt_close($itemsStmt);

// Get previous order id
$prevRes   = mysqli_query($conn, "SELECT order_id FROM orders WHERE order_id < $orderId ORDER BY order_id DESC LIMIT 1");
$prevOrder = mysqli_fetch_assoc($prevRes);
$prevId    = $prevOrder['order_id'] ?? null;

// Get next order id
$nextRes   = mysqli_query($conn, "SELECT order_id FROM orders WHERE order_id > $orderId ORDER BY order_id ASC LIMIT 1");
$nextOrder = mysqli_fetch_assoc($nextRes);
$nextId    = $nextOrder['order_id'] ?? null;

// Convert gram to vori-ana-roti-point
function convertGramToVoriAna($gram) {
    if (!$gram || $gram <= 0) return '0V 0A 0R 0P';
    $totalPoints      = round(($gram / 11.664) * 16 * 6 * 10);
    $bhori            = floor($totalPoints / 960);
    $remainingPoints  = $totalPoints % 960;
    $ana              = floor($remainingPoints / 60);
    $remainingAfterAna = $remainingPoints % 60;
    $roti             = floor($remainingAfterAna / 10);
    $point            = $remainingAfterAna % 10;
    return "{$bhori}V {$ana}A {$roti}R {$point}P";
}

include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order #<?= htmlspecialchars($order['order_id']) ?> — Rajaiswari</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:      #f1f3f6;
            --surface: #ffffff;
            --s2:      #fafbfc;
            --border:  #e4e7ec;
            --bsoft:   #f0f1f3;
            --t1:      #111827;
            --t2:      #374151;
            --t3:      #6b7280;
            --t4:      #9ca3af;
            --blue:    #2563eb; --blue-bg: #eff6ff; --blue-b: #bfdbfe;
            --green:   #059669; --green-bg:#ecfdf5; --green-b:#a7f3d0;
            --amber:   #d97706; --amber-bg:#fffbeb; --amber-b:#fde68a;
            --red:     #dc2626; --red-bg:  #fef2f2; --red-b:  #fecaca;
            --violet:  #7c3aed; --violet-bg:#f5f3ff; --violet-b:#ddd6fe;
            --cyan:    #0891b2; --cyan-bg: #ecfeff; --cyan-b: #a5f3fc;
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
            font-size: 13px; background: var(--blue-bg); color: var(--blue);
            flex-shrink: 0;
        }
        .tb-title { font-size: 1.0625rem; font-weight: 700; color: var(--t1); }
        .tb-sub   { font-size: .78rem; color: var(--t4); }
        .tb-right { margin-left: auto; display: flex; gap: 7px; align-items: center; }

        /* ── Buttons ───────────────────────────── */
        .btn-pos {
            display: inline-flex; align-items: center; gap: 6px;
            height: 32px; padding: 0 13px;
            border: none; border-radius: var(--rs);
            font-family: inherit; font-size: .8125rem; font-weight: 600;
            cursor: pointer; transition: all .15s; text-decoration: none;
            white-space: nowrap;
        }
        .btn-ghost {
            background: var(--surface); color: var(--t2);
            border: 1.5px solid var(--border);
        }
        .btn-ghost:hover { background: var(--s2); border-color: #9ca3af; color: var(--t1); }
        .btn-blue { background: var(--blue); color: #fff; border: none; }
        .btn-blue:hover { background: #1d4ed8; color: #fff; }

        /* ── Main ──────────────────────────────── */
        .main {
            flex: 1;
            padding: 20px 22px 60px;
        }

        /* ── Layout grid ───────────────────────── */
        .layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 16px;
            align-items: start;
        }
        @media (max-width: 1024px) { .layout { grid-template-columns: 1fr; } }

        /* ── Panel ─────────────────────────────── */
        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            box-shadow: var(--sh);
            overflow: hidden;
        }

        .panel-hd {
            display: flex; align-items: center; gap: 8px;
            padding: 11px 16px;
            background: var(--s2);
            border-bottom: 1px solid var(--bsoft);
        }
        .ph-ico {
            width: 26px; height: 26px; border-radius: var(--rs);
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; flex-shrink: 0;
        }
        .ph-ico.bl { background: var(--blue-bg);   color: var(--blue);   }
        .ph-ico.gr { background: var(--green-bg);  color: var(--green);  }
        .ph-ico.vi { background: var(--violet-bg); color: var(--violet); }
        .ph-ico.am { background: var(--amber-bg);  color: var(--amber);  }
        .ph-ico.cy { background: var(--cyan-bg);   color: var(--cyan);   }

        .ph-title {
            font-size: .875rem; font-weight: 700; color: var(--t1);
        }
        .ph-meta {
            margin-left: auto; font-size: .75rem; color: var(--t4);
        }

        /* ── Info rows ─────────────────────────── */
        .info-body { padding: 4px 0; }

        .info-row {
            display: flex; align-items: flex-start;
            justify-content: space-between;
            padding: 9px 16px;
            border-bottom: 1px solid var(--bsoft);
            gap: 10px;
        }
        .info-row:last-child { border-bottom: none; }

        .ir-lbl {
            font-size: .76rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .05em;
            color: var(--t4); flex-shrink: 0;
        }
        .ir-val {
            font-size: .875rem; font-weight: 600; color: var(--t1);
            text-align: right;
        }
        .ir-val.mono { font-family: 'DM Mono', monospace; }

        /* ── Status badge ──────────────────────── */
        .s-badge {
            display: inline-flex; align-items: center; gap: 4px;
            border-radius: 5px; padding: 3px 10px;
            font-size: .76rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .04em;
        }
        .s-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .s-paid     { background: var(--green-bg); color: var(--green); border: 1px solid var(--green-b); }
        .s-pending  { background: var(--amber-bg); color: var(--amber); border: 1px solid var(--amber-b); }
        .s-cancelled{ background: var(--red-bg);   color: var(--red);   border: 1px solid var(--red-b);   }

        /* ── Total box ─────────────────────────── */
        .total-box {
            padding: 16px;
            text-align: center;
        }
        .total-lbl {
            font-size: .72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .08em;
            color: var(--t4); margin-bottom: 6px;
        }
        .total-val {
            font-size: 2rem; font-weight: 800;
            color: var(--t1); letter-spacing: -.04em;
            font-family: 'DM Mono', monospace;
            line-height: 1;
        }
        .total-val span { font-size: 1.1rem; font-weight: 600; color: var(--t3); }

        /* ── Items table ───────────────────────── */
        .pos-tbl { width: 100%; border-collapse: collapse; }
        .pos-tbl thead th {
            padding: 9px 12px;
            font-size: .71rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .07em;
            color: var(--t4); background: var(--s2);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        .pos-tbl thead th:first-child { padding-left: 18px; }
        .pos-tbl thead th.r { text-align: right; padding-right: 16px; }
        .pos-tbl thead th.c { text-align: center; }

        .pos-tbl tbody td {
            padding: 11px 12px;
            font-size: .8375rem; color: var(--t2);
            border-bottom: 1px solid var(--bsoft);
            vertical-align: middle;
        }
        .pos-tbl tbody td:first-child { padding-left: 18px; }
        .pos-tbl tbody tr:last-child td { border-bottom: none; }
        .pos-tbl tbody tr:hover td { background: #f8faff; }

        .pos-tbl tfoot td {
            padding: 11px 12px;
            background: var(--s2);
            border-top: 2px solid var(--border);
            font-size: .875rem; font-weight: 700;
        }
        .pos-tbl tfoot td:first-child { padding-left: 18px; }

        /* ── Row number ────────────────────────── */
        .row-n {
            display: inline-flex; align-items: center; justify-content: center;
            width: 22px; height: 22px; border-radius: 50%;
            background: var(--s2); border: 1px solid var(--border);
            font-size: .76rem; font-weight: 700; color: var(--t3);
            font-family: 'DM Mono', monospace;
        }

        .item-name { font-weight: 600; color: var(--t1); }
        .svc-tag {
            display: inline-block;
            background: var(--blue-bg); color: var(--blue);
            border-radius: 4px; padding: 1px 7px;
            font-size: .74rem; font-weight: 600;
        }
        .karat-tag {
            display: inline-block;
            background: var(--amber-bg); color: var(--amber);
            border-radius: 4px; padding: 1px 7px;
            font-size: .74rem; font-weight: 600;
        }
        .weight-main { font-size: .875rem; font-weight: 600; font-family: 'DM Mono', monospace; }
        .weight-sub  { font-size: .72rem; color: var(--t4); font-family: 'DM Mono', monospace; margin-top: 1px; }
        .qty-val  { font-family: 'DM Mono', monospace; font-weight: 700; text-align: center; }
        .price-val{ font-family: 'DM Mono', monospace; font-size: .875rem; }
        .total-cell { font-family: 'DM Mono', monospace; font-size: .9rem; font-weight: 700; color: var(--t1); text-align: right; padding-right: 16px !important; }

        /* ── Nav buttons ───────────────────────── */
        .nav-bar {
            display: flex; align-items: center;
            justify-content: space-between;
            padding: 13px 16px;
            background: var(--s2);
            border-top: 1px solid var(--border);
            flex-wrap: wrap; gap: 8px;
        }
        .nav-bar-left  { display: flex; gap: 7px; }
        .nav-bar-right { display: flex; gap: 7px; }

        /* ── Empty state ───────────────────────── */
        .empty-items {
            text-align: center; padding: 40px 20px; color: var(--t4);
        }
        .empty-items i { font-size: 1.8rem; opacity: .2; display: block; margin-bottom: 8px; }
        .empty-items p { font-size: .875rem; }

        /* ── Responsive ────────────────────────── */
        @media (max-width: 991.98px) {
            .page-shell { margin-left: 0; }
            .top-bar    { top: 52px; }
            .main       { padding: 14px 14px 50px; }
        }
    </style>
</head>
<body>

<div class="page-shell">

    <!-- Top Bar -->
    <header class="top-bar">
        <div class="tb-ico"><i class="fas fa-file-invoice"></i></div>
        <div>
            <div class="tb-title">Order Details</div>
            <div class="tb-sub">
                #<?= htmlspecialchars($order['order_id']) ?>
                &nbsp;·&nbsp;
                <?= date("d M Y, h:i A", strtotime($order['created_at'])) ?>
            </div>
        </div>
        <div class="tb-right">
            <?php if ($prevId): ?>
            <a href="bill.php?id=<?= $prevId ?>" class="btn-pos btn-ghost">
                <i class="fas fa-angle-left" style="font-size:.65rem;"></i> Prev
            </a>
            <?php endif; ?>
            <?php if ($nextId): ?>
            <a href="bill.php?id=<?= $nextId ?>" class="btn-pos btn-ghost">
                Next <i class="fas fa-angle-right" style="font-size:.65rem;"></i>
            </a>
            <?php endif; ?>
            <a href="reports.php" class="btn-pos btn-ghost">
                <i class="fas fa-arrow-left" style="font-size:.65rem;"></i> Reports
            </a>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="edit_bill_form.php?id=<?= $orderId ?>" class="btn-pos btn-blue">
                <i class="fas fa-pen" style="font-size:.6rem;"></i> Edit
            </a>
            <?php endif; ?>
        </div>
    </header>

    <div class="main">
        <div class="layout">

            <!-- ── Left sidebar ───────────────────── -->
            <div style="display:flex;flex-direction:column;gap:14px;">

                <!-- Customer info -->
                <div class="panel">
                    <div class="panel-hd">
                        <span class="ph-ico bl"><i class="fas fa-user"></i></span>
                        <span class="ph-title">Customer</span>
                    </div>
                    <div class="info-body">
                        <div class="info-row">
                            <span class="ir-lbl">Name</span>
                            <span class="ir-val"><?= htmlspecialchars($order['customer_name']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="ir-lbl">Phone</span>
                            <span class="ir-val mono"><?= htmlspecialchars($order['customer_phone']) ?></span>
                        </div>
                        <?php if (!empty($order['customer_address'])): ?>
                        <div class="info-row">
                            <span class="ir-lbl">Address</span>
                            <span class="ir-val"><?= htmlspecialchars($order['customer_address']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($order['customer_id'])): ?>
                        <div class="info-row">
                            <span class="ir-lbl">Customer ID</span>
                            <span class="ir-val mono">#<?= htmlspecialchars($order['customer_id']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Additional info -->
                <?php if (!empty($order['manufacturer']) || !empty($order['box_no'])): ?>
                <div class="panel">
                    <div class="panel-hd">
                        <span class="ph-ico vi"><i class="fas fa-info-circle"></i></span>
                        <span class="ph-title">Additional Info</span>
                    </div>
                    <div class="info-body">
                        <?php if (!empty($order['manufacturer'])): ?>
                        <div class="info-row">
                            <span class="ir-lbl">Manufacturer</span>
                            <span class="ir-val"><?= htmlspecialchars($order['manufacturer']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($order['box_no'])): ?>
                        <div class="info-row">
                            <span class="ir-lbl">Box No</span>
                            <span class="ir-val mono"><?= htmlspecialchars($order['box_no']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Payment status -->
                <div class="panel">
                    <div class="panel-hd">
                        <span class="ph-ico gr"><i class="fas fa-credit-card"></i></span>
                        <span class="ph-title">Payment</span>
                    </div>
                    <div style="padding:14px 16px;display:flex;flex-direction:column;gap:10px;">
                        <div>
                            <?php if ($order['status'] === 'paid'): ?>
                                <span class="s-badge s-paid"><span class="s-dot"></span>Paid</span>
                            <?php elseif ($order['status'] === 'cancelled'): ?>
                                <span class="s-badge s-cancelled"><span class="s-dot"></span>Cancelled</span>
                            <?php else: ?>
                                <span class="s-badge s-pending"><span class="s-dot"></span>Pending</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($order['created_by'])): ?>
                        <div style="font-size:.78rem;color:var(--t3);">
                            <i class="fas fa-user-pen" style="font-size:.65rem;margin-right:4px;"></i>
                            Created by <strong><?= htmlspecialchars($order['created_by']) ?></strong>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Total amount -->
                <div class="panel">
                    <div class="panel-hd">
                        <span class="ph-ico cy"><i class="fas fa-calculator"></i></span>
                        <span class="ph-title">Total Amount</span>
                    </div>
                    <div class="total-box">
                        <div class="total-lbl">Grand Total</div>
                        <div class="total-val">
                            <span>৳</span><?= number_format($totalAmount, 2) ?>
                        </div>
                        <div style="font-size:.76rem;color:var(--t4);margin-top:6px;">
                            <?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?>
                        </div>
                    </div>
                </div>

            </div>
            <!-- /left sidebar -->

            <!-- ── Right: items table ──────────────── -->
            <div class="panel">
                <div class="panel-hd">
                    <span class="ph-ico am"><i class="fas fa-list-check"></i></span>
                    <span class="ph-title">Order Items</span>
                    <span class="ph-meta"><?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?></span>
                </div>

                <?php if (!empty($items)): ?>
                <div style="overflow-x:auto;">
                    <table class="pos-tbl">
                        <thead>
                            <tr>
                                <th style="width:36px;">#</th>
                                <th>Item</th>
                                <th>Service</th>
                                <th class="c">Karat</th>
                                <th class="r">Weight</th>
                                <th class="c">Qty</th>
                                <th class="r">Unit Price</th>
                                <th class="r">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $index => $item): ?>
                            <tr>
                                <td><span class="row-n"><?= $index + 1 ?></span></td>
                                <td>
                                    <span class="item-name"><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></span>
                                </td>
                                <td>
                                    <span class="svc-tag"><?= htmlspecialchars($item['service_name'] ?? 'N/A') ?></span>
                                </td>
                                <td style="text-align:center;">
                                    <?php if (!empty($item['karat']) && $item['karat'] !== '-'): ?>
                                        <span class="karat-tag"><?= htmlspecialchars($item['karat']) ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--t4);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;padding-right:16px;">
                                    <?php $w = floatval($item['weight'] ?? 0); ?>
                                    <?php if ($w > 0): ?>
                                        <div class="weight-main"><?= number_format($w, 2) ?> gm</div>
                                        <div class="weight-sub"><?= convertGramToVoriAna($w) ?></div>
                                    <?php else: ?>
                                        <span style="color:var(--t4);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="qty-val"><?= number_format(floatval($item['quantity']), 0) ?></span>
                                </td>
                                <td style="text-align:right;padding-right:16px;">
                                    <span class="price-val">৳<?= number_format(floatval($item['unit_price']), 2) ?></span>
                                </td>
                                <td class="total-cell">
                                    ৳<?= number_format(floatval($item['total_price']), 2) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="7" style="text-align:right;padding-right:16px;color:var(--t3);font-size:.8rem;letter-spacing:.04em;text-transform:uppercase;">
                                    Grand Total
                                </td>
                                <td style="text-align:right;padding-right:16px;font-size:1.05rem;font-weight:800;color:var(--t1);font-family:'DM Mono',monospace;">
                                    ৳<?= number_format($totalAmount, 2) ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <?php else: ?>
                <div class="empty-items">
                    <i class="fas fa-inbox"></i>
                    <p>No items found in this order.</p>
                </div>
                <?php endif; ?>

                <!-- Nav bar -->
                <div class="nav-bar">
                    <div class="nav-bar-left">
                        <?php if ($prevId): ?>
                        <a href="bill.php?id=<?= $prevId ?>" class="btn-pos btn-ghost" style="height:34px;">
                            <i class="fas fa-angle-left" style="font-size:.65rem;"></i> Previous
                        </a>
                        <?php endif; ?>
                        <?php if ($nextId): ?>
                        <a href="bill.php?id=<?= $nextId ?>" class="btn-pos btn-ghost" style="height:34px;">
                            Next <i class="fas fa-angle-right" style="font-size:.65rem;"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="nav-bar-right">
                        <a href="reports.php" class="btn-pos btn-ghost" style="height:34px;">
                            <i class="fas fa-arrow-left" style="font-size:.65rem;"></i> Back to Reports
                        </a>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <a href="edit_bill_form.php?id=<?= $orderId ?>" class="btn-pos btn-blue" style="height:34px;">
                            <i class="fas fa-pen" style="font-size:.6rem;"></i> Edit Order
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
            <!-- /right panel -->

        </div><!-- /layout -->
    </div><!-- /main -->
</div><!-- /page-shell -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>