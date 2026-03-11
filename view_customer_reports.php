<?php
require 'auth.php';
require 'mydb.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Customer Reports — Rajaiswari</title>
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
            font-size: 13px; background: var(--cyan-bg); color: var(--cyan); flex-shrink: 0;
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
        .btn-blue  { background: var(--blue);  color: #fff; }
        .btn-blue:hover  { background: #1d4ed8; color: #fff; }

        /* ── Main ──────────────────────────────── */
        .main { flex: 1; padding: 20px 22px 60px; display: flex; flex-direction: column; gap: 14px; }

        /* ── Cards ─────────────────────────────── */
        .pos-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--r); box-shadow: var(--sh); overflow: hidden;
        }

        /* ── Filter bar ────────────────────────── */
        .filter-bar {
            display: grid;
            grid-template-columns: 1fr auto auto auto;
            gap: 10px; align-items: end;
            padding: 14px 18px;
            background: var(--s2); border-bottom: 1px solid var(--bsoft);
        }
        @media (max-width: 700px) { .filter-bar { grid-template-columns: 1fr; } }

        .lbl { display: block; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--t3); margin-bottom: 5px; }

        .fc {
            width: 100%; height: 34px; padding: 0 10px;
            border: 1.5px solid var(--border); border-radius: var(--rs);
            font-family: inherit; font-size: .875rem; color: var(--t2);
            background: var(--surface); outline: none;
            transition: border-color .15s;
            appearance: none;
        }
        .fc:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
        select.fc {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='7' viewBox='0 0 10 7'%3E%3Cpath fill='%239ca3af' d='M0 0l5 7 5-7z'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 9px center; padding-right: 28px; cursor: pointer;
        }

        /* Search box with icon */
        .search-wrap { position: relative; }
        .search-wrap .fc { padding-left: 34px; }
        .search-wrap .si {
            position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
            font-size: .8rem; color: var(--t4); pointer-events: none;
        }

        /* ── Info bar ──────────────────────────── */
        .info-bar {
            display: flex; align-items: center; justify-content: space-between;
            padding: 9px 18px; font-size: .8rem; color: var(--t3); flex-wrap: wrap; gap: 6px;
        }
        .total-pill {
            display: inline-flex; align-items: center; gap: 5px;
            background: var(--cyan-bg); border: 1px solid var(--cyan-b);
            border-radius: 20px; padding: 3px 12px;
            font-size: .76rem; font-weight: 700; color: var(--cyan);
        }

        /* ── Table ─────────────────────────────── */
        .pos-tbl { width: 100%; border-collapse: collapse; }
        .pos-tbl thead th {
            padding: 9px 12px; font-size: .71rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .07em;
            color: var(--t4); background: var(--s2);
            border-bottom: 1px solid var(--border);
            white-space: nowrap; text-align: left;
        }
        .pos-tbl thead th:first-child { padding-left: 18px; }
        .pos-tbl thead th.c { text-align: center; }
        .pos-tbl thead th.r { text-align: right; padding-right: 16px; }

        .pos-tbl tbody td {
            padding: 10px 12px; font-size: .8375rem; color: var(--t2);
            border-bottom: 1px solid var(--bsoft); vertical-align: middle;
        }
        .pos-tbl tbody td:first-child { padding-left: 18px; }
        .pos-tbl tbody tr:last-child td { border-bottom: none; }
        .pos-tbl tbody tr:hover td { background: #f8faff; }

        /* ── Cell styles ───────────────────────── */
        .id-badge {
            display: inline-flex; align-items: center;
            background: var(--s2); border: 1px solid var(--border);
            border-radius: 5px; padding: 2px 8px;
            font-size: .78rem; font-weight: 700; color: var(--t3);
            font-family: 'DM Mono', monospace;
        }
        .order-badge {
            display: inline-flex; align-items: center;
            background: var(--cyan-bg); border: 1px solid var(--cyan-b);
            border-radius: 5px; padding: 2px 8px;
            font-size: .78rem; font-weight: 700; color: var(--cyan);
            font-family: 'DM Mono', monospace;
        }
        .cust-name  { font-weight: 600; color: var(--t1); }
        .mono-val   { font-family: 'DM Mono', monospace; font-size: .82rem; }
        .date-val   { font-size: .79rem; color: var(--t3); }

        /* service type tags */
        .svc-hallmark {
            display: inline-block;
            background: var(--amber-bg); color: var(--amber);
            border: 1px solid var(--amber-b);
            border-radius: 4px; padding: 1px 7px;
            font-size: .72rem; font-weight: 700;
        }
        .svc-tunch {
            display: inline-block;
            background: var(--violet-bg); color: var(--violet);
            border: 1px solid var(--violet-b);
            border-radius: 4px; padding: 1px 7px;
            font-size: .72rem; font-weight: 700;
        }

        /* purity label mini tag */
        .purity-label {
            font-size: .66rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .04em; color: var(--t4); display: block;
            margin-bottom: 1px;
        }

        .qty-badge {
            display: inline-flex; align-items: center; justify-content: center;
            background: var(--s2); border: 1px solid var(--border);
            border-radius: 5px; padding: 1px 8px;
            font-size: .78rem; font-weight: 600; color: var(--t3);
            font-family: 'DM Mono', monospace;
        }

        /* ── Action buttons ────────────────────── */
        .act-group { display: flex; gap: 5px; justify-content: center; }
        .act-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 28px; height: 28px; border-radius: var(--rs);
            border: 1.5px solid; font-size: .72rem; cursor: pointer;
            transition: all .15s; text-decoration: none; background: var(--surface);
        }
        .act-view        { border-color: var(--blue-b);   color: var(--blue);   }
        .act-view:hover  { background: var(--blue-bg);   }
        .act-edit        { border-color: var(--amber-b);  color: var(--amber);  }
        .act-edit:hover  { background: var(--amber-bg);  }
        .act-open        { border-color: var(--green-b);  color: var(--green);  }
        .act-open:hover  { background: var(--green-bg);  }

        /* ── Empty state ───────────────────────── */
        .empty-state { text-align: center; padding: 56px 20px; color: var(--t4); }
        .empty-state i { font-size: 2rem; display: block; margin-bottom: 10px; opacity: .2; }
        .empty-state p { font-size: .875rem; }

        /* ── Pagination ────────────────────────── */
        .pager {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 18px; background: var(--s2); border-top: 1px solid var(--border);
            flex-wrap: wrap; gap: 10px;
        }
        .pager-info  { font-size: .8rem; color: var(--t3); }
        .pager-nav   { display: flex; gap: 3px; }
        .pager-btn {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 30px; height: 30px; padding: 0 8px;
            border: 1.5px solid var(--border); border-radius: var(--rs);
            font-size: .8rem; font-weight: 600; color: var(--t2);
            background: var(--surface); text-decoration: none; transition: all .15s;
        }
        .pager-btn:hover   { border-color: var(--blue); color: var(--blue); background: var(--blue-bg); }
        .pager-btn.active  { background: var(--blue); border-color: var(--blue); color: #fff; pointer-events: none; }
        .pager-btn.disabled{ opacity: .35; pointer-events: none; }
        .pager-ellipsis    { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; color: var(--t4); font-size: .8rem; }

        /* ── Responsive ────────────────────────── */
        @media (max-width: 991.98px) {
            .page-shell { margin-left: 0; }
            .top-bar    { top: 52px; }
            .main       { padding: 14px 14px 50px; }
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="page-shell">

    <header class="top-bar">
        <div class="tb-ico"><i class="fas fa-file-lines"></i></div>
        <div>
            <div class="tb-title">Customer Reports</div>
            <div class="tb-sub">View and manage all hallmark &amp; tunch reports</div>
        </div>
        <div class="tb-right">
            <a href="create_hallmark_report.php" class="btn-pos btn-ghost">
                <i class="fas fa-stamp" style="font-size:.6rem;"></i> Hallmark
            </a>
            <a href="create_tunch_report.php" class="btn-pos btn-ghost">
                <i class="fas fa-flask" style="font-size:.6rem;"></i> Tunch
            </a>
            <a href="dashboard.php" class="btn-pos btn-ghost">
                <i class="fas fa-gauge-high" style="font-size:.6rem;"></i> Dashboard
            </a>
        </div>
    </header>

    <div class="main">
    <?php
        // ── Query params ──────────────────────────────────────────────
        $records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 100;
        $page    = isset($_GET['page'])     ? max(1, (int)$_GET['page']) : 1;
        $offset  = ($page - 1) * $records_per_page;
        $search  = isset($_GET['search'])   ? trim($_GET['search']) : '';
        $filter_type = isset($_GET['type']) ? $_GET['type'] : '';  // 'hallmark' | 'tunch' | ''

        // ── WHERE clause (safe) ───────────────────────────────────────
        $conditions = [];

        if (!empty($search)) {
            $s = mysqli_real_escape_string($conn, $search);
            $conditions[] = "(customer_name LIKE '%$s%' OR item_name LIKE '%$s%' OR order_id LIKE '%$s%')";
        }

        if ($filter_type === 'hallmark') {
            $conditions[] = "service_name LIKE '%hallmark%'";
        } elseif ($filter_type === 'tunch') {
            $conditions[] = "service_name NOT LIKE '%hallmark%'";
        }

        $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // ── Counts ────────────────────────────────────────────────────
        $count_result  = mysqli_query($conn, "SELECT COUNT(*) as total FROM customer_reports $where_clause");
        $total_records = mysqli_fetch_assoc($count_result)['total'];
        $total_pages   = max(1, ceil($total_records / $records_per_page));
        $page          = min($page, $total_pages);
        $offset        = ($page - 1) * $records_per_page;

        $startRecord = $total_records > 0 ? $offset + 1 : 0;
        $endRecord   = min($offset + $records_per_page, $total_records);

        // ── Fetch rows ────────────────────────────────────────────────
        $result = mysqli_query($conn,
            "SELECT id, order_id, customer_name, item_name, quantity, service_name,
                    weight, gold_purity_percent, silver_purity_percent, karat,
                    hallmark, created_at
             FROM customer_reports $where_clause
             ORDER BY created_at DESC
             LIMIT $offset, $records_per_page"
        );

        $hasFilter = !empty($search) || !empty($filter_type);
    ?>

        <div class="pos-card">

            <!-- Filter bar -->
            <form method="GET">
                <div class="filter-bar">
                    <!-- Search -->
                    <div>
                        <label class="lbl">Search</label>
                        <div class="search-wrap">
                            <i class="fas fa-magnifying-glass si"></i>
                            <input type="text" name="search" class="fc"
                                   placeholder="Customer name, item, or order ID"
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>

                    <!-- Type filter -->
                    <div>
                        <label class="lbl">Type</label>
                        <select name="type" class="fc" style="width:130px;">
                            <option value=""        <?= $filter_type === ''         ? 'selected' : '' ?>>All Types</option>
                            <option value="hallmark"<?= $filter_type === 'hallmark' ? 'selected' : '' ?>>Hallmark</option>
                            <option value="tunch"   <?= $filter_type === 'tunch'    ? 'selected' : '' ?>>Tunch</option>
                        </select>
                    </div>

                    <!-- Per page -->
                    <div>
                        <label class="lbl">Per Page</label>
                        <select name="per_page" class="fc" style="width:100px;">
                            <?php foreach ([15,25,50,100,200] as $pp): ?>
                            <option value="<?= $pp ?>" <?= $records_per_page == $pp ? 'selected' : '' ?>><?= $pp ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Actions -->
                    <div style="display:flex;gap:7px;align-items:flex-end;">
                        <button type="submit" class="btn-pos btn-blue" style="height:34px;">
                            <i class="fas fa-filter" style="font-size:.6rem;"></i> Filter
                        </button>
                        <?php if ($hasFilter): ?>
                        <a href="view_customer_reports.php" class="btn-pos btn-ghost" style="height:34px;">
                            <i class="fas fa-rotate-left" style="font-size:.6rem;"></i> Reset
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <!-- Info bar -->
            <div class="info-bar">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span class="total-pill">
                        <i class="fas fa-file-lines" style="font-size:.6rem;"></i>
                        <?= number_format($total_records) ?> Records
                    </span>
                    <?php if ($total_records > 0): ?>
                    <span>Showing <strong><?= number_format($startRecord) ?></strong>–<strong><?= number_format($endRecord) ?></strong></span>
                    <?php endif; ?>
                </div>
                <span>Page <strong><?= $page ?></strong> / <strong><?= $total_pages ?></strong></span>
            </div>

            <!-- Table -->
            <div style="overflow-x:auto;">
                <table class="pos-tbl">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Item</th>
                            <th class="c">Qty</th>
                            <th>Type</th>
                            <th class="r">Weight (g)</th>
                            <th>Purity</th>
                            <th>Karat</th>
                            <th>Hallmark</th>
                            <th>Date</th>
                            <th class="c">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)):
                            $isHallmark = stripos($row['service_name'], 'hallmark') !== false;
                            $isSilver   = stripos($row['item_name'], 'silver') !== false
                                       || strpos($row['item_name'], 'চাঁদি') !== false
                                       || stripos($row['item_name'], 'rupa') !== false;

                            // Purity: pick the right column
                            if ($isHallmark) {
                                $purityVal   = null;
                                $purityLabel = '';
                            } elseif ($isSilver) {
                                $purityVal   = $row['silver_purity_percent'];
                                $purityLabel = 'Silver';
                            } else {
                                $purityVal   = $row['gold_purity_percent'];
                                $purityLabel = 'Gold';
                            }
                        ?>
                        <tr>
                            <td><span class="id-badge"><?= $row['id'] ?></span></td>
                            <td><span class="order-badge">#<?= $row['order_id'] ?></span></td>
                            <td><span class="cust-name"><?= htmlspecialchars($row['customer_name']) ?></span></td>
                            <td style="max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                <?= htmlspecialchars($row['item_name']) ?>
                            </td>
                            <td style="text-align:center;">
                                <span class="qty-badge"><?= htmlspecialchars($row['quantity'] ?: '1') ?></span>
                            </td>
                            <td>
                                <?php if ($isHallmark): ?>
                                    <span class="svc-hallmark">Hallmark</span>
                                <?php else: ?>
                                    <span class="svc-tunch">Tunch</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;padding-right:16px;">
                                <span class="mono-val"><?= number_format((float)$row['weight'], 3) ?></span>
                            </td>
                            <td>
                                <?php if ($purityVal !== null && $purityVal !== ''): ?>
                                    <span class="purity-label"><?= $purityLabel ?></span>
                                    <span class="mono-val"><?= number_format((float)$purityVal, 3) ?>%</span>
                                <?php else: ?>
                                    <span style="color:var(--t4);">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$isHallmark && $row['karat'] !== null && $row['karat'] !== ''): ?>
                                    <span class="mono-val"><?= number_format((float)$row['karat'], 2) ?>K</span>
                                <?php else: ?>
                                    <span style="color:var(--t4);">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['hallmark'])): ?>
                                    <span style="font-family:'DM Mono',monospace;font-weight:700;font-size:.85rem;"><?= htmlspecialchars($row['hallmark']) ?></span>
                                <?php else: ?>
                                    <span style="color:var(--t4);">—</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="date-val"><?= date('d M Y', strtotime($row['created_at'])) ?></span></td>
                            <td>
                                <div class="act-group">
                                    <!-- View report (internal preview) -->
                                    <?php if ($isHallmark): ?>
                                    <a href="create_hallmark_report.php?report_id=<?= $row['id'] ?>"
                                       class="act-btn act-view" title="View Hallmark Report">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php else: ?>
                                    <a href="create_tunch_report.php?report_id=<?= $row['id'] ?>"
                                       class="act-btn act-view" title="View Tunch Report">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php endif; ?>

                                    <!-- Open public verification page -->
                                    <a href="report_varification.php?id=<?= $row['id'] ?>"
                                       class="act-btn act-open" title="Open Verification Page" target="_blank">
                                        <i class="fas fa-arrow-up-right-from-square"></i>
                                    </a>

                                    <!-- Edit -->
                                    <a href="edit_customer_report_form.php?id=<?= $row['id'] ?>"
                                       class="act-btn act-edit" title="Edit">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12">
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>No records found<?= $hasFilter ? ' matching your filter' : '' ?>.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pager">
                <div class="pager-info">
                    Page <?= $page ?> of <?= $total_pages ?> &nbsp;·&nbsp; <?= number_format($total_records) ?> total records
                </div>
                <div class="pager-nav">
                    <a class="pager-btn <?= $page <= 1 ? 'disabled' : '' ?>"
                       href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                        <i class="fas fa-angle-left" style="font-size:.6rem;"></i>
                    </a>

                    <?php
                    $sp = max(1, $page - 2);
                    $ep = min($total_pages, $page + 2);
                    if ($sp > 1): ?>
                        <a class="pager-btn" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
                        <?php if ($sp > 2): ?><span class="pager-ellipsis">…</span><?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $sp; $i <= $ep; $i++): ?>
                    <a class="pager-btn <?= $i == $page ? 'active' : '' ?>"
                       href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                    <?php endfor; ?>

                    <?php if ($ep < $total_pages): ?>
                        <?php if ($ep < $total_pages - 1): ?><span class="pager-ellipsis">…</span><?php endif; ?>
                        <a class="pager-btn" href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>"><?= $total_pages ?></a>
                    <?php endif; ?>

                    <a class="pager-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"
                       href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                        <i class="fas fa-angle-right" style="font-size:.6rem;"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /pos-card -->
    </div><!-- /main -->
</div><!-- /page-shell -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>