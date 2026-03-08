<?php
require 'auth.php';
require 'mydb.php';

// Get report ID from URL
$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch report data
$query = "SELECT * FROM customer_reports WHERE id = $report_id";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    header('Location: view_customer_reports.php');
    exit();
}

$report = mysqli_fetch_assoc($result);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $quantity = (int)$_POST['quantity'];
    $gold_purity = mysqli_real_escape_string($conn, $_POST['gold_purity']);
    $karat = mysqli_real_escape_string($conn, $_POST['karat']);
    $hallmark = mysqli_real_escape_string($conn, $_POST['hallmark']);
    
    $update_query = "UPDATE customer_reports SET 
        quantity = $quantity,
        gold_purity = '$gold_purity',
        karat = '$karat',
        hallmark = '$hallmark'
        WHERE id = $report_id";
    
    if (mysqli_query($conn, $update_query)) {
        // Redirect to list page after successful update
        header('Location: view_customer_reports.php');
        exit();
    } else {
        $error_message = "Error updating report: " . mysqli_error($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Customer Report - Billing App</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .form-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .required::after {
            content: " *";
            color: red;
        }
        .editable-field {
            background-color: #fff3cd;
            border: 2px solid #ffc107;
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-4 mb-5">
    <div class="row">
        <div class="col-lg-10 offset-lg-1">
            <div class="form-card">
                <div class="form-header">
                    <h3 class="mb-0"><i class="bi bi-pencil-square"></i> Edit Customer Report</h3>
                    <p class="mb-0 mt-2 opacity-75">Update report details for Order #<?php echo $report['order_id']; ?></p>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="row g-3">
                        <!-- Order ID (Read-only) -->
                        <div class="col-md-6">
                            <label for="order_id" class="form-label">Order ID</label>
                            <input type="number" class="form-control" id="order_id" 
                                   value="<?php echo htmlspecialchars($report['order_id']); ?>" readonly disabled>
                        </div>

                        <!-- Customer Name (Read-only) -->
                        <div class="col-md-6">
                            <label for="customer_name" class="form-label">Customer Name</label>
                            <input type="text" class="form-control" id="customer_name" 
                                   value="<?php echo htmlspecialchars($report['customer_name']); ?>" readonly disabled>
                        </div>

                        <!-- Item Name (Read-only) -->
                        <div class="col-md-6">
                            <label for="item_name" class="form-label">Item Name</label>
                            <input type="text" class="form-control" id="item_name" 
                                   value="<?php echo htmlspecialchars($report['item_name']); ?>" readonly disabled>
                        </div>

                        <!-- Service Name (Read-only) -->
                        <div class="col-md-6">
                            <label for="service_name" class="form-label">Service Name</label>
                            <input type="text" class="form-control" id="service_name" 
                                   value="<?php echo htmlspecialchars($report['service_name']); ?>" readonly disabled>
                        </div>

                        <!-- Weight (Read-only) -->
                        <div class="col-md-4">
                            <label for="weight" class="form-label">Weight (grams)</label>
                            <input type="text" class="form-control" id="weight" 
                                   value="<?php echo htmlspecialchars($report['weight']); ?>" readonly disabled>
                        </div>

                        <!-- Quantity (Editable) -->
                        <div class="col-md-4">
                            <label for="quantity" class="form-label required">Quantity</label>
                            <input type="number" class="form-control editable-field" id="quantity" name="quantity" 
                                   value="<?php echo htmlspecialchars($report['quantity'] ?: '1'); ?>" 
                                   min="1" required>
                        </div>

                        <!-- Gold Purity (Editable) -->
                        <div class="col-md-4">
                            <label for="gold_purity" class="form-label">Gold Purity</label>
                            <input type="text" class="form-control editable-field" id="gold_purity" name="gold_purity" 
                                   value="<?php echo htmlspecialchars($report['gold_purity']); ?>" 
                                   placeholder="e.g., 91.6, 87.5%">
                        </div>

                        <!-- Karat (Editable) -->
                        <div class="col-md-6">
                            <label for="karat" class="form-label">Karat</label>
                            <input type="text" class="form-control editable-field" id="karat" name="karat" 
                                   value="<?php echo htmlspecialchars($report['karat']); ?>" 
                                   placeholder="e.g., 22, 21, 18">
                        </div>

                        <!-- Hallmark (Editable) -->
                        <div class="col-md-6">
                            <label for="hallmark" class="form-label">Hallmark</label>
                            <input type="text" class="form-control editable-field" id="hallmark" name="hallmark" 
                                   value="<?php echo htmlspecialchars($report['hallmark']); ?>" 
                                   placeholder="e.g., 916 RJ, SRJ 22K">
                        </div>

                        <div class="col-md-12">
                            <div class="alert alert-warning">
                                <small>
                                    <strong><i class="bi bi-info-circle"></i> Note:</strong> 
                                    Only Quantity, Gold Purity, Karat, and Hallmark fields (highlighted in yellow) can be edited. Other fields are read-only.
                                </small>
                            </div>
                        </div>

                        <!-- Record Info -->
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                <small>
                                    <strong><i class="bi bi-info-circle"></i> Record Information:</strong><br>
                                    Created: <?php echo date('d M Y, h:i A', strtotime($report['created_at'])); ?><br>
                                    Last Updated: <?php echo date('d M Y, h:i A', strtotime($report['updated_at'])); ?>
                                </small>
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="col-md-12">
                            <hr>
                            <div class="d-flex justify-content-between">
                                <a href="view_customer_reports.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Back to List
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Update Report
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>