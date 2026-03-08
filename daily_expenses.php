<?php
/**
 * Daily Expenses - Redesigned UI
 * PRG pattern preserved — all backend logic unchanged
 */

require 'auth.php';
include 'mydb.php';

date_default_timezone_set('Asia/Dhaka');

$selectedDate = date('Y-m-d');

// ── Helpers ──────────────────────────────────────────────────
function validateExpense($details, $amount) {
    $errors = [];
    if (empty(trim($details)))           $errors[] = "Please enter expense details";
    if (!is_numeric($amount) || $amount <= 0) $errors[] = "Please enter a valid amount";
    return $errors;
}
function formatCurrency($amount) { return '৳' . number_format($amount, 2); }
function formatDate($date)       { return date('l, M d, Y', strtotime($date)); }
function formatTime($datetime)   { return date('h:i A', strtotime($datetime)); }
function getAdjacentDate($date, $direction = 'next') {
    $ts = strtotime($date);
    return $direction === 'next'
        ? date('Y-m-d', strtotime('+1 day', $ts))
        : date('Y-m-d', strtotime('-1 day', $ts));
}
function redirectWithMessage($message, $type, $date = null) {
    $_SESSION['message']      = $message;
    $_SESSION['message_type'] = $type;
    $url = 'daily_expenses.php' . ($date ? '?date=' . urlencode($date) : '');
    header('Location: ' . $url);
    exit;
}

// ── POST: Add expense ────────────────────────────────────────
if ($_POST && isset($_POST['add_expense'])) {
    $details     = trim($_POST['details']);
    $amount      = floatval($_POST['amount']);
    $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expenseDate)) $expenseDate = date('Y-m-d');

    $errors = validateExpense($details, $amount);
    if (empty($errors)) {
        $expenseDateTime = $expenseDate . ' ' . date('H:i:s');
        $sql  = "INSERT INTO daily_expenses (details, amount, created_time) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sds", $details, $amount, $expenseDateTime);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                redirectWithMessage("Expense added for " . formatDate($expenseDate) . "!", "success", $expenseDate);
            } else {
                mysqli_stmt_close($stmt);
                redirectWithMessage("Error adding expense. Please try again.", "danger");
            }
        } else {
            redirectWithMessage("Database error occurred.", "danger");
        }
    } else {
        redirectWithMessage(implode(". ", $errors), "warning");
    }
}

// ── POST: Delete expense ─────────────────────────────────────
if ($_POST && isset($_POST['delete_expense'])) {
    $expenseId  = intval($_POST['expense_id']);
    $returnDate = $_POST['selected_date'] ?? date('Y-m-d');
    if ($expenseId > 0) {
        $sql  = "DELETE FROM daily_expenses WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $expenseId);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                redirectWithMessage("Expense deleted.", "success", $returnDate);
            } else {
                mysqli_stmt_close($stmt);
                redirectWithMessage("Error deleting expense.", "danger", $returnDate);
            }
        }
    }
}

include 'navbar.php';

// ── Session flash ────────────────────────────────────────────
$message     = $_SESSION['message']      ?? '';
$messageType = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// ── Date setup ───────────────────────────────────────────────
if (isset($_GET['date']) && !empty($_GET['date'])) $selectedDate = $_GET['date'];
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) $selectedDate = date('Y-m-d');

$prevDate = getAdjacentDate($selectedDate, 'prev');
$nextDate = getAdjacentDate($selectedDate, 'next');
$isToday  = ($selectedDate === date('Y-m-d'));
$isFuture = (strtotime($selectedDate) > strtotime(date('Y-m-d')));

// ── Fetch expenses ───────────────────────────────────────────
$expenses    = [];
$totalAmount = 0;
$totalCount  = 0;

$sql  = "SELECT id, details, amount, created_time FROM daily_expenses WHERE DATE(created_time) = ? ORDER BY created_time DESC";
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $selectedDate);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $expenses[]   = $row;
        $totalAmount += $row['amount'];
        $totalCount++;
    }
    mysqli_stmt_close($stmt);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daily Expenses — Rajaiswari</title>
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
      --violet:    #7c3aed;  --violet-bg:#f5f3ff;
      --r:  10px;
      --rs: 6px;
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

    /* ── Two-column layout ──────────────────── */
    .layout {
      display: grid;
      grid-template-columns: 340px 1fr;
      gap: 14px;
      align-items: start;
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
    }
    .sec-ico {
      width: 28px; height: 28px;
      border-radius: var(--rs);
      display: flex; align-items: center; justify-content: center;
      font-size: 12px; flex-shrink: 0;
    }
    .i-blue   { background: var(--blue-bg);   color: var(--blue);   }
    .i-green  { background: var(--green-bg);  color: var(--green);  }
    .i-amber  { background: var(--amber-bg);  color: var(--amber);  }
    .i-red    { background: var(--red-bg);    color: var(--red);    }
    .i-violet { background: var(--violet-bg); color: var(--violet); }

    .sec-title { font-size: .9375rem; font-weight: 700; color: var(--t1); letter-spacing: -.01em; }
    .sec-meta  { margin-left: auto; font-size: .78rem; font-weight: 600; color: var(--t4); }

    .sec-body { padding: 18px; }

    /* ── Form controls ──────────────────────── */
    .lbl {
      display: block;
      font-size: .76rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .06em;
      color: var(--t3); margin-bottom: 5px;
    }
    .lbl .req { color: var(--red); margin-left: 2px; }

    .fc {
      width: 100%; height: 38px;
      padding: 0 11px;
      border: 1.5px solid var(--border);
      border-radius: var(--rs);
      font-family: 'DM Sans', sans-serif;
      font-size: .9rem; color: var(--t2);
      background: var(--surface);
      transition: border-color .15s, box-shadow .15s;
      outline: none;
    }
    .fc:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }

    /* ── Buttons ────────────────────────────── */
    .btn-primary-full {
      display: flex; align-items: center; justify-content: center; gap: 7px;
      width: 100%; height: 40px;
      background: var(--green);
      border: none; border-radius: var(--rs);
      font-family: 'DM Sans', sans-serif;
      font-size: .9375rem; font-weight: 700; color: #fff;
      cursor: pointer;
      box-shadow: 0 1px 4px rgba(5,150,105,.25);
      transition: background .15s;
    }
    .btn-primary-full:hover { background: #047857; }

    .btn-nav {
      display: inline-flex; align-items: center; gap: 5px;
      height: 34px; padding: 0 14px;
      border: 1.5px solid var(--border);
      border-radius: var(--rs);
      font-family: 'DM Sans', sans-serif;
      font-size: .82rem; font-weight: 600;
      cursor: pointer; transition: all .15s;
      text-decoration: none; white-space: nowrap;
    }
    .btn-nav-active { background: var(--blue-bg); border-color: var(--blue-b); color: var(--blue); }
    .btn-nav-active:hover { background: #dbeafe; color: var(--blue); }
    .btn-nav-disabled { background: var(--surface-2); border-color: var(--bsoft); color: var(--t4); cursor: not-allowed; }

    .btn-today {
      display: inline-flex; align-items: center; gap: 5px;
      height: 28px; padding: 0 12px;
      background: var(--green-bg);
      border: 1.5px solid var(--green-b);
      border-radius: 20px;
      font-family: 'DM Sans', sans-serif;
      font-size: .78rem; font-weight: 700;
      color: var(--green);
      text-decoration: none; transition: all .15s;
    }
    .btn-today:hover { background: #d1fae5; color: var(--green); }

    .btn-delete {
      width: 32px; height: 32px;
      display: flex; align-items: center; justify-content: center;
      background: var(--red-bg);
      border: 1.5px solid var(--red-b);
      border-radius: var(--rs);
      color: var(--red); font-size: 13px;
      cursor: pointer; transition: all .15s;
    }
    .btn-delete:hover { background: var(--red); color: #fff; border-color: var(--red); }

    /* ── Date navigation card ───────────────── */
    .date-nav {
      display: flex; align-items: center; gap: 10px;
      padding: 14px 18px;
      border-bottom: 1px solid var(--bsoft);
      flex-wrap: wrap;
    }

    .date-center {
      flex: 1; text-align: center; min-width: 0;
    }

    .date-label {
      font-size: .9375rem; font-weight: 800; color: var(--t1);
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    .date-sub { font-size: .76rem; color: var(--t4); margin-top: 2px; font-family: 'DM Mono', monospace; }

    .today-pill {
      display: inline-flex; align-items: center; gap: 4px;
      background: var(--green-bg);
      border: 1px solid var(--green-b);
      color: var(--green);
      padding: 1px 8px; border-radius: 20px;
      font-size: .7rem; font-weight: 700;
      vertical-align: middle; margin-left: 6px;
    }

    /* ── Date picker row ────────────────────── */
    .date-picker-row {
      display: flex; align-items: center;
      justify-content: center; gap: 10px;
      padding: 10px 18px;
      background: var(--surface-2);
      border-top: 1px solid var(--bsoft);
      flex-wrap: wrap;
    }

    .date-picker-lbl { font-size: .76rem; color: var(--t4); font-weight: 600; white-space: nowrap; }

    .fc-date {
      height: 32px; width: 160px;
      padding: 0 10px;
      border: 1.5px solid var(--border);
      border-radius: var(--rs);
      font-family: 'DM Mono', monospace;
      font-size: .82rem; color: var(--t2);
      background: var(--surface); outline: none;
      transition: border-color .15s;
    }
    .fc-date:focus { border-color: var(--blue); }

    /* ── Summary stat ───────────────────────── */
    .stat-strip {
      display: flex; gap: 14px;
      padding: 13px 18px;
      background: var(--surface-2);
      border-bottom: 1px solid var(--bsoft);
    }

    .stat-item { display: flex; flex-direction: column; }
    .stat-lbl  { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--t4); }
    .stat-val  { font-size: 1.05rem; font-weight: 800; color: var(--t1); font-family: 'DM Mono', monospace; }
    .stat-val.red { color: var(--red); }

    /* ── Expenses table ─────────────────────── */
    .exp-tbl { width: 100%; border-collapse: collapse; }

    .exp-tbl thead th {
      padding: 9px 14px;
      text-align: left;
      font-size: .72rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .07em;
      color: var(--t4);
      background: var(--surface-2);
      border-bottom: 1px solid var(--border);
      white-space: nowrap;
    }

    .exp-tbl tbody td {
      padding: 10px 14px;
      border-bottom: 1px solid var(--bsoft);
      vertical-align: middle;
    }

    .exp-tbl tbody tr:last-child td { border-bottom: none; }
    .exp-tbl tbody tr:hover td { background: #fafbff; }

    .exp-tbl tfoot td {
      padding: 11px 14px;
      background: var(--surface-2);
      border-top: 1px solid var(--border);
    }

    .time-tag {
      display: inline-flex; align-items: center; gap: 4px;
      background: var(--surface-2);
      border: 1px solid var(--border);
      border-radius: 4px;
      padding: 2px 7px;
      font-family: 'DM Mono', monospace;
      font-size: .76rem; color: var(--t3);
    }

    .amount-cell {
      font-family: 'DM Mono', monospace;
      font-size: .9rem; font-weight: 700;
      color: var(--red);
    }

    .total-lbl { font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--t3); }
    .total-val { font-family: 'DM Mono', monospace; font-size: 1.1rem; font-weight: 800; color: var(--red); }

    /* ── Empty state ────────────────────────── */
    .empty-state {
      padding: 48px 24px;
      text-align: center;
    }
    .empty-ico { font-size: 3rem; color: var(--border); margin-bottom: 12px; }
    .empty-title { font-size: .9375rem; font-weight: 700; color: var(--t3); margin-bottom: 6px; }
    .empty-sub   { font-size: .82rem; color: var(--t4); }

    /* ── Alerts ─────────────────────────────── */
    .alert-flash {
      border-radius: var(--rs);
      padding: 12px 16px;
      font-size: .875rem;
      display: flex; align-items: center; gap: 9px;
    }
    .af-success { background: var(--green-bg); border: 1px solid var(--green-b); border-left: 3px solid var(--green); color: #065f46; }
    .af-danger  { background: var(--red-bg);   border: 1px solid var(--red-b);   border-left: 3px solid var(--red);   color: #7f1d1d; }
    .af-warning { background: var(--amber-bg); border: 1px solid var(--amber-b); border-left: 3px solid var(--amber); color: #78350f; }

    /* ── Responsive ─────────────────────────── */
    @media (max-width: 1100px) {
      .layout { grid-template-columns: 1fr; }
    }
    @media (max-width: 991.98px) {
      .page-shell { margin-left: 0; }
      .top-bar    { top: 52px; }
      .main       { padding: 14px 14px 50px; }
    }
  </style>
</head>
<body>

<?php /* navbar already included above via include 'navbar.php' */ ?>

<div class="page-shell">

  <!-- Top Bar -->
  <header class="top-bar">
    <div class="tb-ico"><i class="fas fa-wallet"></i></div>
    <div>
      <div class="tb-title">Daily Expenses</div>
      <div class="tb-sub">Record and review daily spending</div>
    </div>
    <div class="tb-right">
      <a href="daily_expenses_list.php" class="tb-link">
        <i class="fas fa-table-list" style="font-size:.65rem;"></i> All Expenses
      </a>
    </div>
  </header>

  <div class="main">

    <!-- Flash message -->
    <?php if (!empty($message)): ?>
    <?php
      $cls = 'af-danger';
      $ico = 'fas fa-circle-exclamation';
      if ($messageType === 'success') { $cls = 'af-success'; $ico = 'fas fa-circle-check'; }
      if ($messageType === 'warning') { $cls = 'af-warning'; $ico = 'fas fa-triangle-exclamation'; }
    ?>
    <div class="alert-flash <?= $cls ?>" id="flashMsg">
      <i class="<?= $ico ?>"></i>
      <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <div class="layout">

      <!-- LEFT: Add expense form (sticky) -->
      <div>
        <div class="sec">
          <div class="sec-head">
            <span class="sec-ico i-green"><i class="fas fa-plus"></i></span>
            <span class="sec-title">Add Expense</span>
          </div>
          <div class="sec-body">
            <form method="POST" action="" id="expenseForm">
              <div style="display:flex;flex-direction:column;gap:13px;">

                <div>
                  <label class="lbl">Date <span class="req">*</span></label>
                  <input type="date" name="expense_date" class="fc"
                         value="<?= date('Y-m-d') ?>"
                         max="<?= date('Y-m-d') ?>" required>
                </div>

                <div>
                  <label class="lbl">Details <span class="req">*</span></label>
                  <input type="text" name="details" id="details" class="fc"
                         placeholder="What did you spend on?" required autocomplete="off">
                </div>

                <div>
                  <label class="lbl">Amount (৳) <span class="req">*</span></label>
                  <input type="number" name="amount" id="amount" class="fc"
                         step="0.01" min="0.01" placeholder="0.00" required>
                </div>

                <button type="submit" name="add_expense" class="btn-primary-full">
                  <i class="fas fa-plus" style="font-size:.75rem;"></i>
                  Add Expense
                </button>

              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- RIGHT: View expenses by date -->
      <div>
        <div class="sec">

          <!-- Section header -->
          <div class="sec-head">
            <span class="sec-ico i-amber"><i class="fas fa-receipt"></i></span>
            <span class="sec-title">Expense Log</span>
            <span class="sec-meta"><?= $totalCount ?> item<?= $totalCount !== 1 ? 's' : '' ?></span>
          </div>

          <!-- Date navigation -->
          <div class="date-nav">
            <a href="?date=<?= $prevDate ?>" class="btn-nav btn-nav-active">
              <i class="fas fa-chevron-left" style="font-size:.6rem;"></i> Prev
            </a>

            <div class="date-center">
              <div class="date-label">
                <?= formatDate($selectedDate) ?>
                <?php if ($isToday): ?>
                  <span class="today-pill"><i class="fas fa-circle" style="font-size:.4rem;"></i> Today</span>
                <?php endif; ?>
              </div>
              <div class="date-sub"><?= $selectedDate ?></div>
            </div>

            <?php if (!$isFuture): ?>
              <a href="?date=<?= $nextDate ?>" class="btn-nav btn-nav-active">
                Next <i class="fas fa-chevron-right" style="font-size:.6rem;"></i>
              </a>
            <?php else: ?>
              <span class="btn-nav btn-nav-disabled">
                Next <i class="fas fa-chevron-right" style="font-size:.6rem;"></i>
              </span>
            <?php endif; ?>
          </div>

          <!-- Quick date picker -->
          <div class="date-picker-row">
            <span class="date-picker-lbl"><i class="fas fa-calendar-days" style="margin-right:4px;font-size:.7rem;"></i>Jump to:</span>
            <input type="date" id="quickDate" class="fc-date"
                   value="<?= htmlspecialchars($selectedDate) ?>"
                   max="<?= date('Y-m-d') ?>">
            <?php if (!$isToday): ?>
              <a href="daily_expenses.php" class="btn-today">
                <i class="fas fa-calendar-day" style="font-size:.65rem;"></i> Today
              </a>
            <?php endif; ?>
          </div>

          <!-- Summary strip -->
          <?php if ($totalCount > 0): ?>
          <div class="stat-strip">
            <div class="stat-item">
              <span class="stat-lbl">Entries</span>
              <span class="stat-val"><?= $totalCount ?></span>
            </div>
            <div class="stat-item" style="margin-left:auto;">
              <span class="stat-lbl">Total Spent</span>
              <span class="stat-val red"><?= formatCurrency($totalAmount) ?></span>
            </div>
          </div>
          <?php endif; ?>

          <!-- Expenses table -->
          <?php if (!empty($expenses)): ?>
          <div style="overflow-x:auto;">
            <table class="exp-tbl">
              <thead>
                <tr>
                  <th style="width:110px;">Time</th>
                  <th>Details</th>
                  <th style="width:130px;">Amount</th>
                  <th style="width:50px;text-align:center;"></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($expenses as $expense): ?>
                <tr>
                  <td>
                    <span class="time-tag">
                      <i class="fas fa-clock" style="font-size:.6rem;"></i>
                      <?= formatTime($expense['created_time']) ?>
                    </span>
                  </td>
                  <td style="font-size:.875rem;color:var(--t2);">
                    <?= htmlspecialchars($expense['details']) ?>
                  </td>
                  <td class="amount-cell">
                    <?= formatCurrency($expense['amount']) ?>
                  </td>
                  <td style="text-align:center;">
                    <form method="POST" action="" class="d-inline"
                          onsubmit="return confirmDelete('<?= htmlspecialchars(addslashes($expense['details'])) ?>')">
                      <input type="hidden" name="expense_id"   value="<?= $expense['id'] ?>">
                      <input type="hidden" name="selected_date" value="<?= htmlspecialchars($selectedDate) ?>">
                      <button type="submit" name="delete_expense" class="btn-delete" title="Delete">
                        <i class="fas fa-trash-can"></i>
                      </button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="2" style="text-align:right;">
                    <span class="total-lbl">Total</span>
                  </td>
                  <td colspan="2">
                    <span class="total-val"><?= formatCurrency($totalAmount) ?></span>
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>

          <?php else: ?>

          <div class="empty-state">
            <div class="empty-ico"><i class="fas fa-inbox"></i></div>
            <div class="empty-title">No expenses for this date</div>
            <div class="empty-sub">Nothing recorded for <?= formatDate($selectedDate) ?>.</div>
            <?php if (!$isToday): ?>
              <a href="daily_expenses.php" class="btn-today" style="display:inline-flex;margin-top:14px;">
                <i class="fas fa-calendar-day" style="font-size:.65rem;"></i> Go to Today
              </a>
            <?php endif; ?>
          </div>

          <?php endif; ?>

        </div>
      </div><!-- /right -->

    </div><!-- /layout -->
  </div><!-- /main -->
</div><!-- /page-shell -->

<script>
function confirmDelete(label) {
  const s = label.length > 35 ? label.substring(0,35) + '…' : label;
  return confirm(`Delete this expense?\n\n"${s}"\n\nThis cannot be undone.`);
}

document.addEventListener('DOMContentLoaded', function () {
  // Focus details field
  const details = document.getElementById('details');
  if (details) details.focus();

  // Enter on amount submits form
  const amount = document.getElementById('amount');
  if (amount) {
    amount.addEventListener('keypress', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); document.getElementById('expenseForm').submit(); }
    });
  }

  // Auto-dismiss flash
  const flash = document.getElementById('flashMsg');
  if (flash) setTimeout(() => flash.style.opacity = '0', 4500);

  // Quick date picker
  const qd = document.getElementById('quickDate');
  if (qd) qd.addEventListener('change', function () { if (this.value) window.location.href = '?date=' + this.value; });

  // Keyboard nav (Ctrl+← / Ctrl+→)
  document.addEventListener('keydown', function (e) {
    if (e.ctrlKey || e.metaKey) {
      if (e.key === 'ArrowLeft')  { e.preventDefault(); window.location.href = '?date=<?= $prevDate ?>'; }
      if (e.key === 'ArrowRight' && !<?= $isFuture ? 'true' : 'false' ?>) {
        e.preventDefault(); window.location.href = '?date=<?= $nextDate ?>';
      }
    }
  });
});
</script>
</body>
</html>