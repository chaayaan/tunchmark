<?php
require 'auth.php';
require 'mydb.php';

// Only admins can access
if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $deleteUserId = intval($_POST['delete_user_id']);
    
    // Security checks: prevent deleting super admin (ID = 1) and self-deletion
    if ($deleteUserId > 1 && $deleteUserId !== $_SESSION['user_id']) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND id > 1");
        $stmt->bind_param("i", $deleteUserId);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            header("Location: users.php?success=deleted");
            exit;
        } else {
            header("Location: users.php?error=delete_failed");
            exit;
        }
        $stmt->close();
    } else {
        header("Location: users.php?error=permission_denied");
        exit;
    }
}
include 'navbar.php';

// Handle success/error messages from other actions
$message = '';
$messageType = 'info';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added':
            $message = "User created successfully.";
            $messageType = 'success';
            break;
        case 'updated':
            $message = "User updated successfully.";
            $messageType = 'success';
            break;
        case 'deleted':
            $message = "User deleted successfully.";
            $messageType = 'success';
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'delete_failed':
            $message = "Failed to delete user.";
            $messageType = 'danger';
            break;
        case 'permission_denied':
            $message = "Permission denied.";
            $messageType = 'danger';
            break;
    }
}

// Search functionality
$search = trim($_GET['search'] ?? '');
$searchCondition = '';
$params = [];

if ($search !== '') {
    $searchCondition = "AND (username LIKE ? OR role LIKE ?)";
    $params = ["%$search%", "%$search%"];
}

// Fetch all users except super admin (ID = 1)
$sql = "SELECT * FROM users WHERE id > 1 $searchCondition ORDER BY id ASC";
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param("ss", ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get total user count (excluding super admin)
$totalResult = $conn->query("SELECT COUNT(*) as total FROM users WHERE id > 1");
$totalUsers = $totalResult->fetch_assoc()['total'];

// Add serial number for display
$serialNumber = 1;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Users</title>
  <link rel="icon" type="image/png" href="favicon.png">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>


<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-users"></i> Manage Users</h2>
    <div class="d-flex align-items-center gap-3">
      <div class="text-muted">Total Users: <?= $totalUsers ?></div>
      <a href="add_user.php" class="btn btn-success">
        <i class="fas fa-user-plus"></i> Add New User
      </a>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
      <?= htmlspecialchars($message) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- Search Form -->
  <div class="card mb-4">
    <div class="card-body">
      <form method="get" class="row g-3">
        <div class="col-md-10">
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" name="search" class="form-control" placeholder="Search by username or role..." 
                   value="<?= htmlspecialchars($search) ?>">
          </div>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary w-100">Search</button>
        </div>
      </form>
      <?php if ($search !== ''): ?>
        <div class="mt-2">
          <a href="users.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-times"></i> Clear Search
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Users List -->
  <div class="card">
    <div class="card-header bg-secondary text-white">
      <i class="fas fa-list"></i> User Accounts
      <?php if ($search !== ''): ?>
        <span class="badge bg-light text-dark ms-2">Search Results</span>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <?php if (empty($users)): ?>
        <div class="text-center py-4">
          <i class="fas fa-users fa-3x text-muted mb-3"></i>
          <?php if ($search !== ''): ?>
            <p class="text-muted">No users found matching "<?= htmlspecialchars($search) ?>"</p>
          <?php else: ?>
            <p class="text-muted">No users found. <a href="add_user.php">Add the first user</a></p>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-bordered align-middle">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Username</th>
                <th>Role</th>
                <th>Created At</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
              <tr>
                <td><?= $serialNumber++ ?></td>
                <td>
                  <i class="fas fa-user text-muted me-2"></i>
                  <?= htmlspecialchars($user['username']) ?>
                  <?php if ($user['id'] === $_SESSION['user_id']): ?>
                    <span class="badge bg-info ms-2">You</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($user['role'] === 'admin'): ?>
                    <span class="badge bg-danger">Admin</span>
                  <?php else: ?>
                    <span class="badge bg-primary">Employee</span>
                  <?php endif; ?>
                </td>
                <td><?= date('M d, Y H:i', strtotime($user['created_at'])) ?></td>
                <td>
                  <div class="btn-group" role="group">
                    <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-warning">
                      <i class="fas fa-edit"></i> Edit
                    </a>
                    <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                      <button type="button" class="btn btn-sm btn-danger" 
                              onclick="confirmDelete(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                        <i class="fas fa-trash"></i> Delete
                      </button>
                    <?php else: ?>
                      <span class="btn btn-sm btn-secondary disabled">
                        <i class="fas fa-lock"></i> Protected
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
  </div>

  <!-- Security Notice -->
  <div class="alert alert-info mt-4">
    <i class="fas fa-info-circle"></i>
    <strong>Security Notice:</strong> Administrative accounts are protected for security reasons. You cannot delete your own account.
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">
          <i class="fas fa-exclamation-triangle"></i> Confirm Deletion
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to delete user <strong id="deleteUsername"></strong>?</p>
        <p class="text-danger mb-0">
          <i class="fas fa-warning"></i> This action cannot be undone.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <form method="post" style="display: inline;" id="deleteForm">
          <input type="hidden" name="delete_user_id" id="deleteUserId">
          <button type="submit" class="btn btn-danger">
            <i class="fas fa-trash"></i> Delete User
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
function confirmDelete(userId, username) {
    document.getElementById('deleteUsername').textContent = username;
    document.getElementById('deleteUserId').value = userId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
</body>
</html>