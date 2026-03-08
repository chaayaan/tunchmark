<?php
/**
 * BRANCH NOTIFICATION & REPLY MANAGEMENT SYSTEM
 * File: store_msg.php
 * Purpose: Modern messenger-style admin interface with user tracking
 */
require 'auth.php';

// ========================================
// DATABASE CONNECTION (EMBEDDED)
// ========================================
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "license_manager";

// Create MySQLi connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

// ========================================
// INITIALIZE VARIABLES
// ========================================
$success_message = "";
$error_message = "";
$created_by = isset($_SESSION['username']) ? $_SESSION['username'] : 'admin';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'notifications';

// Get all branches from licenses table
$branches = array();
$branch_query = "SELECT DISTINCT branch_name FROM licenses ORDER BY branch_name ASC";
$branch_result = $conn->query($branch_query);
if ($branch_result) {
    while ($row = $branch_result->fetch_assoc()) {
        $branches[] = $row['branch_name'];
    }
}

// ========================================
// HANDLE AJAX REQUESTS
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['ajax_action'] === 'mark_reply_read') {
        $notif_id = intval($_POST['notification_id']);
        $stmt = $conn->prepare("UPDATE branch_notifications SET reply_read_by_admin = 1 WHERE id = ?");
        $stmt->bind_param("i", $notif_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update']);
        }
        $stmt->close();
        exit();
    }
}

// ========================================
// HANDLE FORM SUBMISSION
// ========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_notification'])) {
    
    $branch_name = trim($_POST['branch_name']);
    $notification_message = trim($_POST['notification_message']);
    $notification_type = $_POST['notification_type'];
    
    // Validation
    if (empty($branch_name)) {
        $error_message = "Please select a branch!";
    } elseif (empty($notification_message)) {
        $error_message = "Notification message cannot be empty!";
    } else {
        // Insert notification using prepared statement
        $stmt = $conn->prepare("INSERT INTO branch_notifications (branch_name, notification_message, notification_type, created_by) VALUES (?, ?, ?, ?)");
        
        if ($stmt) {
            $stmt->bind_param("ssss", $branch_name, $notification_message, $notification_type, $created_by);
            
            if ($stmt->execute()) {
                $success_message = "Notification sent successfully!";
                $_POST = array();
            } else {
                $error_message = "Error: " . $stmt->error;
            }
            
            $stmt->close();
        } else {
            $error_message = "Error: " . $conn->error;
        }
    }
}

// ========================================
// DELETE NOTIFICATION
// ========================================
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $delete_stmt = $conn->prepare("UPDATE branch_notifications SET is_active = 0 WHERE id = ?");
    
    if ($delete_stmt) {
        $delete_stmt->bind_param("i", $delete_id);
        if ($delete_stmt->execute()) {
            $success_message = "Notification deleted!";
        }
        $delete_stmt->close();
    }
    
    header("Location: store_msg.php?tab=" . $active_tab);
    exit();
}

// ========================================
// FETCH ALL ACTIVE NOTIFICATIONS
// ========================================
$all_notifications = array();
$stmt = $conn->prepare("SELECT id, branch_name, notification_message, notification_type, created_at, created_by, has_reply FROM branch_notifications WHERE is_active = 1 ORDER BY created_at DESC");

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $all_notifications[] = $row;
    }
    
    $stmt->close();
}

// ========================================
// FETCH ALL REPLIES
// ========================================
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$reply_query = "SELECT id, branch_name, notification_message, notification_type, created_at, created_by,
                client_reply, client_replied_at, replied_by_user_id, replied_by_username, has_reply, reply_read_by_admin
                FROM branch_notifications 
                WHERE is_active = 1 AND has_reply = 1";

if ($filter === 'unread') {
    $reply_query .= " AND reply_read_by_admin = 0";
}

$reply_query .= " ORDER BY client_replied_at DESC";

$all_replies = array();
$reply_result = $conn->query($reply_query);
if ($reply_result) {
    while ($row = $reply_result->fetch_assoc()) {
        $all_replies[] = $row;
    }
}

// Get reply statistics
$stats = array(
    'total' => 0,
    'unread' => 0
);

$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN reply_read_by_admin = 0 THEN 1 ELSE 0 END) as unread
FROM branch_notifications 
WHERE is_active = 1 AND has_reply = 1";

$stats_result = $conn->query($stats_query);
if ($stats_result) {
    $stats = $stats_result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #f0f2f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            overflow-x: hidden;
        }
        
        /* Compact Header */
        .msg-header {
            background: white;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e4e6eb;
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .msg-header h1 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #050505;
            margin: 0;
        }
        
        .back-btn {
            padding: 0.4rem 0.8rem;
            background: #f0f2f5;
            border: none;
            border-radius: 6px;
            color: #050505;
            font-weight: 500;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s;
        }
        
        .back-btn:hover {
            background: #e4e6eb;
        }
        
        /* Tabs - WhatsApp Style */
        .msg-tabs {
            background: white;
            display: flex;
            border-bottom: 2px solid #e4e6eb;
            position: sticky;
            top: 60px;
            z-index: 99;
        }
        
        .msg-tab {
            flex: 1;
            padding: 0.75rem;
            text-align: center;
            border: none;
            background: transparent;
            color: #65676b;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            position: relative;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .msg-tab:hover {
            background: #f0f2f5;
        }
        
        .msg-tab.active {
            color: #0084ff;
        }
        
        .msg-tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: #0084ff;
        }
        
        .msg-tab .badge {
            background: #ff4444;
            color: white;
            font-size: 0.7rem;
            padding: 0.15rem 0.4rem;
            border-radius: 10px;
            margin-left: 0.3rem;
        }
        
        /* Main Container */
        .msg-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        /* Alert Messages - Compact */
        .alert-msg {
            padding: 0.6rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 3px solid #28a745;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 3px solid #dc3545;
        }
        
        /* ===== NOTIFICATIONS TAB ===== */
        
        /* Compose Section - Compact */
        .compose-box {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .compose-header {
            font-size: 0.95rem;
            font-weight: 600;
            color: #050505;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-group-compact {
            margin-bottom: 0.75rem;
        }
        
        .form-group-compact label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #65676b;
            margin-bottom: 0.3rem;
            display: block;
        }
        
        .form-control-compact, .form-select-compact {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #e4e6eb;
            border-radius: 8px;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        
        .form-control-compact:focus, .form-select-compact:focus {
            outline: none;
            border-color: #0084ff;
            box-shadow: 0 0 0 2px rgba(0, 132, 255, 0.1);
        }
        
        .type-selector {
            display: flex;
            gap: 0.5rem;
        }
        
        .type-btn {
            flex: 1;
            padding: 0.5rem;
            border: 2px solid #e4e6eb;
            border-radius: 8px;
            background: white;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .type-btn.active {
            border-color: #0084ff;
            background: #e7f3ff;
            color: #0084ff;
        }
        
        .btn-send {
            width: 100%;
            padding: 0.6rem;
            background: #0084ff;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-send:hover {
            background: #0073e6;
            transform: translateY(-1px);
        }
        
        .btn-send:active {
            transform: translateY(0);
        }
        
        /* Message List - Instagram/WhatsApp Style */
        .msg-list {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .msg-list-header {
            padding: 0.75rem 1rem;
            background: #f0f2f5;
            font-weight: 600;
            font-size: 0.85rem;
            color: #65676b;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .msg-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f0f2f5;
            display: flex;
            gap: 0.75rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .msg-item:hover {
            background: #f0f2f5;
        }
        
        .msg-item:last-child {
            border-bottom: none;
        }
        
        .msg-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        
        .msg-avatar.info { background: linear-gradient(135deg, #667eea, #764ba2); }
        .msg-avatar.success { background: linear-gradient(135deg, #56ab2f, #a8e063); }
        .msg-avatar.warning { background: linear-gradient(135deg, #f2994a, #f2c94c); }
        .msg-avatar.alert { background: linear-gradient(135deg, #eb3349, #f45c43); }
        
        .msg-content {
            flex: 1;
            min-width: 0;
        }
        
        .msg-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.25rem;
        }
        
        .msg-branch {
            font-weight: 600;
            font-size: 0.9rem;
            color: #050505;
        }
        
        .msg-time {
            font-size: 0.75rem;
            color: #65676b;
        }
        
        .msg-preview {
            font-size: 0.85rem;
            color: #65676b;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        
        .msg-badge {
            padding: 0.15rem 0.5rem;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .msg-badge.replied {
            background: #d4edda;
            color: #155724;
        }
        
        .msg-actions {
            display: flex;
            gap: 0.3rem;
            margin-top: 0.3rem;
        }
        
        .btn-action-small {
            padding: 0.25rem 0.6rem;
            font-size: 0.75rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-delete-small {
            background: #fee;
            color: #dc3545;
        }
        
        .btn-delete-small:hover {
            background: #dc3545;
            color: white;
        }
        
        /* Empty State */
        .empty-state {
            padding: 3rem 1rem;
            text-align: center;
            color: #65676b;
        }
        
        .empty-state i {
            font-size: 3rem;
            opacity: 0.3;
            margin-bottom: 1rem;
        }
        
        /* ===== REPLIES TAB ===== */
        
        /* Stats - Compact */
        .stats-compact {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .stat-item {
            flex: 1;
            background: white;
            padding: 0.75rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #050505;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #65676b;
            margin-top: 0.2rem;
        }
        
        /* Filters - Pill Style */
        .filters {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .filter-pill {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            background: white;
            border: 2px solid #e4e6eb;
            font-size: 0.8rem;
            font-weight: 600;
            color: #65676b;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .filter-pill:hover {
            border-color: #0084ff;
            color: #0084ff;
        }
        
        .filter-pill.active {
            background: #0084ff;
            border-color: #0084ff;
            color: white;
        }
        
        /* Reply Cards - Chat Style */
        .reply-card {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .reply-card.unread {
            background: #e7f3ff;
            border-left: 3px solid #0084ff;
        }
        
        .reply-header-compact {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #f0f2f5;
        }
        
        .reply-branch-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .reply-timestamp {
            font-size: 0.75rem;
            color: #65676b;
        }
        
        /* Chat Bubbles */
        .chat-bubble {
            padding: 0.6rem 0.9rem;
            border-radius: 12px;
            margin-bottom: 0.75rem;
            max-width: 80%;
            font-size: 0.85rem;
            line-height: 1.4;
        }
        
        .bubble-sent {
            background: #f0f2f5;
            border: 1px solid #e4e6eb;
            margin-left: 0;
        }
        
        .bubble-received {
            background: #0084ff;
            color: white;
            margin-left: auto;
            margin-right: 0;
        }
        
        .bubble-label {
            font-size: 0.7rem;
            font-weight: 600;
            margin-bottom: 0.3rem;
            opacity: 0.7;
        }
        
        .user-info {
            font-size: 0.75rem;
            margin-top: 0.3rem;
            opacity: 0.8;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        /* Reply Actions - Compact */
        .reply-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .btn-reply-action {
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            border: none;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-read {
            background: #e7f3ff;
            color: #0084ff;
        }
        
        .btn-read:hover {
            background: #0084ff;
            color: white;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .msg-header h1 {
                font-size: 1.1rem;
            }
            
            .stats-compact {
                flex-wrap: wrap;
            }
            
            .stat-item {
                min-width: calc(50% - 0.25rem);
            }
            
            .chat-bubble {
                max-width: 90%;
            }
        }
    </style>
</head>
<body class="container">

<!-- Include Navbar -->
<?php include 'navbar.php'; ?>

<!-- Compact Header -->
<div class="msg-header mt-3">
    <h1><i class="fas fa-comments me-2"></i>Messages</h1>
    <a href="dashboard.php" class="back-btn">
        <i class="fas fa-arrow-left me-1"></i>Back
    </a>
</div>

<!-- Tabs -->
<div class="msg-tabs">
    <a href="?tab=notifications" class="msg-tab <?php echo $active_tab === 'notifications' ? 'active' : ''; ?>">
        <i class="fas fa-paper-plane me-1"></i>
        Sent (<?php echo count($all_notifications); ?>)
    </a>
    <a href="?tab=replies" class="msg-tab <?php echo $active_tab === 'replies' ? 'active' : ''; ?>">
        <i class="fas fa-reply-all me-1"></i>
        Replies (<?php echo $stats['total']; ?>)
        <?php if ($stats['unread'] > 0): ?>
            <span class="badge"><?php echo $stats['unread']; ?></span>
        <?php endif; ?>
    </a>
</div>

<!-- Main Container -->
<div class="msg-container">
    
    <!-- Alerts -->
    <?php if (!empty($success_message)): ?>
        <div class="alert-msg alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert-msg alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <!-- ========== NOTIFICATIONS TAB ========== -->
    <?php if ($active_tab === 'notifications'): ?>
        
        <div class="row g-3">
            <!-- Compose Box -->
            <div class="col-lg-4">
                <div class="compose-box">
                    <div class="compose-header">
                        <i class="fas fa-edit"></i>
                        New Message
                    </div>
                    
                    <form method="POST" action="">
                        <div class="form-group-compact">
                            <label>Branch</label>
                            <select class="form-select-compact" name="branch_name" required>
                                <option value="">Select...</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?php echo htmlspecialchars($branch); ?>">
                                        <?php echo htmlspecialchars($branch); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group-compact">
                            <label>Type</label>
                            <div class="type-selector">
                                <button type="button" class="type-btn active" data-type="info">
                                    ℹ️ Info
                                </button>
                                <button type="button" class="type-btn" data-type="warning">
                                    ⚠️ Warning
                                </button>
                                <button type="button" class="type-btn" data-type="alert">
                                    🚨 Alert
                                </button>
                                <button type="button" class="type-btn" data-type="success">
                                    ✅ Success
                                </button>
                            </div>
                            <input type="hidden" name="notification_type" id="notification_type" value="info">
                        </div>
                        
                        <div class="form-group-compact">
                            <label>Message</label>
                            <textarea class="form-control-compact" name="notification_message" rows="4" required placeholder="Type your message..."></textarea>
                        </div>
                        
                        <button type="submit" name="submit_notification" class="btn-send">
                            <i class="fas fa-paper-plane me-2"></i>Send
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Message List -->
            <div class="col-lg-8">
                <div class="msg-list">
                    <div class="msg-list-header">
                        <span>All Messages</span>
                        <span><?php echo count($all_notifications); ?> total</span>
                    </div>
                    
                    <?php if (empty($all_notifications)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No messages yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($all_notifications as $notif): ?>
                            <div class="msg-item">
                                <div class="msg-avatar <?php echo $notif['notification_type']; ?>">
                                    <?php 
                                    $name_parts = explode(' ', $notif['branch_name']);
                                    echo strtoupper(substr($name_parts[0], 0, 1));
                                    if (count($name_parts) > 1) {
                                        echo strtoupper(substr($name_parts[1], 0, 1));
                                    }
                                    ?>
                                </div>
                                
                                <div class="msg-content">
                                    <div class="msg-header-row">
                                        <span class="msg-branch"><?php echo htmlspecialchars($notif['branch_name']); ?></span>
                                        <span class="msg-time">
                                            <?php 
                                            $diff = time() - strtotime($notif['created_at']);
                                            if ($diff < 3600) echo floor($diff/60) . 'm';
                                            elseif ($diff < 86400) echo floor($diff/3600) . 'h';
                                            else echo date('M d', strtotime($notif['created_at']));
                                            ?>
                                        </span>
                                    </div>
                                    
                                    <div class="msg-preview">
                                        <?php 
                                        $preview = strip_tags($notif['notification_message']);
                                        echo strlen($preview) > 60 ? substr($preview, 0, 60) . '...' : $preview;
                                        ?>
                                        <?php if ($notif['has_reply']): ?>
                                            <span class="msg-badge replied">
                                                <i class="fas fa-reply"></i> Replied
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="msg-actions">
                                        <button class="btn-action-small btn-delete-small" 
                                                onclick="if(confirm('Delete?')) location.href='?delete_id=<?php echo $notif['id']; ?>&tab=notifications'">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    <?php endif; ?>
    
    <!-- ========== REPLIES TAB ========== -->
    <?php if ($active_tab === 'replies'): ?>
        
        <!-- Compact Stats -->
        <div class="stats-compact">
            <div class="stat-item">
                <div class="stat-number" style="color: #0084ff;"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Replies</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" style="color: #ff9800;"><?php echo $stats['unread']; ?></div>
                <div class="stat-label">Unread</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters">
            <a href="?tab=replies&filter=all" class="filter-pill <?php echo $filter === 'all' ? 'active' : ''; ?>">
                All (<?php echo $stats['total']; ?>)
            </a>
            <a href="?tab=replies&filter=unread" class="filter-pill <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                Unread (<?php echo $stats['unread']; ?>)
            </a>
        </div>
        
        <!-- Reply Cards -->
        <?php if (empty($all_replies)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h4>No replies yet</h4>
                <p>Client replies will appear here</p>
            </div>
        <?php else: ?>
            <?php foreach ($all_replies as $reply): ?>
                <div class="reply-card <?php echo !$reply['reply_read_by_admin'] ? 'unread' : ''; ?>" 
                     data-reply-id="<?php echo $reply['id']; ?>">
                    
                    <div class="reply-header-compact">
                        <div class="reply-branch-info">
                            <span class="msg-badge <?php echo $reply['notification_type']; ?>">
                                <?php echo strtoupper($reply['notification_type']); ?>
                            </span>
                            <strong><?php echo htmlspecialchars($reply['branch_name']); ?></strong>
                            <?php if (!$reply['reply_read_by_admin']): ?>
                                <span class="msg-badge" style="background: #ff4444; color: white;">NEW</span>
                            <?php endif; ?>
                        </div>
                        <div class="reply-timestamp">
                            <?php echo date('M d, h:i A', strtotime($reply['client_replied_at'])); ?>
                        </div>
                    </div>
                    
                    <!-- Your Message -->
                    <div class="chat-bubble bubble-sent">
                        <div class="bubble-label">You sent:</div>
                        <?php echo nl2br(htmlspecialchars($reply['notification_message'])); ?>
                    </div>
                    
                    <!-- Client Reply -->
                    <div class="chat-bubble bubble-received">
                        <div class="bubble-label">Client replied:</div>
                        <?php echo nl2br(htmlspecialchars($reply['client_reply'])); ?>
                        <div class="user-info">
                            <i class="fas fa-user"></i>
                            <?php echo htmlspecialchars($reply['replied_by_username']); ?>
                            <?php if ($reply['replied_by_user_id']): ?>
                                <span>(ID: <?php echo $reply['replied_by_user_id']; ?>)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <?php if (!$reply['reply_read_by_admin']): ?>
                        <div class="reply-actions">
                            <button class="btn-reply-action btn-read" data-id="<?php echo $reply['id']; ?>">
                                <i class="fas fa-check me-1"></i>Mark as Read
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
    <?php endif; ?>
    
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Auto-hide alerts
    setTimeout(function() {
        document.querySelectorAll('.alert-msg').forEach(function(alert) {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s';
            setTimeout(function() { alert.remove(); }, 500);
        });
    }, 3000);
    
    // Type selector
    document.querySelectorAll('.type-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('notification_type').value = this.dataset.type;
        });
    });
    
    // Mark as Read
    document.querySelectorAll('.btn-read').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const replyId = this.dataset.id;
            const formData = new FormData();
            formData.append('ajax_action', 'mark_reply_read');
            formData.append('notification_id', replyId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) location.reload();
                else alert('Error: ' + data.message);
            });
        });
    });
});
</script>

</body>
</html>

<?php
$conn->close();
?>