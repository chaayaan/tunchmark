<?php
require_once 'mydb.php';
require_once 'auth.php';

$sale_id = (int)($_GET['id'] ?? 0);
if (!$sale_id) { echo "Invalid invoice."; exit; }

// Fetch sale header
$hdr = $conn->query("
    SELECT so.id, so.sale_date, so.remarks,
           b.name AS buyer_name, b.company_name AS buyer_company,
           b.phone AS buyer_phone, b.email AS buyer_email, b.address AS buyer_address
    FROM stock_out so
    JOIN buyers b ON b.id = so.buyer_id
    WHERE so.id = $sale_id
")->fetch_assoc();

if (!$hdr) { echo "Invoice not found."; exit; }

// Fetch all items in this sale
$items_res = $conn->query("
    SELECT pi.serial_no, pi.part_no,
           p.product_name, p.description AS product_desc
    FROM product_items pi
    JOIN products p ON p.id = pi.product_id
    WHERE pi.stock_out_id = $sale_id
    ORDER BY pi.id ASC
");

$items = [];
while ($row = $items_res->fetch_assoc()) $items[] = $row;
if (empty($items)) { echo "No items found for this invoice."; exit; }

$inv_no     = 'INV-' . str_pad($hdr['id'], 5, '0', STR_PAD_LEFT);
$item_count = count($items);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice <?= $inv_no ?></title>
<style>
  /* ── Reset & base ── */
  * { margin:0; padding:0; box-sizing:border-box; }
  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 12.5px;
    color: #1a1a2e;
    background: #e8eaf0;
  }

  /* ── A4 page shell ── */
  .page {
    width: 210mm;
    min-height: 297mm;
    margin: 20px auto;
    background: #fff;
    position: relative;
    display: flex;
    flex-direction: column;
    box-shadow: 0 4px 32px rgba(0,0,0,.18);
  }

  /* ── Top accent bar ── */
  .top-bar {
    height: 6px;
    background: linear-gradient(to right, #7c3001 50%, #c88d05 50%);
    flex-shrink: 0;
  }

  /* ── Main content grows to push footer down ── */
  .content {
    flex: 1;
    padding: 28px 36px 24px;
  }

  /* ── Header ── */
  .header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 22px;
    padding-bottom: 18px;
    border-bottom: 2px solid #1a1a2e;
  }
  .brand img { height: 72px; display: block; object-fit: contain; }
  .inv-meta { text-align: right; }
  .inv-meta .inv-type {
    font-size: .72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 2px;
    color: #888; margin-bottom: 4px;
  }
  .inv-meta .inv-no {
    font-size: 1.4rem; font-weight: 900;
    color: #7c3001; letter-spacing: 1px;
  }
  .inv-meta p { font-size: .78rem; color: #555; margin-top: 3px; }
  .inv-meta .badge {
    display: inline-block;
    background: #1a1a2e; color: #fff;
    font-size: .65rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 1px;
    padding: 3px 10px; border-radius: 20px;
    margin-top: 6px;
  }

  /* ── Section label ── */
  .section-head {
    font-size: .65rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 1.5px;
    color: #aaa; margin-bottom: 8px;
  }

  /* ── Buyer card — full width ── */
  .buyer-card {
    background: #f0f2f8;
    border-left: 4px solid #1a1a2e;
    border-radius: 0 8px 8px 0;
    padding: 16px 20px;
    margin-bottom: 22px;
    display: flex;
    gap: 32px;
    align-items: flex-start;
    width: 100%;
  }
  .buyer-card .buyer-name-block { min-width: 200px; }
  .buyer-card h3 {
    font-size: 1rem; font-weight: 800;
    color: #1a1a2e; margin-bottom: 4px;
  }
  .buyer-card .company {
    font-size: .8rem; font-weight: 600;
    color: #1a1a2e; margin-bottom: 6px;
  }
  .buyer-card .contact-grid {
    display: flex; flex-wrap: wrap; gap: 6px 28px;
    flex: 1;
  }
  .buyer-card .contact-item {
    display: flex; align-items: center; gap: 6px;
    font-size: .78rem; color: #444;
  }
  .buyer-card .contact-item .icon {
    width: 22px; height: 22px;
    background: #e1dfe8; color: #fff;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: .65rem; flex-shrink: 0;
  }

  /* ── Items table ── */
  table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
  thead tr {
    background: #1a1a2e;
  }
  thead th {
    color: #fff; padding: 9px 12px;
    font-size: .7rem; text-transform: uppercase;
    letter-spacing: .6px; text-align: left; font-weight: 700;
  }
  thead th:first-child { border-radius: 0; }
  thead th:last-child  { border-radius: 0; }
  tbody td {
    padding: 9px 12px;
    border-bottom: 1px solid #eef0f5;
    font-size: .82rem; vertical-align: middle;
  }
  tbody tr:last-child td { border-bottom: none; }
  tbody tr:nth-child(even) td { background: #f7f8fc; }
  .serial-badge {
    font-family: 'Courier New', monospace;
    font-size: .78rem;
    background: #f0f2f8;
    color: #444;
    padding: 2px 8px;
    border-radius: 4px;
    font-weight: 700;
    letter-spacing: .5px;
  }
  .part-badge {
    font-family: 'Courier New', monospace;
    font-size: .78rem;
    background: #f0f2f8;
    color: #444;
    padding: 2px 8px;
    border-radius: 4px;
    font-weight: 700;
    letter-spacing: .5px;
  }
  .row-num { color: #c0c4d0; font-size: .75rem; font-weight: 700; }

  /* ── Summary strip ── */
  .summary-strip {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 20px;
  }
  .summary-box {
    background: #1a1a2e;
    color: #fff;
    border-radius: 8px;
    padding: 12px 24px;
    text-align: center;
    min-width: 140px;
  }
  .summary-box .s-label { font-size: .65rem; letter-spacing: 1px; text-transform: uppercase; color: #aab; margin-bottom: 4px; }
  .summary-box .s-value { font-size: 1.5rem; font-weight: 900; color: #e94560; }
  .summary-box .s-sub   { font-size: .7rem; color: #aab; }

  /* ── Remarks ── */
  .remarks-box {
    background: #fffbf0;
    border: 1px solid #f0d878;
    border-left: 4px solid #f5c800;
    border-radius: 0 6px 6px 0;
    padding: 10px 14px;
    font-size: .8rem;
    margin-bottom: 24px;
    color: #555;
  }

  /* ── Signature ── */
  .sig-wrap { display: flex; justify-content: flex-end; padding-bottom: 16px;margin-top: 150px; }
  .sig-box  { text-align: center; }
  .sig-line { width: 180px; border-top: 1.5px solid #1a1a2e; margin: 0 auto 6px; }
  .sig-label { font-size: .72rem; color: #888; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }

  /* ── Footer ── */
  .footer {
    flex-shrink: 0;
    background: #7c3001;
    padding: 10px 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 28px;
  }
  .footer .f-item {
    display: flex; align-items: center; gap: 6px;
    font-size: .72rem; color: rgb(255, 255, 255);
    white-space: nowrap;
  }
  .footer .f-item .fi { color: #e94560; }

  /* ── Print button (screen only) ── */
  .print-btn { text-align: center; padding: 20px; display: flex; justify-content: center; gap: 10px; }
  .btn-print {
    padding: 10px 32px; background: #1a1a2e; color: #fff;
    border: none; border-radius: 6px; font-size: .88rem;
    cursor: pointer; font-weight: 600;
  }
  .btn-close {
    padding: 10px 20px; background: #eee; color: #333;
    border: none; border-radius: 6px; font-size: .88rem; cursor: pointer;
  }

  /* ── PRINT: preserve colors ── */
  @media print {
  body { background: #fff; }
  .print-btn { display: none; }
  .page { margin: 0; box-shadow: none; min-height: 297mm; }

  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
    color-adjust: exact !important;
  }

  .top-bar          { background: linear-gradient(to right, #7c3001 50%, #c88d05 50%) !important; }

  thead tr          { background: #1a1a2e !important; }
  thead th          { color: #fff !important; }

  .buyer-card       { background: #f0f2f8 !important; border-left-color: #1a1a2e !important; }
  .buyer-card .icon { background: #e1dfe8 !important; }

  .serial-badge     { background: #f0f2f8 !important; color: #444 !important; }
  .part-badge       { background: #f0f2f8 !important; color: #444 !important; }

  .inv-meta .inv-no { color: #7c3001 !important; }
  .inv-meta .badge  { background: #1a1a2e !important; color: #fff !important; }

  .summary-box      { background: #1a1a2e !important; }
  .summary-box .s-value { color: #e94560 !important; }

  .remarks-box      { background: #fffbf0 !important; border-color: #f0d878 !important; border-left-color: #f5c800 !important; }

  tbody tr:nth-child(even) td { background: #f7f8fc !important; }

  .footer           { background: #7c3001 !important; }
  .footer .f-item   { color: #ffffff !important; }
  .footer .fi       { color: #e94560 !important; }

  .sig-line         { border-top-color: #1a1a2e !important; }
}

  @page {
    size: A4 portrait;
    margin: 0;
  }
</style>
</head>
<body>

<div class="page">

  <!-- ── Top accent bar ── -->
  <div class="top-bar"></div>

  <!-- ── Main content ── -->
  <div class="content">

    <!-- Header -->
    <div class="header">
      <div class="brand">
        <img src="rajaiswari-wotbg.png" alt="Logo">
      </div>
      <div class="inv-meta">
        <div class="inv-type">Sales Invoice</div>
        <div class="inv-no"><?= $inv_no ?></div>
        <p>Sale ID: #<?= $hdr['id'] ?></p>
        <p>Date: <?= date('d F Y', strtotime($hdr['sale_date'])) ?></p>
        <span class="badge"><?= $item_count ?> item<?= $item_count != 1 ? 's' : '' ?> sold</span>
      </div>
    </div>

    <!-- Buyer card — full width -->
    <div class="section-head">Customer / Buyer</div>
    <div class="buyer-card">
      <div class="buyer-name-block">
        <h3>Name: <?= htmlspecialchars(ucfirst($hdr['buyer_name'])) ?></h3>
        <?php if($hdr['buyer_company']): ?>
          <div class="company">Company/Shop: <?= htmlspecialchars(ucfirst($hdr['buyer_company'])) ?></div>
        <?php endif; ?>
      </div>
      <div class="contact-grid">
        <?php if($hdr['buyer_phone']): ?>
        <div class="contact-item">
          <span class="icon">📞</span>
          <?= htmlspecialchars($hdr['buyer_phone']) ?>
        </div>
        <?php endif; ?>
        <?php if($hdr['buyer_email']): ?>
        <div class="contact-item">
          <span class="icon">✉</span>
          <?= htmlspecialchars($hdr['buyer_email']) ?>
        </div>
        <?php endif; ?>
        <?php if($hdr['buyer_address']): ?>
        <div class="contact-item">
          <span class="icon">📍</span>
          <?= htmlspecialchars($hdr['buyer_address']) ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Items table -->
    <div class="section-head" style="margin-bottom:8px;">Item Details</div>
    <table>
      <thead>
        <tr>
          <th style="width:34px;">#</th>
          <th>Product Name</th>
          <th>Serial No</th>
          <th>Part No</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($items as $i => $item): ?>
        <tr>
          <td><span class="row-num"><?= $i + 1 ?></span></td>
          <td>
            <strong><?= htmlspecialchars($item['product_name']) ?></strong>
            <?php if($item['product_desc']): ?>
              <br><span style="font-size:.73rem;color:#9ca3af;"><?= htmlspecialchars($item['product_desc']) ?></span>
            <?php endif; ?>
          </td>
          <td><span class="serial-badge"><?= htmlspecialchars($item['serial_no']) ?></span></td>
          <td>
            <?= $item['part_no']
                ? '<span class="part-badge">'.htmlspecialchars($item['part_no']).'</span>'
                : '<span style="color:#ccc;">—</span>' ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>


    <?php if($hdr['remarks']): ?>
    <div class="remarks-box"><strong>📝 Remarks:</strong> <?= htmlspecialchars($hdr['remarks']) ?></div>
    <?php endif; ?>

    <!-- Signature -->
    <div class="sig-wrap">
      <div class="sig-box">
        <div class="sig-line"></div>
        <div class="sig-label">Authorized Signature</div>
      </div>
    </div>

  </div><!-- /content -->

  <!-- ── Footer ── -->
  <div class="footer">
    <div class="f-item"><span class="fi">📍</span> 123 Business Street, City, State – 600001</div>
    <div class="f-item"><span class="fi">📞</span> +91 98765 43210</div>
    <div class="f-item"><span class="fi">🌐</span> www.rajaiswari.com</div>
  </div>

</div><!-- /page -->

<!-- Print buttons (screen only) -->
<div class="print-btn">
  <button class="btn-print" onclick="window.print()">🖨 Print / Save PDF</button>
  <button class="btn-close" onclick="window.close()">✕ Close</button>
</div>

</body>
</html>