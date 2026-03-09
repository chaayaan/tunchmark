<?php
// branches.php - Branch, Income & Expense Category Management System
require 'auth.php';
include 'mydb.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

date_default_timezone_set('Asia/Dhaka');

function redirectWithMessage($message, $type) {
    $_SESSION['message']      = $message;
    $_SESSION['message_type'] = $type;
    header('Location: branches.php');
    exit;
}

// ── Add branch ────────────────────────────────────────────────────────────────
if ($_POST && isset($_POST['add_branch'])) {
    $branch_name = trim($_POST['branch_name']);
    if (empty($branch_name)) redirectWithMessage("Branch name cannot be empty!", "warning");
    $check_stmt = mysqli_prepare($conn, "SELECT id FROM branches WHERE name = ?");
    mysqli_stmt_bind_param($check_stmt, "s", $branch_name);
    mysqli_stmt_execute($check_stmt);
    if (mysqli_num_rows(mysqli_stmt_get_result($check_stmt)) > 0) { mysqli_stmt_close($check_stmt); redirectWithMessage("Branch with this name already exists!", "warning"); }
    mysqli_stmt_close($check_stmt);
    $stmt = mysqli_prepare($conn, "INSERT INTO branches (name) VALUES (?)");
    if ($stmt) { mysqli_stmt_bind_param($stmt, "s", $branch_name); if (mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); redirectWithMessage("✓ Branch '<strong>".htmlspecialchars($branch_name)."</strong>' added!", "success"); } else { mysqli_stmt_close($stmt); redirectWithMessage("⚠ Error adding branch.", "danger"); } }
}

// ── Update branch ─────────────────────────────────────────────────────────────
if ($_POST && isset($_POST['update_branch'])) {
    $branch_id = intval($_POST['branch_id']); $branch_name = trim($_POST['branch_name']); $old_name = trim($_POST['old_branch_name']);
    if (empty($branch_name)) redirectWithMessage("Branch name cannot be empty!", "warning");
    $check_stmt = mysqli_prepare($conn, "SELECT id FROM branches WHERE name = ? AND id != ?");
    mysqli_stmt_bind_param($check_stmt, "si", $branch_name, $branch_id);
    mysqli_stmt_execute($check_stmt);
    if (mysqli_num_rows(mysqli_stmt_get_result($check_stmt)) > 0) { mysqli_stmt_close($check_stmt); redirectWithMessage("⚠ Another branch with this name already exists!", "warning"); }
    mysqli_stmt_close($check_stmt);
    $stmt = mysqli_prepare($conn, "UPDATE branches SET name = ? WHERE id = ?");
    if ($stmt) { mysqli_stmt_bind_param($stmt, "si", $branch_name, $branch_id); if (mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); redirectWithMessage("✓ Branch updated from '<strong>".htmlspecialchars($old_name)."</strong>' to '<strong>".htmlspecialchars($branch_name)."</strong>'", "success"); } else { mysqli_stmt_close($stmt); redirectWithMessage("⚠ Error updating branch.", "danger"); } }
}

// ── Delete branch ─────────────────────────────────────────────────────────────
if ($_POST && isset($_POST['delete_branch'])) {
    $branch_id = intval($_POST['branch_id']); $branch_name = trim($_POST['branch_name']);
    if ($branch_id > 0) { $stmt = mysqli_prepare($conn, "DELETE FROM branches WHERE id = ?"); if ($stmt) { mysqli_stmt_bind_param($stmt, "i", $branch_id); if (mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); redirectWithMessage("✓ Branch '<strong>".htmlspecialchars($branch_name)."</strong>' deleted!", "success"); } else { mysqli_stmt_close($stmt); redirectWithMessage("⚠ Error deleting branch.", "danger"); } } }
}

// ── Add expense category ──────────────────────────────────────────────────────
if ($_POST && isset($_POST['add_expense_category'])) {
    $category = trim($_POST['category']); $description = trim($_POST['description']);
    if (empty($category)) redirectWithMessage("Category name cannot be empty!", "warning");
    $check_stmt = mysqli_prepare($conn, "SELECT id FROM expense_categories WHERE name = ?");
    mysqli_stmt_bind_param($check_stmt, "s", $category); mysqli_stmt_execute($check_stmt);
    if (mysqli_num_rows(mysqli_stmt_get_result($check_stmt)) > 0) { mysqli_stmt_close($check_stmt); redirectWithMessage("Expense category with this name already exists!", "warning"); }
    mysqli_stmt_close($check_stmt);
    $stmt = mysqli_prepare($conn, "INSERT INTO expense_categories (name, description) VALUES (?, ?)");
    if ($stmt) { mysqli_stmt_bind_param($stmt, "ss", $category, $description); if (mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); redirectWithMessage("✓ Expense category '<strong>".htmlspecialchars($category)."</strong>' added!", "success"); } else { mysqli_stmt_close($stmt); redirectWithMessage("⚠ Error adding expense category.", "danger"); } }
}

// ── Update expense category ───────────────────────────────────────────────────
if ($_POST && isset($_POST['update_expense_category'])) {
    $category_id = intval($_POST['category_id']); $category = trim($_POST['category']); $description = trim($_POST['description']); $old_name = trim($_POST['old_category_name']);
    if (empty($category)) redirectWithMessage("Category name cannot be empty!", "warning");
    $check_stmt = mysqli_prepare($conn, "SELECT id FROM expense_categories WHERE name = ? AND id != ?");
    mysqli_stmt_bind_param($check_stmt, "si", $category, $category_id); mysqli_stmt_execute($check_stmt);
    if (mysqli_num_rows(mysqli_stmt_get_result($check_stmt)) > 0) { mysqli_stmt_close($check_stmt); redirectWithMessage("⚠ Another expense category with this name already exists!", "warning"); }
    mysqli_stmt_close($check_stmt);
    $stmt = mysqli_prepare($conn, "UPDATE expense_categories SET name = ?, description = ? WHERE id = ?");
    if ($stmt) { mysqli_stmt_bind_param($stmt, "ssi", $category, $description, $category_id); if (mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); redirectWithMessage("✓ Expense category updated from '<strong>".htmlspecialchars($old_name)."</strong>' to '<strong>".htmlspecialchars($category)."</strong>'", "success"); } else { mysqli_stmt_close($stmt); redirectWithMessage("⚠ Error updating expense category.", "danger"); } }
}

// ── Delete expense category ───────────────────────────────────────────────────
if ($_POST && isset($_POST['delete_expense_category'])) {
    $category_id = intval($_POST['category_id']); $category_name = trim($_POST['category_name']);
    if ($category_id > 0) { $stmt = mysqli_prepare($conn, "DELETE FROM expense_categories WHERE id = ?"); if ($stmt) { mysqli_stmt_bind_param($stmt, "i", $category_id); if (mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); redirectWithMessage("✓ Expense category '<strong>".htmlspecialchars($category_name)."</strong>' deleted!", "success"); } else { mysqli_stmt_close($stmt); redirectWithMessage("⚠ Error deleting expense category.", "danger"); } } }
}

// ── Add income category ───────────────────────────────────────────────────────
if ($_POST && isset($_POST['add_income_category'])) {
    $category = trim($_POST['category']); $description = trim($_POST['description']);
    if (empty($category)) redirectWithMessage("Category name cannot be empty!", "warning");
    $check_stmt = mysqli_prepare($conn, "SELECT id FROM income_categories WHERE name = ?");
    mysqli_stmt_bind_param($check_stmt, "s", $category); mysqli_stmt_execute($check_stmt);
    if (mysqli_num_rows(mysqli_stmt_get_result($check_stmt)) > 0) { mysqli_stmt_close($check_stmt); redirectWithMessage("Income category with this name already exists!", "warning"); }
    mysqli_stmt_close($check_stmt);
    $stmt = mysqli_prepare($conn, "INSERT INTO income_categories (name, description) VALUES (?, ?)");
    if ($stmt) { mysqli_stmt_bind_param($stmt, "ss", $category, $description); if (mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); redirectWithMessage("✓ Income category '<strong>".htmlspecialchars($category)."</strong>' added!", "success"); } else { mysqli_stmt_close($stmt); redirectWithMessage("⚠ Error adding income category.", "danger"); } }
}

// ── Update income category ────────────────────────────────────────────────────
if ($_POST && isset($_POST['update_income_category'])) {
    $category_id = intval($_POST['category_id']); $category = trim($_POST['category']); $description = trim($_POST['description']); $old_name = trim($_POST['old_category_name']);
    if (empty($category)) redirectWithMessage("Category name cannot be empty!", "warning");
    $check_stmt = mysqli_prepare($conn, "SELECT id FROM income_categories WHERE name = ? AND id != ?");
    mysqli_stmt_bind_param($check_stmt, "si", $category, $category_id); mysqli_stmt_execute($check_stmt);
    if (mysqli_num_rows(mysqli_stmt_get_result($check_stmt)) > 0) { mysqli_stmt_close($check_stmt); redirectWithMessage("⚠ Another income category with this name already exists!", "warning"); }
    mysqli_stmt_close($check_stmt);
    $stmt = mysqli_prepare($conn, "UPDATE income_categories SET name = ?, description = ? WHERE id = ?");
    if ($stmt) { mysqli_stmt_bind_param($stmt, "ssi", $category, $description, $category_id); if (mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); redirectWithMessage("✓ Income category updated from '<strong>".htmlspecialchars($old_name)."</strong>' to '<strong>".htmlspecialchars($category)."</strong>'", "success"); } else { mysqli_stmt_close($stmt); redirectWithMessage("⚠ Error updating income category.", "danger"); } }
}

// ── Delete income category ────────────────────────────────────────────────────
if ($_POST && isset($_POST['delete_income_category'])) {
    $category_id = intval($_POST['category_id']); $category_name = trim($_POST['category_name']);
    if ($category_id > 0) { $stmt = mysqli_prepare($conn, "DELETE FROM income_categories WHERE id = ?"); if ($stmt) { mysqli_stmt_bind_param($stmt, "i", $category_id); if (mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); redirectWithMessage("✓ Income category '<strong>".htmlspecialchars($category_name)."</strong>' deleted!", "success"); } else { mysqli_stmt_close($stmt); redirectWithMessage("⚠ Error deleting income category.", "danger"); } } }
}

include 'navbar.php';

$message     = $_SESSION['message']      ?? '';
$messageType = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// Fetch data
$branches = $expenseCategories = $incomeCategories = [];
$r = mysqli_query($conn, "SELECT * FROM branches ORDER BY created_at DESC");
if ($r) while ($row = mysqli_fetch_assoc($r)) $branches[] = $row;
$r = mysqli_query($conn, "SELECT * FROM expense_categories ORDER BY created_at DESC");
if ($r) while ($row = mysqli_fetch_assoc($r)) $expenseCategories[] = $row;
$r = mysqli_query($conn, "SELECT * FROM income_categories ORDER BY created_at DESC");
if ($r) while ($row = mysqli_fetch_assoc($r)) $incomeCategories[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Branch & Category Management — Rajaiswari</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            font-size: 13px; background: var(--violet-bg); color: var(--violet); flex-shrink: 0;
        }
        .tb-title { font-size: 1.0625rem; font-weight: 700; color: var(--t1); }
        .tb-sub   { font-size: .78rem; color: var(--t4); }
        .tb-right { margin-left: auto; display: flex; gap: 7px; align-items: center; }

        /* ── Buttons ───────────────────────────── */
        .btn-pos {
            display: inline-flex; align-items: center; gap: 6px;
            height: 32px; padding: 0 13px;
            border: none; border-radius: var(--rs);
            font-family: inherit; font-size: .8125rem; font-weight: 600;
            cursor: pointer; transition: all .15s; text-decoration: none; white-space: nowrap;
        }
        .btn-ghost  { background: var(--surface); color: var(--t2); border: 1.5px solid var(--border); }
        .btn-ghost:hover  { background: var(--s2); border-color: #9ca3af; color: var(--t1); }
        .btn-blue   { background: var(--blue);  color: #fff; border: none; }
        .btn-blue:hover   { background: #1d4ed8; color: #fff; }
        .btn-green  { background: var(--green); color: #fff; border: none; }
        .btn-green:hover  { background: #047857; color: #fff; }
        .btn-red    { background: var(--red);   color: #fff; border: none; }
        .btn-red:hover    { background: #b91c1c; color: #fff; }

        /* ── Main ──────────────────────────────── */
        .main { flex: 1; padding: 20px 22px 60px; display: flex; flex-direction: column; gap: 16px; max-width: 900px; }

        /* ── Alert ─────────────────────────────── */
        .pos-alert {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 12px 16px; border-radius: var(--rs);
            font-size: .875rem; font-weight: 500;
        }
        .pos-alert.success { background: var(--green-bg); border: 1px solid var(--green-b); border-left: 3px solid var(--green); color: #065f46; }
        .pos-alert.danger  { background: var(--red-bg);   border: 1px solid var(--red-b);   border-left: 3px solid var(--red);   color: #991b1b; }
        .pos-alert.warning { background: var(--amber-bg); border: 1px solid var(--amber-b); border-left: 3px solid var(--amber); color: #92400e; }
        .pos-alert .close-btn { margin-left:auto; background:none; border:none; cursor:pointer; font-size:.75rem; color:inherit; opacity:.6; padding:0; }
        .pos-alert .close-btn:hover { opacity:1; }

        /* ── Tabs ──────────────────────────────── */
        .tab-nav {
            display: flex; gap: 0;
            border-bottom: 2px solid var(--border); background: var(--surface);
            border-radius: var(--r) var(--r) 0 0; overflow: hidden;
        }
        .tab-btn {
            display: flex; align-items: center; gap: 7px;
            padding: 11px 18px; border: none; background: transparent;
            font-family: inherit; font-size: .875rem; font-weight: 600;
            color: var(--t3); cursor: pointer; position: relative;
            transition: color .15s; white-space: nowrap;
        }
        .tab-btn::after {
            content: ''; position: absolute; bottom: -2px; left: 0; right: 0; height: 2px;
            background: transparent; transition: background .15s;
        }
        .tab-btn:hover { color: var(--t1); }
        .tab-btn.active { color: var(--blue); }
        .tab-btn.active::after { background: var(--blue); }
        .tab-btn.tb-green.active { color: var(--green); }
        .tab-btn.tb-green.active::after { background: var(--green); }
        .tab-btn.tb-red.active { color: var(--red); }
        .tab-btn.tb-red.active::after { background: var(--red); }

        .tab-count {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 20px; height: 18px; padding: 0 5px;
            border-radius: 9px; font-size: .7rem; font-weight: 800;
        }
        .tc-blue  { background: var(--blue-bg);  color: var(--blue);  }
        .tc-green { background: var(--green-bg); color: var(--green); }
        .tc-red   { background: var(--red-bg);   color: var(--red);   }

        .tab-panel { display: none; }
        .tab-panel.active { display: flex; flex-direction: column; gap: 14px; }

        /* ── Section card ──────────────────────── */
        .sec {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--r); box-shadow: var(--sh); overflow: hidden;
        }
        .sec-hd {
            display: flex; align-items: center; gap: 9px;
            padding: 11px 18px; background: var(--s2); border-bottom: 1px solid var(--bsoft);
        }
        .sec-ico {
            width: 26px; height: 26px; border-radius: var(--rs);
            display: flex; align-items: center; justify-content: center; font-size: 11px; flex-shrink: 0;
        }
        .si-bl { background: var(--blue-bg);  color: var(--blue);  }
        .si-gr { background: var(--green-bg); color: var(--green); }
        .si-rd { background: var(--red-bg);   color: var(--red);   }
        .sec-title { font-size: .875rem; font-weight: 700; color: var(--t1); }
        .sec-title-count { margin-left: auto; }
        .sec-body { padding: 18px; }

        /* ── Add form ──────────────────────────── */
        .add-form-row { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
        .add-form-row .f-name  { flex: 0 0 200px; }
        .add-form-row .f-desc  { flex: 1; min-width: 160px; }
        .add-form-row .f-btn   { flex-shrink: 0; }

        .lbl { display: block; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--t3); margin-bottom: 5px; }
        .lbl .req { color: var(--red); margin-left: 2px; }
        .fc {
            width: 100%; height: 36px; padding: 0 10px;
            border: 1.5px solid var(--border); border-radius: var(--rs);
            font-family: inherit; font-size: .875rem; color: var(--t2);
            background: var(--surface); outline: none; transition: border-color .15s;
        }
        .fc:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }

        /* ── Item list ─────────────────────────── */
        .item-list { display: flex; flex-direction: column; }
        .item-row {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 18px; border-bottom: 1px solid var(--bsoft);
            transition: background .12s;
        }
        .item-row:last-child { border-bottom: none; }
        .item-row:hover { background: #f8faff; }

        .item-name { font-weight: 700; color: var(--t1); font-size: .875rem; }
        .item-desc { font-size: .78rem; color: var(--t4); margin-top: 1px; }
        .item-date { font-size: .75rem; color: var(--t4); margin-top: 2px; }
        .item-info { flex: 1; }
        .item-actions { display: flex; gap: 5px; flex-shrink: 0; }

        .act-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 28px; height: 28px; border-radius: var(--rs); border: 1.5px solid;
            font-size: .72rem; cursor: pointer; transition: all .15s; background: var(--surface);
        }
        .act-edit-bl { border-color: var(--blue-b); color: var(--blue); }
        .act-edit-bl:hover { background: var(--blue-bg); }
        .act-edit-gr { border-color: var(--green-b); color: var(--green); }
        .act-edit-gr:hover { background: var(--green-bg); }
        .act-del { border-color: var(--red-b); color: var(--red); }
        .act-del:hover { background: var(--red-bg); }

        /* ── Empty state ───────────────────────── */
        .empty-state { text-align: center; padding: 48px 20px; color: var(--t4); }
        .empty-state i { font-size: 2rem; display: block; margin-bottom: 10px; opacity: .2; }
        .empty-state p { font-size: .875rem; }

        /* ── Modal ─────────────────────────────── */
        .modal-content { border: 1px solid var(--border); border-radius: var(--r); box-shadow: 0 8px 32px rgba(0,0,0,.12); }
        .modal-header  { background: var(--s2); border-bottom: 1px solid var(--border); padding: 14px 18px; border-radius: var(--r) var(--r) 0 0; }
        .modal-hd-ico  {
            width: 28px; height: 28px; border-radius: 7px;
            display: inline-flex; align-items: center; justify-content: center; font-size: 11px; margin-right: 8px;
        }
        .modal-title   { font-size: .9375rem; font-weight: 700; color: var(--t1); display: flex; align-items: center; }
        .modal-body    { padding: 18px; display: flex; flex-direction: column; gap: 13px; }
        .modal-footer  { padding: 12px 18px; background: var(--s2); border-top: 1px solid var(--border); gap: 7px; }

        .field-grid-2  { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media (max-width: 500px) { .field-grid-2 { grid-template-columns: 1fr; } }

        .created-info {
            display: flex; align-items: center; gap: 7px; padding: 9px 12px;
            background: var(--blue-bg); border: 1px solid var(--blue-b);
            border-radius: var(--rs); font-size: .8rem; color: #1e40af;
        }

        /* ── Responsive ────────────────────────── */
        @media (max-width: 991.98px) {
            .page-shell { margin-left: 0; }
            .top-bar    { top: 52px; }
            .main       { padding: 14px 14px 50px; max-width: 100%; }
            .add-form-row .f-name, .add-form-row .f-desc { flex: 1 1 100%; }
        }
    </style>
</head>
<body>

<div class="page-shell">

    <!-- Top bar -->
    <header class="top-bar">
        <div class="tb-ico"><i class="fas fa-sliders"></i></div>
        <div>
            <div class="tb-title">Management Center</div>
            <div class="tb-sub">Branches, income &amp; expense categories</div>
        </div>
        <div class="tb-right">
            <a href="account.php" class="btn-pos btn-ghost">
                <i class="fas fa-arrow-left" style="font-size:.6rem;"></i> Finance Panel
            </a>
        </div>
    </header>

    <div class="main">

        <!-- Flash alert -->
        <?php if (!empty($message)): ?>
        <div class="pos-alert <?= $messageType ?>" id="flashAlert">
            <i class="fas <?= $messageType === 'success' ? 'fa-circle-check' : ($messageType === 'danger' ? 'fa-circle-xmark' : 'fa-triangle-exclamation') ?>" style="font-size:.9rem;flex-shrink:0;margin-top:1px;"></i>
            <span><?= $message ?></span>
            <button class="close-btn" onclick="this.closest('.pos-alert').remove()"><i class="fas fa-xmark"></i></button>
        </div>
        <?php endif; ?>

        <!-- Tabs + content wrapper -->
        <div class="sec" style="overflow:visible;">

            <!-- Tab nav -->
            <div class="tab-nav">
                <button class="tab-btn active" onclick="switchTab('branches', this)">
                    <i class="fas fa-code-branch" style="font-size:.65rem;"></i> Branches
                    <span class="tab-count tc-blue"><?= count($branches) ?></span>
                </button>
                <button class="tab-btn tb-green" onclick="switchTab('income', this)">
                    <i class="fas fa-arrow-trend-up" style="font-size:.65rem;"></i> Income Categories
                    <span class="tab-count tc-green"><?= count($incomeCategories) ?></span>
                </button>
                <button class="tab-btn tb-red" onclick="switchTab('expenses', this)">
                    <i class="fas fa-arrow-trend-down" style="font-size:.65rem;"></i> Expense Categories
                    <span class="tab-count tc-red"><?= count($expenseCategories) ?></span>
                </button>
            </div>

            <!-- ═══ BRANCHES ══════════════════════════════════════════════════ -->
            <div id="tab-branches" class="tab-panel active" style="padding:18px;gap:14px;">

                <!-- Add form -->
                <div class="sec">
                    <div class="sec-hd">
                        <span class="sec-ico si-bl"><i class="fas fa-plus"></i></span>
                        <span class="sec-title">Add New Branch</span>
                    </div>
                    <div class="sec-body">
                        <form method="POST" action="">
                            <div class="add-form-row">
                                <div class="f-name">
                                    <label class="lbl">Branch Name <span class="req">*</span></label>
                                    <input type="text" name="branch_name" class="fc" placeholder="e.g. Main Branch" required maxlength="100">
                                </div>
                                <div class="f-btn">
                                    <button type="submit" name="add_branch" class="btn-pos btn-blue" style="height:36px;">
                                        <i class="fas fa-plus" style="font-size:.6rem;"></i> Add Branch
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- List -->
                <div class="sec">
                    <div class="sec-hd">
                        <span class="sec-ico si-bl"><i class="fas fa-list"></i></span>
                        <span class="sec-title">All Branches</span>
                        <span class="sec-title-count tab-count tc-blue"><?= count($branches) ?></span>
                    </div>
                    <?php if (!empty($branches)): ?>
                    <div class="item-list">
                        <?php foreach ($branches as $branch): ?>
                        <div class="item-row">
                            <div class="item-info">
                                <div class="item-name"><i class="fas fa-code-branch" style="font-size:.65rem;color:var(--blue);margin-right:6px;"></i><?= htmlspecialchars($branch['name']) ?></div>
                                <div class="item-date"><i class="fas fa-calendar-days" style="font-size:.6rem;margin-right:4px;"></i>Created <?= date('M d, Y', strtotime($branch['created_at'])) ?></div>
                            </div>
                            <div class="item-actions">
                                <button type="button" class="act-btn act-edit-bl" title="Edit" data-bs-toggle="modal" data-bs-target="#editBranch<?= $branch['id'] ?>"><i class="fas fa-pen"></i></button>
                                <button type="button" class="act-btn act-del" title="Delete" onclick="confirmDeleteBranch(<?= $branch['id'] ?>, '<?= htmlspecialchars(addslashes($branch['name'])) ?>')"><i class="fas fa-trash-can"></i></button>
                            </div>
                        </div>

                        <!-- Edit Branch Modal -->
                        <div class="modal fade" id="editBranch<?= $branch['id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><span class="modal-hd-ico" style="background:var(--blue-bg);color:var(--blue);"><i class="fas fa-pen"></i></span>Edit Branch</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" action="">
                                        <div class="modal-body">
                                            <input type="hidden" name="branch_id"       value="<?= $branch['id'] ?>">
                                            <input type="hidden" name="old_branch_name" value="<?= htmlspecialchars($branch['name']) ?>">
                                            <div>
                                                <label class="lbl">Branch Name <span class="req">*</span></label>
                                                <input type="text" name="branch_name" class="fc" value="<?= htmlspecialchars($branch['name']) ?>" required maxlength="100">
                                            </div>
                                            <div class="created-info"><i class="fas fa-circle-info" style="font-size:.75rem;"></i> Created <?= date('F d, Y', strtotime($branch['created_at'])) ?></div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn-pos btn-ghost" data-bs-dismiss="modal"><i class="fas fa-xmark" style="font-size:.6rem;"></i> Cancel</button>
                                            <button type="submit" name="update_branch" class="btn-pos btn-blue"><i class="fas fa-floppy-disk" style="font-size:.6rem;"></i> Save</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="empty-state"><i class="fas fa-inbox"></i><p>No branches yet. Add one above.</p></div>
                    <?php endif; ?>
                </div>

            </div><!-- /tab-branches -->

            <!-- ═══ INCOME CATEGORIES ════════════════════════════════════════ -->
            <div id="tab-income" class="tab-panel" style="padding:18px;gap:14px;">

                <div class="sec">
                    <div class="sec-hd">
                        <span class="sec-ico si-gr"><i class="fas fa-plus"></i></span>
                        <span class="sec-title">Add New Income Category</span>
                    </div>
                    <div class="sec-body">
                        <form method="POST" action="">
                            <div class="add-form-row">
                                <div class="f-name">
                                    <label class="lbl">Category Name <span class="req">*</span></label>
                                    <input type="text" name="category" class="fc" placeholder="e.g. Sales, Services" required maxlength="100">
                                </div>
                                <div class="f-desc">
                                    <label class="lbl">Description</label>
                                    <input type="text" name="description" class="fc" placeholder="Optional description" maxlength="255">
                                </div>
                                <div class="f-btn">
                                    <button type="submit" name="add_income_category" class="btn-pos btn-green" style="height:36px;">
                                        <i class="fas fa-plus" style="font-size:.6rem;"></i> Add
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="sec">
                    <div class="sec-hd">
                        <span class="sec-ico si-gr"><i class="fas fa-list"></i></span>
                        <span class="sec-title">All Income Categories</span>
                        <span class="sec-title-count tab-count tc-green"><?= count($incomeCategories) ?></span>
                    </div>
                    <?php if (!empty($incomeCategories)): ?>
                    <div class="item-list">
                        <?php foreach ($incomeCategories as $cat): ?>
                        <div class="item-row">
                            <div class="item-info">
                                <div class="item-name"><i class="fas fa-tag" style="font-size:.6rem;color:var(--green);margin-right:6px;"></i><?= htmlspecialchars($cat['name']) ?></div>
                                <?php if (!empty($cat['description'])): ?><div class="item-desc"><?= htmlspecialchars($cat['description']) ?></div><?php endif; ?>
                                <div class="item-date"><i class="fas fa-calendar-days" style="font-size:.6rem;margin-right:4px;"></i>Created <?= date('M d, Y', strtotime($cat['created_at'])) ?></div>
                            </div>
                            <div class="item-actions">
                                <button type="button" class="act-btn act-edit-gr" title="Edit" data-bs-toggle="modal" data-bs-target="#editIncome<?= $cat['id'] ?>"><i class="fas fa-pen"></i></button>
                                <button type="button" class="act-btn act-del" title="Delete" onclick="confirmDeleteIncomeCategory(<?= $cat['id'] ?>, '<?= htmlspecialchars(addslashes($cat['name'])) ?>')"><i class="fas fa-trash-can"></i></button>
                            </div>
                        </div>

                        <!-- Edit Income Modal -->
                        <div class="modal fade" id="editIncome<?= $cat['id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><span class="modal-hd-ico" style="background:var(--green-bg);color:var(--green);"><i class="fas fa-pen"></i></span>Edit Income Category</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" action="">
                                        <div class="modal-body">
                                            <input type="hidden" name="category_id"       value="<?= $cat['id'] ?>">
                                            <input type="hidden" name="old_category_name" value="<?= htmlspecialchars($cat['name']) ?>">
                                            <div>
                                                <label class="lbl">Category Name <span class="req">*</span></label>
                                                <input type="text" name="category" class="fc" value="<?= htmlspecialchars($cat['name']) ?>" required maxlength="100">
                                            </div>
                                            <div>
                                                <label class="lbl">Description</label>
                                                <input type="text" name="description" class="fc" value="<?= htmlspecialchars($cat['description'] ?? '') ?>" maxlength="255">
                                            </div>
                                            <div class="created-info"><i class="fas fa-circle-info" style="font-size:.75rem;"></i> Created <?= date('F d, Y', strtotime($cat['created_at'])) ?></div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn-pos btn-ghost" data-bs-dismiss="modal"><i class="fas fa-xmark" style="font-size:.6rem;"></i> Cancel</button>
                                            <button type="submit" name="update_income_category" class="btn-pos btn-green"><i class="fas fa-floppy-disk" style="font-size:.6rem;"></i> Save</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="empty-state"><i class="fas fa-inbox"></i><p>No income categories yet. Add one above.</p></div>
                    <?php endif; ?>
                </div>

            </div><!-- /tab-income -->

            <!-- ═══ EXPENSE CATEGORIES ═══════════════════════════════════════ -->
            <div id="tab-expenses" class="tab-panel" style="padding:18px;gap:14px;">

                <div class="sec">
                    <div class="sec-hd">
                        <span class="sec-ico si-rd"><i class="fas fa-plus"></i></span>
                        <span class="sec-title">Add New Expense Category</span>
                    </div>
                    <div class="sec-body">
                        <form method="POST" action="">
                            <div class="add-form-row">
                                <div class="f-name">
                                    <label class="lbl">Category Name <span class="req">*</span></label>
                                    <input type="text" name="category" class="fc" placeholder="e.g. Rent, Utilities" required maxlength="100">
                                </div>
                                <div class="f-desc">
                                    <label class="lbl">Description</label>
                                    <input type="text" name="description" class="fc" placeholder="Optional description" maxlength="255">
                                </div>
                                <div class="f-btn">
                                    <button type="submit" name="add_expense_category" class="btn-pos btn-red" style="height:36px;">
                                        <i class="fas fa-plus" style="font-size:.6rem;"></i> Add
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="sec">
                    <div class="sec-hd">
                        <span class="sec-ico si-rd"><i class="fas fa-list"></i></span>
                        <span class="sec-title">All Expense Categories</span>
                        <span class="sec-title-count tab-count tc-red"><?= count($expenseCategories) ?></span>
                    </div>
                    <?php if (!empty($expenseCategories)): ?>
                    <div class="item-list">
                        <?php foreach ($expenseCategories as $cat): ?>
                        <div class="item-row">
                            <div class="item-info">
                                <div class="item-name"><i class="fas fa-tag" style="font-size:.6rem;color:var(--red);margin-right:6px;"></i><?= htmlspecialchars($cat['name']) ?></div>
                                <?php if (!empty($cat['description'])): ?><div class="item-desc"><?= htmlspecialchars($cat['description']) ?></div><?php endif; ?>
                                <div class="item-date"><i class="fas fa-calendar-days" style="font-size:.6rem;margin-right:4px;"></i>Created <?= date('M d, Y', strtotime($cat['created_at'])) ?></div>
                            </div>
                            <div class="item-actions">
                                <button type="button" class="act-btn act-del" title="Edit" data-bs-toggle="modal" data-bs-target="#editExpense<?= $cat['id'] ?>" style="border-color:var(--red-b);color:var(--red);"><i class="fas fa-pen"></i></button>
                                <button type="button" class="act-btn act-del" title="Delete" onclick="confirmDeleteExpenseCategory(<?= $cat['id'] ?>, '<?= htmlspecialchars(addslashes($cat['name'])) ?>')"><i class="fas fa-trash-can"></i></button>
                            </div>
                        </div>

                        <!-- Edit Expense Modal -->
                        <div class="modal fade" id="editExpense<?= $cat['id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><span class="modal-hd-ico" style="background:var(--red-bg);color:var(--red);"><i class="fas fa-pen"></i></span>Edit Expense Category</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" action="">
                                        <div class="modal-body">
                                            <input type="hidden" name="category_id"       value="<?= $cat['id'] ?>">
                                            <input type="hidden" name="old_category_name" value="<?= htmlspecialchars($cat['name']) ?>">
                                            <div>
                                                <label class="lbl">Category Name <span class="req">*</span></label>
                                                <input type="text" name="category" class="fc" value="<?= htmlspecialchars($cat['name']) ?>" required maxlength="100">
                                            </div>
                                            <div>
                                                <label class="lbl">Description</label>
                                                <input type="text" name="description" class="fc" value="<?= htmlspecialchars($cat['description'] ?? '') ?>" maxlength="255">
                                            </div>
                                            <div class="created-info"><i class="fas fa-circle-info" style="font-size:.75rem;"></i> Created <?= date('F d, Y', strtotime($cat['created_at'])) ?></div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn-pos btn-ghost" data-bs-dismiss="modal"><i class="fas fa-xmark" style="font-size:.6rem;"></i> Cancel</button>
                                            <button type="submit" name="update_expense_category" class="btn-pos btn-red"><i class="fas fa-floppy-disk" style="font-size:.6rem;"></i> Save</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="empty-state"><i class="fas fa-inbox"></i><p>No expense categories yet. Add one above.</p></div>
                    <?php endif; ?>
                </div>

            </div><!-- /tab-expenses -->

        </div><!-- /sec (tab wrapper) -->

    </div><!-- /main -->
</div><!-- /page-shell -->

<!-- Hidden delete forms -->
<form method="POST" id="deleteBranchForm"          style="display:none;"><input type="hidden" name="branch_id"    id="deleteBranchId"><input type="hidden" name="branch_name"    id="deleteBranchName"><input type="hidden" name="delete_branch"            value="1"></form>
<form method="POST" id="deleteIncomeCategoryForm"  style="display:none;"><input type="hidden" name="category_id" id="deleteIncomeCategoryId"><input type="hidden" name="category_name" id="deleteIncomeCategoryName"><input type="hidden" name="delete_income_category"  value="1"></form>
<form method="POST" id="deleteExpenseCategoryForm" style="display:none;"><input type="hidden" name="category_id" id="deleteExpenseCategoryId"><input type="hidden" name="category_name" id="deleteExpenseCategoryName"><input type="hidden" name="delete_expense_category" value="1"></form>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
    // ── Tab switching ──────────────────────────────────────────────────────────
    function switchTab(name, btn) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('tab-' + name).classList.add('active');
    }

    // ── Delete confirms (SweetAlert2, logic identical) ─────────────────────────
    function confirmDeleteBranch(id, name) {
        Swal.fire({ icon:'warning', title:'Delete Branch?', html:`<p><strong>${name}</strong></p><div class="alert alert-danger text-start">All transactions for this branch will also be deleted!</div>`, showCancelButton:true, confirmButtonColor:'#dc2626', cancelButtonColor:'#6b7280', confirmButtonText:'Yes, Delete', cancelButtonText:'Cancel', reverseButtons:true, focusCancel:true }).then(r => { if (r.isConfirmed) { Swal.fire({ title:'Deleting...', allowOutsideClick:false, didOpen:() => Swal.showLoading() }); document.getElementById('deleteBranchId').value = id; document.getElementById('deleteBranchName').value = name; document.getElementById('deleteBranchForm').submit(); } });
    }
    function confirmDeleteIncomeCategory(id, name) {
        Swal.fire({ icon:'warning', title:'Delete Income Category?', html:`<p><strong>${name}</strong></p><div class="alert alert-danger text-start">All income records using this category will be deleted!</div>`, showCancelButton:true, confirmButtonColor:'#dc2626', cancelButtonColor:'#6b7280', confirmButtonText:'Yes, Delete', cancelButtonText:'Cancel', reverseButtons:true, focusCancel:true }).then(r => { if (r.isConfirmed) { Swal.fire({ title:'Deleting...', allowOutsideClick:false, didOpen:() => Swal.showLoading() }); document.getElementById('deleteIncomeCategoryId').value = id; document.getElementById('deleteIncomeCategoryName').value = name; document.getElementById('deleteIncomeCategoryForm').submit(); } });
    }
    function confirmDeleteExpenseCategory(id, name) {
        Swal.fire({ icon:'warning', title:'Delete Expense Category?', html:`<p><strong>${name}</strong></p><div class="alert alert-danger text-start">All expense records using this category will be deleted!</div>`, showCancelButton:true, confirmButtonColor:'#dc2626', cancelButtonColor:'#6b7280', confirmButtonText:'Yes, Delete', cancelButtonText:'Cancel', reverseButtons:true, focusCancel:true }).then(r => { if (r.isConfirmed) { Swal.fire({ title:'Deleting...', allowOutsideClick:false, didOpen:() => Swal.showLoading() }); document.getElementById('deleteExpenseCategoryId').value = id; document.getElementById('deleteExpenseCategoryName').value = name; document.getElementById('deleteExpenseCategoryForm').submit(); } });
    }

    // ── Auto-dismiss flash ─────────────────────────────────────────────────────
    setTimeout(() => { const a = document.getElementById('flashAlert'); if (a) a.remove(); }, 8000);
</script>
</body>
</html>