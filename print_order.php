<?php
require 'auth.php';
require 'mydb.php';

$orderId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($orderId <= 0) {
    die("Invalid order ID");
}

// Fetch order details
$stmt = mysqli_prepare($conn, "SELECT * FROM orders WHERE order_id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $orderId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$order) {
    die("Order not found");
}

// Fetch bill items with item and service names
$itemsQuery = "
    SELECT bi.*, 
           i.name as item_name, 
           s.name as service_name
    FROM bill_items bi
    LEFT JOIN items i ON bi.item_id = i.id
    LEFT JOIN services s ON bi.service_id = s.id
    WHERE bi.order_id = ?
";
$stmt = mysqli_prepare($conn, $itemsQuery);
mysqli_stmt_bind_param($stmt, "i", $orderId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$billItems = [];
$grandTotal = 0;
while ($row = mysqli_fetch_assoc($res)) {
    $billItems[] = $row;
    $grandTotal += floatval($row['total_price']);
}
mysqli_stmt_close($stmt);

// Function to convert gram to Vori/Ana/Roti/Point
function convertGramToVoriAna($gram) {
    if (!$gram || $gram <= 0) return '0 bhori 0 ana 0 roti 0 point';
    
    $totalPoints = round(($gram / 11.664) * 16 * 6 * 10);
    $bhori = floor($totalPoints / 960);
    $remainingPoints = $totalPoints % 960;
    $ana = floor($remainingPoints / 60);
    $remainingAfterAna = $remainingPoints % 60;
    $roti = floor($remainingAfterAna / 10);
    $point = $remainingAfterAna % 10;
    
    return "V:$bhori A:$ana R:$roti P:$point";
}

$currentDate = date('d/m/Y, h:i A');

// ==================== FORMAT 1: THERMAL RECEIPT (80mm) - ENGLISH ====================
function generateThermalReceiptEnglish($order, $billItems, $grandTotal, $currentDate, $orderId) {
    $itemsHtml = '';
    foreach ($billItems as $index => $item) {
        $weight = floatval($item['weight'] ?? 0);
        $voriAnaRoti = convertGramToVoriAna($weight);
        
        $itemsHtml .= 'Purpose: ' . htmlspecialchars($item['service_name'] ?? '') . ' | ' . htmlspecialchars($item['karat'] ?? '') . '<br>';
        $itemsHtml .= 'Item: ' . htmlspecialchars($item['item_name'] ?? '') . ' | Qty: ' . htmlspecialchars($item['quantity'] ?? '') . '<br>';
        $itemsHtml .= 'Weight: ' . number_format($weight, 2) . ' gm [' . $voriAnaRoti . ']<br>';
        if ($index < count($billItems) - 1) {
            $itemsHtml .= '<br>';
        }
    }
    
    return '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Order #' . htmlspecialchars($order['order_id']) . '</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
    <style>
        @import url(\'https://fonts.googleapis.com/css2?family=Alex+Brush&display=swap\');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: Arial, sans-serif; 
            font-size: 12px; 
            margin: 4px; 
            line-height: 1.3;
            max-width: 80mm;
        }
        
        .center { 
            text-align: center; 
        }
        
        .company-logo { 
            width: 66mm; 
            height: auto; 
            display: block; 
            margin: 0 auto; 
            max-width: 100%;
        }
        
        hr { 
            border: none; 
            border-top: 1px dashed #000; 
            margin: 4px 0; 
        }
        
        .footer { 
            text-align: center; 
            margin-top: 4px; 
            font-size: 10px; 
            color: #666;
        }
        
        .total-line { 
            font-weight: bold; 
            font-size: 14px; 
            text-align: left; 
            margin: 0; 
            padding: 0; 
        }
        
        .item-line { 
            font-size: 12px; 
            margin: 0; 
            padding: 0; 
            line-height: 1.2; 
        }
        
        .token-line { 
            font-size: 11px; 
            margin: 0; 
            padding: 0; 
            line-height: 1.4; 
        }
        
        .no-print {
            display: block;
            text-align: center;
            margin: 20px 0;
        }
        
        .qr-container {
            margin: 8px 0;
            background: white;
            padding: 4px;
            border: 1px solid #ddd;
        }
        
        .qr-label {
            font-size: 9px;
            margin-bottom: 2px;
            color: #666;
        }
        
        #qrCode {
            display: block;
            margin: 0 auto;
        }
        
        @media print {
            .no-print {
                display: none;
            }
            body {
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 14px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 4px;">
            🖨️ Print Receipt
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 14px; cursor: pointer; background: #6c757d; color: white; border: none; border-radius: 4px; margin-left: 10px;">
            ✖️ Close
        </button>
    </div>

    <div class="center">
        <img src="receiptheader.png" alt="Rajaiswari" class="company-logo" onerror="this.style.display=\'none\';">
    </div>
    
    <hr>
    
    <div class="token-line">
        <strong>TOKEN</strong><br>
        Date: ' . $currentDate . '<br>
        Token No: ' . htmlspecialchars($order['order_id']) . '<br>
        Customer ID: ' . htmlspecialchars($order['customer_id'] ?? 'N/A') . '<br>
        Name: ' . htmlspecialchars($order['customer_name']) . '<br>
        Mobile: ' . htmlspecialchars($order['customer_phone']) . '<br>
        ' . (!empty($order['customer_address']) ? 'Address: ' . htmlspecialchars($order['customer_address']) . '<br>' : '') . '
        ' . (!empty($order['manufacturer']) ? 'Manufacturer: ' . htmlspecialchars($order['manufacturer']) . '<br>' : '') . '
        ' . (!empty($order['box_no']) ? 'Box No: ' . htmlspecialchars($order['box_no']) . '<br>' : '') . '
    </div>
    
    <hr>
    
    <div class="item-line">
        <strong>ITEMS:</strong><br>
        ' . $itemsHtml . '
    </div>
    
    <hr>
    
    <div class="total-line">
        Total Charge: ' . number_format($grandTotal, 2) . ' Tk<br>
        Payment Status: ' . htmlspecialchars(strtoupper($order['status'])) . '
    </div>
    
    <hr>
    
    <!-- QR Code Container -->
    <div class="center qr-container">
        <div class="qr-label">Scan for details</div>
        <canvas id="qrCode"></canvas>
    </div>
    
    <div class="footer">
        THANK YOU | HAVE A GOOD DAY | CDev
    </div>

    <script>
        // Generate QR Code
        function generateQRCode() {
            try {
                const orderId = ' . $orderId . ';
                const baseUrl = window.location.origin + window.location.pathname.replace(\'print_order.php\', \'\');
                const qrText = baseUrl + \'view_bill.php?id=\' + orderId;
                
                const qr = qrcode(0, \'M\');
                qr.addData(qrText);
                qr.make();
                
                const canvas = document.getElementById(\'qrCode\');
                const ctx = canvas.getContext(\'2d\');
                const modules = qr.getModuleCount();
                const cellSize = 3;
                
                canvas.width = cellSize * modules;
                canvas.height = cellSize * modules;
                
                ctx.fillStyle = \'white\';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                
                ctx.fillStyle = \'black\';
                for (let row = 0; row < modules; row++) {
                    for (let col = 0; col < modules; col++) {
                        if (qr.isDark(row, col)) {
                            ctx.fillRect(col * cellSize, row * cellSize, cellSize, cellSize);
                        }
                    }
                }
            } catch (error) {
                console.error(\'QR generation error:\', error);
                document.querySelector(\'.qr-container\').style.display = \'none\';
            }
        }
        
        window.addEventListener(\'load\', function() {
            generateQRCode();
            
            setTimeout(function() {
                if (window.opener) {
                    window.print();
                }
            }, 800);
        });
    </script>
</body>
</html>';
}

// ==================== FORMAT 2: A4 FORMAT - ENGLISH ====================
function generateA4FormatEnglish($order, $billItems, $grandTotal, $currentDate, $orderId) {
    $itemsHtml = '';
    foreach ($billItems as $item) {
        $weight = floatval($item['weight'] ?? 0);
        $itemName = $item['item_name'] ?? '';
        $serviceName = $item['service_name'] ?? '';
        $karat = $item['karat'] ?? '';
        
        $itemsHtml .= '
        <tr>
          <td>' . htmlspecialchars($itemName) . '</td>
          <td>' . htmlspecialchars($serviceName) . '</td>
          <td>' . htmlspecialchars($karat) . '</td>
          <td>' . number_format($weight, 2) . '</td>
        </tr>';
    }
    
    // Add empty rows to fill space
    $emptyRowsNeeded = max(0, 3 - count($billItems));
    for ($i = 0; $i < $emptyRowsNeeded; $i++) {
        $itemsHtml .= '
        <tr>
          <td>&nbsp;</td>
          <td>&nbsp;</td>
          <td>&nbsp;</td>
          <td>&nbsp;</td>
        </tr>';
    }
    
    $dateOnly = date('d/m/Y');
    $timeOnly = date('h:i A');
    
    return '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Order #' . htmlspecialchars($order['order_id']) . '</title>
    <style>
      @page {
        size: A4;
        margin: 20mm;
      }
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }
      body { 
        font-family: Arial, sans-serif; 
        font-size: 16px; 
        padding: 20px;
        width: 210mm;
        min-height: 148.5mm;
      }
      .header {
        text-align: center;
        font-weight: bold;
        font-size: 22px;
        text-decoration: underline;
        margin-bottom: 12px;
      }
      .customer-section {
        margin-bottom: 12px;
        padding-left: 20px;
      }
      .customer-row {
        display: flex;
        margin-bottom: 2px;
        min-height: 20px;
        align-items: center;
      }
      .customer-col {
        display: flex;
        align-items: center;
      }
      .customer-col.left {
        flex: 2;
        padding-right: 30px;
      }
      .customer-col.right {
        flex: 1;
      }
      .customer-col.right .field-label {
        min-width: 80px;
      }
      .field-label {
        font-weight: bold;
        min-width: 140px;
        white-space: nowrap;
      }
      .field-colon {
        margin: 0 5px 0 0;
      }
      .field-value {
        flex: 1;
      }
      .item-section {
        margin-top: 12px;
      }
      table {
        width: 100%;
        border-collapse: collapse;
        border: 2px solid #000;
      }
      .table-header {
        font-weight: bold;
        font-size: 18px;
        text-align: center;
        padding: 4px;
        border: 2px solid #000;
        background-color: #fff;
      }
      th {
        padding: 4px;
        text-align: center;
        border: 1px solid #000;
        font-weight: bold;
        font-size: 16px;
        background-color: #fff;
      }
      td {
        padding: 4px 6px;
        border: 1px solid #000;
        text-align: center;
        min-height: 24px;
        line-height: 1.2;
      }
      .footer-row {
        display: flex;
        border: 2px solid #000;
        border-top: 1px solid #000;
      }
      .footer-cell {
        flex: 1;
        padding: 4px 8px;
        font-weight: bold;
        font-size: 16px;
      }
      .footer-cell:first-child {
        border-right: 1px solid #000;
      }
      .note {
        font-size: 12px;
        margin-top: 6px;
        line-height: 1.5;
        padding-left: 20px;
      }
      .no-print {
        display: block;
        text-align: center;
        margin: 20px 0;
      }
      @media print {
        .no-print {
          display: none;
        }
        body {
          padding: 20mm;
        }
      }
    </style>
</head>
<body>
    
    <div class="no-print">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 14px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 4px;">
            🖨️ Print Receipt
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 14px; cursor: pointer; background: #6c757d; color: white; border: none; border-radius: 4px; margin-left: 10px;">
            ✖️ Close
        </button>
    </div>
    
    <div class="header">Customer Details</div>
    
    <div class="customer-section">
      <div class="customer-row">
        <div class="customer-col left">
          <span class="field-label">Customer No</span>
          <span class="field-colon">:</span>
          <span class="field-value">' . htmlspecialchars($order['customer_id'] ?? '') . '</span>
        </div>
        <div class="customer-col right">
          <span class="field-label">Token No</span>
          <span class="field-colon">:</span>
          <span class="field-value">' . htmlspecialchars($order['order_id']) . '</span>
        </div>
      </div>
      
      <div class="customer-row">
        <div class="customer-col left">
          <span class="field-label">Customer Name</span>
          <span class="field-colon">:</span>
          <span class="field-value">' . htmlspecialchars($order['customer_name']) . '</span>
        </div>
        <div class="customer-col right">
          <span class="field-label">Date</span>
          <span class="field-colon">:</span>
          <span class="field-value">' . $dateOnly . '</span>
        </div>
      </div>
      
      <div class="customer-row">
        <div class="customer-col left">
          <span class="field-label">Mobile</span>
          <span class="field-colon">:</span>
          <span class="field-value">' . htmlspecialchars($order['customer_phone']) . '</span>
        </div>
        <div class="customer-col right">
          <span class="field-label">Time</span>
          <span class="field-colon">:</span>
          <span class="field-value">' . $timeOnly . '</span>
        </div>
      </div>
      
      <div class="customer-row">
        <div class="customer-col left">
          <span class="field-label">Address</span>
          <span class="field-colon">:</span>
          <span class="field-value">' . htmlspecialchars($order['customer_address'] ?? '') . '</span>
        </div>
      </div>
      
      <div class="customer-row">
        <div class="customer-col left">
          <span class="field-label">Manufacturer</span>
          <span class="field-colon">:</span>
          <span class="field-value">' . htmlspecialchars($order['manufacturer'] ?? '') . '</span>
        </div>
      </div>
      
      <div class="customer-row">
        <div class="customer-col left">
          <span class="field-label">Box no</span>
          <span class="field-colon">:</span>
          <span class="field-value">' . htmlspecialchars($order['box_no'] ?? '') . '</span>
        </div>
      </div>
    </div>

    <div class="item-section">
      <table>
        <thead>
          <tr>
            <th colspan="4" class="table-header">ITEM DETAILS</th>
          </tr>
          <tr>
            <th>Items</th>
            <th>Services</th>
            <th>Marking</th>
            <th>Weight</th>
          </tr>
        </thead>
        <tbody>
          ' . $itemsHtml . '
        </tbody>
      </table>

      <div class="footer-row">
        <div class="footer-cell">Total Amount : ' . number_format($grandTotal, 2) . ' TK</div>
        <div class="footer-cell">Payment Status : ' . htmlspecialchars(strtoupper($order['status'])) . '</div>
      </div>
    </div>

    <div class="note">
      The jewellery/article tested at the point of soldering chemical plated jewellery will show a low or fluctuating reading.<br>
      We are not responsible for any melting defect.<br>
      Maximum Diff: (+/-) 0.30%.
    </div>

    <script>
        window.addEventListener(\'load\', function() {
            setTimeout(function() {
                if (window.opener) {
                    window.print();
                }
            }, 800);
        });
    </script>
</body>
</html>';
}

// ==================== FORMAT 3: THERMAL RECEIPT (80mm) - BANGLA ====================
function generateThermalReceiptBangla($order, $billItems, $grandTotal, $currentDate, $orderId) {
    // Helper function to convert English format to Bangla format
    function convertToBanglaFormat($voriAnaRoti) {
        // Extract values from format like "V:1 A:6 R:1 P:6"
        preg_match('/V:(\d+) A:(\d+) R:(\d+) P:(\d+)/', $voriAnaRoti, $matches);
        if (count($matches) == 5) {
            return "[ভ:{$matches[1]} আ:{$matches[2]} র:{$matches[3]} প:{$matches[4]}]";
        }
        return $voriAnaRoti;
    }
    
    $itemsHtml = '';
    foreach ($billItems as $index => $item) {
        $weight = floatval($item['weight'] ?? 0);
        $voriAnaRoti = convertGramToVoriAna($weight);
        $voriAnaRotiBangla = convertToBanglaFormat($voriAnaRoti);
        $itemName = $item['item_name'] ?? $item['service_name'] ?? '';
        
        $itemsHtml .= 'উদ্দেশ্য: ' . htmlspecialchars($item['service_name'] ?? '') . ' | ' . htmlspecialchars($item['karat'] ?? '') . '<br>';
        $itemsHtml .= 'পণ্য: ' . htmlspecialchars($itemName) . ' | পরিমাণ: ' . htmlspecialchars($item['quantity'] ?? '') . '<br>';
        $itemsHtml .= 'ওজন: ' . number_format($weight, 2) . ' গ্রাম ' . $voriAnaRotiBangla . '<br>';
        if ($index < count($billItems) - 1) {
            $itemsHtml .= '<br>';
        }
    }
    
    $statusText = $order['status'] === 'paid' ? 'পরিশোধিত' : 'বাকি';
    
    return '
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Order #' . htmlspecialchars($order['order_id']) . '</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: \'SolaimanLipi\', \'Kalpurush\', Arial, sans-serif;
            font-size: 12px; 
            margin: 4px; 
            line-height: 1.3;
            max-width: 80mm;
        }
        
        .center { 
            text-align: center; 
        }
        
        .company-logo { 
            width: 66mm; 
            height: auto; 
            display: block; 
            margin: 0 auto; 
            max-width: 100%;
        }
        
        hr { 
            border: none; 
            border-top: 1px dashed #000; 
            margin: 4px 0; 
        }
        
        .footer { 
            text-align: center; 
            margin-top: 4px; 
            font-size: 10px; 
            color: #666;
        }
        
        .total-line { 
            font-weight: bold; 
            font-size: 14px; 
            text-align: left; 
            margin: 0; 
            padding: 0; 
        }
        
        .item-line { 
            font-size: 12px; 
            margin: 0; 
            padding: 0; 
            line-height: 1.2; 
        }
        
        .token-line { 
            font-size: 11px; 
            margin: 0; 
            padding: 0; 
            line-height: 1.4; 
        }
        
        .no-print {
            display: block;
            text-align: center;
            margin: 20px 0;
        }
        
        .qr-container {
            margin: 8px 0;
            background: white;
            padding: 4px;
            border: 1px solid #ddd;
        }
        
        .qr-label {
            font-size: 9px;
            margin-bottom: 2px;
            color: #666;
        }
        
        #qrCode {
            display: block;
            margin: 0 auto;
        }
        
        @media print {
            .no-print {
                display: none;
            }
            body {
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 14px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 4px;">
            🖨️ প্রিন্ট করুন
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 14px; cursor: pointer; background: #6c757d; color: white; border: none; border-radius: 4px; margin-left: 10px;">
            ✖️ বন্ধ করুন
        </button>
    </div>

    <div class="center">
        <img src="receiptheader.png" alt="রাজাইশ্বরী" class="company-logo" onerror="this.style.display=\'none\';">
    </div>
    
    <hr>
    
    <div class="token-line">
        <strong>টোকেন</strong><br>
        তারিখ: ' . $currentDate . '<br>
        টোকেন নং: ' . htmlspecialchars($order['order_id']) . '<br>
        গ্রাহক আইডি: ' . htmlspecialchars($order['customer_id'] ?? 'নেই') . '<br>
        নাম: ' . htmlspecialchars($order['customer_name']) . '<br>
        মোবাইল: ' . htmlspecialchars($order['customer_phone']) . '<br>
        ' . (!empty($order['customer_address']) ? 'ঠিকানা: ' . htmlspecialchars($order['customer_address']) . '<br>' : '') . '
        ' . (!empty($order['manufacturer']) ? 'প্রস্তুতকারক: ' . htmlspecialchars($order['manufacturer']) . '<br>' : '') . '
        ' . (!empty($order['box_no']) ? 'বাক্স নং: ' . htmlspecialchars($order['box_no']) . '<br>' : '') . '
    </div>
    
    <hr>
    
    <div class="item-line">
        <strong>পণ্য:</strong><br>
        ' . $itemsHtml . '
    </div>
    
    <hr>
    
    <div class="total-line">
        মোট চার্জ: ' . number_format($grandTotal, 2) . ' টাকা<br>
        পেমেন্ট স্ট্যাটাস: ' . $statusText . '
    </div>
    
    <hr>
    
    <!-- QR Code Container -->
    <div class="center qr-container">
        <div class="qr-label">বিস্তারিত দেখতে স্ক্যান করুন</div>
        <canvas id="qrCode"></canvas>
    </div>
    
    <div class="footer">
        ধন্যবাদ | আপনার শুভ দিন হোক | CDev
    </div>

    <script>
        // Generate QR Code
        function generateQRCode() {
            try {
                const orderId = ' . $orderId . ';
                const baseUrl = window.location.origin + window.location.pathname.replace(\'print_order.php\', \'\');
                const qrText = baseUrl + \'view_bill.php?id=\' + orderId;
                
                const qr = qrcode(0, \'M\');
                qr.addData(qrText);
                qr.make();
                
                const canvas = document.getElementById(\'qrCode\');
                const ctx = canvas.getContext(\'2d\');
                const modules = qr.getModuleCount();
                const cellSize = 3;
                
                canvas.width = cellSize * modules;
                canvas.height = cellSize * modules;
                
                ctx.fillStyle = \'white\';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                
                ctx.fillStyle = \'black\';
                for (let row = 0; row < modules; row++) {
                    for (let col = 0; col < modules; col++) {
                        if (qr.isDark(row, col)) {
                            ctx.fillRect(col * cellSize, row * cellSize, cellSize, cellSize);
                        }
                    }
                }
            } catch (error) {
                console.error(\'QR generation error:\', error);
                document.querySelector(\'.qr-container\').style.display = \'none\';
            }
        }
        
        window.addEventListener(\'load\', function() {
            generateQRCode();
            
            setTimeout(function() {
                if (window.opener) {
                    window.print();
                }
            }, 800);
        });
    </script>
</body>
</html>';
}

// ==================== SELECT WHICH FORMAT TO USE ====================
// Uncomment ONE of the following lines to choose your format:

// FORMAT 1: Thermal Receipt - English (Default)
echo generateThermalReceiptEnglish($order, $billItems, $grandTotal, $currentDate, $orderId);

// FORMAT 2: A4 Format - English
// echo generateA4FormatEnglish($order, $billItems, $grandTotal, $currentDate, $orderId);

// FORMAT 3: Thermal Receipt - Bangla
// echo generateThermalReceiptBangla($order, $billItems, $grandTotal, $currentDate, $orderId);

?>