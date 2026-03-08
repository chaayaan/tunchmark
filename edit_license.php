<?php
require 'auth.php';
require 'mylicensedb.php';


// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Get license ID
if (!isset($_GET['id'])) {
    header('Location: manage_licenses.php');
    exit;
}

$license_id = $_GET['id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("UPDATE licenses SET branch_name = ?, branch_app_link = ?, database_name = ?, license_key = ?, expire_date = ?, last_renew_date = ?, status = ? WHERE id = ?");
        $stmt->execute([
            $_POST['branch_name'],
            $_POST['branch_app_link'],
            $_POST['database_name'],
            $_POST['license_key'],
            $_POST['expire_date'],
            $_POST['last_renew_date'] ?: null,
            $_POST['status'],
            $license_id
        ]);
        
        $_SESSION['toast_message'] = 'License updated successfully';
        $_SESSION['toast_type'] = 'success';
        header('Location: manage_licenses.php');
        exit;
    } catch (PDOException $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Fetch license data
try {
    $stmt = $pdo->prepare("SELECT * FROM licenses WHERE id = ?");
    $stmt->execute([$license_id]);
    $license = $stmt->fetch();
    
    if (!$license) {
        header('Location: manage_licenses.php');
        exit;
    }
} catch (PDOException $e) {
    die('Error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit License</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            background: white;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h2 {
            color: #333;
            margin-bottom: 20px;
        }
        .form-section {
            background: #fafafa;
            padding: 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-edit"></i> Edit License</h2>
            <a href="manage_licenses.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="form-section">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Branch Name *</label>
                        <input type="text" name="branch_name" class="form-control" value="<?php echo htmlspecialchars($license['branch_name']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Branch App Link</label>
                        <input type="text" name="branch_app_link" class="form-control" value="<?php echo htmlspecialchars($license['branch_app_link']); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Database Name</label>
                        <input type="text" name="database_name" class="form-control" value="<?php echo htmlspecialchars($license['database_name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">License Key *</label>
                        <input type="text" name="license_key" class="form-control" value="<?php echo htmlspecialchars($license['license_key']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Expiration Date *</label>
                        <input type="date" name="expire_date" class="form-control" value="<?php echo $license['expire_date']; ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Last Renew Date</label>
                        <input type="date" name="last_renew_date" class="form-control" value="<?php echo $license['last_renew_date']; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Status *</label>
                        <select name="status" class="form-select" required>
                            <option value="active" <?php echo $license['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="expired" <?php echo $license['status'] == 'expired' ? 'selected' : ''; ?>>Expired</option>
                            <option value="suspended" <?php echo $license['status'] == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update License
                    </button>
                    <a href="manage_licenses.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>