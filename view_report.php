<?php
require 'auth.php';
include 'mydb.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php"); exit;
}

date_default_timezone_set('Asia/Dhaka');
include 'navbar.php';

function formatCurrency($amount) { return '৳' . number_format($amount, 2); }

$viewType      = in_array($_GET['view'] ?? '', ['monthly','yearly']) ? $_GET['view'] : 'monthly';
$selectedYear  = (int)($_GET['year']  ?? date('Y'));
$selectedMonth = (int)($_GET['month'] ?? date('m'));
if ($selectedYear  < 2000 || $selectedYear  > 2030) $selectedYear  = (int)date('Y');
if ($selectedMonth < 1    || $selectedMonth > 12)   $selectedMonth = (int)date('m');

$months = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',
           7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];

$branches = $expenseCategories = $incomeCategories = [];
$r = mysqli_query($conn, "SELECT id, name FROM branches ORDER BY name");
if ($r) while ($row = mysqli_fetch_assoc($r)) $branches[] = $row;
$r = mysqli_query($conn, "SELECT id, name FROM expense_categories ORDER BY name");
if ($r) while ($row = mysqli_fetch_assoc($r)) $expenseCategories[] = $row;
$r = mysqli_query($conn, "SELECT id, name FROM income_categories ORDER BY name");
if ($r) while ($row = mysqli_fetch_assoc($r)) $incomeCategories[] = $row;

$expenseData = $incomeMatrix = $summaryMatrix = [];
$monthlyIncomeData = $monthlyExpenseData = [];
$totalIncome = $totalExpenses = 0;

if ($viewType === 'monthly') {
    foreach ($expenseCategories as $cat) {
        $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(expense),0) t FROM branch_expenses WHERE category_id=? AND YEAR(created_at)=? AND MONTH(created_at)=?");
        mysqli_stmt_bind_param($stmt, "iii", $cat['id'], $selectedYear, $selectedMonth);
        mysqli_stmt_execute($stmt); $amt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['t']; mysqli_stmt_close($stmt);
        if ($amt > 0) { $expenseData[] = ['category'=>$cat['name'],'amount'=>$amt]; $totalExpenses += $amt; }
    }
    foreach ($incomeCategories as $cat) {
        $row = ['category'=>$cat['name'],'branches'=>[],'total'=>0];
        foreach ($branches as $b) {
            $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(income),0) t FROM branch_income WHERE branch_id=? AND category_id=? AND YEAR(created_at)=? AND MONTH(created_at)=?");
            mysqli_stmt_bind_param($stmt, "iiii", $b['id'], $cat['id'], $selectedYear, $selectedMonth);
            mysqli_stmt_execute($stmt); $amt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['t']; mysqli_stmt_close($stmt);
            $row['branches'][$b['id']] = $amt; $row['total'] += $amt;
        }
        if ($row['total'] > 0) $incomeMatrix[] = $row;
    }
    foreach ($branches as $b) {
        $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(income),0) t FROM branch_income WHERE branch_id=? AND YEAR(created_at)=? AND MONTH(created_at)=?");
        mysqli_stmt_bind_param($stmt, "iii", $b['id'], $selectedYear, $selectedMonth);
        mysqli_stmt_execute($stmt); $amt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['t']; mysqli_stmt_close($stmt);
        $summaryMatrix[] = ['branch'=>$b['name'],'income'=>$amt]; $totalIncome += $amt;
    }
} else {
    for ($m = 1; $m <= 12; $m++) {
        $row = ['month'=>$months[$m],'branches'=>[],'total'=>0];
        foreach ($branches as $b) {
            $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(income),0) t FROM branch_income WHERE branch_id=? AND YEAR(created_at)=? AND MONTH(created_at)=?");
            mysqli_stmt_bind_param($stmt, "iii", $b['id'], $selectedYear, $m);
            mysqli_stmt_execute($stmt); $amt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['t']; mysqli_stmt_close($stmt);
            $row['branches'][$b['id']] = $amt; $row['total'] += $amt;
        }
        $monthlyIncomeData[] = $row; $totalIncome += $row['total'];
    }
    for ($m = 1; $m <= 12; $m++) {
        $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(expense),0) t FROM branch_expenses WHERE YEAR(created_at)=? AND MONTH(created_at)=?");
        mysqli_stmt_bind_param($stmt, "ii", $selectedYear, $m);
        mysqli_stmt_execute($stmt); $amt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['t']; mysqli_stmt_close($stmt);
        $monthlyExpenseData[] = ['month'=>$months[$m],'amount'=>$amt]; $totalExpenses += $amt;
    }
    foreach ($incomeCategories as $cat) {
        $row = ['category'=>$cat['name'],'branches'=>[],'total'=>0];
        foreach ($branches as $b) {
            $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(income),0) t FROM branch_income WHERE branch_id=? AND category_id=? AND YEAR(created_at)=?");
            mysqli_stmt_bind_param($stmt, "iii", $b['id'], $cat['id'], $selectedYear);
            mysqli_stmt_execute($stmt); $amt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['t']; mysqli_stmt_close($stmt);
            $row['branches'][$b['id']] = $amt; $row['total'] += $amt;
        }
        if ($row['total'] > 0) $incomeMatrix[] = $row;
    }
    foreach ($expenseCategories as $cat) {
        $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(expense),0) t FROM branch_expenses WHERE category_id=? AND YEAR(created_at)=?");
        mysqli_stmt_bind_param($stmt, "ii", $cat['id'], $selectedYear);
        mysqli_stmt_execute($stmt); $amt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['t']; mysqli_stmt_close($stmt);
        if ($amt > 0) $expenseData[] = ['category'=>$cat['name'],'amount'=>$amt];
    }
    foreach ($branches as $b) {
        $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(income),0) t FROM branch_income WHERE branch_id=? AND YEAR(created_at)=?");
        mysqli_stmt_bind_param($stmt, "ii", $b['id'], $selectedYear);
        mysqli_stmt_execute($stmt); $amt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['t']; mysqli_stmt_close($stmt);
        $summaryMatrix[] = ['branch'=>$b['name'],'income'=>$amt];
    }
}
$totalMargin = $totalIncome - $totalExpenses;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Financial Reports &mdash; Rajaiswari</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root{--bg:#f1f3f6;--surface:#fff;--s2:#fafbfc;--border:#e4e7ec;--bsoft:#f0f1f3;--t1:#111827;--t2:#374151;--t3:#6b7280;--t4:#9ca3af;--blue:#2563eb;--blue-bg:#eff6ff;--blue-b:#bfdbfe;--green:#059669;--green-bg:#ecfdf5;--green-b:#a7f3d0;--amber:#d97706;--amber-bg:#fffbeb;--amber-b:#fde68a;--red:#dc2626;--red-bg:#fef2f2;--red-b:#fecaca;--cyan:#0891b2;--cyan-bg:#ecfeff;--cyan-b:#a5f3fc;--violet:#7c3aed;--violet-bg:#f5f3ff;--violet-b:#ddd6fe;--r:10px;--rs:6px;--sh:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);}
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'DM Sans',-apple-system,sans-serif;font-size:14px;background:var(--bg);color:var(--t1);-webkit-font-smoothing:antialiased;min-height:100vh;}
        .page-shell{margin-left:200px;min-height:100vh;display:flex;flex-direction:column;}
        .top-bar{position:sticky;top:0;z-index:200;height:54px;background:var(--surface);border-bottom:1px solid var(--border);box-shadow:var(--sh);display:flex;align-items:center;padding:0 22px;gap:12px;flex-shrink:0;}
        .tb-ico{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;background:var(--violet-bg);color:var(--violet);flex-shrink:0;}
        .tb-title{font-size:1.0625rem;font-weight:700;color:var(--t1);}
        .tb-sub{font-size:.78rem;color:var(--t4);}
        .tb-right{margin-left:auto;display:flex;gap:7px;align-items:center;}
        .btn-pos{display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 13px;border:none;border-radius:var(--rs);font-family:inherit;font-size:.8125rem;font-weight:600;cursor:pointer;transition:all .15s;text-decoration:none;white-space:nowrap;}
        .btn-ghost{background:var(--surface);color:var(--t2);border:1.5px solid var(--border);}
        .btn-ghost:hover{background:var(--s2);border-color:#9ca3af;color:var(--t1);}
        .btn-blue{background:var(--blue);color:#fff;border:none;}
        .btn-blue:hover{background:#1d4ed8;color:#fff;}
        .main{flex:1;padding:20px 22px 60px;display:flex;flex-direction:column;gap:16px;}
        .stat-row{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;}
        .stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);padding:16px 18px;display:flex;align-items:center;gap:14px;}
        .stat-card.sc-green{border-left:3px solid var(--green);}
        .stat-card.sc-red{border-left:3px solid var(--red);}
        .stat-card.sc-cyan{border-left:3px solid var(--cyan);}
        .sc-ico{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;}
        .sc-ico.green{background:var(--green-bg);color:var(--green);}
        .sc-ico.red{background:var(--red-bg);color:var(--red);}
        .sc-ico.cyan{background:var(--cyan-bg);color:var(--cyan);}
        .sc-lbl{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t4);margin-bottom:3px;}
        .sc-val{font-family:'DM Mono',monospace;font-size:1.1rem;font-weight:500;}
        .sc-val.green{color:var(--green);}
        .sc-val.red{color:var(--red);}
        .sc-val.cyan{color:var(--cyan);}
        .sec{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;}
        .sec-head{display:flex;align-items:center;gap:9px;padding:11px 18px;background:var(--s2);border-bottom:1px solid var(--bsoft);}
        .sec-ico{width:26px;height:26px;border-radius:var(--rs);display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;}
        .i-violet{background:var(--violet-bg);color:var(--violet);}
        .i-green{background:var(--green-bg);color:var(--green);}
        .i-red{background:var(--red-bg);color:var(--red);}
        .i-cyan{background:var(--cyan-bg);color:var(--cyan);}
        .sec-title{font-size:.875rem;font-weight:700;color:var(--t1);}
        .sec-body{padding:16px 18px;}
        .filter-row{display:flex;flex-wrap:wrap;align-items:flex-end;gap:12px;}
        .lbl{display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t3);margin-bottom:5px;}
        .fc{height:34px;padding:0 10px;border:1.5px solid var(--border);border-radius:var(--rs);font-family:inherit;font-size:.875rem;color:var(--t2);background:var(--surface);outline:none;transition:border-color .15s;}
        .fc:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
        select.fc{appearance:none;padding-right:28px;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%236b7280'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;}
        .view-toggle{display:flex;border:1.5px solid var(--border);border-radius:var(--rs);overflow:hidden;height:34px;}
        .vt-btn{display:inline-flex;align-items:center;gap:6px;padding:0 14px;font-family:inherit;font-size:.8125rem;font-weight:600;cursor:pointer;border:none;background:var(--surface);color:var(--t3);transition:all .15s;}
        .vt-btn:not(:last-child){border-right:1.5px solid var(--border);}
        .vt-btn.active{background:var(--blue);color:#fff;}
        .vt-btn:not(.active):hover{background:var(--s2);color:var(--t1);}
        .col-layout{display:grid;gap:16px;}
        .split-8-4{grid-template-columns:8fr 4fr;}
        .dt-wrap{overflow-x:auto;}
        table.dt{width:100%;border-collapse:collapse;font-size:.8375rem;}
        table.dt thead th{padding:8px 12px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--t4);background:var(--s2);border-bottom:1px solid var(--border);white-space:nowrap;}
        table.dt thead th:not(:first-child){text-align:right;}
        table.dt tbody td{padding:9px 12px;border-bottom:1px solid var(--bsoft);color:var(--t2);vertical-align:middle;}
        table.dt tbody td:not(:first-child){text-align:right;font-family:'DM Mono',monospace;font-size:.82rem;}
        table.dt tbody tr:last-child td{border-bottom:none;}
        table.dt tbody tr:hover td{background:var(--s2);}
        table.dt tfoot td{padding:9px 12px;border-top:1.5px solid var(--border);background:var(--s2);font-weight:700;font-size:.82rem;}
        table.dt tfoot td:not(:first-child){text-align:right;font-family:'DM Mono',monospace;}
        .amt-income{color:var(--green);}
        .amt-expense{color:var(--red);}
        .amt-cyan{color:var(--cyan);}
        .amt-muted{color:var(--t4);}
        .dot{display:inline-block;width:6px;height:6px;border-radius:50%;margin-right:5px;vertical-align:middle;}
        .dot.gr{background:var(--green);}
        .dot.rd{background:var(--red);}
        .row-net{background:var(--amber-bg) !important;}
        .row-net td{border-top:1.5px solid var(--amber-b) !important;}
        .center-note{text-align:center !important;font-size:.78rem;color:var(--t4);font-style:italic;font-family:inherit !important;}
        .empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 20px;gap:10px;color:var(--t4);}
        .empty-state i{font-size:2.2rem;}
        .empty-state p{font-size:.875rem;}
        @media(max-width:991.98px){.page-shell{margin-left:0;}.top-bar{top:52px;}.main{padding:14px 14px 50px;}.stat-row{grid-template-columns:1fr 1fr;}.split-8-4{grid-template-columns:1fr;}}
        @media(max-width:560px){.stat-row{grid-template-columns:1fr;}}
    </style>
</head>
<body>
<div class="page-shell">

    <header class="top-bar">
        <div class="tb-ico"><i class="fas fa-chart-line"></i></div>
        <div>
            <div class="tb-title">Financial Reports</div>
            <div class="tb-sub">
                <?php if ($viewType === 'monthly'): ?><?= $months[$selectedMonth] ?> <?= $selectedYear ?> &mdash; monthly breakdown
                <?php else: ?>Full year <?= $selectedYear ?> &mdash; annual overview<?php endif; ?>
            </div>
        </div>
        <div class="tb-right">
            <a href="transactions_list.php" class="btn-pos btn-ghost"><i class="fas fa-list" style="font-size:.6rem;"></i> Transactions</a>
            <a href="account.php" class="btn-pos btn-ghost"><i class="fas fa-arrow-left" style="font-size:.6rem;"></i> Finance Panel</a>
        </div>
    </header>

    <div class="main">

        <div class="sec">
            <div class="sec-head"><span class="sec-ico i-violet"><i class="fas fa-sliders"></i></span><span class="sec-title">Report Filters</span></div>
            <div class="sec-body">
                <form method="GET" id="reportForm">
                    <input type="hidden" name="view" id="viewInput" value="<?= htmlspecialchars($viewType) ?>">
                    <div class="filter-row">
                        <div>
                            <div class="lbl">View Type</div>
                            <div class="view-toggle">
                                <button type="button" class="vt-btn <?= $viewType==='monthly'?'active':'' ?>" onclick="setView('monthly')"><i class="fas fa-calendar-day"></i> Monthly</button>
                                <button type="button" class="vt-btn <?= $viewType==='yearly'?'active':'' ?>" onclick="setView('yearly')"><i class="fas fa-calendar-alt"></i> Yearly</button>
                            </div>
                        </div>
                        <div>
                            <label class="lbl">Year</label>
                            <select name="year" class="fc" style="width:110px;">
                                <?php for ($y=2020;$y<=2030;$y++): ?><option value="<?= $y ?>" <?= $selectedYear==$y?'selected':'' ?>><?= $y ?></option><?php endfor; ?>
                            </select>
                        </div>
                        <?php if ($viewType === 'monthly'): ?>
                        <div>
                            <label class="lbl">Month</label>
                            <select name="month" class="fc" style="width:140px;">
                                <?php foreach ($months as $n=>$name): ?><option value="<?= $n ?>" <?= $selectedMonth==$n?'selected':'' ?>><?= $name ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div style="padding-bottom:1px;">
                            <button type="submit" id="genBtn" class="btn-pos btn-blue" style="height:34px;padding:0 16px;">
                                <i class="fas fa-chart-bar" style="font-size:.7rem;"></i> Generate Report
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="stat-row">
            <div class="stat-card sc-green">
                <div class="sc-ico green"><i class="fas fa-arrow-trend-up"></i></div>
                <div><div class="sc-lbl">Total Income</div><div class="sc-val green"><?= formatCurrency($totalIncome) ?></div></div>
            </div>
            <div class="stat-card sc-red">
                <div class="sc-ico red"><i class="fas fa-arrow-trend-down"></i></div>
                <div><div class="sc-lbl">Total Expenses</div><div class="sc-val red"><?= formatCurrency($totalExpenses) ?></div></div>
            </div>
            <div class="stat-card <?= $totalMargin>=0?'sc-cyan':'sc-red' ?>">
                <div class="sc-ico <?= $totalMargin>=0?'cyan':'red' ?>"><i class="fas fa-scale-balanced"></i></div>
                <div><div class="sc-lbl">Net Margin</div><div class="sc-val <?= $totalMargin>=0?'cyan':'red' ?>"><?= ($totalMargin<0?'&minus;':'').formatCurrency(abs($totalMargin)) ?></div></div>
            </div>
        </div>

        <?php if (empty($expenseData) && empty($incomeMatrix) && empty($monthlyIncomeData)): ?>
        <div class="sec"><div class="empty-state"><i class="fas fa-chart-pie"></i><p>No transactions found for the selected period.</p></div></div>

        <?php elseif ($viewType === 'monthly'): ?>

        <div class="col-layout split-8-4">
            <div class="sec">
                <div class="sec-head"><span class="sec-ico i-green"><i class="fas fa-arrow-trend-up"></i></span><span class="sec-title">Monthly Income by Branch</span></div>
                <?php if (empty($incomeMatrix)): ?>
                <div class="empty-state" style="padding:36px 20px;"><i class="fas fa-inbox"></i><p>No income recorded</p></div>
                <?php else: ?>
                <div class="dt-wrap"><table class="dt">
                    <thead><tr><th>Category</th><?php foreach($branches as $b):?><th><?=htmlspecialchars($b['name'])?></th><?php endforeach;?><th>Total</th></tr></thead>
                    <tbody><?php foreach($incomeMatrix as $row):?><tr>
                        <td style="font-weight:600;"><span class="dot gr"></span><?=htmlspecialchars($row['category'])?></td>
                        <?php foreach($branches as $b):?><td class="<?=($row['branches'][$b['id']]??0)>0?'amt-income':'amt-muted'?>"><?=($row['branches'][$b['id']]??0)>0?formatCurrency($row['branches'][$b['id']]):'&mdash;'?></td><?php endforeach;?>
                        <td class="amt-income" style="font-weight:700;"><?=formatCurrency($row['total'])?></td>
                    </tr><?php endforeach;?></tbody>
                    <tfoot><tr><td>Total</td><?php foreach($branches as $b):$bt=0;foreach($incomeMatrix as $r)$bt+=$r['branches'][$b['id']]??0;?><td class="amt-income"><?=formatCurrency($bt)?></td><?php endforeach;?><td class="amt-income"><?=formatCurrency($totalIncome)?></td></tr></tfoot>
                </table></div>
                <?php endif;?>
            </div>
            <div class="sec">
                <div class="sec-head"><span class="sec-ico i-red"><i class="fas fa-arrow-trend-down"></i></span><span class="sec-title">Monthly Expenses</span></div>
                <?php if (empty($expenseData)): ?>
                <div class="empty-state" style="padding:36px 20px;"><i class="fas fa-inbox"></i><p>No expenses recorded</p></div>
                <?php else: ?>
                <div class="dt-wrap"><table class="dt">
                    <thead><tr><th>Category</th><th>Amount</th></tr></thead>
                    <tbody><?php foreach($expenseData as $item):?><tr><td><span class="dot rd"></span><?=htmlspecialchars($item['category'])?></td><td class="amt-expense"><?=formatCurrency($item['amount'])?></td></tr><?php endforeach;?></tbody>
                    <tfoot><tr><td>Total</td><td class="amt-expense"><?=formatCurrency($totalExpenses)?></td></tr></tfoot>
                </table></div>
                <?php endif;?>
            </div>
        </div>

        <div class="sec">
            <div class="sec-head"><span class="sec-ico i-cyan"><i class="fas fa-scale-balanced"></i></span><span class="sec-title">Summary Matrix &mdash; <?=$months[$selectedMonth]?> <?=$selectedYear?></span></div>
            <div class="dt-wrap"><table class="dt">
                <thead><tr><th>Type</th><?php foreach($summaryMatrix as $item):?><th><?=htmlspecialchars($item['branch'])?></th><?php endforeach;?><th>Total</th></tr></thead>
                <tbody>
                <tr><td style="font-weight:700;"><span class="dot gr"></span>Income</td><?php foreach($summaryMatrix as $item):?><td class="amt-income"><?=formatCurrency($item['income'])?></td><?php endforeach;?><td class="amt-income" style="font-weight:700;"><?=formatCurrency($totalIncome)?></td></tr>
                <tr><td style="font-weight:700;"><span class="dot rd"></span>Expenses</td><td colspan="<?=count($summaryMatrix)?>" class="center-note">Company-wide &mdash; not allocated to branches</td><td class="amt-expense" style="font-weight:700;"><?=formatCurrency($totalExpenses)?></td></tr>
                <tr class="row-net"><td style="font-weight:700;">Net Margin</td><td colspan="<?=count($summaryMatrix)?>" class="center-note">Total Income &minus; Total Expenses</td><td style="font-weight:700;" class="<?=$totalMargin>=0?'amt-cyan':'amt-expense'?>"><?=($totalMargin<0?'&minus;':'').formatCurrency(abs($totalMargin))?></td></tr>
                </tbody>
            </table></div>
        </div>

        <?php else: ?>

        <div class="col-layout split-8-4">
            <div class="sec">
                <div class="sec-head"><span class="sec-ico i-green"><i class="fas fa-arrow-trend-up"></i></span><span class="sec-title">Monthly Income by Branch &mdash; <?=$selectedYear?></span></div>
                <div class="dt-wrap"><table class="dt">
                    <thead><tr><th>Month</th><?php foreach($branches as $b):?><th><?=htmlspecialchars($b['name'])?></th><?php endforeach;?><th>Total</th></tr></thead>
                    <tbody><?php foreach($monthlyIncomeData as $row):?><tr>
                        <td style="font-weight:600;"><?=$row['month']?></td>
                        <?php foreach($branches as $b):?><td class="<?=($row['branches'][$b['id']]??0)>0?'amt-income':'amt-muted'?>"><?=($row['branches'][$b['id']]??0)>0?formatCurrency($row['branches'][$b['id']]):'&mdash;'?></td><?php endforeach;?>
                        <td class="<?=$row['total']>0?'amt-income':'amt-muted'?>" style="font-weight:700;"><?=$row['total']>0?formatCurrency($row['total']):'&mdash;'?></td>
                    </tr><?php endforeach;?></tbody>
                    <tfoot><tr><td>Yearly Total</td><?php foreach($branches as $b):$byt=0;foreach($monthlyIncomeData as $r)$byt+=$r['branches'][$b['id']]??0;?><td class="amt-income"><?=formatCurrency($byt)?></td><?php endforeach;?><td class="amt-income"><?=formatCurrency($totalIncome)?></td></tr></tfoot>
                </table></div>
            </div>
            <div class="sec">
                <div class="sec-head"><span class="sec-ico i-red"><i class="fas fa-arrow-trend-down"></i></span><span class="sec-title">Monthly Expenses &mdash; <?=$selectedYear?></span></div>
                <div class="dt-wrap"><table class="dt">
                    <thead><tr><th>Month</th><th>Amount</th></tr></thead>
                    <tbody><?php foreach($monthlyExpenseData as $row):?><tr><td><?=$row['month']?></td><td class="<?=$row['amount']>0?'amt-expense':'amt-muted'?>"><?=$row['amount']>0?formatCurrency($row['amount']):'&mdash;'?></td></tr><?php endforeach;?></tbody>
                    <tfoot><tr><td>Yearly Total</td><td class="amt-expense"><?=formatCurrency($totalExpenses)?></td></tr></tfoot>
                </table></div>
            </div>
        </div>

        <?php if (!empty($incomeMatrix) || !empty($expenseData)): ?>
        <div class="col-layout split-8-4">
            <?php if (!empty($incomeMatrix)): ?>
            <div class="sec">
                <div class="sec-head"><span class="sec-ico i-green"><i class="fas fa-tags"></i></span><span class="sec-title">Income Categories &mdash; Annual</span></div>
                <div class="dt-wrap"><table class="dt">
                    <thead><tr><th>Category</th><?php foreach($branches as $b):?><th><?=htmlspecialchars($b['name'])?></th><?php endforeach;?><th>Total</th></tr></thead>
                    <tbody><?php foreach($incomeMatrix as $row):?><tr>
                        <td style="font-weight:600;"><span class="dot gr"></span><?=htmlspecialchars($row['category'])?></td>
                        <?php foreach($branches as $b):?><td class="<?=($row['branches'][$b['id']]??0)>0?'amt-income':'amt-muted'?>"><?=($row['branches'][$b['id']]??0)>0?formatCurrency($row['branches'][$b['id']]):'&mdash;'?></td><?php endforeach;?>
                        <td class="amt-income" style="font-weight:700;"><?=formatCurrency($row['total'])?></td>
                    </tr><?php endforeach;?></tbody>
                    <tfoot><tr><td>Total</td><?php foreach($branches as $b):$bt=0;foreach($incomeMatrix as $r)$bt+=$r['branches'][$b['id']]??0;?><td class="amt-income"><?=formatCurrency($bt)?></td><?php endforeach;?><td class="amt-income"><?=formatCurrency(array_sum(array_column($incomeMatrix,'total')))?></td></tr></tfoot>
                </table></div>
            </div>
            <?php endif;?>
            <?php if (!empty($expenseData)): ?>
            <div class="sec">
                <div class="sec-head"><span class="sec-ico i-red"><i class="fas fa-tags"></i></span><span class="sec-title">Expense Categories &mdash; Annual</span></div>
                <div class="dt-wrap"><table class="dt">
                    <thead><tr><th>Category</th><th>Amount</th></tr></thead>
                    <tbody><?php foreach($expenseData as $item):?><tr><td><span class="dot rd"></span><?=htmlspecialchars($item['category'])?></td><td class="amt-expense"><?=formatCurrency($item['amount'])?></td></tr><?php endforeach;?></tbody>
                    <tfoot><tr><td>Total</td><td class="amt-expense"><?=formatCurrency(array_sum(array_column($expenseData,'amount')))?></td></tr></tfoot>
                </table></div>
            </div>
            <?php endif;?>
        </div>
        <?php endif;?>

        <div class="sec">
            <div class="sec-head"><span class="sec-ico i-cyan"><i class="fas fa-scale-balanced"></i></span><span class="sec-title">Yearly Summary Matrix &mdash; <?=$selectedYear?></span></div>
            <div class="dt-wrap"><table class="dt">
                <thead><tr><th>Type</th><?php foreach($summaryMatrix as $item):?><th><?=htmlspecialchars($item['branch'])?></th><?php endforeach;?><th>Total</th></tr></thead>
                <tbody>
                <tr><td style="font-weight:700;"><span class="dot gr"></span>Income</td><?php foreach($summaryMatrix as $item):?><td class="amt-income"><?=formatCurrency($item['income'])?></td><?php endforeach;?><td class="amt-income" style="font-weight:700;"><?=formatCurrency($totalIncome)?></td></tr>
                <tr><td style="font-weight:700;"><span class="dot rd"></span>Expenses</td><td colspan="<?=count($summaryMatrix)?>" class="center-note">Company-wide &mdash; not allocated to branches</td><td class="amt-expense" style="font-weight:700;"><?=formatCurrency($totalExpenses)?></td></tr>
                <tr class="row-net"><td style="font-weight:700;">Net Margin</td><td colspan="<?=count($summaryMatrix)?>" class="center-note">Total Income &minus; Total Expenses</td><td style="font-weight:700;" class="<?=$totalMargin>=0?'amt-cyan':'amt-expense'?>"><?=($totalMargin<0?'&minus;':'').formatCurrency(abs($totalMargin))?></td></tr>
                </tbody>
            </table></div>
        </div>

        <?php endif;?>

    </div>
</div>
<script>
function setView(v){document.getElementById('viewInput').value=v;document.getElementById('reportForm').submit();}
document.getElementById('reportForm').addEventListener('submit',function(){
    const b=document.getElementById('genBtn');
    b.innerHTML='<i class="fas fa-spinner fa-spin" style="font-size:.7rem;"></i> Loading\u2026';b.disabled=true;
});
</script>
</body>
</html>