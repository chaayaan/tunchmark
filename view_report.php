<?php
/**
 * Financial Reports System - Fixed
 * Income by branch, Expenses without branch
 */

// Include files
require 'auth.php';
include 'mydb.php';
include 'navbar.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}



// Set timezone
date_default_timezone_set('Asia/Dhaka');

// Helper functions
function formatCurrency($amount) {
    return '৳' . number_format($amount, 2);
}

// Get filter parameters
$viewType = $_GET['view'] ?? 'monthly';
$selectedYear = (int)($_GET['year'] ?? date('Y'));
$selectedMonth = (int)($_GET['month'] ?? date('m'));

// Validate inputs
$viewType = in_array($viewType, ['monthly', 'yearly']) ? $viewType : 'monthly';
if ($selectedYear < 2000 || $selectedYear > 2030) $selectedYear = (int)date('Y');
if ($selectedMonth < 1 || $selectedMonth > 12) $selectedMonth = (int)date('m');

// Fetch all branches
$branches = [];
$result = mysqli_query($conn, "SELECT id, name FROM branches ORDER BY name");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $branches[] = $row;
    }
}

// Fetch expense categories
$expenseCategories = [];
$result = mysqli_query($conn, "SELECT id, name FROM expense_categories ORDER BY name");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $expenseCategories[] = $row;
    }
}

// Fetch income categories
$incomeCategories = [];
$result = mysqli_query($conn, "SELECT id, name FROM income_categories ORDER BY name");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $incomeCategories[] = $row;
    }
}

// Month names
$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

// Initialize data
$expenseData = [];
$incomeMatrix = [];
$summaryMatrix = [];
$monthlyIncomeData = [];
$monthlyExpenseData = [];
$totalIncome = 0;
$totalExpenses = 0;

if ($viewType === 'monthly') {
    // ========== MONTHLY REPORT ==========
    
    // Build Expense Data (NO BRANCHES - just categories)
    foreach ($expenseCategories as $category) {
        $catId = $category['id'];
        
        $sql = "SELECT COALESCE(SUM(expense), 0) as total
                FROM branch_expenses
                WHERE category_id = ?
                AND YEAR(created_at) = ? AND MONTH(created_at) = ?";
        
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iii", $catId, $selectedYear, $selectedMonth);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $data = mysqli_fetch_assoc($result);
            $amount = $data['total'];
            
            if ($amount > 0) {
                $expenseData[] = [
                    'category' => $category['name'],
                    'amount' => $amount
                ];
                $totalExpenses += $amount;
            }
            
            mysqli_stmt_close($stmt);
        }
    }
    
    // Build Income Matrix (WITH BRANCHES)
    foreach ($incomeCategories as $category) {
        $catId = $category['id'];
        $row = [
            'category' => $category['name'],
            'branches' => [],
            'total' => 0
        ];
        
        foreach ($branches as $branch) {
            $branchId = $branch['id'];
            
            $sql = "SELECT COALESCE(SUM(income), 0) as total
                    FROM branch_income
                    WHERE branch_id = ? AND category_id = ?
                    AND YEAR(created_at) = ? AND MONTH(created_at) = ?";
            
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "iiii", $branchId, $catId, $selectedYear, $selectedMonth);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $data = mysqli_fetch_assoc($result);
                $amount = $data['total'];
                
                $row['branches'][$branchId] = $amount;
                $row['total'] += $amount;
                
                mysqli_stmt_close($stmt);
            }
        }
        
        if ($row['total'] > 0) {
            $incomeMatrix[] = $row;
        }
    }
    
    // Build Summary Matrix (Income by branch, Expenses total only)
    foreach ($branches as $branch) {
        $branchId = $branch['id'];
        
        // Get total income for this branch
        $sql = "SELECT COALESCE(SUM(income), 0) as total
                FROM branch_income
                WHERE branch_id = ?
                AND YEAR(created_at) = ? AND MONTH(created_at) = ?";
        
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iii", $branchId, $selectedYear, $selectedMonth);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $data = mysqli_fetch_assoc($result);
            $branchIncome = $data['total'];
            mysqli_stmt_close($stmt);
        } else {
            $branchIncome = 0;
        }
        
        $summaryMatrix[] = [
            'branch' => $branch['name'],
            'income' => $branchIncome
        ];
        
        $totalIncome += $branchIncome;
    }
    
} else {
    // ========== YEARLY REPORT ==========
    
    // Monthly Income Totals (WITH BRANCHES)
    for ($m = 1; $m <= 12; $m++) {
        $row = [
            'month' => $months[$m],
            'branches' => [],
            'total' => 0
        ];
        
        foreach ($branches as $branch) {
            $branchId = $branch['id'];
            
            $sql = "SELECT COALESCE(SUM(income), 0) as total
                    FROM branch_income
                    WHERE branch_id = ?
                    AND YEAR(created_at) = ? AND MONTH(created_at) = ?";
            
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "iii", $branchId, $selectedYear, $m);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $data = mysqli_fetch_assoc($result);
                $amount = $data['total'];
                
                $row['branches'][$branchId] = $amount;
                $row['total'] += $amount;
                
                mysqli_stmt_close($stmt);
            }
        }
        
        $monthlyIncomeData[] = $row;
        $totalIncome += $row['total'];
    }
    
    // Monthly Expense Totals (NO BRANCHES - just monthly totals)
    for ($m = 1; $m <= 12; $m++) {
        $sql = "SELECT COALESCE(SUM(expense), 0) as total
                FROM branch_expenses
                WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?";
        
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ii", $selectedYear, $m);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $data = mysqli_fetch_assoc($result);
            $amount = $data['total'];
            
            $monthlyExpenseData[] = [
                'month' => $months[$m],
                'amount' => $amount
            ];
            
            $totalExpenses += $amount;
            
            mysqli_stmt_close($stmt);
        }
    }
    
    // Income Categories Annual (WITH BRANCHES)
    foreach ($incomeCategories as $category) {
        $catId = $category['id'];
        $row = [
            'category' => $category['name'],
            'branches' => [],
            'total' => 0
        ];
        
        foreach ($branches as $branch) {
            $branchId = $branch['id'];
            
            $sql = "SELECT COALESCE(SUM(income), 0) as total
                    FROM branch_income
                    WHERE branch_id = ? AND category_id = ?
                    AND YEAR(created_at) = ?";
            
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "iii", $branchId, $catId, $selectedYear);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $data = mysqli_fetch_assoc($result);
                $amount = $data['total'];
                
                $row['branches'][$branchId] = $amount;
                $row['total'] += $amount;
                
                mysqli_stmt_close($stmt);
            }
        }
        
        if ($row['total'] > 0) {
            $incomeMatrix[] = $row;
        }
    }
    
    // Expense Categories Annual (NO BRANCHES)
    foreach ($expenseCategories as $category) {
        $catId = $category['id'];
        
        $sql = "SELECT COALESCE(SUM(expense), 0) as total
                FROM branch_expenses
                WHERE category_id = ?
                AND YEAR(created_at) = ?";
        
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ii", $catId, $selectedYear);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $data = mysqli_fetch_assoc($result);
            $amount = $data['total'];
            
            if ($amount > 0) {
                $expenseData[] = [
                    'category' => $category['name'],
                    'amount' => $amount
                ];
            }
            
            mysqli_stmt_close($stmt);
        }
    }
    
    // Yearly Summary (Income by branch, Expenses total)
    foreach ($branches as $branch) {
        $branchId = $branch['id'];
        
        $sql = "SELECT COALESCE(SUM(income), 0) as total FROM branch_income
                WHERE branch_id = ? AND YEAR(created_at) = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $branchId, $selectedYear);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $branchIncome = mysqli_fetch_assoc($result)['total'];
        mysqli_stmt_close($stmt);
        
        $summaryMatrix[] = [
            'branch' => $branch['name'],
            'income' => $branchIncome
        ];
    }
}

$totalMargin = $totalIncome - $totalExpenses;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports - Accounting System</title>
    <link rel="icon" type="image/png" href="favicon.png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        body { background: #f8f9fa; }
        .view-btn.active { background: #0d6efd !important; color: white !important; }
        .text-income { color: #198754; font-weight: 600; }
        .text-expense { color: #dc3545; font-weight: 600; }
        .text-margin { color: #0dcaf0; font-weight: 600; }
        .table-hover tbody tr:hover { background: #f8f9fa; }
    </style>
</head>

<body>
<div class="container-fluid py-4" style="max-width: 1600px;">
    
    <!-- Header -->
    <div class="card bg-primary text-white mb-4 shadow">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="bi bi-graph-up-arrow me-2"></i>Financial Reports Dashboard</h4>
                    <small class="opacity-75">Comprehensive income and expense analysis</small>
                </div>
                <a href="account.php" class="btn btn-light">
                    <i class="bi bi-arrow-left me-2"></i>Back To Finance Panel
                </a>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" id="reportForm">
                <div class="row g-3 align-items-end">
                    
                    <!-- View Toggle -->
                    <div class="col-auto">
                        <label class="form-label fw-bold small text-uppercase">View Type</label>
                        <div class="btn-group d-block" role="group">
                            <button type="button" class="btn btn-outline-primary view-btn <?= $viewType === 'monthly' ? 'active' : '' ?>" 
                                    onclick="setView('monthly')">
                                <i class="bi bi-calendar-month"></i> Monthly
                            </button>
                            <button type="button" class="btn btn-outline-primary view-btn <?= $viewType === 'yearly' ? 'active' : '' ?>" 
                                    onclick="setView('yearly')">
                                <i class="bi bi-calendar-range"></i> Yearly
                            </button>
                        </div>
                        <input type="hidden" name="view" id="viewInput" value="<?= htmlspecialchars($viewType) ?>">
                    </div>
                    
                    <!-- Year -->
                    <div class="col-md-2">
                        <label class="form-label fw-bold small text-uppercase">Year</label>
                        <select name="year" class="form-select">
                            <?php for ($y = 2020; $y <= 2030; $y++): ?>
                            <option value="<?= $y ?>" <?= $selectedYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <!-- Month (only for monthly) -->
                    <?php if ($viewType === 'monthly'): ?>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small text-uppercase">Month</label>
                        <select name="month" class="form-select">
                            <?php foreach ($months as $num => $name): ?>
                            <option value="<?= $num ?>" <?= $selectedMonth == $num ? 'selected' : '' ?>><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Generate Button -->
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-2"></i>Generate Report
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-muted mb-1 small text-uppercase fw-bold">💰 Total Income</p>
                            <h3 class="text-success mb-0"><?= formatCurrency($totalIncome) ?></h3>
                        </div>
                        <div class="bg-success bg-opacity-10 p-3 rounded h-100 d-flex align-items-center">
                            <i class="bi bi-cash-coin fs-2 text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-muted mb-1 small text-uppercase fw-bold">💳 Total Expenses</p>
                            <h3 class="text-danger mb-0"><?= formatCurrency($totalExpenses) ?></h3>
                        </div>
                        <div class="bg-danger bg-opacity-10 p-3 rounded h-100 d-flex align-items-center">
                            <i class="bi bi-credit-card fs-2 text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-muted mb-1 small text-uppercase fw-bold">💎 Net Margin</p>
                            <h3 class="<?= $totalMargin >= 0 ? 'text-info' : 'text-danger' ?> mb-0">
                                <?= formatCurrency($totalMargin) ?>
                            </h3>
                        </div>
                        <div class="bg-info bg-opacity-10 p-3 rounded h-100 d-flex align-items-center">
                            <i class="bi bi-wallet2 fs-2 text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($viewType === 'monthly'): ?>
    
    <!-- MONTHLY REPORT -->
    
    <h5 class="mb-3"><i class="bi bi-calendar-month text-primary"></i> Monthly Report - <?= $months[$selectedMonth] ?> <?= $selectedYear ?></h5>
    
    <div class="row">
        <!-- Monthly Expenses (Simple List) -->
        <div class="col-lg-4">
            <?php if (!empty($expenseData)): ?>
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h6 class="mb-0"><i class="bi bi-credit-card me-2"></i>Monthly Expenses</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenseData as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['category']) ?></td>
                                <td class="text-end text-expense"><?= formatCurrency($item['amount']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr class="fw-bold">
                                <td>Total</td>
                                <td class="text-end text-expense"><?= formatCurrency($totalExpenses) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Monthly Income Matrix (With Branches) -->
        <div class="col-lg-8">
            <?php if (!empty($incomeMatrix)): ?>
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="bi bi-cash-coin me-2"></i>Monthly Income by Branch</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Income Category</th>
                                <?php foreach ($branches as $branch): ?>
                                <th class="text-end"><?= htmlspecialchars($branch['name']) ?></th>
                                <?php endforeach; ?>
                                <th class="text-end">💰 Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($incomeMatrix as $row): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($row['category']) ?></td>
                                <?php foreach ($branches as $branch): ?>
                                <td class="text-end <?= $row['branches'][$branch['id']] > 0 ? 'text-income' : 'text-muted' ?>">
                                    <?= $row['branches'][$branch['id']] > 0 ? formatCurrency($row['branches'][$branch['id']]) : '-' ?>
                                </td>
                                <?php endforeach; ?>
                                <td class="text-end text-income fw-bold"><?= formatCurrency($row['total']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr class="fw-bold">
                                <td>Total</td>
                                <?php foreach ($branches as $branch): 
                                    $branchTotal = 0;
                                    foreach ($incomeMatrix as $row) {
                                        $branchTotal += $row['branches'][$branch['id']] ?? 0;
                                    }
                                ?>
                                <td class="text-end text-income"><?= formatCurrency($branchTotal) ?></td>
                                <?php endforeach; ?>
                                <td class="text-end text-income"><?= formatCurrency($totalIncome) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Summary Matrix -->
    <div class="card shadow-sm">
        <div class="card-header bg-info text-white">
            <h6 class="mb-0"><i class="bi bi-wallet2 me-2"></i>Summary Matrix</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Type</th>
                        <?php foreach ($summaryMatrix as $item): ?>
                        <th class="text-end"><?= htmlspecialchars($item['branch']) ?></th>
                        <?php endforeach; ?>
                        <th class="text-end">💰 Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="fw-bold">Income</td>
                        <?php foreach ($summaryMatrix as $item): ?>
                        <td class="text-end text-income"><?= formatCurrency($item['income']) ?></td>
                        <?php endforeach; ?>
                        <td class="text-end text-income fw-bold"><?= formatCurrency($totalIncome) ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Expenses</td>
                        <td colspan="<?= count($summaryMatrix) ?>" class="text-center text-muted small">
                            (Company-wide expenses - not allocated to branches)
                        </td>
                        <td class="text-end text-expense fw-bold"><?= formatCurrency($totalExpenses) ?></td>
                    </tr>
                    <tr class="table-warning">
                        <td class="fw-bold">Net Margin</td>
                        <td colspan="<?= count($summaryMatrix) ?>" class="text-center text-muted small fst-italic">
                            (Total Income - Total Expenses = Company Net Margin)
                        </td>
                        <td class="text-end fw-bold <?= $totalMargin >= 0 ? 'text-margin' : 'text-danger' ?>">
                            <?= formatCurrency($totalMargin) ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php else: ?>
    
    <!-- YEARLY REPORT -->
    
    <h5 class="mb-3"><i class="bi bi-calendar-range text-primary"></i> Yearly Report - <?= $selectedYear ?></h5>
    
    <div class="row">
        <!-- Monthly Income Totals (With Branches) -->
        <div class="col-lg-8">
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="bi bi-cash-coin me-2"></i>Monthly Income by Branch</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Month</th>
                                <?php foreach ($branches as $branch): ?>
                                <th class="text-end"><?= htmlspecialchars($branch['name']) ?></th>
                                <?php endforeach; ?>
                                <th class="text-end">💰 Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthlyIncomeData as $row): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($row['month']) ?></td>
                                <?php foreach ($branches as $branch): ?>
                                <td class="text-end text-income"><?= formatCurrency($row['branches'][$branch['id']] ?? 0) ?></td>
                                <?php endforeach; ?>
                                <td class="text-end text-income fw-bold"><?= formatCurrency($row['total']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr class="fw-bold">
                                <td>Yearly Total</td>
                                <?php foreach ($branches as $branch): 
                                    $branchYearlyIncome = 0;
                                    foreach ($monthlyIncomeData as $row) {
                                        $branchYearlyIncome += $row['branches'][$branch['id']] ?? 0;
                                    }
                                ?>
                                <td class="text-end text-income"><?= formatCurrency($branchYearlyIncome) ?></td>
                                <?php endforeach; ?>
                                <td class="text-end text-income"><?= formatCurrency($totalIncome) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Monthly Expense Totals (Simple) -->
        <div class="col-lg-4">
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h6 class="mb-0"><i class="bi bi-credit-card me-2"></i>Monthly Expenses</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthlyExpenseData as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['month']) ?></td>
                                <td class="text-end text-expense"><?= formatCurrency($row['amount']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr class="fw-bold">
                                <td>Yearly Total</td>
                                <td class="text-end text-expense"><?= formatCurrency($totalExpenses) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Category-Wise Annual Totals -->
    <div class="row">
        <div class="col-lg-8">
            <!-- Income Categories (With Branches) -->
            <?php if (!empty($incomeMatrix)): ?>
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="bi bi-tags me-2"></i>Income Categories (Annual)</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Category</th>
                                <?php foreach ($branches as $branch): ?>
                                <th class="text-end small"><?= htmlspecialchars($branch['name']) ?></th>
                                <?php endforeach; ?>
                                <th class="text-end">💰 Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($incomeMatrix as $row): ?>
                            <tr>
                                <td class="fw-semibold small"><?= htmlspecialchars($row['category']) ?></td>
                                <?php foreach ($branches as $branch): ?>
                                <td class="text-end text-income small"><?= formatCurrency($row['branches'][$branch['id']] ?? 0) ?></td>
                                <?php endforeach; ?>
                                <td class="text-end text-income fw-bold"><?= formatCurrency($row['total']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr class="fw-bold">
                                <td>Total</td>
                                <?php foreach ($branches as $branch): 
                                    $branchTotal = 0;
                                    foreach ($incomeMatrix as $row) {
                                        $branchTotal += $row['branches'][$branch['id']] ?? 0;
                                    }
                                ?>
                                <td class="text-end text-income"><?= formatCurrency($branchTotal) ?></td>
                                <?php endforeach; ?>
                                <td class="text-end text-income"><?= formatCurrency(array_sum(array_column($incomeMatrix, 'total'))) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-4">
            <!-- Expense Categories (Simple List) -->
            <?php if (!empty($expenseData)): ?>
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h6 class="mb-0"><i class="bi bi-tags me-2"></i>Expense Categories (Annual)</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenseData as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['category']) ?></td>
                                <td class="text-end text-expense"><?= formatCurrency($item['amount']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr class="fw-bold">
                                <td>Total</td>
                                <td class="text-end text-expense"><?= formatCurrency(array_sum(array_column($expenseData, 'amount'))) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Yearly Summary Matrix -->
    <div class="card shadow-sm">
        <div class="card-header bg-info text-white">
            <h6 class="mb-0"><i class="bi bi-wallet2 me-2"></i>Yearly Summary Matrix</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Type</th>
                        <?php foreach ($summaryMatrix as $item): ?>
                        <th class="text-end"><?= htmlspecialchars($item['branch']) ?></th>
                        <?php endforeach; ?>
                        <th class="text-end">💰 Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="fw-bold">Income</td>
                        <?php foreach ($summaryMatrix as $item): ?>
                        <td class="text-end text-income"><?= formatCurrency($item['income']) ?></td>
                        <?php endforeach; ?>
                        <td class="text-end text-income fw-bold"><?= formatCurrency($totalIncome) ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Expenses</td>
                        <td colspan="<?= count($summaryMatrix) ?>" class="text-center text-muted small">
                            (Company-wide expenses - not allocated to branches)
                        </td>
                        <td class="text-end text-expense fw-bold"><?= formatCurrency($totalExpenses) ?></td>
                    </tr>
                    <tr class="table-warning">
                        <td class="fw-bold">Net Margin</td>
                        <td colspan="<?= count($summaryMatrix) ?>" class="text-center text-muted small fst-italic">
                            Total Income - Total Expenses = Net Margin
                        </td>
                        <td class="text-end fw-bold <?= $totalMargin >= 0 ? 'text-margin' : 'text-danger' ?>">
                            <?= formatCurrency($totalMargin) ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php endif; ?>
    
    <?php if (empty($expenseData) && empty($incomeMatrix)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-inbox display-1 text-muted"></i>
            <h5 class="mt-3">No Data Available</h5>
            <p class="text-muted">No transactions found for the selected period</p>
        </div>
    </div>
    <?php endif; ?>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
function setView(view) {
    document.getElementById('viewInput').value = view;
    document.getElementById('reportForm').submit();
}

// Loading state on form submit
document.getElementById('reportForm').addEventListener('submit', function(e) {
    const btn = this.querySelector('button[type="submit"]');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
    btn.disabled = true;
});
</script>

</body>
</html>