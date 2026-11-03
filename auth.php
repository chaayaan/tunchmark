<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Client isolation
$current_dir = basename(dirname(__FILE__));
if (!isset($_SESSION['client_dir'])) {
    $_SESSION['client_dir'] = $current_dir;
}
if ($_SESSION['client_dir'] !== $current_dir) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['client_dir'] = $current_dir;
}

// Auto protect - except login and logout pages
$excluded_pages = ['index.php', 'logout.php'];
$current_page = basename($_SERVER['PHP_SELF']);

if (!in_array($current_page, $excluded_pages)) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit;
    }
}

// Kick out users who were deactivated while logged in
if (isset($_SESSION['user_id']) && !in_array($current_page, $excluded_pages)) {
    require_once 'mydb.php';
    if (isset($conn)) {
        $uidChk = (int)$_SESSION['user_id'];
        $chk = $conn->prepare("SELECT role, is_engineer, is_active, name, username FROM users WHERE id = ? LIMIT 1");
        $chk->bind_param("i", $uidChk);
        $chk->execute();
        $chkRow = $chk->get_result()->fetch_assoc();
        $chk->close();
        if (!$chkRow || (int)$chkRow['is_active'] !== 1) {
            session_unset();
            session_destroy();
            header("Location: index.php");
            exit;
        }
        // Refresh from DB on every request, so role/engineer/name changes apply
        // immediately and no longer depend on what index.php stored at login.
        $_SESSION['role']        = $chkRow['role'];
        $_SESSION['is_engineer'] = (int)$chkRow['is_engineer'];
        $_SESSION['username']    = $chkRow['username'];
        $_SESSION['name']        = $chkRow['name'] !== '' ? $chkRow['name'] : $chkRow['username'];
    }
}

// ========== ROLE HELPERS ==========
// Roles: admin (everything) | employee (current pages) | employee + is_engineer=1 (engineer)

function is_admin(): bool {
    return ($_SESSION['role'] ?? '') === 'admin';
}

// Admin always passes; otherwise needs the engineer flag
function can_access_engineering(): bool {
    return is_admin() || !empty($_SESSION['is_engineer']);
}

function require_admin(): void {
    if (!is_admin()) {
        header("Location: dashboard.php");
        exit;
    }
}

/**
 * Guard for every engineering page.
 * Call AFTER require 'mydb.php' so the flag is re-checked against the DB
 * (an admin changing a user's type takes effect immediately, no re-login).
 */
function require_engineering(): void {
    global $conn;

    if (is_admin()) return;

    if (isset($conn) && isset($_SESSION['user_id'])) {
        $uid  = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT role, is_engineer FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $_SESSION['role']        = $row['role'];
            $_SESSION['is_engineer'] = (int)$row['is_engineer'];
        }
    }

    if (!can_access_engineering()) {
        header("Location: order.php");
        exit;
    }
}