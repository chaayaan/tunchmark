<?php
require 'auth.php';
require 'mydb.php';
require_engineering();               // admin + engineer employee only (server-side, re-checked against DB)

if (isset($conn)) { $conn->set_charset('utf8mb4'); }
if (!function_exists('h')) { function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

if (empty($_SESSION['csrf_gold'])) $_SESSION['csrf_gold'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_gold'];
$uid  = (int)$_SESSION['user_id'];

$validDate = function (string $d): bool { $o = DateTime::createFromFormat('Y-m-d', $d); return $o && $o->format('Y-m-d') === $d; };

// ---------- Flash ----------
$flashMap = ['machine_added' => 'Machine added successfully.', 'machine_updated' => 'Machine updated successfully.', 'labnotfound' => 'Laboratory not found.'];
$flash = ''; $flashType = 'success';
$msg = (string)($_GET['msg'] ?? '');
if (isset($flashMap[$msg])) { $flash = $flashMap[$msg]; if ($msg === 'labnotfound') $flashType = 'danger'; }

// ---------- The laboratory (GET ?lab=ID; POST carries the same id) ----------
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$labId  = $isPost ? (int)($_POST['lab_id'] ?? 0) : (int)($_GET['lab'] ?? 0);
$lab = null;
if ($labId > 0) {
    $st = $conn->prepare("SELECT l.*, u.name AS creator_name FROM gold_testing_lab_customers l LEFT JOIN users u ON u.id = l.created_by WHERE l.id = ? LIMIT 1");
    $st->bind_param("i", $labId); $st->execute();
    $lab = $st->get_result()->fetch_assoc(); $st->close();
    if (!$lab) {
        if ($isPost) { http_response_code(404); exit('Laboratory not found.'); }
        header('Location: gold_lab_machines.php?msg=labnotfound'); exit;
    }
}

// ---------- Save machine (add / edit) ----------
$errors = []; $modalOpen = false;
$form = ['id' => 0, 'machine_no' => '', 'model_no' => '', 'serial_no' => '', 'installation_date' => ''];
$limits = ['machine_no' => [100, 'Machine No.'], 'model_no' => [100, 'Model No.'], 'serial_no' => [100, 'Serial No.']];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $lab) {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Invalid or expired form token. Please go back, refresh the page and try again.');
    }
    $form['id'] = (int)($_POST['id'] ?? 0);
    foreach (['machine_no', 'model_no', 'serial_no', 'installation_date'] as $k) { $v = $_POST[$k] ?? ''; $form[$k] = is_string($v) ? trim($v) : ''; }

    if ($form['id'] > 0) {            // the lab of an existing machine can never change
        $st = $conn->prepare("SELECT customer_id FROM gold_testing_lab_customers_machine WHERE id = ? LIMIT 1");
        $st->bind_param("i", $form['id']); $st->execute();
        $cur = $st->get_result()->fetch_assoc(); $st->close();
        if (!$cur) $errors[] = 'This machine no longer exists.';
        elseif ((int)$cur['customer_id'] !== (int)$lab['id']) $errors[] = 'A machine cannot be moved to a different laboratory.';
    }
    if ($form['machine_no'] === '') $errors[] = 'Machine No. is required.';
    foreach ($limits as $k => [$max, $label]) if (mb_strlen($form[$k]) > $max) $errors[] = "$label must be at most $max characters.";
    if ($form['installation_date'] !== '' && !$validDate($form['installation_date'])) $errors[] = 'Installation date is not a valid date.';

    if (!$errors && $form['serial_no'] !== '') {              // serial numbers are unique across ALL labs
        $st = $conn->prepare("SELECT m.machine_no, l.customer_name FROM gold_testing_lab_customers_machine m JOIN gold_testing_lab_customers l ON l.id = m.customer_id WHERE m.serial_no = ? AND m.id <> ? LIMIT 1");
        $st->bind_param("si", $form['serial_no'], $form['id']); $st->execute();
        if ($d = $st->get_result()->fetch_assoc()) $errors[] = 'Serial No. is already registered to machine “' . $d['machine_no'] . '” of ' . $d['customer_name'] . '.';
        $st->close();
    }
    if (!$errors) {                                            // machine no. unique inside one lab
        $st = $conn->prepare("SELECT id FROM gold_testing_lab_customers_machine WHERE customer_id = ? AND machine_no = ? AND id <> ? LIMIT 1");
        $st->bind_param("isi", $lab['id'], $form['machine_no'], $form['id']); $st->execute();
        if ($st->get_result()->fetch_row()) $errors[] = 'This laboratory already has a machine with that Machine No.';
        $st->close();
    }

    if (!$errors) {
        $nn = fn(string $v) => $v === '' ? null : $v;
        $mod = $nn($form['model_no']); $ser = $nn($form['serial_no']); $ins = $nn($form['installation_date']);
        try {
            if ($form['id'] > 0) {                             // customer_id is deliberately NOT in the UPDATE
                $st = $conn->prepare("UPDATE gold_testing_lab_customers_machine SET machine_no=?, model_no=?, serial_no=?, installation_date=? WHERE id=? AND customer_id=?");
                $st->bind_param("ssssii", $form['machine_no'], $mod, $ser, $ins, $form['id'], $lab['id']);
                $done = 'machine_updated';
            } else {
                $st = $conn->prepare("INSERT INTO gold_testing_lab_customers_machine (customer_id, machine_no, model_no, serial_no, installation_date, created_by) VALUES (?,?,?,?,?,?)");
                $st->bind_param("issssi", $lab['id'], $form['machine_no'], $mod, $ser, $ins, $uid);
                $done = 'machine_added';
            }
            $st->execute(); $st->close();
            header('Location: gold_lab_machines.php?lab=' . (int)$lab['id'] . '&msg=' . $done);   // stay on this laboratory
            exit;
        } catch (Throwable $e) {
            if ((int)$e->getCode() === 1062) $errors[] = 'Serial No. or Machine No. already exists.';
            else { error_log('gold machine save failed: ' . $e->getMessage()); $errors[] = 'The machine could not be saved. Nothing was changed — please try again.'; }
        }
    }
    $modalOpen = true;
}

// ---------- Dropdown + this lab's machines ----------
$labs = $conn->query("SELECT id, customer_name FROM gold_testing_lab_customers ORDER BY customer_name ASC, id ASC")->fetch_all(MYSQLI_ASSOC);

$machines = [];
if ($lab) {
    $st = $conn->prepare("SELECT m.*, u.name AS creator_name,
                                 (SELECT COUNT(*) FROM machines_report r WHERE r.machine_id = m.id) AS report_count
                          FROM gold_testing_lab_customers_machine m
                          LEFT JOIN users u ON u.id = m.created_by
                          WHERE m.customer_id = ?
                          ORDER BY m.machine_no ASC, m.id ASC");
    $st->bind_param("i", $lab['id']); $st->execute();
    $machines = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
}
$fmtDate = fn($v) => $v ? date('d M Y', strtotime($v)) : '';

// ---------- Machines table ----------
$machTable = function (array $ms, array $L) use ($fmtDate) { ?>
      <?php if (!$ms): ?>
        <div class="empty" style="padding:26px 16px;"><i class="fas fa-box-open" style="font-size:1.4rem"></i>No machines yet for this laboratory.</div>
      <?php else: ?>
      <table class="utbl">
        <thead><tr><th>Machine No.</th><th>Model No.</th><th>Serial No.</th><th>Installation Date</th><th style="text-align:right">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($ms as $m):
            $payload = ['id' => (int)$m['id'], 'lab' => (int)$L['id'], 'lab_name' => $L['customer_name'], 'machine_no' => $m['machine_no'], 'model_no' => (string)$m['model_no'],
                        'serial_no' => (string)$m['serial_no'], 'installation_date' => (string)$m['installation_date'], 'installation_fmt' => $fmtDate($m['installation_date']),
                        'created' => date('d M Y', strtotime($m['created_at'])) . ' · ' . ($m['creator_name'] ?? '—'), 'reports' => (int)$m['report_count']]; ?>
          <tr>
            <td data-label="Machine No."><span class="fw-bold"><?= h($m['machine_no']) ?></span></td>
            <td data-label="Model No."><?= h($m['model_no'] ?: '—') ?></td>
            <td data-label="Serial No."><span class="mono"><?= h($m['serial_no'] ?: '—') ?></span></td>
            <td data-label="Installation Date"><?= $m['installation_date'] ? h($fmtDate($m['installation_date'])) : '—' ?></td>
            <td class="td-act" style="text-align:right;white-space:nowrap;">
              <div class="d-flex gap-2 justify-content-end">
                <button type="button" class="btn-act btn-view js-view" data-m="<?= h(json_encode($payload, JSON_UNESCAPED_UNICODE)) ?>"><i class="fas fa-eye"></i> View</button>
                <button type="button" class="btn-act btn-edit js-edit" data-m="<?= h(json_encode($payload, JSON_UNESCAPED_UNICODE)) ?>"><i class="fas fa-pen"></i> Edit</button>
                <a class="btn-act btn-hist" href="gold_lab_history.php?id=<?= (int)$L['id'] ?>&amp;machine=<?= (int)$m['id'] ?>"><i class="fas fa-clock-rotate-left"></i> History</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
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
  <title>Lab Machines — Rajaiswari</title>
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
    .pick{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;}
    .pick .grow{flex:1;min-width:240px;}
    .fixed-lab{background:var(--surface-2);border:1.5px dashed var(--border);border-radius:var(--rs);padding:9px 12px;font-weight:600;}
    /* formal, plain tables (same as the lab list page) */
    .ftbl{width:100%;border-collapse:collapse;margin:0;}
    .ftbl th{background:#f3f4f6;color:#111827;width:15%;text-align:left;font-size:.82rem;font-weight:700;border:1px solid #cbd5e1;padding:10px 12px;vertical-align:top;}
    .ftbl td{border:1px solid #e2e8f0;padding:10px 12px;vertical-align:top;font-size:.875rem;word-break:break-word;}
    @media(min-width:768px){
      .utbl{border:1px solid #cbd5e1;}
      .utbl thead th{background:#f3f4f6;color:#111827;text-transform:none;letter-spacing:0;font-size:.82rem;font-weight:700;border:1px solid #cbd5e1;padding:10px 12px;}
      .utbl tbody td{border:1px solid #e2e8f0;vertical-align:top;padding:10px 12px;}
      .utbl tbody tr:hover td{background:#fff;}
      .utbl .muted{color:#6b7280;font-size:.8rem;}
    }
    @media(max-width:767.98px){
      .ftbl tr{display:block;border-bottom:1px solid #cbd5e1;}
      .ftbl th,.ftbl td{display:block;width:100%;border:none;padding:6px 12px;}
      .ftbl th{background:none;color:var(--t4);font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;padding-bottom:0;}
    }
  </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page-shell">
  <header class="top-bar">
    <div class="tb-ico"><i class="fas fa-microchip"></i></div>
    <div><div class="tb-title">Lab Machines</div><div class="tb-sub"><?= $lab ? h($lab['customer_name']) . ' · ' . count($machines) . ' machine' . (count($machines) === 1 ? '' : 's') : 'Add and edit the machines of a laboratory' ?></div></div>
    <div class="tb-right">
      <a href="gold_labs_list.php" class="btn-soft"><i class="fas fa-flask-vial"></i><span class="d-none d-sm-inline"> Gold Testing Labs</span></a>
      <?php if ($lab): ?><a href="gold_lab_history.php?id=<?= (int)$lab['id'] ?>" class="btn-soft"><i class="fas fa-clock-rotate-left"></i><span class="d-none d-sm-inline"> Lab History</span></a><?php endif; ?>
    </div>
  </header>

  <main class="main">
    <?php if ($flash): ?>
      <div class="alert-flash af-<?= h($flashType) ?>"><i class="fas fa-<?= $flashType === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i><?= h($flash) ?></div>
    <?php endif; ?>

    <form class="sec" method="get" id="pickForm">
      <div class="sec-body"><div class="pick">
        <div class="grow">
          <label class="lbl" for="labSel">Client / Laboratory</label>
          <select class="form-select" id="labSel" name="lab">
            <option value="">— Select a client laboratory —</option>
            <?php foreach ($labs as $l): ?><option value="<?= (int)$l['id'] ?>" <?= $lab && (int)$lab['id'] === (int)$l['id'] ? 'selected' : '' ?>><?= h($l['customer_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary fw-bold" style="min-height:38px;"><i class="fas fa-circle-info"></i> Machines Information</button>
      </div></div>
    </form>

    <?php if (!$lab): ?>
      <div class="sec"><div class="empty"><i class="fas fa-hand-pointer"></i>
        <?= $labs ? 'Select a client laboratory above to see and manage its machines.' : 'There are no laboratories yet. <a href="gold_labs_list.php">Add a laboratory</a> first.' ?></div></div>
    <?php else: ?>

    <!-- 1. Client information -->
    <div class="sec">
      <div class="sec-head"><div class="sec-ico i-blue"><i class="fas fa-building"></i></div><div class="sec-title">Client Information</div></div>
      <div class="sec-body" style="padding:0;"><table class="ftbl">
        <tr><th>Customer Name</th><td class="fw-bold"><?= h($lab['customer_name']) ?></td><th>Contact Person</th><td><?= h($lab['contact_person'] ?: '—') ?></td></tr>
        <tr><th>Designation / Dept.</th><td><?= h($lab['designation_department'] ?: '—') ?></td><th>Telephone</th><td><?= h($lab['telephone'] ?: '—') ?></td></tr>
        <tr><th>Email</th><td><?= h($lab['email'] ?: '—') ?></td><th>Address</th><td><?= h($lab['address'] ?: '—') ?></td></tr>
      </table></div>
    </div>

    <!-- 2. Machines information -->
    <div class="sec">
      <div class="sec-head">
        <div class="sec-ico i-teal"><i class="fas fa-microchip"></i></div><div class="sec-title">Machines</div>
        <div class="sec-meta"><button type="button" class="btn-add js-add" data-lab="<?= (int)$lab['id'] ?>" data-name="<?= h($lab['customer_name']) ?>"><i class="fas fa-plus"></i> Add Machine</button></div>
      </div>
      <?php $machTable($machines, $lab); ?>
    </div>

    <?php endif; ?>
  </main>
</div>

<!-- Add / Edit machine modal (lab is set per click, fixed while editing) -->
<div class="modal fade" id="machModal" tabindex="-1" aria-labelledby="machModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <form class="modal-content" method="post" id="machForm" novalidate>
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="lab_id" id="m_lab" value="<?= $lab ? (int)$lab['id'] : 0 ?>">
      <input type="hidden" name="id" id="m_id" value="<?= (int)$form['id'] ?>">
      <div class="modal-header">
        <h5 class="modal-title" id="machModalTitle"><i class="fas fa-microchip me-2 text-muted"></i><span id="machModalText"><?= $form['id'] > 0 ? 'Edit Machine' : 'Add Machine' ?></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert-err" id="machErrors" <?= $errors ? '' : 'style="display:none"' ?>>
          <div class="alert-err-title"><i class="fas fa-circle-exclamation me-1"></i>Please fix the following:</div>
          <ul id="machErrList"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
        </div>
        <div class="row g-3">
          <div class="col-12"><span class="lbl">Laboratory (fixed)</span><div class="fixed-lab"><i class="fas fa-lock me-2 text-muted"></i><span id="m_labname"><?= h($lab['customer_name'] ?? '') ?></span></div></div>
          <div class="col-12"><label class="lbl" for="m_no">Machine No. <span class="req">*</span></label><input type="text" class="form-control" id="m_no" name="machine_no" maxlength="100" value="<?= h($form['machine_no']) ?>" required></div>
          <div class="col-12 col-md-6"><label class="lbl" for="m_model">Model No.</label><input type="text" class="form-control" id="m_model" name="model_no" maxlength="100" value="<?= h($form['model_no']) ?>"></div>
          <div class="col-12 col-md-6"><label class="lbl" for="m_serial">Serial No.</label><input type="text" class="form-control" id="m_serial" name="serial_no" maxlength="100" value="<?= h($form['serial_no']) ?>"></div>
          <div class="col-12 col-md-6"><label class="lbl" for="m_date">Installation Date</label><input type="date" class="form-control" id="m_date" name="installation_date" value="<?= h($form['installation_date']) ?>"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn-submit <?= $form['id'] > 0 ? 'edit' : '' ?>" id="machSubmit"><i class="fas fa-check"></i> <span id="machSubmitText"><?= $form['id'] > 0 ? 'Update Machine' : 'Save Machine' ?></span></button>
      </div>
    </form>
  </div>
</div>

<!-- View machine modal -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="fas fa-eye me-2 text-muted"></i>Machine Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body"><div class="kv" style="grid-template-columns:1fr 1fr">
        <div class="wide"><div class="k">Laboratory</div><div class="v fw-bold" id="v_lab"></div></div>
        <div><div class="k">Machine No.</div><div class="v fw-bold" id="v_no"></div></div>
        <div><div class="k">Model No.</div><div class="v" id="v_model"></div></div>
        <div><div class="k">Serial No.</div><div class="v mono" id="v_serial"></div></div>
        <div><div class="k">Installation Date</div><div class="v" id="v_date"></div></div>
        <div><div class="k">Linked Reports</div><div class="v" id="v_reports"></div></div>
        <div><div class="k">Added</div><div class="v" id="v_created"></div></div>
      </div></div>
      <div class="modal-footer"><a class="btn-act btn-hist" id="v_hist" href="#"><i class="fas fa-clock-rotate-left"></i> History</a><button type="button" class="btn-ghost" data-bs-dismiss="modal">Close</button></div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  var sel = document.getElementById('labSel');
  sel.addEventListener('change', function () { if (sel.value) document.getElementById('pickForm').submit(); });   // button does the same

  var machEl = document.getElementById('machModal');
  if (!machEl) return;
  var machModal = new bootstrap.Modal(machEl), viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
  function $(id) { return document.getElementById(id); }
  function dash(v) { return v ? v : '—'; }

  function setMode(isEdit) {
    $('machModalText').textContent = isEdit ? 'Edit Machine' : 'Add Machine';
    $('machSubmitText').textContent = isEdit ? 'Update Machine' : 'Save Machine';
    $('machSubmit').classList.toggle('edit', isEdit);
  }
  function clearErrors() { $('machErrors').style.display = 'none'; $('machErrList').innerHTML = ''; }

  document.querySelectorAll('.js-add').forEach(function (b) {
    b.addEventListener('click', function () {
      ['m_no', 'm_model', 'm_serial', 'm_date'].forEach(function (i) { $(i).value = ''; });
      $('m_id').value = '0'; $('m_lab').value = b.getAttribute('data-lab'); $('m_labname').textContent = b.getAttribute('data-name');
      clearErrors(); setMode(false); machModal.show();
    });
  });
  document.querySelectorAll('.js-edit').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = JSON.parse(b.getAttribute('data-m'));
      $('m_id').value = d.id; $('m_no').value = d.machine_no; $('m_model').value = d.model_no;
      $('m_serial').value = d.serial_no; $('m_date').value = d.installation_date;
      $('m_lab').value = d.lab; $('m_labname').textContent = d.lab_name;
      clearErrors(); setMode(true); machModal.show();
    });
  });
  document.querySelectorAll('.js-view').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = JSON.parse(b.getAttribute('data-m'));
      $('v_no').textContent = d.machine_no; $('v_model').textContent = dash(d.model_no); $('v_serial').textContent = dash(d.serial_no);
      $('v_date').textContent = dash(d.installation_fmt); $('v_reports').textContent = d.reports; $('v_created').textContent = d.created;
      $('v_lab').textContent = d.lab_name; $('v_hist').href = 'gold_lab_history.php?id=' + d.lab + '&machine=' + d.id;
      viewModal.show();
    });
  });

  $('machForm').addEventListener('submit', function (e) {
    if (!$('m_no').value.trim()) {
      e.preventDefault();
      $('machErrList').innerHTML = '<li>Machine No. is required.</li>'; $('machErrors').style.display = '';
    }
  });

  <?php if ($modalOpen): ?>
  setMode(<?= $form['id'] > 0 ? 'true' : 'false' ?>);
  machModal.show();
  <?php endif; ?>
})();
</script>
</body>
</html>