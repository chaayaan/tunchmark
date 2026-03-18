<?php
require 'auth.php';
include 'mydb.php';

// Handle ADD — must run before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_name'])) {
    $add_name  = trim($_POST['add_name']);
    $add_phone = trim($_POST['add_phone']);
    $add_addr  = trim($_POST['add_address']);
    $add_mfr   = trim($_POST['add_manufacturer']);
    $ins_stmt  = mysqli_prepare($conn,
        "INSERT INTO customers (name, phone, address, manufacturer) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($ins_stmt, 'ssss', $add_name, $add_phone, $add_addr, $add_mfr);
    mysqli_stmt_execute($ins_stmt);
    mysqli_stmt_close($ins_stmt);
    $redirect_params = [
        'page'     => 1,
        'per_page' => isset($_GET['per_page']) ? intval($_GET['per_page']) : 1000,
    ];
    if (!empty($_GET['search'])) $redirect_params['search'] = trim($_GET['search']);
    header('Location: customers_list.php?' . http_build_query($redirect_params));
    exit;
}

// Handle EDIT — must run before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $edit_id       = trim($_POST['edit_id']);
    $edit_name     = trim($_POST['edit_name']);
    $edit_phone    = trim($_POST['edit_phone']);
    $edit_address  = trim($_POST['edit_address']);
    $edit_mfr      = trim($_POST['edit_manufacturer']);
    $upd_stmt = mysqli_prepare($conn,
        "UPDATE customers SET name=?, phone=?, address=?, manufacturer=? WHERE id=?");
    mysqli_stmt_bind_param($upd_stmt, 'sssss',
        $edit_name, $edit_phone, $edit_address, $edit_mfr, $edit_id);
    mysqli_stmt_execute($upd_stmt);
    mysqli_stmt_close($upd_stmt);
    $redirect_params = [
        'page'     => isset($_GET['page'])     ? intval($_GET['page'])     : 1,
        'per_page' => isset($_GET['per_page']) ? intval($_GET['per_page']) : 1000,
    ];
    if (!empty($_GET['search'])) $redirect_params['search'] = trim($_GET['search']);
    header('Location: customers_list.php?' . http_build_query($redirect_params));
    exit;
}

// Handle DELETE — must run before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = trim($_POST['delete_id']);
    $del_stmt = mysqli_prepare($conn, "DELETE FROM customers WHERE id = ?");
    mysqli_stmt_bind_param($del_stmt, 's', $delete_id);
    mysqli_stmt_execute($del_stmt);
    mysqli_stmt_close($del_stmt);
    $redirect_params = [
        'page'     => isset($_GET['page'])     ? intval($_GET['page'])     : 1,
        'per_page' => isset($_GET['per_page']) ? intval($_GET['per_page']) : 1000,
    ];
    if (!empty($_GET['search'])) $redirect_params['search'] = trim($_GET['search']);
    header('Location: customers_list.php?' . http_build_query($redirect_params));
    exit;
}
?>
<?php include 'navbar.php'; ?>
<?php
// Pagination settings
$records_per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 1000;
if (!in_array($records_per_page, [500, 800, 1000])) {
    $records_per_page = 1000;
}

$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $records_per_page;

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
$params = [];
$types = '';

if (!empty($search)) {
    $search_param = '%' . $search . '%';
    $where_clause = "WHERE id LIKE ? OR name LIKE ? OR phone LIKE ?";
    $params = [$search, $search_param, $search_param];
    $types = 'sss';
}

// Count total records
$count_sql = "SELECT COUNT(*) as total FROM customers $where_clause";
if (!empty($params)) {
    $count_stmt = mysqli_prepare($conn, $count_sql);
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
} else {
    $count_result = mysqli_query($conn, $count_sql);
}
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch paginated records
$data_sql = "SELECT * FROM customers $where_clause ORDER BY id DESC LIMIT ? OFFSET ?";
if (!empty($params)) {
    $params[] = $records_per_page;
    $params[] = $offset;
    $types .= 'ii';
    $stmt = mysqli_prepare($conn, $data_sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
} else {
    $stmt = mysqli_prepare($conn, $data_sql);
    mysqli_stmt_bind_param($stmt, "ii", $records_per_page, $offset);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

function buildQueryString($page, $search, $per_page) {
    $params = ['page' => $page];
    if (!empty($search)) $params['search'] = $search;
    if ($per_page != 1000) $params['per_page'] = $per_page;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Customer List — Rajaiswari</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:       #f1f3f6;
            --surface:  #ffffff;
            --s2:       #fafbfc;
            --border:   #e4e7ec;
            --bsoft:    #f0f1f3;
            --t1:       #111827;
            --t2:       #374151;
            --t3:       #6b7280;
            --t4:       #9ca3af;
            --blue:     #2563eb; --blue-bg: #eff6ff; --blue-b: #bfdbfe;
            --green:    #059669; --green-bg:#ecfdf5; --green-b:#a7f3d0;
            --amber:    #d97706; --amber-bg:#fffbeb; --amber-b:#fde68a;
            --red:      #dc2626; --red-bg:  #fef2f2; --red-b:  #fecaca;
            --violet:   #7c3aed; --violet-bg:#f5f3ff;
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

        .page-shell {
            margin-left: 200px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .top-bar {
            position: sticky; top: 0; z-index: 200;
            height: 54px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            box-shadow: var(--sh);
            display: flex; align-items: center;
            padding: 0 22px; gap: 12px; flex-shrink: 0;
        }
        .tb-ico {
            width: 32px; height: 32px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; background: var(--blue-bg); color: var(--blue);
            flex-shrink: 0;
        }
        .tb-title { font-size: 1.0625rem; font-weight: 700; color: var(--t1); }
        .tb-sub   { font-size: .78rem; color: var(--t4); }
        .tb-right { margin-left: auto; display: flex; gap: 8px; align-items: center; }

        .btn-pos {
            display: inline-flex; align-items: center; gap: 6px;
            height: 34px; padding: 0 16px;
            border: none; border-radius: var(--rs);
            font-family: inherit; font-size: .875rem; font-weight: 600;
            cursor: pointer; transition: all .15s; text-decoration: none;
            white-space: nowrap;
        }
        .btn-green  { background: var(--green); color: #fff; }
        .btn-green:hover  { background: #047857; color: #fff; }
        .btn-ghost {
            background: var(--surface); color: var(--t2);
            border: 1.5px solid var(--border);
        }
        .btn-ghost:hover { background: var(--s2); border-color: #9ca3af; color: var(--t1); }

        .main {
            flex: 1;
            padding: 20px 22px 60px;
        }

        .pos-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            box-shadow: var(--sh);
            overflow: hidden;
        }

        .toolbar {
            display: flex; align-items: center;
            justify-content: space-between;
            padding: 12px 18px;
            border-bottom: 1px solid var(--bsoft);
            flex-wrap: wrap; gap: 10px;
        }
        .toolbar-left  { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .toolbar-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

        .stat-pill {
            display: inline-flex; align-items: center; gap: 5px;
            background: var(--blue-bg); border: 1px solid var(--blue-b);
            border-radius: 20px; padding: 3px 12px;
            font-size: .76rem; font-weight: 700; color: var(--blue);
        }

        .search-wrapper { position: relative; }
        .search-wrapper input {
            height: 34px; width: 260px;
            padding: 0 36px 0 11px;
            border: 1.5px solid var(--border); border-radius: var(--rs);
            font-family: inherit; font-size: .875rem; color: var(--t2);
            background: var(--surface); outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        .search-wrapper input:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37,99,235,.1);
        }
        .search-icon {
            position: absolute; right: 10px; top: 50%;
            transform: translateY(-50%);
            color: var(--t4); font-size: .8rem; pointer-events: none;
        }
        .search-loading {
            position: absolute; right: 10px; top: 50%;
            transform: translateY(-50%); display: none;
        }

        .sel-pp {
            height: 34px; padding: 0 28px 0 10px;
            border: 1.5px solid var(--border); border-radius: var(--rs);
            font-family: inherit; font-size: .875rem; color: var(--t2);
            background: var(--surface) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='7' viewBox='0 0 10 7'%3E%3Cpath fill='%239ca3af' d='M0 0l5 7 5-7z'/%3E%3C/svg%3E") no-repeat right 9px center;
            appearance: none; outline: none; cursor: pointer;
        }
        .sel-pp:focus { border-color: var(--blue); }

        /* ── Table ─────────────────────────────── */
        .pos-tbl { width: 100%; border-collapse: collapse; }
        .pos-tbl thead th {
            padding: 9px 14px;
            font-size: .72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .07em;
            color: var(--t4); background: var(--s2);
            border-bottom: 1px solid var(--border);
            white-space: nowrap; text-align: left;
        }
        .pos-tbl thead th:first-child { padding-left: 18px; }
        .pos-tbl thead th.col-actions { text-align: center; width: 90px; }
        .pos-tbl tbody td {
            padding: 10px 14px;
            font-size: .875rem; color: var(--t2);
            border-bottom: 1px solid var(--bsoft);
            vertical-align: middle;
        }
        .pos-tbl tbody td:first-child { padding-left: 18px; }
        .pos-tbl tbody tr:last-child td { border-bottom: none; }
        .pos-tbl tbody tr:hover td { background: #f8faff; }

        /* Actions cell */
        .td-actions {
            text-align: center;
            white-space: nowrap;
        }
        .action-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 30px; height: 30px;
            border-radius: var(--rs);
            border: 1.5px solid var(--border);
            background: var(--surface);
            color: var(--t3);
            font-size: .75rem;
            cursor: pointer;
            text-decoration: none;
            transition: all .15s;
        }
        .action-btn:hover { text-decoration: none; }
        .action-btn.edit:hover  {
            background: var(--blue-bg); border-color: var(--blue-b); color: var(--blue);
        }
        .action-btn.del:hover  {
            background: var(--red-bg); border-color: var(--red-b); color: var(--red);
        }
        .action-sep { display: inline-block; width: 4px; }

        .id-badge {
            display: inline-flex; align-items: center;
            background: var(--s2); border: 1px solid var(--border);
            border-radius: 5px; padding: 2px 8px;
            font-size: .78rem; font-weight: 600; color: var(--t3);
            font-family: 'DM Mono', monospace;
        }
        .cust-name  { font-weight: 600; color: var(--t1); }
        .cust-phone { font-family: 'DM Mono', monospace; font-size: .82rem; }
        .tag-mfr {
            display: inline-block;
            background: var(--violet-bg); color: var(--violet);
            border-radius: 4px; padding: 1px 7px;
            font-size: .74rem; font-weight: 600;
        }
        .text-dim { color: var(--t4); }

        .info-bar {
            display: flex; align-items: center;
            justify-content: space-between;
            padding: 8px 18px;
            background: var(--s2);
            border-bottom: 1px solid var(--bsoft);
            font-size: .8rem; color: var(--t3);
            flex-wrap: wrap; gap: 6px;
        }
        .search-tag {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--blue-bg); border: 1px solid var(--blue-b);
            border-radius: 5px; padding: 2px 9px;
            font-size: .78rem; font-weight: 600; color: var(--blue);
        }
        .clear-link {
            color: var(--red); font-weight: 600; text-decoration: none;
            font-size: .78rem;
        }
        .clear-link:hover { text-decoration: underline; color: var(--red); }

        .empty-state {
            text-align: center; padding: 56px 20px; color: var(--t4);
        }
        .empty-state i {
            font-size: 2.2rem; display: block;
            margin-bottom: 12px; opacity: .2;
        }
        .empty-state p { font-size: .9rem; margin: 0 0 16px; }

        .pager {
            display: flex; align-items: center;
            justify-content: space-between;
            padding: 12px 18px;
            background: var(--s2); border-top: 1px solid var(--border);
            flex-wrap: wrap; gap: 10px;
        }
        .pager-info { font-size: .8rem; color: var(--t3); font-weight: 500; }
        .pager-nav  { display: flex; gap: 3px; align-items: center; }
        .pager-btn {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 30px; height: 30px; padding: 0 8px;
            border: 1.5px solid var(--border); border-radius: var(--rs);
            font-size: .8rem; font-weight: 600; color: var(--t2);
            background: var(--surface); text-decoration: none;
            transition: all .15s;
        }
        .pager-btn:hover   { border-color: var(--blue); color: var(--blue); background: var(--blue-bg); }
        .pager-btn.active  { background: var(--blue); border-color: var(--blue); color: #fff; pointer-events: none; }
        .pager-btn.disabled{ opacity: .35; pointer-events: none; }

        /* ── Delete Modal ──────────────────────── */
        .modal-overlay {
            display: none;
            position: fixed; inset: 0; z-index: 9999;
            background: rgba(0,0,0,.35);
            align-items: center; justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,.15);
            width: 360px; max-width: calc(100vw - 32px);
            padding: 28px 24px 22px;
            animation: modal-in .18s ease;
        }
        @keyframes modal-in {
            from { transform: scale(.93) translateY(-8px); opacity: 0; }
            to   { transform: scale(1)  translateY(0);     opacity: 1; }
        }
        .modal-icon {
            width: 44px; height: 44px; border-radius: 50%;
            background: var(--red-bg); border: 1px solid var(--red-b);
            display: flex; align-items: center; justify-content: center;
            color: var(--red); font-size: 1.1rem;
            margin-bottom: 14px;
        }
        .modal-title {
            font-size: 1rem; font-weight: 700; color: var(--t1);
            margin-bottom: 6px;
        }
        .modal-body {
            font-size: .875rem; color: var(--t3);
            margin-bottom: 20px; line-height: 1.55;
        }
        .modal-body strong { color: var(--t1); }
        .modal-footer { display: flex; gap: 8px; justify-content: flex-end; }
        .btn-cancel {
            height: 36px; padding: 0 18px;
            border: 1.5px solid var(--border); border-radius: var(--rs);
            background: var(--surface); color: var(--t2);
            font-family: inherit; font-size: .875rem; font-weight: 600;
            cursor: pointer; transition: all .15s;
        }
        .btn-cancel:hover { background: var(--s2); border-color: #9ca3af; }
        .btn-del-confirm {
            height: 36px; padding: 0 18px;
            border: none; border-radius: var(--rs);
            background: var(--red); color: #fff;
            font-family: inherit; font-size: .875rem; font-weight: 600;
            cursor: pointer; transition: background .15s;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-del-confirm:hover { background: #b91c1c; }

        /* ── Edit modal fields ─────────────────── */
        .field-label {
            display: block; font-size: .75rem; font-weight: 600;
            color: var(--t3); margin-bottom: 5px; letter-spacing: .02em;
        }
        .field-input {
            width: 100%; height: 36px; padding: 0 11px;
            border: 1.5px solid var(--border); border-radius: var(--rs);
            font-family: inherit; font-size: .875rem; color: var(--t1);
            background: var(--surface); outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        .field-input:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37,99,235,.1);
        }

        @media (max-width: 991.98px) {
            .page-shell { margin-left: 0; }
            .top-bar    { top: 52px; }
            .main       { padding: 14px 14px 50px; }
            .search-wrapper input { width: 190px; }
        }
    </style>
</head>
<body>

<!-- Add Customer Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal-box" style="width:440px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div class="modal-icon" style="margin:0;background:var(--green-bg);border-color:var(--green-b);color:var(--green);width:36px;height:36px;font-size:.9rem;flex-shrink:0;">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div>
                    <div class="modal-title" style="margin:0;">New Customer</div>
                    <div style="font-size:.76rem;color:var(--t4);margin-top:1px;">Fill in the details below</div>
                </div>
            </div>
            <button onclick="closeAddModal()" style="background:none;border:none;cursor:pointer;color:var(--t4);font-size:.95rem;padding:4px;line-height:1;" title="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="addForm">
            <div style="display:flex;flex-direction:column;gap:13px;margin-bottom:20px;">
                <div>
                    <label class="field-label">Name <span style="color:var(--red);">*</span></label>
                    <input type="text" name="add_name" id="addName" class="field-input" placeholder="Customer name" required>
                </div>
                <div>
                    <label class="field-label">Phone</label>
                    <input type="text" name="add_phone" id="addPhone" class="field-input" placeholder="Phone number">
                </div>
                <div>
                    <label class="field-label">Address</label>
                    <input type="text" name="add_address" id="addAddress" class="field-input" placeholder="Address">
                </div>
                <div>
                    <label class="field-label">Manufacturer</label>
                    <input type="text" name="add_manufacturer" id="addManufacturer" class="field-input" placeholder="Manufacturer (optional)">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                <button type="submit" class="btn-del-confirm" style="background:var(--green);">
                    <i class="fas fa-user-plus" style="font-size:.7rem;"></i> Add Customer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Customer Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box" style="width:440px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div class="modal-icon" style="margin:0;background:var(--blue-bg);border-color:var(--blue-b);color:var(--blue);width:36px;height:36px;font-size:.9rem;flex-shrink:0;">
                    <i class="fas fa-pen"></i>
                </div>
                <div>
                    <div class="modal-title" style="margin:0;">Edit Customer</div>
                    <div style="font-size:.76rem;color:var(--t4);margin-top:1px;" id="editModalId"></div>
                </div>
            </div>
            <button onclick="closeEditModal()" style="background:none;border:none;cursor:pointer;color:var(--t4);font-size:.95rem;padding:4px;line-height:1;" title="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="edit_id" id="editIdInput">
            <div style="display:flex;flex-direction:column;gap:13px;margin-bottom:20px;">
                <div>
                    <label class="field-label">Name</label>
                    <input type="text" name="edit_name" id="editName" class="field-input" placeholder="Customer name" required>
                </div>
                <div>
                    <label class="field-label">Phone</label>
                    <input type="text" name="edit_phone" id="editPhone" class="field-input" placeholder="Phone number">
                </div>
                <div>
                    <label class="field-label">Address</label>
                    <input type="text" name="edit_address" id="editAddress" class="field-input" placeholder="Address">
                </div>
                <div>
                    <label class="field-label">Manufacturer</label>
                    <input type="text" name="edit_manufacturer" id="editManufacturer" class="field-input" placeholder="Manufacturer (optional)">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-del-confirm" style="background:var(--blue);">
                    <i class="fas fa-check" style="font-size:.7rem;"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon"><i class="fas fa-trash-can"></i></div>
        <div class="modal-title">Delete Customer?</div>
        <div class="modal-body">
            You are about to delete <strong id="modalCustomerName"></strong>
            <span style="color:var(--t4);font-size:.8rem;display:block;margin-top:4px;" id="modalCustomerId"></span>
            This action cannot be undone.
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
            <form method="POST" id="deleteForm" style="margin:0;">
                <input type="hidden" name="delete_id" id="deleteIdInput">
                <button type="submit" class="btn-del-confirm">
                    <i class="fas fa-trash-can" style="font-size:.7rem;"></i> Delete
                </button>
            </form>
        </div>
    </div>
</div>

<div class="page-shell">

    <!-- Top Bar -->
    <header class="top-bar">
        <div class="tb-ico"><i class="fas fa-users"></i></div>
        <div>
            <div class="tb-title">Customers</div>
            <div class="tb-sub">View and manage customer records</div>
        </div>
        <div class="tb-right">
            <button type="button" class="btn-pos btn-green" onclick="openAddModal()">
                <i class="fas fa-user-plus" style="font-size:.65rem;"></i> New Customer
            </button>
        </div>
    </header>

    <div class="main">
        <div class="pos-card">

            <!-- Toolbar -->
            <div class="toolbar">
                <div class="toolbar-left">
                    <span class="stat-pill">
                        <i class="fas fa-users" style="font-size:.6rem;"></i>
                        <span id="totalRecords"><?= number_format($total_records) ?></span> Customers
                    </span>
                    <?php if (!empty($search)): ?>
                        <span class="search-tag">
                            <i class="fas fa-magnifying-glass" style="font-size:.6rem;"></i>
                            "<?= htmlspecialchars($search) ?>"
                        </span>
                        <a href="customers_list.php?per_page=<?= $records_per_page ?>" class="clear-link">
                            <i class="fas fa-times" style="font-size:.65rem;"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
                <div class="toolbar-right">
                    <form method="GET" id="searchForm" style="display:flex;gap:7px;align-items:center;">
                        <input type="hidden" name="per_page" value="<?= $records_per_page ?>">
                        <div class="search-wrapper">
                            <input type="text"
                                   id="searchInput"
                                   name="search"
                                   placeholder="Search ID, name, phone…"
                                   value="<?= htmlspecialchars($search) ?>"
                                   autocomplete="off">
                            <i class="fas fa-magnifying-glass search-icon" id="searchIco"></i>
                            <div class="search-loading" id="searchLoading">
                                <svg width="14" height="14" viewBox="0 0 14 14">
                                    <circle cx="7" cy="7" r="5.5" stroke="#2563eb" stroke-width="2"
                                        fill="none" stroke-dasharray="22" stroke-dashoffset="8">
                                        <animateTransform attributeName="transform" type="rotate"
                                            from="0 7 7" to="360 7 7" dur=".7s" repeatCount="indefinite"/>
                                    </circle>
                                </svg>
                            </div>
                        </div>
                    </form>
                    <select class="sel-pp" id="perPageSelect"
                        onchange="window.location.href='<?= buildQueryString(1, $search, '') ?>&per_page=' + this.value">
                        <option value="500"  <?= $records_per_page == 500  ? 'selected' : '' ?>>500 / page</option>
                        <option value="800"  <?= $records_per_page == 800  ? 'selected' : '' ?>>800 / page</option>
                        <option value="1000" <?= $records_per_page == 1000 ? 'selected' : '' ?>>1000 / page</option>
                    </select>
                </div>
            </div>

            <!-- Info bar -->
            <div class="info-bar">
                <span>
                    Showing
                    <strong><span id="showingFrom"><?= number_format(min($offset + 1, $total_records)) ?></span></strong>
                    –
                    <strong><span id="showingTo"><?= number_format(min($offset + $records_per_page, $total_records)) ?></span></strong>
                    of <strong><?= number_format($total_records) ?></strong>
                </span>
                <span>Page <?= $current_page ?> of <?= number_format(max(1,$total_pages)) ?></span>
            </div>

            <!-- Table -->
            <div id="tableContainer" style="overflow-x:auto;">
                <?php if (mysqli_num_rows($result) > 0): ?>
                <table class="pos-tbl">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Manufacturer</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="customerTableBody">
                        <?php while ($row = mysqli_fetch_assoc($result)):
                            $eid  = htmlspecialchars($row['id']);
                            $name = htmlspecialchars($row['name']);
                        ?>
                        <tr>
                            <td><span class="id-badge"><?= $eid ?></span></td>
                            <td><span class="cust-name"><?= $name ?></span></td>
                            <td><span class="cust-phone"><?= htmlspecialchars($row['phone']) ?></span></td>
                            <td class="<?= empty($row['address']) || $row['address']==='N/A' ? 'text-dim' : '' ?>">
                                <?= htmlspecialchars($row['address'] ?? 'N/A') ?>
                            </td>
                            <td>
                                <?php if (!empty($row['manufacturer'])): ?>
                                    <span class="tag-mfr"><?= htmlspecialchars($row['manufacturer']) ?></span>
                                <?php else: ?>
                                    <span class="text-dim">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td class="td-actions">
                                <button type="button"
                                        class="action-btn edit"
                                        title="Edit Customer"
                                        onclick="openEditModal('<?= addslashes($eid) ?>', '<?= addslashes($name) ?>', '<?= addslashes(htmlspecialchars($row['phone'])) ?>', '<?= addslashes(htmlspecialchars($row['address'] ?? '')) ?>', '<?= addslashes(htmlspecialchars($row['manufacturer'] ?? '')) ?>')">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <span class="action-sep"></span>
                                <button type="button"
                                        class="action-btn del"
                                        title="Delete Customer"
                                        onclick="confirmDelete('<?= addslashes($eid) ?>', '<?= addslashes($name) ?>')">
                                    <i class="fas fa-trash-can"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <?php if (!empty($search)): ?>
                        <p>No customers found for "<strong><?= htmlspecialchars($search) ?></strong>"</p>
                        <a href="customers_list.php" class="btn-pos btn-ghost" style="display:inline-flex;">
                            View all customers
                        </a>
                    <?php else: ?>
                        <p>No customers yet.</p>
                        <a href="create_customer.php" class="btn-pos btn-green" style="display:inline-flex;">
                            <i class="fas fa-user-plus" style="font-size:.65rem;"></i> Add First Customer
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pager" id="paginationContainer">
                <div class="pager-info">
                    Page <?= $current_page ?> of <?= number_format($total_pages) ?>
                </div>
                <div class="pager-nav">
                    <a class="pager-btn <?= $current_page == 1 ? 'disabled' : '' ?>"
                       href="<?= buildQueryString(1, $search, $records_per_page) ?>">
                        <i class="fas fa-angles-left" style="font-size:.6rem;"></i>
                    </a>
                    <a class="pager-btn <?= $current_page == 1 ? 'disabled' : '' ?>"
                       href="<?= buildQueryString($current_page - 1, $search, $records_per_page) ?>">
                        <i class="fas fa-angle-left" style="font-size:.6rem;"></i>
                    </a>
                    <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page   = min($total_pages, $current_page + 2);
                        for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                    <a class="pager-btn <?= $i == $current_page ? 'active' : '' ?>"
                       href="<?= buildQueryString($i, $search, $records_per_page) ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <a class="pager-btn <?= $current_page == $total_pages ? 'disabled' : '' ?>"
                       href="<?= buildQueryString($current_page + 1, $search, $records_per_page) ?>">
                        <i class="fas fa-angle-right" style="font-size:.6rem;"></i>
                    </a>
                    <a class="pager-btn <?= $current_page == $total_pages ? 'disabled' : '' ?>"
                       href="<?= buildQueryString($total_pages, $search, $records_per_page) ?>">
                        <i class="fas fa-angles-right" style="font-size:.6rem;"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /pos-card -->
    </div><!-- /main -->
</div><!-- /page-shell -->

<script>
// ── Add modal ───────────────────────────────────────────
function openAddModal() {
    document.getElementById('addForm').reset();
    document.getElementById('addModal').classList.add('active');
    setTimeout(() => document.getElementById('addName').focus(), 80);
}
function closeAddModal() {
    document.getElementById('addModal').classList.remove('active');
}
document.getElementById('addModal').addEventListener('click', function(e) {
    if (e.target === this) closeAddModal();
});

// ── Edit modal ──────────────────────────────────────────
function openEditModal(id, name, phone, address, manufacturer) {
    document.getElementById('editIdInput').value      = id;
    document.getElementById('editModalId').textContent = 'ID: ' + id;
    document.getElementById('editName').value         = name;
    document.getElementById('editPhone').value        = phone;
    document.getElementById('editAddress').value      = address === 'N/A' ? '' : address;
    document.getElementById('editManufacturer').value = manufacturer;
    document.getElementById('editModal').classList.add('active');
    setTimeout(() => document.getElementById('editName').focus(), 80);
}
function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

// ── Delete modal ────────────────────────────────────────
function confirmDelete(id, name) {
    document.getElementById('modalCustomerName').textContent = name;
    document.getElementById('modalCustomerId').textContent   = 'ID: ' + id;
    document.getElementById('deleteIdInput').value           = id;
    document.getElementById('deleteModal').classList.add('active');
}
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
}
// Close on backdrop click
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});
// Close on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeAddModal(); closeDeleteModal(); closeEditModal(); }
});

// ── Real-time search ────────────────────────────────────
(function() {
    const searchInput   = document.getElementById('searchInput');
    const searchLoading = document.getElementById('searchLoading');
    const searchIco     = document.getElementById('searchIco');
    const searchForm    = document.getElementById('searchForm');
    const perPageSelect = document.getElementById('perPageSelect');

    let searchTimeout;
    let currentRequest = null;

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        if (query.length >= 1) {
            searchLoading.style.display = 'block';
            searchIco.style.display     = 'none';
        } else {
            searchLoading.style.display = 'none';
            searchIco.style.display     = 'block';
        }
        searchTimeout = setTimeout(() => { performSearch(query); }, 500);
    });

    function performSearch(query) {
        if (currentRequest) { currentRequest.abort(); }
        const perPage = perPageSelect.value;
        const url = query.length >= 1
            ? `customers_list.php?search=${encodeURIComponent(query)}&per_page=${perPage}&page=1`
            : `customers_list.php?per_page=${perPage}`;
        window.history.pushState({}, '', url);
        currentRequest = new XMLHttpRequest();
        currentRequest.open('GET', url, true);
        currentRequest.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        currentRequest.onload = function() {
            if (this.status === 200) {
                updatePageContent(this.responseText);
                searchLoading.style.display = 'none';
                searchIco.style.display     = 'block';
            }
        };
        currentRequest.onerror = function() {
            searchLoading.style.display = 'none';
            searchIco.style.display     = 'block';
        };
        currentRequest.send();
    }

    function updatePageContent(html) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        const newTbl = doc.getElementById('tableContainer');
        const curTbl = document.getElementById('tableContainer');
        if (newTbl && curTbl) curTbl.innerHTML = newTbl.innerHTML;

        const newTotal = doc.getElementById('totalRecords');
        const curTotal = document.getElementById('totalRecords');
        if (newTotal && curTotal) curTotal.textContent = newTotal.textContent;

        const newFrom = doc.getElementById('showingFrom');
        const curFrom = document.getElementById('showingFrom');
        if (newFrom && curFrom) curFrom.textContent = newFrom.textContent;

        const newTo = doc.getElementById('showingTo');
        const curTo = document.getElementById('showingTo');
        if (newTo && curTo) curTo.textContent = newTo.textContent;
    }

    searchForm.addEventListener('submit', function(e) {
        e.preventDefault();
        performSearch(searchInput.value.trim());
    });
})();
</script>

</body>
</html>