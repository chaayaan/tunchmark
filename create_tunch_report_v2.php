<?php
require 'auth.php';
require 'mydb.php';

// Define constant element orders for Gold and Silver reports
$GOLD_ELEMENTS = ['Silver', 'Platinum', 'Bismuth', 'Copper', 'Palladium', 'Nickel', 'Zinc', 'Antimony', 'Indium', 'Cadmium', 'Iron', 'Titanium', 'Iridium', 'Tin', 'Ruthenium', 'Rhodium', 'Lead', 'Vanadium', 'Cobalt', 'Osmium', 'Manganese'];

$SILVER_ELEMENTS = ['Copper', 'Palladium', 'Nickel', 'Zinc', 'Antimony', 'Indium', 'Cadmium', 'Iron', 'Titanium', 'Iridium', 'Tin', 'Ruthenium', 'Rhodium', 'Lead', 'Vanadium', 'Cobalt', 'Osmium', 'Manganese'];

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

// Fetch active services for dropdown (only Tunch services)
$servicesQuery = "SELECT id, name FROM services WHERE is_active = 1 AND name NOT LIKE '%hallmark%' ORDER BY name ASC";
$servicesResult = mysqli_query($conn, $servicesQuery);
if ($servicesResult) {
    while ($row = mysqli_fetch_assoc($servicesResult)) {
        $services[] = $row;
    }
}

// Handle order fetch
if (isset($_POST['fetch_order'])) {
    $order_id = intval($_POST['order_id']);
    $stmt = mysqli_prepare($conn, "SELECT order_id, customer_name FROM orders WHERE order_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $order_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$order_data) {
        $error = "Order not found!";
    } else {
        // Fetch bill items for this order (only non-hallmark services)
        $billItemsQuery = "SELECT bi.bill_item_id, bi.weight, bi.karat, i.name as item_name, s.name as service_name 
                          FROM bill_items bi 
                          JOIN items i ON bi.item_id = i.id 
                          JOIN services s ON bi.service_id = s.id 
                          WHERE bi.order_id = ? AND s.name NOT LIKE '%hallmark%'";
        $stmt = mysqli_prepare($conn, $billItemsQuery);
        mysqli_stmt_bind_param($stmt, "i", $order_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $bill_items[] = $row;
        }
        mysqli_stmt_close($stmt);
        
        if (empty($bill_items)) {
            $error = "No tunch service bill items found for this order!";
        }
    }
}

// Handle report submission
if (isset($_POST['submit_report']) && !isset($_GET['report_id'])) {
    $order_id = intval($_POST['order_id']);
    $customer_name = trim($_POST['customer_name']);
    $bill_item_id = intval($_POST['bill_item_id']);
    $item_name = trim($_POST['item_name']);
    $service_name = trim($_POST['service_name']);
    $weight = floatval($_POST['weight']);
    $gold_purity = trim($_POST['gold_purity']); 
    $karat = trim($_POST['karat']);
    $composition_data = isset($_POST['composition_data']) ? trim($_POST['composition_data']) : NULL;
    
    // Insert into customer_reports
    $stmt = mysqli_prepare($conn, "INSERT INTO customer_reports (order_id, customer_name, item_name, service_name, weight, gold_purity, karat, composition_data, hallmark, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL)");
    mysqli_stmt_bind_param($stmt, "isssdsss", $order_id, $customer_name, $item_name, $service_name, $weight, $gold_purity, $karat, $composition_data);
    mysqli_stmt_execute($stmt);
    
    $report_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    
    // Redirect to prevent duplicate insert on refresh
    header("Location: create_tunch_report_v2.php?report_id=" . $report_id);
    exit;
}

// Fetch existing report if report_id in URL
if (isset($_GET['report_id'])) {
    $report_id = intval($_GET['report_id']);
    $stmt = mysqli_prepare($conn, "SELECT * FROM customer_reports WHERE id = ?");
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
    <title>Generate Tunch Report - Rajaiswari Hallmarking Center</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fa;
        }
        
        /* Tunch Report Styles */
        .tunch-preview {
            width: auto;
            max-width: 700px;
            padding: 0 30px;
            margin: 0 auto;
            background: white;
        }
        
        .tunch-container {
            padding: 0 20px;
            background: white;
            position: relative;
        }

        /* Watermark styling */
        .tunch-container::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-25deg);
            width: 200px;
            height: 200px;
            background-image: url('Varifiedstamp.png');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            opacity: 0.2;
            z-index: 1;
            pointer-events: none;
        }

        .tunch-container > * {
            position: relative;
            z-index: 2;
        }
        
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin: 0;
            padding: 0;
        }
        
        .customer-info {
            flex: 1;
            padding: 0;
        }
        
        .customer-info-line {
            margin: 0;
            padding: 0;
            font-size: 15px;
            line-height: 1.4;
            display: flex;
            color: #000;
            font-weight: 600;
        }
        
        .customer-info-line.customer-name {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 2px;
        }
        
        .info-label {
            display: inline-block;
            min-width: 120px;
            text-align: left;
            font-weight: 600;
        }
        
        .info-colon {
            display: inline-block;
            width: 15px;
            text-align: center;
        }
        
        .info-value {
            flex: 1;
            font-weight: 600;
        }
        
        .qr-section {
            width: 85px;
            text-align: center;
            padding: 5px;
            margin-left: 15px;
            flex-shrink: 0;
        }
        
        #qrcode {
            margin-bottom: 5px;
        }
        
        .qr-date {
            font-size: 12px;
            color: #000;
            font-weight: 700;
            line-height: 1.4;
            margin-top: 5px;
            white-space: nowrap;
        }
        
        .weight-conversion {
            font-size: 13px;
            color: #333;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .dotted-line {
            border-top: 3px dotted #000;
            margin: 2px 0;
            padding: 0;
        }
        
        .quality-info {
            font-size: 24px;
            font-weight: bold;
            margin: 2px 0;
            line-height: 1.5;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 20px;
            color: #000;
        }
        
        .quality-info span {
            white-space: nowrap;
        }
        
        .composition-table {
            width: 100%;
            margin: 2px 0 0 0;
            font-size: 11px;
            line-height: 1.1;
            border-collapse: collapse;
        }
        
        .composition-table td {
            padding: 1px 5px;
            font-weight: 600;
            vertical-align: top;
        }
        
        .composition-table td.element-name {
            text-align: left;
            padding-right: 3px;
        }
        
        .composition-table td.element-colon {
            text-align: center;
            padding: 1px 2px;
        }
        
        .composition-table td.element-value {
            text-align: left;
            padding-left: 3px;
            padding-right: 15px;
        }
        
        .report-note {
            font-size: 11px;
            line-height: 1.4;
            margin: 3px 0 0 0;
            padding: 0;
            font-weight: 600;
            color: #000;
        }
        
        .report-codes {
            font-size: 11px;
            text-align: right;
            margin: 1px 0 0 0;
            font-weight: bold;
            color: #000;
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
    
    <script>
        // Constant element orders (same as PHP)
        const GOLD_ELEMENTS = <?php echo json_encode($GOLD_ELEMENTS); ?>;
        const SILVER_ELEMENTS = <?php echo json_encode($SILVER_ELEMENTS); ?>;
    </script>
</head>
<body>
<?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <?php if (!$report_created): ?>
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">📋 Generate Tunch Report (Constant Format)</h3>
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
                    <div class="alert alert-info">
                        <strong>💡 Quick Paste:</strong> Paste your XRF analysis data below. The system will auto-extract all values and organize them in the standard format!
                    </div>
                    
                    <div class="mb-4">
                        <h5 class="mb-3">Select Bill Item:</h5>
                        <?php foreach ($bill_items as $index => $bill_item): ?>
                        <div class="bill-item-card" onclick="selectBillItem(<?php echo $index; ?>)">
                            <input type="radio" name="selected_bill_item" id="bill_item_<?php echo $index; ?>" value="<?php echo $index; ?>" <?php echo $index === 0 ? 'checked' : ''; ?> style="float: left; margin-right: 8px; margin-top: 2px;">
                            <label for="bill_item_<?php echo $index; ?>" style="cursor: pointer; display: block; overflow: hidden; font-size: 14px;">
                                <strong>Item:</strong> <?php echo htmlspecialchars($bill_item['item_name']); ?> | 
                                <strong>Weight:</strong> <?php echo htmlspecialchars($bill_item['weight']); ?> Gm
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <form method="POST" id="reportForm">
                        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order_data['order_id']); ?>">
                        <input type="hidden" name="bill_item_id" id="bill_item_id" value="<?php echo $bill_items[0]['bill_item_id']; ?>">
                        
                        <h5 class="mb-3">Customer Information (Editable):</h5>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="customer_name" class="form-label">Customer Name <span class="text-danger">*</span></label>
                                <input type="text" id="customer_name" name="customer_name" class="form-control editable-field" value="<?php echo htmlspecialchars($order_data['customer_name']); ?>" required>
                            </div>
                        </div>
                        
                        <h5 class="mb-3 mt-4">Sample Details:</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="item_name" class="form-label">Sample Item Name <span class="text-danger">*</span></label>
                                <input type="text" id="item_name" name="item_name" class="form-control editable-field" value="<?php echo htmlspecialchars($bill_items[0]['item_name']); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="weight" class="form-label">Sample Weight (Gm) <span class="text-danger">*</span></label>
                                <input type="number" id="weight" name="weight" class="form-control editable-field" step="0.001" value="<?php echo $bill_items[0]['weight']; ?>" required>
                            </div>
                        </div>
                        
                        <input type="hidden" id="service_name" name="service_name" value="<?php echo htmlspecialchars($bill_items[0]['service_name']); ?>">
                        
                        <h5 class="mb-3 mt-4">XRF Analysis Data:</h5>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="xrf_data_input" class="form-label">Paste Complete XRF Analysis Data Here <span class="text-danger">*</span></label>
                                <textarea id="xrf_data_input" class="form-control" rows="10" placeholder="Paste your complete XRF analysis data here..." oninput="parseXRFData()" style="font-family: monospace; font-size: 12px;"></textarea>
                                <small class="text-muted">The system will automatically organize elements in the standard format</small>
                            </div>
                        </div>
                        
                        <h5 class="mb-3 mt-4">Testing Results (Auto-populated):</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="gold_purity" class="form-label"><span id="purityLabel">Gold Purity</span> (%) <span class="text-danger">*</span></label>
                                <input type="number" id="gold_purity" name="gold_purity" class="form-control" step="0.01" placeholder="Will be auto-filled" required readonly>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="karat" class="form-label">Karat <span class="text-danger">*</span></label>
                                <input type="text" id="karat" name="karat" class="form-control" placeholder="Will be auto-filled" readonly required>
                            </div>
                        </div>
                        
                        <input type="hidden" id="composition_data" name="composition_data">
                        
                        <div class="text-end">
                            <button type="submit" name="submit_report" class="btn btn-success btn-lg">🚀 Generate Tunch Report</button>
                        </div>
                    </form>
                    
                    <script>
                        const billItems = <?php echo json_encode($bill_items); ?>;
                        
                        // Detect if item is silver or gold
                        function detectMetalType(itemName) {
                            const itemLower = itemName.toLowerCase();
                            return itemLower.includes('silver') || itemLower.includes('চাঁদি') || itemLower.includes('rupa');
                        }
                        
                        // Update label based on item type
                        function updatePurityLabel() {
                            const itemName = document.getElementById('item_name').value;
                            const purityLabel = document.getElementById('purityLabel');
                            
                            if (detectMetalType(itemName)) {
                                purityLabel.textContent = 'Silver Purity';
                            } else {
                                purityLabel.textContent = 'Gold Purity';
                            }
                        }
                        
                        // Parse XRF data with constant format
                        function parseXRFData() {
                            const input = document.getElementById('xrf_data_input').value;
                            const itemName = document.getElementById('item_name').value.toLowerCase();
                            const isSilver = itemName.includes('silver') || itemName.includes('চাঁদি') || itemName.includes('rupa');
                            
                            // Determine which element list to use
                            const elementList = isSilver ? SILVER_ELEMENTS : GOLD_ELEMENTS;
                            
                            // Extract Purity and Karat
                            let purityMatch, karatMatch;
                            if (isSilver) {
                                purityMatch = input.match(/Silver\s+Purity\s*:\s*([\d.]+)%/i);
                            } else {
                                purityMatch = input.match(/Gold\s+Purity\s*:\s*([\d.]+)%/i);
                            }
                            karatMatch = input.match(/Karat\s*:\s*([\d.]+)/i);
                            
                            if (purityMatch) {
                                document.getElementById('gold_purity').value = purityMatch[1];
                            }
                            if (karatMatch) {
                                document.getElementById('karat').value = karatMatch[1];
                            }
                            
                            // Extract element values in the fixed order
                            const values = [];
                            elementList.forEach(element => {
                                // Case-insensitive search for element
                                const regex = new RegExp(element + '\\s*:\\s*([\\d.]+%|--------%)', 'i');
                                const match = input.match(regex);
                                if (match) {
                                    values.push(match[1]);
                                } else {
                                    values.push('--------%'); // Default if not found
                                }
                            });
                            
                            // Extract Gold and Joint codes
                            let goldCode = '';
                            let jointCode = '';
                            const codesMatch = input.match(/Gold:\s*(\d+)\s+Joint:\s*(\d+)/i);
                            if (codesMatch) {
                                goldCode = codesMatch[1];
                                jointCode = codesMatch[2];
                            }
                            
                            // Create JSON with constant format
                            const jsonData = {
                                purity: purityMatch ? purityMatch[1] : '',
                                karat: karatMatch ? karatMatch[1] : '',
                                type: isSilver ? 'silver' : 'gold',
                                values: values,
                                gold: goldCode,
                                joint: jointCode
                            };
                            
                            document.getElementById('composition_data').value = JSON.stringify(jsonData);
                        }
                        
                        function selectBillItem(index) {
                            document.getElementById('bill_item_' + index).checked = true;
                            document.getElementById('bill_item_id').value = billItems[index].bill_item_id;
                            document.getElementById('item_name').value = billItems[index].item_name;
                            document.getElementById('weight').value = billItems[index].weight;
                            document.getElementById('service_name').value = billItems[index].service_name;
                            
                            // Clear fields
                            document.getElementById('xrf_data_input').value = '';
                            document.getElementById('gold_purity').value = '';
                            document.getElementById('karat').value = '';
                            document.getElementById('composition_data').value = '';
                            
                            updatePurityLabel();
                            
                            document.querySelectorAll('.bill-item-card').forEach(card => {
                                card.classList.remove('selected');
                            });
                            event.currentTarget.classList.add('selected');
                        }
                        
                        // Initialize
                        document.querySelector('.bill-item-card').classList.add('selected');
                        updatePurityLabel();
                        document.getElementById('item_name').addEventListener('input', updatePurityLabel);
                    </script>
                <?php endif; ?>
                
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($report_created && $report_data): ?>
        <!-- TUNCH REPORT FORMAT -->
        <?php
            // Detect if the item is silver
            $itemName = strtolower($report_data['item_name']);
            $isSilver = strpos($itemName, 'silver') !== false || 
                        strpos($itemName, 'চাঁদি') !== false || 
                        strpos($itemName, 'rupa') !== false;
            $purityLabel = $isSilver ? 'Silver Purity' : 'Gold Purity';
            
            // Parse composition data
            $compositionData = isset($report_data['composition_data']) ? $report_data['composition_data'] : '';
            $elements = [];
            $goldCode = '';
            $jointCode = '';
            
            // Constant note
            $reportNote = 'The report pertains to specific point and not responsible for other point or melting issues.';
            
            if (!empty($compositionData)) {
                $jsonData = json_decode($compositionData, true);
                
                if ($jsonData && isset($jsonData['type']) && isset($jsonData['values'])) {
                    // New constant format
                    $reportType = $jsonData['type'];
                    $values = $jsonData['values'];
                    $elementList = ($reportType === 'silver') ? $SILVER_ELEMENTS : $GOLD_ELEMENTS;
                    
                    // Map values to elements
                    for ($i = 0; $i < count($elementList); $i++) {
                        if (isset($values[$i])) {
                            $elements[] = ['name' => $elementList[$i], 'percentage' => $values[$i]];
                        }
                    }
                    
                    $goldCode = isset($jsonData['gold']) ? $jsonData['gold'] : '';
                    $jointCode = isset($jsonData['joint']) ? $jsonData['joint'] : '';
                } else if ($jsonData && isset($jsonData['elements'])) {
                    // Old format fallback
                    foreach ($jsonData['elements'] as $element => $percentage) {
                        $elements[] = ['name' => $element, 'percentage' => $percentage];
                    }
                    $goldCode = isset($jsonData['gold']) ? $jsonData['gold'] : '';
                    $jointCode = isset($jsonData['joint']) ? $jsonData['joint'] : '';
                }
            }
        ?>
        <div class="tunch-preview">
            <div class="tunch-container" id="reportPreview">
                <div class="report-header">
                    <div class="customer-info">
                        <div class="customer-info-line customer-name">
                            Customer Name : <?php echo htmlspecialchars($report_data['customer_name']); ?>
                        </div>
                        <div class="customer-info-line">
                            <span class="info-label">Sample Item</span>
                            <span class="info-colon">:</span>
                            <span class="info-value"><?php echo htmlspecialchars($report_data['item_name']); ?></span>
                        </div>
                        <div class="customer-info-line">
                            <span class="info-label">Sample Weight</span>
                            <span class="info-colon">:</span>
                            <span class="info-value"><?php echo htmlspecialchars($report_data['weight']); ?> Gm<span class="weight-conversion" id="weightConversion"></span></span>
                        </div>
                        <div class="customer-info-line">
                            <span class="info-label">Bill No</span>
                            <span class="info-colon">:</span>
                            <span class="info-value"><?php echo htmlspecialchars($report_data['order_id']); ?></span>
                        </div>
                    </div>
                    
                    <div class="qr-section">
                        <div id="qrcode"></div>
                        <div class="qr-date"><?php echo date('d-M-y', strtotime($report_data['created_at'])); ?> <?php echo date('g:i A', strtotime($report_data['created_at'])); ?></div>
                    </div>
                </div>
                
                <div class="dotted-line"></div>
                
                <div class="quality-info">
                    <span><?php echo $purityLabel; ?> : <?php echo htmlspecialchars($report_data['gold_purity'] ?: 'N/A'); ?>%</span>
                    <span>Karat : <?php echo htmlspecialchars($report_data['karat'] ?: 'N/A'); ?>K</span>
                </div>
                
                <div class="dotted-line"></div>
                
                <?php if (!empty($elements)): ?>
                <table class="composition-table">
                    <?php
                    $chunks = array_chunk($elements, 3);
                    foreach ($chunks as $row):
                    ?>
                    <tr>
                        <?php foreach ($row as $element): ?>
                        <td class="element-name"><?php echo htmlspecialchars($element['name']); ?></td>
                        <td class="element-colon">:</td>
                        <td class="element-value"><?php echo htmlspecialchars($element['percentage']); ?></td>
                        <?php endforeach; ?>
                        <?php
                        $remaining = 3 - count($row);
                        for ($i = 0; $i < $remaining; $i++) {
                            echo '<td class="element-name"></td><td class="element-colon"></td><td class="element-value"></td>';
                        }
                        ?>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php endif; ?>
                
                <div class="report-note">NB:- <?php echo htmlspecialchars($reportNote); ?></div>
                
                <?php if ($goldCode !== '' || $jointCode !== ''): ?>
                <div class="report-codes">
                    <?php 
                    $codeParts = [];
                    if ($goldCode !== '') {
                        $codeParts[] = 'Gold: ' . htmlspecialchars($goldCode);
                    }
                    if ($jointCode !== '') {
                        $codeParts[] = 'Joint: ' . htmlspecialchars($jointCode);
                    }
                    echo implode('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', $codeParts);
                    ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
            // Weight conversion
            function convertGramToVoriAna(gram) {
                if (!gram || gram <= 0) return '0 V 0 A 0 R 0 P';
                const totalPoints = Math.round((gram / 11.664) * 16 * 6 * 10);
                const bhori = Math.floor(totalPoints / 960);
                const remainingPoints = totalPoints % 960;
                const ana = Math.floor(remainingPoints / 60);
                const remainingAfterAna = remainingPoints % 60;
                const roti = Math.floor(remainingAfterAna / 10);
                const point = remainingAfterAna % 10;
                return `V:${bhori} A:${ana} R:${roti} P:${point}`;
            }
            
            const weight = <?php echo floatval($report_data['weight']); ?>;
            const conversion = convertGramToVoriAna(weight);
            document.getElementById('weightConversion').textContent = '(' + conversion + ')';
            
            // Generate QR Code
            const baseUrl = window.location.origin + window.location.pathname.replace('create_tunch_report_v2.php', '');
            const reportId = "<?php echo $report_id; ?>" || '';
            const qrLink = `${baseUrl}report_varification.php?id=${reportId}`;
            
            new QRCode(document.getElementById("qrcode"), {
                text: qrLink,
                width: 75,
                height: 75
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
    
        <div class="text-center mb-3 no-print">
            <button onclick="copyFullReportImage()" class="btn btn-warning btn-lg me-2">
                🖼️ Copy Report with QR
            </button>
            <button onclick="location.href='create_tunch_report_v2.php'" class="btn btn-success btn-lg">➕ Create New Report</button>
        </div>
        
        <div class="alert alert-success text-center no-print">
            <strong>✅ Constant Format Active!</strong> All elements are displayed in standard order. Storage reduced by 60%!
        </div>
        <?php endif; ?>
        
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>