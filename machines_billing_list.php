<?php
require 'auth.php';
require 'mydb.php';
require_engineering();               // admin + engineer employee only (server-side, re-checked against DB)

if (isset($conn)) { $conn->set_charset('utf8mb4'); }
if (!function_exists('h')) { function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

// ---------- Flash ----------
$flash = ''; $flashType = 'success';
if (($_GET['msg'] ?? '') === 'notfound') { $flash = 'Bill not found.'; $flashType = 'danger'; }

// ---------- Filters ----------
$q        = trim($_GET['q'] ?? '');
$status   = $_GET['status'] ?? '';
$dateFrom = $_GET['from'] ?? '';
$dateTo   = $_GET['to'] ?? '';
$isDate   = function ($d) { $o = DateTime::createFromFormat('Y-m-d', $d); return $o && $o->format('Y-m-d') === $d; };
if (!in_array($status, ['paid', 'unpaid'], true)) $status = '';
if ($dateFrom !== '' && !$isDate($dateFrom)) $dateFrom = '';
if ($dateTo   !== '' && !$isDate($dateTo))   $dateTo = '';

$where = []; $types = ''; $params = [];
if ($q !== '') {
    $where[] = "(b.customer_name LIKE ? OR b.contact_person LIKE ? OR b.telephone LIKE ? OR b.email LIKE ? OR CONCAT('AMB-', LPAD(b.id,5,'0')) LIKE ?)";
    $like = '%' . $q . '%';
    for ($i = 0; $i < 5; $i++) { $types .= 's'; $params[] = $like; }
}
if ($status !== '')   { $where[] = 'b.payment_status = ?'; $types .= 's'; $params[] = $status; }
if ($dateFrom !== '') { $where[] = 'b.billing_date >= ?'; $types .= 's'; $params[] = $dateFrom; }
if ($dateTo !== '')   { $where[] = 'b.billing_date <= ?'; $types .= 's'; $params[] = $dateTo; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ---------- Count + page ----------
$perPage = 20;
$page    = max(1, (int)($_GET['page'] ?? 1));

$stmt = $conn->prepare("SELECT COUNT(*) FROM machines_billing b $whereSql");
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = (int)$stmt->get_result()->fetch_row()[0];
$stmt->close();

$pages  = max(1, (int)ceil($total / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT b.*, u.name AS creator_name,
               (SELECT COUNT(*) FROM machines_billing_items i WHERE i.billing_id = b.id) AS item_count
        FROM machines_billing b
        LEFT JOIN users u ON u.id = b.created_by
        $whereSql
        ORDER BY b.billing_date DESC, b.id DESC
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$t2 = $types . 'ii'; $p2 = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($t2, ...$p2);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$qs = function (array $o = []) use ($q, $status, $dateFrom, $dateTo) {
    return '?' . http_build_query(array_filter(array_merge(['q' => $q, 'status' => $status, 'from' => $dateFrom, 'to' => $dateTo], $o), fn($v) => $v !== '' && $v !== null));
};
$hasFilter = ($q !== '' || $status !== '' || $dateFrom !== '' || $dateTo !== '');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Annual Maintenance Bills — Rajaiswari</title>
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
    .tb-right{margin-left:auto;display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
    .btn-add{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 16px;background:var(--green);border:none;border-radius:var(--rs);font-size:.875rem;font-weight:700;color:#fff;text-decoration:none;}
    .btn-add:hover{background:#047857;color:#fff;}
    .btn-soft{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 14px;background:var(--surface-2);border:1.5px solid var(--border);border-radius:var(--rs);font-size:.82rem;font-weight:600;color:var(--t2);text-decoration:none;}
    .btn-soft:hover{background:var(--border);color:var(--t1);}
    .main{flex:1;padding:20px 22px 60px;display:flex;flex-direction:column;gap:14px;}
    .sec{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;}
    .sec-body{padding:16px 18px;}
    .lbl{display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t3);margin-bottom:4px;}
    .form-control,.form-select{font-size:.9rem;border:1.5px solid var(--border);border-radius:var(--rs);min-height:38px;}
    .form-control:focus,.form-select:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
    .alert-flash{border-radius:var(--rs);padding:12px 16px;font-size:.875rem;display:flex;align-items:center;gap:9px;}
    .af-success{background:var(--green-bg);border:1px solid var(--green-b);border-left:3px solid var(--green);color:#065f46;}
    .af-danger{background:var(--red-bg);border:1px solid var(--red-b);border-left:3px solid var(--red);color:#7f1d1d;}
    .utbl{width:100%;border-collapse:collapse;margin:0;}
    .utbl thead th{padding:10px 14px;text-align:left;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--t4);background:var(--surface-2);border-bottom:1px solid var(--border);white-space:nowrap;}
    .utbl tbody td{padding:11px 14px;border-bottom:1px solid var(--bsoft);vertical-align:middle;font-size:.875rem;}
    .utbl tbody tr:last-child td{border-bottom:none;}.utbl tbody tr:hover td{background:#fafbff;}
    .mono{font-family:'DM Mono',monospace;font-size:.82rem;}
    .pill{display:inline-flex;align-items:center;gap:5px;padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:700;border:1px solid;}
    .pill-paid{background:var(--green-bg);color:var(--green);border-color:var(--green-b);}
    .pill-unpaid{background:var(--amber-bg);color:#92400e;border-color:var(--amber-b);}
    .amt{font-family:'DM Mono',monospace;font-weight:500;text-align:right;white-space:nowrap;}
    .muted{color:var(--t4);font-size:.78rem;}
    .btn-act{display:inline-flex;align-items:center;gap:5px;height:30px;padding:0 12px;border-radius:var(--rs);font-size:.78rem;font-weight:700;text-decoration:none;border:1.5px solid;}
    .btn-edit{background:var(--amber-bg);color:#92400e;border-color:var(--amber-b);}.btn-edit:hover{background:var(--amber);color:#fff;border-color:var(--amber);}
    .btn-pdf{background:var(--red-bg);color:#991b1b;border-color:var(--red-b);}.btn-pdf:hover{background:var(--red);color:#fff;border-color:var(--red);}
    .empty{text-align:center;padding:48px 16px;color:var(--t4);}.empty i{font-size:2rem;margin-bottom:10px;display:block;}
    .pagination{margin:0;}.page-link{font-size:.82rem;color:var(--t2);}.page-item.active .page-link{background:var(--blue);border-color:var(--blue);}
    @media(max-width:991.98px){.page-shell{margin-left:0;padding-top:52px;}.top-bar{top:52px;padding:8px 14px;}.main{padding:14px 14px 50px;}}
    @media(max-width:767.98px){
      .utbl thead{display:none;}
      .utbl,.utbl tbody,.utbl tr,.utbl td{display:block;width:100%;}
      .utbl tbody tr{border-bottom:8px solid var(--bg);padding:6px 0;}
      .utbl tbody td{border:none;padding:5px 14px;display:flex;gap:10px;justify-content:space-between;text-align:right;}
      .utbl tbody td::before{content:attr(data-label);font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t4);text-align:left;flex-shrink:0;padding-top:2px;}
      .utbl tbody td.td-act{justify-content:flex-end;padding-bottom:10px;}.utbl tbody td.td-act::before{display:none;}
      .btn-act{height:36px;flex:1;justify-content:center;}.td-act{gap:8px!important;}
    }
  </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page-shell">
  <header class="top-bar">
    <div class="tb-ico"><i class="fas fa-file-invoice-dollar"></i></div>
    <div><div class="tb-title">Annual Maintenance Billing</div><div class="tb-sub"><?= number_format($total) ?> bill<?= $total === 1 ? '' : 's' ?></div></div>
    <div class="tb-right">
      <a href="machines_billing_form.php" class="btn-add"><i class="fas fa-plus"></i> Add New Bill</a>
    </div>
  </header>

  <main class="main">
    <?php if ($flash): ?>
      <div class="alert-flash af-<?= h($flashType) ?>"><i class="fas fa-<?= $flashType === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i><?= h($flash) ?></div>
    <?php endif; ?>

    <form class="sec" method="get">
      <div class="sec-body">
        <div class="row g-2 align-items-end">
          <div class="col-12 col-lg-4">
            <label class="lbl" for="q">Search</label>
            <input type="text" id="q" name="q" class="form-control" value="<?= h($q) ?>" placeholder="Customer, contact, telephone, email, bill no.">
          </div>
          <div class="col-6 col-lg-2">
            <label class="lbl" for="status">Payment</label>
            <select id="status" name="status" class="form-select">
              <option value="">All</option>
              <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
              <option value="unpaid" <?= $status === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
            </select>
          </div>
          <div class="col-6 col-lg-2"><label class="lbl" for="from">From</label><input type="date" id="from" name="from" class="form-control" value="<?= h($dateFrom) ?>"></div>
          <div class="col-6 col-lg-2"><label class="lbl" for="to">To</label><input type="date" id="to" name="to" class="form-control" value="<?= h($dateTo) ?>"></div>
          <div class="col-6 col-lg-2 d-flex gap-2">
            <button class="btn btn-primary flex-fill fw-bold" style="min-height:38px;"><i class="fas fa-search"></i> Filter</button>
            <?php if ($hasFilter): ?><a href="machines_billing_list.php" class="btn btn-outline-secondary" style="min-height:38px;" title="Clear"><i class="fas fa-xmark"></i></a><?php endif; ?>
          </div>
        </div>
      </div>
    </form>

    <div class="sec">
      <?php if (!$rows): ?>
        <div class="empty"><i class="fas fa-file-circle-question"></i>No bills found.<?= $hasFilter ? ' Try clearing the filters.' : '' ?></div>
      <?php else: ?>
      <table class="utbl">
        <thead><tr><th>Bill</th><th>Date</th><th>Customer</th><th>Contact</th><th>Services</th><th style="text-align:right">Grand Total (Tk)</th><th>Payment</th><th>Created by</th><th style="text-align:right">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $paid = $r['payment_status'] === 'paid'; ?>
          <tr>
            <td data-label="Bill"><span class="mono fw-bold">AMB-<?= str_pad((string)$r['id'], 5, '0', STR_PAD_LEFT) ?></span></td>
            <td data-label="Date"><?= h(date('d M Y', strtotime($r['billing_date']))) ?></td>
            <td data-label="Customer"><div class="fw-bold"><?= h($r['customer_name']) ?></div></td>
            <td data-label="Contact"><div><?= h($r['contact_person'] ?: '—') ?></div><?php if ($r['telephone']): ?><div class="muted"><?= h($r['telephone']) ?></div><?php endif; ?></td>
            <td data-label="Services" class="muted"><?= (int)$r['item_count'] ?> item<?= (int)$r['item_count'] === 1 ? '' : 's' ?></td>
            <td data-label="Grand Total" class="amt"><?= h(number_format((float)$r['grand_total'], 2)) ?></td>
            <td data-label="Payment"><span class="pill <?= $paid ? 'pill-paid' : 'pill-unpaid' ?>"><i class="fas fa-<?= $paid ? 'circle-check' : 'clock' ?>"></i><?= $paid ? 'Paid' : 'Unpaid' ?></span></td>
            <td data-label="Created by"><?= h($r['creator_name'] ?? '—') ?></td>
            <td class="td-act" style="text-align:right;white-space:nowrap;">
              <div class="d-flex gap-2 justify-content-end">
                <a class="btn-act btn-edit" href="machines_billing_form.php?id=<?= (int)$r['id'] ?>"><i class="fas fa-pen"></i> Edit</a>
                <a class="btn-act btn-pdf" href="machines_billing_pdf.php?id=<?= (int)$r['id'] ?>"><i class="fas fa-file-pdf"></i> PDF</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <?php if ($pages > 1): ?>
    <nav class="d-flex justify-content-center"><ul class="pagination pagination-sm flex-wrap">
      <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= h($qs(['page' => $page - 1])) ?>">&laquo;</a></li>
      <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= h($qs(['page' => $i])) ?>"><?= $i ?></a></li>
      <?php endfor; ?>
      <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= h($qs(['page' => $page + 1])) ?>">&raquo;</a></li>
    </ul></nav>
    <?php endif; ?>
  </main>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
