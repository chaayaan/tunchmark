<?php
require 'auth.php';
require 'mydb.php';
require_engineering();               // admin + engineer employee only — covers page load AND form submission

if (isset($conn)) { $conn->set_charset('utf8mb4'); }
if (!function_exists('h')) { function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

const CV_MAX_ROWS = 40;              // keeps the printed bill on one A4 page
const CV_OWN_ONLY = true;            // true: engineers can open only their own bills, admins open all
$VEHICLES = ['Cng', 'Auto', 'Bus', 'AC Bus', 'Rickshaw', 'Train', 'Launch', 'Bike', 'Pickup', 'Microbus', 'Other'];
$PURPOSES = ['Servicing', 'Breakfast', 'Lunch', 'Dinner', 'Hotel', 'Training', 'Office work'];   // suggestions only — any text can be typed

if (empty($_SESSION['csrf_cv'])) $_SESSION['csrf_cv'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_cv'];

$billId = (int)($_GET['id'] ?? 0);
$isEdit = $billId > 0;
$uid    = (int)$_SESSION['user_id'];
$errors = [];

// ---------- Load existing bill ----------
$bill = null; $rows = [];
if ($isEdit) {
    $stmt = $conn->prepare("SELECT * FROM conveyance WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $billId);
    $stmt->execute();
    $bill = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$bill || (CV_OWN_ONLY && !is_admin() && (int)$bill['created_by'] !== $uid)) { header("Location: conveyance_list.php?msg=notfound"); exit; }
    if ($bill['status'] === 'final') { header("Location: conveyance_print.php?id=" . $billId); exit; }   // finalized bills are locked

    $stmt = $conn->prepare("SELECT expense_date, from_place, to_place, purpose, vehicle, amount FROM conveyance_expenses WHERE conveyance_id = ? ORDER BY sr_no, id");
    $stmt->bind_param("i", $billId);
    $stmt->execute();
    foreach ($stmt->get_result() as $e) {
        $rows[] = ['date' => $e['expense_date'], 'from' => (string)$e['from_place'], 'to' => (string)$e['to_place'],
                   'purpose' => (string)$e['purpose'], 'vehicle' => (string)$e['vehicle'], 'amount' => number_format((float)$e['amount'], 2, '.', '')];
    }
    $stmt->close();
}

$f = $bill ?: ['tour_name' => '', 'purpose' => '', 'engineer_name' => $_SESSION['name'] ?? '', 'designation' => '', 'bill_date' => date('Y-m-d')];

// ---------- Helpers ----------
$validDate = function (string $d): bool { $o = DateTime::createFromFormat('Y-m-d', $d); return $o && $o->format('Y-m-d') === $d; };
$checkLen  = function (string $v, int $max, string $label) use (&$errors) { if (mb_strlen($v) > $max) $errors[] = "$label must be at most $max characters."; };
$post      = function (string $k): string { $v = $_POST[$k] ?? ''; return is_string($v) ? trim($v) : ''; };
$toCents   = function (string $v): ?int {
    $v = str_replace(',', '', trim($v));
    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $v)) return null;
    return (int)round(((float)$v) * 100);
};
$fmtC = fn(int $c): string => number_format($c / 100, 2, '.', '');

// ---------- Save ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Invalid or expired form token. Please go back, refresh the page and try again.');
    }
    $op = ($_POST['op'] ?? '') === 'final' ? 'final' : 'draft';

    foreach (['tour_name', 'purpose', 'engineer_name', 'designation', 'bill_date'] as $k) $f[$k] = $post($k);
    if ($f['tour_name'] === '')     $errors[] = "Tour name is required.";
    if ($f['engineer_name'] === '') $errors[] = "Engineer name is required.";
    if ($f['bill_date'] === '' || !$validDate($f['bill_date'])) $errors[] = "A valid bill date is required.";
    $checkLen($f['tour_name'], 200, 'Tour name');         $checkLen($f['purpose'], 255, 'Purpose');
    $checkLen($f['engineer_name'], 100, 'Engineer name'); $checkLen($f['designation'], 100, 'Designation');

    // --- expense rows (the list built on the page) ---
    $rows = []; $clean = []; $totalC = 0;
    $dts = (array)($_POST['ex_date'] ?? []); $frs = (array)($_POST['ex_from'] ?? []); $tos = (array)($_POST['ex_to'] ?? []);
    $pps = (array)($_POST['ex_purpose'] ?? []); $vhs = (array)($_POST['ex_vehicle'] ?? []); $ams = (array)($_POST['ex_amount'] ?? []);
    $n = min(count($dts), 200);
    for ($i = 0; $i < $n; $i++) {
        $r = ['date' => trim((string)($dts[$i] ?? '')), 'from' => trim((string)($frs[$i] ?? '')), 'to' => trim((string)($tos[$i] ?? '')),
              'purpose' => trim((string)($pps[$i] ?? '')), 'vehicle' => trim((string)($vhs[$i] ?? '')), 'amount' => trim((string)($ams[$i] ?? ''))];
        $rows[] = $r; $ln = count($rows);
        if (!$validDate($r['date'])) { $errors[] = "Row $ln: a valid date is required."; continue; }
        if (mb_strlen($r['from']) > 100 || mb_strlen($r['to']) > 100 || mb_strlen($r['purpose']) > 150) { $errors[] = "Row $ln: From / To / Purpose is too long."; continue; }
        if ($r['purpose'] === '' && $r['vehicle'] === '') { $errors[] = "Row $ln: enter a purpose or choose a vehicle."; continue; }
        if ($r['vehicle'] !== '' && !in_array($r['vehicle'], $VEHICLES, true)) { $errors[] = "Row $ln: invalid vehicle."; continue; }
        $c = $toCents($r['amount']);
        if ($c === null || $c <= 0) { $errors[] = "Row $ln: amount must be a number greater than 0."; continue; }
        $totalC += $c;
        $clean[] = ['date' => $r['date'], 'from' => $r['from'], 'to' => $r['to'], 'purpose' => $r['purpose'], 'vehicle' => $r['vehicle'], 'amount' => $c];
    }
    if (count($rows) > CV_MAX_ROWS) $errors[] = "A maximum of " . CV_MAX_ROWS . " expense rows fits on the one-page bill.";
    if ($op === 'final' && count($rows) === 0) $errors[] = "Add at least one expense before finalizing.";
    if ($totalC > 999999999999) $errors[] = "The total amount is too large.";

    // --- persist in one transaction ---
    if (!$errors) {
        $dates = array_column($clean, 'date');
        $fromDate = $dates ? min($dates) : null;
        $toDate   = $dates ? max($dates) : null;
        $nn  = fn(string $v) => $v === '' ? null : $v;
        $fin = $op === 'final' ? date('Y-m-d H:i:s') : null;
        $total = $fmtC($totalC);
        $purposeV = $nn($f['purpose']); $desigV = $nn($f['designation']);
        try {
            $conn->begin_transaction();
            if ($isEdit) {
                $stmt = $conn->prepare("UPDATE conveyance SET tour_name = ?, purpose = ?, engineer_name = ?, designation = ?, bill_date = ?, from_date = ?, to_date = ?, status = ?, total_amount = ?, finalized_at = ? WHERE id = ? AND status = 'draft'");
                $stmt->bind_param("ssssssssssi", $f['tour_name'], $purposeV, $f['engineer_name'], $desigV, $f['bill_date'], $fromDate, $toDate, $op, $total, $fin, $billId);
                $stmt->execute();
                if ($stmt->errno) throw new RuntimeException($stmt->error);
                $stmt->close();
                $rid = $billId;
                $d = $conn->prepare("DELETE FROM conveyance_expenses WHERE conveyance_id = ?");
                $d->bind_param("i", $rid); $d->execute(); $d->close();
            } else {
                $stmt = $conn->prepare("INSERT INTO conveyance (tour_name, purpose, engineer_name, designation, bill_date, from_date, to_date, status, total_amount, finalized_at, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param("ssssssssssi", $f['tour_name'], $purposeV, $f['engineer_name'], $desigV, $f['bill_date'], $fromDate, $toDate, $op, $total, $fin, $uid);
                $stmt->execute();
                $rid = (int)$conn->insert_id; $stmt->close();
            }
            $stmt = $conn->prepare("INSERT INTO conveyance_expenses (conveyance_id, sr_no, expense_date, from_place, to_place, purpose, vehicle, amount) VALUES (?,?,?,?,?,?,?,?)");
            foreach ($clean as $i => $x) {
                $sr = $i + 1; $fp = $nn($x['from']); $tp = $nn($x['to']); $pp = $nn($x['purpose']); $vv = $nn($x['vehicle']); $am = $fmtC($x['amount']);
                $stmt->bind_param("iissssss", $rid, $sr, $x['date'], $fp, $tp, $pp, $vv, $am);
                $stmt->execute();
            }
            $stmt->close();
            $conn->commit();
            header("Location: " . ($op === 'final' ? "conveyance_print.php?id=" . (int)$rid . "&saved=final" : "conveyance_list.php?msg=draft")); exit;
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('conveyance save failed: ' . $e->getMessage());
            $errors[] = "The bill could not be saved. Nothing was changed — please try again.";
        }
    }
}

$pageTitle = $isEdit ? 'Edit Bill CV-' . str_pad((string)$billId, 5, '0', STR_PAD_LEFT) : 'New Conveyance Bill';
$jsRows = json_encode(array_values($rows), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$lastDate = $rows ? end($rows)['date'] : $f['bill_date'];
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
      --blue:#2563eb;--blue-bg:#eff6ff;--green:#059669;--green-bg:#ecfdf5;--amber:#d97706;--amber-bg:#fffbeb;--amber-b:#fde68a;
      --red:#dc2626;--red-bg:#fef2f2;--red-b:#fecaca;--teal:#0f766e;--teal-bg:#f0fdfa;--r:10px;--rs:6px;--sh:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);}
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
    .i-blue{background:var(--blue-bg);color:var(--blue);}.i-amber{background:var(--amber-bg);color:var(--amber);}
    .sec-title{font-size:.9375rem;font-weight:700;}.sec-meta{margin-left:auto;font-size:.78rem;color:var(--t4);}
    .sec-body{padding:18px;}
    .lbl{display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t3);margin-bottom:4px;}
    .lbl .req{color:var(--red);margin-left:2px;}
    .form-control,.form-select{font-size:.9rem;border:1.5px solid var(--border);border-radius:var(--rs);min-height:38px;}
    .form-control:focus,.form-select:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
    .tour-in{font-size:1.05rem;font-weight:600;min-height:44px;}
    .alert-err{background:var(--red-bg);border:1px solid var(--red-b);border-left:3px solid var(--red);border-radius:var(--rs);padding:13px 16px;}
    .alert-err-title{font-size:.875rem;font-weight:700;color:var(--red);margin-bottom:6px;}
    .alert-err ul{margin:0;padding-left:16px;}.alert-err li{font-size:.82rem;color:var(--red);}
    /* one input row + Add */
    .add-row{display:grid;grid-template-columns:1.15fr 1fr 1fr 1.2fr 1fr .8fr auto;gap:8px;align-items:end;}
    .btn-addrow{height:38px;padding:0 18px;border:none;border-radius:var(--rs);background:var(--blue);color:#fff;font-weight:700;}
    .btn-addrow:hover{filter:brightness(.92);}
    /* list */
    .utbl{width:100%;border-collapse:collapse;margin:0;}
    .utbl thead th{padding:9px 12px;text-align:left;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--t4);background:var(--surface-2);border-bottom:1px solid var(--border);white-space:nowrap;}
    .utbl tbody td{padding:9px 12px;border-bottom:1px solid var(--bsoft);vertical-align:middle;font-size:.875rem;}
    .utbl tbody tr:last-child td{border-bottom:none;}
    .amt{font-family:'DM Mono',monospace;text-align:right;white-space:nowrap;}
    .ibtn{width:32px;height:32px;border-radius:var(--rs);border:1.5px solid var(--border);background:#fff;color:var(--t2);}
    .ibtn.edit:hover{background:var(--amber);border-color:var(--amber);color:#fff;}
    .ibtn.del{border-color:var(--red-b);background:var(--red-bg);color:var(--red);}.ibtn.del:hover{background:var(--red);color:#fff;}
    .list-foot{display:flex;justify-content:space-between;align-items:center;padding:12px 18px;background:var(--surface-2);border-top:1px solid var(--border);font-weight:700;gap:10px;flex-wrap:wrap;}
    .list-empty{padding:26px 16px;text-align:center;color:var(--t4);}
    .action-bar{display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:13px 18px;background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);}
    .btn-ghost{display:inline-flex;align-items:center;gap:6px;height:40px;padding:0 18px;background:var(--surface);border:1.5px solid var(--border);border-radius:7px;font-weight:500;color:var(--t2);text-decoration:none;}
    .btn-ghost:hover{background:var(--surface-2);color:var(--t1);}
    .btn-draft{display:inline-flex;align-items:center;gap:7px;height:40px;padding:0 20px;border:1.5px solid var(--amber);border-radius:7px;font-weight:700;color:#92400e;background:var(--amber-bg);}
    .btn-draft:hover{background:var(--amber);color:#fff;}
    .btn-final{display:inline-flex;align-items:center;gap:7px;height:40px;padding:0 22px;border:none;border-radius:7px;font-weight:700;color:#fff;background:var(--green);}
    .btn-final:hover{filter:brightness(.92);}
    @media(max-width:991.98px){.page-shell{margin-left:0;padding-top:52px;}.top-bar{top:52px;padding:8px 14px;}.main{padding:14px 14px 50px;}
      .add-row{grid-template-columns:repeat(3,1fr);}.add-row .btn-addrow{grid-column:1/-1;}}
    @media(max-width:767.98px){
      .add-row{grid-template-columns:1fr 1fr;}
      .utbl thead{display:none;}.utbl,.utbl tbody,.utbl tr,.utbl td{display:block;width:100%;}
      .utbl tbody tr{border-bottom:8px solid var(--bg);padding:6px 0;}
      .utbl tbody td{border:none;padding:4px 14px;display:flex;gap:10px;justify-content:space-between;text-align:right;}
      .utbl tbody td::before{content:attr(data-label);font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t4);text-align:left;flex-shrink:0;padding-top:2px;}
      .utbl tbody td.td-act{justify-content:flex-end;gap:8px;padding-bottom:10px;}.utbl tbody td.td-act::before{display:none;}
      .ibtn{width:44px;height:38px;}
      .action-bar{flex-direction:column-reverse;align-items:stretch;}.action-bar>*{justify-content:center;}
    }
  </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page-shell">
  <header class="top-bar">
    <div class="tb-ico"><i class="fas fa-<?= $isEdit ? 'pen-to-square' : 'route' ?>"></i></div>
    <div><div class="tb-title"><?= h($pageTitle) ?></div><div class="tb-sub">Conveyance bill</div></div>
    <div class="tb-right"><a href="conveyance_list.php" class="btn-soft"><i class="fas fa-arrow-left"></i> Back</a></div>
  </header>

  <main class="main">
    <form method="post" class="form-wrap" id="cvForm" novalidate>
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <div id="hid"></div>

      <?php if ($errors): ?>
        <div class="alert-err"><div class="alert-err-title"><i class="fas fa-circle-exclamation me-1"></i>Please fix the following:</div>
          <ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>

      <!-- 1. Bill details -->
      <div class="sec">
        <div class="sec-head"><div class="sec-ico i-blue"><i class="fas fa-route"></i></div><div class="sec-title">Bill Details</div></div>
        <div class="sec-body"><div class="row g-3">
          <div class="col-12"><label class="lbl" for="tour_name">Tour name <span class="req">*</span></label>
            <input type="text" class="form-control tour-in" id="tour_name" name="tour_name" maxlength="200" placeholder="e.g. Servicing for Kishoreganj, Benapol, Kaliganj, Dhaka." value="<?= h($f['tour_name']) ?>" required></div>
          <div class="col-12 col-md-4"><label class="lbl" for="engineer_name">Engineer name <span class="req">*</span></label><input type="text" class="form-control" id="engineer_name" name="engineer_name" maxlength="100" value="<?= h($f['engineer_name']) ?>" required></div>
          <div class="col-12 col-md-4"><label class="lbl" for="designation">Designation</label><input type="text" class="form-control" id="designation" name="designation" maxlength="100" placeholder="e.g. Maintenance Engineer" value="<?= h($f['designation']) ?>"></div>
          <div class="col-12 col-md-4"><label class="lbl" for="bill_date">Bill date <span class="req">*</span></label><input type="date" class="form-control" id="bill_date" name="bill_date" value="<?= h($f['bill_date']) ?>" required></div>
          <div class="col-12"><label class="lbl" for="purpose">Purpose / remarks (optional)</label><input type="text" class="form-control" id="purpose" name="purpose" maxlength="255" value="<?= h($f['purpose']) ?>"></div>
        </div></div>
      </div>

      <!-- 2. Expenses -->
      <div class="sec">
        <div class="sec-head"><div class="sec-ico i-amber"><i class="fas fa-receipt"></i></div><div class="sec-title">Expenses</div><div class="sec-meta">Max <?= CV_MAX_ROWS ?> rows</div></div>
        <div class="sec-body">
          <div class="add-row" id="addRow">
            <div><label class="lbl" for="aDate">Date</label><input type="date" class="form-control" id="aDate" value="<?= h($lastDate) ?>"></div>
            <div><label class="lbl" for="aFrom">From</label><input type="text" class="form-control" id="aFrom" maxlength="100"></div>
            <div><label class="lbl" for="aTo">To</label><input type="text" class="form-control" id="aTo" maxlength="100"></div>
            <div><label class="lbl" for="aPurpose">Purpose</label><input type="text" class="form-control" id="aPurpose" maxlength="150" list="purposeList" placeholder="Servicing, Lunch…" autocomplete="off"></div>
            <div><label class="lbl" for="aVehicle">Vehicle</label>
              <select class="form-select" id="aVehicle"><option value="">—</option><?php foreach ($VEHICLES as $v): ?><option><?= h($v) ?></option><?php endforeach; ?></select></div>
            <div><label class="lbl" for="aAmount">Amount</label><input type="number" class="form-control" id="aAmount" min="0" step="0.01" inputmode="decimal" placeholder="0.00"></div>
            <div><button type="button" class="btn-addrow" id="btnAdd"><i class="fas fa-plus"></i> Add</button></div>
          </div>
          <datalist id="purposeList"><?php foreach ($PURPOSES as $p): ?><option value="<?= h($p) ?>"><?php endforeach; ?></datalist>
          <div class="form-text mt-2">After Add, the date stays and <b>From</b> is set to the last <b>To</b>. For meals / hotel, leave Vehicle as “—”.</div>
        </div>

        <div id="listWrap">
          <table class="utbl">
            <thead><tr><th>SL</th><th>Date</th><th>From</th><th>To</th><th>Purpose</th><th>Vehicle</th><th style="text-align:right">Amount</th><th style="text-align:right">Edit</th></tr></thead>
            <tbody id="listBody"></tbody>
          </table>
          <div class="list-empty" id="listEmpty">No expenses yet. Fill the row above and press <b>Add</b>.</div>
        </div>
        <div class="list-foot"><span><span id="cnt">0</span> expense row(s)</span><span>Total: <span class="amt" id="grand">0.00</span> Tk</span></div>
      </div>

      <div class="action-bar">
        <a href="conveyance_list.php" class="btn-ghost">Cancel</a>
        <button type="submit" name="op" value="draft" class="btn-draft" id="btnDraft"><i class="fas fa-floppy-disk"></i> Save as Draft</button>
        <button type="submit" name="op" value="final" class="btn-final" id="btnFinal"><i class="fas fa-lock"></i> Save &amp; Finalize</button>
      </div>
      <div class="text-muted small text-end">Draft: you can edit or add rows later. Finalize: the bill is locked and opens the print page.</div>
    </form>
  </main>
</div>

<!-- Edit row modal -->
<div class="modal fade" id="rowModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title fs-6 fw-bold">Edit expense</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body"><div class="row g-3">
        <div class="col-12 col-sm-6"><label class="lbl" for="mDate">Date</label><input type="date" class="form-control" id="mDate"></div>
        <div class="col-12 col-sm-6"><label class="lbl" for="mAmount">Amount</label><input type="number" class="form-control" id="mAmount" min="0" step="0.01" inputmode="decimal"></div>
        <div class="col-6"><label class="lbl" for="mFrom">From</label><input type="text" class="form-control" id="mFrom" maxlength="100"></div>
        <div class="col-6"><label class="lbl" for="mTo">To</label><input type="text" class="form-control" id="mTo" maxlength="100"></div>
        <div class="col-12 col-sm-6"><label class="lbl" for="mPurpose">Purpose</label><input type="text" class="form-control" id="mPurpose" maxlength="150" list="purposeList" autocomplete="off"></div>
        <div class="col-12 col-sm-6"><label class="lbl" for="mVehicle">Vehicle</label>
          <select class="form-select" id="mVehicle"><option value="">—</option><?php foreach ($VEHICLES as $v): ?><option><?= h($v) ?></option><?php endforeach; ?></select></div>
      </div></div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary fw-bold" id="mSave"><i class="fas fa-check"></i> Save changes</button></div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  var MAX = <?= CV_MAX_ROWS ?>;
  var ROWS = <?= $jsRows ?>;
  var $ = function (id) { return document.getElementById(id); };
  var form = $('cvForm'), listBody = $('listBody'), modal = new bootstrap.Modal($('rowModal')), editIdx = -1;

  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
  function fmtDate(d) { var p = (d || '').split('-'); return p.length === 3 ? p[2] + '-' + p[1] + '-' + p[0] : d; }
  function money(c) { return (c / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function cents(v) { return Math.round((parseFloat(v) || 0) * 100); }

  function render() {
    var html = '', total = 0;
    ROWS.forEach(function (r, i) {
      total += cents(r.amount);
      html += '<tr><td data-label="SL">' + (i + 1) + '</td><td data-label="Date">' + esc(fmtDate(r.date)) + '</td>' +
        '<td data-label="From">' + (esc(r.from) || '***') + '</td><td data-label="To">' + (esc(r.to) || '***') + '</td>' +
        '<td data-label="Purpose">' + (esc(r.purpose) || '***') + '</td><td data-label="Vehicle">' + (esc(r.vehicle) || '***') + '</td>' +
        '<td data-label="Amount" class="amt">' + money(cents(r.amount)) + '</td>' +
        '<td class="td-act" style="text-align:right;white-space:nowrap"><button type="button" class="ibtn edit" data-i="' + i + '" title="Edit"><i class="fas fa-pen"></i></button> ' +
        '<button type="button" class="ibtn del" data-i="' + i + '" title="Delete"><i class="fas fa-trash-can"></i></button></td></tr>';
    });
    listBody.innerHTML = html;
    $('listEmpty').style.display = ROWS.length ? 'none' : '';
    listBody.parentNode.style.display = ROWS.length ? '' : 'none';
    $('cnt').textContent = ROWS.length;
    $('grand').textContent = money(total);
    $('btnAdd').disabled = ROWS.length >= MAX;
    // hidden inputs that are posted with the form
    var h = '';
    ROWS.forEach(function (r) {
      h += '<input type="hidden" name="ex_date[]" value="' + esc(r.date) + '"><input type="hidden" name="ex_from[]" value="' + esc(r.from) + '">' +
           '<input type="hidden" name="ex_to[]" value="' + esc(r.to) + '"><input type="hidden" name="ex_purpose[]" value="' + esc(r.purpose) + '">' +
           '<input type="hidden" name="ex_vehicle[]" value="' + esc(r.vehicle) + '"><input type="hidden" name="ex_amount[]" value="' + esc(r.amount) + '">';
    });
    $('hid').innerHTML = h;
  }

  // validate one row (shared by Add and the edit modal)
  function check(r) {
    if (!r.date) return 'Choose the date.';
    if (!r.purpose && !r.vehicle) return 'Enter a purpose or choose a vehicle.';
    if (!(parseFloat(r.amount) > 0)) return 'Enter an amount greater than 0.';
    return '';
  }

  $('btnAdd').addEventListener('click', function () {
    var r = { date: $('aDate').value, from: $('aFrom').value.trim(), to: $('aTo').value.trim(), purpose: $('aPurpose').value.trim(), vehicle: $('aVehicle').value, amount: $('aAmount').value };
    var err = check(r);
    if (err) { alert(err); return; }
    if (ROWS.length >= MAX) { alert('Maximum ' + MAX + ' rows.'); return; }
    r.amount = (cents(r.amount) / 100).toFixed(2);
    ROWS.push(r); render();
    // quick entry: keep the date, next From = this To (if any), clear the rest
    if (r.to) $('aFrom').value = r.to;
    $('aTo').value = ''; $('aPurpose').value = ''; $('aVehicle').value = ''; $('aAmount').value = '';
    $('aPurpose').focus();
  });
  // Enter in the input row = Add (never submits the whole form)
  $('addRow').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); $('btnAdd').click(); } });

  listBody.addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b) return;
    var i = parseInt(b.dataset.i, 10);
    if (b.classList.contains('del')) { if (confirm('Delete row ' + (i + 1) + '?')) { ROWS.splice(i, 1); render(); } return; }
    editIdx = i; var r = ROWS[i];
    $('mDate').value = r.date; $('mFrom').value = r.from; $('mTo').value = r.to; $('mPurpose').value = r.purpose; $('mVehicle').value = r.vehicle; $('mAmount').value = r.amount;
    modal.show();
  });
  $('mSave').addEventListener('click', function () {
    var r = { date: $('mDate').value, from: $('mFrom').value.trim(), to: $('mTo').value.trim(), purpose: $('mPurpose').value.trim(), vehicle: $('mVehicle').value, amount: $('mAmount').value };
    var err = check(r); if (err) { alert(err); return; }
    r.amount = (cents(r.amount) / 100).toFixed(2);
    ROWS[editIdx] = r; render(); modal.hide();
  });

  form.addEventListener('submit', function (e) {
    var op = e.submitter ? e.submitter.value : 'draft', msgs = [];
    if (!$('tour_name').value.trim()) msgs.push('Tour name is required.');
    if (!$('engineer_name').value.trim()) msgs.push('Engineer name is required.');
    if (!$('bill_date').value) msgs.push('Bill date is required.');
    if (op === 'final' && !ROWS.length) msgs.push('Add at least one expense before finalizing.');
    if (msgs.length) { e.preventDefault(); alert(msgs.join('\n')); return; }
    if (op === 'final' && !confirm('Finalize this bill? It will be locked and cannot be edited.')) e.preventDefault();
  });

  render();
})();
</script>
</body>
</html>
