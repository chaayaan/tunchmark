<?php
require_once 'mydb.php';
require_once 'auth.php';

$msg = '';
$msg_type = 'success';

// Handle multi-item sale — updated for new schema
if (isset($_POST['create_sale'])) {
    $buyer_id  = (int)$_POST['buyer_id'];
    $sale_date = $conn->real_escape_string($_POST['sale_date']);
    $remarks   = $conn->real_escape_string($_POST['remarks']);
    $item_ids  = $_POST['product_item_id'] ?? [];

    $errors = [];
    $added  = 0;

    // Create stock_out header first
    $valid_items = [];
    foreach ($item_ids as $item_id) {
        $item_id = (int)$item_id;
        if (!$item_id) continue;
        $check = $conn->query("SELECT id FROM product_items WHERE id=$item_id AND status='in_stock'");
        if ($check->num_rows === 0) {
            $errors[] = "Item #$item_id no longer available.";
        } else {
            $valid_items[] = $item_id;
        }
    }

    if (!empty($valid_items)) {
        $conn->query("INSERT INTO stock_out (buyer_id, sale_date, remarks) VALUES ($buyer_id, '$sale_date', '$remarks')");
        $new_so_id = $conn->insert_id;
        foreach ($valid_items as $item_id) {
            $conn->query("UPDATE product_items SET stock_out_id=$new_so_id, out_date='$sale_date', status='sold' WHERE id=$item_id");
            $added++;
        }
        $msg = "Sale #$new_so_id recorded — $added item(s) sold.";
        if ($errors) $msg .= ' Skipped: ' . implode(', ', $errors);
        $msg_type = $errors ? 'warning' : 'success';
    } else {
        $msg = 'No items were sold. ' . implode(' ', $errors);
        $msg_type = 'danger';
    }
}

// Pagination
$per_page = 50;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$search_buyer = $conn->real_escape_string($_GET['search_buyer'] ?? '');
$buyer_filter_sql = $search_buyer ? "WHERE b.name LIKE '%$search_buyer%' OR b.company_name LIKE '%$search_buyer%'" : '';

$count_res   = $conn->query("SELECT COUNT(so.id) FROM stock_out so JOIN buyers b ON b.id=so.buyer_id $buyer_filter_sql");
$total_rows  = $count_res->fetch_row()[0];
$total_pages = (int)ceil($total_rows / $per_page);

// Load stock-out batches
$all_sout = $conn->query("
    SELECT so.id, so.sale_date, so.remarks,
           b.name AS buyer_name, b.company_name, b.phone
    FROM stock_out so
    JOIN buyers b ON b.id = so.buyer_id
    $buyer_filter_sql
    ORDER BY so.sale_date DESC, so.id DESC
    LIMIT $per_page OFFSET $offset
");

// Fetch items for each batch
$sout_items = [];
if ($all_sout->num_rows > 0) {
    $ids = [];
    $all_sout->data_seek(0);
    while ($r = $all_sout->fetch_assoc()) $ids[] = $r['id'];
    $all_sout->data_seek(0);
    if (!empty($ids)) {
        $id_list = implode(',', $ids);
        $items_res = $conn->query("
            SELECT pi.id, pi.stock_out_id, pi.serial_no, pi.part_no, pi.out_date,
                   pi.status, p.product_name
            FROM product_items pi
            JOIN products p ON p.id = pi.product_id
            WHERE pi.stock_out_id IN ($id_list)
            ORDER BY pi.created_at DESC
        ");
        while ($row = $items_res->fetch_assoc()) {
            $sout_items[$row['stock_out_id']][] = $row;
        }
    }
}

// Available items for sale modal
$available = $conn->query("
    SELECT pi.id, pi.serial_no, pi.part_no, p.product_name
    FROM product_items pi
    JOIN products p ON p.id=pi.product_id
    WHERE pi.status='in_stock'
    ORDER BY p.product_name, pi.serial_no
");

// Buyers for modal
$buyers = $conn->query("SELECT id, name, company_name FROM buyers ORDER BY name");

$active_page = 'stock_out';

function soutPagUrl($p) {
    $q = $_GET; $q['page'] = $p;
    return '?' . http_build_query($q);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stock Out — Inventory</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
  body { background:#f0f2f5; font-family:'Segoe UI',sans-serif; margin:0; }
  .page-shell { margin-left:200px; min-height:100vh; display:flex; flex-direction:column; }
  .top-bar {
    position:sticky; top:0; z-index:200;
    height:54px; background:#fff;
    border-bottom:1px solid #e9ecef;
    box-shadow:0 1px 3px rgba(0,0,0,.06);
    display:flex; align-items:center;
    padding:0 22px; gap:10px; flex-shrink:0;
  }
  .tb-ico { width:32px; height:32px; background:#fef2f2; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#dc2626; font-size:13px; flex-shrink:0; }
  .tb-title { font-size:1.0625rem; font-weight:700; color:#111827; }
  .tb-sub   { font-size:.8rem; color:#9ca3af; }
  .tb-right { margin-left:auto; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
  .page-badge { display:inline-flex; align-items:center; gap:5px; background:#fafbfc; border:1px solid #e4e7ec; border-radius:6px; padding:4px 12px; font-size:.78rem; font-weight:500; color:#6b7280; }
  .main { flex:1; padding:20px 22px 60px; display:flex; flex-direction:column; gap:14px; }

  .sec { background:#fff; border:1px solid #e4e7ec; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.06); overflow:hidden; }
  .sec-head { display:flex; align-items:center; gap:9px; padding:12px 18px; background:#fafbfc; border-bottom:1px solid #f0f1f3; }
  .sec-ico { width:28px; height:28px; border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:12px; flex-shrink:0; }
  .i-red  { background:#fef2f2; color:#dc2626; }
  .i-blue { background:#eff6ff; color:#2563eb; }
  .sec-title { font-size:.9375rem; font-weight:700; color:#111827; }
  .sec-meta  { margin-left:auto; font-size:.78rem; color:#9ca3af; font-weight:500; }

  .batch-block { border-bottom:1px solid #f0f1f3; }
  .batch-block:last-child { border-bottom:none; }

  .badge-status { font-size:.72rem; padding:3px 9px; border-radius:20px; font-weight:600; }
  .badge-in_stock { background:#d1fae5; color:#065f46; }
  .badge-sold     { background:#fee2e2; color:#991b1b; }
  .badge-damaged  { background:#fef3c7; color:#92400e; }
  .badge-returned { background:#e0e7ff; color:#3730a3; }

  .pag-wrap { display:flex; align-items:center; justify-content:center; gap:4px; padding:14px 18px; border-top:1px solid #e4e7ec; background:#fafbfc; }
  .pag-btn { display:inline-flex; align-items:center; justify-content:center; min-width:34px; height:34px; padding:0 8px; border:1.5px solid #e4e7ec; border-radius:6px; font-size:.82rem; font-weight:600; color:#374151; background:#fff; text-decoration:none; transition:all .15s; }
  .pag-btn:hover  { background:#eff6ff; border-color:#bfdbfe; color:#2563eb; }
  .pag-btn.active { background:#2563eb; border-color:#2563eb; color:#fff; }
  .pag-btn.disabled { background:#fafbfc; color:#9ca3af; pointer-events:none; }

  .tb-btn { display:inline-flex; align-items:center; gap:6px; height:32px; padding:0 14px; border-radius:6px; font-size:.82rem; font-weight:600; text-decoration:none; cursor:pointer; transition:all .15s; border:1.5px solid transparent; white-space:nowrap; }
  .tb-btn-danger { background:#dc2626; color:#fff; border-color:#dc2626; }
  .tb-btn-danger:hover { background:#b91c1c; color:#fff; }

  /* Search bar in top-right */
  .search-form { display:flex; gap:6px; align-items:center; }
  .search-form input { height:32px; padding:0 10px; border:1.5px solid #e4e7ec; border-radius:6px; font-size:.82rem; color:#374151; outline:none; width:180px; }
  .search-form input:focus { border-color:#2563eb; }
  .search-form button { height:32px; padding:0 12px; background:#1a1a2e; color:#fff; border:none; border-radius:6px; font-size:.82rem; font-weight:600; cursor:pointer; }

  .sale-item-row { background:#f8f9fc; border:1px solid #e9ecef; border-radius:10px; padding:12px 40px 12px 14px; margin-bottom:10px; position:relative; }
  .sale-item-row .remove-btn { position:absolute; top:10px; right:10px; }
  .modal-dialog { max-width:680px; }
  .avail-count { background:#d1fae5; color:#065f46; font-size:.72rem; padding:3px 9px; border-radius:12px; font-weight:600; }

  @media(max-width:991.98px){ .page-shell { margin-left:0; } .top-bar { top:52px; } }
</style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="page-shell">

  <header class="top-bar">
    <div class="tb-ico"><i class="bi bi-box-arrow-up"></i></div>
    <div>
      <div class="tb-title">Stock Out / Sales</div>
      <div class="tb-sub">Batch sale records</div>
    </div>
    <div class="tb-right">
      <form method="GET" class="search-form">
        <input type="text" name="search_buyer" placeholder="Search buyer..." value="<?= htmlspecialchars($_GET['search_buyer'] ?? '') ?>">
        <button type="submit">Go</button>
        <?php if(!empty($_GET['search_buyer'])): ?>
          <a href="stock_out.php" style="height:32px;display:inline-flex;align-items:center;padding:0 10px;border:1.5px solid #e4e7ec;border-radius:6px;font-size:.82rem;color:#6b7280;text-decoration:none;">Clear</a>
        <?php endif; ?>
      </form>
      <div class="page-badge">
        p.<?= $page ?>/<?= max(1,$total_pages) ?> &nbsp;·&nbsp; <?= $total_rows ?> sales
      </div>
      <button class="tb-btn tb-btn-danger" data-bs-toggle="modal" data-bs-target="#saleModal">
        <i class="bi bi-cart-plus"></i> New Sale
      </button>
    </div>
  </header>

  <div class="main">

    <?php if($msg): ?>
      <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show mb-0">
        <?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="sec">
      <div class="sec-head">
        <span class="sec-ico i-red"><i class="bi bi-cart-check"></i></span>
        <span class="sec-title">Sales Records</span>
        <span class="sec-meta">Showing <?= $all_sout->num_rows ?> of <?= $total_rows ?></span>
      </div>

      <!-- Column headers -->
      <div style="padding:0;">
        <table style="width:100%;border-collapse:collapse;">
          <thead>
            <tr style="background:#1a1a2e;">
              <th style="padding:9px 18px;color:#ccd6f6;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;font-weight:700;white-space:nowrap;width:200px;">Buyer / Date</th>
              <th style="padding:9px 74px;color:#ccd6f6;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;font-weight:700;">Product</th>
              <th style="padding:9px 14px;color:#ccd6f6;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;font-weight:700;">Serial No</th>
              <th style="padding:9px 14px;color:#ccd6f6;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;font-weight:700;">Part No</th>
              <th style="padding:9px 14px;color:#ccd6f6;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;font-weight:700;">Out Date</th>
              <th style="padding:9px 14px;color:#ccd6f6;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;font-weight:700;">Status</th>
              <th style="padding:9px 14px;color:#ccd6f6;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;font-weight:700;">Invoice</th>
            </tr>
          </thead>
        </table>
      </div>

      <?php if($all_sout->num_rows === 0): ?>
        <div style="padding:56px 24px;text-align:center;color:#9ca3af;">
          <i class="bi bi-inbox" style="font-size:2.8rem;display:block;margin-bottom:12px;"></i>
          <div style="font-weight:700;font-size:.9375rem;">No sales records found</div>
          <?php if($search_buyer): ?><div style="font-size:.82rem;margin-top:4px;">No results for "<?= htmlspecialchars($search_buyer) ?>"</div><?php endif; ?>
        </div>
      <?php else: ?>

      <?php $all_sout->data_seek(0); while($sout = $all_sout->fetch_assoc()):
        $items = $sout_items[$sout['id']] ?? [];
        $item_count = count($items);
      ?>
      <div class="batch-block">
        <table style="width:100%;border-collapse:collapse;">
          <tbody>
          <?php if(empty($items)): ?>
            <tr>
              <td style="padding:10px 18px;vertical-align:middle;width:200px;border-bottom:1px solid #f9fafb;">
                <div style="font-weight:700;font-size:.875rem;color:#111827;"><?= htmlspecialchars($sout['buyer_name']) ?></div>
                <?php if($sout['company_name']): ?><div style="font-size:.75rem;color:#9ca3af;"><?= htmlspecialchars($sout['company_name']) ?></div><?php endif; ?>
                <?php if($sout['phone']): ?><div style="font-size:.75rem;color:#9ca3af;"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($sout['phone']) ?></div><?php endif; ?>
                <div style="font-size:.78rem;color:#6b7280;margin-top:2px;"><i class="bi bi-calendar3 me-1"></i><?= date('d M Y', strtotime($sout['sale_date'])) ?></div>
                <div style="font-size:.7rem;color:#9ca3af;font-family:monospace;">Sale #<?= $sout['id'] ?></div>
              </td>
              <td colspan="6" style="padding:10px 14px;color:#9ca3af;font-size:.875rem;border-bottom:1px solid #f9fafb;"><em>No items in this sale</em></td>
            </tr>
          <?php else: ?>
          <?php foreach($items as $idx => $item): ?>
            <tr>
              <?php if($idx === 0): ?>
              <td style="padding:10px 18px;vertical-align:top;width:200px;border-bottom:1px solid #f9fafb;" rowspan="<?= $item_count ?>">
                <div style="font-weight:700;font-size:.875rem;color:#111827;"><?= htmlspecialchars($sout['buyer_name']) ?></div>
                <?php if($sout['company_name']): ?><div style="font-size:.75rem;color:#9ca3af;"><?= htmlspecialchars($sout['company_name']) ?></div><?php endif; ?>
                <?php if($sout['phone']): ?><div style="font-size:.75rem;color:#9ca3af;"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($sout['phone']) ?></div><?php endif; ?>
                <div style="font-size:.78rem;color:#6b7280;margin-top:4px;"><i class="bi bi-calendar3 me-1"></i><?= date('d M Y', strtotime($sout['sale_date'])) ?></div>
                <div style="font-size:.7rem;color:#9ca3af;font-family:monospace;margin-top:2px;">Sale #<?= $sout['id'] ?></div>
                <?php if($sout['remarks']): ?><div style="font-size:.72rem;color:#9ca3af;margin-top:4px;"><?= htmlspecialchars($sout['remarks']) ?></div><?php endif; ?>
                <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;">
                  <span style="background:#fee2e2;color:#991b1b;font-size:.7rem;font-weight:700;padding:2px 8px;border-radius:10px;"><?= $item_count ?> item<?= $item_count!=1?'s':'' ?></span>
                  <a href="invoice.php?id=<?= $sout['id'] ?>" target="_blank" style="background:#f0f9ff;color:#0369a1;font-size:.7rem;font-weight:700;padding:2px 8px;border-radius:10px;text-decoration:none;"><i class="bi bi-printer me-1"></i>Invoice</a>
                </div>
              </td>
              <?php endif; ?>
              <td style="padding:8px 14px;font-size:.875rem;vertical-align:middle;border-bottom:1px solid #f9fafb;max-width:200px;">
                <?= htmlspecialchars($item['product_name']) ?>
              </td>
              <td style="padding:8px 14px;vertical-align:middle;border-bottom:1px solid #f9fafb;">
                <code style="font-size:.82rem;"><?= htmlspecialchars($item['serial_no']) ?></code>
              </td>
              <td style="padding:8px 14px;vertical-align:middle;border-bottom:1px solid #f9fafb;font-size:.875rem;">
                <?= $item['part_no'] ? htmlspecialchars($item['part_no']) : '<span style="color:#9ca3af;">—</span>' ?>
              </td>
              <td style="padding:8px 14px;vertical-align:middle;border-bottom:1px solid #f9fafb;font-size:.78rem;color:#6b7280;font-family:monospace;">
                <?= $item['out_date'] ? date('d M Y', strtotime($item['out_date'])) : '<span style="color:#9ca3af;">—</span>' ?>
              </td>
              <td style="padding:8px 14px;vertical-align:middle;border-bottom:1px solid #f9fafb;">
                <span class="badge-status badge-<?= $item['status'] ?>"><?= $item['status'] ?></span>
              </td>
              <td style="padding:8px 14px;vertical-align:middle;border-bottom:1px solid #f9fafb;">
                <?php if($idx === 0): ?>
                <a href="invoice.php?id=<?= $sout['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2">
                  <i class="bi bi-printer"></i>
                </a>
                <?php else: ?>
                <span style="color:#9ca3af;">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php endwhile; ?>

      <!-- Pagination -->
      <?php if($total_pages > 1): ?>
      <div class="pag-wrap">
        <?php if($page > 1): ?>
          <a href="<?= soutPagUrl(1) ?>" class="pag-btn"><i class="bi bi-chevron-double-left" style="font-size:.65rem;"></i></a>
          <a href="<?= soutPagUrl($page-1) ?>" class="pag-btn"><i class="bi bi-chevron-left" style="font-size:.65rem;"></i></a>
        <?php else: ?>
          <span class="pag-btn disabled"><i class="bi bi-chevron-double-left" style="font-size:.65rem;"></i></span>
          <span class="pag-btn disabled"><i class="bi bi-chevron-left" style="font-size:.65rem;"></i></span>
        <?php endif; ?>
        <?php for($i=max(1,$page-2); $i<=min($total_pages,$page+2); $i++): ?>
          <a href="<?= soutPagUrl($i) ?>" class="pag-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if($page < $total_pages): ?>
          <a href="<?= soutPagUrl($page+1) ?>" class="pag-btn"><i class="bi bi-chevron-right" style="font-size:.65rem;"></i></a>
          <a href="<?= soutPagUrl($total_pages) ?>" class="pag-btn"><i class="bi bi-chevron-double-right" style="font-size:.65rem;"></i></a>
        <?php else: ?>
          <span class="pag-btn disabled"><i class="bi bi-chevron-right" style="font-size:.65rem;"></i></span>
          <span class="pag-btn disabled"><i class="bi bi-chevron-double-right" style="font-size:.65rem;"></i></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php endif; ?>
    </div><!-- /sec -->

  </div><!-- /main -->
</div><!-- /page-shell -->

<!-- ====== NEW SALE MODAL ====== -->
<div class="modal fade" id="saleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content" style="border:none;border-radius:14px;overflow:hidden;">
      <form method="POST" id="saleForm">
        <input type="hidden" name="create_sale" value="1">
        <div class="modal-header" style="background:#1a1a2e;border:none;flex-shrink:0;">
          <h5 class="modal-title" style="color:#fff;font-size:.95rem;font-weight:700;">
            <i class="bi bi-cart-plus me-2"></i>New Sale — Batch Entry
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4" style="overflow-y:auto;">
          <div class="row g-3 mb-4 pb-3" style="border-bottom:1px solid #eee;">
            <div class="col-sm-5">
              <label class="form-label form-label-sm fw-semibold">Buyer <span class="text-danger">*</span></label>
              <select name="buyer_id" class="form-select form-select-sm" required>
                <option value="">— Select Buyer —</option>
                <?php $buyers->data_seek(0); while($b=$buyers->fetch_assoc()): ?>
                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?><?= $b['company_name']?' ('.$b['company_name'].')':'' ?></option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="col-sm-4">
              <label class="form-label form-label-sm fw-semibold">Sale Date <span class="text-danger">*</span></label>
              <input type="date" name="sale_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-sm-3">
              <label class="form-label form-label-sm fw-semibold">Remarks</label>
              <input type="text" name="remarks" class="form-control form-control-sm" placeholder="Optional">
            </div>
          </div>
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="fw-semibold" style="font-size:.88rem;color:#1a1a2e;">
              <i class="bi bi-list-ul me-1 text-muted"></i>Items to Sell
              <span class="avail-count ms-2"><?= $available->num_rows ?> available</span>
            </div>
            <button type="button" class="btn btn-sm btn-outline-dark" onclick="addSaleRow()"><i class="bi bi-plus-lg me-1"></i>Add Another Item</button>
          </div>
          <div id="saleItemsContainer">
            <div class="sale-item-row" id="sale-item-0">
              <div class="row g-2 align-items-end">
                <div class="col-5">
                  <label class="form-label form-label-sm">Filter by Product</label>
                  <input type="text" class="form-control form-control-sm product-filter" placeholder="Type to filter..." oninput="filterRow(this)">
                </div>
                <div class="col-7">
                  <label class="form-label form-label-sm">Select Item <span class="text-danger">*</span></label>
                  <select name="product_item_id[]" class="form-select form-select-sm item-select" required>
                    <option value="">— Product / Serial No —</option>
                    <?php $available->data_seek(0); while($row=$available->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>" data-product="<?= htmlspecialchars(strtolower($row['product_name'])) ?>">
                      <?= htmlspecialchars($row['product_name']) ?> — <?= htmlspecialchars($row['serial_no']) ?><?= $row['part_no']?' / '.$row['part_no']:'' ?>
                    </option>
                    <?php endwhile; ?>
                  </select>
                </div>
              </div>
            </div>
          </div>
          <div class="text-muted mt-2" style="font-size:.75rem;"><i class="bi bi-info-circle me-1"></i>All items are sold to the same buyer on the same date.</div>
        </div>
        <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:14px 20px;flex-shrink:0;">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger btn-sm px-4"><i class="bi bi-bag-check me-1"></i>Confirm Sale</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const allItemOptions = <?php
  $available->data_seek(0);
  $opts = [];
  while($row = $available->fetch_assoc()) {
    $label = $row['product_name'] . ' — ' . $row['serial_no'];
    if ($row['part_no']) $label .= ' / ' . $row['part_no'];
    $opts[] = ['id' => $row['id'], 'label' => $label, 'product' => strtolower($row['product_name'])];
  }
  echo json_encode($opts);
?>;

function buildOptions(filterText) {
  const q = filterText.toLowerCase();
  let html = '<option value="">— Product / Serial No —</option>';
  allItemOptions.forEach(o => {
    if (!q || o.product.includes(q) || o.label.toLowerCase().includes(q))
      html += `<option value="${o.id}" data-product="${o.product}">${o.label}</option>`;
  });
  return html;
}
function filterRow(input) {
  const row = input.closest('.sale-item-row');
  row.querySelector('.item-select').innerHTML = buildOptions(input.value);
}
let saleRowCount = 1;
function addSaleRow() {
  const container = document.getElementById('saleItemsContainer');
  const idx = saleRowCount++;
  const div = document.createElement('div');
  div.className = 'sale-item-row';
  div.id = 'sale-item-' + idx;
  div.innerHTML = `
    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 remove-btn" onclick="removeSaleRow('sale-item-${idx}')">
      <i class="bi bi-x-lg"></i>
    </button>
    <div class="row g-2 align-items-end">
      <div class="col-5">
        <label class="form-label form-label-sm">Filter by Product</label>
        <input type="text" class="form-control form-control-sm product-filter" placeholder="Type to filter..." oninput="filterRow(this)">
      </div>
      <div class="col-7">
        <label class="form-label form-label-sm">Select Item <span class="text-danger">*</span></label>
        <select name="product_item_id[]" class="form-select form-select-sm item-select" required>
          ${buildOptions('')}
        </select>
      </div>
    </div>`;
  container.appendChild(div);
  const mb = document.querySelector('#saleModal .modal-body');
  mb.scrollTop = mb.scrollHeight;
  div.querySelector('.product-filter').focus();
}
function removeSaleRow(id) { const el=document.getElementById(id); if(el) el.remove(); }
</script>
</body>
</html>