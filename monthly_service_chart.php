<?php
// monthly_service_chart.php
require 'auth.php';
require 'mydb.php';

$username = htmlspecialchars($_SESSION['username']);
$userRole = $_SESSION['role'] ?? 'employee';

$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Get all active services
$servicesQuery  = "SELECT id, name FROM services WHERE is_active = 1 ORDER BY name ASC";
$servicesResult = mysqli_query($conn, $servicesQuery);
$services = [];
while ($row = mysqli_fetch_assoc($servicesResult)) $services[] = $row;

// Get available years
$yearsQuery  = "SELECT DISTINCT YEAR(created_at) as year FROM orders ORDER BY year DESC";
$yearsResult = mysqli_query($conn, $yearsQuery);
$availableYears = [];
while ($row = mysqli_fetch_assoc($yearsResult)) $availableYears[] = $row['year'];
if (empty($availableYears)) $availableYears[] = date('Y');

// Build monthly data
$monthlyData = [];
for ($month = 1; $month <= 12; $month++) {
    $monthlyData[$month] = ['month_name' => date('M', mktime(0,0,0,$month,1)), 'services' => []];
    foreach ($services as $service) $monthlyData[$month]['services'][$service['name']] = 0;
}

$dataQuery = "
    SELECT MONTH(o.created_at) as month, s.name as service_name,
           COALESCE(SUM(bi.total_price), 0) as total_income
    FROM orders o
    LEFT JOIN bill_items bi ON o.order_id = bi.order_id
    LEFT JOIN services s ON bi.service_id = s.id
    WHERE o.status = 'paid' AND YEAR(o.created_at) = ? AND s.is_active = 1
    GROUP BY MONTH(o.created_at), s.name
    ORDER BY MONTH(o.created_at), s.name
";
$stmt = mysqli_prepare($conn, $dataQuery);
mysqli_stmt_bind_param($stmt, "i", $selectedYear);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $month = intval($row['month']);
    if (isset($monthlyData[$month]['services'][$row['service_name']]))
        $monthlyData[$month]['services'][$row['service_name']] = floatval($row['total_income']);
}
mysqli_stmt_close($stmt);

// Color palette
$colorPalette = [
    '#3b82f6','#10b981','#ef4444','#f59e0b','#06b6d4',
    '#8b5cf6','#ec4899','#14b8a6','#f97316','#6366f1',
    '#84cc16','#f43f5e','#0ea5e9','#a855f7','#22c55e',
    '#fb923c','#2dd4bf','#fbbf24','#fb7185','#64748b'
];
$serviceColors = [];
$ci = 0;
foreach ($services as $s) { $serviceColors[$s['name']] = $colorPalette[$ci % count($colorPalette)]; $ci++; }

// Chart data
$chartLabels   = [];
$chartDatasets = [];
foreach ($monthlyData as $m => $d) $chartLabels[] = $d['month_name'];
foreach ($services as $service) {
    $sd = [];
    foreach ($monthlyData as $m => $d) $sd[] = $d['services'][$service['name']];
    $chartDatasets[] = [
        'label' => $service['name'], 'data' => $sd,
        'backgroundColor' => $serviceColors[$service['name']] ?? '#94a3b8',
        'borderColor'     => $serviceColors[$service['name']] ?? '#94a3b8',
        'borderWidth'     => 1
    ];
}

// Summary stats
$totalIncome  = 0;
$highestMonth = ['name' => '', 'amount' => 0];
$lowestMonth  = ['name' => '', 'amount' => PHP_INT_MAX];
foreach ($monthlyData as $month => $data) {
    $mt = array_sum($data['services']);
    $totalIncome += $mt;
    if ($mt > $highestMonth['amount'])                    $highestMonth = ['name' => $data['month_name'], 'amount' => $mt];
    if ($mt < $lowestMonth['amount'] && $mt > 0)          $lowestMonth  = ['name' => $data['month_name'], 'amount' => $mt];
}
$avgIncome = $totalIncome / 12;

include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Monthly Service Income <?= $selectedYear ?> — Rajaiswari</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --bg:      #f1f3f6;
            --surface: #ffffff;
            --s2:      #fafbfc;
            --border:  #e4e7ec;
            --bsoft:   #f0f1f3;
            --t1:      #111827;
            --t2:      #374151;
            --t3:      #6b7280;
            --t4:      #9ca3af;
            --blue:    #2563eb; --blue-bg: #eff6ff; --blue-b: #bfdbfe;
            --green:   #059669; --green-bg:#ecfdf5; --green-b:#a7f3d0;
            --amber:   #d97706; --amber-bg:#fffbeb; --amber-b:#fde68a;
            --red:     #dc2626; --red-bg:  #fef2f2; --red-b:  #fecaca;
            --cyan:    #0891b2; --cyan-bg: #ecfeff;
            --r: 10px; --rs: 6px;
            --sh: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', -apple-system, sans-serif;
            font-size: 14px;
            background: var(--bg);
            color: var(--t1);
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
        }

        /* ── Shell ─────────────────────────────── */
        .page-shell { margin-left: 200px; min-height: 100vh; display: flex; flex-direction: column; }

        /* ── Top bar ───────────────────────────── */
        .top-bar {
            position: sticky; top: 0; z-index: 200;
            height: 54px; background: var(--surface);
            border-bottom: 1px solid var(--border); box-shadow: var(--sh);
            display: flex; align-items: center; padding: 0 22px; gap: 12px; flex-shrink: 0;
        }
        .tb-ico {
            width: 32px; height: 32px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; background: var(--blue-bg); color: var(--blue); flex-shrink: 0;
        }
        .tb-title { font-size: 1.0625rem; font-weight: 700; color: var(--t1); }
        .tb-sub   { font-size: .78rem; color: var(--t4); }
        .tb-right { margin-left: auto; display: flex; gap: 8px; align-items: center; }

        /* ── Year selector ─────────────────────── */
        .year-sel {
            height: 32px; padding: 0 28px 0 10px;
            border: 1.5px solid var(--border); border-radius: var(--rs);
            font-family: inherit; font-size: .8125rem; font-weight: 600;
            color: var(--t2); background: var(--surface); outline: none;
            appearance: none; cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='7' viewBox='0 0 10 7'%3E%3Cpath fill='%239ca3af' d='M0 0l5 7 5-7z'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 8px center;
            transition: border-color .15s;
        }
        .year-sel:focus { border-color: var(--blue); }

        /* ── Back button ───────────────────────── */
        .btn-back {
            display: inline-flex; align-items: center; gap: 6px;
            height: 32px; padding: 0 13px;
            border: 1.5px solid var(--border); border-radius: var(--rs);
            font-family: inherit; font-size: .8125rem; font-weight: 600;
            color: var(--t2); background: var(--surface); cursor: pointer;
            text-decoration: none; transition: all .15s;
        }
        .btn-back:hover { background: var(--s2); border-color: #9ca3af; color: var(--t1); }

        /* ── Main ──────────────────────────────── */
        .main { flex: 1; padding: 20px 22px 60px; display: flex; flex-direction: column; gap: 14px; }

        /* ── Stat cards ────────────────────────── */
        .stat-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }
        @media (max-width: 900px) { .stat-row { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 480px) { .stat-row { grid-template-columns: 1fr; } }

        .stat-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--r); padding: 14px 16px;
            box-shadow: var(--sh); display: flex; align-items: center; gap: 12px;
            position: relative; overflow: hidden;
        }
        .stat-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
            border-radius: var(--r) var(--r) 0 0;
        }
        .sc-blue::before  { background: var(--blue); }
        .sc-cyan::before  { background: var(--cyan); }
        .sc-green::before { background: var(--green); }
        .sc-red::before   { background: var(--red); }

        .stat-ico {
            width: 38px; height: 38px; border-radius: 9px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 14px;
        }
        .sc-blue  .stat-ico { background: var(--blue-bg);  color: var(--blue);  }
        .sc-cyan  .stat-ico { background: var(--cyan-bg);  color: var(--cyan);  }
        .sc-green .stat-ico { background: var(--green-bg); color: var(--green); }
        .sc-red   .stat-ico { background: var(--red-bg);   color: var(--red);   }

        .stat-lbl { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--t4); margin-bottom: 3px; }
        .stat-val { font-size: 1.25rem; font-weight: 800; color: var(--t1); letter-spacing: -.02em; line-height: 1; font-family: 'DM Mono', monospace; }
        .stat-val-sm { font-size: .875rem; font-weight: 700; color: var(--t1); line-height: 1.3; }

        /* ── Card ──────────────────────────────── */
        .pos-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--r); box-shadow: var(--sh); overflow: hidden;
        }
        .card-hd {
            display: flex; align-items: center; gap: 9px;
            padding: 11px 18px; background: var(--s2); border-bottom: 1px solid var(--bsoft);
        }
        .ch-ico {
            width: 26px; height: 26px; border-radius: var(--rs);
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; flex-shrink: 0;
        }
        .ci-bl { background: var(--blue-bg); color: var(--blue); }
        .ci-t2 { background: var(--s2); border: 1px solid var(--border); color: var(--t3); }
        .card-hd-title { font-size: .875rem; font-weight: 700; color: var(--t1); }

        /* ── Chart ─────────────────────────────── */
        .chart-wrap { padding: 18px; }
        .chart-inner { position: relative; height: 400px; }

        /* ── Table ─────────────────────────────── */
        .pos-tbl { width: 100%; border-collapse: collapse; }
        .pos-tbl thead th {
            padding: 9px 12px; font-size: .71rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .06em;
            color: var(--t4); background: var(--s2);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        .pos-tbl thead th:first-child { padding-left: 18px; }
        .pos-tbl thead th.r { text-align: right; padding-right: 16px; }

        .pos-tbl tbody td {
            padding: 9px 12px; font-size: .8375rem; color: var(--t2);
            border-bottom: 1px solid var(--bsoft); white-space: nowrap;
        }
        .pos-tbl tbody td:first-child { padding-left: 18px; }
        .pos-tbl tbody tr:last-child td { border-bottom: none; }
        .pos-tbl tbody tr:hover td { background: #f8faff; }
        .pos-tbl tbody td.r { text-align: right; padding-right: 16px; font-family: 'DM Mono', monospace; font-size: .82rem; }
        .pos-tbl tbody td.r.zero { color: var(--t4); }

        .pos-tbl tfoot td {
            padding: 10px 12px; font-size: .8375rem; font-weight: 700;
            background: var(--s2); border-top: 2px solid var(--border);
            white-space: nowrap;
        }
        .pos-tbl tfoot td:first-child { padding-left: 18px; color: var(--t3); font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; }
        .pos-tbl tfoot td.r { text-align: right; padding-right: 16px; font-family: 'DM Mono', monospace; }

        .month-lbl { font-weight: 600; color: var(--t1); }

        /* ── Service color dots in table header ── */
        .svc-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 5px; flex-shrink: 0; }

        /* ── Responsive ────────────────────────── */
        @media (max-width: 991.98px) {
            .page-shell { margin-left: 0; }
            .top-bar    { top: 52px; }
            .main       { padding: 14px 14px 50px; }
            .chart-inner { height: 300px; }
        }
    </style>
</head>
<body>

<div class="page-shell">

    <!-- Top Bar -->
    <header class="top-bar">
        <div class="tb-ico"><i class="fas fa-chart-bar"></i></div>
        <div>
            <div class="tb-title">Monthly Service Income</div>
            <div class="tb-sub">Year-round service revenue analysis · <?= $selectedYear ?></div>
        </div>
        <div class="tb-right">
            <select class="year-sel" onchange="changeYear(this.value)">
                <?php foreach ($availableYears as $year): ?>
                <option value="<?= $year ?>" <?= $year == $selectedYear ? 'selected' : '' ?>><?= $year ?></option>
                <?php endforeach; ?>
            </select>
            <a href="dashboard.php" class="btn-back">
                <i class="fas fa-arrow-left" style="font-size:.6rem;"></i> Dashboard
            </a>
        </div>
    </header>

    <div class="main">

        <!-- Stat cards -->
        <div class="stat-row">
            <div class="stat-card sc-blue">
                <div class="stat-ico"><i class="fas fa-money-bill-wave"></i></div>
                <div>
                    <div class="stat-lbl">Total Income</div>
                    <div class="stat-val">৳<?= number_format($totalIncome, 0) ?></div>
                </div>
            </div>
            <div class="stat-card sc-cyan">
                <div class="stat-ico"><i class="fas fa-chart-line"></i></div>
                <div>
                    <div class="stat-lbl">Average / Month</div>
                    <div class="stat-val">৳<?= number_format($avgIncome, 0) ?></div>
                </div>
            </div>
            <div class="stat-card sc-green">
                <div class="stat-ico"><i class="fas fa-arrow-trend-up"></i></div>
                <div>
                    <div class="stat-lbl">Best Month</div>
                    <div class="stat-val-sm">
                        <?= $highestMonth['name'] ?>
                        <span style="font-size:.78rem;color:var(--t3);font-weight:500;display:block;">৳<?= number_format($highestMonth['amount'], 0) ?></span>
                    </div>
                </div>
            </div>
            <div class="stat-card sc-red">
                <div class="stat-ico"><i class="fas fa-arrow-trend-down"></i></div>
                <div>
                    <div class="stat-lbl">Lowest Month</div>
                    <div class="stat-val-sm">
                        <?= $lowestMonth['name'] !== '' ? $lowestMonth['name'] : '—' ?>
                        <span style="font-size:.78rem;color:var(--t3);font-weight:500;display:block;">
                            <?= $lowestMonth['amount'] !== PHP_INT_MAX ? '৳'.number_format($lowestMonth['amount'], 0) : '—' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart card -->
        <div class="pos-card">
            <div class="card-hd">
                <span class="ch-ico ci-bl"><i class="fas fa-chart-bar"></i></span>
                <span class="card-hd-title">Monthly Breakdown — <?= $selectedYear ?></span>
            </div>
            <div class="chart-wrap">
                <div class="chart-inner">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Breakdown table card -->
        <div class="pos-card">
            <div class="card-hd">
                <span class="ch-ico ci-t2"><i class="fas fa-table-cells"></i></span>
                <span class="card-hd-title">Monthly Breakdown Table</span>
            </div>
            <div style="overflow-x:auto;">
                <table class="pos-tbl">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <?php foreach ($services as $service): ?>
                            <th class="r">
                                <span class="svc-dot" style="background:<?= $serviceColors[$service['name']] ?? '#94a3b8' ?>"></span>
                                <?= htmlspecialchars($service['name']) ?>
                            </th>
                            <?php endforeach; ?>
                            <th class="r" style="color:var(--t1);">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthlyData as $month => $data): ?>
                        <?php $mt = array_sum($data['services']); ?>
                        <tr>
                            <td><span class="month-lbl"><?= $data['month_name'] ?></span></td>
                            <?php foreach ($services as $service): ?>
                            <?php $amt = $data['services'][$service['name']]; ?>
                            <td class="r <?= $amt == 0 ? 'zero' : '' ?>">
                                <?= $amt > 0 ? '৳'.number_format($amt, 0) : '—' ?>
                            </td>
                            <?php endforeach; ?>
                            <td class="r" style="font-weight:700;color:var(--t1);">৳<?= number_format($mt, 0) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            <?php
                            $grandTotal = 0;
                            foreach ($services as $service):
                                $st = 0;
                                foreach ($monthlyData as $m => $d) $st += $d['services'][$service['name']];
                                $grandTotal += $st;
                            ?>
                            <td class="r">৳<?= number_format($st, 0) ?></td>
                            <?php endforeach; ?>
                            <td class="r">৳<?= number_format($grandTotal, 0) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

    </div><!-- /main -->
</div><!-- /page-shell -->

<script>
    function changeYear(year) { window.location.href = '?year=' + year; }

    const ctx = document.getElementById('monthlyChart').getContext('2d');

    const config = {
        type: 'bar',
        data: {
            labels:   <?= json_encode($chartLabels) ?>,
            datasets: <?= json_encode($chartDatasets) ?>
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Monthly Service Income for <?= $selectedYear ?>',
                    font: { size: 15, weight: 'bold' },
                    padding: 14,
                    color: '#111827'
                },
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: { boxWidth: 11, font: { size: 11 }, padding: 12, color: '#6b7280' }
                },
                tooltip: {
                    mode: 'index', intersect: false,
                    backgroundColor: 'rgba(17,24,39,.88)',
                    titleFont: { size: 13, weight: 'bold' },
                    bodyFont: { size: 12 }, padding: 10,
                    callbacks: {
                        label: ctx => {
                            let l = ctx.dataset.label || '';
                            if (l) l += ': ';
                            if (ctx.parsed.y !== null) l += '৳' + ctx.parsed.y.toLocaleString();
                            return l;
                        },
                        footer: items => {
                            let s = 0;
                            items.forEach(i => s += i.parsed.y);
                            return 'Total: ৳' + s.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                x: {
                    stacked: true,
                    grid: { display: false },
                    ticks: { font: { size: 11, weight: '500' }, color: '#6b7280' }
                },
                y: {
                    stacked: true,
                    grid: { color: 'rgba(0,0,0,.04)', drawBorder: false },
                    ticks: {
                        font: { size: 11, weight: '500' }, color: '#6b7280',
                        callback: v => '৳' + (v >= 1000 ? (v/1000).toFixed(0)+'k' : v)
                    }
                }
            },
            animation: { duration: 1000, easing: 'easeInOutQuart' }
        }
    };

    new Chart(ctx, config);
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>