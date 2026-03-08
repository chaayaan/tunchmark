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