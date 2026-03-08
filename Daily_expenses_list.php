<?php
/**
 * Daily Expenses List - Simple View
 * Shows expenses with date range filter (default: current month)
 */

// Include database connection
require 'auth.php';
include 'mydb.php';

// Set timezone
date_default_timezone_set('Asia/Dhaka');

// ============================================================================
// GET FILTER PARAMETERS
// ============================================================================

// Default to current month
$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate = $_GET['to_date'] ?? date('Y-m-t');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
    $fromDate = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
    $toDate = date('Y-m-t');
}

// Ensure from_date is not after to_date
if (strtotime($fromDate) > strtotime($toDate)) {
    $temp = $fromDate;
    $fromDate = $toDate;
    $toDate = $temp;
}

// ============================================================================
// FETCH EXPENSE DATA
// ============================================================================

$expenses = [];
$totalAmount = 0;

$sql = "SELECT DATE(created_time) as expense_date, 
               GROUP_CONCAT(details SEPARATOR ', ') as notes,
               SUM(amount) as daily_total
        FROM daily_expenses 
        WHERE DATE(created_time) BETWEEN ? AND ?
        GROUP BY DATE(created_time)
        ORDER BY expense_date ASC";

$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ss", $fromDate, $toDate);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $expenses[] = $row;
        $totalAmount += $row['daily_total'];
    }
    
    mysqli_stmt_close($stmt);
}

// Include navbar
include 'navbar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses List - POS System</title>
    <link rel="icon" type="image/png" href="favicon.png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        body {
            background-color: #f8f9fa;
            padding-bottom: 3rem;
        }
        
        .page-header {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
        }
        
        .filter-section {
            background: white;
            border-radius: 0.5rem;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .table-section {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            overflow: hidden;
        }
        
        table td, table th {
            vertical-align: middle;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 0.5rem;
        }
        
        @media print {
            .filter-section, .btn, .page-header .btn {
                display: none !important;
            }
        }
    </style>
</head>

<body>
<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10">
    
            <!-- Page Header -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h3 class="mb-0">
                            <i class="bi bi-receipt-cutoff me-2"></i>
                            Daily Expenses List
                        </h3>
                    </div>
                    <!-- <div class="col-md-6 text-md-end mt-2 mt-md-0">
                        <button onclick="window.print()" class="btn btn-light btn-sm">
                            <i class="bi bi-printer me-1"></i>
                            Print
                        </button>
                    </div> -->
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" action="" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-calendar-event me-1"></i>
                            From Date
                        </label>
                        <input type="date" 
                               name="from_date" 
                               class="form-control" 
                               value="<?= htmlspecialchars($fromDate) ?>"
                               required>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-calendar-check me-1"></i>
                            To Date
                        </label>
                        <input type="date" 
                               name="to_date" 
                               class="form-control" 
                               value="<?= htmlspecialchars($toDate) ?>"
                               required>
                    </div>
                    
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-filter me-1"></i>
                            Filter
                        </button>
                    </div>
                    
                    <div class="col-md-2">
                        <a href="?" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-arrow-clockwise me-1"></i>
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <?php if (!empty($expenses)): ?>
                
                <!-- Expenses Table -->
                <div class="table-section">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th width="15%">Date</th>
                                    <th width="15%">Day</th>
                                    <th width="50%">Note</th>
                                    <th width="20%" class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expenses as $expense): 
                                    $date = $expense['expense_date'];
                                    $dayName = date('l', strtotime($date));
                                    $formattedDate = date('M d, Y', strtotime($date));
                                ?>
                                <tr>
                                    <td><?= $formattedDate ?></td>
                                    <td><?= $dayName ?></td>
                                    <td><?= htmlspecialchars($expense['notes']) ?></td>
                                    <td class="text-end text-danger fw-bold">
                                        ৳<?= number_format($expense['daily_total'], 2) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="3" class="text-end">
                                        <i class="bi bi-calculator me-2"></i>
                                        Total:
                                    </th>
                                    <th class="text-end text-danger fs-5">
                                        ৳<?= number_format($totalAmount, 2) ?>
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

            <?php else: ?>
                
                <!-- Empty State -->
                <div class="empty-state">
                    <i class="bi bi-inbox" style="font-size: 4rem; color: #dee2e6;"></i>
                    <h5 class="mt-3 text-muted">No Expenses Found</h5>
                    <p class="text-muted">
                        No expenses recorded between <?= date('M d, Y', strtotime($fromDate)) ?> 
                        and <?= date('M d, Y', strtotime($toDate)) ?>
                    </p>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.js"></script>

</body>
</html>