<?php require_once 'mydb.php';
require_once 'auth.php';
include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inventory Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
  :root { --primary:#1a1a2e; --accent:#e94560; }
  body { background:#f0f2f5; font-family:'Segoe UI',sans-serif; margin:0; }
  .page-shell { margin-left:200px; min-height:100vh; display:flex; flex-direction:column; }
  .top-bar { position:sticky; top:0; z-index:200; height:54px; background:#fff; border-bottom:1px solid #e9ecef; box-shadow:0 1px 3px rgba(0,0,0,.06); display:flex; align-items:center; padding:0 22px; gap:10px; flex-shrink:0; }
  .tb-ico { width:32px; height:32px; background:#eff6ff; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#2563eb; font-size:13px; flex-shrink:0; }
  .tb-title { font-size:1.0625rem; font-weight:700; color:#111827; }
  .tb-sub { font-size:.8rem; color:#9ca3af; }
  .main { flex:1; padding:20px 22px 60px; display:flex; flex-direction:column; gap:14px; }

  .card { border:none; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,.07); }
  .stat-card { border-radius:12px; padding:20px 24px; color:#fff; position:relative; overflow:hidden; }
  .stat-card .icon { font-size:2.4rem; opacity:.25; position:absolute; right:18px; top:14px; }
  .stat-card h2 { font-size:2rem; font-weight:800; margin:0; }
  .stat-card p { margin:0; font-size:.8rem; opacity:.85; text-transform:uppercase; letter-spacing:.5px; }
  .bg-inv { background:linear-gradient(135deg,#0f3460,#1a1a6e); }
  .bg-in  { background:linear-gradient(135deg,#0ead69,#06795c); }
  .bg-out { background:linear-gradient(135deg,#e94560,#a01535); }
  .bg-dmg { background:linear-gradient(135deg,#f5a623,#c07d0e); }
  .section-title { font-weight:700; font-size:.8rem; text-transform:uppercase; letter-spacing:1px; color:#6c757d; margin-bottom:12px; }
  .table thead th { background:var(--primary); color:#ccd6f6; font-size:.78rem; text-transform:uppercase; letter-spacing:.5px; border:none; }
  .table tbody td { font-size:.88rem; vertical-align:middle; }
  .badge-status { font-size:.72rem; padding:4px 10px; border-radius:20px; font-weight:600; }
  .badge-in_stock { background:#d1fae5; color:#065f46; }
  .badge-sold { background:#fee2e2; color:#991b1b; }
  .badge-damaged { background:#fef3c7; color:#92400e; }

  @media(max-width:991.98px){ .page-shell { margin-left:0; } .top-bar { top:52px; } }
</style>
</head>
<body>
<div class="page-shell">

  <header class="top-bar">
    <div class="tb-ico"><i class="bi bi-speedometer2"></i></div>
    <div>
      <div class="tb-title">Inventory Dashboard</div>
      <div class="tb-sub">In & Out overview</div>
    </div>
  </header>

  <div class="main">

<?php
$total    = $conn->query("SELECT COUNT(*) FROM product_items")->fetch_row()[0];
$in_stock = $conn->query("SELECT COUNT(*) FROM product_items WHERE status='in_stock'")->fetch_row()[0];
$sold     = $conn->query("SELECT COUNT(*) FROM product_items WHERE status='sold'")->fetch_row()[0];
$damaged  = $conn->query("SELECT COUNT(*) FROM product_items WHERE status='damaged'")->fetch_row()[0];
$products_count  = $conn->query("SELECT COUNT(*) FROM products")->fetch_row()[0];
$suppliers_count = $conn->query("SELECT COUNT(*) FROM suppliers")->fetch_row()[0];
$buyers_count    = $conn->query("SELECT COUNT(*) FROM buyers")->fetch_row()[0];

// Updated query: join via pi.stock_out_id (new schema)
$sales = $conn->query("
  SELECT so.sale_date, p.product_name, pi.serial_no, b.name AS buyer_name, b.company_name
  FROM stock_out so
  JOIN product_items pi ON pi.stock_out_id = so.id
  JOIN products p ON p.id = pi.product_id
  JOIN buyers b ON b.id = so.buyer_id
  ORDER BY so.created_at DESC LIMIT 8
");

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where  = $search ? "WHERE p.product_name LIKE '%".$conn->real_escape_string($search)."%'" : '';
$summary = $conn->query("
  SELECT p.id, p.product_name,
    COUNT(pi.id) AS total,
    SUM(pi.status='in_stock') AS in_stock,
    SUM(pi.status='sold') AS sold,
    SUM(pi.status='damaged') AS damaged
  FROM products p
  LEFT JOIN product_items pi ON pi.product_id=p.id
  $where
  GROUP BY p.id, p.product_name
  ORDER BY p.product_name
");
?>

    <!-- Stat cards -->
    <div class="row g-3">
      <div class="col-6 col-md-3">
        <div class="stat-card bg-inv"><i class="bi bi-boxes icon"></i><p>Total Units</p><h2><?= $total ?></h2></div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card bg-in"><i class="bi bi-archive icon"></i><p>In Stock</p><h2><?= $in_stock ?></h2></div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card bg-out"><i class="bi bi-cart-check icon"></i><p>Sold</p><h2><?= $sold ?></h2></div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card bg-dmg"><i class="bi bi-exclamation-triangle icon"></i><p>Damaged</p><h2><?= $damaged ?></h2></div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-4">
        <div class="card p-3 text-center">
          <div class="text-muted" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.5px">Products</div>
          <div style="font-size:1.6rem;font-weight:800;color:var(--primary)"><?= $products_count ?></div>
        </div>
      </div>
      <div class="col-4">
        <div class="card p-3 text-center">
          <div class="text-muted" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.5px">Suppliers</div>
          <div style="font-size:1.6rem;font-weight:800;color:var(--primary)"><?= $suppliers_count ?></div>
        </div>
      </div>
      <div class="col-4">
        <div class="card p-3 text-center">
          <div class="text-muted" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.5px">Buyers</div>
          <div style="font-size:1.6rem;font-weight:800;color:var(--primary)"><?= $buyers_count ?></div>
        </div>
      </div>
    </div>

    <div class="row g-4">

      <!-- Product Stock Summary -->
      <div class="col-lg-6">
        <div class="card p-3">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="section-title mb-0">Product Stock Summary</div>
            <form method="GET" class="d-flex gap-2">
              <input type="text" name="search" class="form-control form-control-sm" placeholder="Search product..." value="<?= htmlspecialchars($search) ?>">
              <button class="btn btn-sm btn-dark px-3">Go</button>
              <?php if($search): ?><a href="inventory.php" class="btn btn-sm btn-outline-secondary">Clear</a><?php endif; ?>
            </form>
          </div>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead><tr>
                <th>Product</th>
                <th class="text-center">Total</th>
                <th class="text-center">In Stock</th>
                <th class="text-center">Sold</th>
                <th class="text-center">Damaged</th>
              </tr></thead>
              <tbody>
              <?php while($row=$summary->fetch_assoc()): ?>
              <tr>
                <td><strong><?= htmlspecialchars($row['product_name']) ?></strong></td>
                <td class="text-center"><?= $row['total'] ?></td>
                <td class="text-center"><span class="badge-status badge-in_stock"><?= $row['in_stock'] ?></span></td>
                <td class="text-center"><span class="badge-status badge-sold"><?= $row['sold'] ?></span></td>
                <td class="text-center"><span class="badge-status badge-damaged"><?= $row['damaged'] ?></span></td>
              </tr>
              <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Recent Sales — updated JOIN -->
      <div class="col-lg-6">
        <div class="card p-3">
          <div class="section-title">Recent Sales</div>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead><tr>
                <th>Date</th><th>Product</th><th>Serial</th><th>Buyer</th>
              </tr></thead>
              <tbody>
              <?php while($row=$sales->fetch_assoc()): ?>
              <tr>
                <td><?= date('d M', strtotime($row['sale_date'])) ?></td>
                <td><?= htmlspecialchars($row['product_name']) ?></td>
                <td><code><?= htmlspecialchars($row['serial_no']) ?></code></td>
                <td><?= htmlspecialchars($row['buyer_name']) ?>
                  <?php if($row['company_name']): ?><br><small class="text-muted"><?= htmlspecialchars($row['company_name']) ?></small><?php endif; ?>
                </td>
              </tr>
              <?php endwhile; ?>
              </tbody>
            </table>
          </div>
          <div class="mt-3 text-end">
            <a href="stock_out.php" class="btn btn-sm btn-outline-danger">View All Sales →</a>
          </div>
        </div>
      </div>

    </div>
  </div><!-- /main -->
</div><!-- /page-shell -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>