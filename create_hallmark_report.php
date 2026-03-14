<?php
require 'auth.php';
require 'mydb.php';

$order_data     = null;
$bill_items     = [];
$items          = [];
$services       = [];
$report_created = false;
$report_id      = null;
$report_data    = null;
$upload_error   = null;

$itemsResult = mysqli_query($conn, "SELECT id, name FROM items WHERE is_active = 1 ORDER BY name ASC");
if ($itemsResult) while ($row = mysqli_fetch_assoc($itemsResult)) $items[] = $row;

$servicesResult = mysqli_query($conn, "SELECT id, name FROM services WHERE is_active = 1 AND name LIKE '%hallmark%' ORDER BY name ASC");
if ($servicesResult) while ($row = mysqli_fetch_assoc($servicesResult)) $services[] = $row;

// ── Fetch Order ────────────────────────────────────────────────────────────
if (isset($_POST['fetch_order'])) {
    $order_id = intval($_POST['order_id']);
    $stmt = mysqli_prepare($conn, "SELECT order_id, customer_name, manufacturer, customer_address FROM orders WHERE order_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    mysqli_stmt_execute($stmt);
    $order_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$order_data) {
        $error = "Order not found!";
    } else {
        $stmt = mysqli_prepare($conn,
            "SELECT bi.bill_item_id, bi.weight, bi.karat, bi.quantity, i.name as item_name, s.name as service_name
             FROM bill_items bi
             JOIN items i ON bi.item_id = i.id
             JOIN services s ON bi.service_id = s.id
             WHERE bi.order_id = ? AND s.name LIKE '%hallmark%'");
        mysqli_stmt_bind_param($stmt, "i", $order_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) $bill_items[] = $row;
        mysqli_stmt_close($stmt);
        if (empty($bill_items)) $error = "No hallmark service bill items found for this order!";
    }
}

// ── Submit Report ──────────────────────────────────────────────────────────
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

    // ── Validate image ─────────────────────────────────────────────────────
    $allowed_types = ['image/jpeg','image/jpg','image/png','image/webp'];
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
        // Re-populate order data so form shows again
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
        $stmt = mysqli_prepare($conn,
            "INSERT INTO customer_reports (order_id, customer_name, item_name, service_name, weight, quantity, hallmark, address, manufacturer)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isssdisss", $order_id, $customer_name, $item_name, $service_name, $weight, $quantity, $hallmark, $customer_address, $manufacturer);
        mysqli_stmt_execute($stmt);
        $report_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        // ── Save image ─────────────────────────────────────────────────────
        if ($photo_info !== null) {
            $upload_dir = __DIR__ . '/uploads/hallmark_reports/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $ext_map  = ['image/jpeg'=>'jpg','image/jpg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            $ext      = $ext_map[$photo_info['mime']] ?? 'jpg';
            $filename = "hallmark_{$report_id}.{$ext}";
            $dest     = $upload_dir . $filename;
            $rel_path = "uploads/hallmark_reports/{$filename}";

            if (move_uploaded_file($photo_info['tmp'], $dest)) {
                $imgStmt = mysqli_prepare($conn,
                    "INSERT INTO report_images (report_id, img_type, img_number, img_path) VALUES (?, 'hallmark', 1, ?)");
                mysqli_stmt_bind_param($imgStmt, 'is', $report_id, $rel_path);
                mysqli_stmt_execute($imgStmt);
                mysqli_stmt_close($imgStmt);
            }
        }

        header("Location: create_hallmark_report.php?report_id=" . $report_id);
        exit;
    }
}

// ── Load existing report ───────────────────────────────────────────────────
if (isset($_GET['report_id'])) {
    $report_id = intval($_GET['report_id']);
    $stmt = mysqli_prepare($conn, "SELECT * FROM customer_reports WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $report_id);
    mysqli_stmt_execute($stmt);
    $report_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($report_data) {
        $report_created = true;
        $imgStmt = mysqli_prepare($conn,
            "SELECT img_path FROM report_images WHERE report_id=? AND img_type='hallmark' ORDER BY img_number ASC LIMIT 1");
        mysqli_stmt_bind_param($imgStmt, 'i', $report_id);
        mysqli_stmt_execute($imgStmt);
        $imgRow       = mysqli_fetch_assoc(mysqli_stmt_get_result($imgStmt));
        $report_image = $imgRow ? $imgRow['img_path'] : null;
        mysqli_stmt_close($imgStmt);
    }
}

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
            --bg:#f1f3f6; --surface:#ffffff; --s2:#fafbfc;
            --border:#e4e7ec; --bsoft:#f0f1f3;
            --t1:#111827; --t2:#374151; --t3:#6b7280; --t4:#9ca3af;
            --blue:#2563eb;  --blue-bg:#eff6ff;  --blue-b:#bfdbfe;
            --green:#059669; --green-bg:#ecfdf5; --green-b:#a7f3d0;
            --amber:#d97706; --amber-bg:#fffbeb; --amber-b:#fde68a;
            --red:#dc2626;   --red-bg:#fef2f2;   --red-b:#fecaca;
            --cyan:#0891b2;  --cyan-bg:#ecfeff;  --cyan-b:#a5f3fc;
            --r:10px; --rs:6px;
            --sh:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);
        }
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'DM Sans',-apple-system,sans-serif;font-size:14px;background:var(--bg);color:var(--t1);-webkit-font-smoothing:antialiased;min-height:100vh;}
        .page-shell{margin-left:200px;min-height:100vh;display:flex;flex-direction:column;}
        .top-bar{position:sticky;top:0;z-index:200;height:54px;background:var(--surface);border-bottom:1px solid var(--border);box-shadow:var(--sh);display:flex;align-items:center;padding:0 22px;gap:12px;flex-shrink:0;}
        .tb-ico{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;background:var(--cyan-bg);color:var(--cyan);flex-shrink:0;}
        .tb-title{font-size:1.0625rem;font-weight:700;color:var(--t1);}
        .tb-sub{font-size:.78rem;color:var(--t4);}
        .tb-right{margin-left:auto;display:flex;gap:7px;align-items:center;}
        .btn-pos{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 14px;border:none;border-radius:var(--rs);font-family:inherit;font-size:.8125rem;font-weight:600;cursor:pointer;transition:all .15s;text-decoration:none;white-space:nowrap;}
        .btn-ghost{background:var(--surface);color:var(--t2);border:1.5px solid var(--border);}
        .btn-ghost:hover{background:var(--s2);border-color:#9ca3af;color:var(--t1);}
        .btn-blue{background:var(--blue);color:#fff;} .btn-blue:hover{background:#1d4ed8;color:#fff;}
        .btn-green{background:var(--green);color:#fff;} .btn-green:hover{background:#047857;color:#fff;}
        .btn-amber{background:var(--amber);color:#fff;} .btn-amber:hover{background:#b45309;color:#fff;}
        .main{flex:1;padding:20px 22px 60px;display:flex;flex-direction:column;gap:14px;max-width:860px;}
        .sec{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;}
        .sec-hd{display:flex;align-items:center;gap:9px;padding:11px 18px;background:var(--s2);border-bottom:1px solid var(--bsoft);}
        .sec-ico{width:26px;height:26px;border-radius:var(--rs);display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;}
        .si-cy{background:var(--cyan-bg);color:var(--cyan);}
        .si-bl{background:var(--blue-bg);color:var(--blue);}
        .si-am{background:var(--amber-bg);color:var(--amber);}
        .si-gr{background:var(--green-bg);color:var(--green);}
        .sec-title{font-size:.875rem;font-weight:700;color:var(--t1);}
        .sec-body{padding:18px;}
        .pos-alert{display:flex;align-items:flex-start;gap:10px;padding:12px 16px;border-radius:var(--rs);font-size:.875rem;font-weight:500;}
        .pos-alert.danger{background:var(--red-bg);border:1px solid var(--red-b);border-left:3px solid var(--red);color:#991b1b;}
        .pos-alert.info{background:var(--blue-bg);border:1px solid var(--blue-b);border-left:3px solid var(--blue);color:#1e40af;}
        .field-grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;}
        @media(max-width:700px){.field-grid-3{grid-template-columns:1fr;}}
        .lbl{display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t3);margin-bottom:5px;}
        .lbl .req{color:var(--red);margin-left:2px;}
        .fc{width:100%;height:36px;padding:0 10px;border:1.5px solid var(--border);border-radius:var(--rs);font-family:inherit;font-size:.875rem;color:var(--t2);background:var(--surface);outline:none;transition:border-color .15s,box-shadow .15s;}
        .fc:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
        .fc.editable{background:var(--amber-bg);border-color:var(--amber-b);}
        .fc.editable:focus{border-color:var(--amber);box-shadow:0 0 0 3px rgba(217,119,6,.1);}
        .fc[readonly]{background:var(--s2);color:var(--t3);cursor:default;}
        .fetch-row{display:flex;gap:10px;align-items:flex-end;}
        .fetch-row .fc{flex:1;max-width:220px;font-family:'DM Mono',monospace;}
        .item-cards{display:flex;flex-direction:column;gap:8px;}
        .item-card{display:flex;align-items:center;gap:12px;padding:10px 14px;border:2px solid var(--border);border-radius:var(--rs);cursor:pointer;transition:all .2s;background:var(--surface);}
        .item-card:hover{border-color:var(--blue-b);background:var(--blue-bg);}
        .item-card.selected{border-color:var(--green);background:var(--green-bg);}
        .item-card input[type=radio]{accent-color:var(--green);flex-shrink:0;}
        .item-card-info{flex:1;display:flex;flex-wrap:wrap;gap:6px 16px;}
        .ic-tag{display:inline-flex;align-items:center;gap:4px;font-size:.8rem;color:var(--t2);}
        .ic-tag strong{color:var(--t1);font-weight:700;}
        .hallmark-input{width:100%;height:52px;padding:0 14px;border:2px solid var(--border);border-radius:var(--rs);font-family:'DM Mono',monospace;font-size:1.4rem;font-weight:800;color:var(--t1);letter-spacing:.06em;outline:none;transition:border-color .15s,box-shadow .15s;text-align:center;}
        .hallmark-input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
        .form-actions{display:flex;justify-content:flex-end;padding:14px 18px;background:var(--s2);border-top:1px solid var(--border);}

        /* ── Drag & drop photo zone ── */
        .dz-wrap{display:flex;flex-direction:column;gap:6px;}
        .drop-zone{
            position:relative;display:flex;flex-direction:column;
            align-items:center;justify-content:center;gap:6px;
            height:130px;border:2px dashed var(--border);border-radius:var(--rs);
            cursor:pointer;background:var(--s2);transition:border-color .15s,background .15s;
            overflow:hidden;
        }
        .drop-zone:hover,.drop-zone.dz-over{border-color:var(--cyan);background:var(--cyan-bg);}
        .drop-zone.dz-over{border-style:solid;}
        .drop-zone.has-file{border-style:solid;border-color:var(--green-b);background:var(--green-bg);}
        .drop-zone.has-file .dz-placeholder{display:none;}
        .dz-placeholder{display:flex;flex-direction:column;align-items:center;gap:4px;pointer-events:none;}
        .dz-placeholder i{font-size:1.5rem;color:var(--t4);}
        .dz-placeholder span{font-size:.75rem;color:var(--t3);font-weight:600;}
        .dz-placeholder small{font-size:.68rem;color:var(--t4);}
        .dz-preview{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:none;border-radius:calc(var(--rs) - 2px);}
        .drop-zone.has-file .dz-preview{display:block;}
        .dz-clear{position:absolute;top:5px;right:5px;width:22px;height:22px;border-radius:50%;background:rgba(220,38,38,.85);border:none;color:#fff;font-size:9px;cursor:pointer;display:none;align-items:center;justify-content:center;z-index:10;}
        .drop-zone.has-file .dz-clear{display:flex;}
        .dz-input{display:none;}

        /* ── Hallmark Report Preview ── */
        .hallmark-preview{width:auto;max-width:750px;background:white;border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;}
        #reportPreview{background:white;padding:0;position:relative;}
        #reportPreview::before{content:'';position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-25deg);width:250px;height:250px;background-image:url('Varifiedstamp.png');background-size:contain;background-repeat:no-repeat;background-position:center;opacity:0.25;z-index:1;pointer-events:none;}
        #reportPreview > *{position:relative;z-index:2;}
        .report-header-title{text-align:center;color:#3eb1e3;font-size:32px;font-weight:bold;margin:0;padding:2px 0;letter-spacing:2px;line-height:1;}
        .hallmark-dotted{border-top:2.5px dotted #000;margin:0;}
        .hallmark-info-section{display:flex;justify-content:space-between;align-items:flex-start;padding:3px 8px;margin:0;}
        .hallmark-left-info{flex:1;padding:0;line-height:1.2;}
        .customer-info-line{margin:0;padding:0;font-size:15px;line-height:1.4;display:block;color:#000;font-weight:600;}
        .customer-info-line.customer-name{font-size:20px;font-weight:bold;margin-bottom:2px;}
        .info-label{display:inline-block;min-width:110px;text-align:left;font-weight:600;}
        .info-colon{display:inline;margin:0 3px;}
        .info-value{display:inline;font-weight:600;}
        .qr-section{width:100px;text-align:center;padding:0;margin-left:8px;flex-shrink:0;}
        #qrcode{margin:0;line-height:0;}
        #qrcode img{display:block;margin:0 auto;}
        .qr-date{font-size:10px;color:#000;font-weight:700;line-height:1.2;margin:1px 0 0;padding:0;}
        .main-box{border:2.5px solid #000;display:flex;margin:0 8px;height:100px;}
        .checkbox-section{flex:1;padding:8px 12px;display:grid;grid-template-columns:repeat(4,1fr);gap:5px 15px;align-content:center;}
        .checkbox-item{display:flex;align-items:center;font-size:12px;line-height:1;font-weight:600;color:#000;}
        .checkbox-box{width:14px;height:14px;border:2px solid #000;margin-right:4px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:bold;line-height:1;}
        .hallmark-section{width:240px;border-left:2.5px solid #000;display:flex;flex-direction:column;}
        .hallmark-value-container{flex:1;display:flex;align-items:center;justify-content:center;border-bottom:2.5px solid #000;padding:8px 12px;overflow:hidden;}
        .hallmark-value{font-size:40px;font-weight:bold;line-height:1;color:#000;text-align:center;word-wrap:break-word;word-break:break-word;max-width:100%;font-family:'Times New Roman',Times,serif;}
        .hallmark-label{font-size:15px;font-weight:700;text-align:center;padding:4px;color:#000;line-height:1;font-family:'Times New Roman',Times,serif;}
        .weight-conversion{font-size:13px;color:#000;font-weight:600;margin-left:0;}
        .report-actions{display:flex;align-items:center;justify-content:center;gap:10px;padding:16px 18px;background:var(--s2);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);flex-wrap:wrap;}

        @media(max-width:991.98px){.page-shell{margin-left:0;}.top-bar{top:52px;}.main{padding:14px 14px 50px;max-width:100%;}}
        @media print {
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            body { background: #fff !important; }
            /* Hide navbar, top bar, form, buttons, alerts — everything except the report */
            #app-navbar,
            nav, aside, #navbar, .navbar, [class*="navbar"], [class*="sidebar"],
            .top-bar,
            .report-actions,
            .pos-alert,
            .main > *:not(.hallmark-preview) { display: none !important; }
            .page-shell { margin-left: 0 !important; }
            .main { padding: 0 !important; max-width: 100% !important; }
            .hallmark-preview { border: none !important; box-shadow: none !important; max-width: 100% !important; }
        }
    </style>
</head>
<body>
<div id="app-navbar"><?php include 'navbar.php'; ?></div>
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

    <!-- Step 1 — Fetch Order -->
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

    <!-- Step 2 — Select Bill Item -->
    <div class="sec">
        <div class="sec-hd">
            <span class="sec-ico si-am"><i class="fas fa-list-check"></i></span>
            <span class="sec-title">Step 2 — Select Bill Item</span>
        </div>
        <div class="sec-body">
            <div class="item-cards">
                <?php foreach ($bill_items as $idx => $bi): ?>
                <div class="item-card <?= $idx===0?'selected':'' ?>" onclick="selectBillItem(<?= $idx ?>)">
                    <input type="radio" name="selected_bill_item" id="bi_<?= $idx ?>" value="<?= $idx ?>" <?= $idx===0?'checked':'' ?>>
                    <div class="item-card-info">
                        <span class="ic-tag"><strong><?= htmlspecialchars($bi['item_name']) ?></strong></span>
                        <span class="ic-tag">Weight: <strong><?= htmlspecialchars($bi['weight']) ?> gm</strong></span>
                        <span class="ic-tag">Qty: <strong><?= htmlspecialchars($bi['quantity']?:'1') ?></strong></span>
                        <span class="ic-tag">Karat: <strong><?= htmlspecialchars($bi['karat']?:'N/A') ?></strong></span>
                        <span class="ic-tag" style="color:var(--blue);"><?= htmlspecialchars($bi['service_name']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Step 3 — Fill Details & Generate -->
    <form method="POST" id="reportForm" enctype="multipart/form-data">
        <input type="hidden" name="order_id"     value="<?= htmlspecialchars($order_data['order_id']) ?>">
        <input type="hidden" name="bill_item_id" id="bill_item_id" value="<?= $bill_items[0]['bill_item_id'] ?>">
        <input type="hidden" name="item_name"    id="item_name"    value="<?= htmlspecialchars($bill_items[0]['item_name']) ?>">
        <input type="hidden" name="service_name" id="service_name" value="<?= htmlspecialchars($bill_items[0]['service_name']) ?>">

        <!-- Customer Information -->
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

        <!-- Sample Details -->
        <div class="sec">
            <div class="sec-hd">
                <span class="sec-ico si-am"><i class="fas fa-gem"></i></span>
                <span class="sec-title">Step 4 — Sample Details &amp; Photo</span>
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
                               required value="<?= htmlspecialchars($bill_items[0]['quantity']?:'1') ?>">
                    </div>
                </div>

                <!-- Hallmark value -->
                <div>
                    <label class="lbl">Hallmark Value <span class="req">*</span></label>
                    <input type="text" name="hallmark" id="hallmark" class="hallmark-input"
                           placeholder="e.g. 21K RJ" required>
                </div>

                <!-- Sample Photo — drag & drop -->
                <div>
                    <?php if ($upload_error): ?>
                    <div class="pos-alert danger" style="margin-bottom:12px;">
                        <i class="fas fa-circle-xmark" style="font-size:.9rem;flex-shrink:0;margin-top:1px;"></i>
                        <?= htmlspecialchars($upload_error) ?>
                    </div>
                    <?php endif; ?>
                    <div class="dz-wrap" style="max-width:320px;">
                        <label class="lbl">Sample Photo
                            <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--t4);">Optional · max 200 KB</span>
                        </label>
                        <div class="drop-zone" id="dz_photo"
                             onclick="document.getElementById('photo').click()"
                             ondragenter="dzEnter(event,this)"
                             ondragover="dzOver(event)"
                             ondragleave="dzLeave(event,this)"
                             ondrop="dzDrop(event,this,'photo','photo_prev')">
                            <div class="dz-placeholder">
                                <i class="fas fa-cloud-arrow-up"></i>
                                <span>Drag & drop or click</span>
                                <small>JPG · PNG · WEBP · max 200 KB</small>
                            </div>
                            <img id="photo_prev" class="dz-preview" alt="Photo preview">
                            <button type="button" class="dz-clear"
                                    onclick="dzClear(event,'dz_photo','photo','photo_prev')" title="Remove">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <input type="file" id="photo" name="photo" class="dz-input"
                               accept=".jpg,.jpeg,.png,.webp"
                               onchange="dzFromInput(this,'dz_photo','photo_prev')">
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
                        <span class="info-value"><?= htmlspecialchars($report_data['quantity']?:'1') ?></span>
                    </div>
                    <div class="customer-info-line">
                        <span class="info-label">Weight</span>
                        <span class="info-colon">:</span>
                        <span class="info-value"><?= htmlspecialchars($report_data['weight']) ?> Gm<span class="weight-conversion" id="weightConversionHall"></span></span>
                    </div>
                    <div class="customer-info-line">
                        <span class="info-label">Made by</span>
                        <span class="info-colon">:</span>
                        <span class="info-value"><?= htmlspecialchars($report_data['manufacturer']?:'N/A') ?></span>
                    </div>
                    <div class="customer-info-line">
                        <span class="info-label">Address</span>
                        <span class="info-colon">:</span>
                        <span class="info-value"><?= htmlspecialchars($report_data['address']?:'N/A') ?></span>
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
            <div style="display:flex;gap:0;margin:6px 8px 4px;align-items:stretch;min-height:90px;">
                <div style="flex:0 0 58.333%;max-width:58.333%;display:flex;align-items:flex-end;padding-right:8px;">
                    <img src="<?= htmlspecialchars($report_image) ?>" alt="Sample photo"
                         style="width:160px;height:105px;object-fit:cover;border-radius:4px;border:1px solid #ddd;">
                </div>
                <div style="flex:0 0 41.667%;max-width:41.667%;display:flex;flex-direction:column;justify-content:flex-end;">
                    <div style="text-align:center;font-size:11px;font-weight:bold;color:#000;padding-bottom:2px;">
                        Authorized Signature
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div style="text-align:center;font-size:11px;font-weight:bold;color:#000;margin:6px 8px 4px;padding-bottom:2px;">
                Authorized Signature
            </div>
            <?php endif; ?>

        </div><!-- /reportPreview -->
    </div><!-- /hallmark-preview -->

    <div class="report-actions">
        <button onclick="copyFullReportImage()" class="btn-pos btn-amber" style="height:38px;font-size:.875rem;">
            <i class="fas fa-copy" style="font-size:.65rem;"></i> Copy Report with QR
        </button>
        <button onclick="window.print()" class="btn-pos btn-ghost" style="height:38px;font-size:.875rem;">
            <i class="fas fa-print" style="font-size:.65rem;"></i> Print Report
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
            if (!gram || gram <= 0) return '';
            const tp = Math.round((gram / 11.664) * 16 * 6 * 10);
            const b  = Math.floor(tp / 960), r1 = tp % 960;
            const a  = Math.floor(r1 / 60),  r2 = r1 % 60;
            const ro = Math.floor(r2 / 10),  p  = r2 % 10;
            return ` [V:${b} A:${a} R:${ro} P:${p}]`;
        }
        document.getElementById('weightConversionHall').textContent =
            convertGramToVoriAnaHall(<?= floatval($report_data['weight']) ?>);

        const itemName = "<?= strtolower(htmlspecialchars($report_data['item_name'])) ?>";
        document.querySelectorAll('.checkbox-item').forEach(item => {
            const t = item.getAttribute('data-item').toLowerCase();
            if (itemName === t || itemName.includes(' '+t+' ') ||
                itemName.startsWith(t+' ') || itemName.endsWith(' '+t)) {
                const cb = item.querySelector('.checkbox-box');
                cb.innerHTML = '✓';
                cb.style.cssText = 'font-size:14px;font-weight:bold;display:flex;align-items:center;justify-content:center;line-height:1;';
            }
        });

        new QRCode(document.getElementById("qrcode"), {
            text: "https://www.app.rajaiswari.com/report_varification.php?id=<?= $report_id ?>",
            width: 100, height: 100
        });

        async function copyFullReportImage() {
            const btn = event.target, orig = btn.innerHTML;
            btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Capturing...';
            try {
                const canvas = await html2canvas(document.getElementById("reportPreview"),
                    { scale:2, useCORS:true, backgroundColor:'#ffffff', logging:false });
                canvas.toBlob(async blob => {
                    try {
                        await navigator.clipboard.write([new ClipboardItem({ "image/png": blob })]);
                        btn.disabled = false; btn.innerHTML = orig;
                        alert("✅ Report copied! Press Ctrl+V in MS Word to paste.");
                    } catch(e) { btn.disabled=false; btn.innerHTML=orig; alert("❌ Clipboard error: "+e.message); }
                });
            } catch(e) { btn.disabled=false; btn.innerHTML=orig; alert("❌ Capture error: "+e.message); }
        }
    </script>

<?php endif; ?>

</div><!-- /main -->
</div><!-- /page-shell -->

<script>
// ── Bill item selection ────────────────────────────────────────────────────
const billItems = <?= isset($bill_items) ? json_encode($bill_items) : '[]' ?>;

function selectBillItem(i) {
    document.getElementById('bi_' + i).checked         = true;
    document.getElementById('bill_item_id').value      = billItems[i].bill_item_id;
    document.getElementById('item_name').value         = billItems[i].item_name;
    document.getElementById('item_name_display').value = billItems[i].item_name;
    document.getElementById('weight').value            = billItems[i].weight;
    document.getElementById('quantity').value          = billItems[i].quantity || 1;
    document.getElementById('service_name').value      = billItems[i].service_name;
    document.querySelectorAll('.item-card').forEach(c => c.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
}

// ── Drag & Drop Photo Zone ─────────────────────────────────────────────────
const MAX_PHOTO_SIZE = 200 * 1024; // 200 KB
const ALLOWED_TYPES  = ['image/jpeg','image/jpg','image/png','image/webp'];

function dzValidate(file) {
    if (!ALLOWED_TYPES.includes(file.type)) {
        alert('Only JPG, PNG, or WEBP images are allowed.');
        return false;
    }
    if (file.size > MAX_PHOTO_SIZE) {
        alert('Photo exceeds 200 KB. Please choose a smaller file.');
        return false;
    }
    return true;
}
function dzApply(file, zoneId, previewId) {
    if (!dzValidate(file)) return;
    document.getElementById(previewId).src = URL.createObjectURL(file);
    document.getElementById(zoneId).classList.add('has-file');
}
function dzFromInput(input, zoneId, previewId) {
    if (input.files && input.files[0]) dzApply(input.files[0], zoneId, previewId);
}
function dzEnter(e, zone) {
    e.preventDefault();
    zone.classList.add('dz-over');
}
function dzOver(e) {
    e.preventDefault();
}
function dzLeave(e, zone) {
    if (!zone.contains(e.relatedTarget)) zone.classList.remove('dz-over');
}
function dzDrop(e, zone, inputId, previewId) {
    e.preventDefault();
    zone.classList.remove('dz-over');
    const file = e.dataTransfer.files[0];
    if (!file) return;
    const dt = new DataTransfer();
    dt.items.add(file);
    document.getElementById(inputId).files = dt.files;
    dzApply(file, zone.id, previewId);
}
function dzClear(e, zoneId, inputId, previewId) {
    e.stopPropagation();
    document.getElementById(inputId).value = '';
    document.getElementById(previewId).src  = '';
    document.getElementById(zoneId).classList.remove('has-file');
}
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>