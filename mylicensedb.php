<?php
/**
 * License Database Connection
 * Connects to the license_manager database for license verification
 */

// Database configuration
$license_db_host = 'localhost';
$license_db_name = 'license_manager';
$license_db_user = 'root'; // Change this to your database username
$license_db_pass = '';     // Change this to your database password

try {
    // Create PDO connection
    $pdo = new PDO(
        "mysql:host=$license_db_host;dbname=$license_db_name;charset=utf8mb4",
        $license_db_user,
        $license_db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    // Log the error (in production, log to file instead of displaying)
    error_log("License Database Connection Error: " . $e->getMessage());
    
    // For development, you can uncomment the line below
    // die("License Database Connection Failed: " . $e->getMessage());
    
    // For production, show a generic error
    die("Unable to connect to license database. Please contact support.");
}
?>