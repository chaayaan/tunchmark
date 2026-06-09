<?php
require_once 'mydb.php';
require_once 'auth.php';

$msg = '';
$msg_type = 'success';

if (isset($_POST['submit_batch'])) {
    $supplier_id = (int)$_POST['supplier_id'];
    $stock_date  = $conn->real_escape_string($_POST['stock_date']);
    $remarks     = $conn->real_escape_string($_POST['remarks']);

    $conn->query("INSERT INTO stock_in (supplier_id, stock_date, remarks) VALUES ($supplier_id, '$stock_date', '$remarks')");
    $new_id = $conn->insert_id;

    $errors = []; $added = 0;
    $product_ids = $_POST['product_id'] ?? [];
    $serial_nos  = $_POST['serial_no']  ?? [];
    $part_nos    = $_POST['part_no']    ?? [];

    foreach ($product_ids as $i => $pid) {
        $pid    = (int)$pid;
        $serial = $conn->real_escape_string(trim($serial_nos[$i] ?? ''));
        $part   = $conn->real_escape_string(trim($part_nos[$i]  ?? ''));
        if (!$pid || !$serial) continue;
        $dup = $conn->query("SELECT id FROM product_items WHERE serial_no='$serial'");
        if ($dup->num_rows > 0) {
            $errors[] = "Serial <strong>$serial</strong> already exists — skipped.";
        } else {
            $conn->query("INSERT INTO product_items (product_id, stock_in_id, serial_no, part_no, in_date, status)
                          VALUES ($pid, $new_id, '$serial', '$part', '$stock_date', 'in_stock')");
            $added++;
        }
    }

    if ($added === 0 && count($errors)) {
        $conn->query("DELETE FROM stock_in WHERE id=$new_id");
        $msg = 'No items were added. ' . implode(' ', $errors);
        $msg_type = 'danger';
    } else {
        $msg = "Stock-in #$new_id created with $added item(s).";
        if ($errors) $msg .= ' Skipped: ' . implode(', ', $errors);
        $msg_type = $errors ? 'warning' : 'success';
    }
}

if (isset($_GET['del_item'])) {
    $item_id   = (int)$_GET['del_item'];
    $page_back = (int)($_GET['page'] ?? 1);
    $conn->query("DELETE FROM product_items WHERE id=$item_id AND status='in_stock'");
    header("Location: stock_in.php?page=$page_back"); exit;
}

$per_page    = 50;
$page        = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($page - 1) * $per_page;
$total_res   = $conn->query("SELECT COUNT(*) FROM stock_in");
$total_rows  = $total_res->fetch_row()[0];
$total_pages = (int)ceil($total_rows / $per_page);

$suppliers = $conn->query("SELECT id, name, company_name FROM suppliers ORDER BY name");
$products  = $conn->query("SELECT id, product_name FROM products ORDER BY product_name");

$all_sin = $conn->query("
    SELECT si.id, si.stock_date, si.remarks,
           s.name AS supplier_name, s.company_name
    FROM stock_in si
    JOIN suppliers s ON s.id = si.supplier_id
    ORDER BY si.stock_date DESC, si.id DESC
    LIMIT $per_page OFFSET $offset
");

$sin_items = [];
if ($all_sin->num_rows > 0) {
    $ids = [];
    $all_sin->data_seek(0);
    while ($r = $all_sin->fetch_assoc()) $ids[] = $r['id'];
    $all_sin->data_seek(0);
    $id_list   = implode(',', $ids);
    $items_res = $conn->query("
        SELECT pi.id, pi.stock_in_id, pi.serial_no, pi.part_no, pi.in_date, pi.status, p.product_name
        FROM product_items pi
        JOIN products p ON p.id = pi.product_id
        WHERE pi.stock_in_id IN ($id_list)
        ORDER BY pi.created_at ASC
    ");
    while ($row = $items_res->fetch_assoc()) {
        $sin_items[$row['stock_in_id']][] = $row;
    }
}

function sinPagUrl($p) { $q = $_GET; $q['page'] = $p; return '?' . http_build_query($q); }
$active_page = 'stock_in';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stock In — Inventory</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
  body { background:#f0f2f5; font-family:'Segoe UI',sans-serif; margin:0; }
  .page-shell { margin-left:200px; min-height:100vh; display:flex; flex-direction:column; }
  .top-bar { position:sticky; top:0; z-index:200; height:54px; background:#fff; border-bottom:1px solid #e9ecef; box-shadow:0 1px 3px rgba(0,0,0,.06); display:flex; align-items:center; padding:0 22px; gap:10px; flex-shrink:0; }
  .tb-ico { width:32px; height:32px; background:#eff6ff; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#2563eb; font-size:13px; flex-shrink:0; }
  .tb-title { font-size:1.0625rem; font-weight:700; color:#111827; }
  .tb-sub   { font-size:.8rem; color:#9ca3af; }
  .tb-right { margin-left:auto; display:flex; align-items:center; gap:8px; }
  .page-badge { display:inline-flex; align-items:center; gap:5px; background:#fafbfc; border:1px solid #e4e7ec; border-radius:6px; padding:4px 12px; font-size:.78rem; font-weight:500; color:#6b7280; }
  .main { flex:1; padding:20px 22px 60px; display:flex; flex-direction:column; gap:14px; }

  .sec { background:#fff; border:1px solid #e4e7ec; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.06); overflow:hidden; }
  .sec-head { display:flex; align-items:center; gap:9px; padding:12px 18px; background:#fafbfc; border-bottom:1px solid #f0f1f3; }
  .sec-ico { width:28px; height:28px; border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:12px; flex-shrink:0; }
  .i-blue { background:#eff6ff; color:#2563eb; }
  .sec-title { font-size:.9375rem; font-weight:700; color:#111827; }
  .sec-meta  { margin-left:auto; font-size:.78rem; color:#9ca3af; font-weight:500; }

  /* Batch blocks */
  .batch-block { border-bottom:1px solid #f0f1f3; }
  .batch-block:last-child { border-bottom:none; }

  .badge-status { font-size:.72rem; padding:3px 9px; border-radius:20px; font-weight:600; }
  .badge-in_stock { background:#d1fae5; color:#065f46; }
  .badge-sold     { background:#fee2e2; color:#991b1b; }
  .badge-damaged  { background:#fef3c7; color:#92400e; }
  .badge-returned { background:#e0e7ff; color:#3730a3; }

  /* Pagination */
  .pag-wrap { display:flex; align-items:center; justify-content:center; gap:4px; padding:14px 18px; border-top:1px solid #e4e7ec; background:#fafbfc; }
  .pag-btn { display:inline-flex; align-items:center; justify-content:center; min-width:34px; height:34px; padding:0 8px; border:1.5px solid #e4e7ec; border-radius:6px; font-size:.82rem; font-weight:600; color:#374151; background:#fff; text-decoration:none; transition:all .15s; }
  .pag-btn:hover  { background:#eff6ff; border-color:#bfdbfe; color:#2563eb; }
  .pag-btn.active { background:#2563eb; border-color:#2563eb; color:#fff; }
  .pag-btn.disabled { background:#fafbfc; color:#9ca3af; pointer-events:none; }

  .tb-btn { display:inline-flex; align-items:center; gap:6px; height:32px; padding:0 14px; border-radius:6px; font-size:.82rem; font-weight:600; cursor:pointer; transition:all .15s; border:1.5px solid transparent; white-space:nowrap; }
  .tb-btn-dark { background:#1a1a2e; color:#fff; border-color:#1a1a2e; }
  .tb-btn-dark:hover { background:#2d2d4e; color:#fff; }

  .item-row { background:#f8f9fc; border:1px solid #e9ecef; border-radius:10px; padding:12px 14px; margin-bottom:10px; position:relative; }
  .item-row .remove-btn { position:absolute; top:10px; right:10px; }
  .modal-dialog { max-width:680px; }

  @media(max-width:991.98px){ .page-shell { margin-left:0; } .top-bar { top:52px; } }
</style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="page-shell">

  <header class="top-bar">
    <div class="tb-ico"><i class="bi bi-box-arrow-in-down"></i></div>
    <div>
      <div class="tb-title">Stock In</div>
      <div class="tb-sub">Batch purchase records</div>
    </div>
    <div class="tb-right">
      <div class="page-badge">
        p.<?= $page ?>/<?= max(1,$total_pages) ?> &nbsp;·&nbsp; <?= $total_rows ?> batches
      </div>
      <button class="tb-btn tb-btn-dark" data-bs-toggle="modal" data-bs-target="#stockInModal">
        <i class="bi bi-plus-lg"></i> New Stock In
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
        <span class="sec-ico i-blue"><i class="bi bi-box-seam"></i></span>
        <span class="sec-title">Stock-In Records</span>
        <span class="sec-meta">Showing <?= $all_sin->num_rows ?> of <?= $total_rows ?></span>
      </div>

      <?php if($all_sin->num_rows === 0): ?>
        <div style="padding:56px 24px;text-align:center;color:#9ca3af;">
          <i class="bi bi-inbox" style="font-size:2.8rem;display:block;margin-bottom:12px;"></i>
          <div style="font-weight:700;font-size:.9375rem;">No stock-in records found</div>
        </div>
      <?php else: ?>

      <div style="padding:0;">
        <table style="width:100%;border-collapse:collapse;table-layout:fixed;">
          <colgroup>
            <col style="width:200px;">
            <col style="width:auto;">
            <col style="width:130px;">
            <col style="width:110px;">
            <col style="width:100px;">
            <col style="width:100px;">
            <col style="width:70px;">
          </colgroup>
          <thead>
            <tr style="background:#1a1a2e;">
              <th style="padding:9px 18px;color:#ccd6f6;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;font-weight:700;">Supplier / Date</th>
              <th style="padding:9px 14px;color:#ccd6f6;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;font-weight:700;">Product</th>
              <th style="padding:9px 14px;color:#ccd6f6;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;font-weight:700;">Serial No</th>
              <th style="padding:9px 14px;color:#ccd6f6;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;font-weight:700;">Part No</th>
              <th style="padding:9px 14px;color:#ccd6f6;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;font-weight:700;">In Date</th>
              <th style="padding:9px 14px;color:#ccd6f6;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;font-weight:700;">Status</th>
              <th style="padding:9px 14px;color:#ccd6f6;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;font-weight:700;text-align:center;">Action</th>
            </tr>
          </thead>
        </table>
      </div>

<?php if($all_sin->num_rows === 0): ?>
  <div style="padding:56px 24px;text-align:center;color:#9ca3af;">
    <i class="bi bi-inbox" style="font-size:2.8rem;display:block;margin-bottom:12px;"></i>
    <div style="font-weight:700;font-size:.9375rem;">No stock-in records found</div>
  </div>
<?php else: ?>

<?php $all_sin->data_seek(0); while($sin = $all_sin->fetch_assoc()):
  $items      = $sin_items[$sin['id']] ?? [];
  $item_count = count($items);
?>
<div class="batch-block">
  <table style="width:100%;border-collapse:collapse;table-layout:fixed;">
    <colgroup>
      <col style="width:200px;">   <!-- Supplier / Date -->
      <col style="width:auto;">    <!-- Product — flex -->
      <col style="width:130px;">   <!-- Serial No -->
      <col style="width:110px;">   <!-- Part No -->
      <col style="width:100px;">   <!-- In Date -->
      <col style="width:100px;">   <!-- Status -->
      <col style="width:70px;">    <!-- Action -->
    </colgroup>
    <tbody>
    <?php if(empty($items)): ?>
      <tr>
        <td style="padding:10px 18px;vertical-align:middle;border-bottom:1px solid #f9fafb;">
          <div style="font-weight:700;font-size:.875rem;color:#111827;"><?= htmlspecialchars($sin['supplier_name']) ?></div>
          <?php if($sin['company_name']): ?><div style="font-size:.75rem;color:#9ca3af;"><?= htmlspecialchars($sin['company_name']) ?></div><?php endif; ?>
          <div style="font-size:.78rem;color:#6b7280;margin-top:2px;"><i class="bi bi-calendar3 me-1"></i><?= date('d M Y', strtotime($sin['stock_date'])) ?></div>
          <div style="font-size:.7rem;color:#9ca3af;font-family:monospace;">Batch #<?= $sin['id'] ?></div>
        </td>
        <td colspan="6" style="padding:10px 14px;color:#9ca3af;font-size:.875rem;font-style:italic;">No items in this batch</td>
      </tr>
    <?php else: ?>
      <?php foreach($items as $idx => $item): ?>
      <tr style="<?= $idx % 2 === 0 ? 'background:#fff;' : 'background:#fafbfc;' ?>">

        <!-- Supplier cell: only on first row, rowspan for the rest -->
        <?php if($idx === 0): ?>
        <td style="padding:10px 18px;vertical-align:top;border-bottom:1px solid #f9fafb;" rowspan="<?= $item_count ?>">
          <div style="font-weight:700;font-size:.875rem;color:#111827;"><?= htmlspecialchars($sin['supplier_name']) ?></div>
          <?php if($sin['company_name']): ?><div style="font-size:.75rem;color:#9ca3af;"><?= htmlspecialchars($sin['company_name']) ?></div><?php endif; ?>
          <div style="font-size:.78rem;color:#6b7280;margin-top:4px;"><i class="bi bi-calendar3 me-1"></i><?= date('d M Y', strtotime($sin['stock_date'])) ?></div>
          <div style="font-size:.7rem;color:#9ca3af;font-family:monospace;margin-top:2px;">Batch #<?= $sin['id'] ?></div>
          <?php if($sin['remarks']): ?><div style="font-size:.72rem;color:#9ca3af;margin-top:4px;"><?= htmlspecialchars($sin['remarks']) ?></div><?php endif; ?>
          <div style="margin-top:6px;">
            <span style="background:#dbeafe;color:#1e40af;font-size:.7rem;font-weight:700;padding:2px 8px;border-radius:10px;"><?= $item_count ?> item<?= $item_count!=1?'s':'' ?></span>
          </div>
        </td>
        <?php endif; ?>

        <!-- Product: truncate long names with ellipsis -->
        <td style="padding:8px 14px;vertical-align:middle;border-bottom:1px solid #f9fafb;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;font-size:.875rem;" title="<?= htmlspecialchars($item['product_name']) ?>">
          <?= htmlspecialchars($item['product_name']) ?>
        </td>

        <!-- Serial No -->
        <td style="padding:8px 14px;vertical-align:middle;border-bottom:1px solid #f9fafb;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;">
          <code style="font-size:.82rem;background:#f3f4f6;padding:1px 6px;border-radius:4px;"><?= htmlspecialchars($item['serial_no']) ?></code>
        </td>

        <!-- Part No -->
        <td style="padding:8px 14px;vertical-align:middle;border-bottom:1px solid #f9fafb;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;font-size:.875rem;color:#6b7280;">
          <?= $item['part_no'] ? htmlspecialchars($item['part_no']) : '<span style="color:#d1d5db;">—</span>' ?>
        </td>

        <!-- In Date -->
        <td style="padding:8px 14px;vertical-align:middle;border-bottom:1px solid #f9fafb;font-size:.78rem;color:#6b7280;white-space:nowrap;font-family:monospace;">
          <?= date('d M Y', strtotime($item['in_date'])) ?>
        </td>

        <!-- Status -->
        <td style="padding:8px 14px;vertical-align:middle;border-bottom:1px solid #f9fafb;">
          <span class="badge-status badge-<?= $item['status'] ?>"><?= $item['status'] ?></span>
        </td>

        <!-- Action -->
        <td style="padding:8px 14px;vertical-align:middle;border-bottom:1px solid #f9fafb;text-align:center;">
          <?php if($item['status'] === 'in_stock'): ?>
            <a href="stock_in.php?del_item=<?= $item['id'] ?>&page=<?= $page ?>"
               style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border:1.5px solid #fecaca;border-radius:6px;color:#dc2626;font-size:11px;text-decoration:none;"
               onclick="return confirm('Remove this item?')" title="Remove">
              <i class="bi bi-trash"></i>
            </a>
          <?php else: ?>
            <span style="color:#d1d5db;">—</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<?php endwhile; ?>

<?php endif; ?>

      <!-- Pagination -->
      <?php if($total_pages > 1): ?>
      <div class="pag-wrap">
        <?php if($page > 1): ?>
          <a href="<?= sinPagUrl(1) ?>" class="pag-btn"><i class="bi bi-chevron-double-left" style="font-size:.65rem;"></i></a>
          <a href="<?= sinPagUrl($page-1) ?>" class="pag-btn"><i class="bi bi-chevron-left" style="font-size:.65rem;"></i></a>
        <?php else: ?>
          <span class="pag-btn disabled"><i class="bi bi-chevron-double-left" style="font-size:.65rem;"></i></span>
          <span class="pag-btn disabled"><i class="bi bi-chevron-left" style="font-size:.65rem;"></i></span>
        <?php endif; ?>
        <?php for($i=max(1,$page-2); $i<=min($total_pages,$page+2); $i++): ?>
          <a href="<?= sinPagUrl($i) ?>" class="pag-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if($page < $total_pages): ?>
          <a href="<?= sinPagUrl($page+1) ?>" class="pag-btn"><i class="bi bi-chevron-right" style="font-size:.65rem;"></i></a>
          <a href="<?= sinPagUrl($total_pages) ?>" class="pag-btn"><i class="bi bi-chevron-double-right" style="font-size:.65rem;"></i></a>
        <?php else: ?>
          <span class="pag-btn disabled"><i class="bi bi-chevron-right" style="font-size:.65rem;"></i></span>
          <span class="pag-btn disabled"><i class="bi bi-chevron-double-right" style="font-size:.65rem;"></i></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php endif; ?>
    </div>

  </div>
</div>

<!-- NEW STOCK IN MODAL -->
<div class="modal fade" id="stockInModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content" style="border:none;border-radius:14px;overflow:hidden;">
      <form method="POST">
        <input type="hidden" name="submit_batch" value="1">
        <div class="modal-header" style="background:#1a1a2e;border:none;">
          <h5 class="modal-title" style="color:#fff;font-size:.95rem;font-weight:700;">
            <i class="bi bi-box-arrow-in-down me-2"></i>New Stock In — Batch Entry
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3 mb-4 pb-3" style="border-bottom:1px solid #eee;">
            <div class="col-sm-6">
              <label class="form-label form-label-sm fw-semibold">Supplier <span class="text-danger">*</span></label>
              <select name="supplier_id" class="form-select form-select-sm" required>
                <option value="">— Select Supplier —</option>
                <?php $suppliers->data_seek(0); while($s=$suppliers->fetch_assoc()): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?><?= $s['company_name']?' ('.$s['company_name'].')':'' ?></option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="col-sm-3">
              <label class="form-label form-label-sm fw-semibold">Stock Date <span class="text-danger">*</span></label>
              <input type="date" name="stock_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-sm-3">
              <label class="form-label form-label-sm fw-semibold">Remarks</label>
              <input type="text" name="remarks" class="form-control form-control-sm" placeholder="Optional">
            </div>
          </div>
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="fw-semibold" style="font-size:.88rem;color:#1a1a2e;"><i class="bi bi-list-ul me-1 text-muted"></i>Items</div>
            <button type="button" class="btn btn-sm btn-outline-dark" onclick="addItemRow()"><i class="bi bi-plus-lg me-1"></i>Add Another Item</button>
          </div>
          <div id="itemsContainer">
            <div class="item-row" id="item-0">
              <div class="row g-2">
                <div class="col-sm-5">
                  <label class="form-label form-label-sm">Product <span class="text-danger">*</span></label>
                  <select name="product_id[]" class="form-select form-select-sm" required>
                    <option value="">— Product —</option>
                    <?php $products->data_seek(0); while($p=$products->fetch_assoc()): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['product_name']) ?></option>
                    <?php endwhile; ?>
                  </select>
                </div>
                <div class="col-sm-4">
                  <label class="form-label form-label-sm">Serial No <span class="text-danger">*</span></label>
                  <input type="text" name="serial_no[]" class="form-control form-control-sm" placeholder="SN-0001" required>
                </div>
                <div class="col-sm-3">
                  <label class="form-label form-label-sm">Part No <span class="text-danger">*</span></label>
                  <input type="text" name="part_no[]" class="form-control form-control-sm" placeholder="PN-0001" required>
                </div>
              </div>
            </div>
          </div>
          <div class="text-muted mt-2" style="font-size:.75rem;"><i class="bi bi-info-circle me-1"></i>Duplicate serial numbers will be skipped automatically.</div>
        </div>
        <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:14px 20px;">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-dark btn-sm px-4"><i class="bi bi-check-lg me-1"></i>Submit Stock In</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const productOptions = `<?php
  $products->data_seek(0);
  $opts = '<option value="">— Product —</option>';
  while($p=$products->fetch_assoc()){
    $opts .= '<option value="'.$p['id'].'">'.htmlspecialchars($p['product_name']).'</option>';
  }
  echo addslashes($opts);
?>`;

let rowCount = 1;
function addItemRow() {
  const container = document.getElementById('itemsContainer');
  const idx = rowCount++;
  const div = document.createElement('div');
  div.className = 'item-row';
  div.id = 'item-' + idx;
  div.innerHTML = `
    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 remove-btn" onclick="removeRow('item-${idx}')">
      <i class="bi bi-x-lg"></i>
    </button>
    <div class="row g-2">
      <div class="col-sm-5">
        <label class="form-label form-label-sm">Product <span class="text-danger">*</span></label>
        <select name="product_id[]" class="form-select form-select-sm" required>${productOptions}</select>
      </div>
      <div class="col-sm-4">
        <label class="form-label form-label-sm">Serial No <span class="text-danger">*</span></label>
        <input type="text" name="serial_no[]" class="form-control form-control-sm" placeholder="SN-000${idx+1}" required>
      </div>
      <div class="col-sm-3">
        <label class="form-label form-label-sm">Part No</label>
        <input type="text" name="part_no[]" class="form-control form-control-sm" placeholder="Optional">
      </div>
    </div>`;
  container.appendChild(div);
  div.querySelector('input[name="serial_no[]"]').focus();
}
function removeRow(id) { const el=document.getElementById(id); if(el) el.remove(); }
</script>
</body>
</html>