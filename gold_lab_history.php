<?php
require 'auth.php';
require 'mydb.php';
require_engineering();               // admin + engineer employee only (server-side, re-checked against DB)

if (isset($conn)) { $conn->set_charset('utf8mb4'); }
if (!function_exists('h')) { function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

$isDate = function ($d) { $o = DateTime::createFromFormat('Y-m-d', (string)$d); return $o && $o->format('Y-m-d') === $d; };

// ---------- Lab ----------
$labId = (int)($_GET['id'] ?? 0);
$lab = null;
if ($labId > 0) {
    $st = $conn->prepare("SELECT * FROM gold_testing_lab_customers WHERE id = ? LIMIT 1");
    $st->bind_param("i", $labId); $st->execute();
    $lab = $st->get_result()->fetch_assoc(); $st->close();
}
if (!$lab) { header('Location: gold_labs_list.php?msg=notfound'); exit; }

$st = $conn->prepare("SELECT id, machine_no, model_no, serial_no FROM gold_testing_lab_customers_machine WHERE customer_id = ? ORDER BY machine_no ASC, id ASC");
$st->bind_param("i", $labId); $st->execute();
$labMachines = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
$machineIds = array_map(fn($m) => (int)$m['id'], $labMachines);

// ---------- Filters (all combine with AND) ----------
$gs       = fn(string $k) => is_string($_GET[$k] ?? null) ? trim($_GET[$k]) : '';
$type     = $gs('type');
$dateFrom = $gs('from');
$dateTo   = $gs('to');
$machine  = (int)$gs('machine');
$ref      = $gs('ref');
if (!in_array($type, ['installation', 'service', 'other', 'billing'], true)) $type = '';
if ($dateFrom !== '' && !$isDate($dateFrom)) $dateFrom = '';
if ($dateTo   !== '' && !$isDate($dateTo))   $dateTo = '';
if ($machine > 0 && !in_array($machine, $machineIds, true)) $machine = 0;       // only this lab's machines
$page = max(1, (int)($_GET['page'] ?? 1));

$where = []; $types = ''; $params = [];
if ($type === 'other')      { $where[] = "u.rec_type NOT IN ('installation','service','billing')"; }
elseif ($type !== '')       { $where[] = 'u.rec_type = ?'; $types .= 's'; $params[] = $type; }
if ($dateFrom !== '')       { $where[] = 'u.rec_date >= ?'; $types .= 's'; $params[] = $dateFrom; }
if ($dateTo !== '')         { $where[] = 'u.rec_date <= ?'; $types .= 's'; $params[] = $dateTo; }
if ($machine > 0)           { $where[] = "(u.rec_type <> 'billing' AND EXISTS (SELECT 1 FROM machines_report_machine x WHERE x.report_id = u.rec_id AND x.machine_id = ?))"; $types .= 'i'; $params[] = $machine; }   // billing is not tied to a machine, so it drops out
if ($ref !== '')            { $where[] = 'u.ref LIKE ?'; $types .= 's'; $params[] = '%' . addcslashes($ref, '%_\\') . '%'; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ---------- One read-only UNION ALL over the existing tables (nothing is copied or duplicated) ----------
$union = "
  SELECT r.report_date AS rec_date, r.report_type AS rec_type, r.id AS rec_id, CONCAT('MR-', LPAD(r.id, 5, '0')) AS ref,
         r.machine_id AS machine_id, (SELECT GROUP_CONCAT(mm.machine_no ORDER BY mm.machine_no SEPARATOR ', ') FROM machines_report_machine x JOIN gold_testing_lab_customers_machine mm ON mm.id = x.machine_id WHERE x.report_id = r.id) AS m_no,
         (SELECT GROUP_CONCAT(mm.serial_no ORDER BY mm.machine_no SEPARATOR ', ') FROM machines_report_machine x JOIN gold_testing_lab_customers_machine mm ON mm.id = x.machine_id WHERE x.report_id = r.id AND mm.serial_no IS NOT NULL) AS m_serial,
         r.instrument_type AS snap_type, r.model AS snap_model, r.instrument_sr_no AS snap_sr,
         LEFT(r.observations, 160) AS descr, NULL AS amount, NULL AS pay_status, uc.name AS creator
  FROM machines_report r
  LEFT JOIN users uc ON uc.id = r.created_by
  WHERE r.lab_id = ?
  UNION ALL
  SELECT b.billing_date, 'billing', b.id, CONCAT('AMB-', LPAD(b.id, 5, '0')),
         NULL, NULL, NULL, NULL, NULL, NULL,
         (SELECT GROUP_CONCAT(i.service ORDER BY i.sr_no SEPARATOR '; ') FROM machines_billing_items i WHERE i.billing_id = b.id),
         b.grand_total, b.payment_status, ub.name
  FROM machines_billing b
  LEFT JOIN users ub ON ub.id = b.created_by
  WHERE b.lab_id = ?";
$baseTypes = 'ii'; $baseParams = [$labId, $labId];

// ---------- Count + page ----------
$perPage = 20;
$st = $conn->prepare("SELECT COUNT(*) FROM ($union) u $whereSql");
$st->bind_param($baseTypes . $types, ...array_merge($baseParams, $params));
$st->execute(); $total = (int)$st->get_result()->fetch_row()[0]; $st->close();

$pages  = max(1, (int)ceil($total / $perPage));
$page   = min($page, $pages);                       // a page number beyond the last one lands on the last page
$offset = ($page - 1) * $perPage;

$st = $conn->prepare("SELECT u.* FROM ($union) u $whereSql ORDER BY u.rec_date DESC, u.rec_id DESC, u.ref DESC LIMIT ? OFFSET ?");
$st->bind_param($baseTypes . $types . 'ii', ...array_merge($baseParams, $params, [$perPage, $offset]));
$st->execute(); $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();

$filters = ['id' => $labId, 'type' => $type, 'from' => $dateFrom, 'to' => $dateTo, 'machine' => $machine ?: '', 'ref' => $ref];
$qs = function (array $o = []) use ($filters) {
    return '?' . http_build_query(array_filter(array_merge($filters, $o), fn($v) => $v !== '' && $v !== null));
};
$hasFilter = ($type !== '' || $dateFrom !== '' || $dateTo !== '' || $machine > 0 || $ref !== '');

$typeLabel = ['installation' => ['Installation', 'screwdriver-wrench'], 'service' => ['Service', 'gears'], 'billing' => ['Annual Billing', 'file-invoice-dollar']];
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
  <title>Lab History — Rajaiswari</title>
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
  </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page-shell">
  <header class="top-bar">
    <div class="tb-ico"><i class="fas fa-clock-rotate-left"></i></div>
    <div><div class="tb-title">Lab History</div><div class="tb-sub"><?= h($lab['customer_name']) ?></div></div>
    <div class="tb-right">
      <a href="gold_lab_machines.php?lab=<?= (int)$lab['id'] ?>" class="btn-soft"><i class="fas fa-microchip"></i><span class="d-none d-sm-inline"> Machines</span></a>
      <a href="gold_labs_list.php" class="btn-soft"><i class="fas fa-arrow-left"></i><span class="d-none d-sm-inline"> Labs</span></a>
    </div>
  </header>

  <main class="main">
    <form class="sec" method="get">
      <input type="hidden" name="id" value="<?= (int)$labId ?>">
      <div class="sec-body">
        <div class="row g-2 align-items-end">
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
          <div class="col-6 col-lg-3">
            <label class="lbl" for="machine">Machine</label>
            <select id="machine" name="machine" class="form-select">
              <option value="">All machines</option>
              <?php foreach ($labMachines as $m): ?>
                <option value="<?= (int)$m['id'] ?>" <?= $machine === (int)$m['id'] ? 'selected' : '' ?>><?= h($m['machine_no'] . ($m['serial_no'] ? ' · ' . $m['serial_no'] : '')) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-lg-3">
            <label class="lbl" for="ref">Reference</label>
            <input type="text" id="ref" name="ref" class="form-control" value="<?= h($ref) ?>" placeholder="e.g. MR-00001 or AMB-00002">
          </div>
          <div class="col-12 d-flex gap-2 justify-content-end">
            <?php if ($hasFilter): ?><a href="gold_lab_history.php?id=<?= (int)$labId ?>" class="btn btn-outline-secondary" style="min-height:38px;"><i class="fas fa-xmark"></i> Clear</a><?php endif; ?>
            <button class="btn btn-primary fw-bold" style="min-height:38px;"><i class="fas fa-filter"></i> Apply filters</button>
          </div>
        </div>
      </div>
    </form>

    <div class="pager">
      <div class="info"><strong><?= number_format($total) ?></strong> record<?= $total === 1 ? '' : 's' ?> found<?= $hasFilter ? ' (filtered)' : '' ?><?= $machine > 0 ? ' · billing is not tied to a machine, so it is hidden while a machine is selected' : '' ?></div>
    </div>

    <div class="sec">
      <?php if (!$rows): ?>
        <div class="empty"><i class="fas fa-file-circle-question"></i><?= $hasFilter ? 'No records match these filters.' : 'No records are linked to this laboratory yet.' ?></div>
      <?php else: ?>
      <table class="utbl">
        <thead><tr><th>Date</th><th>Record Type</th><th>Machine</th><th>Reference</th><th>Description</th><th>Created By</th><th style="text-align:right">Action</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
            $isBill = $r['rec_type'] === 'billing';
            $tk     = isset($typeLabel[$r['rec_type']]) ? $r['rec_type'] : 'other';
            [$tLbl, $tIco] = $typeLabel[$r['rec_type']] ?? [ucfirst((string)$r['rec_type']), 'file-lines'];
            $pdfUrl  = ($isBill ? 'machines_billing_pdf.php' : 'machines_report_pdf.php') . '?id=' . (int)$r['rec_id'];
            $editUrl = ($isBill ? 'machines_billing_form.php' : 'machines_report_form.php') . '?id=' . (int)$r['rec_id'];
            $descr = trim((string)preg_replace('/\s+/', ' ', (string)$r['descr']));
            if ($isBill) { $descr = ($descr !== '' ? $descr : 'Annual maintenance bill'); }
            elseif ($descr === '') { $descr = trim($tLbl . ' report'); } ?>
          <tr>
            <td data-label="Date"><?= h(date('d M Y', strtotime($r['rec_date']))) ?></td>
            <td data-label="Record Type"><span class="pill pill-<?= h($tk) ?>"><i class="fas fa-<?= h($tIco) ?>"></i><?= h($tLbl) ?></span></td>
            <td data-label="Machine">
              <?php if ($r['m_no']): ?><div class="fw-bold"><?= h($r['m_no']) ?></div><?php if ($r['m_serial']): ?><div class="muted mono"><?= h($r['m_serial']) ?></div><?php endif; ?>
              <?php elseif (!$isBill && trim($r['snap_type'] . $r['snap_sr']) !== ''): ?><div><?= h(trim($r['snap_type'] . ' ' . $r['snap_model'])) ?></div><?php if ($r['snap_sr']): ?><div class="muted mono"><?= h($r['snap_sr']) ?></div><?php endif; ?><div class="muted">not linked</div>
              <?php else: ?><span class="muted">—</span><?php endif; ?>
            </td>
            <td data-label="Reference"><span class="mono fw-bold"><?= h($r['ref']) ?></span></td>
            <td data-label="Description" style="max-width:340px;">
              <div style="overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;"><?= h($descr) ?></div>
              <?php if ($isBill): ?><div class="muted">Tk <?= h(number_format((float)$r['amount'], 2)) ?> · <?= $r['pay_status'] === 'paid' ? 'Paid' : 'Unpaid' ?></div><?php endif; ?>
            </td>
            <td data-label="Created By"><?= h($r['creator'] ?? '—') ?></td>
            <td class="td-act" style="text-align:right;white-space:nowrap;">
              <div class="d-flex gap-2 justify-content-end">
                <a class="btn-act btn-pdf" href="<?= h($pdfUrl) ?>" target="_blank" rel="noopener" title="Open to view, print or download the PDF"><i class="fas fa-file-pdf"></i> View / Print / PDF</a>
                <a class="btn-act btn-edit" href="<?= h($editUrl) ?>"><i class="fas fa-pen"></i> Edit</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <?php if ($total > 0): ?>
    <div class="pager">
      <div class="info">Page <?= $page ?> of <?= $pages ?> · <?= number_format($total) ?> total</div>
      <div class="d-flex gap-2">
        <a class="btn-pg <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= h($qs(['page' => $page - 1 > 1 ? $page - 1 : null])) ?>"><i class="fas fa-chevron-left"></i> Previous</a>
        <a class="btn-pg <?= $page >= $pages ? 'disabled' : '' ?>" href="<?= h($qs(['page' => $page + 1])) ?>">Next <i class="fas fa-chevron-right"></i></a>
      </div>
    </div>
    <?php endif; ?>
  </main>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
