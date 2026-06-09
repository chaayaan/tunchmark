<?php
require_once 'mydb.php';
require_once 'auth.php';

$msg = '';
$msg_type = 'success';

// Create
if (isset($_POST['create_buyer'])) {
    $name    = $conn->real_escape_string(trim($_POST['name']));
    $company = $conn->real_escape_string(trim($_POST['company_name']));
    $phone   = $conn->real_escape_string(trim($_POST['phone']));
    $email   = $conn->real_escape_string(trim($_POST['email']));
    $address = $conn->real_escape_string(trim($_POST['address']));
    $conn->query("INSERT INTO buyers (name, company_name, phone, email, address) VALUES ('$name','$company','$phone','$email','$address')");
    header("Location: buyers.php?msg=created&page=" . (int)($_GET['page'] ?? 1)); exit;
}

// Update
if (isset($_POST['update_buyer'])) {
    $id      = (int)$_POST['id'];
    $name    = $conn->real_escape_string(trim($_POST['name']));
    $company = $conn->real_escape_string(trim($_POST['company_name']));
    $phone   = $conn->real_escape_string(trim($_POST['phone']));
    $email   = $conn->real_escape_string(trim($_POST['email']));
    $address = $conn->real_escape_string(trim($_POST['address']));
    $conn->query("UPDATE buyers SET name='$name', company_name='$company', phone='$phone', email='$email', address='$address' WHERE id=$id");
    header("Location: buyers.php?msg=updated&page=" . (int)($_POST['page'] ?? 1)); exit;
}

// Delete
if (isset($_GET['delete'])) {
    $id    = (int)$_GET['delete'];
    $check = $conn->query("SELECT COUNT(*) FROM stock_out WHERE buyer_id=$id")->fetch_row()[0];
    if ($check > 0) {
        $msg = 'Cannot delete: this buyer has existing sales records.';
        $msg_type = 'danger';
    } else {
        $conn->query("DELETE FROM buyers WHERE id=$id");
        header("Location: buyers.php?msg=deleted"); exit;
    }
}

if (isset($_GET['msg'])) {
    $msgs = ['created'=>'Buyer added successfully.','updated'=>'Buyer updated.','deleted'=>'Buyer deleted.'];
    $msg = $msgs[$_GET['msg']] ?? '';
}

// Pagination + search
$per_page = 50;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;
$search   = $conn->real_escape_string($_GET['search'] ?? '');
$where    = $search ? "WHERE name LIKE '%$search%' OR company_name LIKE '%$search%'" : '';

$count_res   = $conn->query("SELECT COUNT(*) FROM buyers $where");
$total_rows  = $count_res->fetch_row()[0];
$total_pages = (int)ceil($total_rows / $per_page);

$buyers = $conn->query("SELECT * FROM buyers $where ORDER BY name LIMIT $per_page OFFSET $offset");

function buyerPagUrl($p) {
    $q = $_GET; $q['page'] = $p;
    return '?' . http_build_query($q);
}

$active_page = 'buyers';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Buyers — Inventory</title>
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
  .i-red    { background:#fef2f2; color:#dc2626; }
  .sec-title { font-size:.9375rem; font-weight:700; color:#111827; }
  .sec-meta  { margin-left:auto; font-size:.78rem; color:#9ca3af; font-weight:500; }

  /* Table */
  .main-tbl { width:100%; border-collapse:collapse; }
  .main-tbl thead th { padding:9px 16px; background:#1a1a2e; color:#ccd6f6; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; font-weight:700; white-space:nowrap; }
  .main-tbl tbody td { padding:10px 16px; border-bottom:1px solid #f0f1f3; font-size:.875rem; vertical-align:middle; }
  .main-tbl tbody tr:last-child td { border-bottom:none; }
  .main-tbl tbody tr:hover td { background:#fafbff; }

  /* Avatar */
  .avatar { width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg,#1a1a2e,#0f3460); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.85rem; flex-shrink:0; }

  /* History sub-table inside expanded row */
  .history-tbl { width:100%; border-collapse:collapse; }
  .history-tbl thead th { padding:7px 14px; background:#f8f9fc; color:#6b7280; font-size:.7rem; text-transform:uppercase; letter-spacing:.06em; font-weight:700; border-bottom:1px solid #f0f1f3; }
  .history-tbl tbody td { padding:7px 14px; font-size:.82rem; border-bottom:1px solid #f9fafb; vertical-align:middle; }
  .history-tbl tbody tr:last-child td { border-bottom:none; }

  .badge-status { font-size:.72rem; padding:3px 9px; border-radius:20px; font-weight:600; }
  .badge-sold    { background:#fee2e2; color:#991b1b; }
  .badge-in_stock { background:#d1fae5; color:#065f46; }

  /* Expand toggle */
  .expand-btn { background:none; border:none; cursor:pointer; color:#6b7280; padding:2px 6px; border-radius:4px; transition:background .15s; }
  .expand-btn:hover { background:#f0f1f3; color:#111827; }
  .expand-row { display:none; }
  .expand-row.open { display:table-row; }

  /* Search bar */
  .search-form { display:flex; gap:6px; align-items:center; }
  .search-form input { height:32px; padding:0 10px; border:1.5px solid #e4e7ec; border-radius:6px; font-size:.82rem; color:#374151; outline:none; width:180px; }
  .search-form input:focus { border-color:#2563eb; }
  .search-form button { height:32px; padding:0 12px; background:#1a1a2e; color:#fff; border:none; border-radius:6px; font-size:.82rem; font-weight:600; cursor:pointer; }

  /* Topbar btn */
  .tb-btn { display:inline-flex; align-items:center; gap:6px; height:32px; padding:0 14px; border-radius:6px; font-size:.82rem; font-weight:600; text-decoration:none; cursor:pointer; transition:all .15s; border:1.5px solid transparent; white-space:nowrap; }
  .tb-btn-dark { background:#1a1a2e; color:#fff; border-color:#1a1a2e; }
  .tb-btn-dark:hover { background:#2d2d4e; color:#fff; }

  /* Action btns */
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

  /* Modal */
  .modal-dialog { max-width:560px; }

  @media(max-width:991.98px){ .page-shell{ margin-left:0; } .top-bar{ top:52px; } }
</style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="page-shell">

  <header class="top-bar">
    <div class="tb-ico"><i class="bi bi-people"></i></div>
    <div>
      <div class="tb-title">Buyers</div>
      <div class="tb-sub">Customer records</div>
    </div>
    <div class="tb-right">
      <form method="GET" class="search-form">
        <input type="text" name="search" placeholder="Search buyers..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit">Go</button>
        <?php if($search): ?>
          <a href="buyers.php" style="height:32px;display:inline-flex;align-items:center;padding:0 10px;border:1.5px solid #e4e7ec;border-radius:6px;font-size:.82rem;color:#6b7280;text-decoration:none;">Clear</a>
        <?php endif; ?>
      </form>
      <div class="page-badge">
        p.<?= $page ?>/<?= max(1,$total_pages) ?> &nbsp;·&nbsp; <?= $total_rows ?> buyers
      </div>
      <button class="tb-btn tb-btn-dark" data-bs-toggle="modal" data-bs-target="#buyerModal" onclick="openAddModal()">
        <i class="bi bi-plus-lg"></i> Add Buyer
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
        <span class="sec-ico i-blue"><i class="bi bi-person-lines-fill"></i></span>
        <span class="sec-title">All Buyers</span>
        <span class="sec-meta">Showing <?= $buyers->num_rows ?> of <?= $total_rows ?></span>
      </div>

      <?php if($buyers->num_rows === 0): ?>
        <div style="padding:56px 24px;text-align:center;color:#9ca3af;">
          <i class="bi bi-people" style="font-size:2.8rem;display:block;margin-bottom:12px;"></i>
          <div style="font-weight:700;font-size:.9375rem;">No buyers found</div>
          <?php if($search): ?><div style="font-size:.82rem;margin-top:4px;">No results for "<?= htmlspecialchars($search) ?>"</div><?php endif; ?>
        </div>
      <?php else: ?>

      <div style="overflow-x:auto;">
        <table class="main-tbl">
          <thead>
            <tr>
              <th style="width:36px;"></th>
              <th>Buyer</th>
              <th>Phone</th>
              <th>Email</th>
              <th style="text-align:center;">Sales</th>
              <th style="text-align:center;">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php $buyers->data_seek(0); while($b = $buyers->fetch_assoc()):
            // Get sale batches (stock_out rows) for this buyer
            $so_res = $conn->query("
                SELECT so.id, so.sale_date, so.remarks,
                       COUNT(pi.id) AS item_count
                FROM stock_out so
                LEFT JOIN product_items pi ON pi.stock_out_id = so.id
                WHERE so.buyer_id = {$b['id']}
                GROUP BY so.id
                ORDER BY so.sale_date DESC
            ");
            $sales_count = $so_res->num_rows;
          ?>
          <tr>
            <td style="text-align:center;">
              <button class="expand-btn" onclick="toggleHistory(<?= $b['id'] ?>)" title="View purchase history">
                <i class="bi bi-chevron-right" id="chevron-<?= $b['id'] ?>"></i>
              </button>
            </td>
            <td>
              <div style="display:flex;align-items:center;gap:10px;">
                <div class="avatar"><?= strtoupper(substr($b['name'],0,1)) ?></div>
                <div>
                  <div style="font-weight:600;"><?= htmlspecialchars($b['name']) ?></div>
                  <?php if($b['company_name']): ?><div style="font-size:.75rem;color:#9ca3af;"><?= htmlspecialchars($b['company_name']) ?></div><?php endif; ?>
                  <?php if($b['address']): ?><div style="font-size:.72rem;color:#9ca3af;"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($b['address']) ?></div><?php endif; ?>
                </div>
              </div>
            </td>
            <td style="font-size:.82rem;color:#374151;font-family:monospace;"><?= $b['phone'] ? htmlspecialchars($b['phone']) : '<span style="color:#9ca3af;">—</span>' ?></td>
            <td style="font-size:.82rem;color:#374151;"><?= $b['email'] ? htmlspecialchars($b['email']) : '<span style="color:#9ca3af;">—</span>' ?></td>
            <td style="text-align:center;">
              <?php if($sales_count > 0): ?>
                <span style="background:#fee2e2;color:#991b1b;font-size:.7rem;font-weight:700;padding:3px 9px;border-radius:12px;"><?= $sales_count ?> sale<?= $sales_count!=1?'s':'' ?></span>
              <?php else: ?>
                <span style="color:#9ca3af;">—</span>
              <?php endif; ?>
            </td>
            <td style="text-align:center;">
              <div style="display:flex;gap:6px;justify-content:center;">
                <button type="button" class="btn-action btn-edit"
                  onclick="openEditModal(
                    <?= $b['id'] ?>,
                    <?= htmlspecialchars(json_encode($b['name'])) ?>,
                    <?= htmlspecialchars(json_encode($b['company_name'] ?? '')) ?>,
                    <?= htmlspecialchars(json_encode($b['phone'] ?? '')) ?>,
                    <?= htmlspecialchars(json_encode($b['email'] ?? '')) ?>,
                    <?= htmlspecialchars(json_encode($b['address'] ?? '')) ?>,
                    <?= $page ?>
                  )" title="Edit"><i class="bi bi-pencil"></i></button>
                <a href="buyers.php?delete=<?= $b['id'] ?>&page=<?= $page ?><?= $search ? '&search='.urlencode($search) : '' ?>"
                   class="btn-action btn-del"
                   onclick="return confirm('Delete this buyer?')" title="Delete"><i class="bi bi-trash"></i></a>
              </div>
            </td>
          </tr>
          <!-- Expandable history row -->
          <tr class="expand-row" id="history-<?= $b['id'] ?>">
            <td></td>
            <td colspan="5" style="padding:0;background:#fafbfc;">
              <?php if($sales_count === 0): ?>
                <div style="padding:14px 16px;color:#9ca3af;font-size:.82rem;"><i class="bi bi-cart-x me-1"></i>No purchase history yet.</div>
              <?php else: ?>
              <table class="history-tbl">
                <thead>
                  <tr>
                    <th>Sale #</th>
                    <th>Date</th>
                    <th>Items</th>
                    <th>Products / Serials</th>
                    <th>Remarks</th>
                    <th>Invoice</th>
                  </tr>
                </thead>
                <tbody>
                <?php $so_res->data_seek(0); while($so = $so_res->fetch_assoc()):
                  // Get items for this sale batch
                  $items_res = $conn->query("
                      SELECT pi.serial_no, pi.part_no, p.product_name
                      FROM product_items pi
                      JOIN products p ON p.id = pi.product_id
                      WHERE pi.stock_out_id = {$so['id']}
                  ");
                ?>
                <tr>
                  <td style="font-family:monospace;font-size:.78rem;color:#6b7280;">#<?= $so['id'] ?></td>
                  <td style="font-size:.82rem;"><?= date('d M Y', strtotime($so['sale_date'])) ?></td>
                  <td style="text-align:center;">
                    <span style="background:#e0f2fe;color:#0369a1;font-size:.7rem;font-weight:700;padding:2px 8px;border-radius:10px;"><?= $so['item_count'] ?></span>
                  </td>
                  <td>
                    <?php while($it = $items_res->fetch_assoc()): ?>
                      <div style="font-size:.78rem;color:#374151;">
                        <strong><?= htmlspecialchars($it['product_name']) ?></strong>
                        — <code style="font-size:.75rem;"><?= htmlspecialchars($it['serial_no']) ?></code>
                        <?= $it['part_no'] ? '<span style="color:#9ca3af;"> / '.htmlspecialchars($it['part_no']).'</span>' : '' ?>
                      </div>
                    <?php endwhile; ?>
                  </td>
                  <td style="font-size:.78rem;color:#9ca3af;"><?= $so['remarks'] ? htmlspecialchars($so['remarks']) : '—' ?></td>
                  <td>
                    <a href="invoice.php?id=<?= $so['id'] ?>" target="_blank"
                       style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border:1.5px solid #e4e7ec;border-radius:6px;color:#6b7280;font-size:11px;text-decoration:none;">
                      <i class="bi bi-printer"></i>
                    </a>
                  </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
              </table>
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
          <a href="<?= buyerPagUrl(1) ?>" class="pag-btn"><i class="bi bi-chevron-double-left" style="font-size:.65rem;"></i></a>
          <a href="<?= buyerPagUrl($page-1) ?>" class="pag-btn"><i class="bi bi-chevron-left" style="font-size:.65rem;"></i></a>
        <?php else: ?>
          <span class="pag-btn disabled"><i class="bi bi-chevron-double-left" style="font-size:.65rem;"></i></span>
          <span class="pag-btn disabled"><i class="bi bi-chevron-left" style="font-size:.65rem;"></i></span>
        <?php endif; ?>
        <?php for($i=max(1,$page-2); $i<=min($total_pages,$page+2); $i++): ?>
          <a href="<?= buyerPagUrl($i) ?>" class="pag-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if($page < $total_pages): ?>
          <a href="<?= buyerPagUrl($page+1) ?>" class="pag-btn"><i class="bi bi-chevron-right" style="font-size:.65rem;"></i></a>
          <a href="<?= buyerPagUrl($total_pages) ?>" class="pag-btn"><i class="bi bi-chevron-double-right" style="font-size:.65rem;"></i></a>
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

<!-- Add / Edit Buyer Modal -->
<div class="modal fade" id="buyerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:none;border-radius:14px;overflow:hidden;">
      <form method="POST">
        <input type="hidden" name="modal_mode" id="modal_mode" value="create">
        <input type="hidden" name="id" id="modal_id" value="">
        <input type="hidden" name="page" id="modal_page" value="<?= $page ?>">
        <div class="modal-header" style="background:#1a1a2e;border:none;">
          <h5 class="modal-title" style="color:#fff;font-size:.95rem;font-weight:700;">
            <i class="bi bi-people me-2"></i><span id="modal_title_text">Add New Buyer</span>
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label form-label-sm fw-semibold">Name <span class="text-danger">*</span></label>
              <input type="text" name="name" id="modal_name" class="form-control form-control-sm" required>
            </div>
            <div class="col-sm-6">
              <label class="form-label form-label-sm fw-semibold">Company Name</label>
              <input type="text" name="company_name" id="modal_company" class="form-control form-control-sm">
            </div>
            <div class="col-sm-6">
              <label class="form-label form-label-sm fw-semibold">Phone</label>
              <input type="text" name="phone" id="modal_phone" class="form-control form-control-sm">
            </div>
            <div class="col-sm-6">
              <label class="form-label form-label-sm fw-semibold">Email</label>
              <input type="email" name="email" id="modal_email" class="form-control form-control-sm">
            </div>
            <div class="col-12">
              <label class="form-label form-label-sm fw-semibold">Address</label>
              <textarea name="address" id="modal_address" class="form-control form-control-sm" rows="2"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:14px 20px;">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-dark btn-sm px-4" id="modal_submit_btn">
            <i class="bi bi-check-lg me-1"></i>Add Buyer
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleHistory(id) {
  const row = document.getElementById('history-' + id);
  const chevron = document.getElementById('chevron-' + id);
  const isOpen = row.classList.contains('open');
  row.classList.toggle('open', !isOpen);
  chevron.className = isOpen ? 'bi bi-chevron-right' : 'bi bi-chevron-down';
}

function openAddModal() {
  document.getElementById('modal_mode').name = 'create_buyer';
  document.getElementById('modal_id').value = '';
  ['name','company','phone','email','address'].forEach(f => document.getElementById('modal_'+f).value = '');
  document.getElementById('modal_title_text').textContent = 'Add New Buyer';
  document.getElementById('modal_submit_btn').innerHTML = '<i class="bi bi-check-lg me-1"></i>Add Buyer';
}

function openEditModal(id, name, company, phone, email, address, page) {
  document.getElementById('modal_mode').name = 'update_buyer';
  document.getElementById('modal_id').value = id;
  document.getElementById('modal_page').value = page;
  document.getElementById('modal_name').value = name;
  document.getElementById('modal_company').value = company;
  document.getElementById('modal_phone').value = phone;
  document.getElementById('modal_email').value = email;
  document.getElementById('modal_address').value = address;
  document.getElementById('modal_title_text').textContent = 'Edit Buyer';
  document.getElementById('modal_submit_btn').innerHTML = '<i class="bi bi-check-lg me-1"></i>Update Buyer';
  new bootstrap.Modal(document.getElementById('buyerModal')).show();
}
</script>
</body>
</html>