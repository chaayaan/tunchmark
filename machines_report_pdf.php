<?php
require 'auth.php';
require 'mydb.php';
require_engineering();               // admin + engineer employee only — applies to view, print and download

if (isset($conn)) { $conn->set_charset('utf8mb4'); }
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
$report = null;
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM machines_report WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$report) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">'
       . '<div class="container py-5 text-center"><h4>Report not found</h4><a class="btn btn-primary mt-3" href="machines_report_list.php">Back to reports</a></div>';
    exit;
}

$stmt = $conn->prepare("SELECT action_text FROM machines_report_action WHERE report_id = ? ORDER BY sort_order, id");
$stmt->bind_param("i", $id); $stmt->execute();
$actions = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'action_text');
$stmt->close();

$stmt = $conn->prepare("SELECT part_code, part_description, quantity, billing_instructions FROM machines_report_spareparts WHERE report_id = ? ORDER BY sr_no, id");
$stmt->bind_param("i", $id); $stmt->execute();
$parts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
while (count($parts) < 3) $parts[] = ['part_code' => '', 'part_description' => '', 'quantity' => '', 'billing_instructions' => ''];

$isInst   = $report['report_type'] === 'installation';
$title    = $isInst ? 'INSTALLATION REPORT' : 'SERVICE REPORT';
$reportNo = 'MR-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
$d        = fn($v) => $v ? date('d.m.Y', strtotime($v)) : '';
$fileName = ($isInst ? 'Installation' : 'Service') . '-Report-' . $reportNo . '-' . preg_replace('/[^A-Za-z0-9]+/', '-', trim($report['customer_name'], ' -')) . '.pdf';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title . ' ' . $reportNo) ?></title>
  <link rel="icon" type="image/png" href="favicon.png">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    @page { size: A4 portrait; margin: 1in 0.5in 1in 0.5in; }   /* 1 inch top & bottom */
    body{background:#e5e7eb;font-family:Arial,Helvetica,sans-serif;color:#000;}
    .toolbar{position:sticky;top:0;z-index:50;background:#fff;border-bottom:1px solid #d1d5db;padding:8px 14px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;}
    .toolbar .ttl{font-weight:700;margin-right:auto;font-size:.95rem;}
    .stage{padding:18px 8px 40px;overflow-x:hidden;}
    .paper-wrap{margin:0 auto;}
    .paper{width:210mm;height:297mm;background:#fff;padding:1in 0.5in;box-shadow:0 2px 14px rgba(0,0,0,.2);transform-origin:top left;overflow:hidden;}
    #sheet{position:relative;width:184mm;font-size:10.5pt;line-height:1.25;}
    .wm{position:absolute;left:50%;top:50%;width:62%;transform:translate(-50%,-50%);opacity:.07;z-index:0;pointer-events:none;}
    .frame{position:relative;z-index:1;border:.17em solid #000;overflow:hidden;background:transparent;}
    table.t{width:calc(100% + .14em);margin:-.07em;table-layout:fixed;border-collapse:collapse;}
    table.t td,table.t th{border:.07em solid #000;padding:.22em .45em;vertical-align:top;overflow-wrap:break-word;word-break:normal;hyphens:none;text-align:left;}
    table.t.thick tr:first-child>td,table.t.thick tr:first-child>th{border-top:.14em solid #000;}
    .lb{font-weight:700;white-space:nowrap;}
    .lbu{font-weight:700;text-decoration:underline;white-space:nowrap;}
    .v{font-family:'Times New Roman',Times,serif;font-size:1.05em;white-space:pre-wrap;}
    .title{font-size:2.1em;font-weight:700;text-align:center!important;padding:.1em 0!important;line-height:1.2;}
    .meta{font-size:.85em;font-weight:700;height:1.9em;vertical-align:middle!important;}
    .secthead{text-align:center!important;font-weight:700;}
    .r1{height:2.6em;}.r2{height:2.1em;}
    .box-obs{min-height:3.4em;}.box-act{min-height:6.6em;}.box-add{min-height:4.8em;}
    ol.acts{margin:.3em 0 0;padding-left:2.4em;}ol.acts li{margin:.18em 0;font-family:'Times New Roman',Times,serif;font-size:1.05em;}
    table.sp td,table.sp th{height:2.1em;}
    table.sp th{height:3em;}
    .sig{display:flex;flex-direction:column;justify-content:space-between;min-height:11em;padding-bottom:1.6em;}
    .signame{margin-top:.3em;font-family:'Times New Roman',Times,serif;font-size:1.05em;min-height:1.2em;}
    .ctr{text-align:center!important;}
    @media (max-width:767.98px){ .toolbar .ttl{flex-basis:100%;} }
    @media print{
      html,body{background:#fff!important;}
      .toolbar{display:none!important;}
      .stage{padding:0!important;overflow:visible!important;}
      .paper-wrap{width:auto!important;height:auto!important;margin:0!important;}
      .paper{transform:none!important;width:auto!important;height:auto!important;padding:0!important;box-shadow:none!important;overflow:visible!important;}
      #sheet{width:184mm;margin:0 auto;}
      .frame{page-break-inside:avoid;break-inside:avoid;}
      *{-webkit-print-color-adjust:exact;print-color-adjust:exact;}
    }
  </style>
</head>
<body>
  <div class="toolbar">
    <div class="ttl"><i class="fas fa-file-pdf text-danger me-1"></i><?= h($title) ?> · <?= h($reportNo) ?></div>
    <div class="form-check form-switch m-0 me-2"><input class="form-check-input" type="checkbox" id="wmToggle" checked><label class="form-check-label small" for="wmToggle">Watermark</label></div>
    <a href="machines_report_list.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Reports</a>
    <a href="machines_report_form.php?id=<?= $id ?>" class="btn btn-outline-warning btn-sm"><i class="fas fa-pen"></i> Edit</a>
    <button type="button" class="btn btn-outline-primary btn-sm" id="btnPrint"><i class="fas fa-print"></i> Print</button>
    <button type="button" class="btn btn-danger btn-sm fw-bold" id="btnDownload"><i class="fas fa-download"></i> Download PDF</button>
  </div>

  <div class="stage">
    <div class="paper-wrap" id="paperWrap">
      <div class="paper" id="paper">
        <div id="sheet">
          <img class="wm" id="wm" src="rajaiswari-wotbg.png" alt="" onerror="this.remove()">
          <div class="frame">
            <table class="t"><colgroup><col style="width:100%"></colgroup>
              <tr><th class="title"><?= h($title) ?></th></tr></table>
            <table class="t"><colgroup><col style="width:52%"><col style="width:48%"></colgroup>
              <tr><td class="meta">Report No: <span class="v"><?= h($reportNo) ?></span></td><td class="meta" style="text-align:right!important">Date: <span class="v"><?= h($d($report['report_date'])) ?></span></td></tr></table>

            <table class="t thick"><colgroup><col style="width:52%"><col style="width:17.4%"><col style="width:30.6%"></colgroup>
              <tr class="r1"><td rowspan="4"><div class="lb">Customer Name and Address</div><div class="v"><b><?= h($report['customer_name']) ?></b><?= $report['customer_address'] ? "\n" . h($report['customer_address']) : '' ?></div></td><td class="lb">Contact Person</td><td class="v"><?= h($report['contact_person']) ?></td></tr>
              <tr class="r2"><td class="lb">Desgn./Dept.</td><td class="v"><?= h($report['designation']) ?></td></tr>
              <tr class="r2"><td class="lb">Telephone</td><td class="v"><?= h($report['telephone']) ?></td></tr>
              <tr class="r2"><td class="lb">Email</td><td class="v"><?= h($report['email']) ?></td></tr>
            </table>

            <table class="t thick"><colgroup><col style="width:14.7%"><col style="width:12.5%"><col style="width:14%"><col style="width:10.8%"><col style="width:12.9%"><col style="width:12.9%"><col style="width:13.4%"><col style="width:8.8%"></colgroup>
              <tr class="r1"><td class="lb">Work Start<br>Date</td><td class="v"><?= h($d($report['work_start_date'])) ?></td><td class="lb">Completion<br>Date</td><td class="v"><?= h($d($report['completion_date'])) ?></td><td class="lb">Travel<br>Time Hrs.</td><td class="v"><?= h($report['travel_time_hrs']) ?></td><td class="lb">Working<br>Hrs.</td><td class="v"><?= h($report['working_hrs']) ?></td></tr>
            </table>

            <table class="t thick"><colgroup><col style="width:26.5%"><col style="width:25.5%"><col style="width:19.3%"><col style="width:28.7%"></colgroup>
              <tr class="r2"><td class="lb">Instrument Type</td><td class="v"><?= h($report['instrument_type']) ?></td><td class="lb">Model</td><td class="v"><?= h($report['model']) ?></td></tr>
            </table>

            <table class="t thick"><colgroup><col style="width:19%"><col style="width:25.5%"><col style="width:19.1%"><col style="width:36.4%"></colgroup>
              <tr class="r1"><td class="lb">Instrument Sr.<br>No.</td><td class="v"><?= h($report['instrument_sr_no']) ?></td><td class="lb">Visit Type</td><td class="v"><?= h($report['visit_type']) ?></td></tr>
            </table>

            <table class="t thick"><colgroup><col style="width:100%"></colgroup>
              <tr><td><div class="lbu">Observations</div><div class="v box-obs"><?= h($report['observations']) ?></div></td></tr></table>

            <table class="t thick"><colgroup><col style="width:100%"></colgroup>
              <tr><td><div class="lbu">Action Taken</div><div class="box-act"><?php if ($actions): ?><ol class="acts"><?php foreach ($actions as $a): ?><li><?= h($a) ?></li><?php endforeach; ?></ol><?php endif; ?></div></td></tr></table>

            <table class="t thick sp"><colgroup><col style="width:8%"><col style="width:18.6%"><col style="width:30.4%"><col style="width:11.4%"><col style="width:31.6%"></colgroup>
              <tr><td colspan="5" class="secthead" style="height:1.9em">SPARE PARTS REPLACED (IF ANY)</td></tr>
              <tr><th class="lb">Sr.<br>No.</th><th class="lb">Part Code</th><th class="lb">Part Description</th><th class="lb">Quantity</th><th class="lb">Instructions for Billing</th></tr>
              <?php foreach ($parts as $i => $p): $has = ($p['part_code'] . $p['part_description'] . $p['quantity'] . $p['billing_instructions']) !== ''; ?><tr class="v"><td class="ctr"><?= $has ? $i + 1 : '' ?></td><td><?= h($p['part_code']) ?></td><td><?= h($p['part_description']) ?></td><td class="ctr"><?= h($p['quantity']) ?></td><td><?= h($p['billing_instructions']) ?></td></tr><?php endforeach; ?>
            </table>

            <table class="t thick"><colgroup><col style="width:100%"></colgroup>
              <tr><td><div class="lbu">Additional Activities</div><div class="v box-add"><?= h($report['additional_activities']) ?></div></td></tr></table>

            <table class="t thick"><colgroup><col style="width:52%"><col style="width:48%"></colgroup>
              <tr><td class="lb">Engineer's Remarks</td><td class="lb">Customer's Remarks</td></tr>
              <tr><td><div class="sig"><div class="v"><?= h($report['engineer_remarks']) ?></div><div><div class="lb">Engineer Signature:</div><div class="signame"><?= h($report['engineer_name']) ?></div></div></div></td>
                  <td><div class="sig"><div class="v"><?= h($report['customer_remarks']) ?></div><div><div class="lb">Customer Signature</div><div class="signame">&nbsp;</div></div></div></td></tr>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
(function () {
  var sheet = document.getElementById('sheet'), paper = document.getElementById('paper'), wrap = document.getElementById('paperWrap');
  var MAX_PX = 240 / 25.4 * 96;       // 246 mm printable height (A4 less 1 in top/bottom) minus a small safety gap

  // Shrink the type slightly if a long report would otherwise spill past one page
  function fit() {
    var fs = 10.5; sheet.style.fontSize = fs + 'pt';
    while (sheet.offsetHeight > MAX_PX && fs > 6) { fs -= 0.25; sheet.style.fontSize = fs + 'pt'; }
  }

  // Scale the A4 preview down on narrow screens (preview only; print/PDF unaffected)
  function scalePreview() {
    var pw = paper.offsetWidth, ph = paper.offsetHeight, avail = document.documentElement.clientWidth - 16;
    var s = Math.min(1, avail / pw);
    paper.style.transform = 'scale(' + s + ')';
    wrap.style.width = (pw * s) + 'px'; wrap.style.height = (ph * s) + 'px';
  }

  function init() { fit(); scalePreview(); }
  window.addEventListener('load', init);
  window.addEventListener('resize', scalePreview);
  init();

  var wm = document.getElementById('wm');
  document.getElementById('wmToggle').addEventListener('change', function () { if (wm) wm.style.display = this.checked ? '' : 'none'; });
  document.getElementById('btnPrint').addEventListener('click', function () { fit(); window.print(); });

  document.getElementById('btnDownload').addEventListener('click', function () {
    var btn = this;
    if (typeof html2pdf === 'undefined') { alert('PDF library could not load. Opening the print dialog — choose "Save as PDF".'); window.print(); return; }
    fit();
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Preparing…';
    html2pdf().set({
      margin: [1, 0.5, 1, 0.5],                       // inches: top, left, bottom, right
      filename: <?= json_encode($fileName) ?>,
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: { scale: 2, useCORS: true, scrollX: 0, scrollY: 0, backgroundColor: '#ffffff' },
      jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' },
      pagebreak: { mode: ['avoid-all'] }
    }).from(sheet).save().catch(function () { alert('Could not create the PDF. Please use Print → Save as PDF.'); })
      .finally(function () { btn.disabled = false; btn.innerHTML = '<i class="fas fa-download"></i> Download PDF'; });
  });
})();
</script>
</body>
</html>