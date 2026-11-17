<?php
require 'auth.php';
require 'mydb.php';
require_engineering();               // admin + engineer employee only (server-side, re-checked against DB)

if (isset($conn)) { $conn->set_charset('utf8mb4'); }
if (!function_exists('h')) { function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

$isDate = function ($d) { $o = DateTime::createFromFormat('Y-m-d', (string)$d); return $o && $o->format('Y-m-d') === $d; };

// ---------- Fixed bank details / note / services (same as machines_billing_pdf.php; edit both if you ever change them) ----------
$bank = [
    'Bank Name'      => 'National Bank Ltd.',
    'Branch Name'    => 'Anderkilla Branch, Chattogram.',
    'Account Name'   => 'Raj Aiswari',
    'Account Number' => '1101006047348',
    'Routing number' => '150150468',
];
$fixedNote = "Note: Out of these services if any physical service that will be extra charges.\nIf you have any question concerning this Proposal, Please tell us.";
$fixedServices = ['Yearly two physical services', 'Yearly one Calibration', 'All time online service', 'All kind of business support'];

// ---------- Amount in words (Bangladeshi system: thousand / lakh / crore) ----------
if (!function_exists('amb_words')) {
    function amb_words(int $n): string {
        static $ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve',
                        'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
        static $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
        if ($n < 20)   return $ones[$n];
        if ($n < 100)  return $tens[intdiv($n, 10)] . ($n % 10 ? '-' . $ones[$n % 10] : '');
        if ($n < 1000) return $ones[intdiv($n, 100)] . ' hundred' . ($n % 100 ? ' ' . amb_words($n % 100) : '');
        foreach ([[10000000, 'crore'], [100000, 'lakh'], [1000, 'thousand']] as [$div, $name]) {
            if ($n >= $div) return amb_words(intdiv($n, $div)) . ' ' . $name . ($n % $div ? ' ' . amb_words($n % $div) : '');
        }
        return '';
    }
}
if (!function_exists('amb_in_words')) {
    function amb_in_words($amount): string {
        $parts = explode('.', number_format((float)$amount, 2, '.', ''));
        $taka  = (int)$parts[0]; $paisa = (int)$parts[1];
        $w = ($taka > 0 ? amb_words($taka) : 'zero') . ' taka';
        if ($paisa > 0) $w .= ' and ' . amb_words($paisa) . ' paisa';
        return ucfirst($w) . ' only.';
    }
}
if (!function_exists('amb_money')) { function amb_money($v): string { return number_format((float)$v, 2); } }

// ---------- Lab (or ALL labs when no lab is chosen) ----------
$labId = (int)($_GET['id'] ?? 0);
$lab = null;
if ($labId > 0) {
    $st = $conn->prepare("SELECT * FROM gold_testing_lab_customers WHERE id = ? LIMIT 1");
    $st->bind_param("i", $labId); $st->execute();
    $lab = $st->get_result()->fetch_assoc(); $st->close();
}
$overall = ($labId === 0);                                         // no lab chosen: history of ALL laboratories
if (!$overall && !$lab) { header('Location: gold_labs_list.php?msg=notfound'); exit; }

$allLabs = $conn->query("SELECT id, customer_name FROM gold_testing_lab_customers ORDER BY customer_name ASC, id ASC")->fetch_all(MYSQLI_ASSOC);
$allLabIds = array_map(fn($l) => (int)$l['id'], $allLabs);

$labMachines = [];
if (!$overall) {
    $st = $conn->prepare("SELECT m.*, (SELECT COUNT(*) FROM machines_report_machine x WHERE x.machine_id = m.id) AS report_count
                          FROM gold_testing_lab_customers_machine m WHERE m.customer_id = ? ORDER BY m.machine_no ASC, m.id ASC");
    $st->bind_param("i", $labId); $st->execute();
    $labMachines = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
}
$fmtDate = fn($v) => $v ? date('d M Y', strtotime($v)) : '';

// ---------- Filters (all combine with AND) ----------
$gs       = fn(string $k) => is_string($_GET[$k] ?? null) ? trim($_GET[$k]) : '';
$type     = $gs('type');
$dateFrom = $gs('from');
$dateTo   = $gs('to');
$machine  = (int)$gs('machine');
$ref      = $gs('ref');
$orderKey = $gs('order') === 'asc' ? 'asc' : 'desc';
$dir      = $orderKey === 'asc' ? 'ASC' : 'DESC';
if (!in_array($type, ['installation', 'service', 'other', 'billing'], true)) $type = '';
if ($dateFrom !== '' && !$isDate($dateFrom)) $dateFrom = '';
if ($dateTo   !== '' && !$isDate($dateTo))   $dateTo = '';
$fLab = '';                                                        // laboratory filter (all-labs view only): '' = all, 'none' = not linked, or a lab id
if ($overall) {
    $fLab = $gs('lab');
    if ($fLab !== 'none') $fLab = (ctype_digit($fLab) && in_array((int)$fLab, $allLabIds, true)) ? (string)(int)$fLab : '';
}
// machines offered in the Machine filter (this lab's, or every machine grouped by lab)
$machOpts = [];
if ($overall) {
    if ($fLab !== 'none') {
        $sqlM = "SELECT m.id, m.machine_no, m.serial_no, l.customer_name AS lab_name FROM gold_testing_lab_customers_machine m JOIN gold_testing_lab_customers l ON l.id = m.customer_id"
              . (ctype_digit($fLab) ? " WHERE m.customer_id = " . (int)$fLab : '') . " ORDER BY l.customer_name ASC, m.machine_no ASC, m.id ASC";
        $machOpts = $conn->query($sqlM)->fetch_all(MYSQLI_ASSOC);
    }
} else {
    foreach ($labMachines as $m) $machOpts[] = ['id' => $m['id'], 'machine_no' => $m['machine_no'], 'serial_no' => $m['serial_no'], 'lab_name' => $lab['customer_name']];
}
$machineIds = array_map(fn($m) => (int)$m['id'], $machOpts);
if ($machine > 0 && !in_array($machine, $machineIds, true)) $machine = 0;       // only machines that exist in the chosen scope
$page = max(1, (int)($_GET['page'] ?? 1));

$where = []; $types = ''; $params = [];
if (!$overall)              { $where[] = 'u.lab_id = ?'; $types .= 'i'; $params[] = $labId; }
elseif ($fLab === 'none')   { $where[] = 'u.lab_id IS NULL'; }
elseif ($fLab !== '')       { $where[] = 'u.lab_id = ?'; $types .= 'i'; $params[] = (int)$fLab; }
if ($type === 'other')      { $where[] = "u.rec_type NOT IN ('installation','service','billing')"; }
elseif ($type !== '')       { $where[] = 'u.rec_type = ?'; $types .= 's'; $params[] = $type; }
if ($dateFrom !== '')       { $where[] = 'u.rec_date >= ?'; $types .= 's'; $params[] = $dateFrom; }
if ($dateTo !== '')         { $where[] = 'u.rec_date <= ?'; $types .= 's'; $params[] = $dateTo; }
if ($machine > 0)           { $where[] = "(u.rec_type <> 'billing' AND EXISTS (SELECT 1 FROM machines_report_machine x WHERE x.report_id = u.rec_id AND x.machine_id = ?))"; $types .= 'i'; $params[] = $machine; }   // bills are not tied to a machine
if ($ref !== '')            { $where[] = 'u.ref LIKE ?'; $types .= 's'; $params[] = '%' . addcslashes($ref, '%_\\') . '%'; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ---------- One read-only UNION ALL over the existing tables (nothing is copied or duplicated) ----------
$union = "
  SELECT r.report_date AS rec_date, r.report_type AS rec_type, r.id AS rec_id, CONCAT('MR-', LPAD(r.id, 5, '0')) AS ref, uc.name AS creator, r.lab_id AS lab_id, r.customer_name AS snap_name
  FROM machines_report r LEFT JOIN users uc ON uc.id = r.created_by
  UNION ALL
  SELECT b.billing_date, 'billing', b.id, CONCAT('AMB-', LPAD(b.id, 5, '0')), ub.name, b.lab_id, b.customer_name
  FROM machines_billing b LEFT JOIN users ub ON ub.id = b.created_by";
$bind = function ($st, string $t, array $p) { if ($p) $st->bind_param($t, ...$p); };

// ---------- Count + page (each record is a full A4 page, so only a few per screen) ----------
$perPage = 5;
$st = $conn->prepare("SELECT COUNT(*) FROM ($union) u $whereSql");
$bind($st, $types, $params);
$st->execute(); $total = (int)$st->get_result()->fetch_row()[0]; $st->close();

$pages  = max(1, (int)ceil($total / $perPage));
$page   = min($page, $pages);                       // a page number beyond the last one lands on the last page
$offset = ($page - 1) * $perPage;

$st = $conn->prepare("SELECT u.*, l.customer_name AS lab_name FROM ($union) u LEFT JOIN gold_testing_lab_customers l ON l.id = u.lab_id $whereSql ORDER BY u.rec_date $dir, u.rec_id $dir, u.ref $dir LIMIT ? OFFSET ?");
$bind($st, $types . 'ii', array_merge($params, [$perPage, $offset]));
$st->execute(); $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();

// ---------- Full records for the pages being shown ----------
$repIds = []; $billIds = [];
foreach ($rows as $r) { if ($r['rec_type'] === 'billing') $billIds[] = (int)$r['rec_id']; else $repIds[] = (int)$r['rec_id']; }
$fetch = function (string $sql, array $ids) use ($conn): array {
    if (!$ids) return [];
    $st = $conn->prepare(str_replace('{IN}', implode(',', array_fill(0, count($ids), '?')), $sql));
    $st->bind_param(str_repeat('i', count($ids)), ...$ids); $st->execute();
    $out = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
    return $out;
};
$reports = []; $actionsBy = []; $partsBy = []; $bills = []; $itemsBy = [];
foreach ($fetch("SELECT * FROM machines_report WHERE id IN ({IN})", $repIds) as $x) $reports[(int)$x['id']] = $x;
foreach ($fetch("SELECT report_id, action_text FROM machines_report_action WHERE report_id IN ({IN}) ORDER BY report_id, sort_order, id", $repIds) as $x) $actionsBy[(int)$x['report_id']][] = $x['action_text'];
foreach ($fetch("SELECT report_id, part_code, part_description, quantity, billing_instructions FROM machines_report_spareparts WHERE report_id IN ({IN}) ORDER BY report_id, sr_no, id", $repIds) as $x) $partsBy[(int)$x['report_id']][] = $x;
foreach ($fetch("SELECT * FROM machines_billing WHERE id IN ({IN})", $billIds) as $x) $bills[(int)$x['id']] = $x;
foreach ($fetch("SELECT billing_id, sr_no, service, unit_charge, total_amount FROM machines_billing_items WHERE billing_id IN ({IN}) ORDER BY billing_id, sr_no, id", $billIds) as $x) $itemsBy[(int)$x['billing_id']][] = $x;

$filters = ['id' => $labId ?: '', 'lab' => $fLab, 'type' => $type, 'from' => $dateFrom, 'to' => $dateTo, 'machine' => $machine ?: '', 'ref' => $ref, 'order' => $orderKey === 'asc' ? 'asc' : ''];
$qs = function (array $o = []) use ($filters) {
    return '?' . http_build_query(array_filter(array_merge($filters, $o), fn($v) => $v !== '' && $v !== null));
};
$hasFilter = ($type !== '' || $dateFrom !== '' || $dateTo !== '' || $machine > 0 || $ref !== '' || $fLab !== '');
$selMachine = null; foreach ($machOpts as $m) if ((int)$m['id'] === $machine) $selMachine = $m;

$typeLabel = ['installation' => ['Installation', 'screwdriver-wrench'], 'service' => ['Service', 'gears'], 'billing' => ['Annual Billing', 'file-invoice-dollar']];
$dd = fn($v) => $v ? date('d.m.Y', strtotime($v)) : '';

// ---------- A4 page: installation / service report (same layout as machines_report_pdf.php) ----------
$renderReport = function (array $report, array $actions, array $parts) use ($dd) {
    while (count($parts) < 3) $parts[] = ['part_code' => '', 'part_description' => '', 'quantity' => '', 'billing_instructions' => ''];
    $title = $report['report_type'] === 'installation' ? 'INSTALLATION REPORT' : 'SERVICE REPORT'; ?>
<div class="sheet rep">
  <img class="wm" src="rajaiswari-wotbg.png" alt="" onerror="this.remove()">
  <div class="frame">
    <table class="t"><colgroup><col style="width:100%"></colgroup>
      <tr><th class="title"><?= h($title) ?></th></tr></table>

    <table class="t thick"><colgroup><col style="width:52.1%"><col style="width:18.6%"><col style="width:29.3%"></colgroup>
      <tr class="rc"><td class="lb">Customer Name and Address</td><td class="lb">Contact Person</td><td class="v"><?= h($report['contact_person']) ?></td></tr>
      <tr class="rc"><td rowspan="3" class="v" style="vertical-align:top!important"><?= h($report['customer_name']) ?><?= $report['customer_address'] ? "\n" . h($report['customer_address']) : '' ?></td><td class="lb">Desgn./Dept.</td><td class="v"><?= h($report['designation']) ?></td></tr>
      <tr class="rc"><td class="lb">Telephone</td><td class="v"><?= h($report['telephone']) ?></td></tr>
      <tr class="rc"><td class="lb">Email</td><td class="v"><?= h($report['email']) ?></td></tr>
    </table>

    <table class="t thick"><colgroup><col style="width:12.5%"><col style="width:13.5%"><col style="width:12.5%"><col style="width:13.6%"><col style="width:15%"><col style="width:9.5%"><col style="width:9.5%"><col style="width:13.9%"></colgroup>
      <tr class="r1"><td class="lb">Work Start<br>Date</td><td class="v"><?= h($dd($report['work_start_date'])) ?></td><td class="lb">Completion<br>Date</td><td class="v"><?= h($dd($report['completion_date'])) ?></td><td class="lb">Travel Time<br>Hrs.</td><td class="v"><?= h($report['travel_time_hrs']) ?></td><td class="lb">Working<br>Hrs.</td><td class="v"><?= h($report['working_hrs']) ?></td></tr>
    </table>

    <table class="t thick"><colgroup><col style="width:25.7%"><col style="width:26.4%"><col style="width:18.6%"><col style="width:29.3%"></colgroup>
      <tr class="r2"><td class="lb">Instrument Type</td><td class="v"><?= h($report['instrument_type']) ?></td><td class="lb">Model</td><td class="v"><?= h($report['model']) ?></td></tr>
      <tr class="r2"><td class="lb">Instrument Sr. No.</td><td class="v"><?= h($report['instrument_sr_no']) ?></td><td class="lb">Visit Type</td><td class="v"><?= h($report['visit_type']) ?></td></tr>
    </table>

    <table class="t thick"><colgroup><col style="width:100%"></colgroup>
      <tr><td><div class="lbu">Observations</div><div class="v box-obs"><?= h($report['observations']) ?></div></td></tr></table>

    <table class="t thick"><colgroup><col style="width:100%"></colgroup>
      <tr><td><div class="lbu">Action Taken</div><div class="box-act"><?php if ($actions): ?><ol class="acts"><?php foreach ($actions as $a): ?><li<?= $a === '' ? ' class="hw"' : '' ?>><?= $a === '' ? '&nbsp;' : h($a) ?></li><?php endforeach; ?></ol><?php endif; ?></div></td></tr></table>

    <table class="t thick sp"><colgroup><col style="width:8%"><col style="width:18.6%"><col style="width:30.4%"><col style="width:11.4%"><col style="width:31.6%"></colgroup>
      <tr><td colspan="5" class="secthead" style="height:1.9em">SPARE PARTS REPLACED (IF ANY)</td></tr>
      <tr><th class="lb">Sr.<br>No.</th><th class="lb">Part Code</th><th class="lb">Part Description</th><th class="lb">Quantity</th><th class="lb">Instructions for Billing</th></tr>
      <?php foreach ($parts as $i => $p): $has = ($p['part_code'] . $p['part_description'] . $p['quantity'] . $p['billing_instructions']) !== ''; ?><tr class="v"><td class="ctr"><?= $has ? $i + 1 : '' ?></td><td><?= h($p['part_code']) ?></td><td><?= h($p['part_description']) ?></td><td class="ctr"><?= h($p['quantity']) ?></td><td><?= h($p['billing_instructions']) ?></td></tr><?php endforeach; ?>
    </table>

    <table class="t thick"><colgroup><col style="width:100%"></colgroup>
      <tr><td><div class="lbu">Additional Activities</div><div class="v box-add"><?= h($report['additional_activities']) ?></div></td></tr></table>

    <table class="t thick"><colgroup><col style="width:52.1%"><col style="width:47.9%"></colgroup>
      <tr><td class="lb">Engineer's Remarks</td><td class="lb">Customer's Remarks</td></tr>
      <tr><td><div class="sig"><div class="v"><?= h($report['engineer_remarks']) ?></div><div><div class="lb">Engineer Signature:</div><div class="signame"><?= h($report['engineer_name']) ?></div></div></div></td>
          <td><div class="sig"><div class="v"><?= h($report['customer_remarks']) ?></div><div><div class="lb">Customer Signature</div><div class="signame">&nbsp;</div></div></div></td></tr>
    </table>
  </div>
</div>
<?php };

// ---------- A4 page: annual maintenance bill (same layout as machines_billing_pdf.php) ----------
$renderBill = function (array $report, array $items) use ($bank, $fixedNote, $fixedServices) {
    $minRows = 2; ?>
<div class="sheet bil">
  <img class="wm" src="rajaiswari-wotbg.png" alt="" onerror="this.remove()">
  <div class="frame g">
    <table class="t"><colgroup><col style="width:52.1%"><col style="width:18.6%"><col style="width:29.3%"></colgroup>
      <tr class="rc"><td class="lb">Customer Name and Address</td><td class="lb">Contact Person</td><td class="v"><?= h($report['contact_person']) ?></td></tr>
      <tr class="rc"><td rowspan="3" class="v" style="vertical-align:top!important"><?= h($report['customer_name']) ?><?= $report['customer_address'] ? "\n" . h($report['customer_address']) : '' ?></td><td class="lb">Desgn./Dept.</td><td class="v"><?= h($report['designation']) ?></td></tr>
      <tr class="rc"><td class="lb">Telephone</td><td class="v"><?= h($report['telephone']) ?></td></tr>
      <tr class="rc"><td class="lb">Email</td><td class="v"><?= h($report['email']) ?></td></tr>
    </table>
  </div>

  <div class="frame g">
    <table class="t bill"><colgroup><col style="width:12%"><col style="width:50%"><col style="width:19%"><col style="width:19%"></colgroup>
      <tr><th colspan="4" class="ttl">ANNUAL MAINTENANCE SCHEDULE</th></tr>
      <tr class="hd"><th class="ctr">SI</th><th class="ctr">MAINTENANCE SERVICE</th><th class="ctr">Unit Charge</th><th class="ctr">Total Amount</th></tr>
      <?php for ($i = 0, $n = max($minRows, count($items)); $i < $n; $i++): $it = $items[$i] ?? null; ?>
      <tr class="it"><td class="ctr"><?= $it ? $i + 1 : '&nbsp;' ?></td>
        <td class="v"><?= $it ? h($it['service']) : '' ?></td>
        <td class="num"><?= $it ? h(amb_money($it['unit_charge'])) : '' ?></td>
        <td class="num"><?= $it ? h(amb_money($it['total_amount'])) : '' ?></td></tr>
      <?php endfor; ?>
      <tr class="sum"><td>&nbsp;</td><td></td><td class="num">Total:</td><td class="num"><?= h(amb_money($report['subtotal'])) ?></td></tr>
      <tr class="sum"><td>&nbsp;</td><td></td><td class="num">Discount:</td><td class="num"><?= h(amb_money($report['discount'])) ?></td></tr>
      <tr class="sum"><td>&nbsp;</td><td></td><td class="num"><b>Grand Total:</b></td><td class="num"><?= h(amb_money($report['grand_total'])) ?></td></tr>
      <tr><td colspan="4" class="words">In Word: <?= h(amb_in_words($report['grand_total'])) ?><?= $report['payment_status'] === 'paid' ? ' &nbsp;&nbsp; Paid' : '' ?></td></tr>
      <tr><td colspan="4" class="svc serif"><b>Services Included:</b>
        <ol class="svc-list"><?php foreach ($fixedServices as $s): ?><li><?= h($s) ?></li><?php endforeach; ?></ol></td></tr>
      <tr><td colspan="4" class="note serif"><?= nl2br(h($fixedNote)) ?></td></tr>
    </table>
  </div>

  <div class="frame g">
    <table class="t"><colgroup><col style="width:41.6%"><col style="width:58.4%"></colgroup>
      <tr>
        <td class="np"><div class="engbox"><div class="typed v"><?= h($report['engineer_sign_text']) ?></div><div class="sigline serif">Engineer Sign</div></div></td>
        <td class="np"><table class="bank serif">
          <tr><td colspan="2" class="bk-title"><b>Bank Details :</b></td></tr>
          <?php foreach ($bank as $k => $v): ?>
          <tr><td class="bk-l"><?= h($k) ?></td><td class="bk-v">:<?= $v !== '' ? ' ' . h($v) : '' ?></td></tr>
          <?php endforeach; ?>
        </table></td>
      </tr>
    </table>
  </div>
</div>
<?php };
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="favicon.png">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <title><?= $overall ? 'All Labs History' : 'Lab History' ?> — Rajaiswari</title>
  <style>
    :root{--bg:#f1f3f6;--surface:#fff;--surface-2:#fafbfc;--border:#e4e7ec;--bsoft:#f0f1f3;--t1:#111827;--t2:#374151;--t3:#6b7280;--t4:#9ca3af;
      --blue:#2563eb;--blue-bg:#eff6ff;--blue-b:#bfdbfe;--green:#059669;--green-bg:#ecfdf5;--green-b:#a7f3d0;--amber:#d97706;--amber-bg:#fffbeb;--amber-b:#fde68a;
      --red:#dc2626;--red-bg:#fef2f2;--red-b:#fecaca;--violet:#7c3aed;--violet-bg:#f5f3ff;--violet-b:#ddd6fe;--teal:#0f766e;--teal-bg:#f0fdfa;--teal-b:#99f6e4;
      --gray-bg:#f3f4f6;--gray-b:#d1d5db;--r:10px;--rs:6px;--sh:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);}
    body{font-family:'DM Sans',-apple-system,sans-serif;font-size:14.5px;background:var(--bg);color:var(--t1);-webkit-font-smoothing:antialiased;min-height:100vh;}
    .page-shell{margin-left:200px;min-height:100vh;display:flex;flex-direction:column;}
    .top-bar{position:sticky;top:0;z-index:200;min-height:54px;background:var(--surface);border-bottom:1px solid var(--border);box-shadow:var(--sh);display:flex;align-items:center;flex-wrap:wrap;padding:6px 22px;gap:10px;}
    .tb-ico{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;background:var(--teal-bg);color:var(--teal);}
    .tb-title{font-size:1.0625rem;font-weight:700;line-height:1.2;}.tb-sub{font-size:.8rem;color:var(--t4);}
    .tb-right{margin-left:auto;display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
    .btn-add{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 16px;background:var(--green);border:none;border-radius:var(--rs);font-size:.875rem;font-weight:700;color:#fff;text-decoration:none;cursor:pointer;}
    .btn-add:hover{background:#047857;color:#fff;}
    .btn-soft{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 14px;background:var(--surface-2);border:1.5px solid var(--border);border-radius:var(--rs);font-size:.82rem;font-weight:600;color:var(--t2);text-decoration:none;cursor:pointer;}
    .btn-soft:hover{background:var(--border);color:var(--t1);}
    .main{flex:1;padding:20px 22px 60px;display:flex;flex-direction:column;gap:14px;}
    .sec{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;}
    .sec-head{display:flex;align-items:center;gap:9px;padding:12px 18px;background:var(--surface-2);border-bottom:1px solid var(--bsoft);flex-wrap:wrap;}
    .sec-ico{width:28px;height:28px;border-radius:var(--rs);display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;}
    .i-blue{background:var(--blue-bg);color:var(--blue);}.i-violet{background:var(--violet-bg);color:var(--violet);}.i-amber{background:var(--amber-bg);color:var(--amber);}
    .i-green{background:var(--green-bg);color:var(--green);}.i-red{background:var(--red-bg);color:var(--red);}.i-teal{background:var(--teal-bg);color:var(--teal);}
    .sec-title{font-size:.9375rem;font-weight:700;}.sec-meta{margin-left:auto;display:flex;gap:8px;align-items:center;font-size:.78rem;color:var(--t4);}
    .sec-body{padding:16px 18px;}
    .lbl{display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t3);margin-bottom:4px;}
    .lbl .req{color:var(--red);margin-left:2px;}
    .form-control,.form-select{font-size:.9rem;border:1.5px solid var(--border);border-radius:var(--rs);min-height:38px;}
    textarea.form-control{min-height:auto;}
    .form-control:focus,.form-select:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
    .alert-flash{border-radius:var(--rs);padding:12px 16px;font-size:.875rem;display:flex;align-items:center;gap:9px;}
    .af-success{background:var(--green-bg);border:1px solid var(--green-b);border-left:3px solid var(--green);color:#065f46;}
    .af-danger{background:var(--red-bg);border:1px solid var(--red-b);border-left:3px solid var(--red);color:#7f1d1d;}
    .alert-err{background:var(--red-bg);border:1px solid var(--red-b);border-left:3px solid var(--red);border-radius:var(--rs);padding:11px 14px;margin-bottom:14px;}
    .alert-err-title{font-size:.85rem;font-weight:700;color:var(--red);margin-bottom:4px;}
    .alert-err ul{margin:0;padding-left:16px;}.alert-err li{font-size:.82rem;color:var(--red);}
    .utbl{width:100%;border-collapse:collapse;margin:0;}
    .utbl thead th{padding:10px 14px;text-align:left;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--t4);background:var(--surface-2);border-bottom:1px solid var(--border);white-space:nowrap;}
    .utbl tbody td{padding:11px 14px;border-bottom:1px solid var(--bsoft);vertical-align:middle;font-size:.875rem;}
    .utbl tbody tr:last-child td{border-bottom:none;}.utbl tbody tr:hover td{background:#fafbff;}
    .mono{font-family:'DM Mono',monospace;font-size:.82rem;}
    .muted{color:var(--t4);font-size:.78rem;}
    .pill{display:inline-flex;align-items:center;gap:5px;padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:700;border:1px solid;white-space:nowrap;}
    .pill-installation{background:var(--violet-bg);color:var(--violet);border-color:var(--violet-b);}
    .pill-service{background:var(--blue-bg);color:var(--blue);border-color:var(--blue-b);}
    .pill-billing{background:var(--teal-bg);color:var(--teal);border-color:var(--teal-b);}
    .pill-other{background:var(--gray-bg);color:var(--t2);border-color:var(--gray-b);}
    .pill-count{background:var(--surface-2);color:var(--t2);border-color:var(--border);}
    .btn-act{display:inline-flex;align-items:center;gap:5px;height:30px;padding:0 12px;border-radius:var(--rs);font-size:.78rem;font-weight:700;text-decoration:none;border:1.5px solid;background:none;cursor:pointer;white-space:nowrap;}
    .btn-edit{background:var(--amber-bg);color:#92400e;border-color:var(--amber-b);}.btn-edit:hover{background:var(--amber);color:#fff;border-color:var(--amber);}
    .btn-pdf{background:var(--red-bg);color:#991b1b;border-color:var(--red-b);}.btn-pdf:hover{background:var(--red);color:#fff;border-color:var(--red);}
    .btn-view{background:var(--blue-bg);color:#1d4ed8;border-color:var(--blue-b);}.btn-view:hover{background:var(--blue);color:#fff;border-color:var(--blue);}
    .btn-mach{background:var(--teal-bg);color:var(--teal);border-color:var(--teal-b);}.btn-mach:hover{background:var(--teal);color:#fff;border-color:var(--teal);}
    .btn-hist{background:var(--violet-bg);color:var(--violet);border-color:var(--violet-b);}.btn-hist:hover{background:var(--violet);color:#fff;border-color:var(--violet);}
    .empty{text-align:center;padding:48px 16px;color:var(--t4);}.empty i{font-size:2rem;margin-bottom:10px;display:block;}
    .pager{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;}
    .pager .info{font-size:.82rem;color:var(--t3);}
    .btn-pg{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 14px;border:1.5px solid var(--border);border-radius:var(--rs);background:var(--surface);font-size:.82rem;font-weight:600;color:var(--t2);text-decoration:none;}
    .btn-pg:hover{background:var(--surface-2);color:var(--t1);}.btn-pg.disabled{opacity:.45;pointer-events:none;}
    .kv{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px 18px;}
    .kv .k{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t4);margin-bottom:2px;}
    .kv .v{font-size:.9rem;color:var(--t1);word-break:break-word;}.kv .wide{grid-column:1/-1;}
    .modal-header{background:var(--surface-2);}.modal-title{font-size:1rem;font-weight:700;}
    .modal-footer{background:var(--surface-2);}
    .btn-submit{display:inline-flex;align-items:center;gap:7px;height:40px;padding:0 22px;border:none;border-radius:7px;font-weight:700;color:#fff;background:var(--green);}
    .btn-submit.edit{background:var(--amber);}.btn-submit:hover{filter:brightness(.92);}
    .btn-ghost{display:inline-flex;align-items:center;gap:6px;height:40px;padding:0 18px;background:var(--surface);border:1.5px solid var(--border);border-radius:7px;font-weight:500;color:var(--t2);}
    .btn-ghost:hover{background:var(--surface-2);color:var(--t1);}
    @media(max-width:991.98px){.page-shell{margin-left:0;padding-top:52px;}.top-bar{top:52px;padding:8px 14px;}.main{padding:14px 14px 50px;}}
    @media(max-width:767.98px){
      .kv{grid-template-columns:1fr 1fr;}
      .utbl thead{display:none;}
      .utbl,.utbl tbody,.utbl tr,.utbl td{display:block;width:100%;}
      .utbl tbody tr{border-bottom:8px solid var(--bg);padding:6px 0;}
      .utbl tbody td{border:none;padding:5px 14px;display:flex;gap:10px;justify-content:space-between;text-align:right;}
      .utbl tbody td::before{content:attr(data-label);font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t4);text-align:left;flex-shrink:0;padding-top:2px;}
      .utbl tbody td.td-act{justify-content:flex-end;padding-bottom:10px;}.utbl tbody td.td-act::before{display:none;}
      .btn-act{height:36px;flex:1;justify-content:center;}.td-act{gap:8px!important;}.td-act>div{width:100%;}
    }
    /* ---- formal info tables (same as the lab machines page) ---- */
    .ftbl{width:100%;border-collapse:collapse;margin:0;}
    .ftbl th{background:#f3f4f6;color:#111827;width:15%;text-align:left;font-size:.82rem;font-weight:700;border:1px solid #cbd5e1;padding:10px 12px;vertical-align:top;}
    .ftbl td{border:1px solid #e2e8f0;padding:10px 12px;vertical-align:top;font-size:.875rem;word-break:break-word;}
    @media(min-width:768px){
      .utbl{border:1px solid #cbd5e1;}
      .utbl thead th{background:#f3f4f6;color:#111827;text-transform:none;letter-spacing:0;font-size:.82rem;font-weight:700;border:1px solid #cbd5e1;padding:10px 12px;}
      .utbl tbody td{border:1px solid #e2e8f0;vertical-align:top;padding:10px 12px;}
      .utbl tbody tr:hover td{background:#fff;}
    }
    @media(max-width:767.98px){
      .ftbl tr{display:block;border-bottom:1px solid #cbd5e1;}
      .ftbl th,.ftbl td{display:block;width:100%;border:none;padding:6px 12px;}
      .ftbl th{background:none;color:var(--t4);font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;padding-bottom:0;}
    }
    .sec{border-radius:4px;border-color:#cbd5e1;box-shadow:none;}
    .sec-head{background:#f3f4f6;border-bottom:1px solid #cbd5e1;}
    .form-control,.form-select{border:1px solid #9ca3af;border-radius:4px;}

    /* ---- one A4 page per record (letterhead sheet, same as the PDF pages) ---- */
    .pgs{display:flex;flex-direction:column;gap:26px;align-items:center;}
    .pg{max-width:100%;}
    .pg-cap{display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:#f3f4f6;border:1px solid #cbd5e1;border-bottom:0;border-radius:4px 4px 0 0;padding:8px 12px;font-size:.85rem;}
    .pg-cap .ms-auto{margin-left:auto;display:flex;gap:8px;}
    .paper-wrap{margin:0 auto;}
    .paper{width:210mm;height:296.6mm;padding:1in 0.5in;background:#fffee6 url('machines_report_letterhead.png') 0 0/100% 100% no-repeat;box-shadow:0 2px 14px rgba(0,0,0,.2);transform-origin:top left;overflow:hidden;box-sizing:border-box;}
    .paper.nobg{background:#fff;}
    .sheet{position:relative;width:184mm;font-family:Arial,'Liberation Sans',Helvetica,sans-serif;font-size:12pt;line-height:1.25;color:#000;}
    .sheet *{font-family:inherit;}
    .sheet .wm{position:absolute;left:50%;top:50%;width:62%;transform:translate(-50%,-50%);opacity:.07;z-index:0;pointer-events:none;}
    .sheet .frame{position:relative;z-index:1;border:.17em solid #000;overflow:hidden;background:transparent;}
    .sheet table.t{width:calc(100% + .14em);margin:-.07em;table-layout:fixed;border-collapse:collapse;}
    .sheet table.t td,.sheet table.t th{border:.07em solid #000;padding:.22em .45em;vertical-align:top;overflow-wrap:break-word;word-break:normal;hyphens:none;text-align:left;}
    .sheet table.t.thick tr:first-child>td,.sheet table.t.thick tr:first-child>th{border-top:.14em solid #000;}
    .sheet .lb{font-weight:700;white-space:nowrap;}
    .sheet .lbu{font-weight:700;text-decoration:underline;white-space:nowrap;}
    .sheet .secthead{text-align:center!important;font-weight:700;}
    .sheet .ctr{text-align:center!important;}
    /* installation / service report */
    .rep .v{font-size:1.05em;white-space:pre-wrap;}
    .rep .title{font-size:2.1em;font-weight:700;text-align:center!important;padding:.1em 0!important;line-height:1.2;}
    .rep .rc>td{height:2.4em;vertical-align:middle!important;}
    .rep .r1{height:2.6em;}.rep .r2{height:2.1em;}
    .rep .box-obs{min-height:3.4em;}.rep .box-act{min-height:6.6em;}.rep .box-add{min-height:4.8em;}
    .rep ol.acts{margin:.3em 0 0;padding-left:2.4em;}.rep ol.acts li{margin:.18em 0;font-size:1.05em;}
    .rep ol.acts li.hw{border-bottom:.07em dotted #000;margin-right:1.5em;}
    .rep table.sp td,.rep table.sp th{height:2.1em;}
    .rep table.sp th{height:3em;}
    .rep .sig{display:flex;flex-direction:column;justify-content:space-between;min-height:11em;padding-bottom:1.6em;}
    .rep .signame{margin-top:.3em;font-size:1.05em;min-height:1.2em;}
    /* annual maintenance bill (light-grey thin grid) */
    .bil .v{font-size:1em;white-space:pre-wrap;}
    .bil .v{text-shadow:.006em 0 0 currentColor,-.006em 0 0 currentColor;}
    .bil .rc>td{height:1.5em;vertical-align:middle!important;}
    .bil .frame+.frame{margin-top:1.1em;}
    .bil .frame.g{border:.06em solid #8c8c8c;}
    .bil .frame.g table.t td,.bil .frame.g table.t th{border:.06em solid #8c8c8c;}
    .bil .serif{font-family:'Times New Roman',Times,'Liberation Serif',serif;}
    .bil table.t.bill th.ttl{text-align:center;font-weight:700;font-size:1.1em;padding:.3em!important;}
    .bil table.t.bill tr.hd th{font-weight:700;padding:.3em .45em;vertical-align:middle;}
    .bil table.t.bill tr.it td,.bil table.t.bill tr.sum td{height:1.55em;}
    .bil table.t.bill td.num{text-align:right;white-space:nowrap;padding-right:.6em;}
    .bil table.t.bill td.words{text-align:center;font-weight:700;padding:.3em .45em;}
    .bil table.t.bill td.note{text-align:center;font-size:.85em;padding:.3em .45em;line-height:1.3;}
    .bil table.t.bill td.svc{text-align:left;font-size:.95em;padding:.3em .6em;line-height:1.3;}
    .bil table.t.bill td.svc ol.svc-list{margin:.15em 0 0;padding-left:1.6em;}
    .bil table.t.bill td.svc ol.svc-list li{margin:.05em 0;}
    .bil .engbox{display:flex;flex-direction:column;justify-content:flex-end;min-height:11em;padding:.4em .9em .5em;}
    .bil .engbox .typed{text-align:center;min-height:2.6em;display:flex;align-items:flex-end;justify-content:center;white-space:pre-wrap;padding-bottom:.15em;}
    .bil .engbox .sigline{width:72%;margin:0 auto;border-top:.07em solid #000;text-align:center;padding-top:.2em;}
    .bil table.t table.bank{width:100%;table-layout:auto;border-collapse:collapse;}
    .bil .frame.g table.t table.bank td{border:0;padding:.12em .45em;vertical-align:middle;}
    .bil table.t table.bank td.bk-title{font-size:1.2em;padding-top:.2em;padding-bottom:.15em;}
    .bil table.t table.bank td.bk-l{width:32%;white-space:nowrap;}
  </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page-shell">
  <header class="top-bar">
    <div class="tb-ico"><i class="fas fa-clock-rotate-left"></i></div>
    <div><div class="tb-title"><?= $overall ? 'All Labs History' : 'Lab History' ?></div><div class="tb-sub"><?= $overall ? number_format($total) . ' record' . ($total === 1 ? '' : 's') . ' · ' . count($allLabs) . ' laborator' . (count($allLabs) === 1 ? 'y' : 'ies') . ($hasFilter ? ' (filtered)' : '') : h($lab['customer_name']) ?></div></div>
    <div class="tb-right">
      <div class="form-check form-switch m-0 me-1"><input class="form-check-input" type="checkbox" id="bgToggle" checked><label class="form-check-label small" for="bgToggle">Letterhead</label></div>
      <div class="form-check form-switch m-0 me-1"><input class="form-check-input" type="checkbox" id="wmToggle" checked><label class="form-check-label small" for="wmToggle">Watermark</label></div>
      <?php if (!$overall): ?><a href="gold_lab_machines.php?lab=<?= (int)$lab['id'] ?>" class="btn-soft"><i class="fas fa-microchip"></i><span class="d-none d-sm-inline"> Machines</span></a>
      <a href="gold_lab_history.php" class="btn-soft"><i class="fas fa-layer-group"></i><span class="d-none d-sm-inline"> All Labs History</span></a><?php endif; ?>
      <a href="gold_labs_list.php" class="btn-soft"><i class="fas fa-arrow-left"></i><span class="d-none d-sm-inline"> Labs</span></a>
    </div>
  </header>

  <main class="main">
    <?php if (!$overall): ?>
    <!-- 1. Lab information -->
    <div class="sec">
      <div class="sec-head"><div class="sec-ico i-blue"><i class="fas fa-building"></i></div><div class="sec-title">Lab Information</div></div>
      <div class="sec-body" style="padding:0;"><table class="ftbl">
        <tr><th>Customer Name</th><td class="fw-bold"><?= h($lab['customer_name']) ?></td><th>Contact Person</th><td><?= h($lab['contact_person'] ?: '—') ?></td></tr>
        <tr><th>Designation / Dept.</th><td><?= h($lab['designation_department'] ?: '—') ?></td><th>Telephone</th><td><?= h($lab['telephone'] ?: '—') ?></td></tr>
        <tr><th>Email</th><td><?= h($lab['email'] ?: '—') ?></td><th>Address</th><td><?= h($lab['address'] ?: '—') ?></td></tr>
        <?php if (array_key_exists('remarks', $lab) && trim((string)$lab['remarks']) !== ''): ?><tr><th>Remarks</th><td colspan="3" style="white-space:pre-line;"><?= h($lab['remarks']) ?></td></tr><?php endif; ?>
      </table></div>
    </div>

    <!-- 2. Machines -->
    <div class="sec">
      <div class="sec-head"><div class="sec-ico i-teal"><i class="fas fa-microchip"></i></div><div class="sec-title">Machines</div>
        <div class="sec-meta"><?= count($labMachines) ?> machine<?= count($labMachines) === 1 ? '' : 's' ?></div></div>
      <?php if (!$labMachines): ?>
        <div class="empty" style="padding:26px 16px;"><i class="fas fa-box-open" style="font-size:1.4rem"></i>No machines registered for this laboratory yet.</div>
      <?php else: ?>
      <table class="utbl">
        <thead><tr><th style="width:64px;">Sl. No.</th><th>Machine No.</th><th>Model No.</th><th>Serial No.</th><th>Installation Date</th><th style="text-align:center">Reports</th><th style="text-align:right">History</th></tr></thead>
        <tbody>
        <?php foreach ($labMachines as $i => $m): ?>
          <tr>
            <td data-label="Sl. No."><?= $i + 1 ?></td>
            <td data-label="Machine No."><span class="fw-bold"><?= h($m['machine_no']) ?></span></td>
            <td data-label="Model No."><?= h($m['model_no'] ?: '—') ?></td>
            <td data-label="Serial No."><span class="mono"><?= h($m['serial_no'] ?: '—') ?></span></td>
            <td data-label="Installation Date"><?= $m['installation_date'] ? h($fmtDate($m['installation_date'])) : '—' ?></td>
            <td data-label="Reports" style="text-align:center"><?= (int)$m['report_count'] ?></td>
            <td class="td-act" style="text-align:right;white-space:nowrap;"><a class="btn-act btn-hist" href="<?= h($qs(['machine' => (int)$m['id'], 'page' => null])) ?>#records"><i class="fas fa-clock-rotate-left"></i> History</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <?php endif; ?>

    <!-- 3. Reports and bills, one page each, by date -->
    <form class="sec" method="get" id="records" action="#records">
      <?php if (!$overall): ?><input type="hidden" name="id" value="<?= (int)$labId ?>"><?php endif; ?>
      <div class="sec-head"><div class="sec-ico i-violet"><i class="fas fa-file-lines"></i></div><div class="sec-title"><?= $overall ? 'All Reports &amp; Bills' : 'Reports &amp; Bills' ?></div>
        <div class="sec-meta"><strong><?= number_format($total) ?></strong>&nbsp;record<?= $total === 1 ? '' : 's' ?><?= $hasFilter ? ' (filtered)' : '' ?> · one page each, by date</div></div>
      <div class="sec-body">
        <div class="row g-2 align-items-end">
          <?php if ($overall): ?>
          <div class="col-12 col-lg-3">
            <label class="lbl" for="lab">Laboratory</label>
            <select id="lab" name="lab" class="form-select">
              <option value="">All laboratories</option>
              <option value="none" <?= $fLab === 'none' ? 'selected' : '' ?>>— Not linked to any lab —</option>
              <?php foreach ($allLabs as $l): ?><option value="<?= (int)$l['id'] ?>" <?= $fLab === (string)$l['id'] ? 'selected' : '' ?>><?= h($l['customer_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <div class="col-6 col-lg-2">
            <label class="lbl" for="type">Record type</label>
            <select id="type" name="type" class="form-select">
              <option value="">All</option>
              <?php foreach (['installation' => 'Installation', 'service' => 'Service', 'other' => 'Other', 'billing' => 'Annual Billing'] as $k => $lbl): ?>
                <option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= $lbl ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-lg-2"><label class="lbl" for="from">From</label><input type="date" id="from" name="from" class="form-control" value="<?= h($dateFrom) ?>"></div>
          <div class="col-6 col-lg-2"><label class="lbl" for="to">To</label><input type="date" id="to" name="to" class="form-control" value="<?= h($dateTo) ?>"></div>
          <div class="col-6 col-lg-2">
            <label class="lbl" for="machine">Machine</label>
            <select id="machine" name="machine" class="form-select">
              <option value="">All machines</option>
              <?php $curGrp = null; foreach ($machOpts as $m):
                  if ($overall && $curGrp !== $m['lab_name']) { if ($curGrp !== null) echo '</optgroup>'; echo '<optgroup label="' . h($m['lab_name']) . '">'; $curGrp = $m['lab_name']; } ?>
                <option value="<?= (int)$m['id'] ?>" <?= $machine === (int)$m['id'] ? 'selected' : '' ?>><?= h($m['machine_no'] . ($m['serial_no'] ? ' · ' . $m['serial_no'] : '')) ?></option>
              <?php endforeach; if ($overall && $curGrp !== null) echo '</optgroup>'; ?>
            </select>
          </div>
          <div class="col-6 col-lg-2"><label class="lbl" for="ref">Reference</label><input type="text" id="ref" name="ref" class="form-control" value="<?= h($ref) ?>" placeholder="MR-00001 / AMB-00002"></div>
          <div class="col-6 col-lg-2">
            <label class="lbl" for="order">Order</label>
            <select id="order" name="order" class="form-select">
              <option value="desc" <?= $orderKey === 'desc' ? 'selected' : '' ?>>Newest first</option>
              <option value="asc" <?= $orderKey === 'asc' ? 'selected' : '' ?>>Oldest first</option>
            </select>
          </div>
          <div class="col-12 d-flex gap-2 justify-content-end">
            <?php if ($hasFilter || $orderKey === 'asc'): ?><a href="gold_lab_history.php<?= $overall ? '' : '?id=' . (int)$labId ?>#records" class="btn btn-outline-secondary" style="min-height:38px;"><i class="fas fa-xmark"></i> Clear</a><?php endif; ?>
            <button class="btn btn-primary fw-bold" style="min-height:38px;"><i class="fas fa-filter"></i> Apply</button>
          </div>
        </div>
        <?php if ($selMachine): ?><div class="muted mt-2">Showing reports of <strong><?= h($selMachine['machine_no']) ?></strong><?= $selMachine['serial_no'] ? ' · ' . h($selMachine['serial_no']) : '' ?> only. Annual bills are not tied to a machine, so they are hidden.</div><?php endif; ?>
      </div>
    </form>

    <?php if (!$rows): ?>
      <div class="sec"><div class="empty"><i class="fas fa-file-circle-question"></i><?= $hasFilter ? 'No records match these filters.' : ($overall ? 'There are no reports or bills yet.' : 'No reports or bills are linked to this laboratory yet.') ?></div></div>
    <?php else: ?>
    <div class="pgs" id="pgs">
      <?php foreach ($rows as $i => $r):
          $isBill = $r['rec_type'] === 'billing';
          $tk = isset($typeLabel[$r['rec_type']]) ? $r['rec_type'] : 'other';
          [$tLbl, $tIco] = $typeLabel[$r['rec_type']] ?? [ucfirst((string)$r['rec_type']), 'file-lines'];
          $rid = (int)$r['rec_id'];
          $data = $isBill ? ($bills[$rid] ?? null) : ($reports[$rid] ?? null);
          if (!$data) continue;
          $pdfUrl  = ($isBill ? 'machines_billing_pdf.php' : 'machines_report_pdf.php') . '?id=' . $rid;
          $editUrl = ($isBill ? 'machines_billing_form.php' : 'machines_report_form.php') . '?id=' . $rid; ?>
      <div class="pg">
        <div class="pg-cap">
          <span class="fw-bold">#<?= $offset + $i + 1 ?></span>
          <span class="pill pill-<?= h($tk) ?>"><i class="fas fa-<?= h($tIco) ?>"></i><?= h($tLbl) ?></span>
          <span class="mono fw-bold"><?= h($r['ref']) ?></span>
          <span><?= h(date('d M Y', strtotime($r['rec_date']))) ?></span>
          <?php if ($overall): ?><?php if ($r['lab_id']): ?><a class="fw-bold" style="color:var(--teal);text-decoration:none;" href="gold_lab_history.php?id=<?= (int)$r['lab_id'] ?>" title="Open this laboratory's history"><i class="fas fa-flask-vial"></i> <?= h($r['lab_name']) ?></a><?php else: ?><span class="muted" title="This record is not linked to a laboratory"><i class="fas fa-link-slash"></i> <?= h($r['snap_name']) ?> (not linked)</span><?php endif; ?><?php endif; ?>
          <span class="muted">by <?= h($r['creator'] ?? '—') ?></span>
          <span class="ms-auto">
            <a class="btn-act btn-pdf" href="<?= h($pdfUrl) ?>" target="_blank" rel="noopener" title="Open to view, print or download the PDF"><i class="fas fa-file-pdf"></i> View / Print / PDF</a>
            <a class="btn-act btn-edit" href="<?= h($editUrl) ?>"><i class="fas fa-pen"></i> Edit</a>
          </span>
        </div>
        <div class="paper-wrap"><div class="paper">
          <?php if ($isBill) $renderBill($data, $itemsBy[$rid] ?? []); else $renderReport($data, $actionsBy[$rid] ?? [], $partsBy[$rid] ?? []); ?>
        </div></div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div class="muted">Page <?= $page ?> of <?= $pages ?> · <?= number_format($total) ?> total</div>
      <div class="d-flex gap-2">
        <a class="btn-pg <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= h($qs(['page' => $page - 1 > 1 ? $page - 1 : null])) ?>#records"><i class="fas fa-chevron-left"></i> Previous</a>
        <a class="btn-pg <?= $page >= $pages ? 'disabled' : '' ?>" href="<?= h($qs(['page' => $page + 1])) ?>#records">Next <i class="fas fa-chevron-right"></i></a>
      </div>
    </div>
    <?php endif; ?>
  </main>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  var MAX_PX = 240 / 25.4 * 96;                 // printable height between the 1 in top/bottom margins (same rule as the PDF pages)
  var holder = document.getElementById('pgs');
  if (!holder) return;
  function fit(sheet) {                         // shrink the type slightly if a long record would spill past one A4 page
    var fs = 12; sheet.style.fontSize = fs + 'pt';
    while (sheet.offsetHeight > MAX_PX && fs > 6) { fs -= 0.25; sheet.style.fontSize = fs + 'pt'; }
  }
  function scale(pg) {                          // scale the A4 sheet down to the screen width (preview only)
    var wrap = pg.querySelector('.paper-wrap'), paper = pg.querySelector('.paper');
    paper.style.transform = 'none';
    var pw = paper.offsetWidth, ph = paper.offsetHeight, avail = holder.parentNode.clientWidth;
    var s = Math.min(1, avail / pw);
    paper.style.transform = 'scale(' + s + ')';
    wrap.style.width = (pw * s) + 'px'; wrap.style.height = (ph * s) + 'px';
    pg.style.width = (pw * s) + 'px';
  }
  function all(fn) { Array.prototype.forEach.call(holder.querySelectorAll('.pg'), fn); }
  function init() { all(function (pg) { fit(pg.querySelector('.sheet')); scale(pg); }); }
  window.addEventListener('load', init);
  window.addEventListener('resize', function () { all(scale); });
  init();

  document.getElementById('bgToggle').addEventListener('change', function () {
    var on = this.checked; all(function (pg) { pg.querySelector('.paper').classList.toggle('nobg', !on); });
  });
  document.getElementById('wmToggle').addEventListener('change', function () {
    var on = this.checked; all(function (pg) { var w = pg.querySelector('.wm'); if (w) w.style.display = on ? '' : 'none'; });
  });
})();
</script>
</body>
</html>