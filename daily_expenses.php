<?php
/**
 * Simple Daily Expenses System for POS
 * Fixed: Prevents duplicate entries on page refresh using PRG pattern
 */


// Include database connection (before any HTML output)
require 'auth.php';
include 'mydb.php';

// Set timezone
date_default_timezone_set('Asia/Dhaka');

$selectedDate = date('Y-m-d'); // Default to today

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function validateExpense($details, $amount) {
    $errors = [];
    
    if (empty(trim($details))) {
        $errors[] = "Please enter expense details";
    }
    
    if (!is_numeric($amount) || $amount <= 0) {
        $errors[] = "Please enter a valid amount";
    }
    
    return $errors;
}

function formatCurrency($amount) {
    return '৳' . number_format($amount, 2);
}

function formatDate($date) {
    return date('l, M d, Y', strtotime($date));
}

function formatTime($datetime) {
    return date('h:i A', strtotime($datetime));
}

function getAdjacentDate($date, $direction = 'next') {
    $timestamp = strtotime($date);
    if ($direction === 'next') {
        return date('Y-m-d', strtotime('+1 day', $timestamp));
    } else {
        return date('Y-m-d', strtotime('-1 day', $timestamp));
    }
}

function redirectWithMessage($message, $type, $date = null) {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
    
    $url = 'daily_expenses.php';
    if ($date) {
        $url .= '?date=' . urlencode($date);
    }
    
    header('Location: ' . $url);
    exit;
}

// ============================================================================
// PROCESS FORM SUBMISSIONS (Must be before any HTML output)
// ============================================================================

// Handle adding new expense
if ($_POST && isset($_POST['add_expense'])) {
    $details = trim($_POST['details']);
    $amount = floatval($_POST['amount']);
    $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expenseDate)) {
        $expenseDate = date('Y-m-d');
    }
    
    // Validate input
    $errors = validateExpense($details, $amount);
    
    if (empty($errors)) {
        // Create full datetime string for the selected date with current time
        $expenseDateTime = $expenseDate . ' ' . date('H:i:s');
        
        // Insert into database with the specified date
        $sql = "INSERT INTO daily_expenses (details, amount, created_time) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sds", $details, $amount, $expenseDateTime);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                redirectWithMessage(
                    "Expense added successfully for " . formatDate($expenseDate) . "!",
                    "success",
                    $expenseDate
                );
            } else {
                mysqli_stmt_close($stmt);
                redirectWithMessage("Error adding expense. Please try again.", "danger");
            }
        } else {
            redirectWithMessage("Database error occurred.", "danger");
        }
    } else {
        redirectWithMessage(implode(". ", $errors), "warning");
    }
}

// Handle deleting expense
if ($_POST && isset($_POST['delete_expense'])) {
    $expenseId = intval($_POST['expense_id']);
    $returnDate = $_POST['selected_date'] ?? date('Y-m-d');
    
    if ($expenseId > 0) {
        $sql = "DELETE FROM daily_expenses WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $expenseId);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                redirectWithMessage("Expense deleted successfully!", "success", $returnDate);
            } else {
                mysqli_stmt_close($stmt);
                redirectWithMessage("Error deleting expense.", "danger", $returnDate);
            }
        }
    }
}

// Now include navbar (after POST processing)
include 'navbar.php';

// Get message from session and clear it
$message = $_SESSION['message'] ?? '';
$messageType = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// Get selected date for viewing
if (isset($_GET['date']) && !empty($_GET['date'])) {
    $selectedDate = $_GET['date'];
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

// Calculate previous and next dates
$prevDate = getAdjacentDate($selectedDate, 'prev');
$nextDate = getAdjacentDate($selectedDate, 'next');
$isToday = ($selectedDate === date('Y-m-d'));
$isFuture = (strtotime($selectedDate) > strtotime(date('Y-m-d')));

// ============================================================================
// FETCH EXPENSE DATA
// ============================================================================

// Get expenses for selected date
$expenses = [];
$totalAmount = 0;
$totalCount = 0;

$sql = "SELECT id, details, amount, created_time 
        FROM daily_expenses 
        WHERE DATE(created_time) = ? 
        ORDER BY created_time DESC";

$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $selectedDate);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $expenses[] = $row;
        $totalAmount += $row['amount'];
        $totalCount++;
    }
    
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Expenses - POS System</title>
    <link rel="icon" type="image/png" href="favicon.png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Custom Styles -->
    <style>
        body {
            background-color: #f8f9fa;
            padding-bottom: 3rem;
            font-size: 0.875rem;
        }
        
        .section-divider {
            border-top: 3px solid #dee2e6;
            margin: 2.5rem 0;
        }
        
        .section-header {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 1rem 1.25rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .section-header h4 {
            font-size: 1.1rem;
        }
        
        .form-section {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .table-section {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .date-navigation {
            background: white;
            padding: 0.875rem 1.25rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }
        
        .stats-badge {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            color: #0056b3;
            padding: 0.4rem 0.875rem;
            border-radius: 0.375rem;
            font-weight: 500;
            font-size: 0.8rem;
        }
        
        .btn-nav {
            min-width: 90px;
            font-size: 0.8rem;
            padding: 0.4rem 0.75rem;
        }
        
        .form-control, .form-select {
            font-size: 0.875rem;
            padding: 0.5rem 0.75rem;
        }
        
        .form-label {
            font-size: 0.8rem;
            margin-bottom: 0.375rem;
        }
        
        .btn {
            font-size: 0.875rem;
        }
        
        table {
            font-size: 0.8rem;
        }
        
        table th {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.625rem 0.75rem;
        }
        
        table td {
            padding: 0.625rem 0.75rem;
        }
        
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        
        .delete-btn:hover {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
            transform: translateY(-1px);
            transition: all 0.2s ease;
        }
        
        .date-badge {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            color: #0056b3;
            padding: 0.25rem 0.75rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
        }
        
        .date-picker-section {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .date-picker-section input {
            max-width: 150px;
        }
        
        .date-display h5 {
            font-size: 1rem;
        }
        
        @media (max-width: 768px) {
            body {
                padding-bottom: 4rem;
                font-size: 0.8rem;
            }
            
            .btn-nav {
                min-width: 75px;
                font-size: 0.75rem;
                padding: 0.375rem 0.5rem;
            }
            
            .section-header h4 {
                font-size: 1rem;
            }
            
            .date-picker-section {
                flex-direction: column;
                gap: 0.375rem;
            }
            
            .date-picker-section input {
                max-width: 100%;
            }
        }
    </style>
</head>

<body>
<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10">
            
            <!-- Alert Messages -->
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- ========================================= -->
            <!-- SECTION 1: ADD EXPENSES -->
            <!-- ========================================= -->
            
            <div class="section-header">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h4 class="mb-0">
                <i class="bi bi-plus-circle-fill me-2"></i>
                Add New Expense
            </h4>
        </div>
        <div class="col-md-6 text-md-end mt-2 mt-md-0">
            <a href="daily_expenses_list.php" class="btn btn-light btn-sm">
                <i class="bi bi-list-ul me-1"></i>
                View All Expenses
            </a>
        </div>
    </div>
</div>

            <div class="form-section mb-4">
                <form method="POST" action="" id="expenseForm">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="expense_date" class="form-label fw-semibold">
                                <i class="bi bi-calendar-event me-1"></i>
                                Expense Date
                            </label>
                            <input type="date" 
                                   class="form-control" 
                                   id="expense_date" 
                                   name="expense_date" 
                                   value="<?= date('Y-m-d') ?>"
                                   max="<?= date('Y-m-d') ?>"
                                   required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="details" class="form-label fw-semibold">
                                <i class="bi bi-text-left me-1"></i>
                                Details
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="details" 
                                   name="details" 
                                   placeholder="What did you spend on?"
                                   required>
                        </div>
                        
                        <div class="col-md-2 mb-3">
                            <label for="amount" class="form-label fw-semibold">
                                <i class="bi bi-currency-exchange me-1"></i>
                                Amount (৳)
                            </label>
                            <input type="number" 
                                   class="form-control" 
                                   id="amount" 
                                   name="amount" 
                                   step="0.01" 
                                   min="0.01"
                                   placeholder="0.00"
                                   required>
                        </div>
                        
                        <div class="col-md-2 mb-3">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" 
                                    name="add_expense" 
                                    class="btn btn-success w-100">
                                <i class="bi bi-plus-lg me-1"></i>
                                Add
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Section Divider -->
            <div class="section-divider"></div>

            <!-- ========================================= -->
            <!-- SECTION 2: VIEW EXPENSES BY DATE -->
            <!-- ========================================= -->
            
            <div class="section-header">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h4 class="mb-0">
                            <i class="bi bi-receipt-cutoff me-2"></i>
                            View Expenses
                        </h4>
                    </div>
                    <div class="col-md-6 text-md-end mt-2 mt-md-0">
                        <span class="stats-badge">
                            <i class="bi bi-list-check me-1"></i>
                            <?= $totalCount ?> Items • <?= formatCurrency($totalAmount) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Date Navigation -->
            <div class="date-navigation">
                <div class="row align-items-center g-2">
                    <div class="col-auto">
                        <a href="?date=<?= $prevDate ?>" class="btn btn-outline-primary btn-nav">
                            <i class="bi bi-chevron-left me-1"></i>
                            Previous
                        </a>
                    </div>
                    
                    <div class="col text-center date-display">
                        <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap">
                            <h5 class="mb-0 fw-bold text-primary">
                                <?= formatDate($selectedDate) ?>
                            </h5>
                            <?php if ($isToday): ?>
                                <span class="badge bg-success" style="font-size: 0.7rem;">Today</span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted" style="font-size: 0.75rem;">
                            <i class="bi bi-calendar3 me-1"></i>
                            <?= date('Y-m-d', strtotime($selectedDate)) ?>
                        </small>
                    </div>
                    
                    <div class="col-auto">
                        <?php if (!$isFuture): ?>
                        <a href="?date=<?= $nextDate ?>" class="btn btn-outline-primary btn-nav">
                            Next
                            <i class="bi bi-chevron-right ms-1"></i>
                        </a>
                        <?php else: ?>
                        <button class="btn btn-outline-secondary btn-nav" disabled>
                            Next
                            <i class="bi bi-chevron-right ms-1"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Date Picker and Quick Actions -->
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap">
                            <div class="date-picker-section">
                                <label class="mb-0 text-muted" style="font-size: 0.75rem;">
                                    <i class="bi bi-calendar-range me-1"></i>
                                    Jump to date:
                                </label>
                                <input type="date" 
                                       id="quickDatePicker" 
                                       class="form-control form-control-sm" 
                                       value="<?= htmlspecialchars($selectedDate) ?>"
                                       max="<?= date('Y-m-d') ?>">
                            </div>
                            
                            <?php if (!$isToday): ?>
                            <a href="daily_expenses.php" class="btn btn-sm btn-success">
                                <i class="bi bi-calendar-today me-1"></i>
                                Today
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Expenses Table -->
            <?php if (!empty($expenses)): ?>
            <div class="table-section">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="15%">Time</th>
                                <th width="50%">Details</th>
                                <th width="20%">Amount</th>
                                <th width="15%" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $expense): ?>
                            <tr>
                                <td>
                                    <i class="bi bi-clock text-muted me-1"></i>
                                    <strong><?= formatTime($expense['created_time']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($expense['details']) ?></td>
                                <td>
                                    <span class="text-danger fw-bold">
                                        <?= formatCurrency($expense['amount']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <form method="POST" action="" class="d-inline" 
                                          onsubmit="return confirmDelete('<?= htmlspecialchars($expense['details']) ?>')">
                                        <input type="hidden" name="expense_id" value="<?= $expense['id'] ?>">
                                        <input type="hidden" name="selected_date" value="<?= htmlspecialchars($selectedDate) ?>">
                                        <button type="submit" 
                                                name="delete_expense" 
                                                class="btn btn-sm btn-outline-danger delete-btn"
                                                title="Delete Expense">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="2" class="text-end">
                                    <i class="bi bi-calculator me-2"></i>
                                    Total:
                                </th>
                                <th class="text-danger fs-5"><?= formatCurrency($totalAmount) ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <?php else: ?>
            <!-- No Expenses Message -->
            <div class="table-section text-center py-5">
                <i class="bi bi-inbox text-muted" style="font-size: 4rem; opacity: 0.5;"></i>
                <h5 class="mt-3 text-muted">No expenses found for this date</h5>
                <p class="text-muted mb-3">There are no recorded expenses for <?= formatDate($selectedDate) ?>.</p>
                <?php if (!$isToday): ?>
                <a href="daily_expenses.php" class="btn btn-primary">
                    <i class="bi bi-calendar-today me-1"></i>
                    View Today's Expenses
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Bottom spacing -->
<div class="pb-4"></div>

<!-- Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.js"></script>

<!-- Custom JavaScript -->
<script>
function confirmDelete(expenseDetails) {
    const truncatedDetails = expenseDetails.length > 30 
        ? expenseDetails.substring(0, 30) + '...' 
        : expenseDetails;
    
    return confirm(`Are you sure you want to delete this expense?\n\n"${truncatedDetails}"\n\nThis action cannot be undone.`);
}

document.addEventListener('DOMContentLoaded', function() {
    const detailsField = document.getElementById('details');
    
    // Focus on details field for quick entry
    if (detailsField) {
        detailsField.focus();
    }
    
    // Allow Enter key to submit form when in amount field
    const amountField = document.getElementById('amount');
    if (amountField) {
        amountField.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('expenseForm').submit();
            }
        });
    }
    
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const closeBtn = alert.querySelector('.btn-close');
            if (closeBtn) {
                closeBtn.click();
            }
        }, 5000);
    });
    
    // Keyboard navigation for Previous/Next
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey || e.metaKey) {
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                window.location.href = '?date=<?= $prevDate ?>';
            } else if (e.key === 'ArrowRight' && !<?= $isFuture ? 'true' : 'false' ?>) {
                e.preventDefault();
                window.location.href = '?date=<?= $nextDate ?>';
            }
        }
    });
    
    // Date picker quick navigation
    const quickDatePicker = document.getElementById('quickDatePicker');
    if (quickDatePicker) {
        quickDatePicker.addEventListener('change', function() {
            if (this.value) {
                window.location.href = '?date=' + this.value;
            }
        });
    }
});
</script>

</body>
</html>