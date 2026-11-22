<?php
require 'auth.php';
require 'mydb.php';
require_engineering();               // admin + engineer employee only — covers page load AND form submission

if (isset($conn)) { $conn->set_charset('utf8mb4'); }
if (!function_exists('h')) { function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

const QT_MAX_CENTS = 99999999999999;  // DECIMAL(14,2)

// ---------- Category / item catalog (edit here to add or rename items) ----------
$catalog = [
    ['name' => 'Gold Testing Machine, Fischer Company, Made in Germany', 'items' => [
        'GOLDSCOPE SD 510', 'GOLDSCOPE SD 515', 'GOLDSCOPE SD 520 DPP+', 'GOLDSCOPE SD 550 DPP+', 'GOLDSCOPE SD 600 DPP+ Top Source']],
    ['name' => 'Computer and Peripherals', 'items' => [
        'Computer DELL / HP', 'Original Windows', 'Online UPS 1Kv (Italy)', 'Isolation Transformer (India)', 'Epson Color Printer',
        'Scratcher Machine', 'Scratcher Bit', 'Temperature Machine', 'Training Gold Testing Machine']],
    ['name' => 'Laser Marking Machine', 'items' => [
        'Laser Marking Machine (HK) - Portable', 'Laser Marking Machine (HK) - Desktop',
        'Laser Marking Machine (India)/(Italy) - Portable', 'Laser Marking Machine (India)/(Italy) - Desktop']],
    ['name' => 'Laser Spot Welding Machine', 'items' => [
        'Laser Spot Welding Machine - Portable', 'Laser Spot Welding Machine - Desktop']],
    ['name' => 'Billing Process', 'items' => [
        'Bill Software', 'Bill Software Computer', 'Bill Software Printer Epson']],
    // 'kg' => true : the kg / size is typed by hand on the form and printed in brackets after the name, e.g. "Melting Machine (India) (2 KG)"
    ['name' => 'Melting Machine (India)', 'items' => [
        ['name' => 'Melting Machine (India)', 'kg' => true], ['name' => 'Melting Machine (India)', 'kg' => true], 'Automatic Chiller System', 'Melting Crucible']],
    ['name' => 'Gold Refinery Project', 'items' => [
        ['name' => 'Gold Refinery Project', 'kg' => true], ['name' => 'Gold Refinery Project', 'kg' => true]]],
    ['name' => 'Smart Card Process Computer', 'items' => [
        'Smart Card Process Computer', 'Smart Card Process Printer']],
    ['name' => 'Weight Balance with extra Screen', 'items' => [
        ['name' => 'Weight Balance with extra Screen', 'kg' => true]]],
];

// placeholder shown in the typed-in-brackets box (default: kg)
$kgHints = ['Weight Balance with extra Screen' => 'type grams e.g. 600gm upto 2200gm'];

if (empty($_SESSION['csrf_qt'])) $_SESSION['csrf_qt'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_qt'];

$qId    = (int)($_GET['id'] ?? 0);
$isEdit = $qId > 0;
$uid    = (int)$_SESSION['user_id'];
$errors = [];

// ---------- Load existing quotation ----------
$quo = null; $savedItems = [];
if ($isEdit) {
    $stmt = $conn->prepare("SELECT * FROM quotations WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $qId);
    $stmt->execute();
    $quo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$quo) { header("Location: quotation_list.php?msg=notfound"); exit; }

    $stmt = $conn->prepare("SELECT category, item, quantity, unit_price FROM quotation_items WHERE quotation_id = ? ORDER BY cat_no, id");
    $stmt->bind_param("i", $qId);
    $stmt->execute();
    $savedItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$f = ['quotation_date' => date('Y-m-d'), 'lab_id' => '', 'customer_name' => '', 'customer_address' => '', 'designation' => '',
      'telephone' => '', 'email' => '', 'validity_days' => ''];
if ($quo) foreach ($f as $k => $_) $f[$k] = (string)($quo[$k] ?? '');
$quotationNo = $quo['quotation_no'] ?? '';

// ---------- Item state: every catalog item unchecked, then overlay saved rows ----------
$state = [];
foreach ($catalog as $c) {
    $rows = [];
    foreach ($c['items'] as $it) {
        $isKg = is_array($it);
        $rows[] = ['name' => $isKg ? $it['name'] : $it, 'on' => false, 'qty' => '', 'price' => '', 'custom' => false, 'kgrow' => $isKg, 'kg' => ''];
    }
    $state[] = ['name' => $c['name'], 'rows' => $rows];
}
foreach ($savedItems as $si) {
    $ci = null;
    foreach ($state as $i => $cs) if ($cs['name'] === $si['category']) { $ci = $i; break; }
    if ($ci === null) { $state[] = ['name' => $si['category'], 'rows' => []]; $ci = count($state) - 1; }
    $ri = null; $kgText = '';
    foreach ($state[$ci]['rows'] as $k => $r) {
        if ($r['on']) continue;
        if (!empty($r['kgrow'])) {                         // "Base" or "Base (typed kg)"
            if ($si['item'] === $r['name']) { $ri = $k; break; }
            if (preg_match('/^' . preg_quote($r['name'], '/') . ' \((.*)\)$/su', $si['item'], $m)) { $ri = $k; $kgText = $m[1]; break; }
        } elseif ($r['name'] === $si['item']) { $ri = $k; break; }
    }
    $vals = ['on' => true, 'qty' => $si['quantity'] === null ? '' : (string)$si['quantity'], 'price' => $si['unit_price'] === null ? '' : (string)$si['unit_price']];
    if ($ri === null) $state[$ci]['rows'][] = ['name' => $si['item'], 'custom' => true, 'kgrow' => false, 'kg' => ''] + $vals;     // item not in catalog -> custom row
    else              $state[$ci]['rows'][$ri] = array_merge($state[$ci]['rows'][$ri], $vals, ['kg' => $kgText]);
}

// ---------- Helpers ----------
$validDate = function (string $d): bool { $o = DateTime::createFromFormat('Y-m-d', $d); return $o && $o->format('Y-m-d') === $d; };
$checkLen  = function (string $v, int $max, string $label) use (&$errors) { if (mb_strlen($v) > $max) $errors[] = "$label must be at most $max characters."; };
$post      = function (string $k): string { $v = $_POST[$k] ?? ''; return is_string($v) ? trim($v) : ''; };
$toCents   = function (string $v): ?int {                       // "1,500.50" -> 150050 ; null if invalid
    $v = str_replace(',', '', trim($v));
    if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $v)) return null;
    return (int)round(((float)$v) * 100);
};
$fmtC = fn(int $c): string => number_format($c / 100, 2, '.', '');

// ---------- Save ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Invalid or expired form token. Please go back, refresh the page and try again.');
    }

    foreach (['quotation_date', 'lab_id', 'customer_name', 'customer_address', 'designation', 'telephone', 'email', 'validity_days'] as $k) $f[$k] = $post($k);

    if ($f['quotation_date'] === '' || !$validDate($f['quotation_date'])) $errors[] = "A valid date is required.";
    if ($f['customer_name'] === '') $errors[] = "Customer name is required.";
    if ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errors[] = "Email address is not valid.";
    if ($f['telephone'] !== '' && !preg_match('/^[0-9+\-()\s,\/]{5,40}$/', $f['telephone'])) $errors[] = "Telephone may contain only digits, spaces and + - ( ) , /";
    if ($f['validity_days'] !== '' && (!ctype_digit($f['validity_days']) || (int)$f['validity_days'] < 1 || (int)$f['validity_days'] > 365)) $errors[] = "Validity days must be a number between 1 and 365 (or leave it blank).";
    $checkLen($f['customer_name'], 150, 'Customer name'); $checkLen($f['customer_address'], 400, 'Address');
    $checkLen($f['designation'], 100, 'Designation');     $checkLen($f['email'], 120, 'Email');

    // --- Laboratory link (optional) ---
    $labIn = ctype_digit($f['lab_id']) ? (int)$f['lab_id'] : 0;
    if ($labIn > 0) {
        $gs = $conn->prepare("SELECT id FROM gold_testing_lab_customers WHERE id = ? LIMIT 1");
        $gs->bind_param("i", $labIn); $gs->execute();
        if (!$gs->get_result()->fetch_row()) { $errors[] = "The selected laboratory does not exist."; $labIn = 0; }
        $gs->close();
    }
    $f['lab_id'] = $labIn > 0 ? (string)$labIn : '';

    // --- Items: rebuild state from the POST (so the form re-renders exactly as submitted) ---
    $catIn = is_array($_POST['cat'] ?? null) ? $_POST['cat'] : [];
    $rowIn = is_array($_POST['rows'] ?? null) ? $_POST['rows'] : [];
    $state = []; $clean = []; $subC = 0; $selected = 0;
    $nCat = 0;
    foreach ($catIn as $ci => $cn) {
        if (++$nCat > 30) break;
        $cn = is_string($cn) ? trim($cn) : '';
        if ($cn === '' || mb_strlen($cn) > 150) continue;
        $in = is_array($rowIn[$ci] ?? null) ? $rowIn[$ci] : [];
        $rows = []; $nRow = 0;
        foreach ($in as $r) {
            if (!is_array($r) || ++$nRow > 80) continue;
            $name  = trim((string)($r['name'] ?? ''));
            $on    = !empty($r['on']);
            $qty   = trim((string)($r['qty'] ?? ''));
            $price = trim((string)($r['price'] ?? ''));
            $cust  = !empty($r['custom']);
            $kgrow = !empty($r['kgrow']);
            $kg    = trim((string)($r['kg'] ?? ''));
            if ($cust && !$on && $name === '' && $qty === '' && $price === '') continue;     // empty custom row
            $rows[] = ['name' => $name, 'on' => $on, 'qty' => $qty, 'price' => $price, 'custom' => $cust, 'kgrow' => $kgrow, 'kg' => $kg];
            if (!$on) continue;

            $selected++;
            if ($kgrow) {                                      // typed kg goes in brackets after the name
                if (mb_strlen($kg) > 60) { $errors[] = "\"$name\": the kg / size text is too long (max 60)."; continue; }
                if ($kg !== '') $name .= ' (' . $kg . ')';
            }
            $lbl = $name !== '' ? '"' . mb_strimwidth($name, 0, 40, '…') . '"' : 'a custom item';
            if ($name === '')               { $errors[] = "Enter a name for the custom item in \"$cn\"."; continue; }
            if (mb_strlen($name) > 255)     { $errors[] = "Item $lbl: name is too long (max 255)."; continue; }
            $qn = null;
            if ($qty !== '') {
                if (!preg_match('/^\d{1,6}$/', $qty) || (int)$qty < 1) { $errors[] = "Item $lbl: quantity must be a whole number of 1 or more."; continue; }
                $qn = (int)$qty;
            }
            $pc = null;
            if ($price !== '') {
                $pc = $toCents($price);
                if ($pc === null) { $errors[] = "Item $lbl: price must be a valid amount (e.g. 1500 or 1500.50)."; continue; }
            }
            if ($qn === null) $qn = 1;                                 // selected item, quantity left blank -> 1
            $lc = $pc !== null ? $qn * $pc : null;
            if ($lc !== null) $subC += $lc;
            $clean[] = ['cat' => $cn, 'item' => $name, 'qty' => $qn, 'price' => $pc, 'total' => $lc];
        }
        $state[] = ['name' => $cn, 'rows' => $rows];
    }
    if ($selected === 0) $errors[] = "Tick at least one item.";
    if ($subC > QT_MAX_CENTS) $errors[] = "The total amount is too large.";

    // --- Persist inside one transaction ---
    if (!$errors) {
        $nn = fn(string $v) => $v === '' ? null : $v;
        $cols = ['quotation_date', 'lab_id', 'customer_name', 'customer_address', 'designation', 'telephone', 'email', 'validity_days', 'grand_total'];
        $vals = [$f['quotation_date'], $nn($f['lab_id']), $f['customer_name'], $nn($f['customer_address']), $nn($f['designation']),
                 $nn($f['telephone']), $nn($f['email']), $nn($f['validity_days']), $fmtC($subC)];
        try {
            $conn->begin_transaction();

            if ($isEdit) {
                $set  = implode(', ', array_map(fn($c) => "$c = ?", $cols));
                $stmt = $conn->prepare("UPDATE quotations SET $set WHERE id = ?");
                $bindVals = array_merge($vals, [$qId]);
                $stmt->bind_param(str_repeat('s', count($cols)) . 'i', ...$bindVals);
                $stmt->execute(); $stmt->close();
                $rid = $qId;
                $d = $conn->prepare("DELETE FROM quotation_items WHERE quotation_id = ?");
                $d->bind_param("i", $rid); $d->execute(); $d->close();
            } else {
                // Quotation no: SI-<id>-<dd-mm-yyyy>, e.g. SI-15-22-11-2026 (id = primary key).
                // The id is only known after the insert, so insert with a temporary unique number, then set the real one.
                $suffix = DateTime::createFromFormat('Y-m-d', $f['quotation_date'])->format('d-m-Y');
                $tmpNo  = 'TMP-' . bin2hex(random_bytes(8));

                $insCols = array_merge(['quotation_no'], $cols);
                $ph   = implode(',', array_fill(0, count($insCols) + 1, '?'));
                $stmt = $conn->prepare("INSERT INTO quotations (" . implode(',', $insCols) . ", created_by) VALUES ($ph)");
                $bindVals = array_merge([$tmpNo], $vals, [$uid]);
                $stmt->bind_param(str_repeat('s', count($insCols)) . 'i', ...$bindVals);
                $stmt->execute();
                $rid = (int)$conn->insert_id; $stmt->close();

                $realNo = sprintf('SI-%02d-%s', $rid, $suffix);
                $un = $conn->prepare("UPDATE quotations SET quotation_no = ? WHERE id = ?");
                $un->bind_param("si", $realNo, $rid); $un->execute(); $un->close();
            }

            $stmt = $conn->prepare("INSERT INTO quotation_items (quotation_id, cat_no, category, item, quantity, unit_price, line_total) VALUES (?,?,?,?,?,?,?)");
            $catNo = 0; $lastCat = null;
            foreach ($clean as $it) {
                if ($it['cat'] !== $lastCat) { $catNo++; $lastCat = $it['cat']; }
                $cat = $it['cat']; $item = $it['item']; $qv = $it['qty'];
                $up = $it['price'] === null ? null : $fmtC($it['price']);
                $lt = $it['total'] === null ? null : $fmtC($it['total']);
                $stmt->bind_param("iississ", $rid, $catNo, $cat, $item, $qv, $up, $lt);
                $stmt->execute();
            }
            $stmt->close();

            $conn->commit();
            header("Location: quotation_pdf.php?id=" . (int)$rid . "&saved=" . ($isEdit ? 'updated' : 'created')); exit;
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('quotation save failed: ' . $e->getMessage());
            $errors[] = "The quotation could not be saved. Nothing was changed — please try again.";
        }
    }
}

// ---------- Gold Testing Lab data for the Laboratory select (embedded as JSON; no extra request) ----------
$goldLabs = [];
foreach ($conn->query("SELECT id, customer_name, address, contact_person, designation_department, telephone, email FROM gold_testing_lab_customers ORDER BY customer_name ASC, id ASC") as $gl) {
    $goldLabs[] = ['id' => (int)$gl['id'], 'customer_name' => $gl['customer_name'], 'address' => (string)$gl['address'],
                   'designation' => (string)$gl['designation_department'], 'telephone' => (string)$gl['telephone'], 'email' => (string)$gl['email']];
}
$goldJson = json_encode(['labs' => $goldLabs],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);

$pageTitle = $isEdit ? 'Edit Quotation ' . $quotationNo : 'New Quotation';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#0f766e">
  <title><?= h($pageTitle) ?> — Rajaiswari</title>
  <link rel="icon" type="image/png" href="favicon.png">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root{--bg:#f1f3f6;--surface:#fff;--surface-2:#fafbfc;--border:#e4e7ec;--bsoft:#f0f1f3;--t1:#111827;--t2:#374151;--t3:#6b7280;--t4:#9ca3af;
      --blue:#2563eb;--blue-bg:#eff6ff;--green:#059669;--green-bg:#ecfdf5;--amber:#d97706;--amber-bg:#fffbeb;
      --red:#dc2626;--red-bg:#fef2f2;--red-b:#fecaca;--teal:#0f766e;--teal-bg:#f0fdfa;
      --r:10px;--rs:6px;--sh:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);}
    body{font-family:'DM Sans',-apple-system,sans-serif;font-size:14.5px;background:var(--bg);color:var(--t1);-webkit-font-smoothing:antialiased;min-height:100vh;}
    .page-shell{margin-left:200px;min-height:100vh;display:flex;flex-direction:column;}
    .top-bar{position:sticky;top:0;z-index:200;min-height:54px;background:var(--surface);border-bottom:1px solid var(--border);box-shadow:var(--sh);display:flex;align-items:center;flex-wrap:wrap;padding:6px 22px;gap:10px;}
    .tb-ico{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;background:var(--teal-bg);color:var(--teal);}
    .tb-title{font-size:1.0625rem;font-weight:700;line-height:1.2;}.tb-sub{font-size:.8rem;color:var(--t4);}
    .tb-right{margin-left:auto;display:flex;gap:8px;}
    .btn-soft{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 14px;background:var(--surface-2);border:1.5px solid var(--border);border-radius:var(--rs);font-size:.82rem;font-weight:600;color:var(--t2);text-decoration:none;}
    .btn-soft:hover{background:var(--border);color:var(--t1);}
    .main{flex:1;padding:20px 22px 60px;}
    .form-wrap{max-width:1000px;margin:0 auto;display:flex;flex-direction:column;gap:14px;}
    .sec{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;}
    .sec-head{display:flex;align-items:center;gap:9px;padding:12px 18px;background:var(--surface-2);border-bottom:1px solid var(--bsoft);}
    .sec-ico{width:28px;height:28px;border-radius:var(--rs);display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;}
    .i-blue{background:var(--blue-bg);color:var(--blue);}.i-amber{background:var(--amber-bg);color:var(--amber);}.i-teal{background:var(--teal-bg);color:var(--teal);}
    .sec-title{font-size:.9375rem;font-weight:700;}.sec-meta{margin-left:auto;font-size:.78rem;color:var(--t4);}
    .sec-body{padding:18px;}
    .lbl{display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t3);margin-bottom:4px;}
    .lbl .req{color:var(--red);margin-left:2px;}
    .form-control,.form-select{font-size:.9rem;border:1.5px solid var(--border);border-radius:var(--rs);min-height:38px;}
    textarea.form-control{min-height:auto;}
    .form-control:focus,.form-select:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
    .alert-err{background:var(--red-bg);border:1px solid var(--red-b);border-left:3px solid var(--red);border-radius:var(--rs);padding:13px 16px;}
    .alert-err-title{font-size:.875rem;font-weight:700;color:var(--red);margin-bottom:6px;}
    .alert-err ul{margin:0;padding-left:16px;}.alert-err li{font-size:.82rem;color:var(--red);}
    /* categories + item rows */
    .cat{border:1px solid #cbd5e1;border-radius:4px;margin-bottom:14px;overflow:hidden;background:#fff;}
    .cat-head{display:flex;align-items:center;gap:10px;padding:9px 12px;background:#f3f4f6;border-bottom:1px solid #cbd5e1;flex-wrap:wrap;}
    .cat-no{width:26px;height:26px;border-radius:4px;background:var(--teal);color:#fff;font-weight:700;font-size:.8rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .cat-name{font-weight:700;flex:1;min-width:180px;}
    .cat-count{font-size:.78rem;color:var(--t3);}
    .qi-row{display:grid;grid-template-columns:34px 3fr .8fr 1.2fr 1.2fr 38px;gap:8px;align-items:center;padding:7px 12px;border-bottom:1px solid #e2e8f0;}
    .qi-row:last-child{border-bottom:0;}
    .qi-row.is-on{background:#f0fdfa;}
    .qi-head{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--t4);background:#fafbfc;}
    .qi-chk{text-align:center;}.qi-chk input{width:1.15rem;height:1.15rem;cursor:pointer;}
    .qi-lbl{margin:0;cursor:pointer;font-size:.9rem;}
    .qi-nm{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
    .qi-nm .qi-name{flex:1;}
    .qi-kg{display:inline-flex;align-items:center;gap:4px;font-weight:600;}
    .qi-kgin{width:230px;max-width:100%;}
    /* mobile-only pieces (hidden on desktop) */
    .qs-btn,.cat-chev,.mbar{display:none;}
    .mbar{position:fixed;left:0;right:0;bottom:0;z-index:300;align-items:center;gap:12px;padding:10px 14px calc(10px + env(safe-area-inset-bottom));background:#fff;border-top:1px solid #cbd5e1;box-shadow:0 -2px 10px rgba(0,0,0,.1);}
    .mbar .mb-tot{flex:1;line-height:1.15;}
    .mbar small{display:block;color:var(--t3);font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;}
    .mbar b{font-family:'DM Mono',monospace;font-size:1.15rem;}
    .mbar .btn-submit{height:48px;padding:0 22px;border-radius:12px;font-size:1rem;}
    .qi-amt{font-family:'DM Mono',monospace;font-weight:500;text-align:right;white-space:nowrap;color:var(--t2);}
    .qi-mlbl{display:none;}
    .btn-rm{width:36px;height:36px;border:1.5px solid var(--red-b);background:var(--red-bg);color:var(--red);border-radius:4px;}
    .btn-rm:hover{background:var(--red);color:#fff;}
    .totals{max-width:380px;margin-left:auto;margin-top:6px;border:1px solid #cbd5e1;border-radius:4px;background:#fff;padding:10px 14px;}
    .tot-row{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:4px 0;}
    .tot-row .num{font-family:'DM Mono',monospace;font-weight:600;}
    .tot-grand{font-weight:700;font-size:1.05rem;}
    .action-bar{display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:13px 18px;background:#f3f4f6;border-top:1px solid #cbd5e1;}
    .btn-ghost{display:inline-flex;align-items:center;gap:6px;height:40px;padding:0 18px;background:var(--surface);border:1.5px solid var(--border);border-radius:4px;font-weight:500;color:var(--t2);text-decoration:none;}
    .btn-ghost:hover{background:var(--surface-2);color:var(--t1);}
    .btn-submit{display:inline-flex;align-items:center;gap:7px;height:40px;padding:0 24px;border:none;border-radius:4px;font-weight:700;color:#fff;background:var(--green);}
    .btn-submit.edit{background:var(--amber);}.btn-submit:hover{filter:brightness(.92);}
    /* formal UI: same look as the Gold Testing Labs pages */
    .sec{border-radius:4px;border-color:#cbd5e1;box-shadow:none;}
    .sec-head{background:#f3f4f6;border-bottom:1px solid #cbd5e1;}
    .form-control,.form-select{border:1px solid #9ca3af;border-radius:4px;}
    .btn-soft{border-radius:4px;}
    @media(max-width:991.98px){.page-shell{margin-left:0;padding-top:52px;}.top-bar{top:52px;padding:8px 14px;}.main{padding:14px 14px 50px;}}
    /* ===== Mobile: native-app style (phones) — desktop layout above is untouched ===== */
    @media(max-width:767.98px){
      body{font-size:15px;-webkit-tap-highlight-color:transparent;}
      button,.btn,.btn-soft,.qs-btn,.cat-head{-webkit-tap-highlight-color:transparent;touch-action:manipulation;}
      button:active,.btn:active{transform:scale(.97);}
      .top-bar{min-height:50px;padding:6px 12px;gap:8px;}
      .tb-sub{display:none;}.tb-title{font-size:1rem;}
      .btn-soft{height:40px;border-radius:12px;}
      .main{padding:12px 12px 130px;}
      .form-wrap{gap:12px;}
      .sec{border:0;border-radius:16px;box-shadow:0 1px 4px rgba(0,0,0,.1);}
      .sec-head{padding:12px 14px;}
      .sec-meta{display:none;}
      .sec-body{padding:14px;}
      .form-control,.form-select{font-size:16px;min-height:46px;border-radius:12px;}   /* 16px stops iOS zoom on focus */
      textarea.form-control{min-height:84px;}
      .alert-err{border-radius:12px;}

      /* categories = collapsible cards */
      .cat{border-radius:14px;margin-bottom:10px;}
      .cat-head{display:grid;grid-template-columns:28px 1fr 22px;grid-template-areas:"no name chev" ". count add";gap:6px 10px;align-items:center;padding:12px;cursor:pointer;}
      .cat-no{grid-area:no;}
      .cat-name{grid-area:name;min-width:0;font-size:.95rem;line-height:1.3;}
      .cat-chev{display:block;grid-area:chev;color:var(--t3);transition:transform .15s;text-align:center;}
      .cat-count{grid-area:count;}
      .btn-addc{grid-area:add;justify-self:end;min-height:38px;border-radius:10px;}
      .cat.collapsed .cat-rows{display:none;}
      .cat.collapsed .cat-chev{transform:rotate(-90deg);}
      .cat.collapsed .cat-head{border-bottom:0;}

      /* item rows = cards; qty / price / amount appear once the item is ticked */
      .qi-head{display:none;}
      .qi-row{grid-template-columns:44px 1fr 1fr;grid-template-areas:"chk nm nm" ". q p" ". amt rm";gap:8px 10px;padding:12px;align-items:center;}
      .qi-chk{grid-area:chk;}.qi-nm{grid-area:nm;}.qi-q{grid-area:q;}.qi-p{grid-area:p;}
      .qi-amt{grid-area:amt;text-align:left;font-size:1rem;font-weight:600;}
      .qi-rm{grid-area:rm;justify-self:end;}
      .qi-chk input{width:1.6rem;height:1.6rem;}
      .qi-lbl{font-size:.95rem;line-height:1.3;}
      .qi-row:not(.is-on) .qi-q,.qi-row:not(.is-on) .qi-p,.qi-row:not(.is-on) .qi-amt{display:none;}
      .qi-row.is-on{border-left:4px solid var(--teal);}
      .qi-mlbl{display:block;font-size:.66rem;font-weight:700;text-transform:uppercase;color:var(--t4);margin-bottom:3px;}
      .qi-kg{width:100%;}.qi-kgin{width:100%;flex:1;}
      .qi-nm .qi-name{flex:1 1 100%;}
      .qi-step{display:flex;align-items:center;gap:6px;}
      .qi-step .qi-qty{text-align:center;min-width:0;padding-left:4px;padding-right:4px;}
      .qs-btn{display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;width:44px;height:46px;border:1px solid #9ca3af;border-radius:12px;background:#fff;font-size:1.35rem;font-weight:700;color:var(--t2);line-height:1;}
      .qs-btn:active{background:#e2e8f0;}
      .btn-rm{width:44px;height:44px;border-radius:12px;}

      .totals{max-width:none;border-radius:12px;}
      .action-bar{display:none;}                          /* replaced by the bottom bar */
      .mbar{display:flex;}
    }
  </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page-shell">
  <header class="top-bar">
    <div class="tb-ico"><i class="fas fa-<?= $isEdit ? 'pen-to-square' : 'file-signature' ?>"></i></div>
    <div><div class="tb-title"><?= h($pageTitle) ?></div><div class="tb-sub">Quotation</div></div>
    <div class="tb-right">
      <?php if ($isEdit): ?><a href="quotation_pdf.php?id=<?= $qId ?>" class="btn-soft"><i class="fas fa-file-pdf"></i><span class="d-none d-sm-inline"> PDF</span></a><?php endif; ?>
      <a href="quotation_list.php" class="btn-soft"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
  </header>

  <main class="main">
    <form method="post" class="form-wrap" id="qForm" novalidate>
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

      <?php if ($errors): ?>
        <div class="alert-err"><div class="alert-err-title"><i class="fas fa-circle-exclamation me-1"></i>Please fix the following:</div>
          <ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>

      <!-- 1. Customer & quotation details -->
      <div class="sec">
        <div class="sec-head"><div class="sec-ico i-blue"><i class="fas fa-building"></i></div><div class="sec-title">Customer &amp; Quotation Details</div></div>
        <div class="sec-body"><div class="row g-3">
          <div class="col-12 col-md-6"><label class="lbl" for="quotation_date">Date <span class="req">*</span></label><input type="date" class="form-control" id="quotation_date" name="quotation_date" value="<?= h($f['quotation_date']) ?>" required></div>
          <div class="col-12 col-md-6"><label class="lbl">Quotation No</label><input type="text" class="form-control" value="<?= h($quotationNo !== '' ? $quotationNo : 'Auto-generated on save (SI-id-dd-mm-yyyy)') ?>" readonly disabled></div>

          <div class="col-12 col-md-6"><label class="lbl" for="lab_id">Laboratory (optional)</label>
            <select class="form-select" id="lab_id" name="lab_id"><option value="">— None / new customer —</option>
              <?php foreach ($goldLabs as $gl): ?><option value="<?= (int)$gl['id'] ?>" <?= (string)$f['lab_id'] === (string)$gl['id'] ? 'selected' : '' ?>><?= h($gl['customer_name']) ?></option><?php endforeach; ?>
            </select>
            <div class="form-text">Picking a laboratory fills the customer fields. <a href="gold_labs_list.php">Manage labs</a></div></div>
          <div class="col-12 col-md-6"><label class="lbl" for="customer_name">Customer Name <span class="req">*</span></label><input type="text" class="form-control" id="customer_name" name="customer_name" maxlength="150" value="<?= h($f['customer_name']) ?>" required></div>
          <div class="col-12"><label class="lbl" for="customer_address">Customer Address</label><textarea class="form-control" id="customer_address" name="customer_address" rows="2" maxlength="400"><?= h($f['customer_address']) ?></textarea></div>
          <div class="col-12 col-md-4"><label class="lbl" for="designation">Designation</label><input type="text" class="form-control" id="designation" name="designation" maxlength="100" value="<?= h($f['designation']) ?>"></div>
          <div class="col-12 col-md-4"><label class="lbl" for="telephone">Phone</label><input type="tel" class="form-control" id="telephone" name="telephone" maxlength="40" value="<?= h($f['telephone']) ?>"></div>
          <div class="col-12 col-md-4"><label class="lbl" for="email">Email</label><input type="email" class="form-control" id="email" name="email" maxlength="120" value="<?= h($f['email']) ?>"></div>
        </div></div>
      </div>

      <!-- 2. Items by category -->
      <div class="sec">
        <div class="sec-head"><div class="sec-ico i-amber"><i class="fas fa-list-check"></i></div><div class="sec-title">Items</div><div class="sec-meta">Tick what you need · type quantity and price</div></div>
        <div class="sec-body">
          <div id="items">
          <?php foreach ($state as $ci => $cs): $cnt = 0; foreach ($cs['rows'] as $r) if ($r['on']) $cnt++; ?>
            <div class="cat" data-ci="<?= (int)$ci ?>" data-next="<?= count($cs['rows']) ?>">
              <input type="hidden" name="cat[<?= (int)$ci ?>]" value="<?= h($cs['name']) ?>">
              <div class="cat-head">
                <div class="cat-no"><?= (int)$ci + 1 ?></div>
                <div class="cat-name"><?= h($cs['name']) ?></div>
                <span class="cat-count"><?= $cnt ?> selected</span>
                <button type="button" class="btn btn-outline-primary btn-sm btn-addc"><i class="fas fa-plus"></i> Custom item</button>
                <i class="fas fa-chevron-down cat-chev"></i>
              </div>
              <div class="qi-row qi-head"><div></div><div>Item</div><div>Quantity</div><div>Price (Tk)</div><div style="text-align:right">Amount</div><div></div></div>
              <div class="cat-rows">
              <?php foreach ($cs['rows'] as $k => $r): $rid = "c{$ci}_{$k}"; $nm = "rows[$ci][$k]"; ?>
                <div class="qi-row">
                  <div class="qi-chk"><input type="checkbox" class="form-check-input qi-on" id="<?= h($rid) ?>" name="<?= $nm ?>[on]" value="1" <?= $r['on'] ? 'checked' : '' ?>></div>
                  <div class="qi-nm">
                    <?php if ($r['custom']): ?>
                      <input type="hidden" name="<?= $nm ?>[custom]" value="1">
                      <input type="text" class="form-control qi-name" name="<?= $nm ?>[name]" maxlength="255" placeholder="Custom item name" value="<?= h($r['name']) ?>">
                    <?php else: ?>
                      <label class="qi-lbl" for="<?= h($rid) ?>"><?= h($r['name']) ?></label>
                      <input type="hidden" name="<?= $nm ?>[name]" value="<?= h($r['name']) ?>">
                      <?php if (!empty($r['kgrow'])): ?>
                        <input type="hidden" name="<?= $nm ?>[kgrow]" value="1">
                        <span class="qi-kg">(<input type="text" class="form-control form-control-sm qi-kgin" name="<?= $nm ?>[kg]" maxlength="60" placeholder="<?= h($kgHints[$r['name']] ?? 'type kg e.g. 2 KG') ?>" value="<?= h($r['kg'] ?? '') ?>">)</span>
                      <?php endif; ?>
                    <?php endif; ?>
                  </div>
                  <div class="qi-q"><span class="qi-mlbl">Quantity</span><div class="qi-step"><button type="button" class="qs-btn qs-minus" aria-label="Decrease quantity">&minus;</button><input type="number" class="form-control qi-qty" name="<?= $nm ?>[qty]" min="1" step="1" inputmode="numeric" placeholder="1" value="<?= h($r['qty']) ?>"><button type="button" class="qs-btn qs-plus" aria-label="Increase quantity">+</button></div></div>
                  <div class="qi-p"><span class="qi-mlbl">Price (Tk)</span><input type="number" class="form-control qi-price" name="<?= $nm ?>[price]" min="0" step="0.01" inputmode="decimal" value="<?= h($r['price']) ?>"></div>
                  <div class="qi-amt">—</div>
                  <div class="qi-rm"><?php if ($r['custom']): ?><button type="button" class="btn-rm" title="Remove row"><i class="fas fa-trash-can"></i></button><?php endif; ?></div>
                </div>
              <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
          </div>

          <div class="totals">
            <div class="tot-row tot-grand"><span>Grand Total</span><span class="num" id="tGrand">0.00</span></div>
          </div>
        </div>

        <div class="sec-body" style="border-top:1px solid #cbd5e1">
          <div class="row g-3">
            <div class="col-12 col-md-4"><label class="lbl" for="validity_days">Validity period (days) — optional</label>
              <input type="number" class="form-control" id="validity_days" name="validity_days" min="1" max="365" step="1" value="<?= h($f['validity_days']) ?>"></div>
            <div class="col-12 col-md-8"><div class="form-text mt-4">Leave blank to print “Quotation validity period ____ days.” and fill it in by hand. The other notes (phone, dollar-rate) are fixed and printed automatically.</div></div>
          </div>
        </div>

        <div class="action-bar">
          <a href="quotation_list.php" class="btn-ghost">Cancel</a>
          <button type="submit" class="btn-submit <?= $isEdit ? 'edit' : '' ?>"><i class="fas fa-<?= $isEdit ? 'floppy-disk' : 'check' ?>"></i> <?= $isEdit ? 'Update Quotation' : 'Save Quotation' ?></button>
        </div>
      </div>
      <!-- Mobile bottom bar (shown on phones only) -->
      <div class="mbar">
        <div class="mb-tot"><small>Grand Total (Tk)</small><b id="tGrandM">0.00</b></div>
        <button type="submit" class="btn-submit <?= $isEdit ? 'edit' : '' ?>"><i class="fas fa-<?= $isEdit ? 'floppy-disk' : 'check' ?>"></i> <?= $isEdit ? 'Update' : 'Save' ?></button>
      </div>
    </form>
  </main>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
// Laboratory -> customer fields (data embedded above, no request)
(function () {
  var GOLD = <?= $goldJson ?>;
  var labSel = document.getElementById('lab_id');
  function setVal(id, v) { var el = document.getElementById(id); if (el) el.value = v || ''; }
  labSel.addEventListener('change', function () {
    var id = parseInt(this.value, 10) || 0;
    var lab = GOLD.labs.filter(function (l) { return l.id === id; })[0];
    if (!lab) return;                                   // clearing the lab keeps whatever is typed
    setVal('customer_name', lab.customer_name); setVal('customer_address', lab.address);
    setVal('designation', lab.designation); setVal('telephone', lab.telephone); setVal('email', lab.email);
  });
})();
</script>
<script>
(function () {
  var root = document.getElementById('items');
  function isMobile() { return window.matchMedia('(max-width: 767.98px)').matches; }
  function fmt(c) { return (c / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

  function calc() {
    var sub = 0, count = 0, lines = 0;
    root.querySelectorAll('.cat').forEach(function (cat) {
      var n = 0;
      cat.querySelectorAll('.qi-row:not(.qi-head)').forEach(function (r) {
        var on = r.querySelector('.qi-on').checked;
        r.classList.toggle('is-on', on);
        var amt = '—';
        if (on) {
          n++;
          var p = parseFloat(r.querySelector('.qi-price').value);
          if (!isNaN(p)) {
            var qv = r.querySelector('.qi-qty').value.trim();
            var q = qv === '' ? 1 : (parseInt(qv, 10) || 0);
            var c = q * Math.round(p * 100);
            sub += c; amt = fmt(c);
          }
        }
        r.querySelector('.qi-amt').textContent = amt;
      });
      cat.querySelector('.cat-count').textContent = n + ' selected';
      count += n; lines += n ? n + 1 : 0;
    });
    document.getElementById('tGrand').textContent = fmt(sub);
    document.getElementById('tGrandM').textContent = fmt(sub);
  }

  // typing a quantity / price / custom name ticks the row automatically
  root.addEventListener('input', function (e) {
    var row = e.target.closest('.qi-row'); if (!row) return;
    if (e.target.matches('.qi-qty,.qi-price,.qi-name,.qi-kgin') && e.target.value !== '') {
      row.querySelector('.qi-on').checked = true;
      var qf = row.querySelector('.qi-qty');
      if (e.target !== qf && qf.value.trim() === '') qf.value = '1';       // default quantity 1
    }
    calc();
  });
  root.addEventListener('change', function (e) {
    if (!e.target.classList.contains('qi-on')) return;
    var q = e.target.closest('.qi-row').querySelector('.qi-qty');
    if (e.target.checked && q.value.trim() === '') q.value = '1';       // default quantity 1 on a selected item
    calc();
  });

  root.addEventListener('click', function (e) {
    var qsb = e.target.closest('.qs-btn');                 // mobile quantity stepper
    if (qsb) {
      var srow = qsb.closest('.qi-row'), qf = srow.querySelector('.qi-qty'), v = parseInt(qf.value, 10) || 1;
      qf.value = qsb.classList.contains('qs-plus') ? v + 1 : Math.max(1, v - 1);
      srow.querySelector('.qi-on').checked = true; calc(); return;
    }
    var head = e.target.closest('.cat-head');              // mobile: tap a category to open / close it
    if (head && !e.target.closest('.btn-addc') && isMobile()) { head.closest('.cat').classList.toggle('collapsed'); return; }
    var rm = e.target.closest('.btn-rm');
    if (rm) { rm.closest('.qi-row').remove(); calc(); return; }
    var add = e.target.closest('.btn-addc');
    if (!add) return;
    var cat = add.closest('.cat'), ci = parseInt(cat.dataset.ci, 10), k = parseInt(cat.dataset.next, 10);
    cat.classList.remove('collapsed');
    cat.dataset.next = k + 1;
    var nm = 'rows[' + ci + '][' + k + ']';
    var row = document.createElement('div');
    row.className = 'qi-row';
    row.innerHTML =
      '<div class="qi-chk"><input type="checkbox" class="form-check-input qi-on" name="' + nm + '[on]" value="1" checked></div>' +
      '<div class="qi-nm"><input type="hidden" name="' + nm + '[custom]" value="1"><input type="text" class="form-control qi-name" name="' + nm + '[name]" maxlength="255" placeholder="Custom item name"></div>' +
      '<div class="qi-q"><span class="qi-mlbl">Quantity</span><div class="qi-step"><button type="button" class="qs-btn qs-minus" aria-label="Decrease quantity">&minus;</button><input type="number" class="form-control qi-qty" name="' + nm + '[qty]" min="1" step="1" inputmode="numeric" placeholder="1" value="1"><button type="button" class="qs-btn qs-plus" aria-label="Increase quantity">+</button></div></div>' +
      '<div class="qi-p"><span class="qi-mlbl">Price (Tk)</span><input type="number" class="form-control qi-price" name="' + nm + '[price]" min="0" step="0.01" inputmode="decimal"></div>' +
      '<div class="qi-amt">—</div>' +
      '<div class="qi-rm"><button type="button" class="btn-rm" title="Remove row"><i class="fas fa-trash-can"></i></button></div>';
    cat.querySelector('.cat-rows').appendChild(row);
    row.querySelector('.qi-name').focus();
    calc();
  });
  calc();
  if (isMobile()) {                                        // phones: open only categories that have a ticked item (or the first one)
    var cats = root.querySelectorAll('.cat'), anyOn = false;
    cats.forEach(function (c) { if (c.querySelector('.qi-on:checked')) anyOn = true; else c.classList.add('collapsed'); });
    if (!anyOn && cats[0]) cats[0].classList.remove('collapsed');
  }

  // client-side validation (server re-validates everything)
  document.getElementById('qForm').addEventListener('submit', function (e) {
    var msgs = [];
    if (!document.getElementById('customer_name').value.trim()) msgs.push('Customer name is required.');
    var picked = 0;
    root.querySelectorAll('.qi-row:not(.qi-head)').forEach(function (r) {
      if (!r.querySelector('.qi-on').checked) return;
      picked++;
      var nameEl = r.querySelector('.qi-name');
      if (nameEl && !nameEl.value.trim()) msgs.push('Enter a name for each custom item (or remove the row).');
    });
    if (!picked) msgs.push('Tick at least one item.');
    var em = document.getElementById('email'); if (em.value && !em.checkValidity()) msgs.push('Email address is not valid.');
    if (msgs.length) { e.preventDefault(); alert(msgs.filter(function (m, i) { return msgs.indexOf(m) === i; }).join('\n')); }
  });
})();
</script>
</body>
</html>