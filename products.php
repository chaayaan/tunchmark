<?php
require_once 'mydb.php';
require_once 'auth.php';

$msg = '';
$msg_type = 'success';

// Create
if (isset($_POST['create_product'])) {
    $name = $conn->real_escape_string(trim($_POST['product_name']));
    $desc = $conn->real_escape_string(trim($_POST['description']));
    $conn->query("INSERT INTO products (product_name, description) VALUES ('$name','$desc')");
    header("Location: products.php?msg=created&page=" . (int)($_GET['page'] ?? 1)); exit;
}

// Update
if (isset($_POST['update_product'])) {
    $id   = (int)$_POST['id'];
    $name = $conn->real_escape_string(trim($_POST['product_name']));
    $desc = $conn->real_escape_string(trim($_POST['description']));
    $conn->query("UPDATE products SET product_name='$name', description='$desc' WHERE id=$id");
    header("Location: products.php?msg=updated&page=" . (int)($_POST['page'] ?? 1)); exit;
}

// Delete
if (isset($_GET['delete'])) {
    $id    = (int)$_GET['delete'];
    $check = $conn->query("SELECT COUNT(*) FROM product_items WHERE product_id=$id")->fetch_row()[0];
    if ($check > 0) {
        $msg = 'Cannot delete: this product has existing inventory items.';
        $msg_type = 'danger';
    } else {
        $conn->query("DELETE FROM products WHERE id=$id");
        header("Location: products.php?msg=deleted"); exit;
    }
}

if (isset($_GET['msg'])) {
    $msgs = ['created'=>'Product added successfully.','updated'=>'Product updated.','deleted'=>'Product deleted.'];
    $msg = $msgs[$_GET['msg']] ?? '';
}

// Pagination + search
$per_page = 50;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;
$search   = $conn->real_escape_string($_GET['search'] ?? '');
$where    = $search ? "WHERE p.product_name LIKE '%$search%'" : '';

$count_res   = $conn->query("SELECT COUNT(*) FROM products p $where");
$total_rows  = $count_res->fetch_row()[0];
$total_pages = (int)ceil($total_rows / $per_page);

$products = $conn->query("
    SELECT p.*,
        COUNT(pi.id)              AS total_units,
        SUM(pi.status='in_stock') AS in_stock,
        SUM(pi.status='sold')     AS sold,
        SUM(pi.status='damaged')  AS damaged,
        SUM(pi.status='returned') AS returned
    FROM products p
    LEFT JOIN product_items pi ON pi.product_id = p.id
    $where
    GROUP BY p.id
    ORDER BY p.product_name
    LIMIT $per_page OFFSET $offset
");

function prodPagUrl($p) {
    $q = $_GET; $q['page'] = $p;
    return '?' . http_build_query($q);
}

$active_page = 'products';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Products — Inventory</title>
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
  .tb-sub { font-size:.8rem; color:#9ca3af; }
  .tb-right { margin-left:auto; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
  .page-badge { display:inline-flex; align-items:center; gap:5px; background:#fafbfc; border:1px solid #e4e7ec; border-radius:6px; padding:4px 12px; font-size:.78rem; font-weight:500; color:#6b7280; }
  .main { flex:1; padding:20px 22px 60px; display:flex; flex-direction:column; gap:14px; }
  .sec { background:#fff; border:1px solid #e4e7ec; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.06); overflow:hidden; }
  .sec-head { display:flex; align-items:center; gap:9px; padding:12px 18px; background:#fafbfc; border-bottom:1px solid #f0f1f3; }
  .sec-ico { width:28px; height:28px; border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:12px; flex-shrink:0; }
  .i-blue   { background:#eff6ff; color:#2563eb; }
  .i-green  { background:#ecfdf5; color:#059669; }
  .i-red    { background:#fef2f2; color:#dc2626; }
  .i-amber  { background:#fffbeb; color:#d97706; }
  .i-violet { background:#f5f3ff; color:#7c3aed; }
  .sec-title { font-size:.9375rem; font-weight:700; color:#111827; }
  .sec-meta  { margin-left:auto; font-size:.78rem; color:#9ca3af; font-weight:500; }

  /* Table */
  .main-tbl { width:100%; border-collapse:collapse; }
  .main-tbl thead th { padding:9px 16px; background:#1a1a2e; color:#ccd6f6; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; font-weight:700; white-space:nowrap; }
  .main-tbl tbody td { padding:10px 16px; border-bottom:1px solid #f0f1f3; font-size:.875rem; vertical-align:middle; }
  .main-tbl tbody tr:last-child td { border-bottom:none; }
  .main-tbl tbody tr:hover td { background:#fafbff; }

  /* Prod icon */
  .prod-icon { width:36px; height:36px; border-radius:8px; background:linear-gradient(135deg,#0f3460,#1a1a6e); color:#fff; display:flex; align-items:center; justify-content:center; font-size:.85rem; flex-shrink:0; }

  /* Expand */
  .expand-btn { background:none; border:none; cursor:pointer; color:#6b7280; padding:2px 6px; border-radius:4px; transition:background .15s; }
  .expand-btn:hover { background:#f0f1f3; color:#111827; }
  .expand-row { display:none; }
  .expand-row.open { display:table-row; }

  /* Items sub-table */
  .items-tbl { width:100%; border-collapse:collapse; }
  .items-tbl thead th { padding:7px 14px; background:#f8f9fc; color:#6b7280; font-size:.7rem; text-transform:uppercase; letter-spacing:.06em; font-weight:700; border-bottom:1px solid #f0f1f3; }
  .items-tbl tbody td { padding:7px 14px; font-size:.82rem; border-bottom:1px solid #f9fafb; vertical-align:middle; }
  .items-tbl tbody tr:last-child td { border-bottom:none; }

  /* Stat mini-pills */
  .mini-pill { font-size:.68rem; padding:2px 7px; border-radius:10px; font-weight:600; display:inline-block; white-space:nowrap; }
  .mp-in    { background:#d1fae5; color:#065f46; }
  .mp-sold  { background:#fee2e2; color:#991b1b; }
  .mp-dmg   { background:#fef3c7; color:#92400e; }
  .mp-ret   { background:#e0e7ff; color:#3730a3; }

  /* Badge status */
  .badge-status { font-size:.72rem; padding:3px 9px; border-radius:20px; font-weight:600; }
  .badge-in_stock  { background:#d1fae5; color:#065f46; }
  .badge-sold      { background:#fee2e2; color:#991b1b; }
  .badge-damaged   { background:#fef3c7; color:#92400e; }
  .badge-returned  { background:#e0e7ff; color:#3730a3; }

  /* Search */
  .search-form { display:flex; gap:6px; align-items:center; }
  .search-form input { height:32px; padding:0 10px; border:1.5px solid #e4e7ec; border-radius:6px; font-size:.82rem; color:#374151; outline:none; width:180px; }
  .search-form input:focus { border-color:#2563eb; }
  .search-form button { height:32px; padding:0 12px; background:#1a1a2e; color:#fff; border:none; border-radius:6px; font-size:.82rem; font-weight:600; cursor:pointer; }

  .tb-btn { display:inline-flex; align-items:center; gap:6px; height:32px; padding:0 14px; border-radius:6px; font-size:.82rem; font-weight:600; text-decoration:none; cursor:pointer; transition:all .15s; border:1.5px solid transparent; white-space:nowrap; }
  .tb-btn-dark { background:#1a1a2e; color:#fff; border-color:#1a1a2e; }
  .tb-btn-dark:hover { background:#2d2d4e; color:#fff; }

  .btn-action { width:28px; height:28px; display:inline-flex; align-items:center; justify-content:center; border-radius:6px; font-size:12px; cursor:pointer; transition:all .15s; text-decoration:none; border:1.5px solid; }
  .btn-edit  { background:#eff6ff; border-color:#bfdbfe; color:#2563eb; }
  .btn-edit:hover  { background:#2563eb; color:#fff; }
  .btn-del   { background:#fef2f2; border-color:#fecaca; color:#dc2626; }
  .btn-del:hover   { background:#dc2626; color:#fff; }

  /* Pagination */
  .pag-wrap { display:flex; align-items:center; justify-content:center; gap:4px; padding:14px 18px; border-top:1px solid #e4e7ec; background:#fafbfc; }
  .pag-btn { display:inline-flex; align-items:center; justify-content:center; min-width:34px; height:34px; padding:0 8px; border:1.5px solid #e4e7ec; border-radius:6px; font-size:.82rem; font-weight:600; color:#374151; background:#fff; text-decoration:none; transition:all .15s; }
  .pag-btn:hover  { background:#eff6ff; border-color:#bfdbfe; color:#2563eb; }
  .pag-btn.active { background:#2563eb; border-color:#2563eb; color:#fff; }
  .pag-btn.disabled { background:#fafbfc; color:#9ca3af; pointer-events:none; }

  .modal-dialog { max-width:500px; }

  @media(max-width:991.98px){ .page-shell{ margin-left:0; } .top-bar{ top:52px; } }
</style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="page-shell">

  <header class="top-bar">
    <div class="tb-ico"><i class="bi bi-gear"></i></div>
    <div>
      <div class="tb-title">Products</div>
      <div class="tb-sub">Product catalog &amp; stock levels</div>
    </div>
    <div class="tb-right">
      <form method="GET" class="search-form">
        <input type="text" name="search" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit">Go</button>
        <?php if($search): ?>
          <a href="products.php" style="height:32px;display:inline-flex;align-items:center;padding:0 10px;border:1.5px solid #e4e7ec;border-radius:6px;font-size:.82rem;color:#6b7280;text-decoration:none;">Clear</a>
        <?php endif; ?>
      </form>
      <div class="page-badge">
        p.<?= $page ?>/<?= max(1,$total_pages) ?> &nbsp;·&nbsp; <?= $total_rows ?> products
      </div>
      <button class="tb-btn tb-btn-dark" data-bs-toggle="modal" data-bs-target="#productModal" onclick="openAddModal()">
        <i class="bi bi-plus-lg"></i> Add Product
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
        <span class="sec-ico i-blue"><i class="bi bi-gear-wide-connected"></i></span>
        <span class="sec-title">All Products</span>
        <span class="sec-meta">Showing <?= $products->num_rows ?> of <?= $total_rows ?></span>
      </div>

      <?php if($products->num_rows === 0): ?>
        <div style="padding:56px 24px;text-align:center;color:#9ca3af;">
          <i class="bi bi-gear" style="font-size:2.8rem;display:block;margin-bottom:12px;"></i>
          <div style="font-weight:700;font-size:.9375rem;">No products found</div>
          <?php if($search): ?><div style="font-size:.82rem;margin-top:4px;">No results for "<?= htmlspecialchars($search) ?>"</div><?php endif; ?>
        </div>
      <?php else: ?>

      <div style="overflow-x:auto;">
        <table class="main-tbl">
          <thead>
            <tr>
              <th style="width:36px;"></th>
              <th>Product</th>
              <th style="text-align:center;">Total Units</th>
              <th style="text-align:center;">In Stock</th>
              <th style="text-align:center;">Sold</th>
              <th style="text-align:center;">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php $products->data_seek(0); while($p = $products->fetch_assoc()):

            // Recent items for expand (limit 20 for performance)
            $items_res = $conn->query("
                SELECT pi.id, pi.serial_no, pi.part_no, pi.status, pi.in_date, pi.out_date,
                       s.name AS supplier_name
                FROM product_items pi
                JOIN stock_in si ON si.id = pi.stock_in_id
                JOIN suppliers s ON s.id = si.supplier_id
                WHERE pi.product_id = {$p['id']}
                ORDER BY pi.created_at DESC
                LIMIT 20
            ");
            $items_count = $items_res->num_rows;
          ?>
          <tr>
            <td style="text-align:center;">
              <button class="expand-btn" onclick="toggleItems(<?= $p['id'] ?>)" title="View units">
                <i class="bi bi-chevron-right" id="chevron-<?= $p['id'] ?>"></i>
              </button>
            </td>
            <td>
              <div style="display:flex;align-items:center;gap:10px;">
                <div class="prod-icon"><i class="bi bi-gear"></i></div>
                <div>
                  <div style="font-weight:600;"><?= htmlspecialchars($p['product_name']) ?></div>
                  <?php if($p['description']): ?>
                    <div style="font-size:.75rem;color:#9ca3af;max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($p['description']) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td style="text-align:center;font-weight:700;font-size:.875rem;"><?= $p['total_units'] ?: '—' ?></td>
            <td style="text-align:center;">
              <?php if($p['in_stock'] > 0): ?>
                <span class="mini-pill mp-in"><?= $p['in_stock'] ?></span>
              <?php else: ?>
                <span style="color:#9ca3af;">0</span>
              <?php endif; ?>
            </td>
            <td style="text-align:center;">
              <?php if($p['sold'] > 0): ?>
                <span class="mini-pill mp-sold"><?= $p['sold'] ?></span>
              <?php else: ?>
                <span style="color:#9ca3af;">—</span>
              <?php endif; ?>
            </td>
            <td style="text-align:center;">
              <div style="display:flex;gap:6px;justify-content:center;">
                <button type="button" class="btn-action btn-edit"
                  onclick="openEditModal(
                    <?= $p['id'] ?>,
                    <?= htmlspecialchars(json_encode($p['product_name'])) ?>,
                    <?= htmlspecialchars(json_encode($p['description'] ?? '')) ?>,
                    <?= $page ?>
                  )" title="Edit"><i class="bi bi-pencil"></i></button>
                <a href="products.php?delete=<?= $p['id'] ?>&page=<?= $page ?><?= $search ? '&search='.urlencode($search) : '' ?>"
                   class="btn-action btn-del"
                   onclick="return confirm('Delete this product?')" title="Delete"><i class="bi bi-trash"></i></a>
              </div>
            </td>
          </tr>
          <!-- Expandable items row -->
          <tr class="expand-row" id="items-<?= $p['id'] ?>">
            <td></td>
            <td colspan="6" style="padding:0;background:#fafbfc;">
              <?php if($items_count === 0): ?>
                <div style="padding:14px 16px;color:#9ca3af;font-size:.82rem;">
                  <i class="bi bi-box me-1"></i>No units stocked yet.
                  <a href="stock_in.php" style="color:#2563eb;text-decoration:none;font-weight:600;margin-left:6px;">Add stock →</a>
                </div>
              <?php else: ?>
              <div style="padding:10px 14px 4px;display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:.75rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;">
                  Units (showing <?= $items_count ?><?= $p['total_units'] > 20 ? ' of '.$p['total_units'] : '' ?>)
                </span>
                <?php if($p['damaged'] > 0 || $p['returned'] > 0): ?>
                <div style="display:flex;gap:4px;">
                  <?php if($p['damaged'] > 0): ?><span class="mini-pill mp-dmg">Damaged: <?= $p['damaged'] ?></span><?php endif; ?>
                  <?php if($p['returned'] > 0): ?><span class="mini-pill mp-ret">Returned: <?= $p['returned'] ?></span><?php endif; ?>
                </div>
                <?php endif; ?>
              </div>
              <table class="items-tbl">
                <thead>
                  <tr>
                    <th>Serial No</th>
                    <th>Part No</th>
                    <th>Supplier</th>
                    <th>In Date</th>
                    <th>Out Date</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                <?php $items_res->data_seek(0); while($item = $items_res->fetch_assoc()): ?>
                <tr>
                  <td><code style="font-size:.78rem;"><?= htmlspecialchars($item['serial_no']) ?></code></td>
                  <td style="color:#374151;"><?= $item['part_no'] ? htmlspecialchars($item['part_no']) : '<span style="color:#9ca3af;">—</span>' ?></td>
                  <td style="font-size:.78rem;color:#6b7280;"><?= htmlspecialchars($item['supplier_name']) ?></td>
                  <td style="font-size:.78rem;color:#6b7280;"><?= $item['in_date'] ? date('d M Y', strtotime($item['in_date'])) : '—' ?></td>
                  <td style="font-size:.78rem;color:#6b7280;"><?= $item['out_date'] ? date('d M Y', strtotime($item['out_date'])) : '<span style="color:#9ca3af;">—</span>' ?></td>
                  <td><span class="badge-status badge-<?= $item['status'] ?>"><?= $item['status'] ?></span></td>
                  <td>
                    <?php if($item['status'] === 'in_stock'): ?>
                      <a href="stock_out.php" style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border:1.5px solid #fecaca;border-radius:5px;color:#dc2626;font-size:10px;text-decoration:none;" title="Record sale">
                        <i class="bi bi-bag-check"></i>
                      </a>
                    <?php else: ?>
                      <span style="color:#9ca3af;">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
              </table>
              <?php if($p['total_units'] > 20): ?>
                <div style="padding:8px 14px;font-size:.75rem;color:#9ca3af;">
                  <i class="bi bi-info-circle me-1"></i>Showing most recent 20 of <?= $p['total_units'] ?> units.
                </div>
              <?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if($total_pages > 1): ?>
      <div class="pag-wrap">
        <?php if($page > 1): ?>
          <a href="<?= prodPagUrl(1) ?>" class="pag-btn"><i class="bi bi-chevron-double-left" style="font-size:.65rem;"></i></a>
          <a href="<?= prodPagUrl($page-1) ?>" class="pag-btn"><i class="bi bi-chevron-left" style="font-size:.65rem;"></i></a>
        <?php else: ?>
          <span class="pag-btn disabled"><i class="bi bi-chevron-double-left" style="font-size:.65rem;"></i></span>
          <span class="pag-btn disabled"><i class="bi bi-chevron-left" style="font-size:.65rem;"></i></span>
        <?php endif; ?>
        <?php for($i=max(1,$page-2); $i<=min($total_pages,$page+2); $i++): ?>
          <a href="<?= prodPagUrl($i) ?>" class="pag-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if($page < $total_pages): ?>
          <a href="<?= prodPagUrl($page+1) ?>" class="pag-btn"><i class="bi bi-chevron-right" style="font-size:.65rem;"></i></a>
          <a href="<?= prodPagUrl($total_pages) ?>" class="pag-btn"><i class="bi bi-chevron-double-right" style="font-size:.65rem;"></i></a>
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

<!-- Add / Edit Product Modal -->
<div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:none;border-radius:14px;overflow:hidden;">
      <form method="POST">
        <input type="hidden" name="modal_mode" id="modal_mode" value="create">
        <input type="hidden" name="id" id="modal_id" value="">
        <input type="hidden" name="page" id="modal_page" value="<?= $page ?>">
        <div class="modal-header" style="background:#1a1a2e;border:none;">
          <h5 class="modal-title" style="color:#fff;font-size:.95rem;font-weight:700;">
            <i class="bi bi-gear me-2"></i><span id="modal_title_text">Add New Product</span>
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label form-label-sm fw-semibold">Product Name <span class="text-danger">*</span></label>
            <input type="text" name="product_name" id="modal_product_name" class="form-control form-control-sm" required placeholder="e.g. Generator 5kW">
          </div>
          <div>
            <label class="form-label form-label-sm fw-semibold">Description</label>
            <textarea name="description" id="modal_description" class="form-control form-control-sm" rows="3" placeholder="Optional details..."></textarea>
          </div>
        </div>
        <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:14px 20px;">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-dark btn-sm px-4" id="modal_submit_btn">
            <i class="bi bi-check-lg me-1"></i>Add Product
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleItems(id) {
  const row = document.getElementById('items-' + id);
  const chevron = document.getElementById('chevron-' + id);
  const isOpen = row.classList.contains('open');
  row.classList.toggle('open', !isOpen);
  chevron.className = isOpen ? 'bi bi-chevron-right' : 'bi bi-chevron-down';
}

function openAddModal() {
  document.getElementById('modal_mode').name = 'create_product';
  document.getElementById('modal_id').value = '';
  document.getElementById('modal_product_name').value = '';
  document.getElementById('modal_description').value = '';
  document.getElementById('modal_title_text').textContent = 'Add New Product';
  document.getElementById('modal_submit_btn').innerHTML = '<i class="bi bi-check-lg me-1"></i>Add Product';
}

function openEditModal(id, name, desc, page) {
  document.getElementById('modal_mode').name = 'update_product';
  document.getElementById('modal_id').value = id;
  document.getElementById('modal_page').value = page;
  document.getElementById('modal_product_name').value = name;
  document.getElementById('modal_description').value = desc;
  document.getElementById('modal_title_text').textContent = 'Edit Product';
  document.getElementById('modal_submit_btn').innerHTML = '<i class="bi bi-check-lg me-1"></i>Update Product';
  new bootstrap.Modal(document.getElementById('productModal')).show();
}
</script>
</body>
</html>