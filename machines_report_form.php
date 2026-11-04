<?php
require 'auth.php';
require 'mydb.php';
require_engineering();               // admin + engineer employee only — covers page load AND form submission

if (isset($conn)) { $conn->set_charset('utf8mb4'); }
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

const MR_MAX_PARTS = 15;             // keeps the PDF on a single A4 page

if (empty($_SESSION['csrf_mr'])) $_SESSION['csrf_mr'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_mr'];

$reportId = (int)($_GET['id'] ?? 0);
$isEdit   = $reportId > 0;
$uid      = (int)$_SESSION['user_id'];
$errors   = [];

// ---------- Load existing report ----------
$report = null; $savedActions = []; $savedParts = [];
if ($isEdit) {
    $stmt = $conn->prepare("SELECT * FROM machines_report WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $reportId);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$report) { header("Location: machines_report_list.php?msg=notfound"); exit; }

    // snapshot of actions already on this report: action_list_id => text
    $stmt = $conn->prepare("SELECT action_list_id, action_text FROM machines_report_action WHERE report_id = ? AND action_list_id IS NOT NULL ORDER BY sort_order, id");
    $stmt->bind_param("i", $reportId);
    $stmt->execute();
    foreach ($stmt->get_result() as $a) $savedActions[(int)$a['action_list_id']] = $a['action_text'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT part_code, part_description, quantity, billing_instructions FROM machines_report_spareparts WHERE report_id = ? ORDER BY sr_no, id");
    $stmt->bind_param("i", $reportId);
    $stmt->execute();
    $savedParts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ---------- Helpers ----------
$validDate = function (string $d): bool { $o = DateTime::createFromFormat('Y-m-d', $d); return $o && $o->format('Y-m-d') === $d; };
$checkLen  = function (string $v, int $max, string $label) use (&$errors) { if (mb_strlen($v) > $max) $errors[] = "$label must be at most $max characters."; };
$post      = function (string $k): string { $v = $_POST[$k] ?? ''; return is_string($v) ? trim($v) : ''; };

// ---------- Save ----------
$selectedIds = array_keys($savedActions);
$partsRows   = $savedParts;
$f = $report ?: [
    'report_type' => '', 'report_date' => date('Y-m-d'), 'customer_name' => '', 'customer_address' => '', 'contact_person' => '',
    'designation' => '', 'telephone' => '', 'email' => '', 'work_start_date' => '', 'completion_date' => '', 'travel_time_hrs' => '',
    'working_hrs' => '', 'instrument_type' => '', 'model' => '', 'instrument_sr_no' => '', 'visit_type' => 'Physical', 'observations' => '',
    'additional_activities' => '', 'engineer_remarks' => '', 'customer_remarks' => '',
    'engineer_name' => $isEdit ? '' : ($_SESSION['name'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Invalid or expired form token. Please go back, refresh the page and try again.');
    }

    foreach (array_keys($f) as $k) $f[$k] = $post($k);

    if (!in_array($f['report_type'], ['installation', 'service'], true)) $errors[] = "Please select a report type.";
    if ($f['report_date'] === '' || !$validDate($f['report_date']))     $errors[] = "A valid report date is required.";
    if ($f['customer_name'] === '')                                     $errors[] = "Customer name is required.";
    if ($f['instrument_type'] === '')                                   $errors[] = "Instrument type is required.";
    foreach (['work_start_date' => 'Work start date', 'completion_date' => 'Completion date'] as $k => $lab)
        if ($f[$k] !== '' && !$validDate($f[$k])) $errors[] = "$lab is not a valid date.";
    if (!$errors && $f['work_start_date'] !== '' && $f['completion_date'] !== '' && $f['completion_date'] < $f['work_start_date'])
        $errors[] = "Completion date cannot be before the work start date.";
    if ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errors[] = "Email address is not valid.";
    if ($f['telephone'] !== '' && !preg_match('/^[0-9+\-()\s,\/]{5,40}$/', $f['telephone'])) $errors[] = "Telephone may contain only digits, spaces and + - ( ) , /";
    if (!in_array($f['visit_type'], ['Physical', 'Remote'], true)) $errors[] = "Invalid visit type.";

    $checkLen($f['customer_name'], 150, 'Customer name');   $checkLen($f['customer_address'], 400, 'Address');
    $checkLen($f['contact_person'], 100, 'Contact person'); $checkLen($f['designation'], 100, 'Designation');
    $checkLen($f['email'], 120, 'Email');                   $checkLen($f['travel_time_hrs'], 30, 'Travel time');
    $checkLen($f['working_hrs'], 30, 'Working hrs');        $checkLen($f['instrument_type'], 100, 'Instrument type');
    $checkLen($f['model'], 100, 'Model');                   $checkLen($f['instrument_sr_no'], 100, 'Instrument Sr. No.');
    $checkLen($f['engineer_name'], 100, 'Engineer name');
    $checkLen($f['observations'], 1000, 'Observations');    $checkLen($f['additional_activities'], 1000, 'Additional activities');
    $checkLen($f['engineer_remarks'], 600, "Engineer's remarks"); $checkLen($f['customer_remarks'], 600, "Customer's remarks");

    // --- Spare parts rows ---
    $partsRows = []; $clean = [];
    $codes = (array)($_POST['sp_code'] ?? []); $descs = (array)($_POST['sp_desc'] ?? []);
    $qtys  = (array)($_POST['sp_qty'] ?? []);  $bills = (array)($_POST['sp_bill'] ?? []);
    $n = min(count($codes), 100);
    for ($i = 0; $i < $n; $i++) {
        $row = [
            'part_code'            => trim((string)($codes[$i] ?? '')),
            'part_description'     => trim((string)($descs[$i] ?? '')),
            'quantity'             => trim((string)($qtys[$i] ?? '')),
            'billing_instructions' => trim((string)($bills[$i] ?? '')),
        ];
        if (implode('', $row) === '') continue;           // ignore blank rows
        $partsRows[] = $row;
        $ln = count($partsRows);
        if ($row['part_code'] === '' && $row['part_description'] === '') $errors[] = "Spare part row $ln: enter a part code or description.";
        if ($row['quantity'] !== '' && (!ctype_digit($row['quantity']) || (int)$row['quantity'] > 99999)) $errors[] = "Spare part row $ln: quantity must be a whole number.";
        if (mb_strlen($row['part_code']) > 100 || mb_strlen($row['part_description']) > 255 || mb_strlen($row['billing_instructions']) > 255)
            $errors[] = "Spare part row $ln: a field is too long.";
        $clean[] = $row;
    }
    if (count($clean) > MR_MAX_PARTS) $errors[] = "A maximum of " . MR_MAX_PARTS . " spare part rows fits on the one-page PDF.";

    // --- Actions ---
    $selectedIds = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['actions'] ?? [])), fn($v) => $v > 0)));
    $actionRows = [];
    if ($selectedIds) {
        $ph  = implode(',', array_fill(0, count($selectedIds), '?'));
        $stmt = $conn->prepare("SELECT id, action_text, is_active FROM machines_report_action_list WHERE id IN ($ph) ORDER BY sort_order, id");
        $stmt->bind_param(str_repeat('i', count($selectedIds)), ...$selectedIds);
        $stmt->execute();
        $master = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($master as $m) {
            $mid = (int)$m['id'];
            if (isset($savedActions[$mid]))        $actionRows[] = [$mid, $savedActions[$mid]];   // keep original wording
            elseif ((int)$m['is_active'] === 1)    $actionRows[] = [$mid, $m['action_text']];     // newly ticked
            // inactive + not previously saved -> ignored
        }
    }

    // --- Persist inside one transaction ---
    if (!$errors) {
        $cols = ['report_type','report_date','customer_name','customer_address','contact_person','designation','telephone','email',
                 'work_start_date','completion_date','travel_time_hrs','working_hrs','instrument_type','model','instrument_sr_no',
                 'visit_type','observations','additional_activities','engineer_remarks','customer_remarks','engineer_name'];
        $vals = [];
        foreach ($cols as $c) $vals[] = ($f[$c] === '' && !in_array($c, ['report_type','report_date','customer_name'], true)) ? null : $f[$c];

        try {
            $conn->begin_transaction();

            if ($isEdit) {
                $set  = implode(', ', array_map(fn($c) => "$c = ?", $cols));
                $stmt = $conn->prepare("UPDATE machines_report SET $set WHERE id = ?");
                $bindVals = array_merge($vals, [$reportId]);
                $stmt->bind_param(str_repeat('s', count($cols)) . 'i', ...$bindVals);
                $stmt->execute(); $stmt->close();
                $rid = $reportId;
                foreach (['machines_report_action', 'machines_report_spareparts'] as $tbl) {
                    $d = $conn->prepare("DELETE FROM $tbl WHERE report_id = ?");
                    $d->bind_param("i", $rid); $d->execute(); $d->close();
                }
            } else {
                $ph   = implode(',', array_fill(0, count($cols) + 1, '?'));
                $stmt = $conn->prepare("INSERT INTO machines_report (" . implode(',', $cols) . ", created_by) VALUES ($ph)");
                $bindVals = array_merge($vals, [$uid]);
                $stmt->bind_param(str_repeat('s', count($cols)) . 'i', ...$bindVals);
                $stmt->execute();
                $rid = (int)$conn->insert_id; $stmt->close();
            }

            if ($actionRows) {
                $stmt = $conn->prepare("INSERT INTO machines_report_action (report_id, action_list_id, action_text, sort_order) VALUES (?,?,?,?)");
                foreach ($actionRows as $i => [$aid, $atext]) { $so = $i + 1; $stmt->bind_param("iisi", $rid, $aid, $atext, $so); $stmt->execute(); }
                $stmt->close();
            }
            if ($clean) {
                $stmt = $conn->prepare("INSERT INTO machines_report_spareparts (report_id, sr_no, part_code, part_description, quantity, billing_instructions) VALUES (?,?,?,?,?,?)");
                foreach ($clean as $i => $p) {
                    $sr = $i + 1;
                    $pc = $p['part_code'] !== '' ? $p['part_code'] : null;
                    $pd = $p['part_description'] !== '' ? $p['part_description'] : null;
                    $pq = $p['quantity'] !== '' ? (int)$p['quantity'] : null;
                    $pb = $p['billing_instructions'] !== '' ? $p['billing_instructions'] : null;
                    $stmt->bind_param("iissis", $rid, $sr, $pc, $pd, $pq, $pb);
                    $stmt->execute();
                }
                $stmt->close();
            }

            $conn->commit();
            header("Location: machines_report_pdf.php?id=" . (int)$rid . "&saved=" . ($isEdit ? 'updated' : 'created')); exit;
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('machines_report save failed: ' . $e->getMessage());
            $errors[] = "The report could not be saved. Nothing was changed — please try again.";
        }
    }
}

// ---------- Checklist items (active + anything already saved on this report) ----------
$items = [];
$res = $conn->query("SELECT id, action_text FROM machines_report_action_list WHERE is_active = 1 ORDER BY sort_order, id");
foreach ($res as $a) $items[(int)$a['id']] = ['text' => $savedActions[(int)$a['id']] ?? $a['action_text'], 'legacy' => false];
foreach ($savedActions as $id => $text) if (!isset($items[$id])) $items[$id] = ['text' => $text, 'legacy' => true];

// spare parts: at least 3 rows
while (count($partsRows) < 3) $partsRows[] = ['part_code' => '', 'part_description' => '', 'quantity' => '', 'billing_instructions' => ''];

$pageTitle = $isEdit ? 'Edit Report MR-' . str_pad((string)$reportId, 5, '0', STR_PAD_LEFT) : 'New Machine Report';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($pageTitle) ?> — Rajaiswari</title>
  <link rel="icon" type="image/png" href="favicon.png">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root{--bg:#f1f3f6;--surface:#fff;--surface-2:#fafbfc;--border:#e4e7ec;--bsoft:#f0f1f3;--t1:#111827;--t2:#374151;--t3:#6b7280;--t4:#9ca3af;
      --blue:#2563eb;--blue-bg:#eff6ff;--blue-b:#bfdbfe;--green:#059669;--green-bg:#ecfdf5;--green-b:#a7f3d0;--amber:#d97706;--amber-bg:#fffbeb;--amber-b:#fde68a;
      --red:#dc2626;--red-bg:#fef2f2;--red-b:#fecaca;--violet:#7c3aed;--violet-bg:#f5f3ff;--violet-b:#ddd6fe;--teal:#0f766e;--teal-bg:#f0fdfa;--teal-b:#99f6e4;
      --r:10px;--rs:6px;--sh:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);}
    body{font-family:'DM Sans',-apple-system,sans-serif;font-size:14.5px;background:var(--bg);color:var(--t1);-webkit-font-smoothing:antialiased;min-height:100vh;}
    .page-shell{margin-left:200px;min-height:100vh;display:flex;flex-direction:column;}
    .top-bar{position:sticky;top:0;z-index:200;min-height:54px;background:var(--surface);border-bottom:1px solid var(--border);box-shadow:var(--sh);display:flex;align-items:center;flex-wrap:wrap;padding:6px 22px;gap:10px;}
    .tb-ico{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;background:var(--teal-bg);color:var(--teal);}
    .tb-title{font-size:1.0625rem;font-weight:700;line-height:1.2;}.tb-sub{font-size:.8rem;color:var(--t4);}
    .tb-right{margin-left:auto;display:flex;gap:8px;}
    .btn-soft{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 14px;background:var(--surface-2);border:1.5px solid var(--border);border-radius:var(--rs);font-size:.82rem;font-weight:600;color:var(--t2);text-decoration:none;}
    .btn-soft:hover{background:var(--border);color:var(--t1);}
    .main{flex:1;padding:20px 22px 60px;}
    .form-wrap{max-width:1000px;margin:0 auto;display:flex;flex-direction:column;gap:14px;}
    .sec{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;}
    .sec-head{display:flex;align-items:center;gap:9px;padding:12px 18px;background:var(--surface-2);border-bottom:1px solid var(--bsoft);}
    .sec-ico{width:28px;height:28px;border-radius:var(--rs);display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;}
    .i-blue{background:var(--blue-bg);color:var(--blue);}.i-violet{background:var(--violet-bg);color:var(--violet);}.i-amber{background:var(--amber-bg);color:var(--amber);}
    .i-green{background:var(--green-bg);color:var(--green);}.i-red{background:var(--red-bg);color:var(--red);}.i-teal{background:var(--teal-bg);color:var(--teal);}
    .sec-title{font-size:.9375rem;font-weight:700;}.sec-meta{margin-left:auto;font-size:.78rem;color:var(--t4);}
    .sec-body{padding:18px;}
    .lbl{display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t3);margin-bottom:4px;}
    .lbl .req{color:var(--red);margin-left:2px;}
    .form-control,.form-select{font-size:.9rem;border:1.5px solid var(--border);border-radius:var(--rs);min-height:38px;}
    textarea.form-control{min-height:auto;}
    .form-control:focus,.form-select:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
    .alert-err{background:var(--red-bg);border:1px solid var(--red-b);border-left:3px solid var(--red);border-radius:var(--rs);padding:13px 16px;}
    .alert-err-title{font-size:.875rem;font-weight:700;color:var(--red);margin-bottom:6px;}
    .alert-err ul{margin:0;padding-left:16px;}.alert-err li{font-size:.82rem;color:var(--red);}
    /* report type cards */
    .type-opt input{position:absolute;opacity:0;pointer-events:none;}
    .type-card{display:flex;align-items:center;gap:12px;padding:14px 16px;border:2px solid var(--border);border-radius:var(--r);cursor:pointer;background:var(--surface);transition:all .15s;height:100%;}
    .type-card:hover{border-color:#9ca3af;}
    .type-card .tc-ico{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:15px;background:var(--bsoft);color:var(--t3);}
    .type-card .tc-t{font-weight:700;}.type-card .tc-s{font-size:.78rem;color:var(--t4);}
    .type-opt input:checked + .type-card{border-color:var(--blue);background:var(--blue-bg);}
    .type-opt input:checked + .type-card .tc-ico{background:var(--blue);color:#fff;}
    .type-opt input:focus-visible + .type-card{box-shadow:0 0 0 3px rgba(37,99,235,.25);}
    /* checklist */
    .chk-item{display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border:1.5px solid var(--border);border-radius:var(--rs);cursor:pointer;background:var(--surface);height:100%;transition:all .15s;}
    .chk-item:hover{border-color:#9ca3af;}
    .chk-item .form-check-input{margin-top:3px;flex-shrink:0;width:1.1em;height:1.1em;}
    .chk-item:has(input:checked){background:var(--green-bg);border-color:var(--green-b);}
    .chk-text{font-size:.875rem;color:var(--t2);}
    .legacy-tag{font-size:.65rem;font-weight:700;background:var(--amber-bg);color:#92400e;border:1px solid var(--amber-b);border-radius:20px;padding:0 7px;margin-left:5px;white-space:nowrap;}
    /* spare parts */
    .sp-row{display:grid;grid-template-columns:44px 1.1fr 2fr 90px 1.6fr 40px;gap:8px;align-items:center;margin-bottom:8px;}
    .sp-head{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t4);}
    .sp-no{font-family:'DM Mono',monospace;font-weight:600;color:var(--t3);text-align:center;}
    .sp-mlbl{display:none;}
    .btn-rm{width:38px;height:38px;border:1.5px solid var(--red-b);background:var(--red-bg);color:var(--red);border-radius:var(--rs);}
    .btn-rm:hover{background:var(--red);color:#fff;}
    .action-bar{display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:13px 18px;background:var(--surface-2);border-top:1px solid var(--border);}
    .btn-ghost{display:inline-flex;align-items:center;gap:6px;height:40px;padding:0 18px;background:var(--surface);border:1.5px solid var(--border);border-radius:7px;font-weight:500;color:var(--t2);text-decoration:none;}
    .btn-ghost:hover{background:var(--surface-2);color:var(--t1);}
    .btn-submit{display:inline-flex;align-items:center;gap:7px;height:40px;padding:0 24px;border:none;border-radius:7px;font-weight:700;color:#fff;background:var(--green);}
    .btn-submit.edit{background:var(--amber);}.btn-submit:hover{filter:brightness(.92);}
    @media(max-width:991.98px){.page-shell{margin-left:0;padding-top:52px;}.top-bar{top:52px;padding:8px 14px;}.main{padding:14px 14px 50px;}}
    @media(max-width:767.98px){
      .sp-head-row{display:none;}
      .sp-row{grid-template-columns:1fr 1fr;border:1.5px solid var(--border);border-radius:var(--rs);padding:10px;background:var(--surface-2);}
      .sp-row .sp-no{grid-column:1/2;text-align:left;}.sp-row .sp-rm{grid-column:2/3;justify-self:end;}
      .sp-row .sp-c{grid-column:1/3;}.sp-row .sp-q{grid-column:1/3;}
      .sp-mlbl{display:block;font-size:.68rem;font-weight:700;text-transform:uppercase;color:var(--t4);margin-bottom:2px;}
      .action-bar{flex-direction:column-reverse;align-items:stretch;}.action-bar>*{justify-content:center;}
    }
  </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page-shell">
  <header class="top-bar">
    <div class="tb-ico"><i class="fas fa-<?= $isEdit ? 'pen-to-square' : 'file-circle-plus' ?>"></i></div>
    <div><div class="tb-title"><?= h($pageTitle) ?></div><div class="tb-sub">Installation / Service report</div></div>
    <div class="tb-right">
      <?php if ($isEdit): ?><a href="machines_report_pdf.php?id=<?= $reportId ?>" class="btn-soft"><i class="fas fa-file-pdf"></i><span class="d-none d-sm-inline"> PDF</span></a><?php endif; ?>
      <a href="machines_report_list.php" class="btn-soft"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
  </header>

  <main class="main">
    <form method="post" class="form-wrap" id="reportForm" novalidate>
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

      <?php if ($errors): ?>
        <div class="alert-err"><div class="alert-err-title"><i class="fas fa-circle-exclamation me-1"></i>Please fix the following:</div>
          <ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>

      <!-- 1. Type -->
      <div class="sec">
        <div class="sec-head"><div class="sec-ico i-violet"><i class="fas fa-clipboard-list"></i></div><div class="sec-title">Report Type</div><div class="sec-meta">Required</div></div>
        <div class="sec-body"><div class="row g-3">
          <div class="col-12 col-sm-6"><label class="type-opt d-block h-100 position-relative">
            <input type="radio" name="report_type" value="installation" required <?= $f['report_type'] === 'installation' ? 'checked' : '' ?>>
            <div class="type-card"><div class="tc-ico"><i class="fas fa-screwdriver-wrench"></i></div><div><div class="tc-t">Installation Report</div><div class="tc-s">New machine setup &amp; commissioning</div></div></div></label></div>
          <div class="col-12 col-sm-6"><label class="type-opt d-block h-100 position-relative">
            <input type="radio" name="report_type" value="service" required <?= $f['report_type'] === 'service' ? 'checked' : '' ?>>
            <div class="type-card"><div class="tc-ico"><i class="fas fa-gears"></i></div><div><div class="tc-t">Service Report</div><div class="tc-s">Maintenance, repair &amp; calibration visit</div></div></div></label></div>
        </div></div>
      </div>

      <!-- 2. Customer -->
      <div class="sec">
        <div class="sec-head"><div class="sec-ico i-blue"><i class="fas fa-building"></i></div><div class="sec-title">Customer Details</div></div>
        <div class="sec-body"><div class="row g-3">
          <div class="col-12 col-md-4"><label class="lbl" for="report_date">Report Date <span class="req">*</span></label><input type="date" class="form-control" id="report_date" name="report_date" value="<?= h($f['report_date']) ?>" required></div>
          <div class="col-12 col-md-8"><label class="lbl" for="customer_name">Customer Name <span class="req">*</span></label><input type="text" class="form-control" id="customer_name" name="customer_name" maxlength="150" value="<?= h($f['customer_name']) ?>" required></div>
          <div class="col-12"><label class="lbl" for="customer_address">Customer Address</label><textarea class="form-control" id="customer_address" name="customer_address" rows="2" maxlength="400"><?= h($f['customer_address']) ?></textarea></div>
          <div class="col-12 col-md-6"><label class="lbl" for="contact_person">Contact Person</label><input type="text" class="form-control" id="contact_person" name="contact_person" maxlength="100" value="<?= h($f['contact_person']) ?>"></div>
          <div class="col-12 col-md-6"><label class="lbl" for="designation">Designation / Dept.</label><input type="text" class="form-control" id="designation" name="designation" maxlength="100" value="<?= h($f['designation']) ?>"></div>
          <div class="col-12 col-md-6"><label class="lbl" for="telephone">Telephone</label><input type="tel" class="form-control" id="telephone" name="telephone" maxlength="40" value="<?= h($f['telephone']) ?>"></div>
          <div class="col-12 col-md-6"><label class="lbl" for="email">Email</label><input type="email" class="form-control" id="email" name="email" maxlength="120" value="<?= h($f['email']) ?>"></div>
        </div></div>
      </div>

      <!-- 3. Visit & Machine -->
      <div class="sec">
        <div class="sec-head"><div class="sec-ico i-amber"><i class="fas fa-microchip"></i></div><div class="sec-title">Visit &amp; Machine Details</div></div>
        <div class="sec-body"><div class="row g-3">
          <div class="col-6 col-md-3"><label class="lbl" for="work_start_date">Work Start Date</label><input type="date" class="form-control" id="work_start_date" name="work_start_date" value="<?= h($f['work_start_date']) ?>"></div>
          <div class="col-6 col-md-3"><label class="lbl" for="completion_date">Completion Date</label><input type="date" class="form-control" id="completion_date" name="completion_date" value="<?= h($f['completion_date']) ?>"></div>
          <div class="col-6 col-md-3"><label class="lbl" for="travel_time_hrs">Travel Time Hrs.</label><input type="text" class="form-control" id="travel_time_hrs" name="travel_time_hrs" maxlength="30" placeholder="e.g. 10 hrs" value="<?= h($f['travel_time_hrs']) ?>"></div>
          <div class="col-6 col-md-3"><label class="lbl" for="working_hrs">Working Hrs.</label><input type="text" class="form-control" id="working_hrs" name="working_hrs" maxlength="30" placeholder="e.g. 3 hrs" value="<?= h($f['working_hrs']) ?>"></div>
          <div class="col-12 col-md-6"><label class="lbl" for="instrument_type">Instrument Type <span class="req">*</span></label><input type="text" class="form-control" id="instrument_type" name="instrument_type" maxlength="100" value="<?= h($f['instrument_type']) ?>" required></div>
          <div class="col-12 col-md-6"><label class="lbl" for="model">Model</label><input type="text" class="form-control" id="model" name="model" maxlength="100" value="<?= h($f['model']) ?>"></div>
          <div class="col-12 col-md-6"><label class="lbl" for="instrument_sr_no">Instrument Sr. No.</label><input type="text" class="form-control" id="instrument_sr_no" name="instrument_sr_no" maxlength="100" value="<?= h($f['instrument_sr_no']) ?>"></div>
          <div class="col-12 col-md-6"><label class="lbl" for="visit_type">Visit Type</label>
            <select class="form-select" id="visit_type" name="visit_type"><?php foreach (['Physical', 'Remote'] as $v): ?><option <?= $f['visit_type'] === $v ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
          <div class="col-12"><label class="lbl" for="observations">Observations</label><textarea class="form-control" id="observations" name="observations" rows="3" maxlength="1000"><?= h($f['observations']) ?></textarea></div>
        </div></div>
      </div>

      <!-- 4. Action checklist -->
      <div class="sec">
        <div class="sec-head"><div class="sec-ico i-green"><i class="fas fa-list-check"></i></div><div class="sec-title">Action Taken</div>
          <div class="sec-meta"><a href="machines_report_action_list.php" class="text-decoration-none">Manage list</a></div></div>
        <div class="sec-body">
          <?php if (!$items): ?>
            <div class="text-muted">No active actions yet. <a href="machines_report_action_list.php">Add actions</a> to build the checklist.</div>
          <?php else: ?>
          <div class="row g-2">
            <?php foreach ($items as $id => $it): ?>
            <div class="col-12 col-md-6">
              <label class="chk-item">
                <input class="form-check-input" type="checkbox" name="actions[]" value="<?= (int)$id ?>" <?= in_array($id, $selectedIds, true) ? 'checked' : '' ?>>
                <span class="chk-text"><?= h($it['text']) ?><?php if ($it['legacy']): ?><span class="legacy-tag">no longer in list</span><?php endif; ?></span>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- 5. Spare parts -->
      <div class="sec">
        <div class="sec-head"><div class="sec-ico i-red"><i class="fas fa-gear"></i></div><div class="sec-title">Spare Parts Replaced (if any)</div><div class="sec-meta">Max <?= MR_MAX_PARTS ?> rows</div></div>
        <div class="sec-body">
          <div class="sp-row sp-head sp-head-row"><div class="text-center">Sr.</div><div>Part Code</div><div>Part Description</div><div>Qty</div><div>Instructions for Billing</div><div></div></div>
          <div id="spBody">
            <?php foreach ($partsRows as $p): ?>
            <div class="sp-row">
              <div class="sp-no">1</div>
              <div class="sp-c"><span class="sp-mlbl">Part Code</span><input type="text" class="form-control" name="sp_code[]" maxlength="100" value="<?= h($p['part_code']) ?>"></div>
              <div class="sp-c"><span class="sp-mlbl">Part Description</span><input type="text" class="form-control" name="sp_desc[]" maxlength="255" value="<?= h($p['part_description']) ?>"></div>
              <div class="sp-q"><span class="sp-mlbl">Quantity</span><input type="number" class="form-control" name="sp_qty[]" min="0" max="99999" step="1" inputmode="numeric" value="<?= h($p['quantity']) ?>"></div>
              <div class="sp-c"><span class="sp-mlbl">Instructions for Billing</span><input type="text" class="form-control" name="sp_bill[]" maxlength="255" value="<?= h($p['billing_instructions']) ?>"></div>
              <div class="sp-rm"><button type="button" class="btn-rm" title="Remove row"><i class="fas fa-trash-can"></i></button></div>
            </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="btn btn-outline-primary btn-sm fw-bold mt-1" id="addRow"><i class="fas fa-plus"></i> Add Row</button>
        </div>
      </div>

      <!-- 6. Remarks -->
      <div class="sec">
        <div class="sec-head"><div class="sec-ico i-teal"><i class="fas fa-comment-dots"></i></div><div class="sec-title">Additional Activities &amp; Remarks</div></div>
        <div class="sec-body"><div class="row g-3">
          <div class="col-12"><label class="lbl" for="additional_activities">Additional Activities</label><textarea class="form-control" id="additional_activities" name="additional_activities" rows="3" maxlength="1000"><?= h($f['additional_activities']) ?></textarea></div>
          <div class="col-12 col-md-6"><label class="lbl" for="engineer_remarks">Engineer's Remarks</label><textarea class="form-control" id="engineer_remarks" name="engineer_remarks" rows="3" maxlength="600"><?= h($f['engineer_remarks']) ?></textarea></div>
          <div class="col-12 col-md-6"><label class="lbl" for="customer_remarks">Customer's Remarks</label><textarea class="form-control" id="customer_remarks" name="customer_remarks" rows="3" maxlength="600"><?= h($f['customer_remarks']) ?></textarea></div>
          <div class="col-12 col-md-6"><label class="lbl" for="engineer_name">Engineer Name (printed under signature)</label><input type="text" class="form-control" id="engineer_name" name="engineer_name" maxlength="100" value="<?= h($f['engineer_name']) ?>"></div>
        </div></div>
        <div class="action-bar">
          <a href="machines_report_list.php" class="btn-ghost">Cancel</a>
          <button type="submit" class="btn-submit <?= $isEdit ? 'edit' : '' ?>"><i class="fas fa-<?= $isEdit ? 'floppy-disk' : 'check' ?>"></i> <?= $isEdit ? 'Update Report' : 'Save Report' ?></button>
        </div>
      </div>
    </form>
  </main>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  var MAX = <?= MR_MAX_PARTS ?>;
  var body = document.getElementById('spBody');
  var addBtn = document.getElementById('addRow');

  function renumber() {
    var rows = body.querySelectorAll('.sp-row');
    rows.forEach(function (r, i) { r.querySelector('.sp-no').textContent = i + 1; });
    addBtn.disabled = rows.length >= MAX;
  }
  addBtn.addEventListener('click', function () {
    var rows = body.querySelectorAll('.sp-row');
    if (rows.length >= MAX) return;
    var c = rows[rows.length - 1].cloneNode(true);
    c.querySelectorAll('input').forEach(function (i) { i.value = ''; });
    body.appendChild(c); renumber();
    c.querySelector('input').focus();
  });
  body.addEventListener('click', function (e) {
    var b = e.target.closest('.btn-rm'); if (!b) return;
    var rows = body.querySelectorAll('.sp-row'), row = b.closest('.sp-row');
    if (rows.length <= 1) { row.querySelectorAll('input').forEach(function (i) { i.value = ''; }); return; }
    row.remove(); renumber();
  });
  renumber();

  // client-side validation (server re-validates everything)
  document.getElementById('reportForm').addEventListener('submit', function (e) {
    var msgs = [];
    if (!document.querySelector('input[name=report_type]:checked')) msgs.push('Select a report type.');
    if (!document.getElementById('customer_name').value.trim()) msgs.push('Customer name is required.');
    if (!document.getElementById('instrument_type').value.trim()) msgs.push('Instrument type is required.');
    var s = document.getElementById('work_start_date').value, c = document.getElementById('completion_date').value;
    if (s && c && c < s) msgs.push('Completion date cannot be before the work start date.');
    var em = document.getElementById('email'); if (em.value && !em.checkValidity()) msgs.push('Email address is not valid.');
    if (msgs.length) { e.preventDefault(); alert(msgs.join('\n')); }
  });
})();
</script>
</body>
</html>
