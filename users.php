<?php
require 'auth.php';
require 'mydb.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $deleteUserId = intval($_POST['delete_user_id']);
    if ($deleteUserId > 1 && $deleteUserId !== $_SESSION['user_id']) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND id > 1");
        $stmt->bind_param("i", $deleteUserId);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            header("Location: users.php?success=deleted"); exit;
        } else {
            header("Location: users.php?error=delete_failed"); exit;
        }
        $stmt->close();
    } else {
        header("Location: users.php?error=permission_denied"); exit;
    }
}

include 'navbar.php';

$message = ''; $messageType = 'success';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added':   $message = "User created successfully."; break;
        case 'updated': $message = "User updated successfully."; break;
        case 'deleted': $message = "User deleted successfully."; break;
    }
}
if (isset($_GET['error'])) {
    $messageType = 'danger';
    switch ($_GET['error']) {
        case 'delete_failed':    $message = "Failed to delete user."; break;
        case 'permission_denied':$message = "Permission denied."; break;
    }
}

$search = trim($_GET['search'] ?? '');
$searchCondition = '';
$params = [];
if ($search !== '') {
    $searchCondition = "AND (username LIKE ? OR role LIKE ?)";
    $params = ["%$search%", "%$search%"];
}

$sql = "SELECT * FROM users WHERE id > 1 $searchCondition ORDER BY id ASC";
$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param("ss", ...$params);
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalResult = $conn->query("SELECT COUNT(*) as total FROM users WHERE id > 1");
$totalUsers = $totalResult->fetch_assoc()['total'];
$serialNumber = 1;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Users — Rajaiswari</title>
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
      --r:10px; --rs:6px;
      --sh:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'DM Sans',-apple-system,sans-serif;font-size:14.5px;background:var(--bg);color:var(--t1);-webkit-font-smoothing:antialiased;min-height:100vh;}

    .page-shell{margin-left:200px;min-height:100vh;display:flex;flex-direction:column;}

    .top-bar{position:sticky;top:0;z-index:200;height:54px;background:var(--surface);border-bottom:1px solid var(--border);box-shadow:var(--sh);display:flex;align-items:center;padding:0 22px;gap:10px;flex-shrink:0;}
    .tb-ico{width:32px;height:32px;background:var(--violet-bg);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--violet);font-size:13px;flex-shrink:0;}
    .tb-title{font-size:1.0625rem;font-weight:700;color:var(--t1);line-height:1.2;}
    .tb-sub{font-size:.8rem;color:var(--t4);}
    .tb-right{margin-left:auto;display:flex;align-items:center;gap:8px;}

    .tb-count{display:inline-flex;align-items:center;gap:5px;background:var(--surface-2);border:1px solid var(--border);border-radius:var(--rs);padding:4px 12px;font-family:'DM Mono',monospace;font-size:.82rem;font-weight:500;color:var(--t3);}

    .btn-add{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 16px;background:var(--green);border:none;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.875rem;font-weight:700;color:#fff;text-decoration:none;cursor:pointer;transition:background .15s;}
    .btn-add:hover{background:#047857;color:#fff;}

    .main{flex:1;padding:20px 22px 60px;display:flex;flex-direction:column;gap:14px;}

    /* Alerts */
    .alert-flash{border-radius:var(--rs);padding:12px 16px;font-size:.875rem;display:flex;align-items:center;gap:9px;}
    .af-success{background:var(--green-bg);border:1px solid var(--green-b);border-left:3px solid var(--green);color:#065f46;}
    .af-danger {background:var(--red-bg);  border:1px solid var(--red-b);  border-left:3px solid var(--red);  color:#7f1d1d;}

    /* Section card */
    .sec{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;}
    .sec-head{display:flex;align-items:center;gap:9px;padding:12px 18px;background:var(--surface-2);border-bottom:1px solid var(--bsoft);}
    .sec-ico{width:28px;height:28px;border-radius:var(--rs);display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;}
    .i-blue  {background:var(--blue-bg);  color:var(--blue);}
    .i-violet{background:var(--violet-bg);color:var(--violet);}
    .i-amber {background:var(--amber-bg); color:var(--amber);}
    .i-red   {background:var(--red-bg);   color:var(--red);}
    .sec-title{font-size:.9375rem;font-weight:700;color:var(--t1);letter-spacing:-.01em;}
    .sec-meta{margin-left:auto;font-size:.78rem;color:var(--t4);}
    .sec-body{padding:18px;}

    /* Search */
    .search-row{display:flex;gap:8px;align-items:center;}
    .fc{height:38px;padding:0 11px;border:1.5px solid var(--border);border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.9rem;color:var(--t2);background:var(--surface);transition:border-color .15s,box-shadow .15s;outline:none;width:100%;}
    .fc:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
    .btn-search{display:inline-flex;align-items:center;gap:6px;height:38px;padding:0 18px;background:var(--blue);border:none;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.875rem;font-weight:700;color:#fff;cursor:pointer;white-space:nowrap;transition:background .15s;}
    .btn-search:hover{background:#1d4ed8;}
    .btn-clear{display:inline-flex;align-items:center;gap:5px;height:38px;padding:0 14px;background:var(--surface-2);border:1.5px solid var(--border);border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.82rem;font-weight:600;color:var(--t2);text-decoration:none;white-space:nowrap;transition:all .15s;}
    .btn-clear:hover{background:var(--border);color:var(--t1);}

    /* Users table */
    .utbl{width:100%;border-collapse:collapse;}
    .utbl thead th{padding:10px 16px;text-align:left;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--t4);background:var(--surface-2);border-bottom:1px solid var(--border);white-space:nowrap;}
    .utbl tbody td{padding:12px 16px;border-bottom:1px solid var(--bsoft);vertical-align:middle;font-size:.875rem;}
    .utbl tbody tr:last-child td{border-bottom:none;}
    .utbl tbody tr:hover td{background:#fafbff;}

    .user-avatar{width:34px;height:34px;border-radius:50%;background:var(--violet-bg);border:2px solid var(--violet-b);display:flex;align-items:center;justify-content:center;font-size:13px;color:var(--violet);font-weight:700;flex-shrink:0;}
    .user-name{font-weight:700;color:var(--t1);}
    .you-badge{display:inline-flex;align-items:center;gap:3px;background:var(--blue-bg);border:1px solid var(--blue-b);color:var(--blue);padding:1px 7px;border-radius:20px;font-size:.68rem;font-weight:700;margin-left:6px;vertical-align:middle;}

    .role-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
    .role-admin   {background:var(--red-bg);   color:var(--red);   border:1px solid var(--red-b);}
    .role-employee{background:var(--blue-bg);  color:var(--blue);  border:1px solid var(--blue-b);}

    .date-mono{font-family:'DM Mono',monospace;font-size:.78rem;color:var(--t4);}

    .row-actions{display:flex;gap:6px;}
    .btn-act{width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:var(--rs);font-size:12px;cursor:pointer;transition:all .15s;text-decoration:none;border:1.5px solid;}
    .btn-edit{background:var(--amber-bg);border-color:var(--amber-b);color:var(--amber);}
    .btn-edit:hover{background:var(--amber);color:#fff;border-color:var(--amber);}
    .btn-del {background:var(--red-bg);  border-color:var(--red-b);  color:var(--red);}
    .btn-del:hover{background:var(--red);color:#fff;border-color:var(--red);}
    .btn-lock{background:var(--surface-2);border-color:var(--border);color:var(--t4);cursor:not-allowed;}

    /* Empty state */
    .empty-state{padding:52px 24px;text-align:center;}
    .empty-ico{font-size:2.8rem;color:var(--border);margin-bottom:12px;}
    .empty-title{font-size:.9375rem;font-weight:700;color:var(--t3);margin-bottom:6px;}
    .empty-sub{font-size:.82rem;color:var(--t4);}

    /* Security note */
    .note-box{background:var(--blue-bg);border:1px solid var(--blue-b);border-left:3px solid var(--blue);border-radius:var(--rs);padding:11px 14px;font-size:.82rem;color:#1e40af;line-height:1.5;}

    /* Modal */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9000;align-items:center;justify-content:center;}
    .modal-overlay.open{display:flex;}
    .modal-box{background:var(--surface);border-radius:var(--r);box-shadow:0 20px 60px rgba(0,0,0,.2);width:100%;max-width:420px;overflow:hidden;}
    .modal-hd{display:flex;align-items:center;gap:10px;padding:16px 20px;background:var(--red-bg);border-bottom:1px solid var(--red-b);}
    .modal-hd-ico{width:32px;height:32px;border-radius:var(--rs);background:var(--red-bg);border:1.5px solid var(--red-b);display:flex;align-items:center;justify-content:center;color:var(--red);font-size:13px;}
    .modal-hd-title{font-size:.9375rem;font-weight:700;color:var(--red);}
    .modal-body{padding:20px;}
    .modal-body p{font-size:.875rem;color:var(--t2);line-height:1.5;}
    .modal-body .warn{font-size:.78rem;color:var(--red);margin-top:8px;display:flex;align-items:center;gap:5px;}
    .modal-ft{display:flex;justify-content:flex-end;gap:8px;padding:14px 20px;background:var(--surface-2);border-top:1px solid var(--border);}
    .btn-cancel{display:inline-flex;align-items:center;gap:6px;height:36px;padding:0 16px;background:var(--surface);border:1.5px solid var(--border);border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.875rem;font-weight:500;color:var(--t2);cursor:pointer;transition:all .15s;}
    .btn-cancel:hover{background:var(--surface-2);}
    .btn-confirm-del{display:inline-flex;align-items:center;gap:6px;height:36px;padding:0 18px;background:var(--red);border:none;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.875rem;font-weight:700;color:#fff;cursor:pointer;transition:background .15s;}
    .btn-confirm-del:hover{background:#b91c1c;}

    @media(max-width:991.98px){.page-shell{margin-left:0;}.top-bar{top:52px;}.main{padding:14px 14px 50px;}}
  </style>
</head>
<body>

<div class="page-shell">

  <header class="top-bar">
    <div class="tb-ico"><i class="fas fa-users"></i></div>
    <div>
      <div class="tb-title">Manage Users</div>
      <div class="tb-sub">Admin access only</div>
    </div>
    <div class="tb-right">
      <div class="tb-count">
        <i class="fas fa-user" style="font-size:.6rem;"></i>
        <?= $totalUsers ?> user<?= $totalUsers !== 1 ? 's' : '' ?>
      </div>
      <a href="add_user.php" class="btn-add">
        <i class="fas fa-user-plus" style="font-size:.7rem;"></i> Add User
      </a>
    </div>
  </header>

  <div class="main">

    <?php if ($message): ?>
    <div class="alert-flash <?= $messageType === 'success' ? 'af-success' : 'af-danger' ?>">
      <i class="fas fa-<?= $messageType === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
      <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <!-- Search -->
    <div class="sec">
      <div class="sec-head">
        <span class="sec-ico i-blue"><i class="fas fa-magnifying-glass"></i></span>
        <span class="sec-title">Search Users</span>
      </div>
      <div class="sec-body">
        <form method="get">
          <div class="search-row">
            <input type="text" name="search" class="fc"
                   placeholder="Search by username or role…"
                   value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn-search">
              <i class="fas fa-magnifying-glass" style="font-size:.72rem;"></i> Search
            </button>
            <?php if ($search !== ''): ?>
            <a href="users.php" class="btn-clear">
              <i class="fas fa-xmark" style="font-size:.72rem;"></i> Clear
            </a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>

    <!-- Users list -->
    <div class="sec">
      <div class="sec-head">
        <span class="sec-ico i-violet"><i class="fas fa-user-group"></i></span>
        <span class="sec-title">User Accounts</span>
        <?php if ($search !== ''): ?>
        <span class="sec-meta">Results for "<?= htmlspecialchars($search) ?>"</span>
        <?php else: ?>
        <span class="sec-meta"><?= count($users) ?> record<?= count($users) !== 1 ? 's' : '' ?></span>
        <?php endif; ?>
      </div>

      <?php if (empty($users)): ?>
      <div class="empty-state">
        <div class="empty-ico"><i class="fas fa-users"></i></div>
        <div class="empty-title">
          <?= $search !== '' ? 'No users match "' . htmlspecialchars($search) . '"' : 'No users yet' ?>
        </div>
        <div class="empty-sub">
          <?= $search !== '' ? 'Try a different search term.' : '<a href="add_user.php">Add the first user</a>' ?>
        </div>
      </div>

      <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="utbl">
          <thead>
            <tr>
              <th style="width:44px;">#</th>
              <th>User</th>
              <th style="width:120px;">Role</th>
              <th style="width:160px;">Created</th>
              <th style="width:90px;text-align:center;">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($users as $user): ?>
            <tr>
              <td style="font-family:'DM Mono',monospace;color:var(--t4);font-size:.78rem;"><?= $serialNumber++ ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:10px;">
                  <div class="user-avatar">
                    <?= strtoupper(substr($user['username'], 0, 1)) ?>
                  </div>
                  <div>
                    <div class="user-name">
                      <?= htmlspecialchars($user['username']) ?>
                      <?php if ($user['id'] === $_SESSION['user_id']): ?>
                        <span class="you-badge"><i class="fas fa-circle" style="font-size:.4rem;"></i> You</span>
                      <?php endif; ?>
                    </div>
                    <div style="font-size:.75rem;color:var(--t4);font-family:'DM Mono',monospace;">
                      ID #<?= $user['id'] ?>
                    </div>
                  </div>
                </div>
              </td>
              <td>
                <?php if ($user['role'] === 'admin'): ?>
                  <span class="role-pill role-admin"><i class="fas fa-shield-halved" style="font-size:.6rem;"></i> Admin</span>
                <?php else: ?>
                  <span class="role-pill role-employee"><i class="fas fa-user" style="font-size:.6rem;"></i> Employee</span>
                <?php endif; ?>
              </td>
              <td class="date-mono"><?= date('M d, Y · H:i', strtotime($user['created_at'])) ?></td>
              <td>
                <div class="row-actions" style="justify-content:center;">
                  <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn-act btn-edit" title="Edit user">
                    <i class="fas fa-pen"></i>
                  </a>
                  <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                  <button class="btn-act btn-del" title="Delete user"
                          onclick="openDeleteModal(<?= $user['id'] ?>, '<?= htmlspecialchars(addslashes($user['username'])) ?>')">
                    <i class="fas fa-trash-can"></i>
                  </button>
                  <?php else: ?>
                  <span class="btn-act btn-lock" title="Cannot delete own account">
                    <i class="fas fa-lock"></i>
                  </span>
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

    <!-- Security note -->
    <div class="note-box">
      <i class="fas fa-circle-info" style="margin-right:6px;"></i>
      <strong>Security Notice:</strong> You cannot delete your own account. The super admin account (ID 1) is permanently protected.
    </div>

  </div>
</div>

<!-- Delete confirmation modal -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal-box">
    <div class="modal-hd">
      <div class="modal-hd-ico"><i class="fas fa-triangle-exclamation"></i></div>
      <div class="modal-hd-title">Confirm Deletion</div>
    </div>
    <div class="modal-body">
      <p>Are you sure you want to delete user <strong id="deleteUsername"></strong>?</p>
      <p class="warn"><i class="fas fa-circle-exclamation"></i> This action cannot be undone.</p>
    </div>
    <div class="modal-ft">
      <button class="btn-cancel" onclick="closeDeleteModal()">
        <i class="fas fa-xmark" style="font-size:.7rem;"></i> Cancel
      </button>
      <form method="post" id="deleteForm" style="display:inline;">
        <input type="hidden" name="delete_user_id" id="deleteUserId">
        <button type="submit" class="btn-confirm-del">
          <i class="fas fa-trash-can" style="font-size:.7rem;"></i> Delete User
        </button>
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
function closeDeleteModal() {
  document.getElementById('deleteModal').classList.remove('open');
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
  if (e.target === this) closeDeleteModal();
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeDeleteModal();
});
</script>
</body>
</html>