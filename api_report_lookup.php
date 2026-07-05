<?php
/**
 * HUID Report Lookup API
 * -----------------------
 * Deploy this file on your MAIN domain (same server as mydb.php),
 * e.g. https://app.rajaiswari.com/api_report_lookup.php
 *
 * Usage:  GET  api_report_lookup.php?huid=2226B9
 *
 * Returns JSON. CORS is enabled so this can be called via fetch()
 * from a search page hosted on ANY domain.
 */

require 'mydb.php';

// ── CORS ─────────────────────────────────────────────────────────────────
// "*" allows any domain to call this API. If you want to restrict it to
// specific domains later, replace "*" with a specific origin check.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$GOLD_ELEMENTS   = ['Silver','Platinum','Bismuth','Copper','Palladium','Nickel','Zinc','Antimony','Indium','Cadmium','Iron','Titanium','Iridium','Tin','Ruthenium','Rhodium','Lead','Vanadium','Cobalt','Osmium','Manganese'];
$SILVER_ELEMENTS = ['Copper','Palladium','Nickel','Zinc','Antimony','Indium','Cadmium','Iron','Titanium','Iridium','Tin','Ruthenium','Rhodium','Lead','Vanadium','Cobalt','Osmium','Manganese'];

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// ── Validate input ──────────────────────────────────────────────────────
$huid = isset($_GET['huid']) ? trim($_GET['huid']) : '';

if ($huid === '') {
    respond(['success' => false, 'message' => 'Missing huid parameter.'], 400);
}

// HUID format check: exactly 6 characters from the safe charset
if (!preg_match('/^[23456789A-HJ-NP-Za-km-z]{6}$/', $huid)) {
    respond(['success' => false, 'message' => 'Invalid HUID format.'], 400);
}

// ── Lookup ──────────────────────────────────────────────────────────────
$stmt = mysqli_prepare($conn, "SELECT * FROM customer_reports WHERE huid = ?");
mysqli_stmt_bind_param($stmt, "s", $huid);
mysqli_stmt_execute($stmt);
$report_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$report_data) {
    respond(['success' => false, 'message' => 'Report not found for this HUID.'], 404);
}

$report_id  = (int)$report_data['id'];
$isSilver   = false;
$isHallmark = stripos($report_data['service_name'] ?? '', 'hallmark') !== false;

// ── Build composition elements (tunch reports only) ────────────────────
$elements = [];
if (!$isHallmark) {
    $itemLower = strtolower($report_data['item_name']);
    $isSilver  = strpos($itemLower, 'silver') !== false || strpos($itemLower, 'চাঁদি') !== false || strpos($itemLower, 'rupa') !== false;
    $elementOrder = $isSilver ? $SILVER_ELEMENTS : $GOLD_ELEMENTS;
    foreach ($elementOrder as $elName) {
        $col = strtolower($elName);
        $val = $report_data[$col] ?? null;
        $elements[] = [
            'name'  => $elName,
            'value' => ($val === null) ? null : round((float)$val, 3),
        ];
    }
}

// ── Fetch images ────────────────────────────────────────────────────────
$images = [];
if ($isHallmark) {
    $imgStmt = mysqli_prepare($conn,
        "SELECT img_path FROM report_images WHERE report_id=? AND img_type='hallmark' ORDER BY img_number ASC LIMIT 1");
    mysqli_stmt_bind_param($imgStmt, 'i', $report_id);
    mysqli_stmt_execute($imgStmt);
    $imgRow = mysqli_fetch_assoc(mysqli_stmt_get_result($imgStmt));
    if ($imgRow) $images[] = $imgRow['img_path'];
    mysqli_stmt_close($imgStmt);
} else {
    $imgStmt = mysqli_prepare($conn,
        "SELECT img_path FROM report_images WHERE report_id=? AND img_type='tunch' ORDER BY img_number ASC LIMIT 2");
    mysqli_stmt_bind_param($imgStmt, 'i', $report_id);
    mysqli_stmt_execute($imgStmt);
    $imgResult = mysqli_stmt_get_result($imgStmt);
    while ($r = mysqli_fetch_assoc($imgResult)) $images[] = $r['img_path'];
    mysqli_stmt_close($imgStmt);
}

// ── Prefix image paths with full URL (so any domain can display them) ──
$baseUrl = 'http://localhost/tunchmark/'; // ← update to your actual main domain
foreach ($images as &$img) {
    if (!preg_match('#^https?://#i', $img)) $img = $baseUrl . $img;
}
unset($img);

// ── Build response ──────────────────────────────────────────────────────
respond([
    'success'     => true,
    'report_type' => $isHallmark ? 'hallmark' : 'tunch',
    'huid'        => $report_data['huid'],
    'order_id'    => $report_data['order_id'],
    'customer_name'   => $report_data['customer_name'],
    'item_name'       => $report_data['item_name'],
    'weight'          => $report_data['weight'],
    'created_at'      => $report_data['created_at'],

    // Hallmark-specific
    'quantity'     => $report_data['quantity'] ?? null,
    'manufacturer' => $report_data['manufacturer'] ?? null,
    'address'      => $report_data['address'] ?? null,
    'hallmark'     => $report_data['hallmark'] ?? null,

    // Tunch-specific
    'is_silver'     => $isSilver,
    'purity_label'  => $isSilver ? 'Silver Purity' : 'Gold Purity',
    'purity_value'  => $isSilver ? $report_data['silver_purity_percent'] : $report_data['gold_purity_percent'],
    'karat'         => $report_data['karat'] ?? null,
    'gold'          => $report_data['gold']  ?? null,
    'joint'         => $report_data['joint'] ?? null,
    'elements'      => $elements,

    'images' => $images,
]);