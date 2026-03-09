<?php
require 'auth.php';
require 'mylicensedb.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php'); exit;
}

// AJAX handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    try {
        if ($_POST['action'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO licenses (branch_name, branch_app_link, license_key, expire_date, last_renew_date, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['branch_name'], $_POST['branch_app_link'], $_POST['license_key'], $_POST['expire_date'], $_POST['last_renew_date'] ?: null, $_POST['status']]);
            echo json_encode(['success' => true, 'message' => 'License added successfully']);
        } elseif ($_POST['action'] === 'update') {
            $stmt = $pdo->prepare("UPDATE licenses SET branch_name=?, branch_app_link=?, license_key=?, expire_date=?, last_renew_date=?, status=? WHERE id=?");
            $stmt->execute([$_POST['branch_name'], $_POST['branch_app_link'], $_POST['license_key'], $_POST['expire_date'], $_POST['last_renew_date'] ?: null, $_POST['status'], $_POST['id']]);
            echo json_encode(['success' => true, 'message' => 'License updated successfully']);
        } elseif ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM licenses WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            echo json_encode(['success' => true, 'message' => 'License deleted successfully']);
        } elseif ($_POST['action'] === 'fetch') {
            $stmt = $pdo->query("SELECT * FROM licenses ORDER BY expire_date ASC");
            echo json_encode(['success' => true, 'licenses' => $stmt->fetchAll()]);
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
        :root{--bg:#f1f3f6;--surface:#fff;--s2:#fafbfc;--border:#e4e7ec;--bsoft:#f0f1f3;--t1:#111827;--t2:#374151;--t3:#6b7280;--t4:#9ca3af;--blue:#2563eb;--blue-bg:#eff6ff;--blue-b:#bfdbfe;--green:#059669;--green-bg:#ecfdf5;--green-b:#a7f3d0;--amber:#d97706;--amber-bg:#fffbeb;--amber-b:#fde68a;--red:#dc2626;--red-bg:#fef2f2;--red-b:#fecaca;--violet:#7c3aed;--violet-bg:#f5f3ff;--violet-b:#ddd6fe;--r:10px;--rs:6px;--sh:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);}
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
        /* Section cards */
        .sec{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;}
        .sec-head{display:flex;align-items:center;gap:9px;padding:11px 18px;background:var(--s2);border-bottom:1px solid var(--bsoft);}
        .sec-ico{width:26px;height:26px;border-radius:var(--rs);display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;}
        .i-violet{background:var(--violet-bg);color:var(--violet);}
        .i-blue{background:var(--blue-bg);color:var(--blue);}
        .i-green{background:var(--green-bg);color:var(--green);}
        .i-red{background:var(--red-bg);color:var(--red);}
        .i-amber{background:var(--amber-bg);color:var(--amber);}
        .sec-title{font-size:.875rem;font-weight:700;color:var(--t1);}
        .sec-body{padding:18px;}
        /* Form grid */
        .form-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;}
        .span-2{grid-column:span 2;}
        .lbl{display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t3);margin-bottom:5px;}
        .lbl .req{color:var(--red);margin-left:2px;}
        .fc{width:100%;height:36px;padding:0 10px;border:1.5px solid var(--border);border-radius:var(--rs);font-family:inherit;font-size:.875rem;color:var(--t2);background:var(--surface);outline:none;transition:border-color .15s;}
        .fc:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
        select.fc{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%236b7280'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;}
        .form-footer{display:flex;justify-content:flex-end;gap:8px;padding-top:4px;}
        /* Table */
        .dt-wrap{overflow-x:auto;}
        table.dt{width:100%;border-collapse:collapse;font-size:.8375rem;}
        table.dt thead th{padding:8px 12px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--t4);background:var(--s2);border-bottom:1px solid var(--border);white-space:nowrap;}
        table.dt tbody td{padding:9px 12px;border-bottom:1px solid var(--bsoft);color:var(--t2);vertical-align:middle;}
        table.dt tbody tr:last-child td{border-bottom:none;}
        table.dt tbody tr:hover td{background:var(--s2);}
        table.dt tbody tr.row-expired td{background:#fef2f2;}
        table.dt tbody tr.row-expired:hover td{background:#fee2e2;}
        table.dt tbody tr.row-warn td{background:#fffbeb;}
        table.dt tbody tr.row-warn:hover td{background:#fef3c7;}
        /* Status pills */
        .pill{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:20px;font-size:.72rem;font-weight:700;white-space:nowrap;}
        .pill::before{content:'';width:5px;height:5px;border-radius:50%;flex-shrink:0;}
        .pill-active{background:var(--green-bg);color:var(--green);border:1px solid var(--green-b);}
        .pill-active::before{background:var(--green);}
        .pill-expired{background:var(--red-bg);color:var(--red);border:1px solid var(--red-b);}
        .pill-expired::before{background:var(--red);}
        .pill-suspended{background:var(--amber-bg);color:var(--amber);border:1px solid var(--amber-b);}
        .pill-suspended::before{background:var(--amber);}
        /* Days left badges */
        .days-ok{font-family:'DM Mono',monospace;font-size:.8rem;color:var(--t2);}
        .days-warn{font-family:'DM Mono',monospace;font-size:.8rem;color:var(--amber);font-weight:700;}
        .days-exp{font-family:'DM Mono',monospace;font-size:.8rem;color:var(--red);font-weight:700;}
        /* Action buttons */
        .act-btn{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:var(--rs);border:none;cursor:pointer;font-size:.75rem;transition:all .15s;}
        .act-edit{background:var(--blue-bg);color:var(--blue);}
        .act-edit:hover{background:var(--blue);color:#fff;}
        .act-del{background:var(--red-bg);color:var(--red);}
        .act-del:hover{background:var(--red);color:#fff;}
        .mono{font-family:'DM Mono',monospace;font-size:.8rem;}
        .link-cell{max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        /* Flash toast */
        .flash-toast{position:fixed;top:16px;right:16px;z-index:9999;display:flex;flex-direction:column;gap:8px;pointer-events:none;}
        .ft{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:var(--rs);font-size:.875rem;font-weight:500;box-shadow:0 4px 12px rgba(0,0,0,.12);pointer-events:all;animation:ftIn .2s ease;max-width:340px;}
        @keyframes ftIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
        .ft.success{background:var(--green-bg);border:1px solid var(--green-b);border-left:3px solid var(--green);color:#065f46;}
        .ft.danger{background:var(--red-bg);border:1px solid var(--red-b);border-left:3px solid var(--red);color:#991b1b;}
        .ft-close{margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;opacity:.5;font-size:.75rem;padding:0;}
        .ft-close:hover{opacity:1;}
        /* Delete confirm modal */
        .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:8000;display:flex;align-items:center;justify-content:center;padding:20px;}
        .modal-box{background:var(--surface);border-radius:var(--r);box-shadow:0 8px 32px rgba(0,0,0,.18);width:100%;max-width:380px;overflow:hidden;}
        .modal-head{display:flex;align-items:center;gap:10px;padding:14px 18px;background:var(--s2);border-bottom:1px solid var(--border);}
        .modal-title{font-weight:700;font-size:.9375rem;color:var(--t1);}
        .modal-body{padding:18px;font-size:.875rem;color:var(--t2);line-height:1.5;}
        .modal-foot{display:flex;justify-content:flex-end;gap:8px;padding:12px 18px;border-top:1px solid var(--bsoft);}
        @media(max-width:991.98px){.page-shell{margin-left:0;}.top-bar{top:52px;}.main{padding:14px 14px 50px;}.form-grid{grid-template-columns:1fr 1fr;}.span-2{grid-column:span 1;}}
        @media(max-width:600px){.form-grid{grid-template-columns:1fr;}}
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
            <a href="dashboard.php" class="btn-pos btn-ghost"><i class="fas fa-arrow-left" style="font-size:.6rem;"></i> Dashboard</a>
        </div>
    </header>

    <div class="main">

        <!-- Add License Form -->
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
                            <label class="lbl">Last Renew Date</label>
                            <input type="date" name="last_renew_date" class="fc">
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
                            <th>Expires</th>
                            <th>Days Left</th>
                            <th>Last Renewed</th>
                            <th>Added</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="licensesTable">
                        <tr><td colspan="9" style="text-align:center;padding:32px;color:var(--t4);">
                            <i class="fas fa-spinner fa-spin" style="margin-right:6px;"></i>Loading…
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /.main -->
</div><!-- /.page-shell -->

<!-- Toast container -->
<div class="flash-toast" id="toastContainer"></div>

<!-- Delete confirm modal -->
<div class="modal-overlay" id="delModal" style="display:none;" onclick="if(event.target===this)closeDelModal()">
    <div class="modal-box">
        <div class="modal-head">
            <span class="sec-ico i-red"><i class="fas fa-trash"></i></span>
            <span class="modal-title">Delete License</span>
        </div>
        <div class="modal-body">
            Are you sure you want to delete the license for <strong id="delBranchName"></strong>? This action cannot be undone.
        </div>
        <div class="modal-foot">
            <button class="btn-pos btn-ghost" onclick="closeDelModal()">Cancel</button>
            <button class="btn-pos" id="delConfirmBtn" style="background:var(--red);color:#fff;border:none;height:32px;padding:0 16px;font-size:.8125rem;">
                <i class="fas fa-trash" style="font-size:.65rem;"></i> Delete
            </button>
        </div>
    </div>
</div>

<script>
let pendingDeleteId = null;

document.addEventListener('DOMContentLoaded', loadLicenses);

function loadLicenses() {
    fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'ajax=1&action=fetch' })
    .then(r => r.json()).then(data => {
        if (data.success) displayLicenses(data.licenses);
    });
}

function displayLicenses(licenses) {
    const tbody = document.getElementById('licensesTable');
    const count = document.getElementById('licCount');
    if (!licenses.length) {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:40px;color:var(--t4);">No licenses found</td></tr>';
        count.textContent = '';
        return;
    }
    count.textContent = licenses.length + ' record' + (licenses.length !== 1 ? 's' : '');

    tbody.innerHTML = licenses.map(lic => {
        const today = new Date(); today.setHours(0,0,0,0);
        const exp = new Date(lic.expire_date);
        const daysLeft = Math.ceil((exp - today) / 86400000);

        let rowClass = '';
        let daysHtml = '';
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
            active: '<span class="pill pill-active">Active</span>',
            expired: '<span class="pill pill-expired">Expired</span>',
            suspended: '<span class="pill pill-suspended">Suspended</span>'
        }[lic.status] || '<span class="pill">' + lic.status + '</span>';

        const added = new Date(lic.created_at).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'});
        const appLink = lic.branch_app_link
            ? '<a href="' + esc(lic.branch_app_link) + '" target="_blank" class="link-cell" title="' + esc(lic.branch_app_link) + '" style="color:var(--blue);text-decoration:none;display:block;">' + esc(lic.branch_app_link) + '</a>'
            : '<span style="color:var(--t4);">&mdash;</span>';

        return `<tr class="${rowClass}">
            <td style="font-weight:600;">${esc(lic.branch_name)}</td>
            <td class="link-cell">${appLink}</td>
            <td class="mono">${esc(lic.license_key)}</td>
            <td class="mono" style="font-size:.8rem;">${lic.expire_date}</td>
            <td>${daysHtml}</td>
            <td class="mono" style="font-size:.8rem;">${lic.last_renew_date || '<span style="color:var(--t4);">&mdash;</span>'}</td>
            <td style="font-size:.8rem;color:var(--t3);">${added}</td>
            <td>${statusPill}</td>
            <td style="white-space:nowrap;display:flex;gap:5px;padding:9px 12px;">
                <a href="edit_license.php?id=${lic.id}" class="act-btn act-edit" title="Edit"><i class="fas fa-pen"></i></a>
                <button class="act-btn act-del" onclick="confirmDelete(${lic.id}, '${esc(lic.branch_name)}')" title="Delete"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`;
    }).join('');
}

function esc(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

document.getElementById('addForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('addBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:.7rem;"></i> Adding…';
    const body = new URLSearchParams(new FormData(this));
    body.append('ajax','1'); body.append('action','add');
    fetch('', { method:'POST', body }).then(r => r.json()).then(data => {
        showToast(data.message, data.success ? 'success' : 'danger');
        if (data.success) { this.reset(); loadLicenses(); }
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-circle-plus" style="font-size:.75rem;"></i> Add License';
    });
});

function confirmDelete(id, name) {
    pendingDeleteId = id;
    document.getElementById('delBranchName').textContent = name;
    document.getElementById('delModal').style.display = 'flex';
}
function closeDelModal() {
    pendingDeleteId = null;
    document.getElementById('delModal').style.display = 'none';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDelModal(); });

document.getElementById('delConfirmBtn').addEventListener('click', function() {
    if (!pendingDeleteId) return;
    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:.65rem;"></i> Deleting…';
    fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'ajax=1&action=delete&id='+pendingDeleteId })
    .then(r => r.json()).then(data => {
        showToast(data.message, data.success ? 'success' : 'danger');
        if (data.success) { loadLicenses(); closeDelModal(); }
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-trash" style="font-size:.65rem;"></i> Delete';
    });
});

function showToast(msg, type) {
    const el = document.createElement('div');
    el.className = 'ft ' + type;
    el.innerHTML = '<i class="fas ' + (type==='success'?'fa-circle-check':'fa-circle-xmark') + '" style="font-size:.85rem;flex-shrink:0;"></i><span>' + esc(msg) + '</span><button class="ft-close" onclick="this.closest(\'.ft\').remove()"><i class="fas fa-xmark"></i></button>';
    document.getElementById('toastContainer').appendChild(el);
    setTimeout(() => { el.style.transition='opacity .4s'; el.style.opacity='0'; setTimeout(()=>el.remove(),400); }, 4000);
}
</script>
</body>
</html>