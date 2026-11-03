<?php
require 'auth.php';
require 'mydb.php';

require_admin();

// ---------- Helpers ----------
// Account type (form) <-> role + is_engineer (DB)
function typeToDb(string $type): ?array {
    switch ($type) {
        case 'admin':    return ['admin',    0];
        case 'employee': return ['employee', 0];
        case 'engineer': return ['employee', 1];
    }
    return null;
}
function dbToType(array $u): string {
    if ($u['role'] === 'admin') return 'admin';
    return !empty($u['is_engineer']) ? 'engineer' : 'employee';
}

function validateUsername(string $u): ?string {
    if ($u === '')                              return "Username is required.";
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $u))   return "Username can only contain letters, numbers, and underscores.";
    if (strlen($u) < 3)                         return "Username must be at least 3 characters long.";
    if (strlen($u) > 50)                        return "Username must be less than 50 characters.";
    return null;
}

function validateName(string $n): ?string {
    if ($n === '')                       return "Name is required.";
    if (mb_strlen($n) < 2)               return "Name must be at least 2 characters long.";
    if (mb_strlen($n) > 100)             return "Name must be less than 100 characters.";
    return null;
}

$action = $_GET['action'] ?? 'list';           // list | add | edit
if (!in_array($action, ['list', 'add', 'edit'], true)) $action = 'list';
$errors = [];

// ---------- SOFT DELETE (deactivate) / REACTIVATE ----------
// Rows are never removed; is_active = 0 only switches the login off.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['delete_user_id']) || isset($_POST['reactivate_user_id']))) {
    $reactivate = isset($_POST['reactivate_user_id']);
    $targetId   = intval($reactivate ? $_POST['reactivate_user_id'] : $_POST['delete_user_id']);
    if ($targetId > 1 && $targetId !== (int)$_SESSION['user_id']) {
        $flag = $reactivate ? 1 : 0;
        $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ? AND id > 1");
        $stmt->bind_param("ii", $flag, $targetId);
        $ok = $stmt->execute();
        $stmt->close();
        $okMsg = $reactivate ? "reactivated" : "deactivated";
        header("Location: users.php?" . ($ok ? "success=$okMsg" : "error=delete_failed")); exit;
    }
    header("Location: users.php?error=permission_denied"); exit;
}

// ---------- ADD ----------
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['name'] ?? '');
    if ($e = validateName($fullName)) $errors[] = $e;
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $type     = $_POST['account_type'] ?? '';

    if ($e = validateUsername($username)) $errors[] = $e;

    if ($password === '')               $errors[] = "Password is required.";
    elseif (strlen($password) < 6)      $errors[] = "Password must be at least 6 characters long.";
    elseif (strlen($password) > 255)    $errors[] = "Password is too long.";
    if ($password !== $confirm)         $errors[] = "Passwords do not match.";

    $db = typeToDb($type);
    if (!$db) $errors[] = "Invalid account type selected.";

    if (!$errors) {
        $c = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $c->bind_param("s", $username);
        $c->execute();
        if ($c->get_result()->num_rows > 0) $errors[] = "Username already exists.";
        $c->close();
    }

    if (!$errors) {
        [$role, $isEng] = $db;
        $hp   = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, username, password, role, is_engineer) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $fullName, $username, $hp, $role, $isEng);
        if ($stmt->execute()) { $stmt->close(); header("Location: users.php?success=added"); exit; }
        $errors[] = "Error creating user: " . $conn->error;
        $stmt->close();
    }
}

// ---------- EDIT (load + save) ----------
$editUser = null;
if ($action === 'edit') {
    $editId = intval($_GET['id'] ?? 0);
    if ($editId <= 1) { header("Location: users.php?error=permission_denied"); exit; }

    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND id > 1 LIMIT 1");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$editUser) { header("Location: users.php?error=permission_denied"); exit; }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $fullName       = trim($_POST['name'] ?? '');
        if ($e = validateName($fullName)) $errors[] = $e;
        $username       = trim($_POST['username'] ?? '');
        $type           = $_POST['account_type'] ?? '';
        $changePassword = isset($_POST['change_password']);
        $newPassword    = $_POST['new_password'] ?? '';
        $confirmPw      = $_POST['confirm_password'] ?? '';

        if ($e = validateUsername($username)) $errors[] = $e;

        $db = typeToDb($type);
        if (!$db) $errors[] = "Invalid account type selected.";

        if (!$errors) {
            $c = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $c->bind_param("si", $username, $editId);
            $c->execute();
            if ($c->get_result()->num_rows > 0) $errors[] = "Username already exists.";
            $c->close();
        }

        if ($changePassword) {
            if ($newPassword === '')           $errors[] = "New password is required when changing password.";
            elseif (strlen($newPassword) < 6)  $errors[] = "New password must be at least 6 characters long.";
            if ($newPassword !== $confirmPw)   $errors[] = "New passwords do not match.";
        }

        if (!$errors) {
            [$role, $isEng] = $db;
            if ($changePassword) {
                $hp   = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET name=?, username=?, password=?, role=?, is_engineer=? WHERE id=? AND id > 1");
                $stmt->bind_param("ssssii", $fullName, $username, $hp, $role, $isEng, $editId);
            } else {
                $stmt = $conn->prepare("UPDATE users SET name=?, username=?, role=?, is_engineer=? WHERE id=? AND id > 1");
                $stmt->bind_param("sssii", $fullName, $username, $role, $isEng, $editId);
            }
            if ($stmt->execute()) {
                $stmt->close();
                // keep own session in sync if admin edited themselves
                if ($editId === (int)$_SESSION['user_id']) {
                    $_SESSION['username']    = $username;
                    $_SESSION['name']        = $fullName;
                    $_SESSION['role']        = $role;
                    $_SESSION['is_engineer'] = $isEng;
                }
                header("Location: users.php?success=updated"); exit;
            }
            $errors[] = "Error updating user: " . $conn->error;
            $stmt->close();
        }
    }
}

// ---------- LIST data ----------
$message = ''; $messageType = 'success';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added':   $message = "User created successfully."; break;
        case 'updated': $message = "User updated successfully."; break;
        case 'deactivated': $message = "User deactivated. Their login is now off."; break;
        case 'reactivated': $message = "User reactivated. They can log in again."; break;
    }
}
if (isset($_GET['error'])) {
    $messageType = 'danger';
    switch ($_GET['error']) {
        case 'delete_failed':     $message = "Failed to update user status."; break;
        case 'permission_denied': $message = "Permission denied."; break;
    }
}

$users = []; $search = ''; $totalUsers = 0;
if ($action === 'list') {
    $search = trim($_GET['search'] ?? '');
    $sql = "SELECT * FROM users WHERE id > 1";
    if ($search !== '') {
        // match role names and the engineer label too
        $sql .= " AND (name LIKE ? OR username LIKE ? OR role LIKE ? OR (? LIKE '%engineer%' AND is_engineer = 1))";
    }
    $sql .= " ORDER BY id ASC";
    $stmt = $conn->prepare($sql);
    if ($search !== '') {
        $like = "%$search%";
        $stmt->bind_param("ssss", $like, $like, $like, $search);
    }
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $totalUsers = (int)$conn->query("SELECT COUNT(*) AS total FROM users WHERE id > 1")->fetch_assoc()['total'];
}

// form values (repopulate on error)
$fv_username = htmlspecialchars($_POST['username'] ?? ($editUser['username'] ?? ''));
$fv_name     = htmlspecialchars($_POST['name'] ?? ($editUser['name'] ?? ''));
$fv_type     = $_POST['account_type'] ?? ($editUser ? dbToType($editUser) : '');
$serialNumber = 1;
$myId = (int)$_SESSION['user_id'];

$pageTitle = $action === 'add' ? 'Add New User' : ($action === 'edit' ? 'Edit User' : 'Manage Users');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $pageTitle ?> — Rajaiswari</title>
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
      --violet:#7c3aed;--violet-bg:#f5f3ff;--violet-b:#ddd6fe;
      --teal:#0f766e;  --teal-bg:#f0fdfa;  --teal-b:#99f6e4;
      --r:10px; --rs:6px;
      --sh:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'DM Sans',-apple-system,sans-serif;font-size:14.5px;background:var(--bg);color:var(--t1);-webkit-font-smoothing:antialiased;min-height:100vh;}
    .page-shell{margin-left:200px;min-height:100vh;display:flex;flex-direction:column;}
    .top-bar{position:sticky;top:0;z-index:200;height:54px;background:var(--surface);border-bottom:1px solid var(--border);box-shadow:var(--sh);display:flex;align-items:center;padding:0 22px;gap:10px;flex-shrink:0;}
    .tb-ico{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;}
    .tb-title{font-size:1.0625rem;font-weight:700;color:var(--t1);line-height:1.2;}
    .tb-sub{font-size:.8rem;color:var(--t4);}
    .tb-right{margin-left:auto;display:flex;align-items:center;gap:8px;}
    .tb-count{display:inline-flex;align-items:center;gap:5px;background:var(--surface-2);border:1px solid var(--border);border-radius:var(--rs);padding:4px 12px;font-family:'DM Mono',monospace;font-size:.82rem;font-weight:500;color:var(--t3);}
    .tb-back{display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 14px;background:var(--surface-2);border:1.5px solid var(--border);border-radius:var(--rs);font-size:.82rem;font-weight:600;color:var(--t2);text-decoration:none;transition:all .15s;}
    .tb-back:hover{background:var(--border);color:var(--t1);}
    .btn-add{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 16px;background:var(--green);border:none;border-radius:var(--rs);font-size:.875rem;font-weight:700;color:#fff;text-decoration:none;cursor:pointer;transition:background .15s;}
    .btn-add:hover{background:#047857;color:#fff;}
    .main{flex:1;padding:20px 22px 60px;display:flex;flex-direction:column;gap:14px;}
    .main.center{align-items:center;}
    .form-wrap{width:100%;max-width:680px;display:flex;flex-direction:column;gap:14px;}

    .alert-flash{border-radius:var(--rs);padding:12px 16px;font-size:.875rem;display:flex;align-items:center;gap:9px;}
    .af-success{background:var(--green-bg);border:1px solid var(--green-b);border-left:3px solid var(--green);color:#065f46;}
    .af-danger {background:var(--red-bg);  border:1px solid var(--red-b);  border-left:3px solid var(--red);  color:#7f1d1d;}
    .alert-err{background:var(--red-bg);border:1px solid var(--red-b);border-left:3px solid var(--red);border-radius:var(--rs);padding:13px 16px;}
    .alert-err-title{font-size:.875rem;font-weight:700;color:var(--red);margin-bottom:6px;}
    .alert-err ul{margin:0;padding-left:16px;}
    .alert-err li{font-size:.82rem;color:var(--red);}

    .sec{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;}
    .sec-head{display:flex;align-items:center;gap:9px;padding:12px 18px;background:var(--surface-2);border-bottom:1px solid var(--bsoft);}
    .sec-ico{width:28px;height:28px;border-radius:var(--rs);display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;}
    .i-blue{background:var(--blue-bg);color:var(--blue);} .i-violet{background:var(--violet-bg);color:var(--violet);}
    .i-amber{background:var(--amber-bg);color:var(--amber);} .i-green{background:var(--green-bg);color:var(--green);}
    .i-red{background:var(--red-bg);color:var(--red);} .i-teal{background:var(--teal-bg);color:var(--teal);}
    .sec-title{font-size:.9375rem;font-weight:700;color:var(--t1);letter-spacing:-.01em;}
    .sec-meta{margin-left:auto;font-size:.78rem;color:var(--t4);}
    .sec-body{padding:18px;}

    .search-row{display:flex;gap:8px;align-items:center;}
    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
    .lbl{display:block;font-size:.76rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t3);margin-bottom:5px;}
    .lbl .req{color:var(--red);margin-left:2px;}
    .fhint{font-size:.74rem;color:var(--t4);margin-top:3px;}
    .fc-wrap{position:relative;}
    .fc{width:100%;height:38px;padding:0 11px;border:1.5px solid var(--border);border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.9rem;color:var(--t2);background:var(--surface);outline:none;transition:border-color .15s,box-shadow .15s;appearance:none;}
    .fc:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
    .fc-with-btn{padding-right:42px;}
    .fc-eye{position:absolute;right:0;top:0;width:38px;height:38px;display:flex;align-items:center;justify-content:center;background:none;border:none;color:var(--t4);cursor:pointer;font-size:13px;}
    .fc-eye:hover{color:var(--t2);}
    select.fc{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%239ca3af' d='M1 1l5 5 5-5'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;padding-right:28px;}
    .btn-search{display:inline-flex;align-items:center;gap:6px;height:38px;padding:0 18px;background:var(--blue);border:none;border-radius:var(--rs);font-size:.875rem;font-weight:700;color:#fff;cursor:pointer;white-space:nowrap;}
    .btn-search:hover{background:#1d4ed8;}
    .btn-clear{display:inline-flex;align-items:center;gap:5px;height:38px;padding:0 14px;background:var(--surface-2);border:1.5px solid var(--border);border-radius:var(--rs);font-size:.82rem;font-weight:600;color:var(--t2);text-decoration:none;white-space:nowrap;}
    .btn-clear:hover{background:var(--border);color:var(--t1);}

    .strength-bar-wrap{height:4px;border-radius:2px;background:var(--bsoft);margin-top:6px;overflow:hidden;}
    .strength-bar{height:100%;width:0;border-radius:2px;transition:width .3s,background .3s;}
    .match-ok{font-size:.74rem;color:var(--green);margin-top:3px;}
    .match-fail{font-size:.74rem;color:var(--red);margin-top:3px;}

    .toggle-row{display:flex;align-items:center;gap:10px;padding:12px 18px;background:var(--surface-2);cursor:pointer;user-select:none;}
    .toggle-chk{width:18px;height:18px;border:2px solid var(--border);border-radius:4px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .toggle-chk.checked{background:var(--blue);border-color:var(--blue);color:#fff;}
    .toggle-lbl{font-size:.875rem;font-weight:600;color:var(--t2);}
    .pw-section{padding:18px;border-top:1px solid var(--bsoft);display:none;}
    .pw-section.open{display:block;}
    .note-amber{background:var(--amber-bg);border:1px solid var(--amber-b);border-left:3px solid var(--amber);border-radius:var(--rs);padding:10px 14px;font-size:.82rem;color:#78350f;margin-top:14px;}

    .perm-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;}
    .perm-card{background:var(--surface-2);border:1px solid var(--border);border-radius:var(--rs);padding:12px 14px;}
    .perm-title{font-size:.78rem;font-weight:700;margin-bottom:6px;display:flex;align-items:center;gap:6px;}
    .perm-list{list-style:none;padding:0;margin:0;}
    .perm-list li{font-size:.75rem;color:var(--t3);padding:2px 0;display:flex;align-items:center;gap:5px;}
    .perm-list li::before{content:'';width:4px;height:4px;border-radius:50%;background:var(--t4);flex-shrink:0;}

    .info-rows{padding:0 18px;}
    .info-row{display:flex;align-items:center;gap:10px;padding:11px 0;border-bottom:1px solid var(--bsoft);}
    .info-row:last-child{border-bottom:none;}
    .info-ico{width:26px;height:26px;border-radius:var(--rs);display:flex;align-items:center;justify-content:center;font-size:10px;flex-shrink:0;}
    .info-lbl{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t4);width:100px;flex-shrink:0;}
    .info-val{font-size:.875rem;font-weight:600;color:var(--t1);}
    .info-val.mono{font-family:'DM Mono',monospace;}

    .action-bar{display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:13px 18px;background:var(--surface-2);border-top:1px solid var(--border);}
    .btn-ghost{display:inline-flex;align-items:center;gap:6px;height:38px;padding:0 18px;background:var(--surface);border:1.5px solid var(--border);border-radius:7px;font-size:.9rem;font-weight:500;color:var(--t2);text-decoration:none;cursor:pointer;}
    .btn-ghost:hover{background:var(--surface-2);border-color:#9ca3af;color:var(--t1);}
    .btn-submit{display:inline-flex;align-items:center;gap:7px;height:38px;padding:0 24px;border:none;border-radius:7px;font-size:.9375rem;font-weight:700;color:#fff;cursor:pointer;}
    .btn-submit.add{background:var(--green);} .btn-submit.add:hover{background:#047857;}
    .btn-submit.edit{background:var(--amber);} .btn-submit.edit:hover{background:#b45309;}
    .btn-submit:disabled{background:var(--t4);cursor:not-allowed;}

    .utbl{width:100%;border-collapse:collapse;}
    .utbl thead th{padding:10px 16px;text-align:left;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--t4);background:var(--surface-2);border-bottom:1px solid var(--border);white-space:nowrap;}
    .utbl tbody td{padding:12px 16px;border-bottom:1px solid var(--bsoft);vertical-align:middle;font-size:.875rem;}
    .utbl tbody tr:last-child td{border-bottom:none;}
    .utbl tbody tr:hover td{background:#fafbff;}
    .user-avatar{width:34px;height:34px;border-radius:50%;background:var(--violet-bg);border:2px solid var(--violet-b);display:flex;align-items:center;justify-content:center;font-size:13px;color:var(--violet);font-weight:700;flex-shrink:0;}
    .user-name{font-weight:700;color:var(--t1);}
    .you-badge,.you-note{display:inline-flex;align-items:center;gap:3px;background:var(--blue-bg);border:1px solid var(--blue-b);color:var(--blue);padding:1px 7px;border-radius:20px;font-size:.68rem;font-weight:700;margin-left:6px;vertical-align:middle;}
    .role-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
    .role-admin{background:var(--red-bg);color:var(--red);border:1px solid var(--red-b);}
    .role-employee{background:var(--blue-bg);color:var(--blue);border:1px solid var(--blue-b);}
    .role-engineer{background:var(--teal-bg);color:var(--teal);border:1px solid var(--teal-b);}
    .date-mono{font-family:'DM Mono',monospace;font-size:.78rem;color:var(--t4);}
    .row-actions{display:flex;gap:6px;justify-content:center;}
    .btn-act{width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:var(--rs);font-size:12px;cursor:pointer;transition:all .15s;text-decoration:none;border:1.5px solid;}
    .btn-edit{background:var(--amber-bg);border-color:var(--amber-b);color:var(--amber);}
    .btn-edit:hover{background:var(--amber);color:#fff;border-color:var(--amber);}
    .btn-del{background:var(--red-bg);border-color:var(--red-b);color:var(--red);}
    .btn-del:hover{background:var(--red);color:#fff;border-color:var(--red);}
    .btn-lock{background:var(--surface-2);border-color:var(--border);color:var(--t4);cursor:not-allowed;}
    .empty-state{padding:52px 24px;text-align:center;}
    .empty-ico{font-size:2.8rem;color:var(--border);margin-bottom:12px;}
    .empty-title{font-size:.9375rem;font-weight:700;color:var(--t3);margin-bottom:6px;}
    .empty-sub{font-size:.82rem;color:var(--t4);}
    .note-box{background:var(--blue-bg);border:1px solid var(--blue-b);border-left:3px solid var(--blue);border-radius:var(--rs);padding:11px 14px;font-size:.82rem;color:#1e40af;line-height:1.5;}

    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9000;align-items:center;justify-content:center;}
    .modal-overlay.open{display:flex;}
    .modal-box{background:var(--surface);border-radius:var(--r);box-shadow:0 20px 60px rgba(0,0,0,.2);width:100%;max-width:420px;overflow:hidden;}
    .modal-hd{display:flex;align-items:center;gap:10px;padding:16px 20px;background:var(--red-bg);border-bottom:1px solid var(--red-b);}
    .modal-hd-ico{width:32px;height:32px;border-radius:var(--rs);border:1.5px solid var(--red-b);display:flex;align-items:center;justify-content:center;color:var(--red);font-size:13px;}
    .modal-hd-title{font-size:.9375rem;font-weight:700;color:var(--red);}
    .modal-body{padding:20px;}
    .modal-body p{font-size:.875rem;color:var(--t2);line-height:1.5;}
    .modal-body .warn{font-size:.78rem;color:var(--red);margin-top:8px;display:flex;align-items:center;gap:5px;}
    .modal-ft{display:flex;justify-content:flex-end;gap:8px;padding:14px 20px;background:var(--surface-2);border-top:1px solid var(--border);}
    .btn-cancel{display:inline-flex;align-items:center;gap:6px;height:36px;padding:0 16px;background:var(--surface);border:1.5px solid var(--border);border-radius:var(--rs);font-size:.875rem;font-weight:500;color:var(--t2);cursor:pointer;}
    .btn-confirm-del{display:inline-flex;align-items:center;gap:6px;height:36px;padding:0 18px;background:var(--red);border:none;border-radius:var(--rs);font-size:.875rem;font-weight:700;color:#fff;cursor:pointer;}
    .btn-confirm-del:hover{background:#b91c1c;}

    @media(max-width:991.98px){.page-shell{margin-left:0;}.top-bar{top:52px;}.main{padding:14px 14px 50px;}.grid-2,.perm-grid{grid-template-columns:1fr;}}
  </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="page-shell">

<?php /* =================== ADD / EDIT FORM =================== */ ?>
<?php if ($action === 'add' || $action === 'edit'): $isEdit = ($action === 'edit'); ?>

  <header class="top-bar">
    <div class="tb-ico" style="background:var(--<?= $isEdit ? 'amber' : 'green' ?>-bg);color:var(--<?= $isEdit ? 'amber' : 'green' ?>);">
      <i class="fas fa-<?= $isEdit ? 'user-pen' : 'user-plus' ?>"></i>
    </div>
    <div>
      <div class="tb-title"><?= $pageTitle ?></div>
      <div class="tb-sub"><?= $isEdit ? htmlspecialchars($editUser['username']) : 'Create a staff account' ?></div>
    </div>
    <div class="tb-right">
      <a href="users.php" class="tb-back"><i class="fas fa-arrow-left" style="font-size:.65rem;"></i> Back</a>
    </div>
  </header>

  <div class="main center">
    <div class="form-wrap">

      <?php if ($errors): ?>
      <div class="alert-err">
        <div class="alert-err-title"><i class="fas fa-circle-exclamation" style="margin-right:6px;"></i>Please fix the following errors:</div>
        <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
      </div>
      <?php endif; ?>

      <form method="POST" id="userForm">

        <div class="sec">
          <div class="sec-head">
            <span class="sec-ico <?= $isEdit ? 'i-amber' : 'i-green' ?>"><i class="fas fa-<?= $isEdit ? 'user-pen' : 'user-plus' ?>"></i></span>
            <span class="sec-title">Account Details</span>
          </div>
          <div class="sec-body">
            <div style="margin-bottom:14px;">
              <label class="lbl">Full Name <span class="req">*</span></label>
              <input type="text" name="name" class="fc" required minlength="2" maxlength="100"
                     value="<?= $fv_name ?>" placeholder="Enter full name">
              <div class="fhint">Shown across the system · 2–100 chars</div>
            </div>
            <div class="grid-2">
              <div>
                <label class="lbl">Username <span class="req">*</span></label>
                <input type="text" name="username" class="fc" required pattern="[a-zA-Z0-9_]+" minlength="3" maxlength="50"
                       value="<?= $fv_username ?>" placeholder="Enter username">
                <div class="fhint">Letters, numbers, underscores · 3–50 chars</div>
              </div>
              <div>
                <label class="lbl">Account Type <span class="req">*</span></label>
                <select name="account_type" class="fc" required>
                  <?php if (!$isEdit): ?><option value="">— Select type —</option><?php endif; ?>
                  <option value="employee" <?= $fv_type === 'employee' ? 'selected' : '' ?>>Employee</option>
                  <option value="engineer" <?= $fv_type === 'engineer' ? 'selected' : '' ?>>Engineer</option>
                  <option value="admin"    <?= $fv_type === 'admin'    ? 'selected' : '' ?>>Admin</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <?php if ($isEdit): ?>
        <!-- Optional password change -->
        <div class="sec">
          <div class="toggle-row" onclick="togglePasswordSection()">
            <div class="toggle-chk <?= isset($_POST['change_password']) ? 'checked' : '' ?>" id="toggleChk">
              <?php if (isset($_POST['change_password'])): ?><i class="fas fa-check" style="font-size:10px;"></i><?php endif; ?>
            </div>
            <input type="checkbox" name="change_password" id="changePassword" <?= isset($_POST['change_password']) ? 'checked' : '' ?> style="display:none;">
            <span class="toggle-lbl"><i class="fas fa-key" style="margin-right:6px;font-size:.75rem;color:var(--t4);"></i>Change Password</span>
          </div>
          <div class="pw-section <?= isset($_POST['change_password']) ? 'open' : '' ?>" id="pwSection">
            <div class="grid-2">
              <div>
                <label class="lbl">New Password <span class="req">*</span></label>
                <div class="fc-wrap">
                  <input type="password" name="new_password" id="pw1" class="fc fc-with-btn" minlength="6" maxlength="255" placeholder="New password">
                  <button type="button" class="fc-eye" onclick="toggleEye('pw1', this)"><i class="fas fa-eye"></i></button>
                </div>
                <div class="strength-bar-wrap"><div class="strength-bar" id="strengthBar"></div></div>
                <div class="fhint">Minimum 6 characters</div>
              </div>
              <div>
                <label class="lbl">Confirm Password <span class="req">*</span></label>
                <div class="fc-wrap">
                  <input type="password" name="confirm_password" id="pw2" class="fc fc-with-btn" minlength="6" maxlength="255" placeholder="Confirm new password">
                  <button type="button" class="fc-eye" onclick="toggleEye('pw2', this)"><i class="fas fa-eye"></i></button>
                </div>
                <div id="matchMsg"></div>
              </div>
            </div>
            <div class="note-amber"><i class="fas fa-triangle-exclamation" style="margin-right:5px;"></i>Changing the password will require the user to log in again with the new password.</div>
          </div>
        </div>
        <?php else: ?>
        <!-- Password (add) -->
        <div class="sec">
          <div class="sec-head">
            <span class="sec-ico i-violet"><i class="fas fa-key"></i></span>
            <span class="sec-title">Set Password</span>
          </div>
          <div class="sec-body">
            <div class="grid-2">
              <div>
                <label class="lbl">Password <span class="req">*</span></label>
                <div class="fc-wrap">
                  <input type="password" name="password" id="pw1" class="fc fc-with-btn" required minlength="6" maxlength="255" placeholder="Enter password">
                  <button type="button" class="fc-eye" onclick="toggleEye('pw1', this)"><i class="fas fa-eye"></i></button>
                </div>
                <div class="strength-bar-wrap"><div class="strength-bar" id="strengthBar"></div></div>
                <div class="fhint">Minimum 6 characters</div>
              </div>
              <div>
                <label class="lbl">Confirm Password <span class="req">*</span></label>
                <div class="fc-wrap">
                  <input type="password" name="confirm_password" id="pw2" class="fc fc-with-btn" required minlength="6" maxlength="255" placeholder="Confirm password">
                  <button type="button" class="fc-eye" onclick="toggleEye('pw2', this)"><i class="fas fa-eye"></i></button>
                </div>
                <div id="matchMsg"></div>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($isEdit): ?>
        <div class="sec">
          <div class="sec-head">
            <span class="sec-ico i-blue"><i class="fas fa-circle-info"></i></span>
            <span class="sec-title">Record Information</span>
          </div>
          <div class="info-rows">
            <div class="info-row">
              <div class="info-ico i-violet"><i class="fas fa-hashtag" style="font-size:.65rem;"></i></div>
              <div class="info-lbl">User ID</div>
              <div class="info-val mono">#<?= (int)$editUser['id'] ?></div>
            </div>
            <div class="info-row">
              <div class="info-ico i-amber"><i class="fas fa-calendar-plus" style="font-size:.65rem;"></i></div>
              <div class="info-lbl">Created</div>
              <div class="info-val mono"><?= date('M d, Y · H:i', strtotime($editUser['created_at'])) ?></div>
            </div>
            <div class="info-row">
              <div class="info-ico i-blue"><i class="fas fa-shield-halved" style="font-size:.65rem;"></i></div>
              <div class="info-lbl">Current Type</div>
              <div class="info-val"><?= ucfirst(dbToType($editUser)) ?>
                <?php if ((int)$editUser['id'] === $myId): ?><span class="you-note"><i class="fas fa-circle" style="font-size:.4rem;"></i> This is you</span><?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Permissions guide -->
        <div class="sec">
          <div class="sec-head">
            <span class="sec-ico i-blue"><i class="fas fa-shield-halved"></i></span>
            <span class="sec-title">Account Type Permissions</span>
          </div>
          <div class="sec-body">
            <div class="perm-grid">
              <div class="perm-card">
                <div class="perm-title" style="color:var(--blue);"><i class="fas fa-user" style="font-size:.7rem;"></i> Employee</div>
                <ul class="perm-list">
                  <li>Create &amp; manage orders</li><li>View customer list</li><li>Billing &amp; sales reports</li><li>Daily expenses</li>
                </ul>
              </div>
              <div class="perm-card">
                <div class="perm-title" style="color:var(--teal);"><i class="fas fa-helmet-safety" style="font-size:.7rem;"></i> Engineer</div>
                <ul class="perm-list">
                  <li>All employee permissions</li><li>Engineering module</li>
                </ul>
              </div>
              <div class="perm-card">
                <div class="perm-title" style="color:var(--red);"><i class="fas fa-shield-halved" style="font-size:.7rem;"></i> Admin</div>
                <ul class="perm-list">
                  <li>Everything, all modules</li><li>User management</li><li>Edit &amp; delete bills</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <div class="sec">
          <div class="action-bar">
            <a href="users.php" class="btn-ghost"><i class="fas fa-xmark" style="font-size:.7rem;"></i> Cancel</a>
            <button type="submit" class="btn-submit <?= $isEdit ? 'edit' : 'add' ?>" id="submitBtn">
              <i class="fas fa-<?= $isEdit ? 'floppy-disk' : 'user-plus' ?>" style="font-size:.75rem;"></i> <?= $isEdit ? 'Update User' : 'Create User' ?>
            </button>
          </div>
        </div>

      </form>
    </div>
  </div>

<script>
var IS_EDIT = <?= $isEdit ? 'true' : 'false' ?>;

function toggleEye(id, btn) {
  var f = document.getElementById(id), i = btn.querySelector('i');
  if (f.type === 'password') { f.type = 'text'; i.classList.replace('fa-eye', 'fa-eye-slash'); }
  else { f.type = 'password'; i.classList.replace('fa-eye-slash', 'fa-eye'); }
}

function togglePasswordSection() {
  var chk = document.getElementById('changePassword'), vis = document.getElementById('toggleChk'),
      sec = document.getElementById('pwSection'), p1 = document.getElementById('pw1'), p2 = document.getElementById('pw2');
  chk.checked = !chk.checked;
  if (chk.checked) {
    vis.classList.add('checked'); vis.innerHTML = '<i class="fas fa-check" style="font-size:10px;"></i>';
    sec.classList.add('open'); p1.required = p2.required = true;
  } else {
    vis.classList.remove('checked'); vis.innerHTML = '';
    sec.classList.remove('open'); p1.required = p2.required = false;
    p1.value = p2.value = '';
    document.getElementById('matchMsg').innerHTML = '';
    document.getElementById('strengthBar').style.width = '0';
    document.getElementById('submitBtn').disabled = false;
  }
}

document.getElementById('pw1').addEventListener('input', function () {
  var pw = this.value, s = 0, bar = document.getElementById('strengthBar');
  if (pw.length >= 6) s++; if (/[a-z]/.test(pw)) s++; if (/[A-Z]/.test(pw)) s++;
  if (/[0-9]/.test(pw)) s++; if (/[^a-zA-Z0-9]/.test(pw)) s++;
  bar.style.width = (s * 20) + '%';
  bar.style.background = s < 2 ? 'var(--red)' : s < 4 ? 'var(--amber)' : 'var(--green)';
});

document.getElementById('pw2').addEventListener('input', function () {
  var m = document.getElementById('matchMsg'), btn = document.getElementById('submitBtn');
  if (!this.value) { m.innerHTML = ''; btn.disabled = false; return; }
  if (this.value === document.getElementById('pw1').value) {
    m.innerHTML = '<div class="match-ok"><i class="fas fa-circle-check" style="font-size:.65rem;"></i> Passwords match</div>'; btn.disabled = false;
  } else {
    m.innerHTML = '<div class="match-fail"><i class="fas fa-circle-xmark" style="font-size:.65rem;"></i> Passwords do not match</div>'; btn.disabled = true;
  }
});

document.getElementById('userForm').addEventListener('submit', function (e) {
  var needPw = !IS_EDIT || document.getElementById('changePassword').checked;
  if (!needPw) return;
  var a = document.getElementById('pw1').value, b = document.getElementById('pw2').value;
  if (a !== b || a.length < 6) e.preventDefault();
});
</script>

<?php /* =================== LIST =================== */ ?>
<?php else: ?>

  <header class="top-bar">
    <div class="tb-ico" style="background:var(--violet-bg);color:var(--violet);"><i class="fas fa-users"></i></div>
    <div>
      <div class="tb-title">Manage Users</div>
      <div class="tb-sub">Admin access only</div>
    </div>
    <div class="tb-right">
      <div class="tb-count"><i class="fas fa-user" style="font-size:.6rem;"></i> <?= $totalUsers ?> user<?= $totalUsers !== 1 ? 's' : '' ?></div>
      <a href="users.php?action=add" class="btn-add"><i class="fas fa-user-plus" style="font-size:.7rem;"></i> Add User</a>
    </div>
  </header>

  <div class="main">

    <?php if ($message): ?>
    <div class="alert-flash <?= $messageType === 'success' ? 'af-success' : 'af-danger' ?>">
      <i class="fas fa-<?= $messageType === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
      <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <div class="sec">
      <div class="sec-head">
        <span class="sec-ico i-blue"><i class="fas fa-magnifying-glass"></i></span>
        <span class="sec-title">Search Users</span>
      </div>
      <div class="sec-body">
        <form method="get">
          <div class="search-row">
            <input type="text" name="search" class="fc" placeholder="Search by name, username, admin, employee or engineer…" value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn-search"><i class="fas fa-magnifying-glass" style="font-size:.72rem;"></i> Search</button>
            <?php if ($search !== ''): ?>
            <a href="users.php" class="btn-clear"><i class="fas fa-xmark" style="font-size:.72rem;"></i> Clear</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>

    <div class="sec">
      <div class="sec-head">
        <span class="sec-ico i-violet"><i class="fas fa-user-group"></i></span>
        <span class="sec-title">User Accounts</span>
        <span class="sec-meta">
          <?= $search !== '' ? 'Results for "' . htmlspecialchars($search) . '"' : count($users) . ' record' . (count($users) !== 1 ? 's' : '') ?>
        </span>
      </div>

      <?php if (empty($users)): ?>
      <div class="empty-state">
        <div class="empty-ico"><i class="fas fa-users"></i></div>
        <div class="empty-title"><?= $search !== '' ? 'No users match "' . htmlspecialchars($search) . '"' : 'No users yet' ?></div>
        <div class="empty-sub"><?= $search !== '' ? 'Try a different search term.' : '<a href="users.php?action=add">Add the first user</a>' ?></div>
      </div>
      <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="utbl">
          <thead>
            <tr>
              <th style="width:44px;">#</th>
              <th>User</th>
              <th style="width:130px;">Type</th>
              <th style="width:110px;">Status</th>
              <th style="width:160px;">Created</th>
              <th style="width:90px;text-align:center;">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($users as $user): $uType = dbToType($user); $uid = (int)$user['id'];
                $dispName = ($user['name'] ?? '') !== '' ? $user['name'] : $user['username']; ?>
            <tr style="<?= empty($user['is_active']) ? 'opacity:.55;' : '' ?>">
              <td style="font-family:'DM Mono',monospace;color:var(--t4);font-size:.78rem;"><?= $serialNumber++ ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:10px;">
                  <div class="user-avatar"><?= strtoupper(mb_substr($dispName, 0, 1)) ?></div>
                  <div>
                    <div class="user-name">
                      <?= htmlspecialchars($dispName) ?>
                      <?php if ($uid === $myId): ?><span class="you-badge"><i class="fas fa-circle" style="font-size:.4rem;"></i> You</span><?php endif; ?>
                    </div>
                    <div style="font-size:.75rem;color:var(--t4);font-family:'DM Mono',monospace;">@<?= htmlspecialchars($user['username']) ?> · ID #<?= $uid ?></div>
                  </div>
                </div>
              </td>
              <td>
                <?php if ($uType === 'admin'): ?>
                  <span class="role-pill role-admin"><i class="fas fa-shield-halved" style="font-size:.6rem;"></i> Admin</span>
                <?php elseif ($uType === 'engineer'): ?>
                  <span class="role-pill role-engineer"><i class="fas fa-helmet-safety" style="font-size:.6rem;"></i> Engineer</span>
                <?php else: ?>
                  <span class="role-pill role-employee"><i class="fas fa-user" style="font-size:.6rem;"></i> Employee</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($user['is_active'])): ?>
                  <span class="role-pill" style="background:var(--green-bg);color:var(--green);border:1px solid var(--green-b);">Active</span>
                <?php else: ?>
                  <span class="role-pill" style="background:var(--surface-2);color:var(--t3);border:1px solid var(--border);">Inactive</span>
                <?php endif; ?>
              </td>
              <td class="date-mono"><?= date('M d, Y · H:i', strtotime($user['created_at'])) ?></td>
              <td>
                <div class="row-actions">
                  <a href="users.php?action=edit&id=<?= $uid ?>" class="btn-act btn-edit" title="Edit user"><i class="fas fa-pen"></i></a>
                  <?php if ($uid !== $myId && empty($user['is_active'])): ?>
                  <form method="post" style="display:inline;margin:0;">
                    <input type="hidden" name="reactivate_user_id" value="<?= $uid ?>">
                    <button type="submit" class="btn-act" title="Reactivate login"
                            style="background:var(--green-bg);border-color:var(--green-b);color:var(--green);">
                      <i class="fas fa-rotate-left"></i>
                    </button>
                  </form>
                  <?php elseif ($uid !== $myId): ?>
                  <button class="btn-act btn-del" title="Deactivate user (turn login off)"
                          onclick="openDeleteModal(<?= $uid ?>, <?= htmlspecialchars(json_encode($dispName), ENT_QUOTES) ?>)">
                    <i class="fas fa-user-slash"></i>
                  </button>
                  <?php else: ?>
                  <span class="btn-act btn-lock" title="Cannot delete own account"><i class="fas fa-lock"></i></span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <div class="note-box">
      <i class="fas fa-circle-info" style="margin-right:6px;"></i>
      <strong>Security Notice:</strong> You cannot delete your own account. The super admin account (ID 1) is permanently protected.
    </div>
  </div>

<!-- Delete confirmation modal -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal-box">
    <div class="modal-hd">
      <div class="modal-hd-ico"><i class="fas fa-triangle-exclamation"></i></div>
      <div class="modal-hd-title">Deactivate User</div>
    </div>
    <div class="modal-body">
      <p>Turn off login for <strong id="deleteUsername"></strong>?</p>
      <p class="warn"><i class="fas fa-circle-info"></i> Their data is kept. You can reactivate them anytime.</p>
    </div>
    <div class="modal-ft">
      <button class="btn-cancel" onclick="closeDeleteModal()"><i class="fas fa-xmark" style="font-size:.7rem;"></i> Cancel</button>
      <form method="post" style="display:inline;">
        <input type="hidden" name="delete_user_id" id="deleteUserId">
        <button type="submit" class="btn-confirm-del"><i class="fas fa-user-slash" style="font-size:.7rem;"></i> Deactivate</button>
      </form>
    </div>
  </div>
</div>

<script>
function openDeleteModal(id, name) {
  document.getElementById('deleteUsername').textContent = name;
  document.getElementById('deleteUserId').value = id;
  document.getElementById('deleteModal').classList.add('open');
}
function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('open'); }
document.getElementById('deleteModal').addEventListener('click', function (e) { if (e.target === this) closeDeleteModal(); });
document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeDeleteModal(); });
</script>

<?php endif; ?>

</div>
</body>
</html>