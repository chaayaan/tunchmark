<?php
require 'auth.php';
require 'mydb.php';

$GOLD_ELEMENTS   = ['Silver','Platinum','Bismuth','Copper','Palladium','Nickel','Zinc','Antimony','Indium','Cadmium','Iron','Titanium','Iridium','Tin','Ruthenium','Rhodium','Lead','Vanadium','Cobalt','Osmium','Manganese'];
$SILVER_ELEMENTS = ['Copper','Palladium','Nickel','Zinc','Antimony','Indium','Cadmium','Iron','Titanium','Iridium','Tin','Ruthenium','Rhodium','Lead','Vanadium','Cobalt','Osmium','Manganese'];
$ALL_ELEMENT_COLUMNS = ['silver','platinum','bismuth','copper','palladium','nickel','zinc','antimony','indium','cadmium','iron','titanium','iridium','tin','ruthenium','rhodium','lead','vanadium','cobalt','osmium','manganese','germanium','tungsten','gallium','rhenium'];

$order_data     = null;
$bill_items     = [];
$report_created = false;
$report_id      = null;
$report_data    = null;
$upload_error   = null;

// ── Fetch Order ────────────────────────────────────────────────────────────
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
        $stmt = mysqli_prepare($conn,
            "SELECT bi.bill_item_id, bi.weight, bi.karat, i.name as item_name, s.name as service_name
             FROM bill_items bi
             JOIN items i ON bi.item_id = i.id
             JOIN services s ON bi.service_id = s.id
             WHERE bi.order_id = ? AND s.name NOT LIKE '%hallmark%'");
        mysqli_stmt_bind_param($stmt, "i", $order_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) $bill_items[] = $row;
        mysqli_stmt_close($stmt);
        if (empty($bill_items)) $error = "No tunch service items found for this order.";
    }
}

// ── Submit Report ──────────────────────────────────────────────────────────
if (isset($_POST['submit_report'])) {
    $order_id      = intval($_POST['order_id']);
    $customer_name = trim($_POST['customer_name']);
    $bill_item_id  = intval($_POST['bill_item_id']);
    $item_name     = trim($_POST['item_name']);
    $service_name  = trim($_POST['service_name']);
    $weight        = floatval($_POST['weight']);

    $itemLower = strtolower($item_name);
    $isSilver  = strpos($itemLower,'silver')!==false || strpos($itemLower,'চাঁদি')!==false || strpos($itemLower,'rupa')!==false;

    $gold_purity_percent   = $isSilver ? null : (floatval($_POST['purity_percent']) ?: null);
    $silver_purity_percent = $isSilver ? (floatval($_POST['purity_percent']) ?: null) : null;
    $karat     = floatval($_POST['karat'])     ?: null;
    $gold_raw  = floatval($_POST['gold_val']);
    $joint_raw = floatval($_POST['joint_val']);
    $gold_val  = ($gold_raw  >= 0 && $gold_raw  <= 24) ? ($gold_raw  ?: null) : null;
    $joint_val = ($joint_raw >= 0 && $joint_raw <= 24) ? ($joint_raw ?: null) : null;

    $elementCols = []; $elementVals = []; $elementTypes = '';
    foreach ($ALL_ELEMENT_COLUMNS as $col) {
        $raw = $_POST['elem_' . $col] ?? '';
        $elementCols[]  = "`$col`";
        $elementVals[]  = ($raw === '' || $raw === '--------') ? null : floatval($raw);
        $elementTypes  .= 'd';
    }

    // ── Validate uploaded images ──────────────────────────────────────────
    $allowed_types = ['image/jpeg','image/jpg','image/png','image/webp'];
    $max_size      = 200 * 1024; // 200 KB
    $photo_paths   = [];

    for ($n = 1; $n <= 2; $n++) {
        $key = 'photo_' . $n;
        if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) continue;

        $file = $_FILES[$key];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $upload_error = "Photo $n upload error (code {$file['error']}).";
            break;
        }
        if ($file['size'] > $max_size) {
            $upload_error = "Photo $n exceeds 200 KB limit.";
            break;
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowed_types, true)) {
            $upload_error = "Photo $n must be JPG, PNG, or WEBP.";
            break;
        }
        $photo_paths[$n] = ['tmp' => $file['tmp_name'], 'mime' => $mime];
    }

    if ($upload_error) {
        $stmt = mysqli_prepare($conn, "SELECT order_id, customer_name FROM orders WHERE order_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $order_id);
        mysqli_stmt_execute($stmt);
        $order_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        $stmt = mysqli_prepare($conn,
            "SELECT bi.bill_item_id, bi.weight, bi.karat, i.name as item_name, s.name as service_name
             FROM bill_items bi JOIN items i ON bi.item_id=i.id JOIN services s ON bi.service_id=s.id
             WHERE bi.order_id=? AND s.name NOT LIKE '%hallmark%'");
        mysqli_stmt_bind_param($stmt, "i", $order_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) $bill_items[] = $row;
        mysqli_stmt_close($stmt);
    } else {
        // ── Insert report ─────────────────────────────────────────────────
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
            $elementVals, [$gold_val, $joint_val]
        );
        $bp = [$types];
        foreach ($params as &$p) $bp[] = &$p;
        call_user_func_array([$stmt, 'bind_param'], $bp);
        mysqli_stmt_execute($stmt);
        $report_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        // ── Save images ───────────────────────────────────────────────────
        $upload_dir = __DIR__ . '/uploads/tunch_reports/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        foreach ($photo_paths as $n => $info) {
            $ext_map = ['image/jpeg'=>'jpg','image/jpg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            $ext     = $ext_map[$info['mime']] ?? 'jpg';
            $filename  = "tunch_{$report_id}_{$n}.{$ext}";
            $dest_path = $upload_dir . $filename;
            $rel_path  = "uploads/tunch_reports/{$filename}";

            if (move_uploaded_file($info['tmp'], $dest_path)) {
                $imgStmt = mysqli_prepare($conn,
                    "INSERT INTO report_images (report_id, img_type, img_number, img_path)
                     VALUES (?, 'tunch', ?, ?)");
                mysqli_stmt_bind_param($imgStmt, 'iis', $report_id, $n, $rel_path);
                mysqli_stmt_execute($imgStmt);
                mysqli_stmt_close($imgStmt);
            }
        }

        header("Location: create_tunch_report.php?report_id=" . $report_id);
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
            --bg:#f1f3f6; --surface:#ffffff; --s2:#fafbfc;
            --border:#e4e7ec; --bsoft:#f0f1f3;
            --t1:#111827; --t2:#374151; --t3:#6b7280; --t4:#9ca3af;
            --blue:#2563eb;   --blue-bg:#eff6ff;   --blue-b:#bfdbfe;
            --green:#059669;  --green-bg:#ecfdf5;  --green-b:#a7f3d0;
            --amber:#d97706;  --amber-bg:#fffbeb;  --amber-b:#fde68a;
            --red:#dc2626;    --red-bg:#fef2f2;    --red-b:#fecaca;
            --violet:#7c3aed; --violet-bg:#f5f3ff;
            --r:10px; --rs:6px;
            --sh:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);
        }
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'DM Sans',sans-serif;font-size:14px;background:var(--bg);color:var(--t1);-webkit-font-smoothing:antialiased;min-height:100vh;}
        .page-shell{margin-left:200px;min-height:100vh;display:flex;flex-direction:column;}
        .top-bar{position:sticky;top:0;z-index:200;height:54px;background:var(--surface);border-bottom:1px solid var(--border);box-shadow:var(--sh);display:flex;align-items:center;padding:0 22px;gap:12px;flex-shrink:0;}
        .tb-ico{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;background:var(--violet-bg);color:var(--violet);flex-shrink:0;}
        .tb-title{font-size:1.0625rem;font-weight:700;color:var(--t1);}
        .tb-sub{font-size:.78rem;color:var(--t4);}
        .tb-right{margin-left:auto;display:flex;gap:7px;align-items:center;}
        .btn-pos{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 14px;border:none;border-radius:var(--rs);font-family:inherit;font-size:.8125rem;font-weight:600;cursor:pointer;transition:all .15s;text-decoration:none;white-space:nowrap;}
        .btn-ghost{background:var(--surface);color:var(--t2);border:1.5px solid var(--border);}
        .btn-ghost:hover{background:var(--s2);border-color:#9ca3af;color:var(--t1);}
        .btn-blue{background:var(--blue);color:#fff;} .btn-blue:hover{background:#1d4ed8;}
        .btn-green{background:var(--green);color:#fff;} .btn-green:hover{background:#047857;}
        .btn-amber{background:var(--amber);color:#fff;} .btn-amber:hover{background:#b45309;}
        .btn-violet{background:var(--violet);color:#fff;} .btn-violet:hover{background:#6d28d9;}
        .split-body{padding:18px 16px 60px;}
        .split-left{display:flex;flex-direction:column;gap:14px;}
        .split-right{display:flex;flex-direction:column;gap:14px;}
        .col-divider{border-left:2px solid var(--border);}
        .rp-placeholder{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;min-height:300px;color:var(--t4);text-align:center;background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);}
        .rp-placeholder-icon{width:50px;height:50px;border-radius:12px;background:var(--border);display:flex;align-items:center;justify-content:center;font-size:20px;color:var(--t4);}
        .rp-placeholder h3{font-size:.875rem;font-weight:700;color:var(--t3);margin:0;}
        .rp-placeholder p{font-size:.8rem;color:var(--t4);margin:0;max-width:190px;line-height:1.5;}
        .sec{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;}
        .sec-hd{display:flex;align-items:center;gap:9px;padding:11px 18px;background:var(--s2);border-bottom:1px solid var(--bsoft);}
        .sec-ico{width:26px;height:26px;border-radius:var(--rs);display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;}
        .si-vi{background:var(--violet-bg);color:var(--violet);}
        .si-bl{background:var(--blue-bg);color:var(--blue);}
        .si-am{background:var(--amber-bg);color:var(--amber);}
        .si-gr{background:var(--green-bg);color:var(--green);}
        .sec-title{font-size:.875rem;font-weight:700;color:var(--t1);}
        .sec-body{padding:18px;}
        .sec-title-note{margin-left:auto;font-size:.75rem;color:var(--amber);font-weight:600;}
        .pos-alert{display:flex;align-items:flex-start;gap:10px;padding:12px 16px;border-radius:var(--rs);font-size:.875rem;font-weight:500;}
        .pos-alert.danger{background:var(--red-bg);border:1px solid var(--red-b);border-left:3px solid var(--red);color:#991b1b;}
        .pos-alert.info{background:var(--blue-bg);border:1px solid var(--blue-b);border-left:3px solid var(--blue);color:#1e40af;}
        .pos-alert.amber{background:var(--amber-bg);border:1px solid var(--amber-b);border-left:3px solid var(--amber);color:#92400e;}
        .lbl{display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t3);margin-bottom:5px;}
        .lbl .req{color:var(--red);margin-left:2px;}
        .fc{width:100%;height:36px;padding:0 10px;border:1.5px solid var(--border);border-radius:var(--rs);font-family:inherit;font-size:.875rem;color:var(--t2);background:var(--surface);outline:none;transition:border-color .15s,box-shadow .15s;}
        .fc:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
        .fc.editable{background:var(--amber-bg);border-color:var(--amber-b);}
        .fc.editable:focus{border-color:var(--amber);box-shadow:0 0 0 3px rgba(217,119,6,.1);}
        textarea.fc{height:auto;padding:8px 10px;resize:vertical;}
        .fetch-row{display:flex;gap:10px;align-items:flex-end;}
        .form-actions{display:flex;justify-content:flex-end;padding:14px 18px;background:var(--s2);border-top:1px solid var(--border);}
        .item-cards{display:flex;flex-direction:column;gap:8px;}
        .item-card{display:flex;align-items:center;gap:12px;padding:10px 14px;border:2px solid var(--border);border-radius:var(--rs);cursor:pointer;transition:all .2s;}
        .item-card:hover{border-color:var(--blue-b);background:var(--blue-bg);}
        .item-card.selected{border-color:var(--green);background:var(--green-bg);}
        .item-card input[type=radio]{accent-color:var(--green);flex-shrink:0;}
        .item-card-info{flex:1;display:flex;flex-wrap:wrap;gap:6px 16px;}
        .ic-tag{display:inline-flex;align-items:center;gap:4px;font-size:.8rem;color:var(--t2);}
        .ic-tag strong{color:var(--t1);font-weight:700;}
        .xrf-textarea{font-family:'DM Mono',monospace;font-size:12px;line-height:1.6;}
        .parse-btn{margin-top:8px;}
        .photo-upload-box{display:flex;flex-direction:column;gap:6px;}
        .photo-preview{width:100%;height:110px;object-fit:cover;border-radius:var(--rs);border:1px solid var(--border);display:none;margin-top:6px;}
        .report-wrap{padding:20px 22px 60px;display:flex;flex-direction:column;gap:14px;max-width:860px;}
        .tunch-preview{width:auto;max-width:700px;padding:0 30px;background:white;border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;}
        .tunch-container{padding:0 20px;background:white;position:relative;}
        .tunch-container::before{content:'';position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-25deg);width:200px;height:200px;background-image:url('Varifiedstamp.png');background-size:contain;background-repeat:no-repeat;background-position:center;opacity:0.2;z-index:1;pointer-events:none;}
        .tunch-container > *{position:relative;z-index:2;}
        .report-header{display:flex;justify-content:space-between;align-items:flex-start;}
        .customer-info{flex:1;}
        .customer-info-line{margin:0;padding:0;font-size:15px;line-height:1.8;display:flex;color:#000;font-weight:600;}
        .customer-info-line.customer-name{font-size:22px;font-weight:bold;margin-bottom:3px;}
        .info-label{display:inline-block;min-width:120px;font-weight:600;}
        .info-colon{display:inline-block;width:15px;text-align:center;}
        .info-value{flex:1;font-weight:600;}
        .qr-section{width:100px;text-align:center;padding:5px;margin-left:15px;flex-shrink:0;}
        .qr-date{font-size:12px;color:#000;font-weight:700;line-height:1.4;margin-top:5px;white-space:nowrap;}
        .weight-conversion{font-size:13px;color:#333;font-weight:600;margin-left:10px;}
        .dotted-line{border-top:3px dotted #000;margin:5px 0;}
        .quality-info{font-size:24px;font-weight:bold;margin:5px 0;line-height:1.5;display:flex;justify-content:space-around;flex-wrap:wrap;gap:20px;color:#000;}
        .quality-info span{white-space:nowrap;}
        .composition-table{width:100%;margin:2px 0 0;font-size:11px;line-height:1.1;border-collapse:collapse;}
        .composition-table td{padding:1px 5px;font-weight:600;vertical-align:top;}
        .composition-table td.element-name{text-align:left;padding-right:3px;}
        .composition-table td.element-colon{text-align:center;padding:1px 2px;}
        .composition-table td.element-value{text-align:left;padding-left:3px;padding-right:15px;}
        .report-note{font-size:11px;line-height:1.4;margin:3px 0 0;font-weight:600;color:#000;}
        .report-photos{display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;}
        .report-photos img{width:140px;height:100px;object-fit:cover;border-radius:4px;border:1px solid #ddd;}
        .report-codes{font-size:11px;text-align:right;margin:4px 0 0;font-weight:bold;color:#000;}
        .report-actions{display:flex;align-items:center;justify-content:center;gap:10px;padding:16px 18px;background:var(--s2);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);flex-wrap:wrap;}
        @media(max-width:991.98px){.page-shell{margin-left:0;}.top-bar{top:52px;}.col-divider{border-left:none;border-top:2px solid var(--border);padding-top:14px;margin-top:0;}}
        @media print{.page-shell{margin-left:0;}.split-body{display:none!important;}}
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

<!-- ═══════════════════════════════════════════ FORM ═══ -->
<div class="split-body">
<div class="row g-0">

<!-- LEFT col: Steps 1–4 -->
<div class="col-12 col-lg-6 pe-lg-3">
<div class="split-left">

    <!-- Step 1 — Fetch Order -->
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
                        <span class="ic-tag">Karat: <strong><?= htmlspecialchars($bi['karat']?:'N/A') ?></strong></span>
                        <span class="ic-tag" style="color:var(--violet);"><?= htmlspecialchars($bi['service_name']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- MAIN FORM -->
    <form method="POST" id="reportForm" enctype="multipart/form-data">
        <input type="hidden" name="order_id"     value="<?= htmlspecialchars($order_data['order_id']) ?>">
        <input type="hidden" name="bill_item_id" id="bill_item_id" value="<?= $bill_items[0]['bill_item_id'] ?>">
        <input type="hidden" name="service_name" id="service_name" value="<?= htmlspecialchars($bill_items[0]['service_name']) ?>">

        <!-- Step 3 — Customer & Sample Details -->
        <div class="sec">
            <div class="sec-hd">
                <span class="sec-ico si-bl"><i class="fas fa-user"></i></span>
                <span class="sec-title">Step 3 — Customer &amp; Sample Details</span>
                <span class="sec-title-note"><i class="fas fa-pen" style="font-size:.6rem;"></i> Editable</span>
            </div>
            <div class="sec-body">
                <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px;">
                    <div>
                        <label class="lbl">Customer Name <span class="req">*</span></label>
                        <input type="text" name="customer_name" id="customer_name" class="fc editable" required
                               value="<?= htmlspecialchars($order_data['customer_name']) ?>">
                    </div>
                    <div>
                        <label class="lbl">Sample Weight (gm) <span class="req">*</span></label>
                        <input type="number" name="weight" id="weight" class="fc editable" step="0.001" required
                               value="<?= $bill_items[0]['weight'] ?>">
                    </div>
                    <div>
                        <label class="lbl">Sample Item Name <span class="req">*</span></label>
                        <input type="text" name="item_name" id="item_name" class="fc editable" required
                               value="<?= htmlspecialchars($bill_items[0]['item_name']) ?>"
                               oninput="updateMetalType()">
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 4 — XRF Paste -->
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

    </form><!-- end reportForm -->

    <?php endif; ?>

</div><!-- /split-left -->
</div><!-- /left col -->

<!-- RIGHT col: Step 5 Testing Results + Step 6 Photos + Generate button -->
<div class="col-12 col-lg-6 ps-lg-3 col-divider">
<div class="split-right">

    <?php if ($order_data && !empty($bill_items)): ?>

    <!-- Step 5 — Testing Results -->
    <div class="sec">
        <div class="sec-hd">
            <span class="sec-ico si-vi"><i class="fas fa-flask"></i></span>
            <span class="sec-title">Step 5 — Testing Results</span>
        </div>
        <div class="sec-body" style="display:flex;flex-direction:column;gap:14px;">

            <!-- Purity & Karat -->
            <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--violet-bg);border:1px solid #ddd6fe;border-radius:7px;">
                <span style="font-size:.78rem;font-weight:700;color:var(--violet);text-transform:uppercase;letter-spacing:.05em;white-space:nowrap;" id="purityLabel">Gold Purity</span>
                <input type="number" name="purity_percent" id="purity_percent" class="fc" form="reportForm"
                       step="0.001" min="0" max="100" placeholder="0.000"
                       style="font-family:'DM Mono',monospace;height:30px;padding:0 6px;flex:1;">
                <span style="font-size:.82rem;color:var(--t2);white-space:nowrap;">Karat</span>
                <input type="number" name="karat" id="karat" class="fc" form="reportForm"
                       step="0.01" min="0" max="24" placeholder="0.00"
                       style="font-family:'DM Mono',monospace;height:30px;padding:0 6px;width:80px;">
            </div>

            <!-- Element grid -->
            <table style="width:100%;border-collapse:collapse;">
            <?php
            $elemOrder = ['silver','copper','zinc','cadmium','iridium','rhodium','cobalt',
                          'germanium','palladium','platinum','iron','tin','lead','gallium',
                          'bismuth','nickel','indium','tungsten','ruthenium','rhenium','osmium',
                          'antimony','titanium','vanadium','manganese'];
            $rows = array_chunk($elemOrder, 3);
            foreach ($rows as $row):
            ?>
            <tr>
                <?php foreach ($row as $el): ?>
                <td style="padding:2px 4px;white-space:nowrap;">
                    <div style="display:flex;align-items:center;gap:4px;">
                        <span style="font-size:.75rem;font-weight:600;color:var(--t2);min-width:68px;"><?= ucfirst($el) ?></span>
                        <input type="number" name="elem_<?= $el ?>" id="elem_<?= $el ?>"
                               class="fc" form="reportForm"
                               step="0.001" min="0" max="100" placeholder="--------"
                               style="font-family:'DM Mono',monospace;height:30px;padding:0 6px;font-size:.78rem;">
                    </div>
                </td>
                <?php endforeach; ?>
                <?php for ($x=count($row);$x<3;$x++): ?><td></td><?php endfor; ?>
            </tr>
            <?php endforeach; ?>
            </table>

            <!-- Gold & Joint -->
            <div style="display:flex;align-items:center;gap:24px;padding:10px 14px;background:var(--s2);border:1px solid var(--border);border-radius:7px;">
                <span style="font-size:.78rem;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.05em;white-space:nowrap;">Gold &amp; Joint</span>
                <div style="display:flex;align-items:center;gap:8px;flex:1;">
                    <span style="font-size:.82rem;color:var(--t2);white-space:nowrap;">Gold (K)</span>
                    <div style="flex:1;min-width:0;">
                        <input type="number" name="gold_val" id="gold_val" class="fc" form="reportForm"
                               step="0.001" min="0" max="24" placeholder="0.000"
                               oninput="validateKarat(this)"
                               style="font-family:'DM Mono',monospace;height:30px;padding:0 6px;width:100%;">
                        <span id="gold_val_err" style="display:none;font-size:.7rem;color:var(--red);margin-top:2px;">Must be 0 – 24</span>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;flex:1;">
                    <span style="font-size:.82rem;color:var(--t2);white-space:nowrap;">Joint (K)</span>
                    <div style="flex:1;min-width:0;">
                        <input type="number" name="joint_val" id="joint_val" class="fc" form="reportForm"
                               step="0.001" min="0" max="24" placeholder="0.000"
                               oninput="validateKarat(this)"
                               style="font-family:'DM Mono',monospace;height:30px;padding:0 6px;width:100%;">
                        <span id="joint_val_err" style="display:none;font-size:.7rem;color:var(--red);margin-top:2px;">Must be 0 – 24</span>
                    </div>
                </div>
            </div>

        </div>
    </div><!-- /Step 5 -->

    <!-- Step 6 — Sample Photos + Generate button -->
    <div class="sec">
        <div class="sec-hd">
            <span class="sec-ico si-am"><i class="fas fa-images"></i></span>
            <span class="sec-title">Step 6 — Sample Photos</span>
            <span style="margin-left:auto;font-size:.75rem;color:var(--t4);">Optional · max 200 KB each</span>
        </div>
        <div class="sec-body">
            <?php if ($upload_error): ?>
            <div class="pos-alert danger" style="margin-bottom:14px;">
                <i class="fas fa-circle-xmark" style="font-size:.9rem;flex-shrink:0;margin-top:1px;"></i>
                <?= htmlspecialchars($upload_error) ?>
            </div>
            <?php endif; ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="photo-upload-box">
                    <label class="lbl">Photo 1</label>
                    <input type="file" name="photo_1" id="photo_1" class="fc" form="reportForm"
                           accept=".jpg,.jpeg,.png,.webp"
                           style="height:auto;padding:6px 10px;cursor:pointer;"
                           onchange="previewPhoto(this, 'prev1')">
                    <img id="prev1" class="photo-preview" alt="Photo 1 preview">
                </div>
                <div class="photo-upload-box">
                    <label class="lbl">Photo 2</label>
                    <input type="file" name="photo_2" id="photo_2" class="fc" form="reportForm"
                           accept=".jpg,.jpeg,.png,.webp"
                           style="height:auto;padding:6px 10px;cursor:pointer;"
                           onchange="previewPhoto(this, 'prev2')">
                    <img id="prev2" class="photo-preview" alt="Photo 2 preview">
                </div>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" name="submit_report" form="reportForm"
                    class="btn-pos btn-green" style="height:38px;font-size:.9rem;">
                <i class="fas fa-file-circle-check" style="font-size:.7rem;"></i> Generate Tunch Report
            </button>
        </div>
    </div><!-- /Step 6 -->

    <?php else: ?>
    <div class="rp-placeholder">
        <div class="rp-placeholder-icon"><i class="fas fa-vial"></i></div>
        <h3>Testing Results</h3>
        <p>Fetch an order on the left to enter composition data here</p>
    </div>
    <?php endif; ?>

</div><!-- /split-right -->
</div><!-- /right col -->

</div><!-- /row -->
</div><!-- /split-body -->

<?php endif; ?>

<!-- ═══════════════════════════════════════════ REPORT ═══ -->
<?php if ($report_created && $report_data):
    $itemLower  = strtolower($report_data['item_name']);
    $isSilver   = strpos($itemLower,'silver')!==false || strpos($itemLower,'চাঁদি')!==false || strpos($itemLower,'rupa')!==false;
    $purityLabel = $isSilver ? 'Silver Purity' : 'Gold Purity';
    $purityValue = $isSilver ? $report_data['silver_purity_percent'] : $report_data['gold_purity_percent'];
    $elementOrder = $isSilver ? $SILVER_ELEMENTS : $GOLD_ELEMENTS;

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

    $imgStmt = mysqli_prepare($conn,
        "SELECT img_path FROM report_images WHERE report_id=? AND img_type='tunch' ORDER BY img_number ASC LIMIT 2");
    mysqli_stmt_bind_param($imgStmt, 'i', $report_data['id']);
    mysqli_stmt_execute($imgStmt);
    $imgResult = mysqli_stmt_get_result($imgStmt);
    $report_images = [];
    while ($r = mysqli_fetch_assoc($imgResult)) $report_images[] = $r['img_path'];
    mysqli_stmt_close($imgStmt);
?>
<div class="report-wrap">

    <div class="tunch-preview">
        <div class="tunch-container" id="reportPreview">

            <div class="report-header">
                <div class="customer-info">
                    <div class="customer-info-line customer-name">Customer Name : <?= htmlspecialchars($report_data['customer_name']) ?></div>
                    <div class="customer-info-line">
                        <span class="info-label">Sample Item</span><span class="info-colon">:</span>
                        <span class="info-value"><?= htmlspecialchars($report_data['item_name']) ?></span>
                    </div>
                    <div class="customer-info-line">
                        <span class="info-label">Sample Weight</span><span class="info-colon">:</span>
                        <span class="info-value"><?= htmlspecialchars($report_data['weight']) ?> Gm<span class="weight-conversion" id="weightConversion"></span></span>
                    </div>
                    <div class="customer-info-line">
                        <span class="info-label">Bill No</span><span class="info-colon">:</span>
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
                    <?php for ($i=count($row);$i<3;$i++): ?><td class="element-name"></td><td class="element-colon"></td><td class="element-value"></td><?php endfor; ?>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>

            <div class="report-note">NB:- <?= htmlspecialchars($nbNote) ?></div>

            <!-- Bottom split: 7/12 images | 5/12 gold+joint+signature -->
            <div style="display:flex;gap:0;margin-top:6px;align-items:stretch;min-height:90px;">

                <!-- Left 7/12 — photos -->
                <div style="flex:0 0 58.333%;max-width:58.333%;display:flex;align-items:flex-end;gap:8px;flex-wrap:wrap;padding-right:8px;">
                    <?php if (!empty($report_images)): ?>
                        <?php foreach ($report_images as $img_path): ?>
                        <img src="<?= htmlspecialchars($img_path) ?>" alt="Sample photo"
                             style="width:130px;height:95px;object-fit:cover;border-radius:4px;border:1px solid #ddd;">
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Right 5/12 — gold/joint top-right + authorized signature bottom-center -->
                <div style="flex:0 0 41.667%;max-width:41.667%;display:flex;flex-direction:column;justify-content:space-between;">

                    <!-- Gold & Joint in same row, top-right -->
                    <div style="display:flex;justify-content:flex-end;gap:18px;font-size:11px;font-weight:bold;color:#000;">
                        <?php if ($goldCode !== ''): ?><span>Gold : <?= htmlspecialchars($goldCode) ?></span><?php endif; ?>
                        <?php if ($jointCode !== ''): ?><span>Joint : <?= htmlspecialchars($jointCode) ?></span><?php endif; ?>
                    </div>

                    <!-- Authorized Signature bottom-center of right column -->
                    <div style="text-align:center;font-size:11px;font-weight:bold;color:#000;padding-bottom:2px;">
                        Authorized Signature
                    </div>

                </div>

            </div>

        </div><!-- /tunch-container -->
    </div><!-- /tunch-preview -->

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
        const tp = Math.round((gram / 11.664) * 16 * 6 * 10);
        const b  = Math.floor(tp / 960), r1 = tp % 960;
        const a  = Math.floor(r1 / 60), r2 = r1 % 60;
        const ro = Math.floor(r2 / 10), p = r2 % 10;
        return ` (V:${b} A:${a} R:${ro} P:${p})`;
    }
    document.getElementById('weightConversion').textContent = convertGramToVoriAna(<?= floatval($report_data['weight']) ?>);
    new QRCode(document.getElementById("qrcode"), {
        text: "https://www.app.rajaiswari.com/report_varification.php?id=<?= $report_id ?>",
        width: 90, height: 90
    });
    async function copyFullReportImage() {
        const btn = event.target, orig = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Capturing...';
        try {
            const canvas = await html2canvas(document.getElementById("reportPreview"), { scale:2, useCORS:true, backgroundColor:'#ffffff', logging:false });
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
function validateKarat(input) {
    const val = parseFloat(input.value);
    const err = document.getElementById(input.id + '_err');
    const bad = input.value !== '' && (isNaN(val) || val < 0 || val > 24);
    input.style.borderColor = bad ? 'var(--red)' : '';
    input.style.background  = bad ? 'var(--red-bg)' : '';
    if (err) err.style.display = bad ? 'block' : 'none';
}
function parseXRFData() {
    const input  = document.getElementById('xrf_raw').value;
    const silver = isSilverItem(document.getElementById('item_name').value);
    const pRx    = silver ? /Silver\s+Purity\s*[:\s]+([\d.]+)\s*%/i : /Gold\s+Purity\s*[:\s]+([\d.]+)\s*%/i;
    const pM     = input.match(pRx);
    if (pM) document.getElementById('purity_percent').value = pM[1];
    const kM = input.match(/Karat\s*[:\s]+([\d.]+)/i);
    if (kM) document.getElementById('karat').value = kM[1];
    const allEls = [...new Set([...GOLD_ELEMENTS, ...SILVER_ELEMENTS])];
    allEls.forEach(el => {
        const f = document.getElementById('elem_' + el.toLowerCase());
        if (!f) return;
        const m = input.match(new RegExp(el + '\\s*[:\\s]+([\\d.]+|--------)', 'i'));
        f.value = m ? m[1].replace('%','') : '';
    });
    const gM = input.match(/Gold\s*[:\s]+([\d.]+)/i);
    const jM = input.match(/Joint\s*[:\s]+([\d.]+)/i);
    if (gM) document.getElementById('gold_val').value  = gM[1];
    if (jM) document.getElementById('joint_val').value = jM[1];
    const btn = document.querySelector('.parse-btn'), orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> Extracted!'; btn.style.background = 'var(--green)';
    setTimeout(() => { btn.innerHTML = orig; btn.style.background = ''; }, 2000);
}
function selectBillItem(i) {
    document.getElementById('bi_' + i).checked        = true;
    document.getElementById('bill_item_id').value     = billItems[i].bill_item_id;
    document.getElementById('item_name').value        = billItems[i].item_name;
    document.getElementById('weight').value           = billItems[i].weight;
    document.getElementById('service_name').value     = billItems[i].service_name;
    document.getElementById('xrf_raw').value          = '';
    document.getElementById('purity_percent').value   = '';
    document.getElementById('karat').value            = '';
    updateMetalType();
    document.querySelectorAll('.item-card').forEach(c => c.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
}
function previewPhoto(input, previewId) {
    const preview = document.getElementById(previewId);
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
document.addEventListener('DOMContentLoaded', () => {
    const first = document.querySelector('.item-card');
    if (first) first.classList.add('selected');
    updateMetalType();
});
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>