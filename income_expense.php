<?php
/**
 * Income & Expense Entry System - Fixed
 * Income requires branch, Expense does not
 */

// Include files
require 'auth.php';
include 'mydb.php';

// Set timezone
date_default_timezone_set('Asia/Dhaka');

// Variables for messages and form state
$message = '';
$messageType = '';
$success = false;

// ============================================================================
// PROCESS FORM SUBMISSIONS
// ============================================================================

// Add new income
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_income'])) {
    $branch_id = intval($_POST['branch_id'] ?? 0);
    $category_id = intval($_POST['income_category_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $transaction_date = $_POST['transaction_date'] ?? date('Y-m-d');
    
    // Validate
    if ($branch_id <= 0) {
        $message = "Please select a branch!";
        $messageType = "warning";
    } elseif ($category_id <= 0) {
        $message = "Please select an income category!";
        $messageType = "warning";
    } elseif ($amount <= 0) {
        $message = "Income amount must be greater than zero!";
        $messageType = "warning";
    } else {
        // Validate and format date
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $transaction_date)) {
            $transaction_date = date('Y-m-d');
        }
        
        // Create full datetime
        $transactionDateTime = $transaction_date . ' ' . date('H:i:s');
        
        // Insert income
        $sql = "INSERT INTO branch_income (branch_id, category_id, income, notes, created_at) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iidss", $branch_id, $category_id, $amount, $notes, $transactionDateTime);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "✓ Income of ৳" . number_format($amount, 2) . " recorded successfully!";
                $messageType = "success";
                $success = true;
            } else {
                $message = "⚠ Database Error: " . mysqli_stmt_error($stmt);
                $messageType = "danger";
            }
            mysqli_stmt_close($stmt);
        } else {
            $message = "⚠ Failed to prepare statement: " . mysqli_error($conn);
            $messageType = "danger";
        }
    }
}

// Add new expense (NO BRANCH REQUIRED)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $category_id = intval($_POST['expense_category_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $transaction_date = $_POST['transaction_date'] ?? date('Y-m-d');
    
    // Validate
    if ($category_id <= 0) {
        $message = "Please select an expense category!";
        $messageType = "warning";
    } elseif ($amount <= 0) {
        $message = "Expense amount must be greater than zero!";
        $messageType = "warning";
    } else {
        // Validate and format date
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $transaction_date)) {
            $transaction_date = date('Y-m-d');
        }
        
        // Create full datetime
        $transactionDateTime = $transaction_date . ' ' . date('H:i:s');
        
        // Insert expense (no branch_id)
        $sql = "INSERT INTO branch_expenses (category_id, expense, notes, created_at) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "idss", $category_id, $amount, $notes, $transactionDateTime);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "✓ Expense of ৳" . number_format($amount, 2) . " recorded successfully!";
                $messageType = "success";
                $success = true;
            } else {
                $message = "⚠ Database Error: " . mysqli_stmt_error($stmt);
                $messageType = "danger";
            }
            mysqli_stmt_close($stmt);
        } else {
            $message = "⚠ Failed to prepare statement: " . mysqli_error($conn);
            $messageType = "danger";
        }
    }
}

// Include navbar after processing
include 'navbar.php';

// Fetch all branches for dropdown (only needed for income)
$branches = [];
$result = mysqli_query($conn, "SELECT id, name FROM branches ORDER BY name");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $branches[] = $row;
    }
}

// Fetch all income categories
$incomeCategories = [];
$result = mysqli_query($conn, "SELECT id, name, description FROM income_categories ORDER BY name");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $incomeCategories[] = $row;
    }
}

// Fetch all expense categories
$expenseCategories = [];
$result = mysqli_query($conn, "SELECT id, name, description FROM expense_categories ORDER BY name");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $expenseCategories[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Income & Expense Entry - Accounting System</title>
    <link rel="icon" type="image/png" href="favicon.png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        body { background: #f8f9fa; padding-bottom: 3rem; }
        
        .entry-toggle-btn {
            padding: 1.5rem;
            border: 3px solid #dee2e6;
            border-radius: 0.75rem;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        
        .entry-toggle-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .entry-toggle-btn.active {
            border-color: #28a745;
            background: #d4edda;
        }
        
        .entry-toggle-btn.expense-active {
            border-color: #dc3545;
            background: #f8d7da;
        }
        
        .entry-toggle-btn .icon { font-size: 3rem; margin-bottom: 0.5rem; }
        .entry-toggle-btn.active .icon { color: #28a745; }
        .entry-toggle-btn.expense-active .icon { color: #dc3545; }
        
        .entry-form {
            display: none;
            animation: fadeIn 0.3s ease-in;
        }
        
        .entry-form.active { display: block; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>

<body>
<div class="container py-4" style="max-width: 900px;">
    
    <!-- Page Header -->
    <div class="card bg-success text-white mb-4 shadow">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="mb-1">
                        <i class="bi bi-cash-coin me-2"></i>
                        Income & Expense Entry
                    </h4>
                    <p class="mb-0 opacity-75 small">Quick entry for daily transactions</p>
                </div>
                <div class="col-md-4 text-md-end mt-2 mt-md-0">
                    <a href="account.php" class="btn btn-light btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Back to Finance Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Alert Messages -->
    <?php if (!empty($message)): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
        <div class="d-flex align-items-start">
            <div class="me-3 fs-4">
                <?php if ($messageType === 'success'): ?>
                    <i class="bi bi-check-circle-fill"></i>
                <?php elseif ($messageType === 'danger'): ?>
                    <i class="bi bi-x-circle-fill"></i>
                <?php else: ?>
                    <i class="bi bi-exclamation-triangle-fill"></i>
                <?php endif; ?>
            </div>
            <div class="flex-grow-1"><?= $message ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Check prerequisites -->
    <?php if (empty($incomeCategories) && empty($expenseCategories)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>No categories found!</strong> Please <a href="branches.php" class="alert-link">add categories</a> first.
    </div>
    <?php else: ?>
    
    <!-- Entry Forms -->
    <div class="card shadow">
        <div class="card-body p-4">
            
            <!-- Toggle Buttons -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="entry-toggle-btn active" id="incomeToggle" onclick="switchToIncome()">
                        <div class="icon"><i class="bi bi-arrow-down-circle-fill"></i></div>
                        <h5 class="mb-0">Add Income</h5>
                        <small class="text-muted">Record revenue by branch</small>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="entry-toggle-btn" id="expenseToggle" onclick="switchToExpense()">
                        <div class="icon"><i class="bi bi-arrow-up-circle-fill"></i></div>
                        <h5 class="mb-0">Add Expense</h5>
                        <small class="text-muted">Record general spending</small>
                    </div>
                </div>
            </div>
            
            <!-- Income Form -->
            <div class="entry-form active" id="incomeForm">
                <?php if (empty($branches)): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    No branches found. Please <a href="branches.php">add a branch</a> first before recording income.
                </div>
                <?php elseif (empty($incomeCategories)): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    No income categories found. Please <a href="branches.php">add income categories</a> first.
                </div>
                <?php else: ?>
                <form method="POST" action="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                <i class="bi bi-calendar-event me-1"></i>Transaction Date
                            </label>
                            <input type="date" class="form-control" name="transaction_date" 
                                   value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                <i class="bi bi-building me-1"></i>Branch <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" name="branch_id" required>
                                <option value="">Choose branch...</option>
                                <?php foreach ($branches as $branch): ?>
                                <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                <i class="bi bi-tag me-1"></i>Income Category <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" name="income_category_id" required>
                                <option value="">Select category...</option>
                                <?php foreach ($incomeCategories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                <i class="bi bi-cash-stack me-1"></i>Amount (৳) <span class="text-danger">*</span>
                            </label>
                            <input type="number" class="form-control" name="amount" 
                                   step="0.01" min="0.01" placeholder="0.00" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-bold">
                                <i class="bi bi-journal-text me-1"></i>Notes (Optional)
                            </label>
                            <textarea class="form-control" name="notes" rows="2" 
                                      placeholder="Add additional notes..." maxlength="500"></textarea>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" name="add_income" class="btn btn-success btn-lg px-5">
                            <i class="bi bi-check-lg me-2"></i>Record Income
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
            
            <!-- Expense Form (NO BRANCH FIELD) -->
            <div class="entry-form" id="expenseForm">
                <?php if (empty($expenseCategories)): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    No expense categories found. Please <a href="branches.php">add expense categories</a> first.
                </div>
                <?php else: ?>
                <form method="POST" action="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                <i class="bi bi-calendar-event me-1"></i>Transaction Date
                            </label>
                            <input type="date" class="form-control" name="transaction_date" 
                                   value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                <i class="bi bi-tag me-1"></i>Expense Category <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" name="expense_category_id" required>
                                <option value="">Select category...</option>
                                <?php foreach ($expenseCategories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                <i class="bi bi-cash-stack me-1"></i>Amount (৳) <span class="text-danger">*</span>
                            </label>
                            <input type="number" class="form-control" name="amount" 
                                   step="0.01" min="0.01" placeholder="0.00" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-bold">
                                <i class="bi bi-journal-text me-1"></i>Notes (Optional)
                            </label>
                            <textarea class="form-control" name="notes" rows="2" 
                                      placeholder="Add additional notes..." maxlength="500"></textarea>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" name="add_expense" class="btn btn-danger btn-lg px-5">
                            <i class="bi bi-check-lg me-2"></i>Record Expense
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
            
        </div>
    </div>
    
    <?php endif; ?>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
function switchToIncome() {
    document.getElementById('incomeToggle').classList.add('active');
    document.getElementById('incomeToggle').classList.remove('expense-active');
    document.getElementById('expenseToggle').classList.remove('active', 'expense-active');
    document.getElementById('incomeForm').classList.add('active');
    document.getElementById('expenseForm').classList.remove('active');
}

function switchToExpense() {
    document.getElementById('expenseToggle').classList.add('expense-active');
    document.getElementById('expenseToggle').classList.remove('active');
    document.getElementById('incomeToggle').classList.remove('active', 'expense-active');
    document.getElementById('expenseForm').classList.add('active');
    document.getElementById('incomeForm').classList.remove('active');
}

// Auto-dismiss success alerts
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert-success');
    alerts.forEach(alert => {
        setTimeout(() => {
            const closeBtn = alert.querySelector('.btn-close');
            if (closeBtn) closeBtn.click();
        }, 5000);
    });
    
    <?php if ($success): ?>
    // Reset forms on success
    document.querySelectorAll('input[type="number"], textarea').forEach(el => el.value = '');
    document.querySelectorAll('select').forEach(el => el.selectedIndex = 0);
    <?php endif; ?>
});
</script>

</body>
</html>