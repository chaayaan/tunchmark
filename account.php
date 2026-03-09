<?php
// account.php - Main dashboard for the accounting system
require 'auth.php';
include 'mydb.php';

date_default_timezone_set('Asia/Dhaka');

$userRole = $_SESSION['role'] ?? 'employee';

$stats = [
    'total_branches'           => 0,
    'total_income_categories'  => 0,
    'total_expense_categories' => 0,
    'today_income'             => 0,
    'today_expenses'           => 0
];

$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM branches");
if ($result && $row = mysqli_fetch_assoc($result)) $stats['total_branches'] = $row['count'];

$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM income_categories");
if ($result && $row = mysqli_fetch_assoc($result)) $stats['total_income_categories'] = $row['count'];

$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM expense_categories");
if ($result && $row = mysqli_fetch_assoc($result)) $stats['total_expense_categories'] = $row['count'];

$today  = date('Y-m-d');
$result = mysqli_query($conn, "SELECT COALESCE(SUM(income), 0) as total FROM branch_income WHERE DATE(created_at) = '$today'");
if ($result && $row = mysqli_fetch_assoc($result)) $stats['today_income'] = $row['total'];

$result = mysqli_query($conn, "SELECT COALESCE(SUM(expense), 0) as total FROM branch_expenses WHERE DATE(created_at) = '$today'");
if ($result && $row = mysqli_fetch_assoc($result)) $stats['today_expenses'] = $row['total'];

function formatCurrency($amount) { return '৳' . number_format($amount, 2); }

$netBalance  = $stats['today_income'] - $stats['today_expenses'];
$netPositive = $netBalance >= 0;

include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Finance Dashboard — Rajaiswari</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
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
            --cyan:    #0891b2; --cyan-bg: #ecfeff; --cyan-b: #a5f3fc;
            --violet:  #7c3aed; --violet-bg:#f5f3ff; --violet-b:#ddd6fe;
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
            font-size: 13px; background: var(--green-bg); color: var(--green); flex-shrink: 0;
        }
        .tb-title { font-size: 1.0625rem; font-weight: 700; color: var(--t1); }
        .tb-sub   { font-size: .78rem; color: var(--t4); }
        .tb-right { margin-left: auto; display: flex; gap: 7px; align-items: center; }

        .date-chip {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 12px; background: var(--s2); border: 1px solid var(--border);
            border-radius: 20px; font-size: .78rem; font-weight: 600; color: var(--t3);
        }

        /* ── Main ──────────────────────────────── */
        .main { flex: 1; padding: 20px 22px 60px; display: flex; flex-direction: column; gap: 18px; }

        /* ── Section label ─────────────────────── */
        .sec-label {
            display: flex; align-items: center; gap: 7px;
            font-size: .8rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .07em; color: var(--t3); margin-bottom: 2px;
        }
        .sec-label i { font-size: .65rem; }

        /* ── Stat cards ────────────────────────── */
        .stat-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        @media (max-width: 800px) { .stat-row { grid-template-columns: 1fr; } }

        .stat-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--r); padding: 16px 18px;
            box-shadow: var(--sh); display: flex; align-items: center; gap: 14px;
            position: relative; overflow: hidden;
        }
        .stat-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
            border-radius: var(--r) var(--r) 0 0;
        }
        .sc-blue::before   { background: var(--blue);  }
        .sc-green::before  { background: var(--green); }
        .sc-red::before    { background: var(--red);   }

        .stat-ico {
            width: 40px; height: 40px; border-radius: 9px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 14px;
        }
        .sc-blue  .stat-ico { background: var(--blue-bg);  color: var(--blue);  }
        .sc-green .stat-ico { background: var(--green-bg); color: var(--green); }
        .sc-red   .stat-ico { background: var(--red-bg);   color: var(--red);   }

        .stat-lbl { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--t4); margin-bottom: 3px; }
        .stat-val { font-size: 1.75rem; font-weight: 800; color: var(--t1); letter-spacing: -.03em; line-height: 1; font-family: 'DM Mono', monospace; }

        /* ── Action cards grid ─────────────────── */
        .action-grid {
            display: grid;
            gap: 12px;
        }
        .ag-admin    { grid-template-columns: repeat(4, 1fr); }
        .ag-employee { grid-template-columns: repeat(2, 1fr); }
        @media (max-width: 900px) { .ag-admin, .ag-employee { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 500px) { .ag-admin, .ag-employee { grid-template-columns: 1fr; } }

        .action-card {
            background: var(--surface); border: 1.5px solid var(--border);
            border-radius: var(--r); padding: 22px 18px 18px;
            box-shadow: var(--sh); text-decoration: none; color: inherit;
            display: flex; flex-direction: column; align-items: center; text-align: center;
            gap: 10px; transition: all .2s; cursor: pointer;
        }
        .action-card:hover {
            border-color: var(--blue); box-shadow: 0 4px 16px rgba(37,99,235,.1);
            transform: translateY(-2px); color: inherit;
        }
        .ac-ico {
            width: 52px; height: 52px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
        }
        .ac-blue   { background: var(--blue-bg);   color: var(--blue);   }
        .ac-green  { background: var(--green-bg);  color: var(--green);  }
        .ac-cyan   { background: var(--cyan-bg);   color: var(--cyan);   }
        .ac-amber  { background: var(--amber-bg);  color: var(--amber);  }

        .ac-title { font-size: .9rem; font-weight: 700; color: var(--t1); line-height: 1.2; }
        .ac-desc  { font-size: .78rem; color: var(--t4); line-height: 1.4; }

        /* ── Today summary ─────────────────────── */
        .today-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        @media (max-width: 700px) { .today-row { grid-template-columns: 1fr; } }

        .today-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--r); padding: 16px 18px;
            box-shadow: var(--sh); display: flex; align-items: center;
            justify-content: space-between; gap: 14px; overflow: hidden;
            position: relative;
        }
        .today-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
            border-radius: var(--r) var(--r) 0 0;
        }
        .tc-green::before  { background: var(--green); }
        .tc-red::before    { background: var(--red);   }
        .tc-cyan::before   { background: var(--cyan);  }
        .tc-negbal::before { background: var(--red);   }

        .today-lbl  { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--t4); margin-bottom: 4px; }
        .today-val  { font-size: 1.35rem; font-weight: 800; letter-spacing: -.02em; font-family: 'DM Mono', monospace; line-height: 1; }
        .tv-green   { color: var(--green); }
        .tv-red     { color: var(--red); }
        .tv-cyan    { color: var(--cyan); }

        .today-bg-ico {
            width: 44px; height: 44px; border-radius: 11px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 16px;
        }
        .tc-green  .today-bg-ico { background: var(--green-bg); color: var(--green); }
        .tc-red    .today-bg-ico { background: var(--red-bg);   color: var(--red);   }
        .tc-cyan   .today-bg-ico,
        .tc-negbal .today-bg-ico { background: var(--cyan-bg);  color: var(--cyan);  }

        /* ── Responsive ────────────────────────── */
        @media (max-width: 991.98px) {
            .page-shell { margin-left: 0; }
            .top-bar    { top: 52px; }
            .main       { padding: 14px 14px 50px; }
        }
    </style>
</head>
<body>

<div class="page-shell">

    <!-- Top Bar -->
    <header class="top-bar">
        <div class="tb-ico"><i class="fas fa-landmark"></i></div>
        <div>
            <div class="tb-title">Finance Dashboard</div>
            <div class="tb-sub">Multi-Branch Financial Management</div>
        </div>
        <div class="tb-right">
            <div class="date-chip">
                <i class="fas fa-calendar-days" style="font-size:.6rem;"></i>
                <?= date('d M Y') ?>
            </div>
        </div>
    </header>

    <div class="main">

        <!-- Overview stats -->
        <div>
            <div class="sec-label"><i class="fas fa-chart-simple"></i> Overview</div>
            <div class="stat-row">
                <div class="stat-card sc-blue">
                    <div class="stat-ico"><i class="fas fa-code-branch"></i></div>
                    <div>
                        <div class="stat-lbl">Total Branches</div>
                        <div class="stat-val"><?= $stats['total_branches'] ?></div>
                    </div>
                </div>
                <div class="stat-card sc-green">
                    <div class="stat-ico"><i class="fas fa-tags"></i></div>
                    <div>
                        <div class="stat-lbl">Income Categories</div>
                        <div class="stat-val"><?= $stats['total_income_categories'] ?></div>
                    </div>
                </div>
                <div class="stat-card sc-red">
                    <div class="stat-ico"><i class="fas fa-tags"></i></div>
                    <div>
                        <div class="stat-lbl">Expense Categories</div>
                        <div class="stat-val"><?= $stats['total_expense_categories'] ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick actions -->
        <div>
            <div class="sec-label"><i class="fas fa-bolt"></i> Quick Actions</div>
            <div class="action-grid <?= $userRole === 'admin' ? 'ag-admin' : 'ag-employee' ?>">

                <?php if ($userRole === 'admin'): ?>
                <a href="branches.php" class="action-card">
                    <div class="ac-ico ac-blue"><i class="fas fa-code-branch"></i></div>
                    <div class="ac-title">Branches &amp; Categories</div>
                    <div class="ac-desc">Manage branches and categories</div>
                </a>
                <?php endif; ?>

                <a href="income_expense.php" class="action-card">
                    <div class="ac-ico ac-green"><i class="fas fa-coins"></i></div>
                    <div class="ac-title">Record Transactions</div>
                    <div class="ac-desc">Add income &amp; expenses</div>
                </a>

                <?php if ($userRole === 'admin'): ?>
                <a href="transactions_list.php" class="action-card">
                    <div class="ac-ico ac-cyan"><i class="fas fa-calendar-days"></i></div>
                    <div class="ac-title">Date-wise Reports</div>
                    <div class="ac-desc">Daily transaction analysis</div>
                </a>

                <a href="view_report.php" class="action-card">
                    <div class="ac-ico ac-amber"><i class="fas fa-chart-line"></i></div>
                    <div class="ac-title">Monthly / Yearly Reports</div>
                    <div class="ac-desc">Comprehensive analytics</div>
                </a>
                <?php endif; ?>

            </div>
        </div>

        <!-- Today's summary -->
        <div>
            <div class="sec-label"><i class="fas fa-calendar-day"></i> Today's Summary</div>
            <div class="today-row">
                <div class="today-card tc-green">
                    <div>
                        <div class="today-lbl">Income</div>
                        <div class="today-val tv-green"><?= formatCurrency($stats['today_income']) ?></div>
                    </div>
                    <div class="today-bg-ico"><i class="fas fa-arrow-trend-up"></i></div>
                </div>
                <div class="today-card tc-red">
                    <div>
                        <div class="today-lbl">Expenses</div>
                        <div class="today-val tv-red"><?= formatCurrency($stats['today_expenses']) ?></div>
                    </div>
                    <div class="today-bg-ico"><i class="fas fa-arrow-trend-down"></i></div>
                </div>
                <div class="today-card <?= $netPositive ? 'tc-cyan' : 'tc-negbal' ?>">
                    <div>
                        <div class="today-lbl">Net Balance</div>
                        <div class="today-val <?= $netPositive ? 'tv-cyan' : 'tv-red' ?>">
                            <?= formatCurrency($netBalance) ?>
                        </div>
                    </div>
                    <div class="today-bg-ico"><i class="fas fa-wallet"></i></div>
                </div>
            </div>
        </div>

    </div><!-- /main -->
</div><!-- /page-shell -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>