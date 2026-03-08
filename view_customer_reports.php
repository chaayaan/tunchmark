<?php
require 'auth.php';
require 'mydb.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Reports - Billing App</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .table-responsive {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        .table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        .badge {
            font-size: 0.85em;
            padding: 0.4em 0.8em;
        }
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2><i class="bi bi-file-text"></i> Customer Reports</h2>
            <p class="text-muted">View and manage all customer reports</p>
        </div>
    </div>

    <?php
    // Pagination settings
    $records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 100;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $records_per_page;

    // Search parameter
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

    // Build WHERE clause
    $where_clause = "";
    if (!empty($search)) {
        $where_clause = "WHERE (customer_name LIKE '%$search%' OR item_name LIKE '%$search%' OR order_id LIKE '%$search%')";
    }

    // Get total records
    $count_query = "SELECT COUNT(*) as total FROM customer_reports $where_clause";
    $count_result = mysqli_query($conn, $count_query);
    $total_records = mysqli_fetch_assoc($count_result)['total'];
    $total_pages = ceil($total_records / $records_per_page);

    // Fetch records
    $query = "SELECT * FROM customer_reports $where_clause ORDER BY created_at DESC LIMIT $offset, $records_per_page";
    $result = mysqli_query($conn, $query);
    ?>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-6">
                <label class="form-label"><i class="bi bi-search"></i> Search</label>
                <input type="text" class="form-control" name="search" placeholder="Search by customer name, item, or order ID" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><i class="bi bi-list-ol"></i> Per Page</label>
                <select class="form-select" name="per_page">
                    <option value="15" <?php echo $records_per_page == 15 ? 'selected' : ''; ?>>15</option>
                    <option value="25" <?php echo $records_per_page == 25 ? 'selected' : ''; ?>>25</option>
                    <option value="50" <?php echo $records_per_page == 50 ? 'selected' : ''; ?>>50</option>
                    <option value="100" <?php echo $records_per_page == 100 ? 'selected' : ''; ?>>100</option>
                    <option value="200" <?php echo $records_per_page == 200 ? 'selected' : ''; ?>>200</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
            </div>
        </form>
    </div>

    <!-- Results Summary -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <strong>Total Records:</strong> <?php echo $total_records; ?>
            <span class="text-muted ms-3">Page <?php echo $page; ?> of <?php echo max(1, $total_pages); ?></span>
        </div>
        <?php if (!empty($search)): ?>
            <a href="?" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-x-circle"></i> Clear Search
            </a>
        <?php endif; ?>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Order ID</th>
                    <th>Customer Name</th>
                    <th>Item Name</th>
                    <th>Quantity</th>
                    <th>Service</th>
                    <th>Weight (g)</th>
                    <th>Purity</th>
                    <th>Karat</th>
                    <th>Hallmark</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <?php
                        // Check if service is tunch (not hallmark)
                        $isTunchService = stripos($row['service_name'], 'hallmark') === false && !empty($row['composition_data']);
                        ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><span class="badge bg-info">#<?php echo $row['order_id']; ?></span></td>
                            <td><strong><?php echo htmlspecialchars($row['customer_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['quantity'] ?: '1'); ?></span></td>
                            <td><?php echo htmlspecialchars($row['service_name']); ?></td>
                            <td><?php echo number_format($row['weight'], 3); ?></td>
                            <td><?php echo htmlspecialchars($row['gold_purity'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['karat'] ?: '-'); ?></td>
                            <td><strong><?php echo htmlspecialchars($row['hallmark'] ?: 'N/A'); ?></strong></td>
                            <td><small class="text-muted"><?php echo date('d M Y', strtotime($row['created_at'])); ?></small></td>
                            <td>
                                <?php if ($isTunchService): ?>
                                    <a href="view_tunch_report.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="View Report">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="edit_customer_report_form.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-warning" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="12" class="text-center py-5">
                            <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="text-muted mt-3">No records found</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                        <i class="bi bi-chevron-left"></i> Previous
                    </a>
                </li>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                    </li>
                    <?php if ($start_page > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"><?php echo $total_pages; ?></a>
                    </li>
                <?php endif; ?>
                
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                        Next <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>