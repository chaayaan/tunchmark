<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'mydb.php';

// Set content type to JSON
header('Content-Type: application/json');

// Initialize response
$response = [
    'success' => false, 
    'data' => null,
    'message' => ''
];

try {
    // Check if ID parameter exists
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        $response['message'] = 'Customer ID is required';
        echo json_encode($response);
        exit;
    }

    $customerId = intval($_GET['id']);
    
    if ($customerId <= 0) {
        $response['message'] = 'Invalid Customer ID';
        echo json_encode($response);
        exit;
    }

    // Prepare and execute query
    $stmt = mysqli_prepare($conn, "SELECT id, name, phone, address, manufacturer FROM customers WHERE id = ? LIMIT 1");
    
    if (!$stmt) {
        $response['message'] = 'Database prepare error: ' . mysqli_error($conn);
        echo json_encode($response);
        exit;
    }

    mysqli_stmt_bind_param($stmt, "i", $customerId);
    
    if (!mysqli_stmt_execute($stmt)) {
        $response['message'] = 'Database execute error: ' . mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode($response);
        exit;
    }

    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $response['success'] = true;
        $response['data'] = [
            'id' => $row['id'],
            'name' => $row['name'] ?? '',
            'phone' => $row['phone'] ?? '',
            'address' => $row['address'] ?? '',
            'manufacturer' => $row['manufacturer'] ?? ''
        ];
        $response['message'] = 'Customer found successfully';
    } else {
        $response['message'] = 'Customer not found with ID: ' . $customerId;
    }
    
    mysqli_stmt_close($stmt);

} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

// Output JSON response
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>