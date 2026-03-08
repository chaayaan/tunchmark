<?php
    ini_set('date.timezone', 'Asia/Dhaka');      // PHP timezone
    date_default_timezone_set('Asia/Dhaka');     // PHP timezone (backup)
    $host = "localhost";
    $user = "root";
    $pass = "";
    $db   = "billing_app";

    $conn = mysqli_connect($host, $user, $pass, $db);

    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }

    /* SET Bangladesh timezone (SESSION LEVEL) */
    mysqli_query($conn, "SET time_zone = '+06:00'");
?>
