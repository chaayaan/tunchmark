<?php
require 'mydb.php';

$GOLD_ELEMENTS   = ['Silver','Platinum','Bismuth','Copper','Palladium','Nickel','Zinc','Antimony','Indium','Cadmium','Iron','Titanium','Iridium','Tin','Ruthenium','Rhodium','Lead','Vanadium','Cobalt','Osmium','Manganese'];
$SILVER_ELEMENTS = ['Copper','Palladium','Nickel','Zinc','Antimony','Indium','Cadmium','Iron','Titanium','Iridium','Tin','Ruthenium','Rhodium','Lead','Vanadium','Cobalt','Osmium','Manganese'];

$report_id   = isset($_GET['id']) ? intval($_GET['id']) : 0;
$report_data = null;

if ($report_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM customer_reports WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $report_id);
    mysqli_stmt_execute($stmt);
    $report_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

$isSilver    = false;
$purityLabel = 'Gold Purity';
$purityValue = null;
$elements    = [];
$goldCode    = '';
$jointCode   = '';
$isHallmark  = false;
$nbNote      = 'The report pertains to specific point and not responsible for other point or melting issues.';

if ($report_data) {
    $itemLower  = strtolower($report_data['item_name']);
    $isSilver   = strpos($itemLower, 'silver') !== false || strpos($itemLower, 'চাঁদি') !== false || strpos($itemLower, 'rupa') !== false;
    $isHallmark = stripos($report_data['service_name'] ?? '', 'hallmark') !== false;

    $purityLabel = $isSilver ? 'Silver Purity' : 'Gold Purity';
    $purityValue = $isSilver ? $report_data['silver_purity_percent'] : $report_data['gold_purity_percent'];

    $elementOrder = $isSilver ? $SILVER_ELEMENTS : $GOLD_ELEMENTS;
    foreach ($elementOrder as $elName) {
        $col     = strtolower($elName);
        $val     = $report_data[$col] ?? null;
        $display = ($val === null) ? '--------%' : number_format((float)$val, 3) . '%';
        $elements[] = ['name' => $elName, 'value' => $display];
    }

    $goldCode  = $report_data['gold']  !== null ? number_format((float)$report_data['gold'],  3) : '';
    $jointCode = $report_data['joint'] !== null ? number_format((float)$report_data['joint'], 3) : '';
}

// Full desktop render width for each report type
$cardWidth = ($isHallmark && $report_data) ? 750 : 680;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Verification — Rajaiswari</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0; padding: 0;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            -webkit-touch-callout: none;
        }
        img {
            pointer-events: none;
            -webkit-user-drag: none;
            user-drag: none;
        }

        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            background: #f1f3f6;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 0;
        }
        /* Desktop: restore normal scroll */
        @media (min-width: 700px) {
            html, body { overflow: auto; height: auto; min-height: 100vh; padding: 28px 0 60px; justify-content: flex-start; }
        }

        .scale-wrapper {
            width: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }
        @media (min-width: 700px) {
            .scale-wrapper { height: auto; align-items: flex-start; }
        }
        .scale-inner {
            transform-origin: top center;
        }

        /* Card always rendered at exact desktop pixel width */
        .report-card {
            width: <?= $cardWidth ?>px;
            background: #fff;
            border: 1px solid #e4e7ec;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
            overflow: hidden;
        }

        .receipt-header {
            display: block;
            width: 100%;
            max-width: 340px;
            margin: 0 auto;
        }

        .report-type-banner {
            text-align: center;
            font-family: 'Times New Roman', Times, serif;
            font-size: 28px;
            font-weight: bold;
            letter-spacing: 4px;
            color: #226ab3;
            padding: 14px 0;
            border-bottom: 3px solid #5a7188;
            background: #fff;
        }

        .tunch-container {
            padding: 16px 28px 14px;
            background: white;
            position: relative;
        }
        .tunch-container::before {
            content: '';
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-25deg);
            width: 200px; height: 200px;
            background-image: url('Varifiedstamp.png');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            opacity: 0.2;
            z-index: 1;
            pointer-events: none;
        }
        .tunch-container > * { position: relative; z-index: 2; }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .customer-info { flex: 1; }

        .customer-info-line {
            display: flex;
            font-size: 15px;
            line-height: 1.8;
            color: #000;
            font-weight: 600;
        }
        .customer-info-line.customer-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 3px;
        }
        .info-label  { display: inline-block; min-width: 120px; font-weight: 600; }
        .info-colon  { display: inline-block; width: 15px; text-align: center; }
        .info-value  { flex: 1; font-weight: 600; }
        .weight-conversion { font-size: 13px; color: #333; font-weight: 600; margin-left: 10px; }

        .qr-section {
            width: 100px;
            text-align: center;
            padding: 5px;
            margin-left: 15px;
            flex-shrink: 0;
        }
        .qr-date {
            font-size: 11px;
            color: #000;
            font-weight: 700;
            line-height: 1.4;
            margin-top: 5px;
            white-space: nowrap;
            font-family: 'Times New Roman', Times, serif;
        }

        .dotted-line { border-top: 3px dotted #000; margin: 6px 0; }

        .quality-info {
            font-size: 22px;
            font-weight: bold;
            margin: 5px 0;
            line-height: 1.5;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 20px;
            color: #000;
        }
        .quality-info span { white-space: nowrap; }

        .composition-table {
            width: 100%;
            margin: 2px 0 0 0;
            font-size: 11px;
            line-height: 1.1;
            border-collapse: collapse;
        }
        .composition-table td { padding: 1px 5px; font-weight: 600; vertical-align: top; }
        .composition-table td.element-name  { text-align: left;   padding-right: 3px; }
        .composition-table td.element-colon { text-align: center; padding: 1px 2px; }
        .composition-table td.element-value { text-align: left;   padding-left: 3px; padding-right: 15px; }

        .report-note  { font-size: 11px; line-height: 1.4; margin: 4px 0 0; font-weight: 600; color: #000; font-family: 'Times New Roman', Times, serif; }
        .report-codes { font-size: 11px; text-align: right; margin: 2px 0 0; font-weight: bold; color: #000; font-family: 'Times New Roman', Times, serif; }

        .verified-banner {
            background: #4fa756;
            color: #fff;
            text-align: center;
            padding: 14px 20px;
            font-size: 15px;
            font-weight: 700;
            font-family: 'Times New Roman', Times, serif;
            letter-spacing: .5px;
        }

        .report-footer {
            background: #f8f9fa;
            border-top: 1px solid #e4e7ec;
            padding: 14px 28px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            font-family: 'Times New Roman', Times, serif;
        }

        /* Error state */
        .error-box {
            width: 90%;
            max-width: 500px;
            background: #fff;
            border: 1px solid #e4e7ec;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
            padding: 60px 40px;
            text-align: center;
        }
        .error-icon { font-size: 64px; margin-bottom: 20px; }
        .error-box h2 { color: #dc2626; font-size: 22px; margin-bottom: 12px; font-family: sans-serif; }
        .error-box p  { color: #6b7280; font-size: 14px; line-height: 1.7; font-family: sans-serif; }

        /* Hallmark styles */
        .hallmark-report-title {
            text-align: center;
            color: #3eb1e3;
            font-size: 30px;
            font-weight: bold;
            margin: 0; padding: 2px 0;
            letter-spacing: 2px;
            line-height: 1;
            font-family: 'Times New Roman', Times, serif;
        }
        .hallmark-dotted { border-top: 2.5px dotted #000; margin: 0; }

        .hallmark-info-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 3px 8px;
        }
        .hallmark-left-info { flex: 1; line-height: 1.2; }
        .hallmark-left-info .customer-info-line {
            font-size: 15px;
            line-height: 1.4;
            display: block;
        }
        .hallmark-left-info .customer-info-line.customer-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .hallmark-left-info .info-label { display: inline-block; min-width: 110px; font-weight: 600; }
        .hallmark-left-info .info-colon { margin: 0 3px; }
        .hallmark-left-info .info-value  { font-weight: 600; }

        .main-box {
            border: 2.5px solid #000;
            display: flex;
            margin: 0 8px 8px;
            min-height: 100px;
        }
        .checkbox-section {
            flex: 1; padding: 8px 12px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 5px 10px;
            align-content: center;
        }
        .checkbox-item {
            display: flex; align-items: center;
            font-size: 12px; line-height: 1;
            font-weight: 600; color: #000;
        }
        .checkbox-box {
            width: 14px; height: 14px;
            border: 2px solid #000;
            margin-right: 4px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: bold; line-height: 1;
        }
        .hallmark-section {
            width: 220px;
            border-left: 2.5px solid #000;
            display: flex; flex-direction: column;
        }
        .hallmark-value-container {
            flex: 1;
            display: flex; align-items: center; justify-content: center;
            border-bottom: 2.5px solid #000;
            padding: 8px 12px; overflow: hidden;
        }
        .hallmark-value {
            font-size: 38px; font-weight: bold; line-height: 1;
            color: #000; text-align: center;
            word-wrap: break-word; word-break: break-word;
            font-family: 'Times New Roman', Times, serif;
        }
        .hallmark-label {
            font-size: 15px; font-weight: 700;
            text-align: center; padding: 4px;
            color: #000; line-height: 1;
            font-family: 'Times New Roman', Times, serif;
        }
    </style>
</head>
<body oncontextmenu="return false;" oncopy="return false;" oncut="return false;" onpaste="return false;">

<?php if ($report_data): ?>

    <div class="scale-wrapper">
        <div class="scale-inner" id="scaleInner">
            <div class="report-card" id="reportCard">

                <img src="receiptheader.png" alt="Rajaiswari" class="receipt-header">

                <?php if ($isHallmark): ?>
                <!-- ══ HALLMARK REPORT ══ -->
                <div style="background:white;position:relative;">
                    <div style="position:absolute;top:50%;left:50%;
                                transform:translate(-50%,-50%) rotate(-25deg);
                                width:250px;height:250px;
                                background-image:url('Varifiedstamp.png');
                                background-size:contain;background-repeat:no-repeat;
                                background-position:center;opacity:0.25;
                                z-index:1;pointer-events:none;"></div>

                    <div style="position:relative;z-index:2;">
                        <div class="hallmark-report-title">HALLMARK REPORT</div>
                        <div class="hallmark-dotted"></div>

                        <div class="hallmark-info-section">
                            <div class="hallmark-left-info">
                                <div class="customer-info-line customer-name">
                                    <span>Customer Name</span>
                                    <span class="info-colon">:</span>
                                    <span class="info-value"><?= htmlspecialchars($report_data['customer_name']) ?></span>
                                </div>
                                <div class="customer-info-line">
                                    <span class="info-label">Bill No</span>
                                    <span class="info-colon">:</span>
                                    <span class="info-value"><?= htmlspecialchars($report_data['order_id']) ?></span>
                                </div>
                                <div class="customer-info-line">
                                    <span class="info-label">Quantity</span>
                                    <span class="info-colon">:</span>
                                    <span class="info-value"><?= htmlspecialchars($report_data['quantity'] ?: '1') ?></span>
                                </div>
                                <div class="customer-info-line">
                                    <span class="info-label">Weight</span>
                                    <span class="info-colon">:</span>
                                    <span class="info-value">
                                        <?= htmlspecialchars($report_data['weight']) ?> Gm
                                        <span class="weight-conversion" id="weightConversion"></span>
                                    </span>
                                </div>
                                <div class="customer-info-line">
                                    <span class="info-label">Made by</span>
                                    <span class="info-colon">:</span>
                                    <span class="info-value"><?= htmlspecialchars($report_data['manufacturer'] ?: 'N/A') ?></span>
                                </div>
                                <div class="customer-info-line">
                                    <span class="info-label">Address</span>
                                    <span class="info-colon">:</span>
                                    <span class="info-value"><?= htmlspecialchars($report_data['address'] ?: 'N/A') ?></span>
                                </div>
                            </div>
                            <div class="qr-section">
                                <div id="qrcode"></div>
                                <div class="qr-date">
                                    <?= date('d-M-y', strtotime($report_data['created_at'])) ?><br>
                                    <?= date('g:i A', strtotime($report_data['created_at'])) ?>
                                </div>
                            </div>
                        </div>

                        <div class="main-box">
                            <div class="checkbox-section" id="checkboxSection">
                                <div class="checkbox-item" data-item="anklet">     <div class="checkbox-box"></div><span>Anklet</span></div>
                                <div class="checkbox-item" data-item="bangle">     <div class="checkbox-box"></div><span>Bangle</span></div>
                                <div class="checkbox-item" data-item="bracelet">   <div class="checkbox-box"></div><span>Bracelet</span></div>
                                <div class="checkbox-item" data-item="chain">      <div class="checkbox-box"></div><span>Chain</span></div>
                                <div class="checkbox-item" data-item="ear chain">  <div class="checkbox-box"></div><span>Ear Chain</span></div>
                                <div class="checkbox-item" data-item="earrings">   <div class="checkbox-box"></div><span>Earrings</span></div>
                                <div class="checkbox-item" data-item="mantasha">   <div class="checkbox-box"></div><span>Mantasha</span></div>
                                <div class="checkbox-item" data-item="necklace">   <div class="checkbox-box"></div><span>Necklace</span></div>
                                <div class="checkbox-item" data-item="nose pin">   <div class="checkbox-box"></div><span>Nose Pin</span></div>
                                <div class="checkbox-item" data-item="others">     <div class="checkbox-box"></div><span>Others</span></div>
                                <div class="checkbox-item" data-item="pendant">    <div class="checkbox-box"></div><span>Pendant</span></div>
                                <div class="checkbox-item" data-item="ring">       <div class="checkbox-box"></div><span>Ring</span></div>
                                <div class="checkbox-item" data-item="shakha path"><div class="checkbox-box"></div><span>ShakhaPath</span></div>
                                <div class="checkbox-item" data-item="taira">      <div class="checkbox-box"></div><span>Taira</span></div>
                                <div class="checkbox-item" data-item="tikli">      <div class="checkbox-box"></div><span>Tikli</span></div>
                                <div class="checkbox-item" data-item="watch">      <div class="checkbox-box"></div><span>Watch</span></div>
                            </div>
                            <div class="hallmark-section">
                                <div class="hallmark-value-container">
                                    <div class="hallmark-value"><?= htmlspecialchars($report_data['hallmark']) ?></div>
                                </div>
                                <div class="hallmark-label">HallMark</div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php else: ?>
                <!-- ══ TUNCH REPORT ══ -->
                <div class="report-type-banner">TUNCH REPORT</div>

                <div class="tunch-container">
                    <div class="report-header">
                        <div class="customer-info">
                            <div class="customer-info-line customer-name">
                                Customer Name : <?= htmlspecialchars($report_data['customer_name']) ?>
                            </div>
                            <div class="customer-info-line">
                                <span class="info-label">Sample Item</span>
                                <span class="info-colon">:</span>
                                <span class="info-value"><?= htmlspecialchars($report_data['item_name']) ?></span>
                            </div>
                            <div class="customer-info-line">
                                <span class="info-label">Sample Weight</span>
                                <span class="info-colon">:</span>
                                <span class="info-value">
                                    <?= htmlspecialchars($report_data['weight']) ?> Gm
                                    <span class="weight-conversion" id="weightConversion"></span>
                                </span>
                            </div>
                            <div class="customer-info-line">
                                <span class="info-label">Bill No</span>
                                <span class="info-colon">:</span>
                                <span class="info-value"><?= htmlspecialchars($report_data['order_id']) ?></span>
                            </div>
                        </div>
                        <div class="qr-section">
                            <div id="qrcode"></div>
                            <div class="qr-date">
                                <?= date('d-M-y', strtotime($report_data['created_at'])) ?><br>
                                <?= date('g:i A', strtotime($report_data['created_at'])) ?>
                            </div>
                        </div>
                    </div>

                    <div class="dotted-line"></div>

                    <div class="quality-info">
                        <span><?= $purityLabel ?> : <?= htmlspecialchars($purityValue ?? 'N/A') ?>%</span>
                        <span>Karat : <?= htmlspecialchars($report_data['karat'] ?? 'N/A') ?>K</span>
                    </div>

                    <div class="dotted-line"></div>

                    <?php if (!empty($elements)): ?>
                    <table class="composition-table">
                        <?php foreach (array_chunk($elements, 3) as $row): ?>
                        <tr>
                            <?php foreach ($row as $el): ?>
                            <td class="element-name"><?= htmlspecialchars($el['name']) ?></td>
                            <td class="element-colon">:</td>
                            <td class="element-value"><?= htmlspecialchars($el['value']) ?></td>
                            <?php endforeach; ?>
                            <?php for ($i = count($row); $i < 3; $i++): ?>
                            <td class="element-name"></td><td class="element-colon"></td><td class="element-value"></td>
                            <?php endfor; ?>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php endif; ?>

                    <div class="report-note">NB:- <?= htmlspecialchars($nbNote) ?></div>

                    <?php if ($goldCode !== '' || $jointCode !== ''): ?>
                    <div class="report-codes">
                        <?php
                        $parts = [];
                        if ($goldCode  !== '') $parts[] = 'Gold: '  . htmlspecialchars($goldCode);
                        if ($jointCode !== '') $parts[] = 'Joint: ' . htmlspecialchars($jointCode);
                        echo implode('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', $parts);
                        ?>
                    </div>
                    <?php endif; ?>

                </div><!-- /tunch-container -->
                <?php endif; ?>

                <div class="verified-banner">
                    ✅ This is the Verified Report by Rajaiswari.
                </div>

                <div class="report-footer">
                    This is an official report. For queries, contact our support team.
                </div>

            </div><!-- /report-card -->
        </div><!-- /scale-inner -->
    </div><!-- /scale-wrapper -->

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        // ── Fit card to screen with ZERO scroll in any direction ──
        const CARD_WIDTH = <?= $cardWidth ?>;

        function scaleCard() {
            const inner = document.getElementById('scaleInner');
            const card  = document.getElementById('reportCard');
            if (!inner || !card) return;

            const vw = window.innerWidth;
            const vh = window.innerHeight;

            // Desktop: full size, normal flow
            if (vw >= CARD_WIDTH) {
                inner.style.transform    = 'none';
                inner.style.marginBottom = '0';
                return;
            }

            // Mobile: scale so card fits width AND height — no scrollbar at all
            const cardH    = card.offsetHeight;
            const scaleByW = vw / CARD_WIDTH;
            const scaleByH = vh / cardH;
            const scale    = Math.min(scaleByW, scaleByH);

            inner.style.transform       = 'scale(' + scale + ')';
            inner.style.transformOrigin = 'top center';
            inner.style.marginBottom    = ((cardH * scale) - cardH) + 'px';
        }

        window.addEventListener('load',   scaleCard);
        window.addEventListener('resize', scaleCard);

        // Weight conversion (Vori Ana Roti Point)
        function convertGramToVoriAna(gram) {
            if (!gram || gram <= 0) return '';
            const totalPoints = Math.round((gram / 11.664) * 16 * 6 * 10);
            const bhori = Math.floor(totalPoints / 960);
            const rem   = totalPoints % 960;
            const ana   = Math.floor(rem / 60);
            const rem2  = rem % 60;
            const roti  = Math.floor(rem2 / 10);
            const point = rem2 % 10;
            return `[V:${bhori} A:${ana} R:${roti} P:${point}]`;
        }
        const wc = document.getElementById('weightConversion');
        if (wc) wc.textContent = ' ' + convertGramToVoriAna(<?= floatval($report_data['weight']) ?>);

        // QR code
        new QRCode(document.getElementById("qrcode"), {
            text: "https://www.app.rajaiswari.com/report_varification.php?id=<?= $report_id ?>",
            width: 90, height: 90
        });

        <?php if ($isHallmark): ?>
        // Auto-tick matching checkbox
        const itemName = "<?= strtolower(addslashes($report_data['item_name'])) ?>";
        document.querySelectorAll('.checkbox-item').forEach(item => {
            const itemType = item.getAttribute('data-item').toLowerCase();
            const match = itemName === itemType
                       || itemName.includes(' ' + itemType + ' ')
                       || itemName.startsWith(itemType + ' ')
                       || itemName.endsWith(' ' + itemType);
            if (match) {
                const cb = item.querySelector('.checkbox-box');
                cb.textContent = '✓';
                cb.style.cssText = 'font-size:12px;font-weight:bold;display:flex;align-items:center;justify-content:center;line-height:1;';
            }
        });
        <?php endif; ?>

        // Block copy / screenshot shortcuts
        document.addEventListener('keydown', function(e) {
            if (
                (e.ctrlKey && ['c','x','s','a','p','u'].includes(e.key.toLowerCase())) ||
                (e.ctrlKey && e.shiftKey && ['i','j','c'].includes(e.key.toLowerCase())) ||
                e.key === 'F12' || e.key === 'PrintScreen'
            ) {
                e.preventDefault();
                return false;
            }
        });
        document.addEventListener('keyup', function(e) {
            if (e.key === 'PrintScreen') navigator.clipboard.writeText('').catch(() => {});
        });
        document.addEventListener('selectstart', e => e.preventDefault());
        document.addEventListener('dragstart',   e => e.preventDefault());
        document.addEventListener('copy',        e => e.preventDefault());
        document.addEventListener('cut',         e => e.preventDefault());
    </script>

<?php else: ?>

    <div class="error-box">
        <div class="error-icon">❌</div>
        <h2>Report Not Found</h2>
        <p>The report you are trying to access does not exist or has been removed.</p>
        <p style="margin-top:14px;">Please verify the QR code or link and try again.</p>
    </div>

<?php endif; ?>

</body>
</html>