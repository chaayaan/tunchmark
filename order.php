<?php
require 'auth.php';
require 'mydb.php';

// Get username from session
$createdBy = $_SESSION['username'] ?? null;

// Fetch items and services from database
$items = [];
$services = [];

$itemsQuery = "SELECT id, name FROM items WHERE is_active = 1 ORDER BY name ASC";
$itemsResult = mysqli_query($conn, $itemsQuery);
if ($itemsResult) {
    while ($row = mysqli_fetch_assoc($itemsResult)) {
        $items[] = $row;
    }
}

$servicesQuery = "SELECT id, name, price FROM services WHERE is_active = 1 ORDER BY name ASC";
$servicesResult = mysqli_query($conn, $servicesQuery);
if ($servicesResult) {
    while ($row = mysqli_fetch_assoc($servicesResult)) {
        $services[] = $row;
    }
}

// Next Order Number
$nextOrderNo = 1;
$res = mysqli_query($conn, "SELECT MAX(order_id) AS max_id FROM orders");
if ($res && $row = mysqli_fetch_assoc($res)) {
    $nextOrderNo = ($row['max_id'] ?? 0) + 1;
}

$errors = [];

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Take values directly from POST, don't fetch from database
    $customerId      = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
    $customerName    = trim($_POST['customer_name'] ?? '');
    $customerPhone   = trim($_POST['customer_phone'] ?? '');
    $customerAddress = trim($_POST['customer_address'] ?? '');
    $manufacturer    = trim($_POST['manufacturer'] ?? '');
    $boxNo           = trim($_POST['box_no'] ?? '');
    $paymentStatus   = $_POST['payment_status'] ?? 'pending';

    $itemIds     = $_POST['item_id'] ?? [];
    $serviceIds  = $_POST['service_id'] ?? [];
    $weights     = $_POST['weight'] ?? [];
    $karats      = $_POST['karat'] ?? [];
    $quantities  = $_POST['quantity'] ?? [];
    $unitPrices  = $_POST['unit_price'] ?? [];
    $totalPrices = $_POST['total_price'] ?? [];

    if ($customerName === '')  $errors[] = "Customer name is required";
    if ($customerPhone === '') $errors[] = "Customer phone is required";
    if ($paymentStatus === '') $errors[] = "Payment status is required";

    // Validate Items
    $validItems = [];
    for ($i = 0; $i < count($itemIds); $i++) {
        $itemId    = !empty($itemIds[$i])    ? intval($itemIds[$i])    : null;
        $serviceId = !empty($serviceIds[$i]) ? intval($serviceIds[$i]) : null;
        $weight    = floatval($weights[$i]   ?? 0);
        $karat     = trim($karats[$i]        ?? '');
        $qty       = intval($quantities[$i]  ?? 0);
        $unit      = floatval($unitPrices[$i] ?? 0);
        $total     = floatval($totalPrices[$i] ?? ($qty * $unit));

        if ($itemId !== null && $serviceId !== null && $qty > 0) {
            $validItems[] = [
                'item_id'     => $itemId,
                'service_id'  => $serviceId,
                'weight'      => $weight,
                'karat'       => $karat,
                'quantity'    => $qty,
                'unit_price'  => $unit,
                'total_price' => $total
            ];
        } else if ($itemId !== null || $serviceId !== null) {
            $errors[] = "Row " . ($i + 1) . ": Both Item and Service must be selected";
        }
    }

    if (empty($validItems)) {
        $errors[] = "At least one item is required.";
    }

    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        try {
            $finalCustomerId = $customerId;
            $status = ($paymentStatus === 'paid') ? 'paid' : 'pending';

            $stmt = mysqli_prepare($conn,
                "INSERT INTO orders (customer_id, customer_name, customer_phone, customer_address, manufacturer, box_no, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt, "isssssss",
                $finalCustomerId, $customerName, $customerPhone,
                $customerAddress, $manufacturer, $boxNo, $status, $createdBy
            );

            if (mysqli_stmt_execute($stmt)) {
                $orderId = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);

                $itemStmt = mysqli_prepare($conn,
                    "INSERT INTO bill_items (order_id, item_id, service_id, karat, weight, quantity, unit_price, total_price)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );

                foreach ($validItems as $item) {
                    mysqli_stmt_bind_param($itemStmt, "iiisdidd",
                        $orderId, $item['item_id'], $item['service_id'],
                        $item['karat'], $item['weight'], $item['quantity'],
                        $item['unit_price'], $item['total_price']
                    );
                    if (!mysqli_stmt_execute($itemStmt)) {
                        throw new Exception("Failed to insert bill item: " . mysqli_error($conn));
                    }
                }
                mysqli_stmt_close($itemStmt);
                mysqli_commit($conn);

                // FIXED: redirect to order.php (not order_with_conversion.php)
                header("Location: order.php?success=1&order_id=$orderId");
                exit;
            } else {
                $errors[] = "Failed to insert order: " . mysqli_error($conn);
                mysqli_rollback($conn);
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = "DB Error: " . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>New Order — Rajaiswari</title>
  <link rel="icon" type="image/png" href="favicon.png">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
  <style>
    :root {
      --bg:        #f1f3f6;
      --surface:   #ffffff;
      --surface-2: #fafbfc;
      --border:    #e4e7ec;
      --bsoft:     #f0f1f3;
      --t1:        #111827;
      --t2:        #374151;
      --t3:        #6b7280;
      --t4:        #9ca3af;
      --blue:      #2563eb;  --blue-bg: #eff6ff;  --blue-b: #bfdbfe;
      --green:     #059669;  --green-bg:#ecfdf5;  --green-b:#a7f3d0;
      --amber:     #d97706;  --amber-bg:#fffbeb;
      --red:       #dc2626;  --red-bg:  #fef2f2;  --red-b:  #fecaca;
      --violet:    #7c3aed;  --violet-bg:#f5f3ff;
      --r:         10px;
      --rs:        6px;
      --sh:        0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'DM Sans', -apple-system, sans-serif;
      font-size: 14.5px;
      background: var(--bg);
      color: var(--t1);
      -webkit-font-smoothing: antialiased;
      min-height: 100vh;
    }

    /* ── Shell ──────────────────────────────── */
    .page-shell {
      margin-left: 200px;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ── Top bar ────────────────────────────── */
    .top-bar {
      position: sticky; top: 0; z-index: 200;
      height: 54px;
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      box-shadow: var(--sh);
      display: flex; align-items: center;
      padding: 0 22px; gap: 10px; flex-shrink: 0;
    }

    .tb-ico {
      width: 32px; height: 32px;
      background: var(--blue-bg);
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      color: var(--blue); font-size: 13px; flex-shrink: 0;
    }

    .tb-title { font-size: 1.0625rem; font-weight: 700; color: var(--t1); line-height: 1.2; }
    .tb-sub   { font-size: .8rem; color: var(--t4); }

    .order-badge {
      margin-left: auto;
      display: inline-flex; align-items: center; gap: 5px;
      background: var(--surface-2);
      border: 1px solid var(--border);
      border-radius: var(--rs);
      padding: 4px 12px;
      font-family: 'DM Mono', monospace;
      font-size: .85rem; font-weight: 500; color: var(--t3);
    }

    /* ── Main ───────────────────────────────── */
    .main {
      flex: 1;
      padding: 20px 22px 60px;
      display: flex; flex-direction: column; gap: 14px;
    }

    /* ── Section card ───────────────────────── */
    .sec {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--r);
      box-shadow: var(--sh);
      overflow: hidden;
    }

    .sec-head {
      display: flex; align-items: center; gap: 9px;
      padding: 11px 18px;
      background: var(--surface-2);
      border-bottom: 1px solid var(--bsoft);
    }

    .sec-ico {
      width: 28px; height: 28px;
      border-radius: var(--rs);
      display: flex; align-items: center; justify-content: center;
      font-size: 12px; flex-shrink: 0;
    }
    .i-blue   { background: var(--blue-bg);   color: var(--blue);   }
    .i-violet { background: var(--violet-bg); color: var(--violet); }
    .i-green  { background: var(--green-bg);  color: var(--green);  }

    .sec-title {
      font-size: .9375rem; font-weight: 700;
      color: var(--t1); letter-spacing: -.01em;
    }

    .sec-body { padding: 18px; }

    /* ── Form controls ──────────────────────── */
    .lbl {
      display: block;
      font-size: .76rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .06em;
      color: var(--t3); margin-bottom: 5px;
    }
    .lbl .req { color: var(--red); margin-left: 2px; }

    .fc, select.fc {
      width: 100%; height: 38px;
      padding: 0 11px;
      border: 1.5px solid var(--border);
      border-radius: var(--rs);
      font-family: 'DM Sans', sans-serif;
      font-size: .9rem; color: var(--t2);
      background: var(--surface);
      transition: border-color .15s, box-shadow .15s;
      outline: none;
      appearance: none; -webkit-appearance: none;
    }

    select.fc {
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%239ca3af' d='M1 1l5 5 5-5'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 9px center;
      padding-right: 28px;
    }

    .fc:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
    .fc[readonly] { background: var(--surface-2); color: var(--t3); cursor: default; }

    .fhint { font-size: .76rem; color: var(--t4); margin-top: 3px; }

    /* ── Items table ────────────────────────── */
    .tbl-wrap { overflow-x: auto; }

    .tbl {
      width: 100%;
      border-collapse: collapse;
      min-width: 870px;
    }

    .tbl thead th {
      padding: 10px 8px;
      text-align: left;
      font-size: .74rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .07em;
      color: var(--t4);
      background: var(--surface-2);
      border-bottom: 1px solid var(--border);
      white-space: nowrap;
    }

    .tbl thead th:first-child { padding-left: 18px; }

    .tbl tbody td {
      padding: 8px 6px;
      border-bottom: 1px solid var(--bsoft);
      vertical-align: middle;
    }

    .tbl tbody td:first-child { padding-left: 14px; }
    .tbl tbody tr:last-child td { border-bottom: none; }
    .tbl tbody tr:hover td { background: #fafbff; }

    .row-n {
      display: inline-flex; align-items: center; justify-content: center;
      width: 26px; height: 26px;
      border-radius: 50%;
      background: var(--surface-2);
      border: 1px solid var(--border);
      font-size: .78rem; font-weight: 700;
      color: var(--t3);
      font-family: 'DM Mono', monospace;
    }

    .vori-disp {
      font-size: .76rem; font-weight: 600;
      color: var(--blue);
      margin-top: 2px;
      font-family: 'DM Mono', monospace;
      white-space: nowrap;
      min-height: 14px;
    }

    .btn-rm {
      width: 28px; height: 28px;
      display: flex; align-items: center; justify-content: center;
      background: var(--red-bg);
      border: 1.5px solid var(--red-b);
      border-radius: var(--rs);
      color: var(--red);
      font-size: 15px; font-weight: 700;
      cursor: pointer;
      transition: all .15s;
    }
    .btn-rm:hover { background: var(--red); color: #fff; border-color: var(--red); }

    /* ── Table footer ───────────────────────── */
    .tbl-foot {
      display: flex; align-items: center;
      justify-content: space-between;
      padding: 11px 18px;
      background: var(--surface-2);
      border-top: 1px solid var(--border);
      flex-wrap: wrap; gap: 10px;
    }

    .btn-add-row {
      display: inline-flex; align-items: center; gap: 6px;
      height: 34px; padding: 0 16px;
      background: var(--blue-bg);
      border: 1.5px solid var(--blue-b);
      border-radius: var(--rs);
      font-family: 'DM Sans', sans-serif;
      font-size: .875rem; font-weight: 600;
      color: var(--blue);
      cursor: pointer;
      transition: all .15s;
    }
    .btn-add-row:hover { background: #dbeafe; }

    .grand-wrap { display: flex; align-items: baseline; gap: 8px; }
    .grand-lbl  { font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: var(--t4); }
    .grand-val  {
      font-size: 1.4rem; font-weight: 800;
      color: var(--green);
      font-family: 'DM Mono', monospace;
      letter-spacing: -.02em;
    }

    /* ── Payment options ────────────────────── */
    .pay-opts { display: flex; gap: 10px; flex-wrap: wrap; }

    .pay-opt {
      display: flex; align-items: center; gap: 10px;
      flex: 1; min-width: 150px;
      padding: 12px 16px;
      border: 2px solid var(--border);
      border-radius: var(--rs);
      cursor: pointer;
      background: var(--surface);
      transition: all .15s;
      user-select: none;
    }

    .pay-opt input[type="radio"] { display: none; }

    .pay-dot {
      width: 18px; height: 18px;
      border-radius: 50%;
      border: 2px solid var(--border);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0; transition: all .15s;
    }
    .pay-dot::after {
      content: ''; width: 8px; height: 8px;
      border-radius: 50%; background: transparent; transition: background .15s;
    }

    .pay-opt.paid:has(input:checked)    { border-color: var(--green); background: var(--green-bg); }
    .pay-opt.paid:has(input:checked) .pay-dot { border-color: var(--green); }
    .pay-opt.paid:has(input:checked) .pay-dot::after { background: var(--green); }

    .pay-opt.pending:has(input:checked) { border-color: var(--amber); background: var(--amber-bg); }
    .pay-opt.pending:has(input:checked) .pay-dot { border-color: var(--amber); }
    .pay-opt.pending:has(input:checked) .pay-dot::after { background: var(--amber); }

    .pay-name { font-size: .9375rem; font-weight: 600; color: var(--t1); }
    .pay-desc { font-size: .79rem; color: var(--t4); margin-top: 1px; }

    /* ── Action bar ─────────────────────────── */
    .action-bar {
      display: flex; align-items: center;
      justify-content: flex-end; gap: 8px;
      padding: 13px 18px;
      background: var(--surface-2);
      border-top: 1px solid var(--border);
    }

    .btn-ghost {
      display: inline-flex; align-items: center; gap: 6px;
      height: 38px; padding: 0 18px;
      background: var(--surface);
      border: 1.5px solid var(--border);
      border-radius: 7px;
      font-family: 'DM Sans', sans-serif;
      font-size: .9rem; font-weight: 500; color: var(--t2);
      cursor: pointer; transition: all .15s;
    }
    .btn-ghost:hover { background: var(--surface-2); border-color: #9ca3af; }

    .btn-submit {
      display: inline-flex; align-items: center; gap: 7px;
      height: 38px; padding: 0 24px;
      background: var(--blue);
      border: none; border-radius: 7px;
      font-family: 'DM Sans', sans-serif;
      font-size: .9375rem; font-weight: 700; color: #fff;
      cursor: pointer;
      box-shadow: 0 1px 4px rgba(37,99,235,.25);
      transition: background .15s;
    }
    .btn-submit:hover    { background: #1d4ed8; }
    .btn-submit:disabled { background: #93c5fd; cursor: not-allowed; box-shadow: none; }

    /* ── Alerts ─────────────────────────────── */
    .alert-err {
      background: var(--red-bg);
      border: 1px solid var(--red-b);
      border-left: 3px solid var(--red);
      border-radius: var(--rs);
      padding: 12px 16px;
    }
    .alert-err .ae-ttl { font-size: .9rem; font-weight: 700; color: var(--red); margin-bottom: 4px; }
    .alert-err ul { margin: 0; padding-left: 16px; }
    .alert-err li { font-size: .875rem; color: var(--red); }

    .alert-ok {
      background: var(--green-bg);
      border: 1px solid var(--green-b);
      border-left: 3px solid var(--green);
      border-radius: var(--rs);
      padding: 14px 18px;
      display: flex; align-items: center;
      justify-content: space-between;
      flex-wrap: wrap; gap: 12px;
    }
    .ao-main   { font-size: .9375rem; font-weight: 700; color: #065f46; }
    .ao-detail { font-size: .875rem; color: #047857; margin-top: 3px; }

    .ao-btns { display: flex; gap: 7px; flex-shrink: 0; }

    .btn-sm {
      display: inline-flex; align-items: center; gap: 5px;
      height: 32px; padding: 0 14px;
      background: #fff;
      border: 1.5px solid var(--green-b);
      border-radius: var(--rs);
      font-family: 'DM Sans', sans-serif;
      font-size: .82rem; font-weight: 600; color: var(--green);
      cursor: pointer; transition: all .15s;
    }
    .btn-sm:hover { background: var(--green-bg); }
    .btn-sm.blue  { border-color: var(--blue-b); color: var(--blue); }
    .btn-sm.blue:hover { background: var(--blue-bg); }

    /* ── Autocomplete ───────────────────────── */
    .ac-wrap { position: relative; }

    .ac-drop {
      position: absolute;
      top: calc(100% + 2px); left: 0; right: 0;
      background: var(--surface);
      border: 1.5px solid var(--border);
      border-top: none;
      border-radius: 0 0 var(--rs) var(--rs);
      max-height: 230px; overflow-y: auto;
      z-index: 600; display: none;
      box-shadow: 0 8px 24px rgba(0,0,0,.1);
    }
    .ac-drop.open { display: block; }

    .ac-item {
      padding: 9px 12px; cursor: pointer;
      border-bottom: 1px solid var(--bsoft);
      transition: background .1s;
    }
    .ac-item:last-child { border-bottom: none; }
    .ac-item:hover, .ac-item.sel { background: var(--blue-bg); }

    .ac-name {
      font-size: .9rem; font-weight: 600; color: var(--t1);
      display: flex; align-items: center; gap: 7px;
    }
    .ac-badge {
      display: inline-block;
      background: var(--blue-bg); color: var(--blue);
      border: 1px solid var(--blue-b);
      padding: 1px 6px; border-radius: 4px;
      font-size: .74rem; font-weight: 700;
      font-family: 'DM Mono', monospace;
    }
    .ac-sub  { font-size: .82rem; color: var(--t3); margin-top: 2px; }
    .ac-hint { padding: 10px 12px; text-align: center; font-size: .875rem; color: var(--t4); }

    /* ── QR test area ───────────────────────── */
    #qrTestArea {
      position: fixed; top: 10px; right: 10px;
      width: 100px; height: 100px;
      background: #fff; border: 1px solid var(--border);
      border-radius: 6px; overflow: hidden;
      display: none; z-index: 9999;
    }

    /* ── Responsive ─────────────────────────── */
    @media (max-width: 991.98px) {
      .page-shell { margin-left: 0; }
      .top-bar    { top: 52px; }
      .main       { padding: 14px 14px 50px; }
    }
  </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="page-shell">

  <header class="top-bar">
    <div class="tb-ico"><i class="fas fa-plus"></i></div>
    <div>
      <div class="tb-title">New Order</div>
      <div class="tb-sub">Create a billing order</div>
    </div>
    <div class="order-badge">
      <i class="fas fa-hashtag" style="font-size:.6rem;"></i>
      Order #<?= htmlspecialchars($nextOrderNo) ?>
    </div>
  </header>

  <div class="main">

    <!-- Error alert -->
    <?php if (!empty($errors)): ?>
    <div class="alert-err">
      <div class="ae-ttl"><i class="fas fa-circle-exclamation" style="margin-right:5px;"></i>Please fix the following:</div>
      <ul>
        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <!-- Success alert -->
    <?php if (isset($_GET['success'], $_GET['order_id'])): ?>
    <?php
      $orderId = intval($_GET['order_id']);

      $stmt = mysqli_prepare($conn, "SELECT * FROM orders WHERE order_id=? LIMIT 1");
      mysqli_stmt_bind_param($stmt, "i", $orderId);
      mysqli_stmt_execute($stmt);
      $order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
      mysqli_stmt_close($stmt);

      $stmt = mysqli_prepare($conn, "
        SELECT bi.*, i.name AS item_name, s.name AS service_name
        FROM bill_items bi
        LEFT JOIN items    i ON bi.item_id    = i.id
        LEFT JOIN services s ON bi.service_id = s.id
        WHERE bi.order_id = ?");
      mysqli_stmt_bind_param($stmt, "i", $orderId);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      $billItems = []; $grandTotal = 0;
      while ($row = mysqli_fetch_assoc($res)) { $billItems[] = $row; $grandTotal += floatval($row['total_price']); }
      mysqli_stmt_close($stmt);

      $order['items']        = $billItems;
      $order['total_amount'] = $grandTotal;
      $order['id']           = $order['order_id'];
    ?>
    <script>window.billData = <?= json_encode($order, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP) ?>;</script>

    <div class="alert-ok">
      <div>
        <div class="ao-main">
          <i class="fas fa-circle-check" style="color:var(--green);margin-right:5px;"></i>
          Order Created Successfully!
        </div>
        <div class="ao-detail">
          Order #<?= htmlspecialchars($order['order_id']) ?>
          &nbsp;·&nbsp; Total: <strong>৳<?= number_format($grandTotal, 2) ?></strong>
          &nbsp;·&nbsp; Status: <strong><?= strtoupper(htmlspecialchars($order['status'])) ?></strong>
          <?php if (!empty($order['created_by'])): ?>
            &nbsp;·&nbsp; By: <?= htmlspecialchars($order['created_by']) ?>
          <?php endif; ?>
        </div>
      </div>
      <div class="ao-btns">
        <button class="btn-sm" id="printAgainBtn">
          <i class="fas fa-print" style="font-size:.62rem;"></i> Print Again
        </button>
        <button class="btn-sm blue" id="testQRBtn">
          <i class="fas fa-qrcode" style="font-size:.62rem;"></i> Test QR
        </button>
      </div>
    </div>
    <?php endif; ?>

    <form method="POST" id="orderForm">

      <!-- 1. Customer Information -->
      <div class="sec">
        <div class="sec-head">
          <span class="sec-ico i-blue"><i class="fas fa-user"></i></span>
          <span class="sec-title">Customer Information</span>
        </div>
        <div class="sec-body">
          <div class="row g-3">

            <div class="col-md-2">
              <label class="lbl">Customer ID</label>
              <input type="text" name="customer_id" id="customerId"
                     class="fc" placeholder="Optional">
              <div class="fhint">Won't override your inputs</div>
            </div>

            <div class="col-md-3">
              <label class="lbl">Name <span class="req">*</span></label>
              <div class="ac-wrap">
                <input type="text" name="customer_name" id="customerName"
                       class="fc" required autocomplete="off"
                       placeholder="Search or enter name">
                <div class="ac-drop" id="acName"></div>
              </div>
            </div>

            <div class="col-md-3">
              <label class="lbl">Phone <span class="req">*</span></label>
              <div class="ac-wrap">
                <input type="text" name="customer_phone" id="customerPhone"
                       class="fc" required autocomplete="off"
                       placeholder="Search or enter phone">
                <div class="ac-drop" id="acPhone"></div>
              </div>
            </div>

            <div class="col-md-4">
              <label class="lbl">Address</label>
              <input type="text" name="customer_address" id="customerAddress"
                     class="fc" placeholder="Optional">
            </div>

            <div class="col-md-4">
              <label class="lbl">Manufacturer</label>
              <input type="text" name="manufacturer" id="manufacturer"
                     class="fc" placeholder="Enter manufacturer">
            </div>

            <div class="col-md-4">
              <label class="lbl">Box No</label>
              <input type="text" name="box_no" id="boxNo"
                     class="fc" placeholder="Enter box number">
            </div>

            <div class="col-md-4">
              <label class="lbl">Order Number</label>
              <input type="text" name="order_no" id="orderNo"
                     class="fc"
                     value="<?= htmlspecialchars($nextOrderNo) ?>" readonly>
            </div>

          </div>
        </div>
      </div>

      <!-- 2. Order Items -->
      <div class="sec">
        <div class="sec-head">
          <span class="sec-ico i-violet"><i class="fas fa-list-check"></i></span>
          <span class="sec-title">Order Items</span>
        </div>

        <div class="tbl-wrap">
          <table class="tbl">
            <thead>
              <tr>
                <th style="width:34px;">#</th>
                <th style="width:170px;">Item</th>
                <th style="width:175px;">Service</th>
                <th style="width:100px;">Weight (g)</th>
                <th style="width:130px;">Weight (Vori)</th>
                <th style="width:100px;">Gold Karat</th>
                <th style="width:70px;">Qty</th>
                <th style="width:115px;">Unit Price</th>
                <th style="width:36px;"></th>
              </tr>
            </thead>
            <tbody id="itemsBody">
              <tr class="item-row">
                <td><span class="row-n">1</span></td>
                <td>
                  <select name="item_id[]" class="fc item-select">
                    <option value="">Select Item</option>
                    <?php foreach ($items as $item): ?>
                      <option value="<?= htmlspecialchars($item['id']) ?>">
                        <?= htmlspecialchars($item['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td>
                  <select name="service_id[]" class="fc service-select">
                    <option value="">Select Service</option>
                    <?php foreach ($services as $svc): ?>
                      <option value="<?= htmlspecialchars($svc['id']) ?>"
                              data-price="<?= htmlspecialchars($svc['price']) ?>">
                        <?= htmlspecialchars($svc['name']) ?>
                        <?= $svc['price'] > 0
                            ? ' — ' . number_format($svc['price'], 2) . ' TK'
                            : ' (manual)' ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td>
                  <input type="number" name="weight[]"
                         class="fc weight-input"
                         value="0" min="0" step="0.01">
                </td>
                <td><div class="vori-disp"></div></td>
                <td>
                  <input type="text" name="karat[]"
                         class="fc" placeholder="e.g. 22K">
                </td>
                <td>
                  <input type="number" name="quantity[]"
                         class="fc quantity-input"
                         value="1" min="1">
                </td>
                <td>
                  <input type="number" name="unit_price[]"
                         class="fc service-price"
                         value="0" step="0.01">
                </td>
                <td>
                  <button type="button" class="btn-rm remove-item" title="Remove">&times;</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="tbl-foot">
          <button type="button" id="addItem" class="btn-add-row">
            <i class="fas fa-plus" style="font-size:.6rem;"></i> Add Row
          </button>
          <div class="grand-wrap">
            <span class="grand-lbl">Grand Total</span>
            <span class="grand-val">৳<span id="grandTotal">0.00</span></span>
          </div>
        </div>
      </div>

      <!-- 3. Payment Status -->
      <div class="sec">
        <div class="sec-head">
          <span class="sec-ico i-green"><i class="fas fa-credit-card"></i></span>
          <span class="sec-title">Payment Status <span style="color:var(--red);">*</span></span>
        </div>
        <div class="sec-body">
          <div class="pay-opts">

            <label class="pay-opt paid">
              <input type="radio" name="payment_status" value="paid" required>
              <div class="pay-dot"></div>
              <div>
                <div class="pay-name">
                  <i class="fas fa-circle-check" style="color:var(--green);font-size:.8rem;margin-right:4px;"></i>Paid
                </div>
                <div class="pay-desc">Payment received in full</div>
              </div>
            </label>

            <label class="pay-opt pending">
              <input type="radio" name="payment_status" value="pending">
              <div class="pay-dot"></div>
              <div>
                <div class="pay-name">
                  <i class="fas fa-clock" style="color:var(--amber);font-size:.8rem;margin-right:4px;"></i>Pending
                </div>
                <div class="pay-desc">Payment due later</div>
              </div>
            </label>

          </div>
        </div>

        <div class="action-bar">
          <button type="reset" class="btn-ghost">
            <i class="fas fa-rotate-left" style="font-size:.65rem;"></i> Reset
          </button>
          <button type="submit" class="btn-submit" id="submitBtn">
            <i class="fas fa-paper-plane" style="font-size:.7rem;"></i> Submit Order
          </button>
        </div>
      </div>

    </form>
  </div><!-- /main -->
</div><!-- /page-shell -->

<div id="qrTestArea"></div>

<script>
const dbItems    = <?= json_encode($items) ?>;
const dbServices = <?= json_encode($services) ?>;

/* ── Utilities ──────────────────────────────────────────────── */
function escapeHtml(s) {
  if (s == null) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;')
                  .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function buildItemOpts() {
  return '<option value="">Select Item</option>' +
    dbItems.map(i => `<option value="${i.id}">${escapeHtml(i.name)}</option>`).join('');
}

function buildSvcOpts() {
  return '<option value="">Select Service</option>' +
    dbServices.map(s => {
      const t = parseFloat(s.price) > 0 ? ` — ${parseFloat(s.price).toFixed(2)} TK` : ' (manual)';
      return `<option value="${s.id}" data-price="${s.price}">${escapeHtml(s.name)}${t}</option>`;
    }).join('');
}

/* ── Weight: gram → Vori Ana Roti Point ──────────────────────── */
function gramToVori(g) {
  if (!g || g <= 0) return '';
  const tp = Math.round((g / 11.664) * 16 * 6 * 10);
  const b  = Math.floor(tp / 960),   r1 = tp % 960;
  const a  = Math.floor(r1 / 60),    r2 = r1 % 60;
  const r  = Math.floor(r2 / 10),    p  = r2 % 10;
  return `V:${b} A:${a} R:${r} P:${p}`;
}

function refreshConversions() {
  document.querySelectorAll('.item-row').forEach(row => {
    const g = parseFloat(row.querySelector('.weight-input').value) || 0;
    row.querySelector('.vori-disp').textContent = g > 0 ? gramToVori(g) : '';
  });
}

/* ── Row totals ─────────────────────────────────────────────── */
function updateRow(row) {
  const qty  = parseFloat(row.querySelector('.quantity-input').value) || 0;
  const unit = parseFloat(row.querySelector('.service-price').value)  || 0;
  row.querySelector('.tp-hidden').value = (qty * unit).toFixed(2);
  updateGrand();
}

function updateGrand() {
  let t = 0;
  document.querySelectorAll('.tp-hidden').forEach(el => t += parseFloat(el.value) || 0);
  document.getElementById('grandTotal').textContent = t.toFixed(2);
}

/* ── Row numbering ──────────────────────────────────────────── */
function renumber() {
  document.querySelectorAll('.item-row').forEach((row, i) => {
    const n = row.querySelector('.row-n');
    if (n) n.textContent = i + 1;
  });
}

/* ── First row: attach hidden total_price ───────────────────── */
(function() {
  const r = document.querySelector('.item-row');
  const h = Object.assign(document.createElement('input'),
    { type:'hidden', name:'total_price[]', className:'tp-hidden', value:'0' });
  r.appendChild(h);
})();

/* ── Add row ────────────────────────────────────────────────── */
document.getElementById('addItem').addEventListener('click', () => {
  const tr = document.createElement('tr');
  tr.className = 'item-row';
  tr.innerHTML = `
    <td><span class="row-n">?</span></td>
    <td><select name="item_id[]" class="fc item-select">${buildItemOpts()}</select></td>
    <td><select name="service_id[]" class="fc service-select">${buildSvcOpts()}</select></td>
    <td><input type="number" name="weight[]" class="fc weight-input" value="0" min="0" step="0.01"></td>
    <td><div class="vori-disp"></div></td>
    <td><input type="text" name="karat[]" class="fc" placeholder="e.g. 22K"></td>
    <td><input type="number" name="quantity[]" class="fc quantity-input" value="1" min="1"></td>
    <td><input type="number" name="unit_price[]" class="fc service-price" value="0" step="0.01"></td>
    <td><button type="button" class="btn-rm remove-item" title="Remove">&times;</button></td>`;
  const h = Object.assign(document.createElement('input'),
    { type:'hidden', name:'total_price[]', className:'tp-hidden', value:'0' });
  tr.appendChild(h);
  document.getElementById('itemsBody').appendChild(tr);
  renumber(); refreshConversions();
});

/* ── Delegated events on table ──────────────────────────────── */
const tbody = document.getElementById('itemsBody');

tbody.addEventListener('change', e => {
  if (e.target.classList.contains('service-select')) {
    const row = e.target.closest('.item-row');
    const opt = e.target.options[e.target.selectedIndex];
    if (opt && e.target.value) {
      row.querySelector('.service-price').value = (parseFloat(opt.dataset.price) || 0).toFixed(2);
      updateRow(row);
    }
  }
});

tbody.addEventListener('input', e => {
  const row = e.target.closest('.item-row');
  if (!row) return;
  if (e.target.classList.contains('quantity-input') ||
      e.target.classList.contains('service-price'))  updateRow(row);
  if (e.target.classList.contains('weight-input'))   refreshConversions();
});

tbody.addEventListener('click', e => {
  if (e.target.classList.contains('remove-item')) {
    const rows = document.querySelectorAll('.item-row');
    if (rows.length > 1) {
      e.target.closest('.item-row').remove();
      renumber(); updateGrand(); refreshConversions();
    } else {
      alert('At least one item row is required.');
    }
  }
});

/* ── Autocomplete factory ───────────────────────────────────── */
function makeAC(inp, drop, onPick) {
  let timer, res = [], idx = -1;
  const show = () => drop.classList.add('open');
  const hide = () => { drop.classList.remove('open'); idx = -1; };

  function render(list) {
    res = list; drop.innerHTML = '';
    if (!list.length) { drop.innerHTML = '<div class="ac-hint">No customers found</div>'; show(); return; }
    list.forEach((c, i) => {
      const d = document.createElement('div');
      d.className = 'ac-item';
      d.innerHTML = `
        <div class="ac-name">
          <span class="ac-badge">ID: ${escapeHtml(c.id)}</span>
          ${escapeHtml(c.name)}
        </div>
        <div class="ac-sub">
          <i class="fas fa-phone" style="font-size:.6rem;margin-right:3px;"></i>${escapeHtml(c.phone)}
          ${c.address ? ' &nbsp;·&nbsp; ' + escapeHtml(c.address) : ''}
        </div>`;
      d.addEventListener('mousedown', ev => { ev.preventDefault(); onPick(c); hide(); });
      drop.appendChild(d);
    });
    show();
  }

  inp.addEventListener('input', function() {
    const q = this.value.trim();
    clearTimeout(timer);
    if (!q) { hide(); return; }
    drop.innerHTML = '<div class="ac-hint"><i class="fas fa-spinner fa-spin" style="font-size:.65rem;"></i> Searching…</div>';
    show();
    timer = setTimeout(() => {
      fetch('search_customers.php?query=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(d => d.success ? render(d.data) : render([]))
        .catch(() => hide());
    }, 280);
  });

  inp.addEventListener('keydown', function(e) {
    const items = drop.querySelectorAll('.ac-item');
    if (e.key === 'ArrowDown')  { e.preventDefault(); idx = Math.min(idx+1, items.length-1); }
    else if (e.key === 'ArrowUp')  { e.preventDefault(); idx = Math.max(idx-1, -1); }
    else if (e.key === 'Enter' && idx >= 0) { e.preventDefault(); if (res[idx]) { onPick(res[idx]); hide(); } }
    else if (e.key === 'Escape') hide();
    items.forEach((it, i) => it.classList.toggle('sel', i === idx));
  });

  document.addEventListener('click', e => {
    if (!inp.contains(e.target) && !drop.contains(e.target)) hide();
  });
}

function fillCustomer(c) {
  document.getElementById('customerId').value      = c.id        || '';
  document.getElementById('customerName').value    = c.name      || '';
  document.getElementById('customerPhone').value   = c.phone     || '';
  document.getElementById('customerAddress').value = c.address   || '';
  if (c.manufacturer) document.getElementById('manufacturer').value = c.manufacturer;
}

makeAC(document.getElementById('customerName'),  document.getElementById('acName'),  fillCustomer);
makeAC(document.getElementById('customerPhone'), document.getElementById('acPhone'), fillCustomer);

/* ── Customer ID blur lookup ────────────────────────────────── */
document.getElementById('customerId').addEventListener('blur', function() {
  const id = this.value.trim();
  if (!id) return;
  fetch('fetch_customer_owc.php?id=' + encodeURIComponent(id))
    .then(r => r.json())
    .then(j => {
      if (j.success) {
        fillCustomer(j.data);
        this.style.borderColor = 'var(--green)';
        setTimeout(() => this.style.borderColor = '', 800);
      } else {
        alert('Customer ID not found');
        this.value = ''; this.focus();
      }
    })
    .catch(() => {});
});

/* ── QR Code (unchanged logic from original) ───────────────── */
function generateQRCode(bill) {
  return new Promise(resolve => {
    try {
      const base = window.location.origin + window.location.pathname.replace('order.php','');
      const qr = qrcode(0,'M');
      qr.addData(`${base}view_bill.php?id=${bill.id||''}`);
      qr.make();

      try {
        const svg = qr.createSvgTag(4,2);
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = 120;
        const ctx = canvas.getContext('2d'), img = new Image();
        const url = URL.createObjectURL(new Blob([svg], {type:'image/svg+xml;charset=utf-8'}));
        img.onload = () => {
          ctx.fillStyle='white'; ctx.fillRect(0,0,120,120);
          ctx.drawImage(img,0,0,120,120);
          URL.revokeObjectURL(url);
          resolve(canvas.toDataURL('image/png',0.9));
        };
        img.onerror = () => { URL.revokeObjectURL(url); fallback(); };
        setTimeout(() => { if (!img.complete) { URL.revokeObjectURL(url); fallback(); } }, 3000);
        img.src = url;
      } catch(e) { fallback(); }

      function fallback() {
        try {
          const m = qr.getModuleCount(), cs = Math.max(2,Math.floor(120/m));
          const c = document.createElement('canvas');
          c.width = c.height = cs*m;
          const ctx = c.getContext('2d');
          ctx.fillStyle='white'; ctx.fillRect(0,0,c.width,c.height);
          ctx.fillStyle='black';
          for(let r=0;r<m;r++) for(let x=0;x<m;x++)
            if(qr.isDark(r,x)) ctx.fillRect(x*cs,r*cs,cs,cs);
          resolve(c.toDataURL('image/png',0.9));
        } catch(e) { resolve(null); }
      }
    } catch(e) { resolve(null); }
  });
}

function buildReceiptHtml(bill, qrURL) {
  let items = '';
  (bill.items||[]).forEach((item, i) => {
    const w = parseFloat(item.weight)||0;
    items += `Purpose: ${escapeHtml(item.service_name||'')} | ${escapeHtml(item.karat||'')}<br>`;
    items += `Item: ${escapeHtml(item.item_name||item.service_name||'')} | Qty: ${escapeHtml(item.quantity||'')}<br>`;
    items += `Weight: ${w.toFixed(2)} gm [${gramToVori(w)}]<br>`;
    if (i < bill.items.length-1) items += '<br>';
  });
  const mfr = (bill.manufacturer||'').trim() ? `Manufacturer: ${escapeHtml(bill.manufacturer)}<br>` : '';
  const box = (bill.box_no||'').trim()        ? `Box No: ${escapeHtml(bill.box_no)}<br>`           : '';
  const qr  = qrURL && qrURL.startsWith('data:image')
    ? `<div class="c" style="margin:8px 0;background:white;padding:4px;border:1px solid #ddd;">
         <div style="font-size:9px;margin-bottom:2px;color:#666;">Scan for details</div>
         <img src="${qrURL}" style="width:85px;height:85px;display:block;margin:0 auto;">
       </div>`
    : `<div class="c" style="margin:8px 0;padding:4px;border:2px dashed #ccc;">
         <div style="width:60px;height:60px;border:2px dashed #999;margin:0 auto;display:flex;align-items:center;justify-content:center;font-size:10px;color:#999;">QR</div>
       </div>`;
  const d = new Date().toLocaleString('en-GB',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit',hour12:true});
  return `<html><head><meta charset="utf-8"><style>
    body{font-family:Arial,sans-serif;font-size:12px;margin:4px;line-height:1.3;max-width:80mm;}
    .c{text-align:center;} hr{border:none;border-top:1px dashed #000;margin:4px 0;}
    .logo{width:66mm;height:auto;display:block;margin:0 auto;max-width:100%;}
    .ft{text-align:center;margin-top:4px;font-size:10px;color:#666;}
  </style></head><body>
  <div class="c"><img src="receiptheader.png" class="logo" onerror="this.style.display='none';"></div>
  <hr>
  <div><strong>TOKEN</strong><br>Date: ${d}<br>Token No: ${escapeHtml(bill.id||bill.order_id||'')}<br>
    Customer ID: ${escapeHtml(bill.customer_id||'N/A')}<br>
    Name: ${escapeHtml(bill.customer_name||'')}<br>Mobile: ${escapeHtml(bill.customer_phone||'')}<br>
    ${bill.customer_address?`Address: ${escapeHtml(bill.customer_address)}<br>`:''}${mfr}${box}
  </div>
  <hr><div><strong>ITEMS:</strong><br>${items}</div>
  <hr><div><strong>Total Charge: ${parseFloat(bill.total_amount||0).toFixed(2)} Tk</strong><br>
    Payment Status: ${escapeHtml((bill.status||'').toUpperCase())}
  </div><hr>${qr}
  <div class="ft">THANK YOU | HAVE A GOOD DAY | CDev</div>
  </body></html>`;
}

function printSilent(html) {
  const f = Object.assign(document.createElement('iframe'),
    {style:'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden'});
  document.body.appendChild(f);
  const doc = f.contentDocument || f.contentWindow.document;
  doc.open(); doc.write(html); doc.close();
  setTimeout(() => { try { f.contentWindow.print(); } catch(e){} }, 500);
  setTimeout(() => { try { f.remove(); } catch(e){} }, 3000);
}

async function printBillTwice(bill) {
  if (!bill) return;
  try {
    const qr = await generateQRCode(bill);
    const h  = buildReceiptHtml(bill, qr);
    printSilent(h); setTimeout(() => printSilent(h), 800);
  } catch(e) {
    const h = buildReceiptHtml(bill, null);
    printSilent(h); setTimeout(() => printSilent(h), 800);
  }
}

/* ── Init ───────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  updateGrand();
  refreshConversions();

  if (window.billData) {
    setTimeout(() => printBillTwice(window.billData), 2000);

    document.getElementById('printAgainBtn')
      ?.addEventListener('click', () => printBillTwice(window.billData));

    document.getElementById('testQRBtn')
      ?.addEventListener('click', () => {
        generateQRCode(window.billData).then(url => {
          const a = document.getElementById('qrTestArea');
          a.innerHTML = url
            ? `<img src="${url}" style="width:100%;height:100%;object-fit:contain;">`
            : '<div style="color:red;font-size:10px;text-align:center;padding:10px;">QR Failed</div>';
          a.style.display = 'block';
          setTimeout(() => a.style.display = 'none', 8000);
        });
      });
  }
});

/* ── Form validation ────────────────────────────────────────── */
document.getElementById('orderForm').addEventListener('submit', function(e) {
  const name   = document.getElementById('customerName').value.trim();
  const phone  = document.getElementById('customerPhone').value.trim();
  const status = document.querySelector('input[name="payment_status"]:checked');

  if (!name)   { e.preventDefault(); alert('Customer name is required');  document.getElementById('customerName').focus();  return; }
  if (!phone)  { e.preventDefault(); alert('Customer phone is required'); document.getElementById('customerPhone').focus(); return; }
  if (!status) { e.preventDefault(); alert('Please select a payment status'); return; }

  let valid = false;
  document.querySelectorAll('.item-row').forEach(row => {
    const iSel = row.querySelector('.item-select');
    const sSel = row.querySelector('.service-select');
    const qty  = parseInt(row.querySelector('.quantity-input').value) || 0;
    if (iSel?.value && sSel?.value && qty > 0) valid = true;
  });

  if (!valid) {
    e.preventDefault();
    alert('At least one row with both Item and Service selected is required');
    return;
  }

  const btn = this.querySelector('#submitBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:.7rem;"></i> Processing…';
});
</script>
</body>
</html>