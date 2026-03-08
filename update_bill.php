<?php 
require 'auth.php';
include 'mydb.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: edit_orders.php");
    exit;
}

$orderId = intval($_POST['order_id'] ?? 0);
$customerName = trim($_POST['customer_name'] ?? '');
$customerPhone = trim($_POST['customer_phone'] ?? '');
$customerAddress = trim($_POST['customer_address'] ?? '');
$manufacturer = trim($_POST['manufacturer'] ?? '');
$boxNo = trim($_POST['box_no'] ?? '');

// Validate status
$status = 'pending';
if (isset($_POST['status']) && in_array($_POST['status'], ['paid', 'pending', 'cancelled'])) {
    $status = $_POST['status'];
}

$itemsRaw = $_POST['items'] ?? [];

if ($orderId <= 0) {
    die("Invalid order ID.");
}

// Validate required fields
if (empty($customerName) || empty($customerPhone)) {
    die("Customer name and phone are required.");
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Update order table
    $updateOrderSql = "UPDATE orders 
                       SET customer_name = ?, 
                           customer_phone = ?, 
                           customer_address = ?, 
                           manufacturer = ?,
                           box_no = ?,
                           status = ?,
                           updated_at = NOW()
                       WHERE order_id = ?";
    
    $stmt = mysqli_prepare($conn, $updateOrderSql);
    if (!$stmt) {
        throw new Exception("Prepare order update failed: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "ssssssi", 
        $customerName, 
        $customerPhone, 
        $customerAddress,
        $manufacturer,
        $boxNo,
        $status, 
        $orderId
    );
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Order update failed: " . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);

    // Process items
    $existingItemIds = [];
    $grandTotal = 0.0;
    
    foreach ($itemsRaw as $it) {
        $billItemId = intval($it['bill_item_id'] ?? 0);
        $itemId = intval($it['item_id'] ?? 0);
        $serviceId = intval($it['service_id'] ?? 0);
        $karat = trim($it['karat'] ?? '');
        $weight = floatval($it['weight'] ?? 0);
        $quantity = intval($it['quantity'] ?? 1);
        $unitPrice = floatval($it['unit_price'] ?? 0);
        $totalPrice = $quantity * $unitPrice;
        
        // Skip invalid items
        if ($itemId <= 0 || $serviceId <= 0 || $quantity <= 0) {
            continue;
        }
        
        $grandTotal += $totalPrice;
        
        if ($billItemId > 0) {
            // Update existing item
            $existingItemIds[] = $billItemId;
            
            $updateItemSql = "UPDATE bill_items 
                             SET item_id = ?, 
                                 service_id = ?, 
                                 karat = ?, 
                                 weight = ?, 
                                 quantity = ?, 
                                 unit_price = ?, 
                                 total_price = ?
                             WHERE bill_item_id = ? AND order_id = ?";
            
            $itemStmt = mysqli_prepare($conn, $updateItemSql);
            if (!$itemStmt) {
                throw new Exception("Prepare item update failed: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($itemStmt, "iisdiidii", 
                $itemId, 
                $serviceId, 
                $karat, 
                $weight, 
                $quantity, 
                $unitPrice, 
                $totalPrice,
                $billItemId,
                $orderId
            );
            
            if (!mysqli_stmt_execute($itemStmt)) {
                throw new Exception("Item update failed: " . mysqli_stmt_error($itemStmt));
            }
            mysqli_stmt_close($itemStmt);
            
        } else {
            // Insert new item
            $insertItemSql = "INSERT INTO bill_items 
                             (order_id, item_id, service_id, karat, weight, quantity, unit_price, total_price) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $itemStmt = mysqli_prepare($conn, $insertItemSql);
            if (!$itemStmt) {
                throw new Exception("Prepare item insert failed: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($itemStmt, "iiisdiid", 
                $orderId,
                $itemId, 
                $serviceId, 
                $karat, 
                $weight, 
                $quantity, 
                $unitPrice, 
                $totalPrice
            );
            
            if (!mysqli_stmt_execute($itemStmt)) {
                throw new Exception("Item insert failed: " . mysqli_stmt_error($itemStmt));
            }
            mysqli_stmt_close($itemStmt);
        }
    }
    
    // Delete items that were removed (not in the submitted form)
    if (!empty($existingItemIds)) {
        $placeholders = implode(',', array_fill(0, count($existingItemIds), '?'));
        $deleteSql = "DELETE FROM bill_items 
                     WHERE order_id = ? AND bill_item_id NOT IN ($placeholders)";
        
        $deleteStmt = mysqli_prepare($conn, $deleteSql);
        if (!$deleteStmt) {
            throw new Exception("Prepare delete failed: " . mysqli_error($conn));
        }
        
        $types = 'i' . str_repeat('i', count($existingItemIds));
        $params = array_merge([$orderId], $existingItemIds);
        mysqli_stmt_bind_param($deleteStmt, $types, ...$params);
        
        if (!mysqli_stmt_execute($deleteStmt)) {
            throw new Exception("Delete items failed: " . mysqli_stmt_error($deleteStmt));
        }
        mysqli_stmt_close($deleteStmt);
    } else {
        // If no existing items kept, delete all old items
        $deleteAllSql = "DELETE FROM bill_items WHERE order_id = ?";
        $deleteStmt = mysqli_prepare($conn, $deleteAllSql);
        mysqli_stmt_bind_param($deleteStmt, "i", $orderId);
        mysqli_stmt_execute($deleteStmt);
        mysqli_stmt_close($deleteStmt);
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    // Redirect with success
    header("Location: edit_bills.php?updated=1&order_id=" . $orderId);
    exit;
    
} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($conn);
    die("Update failed: " . $e->getMessage());
}
?>