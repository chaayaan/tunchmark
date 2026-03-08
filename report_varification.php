<?php
// Include database connection
require 'mydb.php';

// Get report ID from URL
$report_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch report data
$report_data = null;
$is_hallmark_report = false;

if ($report_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM customer_reports WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $report_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $report_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    // Determine if it's a hallmark report
    if ($report_data && stripos($report_data['service_name'], 'hallmark') !== false) {
        $is_hallmark_report = true;
    }
}

// Convert Gram to Vori Ana Roti Point function
function convertGramToVoriAna($gram) {
    if (!$gram || $gram <= 0) return '0 V 0 A 0 R 0 P';
    
    $totalPoints = round(($gram / 11.664) * 16 * 6 * 10);
    $bhori = floor($totalPoints / 960);
    $remainingPoints = $totalPoints % 960;
    $ana = floor($remainingPoints / 60);
    $remainingAfterAna = $remainingPoints % 60;
    $roti = floor($remainingAfterAna / 10);
    $point = $remainingAfterAna % 10;
    
    return "V:{$bhori} A:{$ana} R:{$roti} P:{$point}";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Report Verification - Rajaiswari</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        
        body {
            font-family: 'Times New Roman', Times, serif;
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        /* Disable right click, copy, drag */
        body {
            -webkit-touch-callout: none;
            -webkit-user-drag: none;
        }
        
        img {
            pointer-events: none;
            -webkit-user-drag: none;
            -khtml-user-drag: none;
            -moz-user-drag: none;
            -o-user-drag: none;
            user-drag: none;
        }
        
        .verification-container {
            max-width: 700px;
            width: 100%;
            background: white;
            border-radius: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            position: relative;
            border: 1px solid #e0e0e0;
        }
        
        .header-image {
            width: 100%;
            max-width: 350px;
            display: block;
            margin: 0 auto;
            background: #f8f9fa;
        }
        
        .report-type-header {
            text-align: center;
            font-size: 32px;
            font-weight: bold;
            color: #226ab3ff;
            padding: 20px 0;
            margin: 0;
            letter-spacing: 4px;
            font-family: 'Times New Roman', Times, serif;
            background: #ffffff;
            border-bottom: 3px solid #5a7188ff;
        }
        
        .content {
            padding: 30px 40px;
            position: relative;
            z-index: 2;
        }
        
        .verified-stamp {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-20deg);
            width: 180px;
            height: 180px;
            opacity: 0.15;
            z-index: 1;
            pointer-events: none;
        }
        
        .info-section {
            margin-bottom: 20px;
        }
        
        .customer-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #000;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .info-row {
            display: flex;
            padding: 6px 0;
            font-size: 16px;
            line-height: 1.6;
            align-items: flex-start;
        }
        
        .info-label {
            width: 150px;
            color: #000;
            flex-shrink: 0;
            font-weight: 600;
        }
        
        .info-value {
            color: #000;
            flex: 1;
            font-weight: 400;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        .weight-conversion {
            font-size: 14px;
            color: #444;
            margin-left: 8px;
        }
        
        .dotted-line {
            border-top: 2px dotted #000;
            margin: 20px 0;
        }
        
        /* Tunch Report Styles */
        .quality-info {
            display: flex;
            justify-content: space-around;
            font-size: 20px;
            font-weight: bold;
            padding: 25px 20px;
            color: #000;
            background: #fff;
            border-radius: 0;
            border: 3px solid #000;
            box-shadow: none;
        }
        
        .quality-info span {
            white-space: nowrap;
        }
        
        /* Hallmark Report Styles */
        .hallmark-box {
            border: 3px solid #000;
            border-radius: 0;
            text-align: center;
            background: #fff;
            box-shadow: none;
        }
        
        .hallmark-value {
            font-size: 56px;
            font-weight: bold;
            color: #000;
            margin: 20px 0;
            font-family: 'Times New Roman', Times, serif;
            word-wrap: break-word;
        }
        
        .hallmark-label {
            font-size: 20px;
            font-weight: 700;
            color: #000;
            text-transform: uppercase;
            letter-spacing: 3px;
        }
        
        .verification-message {
            background: #4fa756ff;
            color: white;
            text-align: center;
            padding: 20px;
            font-size: 16px;
            font-weight: 600;
            margin-top: 20px;
            border-radius: 0;
            box-shadow: none;
            border: none;
        }
        
        .remarks-section {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .remarks-section strong {
            display: block;
            margin-bottom: 8px;
            color: #1a1a1a;
            font-size: 16px;
        }
        
        .footer {
            background: #f8f9fa;
            padding: 20px 40px;
            text-align: center;
            color: #666;
            font-size: 13px;
            border-top: 1px solid #e0e0e0;
        }
        
        .error-container {
            text-align: center;
            padding: 80px 40px;
        }
        
        .error-icon {
            font-size: 100px;
            margin-bottom: 30px;
            color: #d32f2f;
        }
        
        .error-container h2 {
            color: #d32f2f;
            font-size: 28px;
            margin-bottom: 20px;
        }
        
        .error-container p {
            color: #666;
            font-size: 16px;
            line-height: 1.8;
        }
        
        @media (max-width: 600px) {
            body {
                padding: 10px;
            }
            
            .verification-container {
                border-radius: 0;
            }
            
            .header-image {
                max-width: 100%;
                border-radius: 0;
            }
            
            .report-type-header {
                font-size: 28px;
                padding: 15px 0;
                letter-spacing: 2px;
            }
            
            .content {
                padding: 20px;
            }
            
            .customer-name {
                font-size: 20px;
            }
            
            .info-row {
                padding: 8px 0;
                font-size: 15px;
                display: flex;
                flex-direction: row;
                align-items: flex-start;
            }
            
            .info-label {
                width: 120px;
                margin-bottom: 0;
                font-weight: 600;
                flex-shrink: 0;
            }
            
            .info-value {
                flex: 1;
                word-break: break-word;
            }
            
            .weight-conversion {
                display: inline;
                font-size: 12px;
            }
            
            .verified-stamp {
                width: 150px;
                height: 150px;
            }
            
            .quality-info {
                flex-direction: column;
                gap: 12px;
                font-size: 18px;
                padding: 20px;
                text-align: center;
            }
            
            .quality-info span {
                font-size: 18px;
                padding: 8px 0;
                border-bottom: 1px dashed #ff9800;
                white-space: normal;
            }
            
            .quality-info span:last-child {
                border-bottom: none;
            }
            
            .hallmark-value {
                font-size: 36px;
            }
            
            .hallmark-label {
                font-size: 16px;
            }
            
            .verification-message {
                font-size: 15px;
                padding: 15px;
            }
            
            .footer {
                padding: 15px 20px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 400px) {
            .content {
                padding: 15px;
            }
            
            .report-type-header {
                font-size: 24px;
                padding: 10px 0;
            }
            
            .customer-name {
                font-size: 16px;
            }
            
            .info-row {
                font-size: 13px;
                padding: 6px 0;
            }
            
            .info-label {
                width: 100px;
                font-size: 13px;
            }
            
            .info-value {
                font-size: 13px;
            }
            
            .weight-conversion {
                font-size: 11px;
            }
            
            .verified-stamp {
                width: 120px;
                height: 120px;
            }
            
            .quality-info {
                font-size: 17px;
                padding: 18px;
            }
            
            .quality-info span {
                font-size: 17px;
            }
            
            .hallmark-value {
                font-size: 32px;
            }
            
            .hallmark-label {
                font-size: 14px;
            }
        }
    </style>
</head>
<body oncontextmenu="return false;" oncopy="return false;" oncut="return false;">
    <div class="verification-container">
        <?php if ($report_data): ?>
            <!-- Header Image -->
            <img src="receiptheader.png" alt="Header" class="header-image">
            
            <!-- Report Type Header -->
            <div class="report-type-header">
                <?php echo $is_hallmark_report ? 'HALLMARK REPORT' : 'TUNCH REPORT'; ?>
            </div>
            
            <!-- Verified Stamp Watermark -->
            <img src="Varifiedstamp.png" alt="Verified" class="verified-stamp">
            
            <div class="content">
                <div class="info-section">
                    <div class="customer-name">
                        Customer Name : <?php echo htmlspecialchars($report_data['customer_name']); ?>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Sample Item</span>
                        <span class="info-value">: <?php echo htmlspecialchars($report_data['item_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Sample Weight</span>
                        <span class="info-value">
                            : <?php echo htmlspecialchars($report_data['weight']); ?> Gm
                            <span class="weight-conversion">[<?php echo convertGramToVoriAna($report_data['weight']); ?>]</span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Bill No</span>
                        <span class="info-value">: <?php echo htmlspecialchars($report_data['order_id']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Date & Time</span>
                        <span class="info-value">
                            : <?php echo date('d-M-y g:i A', strtotime($report_data['created_at'])); ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($is_hallmark_report): ?>
                    <!-- Hallmark Report Display -->
                    <div class="hallmark-box">
                        <div class="hallmark-value">
                            <?php echo htmlspecialchars($report_data['hallmark']); ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Tunch Report Display -->
                    <div class="quality-info">
                        <span>Gold Purity : <?php echo htmlspecialchars($report_data['gold_purity'] ?: 'N/A'); ?>%</span>
                        <span>Karat : <?php echo htmlspecialchars($report_data['karat'] ?: 'N/A'); ?></span>
                    </div>
                <?php endif; ?>
                
                <!-- <?php if ($report_data['remarks']): ?>
                <div class="remarks-section">
                    <strong>Remarks:</strong>
                    <?php echo nl2br(htmlspecialchars($report_data['remarks'])); ?>
                </div>
                <?php endif; ?> -->
                
                <div class="verification-message">
                    ✅ This is the Verified Report by Rajaiswari.
                </div>
            </div>
            
            <div class="footer">
                <p style="margin-top: 10px; font-size: 14px; color: #555;">This is an official report. For queries, contact our support team.</p>
            </div>
        <?php else: ?>
            <div class="error-container">
                <div class="error-icon">❌</div>
                <h2>Report Not Found</h2>
                <p>The report you are trying to access does not exist or has been removed.</p>
                <p style="margin-top: 20px; font-size: 14px;">Please verify the QR code or link and try again.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Disable keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Disable Ctrl+C, Ctrl+X, Ctrl+S, Ctrl+A, Ctrl+P, F12, Ctrl+Shift+I
            if ((e.ctrlKey && (e.key === 'c' || e.key === 'x' || e.key === 's' || e.key === 'a' || e.key === 'p')) || 
                e.key === 'F12' || 
                (e.ctrlKey && e.shiftKey && e.key === 'I') ||
                (e.ctrlKey && e.shiftKey && e.key === 'J') ||
                (e.ctrlKey && e.key === 'U')) {
                e.preventDefault();
                return false;
            }
        });

        // Disable text selection on double click
        document.addEventListener('selectstart', function(e) {
            e.preventDefault();
            return false;
        });

        // Disable drag events
        document.addEventListener('dragstart', function(e) {
            e.preventDefault();
            return false;
        });

        // Disable print screen
        document.addEventListener('keyup', function(e) {
            if (e.key === 'PrintScreen') {
                navigator.clipboard.writeText('');
            }
        });
    </script>
</body>
</html>