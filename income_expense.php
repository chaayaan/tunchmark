<?php
require 'auth.php';
include 'mydb.php';

date_default_timezone_set('Asia/Dhaka');

// ── PRG: POST handlers at top before any output ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_income'])) {
    $branch_id        = intval($_POST['branch_id'] ?? 0);
    $category_id      = intval($_POST['income_category_id'] ?? 0);
    $amount           = floatval($_POST['amount'] ?? 0);
    $notes            = trim($_POST['notes'] ?? '');
    $transaction_date = $_POST['transaction_date'] ?? date('Y-m-d');

    if ($branch_id <= 0) {
        $_SESSION['ie_msg'] = "Please select a branch!"; $_SESSION['ie_type'] = 'warning';
    } elseif ($category_id <= 0) {
        $_SESSION['ie_msg'] = "Please select an income category!"; $_SESSION['ie_type'] = 'warning';
    } elseif ($amount <= 0) {
        $_SESSION['ie_msg'] = "Income amount must be greater than zero!"; $_SESSION['ie_type'] = 'warning';
    } else {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $transaction_date)) $transaction_date = date('Y-m-d');
        $dt   = $transaction_date . ' ' . date('H:i:s');
        $stmt = mysqli_prepare($conn, "INSERT INTO branch_income (branch_id, category_id, income, notes, created_at) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iidss", $branch_id, $category_id, $amount, $notes, $dt);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['ie_msg']  = "Income of ৳" . number_format($amount, 2) . " recorded successfully!";
                $_SESSION['ie_type'] = 'success';
            } else {
                $_SESSION['ie_msg']  = "Database Error: " . mysqli_stmt_error($stmt);
                $_SESSION['ie_type'] = 'danger';
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['ie_msg']  = "Failed to prepare statement: " . mysqli_error($conn);
            $_SESSION['ie_type'] = 'danger';
        }
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=income"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $category_id      = intval($_POST['expense_category_id'] ?? 0);
    $amount           = floatval($_POST['amount'] ?? 0);
    $notes            = trim($_POST['notes'] ?? '');
    $transaction_date = $_POST['transaction_date'] ?? date('Y-m-d');

    if ($category_id <= 0) {
        $_SESSION['ie_msg'] = "Please select an expense category!"; $_SESSION['ie_type'] = 'warning';
    } elseif ($amount <= 0) {
        $_SESSION['ie_msg'] = "Expense amount must be greater than zero!"; $_SESSION['ie_type'] = 'warning';
    } else {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $transaction_date)) $transaction_date = date('Y-m-d');
        $dt   = $transaction_date . ' ' . date('H:i:s');
        $stmt = mysqli_prepare($conn, "INSERT INTO branch_expenses (category_id, expense, notes, created_at) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "idss", $category_id, $amount, $notes, $dt);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['ie_msg']  = "Expense of ৳" . number_format($amount, 2) . " recorded successfully!";
                $_SESSION['ie_type'] = 'success';
            } else {
                $_SESSION['ie_msg']  = "Database Error: " . mysqli_stmt_error($stmt);
                $_SESSION['ie_type'] = 'danger';
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['ie_msg']  = "Failed to prepare statement: " . mysqli_error($conn);
            $_SESSION['ie_type'] = 'danger';
        }
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=expense"); exit;
}

// ── GET: read flash from session, clear it, then render ──────────────────────
include 'navbar.php';

$message     = $_SESSION['ie_msg']  ?? '';
$messageType = $_SESSION['ie_type'] ?? '';
$activeTab   = $_GET['tab'] ?? 'income'; // preserved via redirect URL
unset($_SESSION['ie_msg'], $_SESSION['ie_type']);

$branches = $incomeCategories = $expenseCategories = [];
$r = mysqli_query($conn, "SELECT id, name FROM branches ORDER BY name");
if ($r) while ($row = mysqli_fetch_assoc($r)) $branches[] = $row;
$r = mysqli_query($conn, "SELECT id, name, description FROM income_categories ORDER BY name");
if ($r) while ($row = mysqli_fetch_assoc($r)) $incomeCategories[] = $row;
$r = mysqli_query($conn, "SELECT id, name, description FROM expense_categories ORDER BY name");
if ($r) while ($row = mysqli_fetch_assoc($r)) $expenseCategories[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Income &amp; Expense Entry — Rajaiswari</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root{--bg:#f1f3f6;--surface:#fff;--s2:#fafbfc;--border:#e4e7ec;--bsoft:#f0f1f3;--t1:#111827;--t2:#374151;--t3:#6b7280;--t4:#9ca3af;--blue:#2563eb;--blue-bg:#eff6ff;--blue-b:#bfdbfe;--green:#059669;--green-bg:#ecfdf5;--green-b:#a7f3d0;--amber:#d97706;--amber-bg:#fffbeb;--amber-b:#fde68a;--red:#dc2626;--red-bg:#fef2f2;--red-b:#fecaca;--r:10px;--rs:6px;--sh:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);}
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'DM Sans',-apple-system,sans-serif;font-size:14px;background:var(--bg);color:var(--t1);-webkit-font-smoothing:antialiased;min-height:100vh;}
        .page-shell{margin-left:200px;min-height:100vh;display:flex;flex-direction:column;}
        .top-bar{position:sticky;top:0;z-index:200;height:54px;background:var(--surface);border-bottom:1px solid var(--border);box-shadow:var(--sh);display:flex;align-items:center;padding:0 22px;gap:12px;flex-shrink:0;}
        .tb-ico{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;background:var(--green-bg);color:var(--green);flex-shrink:0;}
        .tb-title{font-size:1.0625rem;font-weight:700;color:var(--t1);}
        .tb-sub{font-size:.78rem;color:var(--t4);}
        .tb-right{margin-left:auto;display:flex;gap:7px;align-items:center;}
        .btn-pos{display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 13px;border:none;border-radius:var(--rs);font-family:inherit;font-size:.8125rem;font-weight:600;cursor:pointer;transition:all .15s;text-decoration:none;white-space:nowrap;}
        .btn-ghost{background:var(--surface);color:var(--t2);border:1.5px solid var(--border);}
        .btn-ghost:hover{background:var(--s2);border-color:#9ca3af;color:var(--t1);}
        .btn-green{background:var(--green);color:#fff;border:none;}
        .btn-green:hover{background:#047857;color:#fff;}
        .btn-red{background:var(--red);color:#fff;border:none;}
        .btn-red:hover{background:#b91c1c;color:#fff;}
        .main{flex:1;padding:20px 22px 60px;display:flex;flex-direction:column;gap:16px;max-width:720px;}
        .pos-alert{display:flex;align-items:flex-start;gap:10px;padding:12px 16px;border-radius:var(--rs);font-size:.875rem;font-weight:500;}
        .pos-alert.success{background:var(--green-bg);border:1px solid var(--green-b);border-left:3px solid var(--green);color:#065f46;}
        .pos-alert.danger{background:var(--red-bg);border:1px solid var(--red-b);border-left:3px solid var(--red);color:#991b1b;}
        .pos-alert.warning{background:var(--amber-bg);border:1px solid var(--amber-b);border-left:3px solid var(--amber);color:#92400e;}
        .pos-alert .close-btn{margin-left:auto;background:none;border:none;cursor:pointer;font-size:.75rem;color:inherit;opacity:.6;padding:0;}
        .pos-alert .close-btn:hover{opacity:1;}
        .toggle-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
        .toggle-card{display:flex;align-items:center;gap:14px;padding:16px 18px;border-radius:var(--r);border:2px solid var(--border);background:var(--surface);cursor:pointer;transition:all .2s;user-select:none;}
        .toggle-card:hover{border-color:#9ca3af;box-shadow:var(--sh);}
        .toggle-card.inc-active{border-color:var(--green);background:var(--green-bg);}
        .toggle-card.exp-active{border-color:var(--red);background:var(--red-bg);}
        .toggle-ico{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;transition:all .2s;}
        .ti-gr{background:var(--green-bg);color:var(--green);}
        .toggle-card.inc-active .ti-gr{background:var(--green);color:#fff;}
        .ti-rd{background:var(--red-bg);color:var(--red);}
        .toggle-card.exp-active .ti-rd{background:var(--red);color:#fff;}
        .toggle-label{font-weight:700;font-size:.9375rem;color:var(--t1);}
        .toggle-sub{font-size:.75rem;color:var(--t4);margin-top:2px;}
        .toggle-check{margin-left:auto;width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.6rem;opacity:0;transition:opacity .15s;}
        .toggle-card.inc-active .toggle-check{opacity:1;background:var(--green);color:#fff;}
        .toggle-card.exp-active .toggle-check{opacity:1;background:var(--red);color:#fff;}
        .sec{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;}
        .sec-hd{display:flex;align-items:center;gap:9px;padding:11px 18px;background:var(--s2);border-bottom:1px solid var(--bsoft);}
        .sec-ico{width:26px;height:26px;border-radius:var(--rs);display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;}
        .si-gr{background:var(--green-bg);color:var(--green);}
        .si-rd{background:var(--red-bg);color:var(--red);}
        .sec-title{font-size:.875rem;font-weight:700;color:var(--t1);}
        .sec-body{padding:18px;}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
        .span-2{grid-column:span 2;}
        .lbl{display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t3);margin-bottom:5px;}
        .lbl .req{color:var(--red);margin-left:2px;}
        .fc{width:100%;height:36px;padding:0 10px;border:1.5px solid var(--border);border-radius:var(--rs);font-family:inherit;font-size:.875rem;color:var(--t2);background:var(--surface);outline:none;transition:border-color .15s;}
        .fc:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
        .fc-ta{height:auto;padding:8px 10px;resize:vertical;min-height:70px;}
        select.fc{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%236b7280'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;}
        .entry-form{display:none;}
        .entry-form.active{display:block;animation:fadeUp .22s ease;}
        @keyframes fadeUp{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
        .form-footer{display:flex;justify-content:flex-end;padding-top:6px;}
        .warn-strip{display:flex;align-items:center;gap:9px;padding:10px 14px;background:var(--amber-bg);border:1px solid var(--amber-b);border-radius:var(--rs);font-size:.8rem;color:#92400e;}
        .warn-strip a{color:#92400e;font-weight:700;}
        @media(max-width:991.98px){.page-shell{margin-left:0;}.top-bar{top:52px;}.main{padding:14px 14px 50px;max-width:100%;}}
        @media(max-width:560px){.form-grid{grid-template-columns:1fr;}.span-2{grid-column:span 1;}}
    </style>
</head>
<body>
<div class="page-shell">
    <header class="top-bar">
        <div class="tb-ico"><i class="fas fa-cash-register"></i></div>
        <div>
            <div class="tb-title">Income &amp; Expense Entry</div>
            <div class="tb-sub">Quick entry for daily transactions</div>
        </div>
        <div class="tb-right">
            <a href="transactions_list.php" class="btn-pos btn-ghost"><i class="fas fa-list" style="font-size:.6rem;"></i> View All</a>
            <a href="account.php" class="btn-pos btn-ghost"><i class="fas fa-arrow-left" style="font-size:.6rem;"></i> Finance Panel</a>
        </div>
    </header>

    <div class="main">
        <?php if (!empty($message)): ?>
        <div class="pos-alert <?= $messageType ?>" id="flashAlert">
            <i class="fas <?= $messageType==='success'?'fa-circle-check':($messageType==='danger'?'fa-circle-xmark':'fa-triangle-exclamation') ?>" style="font-size:.9rem;flex-shrink:0;margin-top:1px;"></i>
            <span><?= htmlspecialchars($message) ?></span>
            <button class="close-btn" onclick="this.closest('.pos-alert').remove()"><i class="fas fa-xmark"></i></button>
        </div>
        <?php endif; ?>

        <?php if (empty($incomeCategories) && empty($expenseCategories)): ?>
        <div class="warn-strip"><i class="fas fa-triangle-exclamation"></i> No categories found. <a href="branches.php">Add categories</a> first before recording transactions.</div>
        <?php else: ?>

        <div class="toggle-grid">
            <div class="toggle-card <?= $activeTab === 'income' ? 'inc-active' : '' ?>" id="incToggle" onclick="switchTo('income')">
                <div class="toggle-ico ti-gr"><i class="fas fa-arrow-trend-up"></i></div>
                <div><div class="toggle-label">Add Income</div><div class="toggle-sub">Record revenue by branch</div></div>
                <div class="toggle-check"><i class="fas fa-check"></i></div>
            </div>
            <div class="toggle-card <?= $activeTab === 'expense' ? 'exp-active' : '' ?>" id="expToggle" onclick="switchTo('expense')">
                <div class="toggle-ico ti-rd"><i class="fas fa-arrow-trend-down"></i></div>
                <div><div class="toggle-label">Add Expense</div><div class="toggle-sub">Record general spending</div></div>
                <div class="toggle-check"><i class="fas fa-check"></i></div>
            </div>
        </div>

        <!-- Income Form -->
        <div class="entry-form <?= $activeTab === 'income' ? 'active' : '' ?>" id="incomeForm">
            <div class="sec">
                <div class="sec-hd"><span class="sec-ico si-gr"><i class="fas fa-arrow-trend-up"></i></span><span class="sec-title">Record Income</span></div>
                <div class="sec-body">
                    <?php if (empty($branches)): ?>
                    <div class="warn-strip"><i class="fas fa-triangle-exclamation"></i> No branches found. <a href="branches.php">Add a branch</a> first.</div>
                    <?php elseif (empty($incomeCategories)): ?>
                    <div class="warn-strip"><i class="fas fa-triangle-exclamation"></i> No income categories. <a href="branches.php">Add income categories</a> first.</div>
                    <?php else: ?>
                    <form method="POST" action="">
                        <div class="form-grid">
                            <div>
                                <label class="lbl">Transaction Date</label>
                                <input type="date" name="transaction_date" class="fc" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div>
                                <label class="lbl">Branch <span class="req">*</span></label>
                                <select name="branch_id" class="fc" required>
                                    <option value="">Choose branch…</option>
                                    <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="lbl">Income Category <span class="req">*</span></label>
                                <select name="income_category_id" class="fc" required>
                                    <option value="">Select category…</option>
                                    <?php foreach ($incomeCategories as $cat): ?><option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="lbl">Amount (৳) <span class="req">*</span></label>
                                <input type="number" name="amount" class="fc" step="0.01" min="0.01" placeholder="0.00" required style="font-family:'DM Mono',monospace;">
                            </div>
                            <div class="span-2">
                                <label class="lbl">Notes (Optional)</label>
                                <textarea name="notes" class="fc fc-ta" placeholder="Additional notes…" maxlength="500"></textarea>
                            </div>
                        </div>
                        <div class="form-footer">
                            <button type="submit" name="add_income" class="btn-pos btn-green" style="height:36px;padding:0 20px;"><i class="fas fa-circle-check" style="font-size:.75rem;"></i> Record Income</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Expense Form -->
        <div class="entry-form <?= $activeTab === 'expense' ? 'active' : '' ?>" id="expenseForm">
            <div class="sec">
                <div class="sec-hd"><span class="sec-ico si-rd"><i class="fas fa-arrow-trend-down"></i></span><span class="sec-title">Record Expense</span></div>
                <div class="sec-body">
                    <?php if (empty($expenseCategories)): ?>
                    <div class="warn-strip"><i class="fas fa-triangle-exclamation"></i> No expense categories. <a href="branches.php">Add expense categories</a> first.</div>
                    <?php else: ?>
                    <form method="POST" action="">
                        <div class="form-grid">
                            <div>
                                <label class="lbl">Transaction Date</label>
                                <input type="date" name="transaction_date" class="fc" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div>
                                <label class="lbl">Expense Category <span class="req">*</span></label>
                                <select name="expense_category_id" class="fc" required>
                                    <option value="">Select category…</option>
                                    <?php foreach ($expenseCategories as $cat): ?><option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="lbl">Amount (৳) <span class="req">*</span></label>
                                <input type="number" name="amount" class="fc" step="0.01" min="0.01" placeholder="0.00" required style="font-family:'DM Mono',monospace;">
                            </div>
                            <div class="span-2">
                                <label class="lbl">Notes (Optional)</label>
                                <textarea name="notes" class="fc fc-ta" placeholder="Additional notes…" maxlength="500"></textarea>
                            </div>
                        </div>
                        <div class="form-footer">
                            <button type="submit" name="add_expense" class="btn-pos btn-red" style="height:36px;padding:0 20px;"><i class="fas fa-circle-check" style="font-size:.75rem;"></i> Record Expense</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </div>
</div>
<script>
function switchTo(type) {
    const isIncome = type === 'income';
    document.getElementById('incToggle').className   = 'toggle-card' + (isIncome ? ' inc-active' : '');
    document.getElementById('expToggle').className   = 'toggle-card' + (!isIncome ? ' exp-active' : '');
    document.getElementById('incomeForm').className  = 'entry-form' + (isIncome ? ' active' : '');
    document.getElementById('expenseForm').className = 'entry-form' + (!isIncome ? ' active' : '');
}
// Auto-dismiss flash after 6s
setTimeout(() => {
    const a = document.getElementById('flashAlert');
    if (a) { a.style.transition = 'opacity .5s'; a.style.opacity = '0'; setTimeout(() => a.remove(), 500); }
}, 6000);
</script>
</body>
</html>