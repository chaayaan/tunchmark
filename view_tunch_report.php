<?php
require 'auth.php';
require 'mydb.php';


// Define constant element orders
$GOLD_ELEMENTS = ['Silver', 'Platinum', 'Bismuth', 'Copper', 'Palladium', 'Nickel', 'Zinc', 'Antimony', 'Indium', 'Cadmium', 'Iron', 'Titanium', 'Iridium', 'Tin', 'Ruthenium', 'Rhodium', 'Lead', 'Vanadium', 'Cobalt', 'Osmium', 'Manganese'];
$SILVER_ELEMENTS = ['Copper', 'Palladium', 'Nickel', 'Zinc', 'Antimony', 'Indium', 'Cadmium', 'Iron', 'Titanium', 'Iridium', 'Tin', 'Ruthenium', 'Rhodium', 'Lead', 'Vanadium', 'Cobalt', 'Osmium', 'Manganese'];

// Get report ID
if (!isset($_GET['id'])) {
    die("Report ID not provided");
}

$report_id = intval($_GET['id']);

// Fetch report data
$stmt = mysqli_prepare($conn, "SELECT * FROM customer_reports WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $report_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$report_data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$report_data) {
    die("Report not found");
}

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
    
    if ($jsonData) {
        // Check for new constant format
        if (isset($jsonData['type']) && isset($jsonData['values'])) {
            $reportType = $jsonData['type'];
            $values = $jsonData['values'];
            $elementList = ($reportType === 'silver') ? $SILVER_ELEMENTS : $GOLD_ELEMENTS;
            
            for ($i = 0; $i < count($elementList); $i++) {
                if (isset($values[$i])) {
                    $elements[] = ['name' => $elementList[$i], 'percentage' => $values[$i]];
                }
            }
            
            $goldCode = isset($jsonData['gold']) ? $jsonData['gold'] : '';
            $jointCode = isset($jsonData['joint']) ? $jsonData['joint'] : '';
        }
        // Old format with elements object
        else if (isset($jsonData['elements'])) {
            foreach ($jsonData['elements'] as $element => $percentage) {
                $elements[] = ['name' => $element, 'percentage' => $percentage];
            }
            
            // Extract gold and joint codes
            $goldCode = isset($jsonData['gold']) ? $jsonData['gold'] : '';
            $jointCode = isset($jsonData['joint']) ? $jsonData['joint'] : '';
            
            // Backward compatibility
            if (empty($goldCode) && empty($jointCode) && isset($jsonData['codes'])) {
                if (is_array($jsonData['codes'])) {
                    $goldCode = isset($jsonData['codes']['gold']) ? $jsonData['codes']['gold'] : '';
                    $jointCode = isset($jsonData['codes']['joint']) ? $jsonData['codes']['joint'] : '';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tunch Report #<?php echo $report_id; ?> - Rajaiswari Hallmarking Center</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fa;
        }
        
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
        
        @media print {
            body {
                background: white;
            }
            
            .btn,
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
            const baseUrl = window.location.origin + window.location.pathname.replace('view_tunch_report.php', '');
            const reportId = "<?php echo $report_id; ?>";
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
            
            // Print function
            function printReport() {
                window.print();
            }
        </script>
    
        <div class="text-center mb-3 mt-3 no-print">
            <button onclick="copyFullReportImage()" class="btn btn-warning btn-lg me-2">
                🖼️ Copy Report with QR
            </button>
            <!-- <button onclick="window.close()" class="btn btn-secondary btn-lg">
                ✖️ Close
            </button> -->
            <a href="view_customer_reports.php" class="btn btn-secondary btn-lg" title="Close">
                ✖️ Close
            </a>
        </div>
        
        <div class="alert alert-info text-center no-print">
            <strong>ℹ️ Report ID:</strong> #<?php echo $report_id; ?> | 
            <strong>Created:</strong> <?php echo date('d M Y, g:i A', strtotime($report_data['created_at'])); ?>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>