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
    
    // Insert into customer_reports
    $stmt = mysqli_prepare($conn, "INSERT INTO customer_reports (order_id, customer_name, item_name, service_name, weight, gold_purity, karat, hallmark, address) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, NULL)");
    mysqli_stmt_bind_param($stmt, "isssdss", $order_id, $customer_name, $item_name, $service_name, $weight, $gold_purity, $karat);
    mysqli_stmt_execute($stmt);
    
    $report_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    
    // Redirect to prevent duplicate insert on refresh
    header("Location: create_tunch_report.php?report_id=" . $report_id);
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
            line-height: 1.8;
            display: flex;
            color: #000;
            font-weight: 600;
        }
        
        .customer-info-line.customer-name {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 3px;
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
            width: 100px;
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
            margin: 5px 0;
            padding: 0;
        }
        
        .quality-info {
            font-size: 24px;
            font-weight: bold;
            margin: 5px 0;
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
                <h3 class="mb-0">ЁЯУЛ Generate Tunch Report</h3>
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
                            <button type="submit" name="fetch_order" class="btn btn-primary">ЁЯФН Fetch Order</button>
                        </div>
                    </div>
                </form>
                
                <?php if ($order_data && !empty($bill_items)): ?>
                    <div class="alert alert-warning">
                        <strong>ЁЯУЭ Note:</strong> Customer Name field is editable. You can modify it before generating the report.
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
                        
                        <h5 class="mb-3 mt-4">Testing Results:</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="gold_purity" class="form-label"><span id="purityLabel">Gold Purity</span> (%) <span class="text-danger">*</span></label>
                                <input type="number" id="gold_purity" name="gold_purity" class="form-control" step="0.01" placeholder="e.g., 87.5" required oninput="calculateKarat()">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="karat" class="form-label">Karat (Auto-calculated) <span class="text-danger">*</span></label>
                                <input type="text" id="karat" name="karat" class="form-control" placeholder="Auto-calculated" value="<?php echo htmlspecialchars($bill_items[0]['karat'] ?: ''); ?>" readonly required>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" name="submit_report" class="btn btn-success btn-lg">ЁЯЪА Generate Tunch Report</button>
                        </div>
                    </form>
                    
                    <script>
                        const billItems = <?php echo json_encode($bill_items); ?>;
                        
                        // Detect if item is silver or gold
                        function detectMetalType(itemName) {
                            const itemLower = itemName.toLowerCase();
                            return itemLower.includes('silver') || itemLower.includes('ржЪрж╛ржБржжрж┐') || itemLower.includes('rupa');
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
                        
                        // Calculate Karat based on Purity
                        function calculateKarat() {
                            const purity = parseFloat(document.getElementById('gold_purity').value);
                            const karatField = document.getElementById('karat');
                            
                            if (purity && purity > 0) {
                                const karat = (24 / 100) * purity;
                                karatField.value = karat.toFixed(2);
                            } else {
                                karatField.value = '';
                            }
                        }
                        
                        function selectBillItem(index) {
                            document.getElementById('bill_item_' + index).checked = true;
                            document.getElementById('bill_item_id').value = billItems[index].bill_item_id;
                            document.getElementById('item_name').value = billItems[index].item_name;
                            document.getElementById('weight').value = billItems[index].weight;
                            document.getElementById('service_name').value = billItems[index].service_name;
                            
                            // Clear purity and karat when changing items
                            document.getElementById('gold_purity').value = '';
                            document.getElementById('karat').value = '';
                            
                            // Update purity label based on item type
                            updatePurityLabel();
                            
                            document.querySelectorAll('.bill-item-card').forEach(card => {
                                card.classList.remove('selected');
                            });
                            event.currentTarget.classList.add('selected');
                        }
                        
                        // Initialize
                        document.querySelector('.bill-item-card').classList.add('selected');
                        updatePurityLabel();
                        
                        // Add listener to item name field
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
                        strpos($itemName, 'ржЪрж╛ржБржжрж┐') !== false || 
                        strpos($itemName, 'rupa') !== false;
            $purityLabel = $isSilver ? 'Silver Purity' : 'Gold Purity';
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
            </div>
        </div>
        
        <script>
            // Weight conversion for tunch report
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
        </script>
    
        <div class="text-center mb-3 no-print">
            <button onclick="copyFullReportImage()" class="btn btn-warning btn-lg me-2">
                ЁЯЦ╝я╕П Copy Report with QR
            </button>
            <button onclick="location.href='create_tunch_report.php'" class="btn btn-success btn-lg">тЮХ Create New Report</button>
        </div>
        
        <div class="alert alert-success text-center no-print">
            <strong>тЬЕ How to use:</strong>
            Click "ЁЯЦ╝я╕П Copy Report with QR" button, then open MS Word and press <strong>Ctrl+V</strong> to paste!
        </div>
        
        <script>
            // Generate QR Code
            const qrLink = "<?php echo "https://www.app.rajaiswari.com/report_varification.php?id=" . $report_id; ?>";
            
            new QRCode(document.getElementById("qrcode"), {
                text: qrLink,
                width: 90,
                height: 90
            });
            
            // Copy report as image
            async function copyFullReportImage() {
                const button = event.target;
                const originalText = button.innerHTML;
                button.disabled = true;
                button.innerHTML = 'тП│ Capturing...';
                
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
                            alert("тЬЕ Report copied! Press Ctrl+V in MS Word to paste.");
                        } catch (err) {
                            button.disabled = false;
                            button.innerHTML = originalText;
                            alert("тЭМ Clipboard error: " + err.message);
                        }
                    });
                } catch (error) {
                    button.disabled = false;
                    button.innerHTML = originalText;
                    alert("тЭМ Error capturing report: " + error.message);
                }
            }
        </script>
        <?php endif; ?>
        
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>