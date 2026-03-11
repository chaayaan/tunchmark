<?php
require 'auth.php';
require 'mydb.php';

// Element orders — same as create_tunch_report.php
$GOLD_ELEMENTS   = ['Silver','Platinum','Bismuth','Copper','Palladium','Nickel','Zinc','Antimony','Indium','Cadmium','Iron','Titanium','Iridium','Tin','Ruthenium','Rhodium','Lead','Vanadium','Cobalt','Osmium','Manganese'];
$SILVER_ELEMENTS = ['Copper','Palladium','Nickel','Zinc','Antimony','Indium','Cadmium','Iron','Titanium','Iridium','Tin','Ruthenium','Rhodium','Lead','Vanadium','Cobalt','Osmium','Manganese'];
$ALL_ELEMENT_COLUMNS = ['silver','platinum','bismuth','copper','palladium','nickel','zinc','antimony','indium','cadmium','iron','titanium','iridium','tin','ruthenium','rhodium','lead','vanadium','cobalt','osmium','manganese','germanium','tungsten','gallium','rhenium'];
$COMMON_ELEMENTS = ['silver','copper','zinc','cadmium','nickel','palladium','indium','iridium','tin','ruthenium','rhodium','lead','cobalt','osmium','iron'];
$GOLD_SPECIFIC   = ['germanium','bismuth','platinum','tungsten','gallium','rhenium'];
$SILVER_SPECIFIC = ['antimony','titanium','vanadium','manganese'];

// ── Fetch report ──────────────────────────────────────────────────────────
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

// Detect type
$isHallmark = stripos($report['service_name'], 'hallmark') !== false;
$isSilver   = stripos($report['item_name'], 'silver') !== false
           || strpos($report['item_name'], 'চাঁদি') !== false
           || stripos($report['item_name'], 'rupa')   !== false;

// ── Handle form submission ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name']);
    $address       = trim($_POST['address']      ?? '');
    $manufacturer  = trim($_POST['manufacturer'] ?? '');
    $quantity      = max(1, (int)($_POST['quantity'] ?? 1));
    $hallmark      = trim($_POST['hallmark']     ?? '');

    // Purity — correct column per metal
    $gold_purity_percent   = null;
    $silver_purity_percent = null;
    if (!$isHallmark) {
        $purityRaw = floatval($_POST['purity_percent'] ?? 0);
        if ($isSilver) {
            $silver_purity_percent = $purityRaw ?: null;
        } else {
            $gold_purity_percent   = $purityRaw ?: null;
        }
    }
    $karat     = $isHallmark ? null : (floatval($_POST['karat']     ?? 0) ?: null);
    $gold_val  = $isHallmark ? null : (floatval($_POST['gold_val']  ?? 0) ?: null);
    $joint_val = $isHallmark ? null : (floatval($_POST['joint_val'] ?? 0) ?: null);

    // Element columns
    $elementSetClauses = [];
    $elementVals       = [];
    $elementTypes      = '';
    foreach ($ALL_ELEMENT_COLUMNS as $col) {
        $raw = $_POST['elem_' . $col] ?? '';
        $val = ($raw === '' || $raw === '--------') ? null : floatval($raw);
        $elementSetClauses[] = "`$col` = ?";
        $elementVals[]       = $val;
        $elementTypes       .= 'd';
    }

    // Build SET clause
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
    $sql = "UPDATE customer_reports SET " . implode(', ', $allClauses) . " WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    // Types: s s s i d d d s d d [d*25_elements] i
    $types  = 'sssidddsdd' . $elementTypes . 'i';
    $params = array_merge(
        [
            $customer_name,
            $address,
            $manufacturer,
            $quantity,
            $gold_purity_percent,
            $silver_purity_percent,
            $karat,
            $hallmark,
            $gold_val,
            $joint_val,
        ],
        $elementVals,
        [$report_id]
    );

    // Bind by reference (required for call_user_func_array)
    $bindParams = [$types];
    foreach ($params as &$p) $bindParams[] = &$p;
    call_user_func_array([$stmt, 'bind_param'], $bindParams);

    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        header('Location: view_customer_reports.php');
        exit();
    } else {
        $error_message = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        // Retain posted values so form doesn't reset on error
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
}

// Purity display
$purityValue = $isSilver ? $report['silver_purity_percent'] : $report['gold_purity_percent'];
$purityLabel = $isSilver ? 'Silver Purity (%)' : 'Gold Purity (%)';

require_once 'navbar.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Report #<?= htmlspecialchars($report['order_id']) ?> — Rajaiswari</title>
  <link rel="icon" type="image/png" href="favicon.png">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg:     #f1f3f6;
      --surface:#ffffff;
      --s2:     #fafbfc;
      --border: #e4e7ec;
      --bsoft:  #f0f1f3;
      --t1:     #111827;
      --t2:     #374151;
      --t3:     #6b7280;
      --t4:     #9ca3af;
      --blue:   #2563eb; --blue-bg: #eff6ff; --blue-b: #bfdbfe;
      --green:  #059669; --green-bg:#ecfdf5; --green-b:#a7f3d0;
      --amber:  #d97706; --amber-bg:#fffbeb; --amber-b:#fde68a;
      --red:    #dc2626; --red-bg:  #fef2f2; --red-b:  #fecaca;
      --violet: #7c3aed; --violet-bg:#f5f3ff;--violet-b:#ddd6fe;
      --cyan:   #0891b2; --cyan-bg: #ecfeff; --cyan-b: #a5f3fc;
      --r:10px; --rs:6px;
      --sh: 0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'DM Sans',-apple-system,sans-serif;font-size:14px;background:var(--bg);color:var(--t1);-webkit-font-smoothing:antialiased;min-height:100vh}

    /* ── Shell ── */
    .page-shell{margin-left:200px;min-height:100vh;display:flex;flex-direction:column}

    /* ── Top bar ── */
    .top-bar{position:sticky;top:0;z-index:200;height:54px;background:var(--surface);border-bottom:1px solid var(--border);box-shadow:var(--sh);display:flex;align-items:center;padding:0 22px;gap:10px;flex-shrink:0}
    .tb-ico{width:32px;height:32px;background:var(--violet-bg);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--violet);font-size:13px;flex-shrink:0}
    .tb-title{font-size:1.0625rem;font-weight:700;color:var(--t1);line-height:1.2}
    .tb-sub{font-size:.78rem;color:var(--t4)}
    .tb-right{margin-left:auto;display:flex;align-items:center;gap:8px}
    .order-badge{display:inline-flex;align-items:center;gap:5px;background:var(--s2);border:1px solid var(--border);border-radius:var(--rs);padding:4px 12px;font-family:'DM Mono',monospace;font-size:.85rem;font-weight:500;color:var(--t3)}
    .type-chip{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;letter-spacing:.04em}
    .chip-hallmark{background:var(--amber-bg);color:var(--amber);border:1px solid var(--amber-b)}
    .chip-tunch{background:var(--violet-bg);color:var(--violet);border:1px solid var(--violet-b)}
    .chip-silver{background:var(--cyan-bg);color:var(--cyan);border:1px solid var(--cyan-b)}
    .tb-back{display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 14px;background:var(--s2);border:1.5px solid var(--border);border-radius:var(--rs);font-size:.82rem;font-weight:600;color:var(--t2);text-decoration:none;transition:all .15s}
    .tb-back:hover{background:var(--border);color:var(--t1)}

    /* ── Main / Layout ── */
    .main{flex:1;padding:20px 22px 60px;display:flex;flex-direction:column;gap:14px}
    .layout{display:grid;grid-template-columns:1fr 280px;gap:14px;align-items:start}

    /* ── Section card ── */
    .sec{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden}
    .sec-hd{display:flex;align-items:center;gap:9px;padding:11px 18px;background:var(--s2);border-bottom:1px solid var(--bsoft)}
    .sec-ico{width:26px;height:26px;border-radius:var(--rs);display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0}
    .si-bl{background:var(--blue-bg);color:var(--blue)}
    .si-vi{background:var(--violet-bg);color:var(--violet)}
    .si-am{background:var(--amber-bg);color:var(--amber)}
    .si-gr{background:var(--green-bg);color:var(--green)}
    .si-cy{background:var(--cyan-bg);color:var(--cyan)}
    .sec-title{font-size:.875rem;font-weight:700;color:var(--t1)}
    .sec-badge{margin-left:auto;display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:20px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
    .badge-ro{background:var(--s2);color:var(--t4);border:1px solid var(--border)}
    .badge-ed{background:var(--amber-bg);color:var(--amber);border:1px solid var(--amber-b)}
    .sec-body{padding:18px}

    /* ── Controls ── */
    .lbl{display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t3);margin-bottom:5px}
    .req{color:var(--red);margin-left:2px}
    .fc{width:100%;height:36px;padding:0 10px;border:1.5px solid var(--border);border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.875rem;color:var(--t2);background:var(--surface);outline:none;transition:border-color .15s,box-shadow .15s}
    .fc:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.1)}
    .fc-ro{background:var(--s2);color:var(--t3);cursor:default;border-color:var(--bsoft)}
    .fc-ed{background:var(--amber-bg);border-color:var(--amber-b);color:var(--t1)}
    .fc-ed:focus{border-color:var(--amber);box-shadow:0 0 0 3px rgba(217,119,6,.12)}
    .fc-mono{font-family:'DM Mono',monospace;font-size:.85rem}
    textarea.fc{height:auto;padding:8px 10px;resize:vertical}

    /* ── Grids ── */
    .g2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .g3{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
    .g4{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
    @media(max-width:700px){.g2,.g3,.g4{grid-template-columns:1fr 1fr}}

    /* ── Element group ── */
    .elem-group-title{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--t3);padding-bottom:6px;margin-bottom:10px;border-bottom:1px solid var(--bsoft)}

    /* ── XRF ── */
    .xrf-ta{font-family:'DM Mono',monospace;font-size:12px;line-height:1.6}

    /* ── Alerts ── */
    .alert{display:flex;align-items:flex-start;gap:10px;padding:12px 16px;border-radius:var(--rs);font-size:.875rem;font-weight:500}
    .alert-red{background:var(--red-bg);border:1px solid var(--red-b);border-left:3px solid var(--red);color:#991b1b}
    .alert-amber{background:var(--amber-bg);border:1px solid var(--amber-b);border-left:3px solid var(--amber);color:#92400e}

    /* ── Buttons ── */
    .action-bar{display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:13px 18px;background:var(--s2);border-top:1px solid var(--border)}
    .btn-ghost{display:inline-flex;align-items:center;gap:6px;height:36px;padding:0 16px;background:var(--surface);border:1.5px solid var(--border);border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.875rem;font-weight:600;color:var(--t2);text-decoration:none;cursor:pointer;transition:all .15s}
    .btn-ghost:hover{background:var(--s2);border-color:#9ca3af;color:var(--t1)}
    .btn-violet{display:inline-flex;align-items:center;gap:6px;height:36px;padding:0 22px;background:var(--violet);border:none;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.9rem;font-weight:700;color:#fff;cursor:pointer;transition:background .15s}
    .btn-violet:hover{background:#6d28d9}
    .btn-parse{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 14px;background:var(--violet);border:none;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.8125rem;font-weight:600;color:#fff;cursor:pointer;transition:background .15s;margin-top:8px}
    .btn-parse:hover{background:#6d28d9}

    /* ── Sidebar ── */
    .info-row{display:flex;align-items:flex-start;gap:10px;padding:9px 0;border-bottom:1px solid var(--bsoft)}
    .info-row:last-child{border-bottom:none;padding-bottom:0}
    .info-ico{width:26px;height:26px;border-radius:var(--rs);display:flex;align-items:center;justify-content:center;font-size:10px;flex-shrink:0;margin-top:1px}
    .info-lbl{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t4);margin-bottom:2px}
    .info-val{font-size:.875rem;font-weight:600;color:var(--t1)}
    .info-val.mono{font-family:'DM Mono',monospace}
    .quick-link{display:flex;align-items:center;gap:8px;padding:9px 0;border-bottom:1px solid var(--bsoft);text-decoration:none;font-size:.875rem;font-weight:600;color:var(--t2);transition:color .15s}
    .quick-link:last-child{border-bottom:none;padding-bottom:0}
    .quick-link:hover{color:var(--blue)}
    .ql-ico{width:24px;height:24px;border-radius:var(--rs);display:flex;align-items:center;justify-content:center;font-size:10px;flex-shrink:0}
    .note-box{background:var(--amber-bg);border:1px solid var(--amber-b);border-left:3px solid var(--amber);border-radius:var(--rs);padding:11px 14px;font-size:.82rem;color:#92400e;line-height:1.5}
    .note-box strong{color:var(--amber)}

    /* ── Responsive ── */
    @media(max-width:1100px){.layout{grid-template-columns:1fr}}
    @media(max-width:991.98px){.page-shell{margin-left:0}.top-bar{top:52px}.main{padding:14px 14px 50px}}
  </style>
</head>
<body>

<div class="page-shell">

  <header class="top-bar">
    <div class="tb-ico"><i class="fas fa-pen-to-square"></i></div>
    <div>
      <div class="tb-title">Edit Customer Report</div>
      <div class="tb-sub">
        <?= $isHallmark ? 'Edit hallmark details' : 'Full edit — including element composition &amp; XRF data' ?>
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
      <div class="order-badge">
        <i class="fas fa-hashtag" style="font-size:.6rem;"></i>
        Order #<?= htmlspecialchars($report['order_id']) ?>
      </div>
      <a href="view_customer_reports.php" class="tb-back">
        <i class="fas fa-arrow-left" style="font-size:.65rem;"></i> Back
      </a>
    </div>
  </header>

  <div class="main">

    <?php if (isset($error_message)): ?>
    <div class="alert alert-red">
      <i class="fas fa-circle-xmark" style="font-size:.9rem;flex-shrink:0;margin-top:1px;"></i>
      Error saving: <?= htmlspecialchars($error_message) ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="editForm">
    <div class="layout">

      <!-- ══ LEFT COLUMN ══════════════════════════════ -->
      <div style="display:flex;flex-direction:column;gap:14px;">

        <!-- 1. Read-only info -->
        <div class="sec">
          <div class="sec-hd">
            <span class="sec-ico si-bl"><i class="fas fa-lock"></i></span>
            <span class="sec-title">Order Information</span>
            <span class="sec-badge badge-ro"><i class="fas fa-lock" style="font-size:.5rem;"></i> Read-only</span>
          </div>
          <div class="sec-body">
            <div class="g3">
              <div>
                <label class="lbl">Report ID</label>
                <input type="text" class="fc fc-ro fc-mono" value="#<?= $report_id ?>" readonly>
              </div>
              <div>
                <label class="lbl">Order ID</label>
                <input type="text" class="fc fc-ro fc-mono" value="<?= htmlspecialchars($report['order_id']) ?>" readonly>
              </div>
              <div>
                <label class="lbl">Weight (gm)</label>
                <input type="text" class="fc fc-ro fc-mono" value="<?= htmlspecialchars($report['weight']) ?>" readonly>
              </div>
              <div style="grid-column:1/3">
                <label class="lbl">Item Name</label>
                <input type="text" class="fc fc-ro" value="<?= htmlspecialchars($report['item_name']) ?>" readonly>
              </div>
              <div>
                <label class="lbl">Service</label>
                <input type="text" class="fc fc-ro" value="<?= htmlspecialchars($report['service_name']) ?>" readonly>
              </div>
            </div>
          </div>
        </div>

        <!-- 2. Customer details -->
        <div class="sec">
          <div class="sec-hd">
            <span class="sec-ico si-am"><i class="fas fa-user"></i></span>
            <span class="sec-title">Customer Details</span>
            <span class="sec-badge badge-ed"><i class="fas fa-pen" style="font-size:.5rem;"></i> Editable</span>
          </div>
          <div class="sec-body" style="display:flex;flex-direction:column;gap:14px;">
            <div>
              <label class="lbl">Customer Name <span class="req">*</span></label>
              <input type="text" class="fc fc-ed" name="customer_name"
                     value="<?= htmlspecialchars($report['customer_name']) ?>" required>
            </div>
            <div class="g2">
              <div>
                <label class="lbl">Manufacturer / Made By</label>
                <input type="text" class="fc fc-ed" name="manufacturer"
                       value="<?= htmlspecialchars($report['manufacturer'] ?? '') ?>"
                       placeholder="e.g. Raj Jewellers">
              </div>
              <div>
                <label class="lbl">Address</label>
                <input type="text" class="fc fc-ed" name="address"
                       value="<?= htmlspecialchars($report['address'] ?? '') ?>"
                       placeholder="Customer address">
              </div>
            </div>
            <div style="max-width:150px;">
              <label class="lbl">Quantity <span class="req">*</span></label>
              <input type="number" class="fc fc-ed" name="quantity"
                     value="<?= htmlspecialchars($report['quantity'] ?: '1') ?>" min="1" required>
            </div>
          </div>
        </div>

        <?php if ($isHallmark): ?>
        <!-- ══ HALLMARK ONLY ══════════════════════════ -->
        <div class="sec">
          <div class="sec-hd">
            <span class="sec-ico si-am"><i class="fas fa-stamp"></i></span>
            <span class="sec-title">Hallmark Value</span>
            <span class="sec-badge badge-ed"><i class="fas fa-pen" style="font-size:.5rem;"></i> Editable</span>
          </div>
          <div class="sec-body">
            <label class="lbl">Hallmark</label>
            <input type="text" class="fc fc-ed fc-mono" name="hallmark"
                   value="<?= htmlspecialchars($report['hallmark'] ?? '') ?>"
                   placeholder="e.g. 21K RJ, 916 BIS"
                   style="font-size:1.25rem;height:50px;font-weight:700;text-align:center;letter-spacing:.08em;">
            <!-- hidden dummies so POST keys exist -->
            <input type="hidden" name="purity_percent" value="">
            <input type="hidden" name="karat" value="">
            <input type="hidden" name="gold_val" value="">
            <input type="hidden" name="joint_val" value="">
            <?php foreach ($ALL_ELEMENT_COLUMNS as $col): ?>
            <input type="hidden" name="elem_<?= $col ?>" value="">
            <?php endforeach; ?>
          </div>
          <div class="action-bar">
            <a href="view_customer_reports.php" class="btn-ghost">
              <i class="fas fa-arrow-left" style="font-size:.6rem;"></i> Cancel
            </a>
            <button type="submit" class="btn-violet">
              <i class="fas fa-floppy-disk" style="font-size:.7rem;"></i> Save Changes
            </button>
          </div>
        </div>

        <?php else: ?>
        <!-- ══ TUNCH: purity + XRF + elements ════════ -->

        <!-- 3. Purity & Karat -->
        <div class="sec">
          <div class="sec-hd">
            <span class="sec-ico <?= $isSilver ? 'si-cy' : 'si-vi' ?>"><i class="fas fa-vial"></i></span>
            <span class="sec-title">Purity &amp; Karat</span>
            <span class="sec-badge badge-ed"><i class="fas fa-pen" style="font-size:.5rem;"></i> Editable</span>
          </div>
          <div class="sec-body">
            <div class="g2">
              <div>
                <label class="lbl"><?= $purityLabel ?> <span class="req">*</span></label>
                <input type="number" class="fc fc-ed fc-mono" name="purity_percent" id="purity_percent"
                       step="0.001" min="0" max="100"
                       value="<?= htmlspecialchars($purityValue ?? '') ?>"
                       placeholder="e.g. 75.470" required>
              </div>
              <div>
                <label class="lbl">Karat <span class="req">*</span></label>
                <input type="number" class="fc fc-ed fc-mono" name="karat" id="karat"
                       step="0.01" min="0" max="24"
                       value="<?= htmlspecialchars($report['karat'] ?? '') ?>"
                       placeholder="e.g. 18.11" required>
              </div>
            </div>
          </div>
        </div>

        <!-- 4. XRF paste -->
        <div class="sec">
          <div class="sec-hd">
            <span class="sec-ico si-vi"><i class="fas fa-paste"></i></span>
            <span class="sec-title">Re-paste XRF Data</span>
            <span style="margin-left:auto;font-size:.75rem;color:var(--t4);">Optional — auto-fills elements</span>
          </div>
          <div class="sec-body" style="display:flex;flex-direction:column;gap:10px;">
            <div class="alert alert-amber">
              <i class="fas fa-bolt" style="font-size:.9rem;flex-shrink:0;margin-top:1px;"></i>
              <span>Paste new XRF data and click <strong>Auto-Extract</strong> to update all element values, purity, and karat. Or edit each field directly below.</span>
            </div>
            <div>
              <label class="lbl">Raw XRF Data</label>
              <textarea id="xrf_raw" class="fc xrf-ta" rows="7"
                placeholder="Paste full XRF analysis text here e.g.&#10;Gold Purity : 75.47%  Karat : 18.11&#10;Silver  1.610  Copper  21.950  Zinc  0.040 ..."></textarea>
            </div>
            <div>
              <button type="button" class="btn-parse" onclick="parseXRFData()">
                <i class="fas fa-wand-magic-sparkles" style="font-size:.65rem;"></i> Auto-Extract Values
              </button>
            </div>
          </div>
        </div>

        <!-- 5. Element Composition -->
        <div class="sec">
          <div class="sec-hd">
            <span class="sec-ico si-gr"><i class="fas fa-atom"></i></span>
            <span class="sec-title">Element Composition</span>
            <span class="sec-badge badge-ed"><i class="fas fa-pen" style="font-size:.5rem;"></i> Editable</span>
          </div>
          <div class="sec-body" style="display:flex;flex-direction:column;gap:20px;">

            <!-- Common elements -->
            <div>
              <div class="elem-group-title">Common Elements</div>
              <div class="g4">
                <?php foreach ($COMMON_ELEMENTS as $el):
                  $val = $report[$el] ?? null;
                  $disp = ($val === null) ? '' : rtrim(rtrim(number_format((float)$val, 3), '0'), '.');
                  // keep 3 decimals always for consistency
                  $disp = ($val === null) ? '' : number_format((float)$val, 3);
                ?>
                <div>
                  <label class="lbl"><?= ucfirst($el) ?></label>
                  <input type="text" name="elem_<?= $el ?>" id="elem_<?= $el ?>"
                         class="fc fc-ed fc-mono"
                         value="<?= htmlspecialchars($disp) ?>"
                         placeholder="--------">
                </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Gold-specific -->
            <div>
              <div class="elem-group-title">Gold-Specific Elements</div>
              <div class="g4">
                <?php foreach ($GOLD_SPECIFIC as $el):
                  $val = $report[$el] ?? null;
                  $disp = ($val === null) ? '' : number_format((float)$val, 3);
                ?>
                <div>
                  <label class="lbl"><?= ucfirst($el) ?></label>
                  <input type="text" name="elem_<?= $el ?>" id="elem_<?= $el ?>"
                         class="fc fc-ed fc-mono"
                         value="<?= htmlspecialchars($disp) ?>"
                         placeholder="--------">
                </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Silver-specific -->
            <div>
              <div class="elem-group-title">Silver-Specific Elements</div>
              <div class="g4">
                <?php foreach ($SILVER_SPECIFIC as $el):
                  $val = $report[$el] ?? null;
                  $disp = ($val === null) ? '' : number_format((float)$val, 3);
                ?>
                <div>
                  <label class="lbl"><?= ucfirst($el) ?></label>
                  <input type="text" name="elem_<?= $el ?>" id="elem_<?= $el ?>"
                         class="fc fc-ed fc-mono"
                         value="<?= htmlspecialchars($disp) ?>"
                         placeholder="--------">
                </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Gold % / Joint % -->
            <div>
              <div class="elem-group-title">Gold &amp; Joint</div>
              <div class="g2">
                <div>
                  <label class="lbl">Gold (%)</label>
                  <input type="number" name="gold_val" id="gold_val"
                         class="fc fc-ed fc-mono" step="0.001" placeholder="e.g. 0.000"
                         value="<?= htmlspecialchars($report['gold'] !== null ? number_format((float)$report['gold'], 3) : '') ?>">
                </div>
                <div>
                  <label class="lbl">Joint (%)</label>
                  <input type="number" name="joint_val" id="joint_val"
                         class="fc fc-ed fc-mono" step="0.001" placeholder="e.g. 0.000"
                         value="<?= htmlspecialchars($report['joint'] !== null ? number_format((float)$report['joint'], 3) : '') ?>">
                </div>
              </div>
            </div>

            <!-- Hallmark (optional for tunch) -->
            <div>
              <div class="elem-group-title">Hallmark <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--t4);">(optional)</span></div>
              <input type="text" class="fc fc-ed fc-mono" name="hallmark"
                     value="<?= htmlspecialchars($report['hallmark'] ?? '') ?>"
                     placeholder="Leave blank if none">
            </div>

          </div>
          <div class="action-bar">
            <a href="view_customer_reports.php" class="btn-ghost">
              <i class="fas fa-arrow-left" style="font-size:.6rem;"></i> Cancel
            </a>
            <button type="submit" class="btn-violet">
              <i class="fas fa-floppy-disk" style="font-size:.7rem;"></i> Save Changes
            </button>
          </div>
        </div>

        <?php endif; ?>

      </div><!-- /left -->

      <!-- ══ RIGHT SIDEBAR ════════════════════════════ -->
      <div style="display:flex;flex-direction:column;gap:14px;">

        <!-- Record info -->
        <div class="sec">
          <div class="sec-hd">
            <span class="sec-ico si-gr"><i class="fas fa-circle-info"></i></span>
            <span class="sec-title">Record Info</span>
          </div>
          <div class="sec-body">
            <div class="info-row">
              <div class="info-ico si-bl"><i class="fas fa-hashtag"></i></div>
              <div>
                <div class="info-lbl">Report ID</div>
                <div class="info-val mono">#<?= $report_id ?></div>
              </div>
            </div>
            <div class="info-row">
              <div class="info-ico si-vi"><i class="fas fa-calendar-plus"></i></div>
              <div>
                <div class="info-lbl">Created</div>
                <div class="info-val"><?= date('d M Y', strtotime($report['created_at'])) ?></div>
                <div style="font-size:.78rem;color:var(--t4);"><?= date('h:i A', strtotime($report['created_at'])) ?></div>
              </div>
            </div>
            <div class="info-row">
              <div class="info-ico si-am"><i class="fas fa-clock-rotate-left"></i></div>
              <div>
                <div class="info-lbl">Last Updated</div>
                <div class="info-val"><?= date('d M Y', strtotime($report['updated_at'])) ?></div>
                <div style="font-size:.78rem;color:var(--t4);"><?= date('h:i A', strtotime($report['updated_at'])) ?></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Current snapshot (tunch only) -->
        <?php if (!$isHallmark): ?>
        <div class="sec">
          <div class="sec-hd">
            <span class="sec-ico <?= $isSilver ? 'si-cy' : 'si-am' ?>"><i class="fas fa-chart-simple"></i></span>
            <span class="sec-title">Saved Values</span>
          </div>
          <div class="sec-body">
            <div class="info-row">
              <div class="info-ico <?= $isSilver ? 'si-cy' : 'si-am' ?>"><i class="fas fa-circle-dot"></i></div>
              <div>
                <div class="info-lbl"><?= $isSilver ? 'Silver' : 'Gold' ?> Purity</div>
                <div class="info-val mono">
                  <?= $purityValue !== null ? number_format((float)$purityValue, 3).'%' : '—' ?>
                </div>
              </div>
            </div>
            <div class="info-row">
              <div class="info-ico si-vi"><i class="fas fa-gem"></i></div>
              <div>
                <div class="info-lbl">Karat</div>
                <div class="info-val mono">
                  <?= $report['karat'] !== null ? number_format((float)$report['karat'], 2).'K' : '—' ?>
                </div>
              </div>
            </div>
            <?php if ($report['gold'] !== null || $report['joint'] !== null): ?>
            <div class="info-row">
              <div class="info-ico si-gr"><i class="fas fa-percent"></i></div>
              <div>
                <div class="info-lbl">Gold / Joint</div>
                <div class="info-val mono">
                  <?= $report['gold']  !== null ? number_format((float)$report['gold'], 3)  : '—' ?>
                  /
                  <?= $report['joint'] !== null ? number_format((float)$report['joint'], 3) : '—' ?>
                </div>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Quick links -->
        <div class="sec">
          <div class="sec-hd">
            <span class="sec-ico si-bl"><i class="fas fa-arrow-up-right-from-square"></i></span>
            <span class="sec-title">Quick Links</span>
          </div>
          <div class="sec-body">
            <?php if ($isHallmark): ?>
            <a href="create_hallmark_report.php?report_id=<?= $report_id ?>" class="quick-link">
              <span class="ql-ico si-am"><i class="fas fa-eye"></i></span> View Hallmark Report
            </a>
            <?php else: ?>
            <a href="create_tunch_report.php?report_id=<?= $report_id ?>" class="quick-link">
              <span class="ql-ico si-vi"><i class="fas fa-eye"></i></span> View Tunch Report
            </a>
            <?php endif; ?>
            <a href="report_varification.php?id=<?= $report_id ?>" target="_blank" class="quick-link">
              <span class="ql-ico si-gr"><i class="fas fa-qrcode"></i></span> Open Verification Page
            </a>
            <a href="view_customer_reports.php" class="quick-link">
              <span class="ql-ico si-bl"><i class="fas fa-list"></i></span> All Reports
            </a>
          </div>
        </div>

        <!-- Note -->
        <div class="note-box">
          <strong><i class="fas fa-triangle-exclamation" style="margin-right:4px;"></i> Note</strong><br>
          <?php if ($isHallmark): ?>
            Edit customer details and the hallmark value. Weight and item name are locked to preserve order integrity.
          <?php else: ?>
            All fields are fully editable including element composition. Paste new XRF data above to auto-fill, or edit each element field directly. Weight and item name are locked.
          <?php endif; ?>
        </div>

      </div><!-- /sidebar -->

    </div><!-- /layout -->
    </form>

  </div><!-- /main -->
</div><!-- /page-shell -->

<script>
const GOLD_ELEMENTS   = <?= json_encode($GOLD_ELEMENTS) ?>;
const SILVER_ELEMENTS = <?= json_encode($SILVER_ELEMENTS) ?>;
const isSilver = <?= $isSilver ? 'true' : 'false' ?>;

function parseXRFData() {
    const input = document.getElementById('xrf_raw').value;

    // Purity
    const purityRx = isSilver
        ? /Silver\s+Purity\s*[:\s]+([\d.]+)\s*%/i
        : /Gold\s+Purity\s*[:\s]+([\d.]+)\s*%/i;
    const purityM = input.match(purityRx);
    const purField = document.getElementById('purity_percent');
    if (purityM && purField) purField.value = purityM[1];

    // Karat
    const karatM  = input.match(/Karat\s*[:\s]+([\d.]+)/i);
    const karField = document.getElementById('karat');
    if (karatM && karField) karField.value = karatM[1];

    // Elements
    const allElements = [...new Set([...GOLD_ELEMENTS, ...SILVER_ELEMENTS])];
    allElements.forEach(el => {
        const field = document.getElementById('elem_' + el.toLowerCase());
        if (!field) return;
        const rx    = new RegExp(el + '\\s*[:\\s]+([\\d.]+|--------)', 'i');
        const match = input.match(rx);
        if (match) field.value = match[1].replace('%','');
    });

    // Gold / Joint
    const goldM  = input.match(/\bGold\s*[:\s]+([\d.]+)/i);
    const jointM = input.match(/Joint\s*[:\s]+([\d.]+)/i);
    const gf = document.getElementById('gold_val');
    const jf = document.getElementById('joint_val');
    if (goldM  && gf) gf.value = goldM[1];
    if (jointM && jf) jf.value = jointM[1];

    // Visual feedback
    const btn  = document.querySelector('.btn-parse');
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> Extracted!';
    btn.style.background = '#059669';
    setTimeout(() => { btn.innerHTML = orig; btn.style.background = ''; }, 2000);
}
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>