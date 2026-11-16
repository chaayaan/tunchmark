<?php
require 'auth.php';
require 'mydb.php';
require_engineering();               // admin + engineer employee only — covers page load AND form submission

if (isset($conn)) { $conn->set_charset('utf8mb4'); }
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

const MR_MAX_PARTS = 15;             // keeps the PDF on a single A4 page

if (empty($_SESSION['csrf_mr'])) $_SESSION['csrf_mr'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_mr'];

$reportId = (int)($_GET['id'] ?? 0);
$isEdit   = $reportId > 0;
$uid      = (int)$_SESSION['user_id'];
$errors   = [];

// ---------- Load existing report ----------
$report = null; $savedActions = []; $savedParts = [];
$hasOther = false; $otherTexts = [];     // free-text "Other" actions, one row each (stored as plain text with is_other = 1)
if ($isEdit) {
    $stmt = $conn->prepare("SELECT * FROM machines_report WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $reportId);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$report) { header("Location: machines_report_list.php?msg=notfound"); exit; }

    // actions saved on this report are plain text (no link to the master list)
    $stmt = $conn->prepare("SELECT action_text FROM machines_report_action WHERE report_id = ? AND is_other = 0 ORDER BY sort_order, id");
    $stmt->bind_param("i", $reportId);
    $stmt->execute();
    foreach ($stmt->get_result() as $a) $savedActions[] = $a['action_text'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT action_text FROM machines_report_action WHERE report_id = ? AND is_other = 1 ORDER BY sort_order, id");
    $stmt->bind_param("i", $reportId);
    $stmt->execute();
    foreach ($stmt->get_result() as $o) { $hasOther = true; if (trim((string)$o['action_text']) !== '') $otherTexts[] = $o['action_text']; }
    $stmt->close();

    $stmt = $conn->prepare("SELECT part_code, part_description, quantity, billing_instructions FROM machines_report_spareparts WHERE report_id = ? ORDER BY sr_no, id");
    $stmt->bind_param("i", $reportId);
    $stmt->execute();
    $savedParts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ---------- Helpers ----------
$validDate = function (string $d): bool { $o = DateTime::createFromFormat('Y-m-d', $d); return $o && $o->format('Y-m-d') === $d; };
$checkLen  = function (string $v, int $max, string $label) use (&$errors) { if (mb_strlen($v) > $max) $errors[] = "$label must be at most $max characters."; };
$post      = function (string $k): string { $v = $_POST[$k] ?? ''; return is_string($v) ? trim($v) : ''; };

// ---------- Save ----------
$selectedTexts = $savedActions;
$partsRows   = $savedParts;
$f = $report ?: [
    'report_type' => '', 'lab_id' => '', 'machine_id' => '', 'report_date' => date('Y-m-d'), 'customer_name' => '', 'customer_address' => '', 'contact_person' => '',
    'designation' => '', 'telephone' => '', 'email' => '', 'work_start_date' => '', 'completion_date' => '', 'travel_time_hrs' => '',
    'working_hrs' => '', 'instrument_type' => '', 'model' => '', 'instrument_sr_no' => '', 'visit_type' => 'Physical', 'observations' => '',
    'additional_activities' => '', 'engineer_remarks' => '', 'customer_remarks' => '',
    'engineer_name' => $isEdit ? '' : ($_SESSION['name'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Invalid or expired form token. Please go back, refresh the page and try again.');
    }

    foreach (array_keys($f) as $k) $f[$k] = $post($k);

    if (!in_array($f['report_type'], ['installation', 'service'], true)) $errors[] = "Please select a report type.";
    if ($f['report_date'] === '' || !$validDate($f['report_date']))     $errors[] = "A valid report date is required.";
    if ($f['customer_name'] === '')                                     $errors[] = "Customer name is required.";
    if ($f['instrument_type'] === '')                                   $errors[] = "Instrument type is required.";
    foreach (['work_start_date' => 'Work start date', 'completion_date' => 'Completion date'] as $k => $lab)
        if ($f[$k] !== '' && !$validDate($f[$k])) $errors[] = "$lab is not a valid date.";
    if (!$errors && $f['work_start_date'] !== '' && $f['completion_date'] !== '' && $f['completion_date'] < $f['work_start_date'])
        $errors[] = "Completion date cannot be before the work start date.";
    if ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errors[] = "Email address is not valid.";
    if ($f['telephone'] !== '' && !preg_match('/^[0-9+\-()\s,\/]{5,40}$/', $f['telephone'])) $errors[] = "Telephone may contain only digits, spaces and + - ( ) , /";
    if (!in_array($f['visit_type'], ['Physical', 'Remote'], true)) $errors[] = "Invalid visit type.";

    $checkLen($f['customer_name'], 150, 'Customer name');   $checkLen($f['customer_address'], 400, 'Address');
    $checkLen($f['contact_person'], 100, 'Contact person'); $checkLen($f['designation'], 100, 'Designation');
    $checkLen($f['email'], 120, 'Email');                   $checkLen($f['travel_time_hrs'], 30, 'Travel time');
    $checkLen($f['working_hrs'], 30, 'Working hrs');        $checkLen($f['instrument_type'], 255, 'Instrument type');
    $checkLen($f['model'], 255, 'Model');                   $checkLen($f['instrument_sr_no'], 255, 'Instrument Sr. No.');
    $checkLen($f['engineer_name'], 100, 'Engineer name');
    $checkLen($f['observations'], 1000, 'Observations');    $checkLen($f['additional_activities'], 1000, 'Additional activities');
    $checkLen($f['engineer_remarks'], 600, "Engineer's remarks"); $checkLen($f['customer_remarks'], 600, "Customer's remarks");

    // --- Laboratory / machines link (all optional; every selected machine must belong to the selected lab) ---
    $labIn = ctype_digit($f['lab_id']) ? (int)$f['lab_id'] : 0;
    if ($labIn > 0) {
        $gs = $conn->prepare("SELECT id FROM gold_testing_lab_customers WHERE id = ? LIMIT 1");
        $gs->bind_param("i", $labIn); $gs->execute();
        if (!$gs->get_result()->fetch_row()) { $errors[] = "The selected laboratory does not exist."; $labIn = 0; }
        $gs->close();
    }
    $postIds = [];
    foreach ((array)($_POST['machine_ids'] ?? []) as $v) {
        if (is_string($v) && ctype_digit($v) && (int)$v > 0 && !in_array((int)$v, $postIds, true)) $postIds[] = (int)$v;
    }
    $selIds = $postIds;
    if ($postIds) {
        if ($labIn === 0) { $errors[] = "Select a laboratory before choosing machines."; $selIds = []; }
        else {
            $ph = implode(',', array_fill(0, count($postIds), '?'));
            $gs = $conn->prepare("SELECT COUNT(*) FROM gold_testing_lab_customers_machine WHERE customer_id = ? AND id IN ($ph)");
            $gs->bind_param(str_repeat('i', count($postIds) + 1), $labIn, ...$postIds); $gs->execute();
            if ((int)$gs->get_result()->fetch_row()[0] !== count($postIds)) { $errors[] = "One or more selected machines do not belong to the selected laboratory."; $selIds = []; }
            $gs->close();
        }
    }
    $f['lab_id']     = $labIn > 0 ? (string)$labIn : '';
    $f['machine_id'] = $selIds ? (string)$selIds[0] : '';        // first machine, kept for older pages

    // --- Spare parts rows ---
    $partsRows = []; $clean = [];
    $codes = (array)($_POST['sp_code'] ?? []); $descs = (array)($_POST['sp_desc'] ?? []);
    $qtys  = (array)($_POST['sp_qty'] ?? []);  $bills = (array)($_POST['sp_bill'] ?? []);
    $n = min(count($codes), 100);
    for ($i = 0; $i < $n; $i++) {
        $row = [
            'part_code'            => trim((string)($codes[$i] ?? '')),
            'part_description'     => trim((string)($descs[$i] ?? '')),
            'quantity'             => trim((string)($qtys[$i] ?? '')),
            'billing_instructions' => trim((string)($bills[$i] ?? '')),
        ];
        if (implode('', $row) === '') continue;           // ignore blank rows
        $partsRows[] = $row;
        $ln = count($partsRows);
        if ($row['part_code'] === '' && $row['part_description'] === '') $errors[] = "Spare part row $ln: enter a part code or description.";
        if ($row['quantity'] !== '' && (!ctype_digit($row['quantity']) || (int)$row['quantity'] > 99999)) $errors[] = "Spare part row $ln: quantity must be a whole number.";
        if (mb_strlen($row['part_code']) > 100 || mb_strlen($row['part_description']) > 255 || mb_strlen($row['billing_instructions']) > 255)
            $errors[] = "Spare part row $ln: a field is too long.";
        $clean[] = $row;
    }
    if (count($clean) > MR_MAX_PARTS) $errors[] = "A maximum of " . MR_MAX_PARTS . " spare part rows fits on the one-page PDF.";

    // --- Actions (stored as plain text) ---
    $actionRows = [];
    foreach ((array)($_POST['actions'] ?? []) as $t) {
        if (!is_string($t)) continue;
        $t = trim($t);
        if ($t === '' || in_array($t, $actionRows, true)) continue;
        if (mb_strlen($t) > 255) { $errors[] = "An action is longer than 255 characters."; continue; }
        $actionRows[] = $t;
    }
    if (count($actionRows) > 30) $errors[] = "Too many actions selected (maximum 30).";
    $selectedTexts = $actionRows;

    // --- "Other" (hand-written) actions: any number of extra rows, one point each ---
    $hasOther = !empty($_POST['other_on']); $otherTexts = [];
    if ($hasOther) {
        foreach ((array)($_POST['other_text'] ?? []) as $v) {
            if (!is_string($v)) continue;
            $t = trim($v);
            if ($t === '') continue;                                   // empty boxes are ignored
            if (mb_strlen($t) > 255) { $errors[] = "An 'Other' action is longer than 255 characters."; continue; }
            $otherTexts[] = $t;
        }
        if (count($otherTexts) > 10) $errors[] = "Too many 'Other' actions (maximum 10).";
    }

    // --- Persist inside one transaction ---
    if (!$errors) {
        $cols = ['report_type','lab_id','machine_id','report_date','customer_name','customer_address','contact_person','designation','telephone','email',
                 'work_start_date','completion_date','travel_time_hrs','working_hrs','instrument_type','model','instrument_sr_no',
                 'visit_type','observations','additional_activities','engineer_remarks','customer_remarks','engineer_name'];
        $vals = [];
        foreach ($cols as $c) $vals[] = ($f[$c] === '' && !in_array($c, ['report_type','report_date','customer_name'], true)) ? null : $f[$c];

        try {
            $conn->begin_transaction();

            if ($isEdit) {
                $set  = implode(', ', array_map(fn($c) => "$c = ?", $cols));
                $stmt = $conn->prepare("UPDATE machines_report SET $set WHERE id = ?");
                $bindVals = array_merge($vals, [$reportId]);
                $stmt->bind_param(str_repeat('s', count($cols)) . 'i', ...$bindVals);
                $stmt->execute(); $stmt->close();
                $rid = $reportId;
                foreach (['machines_report_action', 'machines_report_spareparts'] as $tbl) {
                    $d = $conn->prepare("DELETE FROM $tbl WHERE report_id = ?");
                    $d->bind_param("i", $rid); $d->execute(); $d->close();
                }
            } else {
                $ph   = implode(',', array_fill(0, count($cols) + 1, '?'));
                $stmt = $conn->prepare("INSERT INTO machines_report (" . implode(',', $cols) . ", created_by) VALUES ($ph)");
                $bindVals = array_merge($vals, [$uid]);
                $stmt->bind_param(str_repeat('s', count($cols)) . 'i', ...$bindVals);
                $stmt->execute();
                $rid = (int)$conn->insert_id; $stmt->close();
            }

            // every selected machine of this report
            $d = $conn->prepare("DELETE FROM machines_report_machine WHERE report_id = ?");
            $d->bind_param("i", $rid); $d->execute(); $d->close();
            if ($selIds) {
                $mm = $conn->prepare("INSERT INTO machines_report_machine (report_id, machine_id) VALUES (?, ?)");
                foreach ($selIds as $mid) { $mm->bind_param("ii", $rid, $mid); $mm->execute(); }
                $mm->close();
            }

            $stmt = $conn->prepare("INSERT INTO machines_report_action (report_id, action_text, is_other, sort_order) VALUES (?, ?, ?, ?)");
            $so = 0;
            foreach ($actionRows as $atext) { $so++; $io = 0; $stmt->bind_param("isii", $rid, $atext, $io, $so); $stmt->execute(); }
            if ($hasOther) {                       // one row per "Other" action; none typed = one blank line for hand-writing on the PDF
                foreach (($otherTexts ?: ['']) as $ot) { $so++; $io = 1; $stmt->bind_param("isii", $rid, $ot, $io, $so); $stmt->execute(); }
            }
            $stmt->close();
            if ($clean) {
                $stmt = $conn->prepare("INSERT INTO machines_report_spareparts (report_id, sr_no, part_code, part_description, quantity, billing_instructions) VALUES (?,?,?,?,?,?)");
                foreach ($clean as $i => $p) {
                    $sr = $i + 1;
                    $pc = $p['part_code'] !== '' ? $p['part_code'] : null;
                    $pd = $p['part_description'] !== '' ? $p['part_description'] : null;
                    $pq = $p['quantity'] !== '' ? (int)$p['quantity'] : null;
                    $pb = $p['billing_instructions'] !== '' ? $p['billing_instructions'] : null;
                    $stmt->bind_param("iissis", $rid, $sr, $pc, $pd, $pq, $pb);
                    $stmt->execute();
                }
                $stmt->close();
            }

            $conn->commit();
            header("Location: machines_report_pdf.php?id=" . (int)$rid . "&saved=" . ($isEdit ? 'updated' : 'created')); exit;
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('machines_report save failed: ' . $e->getMessage());
            $errors[] = "The report could not be saved. Nothing was changed — please try again.";
        }
    }
}

// ---------- Checklist items: active master actions + anything already ticked that is no longer in the list ----------
$items = []; $seen = [];
$res = $conn->query("SELECT action_text FROM machines_report_action_list WHERE is_active = 1 ORDER BY sort_order, id");
foreach ($res as $a) { $t = trim($a['action_text']); if (isset($seen[$t])) continue; $seen[$t] = 1; $items[] = ['text' => $t, 'legacy' => false]; }
foreach ($selectedTexts as $t) { if (isset($seen[$t])) continue; $seen[$t] = 1; $items[] = ['text' => $t, 'legacy' => true]; }

// spare parts: at least 3 rows
while (count($partsRows) < 3) $partsRows[] = ['part_code' => '', 'part_description' => '', 'quantity' => '', 'billing_instructions' => ''];

// ---------- Gold Testing Lab data for the Lab / Machine selects (embedded as JSON; no extra request) ----------
$goldLabs = []; $goldMachines = [];
foreach ($conn->query("SELECT id, customer_name, address, contact_person, designation_department, telephone, email FROM gold_testing_lab_customers ORDER BY customer_name ASC, id ASC") as $gl) {
    $goldLabs[] = ['id' => (int)$gl['id'], 'customer_name' => $gl['customer_name'], 'address' => (string)$gl['address'], 'contact_person' => (string)$gl['contact_person'],
                   'designation' => (string)$gl['designation_department'], 'telephone' => (string)$gl['telephone'], 'email' => (string)$gl['email']];
}
foreach ($conn->query("SELECT id, customer_id, machine_no, model_no, serial_no FROM gold_testing_lab_customers_machine ORDER BY machine_no ASC, id ASC") as $gm) {
    $goldMachines[(int)$gm['customer_id']][] = ['id' => (int)$gm['id'], 'machine_no' => $gm['machine_no'], 'model_no' => (string)$gm['model_no'], 'serial_no' => (string)$gm['serial_no']];
}
// machines ticked on this report (after a failed save: what was posted; when editing: what is stored)
if (!isset($selIds)) {
    $selIds = [];
    if ($isEdit) {
        $qs = $conn->prepare("SELECT machine_id FROM machines_report_machine WHERE report_id = ? ORDER BY machine_id");
        $qs->bind_param("i", $reportId); $qs->execute();
        foreach ($qs->get_result() as $row) $selIds[] = (int)$row['machine_id'];
        $qs->close();
        if (!$selIds && !empty($f['machine_id'])) $selIds = [(int)$f['machine_id']];
        if (count($selIds) > 1 && !empty($f['instrument_type'])) {      // keep the order of the saved "A, B, C" text
            $savedNames = array_map('trim', explode(',', (string)$f['instrument_type'])); $noById = [];
            foreach ($goldMachines as $glist) foreach ($glist as $gm) $noById[$gm['id']] = $gm['machine_no'];
            usort($selIds, function ($a, $b) use ($savedNames, $noById) {
                $pa = array_search($noById[$a] ?? '', $savedNames, true); $pb = array_search($noById[$b] ?? '', $savedNames, true);
                return ($pa === false ? 999 : $pa) <=> ($pb === false ? 999 : $pb);
            });
        }
    }
}
$goldJson = json_encode(['labs' => $goldLabs, 'machines' => (object)$goldMachines],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);

$pageTitle = $isEdit ? 'Edit Report MR-' . str_pad((string)$reportId, 5, '0', STR_PAD_LEFT) : 'New Machine Report';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($pageTitle) ?> — Rajaiswari</title>
  <link rel="icon" type="image/png" href="favicon.png">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root{--bg:#f1f3f6;--surface:#fff;--surface-2:#fafbfc;--border:#e4e7ec;--bsoft:#f0f1f3;--t1:#111827;--t2:#374151;--t3:#6b7280;--t4:#9ca3af;
      --blue:#2563eb;--blue-bg:#eff6ff;--blue-b:#bfdbfe;--green:#059669;--green-bg:#ecfdf5;--green-b:#a7f3d0;--amber:#d97706;--amber-bg:#fffbeb;--amber-b:#fde68a;
      --red:#dc2626;--red-bg:#fef2f2;--red-b:#fecaca;--violet:#7c3aed;--violet-bg:#f5f3ff;--violet-b:#ddd6fe;--teal:#0f766e;--teal-bg:#f0fdfa;--teal-b:#99f6e4;
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
    .i-blue{background:var(--blue-bg);color:var(--blue);}.i-violet{background:var(--violet-bg);color:var(--violet);}.i-amber{background:var(--amber-bg);color:var(--amber);}
    .i-green{background:var(--green-bg);color:var(--green);}.i-red{background:var(--red-bg);color:var(--red);}.i-teal{background:var(--teal-bg);color:var(--teal);}
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
    /* report type cards */
    .type-opt input{position:absolute;opacity:0;pointer-events:none;}
    .type-card{display:flex;align-items:center;gap:12px;padding:14px 16px;border:2px solid var(--border);border-radius:var(--r);cursor:pointer;background:var(--surface);transition:all .15s;height:100%;}
    .type-card:hover{border-color:#9ca3af;}
    .type-card .tc-ico{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:15px;background:var(--bsoft);color:var(--t3);}
    .type-card .tc-t{font-weight:700;}.type-card .tc-s{font-size:.78rem;color:var(--t4);}
    .type-opt input:checked + .type-card{border-color:var(--blue);background:var(--blue-bg);}
    .type-opt input:checked + .type-card .tc-ico{background:var(--blue);color:#fff;}
    .type-opt input:focus-visible + .type-card{box-shadow:0 0 0 3px rgba(37,99,235,.25);}
    /* checklist */
    .chk-item{display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border:1.5px solid var(--border);border-radius:var(--rs);cursor:pointer;background:var(--surface);height:100%;transition:all .15s;}
    .chk-item:hover{border-color:#9ca3af;}
    .chk-item .form-check-input{margin-top:3px;flex-shrink:0;width:1.1em;height:1.1em;}
    .chk-item:has(input:checked){background:var(--green-bg);border-color:var(--green-b);}
    .chk-text{font-size:.875rem;color:var(--t2);}
    .chk-other{cursor:default;}
    .legacy-tag{font-size:.65rem;font-weight:700;background:var(--amber-bg);color:#92400e;border:1px solid var(--amber-b);border-radius:20px;padding:0 7px;margin-left:5px;white-space:nowrap;}
    /* spare parts */
    .sp-row{display:grid;grid-template-columns:44px 1.1fr 2fr 90px 1.6fr 40px;gap:8px;align-items:center;margin-bottom:8px;}
    .sp-head{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t4);}
    .sp-no{font-family:'DM Mono',monospace;font-weight:600;color:var(--t3);text-align:center;}
    .sp-mlbl{display:none;}
    .btn-rm{width:38px;height:38px;border:1.5px solid var(--red-b);background:var(--red-bg);color:var(--red);border-radius:var(--rs);}
    .btn-rm:hover{background:var(--red);color:#fff;}
    .action-bar{display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:13px 18px;background:var(--surface-2);border-top:1px solid var(--border);}
    .btn-ghost{display:inline-flex;align-items:center;gap:6px;height:40px;padding:0 18px;background:var(--surface);border:1.5px solid var(--border);border-radius:7px;font-weight:500;color:var(--t2);text-decoration:none;}
    .btn-ghost:hover{background:var(--surface-2);color:var(--t1);}
    .btn-submit{display:inline-flex;align-items:center;gap:7px;height:40px;padding:0 24px;border:none;border-radius:7px;font-weight:700;color:#fff;background:var(--green);}
    .btn-submit.edit{background:var(--amber);}.btn-submit:hover{filter:brightness(.92);}
    @media(max-width:991.98px){.page-shell{margin-left:0;padding-top:52px;}.top-bar{top:52px;padding:8px 14px;}.main{padding:14px 14px 50px;}}
    @media(max-width:767.98px){
      .sp-head-row{display:none;}
      .sp-row{grid-template-columns:1fr 1fr;border:1.5px solid var(--border);border-radius:var(--rs);padding:10px;background:var(--surface-2);}
      .sp-row .sp-no{grid-column:1/2;text-align:left;}.sp-row .sp-rm{grid-column:2/3;justify-self:end;}
      .sp-row .sp-c{grid-column:1/3;}.sp-row .sp-q{grid-column:1/3;}
      .sp-mlbl{display:block;font-size:.68rem;font-weight:700;text-transform:uppercase;color:var(--t4);margin-bottom:2px;}
      .action-bar{flex-direction:column-reverse;align-items:stretch;}.action-bar>*{justify-content:center;}
    }

    /* ===== formal UI: same look as the Gold Testing Labs pages ===== */
    .sec{border-radius:4px;border-color:#cbd5e1;box-shadow:none;}
    .sec-head{background:#f3f4f6;border-bottom:1px solid #cbd5e1;}
    .form-control,.form-select{border:1px solid #9ca3af;border-radius:4px;}
    .btn-act,.btn-add,.btn-soft,.btn-ghost,.btn-submit{border-radius:4px;}
    .type-card,.chk-item{border-radius:4px;border-width:1px;}
    .btn-rm{border-radius:4px;}
    .action-bar{background:#f3f4f6;border-top:1px solid #cbd5e1;}
    .other-row{display:flex;align-items:center;gap:8px;margin-top:6px;}
    .other-no{font-family:'DM Mono',monospace;font-weight:600;color:var(--t3);width:22px;text-align:right;flex-shrink:0;}
    .mbox{border:1px solid #9ca3af;border-radius:4px;padding:6px 12px;max-height:200px;overflow:auto;background:#fff;}
    .mrow{display:flex;align-items:flex-start;gap:9px;padding:5px 0;cursor:pointer;font-size:.875rem;margin:0;}
    .mrow .form-check-input{margin-top:3px;flex-shrink:0;}
    @media(min-width:768px){
      .sp-head-row{background:#f3f4f6;border:1px solid #cbd5e1;border-bottom:0;padding:9px 10px;margin-bottom:0;color:#111827;text-transform:none;letter-spacing:0;font-size:.82rem;}
      #spBody .sp-row{margin-bottom:0;padding:8px 10px;border:1px solid #e2e8f0;border-top:0;background:#fff;}
      #spBody{margin-bottom:10px;}
    }
  </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page-shell">
  <header class="top-bar">
    <div class="tb-ico"><i class="fas fa-<?= $isEdit ? 'pen-to-square' : 'file-circle-plus' ?>"></i></div>
    <div><div class="tb-title"><?= h($pageTitle) ?></div><div class="tb-sub">Installation / Service report</div></div>
    <div class="tb-right">
      <?php if ($isEdit): ?><a href="machines_report_pdf.php?id=<?= $reportId ?>" class="btn-soft"><i class="fas fa-file-pdf"></i><span class="d-none d-sm-inline"> PDF</span></a><?php endif; ?>
      <a href="machines_report_list.php" class="btn-soft"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
  </header>

  <main class="main">
    <form method="post" class="form-wrap" id="reportForm" novalidate>
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

      <?php if ($errors): ?>
        <div class="alert-err"><div class="alert-err-title"><i class="fas fa-circle-exclamation me-1"></i>Please fix the following:</div>
          <ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>

      <!-- 0. Laboratory & machine (Gold Testing Lab link) -->
      <div class="sec">
        <div class="sec-head"><div class="sec-ico i-teal"><i class="fas fa-flask-vial"></i></div><div class="sec-title">Laboratory &amp; Machine</div><div class="sec-meta">Optional · fills the fields below</div></div>
        <div class="sec-body"><div class="row g-3">
          <div class="col-12 col-md-6"><label class="lbl" for="lab_id">Laboratory</label>
            <select class="form-select" id="lab_id" name="lab_id"><option value="">— None / one-off customer —</option>
              <?php foreach ($goldLabs as $gl): ?><option value="<?= (int)$gl['id'] ?>" <?= (string)$f['lab_id'] === (string)$gl['id'] ? 'selected' : '' ?>><?= h($gl['customer_name']) ?></option><?php endforeach; ?>
            </select>
            <div class="form-text">Picking a laboratory fills the customer details below. <a href="gold_labs_list.php">Manage labs</a></div></div>
          <div class="col-12 col-md-6"><label class="lbl">Machines <span class="muted" style="text-transform:none;letter-spacing:0;font-weight:400;">(select one or more)</span></label>
            <div class="mbox" id="machBox"><span class="text-muted">Select a laboratory first</span></div>
            <div class="form-text">
              <a href="#" id="machAll">Select all</a> · <a href="#" id="machNone">Clear</a></div></div>
        </div></div>
      </div>

      <!-- 1. Type -->
      <div class="sec">
        <div class="sec-head"><div class="sec-ico i-violet"><i class="fas fa-clipboard-list"></i></div><div class="sec-title">Report Type</div><div class="sec-meta">Required</div></div>
        <div class="sec-body"><div class="row g-3">
          <div class="col-12 col-sm-6"><label class="type-opt d-block h-100 position-relative">
            <input type="radio" name="report_type" value="installation" required <?= $f['report_type'] === 'installation' ? 'checked' : '' ?>>
            <div class="type-card"><div class="tc-ico"><i class="fas fa-screwdriver-wrench"></i></div><div><div class="tc-t">Installation Report</div><div class="tc-s">New machine setup &amp; commissioning</div></div></div></label></div>
          <div class="col-12 col-sm-6"><label class="type-opt d-block h-100 position-relative">
            <input type="radio" name="report_type" value="service" required <?= $f['report_type'] === 'service' ? 'checked' : '' ?>>
            <div class="type-card"><div class="tc-ico"><i class="fas fa-gears"></i></div><div><div class="tc-t">Service Report</div><div class="tc-s">Maintenance, repair &amp; calibration visit</div></div></div></label></div>
        </div></div>
      </div>

      <!-- 2. Customer -->
      <div class="sec">
        <div class="sec-head"><div class="sec-ico i-blue"><i class="fas fa-building"></i></div><div class="sec-title">Customer Details</div></div>
        <div class="sec-body"><div class="row g-3">
          <div class="col-12 col-md-4"><label class="lbl" for="report_date">Report Date <span class="req">*</span></label><input type="date" class="form-control" id="report_date" name="report_date" value="<?= h($f['report_date']) ?>" required></div>
          <div class="col-12 col-md-8"><label class="lbl" for="customer_name">Customer Name <span class="req">*</span></label><input type="text" class="form-control" id="customer_name" name="customer_name" maxlength="150" value="<?= h($f['customer_name']) ?>" required></div>
          <div class="col-12"><label class="lbl" for="customer_address">Customer Address</label><textarea class="form-control" id="customer_address" name="customer_address" rows="2" maxlength="400"><?= h($f['customer_address']) ?></textarea></div>
          <div class="col-12 col-md-6"><label class="lbl" for="contact_person">Contact Person</label><input type="text" class="form-control" id="contact_person" name="contact_person" maxlength="100" value="<?= h($f['contact_person']) ?>"></div>
          <div class="col-12 col-md-6"><label class="lbl" for="designation">Designation / Dept.</label><input type="text" class="form-control" id="designation" name="designation" maxlength="100" value="<?= h($f['designation']) ?>"></div>
          <div class="col-12 col-md-6"><label class="lbl" for="telephone">Telephone</label><input type="tel" class="form-control" id="telephone" name="telephone" maxlength="40" value="<?= h($f['telephone']) ?>"></div>
          <div class="col-12 col-md-6"><label class="lbl" for="email">Email</label><input type="email" class="form-control" id="email" name="email" maxlength="120" value="<?= h($f['email']) ?>"></div>
        </div></div>
      </div>

      <!-- 3. Visit & Machine -->
      <div class="sec">
        <div class="sec-head"><div class="sec-ico i-amber"><i class="fas fa-microchip"></i></div><div class="sec-title">Visit &amp; Machine Details</div></div>
        <div class="sec-body"><div class="row g-3">
          <div class="col-6 col-md-3"><label class="lbl" for="work_start_date">Work Start Date</label><input type="date" class="form-control" id="work_start_date" name="work_start_date" value="<?= h($f['work_start_date']) ?>"></div>
          <div class="col-6 col-md-3"><label class="lbl" for="completion_date">Completion Date</label><input type="date" class="form-control" id="completion_date" name="completion_date" value="<?= h($f['completion_date']) ?>"></div>
          <div class="col-6 col-md-3"><label class="lbl" for="travel_time_hrs">Travel Time Hrs.</label><input type="text" class="form-control" id="travel_time_hrs" name="travel_time_hrs" maxlength="30" placeholder="e.g. 10 hrs" value="<?= h($f['travel_time_hrs']) ?>"></div>
          <div class="col-6 col-md-3"><label class="lbl" for="working_hrs">Working Hrs.</label><input type="text" class="form-control" id="working_hrs" name="working_hrs" maxlength="30" placeholder="e.g. 3 hrs" value="<?= h($f['working_hrs']) ?>"></div>
          <div class="col-12 col-md-6"><label class="lbl" for="instrument_type">Instrument Type <span class="req">*</span></label><input type="text" class="form-control" id="instrument_type" name="instrument_type" maxlength="255" value="<?= h($f['instrument_type']) ?>" required></div>
          <div class="col-12 col-md-6"><label class="lbl" for="model">Model</label><input type="text" class="form-control" id="model" name="model" maxlength="255" value="<?= h($f['model']) ?>"></div>
          <div class="col-12 col-md-6"><label class="lbl" for="instrument_sr_no">Instrument Sr. No.</label><input type="text" class="form-control" id="instrument_sr_no" name="instrument_sr_no" maxlength="255" value="<?= h($f['instrument_sr_no']) ?>"></div>
          <div class="col-12 col-md-6"><label class="lbl" for="visit_type">Visit Type</label>
            <select class="form-select" id="visit_type" name="visit_type"><?php foreach (['Physical', 'Remote'] as $v): ?><option <?= $f['visit_type'] === $v ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
          <div class="col-12"><label class="lbl" for="observations">Observations</label><textarea class="form-control" id="observations" name="observations" rows="3" maxlength="1000"><?= h($f['observations']) ?></textarea></div>
        </div></div>
      </div>

      <!-- 4. Action checklist -->
      <div class="sec">
        <div class="sec-head"><div class="sec-ico i-green"><i class="fas fa-list-check"></i></div><div class="sec-title">Action Taken</div>
          <div class="sec-meta"><a href="machines_report_action_list.php" class="text-decoration-none">Manage list</a></div></div>
        <div class="sec-body">
          <?php if (!$items): ?>
            <div class="text-muted mb-2">No active actions yet. <a href="machines_report_action_list.php">Add actions</a> to build the checklist.</div>
          <?php endif; ?>
          <div class="row g-2">
            <?php foreach ($items as $it): ?>
            <div class="col-12 col-md-6">
              <label class="chk-item">
                <input class="form-check-input" type="checkbox" name="actions[]" value="<?= h($it['text']) ?>" <?= in_array($it['text'], $selectedTexts, true) ? 'checked' : '' ?>>
                <span class="chk-text"><?= h($it['text']) ?><?php if ($it['legacy']): ?><span class="legacy-tag">not in current list</span><?php endif; ?></span>
              </label>
            </div>
            <?php endforeach; ?>
            <div class="col-12">
              <div class="chk-item chk-other">
                <input class="form-check-input" type="checkbox" name="other_on" id="otherOn" value="1" <?= $hasOther ? 'checked' : '' ?>>
                <div class="flex-grow-1">
                  <label class="chk-text fw-bold" for="otherOn" style="cursor:pointer">Other <span class="text-muted fw-normal">(each action is its own point; leave blank to hand-write on the printed report)</span></label>
                  <div id="otherList">
                    <?php foreach (($otherTexts ?: ['']) as $oi => $ot): ?>
                    <div class="other-row"><span class="other-no"><?= $oi + 1 ?></span>
                      <input type="text" class="form-control" name="other_text[]" maxlength="255" placeholder="Other action…" value="<?= h($ot) ?>" <?= $hasOther ? '' : 'disabled' ?>>
                      <button type="button" class="btn-rm other-rm" title="Remove" <?= $hasOther ? '' : 'disabled' ?>><i class="fas fa-trash-can"></i></button></div>
                    <?php endforeach; ?>
                  </div>
                  <button type="button" class="btn btn-outline-primary btn-sm fw-bold mt-2" id="otherAdd" <?= $hasOther ? '' : 'disabled' ?>><i class="fas fa-plus"></i> Add another action</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 5. Spare parts -->
      <div class="sec">
        <div class="sec-head"><div class="sec-ico i-red"><i class="fas fa-gear"></i></div><div class="sec-title">Spare Parts Replaced (if any)</div><div class="sec-meta">Max <?= MR_MAX_PARTS ?> rows</div></div>
        <div class="sec-body">
          <div class="sp-row sp-head sp-head-row"><div class="text-center">Sr.</div><div>Part Code</div><div>Part Description</div><div>Qty</div><div>Instructions for Billing</div><div></div></div>
          <div id="spBody">
            <?php foreach ($partsRows as $p): ?>
            <div class="sp-row">
              <div class="sp-no">1</div>
              <div class="sp-c"><span class="sp-mlbl">Part Code</span><input type="text" class="form-control" name="sp_code[]" maxlength="100" value="<?= h($p['part_code']) ?>"></div>
              <div class="sp-c"><span class="sp-mlbl">Part Description</span><input type="text" class="form-control" name="sp_desc[]" maxlength="255" value="<?= h($p['part_description']) ?>"></div>
              <div class="sp-q"><span class="sp-mlbl">Quantity</span><input type="number" class="form-control" name="sp_qty[]" min="0" max="99999" step="1" inputmode="numeric" value="<?= h($p['quantity']) ?>"></div>
              <div class="sp-c"><span class="sp-mlbl">Instructions for Billing</span><input type="text" class="form-control" name="sp_bill[]" maxlength="255" value="<?= h($p['billing_instructions']) ?>"></div>
              <div class="sp-rm"><button type="button" class="btn-rm" title="Remove row"><i class="fas fa-trash-can"></i></button></div>
            </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="btn btn-outline-primary btn-sm fw-bold mt-1" id="addRow"><i class="fas fa-plus"></i> Add Row</button>
        </div>
      </div>

      <!-- 6. Remarks -->
      <div class="sec">
        <div class="sec-head"><div class="sec-ico i-teal"><i class="fas fa-comment-dots"></i></div><div class="sec-title">Additional Activities &amp; Remarks</div></div>
        <div class="sec-body"><div class="row g-3">
          <div class="col-12"><label class="lbl" for="additional_activities">Additional Activities</label><textarea class="form-control" id="additional_activities" name="additional_activities" rows="3" maxlength="1000"><?= h($f['additional_activities']) ?></textarea></div>
          <div class="col-12 col-md-6"><label class="lbl" for="engineer_remarks">Engineer's Remarks</label><textarea class="form-control" id="engineer_remarks" name="engineer_remarks" rows="3" maxlength="600"><?= h($f['engineer_remarks']) ?></textarea></div>
          <div class="col-12 col-md-6"><label class="lbl" for="customer_remarks">Customer's Remarks</label><textarea class="form-control" id="customer_remarks" name="customer_remarks" rows="3" maxlength="600"><?= h($f['customer_remarks']) ?></textarea></div>
          <div class="col-12 col-md-6"><label class="lbl" for="engineer_name">Engineer Name (printed under signature)</label><input type="text" class="form-control" id="engineer_name" name="engineer_name" maxlength="100" value="<?= h($f['engineer_name']) ?>"></div>
        </div></div>
        <div class="action-bar">
          <a href="machines_report_list.php" class="btn-ghost">Cancel</a>
          <button type="submit" class="btn-submit <?= $isEdit ? 'edit' : '' ?>"><i class="fas fa-<?= $isEdit ? 'floppy-disk' : 'check' ?>"></i> <?= $isEdit ? 'Update Report' : 'Save Report' ?></button>
        </div>
      </div>
    </form>
  </main>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
// Gold Testing Lab: Lab -> customer fields, Machine -> instrument fields (data embedded above, no request)
(function () {
  var GOLD = <?= $goldJson ?>;
  var labSel = document.getElementById('lab_id'), box = document.getElementById('machBox');
  var order = <?= json_encode(array_map('strval', $selIds)) ?>;        // machine ids in the order they were ticked
  function setVal(id, v) { var el = document.getElementById(id); if (el) el.value = v || ''; }
  function machinesOf(labId) { return (labId && GOLD.machines[labId]) ? GOLD.machines[labId] : []; }
  function render(labId) {
    box.innerHTML = '';
    var list = machinesOf(labId);
    if (!labId || !list.length) {
      var s = document.createElement('span'); s.className = 'text-muted';
      s.textContent = !labId ? 'Select a laboratory first' : 'This laboratory has no machines yet';
      box.appendChild(s); return;
    }
    list.forEach(function (m) {
      var l = document.createElement('label'); l.className = 'mrow';
      var c = document.createElement('input'); c.type = 'checkbox'; c.className = 'form-check-input'; c.name = 'machine_ids[]'; c.value = m.id;
      c.checked = order.indexOf(String(m.id)) !== -1;
      var t = document.createElement('span');
      t.textContent = m.machine_no + (m.model_no ? ' · ' + m.model_no : '') + (m.serial_no ? ' · SN ' + m.serial_no : '');
      l.appendChild(c); l.appendChild(t); box.appendChild(l);
    });
  }
  function applyFields() {                                   // instrument fields = ticked machines, in the order they were ticked
    var list = machinesOf(parseInt(labSel.value, 10) || 0);
    var ms = order.map(function (id) { return list.filter(function (m) { return String(m.id) === id; })[0]; }).filter(Boolean);
    var uniq = function (a) { return a.filter(function (v, i) { return v && a.indexOf(v) === i; }); };
    setVal('instrument_type', ms.map(function (m) { return m.machine_no; }).join(', '));
    setVal('model', uniq(ms.map(function (m) { return m.model_no; })).join(', '));
    setVal('instrument_sr_no', ms.map(function (m) { return m.serial_no; }).filter(Boolean).join(', '));
  }
  box.addEventListener('change', function (e) {
    var c = e.target; if (!c || c.type !== 'checkbox') return;
    var id = String(c.value), i = order.indexOf(id);
    if (c.checked && i === -1) order.push(id); else if (!c.checked && i !== -1) order.splice(i, 1);
    applyFields();
  });
  document.getElementById('machAll').addEventListener('click', function (e) {
    e.preventDefault();
    box.querySelectorAll('input[type=checkbox]').forEach(function (c) { if (!c.checked) { c.checked = true; order.push(String(c.value)); } });
    applyFields();
  });
  document.getElementById('machNone').addEventListener('click', function (e) {
    e.preventDefault();
    box.querySelectorAll('input[type=checkbox]').forEach(function (c) { c.checked = false; });
    order = []; applyFields();
  });
  labSel.addEventListener('change', function () {
    var id = parseInt(this.value, 10) || 0, had = order.length > 0;
    order = []; render(id); if (had) applyFields();
    var lab = GOLD.labs.filter(function (l) { return l.id === id; })[0];
    if (!lab) return;                                   // clearing the lab keeps whatever is typed
    setVal('customer_name', lab.customer_name); setVal('customer_address', lab.address); setVal('contact_person', lab.contact_person);
    setVal('designation', lab.designation); setVal('telephone', lab.telephone); setVal('email', lab.email);
  });
  render(parseInt(labSel.value, 10) || 0);                    // on load only restores the ticks; never rewrites saved text
})();
</script>
<script>
(function () {
  var MAX = <?= MR_MAX_PARTS ?>;
  var body = document.getElementById('spBody');
  var addBtn = document.getElementById('addRow');

  function renumber() {
    var rows = body.querySelectorAll('.sp-row');
    rows.forEach(function (r, i) { r.querySelector('.sp-no').textContent = i + 1; });
    addBtn.disabled = rows.length >= MAX;
  }
  addBtn.addEventListener('click', function () {
    var rows = body.querySelectorAll('.sp-row');
    if (rows.length >= MAX) return;
    var c = rows[rows.length - 1].cloneNode(true);
    c.querySelectorAll('input').forEach(function (i) { i.value = ''; });
    body.appendChild(c); renumber();
    c.querySelector('input').focus();
  });
  body.addEventListener('click', function (e) {
    var b = e.target.closest('.btn-rm'); if (!b) return;
    var rows = body.querySelectorAll('.sp-row'), row = b.closest('.sp-row');
    if (rows.length <= 1) { row.querySelectorAll('input').forEach(function (i) { i.value = ''; }); return; }
    row.remove(); renumber();
  });
  renumber();

  // "Other" actions: add as many as needed, one point per row
  var otherOn = document.getElementById('otherOn'), otherList = document.getElementById('otherList'), otherAdd = document.getElementById('otherAdd'), OTHER_MAX = 10;
  function otherRows() { return otherList.querySelectorAll('.other-row'); }
  function otherRefresh() {
    var rows = otherRows(), on = otherOn.checked;
    rows.forEach(function (r, i) { r.querySelector('.other-no').textContent = i + 1; });
    otherList.querySelectorAll('input,button').forEach(function (el) { el.disabled = !on; });
    otherAdd.disabled = !on; otherAdd.style.display = rows.length >= OTHER_MAX ? 'none' : '';
  }
  function otherAddRow() {
    if (otherRows().length >= OTHER_MAX) return null;
    var row = document.createElement('div'); row.className = 'other-row';
    row.innerHTML = '<span class="other-no"></span><input type="text" class="form-control" name="other_text[]" maxlength="255" placeholder="Other action…">' +
                    '<button type="button" class="btn-rm other-rm" title="Remove"><i class="fas fa-trash-can"></i></button>';
    otherList.appendChild(row); otherRefresh();
    return row.querySelector('input');
  }
  otherOn.addEventListener('change', function () { otherRefresh(); if (this.checked) otherRows()[0].querySelector('input').focus(); });
  otherAdd.addEventListener('click', function () { var i = otherAddRow(); if (i) i.focus(); });
  otherList.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('.other-rm') : null; if (!b) return;
    var rows = otherRows(), row = b.closest('.other-row');
    if (rows.length <= 1) { row.querySelector('input').value = ''; return; }
    row.remove(); otherRefresh();
  });
  otherList.addEventListener('keydown', function (e) {                // Enter = next action (does not submit the form)
    if (e.key !== 'Enter' || e.target.tagName !== 'INPUT') return;
    e.preventDefault();
    if (e.target.value.trim() === '') return;
    var rows = Array.prototype.slice.call(otherRows()), idx = rows.indexOf(e.target.closest('.other-row'));
    var next = rows[idx + 1] ? rows[idx + 1].querySelector('input') : otherAddRow();
    if (next) next.focus();
  });
  otherRefresh();

  // client-side validation (server re-validates everything)
  document.getElementById('reportForm').addEventListener('submit', function (e) {
    var msgs = [];
    if (!document.querySelector('input[name=report_type]:checked')) msgs.push('Select a report type.');
    if (!document.getElementById('customer_name').value.trim()) msgs.push('Customer name is required.');
    if (!document.getElementById('instrument_type').value.trim()) msgs.push('Instrument type is required.');
    var s = document.getElementById('work_start_date').value, c = document.getElementById('completion_date').value;
    if (s && c && c < s) msgs.push('Completion date cannot be before the work start date.');
    var em = document.getElementById('email'); if (em.value && !em.checkValidity()) msgs.push('Email address is not valid.');
    if (msgs.length) { e.preventDefault(); alert(msgs.join('\n')); }
  });
})();
</script>
</body>
</html>