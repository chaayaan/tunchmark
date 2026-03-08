<?php
require 'auth.php';
require 'mydb.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php"); exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username        = trim($_POST['username'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role            = $_POST['role'] ?? '';

    if (empty($username))                              $errors[] = "Username is required.";
    elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) $errors[] = "Username can only contain letters, numbers, and underscores.";
    elseif (strlen($username) < 3)                     $errors[] = "Username must be at least 3 characters long.";
    elseif (strlen($username) > 50)                    $errors[] = "Username must be less than 50 characters.";

    if (empty($password))              $errors[] = "Password is required.";
    elseif (strlen($password) < 6)    $errors[] = "Password must be at least 6 characters long.";
    elseif (strlen($password) > 255)  $errors[] = "Password is too long.";

    if ($password !== $confirmPassword) $errors[] = "Passwords do not match.";

    if (!in_array($role, ['admin', 'employee'])) $errors[] = "Invalid role selected.";

    if (empty($errors)) {
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->bind_param("s", $username);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) $errors[] = "Username already exists.";
        $checkStmt->close();
    }

    if (empty($errors)) {
        $hp   = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $hp, $role);
        if ($stmt->execute()) { $stmt->close(); header("Location: users.php?success=added"); exit; }
        else $errors[] = "Error creating user: " . $conn->error;
        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Add New User — Rajaiswari</title>
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
    .tb-ico{width:32px;height:32px;background:var(--green-bg);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--green);font-size:13px;flex-shrink:0;}
    .tb-title{font-size:1.0625rem;font-weight:700;color:var(--t1);line-height:1.2;}
    .tb-sub{font-size:.8rem;color:var(--t4);}
    .tb-right{margin-left:auto;}
    .tb-back{display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 14px;background:var(--surface-2);border:1.5px solid var(--border);border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.82rem;font-weight:600;color:var(--t2);text-decoration:none;transition:all .15s;}
    .tb-back:hover{background:var(--border);color:var(--t1);}
    .main{flex:1;padding:20px 22px 60px;display:flex;flex-direction:column;gap:14px;align-items:center;}
    .form-wrap{width:100%;max-width:680px;display:flex;flex-direction:column;gap:14px;}

    /* Alert */
    .alert-err{background:var(--red-bg);border:1px solid var(--red-b);border-left:3px solid var(--red);border-radius:var(--rs);padding:13px 16px;}
    .alert-err-title{font-size:.875rem;font-weight:700;color:var(--red);margin-bottom:6px;}
    .alert-err ul{margin:0;padding-left:16px;}
    .alert-err li{font-size:.82rem;color:var(--red);}

    /* Section card */
    .sec{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;}
    .sec-head{display:flex;align-items:center;gap:9px;padding:12px 18px;background:var(--surface-2);border-bottom:1px solid var(--bsoft);}
    .sec-ico{width:28px;height:28px;border-radius:var(--rs);display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;}
    .i-green {background:var(--green-bg); color:var(--green);}
    .i-blue  {background:var(--blue-bg);  color:var(--blue);}
    .i-violet{background:var(--violet-bg);color:var(--violet);}
    .i-amber {background:var(--amber-bg); color:var(--amber);}
    .i-red   {background:var(--red-bg);   color:var(--red);}
    .sec-title{font-size:.9375rem;font-weight:700;color:var(--t1);letter-spacing:-.01em;}
    .sec-body{padding:18px;}

    /* Form */
    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
    .lbl{display:block;font-size:.76rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t3);margin-bottom:5px;}
    .lbl .req{color:var(--red);margin-left:2px;}
    .fhint{font-size:.74rem;color:var(--t4);margin-top:3px;}

    .fc-wrap{position:relative;}
    .fc{width:100%;height:38px;padding:0 11px;border:1.5px solid var(--border);border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.9rem;color:var(--t2);background:var(--surface);outline:none;transition:border-color .15s,box-shadow .15s;appearance:none;}
    .fc:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
    .fc-with-btn{padding-right:42px;}
    .fc-eye{position:absolute;right:0;top:0;width:38px;height:38px;display:flex;align-items:center;justify-content:center;background:none;border:none;color:var(--t4);cursor:pointer;transition:color .15s;font-size:13px;}
    .fc-eye:hover{color:var(--t2);}

    select.fc{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%239ca3af' d='M1 1l5 5 5-5'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;padding-right:28px;}

    /* Strength bar */
    .strength-bar-wrap{height:4px;border-radius:2px;background:var(--bsoft);margin-top:6px;overflow:hidden;}
    .strength-bar{height:100%;width:0;border-radius:2px;transition:width .3s,background .3s;}

    /* Password match */
    .match-ok  {font-size:.74rem;color:var(--green);margin-top:3px;}
    .match-fail{font-size:.74rem;color:var(--red);  margin-top:3px;}

    /* Permissions info box */
    .perm-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
    .perm-card{background:var(--surface-2);border:1px solid var(--border);border-radius:var(--rs);padding:12px 14px;}
    .perm-title{font-size:.78rem;font-weight:700;margin-bottom:6px;display:flex;align-items:center;gap:6px;}
    .perm-list{list-style:none;padding:0;margin:0;}
    .perm-list li{font-size:.75rem;color:var(--t3);padding:2px 0;display:flex;align-items:center;gap:5px;}
    .perm-list li::before{content:'';width:4px;height:4px;border-radius:50%;background:var(--t4);flex-shrink:0;}

    /* Action bar */
    .action-bar{display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:13px 18px;background:var(--surface-2);border-top:1px solid var(--border);}
    .btn-ghost{display:inline-flex;align-items:center;gap:6px;height:38px;padding:0 18px;background:var(--surface);border:1.5px solid var(--border);border-radius:7px;font-family:'DM Sans',sans-serif;font-size:.9rem;font-weight:500;color:var(--t2);text-decoration:none;cursor:pointer;transition:all .15s;}
    .btn-ghost:hover{background:var(--surface-2);border-color:#9ca3af;color:var(--t1);}
    .btn-submit{display:inline-flex;align-items:center;gap:7px;height:38px;padding:0 24px;background:var(--green);border:none;border-radius:7px;font-family:'DM Sans',sans-serif;font-size:.9375rem;font-weight:700;color:#fff;cursor:pointer;box-shadow:0 1px 4px rgba(5,150,105,.25);transition:background .15s;}
    .btn-submit:hover{background:#047857;}
    .btn-submit:disabled{background:var(--t4);cursor:not-allowed;box-shadow:none;}

    @media(max-width:991.98px){.page-shell{margin-left:0;}.top-bar{top:52px;}.main{padding:14px 14px 50px;}.grid-2{grid-template-columns:1fr;}.perm-grid{grid-template-columns:1fr;}}
  </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="page-shell">
  <header class="top-bar">
    <div class="tb-ico"><i class="fas fa-user-plus"></i></div>
    <div>
      <div class="tb-title">Add New User</div>
      <div class="tb-sub">Create a staff account</div>
    </div>
    <div class="tb-right">
      <a href="users.php" class="tb-back">
        <i class="fas fa-arrow-left" style="font-size:.65rem;"></i> Back
      </a>
    </div>
  </header>

  <div class="main">
    <div class="form-wrap">

      <?php if (!empty($errors)): ?>
      <div class="alert-err">
        <div class="alert-err-title"><i class="fas fa-circle-exclamation" style="margin-right:6px;"></i>Please fix the following errors:</div>
        <ul>
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <form method="POST" id="addUserForm">

        <!-- Account details -->
        <div class="sec">
          <div class="sec-head">
            <span class="sec-ico i-green"><i class="fas fa-user-plus"></i></span>
            <span class="sec-title">Account Details</span>
          </div>
          <div class="sec-body">
            <div class="grid-2">
              <div>
                <label class="lbl">Username <span class="req">*</span></label>
                <input type="text" name="username" class="fc" required
                       pattern="[a-zA-Z0-9_]+" minlength="3" maxlength="50"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       placeholder="Enter username">
                <div class="fhint">Letters, numbers, underscores · 3–50 chars</div>
              </div>
              <div>
                <label class="lbl">Role <span class="req">*</span></label>
                <select name="role" class="fc" required>
                  <option value="">— Select role —</option>
                  <option value="employee" <?= ($_POST['role'] ?? '') === 'employee' ? 'selected' : '' ?>>Employee</option>
                  <option value="admin"    <?= ($_POST['role'] ?? '') === 'admin'    ? 'selected' : '' ?>>Admin</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Password -->
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
                  <input type="password" name="password" id="password"
                         class="fc fc-with-btn" required minlength="6" maxlength="255"
                         placeholder="Enter password">
                  <button type="button" class="fc-eye" onclick="toggleEye('password', this)">
                    <i class="fas fa-eye"></i>
                  </button>
                </div>
                <div class="strength-bar-wrap"><div class="strength-bar" id="strengthBar"></div></div>
                <div class="fhint">Minimum 6 characters</div>
              </div>
              <div>
                <label class="lbl">Confirm Password <span class="req">*</span></label>
                <div class="fc-wrap">
                  <input type="password" name="confirm_password" id="confirmPassword"
                         class="fc fc-with-btn" required minlength="6" maxlength="255"
                         placeholder="Confirm password">
                  <button type="button" class="fc-eye" onclick="toggleEye('confirmPassword', this)">
                    <i class="fas fa-eye"></i>
                  </button>
                </div>
                <div id="matchMsg"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Role permissions info -->
        <div class="sec">
          <div class="sec-head">
            <span class="sec-ico i-blue"><i class="fas fa-shield-halved"></i></span>
            <span class="sec-title">Role Permissions</span>
          </div>
          <div class="sec-body">
            <div class="perm-grid">
              <div class="perm-card">
                <div class="perm-title" style="color:var(--blue);">
                  <i class="fas fa-user" style="font-size:.7rem;"></i> Employee
                </div>
                <ul class="perm-list">
                  <li>Create & manage orders</li>
                  <li>View customer list</li>
                  <li>Access billing reports</li>
                  <li>View daily expenses</li>
                </ul>
              </div>
              <div class="perm-card">
                <div class="perm-title" style="color:var(--red);">
                  <i class="fas fa-shield-halved" style="font-size:.7rem;"></i> Admin
                </div>
                <ul class="perm-list">
                  <li>All employee permissions</li>
                  <li>User management</li>
                  <li>Edit & delete bills</li>
                  <li>Manage items & services</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="sec">
          <div class="action-bar">
            <a href="users.php" class="btn-ghost">
              <i class="fas fa-xmark" style="font-size:.7rem;"></i> Cancel
            </a>
            <button type="submit" class="btn-submit" id="submitBtn">
              <i class="fas fa-user-plus" style="font-size:.75rem;"></i> Create User
            </button>
          </div>
        </div>

      </form>
    </div>
  </div>
</div>

<script>
function toggleEye(fieldId, btn) {
  const f = document.getElementById(fieldId);
  const i = btn.querySelector('i');
  if (f.type === 'password') {
    f.type = 'text';
    i.classList.replace('fa-eye', 'fa-eye-slash');
  } else {
    f.type = 'password';
    i.classList.replace('fa-eye-slash', 'fa-eye');
  }
}

document.getElementById('password').addEventListener('input', function () {
  const pw  = this.value;
  const bar = document.getElementById('strengthBar');
  let s = 0;
  if (pw.length >= 6)            s++;
  if (pw.match(/[a-z]/))         s++;
  if (pw.match(/[A-Z]/))         s++;
  if (pw.match(/[0-9]/))         s++;
  if (pw.match(/[^a-zA-Z0-9]/)) s++;
  bar.style.width      = (s * 20) + '%';
  bar.style.background = s < 2 ? 'var(--red)' : s < 4 ? 'var(--amber)' : 'var(--green)';
});

document.getElementById('confirmPassword').addEventListener('input', function () {
  const match = document.getElementById('matchMsg');
  const btn   = document.getElementById('submitBtn');
  if (!this.value) { match.innerHTML = ''; btn.disabled = false; return; }
  if (this.value === document.getElementById('password').value) {
    match.innerHTML = '<div class="match-ok"><i class="fas fa-circle-check" style="font-size:.65rem;"></i> Passwords match</div>';
    btn.disabled = false;
  } else {
    match.innerHTML = '<div class="match-fail"><i class="fas fa-circle-xmark" style="font-size:.65rem;"></i> Passwords do not match</div>';
    btn.disabled = true;
  }
});

document.getElementById('addUserForm').addEventListener('submit', function (e) {
  const pw  = document.getElementById('password').value;
  const cpw = document.getElementById('confirmPassword').value;
  if (pw !== cpw || pw.length < 6) e.preventDefault();
});
</script>
</body>
</html>