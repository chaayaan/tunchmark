<?php
require 'auth.php';

// Check if config file exists
if (!file_exists('client_db_config.php')) {
    echo json_encode(['success' => false, 'message' => 'Configuration file not found. Please create client_db_config.php']);
    exit;
}

require 'client_db_config.php';


// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get parameters
$clientId = $_POST['client_id'] ?? null;
$dbName = $_POST['db_name'] ?? null;
$month = $_POST['month'] ?? date('Y-m');

if (!$clientId || !$dbName) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters: client_id or db_name']);
    exit;
}

// Check if config constants are defined
if (!defined('DB_HOST') || !defined('DB_USERNAME') || !defined('DB_PASSWORD')) {
    echo json_encode(['success' => false, 'message' => 'Database configuration constants not defined. Please check client_db_config.php']);
    exit;
}

// Get database credentials from config
$host = DB_HOST;
$username = DB_USERNAME;
$password = DB_PASSWORD;

// Check for default/placeholder values (but allow empty password)
if ($username === 'your_db_username' || $password === 'your_db_password') {
    echo json_encode(['success' => false, 'message' => 'Please update database credentials in client_db_config.php']);
    exit;
}

try {
    // Connect to the client database
    $clientPdo = new PDO(
        "mysql:host=$host;dbname=$dbName;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

// ===== Set MySQL session timezone =====
$clientPdo->exec("SET time_zone = '+06:00'");  // Bangladesh timezone

    $stats = [
        'today' => [],
        'monthly' => []
    ];

    // ====================
    // TODAY'S STATISTICS
    // ====================
    
    // Total orders today
    $stmt = $clientPdo->query("SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = CURRENT_DATE()");
    $stats['today']['total_orders'] = $stmt->fetch()['count'];

    // Total amount today (sum of all bill items for today's orders)
    $stmt = $clientPdo->query("
        SELECT COALESCE(SUM(bi.total_price), 0) as total
        FROM bill_items bi
        INNER JOIN orders o ON bi.order_id = o.order_id
        WHERE DATE(o.created_at) = CURRENT_DATE()
    ");
    $stats['today']['total_amount'] = $stmt->fetch()['total'];

    // Paid orders today - count and amount
    $stmt = $clientPdo->query("
        SELECT 
            COUNT(DISTINCT o.order_id) as count,
            COALESCE(SUM(bi.total_price), 0) as total
        FROM orders o
        LEFT JOIN bill_items bi ON o.order_id = bi.order_id
        WHERE DATE(o.created_at) = CURRENT_DATE() AND o.status = 'paid'
    ");
    $paidToday = $stmt->fetch();
    $stats['today']['paid_orders'] = $paidToday['count'];
    $stats['today']['paid_amount'] = $paidToday['total'];

    // Pending (unpaid) orders today - count and amount
    $stmt = $clientPdo->query("
        SELECT 
            COUNT(DISTINCT o.order_id) as count,
            COALESCE(SUM(bi.total_price), 0) as total
        FROM orders o
        LEFT JOIN bill_items bi ON o.order_id = bi.order_id
        WHERE DATE(o.created_at) = CURRENT_DATE() AND o.status = 'pending'
    ");
    $pendingToday = $stmt->fetch();
    $stats['today']['pending_orders'] = $pendingToday['count'];
    $stats['today']['pending_amount'] = $pendingToday['total'];

    // Cancelled orders today - count and amount
    $stmt = $clientPdo->query("
        SELECT 
            COUNT(DISTINCT o.order_id) as count,
            COALESCE(SUM(bi.total_price), 0) as total
        FROM orders o
        LEFT JOIN bill_items bi ON o.order_id = bi.order_id
        WHERE DATE(o.created_at) = CURRENT_DATE() AND o.status = 'cancelled'
    ");
    $cancelledToday = $stmt->fetch();
    $stats['today']['cancelled_orders'] = $cancelledToday['count'];
    $stats['today']['cancelled_amount'] = $cancelledToday['total'];

    // Service-wise amount for today
    $stmt = $clientPdo->query("
        SELECT 
            s.name as service_name,
            COALESCE(SUM(bi.total_price), 0) as total_amount
        FROM services s
        LEFT JOIN bill_items bi ON s.id = bi.service_id
        LEFT JOIN orders o ON bi.order_id = o.order_id
        WHERE DATE(o.created_at) = CURRENT_DATE()
        GROUP BY s.id, s.name
        HAVING total_amount > 0
        ORDER BY total_amount DESC
    ");
    $stats['today']['services'] = $stmt->fetchAll();

    // ====================
    // MONTHLY STATISTICS
    // ====================
    
    // Parse month
    list($year, $monthNum) = explode('-', $month);
    
    // Total orders for selected month
    $stmt = $clientPdo->prepare("
        SELECT COUNT(*) as count 
        FROM orders 
        WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?
    ");
    $stmt->execute([$year, $monthNum]);
    $stats['monthly']['total_orders'] = $stmt->fetch()['count'];

    // Total amount for selected month
    $stmt = $clientPdo->prepare("
        SELECT COALESCE(SUM(bi.total_price), 0) as total
        FROM bill_items bi
        INNER JOIN orders o ON bi.order_id = o.order_id
        WHERE YEAR(o.created_at) = ? AND MONTH(o.created_at) = ?
    ");
    $stmt->execute([$year, $monthNum]);
    $stats['monthly']['total_amount'] = $stmt->fetch()['total'];

    // Paid orders for selected month - count and amount
    $stmt = $clientPdo->prepare("
        SELECT 
            COUNT(DISTINCT o.order_id) as count,
            COALESCE(SUM(bi.total_price), 0) as total
        FROM orders o
        LEFT JOIN bill_items bi ON o.order_id = bi.order_id
        WHERE YEAR(o.created_at) = ? AND MONTH(o.created_at) = ? AND o.status = 'paid'
    ");
    $stmt->execute([$year, $monthNum]);
    $paidMonthly = $stmt->fetch();
    $stats['monthly']['paid_orders'] = $paidMonthly['count'];
    $stats['monthly']['paid_amount'] = $paidMonthly['total'];

    // Pending orders for selected month - count and amount
    $stmt = $clientPdo->prepare("
        SELECT 
            COUNT(DISTINCT o.order_id) as count,
            COALESCE(SUM(bi.total_price), 0) as total
        FROM orders o
        LEFT JOIN bill_items bi ON o.order_id = bi.order_id
        WHERE YEAR(o.created_at) = ? AND MONTH(o.created_at) = ? AND o.status = 'pending'
    ");
    $stmt->execute([$year, $monthNum]);
    $pendingMonthly = $stmt->fetch();
    $stats['monthly']['pending_orders'] = $pendingMonthly['count'];
    $stats['monthly']['pending_amount'] = $pendingMonthly['total'];

    // Cancelled orders for selected month - count and amount
    $stmt = $clientPdo->prepare("
        SELECT 
            COUNT(DISTINCT o.order_id) as count,
            COALESCE(SUM(bi.total_price), 0) as total
        FROM orders o
        LEFT JOIN bill_items bi ON o.order_id = bi.order_id
        WHERE YEAR(o.created_at) = ? AND MONTH(o.created_at) = ? AND o.status = 'cancelled'
    ");
    $stmt->execute([$year, $monthNum]);
    $cancelledMonthly = $stmt->fetch();
    $stats['monthly']['cancelled_orders'] = $cancelledMonthly['count'];
    $stats['monthly']['cancelled_amount'] = $cancelledMonthly['total'];

    // Service-wise amount for selected month
    $stmt = $clientPdo->prepare("
        SELECT 
            s.name as service_name,
            COALESCE(SUM(bi.total_price), 0) as total_amount
        FROM services s
        LEFT JOIN bill_items bi ON s.id = bi.service_id
        LEFT JOIN orders o ON bi.order_id = o.order_id
        WHERE YEAR(o.created_at) = ? AND MONTH(o.created_at) = ?
        GROUP BY s.id, s.name
        HAVING total_amount > 0
        ORDER BY total_amount DESC
    ");
    $stmt->execute([$year, $monthNum]);
    $stats['monthly']['services'] = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}