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

$isSilver      = false;
$purityLabel   = 'Gold Purity';
$purityValue   = null;
$elements      = [];
$goldCode      = '';
$jointCode     = '';
$isHallmark    = false;
$report_images = [];
$report_image  = null;
$nbNote        = 'The report pertains to specific point and not responsible for other point or melting issues.';

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

    if ($isHallmark) {
        $imgStmt = mysqli_prepare($conn,
            "SELECT img_path FROM report_images WHERE report_id=? AND img_type='hallmark' ORDER BY img_number ASC LIMIT 1");
        mysqli_stmt_bind_param($imgStmt, 'i', $report_id);
        mysqli_stmt_execute($imgStmt);
        $imgRow       = mysqli_fetch_assoc(mysqli_stmt_get_result($imgStmt));
        $report_image = $imgRow ? $imgRow['img_path'] : null;
        mysqli_stmt_close($imgStmt);
    } else {
        $imgStmt = mysqli_prepare($conn,
            "SELECT img_path FROM report_images WHERE report_id=? AND img_type='tunch' ORDER BY img_number ASC LIMIT 2");
        mysqli_stmt_bind_param($imgStmt, 'i', $report_id);
        mysqli_stmt_execute($imgStmt);
        $imgResult = mysqli_stmt_get_result($imgStmt);
        while ($r = mysqli_fetch_assoc($imgResult)) $report_images[] = $r['img_path'];
        mysqli_stmt_close($imgStmt);
    }
}

$cardWidth = ($isHallmark && $report_data) ? 750 : 680;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Verification — Rajaiswari</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&display=swap" rel="stylesheet">
    <style>
        /* ── Variables ─────────────────────────────────── */
        :root {
            --rg-gold:        #B8881E;
            --rg-gold-light:  #D4A83A;
            --rg-gold-pale:   #F5EDD6;
            --rg-gold-border: rgba(184,136,30,0.22);
            --rg-dark:        #1C1A16;
            --rg-text:        #2E2A22;
            --rg-muted:       #7A7060;
            --rg-bg:          #FDFAF4;
            --rg-bg2:         #F7F2E8;
            --rg-white:       #FFFFFF;
            --rg-nav-h:       72px;
        }

        /* ── Reset & Base ───────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; font-size: 16px; }
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--rg-bg);
            color: var(--rg-text);
            overflow-x: hidden;
            line-height: 1.6;
        }
        img { display: block; max-width: 100%; height: auto; }
        a { text-decoration: none; }

        /* ── Intro Overlay ─────────────────────────────── */
        #rg-intro-overlay {
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgb(255, 255, 255);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }
        #rg-intro-overlay.rg-intro-hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }
        #rg-intro-video {
            width: min(680px, 90vw);
            aspect-ratio: 1280 / 504;
            object-fit: contain;
            display: block;
        }

        /* ── Navbar ─────────────────────────────────────── */
        .rg-nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
            height: var(--rg-nav-h);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 48px;
            background: rgba(253,250,244,0.97);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--rg-gold-border);
            transition: box-shadow 0.3s, height 0.3s;
        }
        .rg-nav.rg-scrolled {
            box-shadow: 0 2px 24px rgba(120,90,30,0.1);
            height: 60px;
        }
        .rg-nav-brand {
            display: flex; align-items: center; gap: 10px;
            flex-shrink: 0; text-decoration: none;
        }
        .rg-nav-brand img { width: 100px; height: 50px; object-fit: contain; display: block; }
        .rg-nav-brand-text {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.4rem; font-weight: 600;
            color: var(--rg-gold); letter-spacing: 0.04em; line-height: 1.1; white-space: nowrap;
        }
        .rg-nav-links { display: flex; gap: 28px; list-style: none; }
        .rg-nav-links a {
            color: var(--rg-muted); font-size: 0.8rem; font-weight: 500;
            letter-spacing: 0.1em; text-transform: uppercase;
            transition: color 0.3s; position: relative;
        }
        .rg-nav-links a::after {
            content: ''; position: absolute; bottom: -4px; left: 0;
            width: 0; height: 1.5px; background: var(--rg-gold); transition: width 0.3s;
        }
        .rg-nav-links a:hover { color: var(--rg-gold); }
        .rg-nav-links a:hover::after { width: 100%; }
        .rg-nav-social { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .rg-social-btn {
            display: flex; align-items: center; justify-content: center;
            width: 38px; height: 38px; border-radius: 50%;
            transition: transform 0.2s, box-shadow 0.2s; flex-shrink: 0;
        }
        .rg-social-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 14px rgba(0,0,0,0.18); }
        .rg-fb { background: #1877F2; }
        .rg-wp { background: #25D366; }
        .rg-hamburger {
            display: none; flex-direction: column; gap: 5px;
            cursor: pointer; padding: 4px; background: none; border: none;
        }
        .rg-hamburger span { display: block; width: 24px; height: 2px; background: var(--rg-dark); transition: all 0.3s; }
        .rg-hamburger.rg-open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
        .rg-hamburger.rg-open span:nth-child(2) { opacity: 0; }
        .rg-hamburger.rg-open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }
        .rg-mobile-menu {
            display: none; position: fixed;
            top: var(--rg-nav-h); left: 0; right: 0;
            background: rgba(253,250,244,0.98);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--rg-gold-border);
            padding: 20px 24px 28px;
            flex-direction: column; gap: 0;
            z-index: 999;
            box-shadow: 0 8px 32px rgba(120,90,30,0.1);
        }
        .rg-mobile-menu.rg-open { display: flex; }
        .rg-mobile-menu a {
            display: block; padding: 13px 0;
            border-bottom: 1px solid var(--rg-gold-border);
            color: var(--rg-text); font-size: 0.9rem;
            font-weight: 500; letter-spacing: 0.08em; text-transform: uppercase;
            transition: color 0.3s;
        }
        .rg-mobile-menu a:hover { color: var(--rg-gold); }
        .rg-mob-social { display: flex; gap: 12px; margin-top: 18px; }
        .rg-mob-social a {
            flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px;
            padding: 16px 12px; border-bottom: none !important;
            color: #fff !important; font-size: 0.88rem; font-weight: 600;
            letter-spacing: 0.06em; border-radius: 6px;
            transition: opacity 0.2s; min-height: 52px;
            position: relative; z-index: 10; pointer-events: all !important;
            -webkit-tap-highlight-color: rgba(0,0,0,0.1);
            touch-action: manipulation; cursor: pointer;
        }
        .rg-mob-social a:hover, .rg-mob-social a:active { opacity: 0.88; }
        .rg-mob-social a.rg-fb { background: #1877F2; }
        .rg-mob-social a.rg-wp { background: #25D366; }
        .rg-mob-social a svg { flex-shrink: 0; pointer-events: none; }

        /* ── WhatsApp Floating Button ───────────────────── */
        .rg-wa-wrap { position: fixed; bottom: 28px; right: 28px; z-index: 9999; display: flex; flex-direction: column; align-items: flex-end; gap: 12px; }
        .rg-wa-popup { width: 300px; background: #fff; border-radius: 16px; box-shadow: 0 12px 48px rgba(0,0,0,0.16), 0 2px 8px rgba(0,0,0,0.08); overflow: hidden; opacity: 0; transform: translateY(16px) scale(0.96); pointer-events: none; transition: opacity 0.3s ease, transform 0.3s ease; transform-origin: bottom right; }
        .rg-wa-popup.rg-wa-open { opacity: 1; transform: translateY(0) scale(1); pointer-events: all; }
        .rg-wa-close { position: absolute; top: 10px; right: 12px; width: 22px; height: 22px; background: rgba(255,255,255,0.2); border: none; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #fff; transition: background 0.2s; z-index: 2; }
        .rg-wa-close:hover { background: rgba(255,255,255,0.35); }
        .rg-wa-popup-head { background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); padding: 18px 16px 16px; display: flex; align-items: center; gap: 12px; position: relative; }
        .rg-wa-avatar { width: 44px; height: 44px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; flex-shrink: 0; position: relative; }
        .rg-wa-online-dot { position: absolute; bottom: 1px; right: 1px; width: 10px; height: 10px; border-radius: 50%; background: #4ADE80; border: 2px solid #25D366; animation: rg-wa-pulse 2s ease-in-out infinite; }
        @keyframes rg-wa-pulse { 0%,100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.2); opacity: 0.8; } }
        .rg-wa-popup-info { flex: 1; min-width: 0; }
        .rg-wa-popup-name { display: block; color: #fff; font-weight: 600; font-size: 0.92rem; letter-spacing: 0.02em; line-height: 1.2; }
        .rg-wa-popup-status { display: flex; align-items: center; gap: 5px; color: rgba(255,255,255,0.82); font-size: 0.7rem; margin-top: 3px; }
        .rg-wa-status-dot { width: 7px; height: 7px; border-radius: 50%; background: #4ADE80; flex-shrink: 0; }
        .rg-wa-popup-body { padding: 16px; background: #E5DDD5; }
        .rg-wa-bubble { background: #fff; border-radius: 0 12px 12px 12px; padding: 10px 14px 8px; max-width: 85%; box-shadow: 0 1px 4px rgba(0,0,0,0.1); position: relative; }
        .rg-wa-bubble::before { content: ''; position: absolute; top: 0; left: -8px; border: 8px solid transparent; border-top-color: #fff; border-left: 0; }
        .rg-wa-bubble p { font-size: 0.84rem; color: #2E2A22; line-height: 1.55; margin-bottom: 4px; }
        .rg-wa-bubble p:last-of-type { margin-bottom: 0; }
        .rg-wa-time { display: block; text-align: right; font-size: 0.62rem; color: #999; margin-top: 6px; }
        .rg-wa-popup-btn { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 14px; background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color: #fff; font-size: 0.82rem; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; transition: opacity 0.2s, transform 0.2s; border: none; cursor: pointer; }
        .rg-wa-popup-btn:hover { opacity: 0.92; transform: translateY(-1px); }
        .rg-wa-fab { width: auto; min-width: 56px; height: 56px; background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); border: none; border-radius: 100px; display: flex; align-items: center; gap: 10px; padding: 0 20px 0 16px; cursor: pointer; box-shadow: 0 4px 20px rgba(37,211,102,0.4); position: relative; transition: transform 0.25s ease, box-shadow 0.25s ease, padding 0.25s ease; overflow: hidden; }
        .rg-wa-fab:hover { transform: translateY(-3px) scale(1.03); box-shadow: 0 8px 28px rgba(37,211,102,0.5); }
        .rg-wa-fab:active { transform: scale(0.97); }
        .rg-wa-fab-icon { display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: opacity 0.2s, transform 0.2s; }
        .rg-wa-fab-icon--close { position: absolute; left: 16px; opacity: 0; transform: rotate(-90deg); }
        .rg-wa-fab.rg-wa-active .rg-wa-fab-icon--wa   { opacity: 0; transform: rotate(90deg); }
        .rg-wa-fab.rg-wa-active .rg-wa-fab-icon--close { opacity: 1; transform: rotate(0); }
        .rg-wa-label { color: #fff; font-size: 0.8rem; font-weight: 600; letter-spacing: 0.06em; white-space: nowrap; transition: opacity 0.2s, max-width 0.3s; max-width: 120px; overflow: hidden; }
        .rg-wa-fab.rg-wa-active .rg-wa-label { opacity: 0; max-width: 0; }
        .rg-wa-fab-ping { position: absolute; inset: 0; border-radius: 100px; border: 2px solid rgba(37,211,102,0.6); animation: rg-wa-ring 2.5s ease-out infinite; pointer-events: none; }
        @keyframes rg-wa-ring { 0% { transform: scale(1); opacity: 0.7; } 70%,100% { transform: scale(1.35); opacity: 0; } }

        /* ── Page layout ────────────────────────────────── */
        .rg-page-body {
            padding-top: calc(var(--rg-nav-h) + 40px);
            padding-bottom: 60px;
            min-height: calc(100vh - var(--rg-nav-h));
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            background: #f1f3f6;
        }

        /* ── Report Card Styles ─────────────────────────── */
        .report-card-wrapper {
            width: 100%;
            display: flex;
            justify-content: center;
            padding: 0 16px;
        }

        *, *::before, *::after {
            -webkit-user-select: none; -moz-user-select: none;
            -ms-user-select: none;
        }
        img { pointer-events: none; -webkit-user-drag: none; }

        .scale-wrapper { width: 100%; display: flex; justify-content: center; align-items: flex-start; }
        .scale-inner { transform-origin: top center; }

        .report-card {
            width: <?= $cardWidth ?>px;
            background: #fff;
            border: 1px solid #e4e7ec;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
            overflow: hidden;
        }

        .receipt-header { display: block; width: 100%; max-width: 340px; margin: 0 auto; }

        .report-type-banner {
            text-align: center;
            font-family: 'Times New Roman', Times, serif;
            font-size: 28px; font-weight: bold;
            letter-spacing: 4px; color: #226ab3;
            padding: 14px 0;
            border-bottom: 3px solid #5a7188;
            background: #fff;
        }

        .tunch-container { padding: 16px 28px 14px; background: white; position: relative; }
        .tunch-container::before {
            content: ''; position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-25deg);
            width: 200px; height: 200px;
            background-image: url('Varifiedstamp.png');
            background-size: contain; background-repeat: no-repeat;
            background-position: center; opacity: 0.2; z-index: 1; pointer-events: none;
        }
        .tunch-container > * { position: relative; z-index: 2; }

        .report-header { display: flex; justify-content: space-between; align-items: flex-start; }
        .customer-info { flex: 1; }

        .customer-info-line { display: flex; font-size: 15px; line-height: 1.8; color: #000; font-weight: 600; }
        .customer-info-line.customer-name { font-size: 20px; font-weight: bold; margin-bottom: 3px; }
        .info-label  { display: inline-block; min-width: 120px; font-weight: 600; }
        .info-colon  { display: inline-block; width: 15px; text-align: center; }
        .info-value  { flex: 1; font-weight: 600; }
        .weight-conversion { font-size: 13px; color: #333; font-weight: 600; margin-left: 10px; }

        .qr-section { width: 100px; text-align: center; padding: 5px; margin-left: 15px; flex-shrink: 0; }
        .qr-date { font-size: 11px; color: #000; font-weight: 700; line-height: 1.4; margin-top: 5px; white-space: nowrap; font-family: 'Times New Roman', Times, serif; }

        .dotted-line { border-top: 3px dotted #000; margin: 6px 0; }

        .quality-info {
            font-size: 22px; font-weight: bold; margin: 5px 0; line-height: 1.5;
            display: flex; justify-content: space-around; flex-wrap: wrap; gap: 20px; color: #000;
        }
        .quality-info span { white-space: nowrap; }

        .composition-table { width: 100%; margin: 2px 0 0; font-size: 11px; line-height: 1.1; border-collapse: collapse; }
        .composition-table td { padding: 1px 5px; font-weight: 600; vertical-align: top; }
        .composition-table td.element-name  { text-align: left; padding-right: 3px; }
        .composition-table td.element-colon { text-align: center; padding: 1px 2px; }
        .composition-table td.element-value { text-align: left; padding-left: 3px; padding-right: 15px; }

        .report-note { font-size: 11px; line-height: 1.4; margin: 4px 0 0; font-weight: 600; color: #000; font-family: 'Times New Roman', Times, serif; }

        .verified-banner {
            background: #4fa756; color: #fff; text-align: center;
            padding: 14px 20px; font-size: 15px; font-weight: 700;
            font-family: 'Times New Roman', Times, serif; letter-spacing: .5px;
        }
        .report-footer-note {
            background: #f8f9fa; border-top: 1px solid #e4e7ec;
            padding: 14px 28px; text-align: center;
            font-size: 12px; color: #6b7280; font-family: 'Times New Roman', Times, serif;
        }

        .error-box { width: 90%; max-width: 500px; background: #fff; border: 1px solid #e4e7ec; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,.06); padding: 60px 40px; text-align: center; }
        .error-icon { font-size: 64px; margin-bottom: 20px; }
        .error-box h2 { color: #dc2626; font-size: 22px; margin-bottom: 12px; font-family: sans-serif; }
        .error-box p  { color: #6b7280; font-size: 14px; line-height: 1.7; font-family: sans-serif; }

        .hallmark-info-section { display: flex; justify-content: space-between; align-items: flex-start; padding: 3px 8px; }
        .hallmark-left-info { flex: 1; line-height: 1.2; }
        .hallmark-left-info .customer-info-line { font-size: 15px; line-height: 1.4; display: block; }
        .hallmark-left-info .customer-info-line.customer-name { font-size: 20px; font-weight: bold; margin-bottom: 2px; }
        .hallmark-left-info .info-label { display: inline-block; min-width: 110px; font-weight: 600; }
        .hallmark-left-info .info-colon { margin: 0 3px; }
        .hallmark-left-info .info-value  { font-weight: 600; }

        .main-box { border: 2.5px solid #000; display: flex; margin: 0 8px 0; min-height: 100px; }
        .checkbox-section { flex: 1; padding: 8px 12px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px 10px; align-content: center; }
        .checkbox-item { display: flex; align-items: center; font-size: 12px; line-height: 1; font-weight: 600; color: #000; }
        .checkbox-box { width: 14px; height: 14px; border: 2px solid #000; margin-right: 4px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; line-height: 1; }
        .hallmark-section { width: 220px; border-left: 2.5px solid #000; display: flex; flex-direction: column; }
        .hallmark-value-container { flex: 1; display: flex; align-items: center; justify-content: center; border-bottom: 2.5px solid #000; padding: 8px 12px; overflow: hidden; }
        .hallmark-value { font-size: 38px; font-weight: bold; line-height: 1; color: #000; text-align: center; word-wrap: break-word; word-break: break-word; font-family: 'Times New Roman', Times, serif; }
        .hallmark-label { font-size: 15px; font-weight: 700; text-align: center; padding: 4px; color: #000; line-height: 1; font-family: 'Times New Roman', Times, serif; }

        /* ── Footer ─────────────────────────────────────── */
        .rg-footer {
            background: var(--rg-dark);
            padding: 60px clamp(20px, 6vw, 80px) 0;
            margin-top: 60px;
        }
        .rg-footer-top {
            display: grid;
            grid-template-columns: 1.6fr 1fr 1fr 1.4fr;
            gap: 48px;
            padding-bottom: 48px;
            border-bottom: 1px solid rgba(253,250,244,0.08);
        }
        .rg-footer-brand { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
        .rg-footer-brand img { width: 90px; height: 45px; object-fit: contain; opacity: 0.9; }
        .rg-footer-brand-fallback { font-family: 'Cormorant Garamond', serif; font-size: 1.3rem; font-weight: 600; color: var(--rg-gold-light); letter-spacing: 0.05em; }
        .rg-footer-tagline { color: rgba(253,250,244,0.38); font-size: 0.78rem; font-weight: 300; line-height: 1.75; margin-bottom: 20px; }
        .rg-footer-map { width: 100%; border-radius: 8px; overflow: hidden; border: 1px solid rgba(184,136,30,0.2); margin-bottom: 20px; opacity: 0.85; transition: opacity 0.2s; }
        .rg-footer-map:hover { opacity: 1; }
        .rg-footer-map iframe { display: block; }
        .rg-footer-socials { display: flex; gap: 10px; }
        .rg-footer-social { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: transform 0.2s, box-shadow 0.2s, opacity 0.2s; }
        .rg-footer-social:hover { transform: translateY(-2px); opacity: 0.9; box-shadow: 0 4px 14px rgba(0,0,0,0.3); }
        .rg-footer-social-fb { background: #1877F2; }
        .rg-footer-social-wa { background: #25D366; }
        .rg-footer-col-title { font-family: 'Outfit', sans-serif; font-size: 0.65rem; font-weight: 700; letter-spacing: 0.2em; text-transform: uppercase; color: var(--rg-gold-light); margin-bottom: 18px; padding-bottom: 10px; border-bottom: 1px solid rgba(184,136,30,0.2); }
        .rg-footer-linklist { list-style: none; display: flex; flex-direction: column; gap: 10px; }
        .rg-footer-linklist a { color: rgba(253,250,244,0.42); font-size: 0.8rem; font-weight: 300; letter-spacing: 0.04em; transition: color 0.25s, padding-left 0.25s; display: inline-block; }
        .rg-footer-linklist a:hover { color: var(--rg-gold-light); padding-left: 4px; }
        .rg-footer-contact-list { list-style: none; display: flex; flex-direction: column; gap: 12px; }
        .rg-footer-contact-list li { display: flex; align-items: flex-start; gap: 9px; color: rgba(253,250,244,0.42); font-size: 0.78rem; font-weight: 300; line-height: 1.6; }
        .rg-footer-contact-list svg { color: var(--rg-gold); flex-shrink: 0; margin-top: 3px; }
        .rg-footer-contact-list a { color: rgba(253,250,244,0.42); transition: color 0.25s; }
        .rg-footer-contact-list a:hover { color: var(--rg-gold-light); }
        .rg-footer-bottom { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; padding: 20px 0; }
        .rg-footer-copy, .rg-footer-credit { font-size: 0.65rem; color: rgba(253,250,244,0.18); letter-spacing: 0.04em; }

        /* ── Responsive ─────────────────────────────────── */
        @media (max-width: 900px) {
            .rg-nav { padding: 0 20px; }
            .rg-nav-links, .rg-nav-social { display: none; }
            .rg-hamburger { display: flex; }
            .rg-footer-top { grid-template-columns: 1fr 1fr; gap: 36px; }
            .rg-footer-col-brand { grid-column: span 2; }
        }
        @media (max-width: 600px) {
            :root { --rg-nav-h: 64px; }
            .rg-wa-wrap { bottom: 18px; right: 16px; }
            .rg-wa-popup { width: 272px; }
            .rg-wa-fab { height: 52px; padding: 0 16px 0 13px; }
            .rg-wa-label { font-size: 0.75rem; }
            .rg-footer-top { grid-template-columns: 1fr 1fr; gap: 28px; }
            .rg-footer-col-brand { grid-column: span 2; }
            .rg-footer-bottom { flex-direction: column; gap: 4px; }
        }
        @media (max-width: 400px) {
            .rg-footer-top { grid-template-columns: 1fr; }
            .rg-footer-col-brand { grid-column: span 1; }
        }
    </style>
</head>
<body oncontextmenu="return false;" oncopy="return false;" oncut="return false;" onpaste="return false;">
<!-- ══ INTRO OVERLAY ══ -->
<div id="rg-intro-overlay">
    <video
        id="rg-intro-video"
        src="rj logo animation.mp4"
        autoplay
        muted
        playsinline
        preload="auto"
    ></video>
</div>

<script>
    (function () {
        var overlay = document.getElementById('rg-intro-overlay');
        var video   = document.getElementById('rg-intro-video');

        function hideOverlay() {
            overlay.classList.add('rg-intro-hidden');
        }

        // Hide when video ends
        video.addEventListener('ended', hideOverlay);

        // Fallback: hide after 4.5s in case video fails to load/play
        var fallback = setTimeout(hideOverlay, 3000);

        // Clear fallback if video plays fine
        video.addEventListener('ended', function () { clearTimeout(fallback); });

        // If video can't play at all (e.g. missing file), hide after 1s
        video.addEventListener('error', function () {
            clearTimeout(fallback);
            setTimeout(hideOverlay, 800);
        });
    })();
</script>

<!-- ══ NAV ══════════════════════════════════════ -->
<nav class="rg-nav" id="rgNavbar">
    <a href="https://www.rajaiswari.com/index.php" class="rg-nav-brand">
        <img src="https://www.rajaiswari.com/logo.jpg" alt="Raj Aiswari Gold" width="100" height="50" id="rgNavLogo" onerror="this.style.display='none'">
        <span class="rg-nav-brand-text" id="rgNavText">Raj Aiswari</span>
    </a>

    <ul class="rg-nav-links">
        <li><a href="https://www.rajaiswari.com/index.php">Home</a></li>
        <li><a href="https://www.rajaiswari.com/index.php#about">About Us</a></li>
        <li><a href="https://www.rajaiswari.com/products.php">Products</a></li>
        <li><a href="https://www.rajaiswari.com/gold_lab.php">Gold Lab</a></li>
        <li><a href="https://www.rajaiswari.com/index.php#clients">Clients</a></li>
        <li><a href="https://www.rajaiswari.com/sister-concern.php">Sister Concern</a></li>
        <li><a href="https://www.rajaiswari.com/contact.php">Contact</a></li>
    </ul>

    <div class="rg-nav-social">
        <a href="https://www.facebook.com/rajasiwari" target="_blank" rel="noopener" class="rg-social-btn rg-fb" title="Facebook">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.236 2.686.236v2.97h-1.513c-1.491 0-1.956.93-1.956 1.886v2.267h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>
        </a>
        <a href="https://wa.me/8801716469866" target="_blank" rel="noopener" class="rg-social-btn rg-wp" title="WhatsApp">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        </a>
    </div>

    <button class="rg-hamburger" id="rgHamburger" aria-label="Toggle menu">
        <span></span><span></span><span></span>
    </button>
</nav>

<!-- Mobile Menu -->
<nav class="rg-mobile-menu" id="rgMobileMenu">
    <a href="https://www.rajaiswari.com/index.php"          onclick="rgCloseMenu()">Home</a>
    <a href="https://www.rajaiswari.com/index.php#about"    onclick="rgCloseMenu()">About Us</a>
    <a href="https://www.rajaiswari.com/products.php"       onclick="rgCloseMenu()">Products</a>
    <a href="https://www.rajaiswari.com/gold_lab.php"       onclick="rgCloseMenu()">Gold Lab</a>
    <a href="https://www.rajaiswari.com/index.php#clients"  onclick="rgCloseMenu()">Clients</a>
    <a href="https://www.rajaiswari.com/sister-concern.php" onclick="rgCloseMenu()">Sister Concern</a>
    <a href="https://www.rajaiswari.com/contact.php"        onclick="rgCloseMenu()">Contact</a>
    <div class="rg-mob-social">
        <a href="https://www.facebook.com/rajasiwari" target="_blank" rel="noopener" class="rg-fb">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="white" style="pointer-events:none;"><path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.236 2.686.236v2.97h-1.513c-1.491 0-1.956.93-1.956 1.886v2.267h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>
            Facebook
        </a>
        <a href="https://wa.me/8801716469866" target="_blank" rel="noopener" class="rg-wp">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="white" style="pointer-events:none;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            WhatsApp
        </a>
    </div>
</nav>

<!-- ══ WHATSAPP FLOATING BUTTON ══ -->
<!-- <div class="rg-wa-wrap" id="rgWaWrap">
    <div class="rg-wa-popup" id="rgWaPopup" role="dialog" aria-label="Chat with us on WhatsApp">
        <button class="rg-wa-close" id="rgWaClose" aria-label="Close chat popup">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
        <div class="rg-wa-popup-head">
            <div class="rg-wa-avatar">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="white"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                <span class="rg-wa-online-dot"></span>
            </div>
            <div class="rg-wa-popup-info">
                <span class="rg-wa-popup-name">Raj Aiswari</span>
                <span class="rg-wa-popup-status">
                    <span class="rg-wa-status-dot"></span>Online — typically replies instantly
                </span>
            </div>
        </div>
        <div class="rg-wa-popup-body">
            <div class="rg-wa-bubble">
                <p>Hello! 👋</p>
                <p>How can we help you today? Feel free to ask us anything about our products or services.</p>
                <span class="rg-wa-time">Just now</span>
            </div>
        </div>
        <a href="https://wa.me/8801716469866?text=Hello%2C%20I%20would%20like%20to%20know%20more%20about%20your%20products." target="_blank" rel="noopener" class="rg-wa-popup-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="white"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            Start Chat on WhatsApp
        </a>
    </div>
    <button class="rg-wa-fab" id="rgWaFab" aria-label="Chat with us on WhatsApp">
        <span class="rg-wa-fab-icon rg-wa-fab-icon--wa">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="white"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        </span>
        <span class="rg-wa-fab-icon rg-wa-fab-icon--close">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </span>
        <span class="rg-wa-label">Chat with us</span>
        <span class="rg-wa-fab-ping"></span>
    </button>
</div> -->

<!-- ══ PAGE BODY ══ -->
<div class="rg-page-body">

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

                        <?php if (!empty($report_image)): ?>
                        <div style="display:flex;gap:0;margin:6px 8px 4px;align-items:stretch;min-height:90px;">
                            <div style="flex:0 0 58.333%;max-width:58.333%;display:flex;align-items:flex-end;padding-right:8px;">
                                <img src="<?= htmlspecialchars($report_image) ?>" alt="Sample photo"
                                    style="width:auto;height:105px;object-fit:contain;border-radius:4px;border:1px solid #ddd;">
                            </div>
                            <div style="flex:0 0 41.667%;max-width:41.667%;display:flex;flex-direction:column;justify-content:flex-end;">
                                <div style="text-align:center;font-size:11px;font-weight:bold;color:#000;padding-bottom:2px;">
                                    Authorized Signature
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div style="text-align:center;font-size:11px;font-weight:bold;color:#000;margin:6px 8px 4px;padding-bottom:2px;">
                            Authorized Signature
                        </div>
                        <?php endif; ?>

                    </div>
                </div>

                <?php else: ?>
                <!-- ══ TUNCH REPORT ══ -->
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

                    <div style="display:flex;gap:0;margin-top:6px;align-items:stretch;min-height:90px;">
                        <div style="flex:0 0 58.333%;max-width:58.333%;display:flex;align-items:flex-end;gap:8px;flex-wrap:wrap;padding-right:8px;">
                            <?php foreach ($report_images as $img_path): ?>
                            <img src="<?= htmlspecialchars($img_path) ?>" alt="Sample photo"
                                style="width:auto;height:95px;object-fit:contain;border-radius:4px;border:1px solid #ddd;">
                            <?php endforeach; ?>
                        </div>
                        <div style="flex:0 0 41.667%;max-width:41.667%;display:flex;flex-direction:column;justify-content:space-between;">
                            <div style="display:flex;justify-content:flex-end;gap:18px;font-size:11px;font-weight:bold;color:#000;">
                                <?php if ($goldCode !== ''): ?><span>Gold : <?= htmlspecialchars($goldCode) ?></span><?php endif; ?>
                                <?php if ($jointCode !== ''): ?><span>Joint : <?= htmlspecialchars($jointCode) ?></span><?php endif; ?>
                            </div>
                            <div style="text-align:center;font-size:11px;font-weight:bold;color:#000;padding-bottom:2px;">
                                Authorized Signature
                            </div>
                        </div>
                    </div>

                </div><!-- /tunch-container -->
                <?php endif; ?>

                <!-- <div class="verified-banner">
                    ✅ This is the Verified Report by Rajaiswari.
                </div>

                <div class="report-footer-note">
                    This is an official report. For queries, contact our support team.
                </div> -->

            </div><!-- /report-card -->
        </div><!-- /scale-inner -->
    </div><!-- /scale-wrapper -->

<?php else: ?>

    <div class="error-box">
        <div class="error-icon">❌</div>
        <h2>Report Not Found</h2>
        <p>The report you are trying to access does not exist or has been removed.</p>
        <p style="margin-top:14px;">Please verify the QR code or link and try again.</p>
    </div>

<?php endif; ?>

</div><!-- /rg-page-body -->

<!-- ══ FOOTER ══ -->
<footer class="rg-footer">
    <div class="rg-footer-top">
        <div class="rg-footer-col rg-footer-col-brand">
            <div class="rg-footer-brand">
                <img src="https://www.rajaiswari.com/logo.jpg" alt="Raj Aiswari" width="90" height="45"
                    onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                <span class="rg-footer-brand-fallback" style="display:none;">Raj Aiswari</span>
            </div>
            <p class="rg-footer-tagline">Bangladesh's trusted partner for gold testing &amp; precision measurement technology since 1998.</p>
            <div class="rg-footer-map">
                <iframe
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d14761.482892067597!2d91.81729316711423!3d22.33962664130348!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x30ad2759615a1a91%3A0xc536ffd9b88afada!2sRajaiswari%20Gold%20Testing%20Center!5e0!3m2!1sen!2sbd!4v1773204381328!5m2!1sen!2sbd"
                    width="100%" height="140" style="border:0;" allowfullscreen="" loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade" title="Raj Aiswari Location">
                </iframe>
            </div>
            <div class="rg-footer-socials">
                <a href="https://www.facebook.com/rajasiwari" target="_blank" rel="noopener" class="rg-footer-social rg-footer-social-fb" title="Facebook">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="white"><path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.236 2.686.236v2.97h-1.513c-1.491 0-1.956.93-1.956 1.886v2.267h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>
                </a>
                <a href="https://wa.me/8801716469866" target="_blank" rel="noopener" class="rg-footer-social rg-footer-social-wa" title="WhatsApp">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="white"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                </a>
            </div>
        </div>

        <div class="rg-footer-col">
            <h4 class="rg-footer-col-title">Quick Links</h4>
            <ul class="rg-footer-linklist">
                <li><a href="https://www.rajaiswari.com/index.php">Home</a></li>
                <li><a href="https://www.rajaiswari.com/index.php#about">About Us</a></li>
                <li><a href="https://www.rajaiswari.com/products.php">Products</a></li>
                <li><a href="https://www.rajaiswari.com/index.php#gallery">Gallery</a></li>
                <li><a href="https://www.rajaiswari.com/index.php#clients">Clients</a></li>
                <li><a href="https://www.rajaiswari.com/contact.php">Contact</a></li>
            </ul>
        </div>

        <div class="rg-footer-col">
            <h4 class="rg-footer-col-title">Company</h4>
            <ul class="rg-footer-linklist">
                <li><a href="https://www.rajaiswari.com/sister-concern.php">Sister Concerns</a></li>
                <li><a href="https://www.rajaiswari.com/employees.php">Our Team</a></li>
                <li><a href="https://www.rajaiswari.com/software.php">Software Services</a></li>
                <li><a href="https://www.fischerindia.co.in/" target="_blank" rel="noopener">Fischer India</a></li>
                <li><a href="https://www.helmut-fischer.com/" target="_blank" rel="noopener">Fischer Germany</a></li>
            </ul>
        </div>

        <div class="rg-footer-col">
            <h4 class="rg-footer-col-title">Contact Us</h4>
            <ul class="rg-footer-contact-list">
                <li>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <span>Hazari Market (2nd Floor), Hazari Lane, Chittagong — 4000</span>
                </li>
                <li>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.8a19.79 19.79 0 01-3.07-8.67A2 2 0 012 .91h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 8.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                    <a href="tel:+8801716469866">01716-469866</a>
                </li>
                <li>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <a href="mailto:shimudeb@gmail.com">shimudeb@gmail.com</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="rg-footer-bottom">
        <span class="rg-footer-copy">&copy; <?php echo date('Y'); ?> Raj Aiswari Gold. All rights reserved.</span>
        <span class="rg-footer-credit">Fischer Measurement Technologies — Bangladesh</span>
    </div>
</footer>

<!-- ══ SCRIPTS ══ -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    /* ── Nav logo: hide text if image loads ── */
    var navLogo = document.getElementById('rgNavLogo');
    var navText = document.getElementById('rgNavText');
    if (navLogo) {
        navLogo.onload  = function () { navText.style.display = 'none'; };
        navLogo.onerror = function () { navLogo.style.display = 'none'; navText.style.display = ''; };
        if (navLogo.complete && navLogo.naturalWidth > 0) navText.style.display = 'none';
    }

    /* ── Hamburger ── */
    var hamburger  = document.getElementById('rgHamburger');
    var mobileMenu = document.getElementById('rgMobileMenu');
    if (hamburger) {
        hamburger.addEventListener('click', function () {
            hamburger.classList.toggle('rg-open');
            mobileMenu.classList.toggle('rg-open');
        });
    }

    /* ── Navbar scroll ── */
    var navbar = document.getElementById('rgNavbar');
    window.addEventListener('scroll', function () {
        navbar.classList.toggle('rg-scrolled', window.scrollY > 60);
    });

    /* ── WhatsApp Popup ── */
    var fab      = document.getElementById('rgWaFab');
    var popup    = document.getElementById('rgWaPopup');
    var closeBtn = document.getElementById('rgWaClose');
    var shown    = false;

    function openPopup()  { popup.classList.add('rg-wa-open');    fab.classList.add('rg-wa-active');    shown = true; }
    function closePopup() { popup.classList.remove('rg-wa-open'); fab.classList.remove('rg-wa-active'); shown = false; }

    if (fab)      fab.addEventListener('click', function () { shown ? closePopup() : openPopup(); });
    if (closeBtn) closeBtn.addEventListener('click', function (e) { e.stopPropagation(); closePopup(); });

    if (!sessionStorage.getItem('rg_wa_shown')) {
        setTimeout(function () { openPopup(); sessionStorage.setItem('rg_wa_shown', '1'); }, 4000);
    }

    document.addEventListener('click', function (e) {
        var wrap = document.getElementById('rgWaWrap');
        if (shown && wrap && !wrap.contains(e.target)) closePopup();
    });

    function rgCloseMenu() {
        var h = document.getElementById('rgHamburger');
        var m = document.getElementById('rgMobileMenu');
        if (h) h.classList.remove('rg-open');
        if (m) m.classList.remove('rg-open');
    }

    <?php if ($report_data): ?>
    /* ── Report card scaling — no layout changes, pixel-perfect on all devices ── */
const CARD_WIDTH = <?= $cardWidth ?>;

function scaleCard() {
    var inner  = document.getElementById('scaleInner');
    var card   = document.getElementById('reportCard');
    if (!inner || !card) return;

    var vw     = document.documentElement.clientWidth;
    var padded = vw - 32;

    if (padded >= CARD_WIDTH) {
        inner.style.transform    = 'none';
        inner.style.marginBottom = '0';
        return;
    }

    var scale  = padded / CARD_WIDTH;
    var cardH  = card.getBoundingClientRect().height || card.offsetHeight;
    inner.style.transform       = 'scale(' + scale + ')';
    inner.style.transformOrigin = 'top center';
    inner.style.marginBottom    = Math.round((scale - 1) * cardH) + 'px';
}

/* 
 * Run immediately — the script is at bottom of body so DOM exists.
 * This fires BEFORE window.load, eliminating the resize jump entirely.
 * No layout or design is changed — card stays fixed-width, just scaled.
 */
scaleCard();
window.addEventListener('resize', scaleCard);

    /* ── Weight conversion ── */
    function convertGramToVoriAna(gram) {
        if (!gram || gram <= 0) return '';
        var totalPoints = Math.round((gram / 11.664) * 16 * 6 * 10);
        var bhori = Math.floor(totalPoints / 960);
        var rem   = totalPoints % 960;
        var ana   = Math.floor(rem / 60);
        var rem2  = rem % 60;
        var roti  = Math.floor(rem2 / 10);
        var point = rem2 % 10;
        return '[V:' + bhori + ' A:' + ana + ' R:' + roti + ' P:' + point + ']';
    }
    var wc = document.getElementById('weightConversion');
    if (wc) wc.textContent = ' ' + convertGramToVoriAna(<?= floatval($report_data['weight']) ?>);

    /* ── QR Code ── */
    new QRCode(document.getElementById("qrcode"), {
        text: "https://www.app.rajaiswari.com/report_varification.php?id=<?= $report_id ?>",
        width: 90, height: 90
    });

    <?php if ($isHallmark): ?>
    /* ── Hallmark checkboxes ── */
    var itemName = "<?= strtolower(addslashes($report_data['item_name'])) ?>";
    document.querySelectorAll('.checkbox-item').forEach(function(item) {
        var itemType = item.getAttribute('data-item').toLowerCase();
        var match = itemName === itemType
                 || itemName.includes(' ' + itemType + ' ')
                 || itemName.startsWith(itemType + ' ')
                 || itemName.endsWith(' ' + itemType);
        if (match) {
            var cb = item.querySelector('.checkbox-box');
            cb.textContent = '✓';
            cb.style.cssText = 'font-size:12px;font-weight:bold;display:flex;align-items:center;justify-content:center;line-height:1;';
        }
    });
    <?php endif; ?>

    /* ── Anti-copy ── */
    document.addEventListener('keydown', function(e) {
        if (
            (e.ctrlKey && ['c','x','s','a','p','u'].includes(e.key.toLowerCase())) ||
            (e.ctrlKey && e.shiftKey && ['i','j','c'].includes(e.key.toLowerCase())) ||
            e.key === 'F12' || e.key === 'PrintScreen'
        ) { e.preventDefault(); return false; }
    });
    document.addEventListener('keyup', function(e) {
        if (e.key === 'PrintScreen') navigator.clipboard.writeText('').catch(function(){});
    });
    document.addEventListener('selectstart', function(e) { e.preventDefault(); });
    document.addEventListener('dragstart',   function(e) { e.preventDefault(); });
    document.addEventListener('copy',        function(e) { e.preventDefault(); });
    document.addEventListener('cut',         function(e) { e.preventDefault(); });
    <?php endif; ?>
</script>

</body>
</html>