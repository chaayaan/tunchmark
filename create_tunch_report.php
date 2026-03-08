<?php
require 'auth.php';
require 'mydb.php';

// Initialize variables
$order_data     = null;
$bill_items     = [];
$items          = [];
$services       = [];
$report_created = false;
$report_id      = null;
$report_data    = null;

// Fetch active items for dropdown
$itemsQuery  = "SELECT id, name FROM items WHERE is_active = 1 ORDER BY name ASC";
$itemsResult = mysqli_query($conn, $itemsQuery);
if ($itemsResult) {
    while ($row = mysqli_fetch_assoc($itemsResult)) $items[] = $row;
}

// Fetch active services (only Tunch)
$servicesQuery  = "SELECT id, name FROM services WHERE is_active = 1 AND name NOT LIKE '%hallmark%' ORDER BY name ASC";
$servicesResult = mysqli_query($conn, $servicesQuery);
if ($servicesResult) {
    while ($row = mysqli_fetch_assoc($servicesResult)) $services[] = $row;
}

// Handle order fetch
if (isset($_POST['fetch_order'])) {
    $order_id = intval($_POST['order_id']);
    $stmt = mysqli_prepare($conn, "SELECT order_id, customer_name FROM orders WHERE order_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    mysqli_stmt_execute($stmt);
    $result     = mysqli_stmt_get_result($stmt);
    $order_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$order_data) {
        $error = "Order not found!";
    } else {
        $billItemsQuery = "SELECT bi.bill_item_id, bi.weight, bi.karat, i.name as item_name, s.name as service_name
                          FROM bill_items bi
                          JOIN items i ON bi.item_id = i.id
                          JOIN services s ON bi.service_id = s.id
                          WHERE bi.order_id = ? AND s.name NOT LIKE '%hallmark%'";
        $stmt = mysqli_prepare($conn, $billItemsQuery);
        mysqli_stmt_bind_param($stmt, "i", $order_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) $bill_items[] = $row;
        mysqli_stmt_close($stmt);

        if (empty($bill_items)) $error = "No tunch service bill items found for this order!";
    }
}

// Handle report submission
if (isset($_POST['submit_report']) && !isset($_GET['report_id'])) {
    $order_id      = intval($_POST['order_id']);
    $customer_name = trim($_POST['customer_name']);
    $bill_item_id  = intval($_POST['bill_item_id']);
    $item_name     = trim($_POST['item_name']);
    $service_name  = trim($_POST['service_name']);
    $weight        = floatval($_POST['weight']);
    $gold_purity   = trim($_POST['gold_purity']);
    $karat         = trim($_POST['karat']);

    $stmt = mysqli_prepare($conn, "INSERT INTO customer_reports (order_id, customer_name, item_name, service_name, weight, gold_purity, karat, hallmark, address) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, NULL)");
    mysqli_stmt_bind_param($stmt, "isssdss", $order_id, $customer_name, $item_name, $service_name, $weight, $gold_purity, $karat);
    mysqli_stmt_execute($stmt);
    $report_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    header("Location: create_tunch_report.php?report_id=" . $report_id);
    exit;
}

// Fetch existing report
if (isset($_GET['report_id'])) {
    $report_id = intval($_GET['report_id']);
    $stmt = mysqli_prepare($conn, "SELECT * FROM customer_reports WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $report_id);
    mysqli_stmt_execute($stmt);
    $result      = mysqli_stmt_get_result($stmt);
    $report_data = mysqli_fetch_assoc($result);
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
        .tb-right { margin-left: auto; display: flex; gap: 7px; align-items: center; }

        .btn-pos {
            display: inline-flex; align-items: center; gap: 6px;
            height: 34px; padding: 0 14px;
            border: none; border-radius: var(--rs);
            font-family: inherit; font-size: .8125rem; font-weight: 600;
            cursor: pointer; transition: all .15s; text-decoration: none; white-space: nowrap;
        }
        .btn-ghost { background: var(--surface); color: var(--t2); border: 1.5px solid var(--border); }
        .btn-ghost:hover { background: var(--s2); border-color: #9ca3af; color: var(--t1); }
        .btn-blue  { background: var(--blue);  color: #fff; }
        .btn-blue:hover  { background: #1d4ed8; color: #fff; }
        .btn-green { background: var(--green); color: #fff; }
        .btn-green:hover { background: #047857; color: #fff; }
        .btn-amber { background: var(--amber); color: #fff; }
        .btn-amber:hover { background: #b45309; color: #fff; }

        .main {
            flex: 1; padding: 20px 22px 60px;
            display: flex; flex-direction: column; gap: 14px;
            max-width: 860px;
        }

        .sec {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--r); box-shadow: var(--sh); overflow: hidden;
        }
        .sec-hd {
            display: flex; align-items: center; gap: 9px;
            padding: 11px 18px; background: var(--s2); border-bottom: 1px solid var(--bsoft);
        }
        .sec-ico {
            width: 26px; height: 26px; border-radius: var(--rs);
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; flex-shrink: 0;
        }
        .si-vi { background: var(--violet-bg); color: var(--violet); }
        .si-bl { background: var(--blue-bg);   color: var(--blue);   }
        .si-am { background: var(--amber-bg);  color: var(--amber);  }
        .si-gr { background: var(--green-bg);  color: var(--green);  }
        .sec-title { font-size: .875rem; font-weight: 700; color: var(--t1); }
        .sec-title-note { margin-left: auto; font-size: .75rem; color: var(--amber); font-weight: 600; }
        .sec-body  { padding: 18px; }

        .pos-alert {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 12px 16px; border-radius: var(--rs);
            font-size: .875rem; font-weight: 500;
        }
        .pos-alert.danger  { background: var(--red-bg);   border: 1px solid var(--red-b);   border-left: 3px solid var(--red);   color: #991b1b; }
        .pos-alert.info    { background: var(--blue-bg);  border: 1px solid var(--blue-b);  border-left: 3px solid var(--blue);  color: #1e40af; }

        .field-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .field-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
        @media (max-width: 700px) { .field-grid-2, .field-grid-3 { grid-template-columns: 1fr; } }

        .lbl { display: block; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--t3); margin-bottom: 5px; }
        .lbl .req { color: var(--red); margin-left: 2px; }

        .fc {
            width: 100%; height: 36px; padding: 0 10px;
            border: 1.5px solid var(--border); border-radius: var(--rs);
            font-family: inherit; font-size: .875rem; color: var(--t2);
            background: var(--surface); outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        .fc:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
        .fc.editable { background: var(--amber-bg); border-color: var(--amber-b); }
        .fc.editable:focus { border-color: var(--amber); box-shadow: 0 0 0 3px rgba(217,119,6,.1); }
        .fc[readonly] { background: var(--s2); color: var(--t3); cursor: default; }

        .fetch-row { display: flex; gap: 10px; align-items: flex-end; }

        .item-cards { display: flex; flex-direction: column; gap: 8px; }
        .item-card {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 14px; border: 2px solid var(--border);
            border-radius: var(--rs); cursor: pointer; transition: all .2s;
        }
        .item-card:hover   { border-color: var(--blue-b); background: var(--blue-bg); }
        .item-card.selected{ border-color: var(--green);  background: var(--green-bg); }
        .item-card input[type=radio] { accent-color: var(--green); flex-shrink: 0; }
        .item-card-info { flex: 1; display: flex; flex-wrap: wrap; gap: 6px 16px; }
        .ic-tag { display: inline-flex; align-items: center; gap: 4px; font-size: .8rem; color: var(--t2); }
        .ic-tag strong { color: var(--t1); font-weight: 700; }

        /* Large purity input */
        .purity-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; align-items: end; }
        @media (max-width: 600px) { .purity-row { grid-template-columns: 1fr; } }

        .form-actions {
            display: flex; justify-content: flex-end;
            padding: 14px 18px; background: var(--s2); border-top: 1px solid var(--border);
        }

        /* ── Tunch report preview (untouched layout) ── */
        .tunch-preview {
            width: auto; max-width: 700px; padding: 0 30px;
            background: white;
            border: 1px solid var(--border); border-radius: var(--r); box-shadow: var(--sh); overflow: hidden;
        }
        .tunch-container {
            padding: 0 20px; background: white; position: relative;
        }
        .tunch-container::before {
            content: '';
            position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-25deg);
            width: 200px; height: 200px;
            background-image: url('Varifiedstamp.png');
            background-size: contain; background-repeat: no-repeat; background-position: center;
            opacity: 0.2; z-index: 1; pointer-events: none;
        }
        .tunch-container > * { position: relative; z-index: 2; }
        .report-header { display: flex; justify-content: space-between; align-items: flex-start; margin: 0; padding: 0; }
        .customer-info { flex: 1; padding: 0; }
        .customer-info-line { margin: 0; padding: 0; font-size: 15px; line-height: 1.8; display: flex; color: #000; font-weight: 600; }
        .customer-info-line.customer-name { font-size: 22px; font-weight: bold; margin-bottom: 3px; }
        .info-label  { display: inline-block; min-width: 120px; text-align: left; font-weight: 600; }
        .info-colon  { display: inline-block; width: 15px; text-align: center; }
        .info-value  { flex: 1; font-weight: 600; }
        .qr-section  { width: 100px; text-align: center; padding: 5px; margin-left: 15px; flex-shrink: 0; }
        #qrcode      { margin-bottom: 5px; }
        .qr-date     { font-size: 12px; color: #000; font-weight: 700; line-height: 1.4; margin-top: 5px; white-space: nowrap; }
        .weight-conversion { font-size: 13px; color: #333; font-weight: 600; margin-left: 10px; }
        .dotted-line { border-top: 3px dotted #000; margin: 5px 0; padding: 0; }
        .quality-info {
            font-size: 24px; font-weight: bold; margin: 5px 0;
            line-height: 1.5; display: flex; justify-content: space-around;
            flex-wrap: wrap; gap: 20px; color: #000;
        }
        .quality-info span { white-space: nowrap; }

        .report-actions {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            padding: 16px 18px; background: var(--s2);
            border: 1px solid var(--border); border-radius: var(--r); box-shadow: var(--sh); flex-wrap: wrap;
        }

        @media (max-width: 991.98px) {
            .page-shell { margin-left: 0; }
            .top-bar    { top: 52px; }
            .main       { padding: 14px 14px 50px; max-width: 100%; }
        }
        @media print {
            .page-shell { margin-left: 0; }
            .top-bar, .main > *:not(.tunch-preview) { display: none !important; }
            .tunch-preview { border: none; box-shadow: none; }
        }
    </style>
</head>
<body>

<div class="page-shell">

    <header class="top-bar">
        <div class="tb-ico"><i class="fas fa-flask"></i></div>
        <div>
            <div class="tb-title">Tunch Report</div>
            <div class="tb-sub">Generate purity testing certificate from order</div>
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

    <div class="main">

    <?php if (!$report_created): ?>

        <!-- Step 1: Fetch order -->
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

        <!-- Step 2: Select bill item -->
        <div class="sec">
            <div class="sec-hd">
                <span class="sec-ico si-am"><i class="fas fa-list-check"></i></span>
                <span class="sec-title">Step 2 — Select Bill Item</span>
            </div>
            <div class="sec-body">
                <div class="item-cards">
                    <?php foreach ($bill_items as $index => $bill_item): ?>
                    <div class="item-card <?= $index === 0 ? 'selected' : '' ?>"
                         onclick="selectBillItem(<?= $index ?>)">
                        <input type="radio" name="selected_bill_item"
                               id="bill_item_<?= $index ?>" value="<?= $index ?>"
                               <?= $index === 0 ? 'checked' : '' ?>>
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

        <!-- Step 3: Fill in details -->
        <form method="POST" id="reportForm">
            <input type="hidden" name="order_id"     value="<?= htmlspecialchars($order_data['order_id']) ?>">
            <input type="hidden" name="bill_item_id" id="bill_item_id" value="<?= $bill_items[0]['bill_item_id'] ?>">
            <input type="hidden" name="service_name" id="service_name" value="<?= htmlspecialchars($bill_items[0]['service_name']) ?>">

            <!-- Customer + Sample -->
            <div class="sec">
                <div class="sec-hd">
                    <span class="sec-ico si-bl"><i class="fas fa-user"></i></span>
                    <span class="sec-title">Step 3 — Customer & Sample Details</span>
                    <span class="sec-title-note">
                        <i class="fas fa-pen" style="font-size:.6rem;"></i> Highlighted fields are editable
                    </span>
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
                                   value="<?= htmlspecialchars($bill_items[0]['item_name']) ?>">
                        </div>
                        <div>
                            <label class="lbl">Sample Weight (gm) <span class="req">*</span></label>
                            <input type="number" name="weight" id="weight" class="fc editable"
                                   step="0.001" required value="<?= $bill_items[0]['weight'] ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Testing results -->
            <div class="sec">
                <div class="sec-hd">
                    <span class="sec-ico si-gr"><i class="fas fa-vial"></i></span>
                    <span class="sec-title">Testing Results</span>
                </div>
                <div class="sec-body">
                    <div class="field-grid-2">
                        <div>
                            <label class="lbl"><span id="purityLabel">Gold Purity</span> (%) <span class="req">*</span></label>
                            <input type="number" name="gold_purity" id="gold_purity" class="fc"
                                   step="0.01" placeholder="e.g. 87.5" required
                                   oninput="calculateKarat()">
                        </div>
                        <div>
                            <label class="lbl">Karat (Auto-calculated) <span class="req">*</span></label>
                            <input type="text" name="karat" id="karat" class="fc"
                                   placeholder="Auto-calculated" readonly required
                                   value="<?= htmlspecialchars($bill_items[0]['karat'] ?: '') ?>"
                                   style="font-family:'DM Mono',monospace;font-weight:700;">
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" name="submit_report" class="btn-pos btn-green" style="height:38px;font-size:.9rem;">
                        <i class="fas fa-file-circle-check" style="font-size:.7rem;"></i> Generate Tunch Report
                    </button>
                </div>
            </div>

        </form>

        <script>
            const billItems = <?= json_encode($bill_items) ?>;

            function detectMetalType(itemName) {
                const l = itemName.toLowerCase();
                return l.includes('silver') || l.includes('রুপা') || l.includes('rupa');
            }

            function updatePurityLabel() {
                const itemName = document.getElementById('item_name').value;
                document.getElementById('purityLabel').textContent = detectMetalType(itemName) ? 'Silver Purity' : 'Gold Purity';
            }

            function calculateKarat() {
                const purity = parseFloat(document.getElementById('gold_purity').value);
                const kField = document.getElementById('karat');
                kField.value = (purity && purity > 0) ? ((24 / 100) * purity).toFixed(2) : '';
            }

            function selectBillItem(index) {
                document.getElementById('bill_item_' + index).checked  = true;
                document.getElementById('bill_item_id').value           = billItems[index].bill_item_id;
                document.getElementById('item_name').value              = billItems[index].item_name;
                document.getElementById('weight').value                 = billItems[index].weight;
                document.getElementById('service_name').value           = billItems[index].service_name;
                document.getElementById('gold_purity').value            = '';
                document.getElementById('karat').value                  = '';
                updatePurityLabel();

                document.querySelectorAll('.item-card').forEach(c => c.classList.remove('selected'));
                event.currentTarget.classList.add('selected');
            }

            document.querySelector('.item-card').classList.add('selected');
            updatePurityLabel();
            document.getElementById('item_name').addEventListener('input', updatePurityLabel);
        </script>

        <?php endif; ?>

    <?php endif; ?>

    <?php if ($report_created && $report_data): ?>
        <?php
            $itemNameLower = strtolower($report_data['item_name']);
            $isSilver      = strpos($itemNameLower, 'silver') !== false ||
                             strpos($itemNameLower, 'রুপা') !== false ||
                             strpos($itemNameLower, 'rupa') !== false;
            $purityLabel   = $isSilver ? 'Silver Purity' : 'Gold Purity';
        ?>

        <!-- Tunch Report Preview -->
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
                    <span><?= $purityLabel ?> : <?= htmlspecialchars($report_data['gold_purity'] ?: 'N/A') ?>%</span>
                    <span>Karat : <?= htmlspecialchars($report_data['karat'] ?: 'N/A') ?>K</span>
                </div>
                <div class="dotted-line"></div>
            </div>
        </div>

        <!-- Action bar -->
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

        <script>
            function convertGramToVoriAna(gram) {
                if (!gram || gram <= 0) return '0 V 0 A 0 R 0 P';
                const totalPoints = Math.round((gram / 11.664) * 16 * 6 * 10);
                const bhori = Math.floor(totalPoints / 960);
                const remainingPoints = totalPoints % 960;
                const ana = Math.floor(remainingPoints / 60);
                const remainingAfterAna = remainingPoints % 60;
                const roti = Math.floor(remainingAfterAna / 10);
                const point = remainingAfterAna % 10;
                return `V:${bhori} A:${ana} R:${roti} P:${point}`;
            }

            const weight = <?= floatval($report_data['weight']) ?>;
            document.getElementById('weightConversion').textContent = '(' + convertGramToVoriAna(weight) + ')';

            const qrLink = "<?= "https://www.app.rajaiswari.com/report_varification.php?id=" . $report_id ?>";
            new QRCode(document.getElementById("qrcode"), { text: qrLink, width: 90, height: 90 });

            async function copyFullReportImage() {
                const button = event.target;
                const orig   = button.innerHTML;
                button.disabled  = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Capturing...';
                try {
                    const canvas = await html2canvas(document.getElementById("reportPreview"), {
                        scale: 2, useCORS: true, backgroundColor: '#ffffff', logging: false
                    });
                    canvas.toBlob(async (blob) => {
                        try {
                            await navigator.clipboard.write([new ClipboardItem({ "image/png": blob })]);
                            button.disabled  = false;
                            button.innerHTML = orig;
                            alert("✅ Report copied! Press Ctrl+V in MS Word to paste.");
                        } catch (err) {
                            button.disabled  = false;
                            button.innerHTML = orig;
                            alert("❌ Clipboard error: " + err.message);
                        }
                    });
                } catch (error) {
                    button.disabled  = false;
                    button.innerHTML = orig;
                    alert("❌ Error capturing report: " + error.message);
                }
            }
        </script>

    <?php endif; ?>

    </div><!-- /main -->
</div><!-- /page-shell -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>