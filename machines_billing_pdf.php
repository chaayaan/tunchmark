<?php
require 'auth.php';
require 'mydb.php';
require_engineering();               // admin + engineer employee only — applies to view, print and download

if (isset($conn)) { $conn->set_charset('utf8mb4'); }
if (!function_exists('h')) { function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

// ---------- Fixed bank details (printed on every bill). Edit here only. ----------
$bank = [
    'Bank Name'      => 'National Bank Ltd.',
    'Branch Name'    => 'Anderkilla Branch, Chattogram.',
    'Account Name'   => 'Raj Aiswari',
    'Account Number' => '1101006047348',
    'Routing number' => '150150468',
];

// ---------- Fixed note (printed on every bill; not stored in the database) ----------
$fixedNote = "Note: Out of these services if any physical service that will be extra charges.\nIf you have any question concerning this Proposal, Please tell us.";

// ---------- Fixed service terms (printed under the bill on every bill) ----------
$fixedServices = [
    'Yearly two physical services',
    'Yearly one Calibration',
    'All time online service',
    'All kind of business support',
];

// ---------- Amount in words (Bangladeshi system: thousand / lakh / crore) ----------
function amb_words(int $n): string {
    static $ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve',
                    'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
    static $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
    if ($n < 20)   return $ones[$n];
    if ($n < 100)  return $tens[intdiv($n, 10)] . ($n % 10 ? '-' . $ones[$n % 10] : '');
    if ($n < 1000) return $ones[intdiv($n, 100)] . ' hundred' . ($n % 100 ? ' ' . amb_words($n % 100) : '');
    foreach ([[10000000, 'crore'], [100000, 'lakh'], [1000, 'thousand']] as [$div, $name]) {
        if ($n >= $div) return amb_words(intdiv($n, $div)) . ' ' . $name . ($n % $div ? ' ' . amb_words($n % $div) : '');
    }
    return '';
}
function amb_in_words($amount): string {
    $parts = explode('.', number_format((float)$amount, 2, '.', ''));
    $taka  = (int)$parts[0];
    $paisa = (int)$parts[1];
    $w = ($taka > 0 ? amb_words($taka) : 'zero') . ' taka';
    if ($paisa > 0) $w .= ' and ' . amb_words($paisa) . ' paisa';
    return ucfirst($w) . ' only.';
}
function amb_money($v): string { return number_format((float)$v, 2); }

// ---------- Load bill ----------
$id = (int)($_GET['id'] ?? 0);
$report = null;
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM machines_billing WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$report) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">'
       . '<div class="container py-5 text-center"><h4>Bill not found</h4><a class="btn btn-primary mt-3" href="machines_billing_list.php">Back to bills</a></div>';
    exit;
}

$stmt = $conn->prepare("SELECT sr_no, service, unit_charge, total_amount FROM machines_billing_items WHERE billing_id = ? ORDER BY sr_no, id");
$stmt->bind_param("i", $id); $stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$minRows = 2;                                   // keep the schedule table looking like the form even when short

$title    = 'ANNUAL MAINTENANCE BILL';
$reportNo = 'AMB-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
$d        = fn($v) => $v ? date('d.m.Y', strtotime($v)) : '';
$fileName = 'Annual-Maintenance-Bill-' . $reportNo . '-' . preg_replace('/[^A-Za-z0-9]+/', '-', trim($report['customer_name'], ' -')) . '.pdf';
$flash = ($_GET['saved'] ?? '') === 'created' ? 'Bill created successfully.' : (($_GET['saved'] ?? '') === 'updated' ? 'Bill updated successfully.' : '');
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
    .frame{position:relative;z-index:1;border:.17em solid #000;overflow:hidden;background:transparent;}
    table.t{width:calc(100% + .14em);margin:-.07em;table-layout:fixed;border-collapse:collapse;}
    table.t td,table.t th{border:.07em solid #000;padding:.22em .45em;vertical-align:top;overflow-wrap:break-word;word-break:normal;hyphens:none;text-align:left;}
    table.t.thick tr:first-child>td,table.t.thick tr:first-child>th{border-top:.14em solid #000;}
    .lb{font-weight:700;white-space:nowrap;}
    .lbu{font-weight:700;text-decoration:underline;white-space:nowrap;}
    .v{font-size:1em;white-space:pre-wrap;}
    .v,ol.acts li,.signame,.box-obs,.box-add,table.sp tr.v td{text-shadow:.006em 0 0 currentColor,-.006em 0 0 currentColor;}  /* slightly heavier weight */
    .title{font-size:1.8em;font-weight:700;text-align:center!important;padding:.1em 0!important;line-height:1.2;}
    .meta{font-size:.85em;font-weight:700;height:1.6em;vertical-align:middle!important;}
    .secthead{text-align:center!important;font-weight:700;}
    .rc>td{height:1.5em;vertical-align:middle!important;}
    .r1{height:2.3em;}.r2{height:1.5em;}
    .box-obs{min-height:2.2em;}.box-act{min-height:4.5em;}.box-add{min-height:2.2em;}
    ol.acts{margin:.2em 0 0;padding-left:2.4em;}ol.acts li{margin:.1em 0;font-size:1em;}
    ol.acts li.hw{border-bottom:.07em dotted #000;margin-right:1.5em;}
    table.sp td,table.sp th{height:1.4em;}
    table.sp th{height:2.6em;}
    .sig{display:flex;flex-direction:column;justify-content:space-between;min-height:5.5em;padding-bottom:1.1em;}
    .signame{margin-top:.3em;font-size:1em;min-height:1.2em;}
    .ctr{text-align:center!important;}
    @media (max-width:991.98px){.page-shell{margin-left:0;padding-top:52px;}.top-bar{top:52px;padding:8px 14px;}.alert-flash{margin:12px 14px 0;}}
    @media print{
      html,body{background:#fff!important;margin:0!important;}
      body *{visibility:hidden!important;}
      #paper,#paper *{visibility:visible!important;}
      #paper{position:absolute!important;left:0;top:0;transform:none!important;margin:0!important;box-shadow:none!important;width:210mm!important;height:296.6mm!important;}
      *{-webkit-print-color-adjust:exact;print-color-adjust:exact;}
    }

    /* ---- Annual maintenance billing (light-grey thin grid) ---- */
    .frame+.frame{margin-top:1.1em;}
    .frame.g{border:.06em solid #8c8c8c;}
    .frame.g table.t td,.frame.g table.t th{border:.06em solid #8c8c8c;}
    #sheet .serif{font-family:'Times New Roman',Times,'Liberation Serif',serif;}
    table.t.bill th.ttl{text-align:center;font-weight:700;font-size:1.1em;padding:.3em!important;}
    table.t.bill tr.hd th{font-weight:700;padding:.3em .45em;vertical-align:middle;}
    table.t.bill tr.it td,table.t.bill tr.sum td{height:1.55em;}
    table.t.bill td.num{text-align:right;white-space:nowrap;padding-right:.6em;}
    table.t.bill td.words{text-align:center;font-weight:700;padding:.3em .45em;}
    table.t.bill td.note{text-align:center;font-size:.85em;padding:.3em .45em;line-height:1.3;}
    table.t.bill td.svc{text-align:left;font-size:.95em;padding:.3em .6em;line-height:1.3;}
    table.t.bill td.svc ol.svc-list{margin:.15em 0 0;padding-left:1.6em;}
    table.t.bill td.svc ol.svc-list li{margin:.05em 0;}
    .engbox{display:flex;flex-direction:column;justify-content:flex-end;min-height:11em;padding:.4em .9em .5em;}
    .engbox .typed{text-align:center;min-height:2.6em;display:flex;align-items:flex-end;justify-content:center;white-space:pre-wrap;padding-bottom:.15em;}
    .engbox .sigline{width:72%;margin:0 auto;border-top:.07em solid #000;text-align:center;padding-top:.2em;}
    table.t table.bank{width:100%;table-layout:auto;border-collapse:collapse;}
    .frame.g table.t table.bank td{border:0;padding:.12em .45em;vertical-align:middle;}
    table.t table.bank td.bk-title{font-size:1.2em;padding-top:.2em;padding-bottom:.15em;}
    table.t table.bank td.bk-l{width:32%;white-space:nowrap;}
  </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page-shell">
  <header class="top-bar">
    <div class="tb-ico"><i class="fas fa-file-pdf"></i></div>
    <div><div class="tb-title"><?= h($title) ?></div><div class="tb-sub"><?= h($reportNo) ?> · <?= h($report['customer_name']) ?></div></div>
    <div class="tb-right">
      <div class="form-check form-switch m-0 me-1"><input class="form-check-input" type="checkbox" id="bgToggle" checked><label class="form-check-label small" for="bgToggle">Letterhead</label></div>
      <div class="form-check form-switch m-0 me-1"><input class="form-check-input" type="checkbox" id="wmToggle" checked><label class="form-check-label small" for="wmToggle">Watermark</label></div>
      <a href="machines_billing_list.php" class="btn-soft"><i class="fas fa-arrow-left"></i><span class="d-none d-sm-inline"> Reports</span></a>
      <a href="machines_billing_form.php?id=<?= $id ?>" class="btn-soft"><i class="fas fa-pen"></i><span class="d-none d-sm-inline"> Edit</span></a>
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
          <div class="frame g">
            <?php /* Bill No / Date line (disabled — remove this comment wrapper to show it again)
            <table class="t"><colgroup><col style="width:52.1%"><col style="width:47.9%"></colgroup>
              <tr><td class="meta">Bill No: <span class="v"><?= h($reportNo) ?></span></td><td class="meta" style="text-align:right!important">Date: <span class="v"><?= h($d($report['billing_date'])) ?></span></td></tr></table>
            */ ?>
            <table class="t"><colgroup><col style="width:52.1%"><col style="width:18.6%"><col style="width:29.3%"></colgroup>
              <tr class="rc"><td class="lb">Customer Name and Address</td><td class="lb">Contact Person</td><td class="v"><?= h($report['contact_person']) ?></td></tr>
              <tr class="rc"><td rowspan="3" class="v" style="vertical-align:top!important"><?= h($report['customer_name']) ?><?= $report['customer_address'] ? "\n" . h($report['customer_address']) : '' ?></td><td class="lb">Desgn./Dept.</td><td class="v"><?= h($report['designation']) ?></td></tr>
              <tr class="rc"><td class="lb">Telephone</td><td class="v"><?= h($report['telephone']) ?></td></tr>
              <tr class="rc"><td class="lb">Email</td><td class="v"><?= h($report['email']) ?></td></tr>
            </table>
          </div>

          <div class="frame g">
            <table class="t bill"><colgroup><col style="width:12%"><col style="width:50%"><col style="width:19%"><col style="width:19%"></colgroup>
              <tr><th colspan="4" class="ttl">ANNUAL MAINTENANCE SCHEDULE</th></tr>
              <tr class="hd"><th class="ctr">SI</th><th class="ctr">MAINTENANCE SERVICE</th><th class="ctr">Unit Charge</th><th class="ctr">Total Amount</th></tr>
              <?php for ($i = 0, $n = max($minRows, count($items)); $i < $n; $i++): $it = $items[$i] ?? null; ?>
              <tr class="it"><td class="ctr"><?= $it ? $i + 1 : '&nbsp;' ?></td>
                <td class="v"><?= $it ? h($it['service']) : '' ?></td>
                <td class="num"><?= $it ? h(amb_money($it['unit_charge'])) : '' ?></td>
                <td class="num"><?= $it ? h(amb_money($it['total_amount'])) : '' ?></td></tr>
              <?php endfor; ?>
              <tr class="sum"><td>&nbsp;</td><td></td><td class="num">Total:</td><td class="num"><?= h(amb_money($report['subtotal'])) ?></td></tr>
              <tr class="sum"><td>&nbsp;</td><td></td><td class="num">Discount:</td><td class="num"><?= h(amb_money($report['discount'])) ?></td></tr>
              <tr class="sum"><td>&nbsp;</td><td></td><td class="num"><b>Grand Total:</b></td><td class="num"><?= h(amb_money($report['grand_total'])) ?></td></tr>
              <tr><td colspan="4" class="words">In Word: <?= h(amb_in_words($report['grand_total'])) ?><?= $report['payment_status'] === 'paid' ? ' &nbsp;&nbsp; Paid' : '' ?></td></tr>
              <tr><td colspan="4" class="svc serif"><b>Services Included:</b>
                <ol class="svc-list"><?php foreach ($fixedServices as $s): ?><li><?= h($s) ?></li><?php endforeach; ?></ol></td></tr>
              <tr><td colspan="4" class="note serif"><?= nl2br(h($fixedNote)) ?></td></tr>
            </table>
          </div>

          <div class="frame g">
            <table class="t"><colgroup><col style="width:41.6%"><col style="width:58.4%"></colgroup>
              <tr>
                <td class="np"><div class="engbox"><div class="typed v"><?= h($report['engineer_sign_text']) ?></div><div class="sigline serif">Engineer Sign</div></div></td>
                <td class="np"><table class="bank serif">
                  <tr><td colspan="2" class="bk-title"><b>Bank Details :</b></td></tr>
                  <?php foreach ($bank as $k => $v): ?>
                  <tr><td class="bk-l"><?= h($k) ?></td><td class="bk-v">:<?= $v !== '' ? ' ' . h($v) : '' ?></td></tr>
                  <?php endforeach; ?>
                </table></td>
              </tr>
            </table>
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

  // Shrink the type slightly if a long report would otherwise spill past one page
  function fit() {
    var fs = 12; sheet.style.fontSize = fs + 'pt';
    while (sheet.offsetHeight > MAX_PX && fs > 6) { fs -= 0.25; sheet.style.fontSize = fs + 'pt'; }
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