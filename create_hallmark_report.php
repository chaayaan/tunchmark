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
$upload_error   = null;

// Fetch active items for dropdown
$itemsQuery  = "SELECT id, name FROM items WHERE is_active = 1 ORDER BY name ASC";
$itemsResult = mysqli_query($conn, $itemsQuery);
if ($itemsResult) {
    while ($row = mysqli_fetch_assoc($itemsResult)) $items[] = $row;
}

// Fetch active services for dropdown (only Hallmark services)
$servicesQuery  = "SELECT id, name FROM services WHERE is_active = 1 AND name LIKE '%hallmark%' ORDER BY name ASC";
$servicesResult = mysqli_query($conn, $servicesQuery);
if ($servicesResult) {
    while ($row = mysqli_fetch_assoc($servicesResult)) $services[] = $row;
}

// Handle order fetch
if (isset($_POST['fetch_order'])) {
    $order_id = intval($_POST['order_id']);
    $stmt = mysqli_prepare($conn, "SELECT order_id, customer_name, manufacturer, customer_address FROM orders WHERE order_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    mysqli_stmt_execute($stmt);
    $result     = mysqli_stmt_get_result($stmt);
    $order_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$order_data) {
        $error = "Order not found!";
    } else {
        $billItemsQuery = "SELECT bi.bill_item_id, bi.weight, bi.karat, bi.quantity, i.name as item_name, s.name as service_name
                          FROM bill_items bi
                          JOIN items i ON bi.item_id = i.id
                          JOIN services s ON bi.service_id = s.id
                          WHERE bi.order_id = ? AND s.name LIKE '%hallmark%'";
        $stmt = mysqli_prepare($conn, $billItemsQuery);
        mysqli_stmt_bind_param($stmt, "i", $order_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) $bill_items[] = $row;
        mysqli_stmt_close($stmt);

        if (empty($bill_items)) $error = "No hallmark service bill items found for this order!";
    }
}

// Handle report submission
if (isset($_POST['submit_report']) && !isset($_GET['report_id'])) {
    $order_id         = intval($_POST['order_id']);
    $customer_name    = trim($_POST['customer_name']);
    $customer_address = trim($_POST['customer_address']);
    $manufacturer     = trim($_POST['manufacturer']);
    $bill_item_id     = intval($_POST['bill_item_id']);
    $item_name        = trim($_POST['item_name']);
    $service_name     = trim($_POST['service_name']);
    $weight           = floatval($_POST['weight']);
    $quantity         = intval($_POST['quantity']);
    $hallmark         = trim($_POST['hallmark']);

    // ── Validate uploaded image ────────────────────────────────────────────
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $max_size      = 200 * 1024; // 200 KB
    $photo_info    = null;

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['photo'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $upload_error = "Photo upload error (code {$file['error']}).";
        } elseif ($file['size'] > $max_size) {
            $upload_error = "Photo exceeds 200 KB limit.";
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($file['tmp_name']);
            if (!in_array($mime, $allowed_types, true)) {
                $upload_error = "Photo must be JPG, PNG, or WEBP.";
            } else {
                $photo_info = ['tmp' => $file['tmp_name'], 'mime' => $mime];
            }
        }
    }

    if ($upload_error) {
        // Re-populate order data so form shows again with error
        $stmt = mysqli_prepare($conn, "SELECT order_id, customer_name, manufacturer, customer_address FROM orders WHERE order_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $order_id);
        mysqli_stmt_execute($stmt);
        $order_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        $stmt = mysqli_prepare($conn,
            "SELECT bi.bill_item_id, bi.weight, bi.karat, bi.quantity, i.name as item_name, s.name as service_name
             FROM bill_items bi JOIN items i ON bi.item_id=i.id JOIN services s ON bi.service_id=s.id
             WHERE bi.order_id=? AND s.name LIKE '%hallmark%'");
        mysqli_stmt_bind_param($stmt, "i", $order_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) $bill_items[] = $row;
        mysqli_stmt_close($stmt);
    } else {
        // ── Insert report ──────────────────────────────────────────────────
        $stmt = mysqli_prepare($conn, "INSERT INTO customer_reports (order_id, customer_name, item_name, service_name, weight, quantity, hallmark, address, manufacturer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isssdisss", $order_id, $customer_name, $item_name, $service_name, $weight, $quantity, $hallmark, $customer_address, $manufacturer);
        mysqli_stmt_execute($stmt);
        $report_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        // ── Save image ─────────────────────────────────────────────────────
        if ($photo_info !== null) {
            $upload_dir = __DIR__ . '/uploads/hallmark_reports/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $ext_map  = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $ext      = $ext_map[$photo_info['mime']] ?? 'jpg';
            $filename = "hallmark_{$report_id}.{$ext}";
            $dest     = $upload_dir . $filename;
            $rel_path = "uploads/hallmark_reports/{$filename}";

            if (move_uploaded_file($photo_info['tmp'], $dest)) {
                $imgStmt = mysqli_prepare($conn,
                    "INSERT INTO report_images (report_id, img_type, img_number, img_path)
                     VALUES (?, 'hallmark', 1, ?)");
                mysqli_stmt_bind_param($imgStmt, 'is', $report_id, $rel_path);
                mysqli_stmt_execute($imgStmt);
                mysqli_stmt_close($imgStmt);
            }
        }

        header("Location: create_hallmark_report.php?report_id=" . $report_id);
        exit;
    }
}

// Fetch existing report if report_id in URL
if (isset($_GET['report_id'])) {
    $report_id = intval($_GET['report_id']);
    $stmt = mysqli_prepare($conn, "SELECT cr.* FROM customer_reports cr WHERE cr.id = ?");
    mysqli_stmt_bind_param($stmt, "i", $report_id);
    mysqli_stmt_execute($stmt);
    $result      = mysqli_stmt_get_result($stmt);
    $report_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($report_data) {
        $report_created = true;

        // Load report image
        $imgStmt = mysqli_prepare($conn,
            "SELECT img_path FROM report_images WHERE report_id=? AND img_type='hallmark' ORDER BY img_number ASC LIMIT 1");
        mysqli_stmt_bind_param($imgStmt, 'i', $report_id);
        mysqli_stmt_execute($imgStmt);
        $imgRow        = mysqli_fetch_assoc(mysqli_stmt_get_result($imgStmt));
        $report_image  = $imgRow ? $imgRow['img_path'] : null;
        mysqli_stmt_close($imgStmt);
    }
}

include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hallmark Report — Rajaiswari</title>
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

        .page-shell { margin-left: 200px; min-height: 100vh; display: flex; flex-direction: column; }

        .top-bar {
            position: sticky; top: 0; z-index: 200;
            height: 54px; background: var(--surface);
            border-bottom: 1px solid var(--border); box-shadow: var(--sh);
            display: flex; align-items: center; padding: 0 22px; gap: 12px; flex-shrink: 0;
        }
        .tb-ico { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; background: var(--cyan-bg); color: var(--cyan); flex-shrink: 0; }
        .tb-title { font-size: 1.0625rem; font-weight: 700; color: var(--t1); }
        .tb-sub   { font-size: .78rem; color: var(--t4); }
        .tb-right { margin-left: auto; display: flex; gap: 7px; align-items: center; }

        .btn-pos { display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 14px; border: none; border-radius: var(--rs); font-family: inherit; font-size: .8125rem; font-weight: 600; cursor: pointer; transition: all .15s; text-decoration: none; white-space: nowrap; }
        .btn-ghost { background: var(--surface); color: var(--t2); border: 1.5px solid var(--border); }
        .btn-ghost:hover { background: var(--s2); border-color: #9ca3af; color: var(--t1); }
        .btn-blue  { background: var(--blue);  color: #fff; } .btn-blue:hover  { background: #1d4ed8; color: #fff; }
        .btn-green { background: var(--green); color: #fff; } .btn-green:hover { background: #047857; color: #fff; }
        .btn-amber { background: var(--amber); color: #fff; } .btn-amber:hover { background: #b45309; color: #fff; }

        .main { flex: 1; padding: 20px 22px 60px; display: flex; flex-direction: column; gap: 14px; max-width: 860px; }

        .sec { background: var(--surface); border: 1px solid var(--border); border-radius: var(--r); box-shadow: var(--sh); overflow: hidden; }
        .sec-hd { display: flex; align-items: center; gap: 9px; padding: 11px 18px; background: var(--s2); border-bottom: 1px solid var(--bsoft); }
        .sec-ico { width: 26px; height: 26px; border-radius: var(--rs); display: flex; align-items: center; justify-content: center; font-size: 11px; flex-shrink: 0; }
        .si-cy { background: var(--cyan-bg);   color: var(--cyan);   }
        .si-bl { background: var(--blue-bg);   color: var(--blue);   }
        .si-am { background: var(--amber-bg);  color: var(--amber);  }
        .si-gr { background: var(--green-bg);  color: var(--green);  }
        .sec-title { font-size: .875rem; font-weight: 700; color: var(--t1); }
        .sec-body  { padding: 18px; }

        .pos-alert { display: flex; align-items: flex-start; gap: 10px; padding: 12px 16px; border-radius: var(--rs); font-size: .875rem; font-weight: 500; }
        .pos-alert.danger  { background: var(--red-bg);   border: 1px solid var(--red-b);   border-left: 3px solid var(--red);   color: #991b1b; }
        .pos-alert.success { background: var(--green-bg); border: 1px solid var(--green-b); border-left: 3px solid var(--green); color: #065f46; }
        .pos-alert.info    { background: var(--blue-bg);  border: 1px solid var(--blue-b);  border-left: 3px solid var(--blue);  color: #1e40af; }

        .field-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .field-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
        @media (max-width: 700px) { .field-grid-2, .field-grid-3 { grid-template-columns: 1fr; } }

        .lbl { display: block; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--t3); margin-bottom: 5px; }
        .lbl .req { color: var(--red); margin-left: 2px; }

        .fc { width: 100%; height: 36px; padding: 0 10px; border: 1.5px solid var(--border); border-radius: var(--rs); font-family: inherit; font-size: .875rem; color: var(--t2); background: var(--surface); outline: none; transition: border-color .15s, box-shadow .15s; }
        .fc:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
        .fc.editable { background: var(--amber-bg); border-color: var(--amber-b); }
        .fc.editable:focus { border-color: var(--amber); box-shadow: 0 0 0 3px rgba(217,119,6,.1); }
        .fc[readonly] { background: var(--s2); color: var(--t3); cursor: default; }

        .fetch-row { display: flex; gap: 10px; align-items: flex-end; }
        .fetch-row .fc { flex: 1; max-width: 220px; font-family: 'DM Mono', monospace; }

        .item-cards { display: flex; flex-direction: column; gap: 8px; }
        .item-card { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border: 2px solid var(--border); border-radius: var(--rs); cursor: pointer; transition: all .2s; background: var(--surface); }
        .item-card:hover { border-color: var(--blue-b); background: var(--blue-bg); }
        .item-card.selected { border-color: var(--green); background: var(--green-bg); }
        .item-card input[type=radio] { accent-color: var(--green); flex-shrink: 0; }
        .item-card-info { flex: 1; display: flex; flex-wrap: wrap; gap: 6px 16px; }
        .ic-tag { display: inline-flex; align-items: center; gap: 4px; font-size: .8rem; color: var(--t2); }
        .ic-tag strong { color: var(--t1); font-weight: 700; }

        .hallmark-input { width: 100%; height: 52px; padding: 0 14px; border: 2px solid var(--border); border-radius: var(--rs); font-family: 'DM Mono', monospace; font-size: 1.4rem; font-weight: 800; color: var(--t1); letter-spacing: .06em; outline: none; transition: border-color .15s, box-shadow .15s; text-align: center; }
        .hallmark-input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }

        /* Photo upload */
        .photo-upload-box { display: flex; flex-direction: column; gap: 6px; }
        .photo-preview { width: 100%; height: 110px; object-fit: cover; border-radius: var(--rs); border: 1px solid var(--border); display: none; margin-top: 6px; }

        .form-actions { display: flex; justify-content: flex-end; padding: 14px 18px; background: var(--s2); border-top: 1px solid var(--border); }

        /* ── Hallmark Report Preview ─── */
        .hallmark-preview { width: auto; max-width: 750px; background: white; padding: 0; border: 1px solid var(--border); border-radius: var(--r); box-shadow: var(--sh); overflow: hidden; }

        #reportPreview { background: white; padding: 0; position: relative; }
        #reportPreview::before { content: ''; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-25deg); width: 250px; height: 250px; background-image: url('Varifiedstamp.png'); background-size: contain; background-repeat: no-repeat; background-position: center; opacity: 0.25; z-index: 1; pointer-events: none; }
        #reportPreview > * { position: relative; z-index: 2; }

        .report-header-title { text-align: center; color: #3eb1e3; font-size: 32px; font-weight: bold; margin: 0; padding: 2px 0; letter-spacing: 2px; line-height: 1; }
        .hallmark-dotted { border-top: 2.5px dotted #000; margin: 0; }

        .hallmark-info-section { display: flex; justify-content: space-between; align-items: flex-start; padding: 3px 8px 3px 8px; margin: 0; }
        .hallmark-left-info { flex: 1; padding: 0; line-height: 1.2; }

        .customer-info-line { margin: 0; padding: 0; font-size: 15px; line-height: 1.4; display: block; color: #000; font-weight: 600; }
        .customer-info-line.customer-name { font-size: 20px; font-weight: bold; margin-bottom: 2px; }
        .info-label { display: inline-block; min-width: 110px; text-align: left; font-weight: 600; }
        .info-colon { display: inline; margin: 0 3px; }
        .info-value { display: inline; font-weight: 600; }

        .qr-section { width: 100px; text-align: center; padding: 0; margin-left: 8px; flex-shrink: 0; }
        #qrcode { margin: 0; line-height: 0; }
        #qrcode img { display: block; margin: 0 auto; }
        .qr-date { font-size: 10px; color: #000; font-weight: 700; line-height: 1.2; margin: 1px 0 0 0; padding: 0; }

        .main-box { border: 2.5px solid #000; display: flex; margin: 0 8px; height: 100px; }
        .checkbox-section { flex: 1; padding: 8px 12px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px 15px; align-content: center; }
        .checkbox-item { display: flex; align-items: center; font-size: 12px; line-height: 1; font-weight: 600; color: #000; }
        .checkbox-box { width: 14px; height: 14px; border: 2px solid #000; margin-right: 4px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; line-height: 1; }

        .hallmark-section { width: 240px; border-left: 2.5px solid #000; display: flex; flex-direction: column; }
        .hallmark-value-container { flex: 1; display: flex; align-items: center; justify-content: center; border-bottom: 2.5px solid #000; padding: 8px 12px; overflow: hidden; }
        .hallmark-value { font-size: 40px; font-weight: bold; line-height: 1; color: #000; text-align: center; word-wrap: break-word; word-break: break-word; max-width: 100%; font-family: 'Times New Roman', Times, serif; }
        .hallmark-label { font-size: 15px; font-weight: 700; text-align: center; padding: 4px; color: #000; line-height: 1; font-family: 'Times New Roman', Times, serif; }

        .weight-conversion { font-size: 13px; color: #000; font-weight: 600; margin-left: 0; }

        /* Report photo at bottom */
        .report-photo-bottom { padding: 6px 8px 4px; }
        .report-photo-bottom img { width: 160px; height: 110px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; display: block; }

        .report-actions { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 16px 18px; background: var(--s2); border: 1px solid var(--border); border-radius: var(--r); box-shadow: var(--sh); flex-wrap: wrap; }

        @media (max-width: 991.98px) {
            .page-shell { margin-left: 0; }
            .top-bar    { top: 52px; }
            .main       { padding: 14px 14px 50px; max-width: 100%; }
        }
        @media print {
            .page-shell { margin-left: 0; }
            .top-bar, .main > *:not(.hallmark-preview) { display: none !important; }
            .hallmark-preview { border: none; box-shadow: none; }
        }
    </style>
</head>
<body>

<div class="page-shell">

    <header class="top-bar">
        <div class="tb-ico"><i class="fas fa-stamp"></i></div>
        <div>
            <div class="tb-title">Hallmark Report</div>
            <div class="tb-sub">Generate hallmarking certificate from order</div>
        </div>
        <div class="tb-right">
            <?php if ($report_created): ?>
            <a href="create_hallmark_report.php" class="btn-pos btn-ghost">
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
                <span class="sec-ico si-cy"><i class="fas fa-magnifying-glass"></i></span>
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
                            <input type="number" name="order_id" class="fc" style="max-width:200px;font-family:'DM Mono',monospace;"
                                   required placeholder="e.g. 1042"
                                   value="<?= isset($_POST['order_id']) ? intval($_POST['order_id']) : '' ?>">
                        </div>
                        <button type="submit" name="fetch_order" class="btn-pos btn-blue" style="margin-bottom:0;">
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
                            <span class="ic-tag">Qty: <strong><?= htmlspecialchars($bill_item['quantity'] ?: '1') ?></strong></span>
                            <span class="ic-tag">Karat: <strong><?= htmlspecialchars($bill_item['karat'] ?: 'N/A') ?></strong></span>
                            <span class="ic-tag" style="color:var(--blue);"><?= htmlspecialchars($bill_item['service_name']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Step 3: Fill details & generate -->
        <form method="POST" id="reportForm" enctype="multipart/form-data">
            <input type="hidden" name="order_id"     value="<?= htmlspecialchars($order_data['order_id']) ?>">
            <input type="hidden" name="bill_item_id" id="bill_item_id" value="<?= $bill_items[0]['bill_item_id'] ?>">
            <input type="hidden" name="item_name"    id="item_name"    value="<?= htmlspecialchars($bill_items[0]['item_name']) ?>">
            <input type="hidden" name="service_name" id="service_name" value="<?= htmlspecialchars($bill_items[0]['service_name']) ?>">

            <!-- Customer info -->
            <div class="sec">
                <div class="sec-hd">
                    <span class="sec-ico si-bl"><i class="fas fa-user"></i></span>
                    <span class="sec-title">Step 3 — Customer Information</span>
                    <span style="margin-left:auto;font-size:.75rem;color:var(--amber);font-weight:600;">
                        <i class="fas fa-pen" style="font-size:.6rem;"></i> Highlighted fields are editable
                    </span>
                </div>
                <div class="sec-body">
                    <div class="field-grid-3">
                        <div>
                            <label class="lbl">Customer Name <span class="req">*</span></label>
                            <input type="text" name="customer_name" id="customer_name" class="fc editable" required
                                   value="<?= htmlspecialchars($order_data['customer_name']) ?>">
                        </div>
                        <div>
                            <label class="lbl">Manufacturer</label>
                            <input type="text" name="manufacturer" id="manufacturer" class="fc editable"
                                   value="<?= htmlspecialchars($order_data['manufacturer'] ?: '') ?>">
                        </div>
                        <div>
                            <label class="lbl">Address</label>
                            <input type="text" name="customer_address" id="customer_address" class="fc editable"
                                   value="<?= htmlspecialchars($order_data['customer_address'] ?: '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sample details -->
            <div class="sec">
                <div class="sec-hd">
                    <span class="sec-ico si-am"><i class="fas fa-gem"></i></span>
                    <span class="sec-title">Sample Details</span>
                </div>
                <div class="sec-body" style="display:flex;flex-direction:column;gap:18px;">
                    <div class="field-grid-3">
                        <div>
                            <label class="lbl">Item Name</label>
                            <input type="text" id="item_name_display" class="fc" readonly
                                   value="<?= htmlspecialchars($bill_items[0]['item_name']) ?>">
                            <div style="font-size:.72rem;color:var(--t4);margin-top:4px;">Auto-marked in checkbox</div>
                        </div>
                        <div>
                            <label class="lbl">Weight (gm) <span class="req">*</span></label>
                            <input type="number" name="weight" id="weight" class="fc editable"
                                   step="0.001" required value="<?= $bill_items[0]['weight'] ?>">
                        </div>
                        <div>
                            <label class="lbl">Quantity <span class="req">*</span></label>
                            <input type="number" name="quantity" id="quantity" class="fc editable"
                                   required value="<?= htmlspecialchars($bill_items[0]['quantity'] ?: '1') ?>">
                        </div>
                    </div>

                    <!-- Hallmark value -->
                    <div>
                        <label class="lbl">Hallmark Value <span class="req">*</span></label>
                        <input type="text" name="hallmark" id="hallmark" class="hallmark-input"
                               placeholder="e.g. 21K RJ" required>
                    </div>

                    <!-- Photo upload -->
                    <div>
                        <?php if ($upload_error): ?>
                        <div class="pos-alert danger" style="margin-bottom:12px;">
                            <i class="fas fa-circle-xmark" style="font-size:.9rem;flex-shrink:0;margin-top:1px;"></i>
                            <?= htmlspecialchars($upload_error) ?>
                        </div>
                        <?php endif; ?>
                        <div class="photo-upload-box" style="max-width:320px;">
                            <label class="lbl">Sample Photo <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--t4);">Optional · max 200 KB</span></label>
                            <input type="file" name="photo" id="photo" class="fc"
                                   accept=".jpg,.jpeg,.png,.webp"
                                   style="height:auto;padding:6px 10px;cursor:pointer;"
                                   onchange="previewPhoto(this)">
                            <img id="photo_preview" class="photo-preview" alt="Photo preview">
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="submit_report" class="btn-pos btn-green" style="height:38px;font-size:.9rem;">
                        <i class="fas fa-file-circle-check" style="font-size:.7rem;"></i> Generate Hallmark Report
                    </button>
                </div>
            </div>

        </form>

        <script>
            const billItems = <?= json_encode($bill_items) ?>;

            function selectBillItem(index) {
                document.getElementById('bill_item_' + index).checked = true;
                document.getElementById('bill_item_id').value         = billItems[index].bill_item_id;
                document.getElementById('item_name').value            = billItems[index].item_name;
                document.getElementById('item_name_display').value    = billItems[index].item_name;
                document.getElementById('weight').value               = billItems[index].weight;
                document.getElementById('quantity').value             = billItems[index].quantity || 1;
                document.getElementById('service_name').value         = billItems[index].service_name;
                document.querySelectorAll('.item-card').forEach(c => c.classList.remove('selected'));
                event.currentTarget.classList.add('selected');
            }

            function previewPhoto(input) {
                const preview = document.getElementById('photo_preview');
                if (input.files && input.files[0]) {
                    const file = input.files[0];
                    if (file.size > 200 * 1024) {
                        alert('Photo exceeds 200 KB limit. Please choose a smaller file.');
                        input.value = '';
                        preview.style.display = 'none';
                        return;
                    }
                    preview.src = URL.createObjectURL(file);
                    preview.style.display = 'block';
                } else {
                    preview.style.display = 'none';
                }
            }
        </script>

        <?php endif; ?>

    <?php endif; ?>

    <?php if ($report_created && $report_data): ?>

        <!-- Hallmark Report Preview -->
        <div class="hallmark-preview">
            <div id="reportPreview">
                <div class="report-header-title">HALLMARK REPORT</div>
                <div class="hallmark-dotted"></div>

                <div class="hallmark-info-section">
                    <div class="hallmark-left-info">
                        <div class="customer-info-line customer-name">
                            <span>Customer Name</span>
                            <span class="info-colon">:</span>
                            <span class="info-value"><?= htmlspecialchars($report_data['customer_name']) ?></span>
                        </div>
                        <div class="customer-info-line">
                            <span class="info-label">Bill No</span>
                            <span class="info-colon">:</span>
                            <span class="info-value"><?= htmlspecialchars($report_data['order_id']) ?></span>
                        </div>
                        <div class="customer-info-line">
                            <span class="info-label">Quantity</span>
                            <span class="info-colon">:</span>
                            <span class="info-value"><?= htmlspecialchars($report_data['quantity'] ?: '1') ?></span>
                        </div>
                        <div class="customer-info-line">
                            <span class="info-label">Weight</span>
                            <span class="info-colon">:</span>
                            <span class="info-value"><?= htmlspecialchars($report_data['weight']) ?> Gm<span class="weight-conversion" id="weightConversionHall"></span></span>
                        </div>
                        <div class="customer-info-line">
                            <span class="info-label">Made by</span>
                            <span class="info-colon">:</span>
                            <span class="info-value"><?= htmlspecialchars($report_data['manufacturer'] ?: 'N/A') ?></span>
                        </div>
                        <div class="customer-info-line">
                            <span class="info-label">Address</span>
                            <span class="info-colon">:</span>
                            <span class="info-value"><?= htmlspecialchars($report_data['address'] ?: 'N/A') ?></span>
                        </div>
                    </div>

                    <div class="qr-section">
                        <div id="qrcode"></div>
                        <div class="qr-date"><?= date('d-M-y', strtotime($report_data['created_at'])) ?> <?= date('g:i A', strtotime($report_data['created_at'])) ?></div>
                    </div>
                </div>

                <div class="main-box">
                    <div class="checkbox-section">
                        <div class="checkbox-item" data-item="anklet"><div class="checkbox-box"></div><span>Anklet</span></div>
                        <div class="checkbox-item" data-item="bangle"><div class="checkbox-box"></div><span>Bangle</span></div>
                        <div class="checkbox-item" data-item="bracelet"><div class="checkbox-box"></div><span>Bracelet</span></div>
                        <div class="checkbox-item" data-item="chain"><div class="checkbox-box"></div><span>Chain</span></div>
                        <div class="checkbox-item" data-item="ear chain"><div class="checkbox-box"></div><span>Ear Chain</span></div>
                        <div class="checkbox-item" data-item="earrings"><div class="checkbox-box"></div><span>Earrings</span></div>
                        <div class="checkbox-item" data-item="mantasha"><div class="checkbox-box"></div><span>Mantasha</span></div>
                        <div class="checkbox-item" data-item="necklace"><div class="checkbox-box"></div><span>Necklace</span></div>
                        <div class="checkbox-item" data-item="nose pin"><div class="checkbox-box"></div><span>Nose Pin</span></div>
                        <div class="checkbox-item" data-item="others"><div class="checkbox-box"></div><span>Others</span></div>
                        <div class="checkbox-item" data-item="pendant"><div class="checkbox-box"></div><span>Pendant</span></div>
                        <div class="checkbox-item" data-item="ring"><div class="checkbox-box"></div><span>Ring</span></div>
                        <div class="checkbox-item" data-item="shakha path"><div class="checkbox-box"></div><span>ShakhaPath</span></div>
                        <div class="checkbox-item" data-item="taira"><div class="checkbox-box"></div><span>Taira</span></div>
                        <div class="checkbox-item" data-item="tikli"><div class="checkbox-box"></div><span>Tikli</span></div>
                        <div class="checkbox-item" data-item="watch"><div class="checkbox-box"></div><span>Watch</span></div>
                    </div>

                    <div class="hallmark-section">
                        <div class="hallmark-value-container">
                            <div class="hallmark-value"><?= htmlspecialchars($report_data['hallmark']) ?></div>
                        </div>
                        <div class="hallmark-label">HallMark</div>
                    </div>
                </div>

                <?php if (!empty($report_image)): ?>
                <!-- Bottom split: 7/12 image | 5/12 authorized signature -->
                <div style="display:flex;gap:0;margin:6px 8px 4px;align-items:stretch;min-height:90px;">

                    <!-- Left 7/12 — photo -->
                    <div style="flex:0 0 58.333%;max-width:58.333%;display:flex;align-items:flex-end;padding-right:8px;">
                        <img src="<?= htmlspecialchars($report_image) ?>" alt="Sample photo"
                             style="width:160px;height:105px;object-fit:cover;border-radius:4px;border:1px solid #ddd;">
                    </div>

                    <!-- Right 5/12 — authorized signature bottom-center -->
                    <div style="flex:0 0 41.667%;max-width:41.667%;display:flex;flex-direction:column;justify-content:flex-end;">
                        <div style="text-align:center;font-size:11px;font-weight:bold;color:#000;padding-bottom:2px;">
                            Authorized Signature
                        </div>
                    </div>

                </div>
                <?php else: ?>
                <!-- No image — just authorized signature bottom-center -->
                <div style="text-align:center;font-size:11px;font-weight:bold;color:#000;margin:6px 8px 4px;padding-bottom:2px;">
                    Authorized Signature
                </div>
                <?php endif; ?>

            </div><!-- /reportPreview -->
        </div><!-- /hallmark-preview -->

        <!-- Action bar -->
        <div class="report-actions">
            <button onclick="copyFullReportImage()" class="btn-pos btn-amber" style="height:38px;font-size:.875rem;">
                <i class="fas fa-copy" style="font-size:.65rem;"></i> Copy Report with QR
            </button>
            <a href="create_hallmark_report.php" class="btn-pos btn-green" style="height:38px;font-size:.875rem;">
                <i class="fas fa-plus" style="font-size:.6rem;"></i> Create New Report
            </a>
        </div>

        <div class="pos-alert info">
            <i class="fas fa-circle-info" style="font-size:.9rem;flex-shrink:0;margin-top:1px;"></i>
            <span>Click <strong>Copy Report with QR</strong>, then open MS Word and press <strong>Ctrl+V</strong> to paste.</span>
        </div>

        <script>
            function convertGramToVoriAnaHall(gram) {
                if (!gram || gram <= 0) return '0 V 0 A 0 R 0 P';
                const totalPoints = Math.round((gram / 11.664) * 16 * 6 * 10);
                const bhori = Math.floor(totalPoints / 960);
                const remainingPoints = totalPoints % 960;
                const ana = Math.floor(remainingPoints / 60);
                const remainingAfterAna = remainingPoints % 60;
                const roti = Math.floor(remainingAfterAna / 10);
                const point = remainingAfterAna % 10;
                return `[V:${bhori} A:${ana} R:${roti} P:${point}]`;
            }

            const weightHall = <?= floatval($report_data['weight']) ?>;
            document.getElementById('weightConversionHall').textContent = ' ' + convertGramToVoriAnaHall(weightHall);

            const itemName = "<?= strtolower(htmlspecialchars($report_data['item_name'])) ?>";
            document.querySelectorAll('.checkbox-item').forEach(item => {
                const itemType = item.getAttribute('data-item').toLowerCase();
                const isMatch  = itemName === itemType ||
                                 itemName.includes(' ' + itemType + ' ') ||
                                 itemName.startsWith(itemType + ' ') ||
                                 itemName.endsWith(' ' + itemType);
                if (isMatch) {
                    const cb = item.querySelector('.checkbox-box');
                    cb.innerHTML = '✓';
                    cb.style.cssText = 'font-size:14px;font-weight:bold;display:flex;align-items:center;justify-content:center;line-height:1;';
                }
            });

            const baseUrl  = window.location.origin + window.location.pathname.replace('create_hallmark_report.php', '');
            const reportId = "<?= $report_id ?>" || '';
            new QRCode(document.getElementById("qrcode"), {
                text: `${baseUrl}report_varification.php?id=${reportId}`,
                width: 100, height: 100
            });

            async function copyFullReportImage() {
                const button       = event.target;
                const originalText = button.innerHTML;
                button.disabled    = true;
                button.innerHTML   = '<i class="fas fa-spinner fa-spin"></i> Capturing...';
                try {
                    const canvas = await html2canvas(document.getElementById("reportPreview"), {
                        scale: 2, useCORS: true, backgroundColor: '#ffffff', logging: false
                    });
                    canvas.toBlob(async (blob) => {
                        try {
                            await navigator.clipboard.write([new ClipboardItem({ "image/png": blob })]);
                            button.disabled  = false;
                            button.innerHTML = originalText;
                            alert("✅ Report copied! Press Ctrl+V in MS Word to paste.");
                        } catch (err) {
                            button.disabled  = false;
                            button.innerHTML = originalText;
                            alert("❌ Clipboard error: " + err.message);
                        }
                    });
                } catch (error) {
                    button.disabled  = false;
                    button.innerHTML = originalText;
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