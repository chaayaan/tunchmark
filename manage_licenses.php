<?php
require 'auth.php';
require 'mylicensedb.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php'); exit;
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$isSuperAdmin  = ($currentUserId === 1);

/* ================================================================
   AJAX handler
   ================================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    try {

        /* ---- ADD (super-admin only) ---- */
        if ($_POST['action'] === 'add' && $isSuperAdmin) {
            $activationDate = $_POST['activation_date'] ?: null;
            $stmt = $pdo->prepare(
                "INSERT INTO licenses (branch_name, branch_app_link, license_key, expire_date, activation_date, last_renew, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $_POST['branch_name'],
                $_POST['branch_app_link'],
                $_POST['license_key'],
                $_POST['expire_date'],
                $activationDate,
                $activationDate,
                $_POST['status']
            ]);
            echo json_encode(['success' => true, 'message' => 'License added successfully']);

        /* ---- UPDATE (super-admin only) ---- */
        } elseif ($_POST['action'] === 'update' && $isSuperAdmin) {
            $activationDate = $_POST['activation_date'] ?: null;
            $stmt = $pdo->prepare(
                "UPDATE licenses SET branch_name=?, branch_app_link=?, license_key=?, expire_date=?, activation_date=?, last_renew=?, status=? WHERE id=?"
            );
            $stmt->execute([
                $_POST['branch_name'],
                $_POST['branch_app_link'],
                $_POST['license_key'],
                $_POST['expire_date'],
                $activationDate,
                $activationDate,
                $_POST['status'],
                $_POST['id']
            ]);
            echo json_encode(['success' => true, 'message' => 'License updated successfully']);

        /* ---- FETCH ---- */
        } elseif ($_POST['action'] === 'fetch') {
            $stmt = $pdo->query("SELECT * FROM licenses ORDER BY expire_date ASC");
            echo json_encode(['success' => true, 'licenses' => $stmt->fetchAll()]);

        /* ---- RENEW ---- */
        } elseif ($_POST['action'] === 'renew') {

            $enteredPassword = $_POST['password'] ?? '';

            // $conn is a mysqli connection loaded by auth.php (via mydb.php)
            global $conn;
            if (!isset($conn) || !$conn) {
                require_once 'mydb.php';
            }

            $uStmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
            $uStmt->bind_param("i", $currentUserId);
            $uStmt->execute();
            $uRow = $uStmt->get_result()->fetch_assoc();
            $uStmt->close();

            if (!$uRow || !password_verify($enteredPassword, $uRow['password'])) {
                echo json_encode(['success' => false, 'message' => 'Incorrect password. Renewal cancelled.']);
                exit;
            }

            $licId = (int)$_POST['id'];
            $lStmt = $pdo->prepare("SELECT expire_date FROM licenses WHERE id = ?");
            $lStmt->execute([$licId]);
            $lic = $lStmt->fetch();

            if (!$lic) {
                echo json_encode(['success' => false, 'message' => 'License not found.']);
                exit;
            }

            $today      = new DateTime('today');
            $currentExp = new DateTime($lic['expire_date']);
            $baseDate   = ($currentExp < $today) ? $today : $currentExp;
            $newExpDate = (clone $baseDate)->modify('+1 year')->format('Y-m-d');
            $todayStr   = $today->format('Y-m-d');

            $upStmt = $pdo->prepare(
                "UPDATE licenses SET expire_date = ?, last_renew = ?, status = 'active' WHERE id = ?"
            );
            $upStmt->execute([$newExpDate, $todayStr, $licId]);

            $newExpDisplay = (new DateTime($newExpDate))->format('F d, Y');
            echo json_encode([
                'success'    => true,
                'message'    => 'License renewed successfully until ' . $newExpDisplay,
                'new_expire' => $newExpDate,
                'last_renew' => $todayStr,
            ]);

        } else {
            echo json_encode(['success' => false, 'message' => 'Unauthorized or unknown action.']);
        }

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>License Management &mdash; Rajaiswari</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:#f1f3f6; --surface:#fff; --s2:#fafbfc; --border:#e4e7ec; --bsoft:#f0f1f3;
            --t1:#111827; --t2:#374151; --t3:#6b7280; --t4:#9ca3af;
            --blue:#2563eb; --blue-bg:#eff6ff; --blue-b:#bfdbfe;
            --green:#059669; --green-bg:#ecfdf5; --green-b:#a7f3d0;
            --amber:#d97706; --amber-bg:#fffbeb; --amber-b:#fde68a;
            --red:#dc2626; --red-bg:#fef2f2; --red-b:#fecaca;
            --violet:#7c3aed; --violet-bg:#f5f3ff;
            --teal:#0891b2; --teal-bg:#ecfeff; --teal-b:#a5f3fc;
            --r:10px; --rs:6px; --sh:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);
        }
        *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'DM Sans',-apple-system,sans-serif; font-size:14px; background:var(--bg); color:var(--t1); -webkit-font-smoothing:antialiased; min-height:100vh; }
        .page-shell { margin-left:200px; min-height:100vh; display:flex; flex-direction:column; }
        .top-bar { position:sticky; top:0; z-index:200; height:54px; background:var(--surface); border-bottom:1px solid var(--border); box-shadow:var(--sh); display:flex; align-items:center; padding:0 22px; gap:12px; flex-shrink:0; }
        .tb-ico { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:13px; background:var(--violet-bg); color:var(--violet); flex-shrink:0; }
        .tb-title { font-size:1.0625rem; font-weight:700; color:var(--t1); }
        .tb-sub { font-size:.78rem; color:var(--t4); }
        .tb-right { margin-left:auto; display:flex; gap:7px; align-items:center; }
        .btn-pos { display:inline-flex; align-items:center; gap:6px; height:32px; padding:0 13px; border:none; border-radius:var(--rs); font-family:inherit; font-size:.8125rem; font-weight:600; cursor:pointer; transition:all .15s; text-decoration:none; white-space:nowrap; }
        .btn-ghost { background:var(--surface); color:var(--t2); border:1.5px solid var(--border); }
        .btn-ghost:hover { background:var(--s2); border-color:#9ca3af; color:var(--t1); }
        .btn-blue { background:var(--blue); color:#fff; border:none; }
        .btn-blue:hover { background:#1d4ed8; }
        .main { flex:1; padding:20px 22px 60px; display:flex; flex-direction:column; gap:16px; }
        .sec { background:var(--surface); border:1px solid var(--border); border-radius:var(--r); box-shadow:var(--sh); overflow:hidden; }
        .sec-head { display:flex; align-items:center; gap:9px; padding:11px 18px; background:var(--s2); border-bottom:1px solid var(--bsoft); }
        .sec-ico { width:26px; height:26px; border-radius:var(--rs); display:flex; align-items:center; justify-content:center; font-size:11px; flex-shrink:0; }
        .i-violet { background:var(--violet-bg); color:var(--violet); }
        .i-blue   { background:var(--blue-bg);   color:var(--blue);   }
        .i-teal   { background:var(--teal-bg);   color:var(--teal);   }
        .sec-title { font-size:.875rem; font-weight:700; color:var(--t1); }
        .sec-body { padding:18px; }
        .form-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
        .lbl { display:block; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--t3); margin-bottom:5px; }
        .lbl .req { color:var(--red); margin-left:2px; }
        .fc { width:100%; height:36px; padding:0 10px; border:1.5px solid var(--border); border-radius:var(--rs); font-family:inherit; font-size:.875rem; color:var(--t2); background:var(--surface); outline:none; transition:border-color .15s; }
        .fc:focus { border-color:var(--blue); box-shadow:0 0 0 3px rgba(37,99,235,.1); }
        select.fc { appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%236b7280'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 10px center; }
        .form-footer { display:flex; justify-content:flex-end; gap:8px; padding-top:4px; }
        .dt-wrap { overflow-x:auto; }
        table.dt { width:100%; border-collapse:collapse; font-size:.8375rem; }
        table.dt thead th { padding:8px 12px; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--t4); background:var(--s2); border-bottom:1px solid var(--border); white-space:nowrap; }
        table.dt tbody td { padding:9px 12px; border-bottom:1px solid var(--bsoft); color:var(--t2); vertical-align:middle; }
        table.dt tbody tr:last-child td { border-bottom:none; }
        table.dt tbody tr:hover td { background:var(--s2); }
        table.dt tbody tr.row-expired td { background:#fef2f2; }
        table.dt tbody tr.row-expired:hover td { background:#fee2e2; }
        table.dt tbody tr.row-warn td { background:#fffbeb; }
        table.dt tbody tr.row-warn:hover td { background:#fef3c7; }
        .pill { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:20px; font-size:.72rem; font-weight:700; white-space:nowrap; }
        .pill::before { content:''; width:5px; height:5px; border-radius:50%; flex-shrink:0; }
        .pill-active    { background:var(--green-bg); color:var(--green); border:1px solid var(--green-b); }
        .pill-active::before { background:var(--green); }
        .pill-expired   { background:var(--red-bg);   color:var(--red);   border:1px solid var(--red-b);   }
        .pill-expired::before { background:var(--red); }
        .pill-suspended { background:var(--amber-bg); color:var(--amber); border:1px solid var(--amber-b); }
        .pill-suspended::before { background:var(--amber); }
        .days-ok   { font-family:'DM Mono',monospace; font-size:.8rem; color:var(--t2); }
        .days-warn { font-family:'DM Mono',monospace; font-size:.8rem; color:var(--amber); font-weight:700; }
        .days-exp  { font-family:'DM Mono',monospace; font-size:.8rem; color:var(--red);   font-weight:700; }
        .act-btn { display:inline-flex; align-items:center; gap:4px; height:26px; padding:0 10px; border-radius:var(--rs); border:none; cursor:pointer; font-family:inherit; font-size:.78rem; font-weight:600; transition:all .15s; text-decoration:none; white-space:nowrap; }
        .act-edit:hover  { background:var(--blue); color:#fff; }
        .act-edit  { background:var(--blue-bg); color:var(--blue); }
        .act-renew { background:var(--teal-bg); color:var(--teal); }
        .act-renew:hover { background:var(--teal); color:#fff; }
        .mono { font-family:'DM Mono',monospace; font-size:.8rem; }
        .link-cell { max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .flash-toast { position:fixed; top:16px; right:16px; z-index:9999; display:flex; flex-direction:column; gap:8px; pointer-events:none; }
        .ft { display:flex; align-items:center; gap:10px; padding:12px 16px; border-radius:var(--rs); font-size:.875rem; font-weight:500; box-shadow:0 4px 12px rgba(0,0,0,.12); pointer-events:all; animation:ftIn .2s ease; max-width:340px; }
        @keyframes ftIn { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
        .ft.success { background:var(--green-bg); border:1px solid var(--green-b); border-left:3px solid var(--green); color:#065f46; }
        .ft.danger  { background:var(--red-bg);   border:1px solid var(--red-b);   border-left:3px solid var(--red);   color:#991b1b; }
        .ft-close { margin-left:auto; background:none; border:none; cursor:pointer; color:inherit; opacity:.5; font-size:.75rem; padding:0; }
        .ft-close:hover { opacity:1; }
        .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.35); z-index:8000; display:flex; align-items:center; justify-content:center; padding:20px; }
        .modal-box { background:var(--surface); border-radius:var(--r); box-shadow:0 8px 32px rgba(0,0,0,.18); width:100%; max-width:380px; overflow:hidden; }
        .modal-head { display:flex; align-items:center; gap:10px; padding:14px 18px; background:var(--s2); border-bottom:1px solid var(--border); }
        .modal-title { font-weight:700; font-size:.9375rem; color:var(--t1); }
        .modal-body { padding:18px; font-size:.875rem; color:var(--t2); line-height:1.6; }
        .modal-foot { display:flex; justify-content:flex-end; gap:8px; padding:12px 18px; border-top:1px solid var(--bsoft); }
        .pw-wrap { position:relative; margin-top:12px; }
        .pw-wrap .fc { padding-right:38px; }
        .pw-toggle { position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--t3); font-size:.85rem; padding:4px; }
        .pw-toggle:hover { color:var(--t1); }
        .renew-info { background:var(--teal-bg); border:1px solid var(--teal-b); border-radius:var(--rs); padding:10px 12px; margin-top:12px; font-size:.8125rem; color:var(--teal); display:flex; gap:8px; align-items:flex-start; }
        .pw-error { display:none; align-items:center; gap:5px; color:var(--red); font-size:.8rem; margin-top:6px; }
        @media(max-width:991.98px) {
            .page-shell { margin-left:0; }
            .top-bar { top:52px; }
            .main { padding:14px 14px 50px; }
            .form-grid { grid-template-columns:1fr 1fr; }
        }
        @media(max-width:600px) { .form-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="page-shell">

    <header class="top-bar">
        <div class="tb-ico"><i class="fas fa-key"></i></div>
        <div>
            <div class="tb-title">License Management</div>
            <div class="tb-sub">Manage branch software licenses</div>
        </div>
        <div class="tb-right">
            <a href="dashboard.php" class="btn-pos btn-ghost">
                <i class="fas fa-arrow-left" style="font-size:.6rem;"></i> Dashboard
            </a>
        </div>
    </header>

    <div class="main">

        <?php if ($isSuperAdmin): ?>
        <!-- Add License Form — visible to user_id=1 only -->
        <div class="sec">
            <div class="sec-head">
                <span class="sec-ico i-blue"><i class="fas fa-plus"></i></span>
                <span class="sec-title">Add New License</span>
            </div>
            <div class="sec-body">
                <form id="addForm">
                    <div class="form-grid">
                        <div>
                            <label class="lbl">Branch Name <span class="req">*</span></label>
                            <input type="text" name="branch_name" class="fc" placeholder="e.g. Rajaiswari Main" required>
                        </div>
                        <div>
                            <label class="lbl">App Link</label>
                            <input type="text" name="branch_app_link" class="fc" placeholder="https://…">
                        </div>
                        <div>
                            <label class="lbl">License Key <span class="req">*</span></label>
                            <input type="text" name="license_key" class="fc mono" placeholder="XXXX-XXXX-XXXX" required>
                        </div>
                        <div>
                            <label class="lbl">Expiration Date <span class="req">*</span></label>
                            <input type="date" name="expire_date" class="fc" required>
                        </div>
                        <div>
                            <label class="lbl">Activation Date</label>
                            <input type="date" name="activation_date" class="fc">
                        </div>
                        <div>
                            <label class="lbl">Status <span class="req">*</span></label>
                            <select name="status" class="fc" required>
                                <option value="active">Active</option>
                                <option value="expired">Expired</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-footer" style="margin-top:14px;">
                        <button type="submit" class="btn-pos btn-blue" style="height:36px;padding:0 20px;" id="addBtn">
                            <i class="fas fa-circle-plus" style="font-size:.75rem;"></i> Add License
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Licenses Table -->
        <div class="sec">
            <div class="sec-head">
                <span class="sec-ico i-violet"><i class="fas fa-table-list"></i></span>
                <span class="sec-title">All Licenses</span>
                <span id="licCount" style="margin-left:auto;font-size:.75rem;color:var(--t4);font-family:'DM Mono',monospace;"></span>
            </div>
            <div class="dt-wrap">
                <table class="dt">
                    <thead>
                        <tr>
                            <th>Branch</th>
                            <th>App Link</th>
                            <th>License Key</th>
                            <th>Last Renew</th>
                            <th>Expires</th>
                            <th>Days Left</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="licensesTable">
                        <tr><td colspan="8" style="text-align:center;padding:32px;color:var(--t4);">
                            <i class="fas fa-spinner fa-spin" style="margin-right:6px;"></i>Loading…
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Toast container -->
<div class="flash-toast" id="toastContainer"></div>

<!-- Renew Modal -->
<div class="modal-overlay" id="renewModal" style="display:none;" onclick="if(event.target===this)closeRenewModal()">
    <div class="modal-box">
        <div class="modal-head">
            <span class="sec-ico i-teal"><i class="fas fa-rotate"></i></span>
            <span class="modal-title">Renew License</span>
        </div>
        <div class="modal-body">
            <p>You are about to renew the license for <strong id="renewBranchName"></strong>.</p>
            <div class="renew-info">
                <i class="fas fa-circle-info" style="font-size:.85rem;margin-top:1px;flex-shrink:0;"></i>
                <div id="renewInfoText"></div>
            </div>
            <p style="margin-top:14px;font-size:.8125rem;color:var(--t3);">Enter your admin password to confirm:</p>
            <div class="pw-wrap">
                <input type="password" id="renewPassword" class="fc" placeholder="Your password…" autocomplete="current-password">
                <button type="button" class="pw-toggle" onclick="toggleRenewPw()" tabindex="-1">
                    <i class="fas fa-eye" id="renewEyeIcon"></i>
                </button>
            </div>
            <div class="pw-error" id="renewPwError">
                <i class="fas fa-circle-xmark"></i>
                <span id="renewPwErrorMsg"></span>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn-pos btn-ghost" onclick="closeRenewModal()">Cancel</button>
            <button class="btn-pos" id="renewConfirmBtn" style="background:var(--teal);color:#fff;border:none;height:32px;padding:0 16px;font-size:.8125rem;">
                <i class="fas fa-rotate" style="font-size:.65rem;"></i> Confirm Renewal
            </button>
        </div>
    </div>
</div>

<script>
const IS_SUPER_ADMIN = <?= $isSuperAdmin ? 'true' : 'false' ?>;
let pendingRenewId = null;

document.addEventListener('DOMContentLoaded', loadLicenses);

/* ── Fetch & render ──────────────────────────────────────── */
function loadLicenses() {
    post({ action: 'fetch' }).then(data => {
        if (data.success) displayLicenses(data.licenses);
    });
}

function displayLicenses(licenses) {
    const tbody = document.getElementById('licensesTable');
    const count = document.getElementById('licCount');

    if (!licenses.length) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:var(--t4);">No licenses found</td></tr>';
        count.textContent = '';
        return;
    }

    count.textContent = licenses.length + ' record' + (licenses.length !== 1 ? 's' : '');

    tbody.innerHTML = licenses.map(lic => {
        const today    = new Date(); today.setHours(0,0,0,0);
        const exp      = new Date(lic.expire_date);
        const daysLeft = Math.ceil((exp - today) / 86400000);

        let rowClass = '', daysHtml = '';
        if (daysLeft < 0) {
            rowClass = 'row-expired';
            daysHtml = '<span class="days-exp">EXPIRED</span>';
        } else if (daysLeft <= 30) {
            rowClass = 'row-warn';
            daysHtml = '<span class="days-warn">' + daysLeft + 'd</span>';
        } else {
            daysHtml = '<span class="days-ok">' + daysLeft + 'd</span>';
        }

        const statusPill = {
            active:    '<span class="pill pill-active">Active</span>',
            expired:   '<span class="pill pill-expired">Expired</span>',
            suspended: '<span class="pill pill-suspended">Suspended</span>'
        }[lic.status] || '<span class="pill">' + esc(lic.status) + '</span>';

        const appLink = lic.branch_app_link
            ? `<a href="${esc(lic.branch_app_link)}" target="_blank" class="link-cell" title="${esc(lic.branch_app_link)}" style="color:var(--blue);text-decoration:none;display:block;">${esc(lic.branch_app_link)}</a>`
            : '<span style="color:var(--t4);">&mdash;</span>';

        const lastRenew = lic.last_renew
            ? `<span class="mono">${esc(lic.last_renew)}</span>`
            : '<span style="color:var(--t4);">&mdash;</span>';

        // Renew button — all admins
        const renewBtn = `<button class="act-btn act-renew" onclick="confirmRenew(${lic.id},'${esc(lic.branch_name)}','${esc(lic.expire_date)}')">
            <i class="fas fa-rotate" style="font-size:.7rem;"></i> Renew
        </button>`;

        // Edit button — user_id=1 only
        const editBtn = IS_SUPER_ADMIN
            ? `<a href="edit_license.php?id=${lic.id}" class="act-btn act-edit">
                   <i class="fas fa-pen" style="font-size:.7rem;"></i> Edit
               </a>`
            : '';

        return `<tr class="${rowClass}" id="row-${lic.id}">
            <td style="font-weight:600;">${esc(lic.branch_name)}</td>
            <td class="link-cell">${appLink}</td>
            <td class="mono">${esc(lic.license_key)}</td>
            <td>${lastRenew}</td>
            <td class="mono" style="font-size:.8rem;">${esc(lic.expire_date)}</td>
            <td>${daysHtml}</td>
            <td>${statusPill}</td>
            <td style="white-space:nowrap;display:flex;gap:5px;padding:9px 12px;">${editBtn}${renewBtn}</td>
        </tr>`;
    }).join('');
}

/* ── Add form (super-admin only) ────────────────────────── */
const addFormEl = document.getElementById('addForm');
if (addFormEl) {
    addFormEl.addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('addBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:.7rem;"></i> Adding…';
        const body = new URLSearchParams(new FormData(this));
        body.append('action', 'add');
        post(body).then(data => {
            showToast(data.message, data.success ? 'success' : 'danger');
            if (data.success) { this.reset(); loadLicenses(); }
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-circle-plus" style="font-size:.75rem;"></i> Add License';
        });
    });
}

/* ── Renew modal ─────────────────────────────────────────── */
function confirmRenew(id, name, expireDate) {
    pendingRenewId = id;
    document.getElementById('renewBranchName').textContent = name;
    document.getElementById('renewPassword').value = '';
    document.getElementById('renewPwError').style.display = 'none';

    const today  = new Date(); today.setHours(0,0,0,0);
    const exp    = new Date(expireDate);
    const base   = exp < today ? today : exp;
    const newExp = new Date(base);
    newExp.setFullYear(newExp.getFullYear() + 1);
    const fmt = d => d.toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });

    document.getElementById('renewInfoText').innerHTML = exp < today
        ? `License is currently <strong>expired</strong>. A new 1-year term starts from today (<strong>${fmt(today)}</strong>) and expires <strong>${fmt(newExp)}</strong>.`
        : `Expiry will extend from <strong>${fmt(exp)}</strong> to <strong>${fmt(newExp)}</strong>.`;

    document.getElementById('renewModal').style.display = 'flex';
    setTimeout(() => document.getElementById('renewPassword').focus(), 50);
}

function closeRenewModal() {
    pendingRenewId = null;
    document.getElementById('renewModal').style.display = 'none';
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeRenewModal(); });

document.getElementById('renewPassword').addEventListener('keydown', e => {
    if (e.key === 'Enter') document.getElementById('renewConfirmBtn').click();
});

function toggleRenewPw() {
    const inp  = document.getElementById('renewPassword');
    const icon = document.getElementById('renewEyeIcon');
    const show = inp.type === 'password';
    inp.type       = show ? 'text'         : 'password';
    icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
}

document.getElementById('renewConfirmBtn').addEventListener('click', function () {
    const pw = document.getElementById('renewPassword').value.trim();
    if (!pw) { showRenewError('Please enter your password.'); return; }
    if (!pendingRenewId) return;

    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:.65rem;"></i> Verifying…';

    post({ action: 'renew', id: pendingRenewId, password: pw }).then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            loadLicenses();
            closeRenewModal();
        } else {
            showRenewError(data.message);
        }
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-rotate" style="font-size:.65rem;"></i> Confirm Renewal';
    });
});

function showRenewError(msg) {
    document.getElementById('renewPwErrorMsg').textContent = msg.replace(/^[❌✅]\s*/, '');
    document.getElementById('renewPwError').style.display = 'flex';
}

/* ── Shared helpers ──────────────────────────────────────── */
function post(data) {
    const body = data instanceof URLSearchParams ? data : new URLSearchParams(data);
    body.set('ajax', '1');
    return fetch('', { method: 'POST', body }).then(r => r.json());
}

function esc(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function showToast(msg, type) {
    const el = document.createElement('div');
    el.className = 'ft ' + type;
    el.innerHTML = `<i class="fas ${type === 'success' ? 'fa-circle-check' : 'fa-circle-xmark'}" style="font-size:.85rem;flex-shrink:0;"></i>
        <span>${esc(String(msg).replace(/^[✅❌]\s*/,''))}</span>
        <button class="ft-close" onclick="this.closest('.ft').remove()"><i class="fas fa-xmark"></i></button>`;
    document.getElementById('toastContainer').appendChild(el);
    setTimeout(() => {
        el.style.transition = 'opacity .4s';
        el.style.opacity    = '0';
        setTimeout(() => el.remove(), 400);
    }, 4000);
}
</script>
</body>
</html>