<?php
require 'auth.php';
require 'mydb.php';

// Fixed element orders for Gold and Silver reports
$GOLD_ELEMENTS   = ['Silver','Platinum','Bismuth','Copper','Palladium','Nickel','Zinc','Antimony','Indium','Cadmium','Iron','Titanium','Iridium','Tin','Ruthenium','Rhodium','Lead','Vanadium','Cobalt','Osmium','Manganese'];
$SILVER_ELEMENTS = ['Copper','Palladium','Nickel','Zinc','Antimony','Indium','Cadmium','Iron','Titanium','Iridium','Tin','Ruthenium','Rhodium','Lead','Vanadium','Cobalt','Osmium','Manganese'];

// All DB column names for metal elements (lowercase)
$ALL_ELEMENT_COLUMNS = ['silver','platinum','bismuth','copper','palladium','nickel','zinc','antimony','indium','cadmium','iron','titanium','iridium','tin','ruthenium','rhodium','lead','vanadium','cobalt','osmium','manganese','germanium','tungsten','gallium','rhenium'];

$order_data     = null;
$bill_items     = [];
$report_created = false;
$report_id      = null;
$report_data    = null;

// Fetch active items
$itemsResult = mysqli_query($conn, "SELECT id, name FROM items WHERE is_active = 1 ORDER BY name ASC");
$items = [];
if ($itemsResult) while ($row = mysqli_fetch_assoc($itemsResult)) $items[] = $row;

// Fetch tunch services
$servicesResult = mysqli_query($conn, "SELECT id, name FROM services WHERE is_active = 1 AND name NOT LIKE '%hallmark%' ORDER BY name ASC");
$services = [];
if ($servicesResult) while ($row = mysqli_fetch_assoc($servicesResult)) $services[] = $row;

// Fetch Order
if (isset($_POST['fetch_order'])) {
    $order_id = intval($_POST['order_id']);
    $stmt = mysqli_prepare($conn, "SELECT order_id, customer_name FROM orders WHERE order_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    mysqli_stmt_execute($stmt);
    $order_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$order_data) {
        $error = "Order not found!";
    } else {
        $stmt = mysqli_prepare($conn, "SELECT bi.bill_item_id, bi.weight, bi.karat, i.name as item_name, s.name as service_name
                                       FROM bill_items bi
                                       JOIN items i ON bi.item_id = i.id
                                       JOIN services s ON bi.service_id = s.id
                                       WHERE bi.order_id = ? AND s.name NOT LIKE '%hallmark%'");
        mysqli_stmt_bind_param($stmt, "i", $order_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) $bill_items[] = $row;
        mysqli_stmt_close($stmt);
        if (empty($bill_items)) $error = "No tunch service bill items found for this order!";
    }
}

// Submit Report
if (isset($_POST['submit_report']) && !isset($_GET['report_id'])) {
    $order_id      = intval($_POST['order_id']);
    $customer_name = trim($_POST['customer_name']);
    $bill_item_id  = intval($_POST['bill_item_id']);
    $item_name     = trim($_POST['item_name']);
    $service_name  = trim($_POST['service_name']);
    $weight        = floatval($_POST['weight']);

    $itemLower = strtolower($item_name);
    $isSilver  = strpos($itemLower, 'silver') !== false || strpos($itemLower, 'চাঁদি') !== false || strpos($itemLower, 'rupa') !== false;

    $gold_purity_percent   = $isSilver ? null : (floatval($_POST['purity_percent']) ?: null);
    $silver_purity_percent = $isSilver ? (floatval($_POST['purity_percent']) ?: null) : null;
    $karat     = floatval($_POST['karat'])     ?: null;
    $gold_val  = floatval($_POST['gold_val'])  ?: null;
    $joint_val = floatval($_POST['joint_val']) ?: null;

    $elementCols  = [];
    $elementVals  = [];
    $elementTypes = '';
    foreach ($ALL_ELEMENT_COLUMNS as $col) {
        $raw = $_POST['elem_' . $col] ?? '';
        $val = ($raw === '' || $raw === '--------') ? null : floatval($raw);
        $elementCols[]  = "`$col`";
        $elementVals[]  = $val;
        $elementTypes  .= 'd';
    }

    $sql = "INSERT INTO `customer_reports`
        (order_id, customer_name, item_name, service_name, weight,
         gold_purity_percent, silver_purity_percent, karat,
         " . implode(', ', $elementCols) . ",
         gold, joint, hallmark, address)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?,
                " . implode(', ', array_fill(0, count($elementCols), '?')) . ",
                ?, ?, NULL, NULL)";

    $stmt   = mysqli_prepare($conn, $sql);
    $types  = 'isssdddd' . $elementTypes . 'dd';
    $params = array_merge(
        [$order_id, $customer_name, $item_name, $service_name, $weight,
         $gold_purity_percent, $silver_purity_percent, $karat],
        $elementVals,
        [$gold_val, $joint_val]
    );
    $bindParams = [$types];
    foreach ($params as &$p) $bindParams[] = &$p;
    call_user_func_array([$stmt, 'bind_param'], $bindParams);
    mysqli_stmt_execute($stmt);
    $report_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    header("Location: create_tunch_report.php?report_id=" . $report_id);
    exit;
}

// Fetch Existing Report
if (isset($_GET['report_id'])) {
    $report_id = intval($_GET['report_id']);
    $stmt = mysqli_prepare($conn, "SELECT * FROM customer_reports WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $report_id);
    mysqli_stmt_execute($stmt);
    $report_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($report_data) $report_created = true;
}

include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tunch Report — Rajaiswari</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
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
            --violet:  #7c3aed; --violet-bg:#f5f3ff;
            --r: 10px; --rs: 6px;
            --sh: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; font-size: 14px; background: var(--bg); color: var(--t1); -webkit-font-smoothing: antialiased; min-height: 100vh; }

        /* Shell */
        .page-shell { margin-left: 200px; min-height: 100vh; display: flex; flex-direction: column; }
        .top-bar { position: sticky; top: 0; z-index: 200; height: 54px; background: var(--surface); border-bottom: 1px solid var(--border); box-shadow: var(--sh); display: flex; align-items: center; padding: 0 22px; gap: 12px; flex-shrink: 0; }
        .tb-ico { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; background: var(--violet-bg); color: var(--violet); flex-shrink: 0; }
        .tb-title { font-size: 1.0625rem; font-weight: 700; color: var(--t1); }
        .tb-sub   { font-size: .78rem; color: var(--t4); }
        .tb-right { margin-left: auto; display: flex; gap: 7px; align-items: center; }

        /* Buttons */
        .btn-pos { display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 14px; border: none; border-radius: var(--rs); font-family: inherit; font-size: .8125rem; font-weight: 600; cursor: pointer; transition: all .15s; text-decoration: none; white-space: nowrap; }
        .btn-ghost  { background: var(--surface); color: var(--t2); border: 1.5px solid var(--border); }
        .btn-ghost:hover  { background: var(--s2); border-color: #9ca3af; color: var(--t1); }
        .btn-blue   { background: var(--blue);   color: #fff; } .btn-blue:hover   { background: #1d4ed8; }
        .btn-green  { background: var(--green);  color: #fff; } .btn-green:hover  { background: #047857; }
        .btn-amber  { background: var(--amber);  color: #fff; } .btn-amber:hover  { background: #b45309; }
        .btn-violet { background: var(--violet); color: #fff; } .btn-violet:hover { background: #6d28d9; }

        /* ── SPLIT LAYOUT ── */
        .split-body { padding: 18px 16px 60px; }
        .split-left  { display: flex; flex-direction: column; gap: 14px; }
        .split-right { display: flex; flex-direction: column; gap: 14px; }
        .col-divider { border-left: 2px solid var(--border); }

        /* Placeholder for right panel before order fetch */
        .rp-placeholder {
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            gap: 10px; min-height: 300px;
            color: var(--t4); text-align: center;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--r); box-shadow: var(--sh);
        }
        .rp-placeholder-icon {
            width: 50px; height: 50px; border-radius: 12px;
            background: var(--border); display: flex; align-items: center;
            justify-content: center; font-size: 20px; color: var(--t4); margin: 0 auto;
        }
        .rp-placeholder h3 { font-size: .875rem; font-weight: 700; color: var(--t3); margin: 0; }
        .rp-placeholder p  { font-size: .8rem; color: var(--t4); margin: 0; max-width: 190px; line-height: 1.5; }

        /* Section cards */
        .sec { background: var(--surface); border: 1px solid var(--border); border-radius: var(--r); box-shadow: var(--sh); overflow: hidden; }
        .sec-hd { display: flex; align-items: center; gap: 9px; padding: 11px 18px; background: var(--s2); border-bottom: 1px solid var(--bsoft); }
        .sec-ico { width: 26px; height: 26px; border-radius: var(--rs); display: flex; align-items: center; justify-content: center; font-size: 11px; flex-shrink: 0; }
        .si-vi { background: var(--violet-bg); color: var(--violet); }
        .si-bl { background: var(--blue-bg);   color: var(--blue);   }
        .si-am { background: var(--amber-bg);  color: var(--amber);  }
        .si-gr { background: var(--green-bg);  color: var(--green);  }
        .sec-title { font-size: .875rem; font-weight: 700; color: var(--t1); }
        .sec-body  { padding: 18px; }
        .sec-title-note { margin-left: auto; font-size: .75rem; color: var(--amber); font-weight: 600; }

        /* Alerts */
        .pos-alert { display: flex; align-items: flex-start; gap: 10px; padding: 12px 16px; border-radius: var(--rs); font-size: .875rem; font-weight: 500; }
        .pos-alert.danger { background: var(--red-bg);   border: 1px solid var(--red-b);   border-left: 3px solid var(--red);   color: #991b1b; }
        .pos-alert.info   { background: var(--blue-bg);  border: 1px solid var(--blue-b);  border-left: 3px solid var(--blue);  color: #1e40af; }
        .pos-alert.amber  { background: var(--amber-bg); border: 1px solid var(--amber-b); border-left: 3px solid var(--amber); color: #92400e; }

        /* Grids */
        .field-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .field-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }

        /* Labels & inputs */
        .lbl { display: block; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--t3); margin-bottom: 5px; }
        .lbl .req { color: var(--red); margin-left: 2px; }
        .fc { width: 100%; height: 36px; padding: 0 10px; border: 1.5px solid var(--border); border-radius: var(--rs); font-family: inherit; font-size: .875rem; color: var(--t2); background: var(--surface); outline: none; transition: border-color .15s, box-shadow .15s; }
        .fc:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
        .fc.editable { background: var(--amber-bg); border-color: var(--amber-b); }
        .fc.editable:focus { border-color: var(--amber); box-shadow: 0 0 0 3px rgba(217,119,6,.1); }
        textarea.fc { height: auto; padding: 8px 10px; resize: vertical; }

        .fetch-row { display: flex; gap: 10px; align-items: flex-end; }

        /* Bill item cards */
        .item-cards { display: flex; flex-direction: column; gap: 8px; }
        .item-card { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border: 2px solid var(--border); border-radius: var(--rs); cursor: pointer; transition: all .2s; }
        .item-card:hover    { border-color: var(--blue-b); background: var(--blue-bg); }
        .item-card.selected { border-color: var(--green);  background: var(--green-bg); }
        .item-card input[type=radio] { accent-color: var(--green); flex-shrink: 0; }
        .item-card-info { flex: 1; display: flex; flex-wrap: wrap; gap: 6px 16px; }
        .ic-tag { display: inline-flex; align-items: center; gap: 4px; font-size: .8rem; color: var(--t2); }
        .ic-tag strong { color: var(--t1); font-weight: 700; }

        /* XRF */
        .xrf-textarea { font-family: 'DM Mono', monospace; font-size: 12px; line-height: 1.6; }
        .parse-btn { margin-top: 8px; }

        /* Element group titles */
        .element-group-title { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--t3); margin-bottom: 8px; padding-bottom: 5px; border-bottom: 1px solid var(--bsoft); }

        .form-actions { display: flex; justify-content: flex-end; padding: 14px 18px; background: var(--s2); border-top: 1px solid var(--border); }

        /* Report (after submit) */
        .report-wrap { padding: 20px 22px 60px; display: flex; flex-direction: column; gap: 14px; max-width: 860px; }
        .tunch-preview { width: auto; max-width: 700px; padding: 0 30px; background: white; border: 1px solid var(--border); border-radius: var(--r); box-shadow: var(--sh); overflow: hidden; }
        .tunch-container { padding: 0 20px; background: white; position: relative; }
        .tunch-container::before { content: ''; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-25deg); width: 200px; height: 200px; background-image: url('Varifiedstamp.png'); background-size: contain; background-repeat: no-repeat; background-position: center; opacity: 0.2; z-index: 1; pointer-events: none; }
        .tunch-container > * { position: relative; z-index: 2; }
        .report-header { display: flex; justify-content: space-between; align-items: flex-start; }
        .customer-info { flex: 1; }
        .customer-info-line { margin: 0; padding: 0; font-size: 15px; line-height: 1.8; display: flex; color: #000; font-weight: 600; }
        .customer-info-line.customer-name { font-size: 22px; font-weight: bold; margin-bottom: 3px; }
        .info-label  { display: inline-block; min-width: 120px; font-weight: 600; }
        .info-colon  { display: inline-block; width: 15px; text-align: center; }
        .info-value  { flex: 1; font-weight: 600; }
        .qr-section  { width: 100px; text-align: center; padding: 5px; margin-left: 15px; flex-shrink: 0; }
        .qr-date     { font-size: 12px; color: #000; font-weight: 700; line-height: 1.4; margin-top: 5px; white-space: nowrap; }
        .weight-conversion { font-size: 13px; color: #333; font-weight: 600; margin-left: 10px; }
        .dotted-line { border-top: 3px dotted #000; margin: 5px 0; }
        .quality-info { font-size: 24px; font-weight: bold; margin: 5px 0; line-height: 1.5; display: flex; justify-content: space-around; flex-wrap: wrap; gap: 20px; color: #000; }
        .quality-info span { white-space: nowrap; }
        .composition-table { width: 100%; margin: 2px 0 0 0; font-size: 11px; line-height: 1.1; border-collapse: collapse; }
        .composition-table td { padding: 1px 5px; font-weight: 600; vertical-align: top; }
        .composition-table td.element-name  { text-align: left; padding-right: 3px; }
        .composition-table td.element-colon { text-align: center; padding: 1px 2px; }
        .composition-table td.element-value { text-align: left; padding-left: 3px; padding-right: 15px; }
        .report-note  { font-size: 11px; line-height: 1.4; margin: 3px 0 0; font-weight: 600; color: #000; }
        .report-codes { font-size: 11px; text-align: right; margin: 1px 0 0; font-weight: bold; color: #000; }
        .report-actions { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 16px 18px; background: var(--s2); border: 1px solid var(--border); border-radius: var(--r); box-shadow: var(--sh); flex-wrap: wrap; }

        @media (max-width: 991.98px) {
            .page-shell { margin-left: 0; }
            .top-bar { top: 52px; }
            .col-divider { border-left: none; border-top: 2px solid var(--border); padding-top: 14px; margin-top: 0; }
        }
        @media print {
            .page-shell { margin-left: 0; }
            .split-body { display: none !important; }
        }
    </style>
</head>
<body>
<div class="page-shell">

    <header class="top-bar">
        <div class="tb-ico"><i class="fas fa-flask"></i></div>
        <div>
            <div class="tb-title">Tunch Report</div>
            <div class="tb-sub">Generate purity testing certificate from XRF analysis</div>
        </div>
        <div class="tb-right">
            <?php if ($report_created): ?>
            <a href="create_tunch_report.php" class="btn-pos btn-ghost">
                <i class="fas fa-plus" style="font-size:.6rem;"></i> New Report
            </a>
            <?php endif; ?>
            <a href="dashboard.php" class="btn-pos btn-ghost">
                <i class="fas fa-gauge-high" style="font-size:.6rem;"></i> Dashboard
            </a>
        </div>
    </header>

    <?php if (!$report_created): ?>

    <!-- ════════════════════════════════════════════
         SPLIT LAYOUT
    ════════════════════════════════════════════ -->
    <div class="split-body">
        <div class="row g-0">

        <!-- ── LEFT: Steps 1–4 ── col-6 ─────── -->
        <div class="col-12 col-lg-6 pe-lg-3">
        <div class="split-left">

            <!-- Step 1 -->
            <div class="sec">
                <div class="sec-hd">
                    <span class="sec-ico si-vi"><i class="fas fa-magnifying-glass"></i></span>
                    <span class="sec-title">Step 1 — Fetch Order</span>
                </div>
                <div class="sec-body">
                    <?php if (isset($error)): ?>
                    <div class="pos-alert danger" style="margin-bottom:14px;">
                        <i class="fas fa-circle-xmark" style="font-size:.9rem;flex-shrink:0;margin-top:1px;"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="fetch-row">
                            <div>
                                <label class="lbl">Order ID <span class="req">*</span></label>
                                <input type="number" name="order_id" class="fc"
                                       style="max-width:200px;font-family:'DM Mono',monospace;"
                                       required placeholder="e.g. 1042"
                                       value="<?= isset($_POST['order_id']) ? intval($_POST['order_id']) : '' ?>">
                            </div>
                            <button type="submit" name="fetch_order" class="btn-pos btn-blue">
                                <i class="fas fa-magnifying-glass" style="font-size:.6rem;"></i> Fetch Order
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($order_data && !empty($bill_items)): ?>

            <!-- Step 2 -->
            <div class="sec">
                <div class="sec-hd">
                    <span class="sec-ico si-am"><i class="fas fa-list-check"></i></span>
                    <span class="sec-title">Step 2 — Select Bill Item</span>
                </div>
                <div class="sec-body">
                    <div class="item-cards">
                        <?php foreach ($bill_items as $index => $bill_item): ?>
                        <div class="item-card <?= $index === 0 ? 'selected' : '' ?>" onclick="selectBillItem(<?= $index ?>)">
                            <input type="radio" name="selected_bill_item" id="bill_item_<?= $index ?>" value="<?= $index ?>" <?= $index === 0 ? 'checked' : '' ?>>
                            <div class="item-card-info">
                                <span class="ic-tag"><strong><?= htmlspecialchars($bill_item['item_name']) ?></strong></span>
                                <span class="ic-tag">Weight: <strong><?= htmlspecialchars($bill_item['weight']) ?> gm</strong></span>
                                <span class="ic-tag">Karat: <strong><?= htmlspecialchars($bill_item['karat'] ?: 'N/A') ?></strong></span>
                                <span class="ic-tag" style="color:var(--violet);"><?= htmlspecialchars($bill_item['service_name']) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Main form (hidden fields + Step 3 + Step 4) -->
            <!-- Step 5 inputs live in the right panel using form="reportForm" -->
            <form method="POST" id="reportForm">
                <input type="hidden" name="order_id"     value="<?= htmlspecialchars($order_data['order_id']) ?>">
                <input type="hidden" name="bill_item_id" id="bill_item_id" value="<?= $bill_items[0]['bill_item_id'] ?>">
                <input type="hidden" name="service_name" id="service_name" value="<?= htmlspecialchars($bill_items[0]['service_name']) ?>">

                <!-- Step 3 -->
                <div class="sec">
                    <div class="sec-hd">
                        <span class="sec-ico si-bl"><i class="fas fa-user"></i></span>
                        <span class="sec-title">Step 3 — Customer & Sample Details</span>
                        <span class="sec-title-note"><i class="fas fa-pen" style="font-size:.6rem;"></i> Editable</span>
                    </div>
                    <div class="sec-body" style="display:flex;flex-direction:column;gap:14px;">
                        <div>
                            <label class="lbl">Customer Name <span class="req">*</span></label>
                            <input type="text" name="customer_name" id="customer_name" class="fc editable" required
                                   value="<?= htmlspecialchars($order_data['customer_name']) ?>">
                        </div>
                        <div class="field-grid-2">
                            <div>
                                <label class="lbl">Sample Item Name <span class="req">*</span></label>
                                <input type="text" name="item_name" id="item_name" class="fc editable" required
                                       value="<?= htmlspecialchars($bill_items[0]['item_name']) ?>"
                                       oninput="updateMetalType()">
                            </div>
                            <div>
                                <label class="lbl">Sample Weight (gm) <span class="req">*</span></label>
                                <input type="number" name="weight" id="weight" class="fc editable" step="0.001" required
                                       value="<?= $bill_items[0]['weight'] ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 4 -->
                <div class="sec">
                    <div class="sec-hd">
                        <span class="sec-ico si-vi"><i class="fas fa-paste"></i></span>
                        <span class="sec-title">Step 4 — Paste XRF Analysis Data</span>
                    </div>
                    <div class="sec-body" style="display:flex;flex-direction:column;gap:12px;">
                        <div class="pos-alert amber">
                            <i class="fas fa-bolt" style="font-size:.9rem;flex-shrink:0;margin-top:1px;"></i>
                            <span>Paste your XRF data and click <strong>Auto-Extract</strong> — values fill on the right automatically.</span>
                        </div>
                        <div>
                            <label class="lbl">Raw XRF Data</label>
                            <textarea id="xrf_raw" class="fc xrf-textarea" rows="9"
                                placeholder="Paste full XRF analysis text here e.g.&#10;Gold Purity : 75.47%  Karat : 18.11&#10;Silver  1.610  Copper  21.950  Zinc  0.040 ..."></textarea>
                        </div>
                        <div>
                            <button type="button" class="btn-pos btn-violet parse-btn" onclick="parseXRFData()">
                                <i class="fas fa-wand-magic-sparkles" style="font-size:.65rem;"></i> Auto-Extract Values
                            </button>
                        </div>
                    </div>
                </div>

            </form>

            <?php endif; ?>

        </div><!-- /split-left -->
        </div><!-- /col-6 left -->

        <!-- ── RIGHT: Step 5 — col-6 ─────────── -->
        <div class="col-12 col-lg-6 ps-lg-3 col-divider">
        <div class="split-right">

            <?php if ($order_data && !empty($bill_items)): ?>

            <div class="sec">
                <div class="sec-hd">
                    <span class="sec-ico si-gr"><i class="fas fa-vial"></i></span>
                    <span class="sec-title">Step 5 — Testing Results</span>
                </div>
                <div class="sec-body" style="display:flex;flex-direction:column;gap:16px;">

                    <!-- Purity & Karat -->
                    <div class="field-grid-2">
                        <div>
                            <label class="lbl"><span id="purityLabel">Gold Purity</span> (%) <span class="req">*</span></label>
                            <input type="number" name="purity_percent" id="purity_percent"
                                   class="fc" step="0.001" placeholder="e.g. 75.470"
                                   form="reportForm" required>
                        </div>
                        <div>
                            <label class="lbl">Karat <span class="req">*</span></label>
                            <input type="number" name="karat" id="karat"
                                   class="fc" step="0.01" placeholder="e.g. 18.11"
                                   form="reportForm" required
                                   style="font-family:'DM Mono',monospace;font-weight:700;">
                        </div>
                    </div>

                    <!-- Common Elements -->
                    <div>
                        <div class="element-group-title">Metal Composition — Common Elements</div>
                        <div class="field-grid-3">
                            <?php $commonElements = ['silver','copper','zinc','cadmium','nickel','palladium','indium','iridium','tin','ruthenium','rhodium','lead','cobalt','osmium','iron']; ?>
                            <?php foreach ($commonElements as $el): ?>
                            <div>
                                <label class="lbl"><?= ucfirst($el) ?></label>
                                <input type="text" name="elem_<?= $el ?>" id="elem_<?= $el ?>"
                                       class="fc" form="reportForm"
                                       style="font-family:'DM Mono',monospace;font-size:.8rem;"
                                       placeholder="--------">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Gold-Specific -->
                    <div>
                        <div class="element-group-title">Metal Composition — Gold-Specific</div>
                        <div class="field-grid-3">
                            <?php $goldSpecific = ['germanium','bismuth','platinum','tungsten','gallium','rhenium']; ?>
                            <?php foreach ($goldSpecific as $el): ?>
                            <div>
                                <label class="lbl"><?= ucfirst($el) ?></label>
                                <input type="text" name="elem_<?= $el ?>" id="elem_<?= $el ?>"
                                       class="fc" form="reportForm"
                                       style="font-family:'DM Mono',monospace;font-size:.8rem;"
                                       placeholder="--------">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Silver-Specific -->
                    <div>
                        <div class="element-group-title">Metal Composition — Silver-Specific</div>
                        <div class="field-grid-3">
                            <?php $silverSpecific = ['antimony','titanium','vanadium','manganese']; ?>
                            <?php foreach ($silverSpecific as $el): ?>
                            <div>
                                <label class="lbl"><?= ucfirst($el) ?></label>
                                <input type="text" name="elem_<?= $el ?>" id="elem_<?= $el ?>"
                                       class="fc" form="reportForm"
                                       style="font-family:'DM Mono',monospace;font-size:.8rem;"
                                       placeholder="--------">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Gold & Joint -->
                    <div>
                        <div class="element-group-title">Gold & Joint</div>
                        <div class="field-grid-2">
                            <div>
                                <label class="lbl">Gold (%)</label>
                                <input type="number" name="gold_val" id="gold_val"
                                       class="fc" form="reportForm"
                                       step="0.001" placeholder="e.g. 0.000"
                                       style="font-family:'DM Mono',monospace;">
                            </div>
                            <div>
                                <label class="lbl">Joint (%)</label>
                                <input type="number" name="joint_val" id="joint_val"
                                       class="fc" form="reportForm"
                                       step="0.001" placeholder="e.g. 0.000"
                                       style="font-family:'DM Mono',monospace;">
                            </div>
                        </div>
                    </div>

                </div>
                <div class="form-actions">
                    <button type="submit" name="submit_report" form="reportForm"
                            class="btn-pos btn-green" style="height:38px;font-size:.9rem;">
                        <i class="fas fa-file-circle-check" style="font-size:.7rem;"></i> Generate Tunch Report
                    </button>
                </div>
            </div>

            <?php else: ?>
            <div class="rp-placeholder">
                <div class="rp-placeholder-icon"><i class="fas fa-vial"></i></div>
                <h3>Testing Results</h3>
                <p>Fetch an order on the left to enter composition data here</p>
            </div>
            <?php endif; ?>

        </div><!-- /split-right -->
        </div><!-- /col-6 right -->

        </div><!-- /row -->
    </div><!-- /split-body -->

    <?php endif; ?>

    <?php if ($report_created && $report_data):
        $itemNameLower = strtolower($report_data['item_name']);
        $isSilver      = strpos($itemNameLower, 'silver') !== false || strpos($itemNameLower, 'চাঁদি') !== false || strpos($itemNameLower, 'rupa') !== false;
        $purityLabel   = $isSilver ? 'Silver Purity' : 'Gold Purity';
        $purityValue   = $isSilver ? $report_data['silver_purity_percent'] : $report_data['gold_purity_percent'];
        $elementOrder  = $isSilver ? $SILVER_ELEMENTS : $GOLD_ELEMENTS;

        $elements = [];
        foreach ($elementOrder as $elName) {
            $col     = strtolower($elName);
            $val     = $report_data[$col] ?? null;
            $display = ($val === null) ? '--------%' : number_format((float)$val, 3) . '%';
            $elements[] = ['name' => $elName, 'value' => $display];
        }

        $goldCode  = $report_data['gold']  !== null ? number_format((float)$report_data['gold'],  3) : '';
        $jointCode = $report_data['joint'] !== null ? number_format((float)$report_data['joint'], 3) : '';
        $nbNote    = 'The report pertains to specific point and not responsible for other point or melting issues.';
    ?>

    <!-- ════════════════════════════════════════════
         REPORT VIEW (unchanged from original)
    ════════════════════════════════════════════ -->
    <div class="report-wrap">

        <div class="tunch-preview">
            <div class="tunch-container" id="reportPreview">
                <div class="report-header">
                    <div class="customer-info">
                        <div class="customer-info-line customer-name">
                            Customer Name : <?= htmlspecialchars($report_data['customer_name']) ?>
                        </div>
                        <div class="customer-info-line">
                            <span class="info-label">Sample Item</span>
                            <span class="info-colon">:</span>
                            <span class="info-value"><?= htmlspecialchars($report_data['item_name']) ?></span>
                        </div>
                        <div class="customer-info-line">
                            <span class="info-label">Sample Weight</span>
                            <span class="info-colon">:</span>
                            <span class="info-value"><?= htmlspecialchars($report_data['weight']) ?> Gm<span class="weight-conversion" id="weightConversion"></span></span>
                        </div>
                        <div class="customer-info-line">
                            <span class="info-label">Bill No</span>
                            <span class="info-colon">:</span>
                            <span class="info-value"><?= htmlspecialchars($report_data['order_id']) ?></span>
                        </div>
                    </div>
                    <div class="qr-section">
                        <div id="qrcode"></div>
                        <div class="qr-date"><?= date('d-M-y', strtotime($report_data['created_at'])) ?> <?= date('g:i A', strtotime($report_data['created_at'])) ?></div>
                    </div>
                </div>

                <div class="dotted-line"></div>

                <div class="quality-info">
                    <span><?= $purityLabel ?> : <?= htmlspecialchars($purityValue ?? 'N/A') ?>%</span>
                    <span>Karat : <?= htmlspecialchars($report_data['karat'] ?? 'N/A') ?>K</span>
                </div>

                <div class="dotted-line"></div>

                <?php if (!empty($elements)): ?>
                <table class="composition-table">
                    <?php foreach (array_chunk($elements, 3) as $row): ?>
                    <tr>
                        <?php foreach ($row as $el): ?>
                        <td class="element-name"><?= htmlspecialchars($el['name']) ?></td>
                        <td class="element-colon">:</td>
                        <td class="element-value"><?= htmlspecialchars($el['value']) ?></td>
                        <?php endforeach; ?>
                        <?php for ($i = count($row); $i < 3; $i++): ?>
                        <td class="element-name"></td><td class="element-colon"></td><td class="element-value"></td>
                        <?php endfor; ?>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php endif; ?>

                <div class="report-note">NB:- <?= htmlspecialchars($nbNote) ?></div>

                <?php if ($goldCode !== '' || $jointCode !== ''): ?>
                <div class="report-codes">
                    <?php
                    $parts = [];
                    if ($goldCode  !== '') $parts[] = 'Gold: '  . htmlspecialchars($goldCode);
                    if ($jointCode !== '') $parts[] = 'Joint: ' . htmlspecialchars($jointCode);
                    echo implode('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', $parts);
                    ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="report-actions">
            <button onclick="copyFullReportImage()" class="btn-pos btn-amber" style="height:38px;font-size:.875rem;">
                <i class="fas fa-copy" style="font-size:.65rem;"></i> Copy Report with QR
            </button>
            <a href="create_tunch_report.php" class="btn-pos btn-green" style="height:38px;font-size:.875rem;">
                <i class="fas fa-plus" style="font-size:.6rem;"></i> Create New Report
            </a>
        </div>

        <div class="pos-alert info">
            <i class="fas fa-circle-info" style="font-size:.9rem;flex-shrink:0;margin-top:1px;"></i>
            <span>Click <strong>Copy Report with QR</strong>, then open MS Word and press <strong>Ctrl+V</strong> to paste.</span>
        </div>

    </div><!-- /report-wrap -->

    <script>
        function convertGramToVoriAna(gram) {
            if (!gram || gram <= 0) return '';
            const totalPoints = Math.round((gram / 11.664) * 16 * 6 * 10);
            const bhori = Math.floor(totalPoints / 960);
            const rem   = totalPoints % 960;
            const ana   = Math.floor(rem / 60);
            const rem2  = rem % 60;
            const roti  = Math.floor(rem2 / 10);
            const point = rem2 % 10;
            return `(V:${bhori} A:${ana} R:${roti} P:${point})`;
        }
        document.getElementById('weightConversion').textContent = convertGramToVoriAna(<?= floatval($report_data['weight']) ?>);
        new QRCode(document.getElementById("qrcode"), {
            text: "https://www.app.rajaiswari.com/report_varification.php?id=<?= $report_id ?>",
            width: 90, height: 90
        });
        async function copyFullReportImage() {
            const btn  = event.target;
            const orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Capturing...';
            try {
                const canvas = await html2canvas(document.getElementById("reportPreview"), { scale: 2, useCORS: true, backgroundColor: '#ffffff', logging: false });
                canvas.toBlob(async blob => {
                    try {
                        await navigator.clipboard.write([new ClipboardItem({ "image/png": blob })]);
                        btn.disabled = false; btn.innerHTML = orig;
                        alert("✅ Report copied! Press Ctrl+V in MS Word to paste.");
                    } catch(e) { btn.disabled = false; btn.innerHTML = orig; alert("❌ Clipboard error: " + e.message); }
                });
            } catch(e) { btn.disabled = false; btn.innerHTML = orig; alert("❌ Capture error: " + e.message); }
        }
    </script>

    <?php endif; ?>

</div><!-- /page-shell -->

<script>
const GOLD_ELEMENTS   = <?= json_encode($GOLD_ELEMENTS) ?>;
const SILVER_ELEMENTS = <?= json_encode($SILVER_ELEMENTS) ?>;
const billItems       = <?= isset($bill_items) ? json_encode($bill_items) : '[]' ?>;

function isSilverItem(name) {
    const l = name.toLowerCase();
    return l.includes('silver') || l.includes('চাঁদি') || l.includes('rupa');
}

function updateMetalType() {
    const name = document.getElementById('item_name')?.value || '';
    const lbl  = document.getElementById('purityLabel');
    if (lbl) lbl.textContent = isSilverItem(name) ? 'Silver Purity' : 'Gold Purity';
}

function parseXRFData() {
    const input    = document.getElementById('xrf_raw').value;
    const itemName = document.getElementById('item_name').value;
    const silver   = isSilverItem(itemName);

    const purityRx = silver
        ? /Silver\s+Purity\s*[:\s]+([\d.]+)\s*%/i
        : /Gold\s+Purity\s*[:\s]+([\d.]+)\s*%/i;
    const purityM = input.match(purityRx);
    if (purityM) document.getElementById('purity_percent').value = purityM[1];

    const karatM = input.match(/Karat\s*[:\s]+([\d.]+)/i);
    if (karatM) document.getElementById('karat').value = karatM[1];

    const allElements = [...new Set([...GOLD_ELEMENTS, ...SILVER_ELEMENTS])];
    allElements.forEach(el => {
        const field = document.getElementById('elem_' + el.toLowerCase());
        if (!field) return;
        const rx    = new RegExp(el + '\\s*[:\\s]+([\\d.]+|--------)', 'i');
        const match = input.match(rx);
        field.value = match ? match[1].replace('%', '') : '';
    });

    const goldM  = input.match(/Gold\s*[:\s]+([\d.]+)/i);
    const jointM = input.match(/Joint\s*[:\s]+([\d.]+)/i);
    if (goldM)  document.getElementById('gold_val').value  = goldM[1];
    if (jointM) document.getElementById('joint_val').value = jointM[1];

    const btn  = document.querySelector('.parse-btn');
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> Extracted!';
    btn.style.background = 'var(--green)';
    setTimeout(() => { btn.innerHTML = orig; btn.style.background = ''; }, 2000);
}

function selectBillItem(index) {
    document.getElementById('bill_item_' + index).checked  = true;
    document.getElementById('bill_item_id').value           = billItems[index].bill_item_id;
    document.getElementById('item_name').value              = billItems[index].item_name;
    document.getElementById('weight').value                 = billItems[index].weight;
    document.getElementById('service_name').value           = billItems[index].service_name;
    document.getElementById('xrf_raw').value                = '';
    document.getElementById('purity_percent').value         = '';
    document.getElementById('karat').value                  = '';
    updateMetalType();
    document.querySelectorAll('.item-card').forEach(c => c.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
}

document.addEventListener('DOMContentLoaded', () => {
    const firstCard = document.querySelector('.item-card');
    if (firstCard) firstCard.classList.add('selected');
    updateMetalType();
});
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>