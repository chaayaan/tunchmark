<?php
require 'auth.php';
require 'mydb.php';

// Initialize variables
$order_data = null;
$bill_items = [];
$items = [];
$services = [];
$report_created = false;
$report_id = null;
$report_data = null;

// Fetch active items for dropdown
$itemsQuery = "SELECT id, name FROM items WHERE is_active = 1 ORDER BY name ASC";
$itemsResult = mysqli_query($conn, $itemsQuery);
if ($itemsResult) {
    while ($row = mysqli_fetch_assoc($itemsResult)) {
        $items[] = $row;
    }
}

// Fetch active services for dropdown (only Hallmark services)
$servicesQuery = "SELECT id, name FROM services WHERE is_active = 1 AND name LIKE '%hallmark%' ORDER BY name ASC";
$servicesResult = mysqli_query($conn, $servicesQuery);
if ($servicesResult) {
    while ($row = mysqli_fetch_assoc($servicesResult)) {
        $services[] = $row;
    }
}

// Handle order fetch
if (isset($_POST['fetch_order'])) {
    $order_id = intval($_POST['order_id']);
    $stmt = mysqli_prepare($conn, "SELECT order_id, customer_name, manufacturer, customer_address FROM orders WHERE order_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $order_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$order_data) {
        $error = "Order not found!";
    } else {
        // Fetch bill items for this order (only hallmark services)
        $billItemsQuery = "SELECT bi.bill_item_id, bi.weight, bi.karat, bi.quantity, i.name as item_name, s.name as service_name 
                          FROM bill_items bi 
                          JOIN items i ON bi.item_id = i.id 
                          JOIN services s ON bi.service_id = s.id 
                          WHERE bi.order_id = ? AND s.name LIKE '%hallmark%'";
        $stmt = mysqli_prepare($conn, $billItemsQuery);
        mysqli_stmt_bind_param($stmt, "i", $order_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $bill_items[] = $row;
        }
        mysqli_stmt_close($stmt);
        
        if (empty($bill_items)) {
            $error = "No hallmark service bill items found for this order!";
        }
    }
}

// Handle report submission
if (isset($_POST['submit_report']) && !isset($_GET['report_id'])) {
    $order_id = intval($_POST['order_id']);
    $customer_name = trim($_POST['customer_name']);
    $customer_address = trim($_POST['customer_address']);
    $manufacturer = trim($_POST['manufacturer']);
    $bill_item_id = intval($_POST['bill_item_id']);
    $item_name = trim($_POST['item_name']);
    $service_name = trim($_POST['service_name']);
    $weight = floatval($_POST['weight']);
    $quantity = intval($_POST['quantity']);
    $hallmark = trim($_POST['hallmark']);
    
    // Insert into customer_reports with all editable fields including manufacturer
    $stmt = mysqli_prepare($conn, "INSERT INTO customer_reports (order_id, customer_name, item_name, service_name, weight, quantity, hallmark, address, manufacturer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "isssdisss", $order_id, $customer_name, $item_name, $service_name, $weight, $quantity, $hallmark, $customer_address, $manufacturer);
    mysqli_stmt_execute($stmt);

    $report_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    
    // Redirect to prevent duplicate insert on refresh
    header("Location: create_hallmark_report.php?report_id=" . $report_id);
    exit;
}

// Fetch existing report if report_id in URL
if (isset($_GET['report_id'])) {
    $report_id = intval($_GET['report_id']);
    $stmt = mysqli_prepare($conn, "SELECT cr.* FROM customer_reports cr WHERE cr.id = ?");
    mysqli_stmt_bind_param($stmt, "i", $report_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $report_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($report_data) {
        $report_created = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Hallmark Report - Rajaiswari Hallmarking Center</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fa;
        }
        
        /* Hallmark Report Styles */
        .hallmark-preview {
            width: auto;
            max-width: 750px;
            margin: 20px auto;
            background: white;
            padding: 0;
        }

        #reportPreview {
            background: white;
            padding: 0;
            position: relative;
        }

        /* Watermark styling */
        #reportPreview::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-25deg);
            width: 250px;
            height: 250px;
            background-image: url('Varifiedstamp.png');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            opacity: 0.25;
            z-index: 1;
            pointer-events: none;
        }

        #reportPreview > * {
            position: relative;
            z-index: 2;
        }

        .report-header-title {
            text-align: center;
            color: #3eb1e3;
            font-size: 32px;
            font-weight: bold;
            margin: 0;
            padding: 2px 0;
            letter-spacing: 2px;
            line-height: 1;
        }

        .hallmark-dotted {
            border-top: 2.5px dotted #000;
            margin: 0;
        }

        .hallmark-info-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 3px 8px 3px 8px;
            margin: 0;
        }

        .hallmark-left-info {
            flex: 1;
            padding: 0;
            line-height: 1.2;
        }

        .customer-info-line {
            margin: 0;
            padding: 0;
            font-size: 15px;
            line-height: 1.4;
            display: block;
            color: #000;
            font-weight: 600;
        }

        .customer-info-line.customer-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .info-label {
            display: inline-block;
            min-width: 110px;
            text-align: left;
            font-weight: 600;
        }

        .info-colon {
            display: inline;
            margin: 0 3px;
        }

        .info-value {
            display: inline;
            font-weight: 600;
        }

        .qr-section {
            width: 100px;
            text-align: center;
            padding: 0;
            margin-left: 8px;
            flex-shrink: 0;
        }

        #qrcode {
            margin: 0;
            line-height: 0;
        }

        #qrcode img {
            display: block;
            margin: 0 auto;
        }

        .qr-date {
            font-size: 10px;
            color: #000;
            font-weight: 700;
            line-height: 1.2;
            margin: 1px 0 0 0;
            padding: 0;
        }

        .main-box {
            border: 2.5px solid #000;
            display: flex;
            margin: 0 8px;
            height: 100px;
        }

        .checkbox-section {
            flex: 1;
            padding: 8px 12px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 5px 15px;
            align-content: center;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            font-size: 12px;
            line-height: 1;
            font-weight: 600;
            color: #000;
        }
        
        .checkbox-box {
            width: 14px;
            height: 14px;
            border: 2px solid #000;
            margin-right: 4px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            line-height: 1;
        }

        .hallmark-section {
            width: 240px;
            border-left: 2.5px solid #000;
            display: flex;
            flex-direction: column;
        }

        .hallmark-value-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 2.5px solid #000;
            padding: 8px 12px;
            overflow: hidden;
        }

        .hallmark-value {
            font-size: 40px;
            font-weight: bold;
            line-height: 1;
            color: #000;
            text-align: center;
            word-wrap: break-word;
            word-break: break-word;
            max-width: 100%;
            font-family: 'Times New Roman', Times, serif;
        }

        .hallmark-label {
            font-size: 15px;
            font-weight: 700;
            text-align: center;
            padding: 4px;
            color: #000;
            line-height: 1;
            font-family: 'Times New Roman', Times, serif;
        }

        .weight-conversion {
            font-size: 13px;
            color: #000;
            font-weight: 600;
            margin-left: 0;
        }
        
        .bill-item-card {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 8px 12px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .bill-item-card:hover {
            border-color: #007bff;
            background: #f0f8ff;
        }
        
        .bill-item-card.selected {
            border-color: #28a745;
            background: #d4edda;
        }
        
        .editable-field {
            background-color: #fff3cd;
            border: 2px solid #ffc107;
        }
        
        @media print {
            body {
                background: white;
            }
            
            .form-section,
            .btn,
            .card-header,
            .alert,
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <?php if (!$report_created): ?>
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">📋 Generate Hallmark Report</h3>
            </div>
            <div class="card-body">
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="order_id" class="form-label">Order ID <span class="text-danger">*</span></label>
                            <input type="number" id="order_id" name="order_id" class="form-control" required value="<?php echo isset($_POST['order_id']) ? intval($_POST['order_id']) : ''; ?>">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <button type="submit" name="fetch_order" class="btn btn-primary">🔍 Fetch Order</button>
                        </div>
                    </div>
                </form>
                
                <?php if ($order_data && !empty($bill_items)): ?>
                    <!-- <div class="alert alert-warning">
                        <strong>📝 Note:</strong> Customer Name, Manufacturer, and Address fields are editable. You can modify them before generating the report.
                    </div> -->
                    
                    <div class="mb-4">
                        <h5 class="mb-3">Select Bill Item:</h5>
                        <?php foreach ($bill_items as $index => $bill_item): ?>
                        <div class="bill-item-card" onclick="selectBillItem(<?php echo $index; ?>)">
                            <input type="radio" name="selected_bill_item" id="bill_item_<?php echo $index; ?>" value="<?php echo $index; ?>" <?php echo $index === 0 ? 'checked' : ''; ?> style="float: left; margin-right: 8px; margin-top: 2px;">
                            <label for="bill_item_<?php echo $index; ?>" style="cursor: pointer; display: block; overflow: hidden; font-size: 14px;">
                                <strong>Item:</strong> <?php echo htmlspecialchars($bill_item['item_name']); ?> | 
                                <strong>Weight:</strong> <?php echo htmlspecialchars($bill_item['weight']); ?> Gm | 
                                <strong>Qty:</strong> <?php echo htmlspecialchars($bill_item['quantity'] ?: '1'); ?> | 
                                <strong>Karat:</strong> <?php echo htmlspecialchars($bill_item['karat'] ?: 'N/A'); ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <form method="POST" id="reportForm">
                        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order_data['order_id']); ?>">
                        <input type="hidden" name="bill_item_id" id="bill_item_id" value="<?php echo $bill_items[0]['bill_item_id']; ?>">
                        
                        <h5 class="mb-3">Customer Information (Editable):</h5>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="customer_name" class="form-label">Customer Name <span class="text-danger">*</span></label>
                                <input type="text" id="customer_name" name="customer_name" class="form-control editable-field" value="<?php echo htmlspecialchars($order_data['customer_name']); ?>" required>
                                <!-- <small class="text-muted">✏️ Editable</small> -->
                            </div>
                            
                            <div class="col-md-4">
                                <label for="manufacturer" class="form-label">Manufacturer</label>
                                <input type="text" id="manufacturer" name="manufacturer" class="form-control editable-field" value="<?php echo htmlspecialchars($order_data['manufacturer'] ?: ''); ?>">
                                <!-- <small class="text-muted">✏️ Editable</small> -->
                            </div>
                            
                            <div class="col-md-4">
                                <label for="customer_address" class="form-label">Customer Address</label>
                                <input type="text" id="customer_address" name="customer_address" class="form-control editable-field" value="<?php echo htmlspecialchars($order_data['customer_address'] ?: ''); ?>">
                                <!-- <small class="text-muted">✏️ Editable</small> -->
                            </div>
                        </div>
                        
                        <h5 class="mb-3 mt-4">Sample Details:</h5>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="item_name" class="form-label">Item Name <span class="text-danger">*</span></label>
                                <input type="text" id="item_name" name="item_name" class="form-control" value="<?php echo htmlspecialchars($bill_items[0]['item_name']); ?>" readonly>
                                <small class="text-muted">Auto-marked in checkbox</small>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="weight" class="form-label">Sample Weight (Gm) <span class="text-danger">*</span></label>
                                <input type="number" id="weight" name="weight" class="form-control editable-field" step="0.001" value="<?php echo $bill_items[0]['weight']; ?>" required>
                                <!-- <small class="text-muted">✏️ Editable</small> -->
                            </div>
                            
                            <div class="col-md-4">
                                <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                <input type="number" id="quantity" name="quantity" class="form-control editable-field" value="<?php echo htmlspecialchars($bill_items[0]['quantity'] ?: '1'); ?>" required>
                                <!-- <small class="text-muted">✏️ Editable</small> -->
                            </div>
                        </div>
                        
                        <input type="hidden" id="service_name" name="service_name" value="<?php echo htmlspecialchars($bill_items[0]['service_name']); ?>">
                        
                        <h5 class="mb-3 mt-4">Hallmark Details<span class="text-danger">*</span></h5>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <!-- <label for="hallmark" class="form-label">Hallmark <span class="text-danger">*</span></label> -->
                                <input type="text" id="hallmark" name="hallmark" class="form-control" placeholder="e.g., 21K RJ" required>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" name="submit_report" class="btn btn-success btn-lg">🚀 Generate Hallmark Report</button>
                        </div>
                    </form>
                    
                    <script>
                        const billItems = <?php echo json_encode($bill_items); ?>;
                        
                        function selectBillItem(index) {
                            document.getElementById('bill_item_' + index).checked = true;
                            document.getElementById('bill_item_id').value = billItems[index].bill_item_id;
                            document.getElementById('item_name').value = billItems[index].item_name;
                            document.getElementById('weight').value = billItems[index].weight;
                            document.getElementById('quantity').value = billItems[index].quantity || 1;
                            document.getElementById('service_name').value = billItems[index].service_name;
                            
                            document.querySelectorAll('.bill-item-card').forEach(card => {
                                card.classList.remove('selected');
                            });
                            event.currentTarget.classList.add('selected');
                        }
                        
                        document.querySelector('.bill-item-card').classList.add('selected');
                    </script>
                <?php endif; ?>
                
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($report_created && $report_data): ?>
        <!-- HALLMARK REPORT FORMAT -->
        <div class="hallmark-preview">
            <div id="reportPreview">
                <div class="report-header-title">HALLMARK REPORT</div>
                <div class="hallmark-dotted"></div>
                
                <div class="hallmark-info-section">
                    <div class="hallmark-left-info">
                        <div class="customer-info-line customer-name">
                            <span>Customer Name</span>
                            <span class="info-colon">:</span>
                            <span class="info-value"><?php echo htmlspecialchars($report_data['customer_name']); ?></span>
                        </div>
                        <div class="customer-info-line">
                            <span class="info-label">Bill No</span>
                            <span class="info-colon">:</span>
                            <span class="info-value"><?php echo htmlspecialchars($report_data['order_id']); ?></span>
                        </div>
                        <div class="customer-info-line">
                            <span class="info-label">Quantity</span>
                            <span class="info-colon">:</span>
                            <span class="info-value"><?php echo htmlspecialchars($report_data['quantity'] ?: '1'); ?></span>
                        </div>
                        <div class="customer-info-line">
                            <span class="info-label">Weight</span>
                            <span class="info-colon">:</span>
                            <span class="info-value"><?php echo htmlspecialchars($report_data['weight']); ?> Gm<span class="weight-conversion" id="weightConversionHall"></span></span>
                        </div>
                        <div class="customer-info-line">
                            <span class="info-label">Made by</span>
                            <span class="info-colon">:</span>
                            <span class="info-value"><?php echo htmlspecialchars($report_data['manufacturer'] ?: 'N/A'); ?></span>
                        </div>
                        <div class="customer-info-line">
                            <span class="info-label">Address</span>
                            <span class="info-colon">:</span>
                            <span class="info-value"><?php echo htmlspecialchars($report_data['address'] ?: 'N/A'); ?></span>
                        </div>
                    </div>
                    
                    <div class="qr-section">
                        <div id="qrcode"></div>
                        <div class="qr-date"><?php echo date('d-M-y', strtotime($report_data['created_at'])); ?> <?php echo date('g:i A', strtotime($report_data['created_at'])); ?></div>
                    </div>
                </div>
                
                <div class="main-box">
                    <div class="checkbox-section">
                        <div class="checkbox-item" data-item="anklet"><div class="checkbox-box"></div><span>Anklet</span></div>
                        <div class="checkbox-item" data-item="bangle"><div class="checkbox-box"></div><span>Bangle</span></div>
                        <div class="checkbox-item" data-item="bracelet"><div class="checkbox-box"></div><span>Bracelet</span></div>
                        <div class="checkbox-item" data-item="chain"><div class="checkbox-box"></div><span>Chain</span></div>
                        <div class="checkbox-item" data-item="ear chain"><div class="checkbox-box"></div><span>Ear Chain</span></div>
                        <div class="checkbox-item" data-item="earrings"><div class="checkbox-box"></div><span>Earrings</span></div>
                        <div class="checkbox-item" data-item="mantasha"><div class="checkbox-box"></div><span>Mantasha</span></div>
                        <div class="checkbox-item" data-item="necklace"><div class="checkbox-box"></div><span>Necklace</span></div>
                        <div class="checkbox-item" data-item="nose pin"><div class="checkbox-box"></div><span>Nose Pin</span></div>
                        <div class="checkbox-item" data-item="others"><div class="checkbox-box"></div><span>Others</span></div>
                        <div class="checkbox-item" data-item="pendant"><div class="checkbox-box"></div><span>Pendant</span></div>
                        <div class="checkbox-item" data-item="ring"><div class="checkbox-box"></div><span>Ring</span></div>
                        <div class="checkbox-item" data-item="shakha path"><div class="checkbox-box"></div><span>ShakhaPath</span></div>
                        <div class="checkbox-item" data-item="taira"><div class="checkbox-box"></div><span>Taira</span></div>
                        <div class="checkbox-item" data-item="tikli"><div class="checkbox-box"></div><span>Tikli</span></div>
                        <div class="checkbox-item" data-item="watch"><div class="checkbox-box"></div><span>Watch</span></div>
                    </div>
                    
                    <div class="hallmark-section">
                        <div class="hallmark-value-container">
                            <div class="hallmark-value"><?php echo htmlspecialchars($report_data['hallmark']); ?></div>
                        </div>
                        <div class="hallmark-label">HallMark</div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Weight conversion for hallmark report
            function convertGramToVoriAnaHall(gram) {
                if (!gram || gram <= 0) return '0 V 0 A 0 R 0 P';
                const totalPoints = Math.round((gram / 11.664) * 16 * 6 * 10);
                const bhori = Math.floor(totalPoints / 960);
                const remainingPoints = totalPoints % 960;
                const ana = Math.floor(remainingPoints / 60);
                const remainingAfterAna = remainingPoints % 60;
                const roti = Math.floor(remainingAfterAna / 10);
                const point = remainingAfterAna % 10;
                return `[V:${bhori} A:${ana} R:${roti} P:${point}]`;
            }
            
            const weightHall = <?php echo floatval($report_data['weight']); ?>;
            const conversionHall = convertGramToVoriAnaHall(weightHall);
            document.getElementById('weightConversionHall').textContent = ' ' + conversionHall;
            
            // Auto-check the matching item checkbox
            const itemName = "<?php echo strtolower(htmlspecialchars($report_data['item_name'])); ?>";
            const checkboxItems = document.querySelectorAll('.checkbox-item');
            
            checkboxItems.forEach(item => {
                const itemType = item.getAttribute('data-item').toLowerCase();
                
                // Exact match or word boundary match to avoid partial matches
                const isMatch = itemName === itemType || 
                               itemName.includes(' ' + itemType + ' ') ||
                               itemName.startsWith(itemType + ' ') ||
                               itemName.endsWith(' ' + itemType);
                
                if (isMatch) {
                    const checkbox = item.querySelector('.checkbox-box');
                    checkbox.innerHTML = '✓';
                    checkbox.style.fontSize = '14px';
                    checkbox.style.fontWeight = 'bold';
                    checkbox.style.display = 'flex';
                    checkbox.style.alignItems = 'center';
                    checkbox.style.justifyContent = 'center';
                    checkbox.style.lineHeight = '1';
                }
            });
        </script>
    
        <div class="text-center mb-3 no-print">
            <button onclick="copyFullReportImage()" class="btn btn-warning btn-lg me-2">
                🖼️ Copy Report with QR
            </button>
            <button onclick="location.href='create_hallmark_report.php'" class="btn btn-success btn-lg">➕ Create New Report</button>
        </div>
        
        <div class="alert alert-success text-center no-print">
            <strong>✅ How to use:</strong>
            Click "🖼️ Copy Report with QR" button, then open MS Word and press <strong>Ctrl+V</strong> to paste!
        </div>
        
        <script>
            // Generate QR Code
            const baseUrl = window.location.origin + window.location.pathname.replace('create_hallmark_report.php', '');
            const reportId = "<?php echo $report_id; ?>" || '';
            const qrLink = `${baseUrl}report_varification.php?id=${reportId}`;

            new QRCode(document.getElementById("qrcode"), {
                text: qrLink,
                width: 100,
                height: 100
            });
            
            // Copy report as image
            async function copyFullReportImage() {
                const button = event.target;
                const originalText = button.innerHTML;
                button.disabled = true;
                button.innerHTML = '⏳ Capturing...';
                
                const report = document.getElementById("reportPreview");
                
                try {
                    const canvas = await html2canvas(report, {
                        scale: 2,
                        useCORS: true,
                        backgroundColor: '#ffffff',
                        logging: false
                    });
                    
                    canvas.toBlob(async (blob) => {
                        try {
                            await navigator.clipboard.write([
                                new ClipboardItem({ "image/png": blob })
                            ]);
                            
                            button.disabled = false;
                            button.innerHTML = originalText;
                            alert("✅ Report copied! Press Ctrl+V in MS Word to paste.");
                        } catch (err) {
                            button.disabled = false;
                            button.innerHTML = originalText;
                            alert("❌ Clipboard error: " + err.message);
                        }
                    });
                } catch (error) {
                    button.disabled = false;
                    button.innerHTML = originalText;
                    alert("❌ Error capturing report: " + error.message);
                }
            }
        </script>
        <?php endif; ?>
        
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>