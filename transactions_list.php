<?php
/**
 * All Transactions - Income & Expenses Details
 * Complete transaction history with filters
 */

// Include files
require 'auth.php';
include 'mydb.php';
include 'navbar.php';

// Set timezone
date_default_timezone_set('Asia/Dhaka');

// Get filter parameters
$filterType = $_GET['type'] ?? 'all'; // all, income, expenses
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Fetch branches for filter
$branches = [];
$result = mysqli_query($conn, "SELECT id, name FROM branches ORDER BY name");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $branches[] = $row;
    }
}

// Fetch categories for filter
$incomeCategories = [];
$result = mysqli_query($conn, "SELECT id, name FROM income_categories ORDER BY name");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $incomeCategories[] = $row;
    }
}

$expenseCategories = [];
$result = mysqli_query($conn, "SELECT id, name FROM expense_categories ORDER BY name");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $expenseCategories[] = $row;
    }
}

// Build the unified query
$transactions = [];
$totalRecords = 0;

// UNION query to combine income and expenses
$unionQuery = "";

// Income query
if ($filterType === 'all' || $filterType === 'income') {
    $incomeQuery = "
        SELECT 
            bi.id,
            'income' as type,
            bi.created_at as date,
            ic.name as category,
            b.name as branch,
            bi.income as amount,
            bi.notes
        FROM branch_income bi
        LEFT JOIN income_categories ic ON bi.category_id = ic.id
        LEFT JOIN branches b ON bi.branch_id = b.id
        WHERE 1=1
    ";
    
    if (!empty($filterDateFrom)) {
        $incomeQuery .= " AND DATE(bi.created_at) >= '" . mysqli_real_escape_string($conn, $filterDateFrom) . "'";
    }
    
    if (!empty($filterDateTo)) {
        $incomeQuery .= " AND DATE(bi.created_at) <= '" . mysqli_real_escape_string($conn, $filterDateTo) . "'";
    }
    
    $unionQuery .= $incomeQuery;
}

// Expense query
if ($filterType === 'all' || $filterType === 'expenses') {
    $expenseQuery = "
        SELECT 
            be.id,
            'expense' as type,
            be.created_at as date,
            ec.name as category,
            'N/A' as branch,
            be.expense as amount,
            be.notes
        FROM branch_expenses be
        LEFT JOIN expense_categories ec ON be.category_id = ec.id
        WHERE 1=1
    ";
    
    if (!empty($filterDateFrom)) {
        $expenseQuery .= " AND DATE(be.created_at) >= '" . mysqli_real_escape_string($conn, $filterDateFrom) . "'";
    }
    
    if (!empty($filterDateTo)) {
        $expenseQuery .= " AND DATE(be.created_at) <= '" . mysqli_real_escape_string($conn, $filterDateTo) . "'";
    }
    
    if (!empty($unionQuery)) {
        $unionQuery .= " UNION ALL ";
    }
    $unionQuery .= $expenseQuery;
}

// Complete query with ordering and pagination
if (!empty($unionQuery)) {
    $finalQuery = "SELECT * FROM (" . $unionQuery . ") as transactions ORDER BY date DESC, type ASC LIMIT $perPage OFFSET $offset";
    
    $result = mysqli_query($conn, $finalQuery);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $transactions[] = $row;
        }
    }
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM (" . $unionQuery . ") as transactions";
    $countResult = mysqli_query($conn, $countQuery);
    if ($countResult) {
        $totalRecords = mysqli_fetch_assoc($countResult)['total'];
    }
}

$totalPages = ceil($totalRecords / $perPage);

// Calculate totals using separate queries (not from paginated results)
$totalIncome = 0;
$totalExpenses = 0;

// Calculate total income based on filters
if ($filterType === 'all' || $filterType === 'income') {
    $incomeQuery = "SELECT COALESCE(SUM(income), 0) as total FROM branch_income WHERE 1=1";
    
    if (!empty($filterDateFrom)) {
        $incomeQuery .= " AND DATE(created_at) >= '" . mysqli_real_escape_string($conn, $filterDateFrom) . "'";
    }
    
    if (!empty($filterDateTo)) {
        $incomeQuery .= " AND DATE(created_at) <= '" . mysqli_real_escape_string($conn, $filterDateTo) . "'";
    }
    
    $result = mysqli_query($conn, $incomeQuery);
    if ($result) {
        $totalIncome = mysqli_fetch_assoc($result)['total'];
    }
}

// Calculate total expenses based on filters
if ($filterType === 'all' || $filterType === 'expenses') {
    $expenseQuery = "SELECT COALESCE(SUM(expense), 0) as total FROM branch_expenses WHERE 1=1";
    
    if (!empty($filterDateFrom)) {
        $expenseQuery .= " AND DATE(created_at) >= '" . mysqli_real_escape_string($conn, $filterDateFrom) . "'";
    }
    
    if (!empty($filterDateTo)) {
        $expenseQuery .= " AND DATE(created_at) <= '" . mysqli_real_escape_string($conn, $filterDateTo) . "'";
    }
    
    $result = mysqli_query($conn, $expenseQuery);
    if ($result) {
        $totalExpenses = mysqli_fetch_assoc($result)['total'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Transactions - Accounting System</title>
    <link rel="icon" type="image/png" href="favicon.png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        body { background: #f8f9fa; }
        
        .transaction-income {
            background: #d4edda;
            border-left: 4px solid #28a745;
        }
        
        .transaction-expense {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        
        .badge-income {
            background: #28a745;
            color: white;
        }
        
        .badge-expense {
            background: #dc3545;
            color: white;
        }
        
        .amount-income {
            color: #28a745;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .amount-expense {
            color: #dc3545;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .table-hover tbody tr:hover {
            transform: translateX(3px);
            transition: all 0.2s;
        }
        
        .filter-card {
            position: sticky;
            top: 0;
            z-index: 100;
            background: white;
        }
    </style>
</head>

<body>
<div class="container py-4">
    
    <!-- Header -->
    <div class="card bg-gradient bg-primary text-white mb-4 shadow">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">
                        <i class="bi bi-list-ul me-2"></i>All Transactions
                    </h4>
                    <p class="mb-0 opacity-75 small">Complete history of income and expenses</p>
                </div>
                <a href="account.php" class="btn btn-light">
                    <i class="bi bi-arrow-left me-1"></i>Back to Finance
                </a>
            </div>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="row g-2 mb-3">
        <div class="col-lg-4 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2 px-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted d-block">Total Income</small>
                            <h4 class="mb-0 text-success">৳<?= number_format($totalIncome, 2) ?></h4>
                        </div>
                        <i class="bi bi-arrow-down-circle-fill text-success fs-2"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2 px-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted d-block">Total Expenses</small>
                            <h4 class="mb-0 text-danger">৳<?= number_format($totalExpenses, 2) ?></h4>
                        </div>
                        <i class="bi bi-arrow-up-circle-fill text-danger fs-2"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4 col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2 px-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted d-block">Net Balance</small>
                            <h4 class="mb-0 <?= ($totalIncome - $totalExpenses) >= 0 ? 'text-info' : 'text-danger' ?>">
                                ৳<?= number_format($totalIncome - $totalExpenses, 2) ?>
                            </h4>
                        </div>
                        <i class="bi bi-wallet2 text-info fs-2"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card shadow-sm mb-4 filter-card">
        <div class="card-body py-3">
            <form method="GET" id="filterForm">
                <div class="row g-2 align-items-end">
                    
                    <!-- Type Filter -->
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <label class="form-label small fw-bold mb-1">Type</label>
                        <select name="type" class="form-select form-select-sm">
                            <option value="all" <?= $filterType === 'all' ? 'selected' : '' ?>>All Types</option>
                            <option value="income" <?= $filterType === 'income' ? 'selected' : '' ?>>Income</option>
                            <option value="expenses" <?= $filterType === 'expenses' ? 'selected' : '' ?>>Expenses</option>
                        </select>
                    </div>
                    
                    <!-- Date From -->
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <label class="form-label small fw-bold mb-1">Date From</label>
                        <input type="date" name="date_from" class="form-control form-control-sm" 
                               value="<?= htmlspecialchars($filterDateFrom) ?>">
                    </div>
                    
                    <!-- Date To -->
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <label class="form-label small fw-bold mb-1">Date To</label>
                        <input type="date" name="date_to" class="form-control form-control-sm" 
                               value="<?= htmlspecialchars($filterDateTo) ?>">
                    </div>
                    
                    <!-- Buttons -->
                    <div class="col-lg-3 col-md-3 col-sm-6">
                        <button type="submit" class="btn btn-primary btn-sm me-1">
                            <i class="bi bi-search me-1"></i>Apply
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="resetFilters()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Reset
                        </button>
                    </div>
                    
                    <!-- Summary Info -->
                    <div class="col-lg-3 col-md-12 text-lg-end text-md-start mt-md-2 mt-lg-0">
                        <small class="text-muted d-block">Total Records: <strong><?= number_format($totalRecords) ?></strong></small>
                        <small class="text-muted">Page <?= $page ?> of <?= max(1, $totalPages) ?></small>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Transactions Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-white border-bottom py-2">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="bi bi-table me-2"></i>Transaction Details
                </h6>
                <div class="d-flex gap-3">
                    <small class="text-success">
                        <i class="bi bi-arrow-down-circle-fill me-1"></i>
                        Income: <strong>৳<?= number_format($totalIncome, 2) ?></strong>
                    </small>
                    <small class="text-danger">
                        <i class="bi bi-arrow-up-circle-fill me-1"></i>
                        Expenses: <strong>৳<?= number_format($totalExpenses, 2) ?></strong>
                    </small>
                    <small class="<?= ($totalIncome - $totalExpenses) >= 0 ? 'text-info' : 'text-danger' ?>">
                        <i class="bi bi-wallet2 me-1"></i>
                        Net: <strong>৳<?= number_format($totalIncome - $totalExpenses, 2) ?></strong>
                    </small>
                </div>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th style="width: 100px;">Type</th>
                        <th style="width: 150px;">Date</th>
                        <th style="width: 180px;">Category</th>
                        <th style="width: 120px;">Branch</th>
                        <th class="text-end" style="width: 150px;">Amount</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <i class="bi bi-inbox display-4 text-muted"></i>
                            <p class="text-muted mt-3 mb-0">No transactions found</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $transaction): ?>
                        <tr class="transaction-<?= $transaction['type'] ?>">
                            <td class="fw-bold">#<?= $transaction['id'] ?></td>
                            <td>
                                <span class="badge badge-<?= $transaction['type'] ?>">
                                    <?php if ($transaction['type'] === 'income'): ?>
                                        <i class="bi bi-arrow-down-circle me-1"></i>Income
                                    <?php else: ?>
                                        <i class="bi bi-arrow-up-circle me-1"></i>Expense
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?= date('d M Y', strtotime($transaction['date'])) ?><br>
                                    <?= date('h:i A', strtotime($transaction['date'])) ?>
                                </small>
                            </td>
                            <td>
                                <span class="fw-semibold"><?= htmlspecialchars($transaction['category']) ?></span>
                            </td>
                            <td>
                                <?php if ($transaction['branch'] !== 'N/A'): ?>
                                    <small><i class="bi bi-building me-1"></i><?= htmlspecialchars($transaction['branch']) ?></small>
                                <?php else: ?>
                                    <small class="text-muted fst-italic">Company-wide</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <span class="amount-<?= $transaction['type'] ?>">
                                    ৳<?= number_format($transaction['amount'], 2) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($transaction['notes'])): ?>
                                    <span class="text-muted"><?= htmlspecialchars($transaction['notes']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">No notes</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white border-top">
            <nav>
                <ul class="pagination pagination-sm mb-0 justify-content-center">
                    
                    <!-- Previous -->
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    
                    <!-- Page Numbers -->
                    <?php 
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    if ($startPage > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
                        </li>
                        <?php if ($startPage > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>">
                                <?= $totalPages ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Next -->
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Reset filters function
function resetFilters() {
    window.location.href = 'transactions_list.php';
}

// Loading state on filter submit
document.getElementById('filterForm').addEventListener('submit', function() {
    const btn = this.querySelector('button[type="submit"]');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Loading...';
    btn.disabled = true;
});
</script>

</body>
</html>