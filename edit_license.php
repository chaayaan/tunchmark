<?php
require 'auth.php';
require 'mylicensedb.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php'); exit;
}
if (!isset($_GET['id'])) {
    header('Location: manage_licenses.php'); exit;
}

$license_id = (int)$_GET['id'];
$error = '';

// Handle POST — PRG pattern
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare(
            "UPDATE licenses SET branch_name=?, branch_app_link=?, license_key=?, expire_date=?, activation_date=?, last_renew=?, status=? WHERE id=?"
        );
        $activationDate = $_POST['activation_date'] ?: null;
        $stmt->execute([
            $_POST['branch_name'],
            $_POST['branch_app_link'],
            $_POST['license_key'],
            $_POST['expire_date'],
            $activationDate,
            $activationDate,  // keep last_renew in sync when edited manually
            $_POST['status'],
            $license_id
        ]);
        $_SESSION['toast_message'] = 'License updated successfully';
        $_SESSION['toast_type']    = 'success';
        header('Location: manage_licenses.php'); exit;
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}

// Fetch existing record
try {
    $stmt = $pdo->prepare("SELECT * FROM licenses WHERE id = ?");
    $stmt->execute([$license_id]);
    $license = $stmt->fetch();
    if (!$license) { header('Location: manage_licenses.php'); exit; }
} catch (PDOException $e) {
    die('Error: ' . $e->getMessage());
}

// Days-left calculation for info sidebar
$today    = new DateTime('today');
$expDate  = new DateTime($license['expire_date']);
$daysLeft = (int)$today->diff($expDate)->format('%r%a');

include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit License &mdash; Rajaiswari</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root{--bg:#f1f3f6;--surface:#fff;--s2:#fafbfc;--border:#e4e7ec;--bsoft:#f0f1f3;--t1:#111827;--t2:#374151;--t3:#6b7280;--t4:#9ca3af;--blue:#2563eb;--blue-bg:#eff6ff;--blue-b:#bfdbfe;--green:#059669;--green-bg:#ecfdf5;--green-b:#a7f3d0;--amber:#d97706;--amber-bg:#fffbeb;--amber-b:#fde68a;--red:#dc2626;--red-bg:#fef2f2;--red-b:#fecaca;--violet:#7c3aed;--violet-bg:#f5f3ff;--violet-b:#ddd6fe;--teal:#0891b2;--teal-bg:#ecfeff;--teal-b:#a5f3fc;--r:10px;--rs:6px;--sh:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);}
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'DM Sans',-apple-system,sans-serif;font-size:14px;background:var(--bg);color:var(--t1);-webkit-font-smoothing:antialiased;min-height:100vh;}
        .page-shell{margin-left:200px;min-height:100vh;display:flex;flex-direction:column;}
        .top-bar{position:sticky;top:0;z-index:200;height:54px;background:var(--surface);border-bottom:1px solid var(--border);box-shadow:var(--sh);display:flex;align-items:center;padding:0 22px;gap:12px;flex-shrink:0;}
        .tb-ico{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;background:var(--amber-bg);color:var(--amber);flex-shrink:0;}
        .tb-title{font-size:1.0625rem;font-weight:700;color:var(--t1);}
        .tb-sub{font-size:.78rem;color:var(--t4);}
        .tb-right{margin-left:auto;display:flex;gap:7px;align-items:center;}
        .btn-pos{display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 13px;border:none;border-radius:var(--rs);font-family:inherit;font-size:.8125rem;font-weight:600;cursor:pointer;transition:all .15s;text-decoration:none;white-space:nowrap;}
        .btn-ghost{background:var(--surface);color:var(--t2);border:1.5px solid var(--border);}
        .btn-ghost:hover{background:var(--s2);border-color:#9ca3af;color:var(--t1);}
        .btn-amber{background:var(--amber);color:#fff;border:none;}
        .btn-amber:hover{background:#b45309;color:#fff;}
        .main{flex:1;padding:20px 22px 60px;display:grid;grid-template-columns:1fr 280px;gap:16px;align-items:start;max-width:1000px;}
        .sec{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;}
        .sec-head{display:flex;align-items:center;gap:9px;padding:11px 18px;background:var(--s2);border-bottom:1px solid var(--bsoft);}
        .sec-ico{width:26px;height:26px;border-radius:var(--rs);display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;}
        .i-amber{background:var(--amber-bg);color:var(--amber);}
        .i-violet{background:var(--violet-bg);color:var(--violet);}
        .i-green{background:var(--green-bg);color:var(--green);}
        .i-red{background:var(--red-bg);color:var(--red);}
        .i-blue{background:var(--blue-bg);color:var(--blue);}
        .i-teal{background:var(--teal-bg);color:var(--teal);}
        .sec-title{font-size:.875rem;font-weight:700;color:var(--t1);}
        .sec-body{padding:18px;}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
        .lbl{display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t3);margin-bottom:5px;}
        .lbl .req{color:var(--red);margin-left:2px;}
        .fc{width:100%;height:36px;padding:0 10px;border:1.5px solid var(--border);border-radius:var(--rs);font-family:inherit;font-size:.875rem;color:var(--t2);background:var(--surface);outline:none;transition:border-color .15s;}
        .fc:focus{border-color:var(--amber);box-shadow:0 0 0 3px rgba(217,119,6,.1);}
        select.fc{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%236b7280'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;}
        .fc-mono{font-family:'DM Mono',monospace;letter-spacing:.04em;}
        .form-footer{display:flex;justify-content:flex-end;gap:8px;padding-top:6px;}
        .alert-err{display:flex;align-items:flex-start;gap:10px;padding:12px 16px;border-radius:var(--rs);font-size:.875rem;font-weight:500;background:var(--red-bg);border:1px solid var(--red-b);border-left:3px solid var(--red);color:#991b1b;margin-bottom:12px;}
        .info-row{display:flex;align-items:center;gap:10px;padding:11px 0;border-bottom:1px solid var(--bsoft);}
        .info-row:last-child{border-bottom:none;}
        .info-ico{width:28px;height:28px;border-radius:var(--rs);display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;}
        .info-label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--t4);}
        .info-val{font-size:.85rem;color:var(--t1);font-weight:500;margin-top:1px;}
        .info-val.mono{font-family:'DM Mono',monospace;}
        .pill{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:20px;font-size:.72rem;font-weight:700;}
        .pill::before{content:'';width:5px;height:5px;border-radius:50%;flex-shrink:0;}
        .pill-active{background:var(--green-bg);color:var(--green);border:1px solid var(--green-b);}
        .pill-active::before{background:var(--green);}
        .pill-expired{background:var(--red-bg);color:var(--red);border:1px solid var(--red-b);}
        .pill-expired::before{background:var(--red);}
        .pill-suspended{background:var(--amber-bg);color:var(--amber);border:1px solid var(--amber-b);}
        .pill-suspended::before{background:var(--amber);}
        .days-block{border-radius:var(--rs);padding:10px 14px;text-align:center;margin:0 18px 14px;}
        .days-block.ok{background:var(--green-bg);border:1px solid var(--green-b);}
        .days-block.warn{background:var(--amber-bg);border:1px solid var(--amber-b);}
        .days-block.exp{background:var(--red-bg);border:1px solid var(--red-b);}
        .days-num{font-family:'DM Mono',monospace;font-size:1.5rem;font-weight:500;}
        .days-num.ok{color:var(--green);}
        .days-num.warn{color:var(--amber);}
        .days-num.exp{color:var(--red);}
        .days-caption{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-top:2px;}
        .days-caption.ok{color:var(--green);}
        .days-caption.warn{color:var(--amber);}
        .days-caption.exp{color:var(--red);}
        @media(max-width:991.98px){.page-shell{margin-left:0;}.top-bar{top:52px;}.main{grid-template-columns:1fr;padding:14px 14px 50px;}}
        @media(max-width:560px){.form-grid{grid-template-columns:1fr;}}
    </style>
</head>
<body>
<div class="page-shell">

    <header class="top-bar">
        <div class="tb-ico"><i class="fas fa-pen-to-square"></i></div>
        <div>
            <div class="tb-title">Edit License</div>
            <div class="tb-sub"><?= htmlspecialchars($license['branch_name']) ?></div>
        </div>
        <div class="tb-right">
            <a href="manage_licenses.php" class="btn-pos btn-ghost"><i class="fas fa-arrow-left" style="font-size:.6rem;"></i> All Licenses</a>
        </div>
    </header>

    <div class="main">

        <!-- Left: Edit form -->
        <div>
            <?php if ($error): ?>
            <div class="alert-err" style="margin-bottom:12px;">
                <i class="fas fa-circle-xmark" style="font-size:.9rem;flex-shrink:0;margin-top:1px;"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <div class="sec">
                <div class="sec-head">
                    <span class="sec-ico i-amber"><i class="fas fa-pen"></i></span>
                    <span class="sec-title">License Details</span>
                </div>
                <div class="sec-body">
                    <form method="POST">
                        <div class="form-grid">
                            <div>
                                <label class="lbl">Branch Name <span class="req">*</span></label>
                                <input type="text" name="branch_name" class="fc" value="<?= htmlspecialchars($license['branch_name']) ?>" required>
                            </div>
                            <div>
                                <label class="lbl">Branch App Link</label>
                                <input type="text" name="branch_app_link" class="fc" value="<?= htmlspecialchars($license['branch_app_link'] ?? '') ?>" placeholder="https://…">
                            </div>
                            <div>
                                <label class="lbl">License Key <span class="req">*</span></label>
                                <input type="text" name="license_key" class="fc fc-mono" value="<?= htmlspecialchars($license['license_key']) ?>" required>
                            </div>
                            <div>
                                <label class="lbl">Expiration Date <span class="req">*</span></label>
                                <input type="date" name="expire_date" class="fc" value="<?= htmlspecialchars($license['expire_date']) ?>" required>
                            </div>
                            <div>
                                <label class="lbl">Activation Date</label>
                                <input type="date" name="activation_date" class="fc" value="<?= htmlspecialchars($license['activation_date'] ?? '') ?>">
                            </div>
                            <div>
                                <label class="lbl">Status <span class="req">*</span></label>
                                <select name="status" class="fc" required>
                                    <option value="active"    <?= $license['status']==='active'    ?'selected':'' ?>>Active</option>
                                    <option value="expired"   <?= $license['status']==='expired'   ?'selected':'' ?>>Expired</option>
                                    <option value="suspended" <?= $license['status']==='suspended' ?'selected':'' ?>>Suspended</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-footer" style="margin-top:14px;">
                            <a href="manage_licenses.php" class="btn-pos btn-ghost">Cancel</a>
                            <button type="submit" class="btn-pos btn-amber" style="height:36px;padding:0 20px;">
                                <i class="fas fa-floppy-disk" style="font-size:.75rem;"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right: Info sidebar -->
        <div style="display:flex;flex-direction:column;gap:16px;">

            <?php
                $dClass = $daysLeft < 0 ? 'exp' : ($daysLeft <= 30 ? 'warn' : 'ok');
                $dIco   = $dClass === 'ok' ? 'green' : ($dClass === 'warn' ? 'amber' : 'red');
            ?>
            <div class="sec">
                <div class="sec-head">
                    <span class="sec-ico i-<?= $dIco ?>"><i class="fas fa-clock"></i></span>
                    <span class="sec-title">Expiry Status</span>
                </div>
                <div style="padding-top:14px;">
                    <div class="days-block <?= $dClass ?>">
                        <div class="days-num <?= $dClass ?>"><?= $daysLeft < 0 ? 'EXPIRED' : $daysLeft ?></div>
                        <div class="days-caption <?= $dClass ?>"><?= $daysLeft < 0 ? 'License expired' : 'days remaining' ?></div>
                    </div>
                </div>
                <div class="sec-body" style="padding-top:4px;">
                    <?php if (!empty($license['activation_date'])): ?>
                    <div class="info-row">
                        <div class="info-ico i-teal" style="width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;"><i class="fas fa-calendar-check"></i></div>
                        <div>
                            <div class="info-label">Activation Date</div>
                            <div class="info-val mono"><?= htmlspecialchars($license['activation_date']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($license['last_renew'])): ?>
                    <div class="info-row">
                        <div class="info-ico i-green" style="width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;"><i class="fas fa-rotate"></i></div>
                        <div>
                            <div class="info-label">Last Renewed</div>
                            <div class="info-val mono"><?= htmlspecialchars($license['last_renew']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <div class="info-ico i-<?= $dIco ?>" style="width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;"><i class="fas fa-calendar-xmark"></i></div>
                        <div>
                            <div class="info-label">Expires</div>
                            <div class="info-val mono"><?= htmlspecialchars($license['expire_date']) ?></div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-ico i-violet" style="width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;"><i class="fas fa-circle-info"></i></div>
                        <div>
                            <div class="info-label">Current Status</div>
                            <div class="info-val" style="margin-top:3px;">
                                <?php
                                    $pillClass = ['active'=>'pill-active','expired'=>'pill-expired','suspended'=>'pill-suspended'][$license['status']] ?? '';
                                    echo '<span class="pill ' . $pillClass . '">' . ucfirst($license['status']) . '</span>';
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Record info -->
            <div class="sec">
                <div class="sec-head">
                    <span class="sec-ico i-violet"><i class="fas fa-circle-info"></i></span>
                    <span class="sec-title">Record Info</span>
                </div>
                <div class="sec-body">
                    <div class="info-row">
                        <div class="info-ico i-blue" style="width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;"><i class="fas fa-hashtag"></i></div>
                        <div>
                            <div class="info-label">License ID</div>
                            <div class="info-val mono">#<?= $license_id ?></div>
                        </div>
                    </div>
                    <?php if (!empty($license['created_at'])): ?>
                    <div class="info-row">
                        <div class="info-ico i-violet" style="width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;"><i class="fas fa-calendar-plus"></i></div>
                        <div>
                            <div class="info-label">Added On</div>
                            <div class="info-val mono"><?= date('d M Y', strtotime($license['created_at'])) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /sidebar -->
    </div><!-- /.main -->
</div><!-- /.page-shell -->
</body>
</html>