<?php
require 'mydb.php';

// Get order ID from URL
$orderId = isset($_GET['id']) ? intval($_GET['id']) : 0;

$order = null;
$items = [];
$error = '';

if ($orderId > 0) {
    // Fetch order from database
    $stmt = mysqli_prepare($conn, "SELECT * FROM orders WHERE order_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $orderId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($res)) {
        $order = $row;
        
        // Fetch all items for this order
        $itemsStmt = mysqli_prepare($conn, "
            SELECT bi.*, 
                   i.name as item_name,
                   s.name as service_name,
                   s.price as service_price
            FROM bill_items bi
            LEFT JOIN items i ON bi.item_id = i.id
            LEFT JOIN services s ON bi.service_id = s.id
            WHERE bi.order_id = ?
            ORDER BY bi.bill_item_id ASC
        ");
        mysqli_stmt_bind_param($itemsStmt, "i", $orderId);
        mysqli_stmt_execute($itemsStmt);
        $itemsRes = mysqli_stmt_get_result($itemsStmt);
        
        while ($itemRow = mysqli_fetch_assoc($itemsRes)) {
            $items[] = $itemRow;
        }
        mysqli_stmt_close($itemsStmt);
        
        // Calculate total from items
        $order['calculated_total'] = 0;
        foreach ($items as $item) {
            $order['calculated_total'] += floatval($item['total_price']);
        }
    } else {
        $error = 'Order not found';
    }
    mysqli_stmt_close($stmt);
} else {
    $error = 'Invalid order ID';
}

// Convert gram to vori/ana/roti
function convertGramToVoriAna($gram) {
    if (!$gram || $gram <= 0) return '0 bhori 0 ana 0 roti 0 point';
    
    $totalPoints = round(($gram / 11.664) * 16 * 6 * 10);
    $bhori = floor($totalPoints / 960);
    $remainingPoints = $totalPoints % 960;
    $ana = floor($remainingPoints / 60);
    $remainingAfterAna = $remainingPoints % 60;
    $roti = floor($remainingAfterAna / 10);
    $point = $remainingAfterAna % 10;
    
    return "V:{$bhori} A:{$ana} R:{$roti} P:{$point}";
}

$currentDate = $order ? date('d/m/Y, g:i A', strtotime($order['created_at'])) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= $order ? htmlspecialchars($order['order_id']) : 'N/A' ?> - Rajaiswari</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: Arial, sans-serif;
            font-size: 12px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            line-height: 1.3;
            padding: 20px 10px;
        }
        
        .container {
            max-width: 400px;
            margin: 0 auto;
        }
        
        .action-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .btn {
            flex: 1;
            min-width: 120px;
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            background: rgba(255, 255, 255, 0.95);
            color: #764ba2;
        }
        
        .btn:hover {
            background: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }
        
        .receipt-container {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .center { 
            text-align: center; 
        }
        
        .company-logo { 
            width: 100%; 
            max-width: 280px;
            height: auto; 
            display: block; 
            margin: 0 auto 10px; 
        }
        
        hr { 
            border: none; 
            border-top: 1px dashed #000; 
            margin: 10px 0; 
        }
        
        /* Watermark Styles - Similar to thermal printer */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 70px;
            font-weight: 900;
            pointer-events: none;
            z-index: 1;
            white-space: nowrap;
            text-shadow: 0 0 3px rgba(255,255,255,0.8);
            letter-spacing: 3px;
            font-family: Arial, sans-serif;
        }
        
        .watermark.paid {
            color: rgba(40, 167, 69, 0.15);
        }
        
        .watermark.cancelled {
            color: rgba(220, 53, 69, 0.15);
        }
        
        .receipt-content {
            position: relative;
            z-index: 2;
        }
        
        .token-section {
            font-size: 11px;
            line-height: 1.4;
            margin: 8px 0;
        }
        
        .token-section strong {
            font-size: 12px;
        }
        
        .token-line {
            margin: 3px 0;
        }
        
        .items-section {
            margin: 10px 0;
            font-size: 12px;
            line-height: 1.2;
        }
        
        .item-block {
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px dotted #ccc;
        }
        
        .item-block:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .total-section {
            font-weight: bold;
            font-size: 14px;
            margin: 8px 0;
        }
        
        .status-line {
            font-size: 12px;
            font-weight: bold;
            margin-top: 5px;
        }
        
        .status-paid {
            color: #28a745;
        }
        
        .status-pending {
            color: #ffc107;
        }
        
        .status-cancelled {
            color: #dc3545;
        }
        
        .footer { 
            text-align: center; 
            margin-top: 10px; 
            font-size: 10px; 
            color: #666;
            border-top: 1px dashed #ccc;
            padding-top: 8px;
        }
        
        .error-container {
            background: white;
            border-radius: 15px;
            padding: 40px 20px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .error-message {
            color: #dc3545;
            font-size: 16px;
            font-weight: bold;
            margin: 20px 0;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .container {
                max-width: 80mm;
            }
            
            .action-bar {
                display: none !important;
            }
            
            .receipt-container {
                box-shadow: none;
                padding: 10px;
                border-radius: 0;
            }
            
            .watermark.paid {
                color: rgba(40, 167, 69, 0.1);
            }
            
            .watermark.cancelled {
                color: rgba(220, 53, 69, 0.1);
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 10px 5px;
            }
            
            .receipt-container {
                padding: 15px;
            }
            
            .btn {
                padding: 10px 16px;
                font-size: 13px;
                min-width: 100px;
            }
            
            .watermark {
                font-size: 50px;
            }
            
            .company-logo {
                max-width: 240px;
            }
        }
        
        @media (max-width: 360px) {
            .watermark {
                font-size: 40px;
            }
            
            .action-bar {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($error): ?>
            
            <div class="error-container">
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
                <p style="color: #666;">Please check the order ID and try again</p>
            </div>
            
        <?php elseif ($order): ?>
            
            <!-- Receipt -->
            <div class="receipt-container">
                <?php 
                // Add watermark based on status
                $status = strtolower($order['status']);
                if ($status === 'paid'): ?>
                    <div class="watermark paid">PAID</div>
                <?php elseif ($status === 'cancelled'): ?>
                    <div class="watermark cancelled">CANCELLED</div>
                <?php endif; ?>
                
                <div class="receipt-content">
                    <!-- Header -->
                    <div class="center">
                        <img src="receiptheader.png" alt="Rajaiswari" class="company-logo" onerror="this.style.display='none';">
                    </div>
                    
                    <hr>
                    
                    <!-- Token Section -->
                    <div class="token-section">
                        <strong>TOKEN</strong><br>
                        <div class="token-line">Date: <?= htmlspecialchars($currentDate) ?></div>
                        <div class="token-line">Token No: <?= htmlspecialchars($order['order_id']) ?></div>
                        <div class="token-line">Name: <?= htmlspecialchars($order['customer_name']) ?></div>
                        <div class="token-line">Mobile: <?= htmlspecialchars($order['customer_phone']) ?></div>
                        <?php if (!empty($order['customer_address'])): ?>
                        <div class="token-line">Address: <?= htmlspecialchars($order['customer_address']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($order['manufacturer'])): ?>
                        <div class="token-line">Manufacturer: <?= htmlspecialchars($order['manufacturer']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($order['box_no'])): ?>
                        <div class="token-line">Box No: <?= htmlspecialchars($order['box_no']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <hr>
                    
                    <!-- Items Section -->
                    <div class="items-section">
                        <strong>ITEMS:</strong><br><br>
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $index => $item): 
                                $weight = floatval($item['weight']);
                                $voriAna = convertGramToVoriAna($weight);
                            ?>
                            <div class="item-block">
                                Purpose: <?= htmlspecialchars($item['service_name'] ?? 'N/A') ?> | <?= htmlspecialchars($item['karat'] ?? '') ?><br>
                                Item: <?= htmlspecialchars($item['item_name'] ?? 'Unknown') ?> | Qty: <?= htmlspecialchars($item['quantity'] ?? '1') ?><br>
                                <?php if ($weight > 0): ?>
                                Weight: <?= number_format($weight, 2) ?> gm [<?= $voriAna ?>]<br>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; color: #999; padding: 10px;">
                                No items found
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <hr>
                    
                    <!-- Total Section -->
                    <div class="total-section">
                        Total Charge: <?= number_format($order['calculated_total'], 2) ?> Tk<br>
                        <div class="status-line status-<?= htmlspecialchars($status) ?>">
                            Payment Status: <?= htmlspecialchars(strtoupper($status)) ?>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Footer -->
                    <div class="footer">
                        THANK YOU | HAVE A GOOD DAY | CDev
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <!-- No Data State -->
            <div class="action-bar">
                <a href="dashboard.php" class="btn">
                    ← Back
                </a>
            </div>
            
            <div class="error-container">
                <div class="error-message">No Order Found</div>
                <p style="color: #666;">Unable to load order details</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>