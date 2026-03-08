<?php
// dashboard.php
require 'auth.php';
require 'mydb.php';

$username = htmlspecialchars($_SESSION['username']);
$userRole = $_SESSION['role'] ?? 'employee';
$userId   = $_SESSION['user_id'] ?? null;

$searchDate = $_GET['date'] ?? date('Y-m-d');

// ── Stats query ──────────────────────────────────────────────
$statsQuery = "SELECT 
    COUNT(*) AS total,
    SUM(CASE WHEN status='paid'      THEN 1 ELSE 0 END) AS paidCount,
    SUM(CASE WHEN status='pending'   THEN 1 ELSE 0 END) AS unpaidCount,
    SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelledCount,
    COALESCE(SUM(CASE WHEN status='paid'      THEN (SELECT SUM(bi.total_price) FROM bill_items bi WHERE bi.order_id = orders.order_id) ELSE 0 END), 0) AS paidAmount,
    COALESCE(SUM(CASE WHEN status='pending'   THEN (SELECT SUM(bi.total_price) FROM bill_items bi WHERE bi.order_id = orders.order_id) ELSE 0 END), 0) AS unpaidAmount,
    COALESCE(SUM(CASE WHEN status='cancelled' THEN (SELECT SUM(bi.total_price) FROM bill_items bi WHERE bi.order_id = orders.order_id) ELSE 0 END), 0) AS cancelledAmount,
    COALESCE((SELECT SUM(bi.total_price) FROM bill_items bi JOIN orders o2 ON bi.order_id = o2.order_id WHERE DATE(o2.created_at) = orders.created_at), 0) AS totalAmount
FROM orders WHERE DATE(created_at) = ?";

$stmt = mysqli_prepare($conn, $statsQuery);
mysqli_stmt_bind_param($stmt, "s", $searchDate);
mysqli_stmt_execute($stmt);
$stats = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

// ── Total amount ─────────────────────────────────────────────
$totalAmountQuery = "SELECT COALESCE(SUM(bi.total_price), 0) as total_amount
FROM orders o LEFT JOIN bill_items bi ON o.order_id = bi.order_id
WHERE DATE(o.created_at) = ?";
$stmt = mysqli_prepare($conn, $totalAmountQuery);
mysqli_stmt_bind_param($stmt, "s", $searchDate);
mysqli_stmt_execute($stmt);
$totalAmountResult = mysqli_stmt_get_result($stmt)->fetch_assoc();
$totalAmount = $totalAmountResult['total_amount'] ?? 0;
mysqli_stmt_close($stmt);

$revenue         = $stats['paidAmount']      ?? 0;
$unpaidAmount    = $stats['unpaidAmount']    ?? 0;
$cancelledAmount = $stats['cancelledAmount'] ?? 0;

// ── Services list ────────────────────────────────────────────
$servicesListQuery  = "SELECT id, name FROM services WHERE is_active = 1 ORDER BY name ASC";
$servicesListResult = mysqli_query($conn, $servicesListQuery);
$servicesList = [];
while ($row = mysqli_fetch_assoc($servicesListResult)) { $servicesList[] = $row; }

// ── Service summary ──────────────────────────────────────────
$servicesSummary = [];
foreach ($servicesList as $s) { $servicesSummary[$s['name']] = ['qty'=>0,'total'=>0]; }

$servicesQuery = "
    SELECT s.name as service_name,
           SUM(bi.quantity) as total_qty,
           SUM(bi.total_price) as total_amount
    FROM orders o
    LEFT JOIN bill_items bi ON o.order_id = bi.order_id
    LEFT JOIN services s ON bi.service_id = s.id
    WHERE o.status='paid' AND DATE(o.created_at) = ?
    GROUP BY s.name";
$stmt = mysqli_prepare($conn, $servicesQuery);
mysqli_stmt_bind_param($stmt, "s", $searchDate);
mysqli_stmt_execute($stmt);
$servicesResult = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($servicesResult)) {
    $sn  = $row['service_name'];
    $qty = floatval($row['total_qty']    ?? 0);
    $tot = floatval($row['total_amount'] ?? 0);
    if (!empty($sn)) {
        $matched = false;
        foreach ($servicesSummary as $key => &$sum) {
            if (strcasecmp($key, $sn) === 0) { $sum['qty'] += $qty; $sum['total'] += $tot; $matched = true; break; }
        }
        if (!$matched) {
            if (!isset($servicesSummary['Others'])) $servicesSummary['Others'] = ['qty'=>0,'total'=>0];
            $servicesSummary['Others']['qty']   += $qty;
            $servicesSummary['Others']['total'] += $tot;
        }
    }
}
mysqli_stmt_close($stmt);

// ── Daily expenses ───────────────────────────────────────────
$expQuery = "SELECT COALESCE(SUM(amount),0) as total FROM daily_expenses WHERE DATE(created_time) = ?";
$stmt = mysqli_prepare($conn, $expQuery);
mysqli_stmt_bind_param($stmt, "s", $searchDate);
mysqli_stmt_execute($stmt);
$dailyExpensesTotal = mysqli_stmt_get_result($stmt)->fetch_assoc()['total'] ?? 0;
mysqli_stmt_close($stmt);

$netProfit = $revenue - $dailyExpensesTotal;

// ── Service icons ────────────────────────────────────────────
$serviceIcons = [
    'Hallmark'    => 'fa-stamp',
    'Purity Test' => 'fa-vial',
    'Welding'     => 'fa-fire',
    'Melting'     => 'fa-fire-flame-curved',
    'Polishing'   => 'fa-star',
    'Engraving'   => 'fa-pen-nib',
    'Others'      => 'fa-ellipsis',
];

$isToday   = ($searchDate === date('Y-m-d'));
$dateLabel = $isToday ? 'Today' : date('M d, Y', strtotime($searchDate));

// ── Monthly chart data ───────────────────────────────────────
$currentYear  = date('Y');
$colorPalette = ['#3b82f6','#10b981','#ef4444','#f59e0b','#06b6d4','#8b5cf6','#ec4899','#14b8a6','#f97316','#6366f1','#84cc16','#f43f5e','#0ea5e9','#a855f7','#22c55e','#fb923c','#2dd4bf','#fbbf24','#fb7185','#64748b'];
$serviceColors = [];
$ci = 0;
foreach ($servicesList as $s) { $serviceColors[$s['name']] = $colorPalette[$ci++ % count($colorPalette)]; }

$monthlyData = [];
for ($m = 1; $m <= 12; $m++) {
    $monthlyData[$m] = ['month_name' => date('M', mktime(0,0,0,$m,1)), 'services' => []];
    foreach ($servicesList as $s) $monthlyData[$m]['services'][$s['name']] = 0;
}

$chartDataQuery = "SELECT MONTH(o.created_at) as month, s.name as service_name, COALESCE(SUM(bi.total_price),0) as total_income
    FROM orders o LEFT JOIN bill_items bi ON o.order_id = bi.order_id LEFT JOIN services s ON bi.service_id = s.id
    WHERE o.status='paid' AND YEAR(o.created_at)=? AND s.is_active=1
    GROUP BY MONTH(o.created_at), s.name ORDER BY MONTH(o.created_at), s.name";
$stmt = mysqli_prepare($conn, $chartDataQuery);
mysqli_stmt_bind_param($stmt, "i", $currentYear);
mysqli_stmt_execute($stmt);
$chartResult = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($chartResult)) {
    $m = intval($row['month']); $sn = $row['service_name'];
    if (isset($monthlyData[$m]['services'][$sn])) $monthlyData[$m]['services'][$sn] = floatval($row['total_income']);
}
mysqli_stmt_close($stmt);

$chartLabels = [];
$chartDatasets = [];
foreach ($monthlyData as $m => $d) $chartLabels[] = $d['month_name'];
foreach ($servicesList as $s) {
    $sn = $s['name']; $sd = [];
    foreach ($monthlyData as $m => $d) $sd[] = $d['services'][$sn];
    $chartDatasets[] = ['label'=>$sn,'data'=>$sd,'backgroundColor'=>$serviceColors[$sn] ?? '#94a3b8','borderColor'=>$serviceColors[$sn] ?? '#94a3b8','borderWidth'=>1,'borderRadius'=>3];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Dashboard — <?= $dateLabel ?></title>
  <link rel="icon" type="image/png" href="favicon.png">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

  <style>
    /* ── Reset ──────────────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { height: 100%; }

    body {
      min-height: 100%;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      font-size: 13px;
      background: #f1f3f6;
      color: #1a1d23;
      -webkit-font-smoothing: antialiased;
    }

    /* ── Page shell ─────────────────────────────────────────── */
    .page-shell {
      margin-left: 200px;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ── Sticky top bar ─────────────────────────────────────── */
    .top-bar {
      position: sticky;
      top: 0;
      z-index: 200;
      height: 54px;
      background: #ffffff;
      border-bottom: 1px solid #e4e7ec;
      box-shadow: 0 1px 4px rgba(0,0,0,.06);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 22px;
      gap: 12px;
      flex-shrink: 0;
    }

    .tb-left {
      display: flex;
      align-items: center;
      gap: 10px;
      min-width: 0;
      flex: 1;
    }

    .tb-title {
      font-size: 0.9375rem;
      font-weight: 700;
      color: #111827;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .tb-title em { font-style: normal; color: #2563eb; }

    .chip {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      border-radius: 20px;
      padding: 3px 9px;
      font-size: 0.6875rem;
      font-weight: 600;
      white-space: nowrap;
      flex-shrink: 0;
    }

    .chip-role { background: #f1f5f9; border: 1px solid #e2e8f0; color: #64748b; }
    .chip-date { background: #eff6ff; border: 1px solid #bfdbfe; color: #2563eb; border-radius: 6px; }

    .tb-right {
      display: flex;
      align-items: center;
      gap: 7px;
      flex-shrink: 0;
    }

    .date-form {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .date-form input[type="date"] {
      height: 34px;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      padding: 0 10px;
      font-family: inherit;
      font-size: 0.8125rem;
      color: #374151;
      background: #fff;
      outline: none;
      transition: border-color .15s, box-shadow .15s;
    }

    .date-form input[type="date"]:focus {
      border-color: #2563eb;
      box-shadow: 0 0 0 3px rgba(37,99,235,.1);
    }

    .btn-primary-sm {
      height: 34px;
      padding: 0 14px;
      background: #2563eb;
      color: #fff;
      border: none;
      border-radius: 6px;
      font-family: inherit;
      font-size: 0.8125rem;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      transition: background .15s;
      white-space: nowrap;
      text-decoration: none;
    }

    .btn-primary-sm:hover { background: #1d4ed8; color: #fff; }

    .btn-ghost-sm {
      height: 34px;
      padding: 0 12px;
      background: #fff;
      color: #374151;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      font-family: inherit;
      font-size: 0.8125rem;
      font-weight: 500;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      text-decoration: none;
      transition: all .15s;
      white-space: nowrap;
    }

    .btn-ghost-sm:hover { background: #f9fafb; border-color: #9ca3af; color: #111827; }

    /* ── Main scroll area ───────────────────────────────────── */
    .main {
      flex: 1;
      padding: 20px 22px 48px;
      width: 100%;
    }

    /* ── Section heading ────────────────────────────────────── */
    .s-head {
      font-size: 0.6875rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: #94a3b8;
      margin-bottom: 10px;
    }

    /* ── Stat cards ─────────────────────────────────────────── */
    .stat-grid {
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 10px;
      margin-bottom: 18px;
    }

    @media (max-width: 1280px) { .stat-grid { grid-template-columns: repeat(3,1fr); } }
    @media (max-width: 600px)  { .stat-grid { grid-template-columns: repeat(2,1fr); } }

    .sc {
      background: #fff;
      border: 1px solid #e4e7ec;
      border-radius: 10px;
      padding: 14px 15px 12px;
      position: relative;
      overflow: hidden;
      transition: box-shadow .18s, transform .18s;
    }

    .sc:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); transform: translateY(-1px); }

    .sc::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 3px;
      border-radius: 10px 10px 0 0;
    }

    .sc.c-blue::before   { background: #2563eb; }
    .sc.c-green::before  { background: #059669; }
    .sc.c-amber::before  { background: #d97706; }
    .sc.c-red::before    { background: #dc2626; }
    .sc.c-cyan::before   { background: #0891b2; }
    .sc.c-violet::before { background: #7c3aed; }

    .sc-ico {
      width: 34px; height: 34px;
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      font-size: 13px;
      margin-bottom: 10px;
    }

    .sc.c-blue   .sc-ico { background: #eff6ff; color: #2563eb; }
    .sc.c-green  .sc-ico { background: #ecfdf5; color: #059669; }
    .sc.c-amber  .sc-ico { background: #fffbeb; color: #d97706; }
    .sc.c-red    .sc-ico { background: #fef2f2; color: #dc2626; }
    .sc.c-cyan   .sc-ico { background: #ecfeff; color: #0891b2; }
    .sc.c-violet .sc-ico { background: #f5f3ff; color: #7c3aed; }

    .sc-lbl {
      font-size: 0.6875rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: #9ca3af;
      margin-bottom: 4px;
    }

    .sc-num {
      font-size: 1.5rem;
      font-weight: 700;
      line-height: 1;
      color: #111827;
      letter-spacing: -.5px;
    }

    .sc-sub { font-size: 0.75rem; font-weight: 600; margin-top: 3px; }
    .sc.c-blue   .sc-sub { color: #2563eb; }
    .sc.c-green  .sc-sub { color: #059669; }
    .sc.c-amber  .sc-sub { color: #d97706; }
    .sc.c-red    .sc-sub { color: #dc2626; }
    .sc.c-cyan   .sc-sub { color: #0891b2; }
    .sc.c-violet .sc-sub { color: #7c3aed; }

    /* ── Two col panels ─────────────────────────────────────── */
    .panels-grid {
      display: grid;
      grid-template-columns: 400px 1fr;
      gap: 14px;
      margin-bottom: 18px;
    }

    @media (max-width: 1100px) { .panels-grid { grid-template-columns: 1fr; } }

    .left-stack { display: flex; flex-direction: column; gap: 12px; }

    /* ── Panel ──────────────────────────────────────────────── */
    .panel {
      background: #fff;
      border: 1px solid #e4e7ec;
      border-radius: 10px;
      overflow: hidden;
    }

    .panel-hd {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 11px 15px;
      background: #fafbfc;
      border-bottom: 1px solid #f0f1f3;
    }

    .panel-hd-t {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.8125rem;
      font-weight: 700;
      color: #1a1d23;
    }

    .p-ico {
      width: 26px; height: 26px;
      border-radius: 6px;
      display: flex; align-items: center; justify-content: center;
      font-size: 11px; flex-shrink: 0;
    }

    .p-ico.bl { background: #eff6ff; color: #2563eb; }
    .p-ico.gr { background: #ecfdf5; color: #059669; }
    .p-ico.vi { background: #f5f3ff; color: #7c3aed; }

    .p-meta { font-size: 0.7rem; font-weight: 400; color: #94a3b8; }

    .p-link {
      font-size: 0.75rem; font-weight: 600; color: #2563eb;
      text-decoration: none;
      display: inline-flex; align-items: center; gap: 4px;
      transition: color .15s;
    }

    .p-link:hover { color: #1d4ed8; }

    /* ── Data table ─────────────────────────────────────────── */
    .dtbl { width: 100%; border-collapse: collapse; }

    .dtbl thead th {
      padding: 8px 14px;
      font-size: 0.6875rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .07em;
      color: #9ca3af;
      background: #fafbfc;
      border-bottom: 1px solid #f0f1f3;
      white-space: nowrap;
    }

    .dtbl tbody td {
      padding: 9px 14px;
      font-size: 0.8125rem;
      color: #374151;
      border-bottom: 1px solid #f8f9fa;
      vertical-align: middle;
    }

    .dtbl tbody tr:last-child td { border-bottom: none; }
    .dtbl tbody tr:hover td { background: #fafbfc; }

    .dtbl tfoot td {
      padding: 9px 14px;
      font-size: 0.8125rem; font-weight: 700;
      color: #111827;
      background: #fafbfc;
      border-top: 2px solid #e4e7ec;
    }

    .svc-dot {
      display: inline-flex; align-items: center; justify-content: center;
      width: 24px; height: 24px;
      border-radius: 5px;
      background: #eff6ff; color: #2563eb;
      font-size: 10px;
      margin-right: 6px;
      vertical-align: middle;
      flex-shrink: 0;
    }

    .qty-tag {
      display: inline-block;
      background: #f1f5f9; border: 1px solid #e2e8f0;
      border-radius: 4px; padding: 1px 7px;
      font-size: 0.75rem; font-weight: 600; color: #475569;
    }

    .mono { font-size: 0.8125rem; font-weight: 600; color: #111827; }

    .empty-state {
      text-align: center; padding: 28px 16px; color: #9ca3af;
    }

    .empty-state i { font-size: 1.6rem; display: block; margin-bottom: 7px; opacity: .3; }
    .empty-state p { font-size: 0.8125rem; margin: 0; }

    /* ── Financial rows ─────────────────────────────────────── */
    .fin-r {
      display: flex; align-items: center;
      justify-content: space-between;
      padding: 11px 15px;
      border-bottom: 1px solid #f8f9fa;
    }

    .fin-r:last-child { border-bottom: none; }

    .fin-lbl {
      display: flex; align-items: center;
      gap: 8px; font-size: 0.8125rem;
      font-weight: 500; color: #374151;
    }

    .f-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
    .fin-amt { font-size: 0.875rem; font-weight: 700; }

    .fin-total {
      display: flex; align-items: center;
      justify-content: space-between;
      padding: 12px 15px;
      background: #f8faff;
      border-top: 2px solid #e4e7ec;
    }

    .fin-total-lbl {
      display: flex; align-items: center;
      gap: 8px; font-size: 0.8125rem;
      font-weight: 700; color: #111827;
    }

    .fin-total-amt { font-size: 0.9375rem; font-weight: 800; }

    /* ── Chart ──────────────────────────────────────────────── */
    .chart-body {
      padding: 14px;
      flex: 1;
      min-height: 320px;
      position: relative;
    }

    .chart-panel {
      display: flex;
      flex-direction: column;
      height: 100%;
    }

    /* ── Quick nav ──────────────────────────────────────────── */
    .qgrid {
      display: grid;
      grid-template-columns: repeat(4,1fr);
      gap: 10px;
    }

    @media (max-width: 1100px) { .qgrid { grid-template-columns: repeat(3,1fr); } }
    @media (max-width: 700px)  { .qgrid { grid-template-columns: repeat(2,1fr); } }

    .qcard {
      display: flex; align-items: center; gap: 11px;
      padding: 13px 14px;
      background: #fff;
      border: 1px solid #e4e7ec;
      border-radius: 10px;
      text-decoration: none;
      transition: all .15s;
    }

    .qcard:hover {
      border-color: #bfdbfe;
      box-shadow: 0 3px 10px rgba(0,0,0,.07);
      transform: translateY(-1px);
      text-decoration: none;
    }

    .q-ico {
      width: 38px; height: 38px;
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      font-size: 14px; flex-shrink: 0;
    }

    .qi-amber  { background: #fffbeb; color: #d97706; }
    .qi-red    { background: #fef2f2; color: #dc2626; }
    .qi-cyan   { background: #ecfeff; color: #0891b2; }
    .qi-blue   { background: #eff6ff; color: #2563eb; }
    .qi-green  { background: #ecfdf5; color: #059669; }
    .qi-violet { background: #f5f3ff; color: #7c3aed; }

    .q-txt h6 {
      margin: 0 0 2px;
      font-size: 0.8125rem; font-weight: 700;
      color: #111827; line-height: 1.2;
    }

    .q-txt p {
      margin: 0;
      font-size: 0.7rem; color: #9ca3af; line-height: 1.3;
    }

    /* ── Mobile ─────────────────────────────────────────────── */
    @media (max-width: 991.98px) {
      .page-shell { margin-left: 0; }
      .top-bar { top: 52px; }
      .main { padding: 14px 14px 40px; }
      .chart-body { min-height: 240px; }
    }
  </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="page-shell">

  <!-- ── Top Bar ──────────────────────────────────────────────── -->
  <header class="top-bar">
    <div class="tb-left">
      <span class="tb-title">Welcome back, <em><?= $username ?></em></span>
      <span class="chip chip-role">
        <i class="fas fa-user-shield" style="font-size:.6rem;"></i>
        <?= htmlspecialchars($userRole) ?>
      </span>
      <span class="chip chip-date">
        <i class="fas fa-calendar-day" style="font-size:.65rem;"></i>
        <?= $dateLabel ?>
      </span>
    </div>
    <div class="tb-right">
      <form method="get" class="date-form">
        <input type="date" name="date"
               value="<?= htmlspecialchars($searchDate) ?>"
               max="<?= date('Y-m-d') ?>">
        <button type="submit" class="btn-primary-sm">
          <i class="fas fa-magnifying-glass" style="font-size:.65rem;"></i> Search
        </button>
        <?php if (!$isToday): ?>
        <a href="dashboard.php" class="btn-ghost-sm">
          <i class="fas fa-rotate-left" style="font-size:.65rem;"></i> Today
        </a>
        <?php endif; ?>
      </form>
    </div>
  </header>

  <!-- ── Main ─────────────────────────────────────────────────── -->
  <div class="main">

    <!-- Stat cards -->
    <p class="s-head">Overview — <?= $dateLabel ?></p>
    <div class="stat-grid">

      <div class="sc c-blue">
        <div class="sc-ico"><i class="fas fa-receipt"></i></div>
        <div class="sc-lbl">Total Orders</div>
        <div class="sc-num"><?= $stats['total'] ?? 0 ?></div>
        <div class="sc-sub">৳<?= number_format($totalAmount, 0) ?></div>
      </div>

      <div class="sc c-green">
        <div class="sc-ico"><i class="fas fa-circle-check"></i></div>
        <div class="sc-lbl">Paid Orders</div>
        <div class="sc-num"><?= $stats['paidCount'] ?? 0 ?></div>
        <div class="sc-sub">৳<?= number_format($revenue, 0) ?></div>
      </div>

      <div class="sc c-amber">
        <div class="sc-ico"><i class="fas fa-clock"></i></div>
        <div class="sc-lbl">Pending</div>
        <div class="sc-num"><?= $stats['unpaidCount'] ?? 0 ?></div>
        <div class="sc-sub">৳<?= number_format($unpaidAmount, 0) ?></div>
      </div>

      <div class="sc c-red">
        <div class="sc-ico"><i class="fas fa-circle-xmark"></i></div>
        <div class="sc-lbl">Cancelled</div>
        <div class="sc-num"><?= $stats['cancelledCount'] ?? 0 ?></div>
        <div class="sc-sub">৳<?= number_format($cancelledAmount, 0) ?></div>
      </div>

      <div class="sc c-cyan">
        <div class="sc-ico"><i class="fas fa-money-bill-wave"></i></div>
        <div class="sc-lbl">Revenue</div>
        <div class="sc-num">৳<?= number_format($revenue, 0) ?></div>
      </div>

      <div class="sc <?= $netProfit >= 0 ? 'c-green' : 'c-red' ?>">
        <div class="sc-ico"><i class="fas fa-chart-line"></i></div>
        <div class="sc-lbl">Net Profit</div>
        <div class="sc-num">৳<?= number_format($netProfit, 0) ?></div>
      </div>

    </div>

    <!-- Panels -->
    <div class="panels-grid">

      <!-- Left: service + financial -->
      <div class="left-stack">

        <div class="panel">
          <div class="panel-hd">
            <div class="panel-hd-t">
              <span class="p-ico bl"><i class="fas fa-chart-pie"></i></span>
              Service Summary
              <span class="p-meta">— <?= $dateLabel ?></span>
            </div>
          </div>
          <div style="overflow-x:auto;">
            <table class="dtbl">
              <thead>
                <tr>
                  <th>Service</th>
                  <th style="text-align:center;">Qty</th>
                  <th style="text-align:right;">Amount (৳)</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $tQty = 0; $tAmt = 0; $hasData = false;
                foreach ($servicesSummary as $sn => $d):
                  if ($d['qty'] == 0 && $d['total'] == 0) continue;
                  $hasData = true; $tQty += $d['qty']; $tAmt += $d['total'];
                  $ico = $serviceIcons[$sn] ?? 'fa-cog';
                ?>
                <tr>
                  <td>
                    <span class="svc-dot"><i class="fas <?= $ico ?>"></i></span>
                    <?= htmlspecialchars($sn) ?>
                  </td>
                  <td style="text-align:center;"><span class="qty-tag"><?= $d['qty'] ?></span></td>
                  <td style="text-align:right;" class="mono"><?= number_format($d['total'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$hasData): ?>
                <tr><td colspan="3">
                  <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No paid services for this date</p>
                  </div>
                </td></tr>
                <?php endif; ?>
              </tbody>
              <?php if ($hasData): ?>
              <tfoot>
                <tr>
                  <td><strong>Total</strong></td>
                  <td style="text-align:center;"><strong><?= $tQty ?></strong></td>
                  <td style="text-align:right;"><strong>৳<?= number_format($tAmt, 2) ?></strong></td>
                </tr>
              </tfoot>
              <?php endif; ?>
            </table>
          </div>
        </div>

        <div class="panel">
          <div class="panel-hd">
            <div class="panel-hd-t">
              <span class="p-ico gr"><i class="fas fa-calculator"></i></span>
              Financial Summary
              <span class="p-meta">— <?= $dateLabel ?></span>
            </div>
          </div>
          <div class="fin-r">
            <div class="fin-lbl">
              <span class="f-dot" style="background:#059669;"></span>Revenue (Paid Orders)
            </div>
            <span class="fin-amt" style="color:#059669;">৳<?= number_format($revenue, 2) ?></span>
          </div>
          <div class="fin-r">
            <div class="fin-lbl">
              <span class="f-dot" style="background:#dc2626;"></span>Daily Expenses
            </div>
            <span class="fin-amt" style="color:#dc2626;">৳<?= number_format($dailyExpensesTotal, 2) ?></span>
          </div>
          <div class="fin-total">
            <div class="fin-total-lbl">
              <i class="fas fa-chart-line"
                 style="color:<?= $netProfit >= 0 ? '#059669' : '#dc2626' ?>;font-size:.8rem;"></i>
              Net Profit
            </div>
            <span class="fin-total-amt"
                  style="color:<?= $netProfit >= 0 ? '#059669' : '#dc2626' ?>;">
              ৳<?= number_format($netProfit, 2) ?>
            </span>
          </div>
        </div>

      </div>

      <!-- Right: chart -->
      <div class="panel chart-panel">
        <div class="panel-hd">
          <div class="panel-hd-t">
            <span class="p-ico vi"><i class="fas fa-chart-column"></i></span>
            Monthly Service Income
            <span class="p-meta"><?= $currentYear ?></span>
          </div>
          <a href="monthly_service_chart.php" class="p-link">
            Full Chart <i class="fas fa-arrow-right" style="font-size:.65rem;"></i>
          </a>
        </div>
        <div class="chart-body">
          <canvas id="monthlyServiceChart"></canvas>
        </div>
      </div>

    </div><!-- /panels-grid -->

    <!-- Quick Access -->
    <p class="s-head">Quick Access</p>
    <div class="qgrid">

      <a href="unpaid_bills.php" class="qcard">
        <div class="q-ico qi-amber"><i class="fas fa-clock"></i></div>
        <div class="q-txt"><h6>Pending Orders</h6><p>Manage pending payments</p></div>
      </a>

      <a href="daily_expenses.php" class="qcard">
        <div class="q-ico qi-red"><i class="fas fa-wallet"></i></div>
        <div class="q-txt"><h6>Daily Expenses</h6><p>Track daily costs</p></div>
      </a>

      <a href="reports.php" class="qcard">
        <div class="q-ico qi-cyan"><i class="fas fa-chart-bar"></i></div>
        <div class="q-txt"><h6>Sales Reports</h6><p>View detailed reports</p></div>
      </a>

      <a href="account.php" class="qcard">
        <div class="q-ico qi-blue"><i class="fas fa-chart-line"></i></div>
        <div class="q-txt"><h6>Finance Panel</h6><p>Income &amp; Expenses</p></div>
      </a>

      <?php if ($userRole === 'admin'): ?>
      <a href="edit_bills.php" class="qcard">
        <div class="q-ico qi-violet"><i class="fas fa-pen-to-square"></i></div>
        <div class="q-txt"><h6>Manage Orders</h6><p>Edit all orders</p></div>
      </a>

      <a href="users.php" class="qcard">
        <div class="q-ico qi-green"><i class="fas fa-users"></i></div>
        <div class="q-txt"><h6>Manage Users</h6><p>User administration</p></div>
      </a>

      <?php if ($userId == 1): ?>
      <a href="manage_items_services.php" class="qcard">
        <div class="q-ico qi-amber"><i class="fas fa-gear"></i></div>
        <div class="q-txt"><h6>Items &amp; Services</h6><p>Manage catalog</p></div>
      </a>

      <a href="manage_licenses.php" class="qcard">
        <div class="q-ico qi-blue"><i class="fas fa-key"></i></div>
        <div class="q-txt"><h6>Branch Licenses</h6><p>Manage licenses</p></div>
      </a>

      <a href="store_msg.php" class="qcard">
        <div class="q-ico qi-cyan"><i class="fas fa-paper-plane"></i></div>
        <div class="q-txt"><h6>Branch Message</h6><p>Send to branches</p></div>
      </a>
      <?php endif; ?>
      <?php endif; ?>

    </div>

  </div><!-- /main -->
</div><!-- /page-shell -->

<script>
(function () {
  const ctx = document.getElementById('monthlyServiceChart').getContext('2d');
  Chart.defaults.font.family = "'Inter', -apple-system, sans-serif";
  Chart.defaults.font.size   = 11;

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels:   <?= json_encode($chartLabels) ?>,
      datasets: <?= json_encode($chartDatasets) ?>
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            boxWidth: 9, boxHeight: 9,
            borderRadius: 3, useBorderRadius: true,
            padding: 10, color: '#6b7280',
            font: { size: 11, weight: '500' }
          }
        },
        tooltip: {
          mode: 'index', intersect: false,
          backgroundColor: '#111827',
          titleColor: '#f9fafb', bodyColor: '#d1d5db',
          borderColor: '#374151', borderWidth: 1,
          padding: 10,
          titleFont: { size: 12, weight: '700' },
          bodyFont: { size: 11 },
          callbacks: {
            label: c => (c.dataset.label ? c.dataset.label + ': ' : '') + '৳' + c.parsed.y.toLocaleString(),
            footer: items => 'Total: ৳' + items.reduce((a, i) => a + i.parsed.y, 0).toLocaleString()
          }
        }
      },
      scales: {
        x: {
          stacked: true,
          grid: { display: false },
          ticks: { color: '#9ca3af', font: { size: 11, weight: '500' } },
          border: { color: '#e4e7ec' }
        },
        y: {
          stacked: true,
          grid: { color: 'rgba(0,0,0,.04)' },
          ticks: {
            color: '#9ca3af',
            font: { size: 11 },
            callback: v => '৳' + (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v)
          },
          border: { dash: [3, 3], color: 'transparent' }
        }
      },
      animation: { duration: 800, easing: 'easeInOutQuart' }
    }
  });
})();
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>