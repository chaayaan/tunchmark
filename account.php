<?php
/**
 * Accounting System Dashboard
 * Main navigation and overview page
 */
// Include files
require 'auth.php';
include 'mydb.php';
include 'navbar.php';

// Set timezone
date_default_timezone_set('Asia/Dhaka');

// Get user role
$userRole = $_SESSION['role'] ?? 'employee';

// Get statistics
$stats = [
    'total_branches' => 0,
    'total_income_categories' => 0,
    'total_expense_categories' => 0,
    'today_income' => 0,
    'today_expenses' => 0
];

// Total branches
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM branches");
if ($result && $row = mysqli_fetch_assoc($result)) {
    $stats['total_branches'] = $row['count'];
}

// Total income categories
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM income_categories");
if ($result && $row = mysqli_fetch_assoc($result)) {
    $stats['total_income_categories'] = $row['count'];
}

// Total expense categories
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM expense_categories");
if ($result && $row = mysqli_fetch_assoc($result)) {
    $stats['total_expense_categories'] = $row['count'];
}

// Today's income
$today = date('Y-m-d');
$result = mysqli_query($conn, "SELECT COALESCE(SUM(income), 0) as total FROM branch_income WHERE DATE(created_at) = '$today'");
if ($result && $row = mysqli_fetch_assoc($result)) {
    $stats['today_income'] = $row['total'];
}

// Today's expenses
$result = mysqli_query($conn, "SELECT COALESCE(SUM(expense), 0) as total FROM branch_expenses WHERE DATE(created_at) = '$today'");
if ($result && $row = mysqli_fetch_assoc($result)) {
    $stats['today_expenses'] = $row['total'];
}

function formatCurrency($amount) {
    return '৳' . number_format($amount, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Dashboard - Branch Management</title>
    <link rel="icon" type="image/png" href="favicon.png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        body {
            background: #f8f9fa;
            padding-bottom: 3rem;
        }
        
        .stat-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15) !important;
        }
        
        .quick-action-card {
            transition: all 0.3s;
            border: 2px solid #e9ecef;
            height: 100%;
        }
        
        .quick-action-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12) !important;
            border-color: #0d6efd;
        }
        
        .quick-action-icon {
            width: 70px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin: 0 auto 1rem;
            font-size: 2rem;
        }
        
        .table-hover tbody tr:hover {
            background: #f8f9fa;
        }
    </style>
</head>

<body>
<div class="container py-4" style="max-width: 1200px;">
    
    <!-- Page Header -->
    <div class="card bg-primary text-white mb-4 shadow">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h3 class="mb-1">
                        <i class="bi bi-speedometer2 me-2"></i>
                        Finance Dashboard
                    </h3>
                    <p class="mb-0 opacity-75">Multi-Branch Financial Management System</p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <div class="badge bg-light text-primary px-3 py-2">
                        <i class="bi bi-calendar-check me-2"></i>
                        <?= date('M d, Y') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1 small text-uppercase fw-bold">Total Branches</p>
                            <h2 class="mb-0 fw-bold text-primary"><?= $stats['total_branches'] ?></h2>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-3 rounded">
                            <i class="bi bi-building fs-2 text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1 small text-uppercase fw-bold">Income Categories</p>
                            <h2 class="mb-0 fw-bold text-success"><?= $stats['total_income_categories'] ?></h2>
                        </div>
                        <div class="bg-success bg-opacity-10 p-3 rounded">
                            <i class="bi bi-tags fs-2 text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1 small text-uppercase fw-bold">Expense Categories</p>
                            <h2 class="mb-0 fw-bold text-danger"><?= $stats['total_expense_categories'] ?></h2>
                        </div>
                        <div class="bg-danger bg-opacity-10 p-3 rounded">
                            <i class="bi bi-tags fs-2 text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <h5 class="mb-3 fw-bold">
        <i class="bi bi-lightning-charge text-warning me-2"></i>
        Quick Actions
    </h5>
    
    <div class="row g-4 mb-4">
        <?php if ($userRole === 'admin'): ?>
        <!-- Manage Branches & Categories -->
        <div class="col-md-3">
            <a href="branches.php" class="text-decoration-none">
                <div class="card quick-action-card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="quick-action-icon bg-primary bg-opacity-10">
                            <i class="bi bi-building text-primary"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Branches & Categories</h5>
                        <p class="text-muted small mb-0">Manage branches and categories</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Record Income & Expenses -->
        <div class="<?= $userRole === 'admin' ? 'col-md-3' : 'col-md-6' ?>">
            <a href="income_expense.php" class="text-decoration-none">
                <div class="card quick-action-card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="quick-action-icon bg-success bg-opacity-10">
                            <i class="bi bi-cash-coin text-success"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Record Transactions</h5>
                        <p class="text-muted small mb-0">Add income & expenses</p>
                    </div>
                </div>
            </a>
        </div>
        
        <?php if ($userRole === 'admin'): ?>
        <!-- View Date-wise Report -->
        <div class="col-md-3">
            <a href="transactions_list.php" class="text-decoration-none">
                <div class="card quick-action-card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="quick-action-icon bg-info bg-opacity-10">
                            <i class="bi bi-calendar-date text-info"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Date-wise Reports</h5>
                        <p class="text-muted small mb-0">Daily transaction analysis</p>
                    </div>
                </div>
            </a>
        </div>
        
        <!-- View Monthly/Yearly Report -->
        <div class="col-md-3">
            <a href="view_report.php" class="text-decoration-none">
                <div class="card quick-action-card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="quick-action-icon bg-warning bg-opacity-10">
                            <i class="bi bi-graph-up text-warning"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Monthly/Yearly Reports</h5>
                        <p class="text-muted small mb-0">Comprehensive analytics</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Today's Summary -->
    <h5 class="mb-3 fw-bold">
        <i class="bi bi-calendar-day text-success me-2"></i>
        Today's Summary
    </h5>
    
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="d-flex justify-content-between align-items-center p-3 bg-success bg-opacity-10 rounded">
                        <div>
                            <small class="text-muted text-uppercase fw-bold d-block mb-1">Income</small>
                            <h3 class="mb-0 text-success fw-bold"><?= formatCurrency($stats['today_income']) ?></h3>
                        </div>
                        <div class="bg-success bg-opacity-25 p-3 rounded">
                            <i class="bi bi-arrow-down-circle fs-2 text-success"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="d-flex justify-content-between align-items-center p-3 bg-danger bg-opacity-10 rounded">
                        <div>
                            <small class="text-muted text-uppercase fw-bold d-block mb-1">Expenses</small>
                            <h3 class="mb-0 text-danger fw-bold"><?= formatCurrency($stats['today_expenses']) ?></h3>
                        </div>
                        <div class="bg-danger bg-opacity-25 p-3 rounded">
                            <i class="bi bi-arrow-up-circle fs-2 text-danger"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="d-flex justify-content-between align-items-center p-3 bg-info bg-opacity-10 rounded">
                        <div>
                            <small class="text-muted text-uppercase fw-bold d-block mb-1">Net Balance</small>
                            <h3 class="mb-0 fw-bold <?= ($stats['today_income'] - $stats['today_expenses']) >= 0 ? 'text-info' : 'text-danger' ?>">
                                <?= formatCurrency($stats['today_income'] - $stats['today_expenses']) ?>
                            </h3>
                        </div>
                        <div class="bg-info bg-opacity-25 p-3 rounded">
                            <i class="bi bi-wallet2 fs-2 text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>