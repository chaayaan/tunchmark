<?php
require 'auth.php';
require 'mydb.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_item') {
        $name = trim($_POST['item_name'] ?? '');
        if (!empty($name)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO items (name) VALUES (?)");
            mysqli_stmt_bind_param($stmt, "s", $name);
            if (mysqli_stmt_execute($stmt)) $_SESSION['message'] = "Item added successfully!";
            else $_SESSION['error'] = "Failed to add item: " . mysqli_error($conn);
            mysqli_stmt_close($stmt);
        } else { $_SESSION['error'] = "Item name is required"; }
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }

    elseif ($action === 'add_service') {
        $name  = trim($_POST['service_name'] ?? '');
        $price = floatval($_POST['service_price'] ?? 0);
        if (!empty($name)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO services (name, price) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, "sd", $name, $price);
            if (mysqli_stmt_execute($stmt)) $_SESSION['message'] = "Service added successfully!";
            else $_SESSION['error'] = "Failed to add service: " . mysqli_error($conn);
            mysqli_stmt_close($stmt);
        } else { $_SESSION['error'] = "Service name is required"; }
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }

    elseif ($action === 'toggle_item') {
        $id = intval($_POST['id'] ?? 0);
        $newStatus = intval($_POST['status'] ?? 0) ? 0 : 1;
        $stmt = mysqli_prepare($conn, "UPDATE items SET is_active = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $newStatus, $id);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        $_SESSION['message'] = $newStatus ? "Item activated." : "Item deactivated.";
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }

    elseif ($action === 'toggle_service') {
        $id = intval($_POST['id'] ?? 0);
        $newStatus = intval($_POST['status'] ?? 0) ? 0 : 1;
        $stmt = mysqli_prepare($conn, "UPDATE services SET is_active = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $newStatus, $id);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        $_SESSION['message'] = $newStatus ? "Service activated." : "Service deactivated.";
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }

    elseif ($action === 'update_service') {
        $id    = intval($_POST['service_id'] ?? 0);
        $name  = trim($_POST['service_name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        if (!empty($name)) {
            $stmt = mysqli_prepare($conn, "UPDATE services SET name = ?, price = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sdi", $name, $price, $id);
            if (mysqli_stmt_execute($stmt)) $_SESSION['message'] = "Service updated successfully!";
            else $_SESSION['error'] = "Failed to update service: " . mysqli_error($conn);
            mysqli_stmt_close($stmt);
        } else { $_SESSION['error'] = "Service name cannot be empty"; }
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }

    elseif ($action === 'update_item_name') {
        $id   = intval($_POST['item_id'] ?? 0);
        $name = trim($_POST['item_name'] ?? '');
        if (!empty($name)) {
            $stmt = mysqli_prepare($conn, "UPDATE items SET name = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $name, $id);
            if (mysqli_stmt_execute($stmt)) $_SESSION['message'] = "Item updated successfully!";
            else $_SESSION['error'] = "Failed to update item: " . mysqli_error($conn);
            mysqli_stmt_close($stmt);
        } else { $_SESSION['error'] = "Item name cannot be empty"; }
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }
}

$message = $_SESSION['message'] ?? '';
$error   = $_SESSION['error']   ?? '';
unset($_SESSION['message'], $_SESSION['error']);

$items = [];
$itemsResult = mysqli_query($conn, "SELECT * FROM items ORDER BY name ASC");
if ($itemsResult) while ($row = mysqli_fetch_assoc($itemsResult)) $items[] = $row;

$services = [];
$servicesResult = mysqli_query($conn, "SELECT * FROM services ORDER BY name ASC");
if ($servicesResult) while ($row = mysqli_fetch_assoc($servicesResult)) $services[] = $row;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Items & Services — Rajaiswari</title>
  <link rel="icon" type="image/png" href="favicon.png">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg:#f1f3f6; --surface:#fff; --surface-2:#fafbfc;
      --border:#e4e7ec; --bsoft:#f0f1f3;
      --t1:#111827; --t2:#374151; --t3:#6b7280; --t4:#9ca3af;
      --blue:#2563eb;  --blue-bg:#eff6ff;  --blue-b:#bfdbfe;
      --green:#059669; --green-bg:#ecfdf5; --green-b:#a7f3d0;
      --amber:#d97706; --amber-bg:#fffbeb; --amber-b:#fde68a;
      --red:#dc2626;   --red-bg:#fef2f2;   --red-b:#fecaca;
      --violet:#7c3aed;--violet-bg:#f5f3ff;
      --r:10px; --rs:6px;
      --sh:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'DM Sans',-apple-system,sans-serif;font-size:14.5px;background:var(--bg);color:var(--t1);-webkit-font-smoothing:antialiased;min-height:100vh;}

    .page-shell{margin-left:200px;min-height:100vh;display:flex;flex-direction:column;}

    .top-bar{position:sticky;top:0;z-index:200;height:54px;background:var(--surface);border-bottom:1px solid var(--border);box-shadow:var(--sh);display:flex;align-items:center;padding:0 22px;gap:10px;flex-shrink:0;}
    .tb-ico{width:32px;height:32px;background:var(--green-bg);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--green);font-size:13px;flex-shrink:0;}
    .tb-title{font-size:1.0625rem;font-weight:700;color:var(--t1);line-height:1.2;}
    .tb-sub{font-size:.8rem;color:var(--t4);}

    .main{flex:1;padding:20px 22px 60px;display:flex;flex-direction:column;gap:14px;}

    /* Alerts */
    .alert-flash{border-radius:var(--rs);padding:12px 16px;font-size:.875rem;display:flex;align-items:center;gap:9px;}
    .af-success{background:var(--green-bg);border:1px solid var(--green-b);border-left:3px solid var(--green);color:#065f46;}
    .af-danger {background:var(--red-bg);  border:1px solid var(--red-b);  border-left:3px solid var(--red);  color:#7f1d1d;}

    /* Two-column layout */
    .cols{display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:start;}

    /* Section card */
    .sec{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;}
    .sec-head{display:flex;align-items:center;gap:9px;padding:12px 18px;background:var(--surface-2);border-bottom:1px solid var(--bsoft);}
    .sec-ico{width:28px;height:28px;border-radius:var(--rs);display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;}
    .i-blue  {background:var(--blue-bg);  color:var(--blue);}
    .i-green {background:var(--green-bg); color:var(--green);}
    .i-violet{background:var(--violet-bg);color:var(--violet);}
    .i-amber {background:var(--amber-bg); color:var(--amber);}
    .sec-title{font-size:.9375rem;font-weight:700;color:var(--t1);letter-spacing:-.01em;}
    .sec-meta{margin-left:auto;font-size:.78rem;color:var(--t4);}

    /* Add form area */
    .add-area{padding:14px 18px;background:var(--surface-2);border-bottom:1px solid var(--bsoft);}
    .add-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
    .lbl{display:block;font-size:.76rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t3);margin-bottom:4px;}
    .fc{height:38px;padding:0 11px;border:1.5px solid var(--border);border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.875rem;color:var(--t2);background:var(--surface);outline:none;width:100%;transition:border-color .15s,box-shadow .15s;}
    .fc:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
    .fc-sm{width:100px;flex-shrink:0;}
    .btn-add-row{display:inline-flex;align-items:center;gap:5px;height:38px;padding:0 16px;background:var(--green);border:none;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.875rem;font-weight:700;color:#fff;cursor:pointer;white-space:nowrap;transition:background .15s;flex-shrink:0;}
    .btn-add-row:hover{background:#047857;}

    /* Table */
    .mtbl{width:100%;border-collapse:collapse;}
    .mtbl thead th{padding:9px 14px;text-align:left;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--t4);background:var(--surface-2);border-bottom:1px solid var(--border);white-space:nowrap;}
    .mtbl tbody td{padding:8px 10px;border-bottom:1px solid var(--bsoft);vertical-align:middle;}
    .mtbl tbody tr:last-child td{border-bottom:none;}
    .mtbl tbody tr:hover td{background:#fafbff;}

    /* Inline edit inputs */
    .fc-inline{height:34px;padding:0 9px;border:1.5px solid var(--border);border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.82rem;color:var(--t2);background:var(--surface);outline:none;transition:border-color .15s;}
    .fc-inline:focus{border-color:var(--blue);box-shadow:0 0 0 2px rgba(37,99,235,.1);}
    .fc-name{width:130px;}
    .fc-price{width:80px;}

    /* ID badge */
    .id-tag{font-family:'DM Mono',monospace;font-size:.72rem;color:var(--t4);background:var(--surface-2);border:1px solid var(--border);border-radius:4px;padding:1px 6px;}

    /* Status pill */
    .pill{display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:20px;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
    .pill::before{content:'';width:5px;height:5px;border-radius:50%;}
    .pill-on {background:var(--green-bg);color:var(--green);border:1px solid var(--green-b);}
    .pill-on::before{background:var(--green);}
    .pill-off{background:var(--surface-2);color:var(--t4);border:1px solid var(--border);}
    .pill-off::before{background:var(--t4);}

    /* Row action buttons */
    .row-actions{display:flex;gap:5px;align-items:center;}
    .btn-save{display:inline-flex;align-items:center;gap:4px;height:30px;padding:0 12px;background:var(--blue-bg);border:1.5px solid var(--blue-b);border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.78rem;font-weight:700;color:var(--blue);cursor:pointer;white-space:nowrap;transition:all .15s;}
    .btn-save:hover{background:var(--blue);color:#fff;border-color:var(--blue);}
    .btn-toggle{display:inline-flex;align-items:center;gap:4px;height:30px;padding:0 10px;border:1.5px solid;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.75rem;font-weight:600;cursor:pointer;white-space:nowrap;transition:all .15s;background:var(--surface);}
    .btn-toggle-on {border-color:var(--amber-b);color:var(--amber);}
    .btn-toggle-on:hover{background:var(--amber);color:#fff;border-color:var(--amber);}
    .btn-toggle-off{border-color:var(--green-b);color:var(--green);}
    .btn-toggle-off:hover{background:var(--green);color:#fff;border-color:var(--green);}

    /* Empty state */
    .empty-row td{padding:32px 16px;text-align:center;color:var(--t4);font-size:.82rem;}

    @media(max-width:991.98px){.page-shell{margin-left:0;}.top-bar{top:52px;}.main{padding:14px 14px 50px;}.cols{grid-template-columns:1fr;}}
  </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="page-shell">

  <header class="top-bar">
    <div class="tb-ico"><i class="fas fa-screwdriver-wrench"></i></div>
    <div>
      <div class="tb-title">Items & Services</div>
      <div class="tb-sub">Manage catalog and pricing</div>
    </div>
  </header>

  <div class="main">

    <?php if ($message): ?>
    <div class="alert-flash af-success" id="flashMsg">
      <i class="fas fa-circle-check"></i> <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert-flash af-danger" id="flashMsg">
      <i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div class="cols">

      <!-- ── ITEMS ─────────────────────────── -->
      <div class="sec">
        <div class="sec-head">
          <span class="sec-ico i-blue"><i class="fas fa-box"></i></span>
          <span class="sec-title">Items</span>
          <span class="sec-meta"><?= count($items) ?> total</span>
        </div>

        <!-- Add item -->
        <div class="add-area">
          <form method="POST" onsubmit="return confirm('Add this item?');">
            <input type="hidden" name="action" value="add_item">
            <label class="lbl">Add New Item</label>
            <div class="add-row">
              <input type="text" name="item_name" class="fc" placeholder="Item name…" required>
              <button type="submit" class="btn-add-row">
                <i class="fas fa-plus" style="font-size:.7rem;"></i> Add
              </button>
            </div>
          </form>
        </div>

        <!-- Items table -->
        <div style="overflow-x:auto;">
          <table class="mtbl">
            <thead>
              <tr>
                <th style="width:44px;">ID</th>
                <th>Name</th>
                <th style="width:80px;">Status</th>
                <th style="width:130px;">Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php if (empty($items)): ?>
              <tr class="empty-row"><td colspan="4">No items yet. Add one above.</td></tr>
            <?php else: ?>
            <?php foreach ($items as $item): ?>
            <tr>
              <form method="POST" style="display:contents;" onsubmit="return confirm('Save changes to this item?');">
                <td><span class="id-tag"><?= $item['id'] ?></span></td>
                <td>
                  <input type="hidden" name="action"  value="update_item_name">
                  <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                  <input type="text" name="item_name" class="fc-inline fc-name"
                         value="<?= htmlspecialchars($item['name']) ?>" required>
                </td>
                <td>
                  <span class="pill <?= $item['is_active'] ? 'pill-on' : 'pill-off' ?>">
                    <?= $item['is_active'] ? 'Active' : 'Off' ?>
                  </span>
                </td>
                <td>
                  <div class="row-actions">
                    <button type="submit" class="btn-save">
                      <i class="fas fa-floppy-disk" style="font-size:.65rem;"></i> Save
                    </button>
              </form>
                    <form method="POST" style="display:inline;"
                          onsubmit="return confirm('<?= $item['is_active'] ? 'Deactivate' : 'Activate' ?> this item?');">
                      <input type="hidden" name="action" value="toggle_item">
                      <input type="hidden" name="id"     value="<?= $item['id'] ?>">
                      <input type="hidden" name="status" value="<?= $item['is_active'] ?>">
                      <button type="submit" class="btn-toggle <?= $item['is_active'] ? 'btn-toggle-on' : 'btn-toggle-off' ?>">
                        <?= $item['is_active'] ? 'Disable' : 'Enable' ?>
                      </button>
                    </form>
                  </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ── SERVICES ──────────────────────── -->
      <div class="sec">
        <div class="sec-head">
          <span class="sec-ico i-green"><i class="fas fa-wrench"></i></span>
          <span class="sec-title">Services</span>
          <span class="sec-meta"><?= count($services) ?> total</span>
        </div>

        <!-- Add service -->
        <div class="add-area">
          <form method="POST" onsubmit="return confirm('Add this service?');">
            <input type="hidden" name="action" value="add_service">
            <label class="lbl">Add New Service</label>
            <div class="add-row">
              <input type="text" name="service_name" class="fc" placeholder="Service name…" required>
              <input type="number" name="service_price" class="fc fc-sm" placeholder="Price" step="0.01" min="0" value="0">
              <button type="submit" class="btn-add-row">
                <i class="fas fa-plus" style="font-size:.7rem;"></i> Add
              </button>
            </div>
          </form>
        </div>

        <!-- Services table -->
        <div style="overflow-x:auto;">
          <table class="mtbl">
            <thead>
              <tr>
                <th style="width:44px;">ID</th>
                <th>Name</th>
                <th style="width:90px;">Price (৳)</th>
                <th style="width:70px;">Status</th>
                <th style="width:140px;">Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php if (empty($services)): ?>
              <tr class="empty-row"><td colspan="5">No services yet. Add one above.</td></tr>
            <?php else: ?>
            <?php foreach ($services as $service): ?>
            <tr>
              <form method="POST" style="display:contents;" onsubmit="return confirm('Save changes to this service?');">
                <td><span class="id-tag"><?= $service['id'] ?></span></td>
                <td>
                  <input type="hidden" name="action"     value="update_service">
                  <input type="hidden" name="service_id" value="<?= $service['id'] ?>">
                  <input type="text" name="service_name" class="fc-inline fc-name"
                         value="<?= htmlspecialchars($service['name']) ?>" required>
                </td>
                <td>
                  <input type="number" name="price" class="fc-inline fc-price"
                         value="<?= htmlspecialchars($service['price']) ?>" step="0.01" min="0">
                </td>
                <td>
                  <span class="pill <?= $service['is_active'] ? 'pill-on' : 'pill-off' ?>">
                    <?= $service['is_active'] ? 'Active' : 'Off' ?>
                  </span>
                </td>
                <td>
                  <div class="row-actions">
                    <button type="submit" class="btn-save">
                      <i class="fas fa-floppy-disk" style="font-size:.65rem;"></i> Save
                    </button>
              </form>
                    <form method="POST" style="display:inline;"
                          onsubmit="return confirm('<?= $service['is_active'] ? 'Deactivate' : 'Activate' ?> this service?');">
                      <input type="hidden" name="action" value="toggle_service">
                      <input type="hidden" name="id"     value="<?= $service['id'] ?>">
                      <input type="hidden" name="status" value="<?= $service['is_active'] ?>">
                      <button type="submit" class="btn-toggle <?= $service['is_active'] ? 'btn-toggle-on' : 'btn-toggle-off' ?>">
                        <?= $service['is_active'] ? 'Disable' : 'Enable' ?>
                      </button>
                    </form>
                  </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div><!-- /cols -->
  </div><!-- /main -->
</div><!-- /page-shell -->

<script>
document.addEventListener('DOMContentLoaded', function () {
  const flash = document.getElementById('flashMsg');
  if (flash) setTimeout(() => { flash.style.transition = 'opacity .5s'; flash.style.opacity = '0'; }, 6000);
});
</script>
</body>
</html>