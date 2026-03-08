<?php
require 'auth.php';
require 'mydb.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_item') {
        $name = trim($_POST['item_name'] ?? '');
        
        if (!empty($name)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO items (name) VALUES (?)");
            mysqli_stmt_bind_param($stmt, "s", $name);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['message'] = "✅ Item added successfully!";
            } else {
                $_SESSION['error'] = "❌ Failed to add item: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error'] = "⚠️ Item name is required";
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    
    elseif ($action === 'add_service') {
        $name = trim($_POST['service_name'] ?? '');
        $price = floatval($_POST['service_price'] ?? 0);
        
        if (!empty($name)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO services (name, price) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, "sd", $name, $price);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['message'] = "✅ Service added successfully!";
            } else {
                $_SESSION['error'] = "❌ Failed to add service: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error'] = "⚠️ Service name is required";
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    
    elseif ($action === 'toggle_item') {
        $id = intval($_POST['id'] ?? 0);
        $status = intval($_POST['status'] ?? 0);
        $newStatus = $status ? 0 : 1;
        
        $stmt = mysqli_prepare($conn, "UPDATE items SET is_active = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $newStatus, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['message'] = $newStatus ? "✅ Item activated successfully!" : "⚠️ Item deactivated!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    
    elseif ($action === 'toggle_service') {
        $id = intval($_POST['id'] ?? 0);
        $status = intval($_POST['status'] ?? 0);
        $newStatus = $status ? 0 : 1;
        
        $stmt = mysqli_prepare($conn, "UPDATE services SET is_active = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $newStatus, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['message'] = $newStatus ? "✅ Service activated successfully!" : "⚠️ Service deactivated!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    
    elseif ($action === 'update_service') {
        $id = intval($_POST['service_id'] ?? 0);
        $name = trim($_POST['service_name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        
        if (!empty($name)) {
            $stmt = mysqli_prepare($conn, "UPDATE services SET name = ?, price = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sdi", $name, $price, $id);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['message'] = "✅ Service updated successfully!";
            } else {
                $_SESSION['error'] = "❌ Failed to update service: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error'] = "⚠️ Service name cannot be empty";
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    
    elseif ($action === 'update_item_name') {
        $id = intval($_POST['item_id'] ?? 0);
        $name = trim($_POST['item_name'] ?? '');
        
        if (!empty($name)) {
            $stmt = mysqli_prepare($conn, "UPDATE items SET name = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $name, $id);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['message'] = "✅ Item updated successfully!";
            } else {
                $_SESSION['error'] = "❌ Failed to update item name: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error'] = "⚠️ Item name cannot be empty";
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Get messages from session
$message = $_SESSION['message'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['message'], $_SESSION['error']);

// Fetch items
$items = [];
$itemsResult = mysqli_query($conn, "SELECT * FROM items ORDER BY name ASC");
if ($itemsResult) {
    while ($row = mysqli_fetch_assoc($itemsResult)) {
        $items[] = $row;
    }
}

// Fetch services
$services = [];
$servicesResult = mysqli_query($conn, "SELECT * FROM services ORDER BY name ASC");
if ($servicesResult) {
    while ($row = mysqli_fetch_assoc($servicesResult)) {
        $services[] = $row;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Items & Services - Rajaiswari</title>
<link rel="icon" type="image/png" href="favicon.png">
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { background: #f8f9fa; }
    .card { margin-bottom: 20px; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
    .badge-active { background-color: #28a745; }
    .badge-inactive { background-color: #6c757d; }
    .edit-price-input { width: 100px; display: inline-block; }
    .edit-name-input { width: 150px; display: inline-block; }
    .alert { position: sticky; top: 10px; z-index: 1000; }
</style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <h2 class="mb-4">🛠️ Manage Items & Services</h2>
    
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Items Section -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">📦 Items</h5>
                </div>
                <div class="card-body">
                    <!-- Add Item Form -->
                    <form method="POST" class="mb-4 p-3 border rounded bg-light" onsubmit="return confirm('Are you sure you want to add this item?');">
                        <input type="hidden" name="action" value="add_item">
                        <h6>Add New Item</h6>
                        <div class="mb-2">
                            <input type="text" name="item_name" class="form-control" placeholder="Item Name" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">➕ Add Item</button>
                    </form>
                    
                    <!-- Items List -->
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                <tr>
                                    <form method="POST" style="display:contents;" onsubmit="return confirm('Are you sure you want to update this item?');">
                                        <td><?= htmlspecialchars($item['id']) ?></td>
                                        <td>
                                            <input type="hidden" name="action" value="update_item_name">
                                            <input type="hidden" name="item_id" value="<?= htmlspecialchars($item['id']) ?>">
                                            <input type="text" name="item_name" class="form-control form-control-sm edit-name-input" 
                                                   value="<?= htmlspecialchars($item['name']) ?>" required>
                                        </td>
                                        <td>
                                            <span class="badge <?= $item['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                                                <?= $item['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="submit" class="btn btn-sm btn-outline-primary me-1">💾 Save</button>
                                    </form>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to <?= $item['is_active'] ? 'deactivate' : 'activate' ?> this item?');">
                                                <input type="hidden" name="action" value="toggle_item">
                                                <input type="hidden" name="id" value="<?= htmlspecialchars($item['id']) ?>">
                                                <input type="hidden" name="status" value="<?= htmlspecialchars($item['is_active']) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                    <?= $item['is_active'] ? 'Deactivate' : 'Activate' ?>
                                                </button>
                                            </form>
                                        </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Services Section -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">🔧 Services</h5>
                </div>
                <div class="card-body">
                    <!-- Add Service Form -->
                    <form method="POST" class="mb-4 p-3 border rounded bg-light" onsubmit="return confirm('Are you sure you want to add this service?');">
                        <input type="hidden" name="action" value="add_service">
                        <h6>Add New Service</h6>
                        <div class="mb-2">
                            <input type="text" name="service_name" class="form-control" placeholder="Service Name" required>
                        </div>
                        <div class="mb-2">
                            <input type="number" name="service_price" class="form-control" placeholder="Price (0 for manual)" step="0.01" min="0" value="0">
                        </div>
                        <button type="submit" class="btn btn-success btn-sm">➕ Add Service</button>
                    </form>
                    
                    <!-- Services List -->
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Price (TK)</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($services as $service): ?>
                                <tr>
                                    <form method="POST" style="display:contents;" onsubmit="return confirm('Are you sure you want to update this service?');">
                                        <td><?= htmlspecialchars($service['id']) ?></td>
                                        <td>
                                            <input type="hidden" name="action" value="update_service">
                                            <input type="hidden" name="service_id" value="<?= htmlspecialchars($service['id']) ?>">
                                            <input type="text" name="service_name" class="form-control form-control-sm edit-name-input" 
                                                   value="<?= htmlspecialchars($service['name']) ?>" required>
                                        </td>
                                        <td>
                                            <input type="number" name="price" class="form-control form-control-sm edit-price-input" 
                                                   value="<?= htmlspecialchars($service['price']) ?>" step="0.01" min="0">
                                        </td>
                                        <td>
                                            <span class="badge <?= $service['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                                                <?= $service['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="submit" class="btn btn-sm btn-outline-primary me-1">💾 Save</button>
                                    </form>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to <?= $service['is_active'] ? 'deactivate' : 'activate' ?> this service?');">
                                                <input type="hidden" name="action" value="toggle_service">
                                                <input type="hidden" name="id" value="<?= htmlspecialchars($service['id']) ?>">
                                                <input type="hidden" name="status" value="<?= htmlspecialchars($service['is_active']) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                    <?= $service['is_active'] ? 'Deactivate' : 'Activate' ?>
                                                </button>
                                            </form>
                                        </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-dismiss alerts after 10 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 10000);
        });
    });
</script>
</body>
</html>