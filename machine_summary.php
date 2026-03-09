<?php
require_once 'mydb.php';
require_once 'auth.php';

// Handle ADD machine request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_machine'])) {
    $machine_type = trim($_POST['machine_type']);
    $stock        = !empty($_POST['add_stock'])        ? intval($_POST['add_stock'])        : NULL;
    $ordered      = !empty($_POST['add_ordered'])      ? intval($_POST['add_ordered'])      : NULL;
    $delivered    = !empty($_POST['add_delivered'])    ? intval($_POST['add_delivered'])    : NULL;
    $maintenance  = !empty($_POST['add_maintenance'])  ? intval($_POST['add_maintenance'])  : NULL;

    if (!empty($machine_type)) {
        $add_sql = "INSERT INTO machines_summary (machine_type, stock, ordered, delivered, maintenance) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($add_sql);
        $stmt->bind_param("siiii", $machine_type, $stock, $ordered, $delivered, $maintenance);
        if ($stmt->execute()) { $message = "Machine added successfully!";        $message_type = "success"; }
        else                  { $message = "Error adding machine: " . $conn->error; $message_type = "danger";  }
        $stmt->close();
    } else {
        $message = "Machine type is required!"; $message_type = "warning";
    }
    header("Location: " . $_SERVER['PHP_SELF']); exit();
}

// Handle UPDATE request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $id          = intval($_POST['id']);
    $stock       = !empty($_POST['stock'])       ? intval($_POST['stock'])       : NULL;
    $ordered     = !empty($_POST['ordered'])     ? intval($_POST['ordered'])     : NULL;
    $delivered   = !empty($_POST['delivered'])   ? intval($_POST['delivered'])   : NULL;
    $maintenance = !empty($_POST['maintenance']) ? intval($_POST['maintenance']) : NULL;

    $update_sql = "UPDATE machines_summary SET stock=?, ordered=?, delivered=?, maintenance=? WHERE id=?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("iiiii", $stock, $ordered, $delivered, $maintenance, $id);
    if ($stmt->execute()) { $message = "Record updated successfully!";          $message_type = "success"; }
    else                  { $message = "Error updating record: " . $conn->error; $message_type = "danger";  }
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF'] . (isset($_GET['search']) ? '?search=' . urlencode($_GET['search']) : '')); exit();
}

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $id = intval($_POST['delete_id']);
    $delete_sql = "DELETE FROM machines_summary WHERE id=?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) { $message = "Machine deleted successfully!";          $message_type = "success"; }
    else                  { $message = "Error deleting machine: " . $conn->error; $message_type = "danger";  }
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF'] . (isset($_GET['search']) ? '?search=' . urlencode($_GET['search']) : '')); exit();
}

include 'navbar.php';

// Get search parameter
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Fetch data
if (!empty($search)) {
    $sql = "SELECT * FROM machines_summary WHERE machine_type LIKE ? ORDER BY machine_type";
    $stmt = $conn->prepare($sql);
    $search_param = "%$search%";
    $stmt->bind_param("s", $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql    = "SELECT * FROM machines_summary ORDER BY machine_type";
    $result = $conn->query($sql);
}

// Calculate totals
$totals_sql = "SELECT COALESCE(SUM(stock),0) as total_stock, COALESCE(SUM(ordered),0) as total_ordered,
               COALESCE(SUM(delivered),0) as total_delivered, COALESCE(SUM(maintenance),0) as total_maintenance,
               COUNT(*) as total_machines FROM machines_summary";
if (!empty($search)) $totals_sql .= " WHERE machine_type LIKE '%$search%'";
$totals = $conn->query($totals_sql)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Machine Summary — Rajaiswari</title>
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
            --violet:  #7c3aed; --violet-bg:#f5f3ff;
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
        .tb-right { margin-left: auto; display: flex; gap: 7px; align-items: center; }

        /* ── Buttons ───────────────────────────── */
        .btn-pos {
            display: inline-flex; align-items: center; gap: 6px;
            height: 32px; padding: 0 13px;
            border: none; border-radius: var(--rs);
            font-family: inherit; font-size: .8125rem; font-weight: 600;
            cursor: pointer; transition: all .15s; text-decoration: none; white-space: nowrap;
        }
        .btn-ghost { background: var(--surface); color: var(--t2); border: 1.5px solid var(--border); }
        .btn-ghost:hover { background: var(--s2); border-color: #9ca3af; color: var(--t1); }
        .btn-blue  { background: var(--blue);  color: #fff; border: none; }
        .btn-blue:hover  { background: #1d4ed8; color: #fff; }
        .btn-green { background: var(--green); color: #fff; border: none; }
        .btn-green:hover { background: #047857; color: #fff; }
        .btn-green-sm {
            display: inline-flex; align-items: center; gap: 5px;
            height: 28px; padding: 0 10px;
            background: var(--green); color: #fff; border: none;
            border-radius: var(--rs); font-family: inherit; font-size: .76rem; font-weight: 700;
            cursor: pointer; transition: background .15s; white-space: nowrap;
        }
        .btn-green-sm:hover { background: #047857; }
        .btn-red-sm {
            display: inline-flex; align-items: center; justify-content: center;
            width: 28px; height: 28px;
            background: var(--red-bg); color: var(--red);
            border: 1.5px solid var(--red-b); border-radius: var(--rs);
            font-size: .72rem; cursor: pointer; transition: all .15s;
        }
        .btn-red-sm:hover { background: var(--red); color: #fff; }

        /* ── Main ──────────────────────────────── */
        .main { flex: 1; padding: 20px 22px 60px; display: flex; flex-direction: column; gap: 14px; }

        /* ── Alert ─────────────────────────────── */
        .pos-alert {
            display: flex; align-items: center; gap: 10px;
            padding: 11px 16px; border-radius: var(--rs);
            font-size: .875rem; font-weight: 500;
        }
        .pos-alert.success { background: var(--green-bg); border: 1px solid var(--green-b); border-left: 3px solid var(--green); color: #065f46; }
        .pos-alert.danger  { background: var(--red-bg);   border: 1px solid var(--red-b);   border-left: 3px solid var(--red);   color: #991b1b; }
        .pos-alert.warning { background: var(--amber-bg); border: 1px solid var(--amber-b); border-left: 3px solid var(--amber); color: #92400e; }
        .pos-alert .close-btn {
            margin-left: auto; background: none; border: none; cursor: pointer;
            font-size: .75rem; color: inherit; opacity: .6; padding: 0;
        }
        .pos-alert .close-btn:hover { opacity: 1; }

        /* ── Stat cards ────────────────────────── */
        .stat-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
        }
        @media (max-width: 1100px) { .stat-row { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 700px)  { .stat-row { grid-template-columns: 1fr 1fr; } }

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
        .sc-blue::before  { background: var(--blue);  }
        .sc-cyan::before  { background: var(--cyan);  }
        .sc-green::before { background: var(--green); }
        .sc-amber::before { background: var(--amber); }
        .sc-red::before   { background: var(--red);   }

        .stat-ico {
            width: 36px; height: 36px; border-radius: 8px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 13px;
        }
        .sc-blue  .stat-ico { background: var(--blue-bg);  color: var(--blue);  }
        .sc-cyan  .stat-ico { background: var(--cyan-bg);  color: var(--cyan);  }
        .sc-green .stat-ico { background: var(--green-bg); color: var(--green); }
        .sc-amber .stat-ico { background: var(--amber-bg); color: var(--amber); }
        .sc-red   .stat-ico { background: var(--red-bg);   color: var(--red);   }

        .stat-lbl { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--t4); margin-bottom: 3px; }
        .stat-val { font-size: 1.375rem; font-weight: 800; color: var(--t1); letter-spacing: -.02em; line-height: 1; font-family: 'DM Mono', monospace; }

        /* ── Card ──────────────────────────────── */
        .pos-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--r); box-shadow: var(--sh); overflow: hidden;
        }

        /* ── Filter bar ────────────────────────── */
        .filter-bar {
            display: flex; gap: 9px; align-items: flex-end;
            padding: 14px 18px; background: var(--s2); border-bottom: 1px solid var(--bsoft);
            flex-wrap: wrap;
        }
        .search-wrap { position: relative; flex: 1; min-width: 180px; }
        .search-wrap .si {
            position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
            font-size: .8rem; color: var(--t4); pointer-events: none;
        }
        .fc-search {
            width: 100%; height: 34px; padding: 0 10px 0 32px;
            border: 1.5px solid var(--border); border-radius: var(--rs);
            font-family: inherit; font-size: .875rem; color: var(--t2);
            background: var(--surface); outline: none; transition: border-color .15s;
        }
        .fc-search:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }

        /* ── Table ─────────────────────────────── */
        .pos-tbl { width: 100%; border-collapse: collapse; }
        .pos-tbl thead th {
            padding: 9px 10px; font-size: .71rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .07em;
            color: var(--t4); background: var(--s2);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        .pos-tbl thead th:first-child { padding-left: 18px; }
        .pos-tbl thead th.c { text-align: center; }

        .pos-tbl tbody td {
            padding: 9px 10px; font-size: .8375rem; color: var(--t2);
            border-bottom: 1px solid var(--bsoft); vertical-align: middle;
        }
        .pos-tbl tbody td:first-child { padding-left: 18px; }
        .pos-tbl tbody tr:last-child td { border-bottom: none; }
        .pos-tbl tbody tr:hover td { background: #f8faff; }

        /* editable number input */
        .num-input {
            width: 72px; height: 30px; padding: 0 6px;
            border: 1.5px solid var(--border); border-radius: var(--rs);
            font-family: 'DM Mono', monospace; font-size: .82rem; font-weight: 600;
            color: var(--t1); text-align: center; outline: none;
            transition: border-color .15s;
        }
        .num-input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }

        .machine-name { font-weight: 700; color: var(--t1); }
        .date-val     { font-size: .79rem; color: var(--t3); font-family: 'DM Mono', monospace; }
        .act-cell     { display: flex; gap: 5px; justify-content: center; }

        /* ── Totals footer ─────────────────────── */
        .pos-tbl tfoot td {
            padding: 10px 10px; font-size: .8375rem; font-weight: 800;
            background: var(--s2); border-top: 2px solid var(--border);
            font-family: 'DM Mono', monospace; white-space: nowrap;
        }
        .pos-tbl tfoot td:first-child {
            padding-left: 18px; font-family: 'DM Sans', sans-serif;
            font-size: .72rem; text-transform: uppercase; letter-spacing: .06em;
            color: var(--t3); font-weight: 700;
        }
        .pos-tbl tfoot td.c { text-align: center; }

        /* ── Empty state ───────────────────────── */
        .empty-state { text-align: center; padding: 56px 20px; color: var(--t4); }
        .empty-state i { font-size: 2rem; display: block; margin-bottom: 10px; opacity: .2; }
        .empty-state p { font-size: .875rem; }

        /* ── Modal ─────────────────────────────── */
        .modal-content { border: 1px solid var(--border); border-radius: var(--r); box-shadow: 0 8px 32px rgba(0,0,0,.12); }
        .modal-header  {
            background: var(--s2); border-bottom: 1px solid var(--border);
            padding: 14px 18px; border-radius: var(--r) var(--r) 0 0;
        }
        .modal-title   { font-size: .9375rem; font-weight: 700; color: var(--t1); }
        .modal-body    { padding: 18px; }
        .modal-footer  { padding: 12px 18px; background: var(--s2); border-top: 1px solid var(--border); gap: 7px; }

        .lbl { display: block; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--t3); margin-bottom: 5px; }
        .lbl .req { color: var(--red); margin-left: 2px; }
        .fc {
            width: 100%; height: 36px; padding: 0 10px;
            border: 1.5px solid var(--border); border-radius: var(--rs);
            font-family: inherit; font-size: .875rem; color: var(--t2);
            background: var(--surface); outline: none; transition: border-color .15s;
        }
        .fc:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }

        .field-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        .pos-alert-info {
            display: flex; align-items: flex-start; gap: 8px;
            padding: 10px 13px; margin-top: 4px;
            background: var(--blue-bg); border: 1px solid var(--blue-b);
            border-radius: var(--rs); font-size: .8rem; color: #1e40af;
        }

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
        <div class="tb-ico"><i class="fas fa-warehouse"></i></div>
        <div>
            <div class="tb-title">Machine Summary</div>
            <div class="tb-sub">Manage and monitor machine inventory</div>
        </div>
        <div class="tb-right">
            <button class="btn-pos btn-green" data-bs-toggle="modal" data-bs-target="#addMachineModal">
                <i class="fas fa-plus" style="font-size:.6rem;"></i> Add Machine
            </button>
            <a href="dashboard.php" class="btn-pos btn-ghost">
                <i class="fas fa-gauge-high" style="font-size:.6rem;"></i> Dashboard
            </a>
        </div>
    </header>

    <div class="main">

        <!-- Flash alert -->
        <?php if (isset($message)): ?>
        <div class="pos-alert <?= $message_type ?>" id="flashAlert">
            <i class="fas <?= $message_type === 'success' ? 'fa-circle-check' : ($message_type === 'danger' ? 'fa-circle-xmark' : 'fa-triangle-exclamation') ?>" style="font-size:.9rem;flex-shrink:0;"></i>
            <?= htmlspecialchars($message) ?>
            <button class="close-btn" onclick="document.getElementById('flashAlert').remove()">
                <i class="fas fa-xmark"></i>
            </button>
        </div>
        <?php endif; ?>

        <!-- Stat cards -->
        <div class="stat-row">
            <div class="stat-card sc-blue">
                <div class="stat-ico"><i class="fas fa-boxes-stacked"></i></div>
                <div>
                    <div class="stat-lbl">Total Stock</div>
                    <div class="stat-val"><?= number_format($totals['total_stock']) ?></div>
                </div>
            </div>
            <div class="stat-card sc-cyan">
                <div class="stat-ico"><i class="fas fa-cart-shopping"></i></div>
                <div>
                    <div class="stat-lbl">Total Ordered</div>
                    <div class="stat-val"><?= number_format($totals['total_ordered']) ?></div>
                </div>
            </div>
            <div class="stat-card sc-green">
                <div class="stat-ico"><i class="fas fa-truck"></i></div>
                <div>
                    <div class="stat-lbl">Delivered</div>
                    <div class="stat-val"><?= number_format($totals['total_delivered']) ?></div>
                </div>
            </div>
            <div class="stat-card sc-amber">
                <div class="stat-ico"><i class="fas fa-screwdriver-wrench"></i></div>
                <div>
                    <div class="stat-lbl">Maintenance</div>
                    <div class="stat-val"><?= number_format($totals['total_maintenance']) ?></div>
                </div>
            </div>
            <div class="stat-card sc-red">
                <div class="stat-ico"><i class="fas fa-gears"></i></div>
                <div>
                    <div class="stat-lbl">Total Machines</div>
                    <div class="stat-val"><?= number_format($totals['total_machines']) ?></div>
                </div>
            </div>
        </div>

        <!-- Main card -->
        <div class="pos-card">

            <!-- Filter bar -->
            <form method="GET" action="">
                <div class="filter-bar">
                    <div class="search-wrap">
                        <i class="fas fa-magnifying-glass si"></i>
                        <input type="text" name="search" class="fc-search"
                               placeholder="Search by machine type..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <button type="submit" class="btn-pos btn-blue" style="height:34px;">
                        <i class="fas fa-filter" style="font-size:.6rem;"></i> Filter
                    </button>
                    <?php if (!empty($search)): ?>
                    <a href="machine_summary.php" class="btn-pos btn-ghost" style="height:34px;">
                        <i class="fas fa-rotate-left" style="font-size:.6rem;"></i> Reset
                    </a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Table -->
            <div style="overflow-x:auto;">
                <table class="pos-tbl">
                    <thead>
                        <tr>
                            <th>Machine Type</th>
                            <th class="c">In Stock</th>
                            <th class="c">Ordered</th>
                            <th class="c">Delivered</th>
                            <th class="c">Maintenance</th>
                            <th class="c">Last Update</th>
                            <th class="c">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <form method="POST" action="" style="display:contents;">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <td><span class="machine-name"><?= htmlspecialchars($row['machine_type']) ?></span></td>
                                <td style="text-align:center;">
                                    <input type="number" class="num-input" name="stock"
                                           value="<?= $row['stock'] !== null ? $row['stock'] : '' ?>"
                                           placeholder="—" min="0">
                                </td>
                                <td style="text-align:center;">
                                    <input type="number" class="num-input" name="ordered"
                                           value="<?= $row['ordered'] !== null ? $row['ordered'] : '' ?>"
                                           placeholder="—" min="0">
                                </td>
                                <td style="text-align:center;">
                                    <input type="number" class="num-input" name="delivered"
                                           value="<?= $row['delivered'] !== null ? $row['delivered'] : '' ?>"
                                           placeholder="—" min="0">
                                </td>
                                <td style="text-align:center;">
                                    <input type="number" class="num-input" name="maintenance"
                                           value="<?= $row['maintenance'] !== null ? $row['maintenance'] : '' ?>"
                                           placeholder="—" min="0">
                                </td>
                                <td style="text-align:center;">
                                    <span class="date-val"><?= date('d M Y, H:i', strtotime($row['last_update'])) ?></span>
                                </td>
                                <td>
                                    <div class="act-cell">
                                        <button type="submit" name="update" class="btn-green-sm">
                                            <i class="fas fa-floppy-disk" style="font-size:.6rem;"></i> Save
                                        </button>
                            </form>
                                        <form method="POST" action="" style="display:inline;"
                                              onsubmit="return confirm('Delete this machine?');">
                                            <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                            <button type="submit" name="delete" class="btn-red-sm" title="Delete">
                                                <i class="fas fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>No machines found<?= !empty($search) ? ' matching your search' : '' ?>.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td><i class="fas fa-sigma" style="font-size:.6rem;margin-right:5px;"></i>Totals</td>
                            <td class="c"><?= number_format($totals['total_stock']) ?></td>
                            <td class="c"><?= number_format($totals['total_ordered']) ?></td>
                            <td class="c"><?= number_format($totals['total_delivered']) ?></td>
                            <td class="c"><?= number_format($totals['total_maintenance']) ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div><!-- /pos-card -->

    </div><!-- /main -->
</div><!-- /page-shell -->

<!-- Add Machine Modal -->
<div class="modal fade" id="addMachineModal" tabindex="-1" aria-labelledby="addMachineModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div style="display:flex;align-items:center;gap:9px;">
                    <div style="width:28px;height:28px;background:var(--green-bg);border-radius:7px;display:flex;align-items:center;justify-content:center;color:var(--green);font-size:11px;">
                        <i class="fas fa-plus"></i>
                    </div>
                    <h5 class="modal-title" id="addMachineModalLabel">Add New Machine</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body" style="display:flex;flex-direction:column;gap:13px;">
                    <div>
                        <label class="lbl">Machine Type <span class="req">*</span></label>
                        <input type="text" class="fc" id="machine_type" name="machine_type"
                               placeholder="e.g. CNC Lathe Machine" required>
                    </div>
                    <div class="field-grid-2">
                        <div>
                            <label class="lbl">Stock</label>
                            <input type="number" class="fc" id="add_stock" name="add_stock"
                                   placeholder="Leave empty for N/A" min="0">
                        </div>
                        <div>
                            <label class="lbl">Ordered</label>
                            <input type="number" class="fc" id="add_ordered" name="add_ordered"
                                   placeholder="Leave empty for N/A" min="0">
                        </div>
                    </div>
                    <div class="field-grid-2">
                        <div>
                            <label class="lbl">Delivered</label>
                            <input type="number" class="fc" id="add_delivered" name="add_delivered"
                                   placeholder="Leave empty for N/A" min="0">
                        </div>
                        <div>
                            <label class="lbl">Maintenance</label>
                            <input type="number" class="fc" id="add_maintenance" name="add_maintenance"
                                   placeholder="Leave empty for N/A" min="0">
                        </div>
                    </div>
                    <div class="pos-alert-info">
                        <i class="fas fa-circle-info" style="flex-shrink:0;margin-top:1px;"></i>
                        <span>Machine Type is required. Leave numeric fields empty to store as N/A.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-pos btn-ghost" data-bs-dismiss="modal">
                        <i class="fas fa-xmark" style="font-size:.6rem;"></i> Cancel
                    </button>
                    <button type="submit" name="add_machine" class="btn-pos btn-green">
                        <i class="fas fa-plus" style="font-size:.6rem;"></i> Add Machine
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-dismiss flash alert after 5s
    setTimeout(function() {
        const a = document.getElementById('flashAlert');
        if (a) a.remove();
    }, 5000);
</script>
</body>
</html>
<?php $conn->close(); ?>