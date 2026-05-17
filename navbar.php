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
            <i class="fab fa-whatsapp"></i> <span>Contact via WhatsApp</span>
        </a>
        <p class="nb-block-logout-wrap">
            <a href="logout.php" class="nb-block-logout">Logout</a>
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
        <span class="nb-mini-text"><?= $is_blocked ? 'BLOCKED' : $days_remaining . 'd' ?></span>
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
                <span>Reports</span>
                <i class="fas fa-chevron-down nb-chevron"></i>
            </div>
            <div class="nb-submenu <?= in_array(basename($_SERVER['PHP_SELF']), ['create_tunch_report.php','create_hallmark_report.php','view_customer_reports.php']) ? 'nb-open' : '' ?>"
                 id="nbReports">
                <a class="nb-link <?= basename($_SERVER['PHP_SELF']) == 'create_tunch_report.php' ? 'nb-active' : '' ?>"
                   href="create_tunch_report.php">
                    <i class="fas fa-plus-square"></i><span>Tunch Report</span>
                </a>
                <a class="nb-link <?= basename($_SERVER['PHP_SELF']) == 'create_hallmark_report.php' ? 'nb-active' : '' ?>"
                   href="create_hallmark_report.php">
                    <i class="fas fa-plus-square"></i><span>Hallmark Report</span>
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
            <div class="nb-group-toggle <?= in_array(basename($_SERVER['PHP_SELF']), ['users.php','account.php','machine_summary.php','manage_licenses.php']) ? 'nb-active' : '' ?>"
                 data-target="nbManage">
                <i class="fas fa-gear nb-gi"></i>
                <span>Manage</span>
                <i class="fas fa-chevron-down nb-chevron"></i>
            </div>
            <div class="nb-submenu <?= in_array(basename($_SERVER['PHP_SELF']), ['users.php','account.php','machine_summary.php','manage_licenses.php']) ? 'nb-open' : '' ?>"
                 id="nbManage">
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <a class="nb-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'nb-active' : '' ?>"
                   href="users.php">
                    <i class="fas fa-users-gear"></i><span>Users</span>
                </a>
                <a class="nb-link <?= basename($_SERVER['PHP_SELF']) == 'machine_summary.php' ? 'nb-active' : '' ?>"
                   href="machine_summary.php">
                    <i class="fas fa-screwdriver-wrench"></i><span>Machines</span>
                </a>
                <?php endif; ?>
                <a class="nb-link <?= basename($_SERVER['PHP_SELF']) == 'account.php' ? 'nb-active' : '' ?>"
                   href="account.php">
                    <i class="fas fa-chart-line"></i><span>Finance</span>
                </a>
                <a class="nb-link <?= basename($_SERVER['PHP_SELF']) == 'manage_licenses.php' ? 'nb-active' : '' ?>"
                   href="manage_licenses.php">
                    <i class="fas fa-key"></i><span>Licenses</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

    </nav>

    <!-- Footer -->
    <div class="nb-footer">
        <!-- Greeting card -->
        <div class="nb-greet-card" id="nbGreetCard">
            <img class="nb-greet-img" id="nbGreetImg" src="" alt="">
            <div class="nb-greet-text" id="nbGreetText"></div>
        </div>

        <!-- License badge -->
        <div class="nb-lic-badge nb-badge-<?= $badge_color ?>" id="nbLicBadge">
            <span class="nb-dot"></span>
            <span class="nb-lic-text">
                <?= $is_blocked ? 'BLOCKED' : $days_remaining . ' days left' ?>
            </span>
            <i class="fas fa-bell nb-lic-bell"></i>
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
            <i class="fas fa-right-from-bracket"></i> <span>Logout</span>
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
            <button class="nb-modal-close" id="nbModalClose" aria-label="Close">&times;</button>
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
            <div class="nb-modal-row nb-modal-row-last">
                <div class="nb-modal-row-ico"><i class="fas fa-clock"></i></div>
                <div class="nb-modal-row-body">
                    <div class="nb-modal-row-lbl"><?= $is_blocked ? 'Notice' : 'Remaining' ?></div>
                    <?php if ($is_blocked): ?>
                        <div class="nb-modal-row-val nb-modal-notice" style="color:<?= $alert_color ?>;">
                            <?= $status_from_db == 'suspended' ? 'License suspended. Contact support.' : 'License expired. Renew to continue.' ?>
                        </div>
                    <?php else: ?>
                        <div class="nb-modal-days-wrap">
                            <span class="nb-modal-days-num" style="color:<?= $alert_color ?>;">
                                <?= $days_remaining ?>
                            </span>
                            <span class="nb-modal-days-label">days left</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="nb-modal-foot">
            <a href="https://wa.me/8801570258084?text=Hi%2C%20I%20need%20help%20with%20my%20license.%0ABranch%3A%20<?= urlencode($branch_name) ?>%0AStatus%3A%20<?= strtoupper($status_from_db) ?>%0AExpiry%3A%20<?= date('M d, Y', strtotime($expire_date)) ?>"
               target="_blank" class="nb-wa-btn-sm">
                <i class="fab fa-whatsapp"></i> <span>WhatsApp</span>
            </a>
            <button class="nb-modal-close-btn" id="nbModalCloseFooter">Close</button>
        </div>

    </div>
</div>

<!-- ========== NAVBAR SCOPED CSS ========== -->
<style>
/* ── Reset & Base ───────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }

/* ── Sidebar shell ───────────────────────────────────────── */
.nb-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 200px;
    min-width: 200px;
    height: 100vh;
    height: 100dvh; /* dynamic viewport height for mobile browsers */
    z-index: 1040;
    background: linear-gradient(180deg, #f8f9fa 0%, #e9ecef 100%);
    border-right: 1px solid rgba(0,0,0,.08);
    box-shadow: 2px 0 12px rgba(0,0,0,.07);
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
    transition: transform .3s cubic-bezier(.4,0,.2,1);
    /* Safe area for notch phones */
    padding-top: env(safe-area-inset-top, 0);
    padding-bottom: env(safe-area-inset-bottom, 0);
    padding-left: env(safe-area-inset-left, 0);
}

/* Custom scrollbar for sidebar */
.nb-sidebar::-webkit-scrollbar { width: 4px; }
.nb-sidebar::-webkit-scrollbar-track { background: transparent; }
.nb-sidebar::-webkit-scrollbar-thumb { background: rgba(0,0,0,.12); border-radius: 4px; }
.nb-sidebar::-webkit-scrollbar-thumb:hover { background: rgba(0,0,0,.2); }

/* ── Sidebar header ──────────────────────────────────────── */
.nb-sidebar-head {
    padding: 1rem 1rem .85rem;
    border-bottom: 1px solid rgba(0,0,0,.07);
    flex-shrink: 0;
}

.nb-logo-link {
    display: inline-flex;
    align-items: center;
    text-decoration: none;
}

.nb-logo {
    height: 36px;
    width: auto;
    max-width: 140px;
    object-fit: contain;
}

/* ── Nav ─────────────────────────────────────────────────── */
.nb-nav {
    flex: 1 1 auto;
    padding: .4rem 0;
    overflow-y: auto;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
    min-height: 0; /* allow flex child to shrink */
}

.nb-nav::-webkit-scrollbar { width: 3px; }
.nb-nav::-webkit-scrollbar-track { background: transparent; }
.nb-nav::-webkit-scrollbar-thumb { background: rgba(0,0,0,.1); border-radius: 3px; }

.nb-link {
    display: flex;
    align-items: center;
    gap: .6rem;
    padding: .5rem 1rem;
    color: #495057;
    text-decoration: none;
    font-size: .84rem;
    font-weight: 500;
    position: relative;
    white-space: nowrap;
    transition: background .15s, color .15s;
    /* Ensure minimum touch target */
    min-height: 40px;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
}

.nb-link i {
    width: 16px;
    font-size: .82rem;
    flex-shrink: 0;
    text-align: center;
}

.nb-link:hover {
    background: rgba(13,202,240,.08);
    color: #0dcaf0;
    text-decoration: none;
}

.nb-link:active {
    background: rgba(13,202,240,.15);
}

.nb-link.nb-active {
    background: rgba(13,202,240,.13);
    color: #0dcaf0;
    font-weight: 600;
}

.nb-link.nb-active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: #0dcaf0;
    border-radius: 0 3px 3px 0;
}

/* ── Groups / submenus ───────────────────────────────────── */
.nb-group { position: relative; }

.nb-group-toggle {
    display: flex;
    align-items: center;
    gap: .6rem;
    padding: .5rem 1rem;
    color: #495057;
    font-size: .84rem;
    font-weight: 500;
    cursor: pointer;
    user-select: none;
    white-space: nowrap;
    transition: background .15s, color .15s;
    min-height: 40px;
    -webkit-tap-highlight-color: transparent;
}

.nb-gi {
    width: 16px;
    font-size: .82rem;
    flex-shrink: 0;
    text-align: center;
}

.nb-group-toggle:hover {
    background: rgba(108,117,125,.08);
    color: #343a40;
}

.nb-group-toggle:active {
    background: rgba(108,117,125,.12);
}

.nb-group-toggle.nb-active {
    background: rgba(13,202,240,.1);
    color: #0dcaf0;
}

.nb-chevron {
    margin-left: auto;
    font-size: .6rem;
    transition: transform .25s cubic-bezier(.4,0,.2,1);
    flex-shrink: 0;
    opacity: .6;
}

.nb-group-toggle.nb-open .nb-chevron {
    transform: rotate(180deg);
    opacity: 1;
}

.nb-submenu {
    display: none;
    background: rgba(0,0,0,.015);
    border-left: 2px solid rgba(13,202,240,.18);
    margin-left: 1rem;
}

.nb-submenu.nb-open {
    display: block;
}

.nb-submenu .nb-link {
    padding: .42rem .85rem .42rem .9rem;
    font-size: .8rem;
    color: #6c757d;
    min-height: 36px;
}

.nb-submenu .nb-link:hover {
    color: #0dcaf0;
    background: rgba(13,202,240,.07);
}

.nb-submenu .nb-link.nb-active {
    color: #0dcaf0;
    background: rgba(13,202,240,.1);
}

/* ── Sidebar footer ──────────────────────────────────────── */
.nb-footer {
    flex-shrink: 0;
    border-top: 1px solid rgba(0,0,0,.07);
    padding: .65rem .75rem;
}

/* ── Greeting card ───────────────────────────────────────── */
.nb-greet-card {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: .5rem;
    background: rgba(13,202,240,.06);
    border: 1px solid rgba(13,202,240,.15);
    border-radius: 10px;
    padding: .45rem .6rem;
    margin-bottom: .5rem;
    min-height: 0;
}

.nb-greet-img {
    width: 30px;
    height: 30px;
    object-fit: contain;
    flex-shrink: 0;
}

.nb-greet-text {
    font-size: .7rem;
    font-weight: 700;
    color: #0891b2;
    line-height: 1.35;
    overflow: hidden;
}

.nb-greet-text small {
    display: block;
    font-size: .62rem;
    font-weight: 400;
    color: #64748b;
    margin-top: 1px;
}

/* ── License badge ───────────────────────────────────────── */
.nb-lic-badge {
    display: flex;
    align-items: center;
    gap: .4rem;
    padding: .42rem .65rem;
    border-radius: 8px;
    font-size: .73rem;
    font-weight: 600;
    cursor: pointer;
    border: 1.5px solid;
    margin-bottom: .5rem;
    transition: all .2s;
    width: 100%;
    min-height: 38px;
    -webkit-tap-highlight-color: transparent;
}

.nb-lic-badge:hover {
    filter: brightness(.95);
    transform: scale(1.01);
}

.nb-lic-badge:active {
    transform: scale(.98);
}

.nb-lic-text {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.nb-lic-bell {
    font-size: .65rem;
    margin-left: auto;
    flex-shrink: 0;
    opacity: .7;
}

.nb-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: currentColor;
    flex-shrink: 0;
    animation: nb-pulse 2s infinite;
}

.nb-badge-active {
    background: #f0fdf4;
    color: #22c55e;
    border-color: rgba(34,197,94,.4);
}
.nb-badge-warning {
    background: #fffbeb;
    color: #d97706;
    border-color: rgba(217,119,6,.4);
}
.nb-badge-critical {
    background: #fef2f2;
    color: #dc2626;
    border-color: rgba(220,38,38,.4);
}
.nb-badge-expired {
    background: #dc2626;
    color: #fff;
    border-color: #991b1b;
    box-shadow: 0 0 10px rgba(220,38,38,.4);
    animation: nb-vibrate .45s infinite;
}

@keyframes nb-pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: .6; transform: scale(1.3); }
}

@keyframes nb-vibrate {
    0%, 100% { transform: translateX(0); }
    20% { transform: translateX(-2px); }
    40% { transform: translateX(2px); }
    60% { transform: translateX(-2px); }
    80% { transform: translateX(2px); }
}

/* ── User card ───────────────────────────────────────────── */
.nb-user-card {
    display: flex;
    align-items: center;
    gap: .55rem;
    background: rgba(108,117,125,.06);
    border: 1px solid rgba(108,117,125,.11);
    border-radius: 9px;
    padding: .55rem .65rem;
    margin-bottom: .5rem;
    min-height: 0;
}

.nb-avatar {
    width: 32px;
    height: 32px;
    background: rgba(13,202,240,.13);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0dcaf0;
    font-size: .85rem;
    flex-shrink: 0;
}

.nb-user-info {
    flex: 1;
    min-width: 0;
}

.nb-user-name {
    font-size: .8rem;
    font-weight: 600;
    color: #343a40;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.nb-user-branch {
    font-size: .68rem;
    color: #6c757d;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ── Logout ──────────────────────────────────────────────── */
.nb-logout {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .45rem;
    width: 100%;
    padding: .45rem .75rem;
    background: linear-gradient(135deg, #fde8ea 0%, #f9c8cd 100%);
    border: 1px solid #f9c8cd;
    border-radius: 8px;
    color: #842029;
    font-size: .8rem;
    font-weight: 500;
    text-decoration: none;
    transition: all .2s;
    min-height: 38px;
    -webkit-tap-highlight-color: transparent;
}

.nb-logout:hover {
    background: linear-gradient(135deg, #f9c8cd 0%, #f5adb5 100%);
    color: #721c24;
    text-decoration: none;
    transform: translateY(-1px);
}

.nb-logout:active {
    transform: translateY(0);
}

/* ── Mobile topbar ───────────────────────────────────────── */
.nb-mobile-bar {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1050;
    height: 54px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid rgba(0,0,0,.08);
    box-shadow: 0 2px 8px rgba(0,0,0,.07);
    padding: 0 .85rem;
    align-items: center;
    gap: .65rem;
    /* Safe area for notch phones */
    padding-top: env(safe-area-inset-top, 0);
    padding-left: calc(.85rem + env(safe-area-inset-left, 0));
    padding-right: calc(.85rem + env(safe-area-inset-right, 0));
}

.nb-hamburger {
    background: none;
    border: 1px solid rgba(108,117,125,.22);
    border-radius: 7px;
    padding: .35rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #495057;
    transition: background .2s;
    flex-shrink: 0;
    width: 38px;
    height: 38px;
    -webkit-tap-highlight-color: transparent;
}

.nb-hamburger:hover {
    background: rgba(108,117,125,.1);
}

.nb-hamburger:active {
    background: rgba(108,117,125,.15);
}

.nb-hamburger svg {
    width: 18px;
    height: 18px;
}

.nb-mobile-bar .nb-logo {
    height: 32px;
    max-width: 120px;
    flex-shrink: 1;
    min-width: 0;
}

.nb-mini-badge {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: .3rem;
    padding: .25rem .55rem;
    border-radius: 20px;
    font-size: .7rem;
    font-weight: 600;
    border: 1.5px solid;
    cursor: pointer;
    flex-shrink: 0;
    -webkit-tap-highlight-color: transparent;
    min-height: 28px;
}

.nb-mini-text {
    white-space: nowrap;
}

/* ── Overlay ─────────────────────────────────────────────── */
.nb-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.4);
    z-index: 1039;
    -webkit-tap-highlight-color: transparent;
}

.nb-overlay.nb-show {
    display: block;
}

/* ── License block overlay ───────────────────────────────── */
.nb-block-overlay {
    position: fixed;
    inset: 0;
    background: rgba(220,38,38,.94);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    /* Safe area */
    padding-top: env(safe-area-inset-top, 1rem);
    padding-bottom: env(safe-area-inset-bottom, 1rem);
    padding-left: env(safe-area-inset-left, 1rem);
    padding-right: env(safe-area-inset-right, 1rem);
}

.nb-block-box {
    background: #fff;
    padding: 2rem 1.75rem;
    border-radius: 18px;
    box-shadow: 0 20px 60px rgba(0,0,0,.45);
    text-align: center;
    max-width: 440px;
    width: 100%;
}

.nb-block-icon {
    font-size: 3.5rem;
    color: #dc2626;
    margin-bottom: 1rem;
    animation: nb-shake .5s infinite;
}

@keyframes nb-shake {
    0%, 100% { transform: rotate(0); }
    25% { transform: rotate(-10deg); }
    75% { transform: rotate(10deg); }
}

.nb-block-title {
    font-size: 1.5rem;
    font-weight: 800;
    color: #dc2626;
    margin-bottom: .6rem;
    line-height: 1.2;
}

.nb-block-msg {
    font-size: .92rem;
    color: #4b5563;
    margin-bottom: 1.25rem;
    line-height: 1.5;
}

.nb-block-details {
    background: #f9fafb;
    padding: 1rem 1.15rem;
    border-radius: 10px;
    margin-bottom: 1.25rem;
    text-align: left;
}

.nb-block-details p {
    margin: .35rem 0;
    font-size: .85rem;
    color: #374151;
    line-height: 1.4;
    word-break: break-word;
}

.nb-block-details strong {
    color: #dc2626;
}

.nb-wa-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    background: #25D366;
    color: #fff;
    padding: .75rem 1.75rem;
    border-radius: 10px;
    border: none;
    font-size: .92rem;
    font-weight: 700;
    text-decoration: none;
    transition: background .15s, transform .15s;
    width: 100%;
    max-width: 300px;
    min-height: 44px;
    -webkit-tap-highlight-color: transparent;
}

.nb-wa-btn:hover {
    background: #1ebe5d;
    color: #fff;
    text-decoration: none;
}

.nb-wa-btn:active {
    transform: scale(.97);
}

.nb-block-logout-wrap {
    margin-top: 1rem;
}

.nb-block-logout {
    color: #6b7280;
    font-size: .82rem;
    text-decoration: none;
    -webkit-tap-highlight-color: transparent;
}

.nb-block-logout:hover {
    color: #374151;
    text-decoration: underline;
}

/* ── License modal ───────────────────────────────────────── */
.nb-modal-wrap {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.6);
    z-index: 99999;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(3px);
    -webkit-backdrop-filter: blur(3px);
    padding: 1rem;
    /* Safe area */
    padding-top: env(safe-area-inset-top, 1rem);
    padding-bottom: env(safe-area-inset-bottom, 1rem);
}

.nb-modal-wrap.nb-show {
    display: flex;
}

.nb-modal {
    background: #fff;
    border-radius: 14px;
    width: 360px;
    max-width: 100%;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,.3);
    max-height: calc(100vh - 2rem);
    max-height: calc(100dvh - 2rem);
    display: flex;
    flex-direction: column;
}

.nb-modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .9rem 1rem;
    flex-shrink: 0;
}

.nb-modal-head-left {
    display: flex;
    align-items: center;
    gap: .5rem;
    min-width: 0;
}

.nb-modal-head-ico {
    width: 34px;
    height: 34px;
    background: rgba(255,255,255,.22);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .95rem;
    color: #fff;
    flex-shrink: 0;
}

.nb-modal-head-sub {
    font-size: .65rem;
    font-weight: 500;
    opacity: .8;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: #fff;
}

.nb-modal-head-title {
    font-size: 1rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: .01em;
}

.nb-modal-close {
    background: rgba(255,255,255,.18);
    border: none;
    color: #fff;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .15s;
    flex-shrink: 0;
    -webkit-tap-highlight-color: transparent;
}

.nb-modal-close:hover {
    background: rgba(255,255,255,.3);
}

.nb-modal-body {
    background: #fff;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    flex: 1 1 auto;
    min-height: 0;
}

.nb-modal-row {
    display: flex;
    align-items: center;
    padding: .6rem 1rem;
    border-bottom: 1px solid #f1f3f5;
    gap: .7rem;
}

.nb-modal-row-last {
    border-bottom: none;
}

.nb-modal-row-ico {
    width: 30px;
    height: 30px;
    border-radius: 7px;
    background: #f1f3f5;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .75rem;
    color: #6c757d;
    flex-shrink: 0;
}

.nb-modal-row-body {
    flex: 1;
    min-width: 0;
}

.nb-modal-row-lbl {
    font-size: .65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #adb5bd;
    margin-bottom: .12rem;
}

.nb-modal-row-val {
    font-size: .88rem;
    font-weight: 700;
    color: #212529;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.nb-modal-notice {
    font-size: .78rem !important;
    white-space: normal !important;
    line-height: 1.45;
    font-weight: 500 !important;
}

.nb-modal-status {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    color: #fff;
    font-size: .68rem;
    font-weight: 800;
    letter-spacing: .07em;
    text-transform: uppercase;
    padding: .2rem .6rem;
    border-radius: 5px;
}

.nb-sdot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: rgba(255,255,255,.7);
}

.nb-modal-days-wrap {
    display: flex;
    align-items: baseline;
    gap: .3rem;
}

.nb-modal-days-num {
    font-size: 1.5rem;
    font-weight: 800;
    line-height: 1;
}

.nb-modal-days-label {
    font-size: .68rem;
    font-weight: 600;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: .05em;
}

.nb-modal-foot {
    background: #f8f9fa;
    border-top: 1px solid #e9ecef;
    padding: .65rem 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    flex-shrink: 0;
}

.nb-wa-btn-sm {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .4rem;
    background: #25D366;
    color: #fff;
    font-size: .78rem;
    font-weight: 700;
    padding: .4rem .85rem;
    border-radius: 7px;
    border: none;
    text-decoration: none;
    cursor: pointer;
    transition: background .15s, transform .15s;
    min-height: 38px;
    -webkit-tap-highlight-color: transparent;
}

.nb-wa-btn-sm:hover {
    background: #1ebe5d;
    color: #fff;
    text-decoration: none;
}

.nb-wa-btn-sm:active {
    transform: scale(.96);
}

.nb-modal-close-btn {
    background: #e9ecef;
    color: #495057;
    font-size: .78rem;
    font-weight: 600;
    padding: .4rem .85rem;
    border-radius: 7px;
    border: none;
    cursor: pointer;
    transition: background .15s, transform .15s;
    min-height: 38px;
    -webkit-tap-highlight-color: transparent;
}

.nb-modal-close-btn:hover {
    background: #dee2e6;
}

.nb-modal-close-btn:active {
    transform: scale(.96);
}

/* ══════════════════════════════════════════════════════════
   RESPONSIVE BREAKPOINTS
   ══════════════════════════════════════════════════════════ */

/* ── Tablets & small laptops: 768px – 991px ─────────────── */
@media (max-width: 991.98px) {
    .nb-sidebar {
        transform: translateX(-100%);
        width: 260px;
        min-width: 260px;
        z-index: 1041;
        box-shadow: 4px 0 24px rgba(0,0,0,.15);
    }

    .nb-sidebar.nb-open {
        transform: translateX(0);
    }

    .nb-mobile-bar {
        display: flex;
    }
}

/* ── Large phones / small tablets portrait: 480px – 767px ─ */
@media (max-width: 767.98px) {
    .nb-sidebar {
        width: 250px;
        min-width: 250px;
    }

    .nb-block-icon {
        font-size: 3rem;
    }

    .nb-block-title {
        font-size: 1.3rem;
    }

    .nb-block-msg {
        font-size: .85rem;
    }

    .nb-block-box {
        padding: 1.5rem 1.25rem;
        border-radius: 14px;
    }

    .nb-block-details {
        padding: .85rem 1rem;
    }

    .nb-block-details p {
        font-size: .82rem;
    }

    .nb-wa-btn {
        font-size: .85rem;
        padding: .65rem 1.5rem;
    }

    .nb-modal {
        border-radius: 12px;
    }

    .nb-modal-head {
        padding: .8rem .9rem;
    }

    .nb-modal-row {
        padding: .55rem .9rem;
    }

    .nb-modal-foot {
        padding: .6rem .9rem;
    }
}

/* ── Standard phones: 360px – 479px ─────────────────────── */
@media (max-width: 479.98px) {
    .nb-sidebar {
        width: 240px;
        min-width: 240px;
    }

    .nb-sidebar-head {
        padding: .85rem .85rem .7rem;
    }

    .nb-logo {
        height: 30px;
        max-width: 110px;
    }

    .nb-link {
        padding: .45rem .85rem;
        font-size: .82rem;
        gap: .5rem;
    }

    .nb-link i {
        width: 15px;
        font-size: .78rem;
    }

    .nb-group-toggle {
        padding: .45rem .85rem;
        font-size: .82rem;
        gap: .5rem;
    }

    .nb-gi {
        width: 15px;
        font-size: .78rem;
    }

    .nb-submenu .nb-link {
        padding: .38rem .75rem .38rem .8rem;
        font-size: .78rem;
    }

    .nb-footer {
        padding: .55rem .65rem;
    }

    .nb-greet-card {
        padding: .4rem .5rem;
        gap: .4rem;
    }

    .nb-greet-img {
        width: 26px;
        height: 26px;
    }

    .nb-greet-text {
        font-size: .65rem;
    }

    .nb-greet-text small {
        font-size: .58rem;
    }

    .nb-lic-badge {
        padding: .38rem .55rem;
        font-size: .7rem;
    }

    .nb-user-card {
        padding: .5rem .55rem;
        gap: .45rem;
    }

    .nb-avatar {
        width: 28px;
        height: 28px;
        font-size: .78rem;
    }

    .nb-user-name {
        font-size: .76rem;
    }

    .nb-user-branch {
        font-size: .65rem;
    }

    .nb-logout {
        padding: .4rem .65rem;
        font-size: .78rem;
    }

    /* Mobile topbar */
    .nb-mobile-bar {
        height: 50px;
        padding: 0 .7rem;
        padding-left: calc(.7rem + env(safe-area-inset-left, 0));
        padding-right: calc(.7rem + env(safe-area-inset-right, 0));
        gap: .5rem;
    }

    .nb-hamburger {
        width: 36px;
        height: 36px;
        padding: .3rem;
    }

    .nb-hamburger svg {
        width: 16px;
        height: 16px;
    }

    .nb-mobile-bar .nb-logo {
        height: 28px;
        max-width: 100px;
    }

    .nb-mini-badge {
        padding: .2rem .45rem;
        font-size: .65rem;
        gap: .25rem;
        min-height: 24px;
    }

    .nb-mini-badge .nb-dot {
        width: 6px;
        height: 6px;
    }

    /* Block overlay */
    .nb-block-overlay {
        padding: .75rem;
    }

    .nb-block-box {
        padding: 1.25rem 1rem;
        border-radius: 12px;
    }

    .nb-block-icon {
        font-size: 2.5rem;
        margin-bottom: .75rem;
    }

    .nb-block-title {
        font-size: 1.15rem;
        margin-bottom: .5rem;
    }

    .nb-block-msg {
        font-size: .8rem;
        margin-bottom: 1rem;
    }

    .nb-block-details {
        padding: .75rem .85rem;
        margin-bottom: 1rem;
    }

    .nb-block-details p {
        font-size: .78rem;
        margin: .3rem 0;
    }

    .nb-wa-btn {
        font-size: .82rem;
        padding: .6rem 1.25rem;
        max-width: 100%;
    }

    /* Modal */
    .nb-modal-wrap {
        padding: .65rem;
    }

    .nb-modal {
        border-radius: 10px;
        max-height: calc(100dvh - 1.3rem);
    }

    .nb-modal-head {
        padding: .7rem .8rem;
    }

    .nb-modal-head-ico {
        width: 30px;
        height: 30px;
        font-size: .85rem;
    }

    .nb-modal-head-sub {
        font-size: .6rem;
    }

    .nb-modal-head-title {
        font-size: .9rem;
    }

    .nb-modal-close {
        width: 28px;
        height: 28px;
        font-size: 1rem;
    }

    .nb-modal-row {
        padding: .5rem .8rem;
        gap: .55rem;
    }

    .nb-modal-row-ico {
        width: 26px;
        height: 26px;
        font-size: .7rem;
    }

    .nb-modal-row-lbl {
        font-size: .6rem;
    }

    .nb-modal-row-val {
        font-size: .82rem;
    }

    .nb-modal-notice {
        font-size: .73rem !important;
    }

    .nb-modal-days-num {
        font-size: 1.3rem;
    }

    .nb-modal-days-label {
        font-size: .62rem;
    }

    .nb-modal-foot {
        padding: .55rem .8rem;
        gap: .4rem;
    }

    .nb-wa-btn-sm {
        font-size: .73rem;
        padding: .35rem .7rem;
        min-height: 36px;
    }

    .nb-modal-close-btn {
        font-size: .73rem;
        padding: .35rem .7rem;
        min-height: 36px;
    }
}

/* ── Very small phones: < 360px ─────────────────────────── */
@media (max-width: 359.98px) {
    .nb-sidebar {
        width: 200px;
        min-width: 200px;
    }

    .nb-sidebar-head {
        padding: .75rem .7rem .6rem;
    }

    .nb-logo {
        height: 26px;
        max-width: 95px;
    }

    .nb-link {
        padding: .4rem .7rem;
        font-size: .8rem;
        gap: .45rem;
        min-height: 36px;
    }

    .nb-link i {
        width: 14px;
        font-size: .75rem;
    }

    .nb-group-toggle {
        padding: .4rem .7rem;
        font-size: .8rem;
        gap: .45rem;
        min-height: 36px;
    }

    .nb-gi {
        width: 14px;
        font-size: .75rem;
    }

    .nb-submenu {
        margin-left: .7rem;
    }

    .nb-submenu .nb-link {
        padding: .35rem .65rem .35rem .7rem;
        font-size: .76rem;
        min-height: 34px;
    }

    .nb-footer {
        padding: .5rem .55rem;
    }

    .nb-greet-card {
        padding: .35rem .45rem;
        gap: .35rem;
        border-radius: 8px;
    }

    .nb-greet-img {
        width: 24px;
        height: 24px;
    }

    .nb-greet-text {
        font-size: .62rem;
    }

    .nb-greet-text small {
        font-size: .55rem;
    }

    .nb-lic-badge {
        padding: .35rem .5rem;
        font-size: .68rem;
        border-radius: 6px;
        min-height: 34px;
    }

    .nb-lic-bell {
        display: none; /* hide bell on very small screens to save space */
    }

    .nb-user-card {
        padding: .45rem .5rem;
        gap: .4rem;
        border-radius: 8px;
    }

    .nb-avatar {
        width: 26px;
        height: 26px;
        font-size: .72rem;
    }

    .nb-user-name {
        font-size: .72rem;
    }

    .nb-user-branch {
        font-size: .62rem;
    }

    .nb-logout {
        padding: .38rem .55rem;
        font-size: .76rem;
        min-height: 34px;
        border-radius: 6px;
    }

    /* Mobile topbar */
    .nb-mobile-bar {
        height: 46px;
        padding: 0 .6rem;
        padding-left: calc(.6rem + env(safe-area-inset-left, 0));
        padding-right: calc(.6rem + env(safe-area-inset-right, 0));
        gap: .4rem;
    }

    .nb-hamburger {
        width: 34px;
        height: 34px;
        padding: .25rem;
    }

    .nb-hamburger svg {
        width: 15px;
        height: 15px;
    }

    .nb-mobile-bar .nb-logo {
        height: 24px;
        max-width: 85px;
    }

    .nb-mini-badge {
        padding: .18rem .4rem;
        font-size: .6rem;
        gap: .2rem;
        min-height: 22px;
        border-radius: 14px;
    }

    .nb-mini-badge .nb-dot {
        width: 5px;
        height: 5px;
    }

    /* Block overlay */
    .nb-block-overlay {
        padding: .5rem;
    }

    .nb-block-box {
        padding: 1rem .75rem;
        border-radius: 10px;
    }

    .nb-block-icon {
        font-size: 2rem;
        margin-bottom: .6rem;
    }

    .nb-block-title {
        font-size: 1.05rem;
        margin-bottom: .4rem;
    }

    .nb-block-msg {
        font-size: .76rem;
        margin-bottom: .85rem;
    }

    .nb-block-details {
        padding: .65rem .75rem;
        margin-bottom: .85rem;
        border-radius: 8px;
    }

    .nb-block-details p {
        font-size: .75rem;
        margin: .25rem 0;
    }

    .nb-wa-btn {
        font-size: .78rem;
        padding: .55rem 1rem;
        border-radius: 8px;
    }

    .nb-block-logout {
        font-size: .76rem;
    }

    /* Modal */
    .nb-modal-wrap {
        padding: .4rem;
    }

    .nb-modal {
        border-radius: 8px;
        max-height: calc(100dvh - .8rem);
    }

    .nb-modal-head {
        padding: .6rem .7rem;
    }

    .nb-modal-head-ico {
        width: 28px;
        height: 28px;
        font-size: .8rem;
        border-radius: 6px;
    }

    .nb-modal-head-sub {
        font-size: .55rem;
    }

    .nb-modal-head-title {
        font-size: .85rem;
    }

    .nb-modal-close {
        width: 26px;
        height: 26px;
        font-size: .9rem;
    }

    .nb-modal-row {
        padding: .45rem .7rem;
        gap: .5rem;
    }

    .nb-modal-row-ico {
        width: 24px;
        height: 24px;
        font-size: .65rem;
        border-radius: 6px;
    }

    .nb-modal-row-lbl {
        font-size: .55rem;
    }

    .nb-modal-row-val {
        font-size: .78rem;
    }

    .nb-modal-notice {
        font-size: .7rem !important;
    }

    .nb-modal-status {
        font-size: .6rem;
        padding: .15rem .5rem;
    }

    .nb-modal-days-num {
        font-size: 1.2rem;
    }

    .nb-modal-days-label {
        font-size: .58rem;
    }

    .nb-modal-foot {
        padding: .5rem .7rem;
        gap: .35rem;
    }

    .nb-wa-btn-sm {
        font-size: .7rem;
        padding: .3rem .6rem;
        min-height: 34px;
        border-radius: 6px;
    }

    .nb-modal-close-btn {
        font-size: .7rem;
        padding: .3rem .6rem;
        min-height: 34px;
        border-radius: 6px;
    }
}

/* ── Desktop: ensure sidebar always visible ──────────────── */
@media (min-width: 992px) {
    .nb-mobile-bar {
        display: none !important;
    }

    .nb-sidebar {
        transform: none !important;
    }

    .nb-overlay {
        display: none !important;
    }
}

/* ── Landscape phones (short height) ────────────────────── */
@media (max-height: 500px) and (orientation: landscape) {
    .nb-sidebar {
        width: 200px;
        min-width: 200px;
    }

    .nb-sidebar-head {
        padding: .6rem .85rem .5rem;
    }

    .nb-logo {
        height: 26px;
        max-width: 100px;
    }

    .nb-link {
        padding: .35rem .85rem;
        font-size: .8rem;
        min-height: 34px;
    }

    .nb-group-toggle {
        padding: .35rem .85rem;
        font-size: .8rem;
        min-height: 34px;
    }

    .nb-submenu .nb-link {
        padding: .3rem .75rem .3rem .8rem;
        font-size: .76rem;
        min-height: 30px;
    }

    .nb-footer {
        padding: .45rem .65rem;
    }

    .nb-greet-card {
        padding: .3rem .45rem;
        margin-bottom: .35rem;
    }

    .nb-greet-img {
        width: 22px;
        height: 22px;
    }

    .nb-greet-text {
        font-size: .6rem;
    }

    .nb-greet-text small {
        font-size: .53rem;
    }

    .nb-lic-badge {
        padding: .3rem .5rem;
        font-size: .68rem;
        margin-bottom: .35rem;
        min-height: 30px;
    }

    .nb-user-card {
        padding: .4rem .55rem;
        margin-bottom: .35rem;
    }

    .nb-avatar {
        width: 24px;
        height: 24px;
        font-size: .7rem;
    }

    .nb-user-name {
        font-size: .72rem;
    }

    .nb-user-branch {
        font-size: .62rem;
    }

    .nb-logout {
        padding: .3rem .55rem;
        font-size: .76rem;
        min-height: 30px;
    }

    /* Block overlay in landscape */
    .nb-block-overlay {
        padding: .5rem 1rem;
    }

    .nb-block-box {
        padding: 1rem 1.25rem;
        max-width: 380px;
    }

    .nb-block-icon {
        font-size: 2.5rem;
        margin-bottom: .5rem;
    }

    .nb-block-title {
        font-size: 1.15rem;
        margin-bottom: .35rem;
    }

    .nb-block-msg {
        font-size: .82rem;
        margin-bottom: .75rem;
    }

    .nb-block-details {
        padding: .6rem .85rem;
        margin-bottom: .75rem;
        display: flex;
        flex-wrap: wrap;
        gap: .25rem .85rem;
    }

    .nb-block-details p {
        font-size: .78rem;
        margin: 0;
        flex: 1 1 45%;
        min-width: 120px;
    }

    .nb-wa-btn {
        padding: .5rem 1.25rem;
        font-size: .82rem;
        max-width: 200px;
    }

    /* Modal in landscape */
    .nb-modal-wrap {
        padding: .5rem;
    }

    .nb-modal {
        width: 320px;
        max-height: calc(100dvh - 1rem);
    }

    .nb-modal-head {
        padding: .55rem .8rem;
    }

    .nb-modal-row {
        padding: .4rem .8rem;
    }

    .nb-modal-foot {
        padding: .45rem .8rem;
    }
}

/* ── Very short landscape (e.g. Galaxy Fold outer) ──────── */
@media (max-height: 380px) and (orientation: landscape) {
    .nb-sidebar {
        width: 180px;
        min-width: 180px;
    }

    .nb-sidebar-head {
        padding: .5rem .65rem .4rem;
    }

    .nb-logo {
        height: 22px;
        max-width: 85px;
    }

    .nb-link {
        padding: .3rem .65rem;
        font-size: .76rem;
        gap: .4rem;
        min-height: 30px;
    }

    .nb-link i {
        width: 13px;
        font-size: .7rem;
    }

    .nb-group-toggle {
        padding: .3rem .65rem;
        font-size: .76rem;
        gap: .4rem;
        min-height: 30px;
    }

    .nb-gi {
        width: 13px;
        font-size: .7rem;
    }

    .nb-submenu {
        margin-left: .5rem;
    }

    .nb-submenu .nb-link {
        padding: .25rem .6rem .25rem .65rem;
        font-size: .72rem;
        min-height: 28px;
    }

    .nb-footer {
        padding: .35rem .5rem;
    }

    .nb-greet-card {
        display: none; /* hide greeting in very short landscape */
    }

    .nb-lic-badge {
        padding: .25rem .45rem;
        font-size: .65rem;
        margin-bottom: .3rem;
        min-height: 26px;
    }

    .nb-lic-bell {
        display: none;
    }

    .nb-user-card {
        padding: .35rem .45rem;
        margin-bottom: .3rem;
        gap: .35rem;
    }

    .nb-avatar {
        width: 22px;
        height: 22px;
        font-size: .65rem;
    }

    .nb-user-name {
        font-size: .68rem;
    }

    .nb-user-branch {
        font-size: .58rem;
    }

    .nb-logout {
        padding: .25rem .45rem;
        font-size: .72rem;
        min-height: 28px;
        border-radius: 6px;
    }

    .nb-block-icon {
        font-size: 2rem;
        margin-bottom: .4rem;
    }

    .nb-block-title {
        font-size: 1rem;
    }

    .nb-block-msg {
        font-size: .76rem;
        margin-bottom: .5rem;
    }

    .nb-block-details {
        padding: .5rem .7rem;
        margin-bottom: .5rem;
    }

    .nb-block-details p {
        font-size: .72rem;
    }
}

/* ── High DPI / Retina sharpening ───────────────────────── */
@media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
    .nb-sidebar {
        border-right-width: .5px;
    }

    .nb-submenu {
        border-left-width: 1.5px;
    }

    .nb-lic-badge {
        border-width: 1px;
    }

    .nb-mini-badge {
        border-width: 1px;
    }
}

/* ── Prefers reduced motion ─────────────────────────────── */
@media (prefers-reduced-motion: reduce) {
    .nb-sidebar,
    .nb-link,
    .nb-group-toggle,
    .nb-lic-badge,
    .nb-logout,
    .nb-wa-btn,
    .nb-wa-btn-sm,
    .nb-modal-close-btn,
    .nb-modal,
    .nb-chevron {
        transition: none !important;
    }

    .nb-dot {
        animation: none !important;
    }

    .nb-badge-expired {
        animation: none !important;
    }

    .nb-block-icon {
        animation: none !important;
    }
}

/* ── Dark mode preference hint (optional future use) ────── */
@media (prefers-color-scheme: dark) {
    /* Placeholder — can be expanded for dark theme support */
}
</style>

<!-- ========== NAVBAR JS ========== -->
<script>
(function () {
    'use strict';

    // ── Elements ───────────────────────────────────────────
    var sidebar  = document.getElementById('nbSidebar');
    var overlay  = document.getElementById('nbOverlay');
    var toggle   = document.getElementById('nbToggle');
    var modal    = document.getElementById('nbModal');
    var licBadge = document.getElementById('nbLicBadge');
    var topBadge = document.getElementById('nbTopBadge');
    var closeX   = document.getElementById('nbModalClose');
    var closeFoot = document.getElementById('nbModalCloseFooter');

    // ── Mobile sidebar ────────────────────────────────────
    function openSidebar() {
        sidebar.classList.add('nb-open');
        overlay.classList.add('nb-show');
        document.body.style.overflow = 'hidden';
        // Prevent background scroll on iOS
        document.body.style.position = 'fixed';
        document.body.style.width = '100%';
    }

    function closeSidebar() {
        sidebar.classList.remove('nb-open');
        overlay.classList.remove('nb-show');
        document.body.style.overflow = '';
        document.body.style.position = '';
        document.body.style.width = '';
    }

    if (toggle) {
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (sidebar.classList.contains('nb-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function (e) {
            e.preventDefault();
            closeSidebar();
        });
    }

    // ── Collapsible submenus ──────────────────────────────
    var groupToggles = document.querySelectorAll('.nb-group-toggle');
    for (var i = 0; i < groupToggles.length; i++) {
        (function (t) {
            var menu = document.getElementById(t.getAttribute('data-target'));
            if (menu && menu.classList.contains('nb-open')) {
                t.classList.add('nb-open');
            }

            t.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (!menu) return;

                var isOpen = menu.classList.contains('nb-open');

                // Close all
                var allSubs = document.querySelectorAll('.nb-submenu');
                var allToggles = document.querySelectorAll('.nb-group-toggle');
                for (var j = 0; j < allSubs.length; j++) {
                    allSubs[j].classList.remove('nb-open');
                }
                for (var k = 0; k < allToggles.length; k++) {
                    allToggles[k].classList.remove('nb-open');
                }

                // Open clicked if it was closed
                if (!isOpen) {
                    menu.classList.add('nb-open');
                    t.classList.add('nb-open');
                }
            });
        })(groupToggles[i]);
    }

    // ── License modal ─────────────────────────────────────
    function openModal() {
        modal.classList.add('nb-show');
        document.body.style.overflow = 'hidden';
        document.body.style.position = 'fixed';
        document.body.style.width = '100%';
    }

    function closeModal() {
        modal.classList.remove('nb-show');
        // Only restore scroll if sidebar isn't also open
        if (!sidebar.classList.contains('nb-open')) {
            document.body.style.overflow = '';
            document.body.style.position = '';
            document.body.style.width = '';
        }
    }

    if (licBadge) {
        licBadge.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openModal();
        });
    }

    if (topBadge) {
        topBadge.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openModal();
        });
    }

    if (closeX) {
        closeX.addEventListener('click', function (e) {
            e.preventDefault();
            closeModal();
        });
    }

    if (closeFoot) {
        closeFoot.addEventListener('click', function (e) {
            e.preventDefault();
            closeModal();
        });
    }

    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeModal();
            }
        });
    }

    // Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (modal && modal.classList.contains('nb-show')) {
                closeModal();
            } else if (sidebar && sidebar.classList.contains('nb-open')) {
                closeSidebar();
            }
        }
    });

    // ── Handle window resize: close mobile sidebar if going desktop ──
    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (window.innerWidth >= 992) {
                if (sidebar.classList.contains('nb-open')) {
                    sidebar.classList.remove('nb-open');
                    overlay.classList.remove('nb-show');
                }
                document.body.style.overflow = '';
                document.body.style.position = '';
                document.body.style.width = '';
            }
        }, 150);
    });

    // ── Handle orientation change: close sidebar ──────────
    if (window.screen.orientation) {
        window.screen.orientation.addEventListener('change', function () {
            setTimeout(function () {
                if (sidebar.classList.contains('nb-open')) {
                    closeSidebar();
                }
                if (modal && modal.classList.contains('nb-show')) {
                    closeModal();
                }
            }, 200);
        });
    } else {
        // Fallback for browsers without orientation API
        window.addEventListener('orientationchange', function () {
            setTimeout(function () {
                if (sidebar.classList.contains('nb-open')) {
                    closeSidebar();
                }
                if (modal && modal.classList.contains('nb-show')) {
                    closeModal();
                }
            }, 200);
        });
    }

    // ── Prevent swipe-to-back interfering on iOS ──────────
    document.addEventListener('touchmove', function (e) {
        if (sidebar.classList.contains('nb-open') || (modal && modal.classList.contains('nb-show'))) {
            // Allow scrolling inside sidebar nav and modal body
            var target = e.target;
            while (target && target !== document.body) {
                if (target === document.getElementById('nbSidebar') ||
                    target.classList.contains('nb-nav') ||
                    target.classList.contains('nb-modal-body')) {
                    return; // allow scroll
                }
                target = target.parentNode;
            }
            // e.preventDefault(); // Uncomment if needed for strict lock
        }
    }, { passive: true });

})();

// ── Occasion Greeting ─────────────────────────────────────
(function () {
    var today = new Date();
    var month = today.getMonth() + 1;
    var date  = today.getDate();

    var occasions = [
        { m:4,  d:14, range:1, text: "শুভ নববর্ষ ১৪৩৩!<br><small>নতুন বছরের শুভেচ্ছা।</small>", img: "https://cdn.jsdelivr.net/gh/twitter/twemoji@14/assets/72x72/1f338.png" },
        { m:1,  d:1,  range:2, text: "Happy New Year!<br><small>Wishing you a great year ahead</small>", img: "https://cdn.jsdelivr.net/gh/twitter/twemoji@14/assets/72x72/1f386.png" },
        { m:3,  d:31, range:3, text: "ঈদ মুবারক!<br><small>Eid ul-Fitr Greetings</small>", img: "https://cdn.jsdelivr.net/gh/twitter/twemoji@14/assets/72x72/1f319.png" },
        { m:6,  d:7,  range:3, text: "ঈদ মুবারক!<br><small>Eid ul-Adha Greetings</small>", img: "https://cdn.jsdelivr.net/gh/twitter/twemoji@14/assets/72x72/1f411.png" },
        { m:12, d:16, range:1, text: "বিজয় দিবসের শুভেচ্ছা!<br><small>Happy Victory Day</small>", img: "https://cdn.jsdelivr.net/gh/twitter/twemoji@14/assets/72x72/1f1e7-1f1e9.png" },
        { m:3,  d:26, range:1, text: "স্বাধীনতা দিবসের শুভেচ্ছা!<br><small>Happy Independence Day</small>", img: "https://cdn.jsdelivr.net/gh/twitter/twemoji@14/assets/72x72/1f1e7-1f1e9.png" },
        { m:2,  d:21, range:1, text: "শহীদ দিবস<br><small>Mother Language Day</small>", img: "https://cdn.jsdelivr.net/gh/twitter/twemoji@14/assets/72x72/1f4d6.png" },
        { m:12, d:25, range:1, text: "Merry Christmas!<br><small>Season's Greetings</small>", img: "https://cdn.jsdelivr.net/gh/twitter/twemoji@14/assets/72x72/1f384.png" },
    ];

    var hour = today.getHours();
    var fallbacks = [
        { text: "সুপ্রভাত!<br><small>Good morning</small>",   img: "https://cdn.jsdelivr.net/gh/twitter/twemoji@14/assets/72x72/1f31e.png" },
        { text: "শুভ বিকেল!<br><small>Good afternoon</small>",  img: "https://cdn.jsdelivr.net/gh/twitter/twemoji@14/assets/72x72/1f44d.png" },
        { text: "শুভ সন্ধ্যা!<br><small>Good evening</small>",  img: "https://cdn.jsdelivr.net/gh/twitter/twemoji@14/assets/72x72/1f31f.png" },
    ];

    var card = null;
    for (var i = 0; i < occasions.length; i++) {
        var o = occasions[i];
        var oDate = new Date(today.getFullYear(), o.m - 1, o.d);
        var diffDays = Math.round((today - oDate) / 86400000);
        if (diffDays >= 0 && diffDays <= o.range) {
            card = o;
            break;
        }
    }

    if (!card) {
        card = hour < 12 ? fallbacks[0] : hour < 18 ? fallbacks[1] : fallbacks[2];
    }

    var imgEl  = document.getElementById('nbGreetImg');
    var textEl = document.getElementById('nbGreetText');
    if (imgEl && textEl) {
        imgEl.src = card.img;
        imgEl.alt = "";
        textEl.innerHTML = card.text;
    }
})();
</script>