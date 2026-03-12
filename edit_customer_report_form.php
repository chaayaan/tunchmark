<?php
require 'auth.php';
require 'mydb.php';

$GOLD_ELEMENTS   = ['Silver','Platinum','Bismuth','Copper','Palladium','Nickel','Zinc','Antimony','Indium','Cadmium','Iron','Titanium','Iridium','Tin','Ruthenium','Rhodium','Lead','Vanadium','Cobalt','Osmium','Manganese'];
$SILVER_ELEMENTS = ['Copper','Palladium','Nickel','Zinc','Antimony','Indium','Cadmium','Iron','Titanium','Iridium','Tin','Ruthenium','Rhodium','Lead','Vanadium','Cobalt','Osmium','Manganese'];
$ALL_ELEMENT_COLUMNS = ['silver','platinum','bismuth','copper','palladium','nickel','zinc','antimony','indium','cadmium','iron','titanium','iridium','tin','ruthenium','rhodium','lead','vanadium','cobalt','osmium','manganese','germanium','tungsten','gallium','rhenium'];

// ── Fetch report ───────────────────────────────────────────────────────────
$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = mysqli_prepare($conn, "SELECT * FROM customer_reports WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $report_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

if (mysqli_num_rows($result) == 0) {
    header('Location: view_customer_reports.php');
    exit();
}
$report = mysqli_fetch_assoc($result);

$isHallmark = stripos($report['service_name'], 'hallmark') !== false;
$isSilver   = stripos($report['item_name'], 'silver') !== false
           || strpos($report['item_name'], 'চাঁদি') !== false
           || stripos($report['item_name'], 'rupa')  !== false;

$upload_error = null;

// ── Handle form submission ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name']);
    $address       = trim($_POST['address']       ?? '');
    $manufacturer  = trim($_POST['manufacturer']  ?? '');
    $quantity      = max(1, (int)($_POST['quantity'] ?? 1));
    $hallmark      = trim($_POST['hallmark']      ?? '');

    $gold_purity_percent   = null;
    $silver_purity_percent = null;
    if (!$isHallmark) {
        $purityRaw = floatval($_POST['purity_percent'] ?? 0);
        if ($isSilver) $silver_purity_percent = $purityRaw ?: null;
        else            $gold_purity_percent   = $purityRaw ?: null;
    }
    $karat     = $isHallmark ? null : (floatval($_POST['karat']     ?? 0) ?: null);
    $gold_val  = $isHallmark ? null : (floatval($_POST['gold_val']  ?? 0) ?: null);
    $joint_val = $isHallmark ? null : (floatval($_POST['joint_val'] ?? 0) ?: null);

    $elementSetClauses = []; $elementVals = []; $elementTypes = '';
    foreach ($ALL_ELEMENT_COLUMNS as $col) {
        $raw = $_POST['elem_' . $col] ?? '';
        $val = ($raw === '' || $raw === '--------') ? null : floatval($raw);
        $elementSetClauses[] = "`$col` = ?";
        $elementVals[]       = $val;
        $elementTypes       .= 'd';
    }

    // ── Image upload (hallmark: 1 photo; tunch: 2 photos) ─────────────────
    $allowed_types = ['image/jpeg','image/jpg','image/png','image/webp'];
    $max_size      = 200 * 1024;
    $photo_paths   = [];

    if ($isHallmark) {
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['photo'];
            if ($file['error'] !== UPLOAD_ERR_OK)  { $upload_error = "Upload error."; }
            elseif ($file['size'] > $max_size)      { $upload_error = "Photo exceeds 200 KB limit."; }
            else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($file['tmp_name']);
                if (!in_array($mime, $allowed_types)) { $upload_error = "Only JPG/PNG/WEBP allowed."; }
                else {
                    $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $dir  = 'uploads/hallmark_reports/';
                    if (!is_dir($dir)) mkdir($dir, 0755, true);
                    $dest = $dir . "hallmark_{$report_id}." . strtolower($ext);
                    if (!move_uploaded_file($file['tmp_name'], $dest)) $upload_error = "Could not save photo.";
                    else $photo_paths[1] = ['path' => $dest, 'type' => 'hallmark', 'num' => 1];
                }
            }
        }
    } else {
        for ($n = 1; $n <= 2; $n++) {
            $key = 'photo_' . $n;
            if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) continue;
            $file = $_FILES[$key];
            if ($file['error'] !== UPLOAD_ERR_OK)  { $upload_error = "Upload error on photo $n."; break; }
            if ($file['size'] > $max_size)          { $upload_error = "Photo $n exceeds 200 KB limit."; break; }
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($file['tmp_name']);
            if (!in_array($mime, $allowed_types))   { $upload_error = "Photo $n: only JPG/PNG/WEBP allowed."; break; }
            $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
            $dir  = 'uploads/tunch_reports/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $dest = $dir . "tunch_{$report_id}_{$n}." . strtolower($ext);
            if (!move_uploaded_file($file['tmp_name'], $dest)) { $upload_error = "Could not save photo $n."; break; }
            $photo_paths[$n] = ['path' => $dest, 'type' => 'tunch', 'num' => $n];
        }
    }

    if (!$upload_error) {
        $baseClauses = [
            'customer_name         = ?',
            'address               = ?',
            'manufacturer          = ?',
            'quantity              = ?',
            'gold_purity_percent   = ?',
            'silver_purity_percent = ?',
            'karat                 = ?',
            'hallmark              = ?',
            'gold                  = ?',
            'joint                 = ?',
        ];
        $allClauses = array_merge($baseClauses, $elementSetClauses);
        $sql  = "UPDATE customer_reports SET " . implode(', ', $allClauses) . " WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);

        $types  = 'sssidddsdd' . $elementTypes . 'i';
        $params = array_merge(
            [$customer_name, $address, $manufacturer, $quantity,
             $gold_purity_percent, $silver_purity_percent, $karat, $hallmark, $gold_val, $joint_val],
            $elementVals, [$report_id]
        );
        $bindParams = [$types];
        foreach ($params as &$p) $bindParams[] = &$p;
        call_user_func_array([$stmt, 'bind_param'], $bindParams);

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            foreach ($photo_paths as $item) {
                $di = mysqli_prepare($conn,
                    "DELETE FROM report_images WHERE report_id=? AND img_type=? AND img_number=?");
                mysqli_stmt_bind_param($di, "isi", $report_id, $item['type'], $item['num']);
                mysqli_stmt_execute($di); mysqli_stmt_close($di);
                $ii = mysqli_prepare($conn,
                    "INSERT INTO report_images (report_id, img_type, img_number, img_path) VALUES (?,?,?,?)");
                mysqli_stmt_bind_param($ii, "isis", $report_id, $item['type'], $item['num'], $item['path']);
                mysqli_stmt_execute($ii); mysqli_stmt_close($ii);
            }
            header('Location: view_customer_reports.php');
            exit();
        } else {
            $error_message = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    // Re-populate on error
    $report['customer_name']         = $customer_name;
    $report['address']               = $address;
    $report['manufacturer']          = $manufacturer;
    $report['quantity']              = $quantity;
    $report['gold_purity_percent']   = $gold_purity_percent;
    $report['silver_purity_percent'] = $silver_purity_percent;
    $report['karat']                 = $karat;
    $report['hallmark']              = $hallmark;
    $report['gold']                  = $gold_val;
    $report['joint']                 = $joint_val;
    foreach ($ALL_ELEMENT_COLUMNS as $col) {
        $raw = $_POST['elem_' . $col] ?? '';
        $report[$col] = ($raw === '' || $raw === '--------') ? null : floatval($raw);
    }
}

$purityValue = $isSilver ? $report['silver_purity_percent'] : $report['gold_purity_percent'];
$purityLabel = $isSilver ? 'Silver Purity' : 'Gold Purity';

$elemOrder = ['silver','copper','zinc','cadmium','iridium','rhodium','cobalt',
              'germanium','palladium','platinum','iron','tin','lead','gallium',
              'bismuth','nickel','indium','tungsten','ruthenium','rhenium','osmium',
              'antimony','titanium','vanadium','manganese'];

require_once 'navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Report #<?= $report_id ?> — Rajaiswari</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:#f1f3f6; --surface:#ffffff; --s2:#fafbfc;
            --border:#e4e7ec; --bsoft:#f0f1f3;
            --t1:#111827; --t2:#374151; --t3:#6b7280; --t4:#9ca3af;
            --blue:#2563eb;   --blue-bg:#eff6ff;   --blue-b:#bfdbfe;
            --green:#059669;  --green-bg:#ecfdf5;  --green-b:#a7f3d0;
            --amber:#d97706;  --amber-bg:#fffbeb;  --amber-b:#fde68a;
            --red:#dc2626;    --red-bg:#fef2f2;    --red-b:#fecaca;
            --violet:#7c3aed; --violet-bg:#f5f3ff; --violet-b:#ddd6fe;
            --cyan:#0891b2;   --cyan-bg:#ecfeff;   --cyan-b:#a5f3fc;
            --r:10px; --rs:6px;
            --sh:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);
        }
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'DM Sans',sans-serif;font-size:14px;background:var(--bg);color:var(--t1);-webkit-font-smoothing:antialiased;min-height:100vh;}
        .page-shell{margin-left:200px;min-height:100vh;display:flex;flex-direction:column;}

        /* ── Top bar ── */
        .top-bar{position:sticky;top:0;z-index:200;height:54px;background:var(--surface);border-bottom:1px solid var(--border);box-shadow:var(--sh);display:flex;align-items:center;padding:0 22px;gap:12px;flex-shrink:0;}
        .tb-ico{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;}
        .tb-ico-vi{background:var(--violet-bg);color:var(--violet);}
        .tb-ico-am{background:var(--amber-bg);color:var(--amber);}
        .tb-ico-cy{background:var(--cyan-bg);color:var(--cyan);}
        .field-grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;}
        @media(max-width:700px){.field-grid-3{grid-template-columns:1fr;}}
        .hallmark-main{flex:1;padding:20px 22px 60px;display:flex;flex-direction:column;gap:14px;max-width:860px;}
        .tb-title{font-size:1.0625rem;font-weight:700;color:var(--t1);}
        .tb-sub{font-size:.78rem;color:var(--t4);}
        .tb-right{margin-left:auto;display:flex;gap:7px;align-items:center;}
        .type-chip{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;letter-spacing:.04em;}
        .chip-hallmark{background:var(--amber-bg);color:var(--amber);border:1px solid var(--amber-b);}
        .chip-tunch{background:var(--violet-bg);color:var(--violet);border:1px solid var(--violet-b);}
        .chip-silver{background:var(--cyan-bg);color:var(--cyan);border:1px solid var(--cyan-b);}

        /* ── Buttons ── */
        .btn-pos{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 14px;border:none;border-radius:var(--rs);font-family:inherit;font-size:.8125rem;font-weight:600;cursor:pointer;transition:all .15s;text-decoration:none;white-space:nowrap;}
        .btn-ghost{background:var(--surface);color:var(--t2);border:1.5px solid var(--border);}
        .btn-ghost:hover{background:var(--s2);border-color:#9ca3af;color:var(--t1);}
        .btn-green{background:var(--green);color:#fff;} .btn-green:hover{background:#047857;}
        .btn-amber{background:var(--amber);color:#fff;} .btn-amber:hover{background:#b45309;}
        .btn-violet{background:var(--violet);color:#fff;} .btn-violet:hover{background:#6d28d9;}

        /* ── Layout ── */
        .split-body{padding:18px 16px 60px;}
        .split-left{display:flex;flex-direction:column;gap:14px;}
        .split-right{display:flex;flex-direction:column;gap:14px;}
        .col-divider{border-left:2px solid var(--border);}

        /* single-col for hallmark */
        .single-body{padding:18px 16px 60px;max-width:780px;}

        /* ── Sections ── */
        .sec{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;}
        .sec-hd{display:flex;align-items:center;gap:9px;padding:11px 18px;background:var(--s2);border-bottom:1px solid var(--bsoft);}
        .sec-ico{width:26px;height:26px;border-radius:var(--rs);display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;}
        .si-vi{background:var(--violet-bg);color:var(--violet);}
        .si-bl{background:var(--blue-bg);color:var(--blue);}
        .si-am{background:var(--amber-bg);color:var(--amber);}
        .si-gr{background:var(--green-bg);color:var(--green);}
        .si-cy{background:var(--cyan-bg);color:var(--cyan);}
        .sec-title{font-size:.875rem;font-weight:700;color:var(--t1);}
        .sec-title-note{margin-left:auto;font-size:.75rem;font-weight:600;}
        .note-ed{color:var(--amber);}
        .note-ro{color:var(--t4);font-weight:500;}
        .sec-body{padding:18px;}

        /* ── Alerts ── */
        .pos-alert{display:flex;align-items:flex-start;gap:10px;padding:12px 16px;border-radius:var(--rs);font-size:.875rem;font-weight:500;}
        .pos-alert.danger{background:var(--red-bg);border:1px solid var(--red-b);border-left:3px solid var(--red);color:#991b1b;}
        .pos-alert.amber{background:var(--amber-bg);border:1px solid var(--amber-b);border-left:3px solid var(--amber);color:#92400e;}

        /* ── Form controls ── */
        .lbl{display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t3);margin-bottom:5px;}
        .req{color:var(--red);margin-left:2px;}
        .fc{width:100%;height:36px;padding:0 10px;border:1.5px solid var(--border);border-radius:var(--rs);font-family:inherit;font-size:.875rem;color:var(--t2);background:var(--surface);outline:none;transition:border-color .15s,box-shadow .15s;}
        .fc:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
        .fc.editable{background:var(--amber-bg);border-color:var(--amber-b);}
        .fc.editable:focus{border-color:var(--amber);box-shadow:0 0 0 3px rgba(217,119,6,.1);}
        .fc.readonly{background:var(--s2);color:var(--t3);cursor:default;border-color:var(--bsoft);}
        textarea.fc{height:auto;padding:8px 10px;resize:vertical;}
        .xrf-textarea{font-family:'DM Mono',monospace;font-size:12px;line-height:1.6;}
        .parse-btn{margin-top:8px;}

        /* ── Photo ── */
        .photo-upload-box{display:flex;flex-direction:column;gap:6px;}
        .photo-preview{width:100%;height:110px;object-fit:cover;border-radius:var(--rs);border:1px solid var(--border);display:none;margin-top:6px;}

        /* ── Form footer ── */
        .form-actions{display:flex;justify-content:flex-end;gap:8px;padding:14px 18px;background:var(--s2);border-top:1px solid var(--border);}

        /* ── Hallmark value input ── */
        .hallmark-input{width:100%;height:56px;padding:0 14px;border:2px solid var(--border);border-radius:var(--rs);font-family:'DM Mono',monospace;font-size:1.5rem;font-weight:800;color:var(--t1);letter-spacing:.06em;outline:none;transition:border-color .15s,box-shadow .15s;text-align:center;background:var(--amber-bg);border-color:var(--amber-b);}
        .hallmark-input:focus{border-color:var(--amber);box-shadow:0 0 0 3px rgba(217,119,6,.12);}

        /* ── Responsive ── */
        @media(max-width:991.98px){
            .page-shell{margin-left:0;}
            .top-bar{top:52px;}
            .col-divider{border-left:none;border-top:2px solid var(--border);padding-top:14px;margin-top:0;}
            .single-body{max-width:100%;}
        }
        @media print{.page-shell{margin-left:0;}.split-body,.single-body{display:none!important;}}
    </style>
</head>
<body>
<div class="page-shell">

<!-- ══ TOP BAR ══ -->
<header class="top-bar">
    <div class="tb-ico <?= $isHallmark ? 'tb-ico-cy' : 'tb-ico-vi' ?>">
        <i class="fas <?= $isHallmark ? 'fa-stamp' : 'fa-pen-to-square' ?>"></i>
    </div>
    <div>
        <div class="tb-title">Edit <?= $isHallmark ? 'Hallmark' : 'Tunch' ?> Report</div>
        <div class="tb-sub">
            <?= $isHallmark ? 'Update customer details and hallmark value' : 'Update purity, element composition and XRF data' ?>
        </div>
    </div>
    <div class="tb-right">
        <?php if ($isHallmark): ?>
            <span class="type-chip chip-hallmark"><i class="fas fa-stamp" style="font-size:.55rem;"></i> Hallmark</span>
        <?php elseif ($isSilver): ?>
            <span class="type-chip chip-silver"><i class="fas fa-flask" style="font-size:.55rem;"></i> Tunch · Silver</span>
        <?php else: ?>
            <span class="type-chip chip-tunch"><i class="fas fa-flask" style="font-size:.55rem;"></i> Tunch · Gold</span>
        <?php endif; ?>
        <a href="view_customer_reports.php" class="btn-pos btn-ghost">
            <i class="fas fa-arrow-left" style="font-size:.6rem;"></i> Back
        </a>
        <?php if ($isHallmark): ?>
        <a href="create_hallmark_report.php?report_id=<?= $report_id ?>" class="btn-pos btn-ghost">
            <i class="fas fa-eye" style="font-size:.6rem;"></i> View Report
        </a>
        <?php else: ?>
        <a href="create_tunch_report.php?report_id=<?= $report_id ?>" class="btn-pos btn-ghost">
            <i class="fas fa-eye" style="font-size:.6rem;"></i> View Report
        </a>
        <?php endif; ?>
    </div>
</header>

<!-- ══ ALERTS ══ -->
<?php if (isset($error_message) || $upload_error): ?>
<div style="padding:14px 16px 0;">
    <div class="pos-alert danger">
        <i class="fas fa-circle-xmark" style="font-size:.9rem;flex-shrink:0;margin-top:1px;"></i>
        <?= htmlspecialchars($error_message ?? $upload_error) ?>
    </div>
</div>
<?php endif; ?>


<?php /* ══════════════════════════════════════════════════════════════
       ║  HALLMARK BRANCH — matches create_hallmark_report.php UI
       ══════════════════════════════════════════════════════════════ */ ?>
<?php if ($isHallmark): ?>

<div class="hallmark-main">
<form method="POST" id="editForm" enctype="multipart/form-data">

    <!-- Step 1 — Order Info (read-only, like item-card info block) -->
    <div class="sec">
        <div class="sec-hd">
            <span class="sec-ico si-cy"><i class="fas fa-lock"></i></span>
            <span class="sec-title">Step 1 — Order Information</span>
            <span style="margin-left:auto;font-size:.75rem;color:var(--t4);font-weight:500;">
                <i class="fas fa-lock" style="font-size:.55rem;"></i> Read-only
            </span>
        </div>
        <div class="sec-body">
            <!-- Styled like an item card to match create page aesthetic -->
            <div style="display:flex;flex-wrap:wrap;gap:8px 20px;padding:8px 12px;border:2px solid var(--green);background:var(--green-bg);border-radius:var(--rs);">
                <span style="display:inline-flex;align-items:center;gap:4px;font-size:.8rem;color:var(--t2);">
                    <strong style="color:var(--t1);"><?= htmlspecialchars($report['item_name']) ?></strong>
                </span>
                <span style="display:inline-flex;align-items:center;gap:4px;font-size:.8rem;color:var(--t2);">
                    Bill No: <strong style="color:var(--t1);"><?= htmlspecialchars($report['order_id']) ?></strong>
                </span>
                <span style="display:inline-flex;align-items:center;gap:4px;font-size:.8rem;color:var(--t2);">
                    Report ID: <strong style="color:var(--t1);font-family:'DM Mono',monospace;">#<?= $report_id ?></strong>
                </span>
                <span style="display:inline-flex;align-items:center;gap:4px;font-size:.8rem;color:var(--blue);">
                    <?= htmlspecialchars($report['service_name']) ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Step 2 — Customer Information -->
    <div class="sec">
        <div class="sec-hd">
            <span class="sec-ico si-bl"><i class="fas fa-user"></i></span>
            <span class="sec-title">Step 2 — Customer Information</span>
            <span style="margin-left:auto;font-size:.75rem;color:var(--amber);font-weight:600;">
                <i class="fas fa-pen" style="font-size:.6rem;"></i> Highlighted fields are editable
            </span>
        </div>
        <div class="sec-body">
            <div class="field-grid-3">
                <div>
                    <label class="lbl">Customer Name <span class="req">*</span></label>
                    <input type="text" name="customer_name" class="fc editable" required
                           value="<?= htmlspecialchars($report['customer_name']) ?>">
                </div>
                <div>
                    <label class="lbl">Manufacturer</label>
                    <input type="text" name="manufacturer" class="fc editable"
                           value="<?= htmlspecialchars($report['manufacturer'] ?? '') ?>"
                           placeholder="e.g. Raj Jewellers">
                </div>
                <div>
                    <label class="lbl">Address</label>
                    <input type="text" name="address" class="fc editable"
                           value="<?= htmlspecialchars($report['address'] ?? '') ?>"
                           placeholder="Customer address">
                </div>
            </div>
        </div>
    </div>

    <!-- Step 3 — Sample Details (matches create_hallmark Step 3) -->
    <div class="sec">
        <div class="sec-hd">
            <span class="sec-ico si-am"><i class="fas fa-gem"></i></span>
            <span class="sec-title">Step 3 — Sample Details</span>
        </div>
        <div class="sec-body" style="display:flex;flex-direction:column;gap:18px;">

            <!-- Weight / Qty / Item (read-only) -->
            <div class="field-grid-3">
                <div>
                    <label class="lbl">Item Name</label>
                    <input type="text" class="fc" readonly
                           value="<?= htmlspecialchars($report['item_name']) ?>">
                    <div style="font-size:.72rem;color:var(--t4);margin-top:4px;">Auto-marked in checkbox</div>
                </div>
                <div>
                    <label class="lbl">Weight (gm) <span class="req">*</span></label>
                    <input type="number" name="weight" class="fc editable"
                           step="0.001" required value="<?= htmlspecialchars($report['weight']) ?>">
                </div>
                <div>
                    <label class="lbl">Quantity <span class="req">*</span></label>
                    <input type="number" name="quantity" class="fc editable"
                           required min="1" value="<?= htmlspecialchars($report['quantity'] ?: '1') ?>">
                </div>
            </div>

            <!-- Hallmark value — full-width below, same as create page -->
            <div>
                <label class="lbl">Hallmark Value <span class="req">*</span></label>
                <input type="text" name="hallmark" class="hallmark-input"
                       required value="<?= htmlspecialchars($report['hallmark'] ?? '') ?>"
                       placeholder="e.g. 21K RJ">
            </div>

            <!-- Photo upload — same layout as create page -->
            <div>
                <?php if ($upload_error): ?>
                <div class="pos-alert danger" style="margin-bottom:12px;">
                    <i class="fas fa-circle-xmark" style="font-size:.9rem;flex-shrink:0;margin-top:1px;"></i>
                    <?= htmlspecialchars($upload_error) ?>
                </div>
                <?php endif; ?>
                <div class="photo-upload-box" style="max-width:320px;">
                    <label class="lbl">Sample Photo
                        <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--t4);">Optional · replaces existing · max 200 KB</span>
                    </label>
                    <input type="file" name="photo" id="photo" class="fc"
                           accept=".jpg,.jpeg,.png,.webp"
                           style="height:auto;padding:6px 10px;cursor:pointer;"
                           onchange="previewHallmarkPhoto(this)">
                    <img id="photo_preview" class="photo-preview" alt="Photo preview">
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a href="view_customer_reports.php" class="btn-pos btn-ghost">
                <i class="fas fa-arrow-left" style="font-size:.6rem;"></i> Cancel
            </a>
            <button type="submit" form="editForm" class="btn-pos btn-green" style="height:38px;font-size:.9rem;">
                <i class="fas fa-floppy-disk" style="font-size:.7rem;"></i> Save Hallmark Report
            </button>
        </div>
    </div>

    <!-- hidden dummy fields to satisfy shared POST handler -->
    <input type="hidden" name="purity_percent" value="">
    <input type="hidden" name="karat" value="">
    <input type="hidden" name="gold_val" value="">
    <input type="hidden" name="joint_val" value="">
    <?php foreach ($ALL_ELEMENT_COLUMNS as $col): ?>
    <input type="hidden" name="elem_<?= $col ?>" value="">
    <?php endforeach; ?>

</form>
</div><!-- /hallmark-main -->


<?php /* ══════════════════════════════════════════════════════════════
       ║  TUNCH BRANCH — split left/right, matches create_tunch UI
       ══════════════════════════════════════════════════════════════ */ ?>
<?php else: ?>

<div class="split-body">
<form method="POST" id="editForm" enctype="multipart/form-data">
<div class="row g-0">

<!-- ══ LEFT COL ══ -->
<div class="col-12 col-lg-6 pe-lg-3">
<div class="split-left">

    <!-- Step 1 — Order Info (read-only) -->
    <div class="sec">
        <div class="sec-hd">
            <span class="sec-ico si-bl"><i class="fas fa-lock"></i></span>
            <span class="sec-title">Step 1 — Order Information</span>
            <span class="sec-title-note note-ro"><i class="fas fa-lock" style="font-size:.55rem;"></i> Read-only</span>
        </div>
        <div class="sec-body">
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
                <div>
                    <label class="lbl">Report ID</label>
                    <input type="text" class="fc readonly" value="#<?= $report_id ?>" readonly style="font-family:'DM Mono',monospace;">
                </div>
                <div>
                    <label class="lbl">Order ID</label>
                    <input type="text" class="fc readonly" value="<?= htmlspecialchars($report['order_id']) ?>" readonly style="font-family:'DM Mono',monospace;">
                </div>
                <div>
                    <label class="lbl">Service</label>
                    <input type="text" class="fc readonly" value="<?= htmlspecialchars($report['service_name']) ?>" readonly>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 2 — Customer & Sample Details -->
    <div class="sec">
        <div class="sec-hd">
            <span class="sec-ico si-am"><i class="fas fa-user"></i></span>
            <span class="sec-title">Step 2 — Customer &amp; Sample Details</span>
            <span class="sec-title-note note-ed"><i class="fas fa-pen" style="font-size:.6rem;"></i> Editable</span>
        </div>
        <div class="sec-body" style="display:flex;flex-direction:column;gap:14px;">
            <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px;">
                <div>
                    <label class="lbl">Customer Name <span class="req">*</span></label>
                    <input type="text" name="customer_name" class="fc editable" required
                           value="<?= htmlspecialchars($report['customer_name']) ?>">
                </div>
                <div>
                    <label class="lbl">Weight (gm)</label>
                    <input type="number" name="weight" class="fc editable" step="0.001"
                           value="<?= htmlspecialchars($report['weight']) ?>"
                           style="font-family:'DM Mono',monospace;">
                </div>
                <div>
                    <label class="lbl">Quantity</label>
                    <input type="number" name="quantity" class="fc editable" min="1"
                           value="<?= htmlspecialchars($report['quantity'] ?: '1') ?>">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label class="lbl">Manufacturer</label>
                    <input type="text" name="manufacturer" class="fc editable"
                           value="<?= htmlspecialchars($report['manufacturer'] ?? '') ?>"
                           placeholder="e.g. Raj Jewellers">
                </div>
                <div>
                    <label class="lbl">Address</label>
                    <input type="text" name="address" class="fc editable"
                           value="<?= htmlspecialchars($report['address'] ?? '') ?>"
                           placeholder="Customer address">
                </div>
            </div>
        </div>
    </div>

    <!-- Step 3 — XRF Paste -->
    <div class="sec">
        <div class="sec-hd">
            <span class="sec-ico si-vi"><i class="fas fa-paste"></i></span>
            <span class="sec-title">Step 3 — Re-paste XRF Data</span>
            <span style="margin-left:auto;font-size:.75rem;color:var(--t4);">Optional</span>
        </div>
        <div class="sec-body" style="display:flex;flex-direction:column;gap:12px;">
            <div class="pos-alert amber">
                <i class="fas fa-bolt" style="font-size:.9rem;flex-shrink:0;margin-top:1px;"></i>
                <span>Paste new XRF data and click <strong>Auto-Extract</strong> — values fill on the right automatically. Or edit each field directly.</span>
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

</div><!-- /split-left -->
</div><!-- /left col -->

<!-- ══ RIGHT COL ══ -->
<div class="col-12 col-lg-6 ps-lg-3 col-divider">
<div class="split-right">

    <!-- Step 4 — Testing Results -->
    <div class="sec">
        <div class="sec-hd">
            <span class="sec-ico si-vi"><i class="fas fa-flask"></i></span>
            <span class="sec-title">Step 4 — Testing Results</span>
            <span class="sec-title-note note-ed"><i class="fas fa-pen" style="font-size:.6rem;"></i> Editable</span>
        </div>
        <div class="sec-body" style="display:flex;flex-direction:column;gap:14px;">

            <!-- Purity & Karat -->
            <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--violet-bg);border:1px solid #ddd6fe;border-radius:7px;">
                <span style="font-size:.78rem;font-weight:700;color:var(--violet);text-transform:uppercase;letter-spacing:.05em;white-space:nowrap;">
                    <?= $purityLabel ?>
                </span>
                <input type="number" name="purity_percent" id="purity_percent" class="fc editable"
                       step="0.001" min="0" max="100" placeholder="0.000"
                       value="<?= htmlspecialchars($purityValue ?? '') ?>"
                       style="font-family:'DM Mono',monospace;height:30px;padding:0 6px;flex:1;">
                <span style="font-size:.82rem;color:var(--t2);white-space:nowrap;">Karat</span>
                <input type="number" name="karat" id="karat" class="fc editable"
                       step="0.01" min="0" max="24" placeholder="0.00"
                       value="<?= htmlspecialchars($report['karat'] ?? '') ?>"
                       style="font-family:'DM Mono',monospace;height:30px;padding:0 6px;width:80px;">
            </div>

            <!-- Element grid -->
            <table style="width:100%;border-collapse:collapse;">
            <?php $rows = array_chunk($elemOrder, 3); foreach ($rows as $row): ?>
            <tr>
                <?php foreach ($row as $el):
                    $val  = $report[$el] ?? null;
                    $disp = ($val === null) ? '' : number_format((float)$val, 3);
                ?>
                <td style="padding:2px 4px;white-space:nowrap;">
                    <div style="display:flex;align-items:center;gap:4px;">
                        <span style="font-size:.75rem;font-weight:600;color:var(--t2);min-width:68px;"><?= ucfirst($el) ?></span>
                        <input type="number" name="elem_<?= $el ?>" id="elem_<?= $el ?>"
                               class="fc editable"
                               step="0.001" min="0" max="100" placeholder="--------"
                               value="<?= htmlspecialchars($disp) ?>"
                               style="font-family:'DM Mono',monospace;height:30px;padding:0 6px;font-size:.78rem;">
                    </div>
                </td>
                <?php endforeach; ?>
                <?php for ($x = count($row); $x < 3; $x++): ?><td></td><?php endfor; ?>
            </tr>
            <?php endforeach; ?>
            </table>

            <!-- Gold & Joint -->
            <div style="display:flex;align-items:center;gap:24px;padding:10px 14px;background:var(--s2);border:1px solid var(--border);border-radius:7px;">
                <span style="font-size:.78rem;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.05em;white-space:nowrap;">Gold &amp; Joint</span>
                <div style="display:flex;align-items:center;gap:8px;flex:1;">
                    <span style="font-size:.82rem;color:var(--t2);white-space:nowrap;">Gold (K)</span>
                    <div style="flex:1;min-width:0;">
                        <input type="number" name="gold_val" id="gold_val" class="fc editable"
                               step="0.001" min="0" max="24" placeholder="0.000"
                               value="<?= htmlspecialchars($report['gold'] !== null ? number_format((float)$report['gold'],3) : '') ?>"
                               oninput="validateKarat(this)"
                               style="font-family:'DM Mono',monospace;height:30px;padding:0 6px;width:100%;">
                        <span id="gold_val_err" style="display:none;font-size:.7rem;color:var(--red);margin-top:2px;">Must be 0 – 24</span>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;flex:1;">
                    <span style="font-size:.82rem;color:var(--t2);white-space:nowrap;">Joint (K)</span>
                    <div style="flex:1;min-width:0;">
                        <input type="number" name="joint_val" id="joint_val" class="fc editable"
                               step="0.001" min="0" max="24" placeholder="0.000"
                               value="<?= htmlspecialchars($report['joint'] !== null ? number_format((float)$report['joint'],3) : '') ?>"
                               oninput="validateKarat(this)"
                               style="font-family:'DM Mono',monospace;height:30px;padding:0 6px;width:100%;">
                        <span id="joint_val_err" style="display:none;font-size:.7rem;color:var(--red);margin-top:2px;">Must be 0 – 24</span>
                    </div>
                </div>
            </div>

        </div>
    </div><!-- /Step 4 -->

    <!-- Step 5 — Sample Photos + Save -->
    <div class="sec">
        <div class="sec-hd">
            <span class="sec-ico si-am"><i class="fas fa-images"></i></span>
            <span class="sec-title">Step 5 — Sample Photos</span>
            <span style="margin-left:auto;font-size:.75rem;color:var(--t4);">Optional · max 200 KB each</span>
        </div>
        <div class="sec-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="photo-upload-box">
                    <label class="lbl">Photo 1 <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--t4);">(replaces existing)</span></label>
                    <input type="file" name="photo_1" id="photo_1" class="fc"
                           accept=".jpg,.jpeg,.png,.webp"
                           style="height:auto;padding:6px 10px;cursor:pointer;"
                           onchange="previewPhoto(this,'prev1')">
                    <img id="prev1" class="photo-preview" alt="Photo 1 preview">
                </div>
                <div class="photo-upload-box">
                    <label class="lbl">Photo 2 <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--t4);">(replaces existing)</span></label>
                    <input type="file" name="photo_2" id="photo_2" class="fc"
                           accept=".jpg,.jpeg,.png,.webp"
                           style="height:auto;padding:6px 10px;cursor:pointer;"
                           onchange="previewPhoto(this,'prev2')">
                    <img id="prev2" class="photo-preview" alt="Photo 2 preview">
                </div>
            </div>
        </div>
        <div class="form-actions">
            <a href="view_customer_reports.php" class="btn-pos btn-ghost">
                <i class="fas fa-arrow-left" style="font-size:.6rem;"></i> Cancel
            </a>
            <button type="submit" form="editForm" class="btn-pos btn-green" style="height:38px;font-size:.9rem;">
                <i class="fas fa-floppy-disk" style="font-size:.7rem;"></i> Save Tunch Report
            </button>
        </div>
    </div><!-- /Step 5 -->

</div><!-- /split-right -->
</div><!-- /right col -->

</div><!-- /row -->
</form>
</div><!-- /split-body -->

<?php endif; ?>

</div><!-- /page-shell -->

<script>
const GOLD_ELEMENTS   = <?= json_encode($GOLD_ELEMENTS) ?>;
const SILVER_ELEMENTS = <?= json_encode($SILVER_ELEMENTS) ?>;
const isSilver = <?= $isSilver ? 'true' : 'false' ?>;

function parseXRFData() {
    const input = document.getElementById('xrf_raw').value;

    const purityRx = isSilver
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
        if (match) field.value = match[1].replace('%','');
    });

    const goldM  = input.match(/\bGold\s*[:\s]+([\d.]+)/i);
    const jointM = input.match(/Joint\s*[:\s]+([\d.]+)/i);
    if (goldM)  document.getElementById('gold_val').value  = goldM[1];
    if (jointM) document.getElementById('joint_val').value = jointM[1];

    const btn  = document.querySelector('.parse-btn');
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> Extracted!';
    btn.style.background = '#059669';
    setTimeout(() => { btn.innerHTML = orig; btn.style.background = ''; }, 2000);
}

function validateKarat(inp) {
    const v   = parseFloat(inp.value);
    const err = document.getElementById(inp.id + '_err');
    if (!err) return;
    const bad = inp.value !== '' && (isNaN(v) || v < 0 || v > 24);
    err.style.display    = bad ? 'block' : 'none';
    inp.style.borderColor = bad ? 'var(--red)' : '';
}

function previewHallmarkPhoto(input) {
    const preview = document.getElementById('photo_preview');
    const file    = input.files[0];
    if (!file) { preview.style.display = 'none'; return; }
    if (file.size > 200 * 1024) {
        alert('Photo exceeds 200 KB limit. Please choose a smaller image.');
        input.value = '';
        preview.style.display = 'none';
        return;
    }
    const reader = new FileReader();
    reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
    reader.readAsDataURL(file);
}

function previewPhoto(input, previewId) {
    const preview = document.getElementById(previewId);
    const file    = input.files[0];
    if (!file) { preview.style.display = 'none'; return; }
    if (file.size > 200 * 1024) {
        alert('Photo exceeds 200 KB limit. Please choose a smaller image.');
        input.value = '';
        preview.style.display = 'none';
        return;
    }
    const reader = new FileReader();
    reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
    reader.readAsDataURL(file);
}
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>