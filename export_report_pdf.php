<?php
/**
 * export_report_pdf.php  (single file)
 * Black & white portrait (vertical) billing report PDF - ONE ROW PER BILL - generated in the browser with jsPDF.
 * Opens in the same tab inside the normal page layout (navbar included) with an inline preview.
 * Uses the same filters as reports.php / export_report_csv.php.
 *
 * Link to it with:  export_report_pdf.php?<?= http_build_query($_GET) ?>   (no target="_blank")
 */
ob_start();
require 'auth.php';
if (!in_array($_SESSION['role'], ['admin', 'employee'])) {
    header("Location: dashboard.php");
    exit;
}

include 'mydb.php';

/* ───────── Filters (identical to the CSV export) ───────── */
$where = []; $params = []; $types = "";

if (!empty($_GET['order_id'])) {
    $where[] = "o.order_id = ?";
    $params[] = intval($_GET['order_id']);
    $types .= "i";
}
if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $where[] = "DATE(o.created_at) BETWEEN ? AND ?";
    $params[] = $_GET['from_date'];
    $params[] = $_GET['to_date'];
    $types .= "ss";
} elseif (!empty($_GET['year'])) {
    $where[] = "YEAR(o.created_at) = ?";
    $params[] = intval($_GET['year']);
    $types .= "i";
}
$serviceFilter = !empty($_GET['service']) ? intval($_GET['service']) : 0;
if ($serviceFilter > 0) {
    $where[] = "bi.service_id = ?";
    $params[] = $serviceFilter;
    $types .= "i";
}
$whereClause = $where ? " WHERE " . implode(" AND ", $where) : "";

/* ───────── Bills: one entry per bill ───────── */
$sql = "SELECT o.order_id, o.customer_name, o.customer_phone, o.status, o.created_at,
               i.name AS item_name, s.name AS service_name,
               bi.quantity, bi.unit_price, bi.total_price
        FROM orders o
        JOIN bill_items bi ON o.order_id = bi.order_id
        LEFT JOIN items i ON bi.item_id = i.id
        LEFT JOIN services s ON bi.service_id = s.id"
        . $whereClause . " ORDER BY o.order_id DESC";
$stmt = mysqli_prepare($conn, $sql);
if ($params) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$bills = [];
while ($r = mysqli_fetch_assoc($res)) {
    $id = $r['order_id'];
    if (!isset($bills[$id])) {
        $bills[$id] = [
            'id'     => (int)$id,
            'name'   => (string)$r['customer_name'],
            'phone'  => (string)$r['customer_phone'],
            'status' => (string)$r['status'],
            'date'   => date('d M Y', strtotime($r['created_at'])),
            'total'  => 0,
            'items'  => []
        ];
    }
    $line = floatval($r['total_price'] ?? 0);
    $bills[$id]['total'] += $line;
    $bills[$id]['items'][] = [
        'item'    => $r['item_name'] ?: '-',
        'service' => $r['service_name'] ?: '-',
        'qty'     => floatval($r['quantity']),
        'unit'    => floatval($r['unit_price']),
        'total'   => $line
    ];
}
mysqli_stmt_close($stmt);

/* ───────── Summary ───────── */
$counts  = ['paid' => 0, 'pending' => 0, 'cancelled' => 0];
$amounts = ['paid' => 0, 'pending' => 0, 'cancelled' => 0];
$grand = 0;
foreach ($bills as $b) {
    $grand += $b['total'];
    if (isset($counts[$b['status']])) { $counts[$b['status']]++; $amounts[$b['status']] += $b['total']; }
}

/* ───────── Filter text + filename ───────── */
$parts = [];
if (!empty($_GET['order_id'])) $parts[] = 'Bill #' . intval($_GET['order_id']);
if (!empty($_GET['from_date']) && !empty($_GET['to_date']))
    $parts[] = date('d M Y', strtotime($_GET['from_date'])) . ' to ' . date('d M Y', strtotime($_GET['to_date']));
elseif (!empty($_GET['year'])) $parts[] = 'Year ' . intval($_GET['year']);
if ($serviceFilter > 0) {
    $q = mysqli_prepare($conn, "SELECT name FROM services WHERE id = ?");
    mysqli_stmt_bind_param($q, "i", $serviceFilter);
    mysqli_stmt_execute($q);
    $sn = mysqli_fetch_assoc(mysqli_stmt_get_result($q))['name'] ?? '';
    mysqli_stmt_close($q);
    if ($sn) $parts[] = 'Service: ' . $sn;
}
$filterText = $parts ? implode('  |  ', $parts) : 'All records';

$filename = 'billing_report_';
if (!empty($_GET['from_date']) && !empty($_GET['to_date']))
    $filename .= preg_replace('/[^0-9\-]/', '', $_GET['from_date']) . '_to_' . preg_replace('/[^0-9\-]/', '', $_GET['to_date']);
elseif (!empty($_GET['year'])) $filename .= 'year_' . intval($_GET['year']);
else $filename .= date('Y-m-d');
$filename .= '.pdf';

$payload = [
    'filter_text' => $filterText,
    'filename'    => $filename,
    'generated'   => date('d M Y, h:i A'),
    'bills'       => array_values($bills),
    'summary'     => ['counts' => $counts, 'amounts' => $amounts, 'grand' => $grand, 'total_bills' => count($bills)],
];
mysqli_close($conn);

$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

/* Link back to reports with the same filters */
$backParams = $_GET; unset($backParams['page']);
$backUrl = 'reports.php' . ($backParams ? '?' . http_build_query($backParams) : '');

ob_end_clean();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PDF Report</title>
  <link rel="icon" type="image/png" href="favicon.png">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg:#f1f3f6; --surface:#ffffff; --surface-2:#fafbfc; --border:#e4e7ec; --bsoft:#f0f1f3;
      --t1:#111827; --t2:#374151; --t3:#6b7280; --t4:#9ca3af;
      --blue:#2563eb; --blue-bg:#eff6ff; --blue-b:#bfdbfe;
      --green:#059669; --green-bg:#ecfdf5; --green-b:#a7f3d0;
      --red:#dc2626;
      --r:10px; --rs:6px;
      --sh:0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
    }
    *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'DM Sans',-apple-system,sans-serif; font-size:14.5px; background:var(--bg); color:var(--t1);
           -webkit-font-smoothing:antialiased; min-height:100vh; }

    .page-shell { margin-left:200px; min-height:100vh; display:flex; flex-direction:column; }

    .top-bar { position:sticky; top:0; z-index:200; height:54px; background:var(--surface);
               border-bottom:1px solid var(--border); box-shadow:var(--sh);
               display:flex; align-items:center; padding:0 22px; gap:10px; flex-shrink:0; }
    .tb-ico { width:32px; height:32px; background:var(--blue-bg); border-radius:8px; display:flex;
              align-items:center; justify-content:center; color:var(--blue); font-size:13px; flex-shrink:0; }
    .tb-title { font-size:1.0625rem; font-weight:700; color:var(--t1); line-height:1.2; }
    .tb-sub { font-size:.8rem; color:var(--t4); }
    .tb-right { margin-left:auto; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .tb-btn { display:inline-flex; align-items:center; gap:6px; height:32px; padding:0 14px; border-radius:var(--rs);
              font-family:'DM Sans',sans-serif; font-size:.82rem; font-weight:600; text-decoration:none; cursor:pointer;
              transition:all .15s; border:1.5px solid transparent; white-space:nowrap; }
    .tb-btn-ghost { background:var(--surface-2); border-color:var(--border); color:var(--t2); }
    .tb-btn-ghost:hover { background:var(--border); color:var(--t1); }
    .tb-btn-green { background:var(--green-bg); border-color:var(--green-b); color:var(--green); }
    .tb-btn-green:hover { background:#d1fae5; color:var(--green); }
    .tb-btn[disabled] { opacity:.5; pointer-events:none; }
    .pn-toggle { display:inline-flex; align-items:center; gap:8px; height:32px; padding:0 12px; border-radius:var(--rs);
                 background:var(--surface-2); border:1.5px solid var(--border); font-size:.82rem; font-weight:600;
                 color:var(--t2); cursor:pointer; user-select:none; white-space:nowrap; }
    .pn-toggle input { display:none; }
    .pn-switch { position:relative; width:30px; height:17px; border-radius:20px; background:#cbd0d8; transition:background .15s; flex-shrink:0; }
    .pn-switch::after { content:''; position:absolute; top:2px; left:2px; width:13px; height:13px; border-radius:50%;
                        background:#fff; transition:left .15s; box-shadow:0 1px 2px rgba(0,0,0,.3); }
    .pn-toggle input:checked + .pn-switch { background:var(--green); }
    .pn-toggle input:checked + .pn-switch::after { left:15px; }
    .pn-toggle.disabled { opacity:.5; pointer-events:none; }

    .main { flex:1; padding:20px 22px 30px; display:flex; flex-direction:column; gap:14px; }
    .sec { background:var(--surface); border:1px solid var(--border); border-radius:var(--r); box-shadow:var(--sh); overflow:hidden; }

    .status-box { padding:48px 24px; text-align:center; }
    .spin { width:34px; height:34px; border:4px solid var(--border); border-top-color:var(--blue); border-radius:50%;
            margin:0 auto 14px; animation:r .8s linear infinite; }
    @keyframes r { to { transform:rotate(360deg); } }
    .status-title { font-size:.9375rem; font-weight:700; color:var(--t2); margin-bottom:4px; }
    .status-msg { font-size:.82rem; color:var(--t4); }
    .err { color:var(--red); }

    #pv { width:100%; height:calc(100vh - 54px - 52px); border:0; display:block; background:#525659; }

    @media (max-width: 991.98px) {
      .page-shell { margin-left:0; }
      .top-bar { top:52px; }
      .main { padding:14px 14px 30px; }
      #pv { height:70vh; }
    }
  </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="page-shell">

  <header class="top-bar">
    <div class="tb-ico"><i class="fas fa-file-pdf"></i></div>
    <div>
      <div class="tb-title">PDF Report</div>
      <div class="tb-sub"><?= htmlspecialchars($filterText) ?></div>
    </div>
    <div class="tb-right">
      <label class="pn-toggle disabled" id="pnLabel" title="Show page numbers in the PDF">
        <input type="checkbox" id="pnToggle">
        <span class="pn-switch"></span>
        Page No
      </label>
      <a href="#" id="btnDownload" class="tb-btn tb-btn-green" disabled>
        <i class="fas fa-download" style="font-size:.7rem;"></i> Download
      </a>
      <a href="#" id="btnPrint" class="tb-btn tb-btn-ghost" disabled>
        <i class="fas fa-print" style="font-size:.7rem;"></i> Print
      </a>
      <a href="<?= htmlspecialchars($backUrl) ?>" class="tb-btn tb-btn-ghost">
        <i class="fas fa-arrow-left" style="font-size:.65rem;"></i> Back to Reports
      </a>
    </div>
  </header>

  <div class="main">
    <div class="sec" id="statusBox">
      <div class="status-box">
        <div class="spin" id="spin"></div>
        <div class="status-title" id="stTitle">Generating PDF…</div>
        <div class="status-msg" id="stMsg">Please wait a moment.</div>
      </div>
    </div>
    <div class="sec" id="previewWrap" style="display:none;">
      <iframe id="pv" title="PDF preview"></iframe>
    </div>
  </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script>
(function () {
  var DATA  = <?= $json ?>;
  var TITLE = 'Rajaiswari Gold Testing Centre';
  var BLACK = [0, 0, 0];
  var SHOW_PAGE_NO = false;   // default: page numbers off

  function money(n) { return Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function qty(n)   { return String(Number(Number(n || 0).toFixed(2))); }
  // jsPDF built-in fonts support Latin characters only
  function clean(s) { return String(s == null ? '' : s).replace(/[^\x20-\x7E -ÿ]/g, '?'); }

  function build() {
    var JsPDF = window.jspdf.jsPDF;
    var doc = new JsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    var pageW = doc.internal.pageSize.getWidth();
    var pageH = doc.internal.pageSize.getHeight();
    var M = 10;

    function drawHeader() {
      doc.setTextColor(0);
      doc.setFont('helvetica', 'bold');
      doc.setFontSize(18);
      doc.text(TITLE, pageW / 2, 14, { align: 'center' });
      doc.setFontSize(11);
      doc.text('BILLING REPORT', pageW / 2, 20, { align: 'center' });
      doc.setFont('helvetica', 'normal');
      doc.setFontSize(9);
      doc.setTextColor(60);
      doc.text('Period / Filter: ' + clean(DATA.filter_text), pageW / 2, 25.5, { align: 'center' });
      doc.setDrawColor(0);
      doc.setLineWidth(0.5);
      doc.line(M, 28, pageW - M, 28);
    }

    if (!DATA.bills.length) {
      drawHeader();
      doc.setFont('helvetica', 'italic');
      doc.setFontSize(11);
      doc.setTextColor(60);
      doc.text('No bills found for the selected filter.', pageW / 2, 50, { align: 'center' });
    } else {
      var body = DATA.bills.map(function (b) {
        var items = b.items.map(function (it) {
          return clean(it.item) + ' - ' + clean(it.service) +
            '  (' + qty(it.qty) + ' x ' + money(it.unit) + ' = ' + money(it.total) + ')';
        }).join('\n');
        return [
          '#' + b.id,
          b.date,
          clean(b.name) + (b.phone ? '\n' + clean(b.phone) : ''),
          String(b.status || '').toUpperCase(),
          items || 'No items',
          money(b.total)
        ];
      });

      doc.autoTable({
        startY: 31,
        margin: { top: 31, left: M, right: M, bottom: 16 },
        head: [['Bill No', 'Date', 'Customer', 'Status', 'Items & Services', 'Total (Tk)']],
        body: body,
        foot: [[
          { content: 'GRAND TOTAL  (' + DATA.summary.total_bills + ' bills)', colSpan: 5, styles: { halign: 'right' } },
          money(DATA.summary.grand)
        ]],
        showFoot: 'lastPage',
        theme: 'grid',
        styles: { font: 'helvetica', fontSize: 7.5, cellPadding: 1.5, lineColor: BLACK, lineWidth: 0.2, textColor: BLACK, valign: 'top' },
        headStyles: { fillColor: [225, 225, 225], textColor: BLACK, fontStyle: 'bold', halign: 'center', valign: 'middle' },
        footStyles: { fillColor: [225, 225, 225], textColor: BLACK, fontStyle: 'bold', fontSize: 9 },
        alternateRowStyles: { fillColor: [255, 255, 255] },
        columnStyles: {
          0: { cellWidth: 15, halign: 'center', fontStyle: 'bold' },
          1: { cellWidth: 21, halign: 'center' },
          2: { cellWidth: 36 },
          3: { cellWidth: 20, halign: 'center', fontStyle: 'bold' },
          4: { cellWidth: 'auto' },
          5: { cellWidth: 26, halign: 'right', fontStyle: 'bold' }
        },
        rowPageBreak: 'avoid',
        didDrawPage: drawHeader
      });

      /* Summary by status */
      var sm = DATA.summary;
      var y = doc.lastAutoTable.finalY + 8;
      if (y + 40 > pageH - 16) { doc.addPage(); drawHeader(); y = 36; }
      doc.setFont('helvetica', 'bold');
      doc.setFontSize(10);
      doc.setTextColor(0);
      doc.text('SUMMARY BY STATUS', M, y);
      doc.autoTable({
        startY: y + 2,
        margin: { left: M, right: M, bottom: 16 },
        tableWidth: 130,
        head: [['Status', 'Bills', 'Amount (Tk)']],
        body: [
          ['Paid',      sm.counts.paid,      money(sm.amounts.paid)],
          ['Pending',   sm.counts.pending,   money(sm.amounts.pending)],
          ['Cancelled', sm.counts.cancelled, money(sm.amounts.cancelled)]
        ],
        foot: [['Total', sm.total_bills, money(sm.grand)]],
        showFoot: 'lastPage',
        theme: 'grid',
        styles: { fontSize: 9, cellPadding: 1.8, lineColor: BLACK, lineWidth: 0.2, textColor: BLACK },
        headStyles: { fillColor: [225, 225, 225], textColor: BLACK, fontStyle: 'bold' },
        footStyles: { fillColor: [225, 225, 225], textColor: BLACK, fontStyle: 'bold' },
        columnStyles: { 0: { cellWidth: 50 }, 1: { cellWidth: 30, halign: 'center' }, 2: { cellWidth: 50, halign: 'right' } }
      });

      /* Signatures */
      var sy = doc.lastAutoTable.finalY + 22;
      if (sy > pageH - 20) { doc.addPage(); drawHeader(); sy = 60; }
      doc.setDrawColor(0);
      doc.setLineWidth(0.3);
      doc.line(M, sy, M + 60, sy);
      doc.line(pageW - M - 60, sy, pageW - M, sy);
      doc.setFont('helvetica', 'normal');
      doc.setFontSize(9);
      doc.setTextColor(0);
      doc.text('Prepared By', M + 30, sy + 5, { align: 'center' });
      doc.text('Authorized Signature', pageW - M - 30, sy + 5, { align: 'center' });
    }

    /* Footer on every page */
    var total = doc.getNumberOfPages();
    for (var i = 1; i <= total; i++) {
      doc.setPage(i);
      doc.setDrawColor(0);
      doc.setLineWidth(0.2);
      doc.line(M, pageH - 11, pageW - M, pageH - 11);
      doc.setFont('helvetica', 'normal');
      doc.setFontSize(8);
      doc.setTextColor(60);
      doc.text('Generated on ' + DATA.generated, M, pageH - 6);
      if (SHOW_PAGE_NO) doc.text('Page ' + i + ' of ' + total, pageW - M, pageH - 6, { align: 'right' });
    }
    return doc;
  }

  var pdfDoc = null, blobUrl = null;
  var btnD = document.getElementById('btnDownload');
  var btnP = document.getElementById('btnPrint');

  function fail(msg) {
    document.getElementById('spin').style.display = 'none';
    document.getElementById('stTitle').textContent = 'Could not generate PDF';
    var m = document.getElementById('stMsg');
    m.textContent = msg;
    m.className = 'status-msg err';
  }

  function run() {
    try {
      if (!window.jspdf || !window.jspdf.jsPDF) throw new Error('jsPDF failed to load. Check your internet connection.');
      if (blobUrl) URL.revokeObjectURL(blobUrl);
      pdfDoc  = build();
      blobUrl = URL.createObjectURL(pdfDoc.output('blob'));

      document.getElementById('statusBox').style.display = 'none';
      document.getElementById('previewWrap').style.display = 'block';
      document.getElementById('pv').src = blobUrl;
      btnD.removeAttribute('disabled');
      btnP.removeAttribute('disabled');
      document.getElementById('pnLabel').classList.remove('disabled');

    } catch (e) {
      fail(e.message);
    }
  }

  btnD.addEventListener('click', function (e) {
    e.preventDefault();
    if (pdfDoc) pdfDoc.save(DATA.filename);
  });
  btnP.addEventListener('click', function (e) {
    e.preventDefault();
    if (!blobUrl) return;
    try {
      var f = document.getElementById('pv');
      f.contentWindow.focus();
      f.contentWindow.print();
    } catch (err) {
      window.location.href = blobUrl;   // fallback: open the PDF in this tab and print from the viewer
    }
  });

  document.getElementById('pnToggle').addEventListener('change', function () {
    SHOW_PAGE_NO = this.checked;
    run();   // rebuild the preview with/without page numbers
  });

  window.addEventListener('load', run);
})();
</script>
</body>
</html>