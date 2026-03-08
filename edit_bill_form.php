<?php
require 'auth.php';
include 'mydb.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

// Fetch items and services from database
$items_list    = [];
$services_list = [];

$itemsQuery  = "SELECT id, name FROM items WHERE is_active = 1 ORDER BY name ASC";
$itemsResult = mysqli_query($conn, $itemsQuery);
if ($itemsResult) {
    while ($row = mysqli_fetch_assoc($itemsResult)) $items_list[] = $row;
}

$servicesQuery  = "SELECT id, name, price FROM services WHERE is_active = 1 ORDER BY name ASC";
$servicesResult = mysqli_query($conn, $servicesQuery);
if ($servicesResult) {
    while ($row = mysqli_fetch_assoc($servicesResult)) $services_list[] = $row;
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) die("Invalid order id.");

// Fetch order
$stmt = mysqli_prepare($conn, "SELECT * FROM orders WHERE order_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res   = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$order) die("Order not found.");

// Fetch order items
$itemsStmt = mysqli_prepare($conn, "
    SELECT bi.*,
           i.id as item_id, i.name as item_name,
           s.id as service_id, s.name as service_name, s.price as service_price
    FROM bill_items bi
    LEFT JOIN items i ON bi.item_id = i.id
    LEFT JOIN services s ON bi.service_id = s.id
    WHERE bi.order_id = ?
    ORDER BY bi.bill_item_id ASC
");
mysqli_stmt_bind_param($itemsStmt, "i", $id);
mysqli_stmt_execute($itemsStmt);
$itemsRes = mysqli_stmt_get_result($itemsStmt);

$items       = [];
$totalAmount = 0;
while ($item = mysqli_fetch_assoc($itemsRes)) {
    $items[]      = $item;
    $totalAmount += floatval($item['total_price']);
}
mysqli_stmt_close($itemsStmt);

if (empty($items)) {
    $items[] = [
        'bill_item_id' => 0, 'item_id' => 0, 'item_name' => '',
        'service_id'   => 0, 'service_name' => '', 'karat' => '',
        'weight' => 0, 'quantity' => 1, 'unit_price' => 0, 'total_price' => 0
    ];
}

include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Order #<?= htmlspecialchars($order['order_id']) ?> — Rajaiswari</title>
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
            display: flex; flex-direction: column;
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
            font-size: 13px; background: var(--violet-bg); color: var(--violet);
            flex-shrink: 0;
        }
        .tb-title { font-size: 1.0625rem; font-weight: 700; color: var(--t1); }
        .tb-sub   { font-size: .78rem; color: var(--t4); }
        .tb-right { margin-left: auto; display: flex; gap: 7px; align-items: center; }

        /* ── Buttons ───────────────────────────── */
        .btn-pos {
            display: inline-flex; align-items: center; gap: 6px;
            height: 34px; padding: 0 14px;
            border: none; border-radius: var(--rs);
            font-family: inherit; font-size: .8125rem; font-weight: 600;
            cursor: pointer; transition: all .15s; text-decoration: none;
            white-space: nowrap;
        }
        .btn-green { background: var(--green); color: #fff; }
        .btn-green:hover { background: #047857; color: #fff; }
        .btn-ghost {
            background: var(--surface); color: var(--t2);
            border: 1.5px solid var(--border);
        }
        .btn-ghost:hover { background: var(--s2); border-color: #9ca3af; color: var(--t1); }
        .btn-red { background: var(--red); color: #fff; border: none; }
        .btn-red:hover { background: #b91c1c; color: #fff; }
        .btn-blue-sm {
            display: inline-flex; align-items: center; gap: 5px;
            height: 28px; padding: 0 11px;
            background: var(--blue); color: #fff;
            border: none; border-radius: var(--rs);
            font-family: inherit; font-size: .775rem; font-weight: 600;
            cursor: pointer; transition: all .15s;
        }
        .btn-blue-sm:hover { background: #1d4ed8; }
        .btn-red-sm {
            display: inline-flex; align-items: center; justify-content: center;
            width: 28px; height: 28px;
            background: var(--red-bg); color: var(--red);
            border: 1.5px solid var(--red-b); border-radius: var(--rs);
            cursor: pointer; transition: all .15s; font-size: .75rem;
        }
        .btn-red-sm:hover { background: var(--red); color: #fff; }

        /* ── Main ──────────────────────────────── */
        .main {
            flex: 1;
            padding: 20px 22px 60px;
            display: flex; flex-direction: column; gap: 14px;
        }

        /* ── Section card ──────────────────────── */
        .sec {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            box-shadow: var(--sh);
            overflow: hidden;
        }
        .sec-hd {
            display: flex; align-items: center; gap: 9px;
            padding: 11px 18px;
            background: var(--s2);
            border-bottom: 1px solid var(--bsoft);
        }
        .sec-ico {
            width: 26px; height: 26px; border-radius: var(--rs);
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; flex-shrink: 0;
        }
        .si-bl { background: var(--blue-bg);   color: var(--blue);   }
        .si-vi { background: var(--violet-bg); color: var(--violet); }
        .si-am { background: var(--amber-bg);  color: var(--amber);  }
        .si-gr { background: var(--green-bg);  color: var(--green);  }

        .sec-title { font-size: .875rem; font-weight: 700; color: var(--t1); }
        .sec-hd-right { margin-left: auto; }

        .sec-body { padding: 18px; }

        /* ── Form fields ───────────────────────── */
        .field-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }
        .field-grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }
        @media (max-width: 900px) {
            .field-grid-3, .field-grid-2 { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 560px) {
            .field-grid-3, .field-grid-2 { grid-template-columns: 1fr; }
        }

        .lbl {
            display: block; font-size: .72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .06em;
            color: var(--t3); margin-bottom: 5px;
        }
        .lbl .req { color: var(--red); margin-left: 2px; }

        .fc {
            width: 100%; height: 36px; padding: 0 10px;
            border: 1.5px solid var(--border); border-radius: var(--rs);
            font-family: inherit; font-size: .875rem; color: var(--t2);
            background: var(--surface); outline: none;
            transition: border-color .15s, box-shadow .15s;
            appearance: none;
        }
        .fc:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
        select.fc {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='7' viewBox='0 0 10 7'%3E%3Cpath fill='%239ca3af' d='M0 0l5 7 5-7z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 9px center;
            padding-right: 28px;
            cursor: pointer;
        }
        .fc-sm {
            height: 32px; padding: 0 8px;
            border: 1.5px solid var(--border); border-radius: var(--rs);
            font-family: inherit; font-size: .8125rem; color: var(--t2);
            background: var(--surface); outline: none; width: 100%;
            transition: border-color .15s;
            appearance: none;
        }
        .fc-sm:focus { border-color: var(--blue); box-shadow: 0 0 0 2px rgba(37,99,235,.08); }
        select.fc-sm {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='7' viewBox='0 0 10 7'%3E%3Cpath fill='%239ca3af' d='M0 0l5 7 5-7z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 7px center;
            padding-right: 24px;
            cursor: pointer;
        }
        .fc-sm.readonly {
            background: var(--s2); color: var(--t3); cursor: default;
            font-family: 'DM Mono', monospace; font-weight: 700;
        }

        /* ── Items table ───────────────────────── */
        .items-tbl-wrap { overflow-x: auto; }

        .items-tbl {
            width: 100%; border-collapse: collapse;
            min-width: 820px;
        }
        .items-tbl thead th {
            padding: 8px 10px;
            font-size: .7rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .07em;
            color: var(--t4); background: var(--s2);
            border-bottom: 1px solid var(--border);
            white-space: nowrap; text-align: left;
        }
        .items-tbl thead th:first-child { padding-left: 18px; width: 28px; }
        .items-tbl thead th.r { text-align: right; }

        .items-tbl tbody .item-row td {
            padding: 7px 8px;
            border-bottom: 1px solid var(--bsoft);
            vertical-align: middle;
        }
        .items-tbl tbody .item-row:last-child td { border-bottom: none; }
        .items-tbl tbody .item-row:hover td { background: #fafbff; }
        .items-tbl tbody .item-row td:first-child { padding-left: 18px; }

        /* row number */
        .rn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 20px; height: 20px; border-radius: 50%;
            background: var(--s2); border: 1px solid var(--border);
            font-size: .7rem; font-weight: 700; color: var(--t4);
            font-family: 'DM Mono', monospace; flex-shrink: 0;
        }

        /* total footer row */
        .total-footer {
            display: flex; align-items: center;
            justify-content: flex-end;
            padding: 14px 18px;
            background: var(--s2);
            border-top: 2px solid var(--border);
            gap: 14px;
        }
        .total-footer-lbl {
            font-size: .76rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .06em; color: var(--t3);
        }
        .total-footer-val {
            font-size: 1.5rem; font-weight: 800; color: var(--t1);
            font-family: 'DM Mono', monospace; letter-spacing: -.02em;
        }
        .total-footer-val span { font-size: .9rem; font-weight: 600; color: var(--t3); }

        /* ── Status radio ──────────────────────── */
        .status-options {
            display: flex; gap: 10px; flex-wrap: wrap;
        }
        .status-opt {
            display: flex; align-items: center; gap: 8px;
            padding: 9px 16px;
            border: 2px solid var(--border);
            border-radius: var(--rs);
            cursor: pointer; transition: all .15s;
            flex: 1; min-width: 110px;
        }
        .status-opt:has(input:checked).opt-paid     { border-color: var(--green); background: var(--green-bg); }
        .status-opt:has(input:checked).opt-pending  { border-color: var(--amber); background: var(--amber-bg); }
        .status-opt:has(input:checked).opt-cancelled{ border-color: var(--red);   background: var(--red-bg);   }
        .status-opt input { display: none; }
        .so-dot {
            width: 10px; height: 10px; border-radius: 50%;
            border: 2px solid currentColor; flex-shrink: 0;
            transition: background .15s;
        }
        .status-opt:has(input:checked) .so-dot { background: currentColor; }
        .opt-paid      { color: var(--green); }
        .opt-pending   { color: var(--amber); }
        .opt-cancelled { color: var(--red);   }
        .so-lbl { font-size: .875rem; font-weight: 600; color: var(--t1); }

        /* ── Form actions bar ──────────────────── */
        .form-actions {
            display: flex; align-items: center;
            justify-content: flex-end; gap: 8px;
            padding: 14px 18px;
            background: var(--s2);
            border: 1px solid var(--border);
            border-radius: var(--r);
            box-shadow: var(--sh);
        }

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
        <div class="tb-ico"><i class="fas fa-pen-to-square"></i></div>
        <div>
            <div class="tb-title">Edit Order #<?= htmlspecialchars($order['order_id']) ?></div>
            <div class="tb-sub">Modify order details and items</div>
        </div>
        <div class="tb-right">
            <a href="bill.php?id=<?= $order['order_id'] ?>" class="btn-pos btn-ghost">
                <i class="fas fa-eye" style="font-size:.6rem;"></i> View Bill
            </a>
            <a href="edit_bills.php" class="btn-pos btn-ghost">
                <i class="fas fa-arrow-left" style="font-size:.6rem;"></i> Back
            </a>
        </div>
    </header>

    <div class="main">
    <form id="editOrderForm" method="post" action="update_bill.php">
        <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">

        <!-- Customer Information -->
        <div class="sec">
            <div class="sec-hd">
                <span class="sec-ico si-bl"><i class="fas fa-user"></i></span>
                <span class="sec-title">Customer Information</span>
            </div>
            <div class="sec-body" style="display:flex;flex-direction:column;gap:14px;">
                <div class="field-grid-3">
                    <div>
                        <label class="lbl">Customer Name <span class="req">*</span></label>
                        <input type="text" name="customer_name" class="fc" required
                               value="<?= htmlspecialchars($order['customer_name']) ?>">
                    </div>
                    <div>
                        <label class="lbl">Phone <span class="req">*</span></label>
                        <input type="text" name="customer_phone" class="fc" required
                               value="<?= htmlspecialchars($order['customer_phone']) ?>"
                               style="font-family:'DM Mono',monospace;">
                    </div>
                    <div>
                        <label class="lbl">Address</label>
                        <input type="text" name="customer_address" class="fc"
                               value="<?= htmlspecialchars($order['customer_address'] ?? '') ?>">
                    </div>
                </div>
                <div class="field-grid-2">
                    <div>
                        <label class="lbl">Manufacturer</label>
                        <input type="text" name="manufacturer" class="fc"
                               value="<?= htmlspecialchars($order['manufacturer'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="lbl">Box No</label>
                        <input type="text" name="box_no" class="fc"
                               value="<?= htmlspecialchars($order['box_no'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="sec">
            <div class="sec-hd">
                <span class="sec-ico si-am"><i class="fas fa-list-check"></i></span>
                <span class="sec-title">Order Items</span>
                <div class="sec-hd-right">
                    <button type="button" id="addRow" class="btn-blue-sm">
                        <i class="fas fa-plus" style="font-size:.6rem;"></i> Add Item
                    </button>
                </div>
            </div>

            <div class="items-tbl-wrap">
                <table class="items-tbl">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th style="min-width:150px;">Item</th>
                            <th style="min-width:160px;">Service</th>
                            <th style="width:72px;">Karat</th>
                            <th style="width:90px;">Weight (g)</th>
                            <th style="width:72px;">Qty</th>
                            <th style="width:110px;">Unit Price</th>
                            <th style="width:110px;" class="r">Total</th>
                            <th style="width:36px;"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsWrapper">
                        <?php foreach ($items as $i => $item): ?>
                        <tr class="item-row" data-is-existing="true">
                            <td><span class="rn"><?= $i + 1 ?></span>
                                <input type="hidden" name="items[<?= $i ?>][bill_item_id]" value="<?= $item['bill_item_id'] ?>">
                            </td>
                            <td>
                                <select name="items[<?= $i ?>][item_id]" class="fc-sm" required>
                                    <option value="">Select Item</option>
                                    <?php foreach ($items_list as $itm): ?>
                                        <option value="<?= $itm['id'] ?>" <?= $item['item_id'] == $itm['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($itm['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="items[<?= $i ?>][service_id]" class="fc-sm service-select" data-row-index="<?= $i ?>" required>
                                    <option value="">Select Service</option>
                                    <?php foreach ($services_list as $srv): ?>
                                        <option value="<?= $srv['id'] ?>"
                                                data-price="<?= $srv['price'] ?>"
                                                <?= $item['service_id'] == $srv['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($srv['name']) ?>
                                            <?php if ($srv['price'] > 0): ?> — ৳<?= number_format($srv['price'], 2) ?><?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="text" name="items[<?= $i ?>][karat]" class="fc-sm"
                                       placeholder="22K" value="<?= htmlspecialchars($item['karat'] ?? '') ?>">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" name="items[<?= $i ?>][weight]"
                                       class="fc-sm" placeholder="0.00"
                                       value="<?= number_format($item['weight'], 2) ?>">
                            </td>
                            <td>
                                <input type="number" step="1" min="1" name="items[<?= $i ?>][quantity]"
                                       class="fc-sm qty-input" placeholder="1" required
                                       value="<?= intval($item['quantity']) ?>">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" name="items[<?= $i ?>][unit_price]"
                                       class="fc-sm price-input" placeholder="0.00" required
                                       value="<?= number_format($item['unit_price'], 2) ?>">
                            </td>
                            <td style="text-align:right;">
                                <input type="text" readonly class="fc-sm readonly total-cell"
                                       value="<?= number_format($item['total_price'], 2) ?>">
                                <input type="hidden" name="items[<?= $i ?>][total_price]" class="hidden-total"
                                       value="<?= number_format($item['total_price'], 2, '.', '') ?>">
                            </td>
                            <td style="text-align:center;">
                                <button type="button" class="btn-red-sm remove-row">
                                    <i class="fas fa-times"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Grand Total -->
            <div class="total-footer">
                <span class="total-footer-lbl">Grand Total</span>
                <span class="total-footer-val">
                    <span>৳</span><span id="grandTotalDisplay"><?= number_format($totalAmount, 2) ?></span>
                </span>
            </div>
        </div>

        <!-- Payment Status -->
        <div class="sec">
            <div class="sec-hd">
                <span class="sec-ico si-gr"><i class="fas fa-credit-card"></i></span>
                <span class="sec-title">Payment Status</span>
            </div>
            <div class="sec-body">
                <div class="status-options">
                    <label class="status-opt opt-paid">
                        <input type="radio" name="status" value="paid" <?= $order['status'] === 'paid' ? 'checked' : '' ?>>
                        <span class="so-dot"></span>
                        <span class="so-lbl">Paid</span>
                    </label>
                    <label class="status-opt opt-pending">
                        <input type="radio" name="status" value="pending" <?= $order['status'] === 'pending' ? 'checked' : '' ?>>
                        <span class="so-dot"></span>
                        <span class="so-lbl">Pending</span>
                    </label>
                    <label class="status-opt opt-cancelled">
                        <input type="radio" name="status" value="cancelled" <?= $order['status'] === 'cancelled' ? 'checked' : '' ?>>
                        <span class="so-dot"></span>
                        <span class="so-lbl">Cancelled</span>
                    </label>
                </div>
                <!-- hidden select for form submission fallback -->
                <select name="status" id="statusSelect" style="display:none;" required>
                    <option value="paid"      <?= $order['status'] === 'paid'      ? 'selected' : '' ?>>Paid</option>
                    <option value="pending"   <?= $order['status'] === 'pending'   ? 'selected' : '' ?>>Pending</option>
                    <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <a href="edit_bills.php" class="btn-pos btn-ghost">
                <i class="fas fa-times" style="font-size:.6rem;"></i> Cancel
            </a>
            <button type="submit" class="btn-pos btn-green">
                <i class="fas fa-floppy-disk" style="font-size:.65rem;"></i> Update Order
            </button>
        </div>

    </form>
    </div><!-- /main -->
</div><!-- /page-shell -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
// Pass PHP data to JavaScript
const dbItems    = <?= json_encode($items_list) ?>;
const dbServices = <?= json_encode($services_list) ?>;

function buildItemOptions(selectedId = 0) {
    let html = '<option value="">Select Item</option>';
    dbItems.forEach(item => {
        const selected = item.id == selectedId ? 'selected' : '';
        html += `<option value="${item.id}" ${selected}>${escapeHtml(item.name)}</option>`;
    });
    return html;
}

function buildServiceOptions(selectedId = 0) {
    let html = '<option value="">Select Service</option>';
    dbServices.forEach(service => {
        const selected = service.id == selectedId ? 'selected' : '';
        const priceText = service.price > 0 ? ` — ৳${parseFloat(service.price).toFixed(2)}` : '';
        html += `<option value="${service.id}" data-price="${service.price}" ${selected}>${escapeHtml(service.name)}${priceText}</option>`;
    });
    return html;
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Sync radio status to hidden select
document.querySelectorAll('.status-opt input[type=radio]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('statusSelect').value = this.value;
    });
});

(function(){
    function updateRowTotal(row) {
        const qty   = parseFloat(row.querySelector('.qty-input').value) || 0;
        const price = parseFloat(row.querySelector('.price-input').value) || 0;
        const total = qty * price;
        row.querySelector('.total-cell').value   = total.toFixed(2);
        row.querySelector('.hidden-total').value = total.toFixed(2);
        updateGrandTotal();
    }

    function updateGrandTotal() {
        let sum = 0;
        document.querySelectorAll('.hidden-total').forEach(inp => {
            sum += parseFloat(inp.value) || 0;
        });
        document.getElementById('grandTotalDisplay').textContent = sum.toFixed(2);
    }

    function reNumberRows() {
        document.querySelectorAll('#itemsWrapper .item-row').forEach((row, idx) => {
            const rn = row.querySelector('.rn');
            if (rn) rn.textContent = idx + 1;
        });
    }

    // Service select change — auto-fill price ONLY for NEW rows
    document.getElementById('itemsWrapper').addEventListener('change', function(e) {
        if (e.target.classList.contains('service-select')) {
            const row        = e.target.closest('.item-row');
            const isExisting = row.getAttribute('data-is-existing') === 'true';
            if (!isExisting) {
                const selectedOption = e.target.options[e.target.selectedIndex];
                const price          = parseFloat(selectedOption.getAttribute('data-price')) || 0;
                const priceInput     = row.querySelector('.price-input');
                if (price > 0) { priceInput.value = price.toFixed(2); updateRowTotal(row); }
            }
        }
    });

    // Update totals on input
    document.getElementById('itemsWrapper').addEventListener('input', function(e) {
        if (e.target.classList.contains('qty-input') || e.target.classList.contains('price-input')) {
            updateRowTotal(e.target.closest('.item-row'));
        }
    });

    // Add new row
    document.getElementById('addRow').addEventListener('click', function() {
        const index = document.querySelectorAll('#itemsWrapper .item-row').length;
        const tr    = document.createElement('tr');
        tr.className = 'item-row';
        tr.setAttribute('data-is-existing', 'false');
        tr.innerHTML = `
            <td><span class="rn">${index + 1}</span>
                <input type="hidden" name="items[${index}][bill_item_id]" value="0">
            </td>
            <td><select name="items[${index}][item_id]" class="fc-sm" required>${buildItemOptions()}</select></td>
            <td><select name="items[${index}][service_id]" class="fc-sm service-select" data-row-index="${index}" required>${buildServiceOptions()}</select></td>
            <td><input type="text"   name="items[${index}][karat]"      class="fc-sm" placeholder="22K"></td>
            <td><input type="number" name="items[${index}][weight]"     class="fc-sm" step="0.01" min="0" placeholder="0.00"></td>
            <td><input type="number" name="items[${index}][quantity]"   class="fc-sm qty-input"   step="1"    min="1" placeholder="1" required value="1"></td>
            <td><input type="number" name="items[${index}][unit_price]" class="fc-sm price-input" step="0.01" min="0" placeholder="0.00" required></td>
            <td style="text-align:right;">
                <input type="text" readonly class="fc-sm readonly total-cell" value="0.00">
                <input type="hidden" name="items[${index}][total_price]" class="hidden-total" value="0.00">
            </td>
            <td style="text-align:center;">
                <button type="button" class="btn-red-sm remove-row"><i class="fas fa-times"></i></button>
            </td>
        `;
        document.getElementById('itemsWrapper').appendChild(tr);
    });

    // Remove row
    document.getElementById('itemsWrapper').addEventListener('click', function(e) {
        if (e.target.closest('.remove-row')) {
            const rows = document.querySelectorAll('#itemsWrapper .item-row');
            if (rows.length > 1) {
                e.target.closest('.item-row').remove();
                // Reindex names
                document.querySelectorAll('#itemsWrapper .item-row').forEach((r, idx) => {
                    r.querySelectorAll('input[name], select[name]').forEach(inp => {
                        inp.setAttribute('name', inp.getAttribute('name').replace(/items\[\d+\]/, `items[${idx}]`));
                    });
                });
                reNumberRows();
                updateGrandTotal();
            } else {
                alert('You must have at least one item.');
            }
        }
    });

    // Initial calculation
    document.querySelectorAll('#itemsWrapper .item-row').forEach(r => updateRowTotal(r));
})();
</script>
</body>
</html>