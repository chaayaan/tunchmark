<?php
require 'auth.php';
require 'mylicensedb.php';


// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Get branch ID if provided
$branch_id = $_GET['id'] ?? null;
$branch = null;

if ($branch_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM licenses WHERE id = ?");
        $stmt->execute([$branch_id]);
        $branch = $stmt->fetch();
    } catch (PDOException $e) {
        $error = "Error fetching branch: " . $e->getMessage();
    }
}

$testResult = null;

// Test connection if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_connection'])) {
    $testDbName = $_POST['db_name'];
    $testHost = $_POST['db_host'];
    $testUsername = $_POST['db_username'];
    $testPassword = $_POST['db_password'] ?? ''; // Allow blank password
    
    try {
        $testPdo = new PDO(
            "mysql:host=$testHost;dbname=$testDbName;charset=utf8mb4",
            $testUsername,
            $testPassword,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Test if required tables exist
        $tables = ['orders', 'bill_items', 'services'];
        $existingTables = [];
        $missingTables = [];
        
        foreach ($tables as $table) {
            $stmt = $testPdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                $existingTables[] = $table;
            } else {
                $missingTables[] = $table;
            }
        }
        
        // Get record counts
        $counts = [];
        foreach ($existingTables as $table) {
            $stmt = $testPdo->query("SELECT COUNT(*) as count FROM $table");
            $counts[$table] = $stmt->fetch()['count'];
        }
        
        $testResult = [
            'success' => true,
            'message' => 'Connection successful!',
            'existing_tables' => $existingTables,
            'missing_tables' => $missingTables,
            'counts' => $counts
        ];
        
    } catch (PDOException $e) {
        $testResult = [
            'success' => false,
            'message' => 'Connection failed: ' . $e->getMessage()
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Connection Test</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            background: white;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .result-box {
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .result-success {
            background-color: #d1e7dd;
            border: 1px solid #badbcc;
            color: #0f5132;
        }
        .result-error {
            background-color: #f8d7da;
            border: 1px solid #f5c2c7;
            color: #842029;
        }
        .table-status {
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-database"></i> Database Connection Test</h2>
            <a href="branch_activity.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>

        <?php if ($branch): ?>
            <div class="alert alert-info">
                <strong>Testing Branch:</strong> <?php echo htmlspecialchars($branch['branch_name']); ?><br>
                <strong>Database:</strong> <?php echo htmlspecialchars($branch['database_name']); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Database Host</label>
                <input type="text" name="db_host" class="form-control" value="localhost" required>
                <small class="text-muted">Usually 'localhost' or '127.0.0.1'</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Database Name</label>
                <input type="text" name="db_name" class="form-control" 
                       value="<?php echo $branch ? htmlspecialchars($branch['database_name']) : ''; ?>" required>
                <small class="text-muted">The name of the branch database</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Database Username</label>
                <input type="text" name="db_username" class="form-control" required>
                <small class="text-muted">MySQL/MariaDB username with access to the database</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Database Password</label>
                <input type="password" name="db_password" class="form-control">
                <small class="text-muted">MySQL/MariaDB password (leave blank if no password)</small>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="showPassword">
                <label class="form-check-label" for="showPassword">
                    Show password
                </label>
            </div>

            <button type="submit" name="test_connection" class="btn btn-primary">
                <i class="fas fa-plug"></i> Test Connection
            </button>
        </form>

        <?php if ($testResult): ?>
            <div class="result-box <?php echo $testResult['success'] ? 'result-success' : 'result-error'; ?>">
                <h5>
                    <i class="fas fa-<?php echo $testResult['success'] ? 'check-circle' : 'times-circle'; ?>"></i>
                    <?php echo $testResult['message']; ?>
                </h5>

                <?php if ($testResult['success']): ?>
                    <div class="table-status">
                        <h6>Table Status:</h6>
                        
                        <?php if (!empty($testResult['existing_tables'])): ?>
                            <p><strong>✓ Found Tables:</strong></p>
                            <ul>
                                <?php foreach ($testResult['existing_tables'] as $table): ?>
                                    <li>
                                        <?php echo $table; ?> 
                                        <span class="badge bg-primary">
                                            <?php echo number_format($testResult['counts'][$table]); ?> records
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if (!empty($testResult['missing_tables'])): ?>
                            <p><strong>✗ Missing Tables:</strong></p>
                            <ul>
                                <?php foreach ($testResult['missing_tables'] as $table): ?>
                                    <li class="text-danger"><?php echo $table; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if (empty($testResult['missing_tables'])): ?>
                            <div class="alert alert-success mt-3">
                                <i class="fas fa-check-circle"></i> All required tables exist! 
                                You can now use these credentials in <code>branch_db_config.php</code>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-triangle"></i> Some required tables are missing. 
                                The branch activity monitor may not work correctly.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="alert alert-secondary mt-4">
            <h6>Instructions:</h6>
            <ol>
                <li>Enter your database credentials above</li>
                <li>Click "Test Connection" to verify</li>
                <li>If successful, copy the same credentials to <code>branch_db_config.php</code></li>
                <li>Update the constants:
                    <pre style="background: #f8f9fa; padding: 10px; border-radius: 5px; margin-top: 10px;">
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'your_username_here');
define('DB_PASSWORD', 'your_password_here');</pre>
                </li>
            </ol>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('showPassword').addEventListener('change', function() {
            const passwordInput = document.querySelector('input[name="db_password"]');
            passwordInput.type = this.checked ? 'text' : 'password';
        });
    </script>
</body>
</html>