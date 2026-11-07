<?php
require 'auth.php';
require 'mydb.php';
require_engineering();               // admin + engineer employee only — covers page load AND form submission

if (isset($conn)) { $conn->set_charset('utf8mb4'); }
if (!function_exists('h')) { function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

const AMB_MAX_ITEMS = 15;

if (empty($_SESSION['csrf_mb'])) $_SESSION['csrf_mb'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_mb'];

$billId = (int)($_GET['id'] ?? 0);
$isEdit = $billId > 0;
$uid    = (int)$_SESSION['user_id'];
$errors = [];

// ---------- Load existing bill ----------
$bill = null; $itemRows = [];
if ($isEdit) {
    $stmt = $conn->prepare("SELECT * FROM machines_billing WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $billId);
    $stmt->execute();
    $bill = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$bill) { header("Location: machines_billing_list.php?msg=notfound"); exit; }

    $stmt = $conn->prepare("SELECT service, unit_charge, total_amount FROM machines_billing_items WHERE billing_id = ? ORDER BY sr_no, id");
    $stmt->bind_param("i", $billId);
    $stmt->execute();
    $itemRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$f = $bill ?: [
    'billing_date' => date('Y-m-d'), 'customer_name' => '', 'customer_address' => '', 'contact_person' => '', 'designation' => '',
    'telephone' => '', 'email' => '', 'discount' => '', 'payment_status' => 'unpaid', 'engineer_sign_text' => '',
];
if ($isEdit && (float)$f['discount'] == 0) $f['discount'] = '';

// ---------- Helpers ----------
$validDate = function (string $d): bool { $o = DateTime::createFromFormat('Y-m-d', $d); return $o && $o->format('Y-m-d') === $d; };
$checkLen  = function (string $v, int $max, string $label) use (&$errors) { if (mb_strlen($v) > $max) $errors[] = "$label must be at most $max characters."; };
$post      = function (string $k): string { $v = $_POST[$k] ?? ''; return is_string($v) ? trim($v) : ''; };
$toCents   = function (string $v): ?int {                       // "1,500.50" -> 150050 ; null if invalid
    $v = str_replace(',', '', trim($v));
    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $v)) return null;
    return (int)round(((float)$v) * 100);
};
$fmtC = fn(int $c): string => number_format($c / 100, 2, '.', '');
const AMB_MAX_CENTS = 999999999999;   // DECIMAL(12,2)

// ---------- Save ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Invalid or expired form token. Please go back, refresh the page and try again.');
    }

    foreach (['billing_date', 'customer_name', 'customer_address', 'contact_person', 'designation', 'telephone', 'email', 'discount',
              'payment_status', 'engineer_sign_text'] as $k) $f[$k] = $post($k);

    if ($f['billing_date'] === '' || !$validDate($f['billing_date'])) $errors[] = "A valid bill date is required.";
    if ($f['customer_name'] === '') $errors[] = "Customer name is required.";
    if ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errors[] = "Email address is not valid.";
    if ($f['telephone'] !== '' && !preg_match('/^[0-9+\-()\s,\/]{5,40}$/', $f['telephone'])) $errors[] = "Telephone may contain only digits, spaces and + - ( ) , /";
    if (!in_array($f['payment_status'], ['paid', 'unpaid'], true)) $errors[] = "Invalid payment status.";
    $checkLen($f['customer_name'], 150, 'Customer name');   $checkLen($f['customer_address'], 400, 'Address');
    $checkLen($f['contact_person'], 100, 'Contact person'); $checkLen($f['designation'], 100, 'Designation');
    $checkLen($f['email'], 120, 'Email');
    $checkLen($f['engineer_sign_text'], 300, 'Engineer sign box text');

    // --- Services: typed manually (service text, unit charge, total amount) ---
    $itemRows = []; $clean = []; $subC = 0;
    $svc = (array)($_POST['bi_service'] ?? []); $chg = (array)($_POST['bi_charge'] ?? []); $tot = (array)($_POST['bi_total'] ?? []);
    $n = min(count($svc), 100);
    for ($i = 0; $i < $n; $i++) {
        $row = ['service' => trim((string)($svc[$i] ?? '')), 'unit_charge' => trim((string)($chg[$i] ?? '')), 'total_amount' => trim((string)($tot[$i] ?? ''))];
        if ($row['service'] === '' && $row['unit_charge'] === '' && $row['total_amount'] === '') continue;   // blank row
        $itemRows[] = $row;
        $ln = count($itemRows);
        if ($row['service'] === '') { $errors[] = "Service row $ln: enter the maintenance service."; continue; }
        if (mb_strlen($row['service']) > 255) { $errors[] = "Service row $ln: description is too long (max 255)."; continue; }
        if ($row['unit_charge'] === '' && $row['total_amount'] === '') { $errors[] = "Service row $ln: enter the amount."; continue; }
        $c = $row['unit_charge'] === '' ? null : $toCents($row['unit_charge']);
        $t = $row['total_amount'] === '' ? null : $toCents($row['total_amount']);
        if (($row['unit_charge'] !== '' && $c === null) || ($row['total_amount'] !== '' && $t === null)) {
            $errors[] = "Service row $ln: amounts must be valid numbers (e.g. 1500 or 1500.50)."; continue;
        }
        if ($c === null) $c = $t;            // only one amount typed -> use it for both
        if ($t === null) $t = $c;            // total left blank -> same as unit charge
        $subC += $t;
        $clean[] = ['service' => $row['service'], 'charge' => $c, 'total' => $t];
    }
    if (count($itemRows) === 0) $errors[] = "Add at least one maintenance service.";
    if (count($itemRows) > AMB_MAX_ITEMS) $errors[] = "A maximum of " . AMB_MAX_ITEMS . " service rows fits on the one-page PDF.";
    if ($subC > AMB_MAX_CENTS) $errors[] = "The total amount is too large.";

    $discC = 0;
    if ($f['discount'] !== '') {
        $discC = $toCents($f['discount']);
        if ($discC === null) { $errors[] = "Discount must be a valid amount."; $discC = 0; }
    }
    if (!$errors && $discC > $subC) $errors[] = "Discount cannot be more than the total.";
    $grandC = $subC - $discC;

    // --- Persist inside one transaction ---
    if (!$errors) {
        $nn = fn(string $v) => $v === '' ? null : $v;
        $cols = ['billing_date', 'customer_name', 'customer_address', 'contact_person', 'designation', 'telephone', 'email',
                 'subtotal', 'discount', 'grand_total', 'payment_status', 'engineer_sign_text'];
        $vals = [$f['billing_date'], $f['customer_name'], $nn($f['customer_address']), $nn($f['contact_person']), $nn($f['designation']),
                 $nn($f['telephone']), $nn($f['email']), $fmtC($subC), $fmtC($discC), $fmtC($grandC), $f['payment_status'],
                 $nn($f['engineer_sign_text'])];
        try {
            $conn->begin_transaction();

            if ($isEdit) {
                $set  = implode(', ', array_map(fn($c) => "$c = ?", $cols));
                $stmt = $conn->prepare("UPDATE machines_billing SET $set WHERE id = ?");
                $bindVals = array_merge($vals, [$billId]);
                $stmt->bind_param(str_repeat('s', count($cols)) . 'i', ...$bindVals);
                $stmt->execute(); $stmt->close();
                $rid = $billId;
                $d = $conn->prepare("DELETE FROM machines_billing_items WHERE billing_id = ?");
                $d->bind_param("i", $rid); $d->execute(); $d->close();
            } else {
                $ph   = implode(',', array_fill(0, count($cols) + 1, '?'));
                $stmt = $conn->prepare("INSERT INTO machines_billing (" . implode(',', $cols) . ", created_by) VALUES ($ph)");
                $bindVals = array_merge($vals, [$uid]);
                $stmt->bind_param(str_repeat('s', count($cols)) . 'i', ...$bindVals);
                $stmt->execute();
                $rid = (int)$conn->insert_id; $stmt->close();
            }

            $stmt = $conn->prepare("INSERT INTO machines_billing_items (billing_id, sr_no, service, unit_charge, total_amount) VALUES (?,?,?,?,?)");
            foreach ($clean as $i => $it) {
                $sr = $i + 1; $uc = $fmtC($it['charge']); $ta = $fmtC($it['total']);
                $svcText = $it['service'];
                $stmt->bind_param("iisss", $rid, $sr, $svcText, $uc, $ta);
                $stmt->execute();
            }
            $stmt->close();

            $conn->commit();
            header("Location: machines_billing_pdf.php?id=" . (int)$rid . "&saved=" . ($isEdit ? 'updated' : 'created')); exit;
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('machines_billing save failed: ' . $e->getMessage());
            $errors[] = "The bill could not be saved. Nothing was changed — please try again.";
        }
    }
}

// at least 3 service rows on screen
while (count($itemRows) < 3) $itemRows[] = ['service' => '', 'unit_charge' => '', 'total_amount' => ''];

$pageTitle = $isEdit ? 'Edit Bill AMB-' . str_pad((string)$billId, 5, '0', STR_PAD_LEFT) : 'New Annual Maintenance Bill';
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
      --blue:#2563eb;--blue-bg:#eff6ff;--green:#059669;--green-bg:#ecfdf5;--amber:#d97706;--amber-bg:#fffbeb;
      --red:#dc2626;--red-bg:#fef2f2;--red-b:#fecaca;--violet:#7c3aed;--violet-bg:#f5f3ff;--teal:#0f766e;--teal-bg:#f0fdfa;
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
    .i-green{background:var(--green-bg);color:var(--green);}.i-teal{background:var(--teal-bg);color:var(--teal);}
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
    /* service rows */
    .bi-row{display:grid;grid-template-columns:44px 2.8fr 1.1fr 1.1fr 40px;gap:8px;align-items:center;margin-bottom:8px;}
    .bi-head{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t4);}
    .bi-no{font-family:'DM Mono',monospace;font-weight:600;color:var(--t3);text-align:center;}
    .bi-mlbl{display:none;}
    .btn-rm{width:38px;height:38px;border:1.5px solid var(--red-b);background:var(--red-bg);color:var(--red);border-radius:var(--rs);}
    .btn-rm:hover{background:var(--red);color:#fff;}
    .totals{max-width:380px;margin-left:auto;margin-top:14px;border-top:1px solid var(--bsoft);padding-top:12px;}
    .tot-row{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:4px 0;}
    .tot-row .num{font-family:'DM Mono',monospace;font-weight:600;}
    .tot-grand{border-top:1px solid var(--border);margin-top:6px;padding-top:9px;font-weight:700;font-size:1.05rem;}
    .action-bar{display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:13px 18px;background:var(--surface-2);border-top:1px solid var(--border);}
    .btn-ghost{display:inline-flex;align-items:center;gap:6px;height:40px;padding:0 18px;background:var(--surface);border:1.5px solid var(--border);border-radius:7px;font-weight:500;color:var(--t2);text-decoration:none;}
    .btn-ghost:hover{background:var(--surface-2);color:var(--t1);}
    .btn-submit{display:inline-flex;align-items:center;gap:7px;height:40px;padding:0 24px;border:none;border-radius:7px;font-weight:700;color:#fff;background:var(--green);}
    .btn-submit.edit{background:var(--amber);}.btn-submit:hover{filter:brightness(.92);}
    @media(max-width:991.98px){.page-shell{margin-left:0;padding-top:52px;}.top-bar{top:52px;padding:8px 14px;}.main{padding:14px 14px 50px;}}
    @media(max-width:767.98px){
      .bi-head-row{display:none;}
      .bi-row{grid-template-columns:1fr 1fr;border:1.5px solid var(--border);border-radius:var(--rs);padding:10px;background:var(--surface-2);}
      .bi-row .bi-no{grid-column:1/2;text-align:left;}.bi-row .bi-rm{grid-column:2/3;justify-self:end;}
      .bi-row .bi-c{grid-column:1/3;}
      .bi-mlbl{display:block;font-size:.68rem;font-weight:700;text-transform:uppercase;color:var(--t4);margin-bottom:2px;}
      .action-bar{flex-direction:column-reverse;align-items:stretch;}.action-bar>*{justify-content:center;}
      .totals{max-width:none;}
    }
  </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page-shell">
  <header class="top-bar">
    <div class="tb-ico"><i class="fas fa-<?= $isEdit ? 'pen-to-square' : 'file-invoice-dollar' ?>"></i></div>
    <div><div class="tb-title"><?= h($pageTitle) ?></div><div class="tb-sub">Annual maintenance billing</div></div>
    <div class="tb-right">
      <?php if ($isEdit): ?><a href="machines_billing_pdf.php?id=<?= $billId ?>" class="btn-soft"><i class="fas fa-file-pdf"></i><span class="d-none d-sm-inline"> PDF</span></a><?php endif; ?>
      <a href="machines_billing_list.php" class="btn-soft"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
  </header>

  <main class="main">
    <form method="post" class="form-wrap" id="billForm" novalidate>
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

      <?php if ($errors): ?>
        <div class="alert-err"><div class="alert-err-title"><i class="fas fa-circle-exclamation me-1"></i>Please fix the following:</div>
          <ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>

      <!-- 1. Customer -->
      <div class="sec">
        <div class="sec-head"><div class="sec-ico i-blue"><i class="fas fa-building"></i></div><div class="sec-title">Customer Details</div></div>
        <div class="sec-body"><div class="row g-3">
          <div class="col-12 col-md-4"><label class="lbl" for="billing_date">Bill Date <span class="req">*</span></label><input type="date" class="form-control" id="billing_date" name="billing_date" value="<?= h($f['billing_date']) ?>" required></div>
          <div class="col-12 col-md-8"><label class="lbl" for="customer_name">Customer Name <span class="req">*</span></label><input type="text" class="form-control" id="customer_name" name="customer_name" maxlength="150" value="<?= h($f['customer_name']) ?>" required></div>
          <div class="col-12"><label class="lbl" for="customer_address">Customer Address</label><textarea class="form-control" id="customer_address" name="customer_address" rows="2" maxlength="400"><?= h($f['customer_address']) ?></textarea></div>
          <div class="col-12 col-md-6"><label class="lbl" for="contact_person">Contact Person</label><input type="text" class="form-control" id="contact_person" name="contact_person" maxlength="100" value="<?= h($f['contact_person']) ?>"></div>
          <div class="col-12 col-md-6"><label class="lbl" for="designation">Designation / Dept.</label><input type="text" class="form-control" id="designation" name="designation" maxlength="100" value="<?= h($f['designation']) ?>"></div>
          <div class="col-12 col-md-6"><label class="lbl" for="telephone">Telephone</label><input type="tel" class="form-control" id="telephone" name="telephone" maxlength="40" value="<?= h($f['telephone']) ?>"></div>
          <div class="col-12 col-md-6"><label class="lbl" for="email">Email</label><input type="email" class="form-control" id="email" name="email" maxlength="120" value="<?= h($f['email']) ?>"></div>
        </div></div>
      </div>

      <!-- 2. Maintenance schedule -->
      <div class="sec">
        <div class="sec-head"><div class="sec-ico i-amber"><i class="fas fa-screwdriver-wrench"></i></div><div class="sec-title">Maintenance Schedule</div><div class="sec-meta">Max <?= AMB_MAX_ITEMS ?> rows</div></div>
        <div class="sec-body">
          <div class="bi-row bi-head bi-head-row"><div class="text-center">SI</div><div>Maintenance Service</div><div>Unit Charge</div><div>Total Amount</div><div></div></div>
          <div id="biBody">
            <?php foreach ($itemRows as $r): ?>
            <div class="bi-row">
              <div class="bi-no">1</div>
              <div class="bi-c"><span class="bi-mlbl">Maintenance Service</span><input type="text" class="form-control" name="bi_service[]" maxlength="255" placeholder="Type the service" value="<?= h($r['service']) ?>"></div>
              <div class="bi-c"><span class="bi-mlbl">Unit Charge</span><input type="number" class="form-control bi-charge" name="bi_charge[]" min="0" step="0.01" inputmode="decimal" value="<?= h($r['unit_charge']) ?>"></div>
              <div class="bi-c"><span class="bi-mlbl">Total Amount</span><input type="number" class="form-control bi-total" name="bi_total[]" min="0" step="0.01" inputmode="decimal" value="<?= h($r['total_amount']) ?>" data-manual="<?= ($r['total_amount'] !== '' && $r['unit_charge'] !== '' && (float)$r['total_amount'] != (float)$r['unit_charge']) ? '1' : '0' ?>"></div>
              <div class="bi-rm"><button type="button" class="btn-rm" title="Remove row"><i class="fas fa-trash-can"></i></button></div>
            </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="btn btn-outline-primary btn-sm fw-bold mt-1" id="addRow"><i class="fas fa-plus"></i> Add Row</button>

          <div class="totals">
            <div class="tot-row"><span>Total</span><span class="num" id="tSub">0.00</span></div>
            <div class="tot-row"><label for="discount" class="m-0">Discount</label>
              <input type="number" class="form-control text-end" style="max-width:150px" id="discount" name="discount" min="0" step="0.01" inputmode="decimal" placeholder="0.00" value="<?= h($f['discount']) ?>"></div>
            <div class="tot-row tot-grand"><span>Grand Total</span><span class="num" id="tGrand">0.00</span></div>
            <div class="tot-row mt-1"><label for="payment_status" class="m-0">Payment status</label>
              <select class="form-select" style="max-width:150px" id="payment_status" name="payment_status">
                <option value="unpaid" <?= $f['payment_status'] === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                <option value="paid" <?= $f['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
              </select></div>
          </div>
        </div>
      </div>

      <!-- 3. Note & engineer -->
      <div class="sec">
        <div class="sec-head"><div class="sec-ico i-teal"><i class="fas fa-comment-dots"></i></div><div class="sec-title">Engineer Sign Box</div></div>
        <div class="sec-body"><div class="row g-3">
          <div class="col-12"><label class="lbl" for="engineer_sign_text">Text for the blank box above “ENGINEER SIGN :” (optional)</label>
            <textarea class="form-control" id="engineer_sign_text" name="engineer_sign_text" rows="2" maxlength="300" placeholder="e.g. engineer name or remarks — leave empty to keep the box blank"><?= h($f['engineer_sign_text']) ?></textarea>
            <div class="form-text">The engineer signature itself is hand-written on the printed bill. The note and bank details are fixed and printed automatically.</div></div>
        </div></div>
        <div class="action-bar">
          <a href="machines_billing_list.php" class="btn-ghost">Cancel</a>
          <button type="submit" class="btn-submit <?= $isEdit ? 'edit' : '' ?>"><i class="fas fa-<?= $isEdit ? 'floppy-disk' : 'check' ?>"></i> <?= $isEdit ? 'Update Bill' : 'Save Bill' ?></button>
        </div>
      </div>
    </form>
  </main>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  var MAX = <?= AMB_MAX_ITEMS ?>;
  var body = document.getElementById('biBody'), addBtn = document.getElementById('addRow'), disc = document.getElementById('discount');
  function fmt(c) { return (c / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

  function calc() {
    var sub = 0;
    body.querySelectorAll('.bi-row').forEach(function (r) {
      sub += Math.round((parseFloat(r.querySelector('.bi-total').value) || 0) * 100);
    });
    var d = Math.round((parseFloat(disc.value) || 0) * 100);
    document.getElementById('tSub').textContent = fmt(sub);
    document.getElementById('tGrand').textContent = fmt(Math.max(sub - d, 0));
  }
  function clearRow(row) {
    row.querySelectorAll('input').forEach(function (i) { i.value = ''; });
    row.querySelector('.bi-total').dataset.manual = '0';
  }
  function renumber() {
    var rows = body.querySelectorAll('.bi-row');
    rows.forEach(function (r, i) { r.querySelector('.bi-no').textContent = i + 1; });
    addBtn.disabled = rows.length >= MAX;
    calc();
  }
  addBtn.addEventListener('click', function () {
    var rows = body.querySelectorAll('.bi-row');
    if (rows.length >= MAX) return;
    var c = rows[rows.length - 1].cloneNode(true);
    clearRow(c);
    body.appendChild(c); renumber();
    c.querySelector('input').focus();
  });
  body.addEventListener('click', function (e) {
    var b = e.target.closest('.btn-rm'); if (!b) return;
    var rows = body.querySelectorAll('.bi-row'), row = b.closest('.bi-row');
    if (rows.length <= 1) { clearRow(row); calc(); return; }
    row.remove(); renumber();
  });
  // Total Amount follows Unit Charge until it is typed over manually
  body.addEventListener('input', function (e) {
    var row = e.target.closest('.bi-row'); if (!row) return;
    var tot = row.querySelector('.bi-total');
    if (e.target.classList.contains('bi-charge') && tot.dataset.manual !== '1') tot.value = e.target.value;
    if (e.target === tot) tot.dataset.manual = tot.value === '' ? '0' : '1';
    calc();
  });
  disc.addEventListener('input', calc);
  renumber();

  // client-side validation (server re-validates everything)
  document.getElementById('billForm').addEventListener('submit', function (e) {
    var msgs = [];
    if (!document.getElementById('customer_name').value.trim()) msgs.push('Customer name is required.');
    var filled = 0;
    body.querySelectorAll('.bi-row').forEach(function (r) {
      var s = r.querySelector('input[name="bi_service[]"]').value.trim(), c = r.querySelector('.bi-charge').value.trim(), t = r.querySelector('.bi-total').value.trim();
      if (s || c || t) { filled++; if (!s) msgs.push('Enter the service name in row ' + filled + '.'); if (!c && !t) msgs.push('Enter the amount in row ' + filled + '.'); }
    });
    if (!filled) msgs.push('Add at least one maintenance service.');
    var em = document.getElementById('email'); if (em.value && !em.checkValidity()) msgs.push('Email address is not valid.');
    if (msgs.length) { e.preventDefault(); alert(msgs.join('\n')); }
  });
})();
</script>
</body>
</html>
