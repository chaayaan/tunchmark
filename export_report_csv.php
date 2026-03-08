<?php
require 'auth.php';
if (!in_array($_SESSION['role'], ['admin','employee'])) {
    header("Location: dashboard.php");
    exit;
}

include 'mydb.php';

// Build query with same filters as reports.php
$where = [];
$params = [];
$types = "";

if (!empty($_GET['order_id'])) {
    $where[] = "o.order_id = ?";
    $params[] = intval($_GET['order_id']);
    $types .= "i";
}

if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $where[] = "DATE(o.created_at) BETWEEN ? AND ?";
    $params[] = $_GET['from_date'];
    $params[] = $_GET['to_date'];
    $types .= "ss";
} elseif (!empty($_GET['year'])) {
    $where[] = "YEAR(o.created_at) = ?";
    $params[] = intval($_GET['year']);
    $types .= "i";
}

$serviceFilter = !empty($_GET['service']) ? intval($_GET['service']) : 0;
if ($serviceFilter > 0) {
    $where[] = "bi.service_id = ?";
    $params[] = $serviceFilter;
    $types .= "i";
}

$whereClause = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

// Get all orders (no pagination for export)
$sql = "SELECT 
            o.order_id,
            o.customer_name,
            o.customer_phone,
            o.status,
            o.created_at,
            GROUP_CONCAT(
                CONCAT(
                    COALESCE(i.name, '-'), '|',
                    COALESCE(s.name, '-'), '|',
                    bi.quantity, '|',
                    bi.unit_price, '|',
                    bi.total_price
                ) 
                SEPARATOR '|||'
            ) AS items_data,
            SUM(bi.total_price) AS total_amount
        FROM orders o
        JOIN bill_items bi ON o.order_id = bi.order_id
        LEFT JOIN items i ON bi.item_id = i.id
        LEFT JOIN services s ON bi.service_id = s.id"
        . $whereClause . 
        " GROUP BY o.order_id
        ORDER BY o.order_id DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

// Generate filename with date range
$filename = 'billing_report_';
if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $filename .= $_GET['from_date'] . '_to_' . $_GET['to_date'];
} elseif (!empty($_GET['year'])) {
    $filename .= 'year_' . $_GET['year'];
} else {
    $filename .= date('Y-m-d');
}
$filename .= '.csv';

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for proper Excel UTF-8 handling
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write header row
fputcsv($output, [
    'Order ID',
    'Customer Name',
    'Customer Phone',
    'Status',
    'Date',
    'Items & Services',
    'Total Amount (৳)'
]);

// Write data rows
while ($row = mysqli_fetch_assoc($res)) {
    // Format items data
    $items_text = '';
    if (!empty($row['items_data'])) {
        $items_raw = explode('|||', $row['items_data']);
        $items_array = [];
        foreach ($items_raw as $item_str) {
            $item_parts = explode('|', $item_str);
            if (count($item_parts) >= 5) {
                $items_array[] = sprintf(
                    "%s - %s (%s × ৳%s = ৳%s)",
                    $item_parts[0], // item name
                    $item_parts[1], // service name
                    number_format((float)$item_parts[2], 0), // quantity
                    number_format((float)$item_parts[3], 2), // unit price
                    number_format((float)$item_parts[4], 2)  // total
                );
            }
        }
        $items_text = implode('; ', $items_array);
    }
    
    fputcsv($output, [
        $row['order_id'],
        $row['customer_name'],
        $row['customer_phone'],
        ucfirst($row['status']),
        date('Y-m-d', strtotime($row['created_at'])),
        $items_text,
        number_format($row['total_amount'], 2)
    ]);
}

fclose($output);
mysqli_stmt_close($stmt);
mysqli_close($conn);
exit;