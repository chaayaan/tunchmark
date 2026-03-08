<?php
require 'auth.php';
require 'mydb.php';

// Only admins can access
if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';

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

    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    } elseif (strlen($password) > 255) {
        $errors[] = "Password is too long.";
    }

    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }

    if (!in_array($role, ['admin', 'employee'])) {
        $errors[] = "Invalid role selected.";
    }

    // Check if username already exists
    if (empty($errors)) {
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->bind_param("s", $username);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "Username already exists. Please choose a different username.";
        }
        $checkStmt->close();
    }

    // Create user if no errors
    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $hashedPassword, $role);
        
        if ($stmt->execute()) {
            $stmt->close();
            header("Location: users.php?success=added");
            exit;
        } else {
            $errors[] = "Error creating user: " . $conn->error;
        }
        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add New User</title>
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
        <h2><i class="fas fa-user-plus"></i> Add New User</h2>
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
        <div class="card-header bg-success text-white">
          <h5 class="mb-0"><i class="fas fa-user-plus"></i> User Information</h5>
        </div>
        <div class="card-body">
          <form method="POST" id="addUserForm">
            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label">Username <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-user"></i></span>
                  <input type="text" name="username" class="form-control" required 
                         pattern="[a-zA-Z0-9_]+" minlength="3" maxlength="50"
                         value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                         placeholder="Enter username">
                </div>
                <small class="form-text text-muted">Only letters, numbers, and underscores allowed (3-50 characters)</small>
              </div>
              <div class="col-md-6">
                <label class="form-label">Role <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                  <select name="role" class="form-select" required>
                    <option value="">Select Role</option>
                    <option value="employee" <?= ($_POST['role'] ?? '') === 'employee' ? 'selected' : '' ?>>Employee</option>
                    <option value="admin" <?= ($_POST['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                  </select>
                </div>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label">Password <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-lock"></i></span>
                  <input type="password" name="password" class="form-control" required 
                         minlength="6" maxlength="255" id="password"
                         placeholder="Enter password">
                  <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                    <i class="fas fa-eye"></i>
                  </button>
                </div>
                <div class="password-strength mt-1" id="passwordStrength"></div>
                <small class="form-text text-muted">Minimum 6 characters</small>
              </div>
              <div class="col-md-6">
                <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-lock"></i></span>
                  <input type="password" name="confirm_password" class="form-control" required 
                         minlength="6" maxlength="255" id="confirmPassword"
                         placeholder="Confirm password">
                  <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                    <i class="fas fa-eye"></i>
                  </button>
                </div>
                <small class="form-text" id="passwordMatch"></small>
              </div>
            </div>

            <div class="alert alert-info">
              <i class="fas fa-info-circle"></i>
              <strong>Role Permissions:</strong>
              <ul class="mb-0 mt-2">
                <li><strong>Employee:</strong> Can create orders, view customers, and access reports</li>
                <li><strong>Admin:</strong> All employee permissions plus user management and bill editing</li>
              </ul>
            </div>

            <div class="d-flex justify-content-between">
              <a href="users.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
              </a>
              <button type="submit" class="btn btn-success" id="submitBtn">
                <i class="fas fa-user-plus"></i> Create User
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
// Password visibility toggle
document.getElementById('togglePassword').addEventListener('click', function() {
    const password = document.getElementById('password');
    const icon = this.querySelector('i');
    
    if (password.type === 'password') {
        password.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        password.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
});

document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
    const confirmPassword = document.getElementById('confirmPassword');
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
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('passwordStrength');
    
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
document.getElementById('confirmPassword').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    const matchIndicator = document.getElementById('passwordMatch');
    const submitBtn = document.getElementById('submitBtn');
    
    if (confirmPassword === '') {
        matchIndicator.textContent = '';
        matchIndicator.className = 'form-text';
    } else if (password === confirmPassword) {
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
document.getElementById('addUserForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
    }
    
    if (password.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long!');
        return false;
    }
});
</script>
</body>
</html>