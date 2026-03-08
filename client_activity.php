<?php
require 'auth.php';
require 'mylicensedb.php';


// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Fetch all licenses with database names - SORTED BY ID ASC
try {
    $stmt = $pdo->query("SELECT id, branch_name, database_name, status, expire_date, last_renew_date, branch_app_link FROM licenses WHERE database_name IS NOT NULL AND database_name != '' ORDER BY id ASC");
    $clients = $stmt->fetchAll();
} catch (PDOException $e) {
    die('Error fetching clients: ' . $e->getMessage());
}

// Helper function to calculate days remaining
function getDaysRemaining($expireDate) {
    $today = new DateTime();
    $expire = new DateTime($expireDate);
    $today->setTime(0, 0, 0);
    $expire->setTime(0, 0, 0);
    $diff = $today->diff($expire);
    
    if ($expire < $today) {
        return -$diff->days; // Negative for expired
    }
    return $diff->days;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Activity Monitor</title>
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
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h2 {
            color: #333;
            margin-bottom: 20px;
        }
        .table {
            margin-top: 20px;
            font-size: 0.9rem;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .client-name {
            font-weight: 600;
            color: #0d6efd;
        }
        .status-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .status-active {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        .status-expired {
            background-color: #f8d7da;
            color: #842029;
        }
        .status-suspended {
            background-color: #fff3cd;
            color: #664d03;
        }
        .btn-view {
            padding: 4px 10px;
            font-size: 0.85rem;
        }
        .view-details-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
            background-color: #cfe2ff;
            color: #084298;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        .view-details-badge:hover {
            background-color: #9ec5fe;
            color: #052c65;
        }
        .days-remaining {
            font-weight: 600;
        }
        .days-warning {
            color: #dc3545;
        }
        .days-ok {
            color: #198754;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <h2><i class="fas fa-chart-line"></i> Client Activity Monitor</h2>

        <?php if (empty($clients)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No clients with database names found. Please add database names to clients in License Management.
            </div>
        <?php else: ?>
            <div class="alert alert-info mb-4">
                <i class="fas fa-info-circle"></i> Click "View Details" to see order statistics for any client.
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Client Name</th>
                            <th>Status</th>
                            <th>Last Renew</th>
                            <th>Expire Date</th>
                            <th>Days Remaining</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $count = 1; ?>
                        <?php foreach ($clients as $client): ?>
                            <?php 
                                $daysRemaining = getDaysRemaining($client['expire_date']);
                                $daysClass = $daysRemaining > 30 ? 'days-ok' : 'days-warning';
                            ?>
                            <tr>
                                <td><?php echo $count++; ?></td>
                                <td class="client-name">
                                    <i class="fas fa-cogs"></i> <?php echo htmlspecialchars($client['branch_name']); ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $client['status']; ?>">
                                        <?php echo ucfirst($client['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                        if (!empty($client['last_renew_date']) && $client['last_renew_date'] != '0000-00-00') {
                                            echo date('d M Y', strtotime($client['last_renew_date']));
                                        } else {
                                            echo '<span class="text-muted">N/A</span>';
                                        }
                                    ?>
                                </td>
                                <td><?php echo date('d M Y', strtotime($client['expire_date'])); ?></td>
                                <td>
                                    <span class="days-remaining <?php echo $daysClass; ?>">
                                        <?php 
                                            if ($daysRemaining < 0) {
                                                echo 'Expired ' . abs($daysRemaining) . ' days ago';
                                            } elseif ($daysRemaining == 0) {
                                                echo 'Expires Today';
                                            } else {
                                                echo $daysRemaining . ' days';
                                            }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="client_details.php?id=<?php echo $client['id']; ?>" class="view-details-badge">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>