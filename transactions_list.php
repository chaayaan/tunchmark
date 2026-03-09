<?php
require 'auth.php';
include 'mydb.php';
include 'navbar.php';

date_default_timezone_set('Asia/Dhaka');

$filterType     = $_GET['type']      ?? 'all';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo   = $_GET['date_to']   ?? '';
$page           = max(1, intval($_GET['page'] ?? 1));
$perPage        = 50;
$offset         = ($page - 1) * $perPage;

// Build UNION query
$incomeQ = "SELECT bi.id, 'income' as type, bi.created_at as date, ic.name as category,
             b.name as branch, bi.income as amount, bi.notes
             FROM branch_income bi
             LEFT JOIN income_categories ic ON bi.category_id = ic.id
             LEFT JOIN branches b ON bi.branch_id = b.id WHERE 1=1";
$expenseQ = "SELECT be.id, 'expense' as type, be.created_at as date, ec.name as category,
              'N/A' as branch, be.expense as amount, be.notes
              FROM branch_expenses be
              LEFT JOIN expense_categories ec ON be.category_id = ec.id WHERE 1=1";

$dateWhere = '';
if (!empty($filterDateFrom)) $dateWhere .= " AND DATE(created_at) >= '" . mysqli_real_escape_string($conn, $filterDateFrom) . "'";
if (!empty($filterDateTo))   $dateWhere .= " AND DATE(created_at) <= '" . mysqli_real_escape_string($conn, $filterDateTo) . "'";

$incomeQ  .= str_replace('created_at', 'bi.created_at', $dateWhere);
$expenseQ .= str_replace('created_at', 'be.created_at', $dateWhere);

$unionParts = [];
if ($filterType === 'all' || $filterType === 'income')   $unionParts[] = $incomeQ;
if ($filterType === 'all' || $filterType === 'expenses') $unionParts[] = $expenseQ;
$unionSQL = implode(' UNION ALL ', $unionParts);

$transactions = [];
$totalRecords = 0;
if ($unionSQL) {
    $r = mysqli_query($conn, "SELECT * FROM ($unionSQL) t ORDER BY date DESC LIMIT $perPage OFFSET $offset");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $transactions[] = $row;
    $cr = mysqli_query($conn, "SELECT COUNT(*) total FROM ($unionSQL) t");
    if ($cr) $totalRecords = mysqli_fetch_assoc($cr)['total'];
}
$totalPages = max(1, ceil($totalRecords / $perPage));

// Totals
$totalIncome = $totalExpenses = 0;
$iWhere = !empty($filterDateFrom) ? " AND DATE(created_at) >= '" . mysqli_real_escape_string($conn, $filterDateFrom) . "'" : '';
$iWhere .= !empty($filterDateTo)  ? " AND DATE(created_at) <= '" . mysqli_real_escape_string($conn, $filterDateTo)   . "'" : '';
if ($filterType !== 'expenses') {
    $ir = mysqli_query($conn, "SELECT COALESCE(SUM(income),0) t FROM branch_income WHERE 1=1$iWhere");
    if ($ir) $totalIncome = mysqli_fetch_assoc($ir)['t'];
}
if ($filterType !== 'income') {
    $er = mysqli_query($conn, "SELECT COALESCE(SUM(expense),0) t FROM branch_expenses WHERE 1=1$iWhere");
    if ($er) $totalExpenses = mysqli_fetch_assoc($er)['t'];
}
$netBalance = $totalIncome - $totalExpenses;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>All Transactions — Rajaiswari</title>
  <link rel="icon" type="image/png" href="favicon.png">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg:#f1f3f6; --surface:#fff; --surface-2:#fafbfc;
      --border:#e4e7ec; --bsoft:#f0f1f3;
      --t1:#111827; --t2:#374151; --t3:#6b7280; --t4:#9ca3af;
      --blue:#2563eb;  --blue-bg:#eff6ff;  --blue-b:#bfdbfe;
      --green:#059669; --green-bg:#ecfdf5; --green-b:#a7f3d0;
      --amber:#d97706; --amber-bg:#fffbeb; --amber-b:#fde68a;
      --red:#dc2626;   --red-bg:#fef2f2;   --red-b:#fecaca;
      --cyan:#0891b2;  --cyan-bg:#ecfeff;  --cyan-b:#a5f3fc;
      --r:10px; --rs:6px;
      --sh:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'DM Sans',-apple-system,sans-serif;font-size:14.5px;background:var(--bg);color:var(--t1);-webkit-font-smoothing:antialiased;min-height:100vh;}
    .page-shell{margin-left:200px;min-height:100vh;display:flex;flex-direction:column;}
    .top-bar{position:sticky;top:0;z-index:200;height:54px;background:var(--surface);border-bottom:1px solid var(--border);box-shadow:var(--sh);display:flex;align-items:center;padding:0 22px;gap:10px;flex-shrink:0;}
    .tb-ico{width:32px;height:32px;background:var(--blue-bg);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--blue);font-size:13px;flex-shrink:0;}
    .tb-title{font-size:1.0625rem;font-weight:700;color:var(--t1);line-height:1.2;}
    .tb-sub{font-size:.8rem;color:var(--t4);}
    .tb-right{margin-left:auto;}
    .tb-btn{display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 14px;background:var(--green);border:none;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.82rem;font-weight:700;color:#fff;text-decoration:none;cursor:pointer;transition:background .15s;}
    .tb-btn:hover{background:#047857;color:#fff;}
    .main{flex:1;padding:20px 22px 60px;display:flex;flex-direction:column;gap:14px;}

    /* Stat cards */
    .stat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;}
    .stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);padding:15px 18px;border-left:3px solid;display:flex;align-items:center;justify-content:space-between;}
    .sc-green{border-left-color:var(--green);}
    .sc-red  {border-left-color:var(--red);}
    .sc-cyan {border-left-color:var(--cyan);}
    .sc-neg  {border-left-color:var(--red);}
    .sc-label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t4);margin-bottom:3px;}
    .sc-val{font-family:'DM Mono',monospace;font-size:1.3rem;font-weight:700;}
    .sc-val-green{color:var(--green);}
    .sc-val-red  {color:var(--red);}
    .sc-val-cyan {color:var(--cyan);}
    .sc-icon{font-size:1.6rem;opacity:.12;}

    /* Section card */
    .sec{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;}
    .sec-head{display:flex;align-items:center;gap:9px;padding:12px 18px;background:var(--surface-2);border-bottom:1px solid var(--bsoft);}
    .sec-ico{width:28px;height:28px;border-radius:var(--rs);display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;}
    .i-blue{background:var(--blue-bg);color:var(--blue);}
    .sec-title{font-size:.9375rem;font-weight:700;color:var(--t1);}
    .sec-meta{margin-left:auto;font-size:.78rem;color:var(--t4);}

    /* Filter row */
    .filter-row{display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;padding:14px 18px;background:var(--surface-2);border-bottom:1px solid var(--bsoft);}
    .filter-group{display:flex;flex-direction:column;gap:4px;}
    .lbl{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t4);}
    .fc{height:36px;padding:0 10px;border:1.5px solid var(--border);border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.875rem;color:var(--t2);background:var(--surface);outline:none;transition:border-color .15s;}
    .fc:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
    select.fc{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%239ca3af' d='M1 1l5 5 5-5'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 9px center;padding-right:26px;}
    .btn-apply{display:inline-flex;align-items:center;gap:5px;height:36px;padding:0 16px;background:var(--blue);border:none;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.875rem;font-weight:700;color:#fff;cursor:pointer;white-space:nowrap;transition:background .15s;}
    .btn-apply:hover{background:#1d4ed8;}
    .btn-reset{display:inline-flex;align-items:center;gap:5px;height:36px;padding:0 14px;background:var(--surface-2);border:1.5px solid var(--border);border-radius:var(--rs);font-size:.82rem;font-weight:600;color:var(--t2);text-decoration:none;white-space:nowrap;transition:all .15s;}
    .btn-reset:hover{background:var(--border);color:var(--t1);}

    /* Active filter chips */
    .filter-chips{display:flex;gap:6px;flex-wrap:wrap;padding:10px 18px;background:var(--surface-2);border-bottom:1px solid var(--bsoft);}
    .chip{display:inline-flex;align-items:center;gap:5px;background:var(--blue-bg);border:1px solid var(--blue-b);border-radius:20px;padding:2px 10px;font-size:.75rem;font-weight:600;color:var(--blue);}
    .chip a{color:var(--blue);text-decoration:none;margin-left:3px;opacity:.7;}
    .chip a:hover{opacity:1;}

    /* Table */
    .txn-tbl{width:100%;border-collapse:collapse;min-width:720px;}
    .txn-tbl thead th{padding:10px 14px;text-align:left;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--t4);background:var(--surface-2);border-bottom:1px solid var(--border);white-space:nowrap;}
    .txn-tbl tbody td{padding:11px 14px;border-bottom:1px solid var(--bsoft);vertical-align:middle;font-size:.875rem;}
    .txn-tbl tbody tr:last-child td{border-bottom:none;}
    .txn-tbl tbody tr.row-income:hover td {background:rgba(5,150,105,.04);}
    .txn-tbl tbody tr.row-expense:hover td{background:rgba(220,38,38,.04);}
    .row-income td:first-child{border-left:3px solid var(--green);}
    .row-expense td:first-child{border-left:3px solid var(--red);}

    .type-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
    .type-pill::before{content:'';width:5px;height:5px;border-radius:50%;}
    .pill-income {background:var(--green-bg);color:var(--green);border:1px solid var(--green-b);}
    .pill-income::before{background:var(--green);}
    .pill-expense{background:var(--red-bg);  color:var(--red);  border:1px solid var(--red-b);}
    .pill-expense::before{background:var(--red);}

    .date-mono{font-family:'DM Mono',monospace;font-size:.78rem;color:var(--t3);}
    .date-time{font-family:'DM Mono',monospace;font-size:.68rem;color:var(--t4);}
    .amount-income {font-family:'DM Mono',monospace;font-weight:700;color:var(--green);}
    .amount-expense{font-family:'DM Mono',monospace;font-weight:700;color:var(--red);}
    .branch-tag{font-size:.75rem;color:var(--t3);}
    .no-branch{font-size:.75rem;color:var(--t4);font-style:italic;}
    .id-tag{font-family:'DM Mono',monospace;font-size:.72rem;color:var(--t4);background:var(--surface-2);border:1px solid var(--border);border-radius:4px;padding:1px 6px;}

    /* Empty state */
    .empty-state{padding:52px;text-align:center;}
    .empty-ico{font-size:2.5rem;color:var(--border);margin-bottom:10px;}
    .empty-title{font-size:.9rem;font-weight:700;color:var(--t3);margin-bottom:4px;}

    /* Totals footer row */
    .totals-row td{padding:11px 14px;background:var(--surface-2);border-top:2px solid var(--border);font-weight:700;font-family:'DM Mono',monospace;font-size:.82rem;}
    .totals-label{font-family:'DM Sans',sans-serif;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--t3);}

    /* Pagination */
    .pag-wrap{display:flex;align-items:center;justify-content:center;gap:4px;padding:14px 18px;background:var(--surface-2);border-top:1px solid var(--border);}
    .pag-btn{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 8px;background:var(--surface);border:1.5px solid var(--border);border-radius:var(--rs);font-family:'DM Mono',monospace;font-size:.82rem;font-weight:600;color:var(--t2);text-decoration:none;transition:all .15s;}
    .pag-btn:hover:not(.pag-disabled):not(.pag-active){background:var(--surface-2);border-color:var(--t4);color:var(--t1);}
    .pag-active{background:var(--blue);border-color:var(--blue);color:#fff;}
    .pag-disabled{opacity:.4;pointer-events:none;}
    .pag-dots{color:var(--t4);padding:0 2px;font-size:.82rem;}

    @media(max-width:991.98px){.page-shell{margin-left:0;}.top-bar{top:52px;}.main{padding:14px 14px 50px;}.stat-grid{grid-template-columns:1fr 1fr;}}
    @media(max-width:575px){.stat-grid{grid-template-columns:1fr;}}
  </style>
</head>
<body>

<div class="page-shell">
  <header class="top-bar">
    <div class="tb-ico"><i class="fas fa-list-ul"></i></div>
    <div>
      <div class="tb-title">All Transactions</div>
      <div class="tb-sub">Income & expense history</div>
    </div>
    <div class="tb-right">
      <a href="income_expense.php" class="tb-btn">
        <i class="fas fa-plus" style="font-size:.7rem;"></i> New Entry
      </a>
    </div>
  </header>

  <div class="main">

    <!-- Stat cards -->
    <div class="stat-grid">
      <div class="stat-card sc-green">
        <div>
          <div class="sc-label">Total Income</div>
          <div class="sc-val sc-val-green">৳<?= number_format($totalIncome, 2) ?></div>
        </div>
        <div class="sc-icon"><i class="fas fa-arrow-down-to-bracket"></i></div>
      </div>
      <div class="stat-card sc-red">
        <div>
          <div class="sc-label">Total Expenses</div>
          <div class="sc-val sc-val-red">৳<?= number_format($totalExpenses, 2) ?></div>
        </div>
        <div class="sc-icon"><i class="fas fa-arrow-up-from-bracket"></i></div>
      </div>
      <div class="stat-card <?= $netBalance >= 0 ? 'sc-cyan' : 'sc-neg' ?>">
        <div>
          <div class="sc-label">Net Balance</div>
          <div class="sc-val <?= $netBalance >= 0 ? 'sc-val-cyan' : 'sc-val-red' ?>">
            <?= $netBalance < 0 ? '−' : '' ?>৳<?= number_format(abs($netBalance), 2) ?>
          </div>
        </div>
        <div class="sc-icon"><i class="fas fa-wallet"></i></div>
      </div>
    </div>

    <!-- Table card -->
    <div class="sec">
      <div class="sec-head">
        <span class="sec-ico i-blue"><i class="fas fa-table-list"></i></span>
        <span class="sec-title">Transaction Records</span>
        <span class="sec-meta"><?= number_format($totalRecords) ?> record<?= $totalRecords !== 1 ? 's' : '' ?> · Page <?= $page ?>/<?= $totalPages ?></span>
      </div>

      <!-- Filters -->
      <form method="GET" id="filterForm">
        <div class="filter-row">
          <div class="filter-group">
            <span class="lbl">Type</span>
            <select name="type" class="fc">
              <option value="all"     <?= $filterType === 'all'      ? 'selected' : '' ?>>All Types</option>
              <option value="income"  <?= $filterType === 'income'   ? 'selected' : '' ?>>Income</option>
              <option value="expenses"<?= $filterType === 'expenses' ? 'selected' : '' ?>>Expenses</option>
            </select>
          </div>
          <div class="filter-group">
            <span class="lbl">From</span>
            <input type="date" name="date_from" class="fc" value="<?= htmlspecialchars($filterDateFrom) ?>">
          </div>
          <div class="filter-group">
            <span class="lbl">To</span>
            <input type="date" name="date_to" class="fc" value="<?= htmlspecialchars($filterDateTo) ?>">
          </div>
          <div style="display:flex;gap:6px;align-items:flex-end;">
            <button type="submit" class="btn-apply">
              <i class="fas fa-magnifying-glass" style="font-size:.7rem;"></i> Apply
            </button>
            <a href="transactions_list.php" class="btn-reset">
              <i class="fas fa-rotate-right" style="font-size:.7rem;"></i> Reset
            </a>
          </div>
        </div>
      </form>

      <!-- Active chips -->
      <?php if ($filterType !== 'all' || $filterDateFrom || $filterDateTo): ?>
      <div class="filter-chips">
        <?php
          $params = array_filter(['type' => $filterType, 'date_from' => $filterDateFrom, 'date_to' => $filterDateTo]);
          if ($filterType !== 'all'):
            $rest = $params; unset($rest['type']);
        ?>
        <span class="chip">Type: <?= htmlspecialchars($filterType) ?> <a href="?<?= http_build_query($rest) ?>">×</a></span>
        <?php endif; if ($filterDateFrom):
            $rest = $params; unset($rest['date_from']); ?>
        <span class="chip">From: <?= htmlspecialchars($filterDateFrom) ?> <a href="?<?= http_build_query($rest) ?>">×</a></span>
        <?php endif; if ($filterDateTo):
            $rest = $params; unset($rest['date_to']); ?>
        <span class="chip">To: <?= htmlspecialchars($filterDateTo) ?> <a href="?<?= http_build_query($rest) ?>">×</a></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Table -->
      <div style="overflow-x:auto;">
        <table class="txn-tbl">
          <thead>
            <tr>
              <th style="width:60px;">ID</th>
              <th style="width:95px;">Type</th>
              <th style="width:130px;">Date</th>
              <th>Category</th>
              <th style="width:130px;">Branch</th>
              <th style="width:130px;text-align:right;">Amount</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($transactions)): ?>
          <tr><td colspan="7">
            <div class="empty-state">
              <div class="empty-ico"><i class="fas fa-inbox"></i></div>
              <div class="empty-title">No transactions found</div>
            </div>
          </td></tr>
          <?php else: foreach ($transactions as $t): ?>
          <tr class="row-<?= $t['type'] ?>">
            <td><span class="id-tag">#<?= $t['id'] ?></span></td>
            <td>
              <span class="type-pill pill-<?= $t['type'] ?>">
                <?= $t['type'] === 'income' ? 'Income' : 'Expense' ?>
              </span>
            </td>
            <td>
              <div class="date-mono"><?= date('d M Y', strtotime($t['date'])) ?></div>
              <div class="date-time"><?= date('h:i A', strtotime($t['date'])) ?></div>
            </td>
            <td style="font-weight:600;"><?= htmlspecialchars($t['category']) ?></td>
            <td>
              <?php if ($t['branch'] !== 'N/A'): ?>
                <span class="branch-tag"><i class="fas fa-building" style="font-size:.6rem;margin-right:4px;color:var(--t4);"></i><?= htmlspecialchars($t['branch']) ?></span>
              <?php else: ?>
                <span class="no-branch">Company-wide</span>
              <?php endif; ?>
            </td>
            <td style="text-align:right;">
              <span class="amount-<?= $t['type'] ?>">
                <?= $t['type'] === 'expense' ? '−' : '+' ?>৳<?= number_format($t['amount'], 2) ?>
              </span>
            </td>
            <td style="color:var(--t3);font-size:.82rem;">
              <?= !empty($t['notes']) ? htmlspecialchars($t['notes']) : '<span style="color:var(--t4);font-style:italic;">—</span>' ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
          </tbody>
          <?php if (!empty($transactions)): ?>
          <tfoot>
            <tr class="totals-row">
              <td colspan="5"><span class="totals-label"><i class="fas fa-calculator" style="margin-right:5px;"></i>Page Totals</span></td>
              <td style="text-align:right;">
                <?php
                  $pgInc = array_sum(array_column(array_filter($transactions, fn($t) => $t['type'] === 'income'), 'amount'));
                  $pgExp = array_sum(array_column(array_filter($transactions, fn($t) => $t['type'] === 'expense'), 'amount'));
                  if ($filterType !== 'expenses') echo '<span style="color:var(--green);">+৳'.number_format($pgInc,2).'</span><br>';
                  if ($filterType !== 'income')   echo '<span style="color:var(--red);">−৳'.number_format($pgExp,2).'</span>';
                ?>
              </td>
              <td></td>
            </tr>
          </tfoot>
          <?php endif; ?>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1):
        $q = array_filter(['type' => $filterType, 'date_from' => $filterDateFrom, 'date_to' => $filterDateTo]);
        function pgLink($q, $p) { return '?' . http_build_query(array_merge($q, ['page' => $p])); }
        $start = max(1, $page - 2); $end = min($totalPages, $page + 2);
      ?>
      <div class="pag-wrap">
        <a href="<?= pgLink($q, $page - 1) ?>" class="pag-btn <?= $page <= 1 ? 'pag-disabled' : '' ?>">
          <i class="fas fa-chevron-left" style="font-size:.6rem;"></i>
        </a>
        <?php if ($start > 1): ?>
          <a href="<?= pgLink($q, 1) ?>" class="pag-btn">1</a>
          <?php if ($start > 2): ?><span class="pag-dots">…</span><?php endif; ?>
        <?php endif; ?>
        <?php for ($i = $start; $i <= $end; $i++): ?>
          <a href="<?= pgLink($q, $i) ?>" class="pag-btn <?= $i === $page ? 'pag-active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($end < $totalPages): ?>
          <?php if ($end < $totalPages - 1): ?><span class="pag-dots">…</span><?php endif; ?>
          <a href="<?= pgLink($q, $totalPages) ?>" class="pag-btn"><?= $totalPages ?></a>
        <?php endif; ?>
        <a href="<?= pgLink($q, $page + 1) ?>" class="pag-btn <?= $page >= $totalPages ? 'pag-disabled' : '' ?>">
          <i class="fas fa-chevron-right" style="font-size:.6rem;"></i>
        </a>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>
</body>
</html>