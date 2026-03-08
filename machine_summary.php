<?php
// Include required files
require_once 'mydb.php';
require_once 'auth.php';


// Handle ADD machine request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_machine'])) {
    $machine_type = trim($_POST['machine_type']);
    $stock = !empty($_POST['add_stock']) ? intval($_POST['add_stock']) : NULL;
    $ordered = !empty($_POST['add_ordered']) ? intval($_POST['add_ordered']) : NULL;
    $delivered = !empty($_POST['add_delivered']) ? intval($_POST['add_delivered']) : NULL;
    $maintenance = !empty($_POST['add_maintenance']) ? intval($_POST['add_maintenance']) : NULL;
    
    if (!empty($machine_type)) {
        $add_sql = "INSERT INTO machines_summary (machine_type, stock, ordered, delivered, maintenance) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($add_sql);
        $stmt->bind_param("siiii", $machine_type, $stock, $ordered, $delivered, $maintenance);
        
        if ($stmt->execute()) {
            $message = "Machine added successfully!";
            $message_type = "success";
        } else {
            $message = "Error adding machine: " . $conn->error;
            $message_type = "danger";
        }
        $stmt->close();
    } else {
        $message = "Machine type is required!";
        $message_type = "warning";
    }
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle UPDATE request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $id = intval($_POST['id']);
    $stock = !empty($_POST['stock']) ? intval($_POST['stock']) : NULL;
    $ordered = !empty($_POST['ordered']) ? intval($_POST['ordered']) : NULL;
    $delivered = !empty($_POST['delivered']) ? intval($_POST['delivered']) : NULL;
    $maintenance = !empty($_POST['maintenance']) ? intval($_POST['maintenance']) : NULL;
    
    $update_sql = "UPDATE machines_summary SET stock=?, ordered=?, delivered=?, maintenance=? WHERE id=?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("iiiii", $stock, $ordered, $delivered, $maintenance, $id);
    
    if ($stmt->execute()) {
        $message = "Record updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error updating record: " . $conn->error;
        $message_type = "danger";
    }
    $stmt->close();
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . (isset($_GET['search']) ? '?search=' . urlencode($_GET['search']) : ''));
    exit();
}

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $id = intval($_POST['delete_id']);
    
    $delete_sql = "DELETE FROM machines_summary WHERE id=?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $message = "Machine deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error deleting machine: " . $conn->error;
        $message_type = "danger";
    }
    $stmt->close();
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . (isset($_GET['search']) ? '?search=' . urlencode($_GET['search']) : ''));
    exit();
}
include 'navbar.php'; 
// Get search parameter
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Fetch data with search filter
if (!empty($search)) {
    $sql = "SELECT * FROM machines_summary WHERE machine_type LIKE ? ORDER BY machine_type";
    $stmt = $conn->prepare($sql);
    $search_param = "%$search%";
    $stmt->bind_param("s", $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT * FROM machines_summary ORDER BY machine_type";
    $result = $conn->query($sql);
}

// Calculate totals (handle NULL values with COALESCE)
$totals_sql = "SELECT 
               COALESCE(SUM(stock), 0) as total_stock, 
               COALESCE(SUM(ordered), 0) as total_ordered, 
               COALESCE(SUM(delivered), 0) as total_delivered, 
               COALESCE(SUM(maintenance), 0) as total_maintenance,
               COUNT(*) as total_machines
               FROM machines_summary";
if (!empty($search)) {
    $totals_sql .= " WHERE machine_type LIKE '%$search%'";
}
$totals_result = $conn->query($totals_sql);
$totals = $totals_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Machine Warehouse Summary Dashboard</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #4e73df;
            --success-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --info-color: #36b9cc;
        }
        
        body {
            background-color: #f8f9fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .dashboard-container {
            padding: 30px 15px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .page-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 600;
        }
        
        .page-header p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
        
        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            border-left: 4px solid;
            position: relative;
        }
        
        .summary-card.stock {
            border-left-color: var(--primary-color);
        }
        
        .summary-card.ordered {
            border-left-color: var(--info-color);
        }
        
        .summary-card.delivered {
            border-left-color: var(--success-color);
        }
        
        .summary-card.maintenance {
            border-left-color: var(--warning-color);
        }
        
        .summary-card.total {
            border-left-color: var(--danger-color);
        }
        
        .summary-card .card-icon {
            font-size: 2.5rem;
            opacity: 0.2;
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .summary-card .card-label {
            font-size: 0.85rem;
            color: #858796;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .summary-card .card-value {
            font-size: 2rem;
            font-weight: 700;
            color: #5a5c69;
        }
        
        .search-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }
        
        .search-box input {
            border: 2px solid #e3e6f0;
            padding: 12px 20px;
            font-size: 16px;
        }
        
        .search-box input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        
        .btn-add-machine {
            background: linear-gradient(135deg, var(--success-color) 0%, #169b6b 100%);
            border: none;
            padding: 12px 25px;
            color: white;
            font-weight: 600;
            border-radius: 5px;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .table thead th {
            border: none;
            padding: 15px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        
        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .editable-input {
            width: 80px;
            padding: 8px 10px;
            border: 2px solid #e3e6f0;
            border-radius: 5px;
            text-align: center;
        }
        
        .editable-input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        
        .btn-update {
            background: linear-gradient(135deg, var(--success-color) 0%, #169b6b 100%);
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            color: white;
            font-weight: 600;
        }
        
        .btn-delete {
            background: linear-gradient(135deg, var(--danger-color) 0%, #c23321 100%);
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            color: white;
            font-weight: 600;
        }
        
        .totals-row {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .totals-row td {
            padding: 18px 15px !important;
            border: none !important;
        }
        
        .last-update {
            color: #858796;
            font-size: 0.85rem;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--success-color) 0%, #169b6b 100%);
            color: white;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        
        @media (max-width: 768px) {
            .editable-input {
                width: 60px;
                padding: 6px;
            }
            
            .table {
                font-size: 0.85rem;
            }
            
            .page-header h1 {
                font-size: 1.5rem;
            }
            
            .summary-cards {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
            
            .summary-card .card-value {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    
    <div class="dashboard-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-warehouse me-2"></i>Machine Summary</h1>
            <p>Manage and monitor your machine inventory</p>
        </div>
        
        <!-- Success/Error Message -->
        <?php if (isset($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card stock">
                <i class="fas fa-boxes card-icon"></i>
                <div class="card-label">Total Stock</div>
                <div class="card-value"><?php echo number_format($totals['total_stock']); ?></div>
            </div>
            <div class="summary-card ordered">
                <i class="fas fa-shopping-cart card-icon"></i>
                <div class="card-label">Total Ordered</div>
                <div class="card-value"><?php echo number_format($totals['total_ordered']); ?></div>
            </div>
            <div class="summary-card delivered">
                <i class="fas fa-truck card-icon"></i>
                <div class="card-label">Total Delivered</div>
                <div class="card-value"><?php echo number_format($totals['total_delivered']); ?></div>
            </div>
            <div class="summary-card maintenance">
                <i class="fas fa-tools card-icon"></i>
                <div class="card-label">In Maintenance</div>
                <div class="card-value"><?php echo number_format($totals['total_maintenance']); ?></div>
            </div>
            <div class="summary-card total">
                <i class="fas fa-cogs card-icon"></i>
                <div class="card-label">Total Machines</div>
                <div class="card-value"><?php echo number_format($totals['total_machines']); ?></div>
            </div>
        </div>
        
        <!-- Search Box & Add Button -->
        <div class="search-box">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-8">
                    <div class="input-group">
                        <span class="input-group-text bg-white">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" 
                               class="form-control" 
                               name="search" 
                               placeholder="Search by machine type..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i>Filter
                    </button>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-add-machine w-100" data-bs-toggle="modal" data-bs-target="#addMachineModal">
                        <i class="fas fa-plus me-2"></i>Add Machine
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Main Table -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-cog me-2"></i>Machine Type</th>
                            <th class="text-center"><i class="fas fa-boxes me-2"></i>In Stock</th>
                            <th class="text-center"><i class="fas fa-shopping-cart me-2"></i>Ordered</th>
                            <th class="text-center"><i class="fas fa-truck me-2"></i>Delivered</th>
                            <th class="text-center"><i class="fas fa-tools me-2"></i>Maintenance</th>
                            <th class="text-center"><i class="fas fa-clock me-2"></i>Last Update</th>
                            <th class="text-center"><i class="fas fa-edit me-2"></i>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <form method="POST" action="" style="display: contents;">
                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                    
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['machine_type']); ?></strong>
                                    </td>
                                    <td class="text-center">
                                        <input type="number" 
                                               class="editable-input" 
                                               name="stock" 
                                               value="<?php echo $row['stock'] !== null ? $row['stock'] : ''; ?>" 
                                               placeholder="N/A"
                                               min="0">
                                    </td>
                                    <td class="text-center">
                                        <input type="number" 
                                               class="editable-input" 
                                               name="ordered" 
                                               value="<?php echo $row['ordered'] !== null ? $row['ordered'] : ''; ?>" 
                                               placeholder="N/A"
                                               min="0">
                                    </td>
                                    <td class="text-center">
                                        <input type="number" 
                                               class="editable-input" 
                                               name="delivered" 
                                               value="<?php echo $row['delivered'] !== null ? $row['delivered'] : ''; ?>" 
                                               placeholder="N/A"
                                               min="0">
                                    </td>
                                    <td class="text-center">
                                        <input type="number" 
                                               class="editable-input" 
                                               name="maintenance" 
                                               value="<?php echo $row['maintenance'] !== null ? $row['maintenance'] : ''; ?>" 
                                               placeholder="N/A"
                                               min="0">
                                    </td>
                                    <td class="text-center last-update">
                                        <?php echo date('M d, Y H:i', strtotime($row['last_update'])); ?>
                                    </td>
                                    <td class="text-center">
                                        <button type="submit" 
                                                name="update" 
                                                class="btn btn-update btn-sm me-1">
                                            <i class="fas fa-save me-1"></i>Update
                                        </button>
                                </form>
                                        <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this machine?');">
                                            <input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" 
                                                    name="delete" 
                                                    class="btn btn-delete btn-sm">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No machines found. Try adjusting your search.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <!-- Totals Row -->
                        <tr class="totals-row">
                            <td><i class="fas fa-calculator me-2"></i>TOTALS</td>
                            <td class="text-center"><?php echo number_format($totals['total_stock']); ?></td>
                            <td class="text-center"><?php echo number_format($totals['total_ordered']); ?></td>
                            <td class="text-center"><?php echo number_format($totals['total_delivered']); ?></td>
                            <td class="text-center"><?php echo number_format($totals['total_maintenance']); ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Add Machine Modal -->
    <div class="modal fade" id="addMachineModal" tabindex="-1" aria-labelledby="addMachineModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addMachineModalLabel">
                        <i class="fas fa-plus-circle me-2"></i>Add New Machine
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="machine_type" class="form-label">Machine Type <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control" 
                                   id="machine_type" 
                                   name="machine_type" 
                                   placeholder="Enter machine type (e.g., CNC Lathe Machine)"
                                   required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="add_stock" class="form-label">Stock</label>
                                <input type="number" 
                                       class="form-control" 
                                       id="add_stock" 
                                       name="add_stock" 
                                       placeholder="Leave empty for N/A"
                                       min="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="add_ordered" class="form-label">Ordered</label>
                                <input type="number" 
                                       class="form-control" 
                                       id="add_ordered" 
                                       name="add_ordered" 
                                       placeholder="Leave empty for N/A"
                                       min="0">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="add_delivered" class="form-label">Delivered</label>
                                <input type="number" 
                                       class="form-control" 
                                       id="add_delivered" 
                                       name="add_delivered" 
                                       placeholder="Leave empty for N/A"
                                       min="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="add_maintenance" class="form-label">Maintenance</label>
                                <input type="number" 
                                       class="form-control" 
                                       id="add_maintenance" 
                                       name="add_maintenance" 
                                       placeholder="Leave empty for N/A"
                                       min="0">
                            </div>
                        </div>
                        <div class="alert alert-info" role="alert">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>Fields marked with <span class="text-danger">*</span> are required. Leave numeric fields empty for N/A values.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="submit" name="add_machine" class="btn btn-success">
                            <i class="fas fa-plus me-1"></i>Add Machine
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Optional: Auto-dismiss alerts after 5 seconds -->
    <script>
        // Auto-dismiss alerts
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>