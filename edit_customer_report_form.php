<?php
require 'auth.php';
require 'mydb.php';

// Get report ID from URL
$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch report data
$query = "SELECT * FROM customer_reports WHERE id = $report_id";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    header('Location: view_customer_reports.php');
    exit();
}

$report = mysqli_fetch_assoc($result);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $quantity      = (int)$_POST['quantity'];
    $gold_purity   = mysqli_real_escape_string($conn, $_POST['gold_purity']);
    $karat         = mysqli_real_escape_string($conn, $_POST['karat']);
    $hallmark      = mysqli_real_escape_string($conn, $_POST['hallmark']);
    $customer_name = mysqli_real_escape_string($conn, $_POST['customer_name']);

    $update_query = "UPDATE customer_reports SET 
        customer_name = '$customer_name',
        quantity = $quantity,
        gold_purity = '$gold_purity',
        karat = '$karat',
        hallmark = '$hallmark'
        WHERE id = $report_id";

    if (mysqli_query($conn, $update_query)) {
        header('Location: view_customer_reports.php');
        exit();
    } else {
        $error_message = "Error updating report: " . mysqli_error($conn);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Report #<?= htmlspecialchars($report['order_id']) ?> — Rajaiswari</title>
  <link rel="icon" type="image/png" href="favicon.png">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg:        #f1f3f6;
      --surface:   #ffffff;
      --surface-2: #fafbfc;
      --border:    #e4e7ec;
      --bsoft:     #f0f1f3;
      --t1:        #111827;
      --t2:        #374151;
      --t3:        #6b7280;
      --t4:        #9ca3af;
      --blue:      #2563eb;  --blue-bg: #eff6ff;  --blue-b: #bfdbfe;
      --green:     #059669;  --green-bg:#ecfdf5;  --green-b:#a7f3d0;
      --amber:     #d97706;  --amber-bg:#fffbeb;  --amber-b:#fde68a;
      --red:       #dc2626;  --red-bg:  #fef2f2;  --red-b:  #fecaca;
      --violet:    #7c3aed;  --violet-bg:#f5f3ff; --violet-b:#ddd6fe;
      --r:         10px;
      --rs:        6px;
      --sh:        0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'DM Sans', -apple-system, sans-serif;
      font-size: 14.5px;
      background: var(--bg);
      color: var(--t1);
      -webkit-font-smoothing: antialiased;
      min-height: 100vh;
    }

    /* ── Shell ──────────────────────────────── */
    .page-shell {
      margin-left: 200px;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ── Top bar ────────────────────────────── */
    .top-bar {
      position: sticky; top: 0; z-index: 200;
      height: 54px;
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      box-shadow: var(--sh);
      display: flex; align-items: center;
      padding: 0 22px; gap: 10px; flex-shrink: 0;
    }

    .tb-ico {
      width: 32px; height: 32px;
      background: var(--violet-bg);
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      color: var(--violet); font-size: 13px; flex-shrink: 0;
    }

    .tb-title { font-size: 1.0625rem; font-weight: 700; color: var(--t1); line-height: 1.2; }
    .tb-sub   { font-size: .8rem; color: var(--t4); }

    .tb-right {
      margin-left: auto;
      display: flex; align-items: center; gap: 8px;
    }

    .order-badge {
      display: inline-flex; align-items: center; gap: 5px;
      background: var(--surface-2);
      border: 1px solid var(--border);
      border-radius: var(--rs);
      padding: 4px 12px;
      font-family: 'DM Mono', monospace;
      font-size: .85rem; font-weight: 500; color: var(--t3);
    }

    .tb-back {
      display: inline-flex; align-items: center; gap: 6px;
      height: 32px; padding: 0 14px;
      background: var(--surface-2);
      border: 1.5px solid var(--border);
      border-radius: var(--rs);
      font-family: 'DM Sans', sans-serif;
      font-size: .82rem; font-weight: 600; color: var(--t2);
      text-decoration: none;
      transition: all .15s;
    }
    .tb-back:hover { background: var(--border); color: var(--t1); }

    /* ── Main ───────────────────────────────── */
    .main {
      flex: 1;
      padding: 20px 22px 60px;
      display: flex;
      flex-direction: column;
      gap: 14px;
    }

    /* ── Two-column layout ──────────────────── */
    .layout {
      display: grid;
      grid-template-columns: 1fr 300px;
      gap: 14px;
      align-items: start;
    }

    /* ── Section card ───────────────────────── */
    .sec {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--r);
      box-shadow: var(--sh);
      overflow: hidden;
    }

    .sec-head {
      display: flex; align-items: center; gap: 9px;
      padding: 12px 18px;
      background: var(--surface-2);
      border-bottom: 1px solid var(--bsoft);
    }

    .sec-ico {
      width: 28px; height: 28px;
      border-radius: var(--rs);
      display: flex; align-items: center; justify-content: center;
      font-size: 12px; flex-shrink: 0;
    }
    .i-blue   { background: var(--blue-bg);   color: var(--blue);   }
    .i-violet { background: var(--violet-bg); color: var(--violet); }
    .i-amber  { background: var(--amber-bg);  color: var(--amber);  }
    .i-green  { background: var(--green-bg);  color: var(--green);  }

    .sec-title {
      font-size: .9375rem; font-weight: 700;
      color: var(--t1); letter-spacing: -.01em;
    }

    .sec-badge {
      margin-left: auto;
      display: inline-flex; align-items: center; gap: 4px;
      padding: 2px 8px;
      border-radius: 20px;
      font-size: .7rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .05em;
    }

    .badge-ro  { background: var(--surface-2); color: var(--t4); border: 1px solid var(--border); }
    .badge-edit { background: var(--amber-bg); color: var(--amber); border: 1px solid var(--amber-b); }

    .sec-body { padding: 18px; }

    /* ── Form controls ──────────────────────── */
    .lbl {
      display: block;
      font-size: .76rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .06em;
      color: var(--t3); margin-bottom: 5px;
    }
    .lbl .req { color: var(--red); margin-left: 2px; }

    .fc {
      width: 100%; height: 38px;
      padding: 0 11px;
      border: 1.5px solid var(--border);
      border-radius: var(--rs);
      font-family: 'DM Sans', sans-serif;
      font-size: .9rem; color: var(--t2);
      background: var(--surface);
      transition: border-color .15s, box-shadow .15s;
      outline: none;
    }

    .fc:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }

    /* Read-only fields */
    .fc-ro {
      background: var(--surface-2);
      color: var(--t3);
      cursor: default;
      border-color: var(--bsoft);
    }

    /* Editable fields — amber highlight */
    .fc-edit {
      background: var(--amber-bg);
      border-color: var(--amber-b);
      color: var(--t1);
    }
    .fc-edit:focus {
      border-color: var(--amber);
      box-shadow: 0 0 0 3px rgba(217,119,6,.12);
    }

    /* ── Field group divider ────────────────── */
    .field-divider {
      grid-column: 1 / -1;
      height: 1px;
      background: var(--bsoft);
      margin: 4px 0;
    }

    /* ── Info panel ─────────────────────────── */
    .info-row {
      display: flex; align-items: flex-start; gap: 10px;
      padding: 10px 0;
      border-bottom: 1px solid var(--bsoft);
    }
    .info-row:last-child { border-bottom: none; padding-bottom: 0; }

    .info-ico {
      width: 28px; height: 28px;
      border-radius: var(--rs);
      display: flex; align-items: center; justify-content: center;
      font-size: 11px; flex-shrink: 0;
      margin-top: 1px;
    }

    .info-lbl  { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--t4); margin-bottom: 2px; }
    .info-val  { font-size: .875rem; font-weight: 600; color: var(--t1); }
    .info-val.mono { font-family: 'DM Mono', monospace; }

    /* ── Note box ───────────────────────────── */
    .note-box {
      background: var(--amber-bg);
      border: 1px solid var(--amber-b);
      border-left: 3px solid var(--amber);
      border-radius: var(--rs);
      padding: 11px 14px;
      font-size: .82rem;
      color: #92400e;
      line-height: 1.5;
    }
    .note-box strong { color: var(--amber); }

    /* ── Alert ──────────────────────────────── */
    .alert-err {
      background: var(--red-bg);
      border: 1px solid var(--red-b);
      border-left: 3px solid var(--red);
      border-radius: var(--rs);
      padding: 12px 16px;
      font-size: .875rem; color: var(--red);
    }

    /* ── Action bar ─────────────────────────── */
    .action-bar {
      display: flex; align-items: center;
      justify-content: flex-end; gap: 8px;
      padding: 13px 18px;
      background: var(--surface-2);
      border-top: 1px solid var(--border);
    }

    .btn-ghost {
      display: inline-flex; align-items: center; gap: 6px;
      height: 38px; padding: 0 18px;
      background: var(--surface);
      border: 1.5px solid var(--border);
      border-radius: 7px;
      font-family: 'DM Sans', sans-serif;
      font-size: .9rem; font-weight: 500; color: var(--t2);
      text-decoration: none;
      cursor: pointer; transition: all .15s;
    }
    .btn-ghost:hover { background: var(--surface-2); border-color: #9ca3af; color: var(--t1); }

    .btn-submit {
      display: inline-flex; align-items: center; gap: 7px;
      height: 38px; padding: 0 24px;
      background: var(--violet);
      border: none; border-radius: 7px;
      font-family: 'DM Sans', sans-serif;
      font-size: .9375rem; font-weight: 700; color: #fff;
      cursor: pointer;
      box-shadow: 0 1px 4px rgba(124,58,237,.25);
      transition: background .15s;
    }
    .btn-submit:hover { background: #6d28d9; }

    /* ── Responsive ─────────────────────────── */
    @media (max-width: 1100px) {
      .layout { grid-template-columns: 1fr; }
    }

    @media (max-width: 991.98px) {
      .page-shell { margin-left: 0; }
      .top-bar    { top: 52px; }
      .main       { padding: 14px 14px 50px; }
    }
  </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="page-shell">

  <!-- Top Bar -->
  <header class="top-bar">
    <div class="tb-ico"><i class="fas fa-pen-to-square"></i></div>
    <div>
      <div class="tb-title">Edit Customer Report</div>
      <div class="tb-sub">Update report fields for the order below</div>
    </div>
    <div class="tb-right">
      <div class="order-badge">
        <i class="fas fa-hashtag" style="font-size:.6rem;"></i>
        Order #<?= htmlspecialchars($report['order_id']) ?>
      </div>
      <a href="view_customer_reports.php" class="tb-back">
        <i class="fas fa-arrow-left" style="font-size:.65rem;"></i> Back
      </a>
    </div>
  </header>

  <div class="main">

    <!-- Error -->
    <?php if (isset($error_message)): ?>
    <div class="alert-err">
      <i class="fas fa-circle-exclamation" style="margin-right:6px;"></i>
      <?= htmlspecialchars($error_message) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="layout">

        <!-- LEFT: Form fields -->
        <div style="display:flex;flex-direction:column;gap:14px;">

          <!-- Read-only info -->
          <div class="sec">
            <div class="sec-head">
              <span class="sec-ico i-blue"><i class="fas fa-lock"></i></span>
              <span class="sec-title">Order Information</span>
              <span class="sec-badge badge-ro"><i class="fas fa-lock" style="font-size:.55rem;"></i> Read-only</span>
            </div>
            <div class="sec-body">
              <div class="row g-3">

                <div class="col-md-6">
                  <label class="lbl">Order ID</label>
                  <input type="text" class="fc fc-ro"
                         value="<?= htmlspecialchars($report['order_id']) ?>" readonly>
                </div>

                <div class="col-md-6">
                  <label class="lbl">Item Name</label>
                  <input type="text" class="fc fc-ro"
                         value="<?= htmlspecialchars($report['item_name']) ?>" readonly>
                </div>

                <div class="col-md-6">
                  <label class="lbl">Service Name</label>
                  <input type="text" class="fc fc-ro"
                         value="<?= htmlspecialchars($report['service_name']) ?>" readonly>
                </div>

                <div class="col-md-4">
                  <label class="lbl">Weight (grams)</label>
                  <input type="text" class="fc fc-ro"
                         value="<?= htmlspecialchars($report['weight']) ?>" readonly>
                </div>

              </div>
            </div>
          </div>

          <!-- Editable fields -->
          <div class="sec">
            <div class="sec-head">
              <span class="sec-ico i-amber"><i class="fas fa-pen"></i></span>
              <span class="sec-title">Editable Fields</span>
              <span class="sec-badge badge-edit"><i class="fas fa-pen" style="font-size:.55rem;"></i> Editable</span>
            </div>
            <div class="sec-body">
              <div class="row g-3">

                <div class="col-md-12">
                  <label class="lbl">Customer Name <span class="req">*</span></label>
                  <input type="text" class="fc fc-edit" name="customer_name"
                         value="<?= htmlspecialchars($report['customer_name']) ?>"
                         placeholder="Enter customer name" required>
                </div>

                <div class="col-md-4">
                  <label class="lbl">Quantity <span class="req">*</span></label>
                  <input type="number" class="fc fc-edit" name="quantity"
                         value="<?= htmlspecialchars($report['quantity'] ?: '1') ?>"
                         min="1" required>
                </div>

                <div class="col-md-4">
                  <label class="lbl">Gold Purity</label>
                  <input type="text" class="fc fc-edit" name="gold_purity"
                         value="<?= htmlspecialchars($report['gold_purity']) ?>"
                         placeholder="e.g. 91.6, 87.5%">
                </div>

                <div class="col-md-4">
                  <label class="lbl">Karat</label>
                  <input type="text" class="fc fc-edit" name="karat"
                         value="<?= htmlspecialchars($report['karat']) ?>"
                         placeholder="e.g. 22, 21, 18">
                </div>

                <div class="col-md-12">
                  <label class="lbl">Hallmark</label>
                  <input type="text" class="fc fc-edit" name="hallmark"
                         value="<?= htmlspecialchars($report['hallmark']) ?>"
                         placeholder="e.g. 916 RJ, SRJ 22K">
                </div>

              </div>
            </div>

            <div class="action-bar">
              <a href="view_customer_reports.php" class="btn-ghost">
                <i class="fas fa-arrow-left" style="font-size:.65rem;"></i> Cancel
              </a>
              <button type="submit" class="btn-submit">
                <i class="fas fa-floppy-disk" style="font-size:.75rem;"></i> Update Report
              </button>
            </div>
          </div>

        </div><!-- /left -->

        <!-- RIGHT: Info sidebar -->
        <div style="display:flex;flex-direction:column;gap:14px;">

          <!-- Record metadata -->
          <div class="sec">
            <div class="sec-head">
              <span class="sec-ico i-green"><i class="fas fa-circle-info"></i></span>
              <span class="sec-title">Record Info</span>
            </div>
            <div class="sec-body">

              <div class="info-row">
                <div class="info-ico i-blue"><i class="fas fa-hashtag" style="font-size:.7rem;"></i></div>
                <div>
                  <div class="info-lbl">Report ID</div>
                  <div class="info-val mono">#<?= htmlspecialchars($report_id) ?></div>
                </div>
              </div>

              <div class="info-row">
                <div class="info-ico i-violet"><i class="fas fa-calendar-plus" style="font-size:.7rem;"></i></div>
                <div>
                  <div class="info-lbl">Created</div>
                  <div class="info-val"><?= date('d M Y', strtotime($report['created_at'])) ?></div>
                  <div style="font-size:.78rem;color:var(--t4);"><?= date('h:i A', strtotime($report['created_at'])) ?></div>
                </div>
              </div>

              <div class="info-row">
                <div class="info-ico i-amber"><i class="fas fa-clock-rotate-left" style="font-size:.7rem;"></i></div>
                <div>
                  <div class="info-lbl">Last Updated</div>
                  <div class="info-val"><?= date('d M Y', strtotime($report['updated_at'])) ?></div>
                  <div style="font-size:.78rem;color:var(--t4);"><?= date('h:i A', strtotime($report['updated_at'])) ?></div>
                </div>
              </div>

            </div>
          </div>

          <!-- Edit note -->
          <div class="note-box">
            <strong><i class="fas fa-triangle-exclamation" style="margin-right:4px;"></i> Note</strong><br>
            Only <strong>Customer Name</strong>, <strong>Quantity</strong>, <strong>Gold Purity</strong>, <strong>Karat</strong>, and <strong>Hallmark</strong>
            can be edited. All other fields are locked to preserve order integrity.
          </div>

        </div><!-- /right -->

      </div><!-- /layout -->
    </form>

  </div><!-- /main -->
</div><!-- /page-shell -->

</body>
</html>