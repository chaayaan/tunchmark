<?php
require 'auth.php';
if (!in_array($_SESSION['role'], ['admin','employee'])) {
    header("Location: dashboard.php");
    exit;
}

include 'mydb.php';

$servicesListQuery = "SELECT id, name FROM services WHERE is_active = 1 ORDER BY name ASC";
$servicesListResult = mysqli_query($conn, $servicesListQuery);
$servicesList = [];
while ($row = mysqli_fetch_assoc($servicesListResult)) {
    $servicesList[] = $row;
}

$isDefaultFilter = false;
if (empty($_GET['order_id']) && empty($_GET['from_date']) && empty($_GET['to_date']) && empty($_GET['year']) && empty($_GET['service'])) {
    $_GET['from_date'] = date('Y-m-01');
    $_GET['to_date'] = date('Y-m-t');
    $isDefaultFilter = true;
}

$records_per_page = 50;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $records_per_page;

$where = [];
$params = [];
$types = "";

if (!empty($_GET['order_id'])) {
    $where[] = "o.order_id = ?";
    $params[] = intval($_GET['order_id']);
    $types .= "i";
}

if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $where[] = "DATE(o.created_at) BETWEEN ? AND ?";
    $params[] = $_GET['from_date'];
    $params[] = $_GET['to_date'];
    $types .= "ss";
} elseif (!empty($_GET['year'])) {
    $where[] = "YEAR(o.created_at) = ?";
    $params[] = intval($_GET['year']);
    $types .= "i";
}

$serviceFilter = !empty($_GET['service']) ? intval($_GET['service']) : 0;
if ($serviceFilter > 0) {
    $where[] = "bi.service_id = ?";
    $params[] = $serviceFilter;
    $types .= "i";
}

$whereClause = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

$count_sql = "SELECT COUNT(DISTINCT o.order_id) as total 
              FROM orders o 
              JOIN bill_items bi ON o.order_id = bi.order_id" . $whereClause;
$count_stmt = mysqli_prepare($conn, $count_sql);
if (!empty($params)) mysqli_stmt_bind_param($count_stmt, $types, ...$params);
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_records = mysqli_fetch_assoc($count_result)['total'];
mysqli_stmt_close($count_stmt);

$total_pages = ceil($total_records / $records_per_page);

$pagination_params = $params;
$pagination_types = $types;
$pagination_params[] = $records_per_page;
$pagination_params[] = $offset;
$pagination_types .= "ii";

$sql = "SELECT 
            o.order_id, o.customer_name, o.customer_phone, o.status, o.created_at,
            SUM(bi.total_price) AS total_amount,
            GROUP_CONCAT(
                CONCAT(COALESCE(i.name,'-'),'|',COALESCE(s.name,'-'),'|',bi.quantity,'|',bi.unit_price,'|',bi.total_price)
                SEPARATOR '|||'
            ) AS items_data
        FROM orders o
        JOIN bill_items bi ON o.order_id = bi.order_id
        LEFT JOIN items i ON bi.item_id = i.id
        LEFT JOIN services s ON bi.service_id = s.id"
        . $whereClause .
        " GROUP BY o.order_id ORDER BY o.order_id DESC LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($pagination_params)) mysqli_stmt_bind_param($stmt, $pagination_types, ...$pagination_params);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$orders = [];
while ($row = mysqli_fetch_assoc($res)) {
    $row['items'] = [];
    if (!empty($row['items_data'])) {
        $items_raw = explode('|||', $row['items_data']);
        foreach ($items_raw as $item_str) {
            $item_parts = explode('|', $item_str);
            if (count($item_parts) >= 5) {
                $row['items'][] = [
                    'item_name'    => $item_parts[0],
                    'service_name' => $item_parts[1],
                    'quantity'     => $item_parts[2],
                    'unit_price'   => $item_parts[3],
                    'total_price'  => $item_parts[4]
                ];
            }
        }
    }
    unset($row['items_data']);
    $orders[] = $row;
}
mysqli_stmt_close($stmt);

$summary_sql = "SELECT o.order_id, o.status, bi.service_id, bi.quantity, bi.total_price, s.name as service_name
                FROM orders o
                JOIN bill_items bi ON o.order_id = bi.order_id
                LEFT JOIN services s ON bi.service_id = s.id" . $whereClause;
$summary_stmt = mysqli_prepare($conn, $summary_sql);
if (!empty($params)) mysqli_stmt_bind_param($summary_stmt, $types, ...$params);
mysqli_stmt_execute($summary_stmt);
$summary_res = mysqli_stmt_get_result($summary_stmt);

$totalPaid = $totalPending = $totalCancelled = $grandTotal = 0;
$countPaid = $countPending = $countCancelled = $countTotal = 0;

$servicesSummary = [];
foreach ($servicesList as $service) {
    $servicesSummary[$service['name']] = ['qty' => 0, 'total' => 0];
}

$processedOrders = [];
while ($row = mysqli_fetch_assoc($summary_res)) {
    $orderId = $row['order_id'];
    if (!isset($processedOrders[$orderId])) {
        $processedOrders[$orderId] = ['status' => $row['status'], 'total' => 0];
        $countTotal++;
        if ($row['status'] === 'paid')      $countPaid++;
        elseif ($row['status'] === 'pending')   $countPending++;
        elseif ($row['status'] === 'cancelled') $countCancelled++;
    }
    $itemTotal = floatval($row['total_price'] ?? 0);
    $processedOrders[$orderId]['total'] += $itemTotal;
    if ($row['status'] === 'paid') {
        $serviceName = $row['service_name'] ?? '';
        $qty = floatval($row['quantity'] ?? 0);
        if (!empty($serviceName)) {
            $matched = false;
            foreach ($servicesSummary as $key => &$summary) {
                if (strcasecmp($key, $serviceName) === 0) {
                    $summary['qty']   += $qty;
                    $summary['total'] += $itemTotal;
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                if (!isset($servicesSummary['Others'])) $servicesSummary['Others'] = ['qty' => 0, 'total' => 0];
                $servicesSummary['Others']['qty']   += $qty;
                $servicesSummary['Others']['total'] += $itemTotal;
            }
        }
    }
}
mysqli_stmt_close($summary_stmt);

foreach ($processedOrders as $orderData) {
    $grandTotal += $orderData['total'];
    if ($orderData['status'] === 'paid')      $totalPaid      += $orderData['total'];
    elseif ($orderData['status'] === 'pending')   $totalPending   += $orderData['total'];
    elseif ($orderData['status'] === 'cancelled') $totalCancelled += $orderData['total'];
}

$dailyExpensesTotal = 0;
$expenses_where = []; $expenses_params = []; $expenses_types = "";
if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $expenses_where[] = "DATE(created_time) BETWEEN ? AND ?";
    $expenses_params[] = $_GET['from_date'];
    $expenses_params[] = $_GET['to_date'];
    $expenses_types .= "ss";
} elseif (!empty($_GET['year'])) {
    $expenses_where[] = "YEAR(created_time) = ?";
    $expenses_params[] = intval($_GET['year']);
    $expenses_types .= "i";
}
$expenses_sql = "SELECT SUM(amount) as total FROM daily_expenses";
if (!empty($expenses_where)) $expenses_sql .= " WHERE " . implode(" AND ", $expenses_where);
$expenses_stmt = mysqli_prepare($conn, $expenses_sql);
if (!empty($expenses_params)) mysqli_stmt_bind_param($expenses_stmt, $expenses_types, ...$expenses_params);
mysqli_stmt_execute($expenses_stmt);
$expenses_result = mysqli_stmt_get_result($expenses_stmt);
$dailyExpensesTotal = mysqli_fetch_assoc($expenses_result)['total'] ?? 0;
mysqli_stmt_close($expenses_stmt);

$netProfit = $totalPaid - $dailyExpensesTotal;

$serviceIcons = [
    'Hallmark'    => 'fa-stamp',
    'Purity Test' => 'fa-vial',
    'Welding'     => 'fa-fire',
    'Melting'     => 'fa-fire-flame-curved',
    'Polishing'   => 'fa-star',
    'Engraving'   => 'fa-pen',
    'Others'      => 'fa-ellipsis'
];

$serviceColors = [
    'Hallmark'    => 'i-blue',
    'Purity Test' => 'i-green',
    'Welding'     => 'i-red',
    'Melting'     => 'i-amber',
    'Polishing'   => 'i-violet',
    'Engraving'   => 'i-blue',
    'Others'      => 'i-gray'
];

function getPaginationUrl($page_num) {
    $params = $_GET;
    $params['page'] = $page_num;
    return '?' . http_build_query($params);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Billing Reports — Rajaiswari</title>
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
      --r:  10px;  --rs: 6px;
      --sh: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
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
      display: flex; flex-direction: column;
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
      background: var(--blue-bg);
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      color: var(--blue); font-size: 13px; flex-shrink: 0;
    }
    .tb-title { font-size: 1.0625rem; font-weight: 700; color: var(--t1); line-height: 1.2; }
    .tb-sub   { font-size: .8rem; color: var(--t4); }
    .tb-right { margin-left: auto; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

    .tb-btn {
      display: inline-flex; align-items: center; gap: 6px;
      height: 32px; padding: 0 14px;
      border-radius: var(--rs);
      font-family: 'DM Sans', sans-serif;
      font-size: .82rem; font-weight: 600;
      text-decoration: none; cursor: pointer; transition: all .15s;
      border: 1.5px solid transparent;
      white-space: nowrap;
    }
    .tb-btn-ghost { background: var(--surface-2); border-color: var(--border); color: var(--t2); }
    .tb-btn-ghost:hover { background: var(--border); color: var(--t1); }
    .tb-btn-green { background: var(--green-bg); border-color: var(--green-b); color: var(--green); }
    .tb-btn-green:hover { background: #d1fae5; color: var(--green); }

    .page-badge {
      display: inline-flex; align-items: center; gap: 5px;
      background: var(--surface-2); border: 1px solid var(--border);
      border-radius: var(--rs); padding: 4px 12px;
      font-family: 'DM Mono', monospace;
      font-size: .78rem; font-weight: 500; color: var(--t3);
    }

    /* ── Main ───────────────────────────────── */
    .main {
      flex: 1;
      padding: 20px 22px 60px;
      display: flex; flex-direction: column; gap: 14px;
    }

    /* ── Active filter chips ─────────────────── */
    .filter-chips {
      display: flex; align-items: center; flex-wrap: wrap; gap: 6px;
    }
    .filter-chips-lbl {
      font-size: .72rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .06em; color: var(--t4);
    }
    .chip {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 3px 10px;
      border-radius: 20px;
      font-size: .78rem; font-weight: 600;
      border: 1px solid;
    }
    .chip-blue   { background: var(--blue-bg);   border-color: var(--blue-b);   color: var(--blue); }
    .chip-violet { background: var(--violet-bg); border-color: var(--violet-b); color: var(--violet); }
    .chip a { color: inherit; text-decoration: none; opacity: .7; transition: opacity .12s; }
    .chip a:hover { opacity: 1; }

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
      width: 28px; height: 28px; border-radius: var(--rs);
      display: flex; align-items: center; justify-content: center;
      font-size: 12px; flex-shrink: 0;
    }
    .i-blue   { background: var(--blue-bg);   color: var(--blue);   }
    .i-green  { background: var(--green-bg);  color: var(--green);  }
    .i-amber  { background: var(--amber-bg);  color: var(--amber);  }
    .i-red    { background: var(--red-bg);    color: var(--red);    }
    .i-violet { background: var(--violet-bg); color: var(--violet); }
    .i-gray   { background: var(--surface-2); color: var(--t4);     }

    .sec-title { font-size: .9375rem; font-weight: 700; color: var(--t1); letter-spacing: -.01em; }
    .sec-meta  { margin-left: auto; font-size: .78rem; color: var(--t4); font-weight: 500; }

    /* ── Filter form ────────────────────────── */
    .filter-body {
      padding: 16px 18px;
      display: grid;
      grid-template-columns: repeat(5, 1fr) auto;
      gap: 12px;
      align-items: end;
    }

    .lbl {
      display: block;
      font-size: .76rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .06em;
      color: var(--t3); margin-bottom: 5px;
    }

    .fc, select.fc {
      width: 100%; height: 38px;
      padding: 0 11px;
      border: 1.5px solid var(--border);
      border-radius: var(--rs);
      font-family: 'DM Sans', sans-serif;
      font-size: .875rem; color: var(--t2);
      background: var(--surface);
      transition: border-color .15s, box-shadow .15s;
      outline: none;
      appearance: none; -webkit-appearance: none;
    }
    select.fc {
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%239ca3af' d='M1 1l5 5 5-5'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 9px center;
      padding-right: 28px;
    }
    .fc:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }

    .btn-search {
      display: inline-flex; align-items: center; justify-content: center; gap: 6px;
      height: 38px; padding: 0 20px;
      background: var(--blue); border: none; border-radius: var(--rs);
      font-family: 'DM Sans', sans-serif;
      font-size: .875rem; font-weight: 700; color: #fff;
      cursor: pointer; transition: background .15s; white-space: nowrap;
    }
    .btn-search:hover { background: #1d4ed8; }

    /* ── Stat cards grid ────────────────────── */
    .stat-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 14px;
    }

    .stat-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--r);
      box-shadow: var(--sh);
      overflow: hidden;
      position: relative;
    }

    .stat-card::before {
      content: '';
      position: absolute;
      left: 0; top: 0; bottom: 0;
      width: 4px;
    }
    .sc-green::before  { background: var(--green); }
    .sc-amber::before  { background: var(--amber); }
    .sc-red::before    { background: var(--red); }
    .sc-blue::before   { background: var(--blue); }

    .stat-inner { padding: 18px 18px 18px 22px; }

    .stat-row {
      display: flex; align-items: center; gap: 12px;
      margin-bottom: 14px;
    }

    .stat-badge {
      width: 38px; height: 38px; border-radius: 9px;
      display: flex; align-items: center; justify-content: center;
      font-size: 15px; flex-shrink: 0;
    }

    .stat-label {
      font-size: .72rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .07em;
      color: var(--t4); line-height: 1;
    }

    .stat-count {
      font-size: 2rem; font-weight: 800;
      line-height: 1.1; color: var(--t1);
      font-family: 'DM Mono', monospace;
      letter-spacing: -.02em;
      margin-bottom: 4px;
    }

    .stat-amount {
      font-size: 1.0625rem; font-weight: 700;
      font-family: 'DM Mono', monospace;
    }
    .sc-green .stat-amount  { color: var(--green); }
    .sc-amber .stat-amount  { color: var(--amber); }
    .sc-red   .stat-amount  { color: var(--red); }
    .sc-blue  .stat-amount  { color: var(--blue); }

    /* ── Two-col panels ─────────────────────── */
    .panels-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
    }

    /* ── Inner tables ───────────────────────── */
    .itbl { width: 100%; border-collapse: collapse; }

    .itbl thead th {
      padding: 9px 16px;
      text-align: left;
      font-size: .72rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .07em;
      color: var(--t4);
      background: var(--surface-2);
      border-bottom: 1px solid var(--border);
      white-space: nowrap;
    }

    .itbl tbody td {
      padding: 10px 16px;
      border-bottom: 1px solid var(--bsoft);
      font-size: .875rem;
      vertical-align: middle;
    }

    .itbl tbody tr:last-child td { border-bottom: none; }
    .itbl tbody tr:hover td { background: #fafbff; }

    .itbl tfoot td {
      padding: 10px 16px;
      background: var(--surface-2);
      border-top: 1px solid var(--border);
      font-weight: 700; font-size: .875rem;
    }

    /* ── Financial rows ─────────────────────── */
    .fin-row {
      display: flex; align-items: center;
      padding: 14px 18px;
      border-bottom: 1px solid var(--bsoft);
      gap: 12px;
    }
    .fin-row:last-child { border-bottom: none; }
    .fin-row.profit { background: var(--green-bg); border-top: 1px solid var(--green-b); }
    .fin-row.loss   { background: var(--red-bg);   border-top: 1px solid var(--red-b); }

    .fin-ico {
      width: 32px; height: 32px; border-radius: var(--rs);
      display: flex; align-items: center; justify-content: center;
      font-size: 13px; flex-shrink: 0;
    }

    .fin-label { font-size: .875rem; font-weight: 600; color: var(--t2); }
    .fin-sub   { font-size: .75rem; color: var(--t4); margin-top: 1px; }

    .fin-val {
      margin-left: auto;
      font-family: 'DM Mono', monospace;
      font-size: 1.05rem; font-weight: 800;
    }
    .fin-val.green  { color: var(--green); }
    .fin-val.red    { color: var(--red); }
    .fin-val.profit { color: var(--green); font-size: 1.2rem; }
    .fin-val.loss   { color: var(--red);   font-size: 1.2rem; }

    /* ── Service icon in table ──────────────── */
    .svc-dot {
      display: inline-flex; align-items: center; justify-content: center;
      width: 22px; height: 22px; border-radius: 5px;
      font-size: 9px; margin-right: 7px; vertical-align: middle;
      flex-shrink: 0;
    }

    /* ── Orders table ───────────────────────── */
    .orders-tbl { width: 100%; border-collapse: collapse; }

    .orders-tbl thead th {
      padding: 10px 14px;
      text-align: left;
      font-size: .72rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .07em;
      color: var(--t4);
      background: var(--surface-2);
      border-bottom: 1px solid var(--border);
      white-space: nowrap;
    }

    .orders-tbl tbody td {
      padding: 10px 14px;
      border-bottom: 1px solid var(--bsoft);
      vertical-align: middle;
      font-size: .875rem;
    }

    .orders-tbl tbody tr:last-child td { border-bottom: none; }
    .orders-tbl tbody tr:hover td { background: #fafbff; }

    .order-id {
      font-family: 'DM Mono', monospace;
      font-weight: 700; font-size: .875rem; color: var(--blue);
    }

    .customer-name { font-weight: 600; color: var(--t1); }
    .customer-phone { font-size: .78rem; color: var(--t4); font-family: 'DM Mono', monospace; }

    .items-list { list-style: none; padding: 0; margin: 0; }
    .items-list li {
      font-size: .78rem; color: var(--t3);
      padding: 2px 0;
      border-bottom: 1px dashed var(--bsoft);
      display: flex; align-items: baseline; gap: 4px; flex-wrap: wrap;
    }
    .items-list li:last-child { border-bottom: none; }
    .items-list .i-item { font-weight: 600; color: var(--t2); }
    .items-list .i-svc  { color: var(--violet); font-weight: 600; }
    .items-list .i-total{ font-family: 'DM Mono', monospace; font-weight: 700; color: var(--t1); }

    .order-total {
      font-family: 'DM Mono', monospace;
      font-weight: 800; font-size: .9375rem; color: var(--t1);
      text-align: right;
    }

    .order-date { font-size: .78rem; color: var(--t4); font-family: 'DM Mono', monospace; }

    /* ── Status pills ───────────────────────── */
    .pill {
      display: inline-flex; align-items: center; gap: 4px;
      padding: 3px 9px; border-radius: 20px;
      font-size: .72rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .04em;
      white-space: nowrap;
    }
    .pill::before { content: ''; width: 5px; height: 5px; border-radius: 50%; }
    .pill-paid     { background: var(--green-bg); color: var(--green); border: 1px solid var(--green-b); }
    .pill-paid::before   { background: var(--green); }
    .pill-pending  { background: var(--amber-bg); color: var(--amber); border: 1px solid var(--amber-b); }
    .pill-pending::before{ background: var(--amber); }
    .pill-cancelled{ background: var(--red-bg);   color: var(--red);   border: 1px solid var(--red-b); }
    .pill-cancelled::before{ background: var(--red); }
    .pill-unknown  { background: var(--surface-2); color: var(--t4); border: 1px solid var(--border); }
    .pill-unknown::before{ background: var(--t4); }

    /* ── Row action buttons ─────────────────── */
    .row-actions { display: flex; gap: 6px; }
    .btn-action {
      width: 30px; height: 30px;
      display: flex; align-items: center; justify-content: center;
      border-radius: var(--rs); font-size: 12px;
      cursor: pointer; transition: all .15s; text-decoration: none;
      border: 1.5px solid;
    }
    .btn-view  { background: var(--blue-bg);  border-color: var(--blue-b);  color: var(--blue); }
    .btn-view:hover  { background: var(--blue);  color: #fff; }
    .btn-print { background: var(--green-bg); border-color: var(--green-b); color: var(--green); }
    .btn-print:hover { background: var(--green); color: #fff; border-color: var(--green); }

    /* ── Pagination ─────────────────────────── */
    .pag-wrap {
      display: flex; align-items: center; justify-content: center;
      gap: 4px; padding: 14px 18px;
      border-top: 1px solid var(--border);
      background: var(--surface-2);
    }
    .pag-btn {
      display: inline-flex; align-items: center; justify-content: center;
      min-width: 34px; height: 34px; padding: 0 8px;
      border: 1.5px solid var(--border);
      border-radius: var(--rs);
      font-family: 'DM Sans', sans-serif;
      font-size: .82rem; font-weight: 600; color: var(--t2);
      background: var(--surface);
      text-decoration: none; transition: all .15s;
    }
    .pag-btn:hover  { background: var(--blue-bg); border-color: var(--blue-b); color: var(--blue); }
    .pag-btn.active { background: var(--blue); border-color: var(--blue); color: #fff; }
    .pag-btn.disabled { background: var(--surface-2); color: var(--t4); pointer-events: none; }

    /* ── Empty state ────────────────────────── */
    .empty-state { padding: 56px 24px; text-align: center; }
    .empty-ico   { font-size: 2.8rem; color: var(--border); margin-bottom: 12px; }
    .empty-title { font-size: .9375rem; font-weight: 700; color: var(--t3); margin-bottom: 6px; }
    .empty-sub   { font-size: .82rem; color: var(--t4); }

    /* ── Responsive ─────────────────────────── */
    @media (max-width: 1200px) {
      .stat-grid   { grid-template-columns: repeat(2, 1fr); }
      .panels-row  { grid-template-columns: 1fr; }
      .filter-body { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 991.98px) {
      .page-shell { margin-left: 0; }
      .top-bar    { top: 52px; }
      .main       { padding: 14px 14px 50px; }
      .stat-grid  { grid-template-columns: repeat(2, 1fr); }
      .filter-body { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 600px) {
      .stat-grid  { grid-template-columns: 1fr 1fr; }
      .filter-body { grid-template-columns: 1fr; }
    }

    @media print {
      .no-print { display: none !important; }
      .page-shell { margin-left: 0; }
      body { background: white; }
    }
  </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="page-shell">

  <!-- ── Top Bar ───────────────────────────── -->
  <header class="top-bar">
    <div class="tb-ico"><i class="fas fa-chart-line"></i></div>
    <div>
      <div class="tb-title">Billing Reports</div>
      <div class="tb-sub">Orders, revenue & service breakdown</div>
    </div>
    <div class="tb-right no-print">
      <div class="page-badge">
        <i class="fas fa-file-lines" style="font-size:.6rem;"></i>
        p.<?= $page ?>/<?= max(1,$total_pages) ?> &nbsp;·&nbsp; <?= $total_records ?> orders
      </div>
      <a href="export_report_csv.php?<?= http_build_query($_GET) ?>" class="tb-btn tb-btn-green">
        <i class="fas fa-file-csv" style="font-size:.7rem;"></i> Export CSV
      </a>
      <a href="reports.php" class="tb-btn tb-btn-ghost">
        <i class="fas fa-rotate-left" style="font-size:.65rem;"></i> Reset
      </a>
    </div>
  </header>

  <div class="main">

    <!-- ── Active filter chips ──────────────── -->
    <?php if (!empty($_GET['order_id']) || !empty($_GET['from_date']) || !empty($_GET['year']) || !empty($_GET['service'])): ?>
    <div class="filter-chips">
      <span class="filter-chips-lbl">Filters:</span>

      <?php if (!empty($_GET['order_id'])): ?>
        <span class="chip chip-blue">
          <i class="fas fa-hashtag" style="font-size:.6rem;"></i>
          Order #<?= htmlspecialchars($_GET['order_id']) ?>
          <a href="?<?= http_build_query(array_diff_key($_GET, ['order_id'=>''])) ?>">
            <i class="fas fa-xmark"></i>
          </a>
        </span>
      <?php endif; ?>

      <?php if (!empty($_GET['from_date']) && !empty($_GET['to_date'])): ?>
        <span class="chip <?= $isDefaultFilter ? 'chip-violet' : 'chip-blue' ?>">
          <?= $isDefaultFilter ? '<i class="fas fa-calendar-check" style="font-size:.65rem;"></i>' : '<i class="fas fa-calendar-range" style="font-size:.65rem;"></i>' ?>
          <?= htmlspecialchars($_GET['from_date']) ?> → <?= htmlspecialchars($_GET['to_date']) ?>
          <?= $isDefaultFilter ? ' (This Month)' : '' ?>
          <?php if (!$isDefaultFilter): ?>
          <a href="?<?= http_build_query(array_diff_key($_GET, ['from_date'=>'','to_date'=>''])) ?>">
            <i class="fas fa-xmark"></i>
          </a>
          <?php endif; ?>
        </span>
      <?php endif; ?>

      <?php if (!empty($_GET['year'])): ?>
        <span class="chip chip-blue">
          <i class="fas fa-calendar" style="font-size:.65rem;"></i>
          <?= htmlspecialchars($_GET['year']) ?>
          <a href="?<?= http_build_query(array_diff_key($_GET, ['year'=>''])) ?>">
            <i class="fas fa-xmark"></i>
          </a>
        </span>
      <?php endif; ?>

      <?php if (!empty($_GET['service'])):
        $selectedServiceName = '';
        foreach ($servicesList as $svc) {
          if ($svc['id'] == $_GET['service']) { $selectedServiceName = $svc['name']; break; }
        }
      ?>
        <span class="chip chip-blue">
          <i class="fas fa-wrench" style="font-size:.6rem;"></i>
          <?= htmlspecialchars($selectedServiceName) ?>
          <a href="?<?= http_build_query(array_diff_key($_GET, ['service'=>''])) ?>">
            <i class="fas fa-xmark"></i>
          </a>
        </span>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── Filter form ───────────────────────── -->
    <div class="sec no-print">
      <div class="sec-head">
        <span class="sec-ico i-blue"><i class="fas fa-filter"></i></span>
        <span class="sec-title">Search & Filter</span>
      </div>
      <form method="get">
        <div class="filter-body">

          <div>
            <label class="lbl">Order ID</label>
            <input type="number" name="order_id" class="fc" placeholder="e.g. 1042"
                   value="<?= htmlspecialchars($_GET['order_id'] ?? '') ?>">
          </div>

          <div>
            <label class="lbl">Service</label>
            <select name="service" class="fc">
              <option value="">All Services</option>
              <?php foreach ($servicesList as $service): ?>
                <option value="<?= $service['id'] ?>"
                        <?= (isset($_GET['service']) && $_GET['service'] == $service['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($service['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="lbl">From Date</label>
            <input type="date" name="from_date" class="fc"
                   value="<?= htmlspecialchars($_GET['from_date'] ?? '') ?>">
          </div>

          <div>
            <label class="lbl">To Date</label>
            <input type="date" name="to_date" class="fc"
                   value="<?= htmlspecialchars($_GET['to_date'] ?? '') ?>">
          </div>

          <div>
            <label class="lbl">Year</label>
            <input type="number" name="year" class="fc"
                   placeholder="<?= date('Y') ?>"
                   value="<?= htmlspecialchars($_GET['year'] ?? '') ?>"
                   min="2000" max="<?= date('Y') + 1 ?>">
          </div>

          <div>
            <button type="submit" class="btn-search" style="width:100%;">
              <i class="fas fa-magnifying-glass" style="font-size:.75rem;"></i> Search
            </button>
          </div>

        </div>
      </form>
    </div>

    <!-- ── Stat cards ────────────────────────── -->
    <div class="stat-grid">

      <div class="stat-card sc-green">
        <div class="stat-inner">
          <div class="stat-row">
            <div class="stat-badge i-green"><i class="fas fa-circle-check"></i></div>
            <div class="stat-label">Paid Orders</div>
          </div>
          <div class="stat-count"><?= $countPaid ?></div>
          <div class="stat-amount">৳<?= number_format($totalPaid, 0) ?></div>
        </div>
      </div>

      <div class="stat-card sc-amber">
        <div class="stat-inner">
          <div class="stat-row">
            <div class="stat-badge i-amber"><i class="fas fa-clock"></i></div>
            <div class="stat-label">Pending Orders</div>
          </div>
          <div class="stat-count"><?= $countPending ?></div>
          <div class="stat-amount">৳<?= number_format($totalPending, 0) ?></div>
        </div>
      </div>

      <div class="stat-card sc-red">
        <div class="stat-inner">
          <div class="stat-row">
            <div class="stat-badge i-red"><i class="fas fa-circle-xmark"></i></div>
            <div class="stat-label">Cancelled</div>
          </div>
          <div class="stat-count"><?= $countCancelled ?></div>
          <div class="stat-amount">৳<?= number_format($totalCancelled, 0) ?></div>
        </div>
      </div>

      <div class="stat-card sc-blue">
        <div class="stat-inner">
          <div class="stat-row">
            <div class="stat-badge i-blue"><i class="fas fa-receipt"></i></div>
            <div class="stat-label">All Orders</div>
          </div>
          <div class="stat-count"><?= $countTotal ?></div>
          <div class="stat-amount">৳<?= number_format($grandTotal, 0) ?></div>
        </div>
      </div>

    </div>

    <!-- ── Side-by-side panels ───────────────── -->
    <div class="panels-row">

      <!-- Service summary -->
      <div class="sec">
        <div class="sec-head">
          <span class="sec-ico i-violet"><i class="fas fa-chart-pie"></i></span>
          <span class="sec-title">Service Summary</span>
          <span class="sec-meta">Paid orders only</span>
        </div>
        <?php
          $totalQty = 0; $totalSvcAmount = 0;
          foreach ($servicesSummary as $sn => $d) {
            if ($d['qty'] == 0 && $d['total'] == 0) continue;
            $totalQty += $d['qty']; $totalSvcAmount += $d['total'];
          }
        ?>
        <?php if ($totalQty == 0): ?>
          <div class="empty-state">
            <div class="empty-ico"><i class="fas fa-chart-pie"></i></div>
            <div class="empty-title">No paid services</div>
            <div class="empty-sub">No paid order services match the current filter.</div>
          </div>
        <?php else: ?>
        <table class="itbl">
          <thead>
            <tr>
              <th>Service</th>
              <th style="text-align:center;">Qty</th>
              <th style="text-align:right;">Total</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($servicesSummary as $serviceName => $data):
              if ($data['qty'] == 0 && $data['total'] == 0) continue;
              $ico = $serviceIcons[$serviceName] ?? 'fa-cog';
              $cls = $serviceColors[$serviceName] ?? 'i-gray';
            ?>
            <tr>
              <td>
                <span class="svc-dot <?= $cls ?>">
                  <i class="fas <?= $ico ?>"></i>
                </span>
                <?= htmlspecialchars($serviceName) ?>
              </td>
              <td style="text-align:center;font-family:'DM Mono',monospace;font-weight:600;">
                <?= $data['qty'] ?>
              </td>
              <td style="text-align:right;font-family:'DM Mono',monospace;font-weight:700;color:var(--t1);">
                ৳<?= number_format($data['total'], 2) ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td>Total</td>
              <td style="text-align:center;font-family:'DM Mono',monospace;"><?= $totalQty ?></td>
              <td style="text-align:right;font-family:'DM Mono',monospace;color:var(--green);">৳<?= number_format($totalSvcAmount, 2) ?></td>
            </tr>
          </tfoot>
        </table>
        <?php endif; ?>
      </div>

      <!-- Financial summary -->
      <div class="sec">
        <div class="sec-head">
          <span class="sec-ico i-green"><i class="fas fa-calculator"></i></span>
          <span class="sec-title">Financial Summary</span>
        </div>

        <div class="fin-row">
          <div class="fin-ico i-green"><i class="fas fa-arrow-trend-up"></i></div>
          <div>
            <div class="fin-label">Total Revenue</div>
            <div class="fin-sub">From paid orders</div>
          </div>
          <div class="fin-val green">৳<?= number_format($totalPaid, 2) ?></div>
        </div>

        <div class="fin-row">
          <div class="fin-ico i-red"><i class="fas fa-arrow-trend-down"></i></div>
          <div>
            <div class="fin-label">Daily Expenses</div>
            <div class="fin-sub">Operational costs</div>
          </div>
          <div class="fin-val red">৳<?= number_format($dailyExpensesTotal, 2) ?></div>
        </div>

        <div class="fin-row <?= $netProfit >= 0 ? 'profit' : 'loss' ?>">
          <div class="fin-ico <?= $netProfit >= 0 ? 'i-green' : 'i-red' ?>">
            <i class="fas fa-<?= $netProfit >= 0 ? 'chart-line' : 'chart-line-down' ?>"></i>
          </div>
          <div>
            <div class="fin-label" style="font-weight:800;font-size:.9375rem;">Net Profit</div>
            <div class="fin-sub">Revenue − Expenses</div>
          </div>
          <div class="fin-val <?= $netProfit >= 0 ? 'profit' : 'loss' ?>">
            <?= $netProfit >= 0 ? '' : '−' ?>৳<?= number_format(abs($netProfit), 2) ?>
          </div>
        </div>

      </div>

    </div><!-- /panels -->

    <!-- ── Orders table ──────────────────────── -->
    <div class="sec">
      <div class="sec-head">
        <span class="sec-ico i-blue"><i class="fas fa-list-ul"></i></span>
        <span class="sec-title">Orders List</span>
        <span class="sec-meta no-print">
          Showing <?= count($orders) ?> of <?= $total_records ?>
        </span>
      </div>

      <?php if (empty($orders)): ?>
        <div class="empty-state">
          <div class="empty-ico"><i class="fas fa-inbox"></i></div>
          <div class="empty-title">No orders found</div>
          <div class="empty-sub">Try adjusting your filters to see results.</div>
        </div>
      <?php else: ?>

      <div style="overflow-x:auto;">
        <table class="orders-tbl">
          <thead>
            <tr>
              <th style="width:80px;">Order</th>
              <th style="width:160px;">Customer</th>
              <th>Items & Services</th>
              <th style="width:120px;text-align:right;">Total</th>
              <th style="width:100px;">Status</th>
              <th style="width:100px;">Date</th>
              <th style="width:80px;text-align:center;" class="no-print">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($orders as $order): ?>
            <tr>
              <td>
                <span class="order-id">#<?= htmlspecialchars($order['order_id']) ?></span>
              </td>
              <td>
                <div class="customer-name"><?= htmlspecialchars($order['customer_name']) ?></div>
                <div class="customer-phone"><?= htmlspecialchars($order['customer_phone']) ?></div>
              </td>
              <td>
                <ul class="items-list">
                  <?php if (!empty($order['items'])): ?>
                    <?php foreach ($order['items'] as $item): ?>
                    <li>
                      <span class="i-item"><?= htmlspecialchars($item['item_name']) ?></span>
                      <span style="color:var(--t4);">·</span>
                      <span class="i-svc"><?= htmlspecialchars($item['service_name']) ?></span>
                      <span style="color:var(--t4);">·</span>
                      <span><?= number_format((float)$item['quantity'], 0) ?> ×
                        ৳<?= number_format((float)$item['unit_price'], 2) ?> =
                      </span>
                      <span class="i-total">৳<?= number_format((float)$item['total_price'], 2) ?></span>
                    </li>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <li><em style="color:var(--t4);">No items</em></li>
                  <?php endif; ?>
                </ul>
              </td>
              <td class="order-total">৳<?= number_format($order['total_amount'], 2) ?></td>
              <td>
                <?php if ($order['status'] === 'paid'): ?>
                  <span class="pill pill-paid">Paid</span>
                <?php elseif ($order['status'] === 'pending'): ?>
                  <span class="pill pill-pending">Pending</span>
                <?php elseif ($order['status'] === 'cancelled'): ?>
                  <span class="pill pill-cancelled">Cancelled</span>
                <?php else: ?>
                  <span class="pill pill-unknown">Unknown</span>
                <?php endif; ?>
              </td>
              <td class="order-date"><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
              <td class="no-print">
                <div class="row-actions" style="justify-content:center;">
                  <a href="bill.php?id=<?= $order['order_id'] ?>" class="btn-action btn-view" title="View Bill">
                    <i class="fas fa-eye"></i>
                  </a>
                  <button onclick="printOrder(<?= $order['order_id'] ?>)" class="btn-action btn-print" title="Print Receipt">
                    <i class="fas fa-print"></i>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($total_pages > 1): ?>
      <div class="pag-wrap no-print">

        <?php if ($page > 1): ?>
          <a href="<?= getPaginationUrl(1) ?>" class="pag-btn" title="First">
            <i class="fas fa-angles-left" style="font-size:.65rem;"></i>
          </a>
          <a href="<?= getPaginationUrl($page - 1) ?>" class="pag-btn">
            <i class="fas fa-angle-left" style="font-size:.65rem;"></i>
          </a>
        <?php else: ?>
          <span class="pag-btn disabled"><i class="fas fa-angles-left" style="font-size:.65rem;"></i></span>
          <span class="pag-btn disabled"><i class="fas fa-angle-left" style="font-size:.65rem;"></i></span>
        <?php endif; ?>

        <?php
          $start = max(1, $page - 2);
          $end   = min($total_pages, $page + 2);
          for ($i = $start; $i <= $end; $i++):
        ?>
          <a href="<?= getPaginationUrl($i) ?>" class="pag-btn <?= $i === $page ? 'active' : '' ?>">
            <?= $i ?>
          </a>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
          <a href="<?= getPaginationUrl($page + 1) ?>" class="pag-btn">
            <i class="fas fa-angle-right" style="font-size:.65rem;"></i>
          </a>
          <a href="<?= getPaginationUrl($total_pages) ?>" class="pag-btn" title="Last">
            <i class="fas fa-angles-right" style="font-size:.65rem;"></i>
          </a>
        <?php else: ?>
          <span class="pag-btn disabled"><i class="fas fa-angle-right" style="font-size:.65rem;"></i></span>
          <span class="pag-btn disabled"><i class="fas fa-angles-right" style="font-size:.65rem;"></i></span>
        <?php endif; ?>

      </div>
      <?php endif; ?>

      <?php endif; ?>
    </div><!-- /orders sec -->

  </div><!-- /main -->
</div><!-- /page-shell -->

<script>
function printOrder(orderId) {
  const w = window.open('print_order.php?id=' + orderId, 'Print Order #' + orderId, 'width=400,height=600,scrollbars=yes,resizable=yes');
  if (w) w.focus();
  else alert('Please allow popups to print receipts');
}
</script>
</body>
</html>