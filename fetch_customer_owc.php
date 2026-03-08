<?php
require 'auth.php';
require 'mydb.php';

header('Content-Type: application/json');

$customerId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($customerId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid customer ID'
    ]);
    exit;
}

// Fetch customer by ID
$stmt = mysqli_prepare($conn, 
    "SELECT id, name, phone, address, manufacturer 
     FROM customers 
     WHERE id = ? 
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $customerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    mysqli_stmt_close($stmt);
    echo json_encode([
        'success' => true,
        'data' => $row
    ]);
} else {
    mysqli_stmt_close($stmt);
    echo json_encode([
        'success' => false,
        'message' => 'Customer not found'
    ]);
}
?>