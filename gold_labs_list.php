<?php
require 'auth.php';
require 'mydb.php';
require_engineering();               // admin + engineer employee only (server-side, re-checked against DB)

if (isset($conn)) { $conn->set_charset('utf8mb4'); }
if (!function_exists('h')) { function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

if (empty($_SESSION['csrf_gold'])) $_SESSION['csrf_gold'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_gold'];
$uid  = (int)$_SESSION['user_id'];

// ---------- Flash ----------
$flashMap  = ['created' => 'Laboratory added successfully.', 'updated' => 'Laboratory updated successfully.', 'notfound' => 'Laboratory not found.'];
$flash = ''; $flashType = 'success';
$msg = (string)($_GET['msg'] ?? '');
if (isset($flashMap[$msg])) { $flash = $flashMap[$msg]; if ($msg === 'notfound') $flashType = 'danger'; }

// ---------- Search + page state (GET, or the hidden copies when a failed save re-renders the page) ----------
$src  = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$q    = is_string($src['q'] ?? null) ? trim($src['q']) : '';
$page = max(1, (int)($src['page'] ?? 1));

// ---------- Save (add / edit) ----------
$errors = []; $modalOpen = false;
$form = ['id' => 0, 'customer_name' => '', 'address' => '', 'contact_person' => '', 'designation_department' => '', 'telephone' => '', 'email' => '', 'remarks' => ''];
$limits = ['customer_name' => [150, 'Customer name'], 'address' => [400, 'Address'], 'contact_person' => [100, 'Contact person'],
           'designation_department' => [100, 'Desgn./Dept.'], 'telephone' => [40, 'Telephone'], 'email' => [120, 'Email'], 'remarks' => [500, 'Remarks']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Invalid or expired form token. Please go back, refresh the page and try again.');
    }
    $form['id'] = (int)($_POST['id'] ?? 0);
    foreach (array_keys($limits) as $k) { $v = $_POST[$k] ?? ''; $form[$k] = is_string($v) ? trim($v) : ''; }

    if ($form['customer_name'] === '') $errors[] = 'Customer name is required.';
    foreach ($limits as $k => [$max, $label]) if (mb_strlen($form[$k]) > $max) $errors[] = "$label must be at most $max characters.";
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email address is not valid.';
    if ($form['telephone'] !== '' && !preg_match('/^[0-9+\-()\s,\/]{5,40}$/', $form['telephone'])) $errors[] = 'Telephone may contain only digits, spaces and + - ( ) , /';

    if ($form['id'] > 0) {
        $st = $conn->prepare("SELECT id FROM gold_testing_lab_customers WHERE id = ? LIMIT 1");
        $st->bind_param("i", $form['id']); $st->execute();
        if (!$st->get_result()->fetch_row()) $errors[] = 'This laboratory no longer exists.';
        $st->close();
    }
    if (!$errors) {                                   // duplicate name (case-insensitive, via column collation)
        $st = $conn->prepare("SELECT id FROM gold_testing_lab_customers WHERE customer_name = ? AND id <> ? LIMIT 1");
        $st->bind_param("si", $form['customer_name'], $form['id']); $st->execute();
        if ($st->get_result()->fetch_row()) $errors[] = 'A laboratory with this name already exists.';
        $st->close();
    }

    if (!$errors) {
        $nn = fn(string $v) => $v === '' ? null : $v;
        $addr = $nn($form['address']); $cp = $nn($form['contact_person']); $dd = $nn($form['designation_department']);
        $tel = $nn($form['telephone']); $em = $nn($form['email']); $rm = $nn($form['remarks']);
        try {
            if ($form['id'] > 0) {
                $st = $conn->prepare("UPDATE gold_testing_lab_customers SET customer_name=?, address=?, contact_person=?, designation_department=?, telephone=?, email=?, remarks=? WHERE id=?");
                $st->bind_param("sssssssi", $form['customer_name'], $addr, $cp, $dd, $tel, $em, $rm, $form['id']);
                $st->execute(); $st->close();
                $done = 'updated';
            } else {
                $st = $conn->prepare("INSERT INTO gold_testing_lab_customers (customer_name, address, contact_person, designation_department, telephone, email, remarks, created_by) VALUES (?,?,?,?,?,?,?,?)");
                $st->bind_param("sssssssi", $form['customer_name'], $addr, $cp, $dd, $tel, $em, $rm, $uid);
                $st->execute(); $st->close();
                $done = 'created';
            }
            header('Location: gold_labs_list.php?' . http_build_query(array_filter(['msg' => $done, 'q' => $q, 'page' => $page > 1 ? $page : null], fn($v) => $v !== '' && $v !== null)));
            exit;
        } catch (Throwable $e) {
            if ((int)($e->getCode()) === 1062) $errors[] = 'A laboratory with this name already exists.';
            else { error_log('gold lab save failed: ' . $e->getMessage()); $errors[] = 'The laboratory could not be saved. Nothing was changed — please try again.'; }
        }
    }
    $modalOpen = true;                                // errors: re-open the modal with what was typed
}

// ---------- List ----------
$where = ''; $types = ''; $params = [];
if ($q !== '') {
    $like  = '%' . addcslashes($q, '%_\\') . '%';
    $where = "WHERE (l.customer_name LIKE ? OR l.contact_person LIKE ? OR l.telephone LIKE ? OR l.address LIKE ? OR l.email LIKE ? OR l.remarks LIKE ?
                 OR EXISTS (SELECT 1 FROM gold_testing_lab_customers_machine m2 WHERE m2.customer_id = l.id AND (m2.machine_no LIKE ? OR m2.model_no LIKE ? OR m2.serial_no LIKE ?)))";
    for ($i = 0; $i < 9; $i++) { $types .= 's'; $params[] = $like; }
}
$perPage = 20;

$st = $conn->prepare("SELECT COUNT(*) FROM gold_testing_lab_customers l $where");
if ($params) $st->bind_param($types, ...$params);
$st->execute(); $total = (int)$st->get_result()->fetch_row()[0]; $st->close();

$pages  = max(1, (int)ceil($total / $perPage));
$page   = min($page, $pages);                         // a page number beyond the last one lands on the last page
$offset = ($page - 1) * $perPage;

$sql = "SELECT l.*
        FROM gold_testing_lab_customers l
        $where
        ORDER BY l.customer_name ASC, l.id ASC
        LIMIT ? OFFSET ?";
$st = $conn->prepare($sql);
$t2 = $types . 'ii'; $p2 = array_merge($params, [$perPage, $offset]);
$st->bind_param($t2, ...$p2);
$st->execute(); $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();

// machines of the labs on this page (read-only here; they are added / edited on gold_lab_machines.php)
$byLab = [];
if ($rows) {
    $ids = array_map(fn($r) => (int)$r['id'], $rows);
    $st  = $conn->prepare("SELECT customer_id, machine_no, model_no, serial_no, installation_date FROM gold_testing_lab_customers_machine WHERE customer_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ") ORDER BY machine_no ASC, id ASC");
    $st->bind_param(str_repeat('i', count($ids)), ...$ids); $st->execute();
    foreach ($st->get_result() as $m) $byLab[(int)$m['customer_id']][] = $m;
    $st->close();
}

$qs = function (array $o = []) use ($q) {
    return '?' . http_build_query(array_filter(array_merge(['q' => $q], $o), fn($v) => $v !== '' && $v !== null));
};
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
  <title>Gold Testing Labs — Rajaiswari</title>
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
    /* formal, plain table (this page only) */
    .mchip{display:block;font-size:.875rem;font-weight:400;line-height:1.5;color:var(--t1);}
    @media(min-width:768px){
      .utbl{border:1px solid #cbd5e1;}
      .utbl thead th{background:#f3f4f6;color:#111827;text-transform:none;letter-spacing:0;font-size:.82rem;font-weight:700;border:1px solid #cbd5e1;padding:10px 12px;}
      .utbl tbody td{border:1px solid #e2e8f0;vertical-align:top;padding:10px 12px;}
      .utbl tbody tr:hover td{background:#fff;}
      .utbl .muted{color:#6b7280;font-size:.8rem;}
    }
  </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page-shell">
  <header class="top-bar">
    <div class="tb-ico"><i class="fas fa-flask-vial"></i></div>
    <div><div class="tb-title">Gold Testing Labs</div><div class="tb-sub"><?= number_format($total) ?> laborator<?= $total === 1 ? 'y' : 'ies' ?><?= $q !== '' ? ' matching your search' : '' ?></div></div>
    <div class="tb-right">
      <a href="gold_lab_history.php" class="btn-soft"><i class="fas fa-clock-rotate-left"></i><span class="d-none d-sm-inline"> All Labs History</span></a>
      <a href="engineering_dashboard.php" class="btn-soft"><i class="fas fa-grip"></i><span class="d-none d-sm-inline"> Engineering</span></a>
      <button type="button" class="btn-add" id="btnAdd"><i class="fas fa-plus"></i> Add New Lab</button>
    </div>
  </header>

  <main class="main">
    <?php if ($flash): ?>
      <div class="alert-flash af-<?= h($flashType) ?>"><i class="fas fa-<?= $flashType === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i><?= h($flash) ?></div>
    <?php endif; ?>

    <form class="sec" method="get">
      <div class="sec-body">
        <div class="row g-2 align-items-end">
          <div class="col-12 col-md-9 col-lg-10">
            <label class="lbl" for="q">Search</label>
            <input type="text" id="q" name="q" class="form-control" value="<?= h($q) ?>" placeholder="Laboratory, contact, telephone, address, email, remarks, machine no., model, serial">
          </div>
          <div class="col-12 col-md-3 col-lg-2 d-flex gap-2">
            <button class="btn btn-primary flex-fill fw-bold" style="min-height:38px;"><i class="fas fa-search"></i> Search</button>
            <?php if ($q !== ''): ?><a href="gold_labs_list.php" class="btn btn-outline-secondary" style="min-height:38px;" title="Clear"><i class="fas fa-xmark"></i></a><?php endif; ?>
          </div>
        </div>
      </div>
    </form>

    <div class="sec">
      <?php if (!$rows): ?>
        <div class="empty"><i class="fas fa-flask"></i><?= $q !== '' ? 'No laboratories match your search.' : 'No laboratories yet. Click “Add New Lab” to create the first one.' ?></div>
      <?php else: ?>
      <table class="utbl">
        <thead><tr><th style="width:64px;">Sl. No.</th><th>Laboratory</th><th>Contact</th><th>Machines &amp; Installation Date</th><th>Remarks</th><th style="text-align:right">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $i => $r):
            $payload = ['id' => (int)$r['id'], 'customer_name' => $r['customer_name'], 'address' => (string)$r['address'], 'contact_person' => (string)$r['contact_person'],
                        'designation_department' => (string)$r['designation_department'], 'telephone' => (string)$r['telephone'], 'email' => (string)$r['email'], 'remarks' => (string)$r['remarks']]; ?>
          <tr>
            <td data-label="Sl. No."><?= $offset + $i + 1 ?></td>
            <td data-label="Laboratory"><div class="fw-bold"><?= h($r['customer_name']) ?></div><?php if ($r['address']): ?><div class="muted"><?= h($r['address']) ?></div><?php endif; ?></td>
            <td data-label="Contact"><div><?= h($r['contact_person'] ?: '—') ?></div><div class="muted"><?= h($r['telephone'] ?: '—') ?></div></td>
            <td data-label="Machines">
              <?php $ms = $byLab[(int)$r['id']] ?? []; if (!$ms): ?><span class="muted">—</span>
              <?php else: foreach ($ms as $m): $tip = trim(($m['model_no'] ?? '') . ($m['serial_no'] ? ' · SN ' . $m['serial_no'] : '')); ?>
                <span class="mchip" <?= $tip !== '' ? 'title="' . h($tip) . '"' : '' ?>><?= h($m['machine_no']) ?> <span class="muted">— <?= $m['installation_date'] ? h(date('d M Y', strtotime($m['installation_date']))) : 'date not set' ?></span></span>
              <?php endforeach; endif; ?>
            </td>
            <td data-label="Remarks" style="white-space:pre-line;"><?= $r['remarks'] !== null && trim($r['remarks']) !== '' ? h($r['remarks']) : '<span class="muted">—</span>' ?></td>
            <td class="td-act" style="text-align:right;white-space:nowrap;">
              <div class="d-flex gap-2 justify-content-end">
                <a class="btn-act btn-mach" href="gold_lab_machines.php?lab=<?= (int)$r['id'] ?>"><i class="fas fa-microchip"></i> Machines</a>
                <button type="button" class="btn-act btn-edit js-edit" data-lab="<?= h(json_encode($payload, JSON_UNESCAPED_UNICODE)) ?>"><i class="fas fa-pen"></i> Edit</button>
                <a class="btn-act btn-hist" href="gold_lab_history.php?id=<?= (int)$r['id'] ?>"><i class="fas fa-clock-rotate-left"></i> History</a>
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

<!-- Add / Edit modal (one modal for both) -->
<div class="modal fade" id="labModal" tabindex="-1" aria-labelledby="labModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <form class="modal-content" method="post" id="labForm" novalidate>
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="id" id="f_id" value="<?= (int)$form['id'] ?>">
      <input type="hidden" name="q" value="<?= h($q) ?>">
      <input type="hidden" name="page" value="<?= (int)$page ?>">
      <div class="modal-header">
        <h5 class="modal-title" id="labModalTitle"><i class="fas fa-flask-vial me-2 text-muted"></i><span id="labModalText"><?= $form['id'] > 0 ? 'Edit Laboratory' : 'Add New Laboratory' ?></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert-err" id="labErrors" <?= $errors ? '' : 'style="display:none"' ?>>
          <div class="alert-err-title"><i class="fas fa-circle-exclamation me-1"></i>Please fix the following:</div>
          <ul id="labErrList"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
        </div>
        <div class="row g-3">
          <div class="col-12"><label class="lbl" for="f_name">Customer Name <span class="req">*</span></label><input type="text" class="form-control" id="f_name" name="customer_name" maxlength="150" value="<?= h($form['customer_name']) ?>" required></div>
          <div class="col-12"><label class="lbl" for="f_address">Address</label><textarea class="form-control" id="f_address" name="address" rows="2" maxlength="400"><?= h($form['address']) ?></textarea></div>
          <div class="col-12 col-md-6"><label class="lbl" for="f_contact">Contact Person</label><input type="text" class="form-control" id="f_contact" name="contact_person" maxlength="100" value="<?= h($form['contact_person']) ?>"></div>
          <div class="col-12 col-md-6"><label class="lbl" for="f_desg">Desgn./Dept.</label><input type="text" class="form-control" id="f_desg" name="designation_department" maxlength="100" value="<?= h($form['designation_department']) ?>"></div>
          <div class="col-12 col-md-6"><label class="lbl" for="f_tel">Telephone</label><input type="tel" class="form-control" id="f_tel" name="telephone" maxlength="40" value="<?= h($form['telephone']) ?>"></div>
          <div class="col-12 col-md-6"><label class="lbl" for="f_email">Email</label><input type="email" class="form-control" id="f_email" name="email" maxlength="120" value="<?= h($form['email']) ?>"></div>
          <div class="col-12"><label class="lbl" for="f_remarks">Remarks</label><textarea class="form-control" id="f_remarks" name="remarks" rows="2" maxlength="500"><?= h($form['remarks']) ?></textarea></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn-submit" id="labSubmit"><i class="fas fa-check"></i> <span id="labSubmitText"><?= $form['id'] > 0 ? 'Update Lab' : 'Save Lab' ?></span></button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  var modalEl = document.getElementById('labModal');
  var modal = new bootstrap.Modal(modalEl);
  var F = { id: 'f_id', customer_name: 'f_name', address: 'f_address', contact_person: 'f_contact',
            designation_department: 'f_desg', telephone: 'f_tel', email: 'f_email', remarks: 'f_remarks' };

  function setMode(isEdit) {
    document.getElementById('labModalText').textContent = isEdit ? 'Edit Laboratory' : 'Add New Laboratory';
    document.getElementById('labSubmitText').textContent = isEdit ? 'Update Lab' : 'Save Lab';
    document.getElementById('labSubmit').classList.toggle('edit', isEdit);
  }
  function clearErrors() { document.getElementById('labErrors').style.display = 'none'; document.getElementById('labErrList').innerHTML = ''; }

  document.getElementById('btnAdd').addEventListener('click', function () {
    Object.keys(F).forEach(function (k) { document.getElementById(F[k]).value = (k === 'id') ? '0' : ''; });
    clearErrors(); setMode(false); modal.show();
  });
  document.querySelectorAll('.js-edit').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = JSON.parse(b.getAttribute('data-lab'));
      Object.keys(F).forEach(function (k) { document.getElementById(F[k]).value = d[k] == null ? '' : d[k]; });
      clearErrors(); setMode(true); modal.show();
    });
  });

  // client-side checks (the server validates everything again)
  document.getElementById('labForm').addEventListener('submit', function (e) {
    var msgs = [], name = document.getElementById('f_name').value.trim(), em = document.getElementById('f_email');
    if (!name) msgs.push('Customer name is required.');
    if (em.value && !em.checkValidity()) msgs.push('Email address is not valid.');
    if (msgs.length) {
      e.preventDefault();
      var list = document.getElementById('labErrList'); list.innerHTML = '';
      msgs.forEach(function (m) { var li = document.createElement('li'); li.textContent = m; list.appendChild(li); });
      document.getElementById('labErrors').style.display = '';
    }
  });

  <?php if ($modalOpen): ?>
  setMode(<?= $form['id'] > 0 ? 'true' : 'false' ?>);
  modal.show();
  <?php endif; ?>
})();
</script>
</body>
</html>