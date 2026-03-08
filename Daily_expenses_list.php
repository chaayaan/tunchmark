<?php
/**
 * Daily Expenses List — Redesigned UI
 * All backend logic unchanged
 */

require 'auth.php';
include 'mydb.php';

date_default_timezone_set('Asia/Dhaka');

// ── Filter params ─────────────────────────────────────────────
$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate   = $_GET['to_date']   ?? date('Y-m-t');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) $fromDate = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate))   $toDate   = date('Y-m-t');

if (strtotime($fromDate) > strtotime($toDate)) {
    [$fromDate, $toDate] = [$toDate, $fromDate];
}

// ── Fetch data ────────────────────────────────────────────────
$expenses    = [];
$totalAmount = 0;

$sql = "SELECT DATE(created_time) as expense_date,
               GROUP_CONCAT(details SEPARATOR ', ') as notes,
               SUM(amount) as daily_total
        FROM daily_expenses
        WHERE DATE(created_time) BETWEEN ? AND ?
        GROUP BY DATE(created_time)
        ORDER BY expense_date ASC";

$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ss", $fromDate, $toDate);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $expenses[]   = $row;
        $totalAmount += $row['daily_total'];
    }
    mysqli_stmt_close($stmt);
}

include 'navbar.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Expenses List — Rajaiswari</title>
  <link rel="icon" type="image/png" href="favicon.png">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg:        #f1f3f6;
      --surface:   #ffffff;
      --surface-2: #fafbfc;
      --border:    #e4e7ec;
      --bsoft:     #f0f1f3;
      --t1:        #111827;
      --t2:        #374151;
      --t3:        #6b7280;
      --t4:        #9ca3af;
      --blue:      #2563eb;  --blue-bg: #eff6ff;  --blue-b: #bfdbfe;
      --green:     #059669;  --green-bg:#ecfdf5;  --green-b:#a7f3d0;
      --amber:     #d97706;  --amber-bg:#fffbeb;  --amber-b:#fde68a;
      --red:       #dc2626;  --red-bg:  #fef2f2;  --red-b:  #fecaca;
      --r:  10px;  --rs: 6px;
      --sh: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'DM Sans', -apple-system, sans-serif;
      font-size: 14.5px;
      background: var(--bg);
      color: var(--t1);
      -webkit-font-smoothing: antialiased;
      min-height: 100vh;
    }

    /* ── Shell ──────────────────────────────── */
    .page-shell {
      margin-left: 200px;
      min-height: 100vh;
      display: flex; flex-direction: column;
    }

    /* ── Top bar ────────────────────────────── */
    .top-bar {
      position: sticky; top: 0; z-index: 200;
      height: 54px;
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      box-shadow: var(--sh);
      display: flex; align-items: center;
      padding: 0 22px; gap: 10px; flex-shrink: 0;
    }
    .tb-ico {
      width: 32px; height: 32px;
      background: var(--amber-bg);
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      color: var(--amber); font-size: 13px; flex-shrink: 0;
    }
    .tb-title { font-size: 1.0625rem; font-weight: 700; color: var(--t1); line-height: 1.2; }
    .tb-sub   { font-size: .8rem; color: var(--t4); }
    .tb-right { margin-left: auto; display: flex; align-items: center; gap: 8px; }

    .tb-link {
      display: inline-flex; align-items: center; gap: 6px;
      height: 32px; padding: 0 14px;
      background: var(--surface-2);
      border: 1.5px solid var(--border);
      border-radius: var(--rs);
      font-family: 'DM Sans', sans-serif;
      font-size: .82rem; font-weight: 600; color: var(--t2);
      text-decoration: none; transition: all .15s;
    }
    .tb-link:hover { background: var(--border); color: var(--t1); }

    /* ── Main ───────────────────────────────── */
    .main {
      flex: 1;
      padding: 20px 22px 60px;
      display: flex; flex-direction: column; gap: 14px;
    }

    /* ── Section card ───────────────────────── */
    .sec {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--r);
      box-shadow: var(--sh);
      overflow: hidden;
    }

    .sec-head {
      display: flex; align-items: center; gap: 9px;
      padding: 12px 18px;
      background: var(--surface-2);
      border-bottom: 1px solid var(--bsoft);
      flex-wrap: wrap; gap-row: 8px;
    }
    .sec-ico {
      width: 28px; height: 28px;
      border-radius: var(--rs);
      display: flex; align-items: center; justify-content: center;
      font-size: 12px; flex-shrink: 0;
    }
    .i-amber  { background: var(--amber-bg); color: var(--amber); }
    .i-blue   { background: var(--blue-bg);  color: var(--blue);  }
    .i-red    { background: var(--red-bg);   color: var(--red);   }

    .sec-title { font-size: .9375rem; font-weight: 700; color: var(--t1); letter-spacing: -.01em; }

    /* ── Filter bar ─────────────────────────── */
    .filter-bar {
      display: flex; align-items: flex-end; gap: 10px;
      padding: 16px 18px;
      flex-wrap: wrap;
    }

    .filter-group { display: flex; flex-direction: column; gap: 5px; }

    .lbl {
      display: block;
      font-size: .76rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .06em;
      color: var(--t3);
    }

    .fc {
      height: 38px; padding: 0 11px;
      border: 1.5px solid var(--border);
      border-radius: var(--rs);
      font-family: 'DM Mono', monospace;
      font-size: .875rem; color: var(--t2);
      background: var(--surface);
      transition: border-color .15s; outline: none;
    }
    .fc:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }

    .btn-filter {
      display: inline-flex; align-items: center; gap: 6px;
      height: 38px; padding: 0 18px;
      background: var(--blue);
      border: none; border-radius: var(--rs);
      font-family: 'DM Sans', sans-serif;
      font-size: .875rem; font-weight: 700; color: #fff;
      cursor: pointer; transition: background .15s;
      white-space: nowrap;
    }
    .btn-filter:hover { background: #1d4ed8; }

    .btn-reset {
      display: inline-flex; align-items: center; gap: 6px;
      height: 38px; padding: 0 16px;
      background: var(--surface);
      border: 1.5px solid var(--border);
      border-radius: var(--rs);
      font-family: 'DM Sans', sans-serif;
      font-size: .875rem; font-weight: 500; color: var(--t2);
      text-decoration: none; cursor: pointer; transition: all .15s;
      white-space: nowrap;
    }
    .btn-reset:hover { background: var(--surface-2); color: var(--t1); }

    /* ── Summary strip ──────────────────────── */
    .summary-strip {
      display: flex; gap: 24px; flex-wrap: wrap;
      padding: 14px 18px;
      border-top: 1px solid var(--bsoft);
      background: var(--surface-2);
    }

    .sum-item { display: flex; flex-direction: column; }
    .sum-lbl  { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--t4); }
    .sum-val  { font-size: 1.1rem; font-weight: 800; font-family: 'DM Mono', monospace; color: var(--t1); }
    .sum-val.red { color: var(--red); }

    /* ── Table ──────────────────────────────── */
    .exp-tbl { width: 100%; border-collapse: collapse; }

    .exp-tbl thead th {
      padding: 10px 16px;
      text-align: left;
      font-size: .72rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .07em;
      color: var(--t4);
      background: var(--surface-2);
      border-bottom: 1px solid var(--border);
      white-space: nowrap;
    }

    .exp-tbl tbody td {
      padding: 11px 16px;
      border-bottom: 1px solid var(--bsoft);
      vertical-align: top;
    }

    .exp-tbl tbody tr:last-child td { border-bottom: none; }
    .exp-tbl tbody tr:hover td { background: #fafbff; }

    .exp-tbl tfoot td {
      padding: 12px 16px;
      background: var(--surface-2);
      border-top: 1px solid var(--border);
    }

    .date-cell { font-weight: 700; color: var(--t1); font-size: .875rem; }
    .day-cell  { font-size: .82rem; color: var(--t3); font-weight: 500; }

    .notes-cell {
      font-size: .82rem; color: var(--t3);
      max-width: 420px;
      overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }

    .amount-cell {
      font-family: 'DM Mono', monospace;
      font-size: .9375rem; font-weight: 700;
      color: var(--red); text-align: right;
    }

    .total-lbl { font-size: .82rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--t3); }
    .total-val { font-family: 'DM Mono', monospace; font-size: 1.15rem; font-weight: 800; color: var(--red); }

    .view-link {
      display: inline-flex; align-items: center; gap: 4px;
      font-size: .75rem; font-weight: 600;
      color: var(--blue); text-decoration: none;
      transition: color .12s;
    }
    .view-link:hover { color: #1d4ed8; }

    /* ── Range badge ────────────────────────── */
    .range-badge {
      margin-left: auto;
      display: inline-flex; align-items: center; gap: 5px;
      background: var(--surface-2);
      border: 1px solid var(--border);
      border-radius: var(--rs);
      padding: 4px 12px;
      font-family: 'DM Mono', monospace;
      font-size: .78rem; font-weight: 500; color: var(--t3);
    }

    /* ── Empty state ────────────────────────── */
    .empty-state {
      padding: 60px 24px;
      text-align: center;
    }
    .empty-ico   { font-size: 3rem; color: var(--border); margin-bottom: 12px; }
    .empty-title { font-size: .9375rem; font-weight: 700; color: var(--t3); margin-bottom: 6px; }
    .empty-sub   { font-size: .82rem; color: var(--t4); }

    /* ── Responsive ─────────────────────────── */
    @media (max-width: 991.98px) {
      .page-shell { margin-left: 0; }
      .top-bar    { top: 52px; }
      .main       { padding: 14px 14px 50px; }
      .notes-cell { max-width: 200px; }
    }

    @media print {
      .page-shell { margin-left: 0; }
      .top-bar, .filter-bar, .tb-right { display: none !important; }
    }
  </style>
</head>
<body>

<div class="page-shell">

  <!-- Top Bar -->
  <header class="top-bar">
    <div class="tb-ico"><i class="fas fa-table-list"></i></div>
    <div>
      <div class="tb-title">Expenses List</div>
      <div class="tb-sub">Daily summary by date range</div>
    </div>
    <div class="tb-right">
      <?php if (!empty($expenses)): ?>
      <div class="range-badge">
        <?= date('M d', strtotime($fromDate)) ?> — <?= date('M d, Y', strtotime($toDate)) ?>
        &nbsp;·&nbsp; <?= count($expenses) ?> day<?= count($expenses) !== 1 ? 's' : '' ?>
      </div>
      <?php endif; ?>
      <a href="daily_expenses.php" class="tb-link">
        <i class="fas fa-arrow-left" style="font-size:.65rem;"></i> Daily View
      </a>
    </div>
  </header>

  <div class="main">

    <!-- Filter card -->
    <div class="sec">
      <div class="sec-head">
        <span class="sec-ico i-blue"><i class="fas fa-filter"></i></span>
        <span class="sec-title">Filter by Date Range</span>
      </div>

      <form method="GET" action="">
        <div class="filter-bar">

          <div class="filter-group">
            <label class="lbl">From Date</label>
            <input type="date" name="from_date" class="fc"
                   value="<?= htmlspecialchars($fromDate) ?>" required>
          </div>

          <div class="filter-group">
            <label class="lbl">To Date</label>
            <input type="date" name="to_date" class="fc"
                   value="<?= htmlspecialchars($toDate) ?>" required>
          </div>

          <button type="submit" class="btn-filter">
            <i class="fas fa-magnifying-glass" style="font-size:.75rem;"></i> Apply Filter
          </button>

          <a href="?" class="btn-reset">
            <i class="fas fa-rotate-left" style="font-size:.72rem;"></i> Reset
          </a>

        </div>
      </form>
    </div>

    <!-- Results table -->
    <div class="sec">

      <div class="sec-head">
        <span class="sec-ico i-amber"><i class="fas fa-receipt"></i></span>
        <span class="sec-title">Expense Summary</span>
      </div>

      <?php if (!empty($expenses)): ?>

      <!-- Summary strip -->
      <div class="summary-strip">
        <div class="sum-item">
          <span class="sum-lbl">Total Days</span>
          <span class="sum-val"><?= count($expenses) ?></span>
        </div>
        <div class="sum-item">
          <span class="sum-lbl">Total Spent</span>
          <span class="sum-val red">৳<?= number_format($totalAmount, 2) ?></span>
        </div>
        <div class="sum-item">
          <span class="sum-lbl">Daily Avg</span>
          <span class="sum-val">৳<?= number_format($totalAmount / max(count($expenses), 1), 2) ?></span>
        </div>
      </div>

      <!-- Table -->
      <div style="overflow-x:auto;">
        <table class="exp-tbl">
          <thead>
            <tr>
              <th style="width:130px;">Date</th>
              <th style="width:90px;">Day</th>
              <th>Notes</th>
              <th style="width:140px;text-align:right;">Total</th>
              <th style="width:80px;text-align:center;">Details</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($expenses as $exp):
              $d = $exp['expense_date'];
            ?>
            <tr>
              <td>
                <span class="date-cell"><?= date('M d, Y', strtotime($d)) ?></span>
              </td>
              <td class="day-cell"><?= date('l', strtotime($d)) ?></td>
              <td>
                <span class="notes-cell" title="<?= htmlspecialchars($exp['notes']) ?>">
                  <?= htmlspecialchars($exp['notes']) ?>
                </span>
              </td>
              <td class="amount-cell">৳<?= number_format($exp['daily_total'], 2) ?></td>
              <td style="text-align:center;">
                <a href="daily_expenses.php?date=<?= $d ?>" class="view-link">
                  <i class="fas fa-arrow-up-right-from-square" style="font-size:.65rem;"></i>
                  View
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3" style="text-align:right;">
                <span class="total-lbl">Grand Total</span>
              </td>
              <td style="text-align:right;">
                <span class="total-val">৳<?= number_format($totalAmount, 2) ?></span>
              </td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <?php else: ?>

      <div class="empty-state">
        <div class="empty-ico"><i class="fas fa-inbox"></i></div>
        <div class="empty-title">No expenses found</div>
        <div class="empty-sub">
          No records between <?= date('M d, Y', strtotime($fromDate)) ?>
          and <?= date('M d, Y', strtotime($toDate)) ?>.
        </div>
      </div>

      <?php endif; ?>

    </div>
  </div><!-- /main -->
</div><!-- /page-shell -->

</body>
</html>