<?php
// ========== CONFIGURATION ==========
$branch_name = 'rajaiswari';

// ========== LICENSE DB CONNECTION ==========
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "license_manager";

$license_conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($license_conn->connect_error) {
    die("License database connection failed: " . $license_conn->connect_error);
}

$license_conn->set_charset("utf8mb4");

// ========== FETCH LICENSE ==========
$stmt = $license_conn->prepare("SELECT expire_date, status FROM licenses WHERE branch_name = ? LIMIT 1");
$stmt->bind_param("s", $branch_name);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $expire_date_from_db = $row['expire_date'];
    $status_from_db      = $row['status'];
} else {
    $expire_date_from_db = '2025-01-01';
    $status_from_db      = 'expired';
}
$stmt->close();
$license_conn->close();

// ========== CALCULATE DATES ==========
$expire_date   = date('Y-m-d', strtotime($expire_date_from_db));
$today         = date('Y-m-d');
$date1         = new DateTime($today);
$date2         = new DateTime($expire_date);
$diff          = $date1->diff($date2);
$days_remaining = $diff->days;
$is_expired    = ($today > $expire_date);

$is_blocked = false;
if ($status_from_db == 'expired' || $status_from_db == 'suspended') {
    $is_blocked = true;
}
if ($is_expired) {
    $is_blocked = true;
}

// ========== FORMAT BRANCH NAME ==========
function getShortBranchName($branch_name) {
    $cleaned = preg_replace('/[^a-zA-Z0-9]+/', ' ', $branch_name);
    $words   = explode(' ', trim($cleaned));
    return ucfirst(strtolower($words[0]));
}

$display_branch_name = getShortBranchName($branch_name);

// ========== BADGE COLOR & ALERT ==========
if ($is_blocked) {
    $badge_color = 'expired';
    $alert_color = '#dc2626';
    $alert_text  = 'EXPIRED';
} else {
    if ($days_remaining <= 7) {
        $badge_color = 'critical';
        $alert_color = '#dc2626';
        $alert_text  = 'CRITICAL';
    } elseif ($days_remaining <= 30) {
        $badge_color = 'warning';
        $alert_color = '#d97706';
        $alert_text  = 'WARNING';
    } else {
        $badge_color = 'active';
        $alert_color = '#059669';
        $alert_text  = 'ACTIVE';
    }
}
?>

<?php if ($is_blocked): ?>
<!-- ========== FULL SCREEN LICENSE BLOCK ========== -->
<div class="nb-block-overlay">
    <div class="nb-block-box">
        <div class="nb-block-icon">
            <i class="fas fa-ban"></i>
        </div>
        <h1 class="nb-block-title">LICENSE <?= strtoupper($status_from_db) ?></h1>
        <p class="nb-block-msg">
            <?= $status_from_db == 'suspended'
                ? 'Your license has been suspended. Please contact support.'
                : 'Your license has expired. Please renew to continue.' ?>
        </p>
        <div class="nb-block-details">
            <p><strong>Branch:</strong> <?= htmlspecialchars($branch_name) ?></p>
            <p><strong>Status:</strong> <?= strtoupper($status_from_db) ?></p>
            <p><strong>Expiry:</strong> <?= date('M d, Y', strtotime($expire_date)) ?></p>
        </div>
        <a href="https://wa.me/8801570258084?text=Hi%2C%20I%20need%20to%20renew%20my%20license.%0ABranch%3A%20<?= urlencode($branch_name) ?>%0AExpiry%3A%20<?= urlencode($expire_date) ?>"
           target="_blank" class="nb-wa-btn">
            <i class="fab fa-whatsapp"></i> Contact via WhatsApp
        </a>
        <p style="margin-top:1.25rem;">
            <a href="logout.php" style="color:#6b7280;font-size:.85rem;">Logout</a>
        </p>
    </div>
</div>
<?php endif; ?>

<!-- ========== MOBILE TOPBAR ========== -->
<div class="nb-mobile-bar" id="nbMobileBar">
    <button class="nb-hamburger" id="nbToggle" aria-label="Open menu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <line x1="3" y1="6"  x2="21" y2="6"/>
            <line x1="3" y1="12" x2="21" y2="12"/>
            <line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
    </button>
    <a href="dashboard.php" class="nb-logo-link">
        <img src="rajaiswari-wotbg.png" alt="Rajaiswari" class="nb-logo">
    </a>
    <div class="nb-mini-badge nb-badge-<?= $badge_color ?>" id="nbTopBadge">
        <span class="nb-dot"></span>
        <span><?= $is_blocked ? 'BLOCKED' : $days_remaining . 'd' ?></span>
    </div>
</div>

<!-- ========== SIDEBAR OVERLAY ========== -->
<div class="nb-overlay" id="nbOverlay"></div>

<!-- ========== SIDEBAR ========== -->
<aside class="nb-sidebar" id="nbSidebar">

    <!-- Logo -->
    <div class="nb-sidebar-head">
        <a href="dashboard.php" class="nb-logo-link">
            <img src="rajaiswari-wotbg.png" alt="Rajaiswari" class="nb-logo">
        </a>
    </div>

    <!-- Nav -->
    <nav class="nb-nav">

        <a class="nb-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'nb-active' : '' ?>"
           href="dashboard.php">
            <i class="fas fa-gauge-high"></i><span>Dashboard</span>
        </a>

        <a class="nb-link <?= basename($_SERVER['PHP_SELF']) == 'order.php' ? 'nb-active' : '' ?>"
           href="order.php">
            <i class="fas fa-plus-circle"></i><span>Order</span>
        </a>

        <!-- Customers -->
        <div class="nb-group">
            <div class="nb-group-toggle <?= in_array(basename($_SERVER['PHP_SELF']), ['customers_list.php','create_customer.php']) ? 'nb-active' : '' ?>"
                 data-target="nbCustomers">
                <i class="fas fa-users nb-gi"></i>
                <span>Customers</span>
                <i class="fas fa-chevron-down nb-chevron"></i>
            </div>
            <div class="nb-submenu <?= in_array(basename($_SERVER['PHP_SELF']), ['customers_list.php','create_customer.php']) ? 'nb-open' : '' ?>"
                 id="nbCustomers">
                <a class="nb-link <?= basename($_SERVER['PHP_SELF']) == 'customers_list.php' ? 'nb-active' : '' ?>"
                   href="customers_list.php">
                    <i class="fas fa-list"></i><span>View Customers</span>
                </a>
                <a class="nb-link <?= basename($_SERVER['PHP_SELF']) == 'create_customer.php' ? 'nb-active' : '' ?>"
                   href="create_customer.php">
                    <i class="fas fa-user-plus"></i><span>Add Customer</span>
                </a>
            </div>
        </div>

        <!-- Bills -->
        <div class="nb-group">
            <div class="nb-group-toggle <?= in_array(basename($_SERVER['PHP_SELF']), ['unpaid_bills.php','edit_bills.php']) ? 'nb-active' : '' ?>"
                 data-target="nbBills">
                <i class="fas fa-file-invoice nb-gi"></i>
                <span>Bills</span>
                <i class="fas fa-chevron-down nb-chevron"></i>
            </div>
            <div class="nb-submenu <?= in_array(basename($_SERVER['PHP_SELF']), ['unpaid_bills.php','edit_bills.php']) ? 'nb-open' : '' ?>"
                 id="nbBills">
                <a class="nb-link <?= basename($_SERVER['PHP_SELF']) == 'unpaid_bills.php' ? 'nb-active' : '' ?>"
                   href="unpaid_bills.php">
                    <i class="fas fa-triangle-exclamation"></i><span>Unpaid Bills</span>
                </a>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a class="nb-link <?= basename($_SERVER['PHP_SELF']) == 'edit_bills.php' ? 'nb-active' : '' ?>"
                   href="edit_bills.php">
                    <i class="fas fa-pen-to-square"></i><span>Edit Bills</span>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Customer Reports -->
        <div class="nb-group">
            <div class="nb-group-toggle <?= in_array(basename($_SERVER['PHP_SELF']), ['create_tunch_report.php','create_hallmark_report.php','view_customer_reports.php']) ? 'nb-active' : '' ?>"
                 data-target="nbReports">
                <i class="fas fa-file-lines nb-gi"></i>
                <span>Customer Reports</span>
                <i class="fas fa-chevron-down nb-chevron"></i>
            </div>
            <div class="nb-submenu <?= in_array(basename($_SERVER['PHP_SELF']), ['create_tunch_report.php','create_hallmark_report.php','view_customer_reports.php']) ? 'nb-open' : '' ?>"
                 id="nbReports">
                <a class="nb-link <?= basename($_SERVER['PHP_SELF']) == 'create_tunch_report.php' ? 'nb-active' : '' ?>"
                   href="create_tunch_report.php">
                    <i class="fas fa-plus-square"></i><span>Make Tunch Report</span>
                </a>
                <a class="nb-link <?= basename($_SERVER['PHP_SELF']) == 'create_hallmark_report.php' ? 'nb-active' : '' ?>"
                   href="create_hallmark_report.php">
                    <i class="fas fa-plus-square"></i><span>Make Hallmark Report</span>
                </a>
                <a class="nb-link <?= basename($_SERVER['PHP_SELF']) == 'view_customer_reports.php' ? 'nb-active' : '' ?>"
                   href="view_customer_reports.php">
                    <i class="fas fa-eye"></i><span>View Reports</span>
                </a>
            </div>
        </div>

        <a class="nb-link <?= basename($_SERVER['PHP_SELF']) == 'daily_expenses.php' ? 'nb-active' : '' ?>"
           href="daily_expenses.php">
            <i class="fas fa-wallet"></i><span>Expenses</span>
        </a>

        <a class="nb-link <?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'nb-active' : '' ?>"
           href="reports.php">
            <i class="fas fa-chart-bar"></i><span>Sales Reports</span>
        </a>

        <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'employee'])): ?>
        <!-- Manage -->
        <div class="nb-group">
            <div class="nb-group-toggle <?= in_array(basename($_SERVER['PHP_SELF']), ['users.php','account.php','machine_summary.php']) ? 'nb-active' : '' ?>"
                 data-target="nbManage">
                <i class="fas fa-gear nb-gi"></i>
                <span>Manage</span>
                <i class="fas fa-chevron-down nb-chevron"></i>
            </div>
            <div class="nb-submenu <?= in_array(basename($_SERVER['PHP_SELF']), ['users.php','account.php','machine_summary.php']) ? 'nb-open' : '' ?>"
                 id="nbManage">
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <a class="nb-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'nb-active' : '' ?>"
                   href="users.php">
                    <i class="fas fa-users-gear"></i><span>Manage Users</span>
                </a>
                <a class="nb-link <?= basename($_SERVER['PHP_SELF']) == 'machine_summary.php' ? 'nb-active' : '' ?>"
                   href="machine_summary.php">
                    <i class="fas fa-screwdriver-wrench"></i><span>Machine Inventory</span>
                </a>
                <?php endif; ?>
                <a class="nb-link <?= basename($_SERVER['PHP_SELF']) == 'account.php' ? 'nb-active' : '' ?>"
                   href="account.php">
                    <i class="fas fa-chart-line"></i><span>Finance Panel</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

    </nav>

    <!-- Footer -->
    <div class="nb-footer">

        <!-- License badge -->
        <div class="nb-lic-badge nb-badge-<?= $badge_color ?>" id="nbLicBadge">
            <span class="nb-dot"></span>
            <span class="nb-lic-text">
                <?= $is_blocked ? 'BLOCKED' : $days_remaining . ' days left' ?>
            </span>
            <i class="fas fa-bell" style="font-size:.7rem;margin-left:auto;"></i>
        </div>

        <!-- User card -->
        <div class="nb-user-card">
            <div class="nb-avatar"><i class="fas fa-user"></i></div>
            <div class="nb-user-info">
                <div class="nb-user-name">
                    <?= isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Guest' ?>
                </div>
                <div class="nb-user-branch"><?= htmlspecialchars($branch_name) ?></div>
            </div>
        </div>

        <!-- Logout -->
        <a href="logout.php" class="nb-logout">
            <i class="fas fa-right-from-bracket"></i> Logout
        </a>

    </div>
</aside>

<!-- ========== LICENSE MODAL ========== -->
<div class="nb-modal-wrap" id="nbModal">
    <div class="nb-modal">

        <div class="nb-modal-head" style="background:<?= $alert_color ?>;">
            <div class="nb-modal-head-left">
                <div class="nb-modal-head-ico">
                    <?php if ($is_blocked): ?>
                        <i class="fas fa-ban"></i>
                    <?php elseif ($badge_color == 'critical'): ?>
                        <i class="fas fa-circle-exclamation"></i>
                    <?php elseif ($badge_color == 'warning'): ?>
                        <i class="fas fa-triangle-exclamation"></i>
                    <?php else: ?>
                        <i class="fas fa-shield-halved"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="nb-modal-head-sub">License Status</div>
                    <div class="nb-modal-head-title"><?= $alert_text ?></div>
                </div>
            </div>
            <button class="nb-modal-close" id="nbModalClose">&times;</button>
        </div>

        <div class="nb-modal-body">
            <div class="nb-modal-row">
                <div class="nb-modal-row-ico"><i class="fas fa-store"></i></div>
                <div class="nb-modal-row-body">
                    <div class="nb-modal-row-lbl">Branch</div>
                    <div class="nb-modal-row-val"><?= htmlspecialchars($branch_name) ?></div>
                </div>
            </div>
            <div class="nb-modal-row">
                <div class="nb-modal-row-ico"><i class="fas fa-circle-check"></i></div>
                <div class="nb-modal-row-body">
                    <div class="nb-modal-row-lbl">Status</div>
                    <div class="nb-modal-row-val">
                        <span class="nb-modal-status" style="background:<?= $alert_color ?>;">
                            <span class="nb-sdot"></span><?= $alert_text ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="nb-modal-row">
                <div class="nb-modal-row-ico"><i class="fas fa-calendar-alt"></i></div>
                <div class="nb-modal-row-body">
                    <div class="nb-modal-row-lbl">Expiry Date</div>
                    <div class="nb-modal-row-val" style="color:<?= $alert_color ?>;">
                        <?= date('d M Y', strtotime($expire_date)) ?>
                    </div>
                </div>
            </div>
            <div class="nb-modal-row" style="border-bottom:none;">
                <div class="nb-modal-row-ico"><i class="fas fa-clock"></i></div>
                <div class="nb-modal-row-body">
                    <div class="nb-modal-row-lbl"><?= $is_blocked ? 'Notice' : 'Remaining' ?></div>
                    <?php if ($is_blocked): ?>
                        <div class="nb-modal-row-val" style="color:<?= $alert_color ?>;font-size:.8rem;white-space:normal;">
                            <?= $status_from_db == 'suspended' ? 'License suspended. Contact support.' : 'License expired. Renew to continue.' ?>
                        </div>
                    <?php else: ?>
                        <div style="display:flex;align-items:baseline;gap:.3rem;">
                            <span style="font-size:1.6rem;font-weight:800;color:<?= $alert_color ?>;line-height:1;">
                                <?= $days_remaining ?>
                            </span>
                            <span style="font-size:.7rem;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;">
                                days left
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="nb-modal-foot">
            <a href="https://wa.me/8801570258084?text=Hi%2C%20I%20need%20help%20with%20my%20license.%0ABranch%3A%20<?= urlencode($branch_name) ?>%0AStatus%3A%20<?= strtoupper($status_from_db) ?>%0AExpiry%3A%20<?= date('M d, Y', strtotime($expire_date)) ?>"
               target="_blank" class="nb-wa-btn-sm">
                <i class="fab fa-whatsapp"></i> WhatsApp
            </a>
            <button class="nb-modal-close-btn" id="nbModalCloseFooter">Close</button>
        </div>

    </div>
</div>

<!-- ========== NAVBAR SCOPED CSS ========== -->
<style>
/* All navbar styles are prefixed nb- to prevent bleeding into page CSS */

/* ── Sidebar shell ───────────────────────────────────────── */
.nb-sidebar {
    position: fixed;
    top: 0; left: 0;
    width: 200px;
    min-width: 200px;
    height: 100vh;
    z-index: 1040;
    background: linear-gradient(180deg, #f8f9fa 0%, #e9ecef 100%);
    border-right: 1px solid rgba(0,0,0,.08);
    box-shadow: 2px 0 12px rgba(0,0,0,.07);
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    overflow-x: hidden;
    transition: transform .3s ease;
}

/* ── Sidebar header ──────────────────────────────────────── */
.nb-sidebar-head {
    padding: 1rem;
    border-bottom: 1px solid rgba(0,0,0,.07);
    flex-shrink: 0;
}

.nb-logo-link { display: inline-flex; align-items: center; }

.nb-logo {
    height: 34px;
    width: auto;
    max-width: 130px;
    object-fit: contain;
}

/* ── Nav ─────────────────────────────────────────────────── */
.nb-nav {
    flex: 1;
    padding: .5rem 0;
    overflow-y: auto;
    overflow-x: hidden;
}

.nb-link {
    display: flex;
    align-items: center;
    gap: .55rem;
    padding: .48rem 1rem;
    color: #495057;
    text-decoration: none;
    font-size: .8375rem;
    font-weight: 500;
    position: relative;
    white-space: nowrap;
    transition: background .15s, color .15s;
}

.nb-link i {
    width: 15px;
    font-size: .8rem;
    flex-shrink: 0;
    text-align: center;
}

.nb-link:hover {
    background: rgba(13,202,240,.08);
    color: #0dcaf0;
    text-decoration: none;
}

.nb-link.nb-active {
    background: rgba(13,202,240,.13);
    color: #0dcaf0;
}

.nb-link.nb-active::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 3px;
    background: #0dcaf0;
    border-radius: 0 3px 3px 0;
}

/* ── Groups / submenus ───────────────────────────────────── */
.nb-group { position: relative; }

.nb-group-toggle {
    display: flex;
    align-items: center;
    gap: .55rem;
    padding: .48rem 1rem;
    color: #495057;
    font-size: .8375rem;
    font-weight: 500;
    cursor: pointer;
    user-select: none;
    white-space: nowrap;
    transition: background .15s, color .15s;
}

.nb-gi {
    width: 15px;
    font-size: .8rem;
    flex-shrink: 0;
    text-align: center;
}

.nb-group-toggle:hover { background: rgba(108,117,125,.08); color: #343a40; }
.nb-group-toggle.nb-active { background: rgba(13,202,240,.1); color: #0dcaf0; }

.nb-chevron {
    margin-left: auto;
    font-size: .65rem;
    transition: transform .22s ease;
    flex-shrink: 0;
}

.nb-group-toggle.nb-open .nb-chevron { transform: rotate(180deg); }

.nb-submenu {
    display: none;
    background: rgba(0,0,0,.02);
    border-left: 2px solid rgba(13,202,240,.2);
    margin-left: 1rem;
}

.nb-submenu.nb-open { display: block; }

.nb-submenu .nb-link {
    padding: .4rem .8rem .4rem .85rem;
    font-size: .8rem;
    color: #6c757d;
}

.nb-submenu .nb-link:hover { color: #0dcaf0; background: rgba(13,202,240,.07); }
.nb-submenu .nb-link.nb-active { color: #0dcaf0; }

/* ── Sidebar footer ──────────────────────────────────────── */
.nb-footer {
    flex-shrink: 0;
    border-top: 1px solid rgba(0,0,0,.07);
    padding: .75rem;
}

/* License badge */
.nb-lic-badge {
    display: flex;
    align-items: center;
    gap: .4rem;
    padding: .42rem .65rem;
    border-radius: 8px;
    font-size: .75rem;
    font-weight: 600;
    cursor: pointer;
    border: 1.5px solid;
    margin-bottom: .55rem;
    transition: all .2s;
    width: 100%;
}

.nb-lic-badge:hover { filter: brightness(.95); transform: scale(1.01); }

.nb-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    background: currentColor;
    flex-shrink: 0;
    animation: nb-pulse 2s infinite;
}

.nb-badge-active   { background: #f0fdf4; color: #22c55e; border-color: #22c55e; }
.nb-badge-warning  { background: #fffbeb; color: #d97706; border-color: #d97706; }
.nb-badge-critical { background: #fef2f2; color: #dc2626; border-color: #dc2626; }
.nb-badge-expired  {
    background: #dc2626; color: #fff; border-color: #991b1b;
    box-shadow: 0 0 10px rgba(220,38,38,.4);
    animation: nb-vibrate .45s infinite;
}

@keyframes nb-pulse {
    0%,100% { opacity:1; transform:scale(1); }
    50%      { opacity:.6; transform:scale(1.2); }
}

@keyframes nb-vibrate {
    0%,100% { transform:translateX(0); }
    20%     { transform:translateX(-2px); }
    40%     { transform:translateX(2px); }
    60%     { transform:translateX(-2px); }
    80%     { transform:translateX(2px); }
}

/* User card */
.nb-user-card {
    display: flex;
    align-items: center;
    gap: .5rem;
    background: rgba(108,117,125,.06);
    border: 1px solid rgba(108,117,125,.11);
    border-radius: 9px;
    padding: .6rem .7rem;
    margin-bottom: .55rem;
}

.nb-avatar {
    width: 30px; height: 30px;
    background: rgba(13,202,240,.13);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: #0dcaf0;
    font-size: .85rem;
    flex-shrink: 0;
}

.nb-user-info { flex: 1; min-width: 0; }

.nb-user-name {
    font-size: .8rem; font-weight: 600; color: #343a40;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

.nb-user-branch {
    font-size: .7rem; color: #6c757d;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

/* Logout */
.nb-logout {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .4rem;
    width: 100%;
    padding: .42rem .75rem;
    background: linear-gradient(135deg, #fde8ea 0%, #f9c8cd 100%);
    border: 1px solid #f9c8cd;
    border-radius: 8px;
    color: #842029;
    font-size: .8rem;
    font-weight: 500;
    text-decoration: none;
    transition: all .2s;
}

.nb-logout:hover {
    background: linear-gradient(135deg, #f9c8cd 0%, #f5adb5 100%);
    color: #721c24;
    text-decoration: none;
    transform: translateY(-1px);
}

/* ── Mobile topbar ───────────────────────────────────────── */
.nb-mobile-bar {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0;
    z-index: 1050;
    height: 52px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid rgba(0,0,0,.08);
    box-shadow: 0 2px 8px rgba(0,0,0,.07);
    padding: 0 1rem;
    align-items: center;
    gap: .75rem;
}

.nb-hamburger {
    background: none;
    border: 1px solid rgba(108,117,125,.22);
    border-radius: 6px;
    padding: .28rem .45rem;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: #495057;
    transition: background .2s;
}

.nb-hamburger:hover { background: rgba(108,117,125,.1); }
.nb-hamburger svg   { width: 17px; height: 17px; }

.nb-mini-badge {
    margin-left: auto;
    display: flex; align-items: center; gap: .3rem;
    padding: .28rem .6rem;
    border-radius: 20px;
    font-size: .72rem; font-weight: 600;
    border: 1.5px solid;
    cursor: pointer;
}

/* ── Overlay ─────────────────────────────────────────────── */
.nb-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,.38);
    z-index: 1039;
}

.nb-overlay.nb-show { display: block; }

/* ── License block overlay ───────────────────────────────── */
.nb-block-overlay {
    position: fixed; inset: 0;
    background: rgba(220,38,38,.94);
    z-index: 9999;
    display: flex; align-items: center; justify-content: center;
}

.nb-block-box {
    background: #fff;
    padding: 2.5rem;
    border-radius: 18px;
    box-shadow: 0 20px 60px rgba(0,0,0,.45);
    text-align: center;
    max-width: 480px;
    width: 90%;
}

.nb-block-icon {
    font-size: 4.5rem; color: #dc2626;
    margin-bottom: 1.25rem;
    animation: nb-shake .5s infinite;
}

@keyframes nb-shake {
    0%,100% { transform: rotate(0); }
    25%     { transform: rotate(-10deg); }
    75%     { transform: rotate(10deg); }
}

.nb-block-title {
    font-size: 1.75rem; font-weight: 800;
    color: #dc2626; margin-bottom: .75rem;
}

.nb-block-msg {
    font-size: 1rem; color: #4b5563; margin-bottom: 1.5rem;
}

.nb-block-details {
    background: #f9fafb; padding: 1.25rem;
    border-radius: 10px; margin-bottom: 1.5rem;
    text-align: left;
}

.nb-block-details p { margin: .4rem 0; font-size: .9rem; color: #374151; }
.nb-block-details strong { color: #dc2626; }

.nb-wa-btn {
    display: inline-flex; align-items: center; gap: .5rem;
    background: #25D366; color: #fff;
    padding: .85rem 2rem;
    border-radius: 10px; border: none;
    font-size: 1rem; font-weight: 700;
    text-decoration: none;
    transition: background .15s;
}

.nb-wa-btn:hover { background: #1ebe5d; color: #fff; text-decoration: none; }

/* ── License modal ───────────────────────────────────────── */
.nb-modal-wrap {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,.65);
    z-index: 99999;
    align-items: center; justify-content: center;
    backdrop-filter: blur(3px);
}

.nb-modal-wrap.nb-show { display: flex; }

.nb-modal {
    background: #fff;
    border-radius: 12px;
    width: 350px;
    max-width: 93vw;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,.3);
}

.nb-modal-head {
    display: flex; align-items: center;
    justify-content: space-between;
    padding: 1rem 1.1rem;
}

.nb-modal-head-left {
    display: flex; align-items: center; gap: .5rem;
}

.nb-modal-head-ico {
    width: 32px; height: 32px;
    background: rgba(255,255,255,.22);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: .95rem; color: #fff; flex-shrink: 0;
}

.nb-modal-head-sub {
    font-size: .68rem; font-weight: 500;
    opacity: .8; text-transform: uppercase;
    letter-spacing: .07em; color: #fff;
}

.nb-modal-head-title {
    font-size: 1rem; font-weight: 800;
    color: #fff; letter-spacing: .01em;
}

.nb-modal-close {
    background: rgba(255,255,255,.18);
    border: none; color: #fff;
    width: 26px; height: 26px;
    border-radius: 50%;
    cursor: pointer; font-size: 1rem;
    display: flex; align-items: center; justify-content: center;
    transition: background .15s;
}

.nb-modal-close:hover { background: rgba(255,255,255,.3); }

.nb-modal-body { background: #fff; }

.nb-modal-row {
    display: flex; align-items: center;
    padding: .65rem 1.1rem;
    border-bottom: 1px solid #f1f3f5;
    gap: .75rem;
}

.nb-modal-row-ico {
    width: 28px; height: 28px;
    border-radius: 7px;
    background: #f1f3f5;
    display: flex; align-items: center; justify-content: center;
    font-size: .75rem; color: #6c757d; flex-shrink: 0;
}

.nb-modal-row-body { flex: 1; min-width: 0; }

.nb-modal-row-lbl {
    font-size: .67rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .06em;
    color: #adb5bd; margin-bottom: .15rem;
}

.nb-modal-row-val {
    font-size: .9rem; font-weight: 700; color: #212529;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

.nb-modal-status {
    display: inline-flex; align-items: center; gap: .3rem;
    color: #fff; font-size: .7rem; font-weight: 800;
    letter-spacing: .07em; text-transform: uppercase;
    padding: .2rem .6rem; border-radius: 5px;
}

.nb-sdot {
    width: 5px; height: 5px;
    border-radius: 50%; background: rgba(255,255,255,.7);
}

.nb-modal-foot {
    background: #f8f9fa;
    border-top: 1px solid #e9ecef;
    padding: .7rem 1.1rem;
    display: flex; align-items: center;
    justify-content: space-between; gap: .5rem;
}

.nb-wa-btn-sm {
    display: inline-flex; align-items: center; gap: .4rem;
    background: #25D366; color: #fff;
    font-size: .8rem; font-weight: 700;
    padding: .38rem .85rem;
    border-radius: 7px; border: none;
    text-decoration: none; cursor: pointer;
    transition: background .15s;
}

.nb-wa-btn-sm:hover { background: #1ebe5d; color: #fff; text-decoration: none; }

.nb-modal-close-btn {
    background: #e9ecef; color: #495057;
    font-size: .8rem; font-weight: 600;
    padding: .38rem .85rem;
    border-radius: 7px; border: none;
    cursor: pointer; transition: background .15s;
}

.nb-modal-close-btn:hover { background: #dee2e6; }

/* ── Responsive ──────────────────────────────────────────── */
@media (max-width: 991.98px) {
    .nb-sidebar {
        transform: translateX(-100%);
        width: 230px; min-width: 230px;
        z-index: 1041;
    }
    .nb-sidebar.nb-open { transform: translateX(0); }
    .nb-mobile-bar { display: flex; }
}

@media (min-width: 992px) {
    .nb-mobile-bar { display: none !important; }
    .nb-sidebar { transform: none !important; }
}
</style>

<!-- ========== NAVBAR JS ========== -->
<script>
(function () {
    // ── Mobile sidebar ────────────────────────────────────
    var sidebar  = document.getElementById('nbSidebar');
    var overlay  = document.getElementById('nbOverlay');
    var toggle   = document.getElementById('nbToggle');

    function openSidebar()  { sidebar.classList.add('nb-open');  overlay.classList.add('nb-show');  document.body.style.overflow = 'hidden'; }
    function closeSidebar() { sidebar.classList.remove('nb-open'); overlay.classList.remove('nb-show'); document.body.style.overflow = ''; }

    if (toggle)  toggle.addEventListener('click', function () { sidebar.classList.contains('nb-open') ? closeSidebar() : openSidebar(); });
    if (overlay) overlay.addEventListener('click', closeSidebar);

    // ── Collapsible submenus ──────────────────────────────
    document.querySelectorAll('.nb-group-toggle').forEach(function (t) {
        var menu = document.getElementById(t.getAttribute('data-target'));
        if (menu && menu.classList.contains('nb-open')) t.classList.add('nb-open');

        t.addEventListener('click', function () {
            if (!menu) return;
            var isOpen = menu.classList.contains('nb-open');

            document.querySelectorAll('.nb-submenu').forEach(function (m) { m.classList.remove('nb-open'); });
            document.querySelectorAll('.nb-group-toggle').forEach(function (x) { x.classList.remove('nb-open'); });

            if (!isOpen) { menu.classList.add('nb-open'); t.classList.add('nb-open'); }
        });
    });

    // ── License modal ─────────────────────────────────────
    var modal       = document.getElementById('nbModal');
    var licBadge    = document.getElementById('nbLicBadge');
    var topBadge    = document.getElementById('nbTopBadge');
    var closeX      = document.getElementById('nbModalClose');
    var closeFoot   = document.getElementById('nbModalCloseFooter');

    function openModal()  { modal.classList.add('nb-show');    document.body.style.overflow = 'hidden'; }
    function closeModal() { modal.classList.remove('nb-show'); document.body.style.overflow = ''; }

    if (licBadge)  licBadge.addEventListener('click', openModal);
    if (topBadge)  topBadge.addEventListener('click', openModal);
    if (closeX)    closeX.addEventListener('click', closeModal);
    if (closeFoot) closeFoot.addEventListener('click', closeModal);

    if (modal) {
        modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    }

    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
})();
</script>