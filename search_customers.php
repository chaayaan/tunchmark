<?php
require 'auth.php';
require 'mydb.php';

header('Content-Type: application/json');

$query = isset($_GET['query']) ? trim($_GET['query']) : '';

if (strlen($query) < 1) {
    echo json_encode(['success' => false, 'data' => []]);
    exit;
}

// Search customers by name OR phone (case-insensitive, partial match)
$stmt = mysqli_prepare($conn, 
    "SELECT id, name, phone, address, manufacturer 
     FROM customers 
     WHERE name LIKE ? OR phone LIKE ?
     ORDER BY 
        CASE 
            WHEN phone LIKE ? THEN 1
            WHEN name LIKE ? THEN 2
            ELSE 3
        END,
        name ASC 
     LIMIT 10"
);

$searchTerm = "%{$query}%";
$exactSearchTerm = "{$query}%";
mysqli_stmt_bind_param($stmt, "ssss", $searchTerm, $searchTerm, $exactSearchTerm, $exactSearchTerm);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$customers = [];
while ($row = mysqli_fetch_assoc($result)) {
    $customers[] = $row;
}

mysqli_stmt_close($stmt);

echo json_encode([
    'success' => true,
    'data' => $customers
]);
?>