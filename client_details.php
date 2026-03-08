<?php
require 'auth.php';
require 'mylicensedb.php';


// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Get client ID
if (!isset($_GET['id'])) {
    header('Location: client_activity.php');
    exit;
}

$client_id = $_GET['id'];

// Fetch client details
try {
    $stmt = $pdo->prepare("SELECT id, branch_name, database_name, status, expire_date, branch_app_link FROM licenses WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();

    if (!$client || !$client['database_name']) {
        $_SESSION['toast_message'] = 'Client not found or database name is missing';
        $_SESSION['toast_type'] = 'danger';
        header('Location: client_activity.php');
        exit;
    }
} catch (PDOException $e) {
    die('Error fetching client: ' . $e->getMessage());
}

// Get selected month/year (default to current month)
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($client['branch_name']); ?> - Activity Details</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h2 {
            color: #333;
            margin-bottom: 5px;
            font-size: 1.5rem;
        }
        .client-info {
            background: linear-gradient(135deg, #14073c 0%, #5f063e 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .client-info .row > div {
            padding: 5px 0;
        }
        .client-info strong {
            opacity: 0.9;
        }
        .month-filter {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stats-section {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s;
        }
        .stats-section:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #0d6efd;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .stat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .stat-row:last-child {
            border-bottom: none;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 500;
        }
        .stat-value {
            font-size: 1.3rem;
            font-weight: bold;
            color: #0d6efd;
        }
        .stat-value.large {
            font-size: 2rem;
        }
        .service-list {
            max-height: 200px;
            overflow-y: auto;
        }
        .service-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            margin: 5px 0;
            background: #f8f9fa;
            border-radius: 5px;
            border-left: 3px solid #0d6efd;
        }
        .service-name {
            font-size: 0.85rem;
            color: #495057;
            font-weight: 500;
        }
        .service-amount {
            font-weight: 600;
            color: #0d6efd;
        }
        .status-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .status-badge {
            flex: 1;
            min-width: 120px;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
        }
        .badge-paid {
            background: #d1e7dd;
            color: #0f5132;
        }
        .badge-pending {
            background: #fff3cd;
            color: #664d03;
        }
        .badge-cancelled {
            background: #f8d7da;
            color: #842029;
        }
        .badge-count {
            display: block;
            font-size: 1.5rem;
            margin-top: 5px;
        }
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.95);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .loading-overlay.active {
            display: flex;
        }
        .compact-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin: 15px 0;
        }
        .compact-stat {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            border-left: 3px solid #0d6efd;
        }
        .compact-stat-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 4px;
        }
        .compact-stat-value {
            font-size: 1.4rem;
            font-weight: bold;
            color: #0d6efd;
        }
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .compact-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="text-center">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3">Loading statistics...</p>
        </div>
    </div>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-chart-bar"></i> <?php echo htmlspecialchars($client['branch_name']); ?></h2>
                <small class="text-muted">Client Activity Details</small>
            </div>
            <a href="client_activity.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>

        <div class="client-info">
            <div class="row">
                <div class="col-md-6">
                    <strong>Client:</strong> <?php echo htmlspecialchars($client['branch_name']); ?>
                </div>
                <div class="col-md-3">
                    <strong>Status:</strong> 
                    <span class="badge bg-<?php echo $client['status'] == 'active' ? 'success' : ($client['status'] == 'expired' ? 'danger' : 'warning'); ?>">
                        <?php echo ucfirst($client['status']); ?>
                    </span>
                </div>
                <div class="col-md-3">
                    <strong>Expires:</strong> <?php echo date('d M Y', strtotime($client['expire_date'])); ?>
                </div>
            </div>
        </div>

        <!-- Month Filter -->
        <div class="month-filter">
            <form method="GET" class="row g-2 align-items-center">
                <input type="hidden" name="id" value="<?php echo $client_id; ?>">
                <div class="col-auto">
                    <label class="form-label mb-0" style="font-weight: 600;">
                        <i class="fas fa-filter"></i> Filter by Month:
                    </label>
                </div>
                <div class="col-auto">
                    <input type="month" name="month" class="form-control form-control-sm" value="<?php echo $selected_month; ?>" style="width: 180px;">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i> Apply
                    </button>
                </div>
                <div class="col-auto">
                    <a href="?id=<?php echo $client_id; ?>" class="btn btn-secondary btn-sm">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Statistics Container -->
        <div id="statsContainer">
            <div class="alert alert-info">
                <i class="fas fa-spinner fa-spin"></i> Loading statistics...
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        const clientId = <?php echo $client_id; ?>;
        const dbName = '<?php echo addslashes($client['database_name']); ?>';
        const selectedMonth = '<?php echo $selected_month; ?>';

        document.addEventListener('DOMContentLoaded', function() {
            loadStats();
        });

        function loadStats() {
            document.getElementById('loadingOverlay').classList.add('active');
            
            fetch('get_client_details_stats.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'client_id=' + clientId + '&db_name=' + encodeURIComponent(dbName) + '&month=' + selectedMonth
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingOverlay').classList.remove('active');
                
                if (data.success) {
                    displayStats(data.stats);
                } else {
                    document.getElementById('statsContainer').innerHTML = 
                        '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + data.message + '</div>';
                }
            })
            .catch(error => {
                document.getElementById('loadingOverlay').classList.remove('active');
                document.getElementById('statsContainer').innerHTML = 
                    '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Failed to load statistics</div>';
            });
        }

        function displayStats(stats) {
            const monthName = new Date(selectedMonth + '-01').toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            
            let html = '<div class="stats-grid">';

            // ========== TODAY'S STATS ==========
            html += '<div class="stats-section">';
            html += '<div class="section-title"><i class="fas fa-calendar-day"></i> Today\'s Activity</div>';
            
            // Compact grid for main stats
            html += '<div class="compact-grid">';
            html += '<div class="compact-stat">';
            html += '<div class="compact-stat-label">Total Orders</div>';
            html += '<div class="compact-stat-value">' + (stats.today.total_orders || 0) + '</div>';
            html += '</div>';
            html += '<div class="compact-stat">';
            html += '<div class="compact-stat-label">Total Amount</div>';
            html += '<div class="compact-stat-value">৳' + formatNumber(stats.today.total_amount || 0) + '</div>';
            html += '</div>';
            html += '</div>';

            // Status breakdown for today
            html += '<div style="margin-top: 15px;">';
            html += '<div style="font-size: 0.85rem; color: #6c757d; font-weight: 600; margin-bottom: 8px;">Order Status Breakdown</div>';
            html += '<div class="status-badges">';
            html += '<div class="status-badge badge-paid">';
            html += '<div><i class="fas fa-check-circle"></i> Paid</div>';
            html += '<div class="badge-count">' + (stats.today.paid_orders || 0) + '</div>';
            html += '<div style="font-size: 0.9rem; margin-top: 5px;">৳' + formatNumber(stats.today.paid_amount || 0) + '</div>';
            html += '</div>';
            html += '<div class="status-badge badge-pending">';
            html += '<div><i class="fas fa-clock"></i> Pending</div>';
            html += '<div class="badge-count">' + (stats.today.pending_orders || 0) + '</div>';
            html += '<div style="font-size: 0.9rem; margin-top: 5px;">৳' + formatNumber(stats.today.pending_amount || 0) + '</div>';
            html += '</div>';
            html += '<div class="status-badge badge-cancelled">';
            html += '<div><i class="fas fa-times-circle"></i> Cancelled</div>';
            html += '<div class="badge-count">' + (stats.today.cancelled_orders || 0) + '</div>';
            html += '<div style="font-size: 0.9rem; margin-top: 5px;">৳' + formatNumber(stats.today.cancelled_amount || 0) + '</div>';
            html += '</div>';
            html += '</div></div>';
            
            // Services breakdown for today
            if (stats.today.services && stats.today.services.length > 0) {
                html += '<div style="margin-top: 15px;">';
                html += '<div style="font-size: 0.85rem; color: #6c757d; font-weight: 600; margin-bottom: 8px;">Service Breakdown</div>';
                html += '<div class="service-list">';
                stats.today.services.forEach(service => {
                    html += '<div class="service-item">';
                    html += '<span class="service-name">' + service.service_name + '</span>';
                    html += '<span class="service-amount">৳' + formatNumber(service.total_amount) + '</span>';
                    html += '</div>';
                });
                html += '</div></div>';
            }
            html += '</div>';

            // ========== MONTHLY STATS ==========
            html += '<div class="stats-section">';
            html += '<div class="section-title"><i class="fas fa-calendar-alt"></i> ' + monthName + '</div>';
            
            // Compact grid for main stats
            html += '<div class="compact-grid">';
            html += '<div class="compact-stat">';
            html += '<div class="compact-stat-label">Total Orders</div>';
            html += '<div class="compact-stat-value">' + (stats.monthly.total_orders || 0) + '</div>';
            html += '</div>';
            html += '<div class="compact-stat">';
            html += '<div class="compact-stat-label">Total Amount</div>';
            html += '<div class="compact-stat-value">৳' + formatNumber(stats.monthly.total_amount || 0) + '</div>';
            html += '</div>';
            html += '</div>';
            
            // Status breakdown for month
            html += '<div style="margin-top: 15px;">';
            html += '<div style="font-size: 0.85rem; color: #6c757d; font-weight: 600; margin-bottom: 8px;">Order Status Breakdown</div>';
            html += '<div class="status-badges">';
            html += '<div class="status-badge badge-paid">';
            html += '<div><i class="fas fa-check-circle"></i> Paid</div>';
            html += '<div class="badge-count">' + (stats.monthly.paid_orders || 0) + '</div>';
            html += '<div style="font-size: 0.9rem; margin-top: 5px;">৳' + formatNumber(stats.monthly.paid_amount || 0) + '</div>';
            html += '</div>';
            html += '<div class="status-badge badge-pending">';
            html += '<div><i class="fas fa-clock"></i> Pending</div>';
            html += '<div class="badge-count">' + (stats.monthly.pending_orders || 0) + '</div>';
            html += '<div style="font-size: 0.9rem; margin-top: 5px;">৳' + formatNumber(stats.monthly.pending_amount || 0) + '</div>';
            html += '</div>';
            html += '<div class="status-badge badge-cancelled">';
            html += '<div><i class="fas fa-times-circle"></i> Cancelled</div>';
            html += '<div class="badge-count">' + (stats.monthly.cancelled_orders || 0) + '</div>';
            html += '<div style="font-size: 0.9rem; margin-top: 5px;">৳' + formatNumber(stats.monthly.cancelled_amount || 0) + '</div>';
            html += '</div>';
            html += '</div></div>';

            // Services breakdown for month
            if (stats.monthly.services && stats.monthly.services.length > 0) {
                html += '<div style="margin-top: 15px;">';
                html += '<div style="font-size: 0.85rem; color: #6c757d; font-weight: 600; margin-bottom: 8px;">Service Breakdown</div>';
                html += '<div class="service-list">';
                stats.monthly.services.forEach(service => {
                    html += '<div class="service-item">';
                    html += '<span class="service-name">' + service.service_name + '</span>';
                    html += '<span class="service-amount">৳' + formatNumber(service.total_amount) + '</span>';
                    html += '</div>';
                });
                html += '</div></div>';
            }
            
            html += '</div>';

            html += '</div>'; // Close stats-grid

            document.getElementById('statsContainer').innerHTML = html;
        }

        function formatNumber(num) {
            return parseFloat(num).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
    </script>
</body>
</html>