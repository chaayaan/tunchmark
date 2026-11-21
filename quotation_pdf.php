<?php
require 'auth.php';
require 'mydb.php';
require_engineering();               // admin + engineer employee only — applies to view, print and download

if (isset($conn)) { $conn->set_charset('utf8mb4'); }
if (!function_exists('h')) { function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

// ---------- Fixed notes (printed on every quotation; not stored in the database) ----------
$noteQuestion = "If you have any question concerning this quotation, Please tell us. 01716469866";
$noteDollar   = "Note: Depending on the Dollar rate, the quotation value will be increase or decrease.";

function qt_money($v): string { return number_format((float)$v, 2); }

// ---------- Load quotation ----------
$id = (int)($_GET['id'] ?? 0);
$report = null;
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM quotations WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$report) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">'
       . '<div class="container py-5 text-center"><h4>Quotation not found</h4><a class="btn btn-primary mt-3" href="quotation_list.php">Back to quotations</a></div>';
    exit;
}

$stmt = $conn->prepare("SELECT cat_no, category, item, quantity, unit_price, line_total FROM quotation_items WHERE quotation_id = ? ORDER BY cat_no, id");
$stmt->bind_param("i", $id); $stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// group rows by category (order preserved)
$groups = [];
foreach ($items as $it) $groups[$it['cat_no'] . '|' . $it['category']][] = $it;

$title    = 'QUOTATION';
$quoNo    = $report['quotation_no'];
$d        = fn($v) => $v ? date('d/m/Y', strtotime($v)) : '';
$fileName = 'Quotation-' . $quoNo . '-' . preg_replace('/[^A-Za-z0-9]+/', '-', trim($report['customer_name'], ' -')) . '.pdf';
$flash    = ($_GET['saved'] ?? '') === 'created' ? 'Quotation created successfully.' : (($_GET['saved'] ?? '') === 'updated' ? 'Quotation updated successfully.' : '');
$validity = $report['validity_days'] ? (int)$report['validity_days'] : 0;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title . ' ' . $quoNo) ?></title>
  <link rel="icon" type="image/png" href="favicon.png">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    @page { size: A4 portrait; margin: 0; }            /* margins are applied inside the sheet: 1 inch top & bottom */
    :root{--bg:#f1f3f6;--surface:#fff;--surface-2:#fafbfc;--border:#e4e7ec;--t2:#374151;--t4:#9ca3af;--teal:#0f766e;--teal-bg:#f0fdfa;--green:#059669;--green-bg:#ecfdf5;--green-b:#a7f3d0;--rs:6px;--sh:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);}
    body{font-family:'DM Sans',-apple-system,sans-serif;font-size:14.5px;background:var(--bg);color:#111827;-webkit-font-smoothing:antialiased;min-height:100vh;}
    .page-shell{margin-left:200px;min-height:100vh;display:flex;flex-direction:column;}
    .top-bar{position:sticky;top:0;z-index:200;min-height:54px;background:var(--surface);border-bottom:1px solid var(--border);box-shadow:var(--sh);display:flex;align-items:center;flex-wrap:wrap;padding:6px 22px;gap:10px;}
    .tb-ico{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;background:var(--teal-bg);color:var(--teal);}
    .tb-title{font-size:1.0625rem;font-weight:700;line-height:1.2;}.tb-sub{font-size:.8rem;color:var(--t4);}
    .tb-right{margin-left:auto;display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
    .btn-soft{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 14px;background:var(--surface-2);border:1.5px solid var(--border);border-radius:var(--rs);font-size:.82rem;font-weight:600;color:var(--t2);text-decoration:none;cursor:pointer;}
    .btn-soft:hover{background:var(--border);color:#111827;}
    .btn-dl{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 16px;background:#dc2626;border:none;border-radius:var(--rs);font-size:.85rem;font-weight:700;color:#fff;}
    .btn-dl:hover{background:#b91c1c;color:#fff;}.btn-dl:disabled{opacity:.7;}
    .alert-flash{margin:14px 22px 0;border-radius:var(--rs);padding:11px 16px;font-size:.875rem;display:flex;align-items:center;gap:9px;background:var(--green-bg);border:1px solid var(--green-b);border-left:3px solid var(--green);color:#065f46;}
    .stage{flex:1;padding:18px 12px 40px;overflow-x:hidden;}
    .paper-wrap{margin:0 auto;}
    /* A4 sheet with the company letterhead as background; 1 in top/bottom, 0.5 in sides */
    .paper{width:210mm;height:296.6mm;padding:1in 0.5in;background:#fffee6 url('machines_report_letterhead.png') 0 0/100% 100% no-repeat;box-shadow:0 2px 14px rgba(0,0,0,.2);transform-origin:top left;overflow:hidden;box-sizing:border-box;}
    .paper.nobg{background:#fff;}
    #sheet{position:relative;width:184mm;font-family:Arial,'Liberation Sans',Helvetica,sans-serif;font-size:12pt;line-height:1.25;color:#000;}
    #sheet *{font-family:inherit;}
    .wm{position:absolute;left:50%;top:50%;width:62%;transform:translate(-50%,-50%);opacity:.07;z-index:0;pointer-events:none;}
    .frame{position:relative;z-index:1;overflow:hidden;background:transparent;}
    .frame+.frame{margin-top:1.1em;}
    .frame.g{border:.06em solid #8c8c8c;}
    table.t{width:calc(100% + .14em);margin:-.07em;table-layout:fixed;border-collapse:collapse;}
    table.t td,table.t th{border:.06em solid #8c8c8c;padding:.22em .45em;vertical-align:top;overflow-wrap:break-word;word-break:normal;hyphens:none;text-align:left;}
    .lb{font-weight:700;white-space:nowrap;}
    .v{font-size:1em;white-space:pre-wrap;}
    .v,.lb{text-shadow:.006em 0 0 currentColor,-.006em 0 0 currentColor;}   /* slightly heavier weight */
    .rc>td{height:1.5em;vertical-align:middle!important;}
    .ctr{text-align:center!important;}
    #sheet .serif{font-family:'Times New Roman',Times,'Liberation Serif',serif;}
    /* quotation items table */
    table.t.qt th.ttl{text-align:center;font-weight:700;font-size:1.1em;padding:.3em!important;}
    table.t.qt tr.hd th{font-weight:700;padding:.3em .45em;vertical-align:middle;text-align:center;}
    table.t.qt tr.cat td{font-weight:700;padding:.2em .45em;vertical-align:middle;}                 /* category: bold only */
    table.t.qt tr.it td{height:1.4em;padding-top:.12em;padding-bottom:.12em;font-weight:400;}      /* items: normal weight */
    table.t.qt tr.it td.v{text-shadow:none;font-weight:400;}
    /* extra-tight spacing, used only when a long quotation still does not fit at 10pt */
    #sheet.tight{line-height:1.12;}
    #sheet.tight table.t td,#sheet.tight table.t th{padding-top:.07em;padding-bottom:.07em;}
    #sheet.tight table.t.qt tr.it td{height:auto;}
    #sheet.tight .frame+.frame{margin-top:.6em;}
    #sheet.tight .notes{margin-top:.6em;}
    table.t.qt td.num{text-align:right;white-space:nowrap;padding-right:.6em;}
    table.t.qt tr.gt td{font-weight:700;height:1.7em;vertical-align:middle;}
    table.t.qt td.note{text-align:center;font-size:.9em;padding:.35em .45em;line-height:1.45;}
    /* formal note below the table */
    .notes{position:relative;z-index:1;margin-top:1em;font-size:.95em;line-height:1.45;text-align:left;}
    .notes .notes-title{font-weight:700;text-decoration:underline;margin-bottom:.2em;}
    .notes ol{margin:0;padding-left:1.7em;}
    .notes li{margin:.1em 0;padding-left:.2em;}
    .notes .blank{display:inline-block;min-width:3.2em;border-bottom:.07em solid #000;text-align:center;font-weight:700;}
    @media (max-width:991.98px){.page-shell{margin-left:0;padding-top:52px;}.top-bar{top:52px;padding:8px 14px;}.alert-flash{margin:12px 14px 0;}}
    @media print{
      html,body{background:#fff!important;margin:0!important;}
      body *{visibility:hidden!important;}
      #paper,#paper *{visibility:visible!important;}
      #paper{position:absolute!important;left:0;top:0;transform:none!important;margin:0!important;box-shadow:none!important;width:210mm!important;height:296.6mm!important;}
      *{-webkit-print-color-adjust:exact;print-color-adjust:exact;}
    }
  </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page-shell">
  <header class="top-bar">
    <div class="tb-ico"><i class="fas fa-file-pdf"></i></div>
    <div><div class="tb-title"><?= h($title) ?></div><div class="tb-sub"><?= h($quoNo) ?> · <?= h($report['customer_name']) ?></div></div>
    <div class="tb-right">
      <div class="form-check form-switch m-0 me-1"><input class="form-check-input" type="checkbox" id="bgToggle" checked><label class="form-check-label small" for="bgToggle">Letterhead</label></div>
      <div class="form-check form-switch m-0 me-1"><input class="form-check-input" type="checkbox" id="wmToggle" checked><label class="form-check-label small" for="wmToggle">Watermark</label></div>
      <a href="quotation_list.php" class="btn-soft"><i class="fas fa-arrow-left"></i><span class="d-none d-sm-inline"> Quotations</span></a>
      <a href="quotation_form.php?id=<?= $id ?>" class="btn-soft"><i class="fas fa-pen"></i><span class="d-none d-sm-inline"> Edit</span></a>
      <button type="button" class="btn-soft" id="btnPrint"><i class="fas fa-print"></i><span class="d-none d-sm-inline"> Print</span></button>
      <button type="button" class="btn-dl" id="btnDownload"><i class="fas fa-download"></i> Download PDF</button>
    </div>
  </header>
  <?php if ($flash): ?><div class="alert-flash"><i class="fas fa-circle-check"></i><?= h($flash) ?></div><?php endif; ?>

  <div class="stage" id="stage">
    <div class="paper-wrap" id="paperWrap">
      <div class="paper" id="paper">
        <div id="sheet">
          <img class="wm" id="wm" src="rajaiswari-wotbg.png" alt="" onerror="this.remove()">

          <!-- Table 1: date, quotation no, customer details -->
          <div class="frame g">
            <table class="t"><colgroup><col style="width:52.1%"><col style="width:47.9%"></colgroup>
              <tr class="rc"><td><span class="lb">Date:</span> <span class="v"><?= h($d($report['quotation_date'])) ?></span></td>
                              <td><span class="lb">Quotation No:</span> <span class="v"><?= h($quoNo) ?></span></td></tr>
              <tr class="rc"><td rowspan="3" style="vertical-align:top!important"><div class="lb">Customer Name and Address</div><div class="v"><?= h($report['customer_name']) ?><?= $report['customer_address'] ? "\n" . h($report['customer_address']) : '' ?></div></td>
                              <td><span class="lb">Designation:</span> <span class="v"><?= h($report['designation']) ?></span></td></tr>
              <tr class="rc"><td><span class="lb">Phone:</span> <span class="v"><?= h($report['telephone']) ?></span></td></tr>
              <tr class="rc"><td><span class="lb">Email:</span> <span class="v"><?= h($report['email']) ?></span></td></tr>
            </table>
          </div>

          <!-- Table 2: items, category wise -->
          <div class="frame g">
            <table class="t qt"><colgroup><col style="width:8%"><col style="width:50%"><col style="width:9%"><col style="width:16.5%"><col style="width:16.5%"></colgroup>
              <tr><th colspan="5" class="ttl"><?= h($title) ?></th></tr>
              <tr class="hd"><th>SI</th><th>Product Description</th><th>Qty</th><th>Unit Price</th><th>Amount</th></tr>
              <?php $n = 0; foreach ($groups as $rows): $n++; ?>
              <tr class="cat"><td class="ctr"><?= $n ?></td><td colspan="4"><?= h($rows[0]['category']) ?></td></tr>
                <?php foreach ($rows as $it): ?>
                <tr class="it"><td>&nbsp;</td>
                  <td class="v"><?= h($it['item']) ?></td>
                  <td class="ctr"><?= $it['quantity'] !== null ? (int)$it['quantity'] : '' ?></td>
                  <td class="num"><?= $it['unit_price'] !== null ? h(qt_money($it['unit_price'])) : '' ?></td>
                  <td class="num"><?= $it['line_total'] !== null ? h(qt_money($it['line_total'])) : '' ?></td></tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
              <tr class="gt"><td colspan="4" class="num">Grand Total:</td><td class="num"><?= h(qt_money($report['grand_total'])) ?></td></tr>
            </table>
          </div>

          <!-- Formal note, outside the table -->
          <div class="notes serif">
            <div class="notes-title">Note:</div>
            <ol>
              <li><?= h(preg_replace('/^Note:\s*/i', '', $noteDollar)) ?></li>
              <li>Quotation validity period <span class="blank"><?= $validity ? $validity : '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' ?></span> days.</li>
              <li><?= h($noteQuestion) ?></li>
            </ol>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
(function () {
  var sheet = document.getElementById('sheet'), paper = document.getElementById('paper'),
      wrap = document.getElementById('paperWrap'), stage = document.getElementById('stage');
  var MAX_PX = 240 / 25.4 * 96;       // printable height between the 1 in top/bottom margins, minus a small safety gap

  // Shrink the type slightly if a long quotation would otherwise spill past one page
  // Font stays between 12pt and 10pt: shrink 12 -> 10 first, then tighten row spacing,
  // and only as a last resort go below 10pt so nothing is ever cut off.
  function fit() {
    sheet.classList.remove('tight');
    var fs = 12; sheet.style.fontSize = fs + 'pt';
    function over() { return sheet.offsetHeight > MAX_PX; }
    while (over() && fs > 10) { fs -= 0.25; sheet.style.fontSize = fs + 'pt'; }
    if (over()) sheet.classList.add('tight');
    while (over() && fs > 5.5) { fs -= 0.25; sheet.style.fontSize = fs + 'pt'; }
  }
  // Scale the A4 preview down on narrow screens (preview only)
  function scalePreview() {
    paper.style.transform = 'none';
    var pw = paper.offsetWidth, ph = paper.offsetHeight, avail = stage.clientWidth - 24;
    var s = Math.min(1, avail / pw);
    paper.style.transform = 'scale(' + s + ')';
    wrap.style.width = (pw * s) + 'px'; wrap.style.height = (ph * s) + 'px';
  }
  function init() { fit(); scalePreview(); }
  window.addEventListener('load', init);
  window.addEventListener('resize', scalePreview);
  init();

  document.getElementById('bgToggle').addEventListener('change', function () { paper.classList.toggle('nobg', !this.checked); });
  var wm = document.getElementById('wm');
  document.getElementById('wmToggle').addEventListener('change', function () { if (wm) wm.style.display = this.checked ? '' : 'none'; });
  document.getElementById('btnPrint').addEventListener('click', function () { fit(); window.print(); });

  document.getElementById('btnDownload').addEventListener('click', function () {
    var btn = this;
    if (typeof html2pdf === 'undefined') { alert('PDF library could not load. Opening the print dialog — choose "Save as PDF".'); window.print(); return; }
    fit();
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Preparing…';
    var prev = paper.style.transform; paper.style.transform = 'none';        // capture at true size
    html2pdf().set({
      margin: 0,                                       // 1 in top/bottom is already built into the sheet
      filename: <?= json_encode($fileName) ?>,
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: { scale: 2, useCORS: true, scrollX: 0, scrollY: 0, backgroundColor: '#ffffff' },
      jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
      pagebreak: { mode: ['avoid-all'] }
    }).from(paper).save().catch(function () { alert('Could not create the PDF. Please use Print → Save as PDF.'); })
      .finally(function () { paper.style.transform = prev; btn.disabled = false; btn.innerHTML = '<i class="fas fa-download"></i> Download PDF'; });
  });
})();
</script>
</body>
</html>