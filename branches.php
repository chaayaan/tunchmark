<?php
/**
 * Branch, Income & Expense Category Management System
 * Add, Edit, Delete, and View Branches, Income Categories, and Expense Categories
 */
// Include files
require 'auth.php';
include 'mydb.php';


if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}



// Set timezone
date_default_timezone_set('Asia/Dhaka');

// Helper functions
function redirectWithMessage($message, $type) {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
    header('Location: branches.php');
    exit;
}

// ============================================================================
// PROCESS BRANCH FORM SUBMISSIONS
// ============================================================================

// Add new branch
if ($_POST && isset($_POST['add_branch'])) {
    $branch_name = trim($_POST['branch_name']);
    
    if (empty($branch_name)) {
        redirectWithMessage("Branch name cannot be empty!", "warning");
    }
    
    $check_sql = "SELECT id FROM branches WHERE name = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "s", $branch_name);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        mysqli_stmt_close($check_stmt);
        redirectWithMessage("Branch with this name already exists!", "warning");
    }
    mysqli_stmt_close($check_stmt);
    
    $sql = "INSERT INTO branches (name) VALUES (?)";
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $branch_name);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            redirectWithMessage("✓ Branch '<strong>" . htmlspecialchars($branch_name) . "</strong>' has been successfully added!", "success");
        } else {
            mysqli_stmt_close($stmt);
            redirectWithMessage("⚠ Error adding branch. Please try again.", "danger");
        }
    }
}

// Update branch
if ($_POST && isset($_POST['update_branch'])) {
    $branch_id = intval($_POST['branch_id']);
    $branch_name = trim($_POST['branch_name']);
    $old_name = trim($_POST['old_branch_name']);
    
    if (empty($branch_name)) {
        redirectWithMessage("Branch name cannot be empty!", "warning");
    }
    
    $check_sql = "SELECT id FROM branches WHERE name = ? AND id != ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "si", $branch_name, $branch_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        mysqli_stmt_close($check_stmt);
        redirectWithMessage("⚠ Another branch with this name already exists!", "warning");
    }
    mysqli_stmt_close($check_stmt);
    
    $sql = "UPDATE branches SET name = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "si", $branch_name, $branch_id);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            redirectWithMessage("✓ Branch updated from '<strong>" . htmlspecialchars($old_name) . "</strong>' to '<strong>" . htmlspecialchars($branch_name) . "</strong>'", "success");
        } else {
            mysqli_stmt_close($stmt);
            redirectWithMessage("⚠ Error updating branch.", "danger");
        }
    }
}

// Delete branch
if ($_POST && isset($_POST['delete_branch'])) {
    $branch_id = intval($_POST['branch_id']);
    $branch_name = trim($_POST['branch_name']);
    
    if ($branch_id > 0) {
        $sql = "DELETE FROM branches WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $branch_id);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                redirectWithMessage("✓ Branch '<strong>" . htmlspecialchars($branch_name) . "</strong>' deleted!", "success");
            } else {
                mysqli_stmt_close($stmt);
                redirectWithMessage("⚠ Error deleting branch.", "danger");
            }
        }
    }
}

// ============================================================================
// PROCESS EXPENSE CATEGORY FORM SUBMISSIONS
// ============================================================================

// Add new expense category
if ($_POST && isset($_POST['add_expense_category'])) {
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    
    if (empty($category)) {
        redirectWithMessage("Category name cannot be empty!", "warning");
    }
    
    $check_sql = "SELECT id FROM expense_categories WHERE name = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "s", $category);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        mysqli_stmt_close($check_stmt);
        redirectWithMessage("Expense category with this name already exists!", "warning");
    }
    mysqli_stmt_close($check_stmt);
    
    $sql = "INSERT INTO expense_categories (name, description) VALUES (?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $category, $description);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            redirectWithMessage("✓ Expense category '<strong>" . htmlspecialchars($category) . "</strong>' has been successfully added!", "success");
        } else {
            mysqli_stmt_close($stmt);
            redirectWithMessage("⚠ Error adding expense category. Please try again.", "danger");
        }
    }
}

// Update expense category
if ($_POST && isset($_POST['update_expense_category'])) {
    $category_id = intval($_POST['category_id']);
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    $old_name = trim($_POST['old_category_name']);
    
    if (empty($category)) {
        redirectWithMessage("Category name cannot be empty!", "warning");
    }
    
    $check_sql = "SELECT id FROM expense_categories WHERE name = ? AND id != ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "si", $category, $category_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        mysqli_stmt_close($check_stmt);
        redirectWithMessage("⚠ Another expense category with this name already exists!", "warning");
    }
    mysqli_stmt_close($check_stmt);
    
    $sql = "UPDATE expense_categories SET name = ?, description = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssi", $category, $description, $category_id);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            redirectWithMessage("✓ Expense category updated from '<strong>" . htmlspecialchars($old_name) . "</strong>' to '<strong>" . htmlspecialchars($category) . "</strong>'", "success");
        } else {
            mysqli_stmt_close($stmt);
            redirectWithMessage("⚠ Error updating expense category.", "danger");
        }
    }
}

// Delete expense category
if ($_POST && isset($_POST['delete_expense_category'])) {
    $category_id = intval($_POST['category_id']);
    $category_name = trim($_POST['category_name']);
    
    if ($category_id > 0) {
        $sql = "DELETE FROM expense_categories WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $category_id);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                redirectWithMessage("✓ Expense category '<strong>" . htmlspecialchars($category_name) . "</strong>' deleted!", "success");
            } else {
                mysqli_stmt_close($stmt);
                redirectWithMessage("⚠ Error deleting expense category.", "danger");
            }
        }
    }
}

// ============================================================================
// PROCESS INCOME CATEGORY FORM SUBMISSIONS
// ============================================================================

// Add new income category
if ($_POST && isset($_POST['add_income_category'])) {
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    
    if (empty($category)) {
        redirectWithMessage("Category name cannot be empty!", "warning");
    }
    
    $check_sql = "SELECT id FROM income_categories WHERE name = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "s", $category);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        mysqli_stmt_close($check_stmt);
        redirectWithMessage("Income category with this name already exists!", "warning");
    }
    mysqli_stmt_close($check_stmt);
    
    $sql = "INSERT INTO income_categories (name, description) VALUES (?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $category, $description);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            redirectWithMessage("✓ Income category '<strong>" . htmlspecialchars($category) . "</strong>' has been successfully added!", "success");
        } else {
            mysqli_stmt_close($stmt);
            redirectWithMessage("⚠ Error adding income category. Please try again.", "danger");
        }
    }
}

// Update income category
if ($_POST && isset($_POST['update_income_category'])) {
    $category_id = intval($_POST['category_id']);
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    $old_name = trim($_POST['old_category_name']);
    
    if (empty($category)) {
        redirectWithMessage("Category name cannot be empty!", "warning");
    }
    
    $check_sql = "SELECT id FROM income_categories WHERE name = ? AND id != ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "si", $category, $category_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        mysqli_stmt_close($check_stmt);
        redirectWithMessage("⚠ Another income category with this name already exists!", "warning");
    }
    mysqli_stmt_close($check_stmt);
    
    $sql = "UPDATE income_categories SET name = ?, description = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssi", $category, $description, $category_id);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            redirectWithMessage("✓ Income category updated from '<strong>" . htmlspecialchars($old_name) . "</strong>' to '<strong>" . htmlspecialchars($category) . "</strong>'", "success");
        } else {
            mysqli_stmt_close($stmt);
            redirectWithMessage("⚠ Error updating income category.", "danger");
        }
    }
}

// Delete income category
if ($_POST && isset($_POST['delete_income_category'])) {
    $category_id = intval($_POST['category_id']);
    $category_name = trim($_POST['category_name']);
    
    if ($category_id > 0) {
        $sql = "DELETE FROM income_categories WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $category_id);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                redirectWithMessage("✓ Income category '<strong>" . htmlspecialchars($category_name) . "</strong>' deleted!", "success");
            } else {
                mysqli_stmt_close($stmt);
                redirectWithMessage("⚠ Error deleting income category.", "danger");
            }
        }
    }
}

// Include navbar after processing
include 'navbar.php';

// Get message from session
$message = $_SESSION['message'] ?? '';
$messageType = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// Fetch all branches
$branches = [];
$sql = "SELECT * FROM branches ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $branches[] = $row;
    }
}

// Fetch all expense categories
$expenseCategories = [];
$sql = "SELECT * FROM expense_categories ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $expenseCategories[] = $row;
    }
}

// Fetch all income categories
$incomeCategories = [];
$sql = "SELECT * FROM income_categories ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $incomeCategories[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branch & Category Management - Accounting System</title>
    <link rel="icon" type="image/png" href="favicon.png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        body {
            background-color: #f8f9fa;
            padding-bottom: 3rem;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .form-section {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 2rem;
        }
        
        .table-section {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 3rem;
        }
        
        .branch-card, .category-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }
        
        .branch-card:hover, .category-card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            transform: translateY(-3px);
        }
        
        .alert {
            border-left: 4px solid;
            animation: slideInDown 0.5s ease-out;
        }
        
        .alert-success {
            background-color: #d1f2eb;
            border-left-color: #28a745;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
        }
        
        @keyframes slideInDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .nav-tabs .nav-link {
            color: #6c757d;
            border: none;
            border-bottom: 3px solid transparent;
            font-weight: 500;
        }

        .nav-tabs .nav-link:hover {
            border-bottom-color: #dee2e6;
        }

        .nav-tabs .nav-link.active {
            color: #667eea;
            border-bottom-color: #667eea;
            background: transparent;
        }
    </style>
</head>

<body>
<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-11 col-xl-10">
            
            <!-- Page Header -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h3 class="mb-0">
                            <i class="bi bi-sliders me-2"></i>
                            Management Center
                        </h3>
                        <p class="mb-0 mt-1 small opacity-75">
                            Manage branches, income & expense categories
                        </p>
                    </div>
                    <div class="col-md-6 text-md-end mt-2 mt-md-0">
                        <a href="account.php" class="btn btn-light btn-sm">
                            <i class="bi bi-arrow-left me-1"></i>
                            Back to Finance Panel
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Alert Messages -->
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <div class="d-flex align-items-start">
                    <div class="me-3" style="font-size: 1.5rem;">
                        <?php if ($messageType === 'success'): ?>
                            <i class="bi bi-check-circle-fill"></i>
                        <?php elseif ($messageType === 'danger'): ?>
                            <i class="bi bi-x-circle-fill"></i>
                        <?php else: ?>
                            <i class="bi bi-exclamation-triangle-fill"></i>
                        <?php endif; ?>
                    </div>
                    <div class="flex-grow-1">
                        <strong>
                            <?php if ($messageType === 'success'): ?>
                                Success!
                            <?php elseif ($messageType === 'danger'): ?>
                                Error!
                            <?php else: ?>
                                Warning!
                            <?php endif; ?>
                        </strong>
                        <div class="mt-1"><?= $message ?></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4" id="managementTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="branches-tab" data-bs-toggle="tab" data-bs-target="#branches" type="button" role="tab">
                        <i class="bi bi-building me-2"></i>Branches <span class="badge bg-primary ms-1"><?= count($branches) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="income-categories-tab" data-bs-toggle="tab" data-bs-target="#income-categories" type="button" role="tab">
                        <i class="bi bi-arrow-down-circle me-2"></i>Income Categories <span class="badge bg-success ms-1"><?= count($incomeCategories) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="expense-categories-tab" data-bs-toggle="tab" data-bs-target="#expense-categories" type="button" role="tab">
                        <i class="bi bi-arrow-up-circle me-2"></i>Expense Categories <span class="badge bg-danger ms-1"><?= count($expenseCategories) ?></span>
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="managementTabsContent">
                
                <!-- ========== BRANCHES TAB ========== -->
                <div class="tab-pane fade show active" id="branches" role="tabpanel">
                    
                    <!-- Add Branch Form -->
                    <div class="form-section">
                        <h5 class="mb-3">
                            <i class="bi bi-plus-circle text-primary me-2"></i>
                            Add New Branch
                        </h5>
                        <form method="POST" action="">
                            <div class="row align-items-end">
                                <div class="col-md-8 mb-2">
                                    <label for="branch_name" class="form-label fw-semibold">
                                        Branch Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="branch_name" 
                                           name="branch_name" 
                                           placeholder="Enter branch name (e.g., Main Branch, Downtown)"
                                           required
                                           maxlength="100">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Choose a clear, descriptive name
                                    </small>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <button type="submit" name="add_branch" class="btn btn-primary w-100">
                                        <i class="bi bi-plus-lg me-1"></i>
                                        Add Branch
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Branches List -->
                    <div class="table-section">
                        <div class="p-3 border-bottom">
                            <h5 class="mb-0">
                                <i class="bi bi-list-ul text-primary me-2"></i>
                                All Branches
                                <span class="badge bg-primary ms-2"><?= count($branches) ?></span>
                            </h5>
                        </div>
                        
                        <?php if (!empty($branches)): ?>
                        <div class="p-3">
                            <?php foreach ($branches as $branch): ?>
                            <div class="branch-card">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h6 class="mb-1">
                                            <i class="bi bi-building-fill text-primary me-2"></i>
                                            <?= htmlspecialchars($branch['name']) ?>
                                        </h6>
                                        <small class="text-muted">
                                            <i class="bi bi-calendar3 me-1"></i>
                                            Created: <?= date('M d, Y', strtotime($branch['created_at'])) ?>
                                        </small>
                                    </div>
                                    
                                    <div class="col-md-4 text-md-end mt-2 mt-md-0">
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-primary me-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editBranchModal<?= $branch['id'] ?>"
                                                title="Edit Branch">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger" 
                                                onclick="confirmDeleteBranch(<?= $branch['id'] ?>, '<?= htmlspecialchars(addslashes($branch['name'])) ?>')"
                                                title="Delete Branch">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Edit Branch Modal -->
                            <div class="modal fade" id="editBranchModal<?= $branch['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <i class="bi bi-pencil-square me-2"></i>
                                                Edit Branch
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST" action="">
                                            <div class="modal-body">
                                                <input type="hidden" name="branch_id" value="<?= $branch['id'] ?>">
                                                <input type="hidden" name="old_branch_name" value="<?= htmlspecialchars($branch['name']) ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">
                                                        Branch Name <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           name="branch_name" 
                                                           value="<?= htmlspecialchars($branch['name']) ?>"
                                                           required
                                                           maxlength="100">
                                                </div>
                                                
                                                <div class="alert alert-info mb-0">
                                                    <i class="bi bi-info-circle me-2"></i>
                                                    <small>
                                                        <strong>Branch Info:</strong><br>
                                                        Created: <?= date('F d, Y', strtotime($branch['created_at'])) ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                    <i class="bi bi-x-lg me-1"></i>
                                                    Cancel
                                                </button>
                                                <button type="submit" name="update_branch" class="btn btn-primary">
                                                    <i class="bi bi-check-lg me-1"></i>
                                                    Save Changes
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox text-muted" style="font-size: 4rem; opacity: 0.5;"></i>
                            <h5 class="mt-3 text-muted">No branches found</h5>
                            <p class="text-muted mb-0">Add your first branch using the form above</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                </div>
                
                <!-- ========== INCOME CATEGORIES TAB ========== -->
                <div class="tab-pane fade" id="income-categories" role="tabpanel">
                    
                    <!-- Add Income Category Form -->
                    <div class="form-section">
                        <h5 class="mb-3">
                            <i class="bi bi-plus-circle text-success me-2"></i>
                            Add New Income Category
                        </h5>
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="income_category" class="form-label fw-semibold">
                                        Category Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="income_category" 
                                           name="category" 
                                           placeholder="e.g., Sales, Services"
                                           required
                                           maxlength="100">
                                </div>
                                <div class="col-md-5 mb-3">
                                    <label for="income_description" class="form-label fw-semibold">
                                        Description
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="income_description" 
                                           name="description" 
                                           placeholder="Optional description"
                                           maxlength="255">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" name="add_income_category" class="btn btn-success w-100">
                                        <i class="bi bi-plus-lg me-1"></i>
                                        Add Category
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Income Categories List -->
                    <div class="table-section">
                        <div class="p-3 border-bottom">
                            <h5 class="mb-0">
                                <i class="bi bi-arrow-down-circle-fill text-success me-2"></i>
                                All Income Categories
                                <span class="badge bg-success ms-2"><?= count($incomeCategories) ?></span>
                            </h5>
                        </div>
                        
                        <?php if (!empty($incomeCategories)): ?>
                        <div class="p-3">
                            <?php foreach ($incomeCategories as $category): ?>
                            <div class="category-card">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h6 class="mb-1">
                                            <i class="bi bi-tag-fill text-success me-2"></i>
                                            <?= htmlspecialchars($category['name']) ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?= !empty($category['description']) ? htmlspecialchars($category['description']) : '<em>No description</em>' ?>
                                        </small>
                                        <div class="mt-1">
                                            <small class="text-muted">
                                                <i class="bi bi-calendar3 me-1"></i>
                                                Created: <?= date('M d, Y', strtotime($category['created_at'])) ?>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4 text-md-end mt-2 mt-md-0">
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-success me-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editIncomeModal<?= $category['id'] ?>"
                                                title="Edit Category">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger" 
                                                onclick="confirmDeleteIncomeCategory(<?= $category['id'] ?>, '<?= htmlspecialchars(addslashes($category['name'])) ?>')"
                                                title="Delete Category">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Edit Income Category Modal -->
                            <div class="modal fade" id="editIncomeModal<?= $category['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <i class="bi bi-pencil-square me-2"></i>
                                                Edit Income Category
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST" action="">
                                            <div class="modal-body">
                                                <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                                                <input type="hidden" name="old_category_name" value="<?= htmlspecialchars($category['name']) ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">
                                                        Category Name <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           name="category" 
                                                           value="<?= htmlspecialchars($category['name']) ?>"
                                                           required
                                                           maxlength="100">
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">
                                                        Description
                                                    </label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           name="description" 
                                                           value="<?= htmlspecialchars($category['description'] ?? '') ?>"
                                                           maxlength="255">
                                                </div>
                                                
                                                <div class="alert alert-info mb-0">
                                                    <i class="bi bi-info-circle me-2"></i>
                                                    <small>
                                                        <strong>Category Info:</strong><br>
                                                        Created: <?= date('F d, Y', strtotime($category['created_at'])) ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                    <i class="bi bi-x-lg me-1"></i>
                                                    Cancel
                                                </button>
                                                <button type="submit" name="update_income_category" class="btn btn-success">
                                                    <i class="bi bi-check-lg me-1"></i>
                                                    Save Changes
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox text-muted" style="font-size: 4rem; opacity: 0.5;"></i>
                            <h5 class="mt-3 text-muted">No income categories found</h5>
                            <p class="text-muted mb-0">Add your first income category using the form above</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                </div>
                
                <!-- ========== EXPENSE CATEGORIES TAB ========== -->
                <div class="tab-pane fade" id="expense-categories" role="tabpanel">
                    
                    <!-- Add Expense Category Form -->
                    <div class="form-section">
                        <h5 class="mb-3">
                            <i class="bi bi-plus-circle text-danger me-2"></i>
                            Add New Expense Category
                        </h5>
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="expense_category" class="form-label fw-semibold">
                                        Category Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="expense_category" 
                                           name="category" 
                                           placeholder="e.g., Rent, Utilities"
                                           required
                                           maxlength="100">
                                </div>
                                <div class="col-md-5 mb-3">
                                    <label for="expense_description" class="form-label fw-semibold">
                                        Description
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="expense_description" 
                                           name="description" 
                                           placeholder="Optional description"
                                           maxlength="255">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" name="add_expense_category" class="btn btn-danger w-100">
                                        <i class="bi bi-plus-lg me-1"></i>
                                        Add Category
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Expense Categories List -->
                    <div class="table-section">
                        <div class="p-3 border-bottom">
                            <h5 class="mb-0">
                                <i class="bi bi-arrow-up-circle-fill text-danger me-2"></i>
                                All Expense Categories
                                <span class="badge bg-danger ms-2"><?= count($expenseCategories) ?></span>
                            </h5>
                        </div>
                        
                        <?php if (!empty($expenseCategories)): ?>
                        <div class="p-3">
                            <?php foreach ($expenseCategories as $category): ?>
                            <div class="category-card">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h6 class="mb-1">
                                            <i class="bi bi-tag-fill text-danger me-2"></i>
                                            <?= htmlspecialchars($category['name']) ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?= !empty($category['description']) ? htmlspecialchars($category['description']) : '<em>No description</em>' ?>
                                        </small>
                                        <div class="mt-1">
                                            <small class="text-muted">
                                                <i class="bi bi-calendar3 me-1"></i>
                                                Created: <?= date('M d, Y', strtotime($category['created_at'])) ?>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4 text-md-end mt-2 mt-md-0">
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger me-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editExpenseModal<?= $category['id'] ?>"
                                                title="Edit Category">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger" 
                                                onclick="confirmDeleteExpenseCategory(<?= $category['id'] ?>, '<?= htmlspecialchars(addslashes($category['name'])) ?>')"
                                                title="Delete Category">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Edit Expense Category Modal -->
                            <div class="modal fade" id="editExpenseModal<?= $category['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <i class="bi bi-pencil-square me-2"></i>
                                                Edit Expense Category
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST" action="">
                                            <div class="modal-body">
                                                <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                                                <input type="hidden" name="old_category_name" value="<?= htmlspecialchars($category['name']) ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">
                                                        Category Name <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           name="category" 
                                                           value="<?= htmlspecialchars($category['name']) ?>"
                                                           required
                                                           maxlength="100">
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">
                                                        Description
                                                    </label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           name="description" 
                                                           value="<?= htmlspecialchars($category['description'] ?? '') ?>"
                                                           maxlength="255">
                                                </div>
                                                
                                                <div class="alert alert-info mb-0">
                                                    <i class="bi bi-info-circle me-2"></i>
                                                    <small>
                                                        <strong>Category Info:</strong><br>
                                                        Created: <?= date('F d, Y', strtotime($category['created_at'])) ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                    <i class="bi bi-x-lg me-1"></i>
                                                    Cancel
                                                </button>
                                                <button type="submit" name="update_expense_category" class="btn btn-danger">
                                                    <i class="bi bi-check-lg me-1"></i>
                                                    Save Changes
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox text-muted" style="font-size: 4rem; opacity: 0.5;"></i>
                            <h5 class="mt-3 text-muted">No expense categories found</h5>
                            <p class="text-muted mb-0">Add your first expense category using the form above</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                </div>
            </div>
            
        </div>
    </div>
</div>

<!-- Delete Forms (Hidden) -->
<form method="POST" action="" id="deleteBranchForm" style="display: none;">
    <input type="hidden" name="branch_id" id="deleteBranchId">
    <input type="hidden" name="branch_name" id="deleteBranchName">
    <input type="hidden" name="delete_branch" value="1">
</form>

<form method="POST" action="" id="deleteIncomeCategoryForm" style="display: none;">
    <input type="hidden" name="category_id" id="deleteIncomeCategoryId">
    <input type="hidden" name="category_name" id="deleteIncomeCategoryName">
    <input type="hidden" name="delete_income_category" value="1">
</form>

<form method="POST" action="" id="deleteExpenseCategoryForm" style="display: none;">
    <input type="hidden" name="category_id" id="deleteExpenseCategoryId">
    <input type="hidden" name="category_name" id="deleteExpenseCategoryName">
    <input type="hidden" name="delete_expense_category" value="1">
</form>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.js"></script>

<script>
// Confirm Delete Branch
function confirmDeleteBranch(branchId, branchName) {
    Swal.fire({
        icon: 'warning',
        title: 'Delete Branch?',
        html: `
            <div class="text-start">
                <p class="mb-2"><strong>Branch Name:</strong> ${branchName}</p>
                <div class="alert alert-danger mb-2">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Warning:</strong> All income and expense transactions associated with this branch will be deleted!
                </div>
                <p class="mb-0"><strong>This action CANNOT be undone!</strong></p>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-trash3 me-1"></i> Yes, Delete It!',
        cancelButtonText: '<i class="bi bi-x-lg me-1"></i> Cancel',
        reverseButtons: true,
        focusCancel: true
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Deleting...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            document.getElementById('deleteBranchId').value = branchId;
            document.getElementById('deleteBranchName').value = branchName;
            document.getElementById('deleteBranchForm').submit();
        }
    });
}

// Confirm Delete Income Category
function confirmDeleteIncomeCategory(categoryId, categoryName) {
    Swal.fire({
        icon: 'warning',
        title: 'Delete Income Category?',
        html: `
            <div class="text-start">
                <p class="mb-2"><strong>Category Name:</strong> ${categoryName}</p>
                <div class="alert alert-danger mb-2">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Warning:</strong> All income records using this category will be deleted!
                </div>
                <p class="mb-0"><strong>Are you sure you want to continue?</strong></p>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-trash3 me-1"></i> Yes, Delete It!',
        cancelButtonText: '<i class="bi bi-x-lg me-1"></i> Cancel',
        reverseButtons: true,
        focusCancel: true
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Deleting...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            document.getElementById('deleteIncomeCategoryId').value = categoryId;
            document.getElementById('deleteIncomeCategoryName').value = categoryName;
            document.getElementById('deleteIncomeCategoryForm').submit();
        }
    });
}

// Confirm Delete Expense Category
function confirmDeleteExpenseCategory(categoryId, categoryName) {
    Swal.fire({
        icon: 'warning',
        title: 'Delete Expense Category?',
        html: `
            <div class="text-start">
                <p class="mb-2"><strong>Category Name:</strong> ${categoryName}</p>
                <div class="alert alert-danger mb-2">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Warning:</strong> All expense records using this category will be deleted!
                </div>
                <p class="mb-0"><strong>Are you sure you want to continue?</strong></p>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-trash3 me-1"></i> Yes, Delete It!',
        cancelButtonText: '<i class="bi bi-x-lg me-1"></i> Cancel',
        reverseButtons: true,
        focusCancel: true
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Deleting...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            document.getElementById('deleteExpenseCategoryId').value = categoryId;
            document.getElementById('deleteExpenseCategoryName').value = categoryName;
            document.getElementById('deleteExpenseCategoryForm').submit();
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss alerts after 8 seconds
    const alerts = document.querySelectorAll('.alert:not(.alert-info)');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const closeBtn = alert.querySelector('.btn-close');
            if (closeBtn) {
                closeBtn.click();
            }
        }, 8000);
    });
    
    // Add animation to cards
    const cards = document.querySelectorAll('.branch-card, .category-card');
    cards.forEach((card, index) => {
        card.style.animation = `fadeInUp 0.5s ease-out ${index * 0.1}s both`;
    });
});

// Add fade in animation
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
`;
document.head.appendChild(style);
</script>

</body>
</html>