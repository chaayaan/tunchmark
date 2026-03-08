<?php
require 'auth.php';
require 'mydb.php';

// Only admins can access
if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$userId = intval($_GET['id'] ?? 0);

// Prevent editing super admin (ID = 1)
if ($userId <= 1) {
    header("Location: users.php?error=permission_denied");
    exit;
}

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND id > 1 LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: users.php?error=permission_denied");
    exit;
}

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $role = $_POST['role'] ?? '';
    $changePassword = isset($_POST['change_password']);
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($username)) {
        $errors[] = "Username is required.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Username can only contain letters, numbers, and underscores.";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters long.";
    } elseif (strlen($username) > 50) {
        $errors[] = "Username must be less than 50 characters.";
    }

    if (!in_array($role, ['admin', 'employee'])) {
        $errors[] = "Invalid role selected.";
    }

    // Check if username already exists (excluding current user)
    if (empty($errors)) {
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $checkStmt->bind_param("si", $username, $userId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "Username already exists. Please choose a different username.";
        }
        $checkStmt->close();
    }

    // Password validation if changing password
    if ($changePassword) {
        if (empty($newPassword)) {
            $errors[] = "New password is required when changing password.";
        } elseif (strlen($newPassword) < 6) {
            $errors[] = "New password must be at least 6 characters long.";
        } elseif (strlen($newPassword) > 255) {
            $errors[] = "New password is too long.";
        }

        if ($newPassword !== $confirmPassword) {
            $errors[] = "New passwords do not match.";
        }
    }

    // Update user if no errors
    if (empty($errors)) {
        if ($changePassword) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ? AND id > 1");
            $stmt->bind_param("sssi", $username, $hashedPassword, $role, $userId);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, role = ? WHERE id = ? AND id > 1");
            $stmt->bind_param("ssi", $username, $role, $userId);
        }
        
        if ($stmt->execute()) {
            $stmt->close();
            header("Location: users.php?success=updated");
            exit;
        } else {
            $errors[] = "Error updating user: " . $conn->error;
        }
        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit User - <?= htmlspecialchars($user['username']) ?></title>
  <link rel="icon" type="image/png" href="favicon.png">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    .password-strength {
      height: 5px;
      border-radius: 3px;
      transition: all 0.3s ease;
    }
    .strength-weak { background-color: #dc3545; }
    .strength-medium { background-color: #ffc107; }
    .strength-strong { background-color: #28a745; }
  </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-4">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-user-edit"></i> Edit User</h2>
        <a href="users.php" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> Back to Users
        </a>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <i class="fas fa-exclamation-triangle"></i> <strong>Please fix the following errors:</strong>
          <ul class="mb-0 mt-2">
            <?php foreach ($errors as $error): ?>
              <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <div class="card shadow">
        <div class="card-header bg-warning text-dark">
          <h5 class="mb-0">
            <i class="fas fa-user-edit"></i> Edit User: <?= htmlspecialchars($user['username']) ?>
          </h5>
        </div>
        <div class="card-body">
          <form method="POST" id="editUserForm">
            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label">Username <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-user"></i></span>
                  <input type="text" name="username" class="form-control" required 
                         pattern="[a-zA-Z0-9_]+" minlength="3" maxlength="50"
                         value="<?= htmlspecialchars($_POST['username'] ?? $user['username']) ?>"
                         placeholder="Enter username">
                </div>
                <small class="form-text text-muted">Only letters, numbers, and underscores allowed (3-50 characters)</small>
              </div>
              <div class="col-md-6">
                <label class="form-label">Role <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                  <select name="role" class="form-select" required>
                    <option value="employee" <?= (($_POST['role'] ?? $user['role']) === 'employee') ? 'selected' : '' ?>>Employee</option>
                    <option value="admin" <?= (($_POST['role'] ?? $user['role']) === 'admin') ? 'selected' : '' ?>>Admin</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Password Change Section -->
            <div class="card mb-3">
              <div class="card-header bg-light">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="change_password" id="changePassword"
                         <?= isset($_POST['change_password']) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="changePassword">
                    <i class="fas fa-key"></i> Change Password
                  </label>
                </div>
              </div>
              <div class="card-body" id="passwordSection" style="display: none;">
                <div class="row">
                  <div class="col-md-6">
                    <label class="form-label">New Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                      <span class="input-group-text"><i class="fas fa-lock"></i></span>
                      <input type="password" name="new_password" class="form-control" 
                             minlength="6" maxlength="255" id="newPassword"
                             placeholder="Enter new password">
                      <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                        <i class="fas fa-eye"></i>
                      </button>
                    </div>
                    <div class="password-strength mt-1" id="passwordStrength"></div>
                    <small class="form-text text-muted">Minimum 6 characters</small>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                      <span class="input-group-text"><i class="fas fa-lock"></i></span>
                      <input type="password" name="confirm_password" class="form-control" 
                             minlength="6" maxlength="255" id="confirmNewPassword"
                             placeholder="Confirm new password">
                      <button class="btn btn-outline-secondary" type="button" id="toggleConfirmNewPassword">
                        <i class="fas fa-eye"></i>
                      </button>
                    </div>
                    <small class="form-text" id="passwordMatch"></small>
                  </div>
                </div>
                <div class="alert alert-warning mt-3">
                  <i class="fas fa-exclamation-triangle"></i>
                  <strong>Note:</strong> Changing the password will require the user to log in with the new password.
                </div>
              </div>
            </div>

            <!-- User Info -->
            <div class="alert alert-info">
              <i class="fas fa-info-circle"></i>
              <strong>User Information:</strong>
              <ul class="mb-0 mt-2">
                <li><strong>User ID:</strong> <?= $user['id'] ?></li>
                <li><strong>Created:</strong> <?= date('M d, Y H:i', strtotime($user['created_at'])) ?></li>
                <li><strong>Current Role:</strong> <?= ucfirst($user['role']) ?></li>
                <?php if ($user['id'] === $_SESSION['user_id']): ?>
                  <li class="text-warning"><strong>This is your account</strong></li>
                <?php endif; ?>
              </ul>
            </div>

            <div class="d-flex justify-content-between">
              <a href="users.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
              </a>
              <button type="submit" class="btn btn-warning" id="submitBtn">
                <i class="fas fa-save"></i> Update User
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle password section
document.getElementById('changePassword').addEventListener('change', function() {
    const passwordSection = document.getElementById('passwordSection');
    const newPassword = document.getElementById('newPassword');
    const confirmNewPassword = document.getElementById('confirmNewPassword');
    
    if (this.checked) {
        passwordSection.style.display = 'block';
        newPassword.required = true;
        confirmNewPassword.required = true;
    } else {
        passwordSection.style.display = 'none';
        newPassword.required = false;
        confirmNewPassword.required = false;
        newPassword.value = '';
        confirmNewPassword.value = '';
        document.getElementById('passwordMatch').textContent = '';
        document.getElementById('passwordStrength').style.width = '0%';
    }
});

// Show password section if it was checked on form submission
if (document.getElementById('changePassword').checked) {
    document.getElementById('passwordSection').style.display = 'block';
    document.getElementById('newPassword').required = true;
    document.getElementById('confirmNewPassword').required = true;
}

// Password visibility toggles
document.getElementById('toggleNewPassword').addEventListener('click', function() {
    const password = document.getElementById('newPassword');
    const icon = this.querySelector('i');
    
    if (password.type === 'password') {
        password.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        password.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
});

document.getElementById('toggleConfirmNewPassword').addEventListener('click', function() {
    const confirmPassword = document.getElementById('confirmNewPassword');
    const icon = this.querySelector('i');
    
    if (confirmPassword.type === 'password') {
        confirmPassword.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        confirmPassword.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
});

// Password strength indicator
document.getElementById('newPassword').addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('passwordStrength');
    
    if (password === '') {
        strengthBar.style.width = '0%';
        return;
    }
    
    let strength = 0;
    if (password.length >= 6) strength++;
    if (password.match(/[a-z]/)) strength++;
    if (password.match(/[A-Z]/)) strength++;
    if (password.match(/[0-9]/)) strength++;
    if (password.match(/[^a-zA-Z0-9]/)) strength++;
    
    strengthBar.style.width = (strength * 20) + '%';
    
    if (strength < 2) {
        strengthBar.className = 'password-strength strength-weak';
    } else if (strength < 4) {
        strengthBar.className = 'password-strength strength-medium';
    } else {
        strengthBar.className = 'password-strength strength-strong';
    }
});

// Password confirmation check
document.getElementById('confirmNewPassword').addEventListener('input', function() {
    const newPassword = document.getElementById('newPassword').value;
    const confirmNewPassword = this.value;
    const matchIndicator = document.getElementById('passwordMatch');
    const submitBtn = document.getElementById('submitBtn');
    
    if (confirmNewPassword === '') {
        matchIndicator.textContent = '';
        matchIndicator.className = 'form-text';
        submitBtn.disabled = false;
    } else if (newPassword === confirmNewPassword) {
        matchIndicator.textContent = 'Passwords match';
        matchIndicator.className = 'form-text text-success';
        submitBtn.disabled = false;
    } else {
        matchIndicator.textContent = 'Passwords do not match';
        matchIndicator.className = 'form-text text-danger';
        submitBtn.disabled = true;
    }
});

// Form validation
document.getElementById('editUserForm').addEventListener('submit', function(e) {
    const changePassword = document.getElementById('changePassword').checked;
    
    if (changePassword) {
        const newPassword = document.getElementById('newPassword').value;
        const confirmNewPassword = document.getElementById('confirmNewPassword').value;
        
        if (newPassword !== confirmNewPassword) {
            e.preventDefault();
            alert('Passwords do not match!');
            return false;
        }
        
        if (newPassword.length < 6) {
            e.preventDefault();
            alert('New password must be at least 6 characters long!');
            return false;
        }
    }
});
</script>
</body>
</html>