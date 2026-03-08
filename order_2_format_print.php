<?php
require 'auth.php';
require 'mydb.php';

// Get username from session
$createdBy = $_SESSION['username'] ?? null;

// Fetch items and services from database
$items = [];
$services = [];

$itemsQuery = "SELECT id, name FROM items WHERE is_active = 1 ORDER BY name ASC";
$itemsResult = mysqli_query($conn, $itemsQuery);
if ($itemsResult) {
    while ($row = mysqli_fetch_assoc($itemsResult)) {
        $items[] = $row;
    }
}

$servicesQuery = "SELECT id, name, price FROM services WHERE is_active = 1 ORDER BY name ASC";
$servicesResult = mysqli_query($conn, $servicesQuery);
if ($servicesResult) {
    while ($row = mysqli_fetch_assoc($servicesResult)) {
        $services[] = $row;
    }
}

// Next Order Number
$nextOrderNo = 1;
$res = mysqli_query($conn, "SELECT MAX(order_id) AS max_id FROM orders");
if ($res && $row = mysqli_fetch_assoc($res)) {
    $nextOrderNo = ($row['max_id'] ?? 0) + 1;
}

$errors = [];

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // MODIFIED: Take values directly from POST, don't fetch from database
    $customerId      = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
    $customerName    = trim($_POST['customer_name'] ?? '');
    $customerPhone   = trim($_POST['customer_phone'] ?? '');
    $customerAddress = trim($_POST['customer_address'] ?? '');
    $manufacturer    = trim($_POST['manufacturer'] ?? '');
    $boxNo           = trim($_POST['box_no'] ?? '');
    $paymentStatus   = $_POST['payment_status'] ?? 'pending';

    $itemIds     = $_POST['item_id'] ?? [];
    $serviceIds  = $_POST['service_id'] ?? [];
    $weights     = $_POST['weight'] ?? [];
    $karats      = $_POST['karat'] ?? [];   
    $quantities  = $_POST['quantity'] ?? [];
    $unitPrices  = $_POST['unit_price'] ?? [];
    $totalPrices = $_POST['total_price'] ?? [];

    if ($customerName === '')  $errors[] = "Customer name is required";
    if ($customerPhone === '') $errors[] = "Customer phone is required";
    if ($paymentStatus === '') $errors[] = "Payment status is required";

    // Validate Items
    $validItems = [];
    for ($i = 0; $i < count($itemIds); $i++) {
        $itemId = !empty($itemIds[$i]) ? intval($itemIds[$i]) : null;
        $serviceId = !empty($serviceIds[$i]) ? intval($serviceIds[$i]) : null;
        $weight = floatval($weights[$i] ?? 0);
        $karat = trim($karats[$i] ?? '');   
        $qty = intval($quantities[$i] ?? 0);
        $unit = floatval($unitPrices[$i] ?? 0);
        $total = floatval($totalPrices[$i] ?? ($qty * $unit));

        // Must have BOTH item_id AND service_id
        if ($itemId !== null && $serviceId !== null && $qty > 0) {
            $validItems[] = [
                'item_id' => $itemId,
                'service_id' => $serviceId,
                'weight' => $weight,
                'karat' => $karat,
                'quantity' => $qty,
                'unit_price' => $unit,
                'total_price' => $total
            ];
        } else if ($itemId !== null || $serviceId !== null) {
            // If only one is selected, show error
            $errors[] = "Row " . ($i + 1) . ": Both Item and Service must be selected";
        }
    }

    if (empty($validItems)) {
        $errors[] = "At least one item is required.";
    }

    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        try {
            // MODIFIED: Use the posted customer_id if provided, but don't override other fields
            $finalCustomerId = $customerId; // Just use the ID as-is, don't fetch from DB
            
            $status = ($paymentStatus === 'paid') ? 'paid' : 'pending';

            // Insert into orders table - use the values from the form
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO orders (customer_id, customer_name, customer_phone, customer_address, manufacturer, box_no, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param(
                $stmt,
                "isssssss",
                $finalCustomerId,
                $customerName,
                $customerPhone,
                $customerAddress,
                $manufacturer,
                $boxNo,
                $status,
                $createdBy
            );

            if (mysqli_stmt_execute($stmt)) {
                $orderId = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);

                // Insert bill_items
                $itemStmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO bill_items (order_id, item_id, service_id, karat, weight, quantity, unit_price, total_price)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );

                foreach ($validItems as $item) {
                    mysqli_stmt_bind_param(
                        $itemStmt,
                        "iiisdidd",
                        $orderId,
                        $item['item_id'],
                        $item['service_id'],
                        $item['karat'],
                        $item['weight'],
                        $item['quantity'],
                        $item['unit_price'],
                        $item['total_price']
                    );
                    
                    if (!mysqli_stmt_execute($itemStmt)) {
                        throw new Exception("Failed to insert bill item: " . mysqli_error($conn));
                    }
                }
                mysqli_stmt_close($itemStmt);

                mysqli_commit($conn);
                header("Location: order.php?success=1&order_id=$orderId");
                exit;
            } else {
                $errors[] = "Failed to insert order: " . mysqli_error($conn);
                mysqli_rollback($conn);
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = "DB Error: " . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>New Order - Rajaiswari Hallmarking Center</title>
<link rel="icon" type="image/png" href="favicon.png">
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
<style>
    body { background: #f8f9fa; }
    .card { margin-bottom: 20px; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
    .qr-test-area { 
        position: fixed; 
        top: 10px; 
        right: 10px; 
        width: 100px; 
        height: 100px; 
        background: white; 
        border: 1px solid #ddd; 
        display: none; 
        z-index: 1000;
    }
    .print-status {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: rgba(0,123,255,0.9);
        color: white;
        padding: 10px;
        border-radius: 5px;
        display: none;
        z-index: 1001;
    }
    .weight-display {
        font-size: 11px;
        color: #0d6efd;
        font-weight: 500;
        margin-top: 2px;
        min-height: 16px;
    }
    .autocomplete-wrapper {
        position: relative;
    }
    .autocomplete-suggestions {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #ddd;
        border-top: none;
        max-height: 250px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .autocomplete-suggestions.active {
        display: block;
    }
    .autocomplete-item {
        padding: 10px 12px;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
        transition: background 0.2s;
    }
    .autocomplete-item:hover {
        background: #f8f9fa;
    }
    .autocomplete-item:last-child {
        border-bottom: none;
    }
    .autocomplete-item .customer-name {
        font-weight: 600;
        color: #333;
        margin-bottom: 2px;
    }
    .autocomplete-item .customer-details {
        font-size: 12px;
        color: #666;
    }
    .autocomplete-item .customer-id {
        display: inline-block;
        background: #e7f3ff;
        color: #0d6efd;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 11px;
        margin-right: 8px;
    }
    .autocomplete-loading {
        padding: 10px;
        text-align: center;
        color: #666;
        font-size: 13px;
    }
    .autocomplete-no-results {
        padding: 10px;
        text-align: center;
        color: #999;
        font-size: 13px;
    }
</style>
</head>
<body>
  <?php include 'navbar.php'; ?>
  
  <div id="qrTestArea" class="qr-test-area"></div>
  <div id="printStatus" class="print-status">🖨️ Preparing receipt...</div>

  <div class="container mt-2">
  <div class="card shadow">
    <div class="card-header bg-primary text-white">
      <h3 class="mb-0">New Order</h3>
    </div>
    <div class="card-body">

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <ul class="mb-0">
            <?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['success'], $_GET['order_id'])): ?>
        <?php
          $orderId = intval($_GET['order_id']);
          
          // Fetch order details
          $stmt = mysqli_prepare($conn, "SELECT * FROM orders WHERE order_id=? LIMIT 1");
          mysqli_stmt_bind_param($stmt, "i", $orderId);
          mysqli_stmt_execute($stmt);
          $res = mysqli_stmt_get_result($stmt);
          $order = mysqli_fetch_assoc($res);
          mysqli_stmt_close($stmt);

          // Fetch bill items with item and service names
          $itemsQuery = "
            SELECT bi.*, 
                   i.name as item_name, 
                   s.name as service_name
            FROM bill_items bi
            LEFT JOIN items i ON bi.item_id = i.id
            LEFT JOIN services s ON bi.service_id = s.id
            WHERE bi.order_id = ?
          ";
          $stmt = mysqli_prepare($conn, $itemsQuery);
          mysqli_stmt_bind_param($stmt, "i", $orderId);
          mysqli_stmt_execute($stmt);
          $res = mysqli_stmt_get_result($stmt);
          
          $billItems = [];
          $grandTotal = 0;
          while ($row = mysqli_fetch_assoc($res)) {
              $billItems[] = $row;
              $grandTotal += floatval($row['total_price']);
          }
          mysqli_stmt_close($stmt);

          $order['items'] = $billItems;
          $order['total_amount'] = $grandTotal;
          $order['id'] = $order['order_id']; // For backward compatibility with JS
        ?>
        <div class="alert alert-success d-flex justify-content-between align-items-center">
          <div>
            <strong>✅ Order Created Successfully!</strong><br>
            Order ID: <?= htmlspecialchars($order['order_id']) ?> | 
            Total: <?= htmlspecialchars(number_format($grandTotal, 2)) ?> TK |
            Status: <?= htmlspecialchars(strtoupper($order['status'])) ?>
            <?php if (!empty($order['created_by'])): ?>
              | Created by: <?= htmlspecialchars($order['created_by']) ?>
            <?php endif; ?>
          </div>
          <div>
            <button class="btn btn-sm btn-outline-dark me-2" id="printAgainBtn">🖨️ Print Again</button>
            <button class="btn btn-sm btn-outline-info" id="testQRBtn">📱 Test QR</button>
          </div>
        </div>

        <script>
          window.billData = <?= json_encode($order, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP); ?>;
        </script>
      <?php endif; ?>

      <form method="POST" id="orderForm" class="mt-3">
        
        <!-- Customer Section -->
        <div class="card mb-4">
          <div class="card-header bg-light">
            <strong>👤 Customer Information</strong>
          </div>
          <div class="card-body">
            <div class="row mb-3">
              <div class="col-md-4">
                <label class="form-label">Customer ID (optional)</label>
                <input type="text" name="customer_id" id="customerId" class="form-control" placeholder="Enter existing ID or leave blank">
                <small class="text-muted">For reference only - won't override your inputs</small>
              </div>
              <div class="col-md-4">
                <label class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" name="customer_name" id="customerName" class="form-control" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Phone <span class="text-danger">*</span></label>
                <input type="text" name="customer_phone" id="customerPhone" class="form-control" required>
              </div>
            </div>
            <div class="row mb-3">
              <div class="col-md-4">
                <label class="form-label">Address</label>
                <input type="text" name="customer_address" id="customerAddress" class="form-control">
              </div>
              <div class="col-md-4">
                <label class="form-label">Manufacturer</label>
                <input type="text" name="manufacturer" id="manufacturer" class="form-control" placeholder="Enter manufacturer name">
              </div>
              <div class="col-md-4">
                <label class="form-label">Box No</label>
                <input type="text" name="box_no" id="boxNo" class="form-control" placeholder="Enter box number">
              </div>
            </div>
          </div>
        </div>

        <!-- Order Section -->
        <div class="card mb-4">
          <div class="card-header bg-light">
            <strong>📋 Order Information</strong>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Order Number</label>
              <input type="text" class="form-control" id="orderNo" name="order_no" 
                     value="<?= htmlspecialchars($nextOrderNo) ?>" readonly>
            </div>

            <!-- Items Header -->
            <div class="row g-2 mb-2 bg-light p-2 rounded">
              <div class="col-md-2"><strong>Items</strong></div>
              <div class="col-md-2"><strong>Services</strong></div>
              <div class="col-md-1"><strong>Weight (g)</strong></div>
              <div class="col-md-2"><strong>Weight (v)</strong></div>
              <div class="col-md-2"><strong>Gold Karat</strong></div>
              <div class="col-md-1"><strong>Qty</strong></div>
              <div class="col-md-2"><strong>Unit Price</strong></div>
            </div>
            
            <div id="itemsSection">
              <div class="item-row mb-3">
                <div class="row g-2 mb-1 align-items-center border-bottom pb-2">
                  <div class="col-md-2">
                    <select name="item_id[]" class="form-select item-select">
                      <option value="">Select Item</option>
                      <?php foreach ($items as $item): ?>
                        <option value="<?= htmlspecialchars($item['id']) ?>">
                          <?= htmlspecialchars($item['name']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-2">
                    <select name="service_id[]" class="form-select service-select">
                      <option value="">Select Service</option>
                      <?php foreach ($services as $service): ?>
                        <option value="<?= htmlspecialchars($service['id']) ?>" 
                                data-price="<?= htmlspecialchars($service['price']) ?>">
                          <?= htmlspecialchars($service['name']) ?>
                          <?php if ($service['price'] > 0): ?>
                            - <?= number_format($service['price'], 2) ?> TK
                          <?php else: ?>
                            (manual)
                          <?php endif; ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-1">
                    <input type="number" name="weight[]" class="form-control weight-input" value="0" min="0" step="0.01">
                  </div>
                  <div class="col-md-2">
                    <div class="weight-display text-secondary fw-bold small"></div>
                  </div>
                  <div class="col-md-2">
                    <input type="text" name="karat[]" class="form-control" placeholder="e.g. 22K">
                  </div>
                  <div class="col-md-1">
                    <input type="number" name="quantity[]" class="form-control quantity-input" value="1" min="1">
                  </div>
                  <div class="col-md-2 d-flex gap-1">
                    <input type="number" name="unit_price[]" class="form-control service-price" value="0" step="0.01">
                    <button type="button" class="btn btn-danger btn-sm remove-item" title="Remove Item">×</button>
                  </div>
                </div>
                <input type="hidden" name="total_price[]" class="total-price" value="0">
              </div>
            </div>
            
            <div class="d-flex justify-content-between align-items-center mt-3 p-3 bg-light rounded">
              <button type="button" id="addItem" class="btn btn-primary">➕ Add Item</button>
              <h5 class="mb-0 text-success">Grand Total: <span id="grandTotal">0.00</span> TK</h5>
            </div>

            <!-- Payment Section -->
            <div class="mt-4 p-3 border rounded">
              <label class="form-label"><strong>💳 Payment Status <span class="text-danger">*</span></strong></label><br>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="payment_status" id="paid" value="paid" required>
                <label class="form-check-label" for="paid">✅ Paid</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="payment_status" id="unpaid" value="pending">
                <label class="form-check-label" for="unpaid">⏳ Pending</label>
              </div>
            </div>
          </div>
        </div>

        <div class="text-end">
          <button type="reset" class="btn btn-secondary me-2">🔄 Reset</button>
          <button type="submit" class="btn btn-success btn-lg">🚀 Submit Order</button>
        </div>
      </form>

    </div>
  </div>
</div>

<script>
// Pass PHP data to JavaScript
const dbItems = <?= json_encode($items) ?>;
const dbServices = <?= json_encode($services) ?>;

// Build options HTML for dynamic row creation
function buildItemOptions() {
  let html = '<option value="">Select Item</option>';
  dbItems.forEach(item => {
    html += `<option value="${item.id}">${escapeHtml(item.name)}</option>`;
  });
  return html;
}

function buildServiceOptions() {
  let html = '<option value="">Select Service</option>';
  dbServices.forEach(service => {
    const priceText = service.price > 0 
      ? ` - ${parseFloat(service.price).toFixed(2)} TK` 
      : ' (manual)';
    html += `<option value="${service.id}" data-price="${service.price}">${escapeHtml(service.name)}${priceText}</option>`;
  });
  return html;
}

function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function convertGramToVoriAna(gram) {
  if (!gram || gram <= 0) return '';
  
  const totalPoints = Math.round((gram / 11.664) * 16 * 6 * 10);
  const bhori = Math.floor(totalPoints / 960);
  const remainingPoints = totalPoints % 960;
  const ana = Math.floor(remainingPoints / 60);
  const remainingAfterAna = remainingPoints % 60;
  const roti = Math.floor(remainingAfterAna / 10);
  const point = remainingAfterAna % 10;
  
  return `V:${bhori} A:${ana} R:${roti} P:${point}`;
}

function updateAllConversions() {
  const itemRows = document.querySelectorAll('.item-row');
  
  itemRows.forEach((row) => {
    const weightInput = row.querySelector('.weight-input');
    const weightDisplay = row.querySelector('.weight-display');
    const gram = parseFloat(weightInput.value) || 0;
    
    if (gram > 0) {
      const conversion = convertGramToVoriAna(gram);
      weightDisplay.textContent = conversion;
    } else {
      weightDisplay.textContent = '';
    }
  });
}

function updateRow(row) {
  const qty = parseFloat(row.querySelector('.quantity-input').value) || 0;
  const unit = parseFloat(row.querySelector('.service-price').value) || 0;
  row.querySelector('.total-price').value = (qty * unit).toFixed(2);
  updateGrand();
}

function updateGrand() {
  let total = 0;
  document.querySelectorAll('.total-price').forEach(el => total += parseFloat(el.value) || 0);
  document.getElementById('grandTotal').textContent = total.toFixed(2);
}

document.addEventListener('change', e => {
  if (e.target.classList.contains('service-select')) {
    const row = e.target.closest('.item-row');
    const unitPriceInput = row.querySelector('.service-price');
    
    if (e.target.value !== '') {
      const selectedOption = e.target.options[e.target.selectedIndex];
      const price = parseFloat(selectedOption.getAttribute('data-price')) || 0;
      unitPriceInput.value = price.toFixed(2);
      updateRow(row);
    }
  }
});

document.addEventListener('input', e => {
  if (e.target.classList.contains('quantity-input') || e.target.classList.contains('service-price')) {
    updateRow(e.target.closest('.item-row'));
  }
  
  if (e.target.classList.contains('weight-input')) {
    updateAllConversions();
  }
});

document.addEventListener('click', e => {
  if (e.target.classList.contains('remove-item')) {
    const rows = document.querySelectorAll('.item-row');
    if (rows.length > 1) {
      e.target.closest('.item-row').remove();
      updateGrand();
      updateAllConversions();
    } else {
      alert('At least one item is required.');
    }
  }
});

document.getElementById('addItem').addEventListener('click', () => {
  const itemRowHtml = `
  <div class="item-row mb-3">
    <div class="row g-2 mb-1 align-items-center border-bottom pb-2">
      <div class="col-md-2">
        <select name="item_id[]" class="form-select item-select">
          ${buildItemOptions()}
        </select>
      </div>
      <div class="col-md-2">
        <select name="service_id[]" class="form-select service-select">
          ${buildServiceOptions()}
        </select>
      </div>
      <div class="col-md-1">
        <input type="number" name="weight[]" class="form-control weight-input" value="0" min="0" step="0.01">
      </div>
      <div class="col-md-2">
        <div class="weight-display text-secondary fw-bold small"></div>
      </div>
      <div class="col-md-2">
        <input type="text" name="karat[]" class="form-control" placeholder="e.g. 22K">
      </div>
      <div class="col-md-1">
        <input type="number" name="quantity[]" class="form-control quantity-input" value="1" min="1">
      </div>
      <div class="col-md-2 d-flex gap-1">
        <input type="number" name="unit_price[]" class="form-control service-price" value="0" step="0.01">
        <button type="button" class="btn btn-danger btn-sm remove-item" title="Remove Item">×</button>
      </div>
    </div>
    <input type="hidden" name="total_price[]" class="total-price" value="0">
  </div>`;
  document.getElementById('itemsSection').insertAdjacentHTML('beforeend', itemRowHtml);
  updateAllConversions();
});

// MODIFIED: Customer autocomplete - fills fields for convenience but doesn't override on submit
(function() {
  const customerNameInput = document.getElementById('customerName');
  const customerIdInput = document.getElementById('customerId');
  const customerPhoneInput = document.getElementById('customerPhone');
  const customerAddressInput = document.getElementById('customerAddress');
  const manufacturerInput = document.getElementById('manufacturer');
  
  const wrapper = document.createElement('div');
  wrapper.className = 'autocomplete-wrapper';
  customerNameInput.parentNode.insertBefore(wrapper, customerNameInput);
  wrapper.appendChild(customerNameInput);
  
  const suggestionsDiv = document.createElement('div');
  suggestionsDiv.className = 'autocomplete-suggestions';
  suggestionsDiv.id = 'customerSuggestions';
  wrapper.appendChild(suggestionsDiv);
  
  let searchTimeout;
  let selectedIndex = -1;
  let suggestions = [];
  
  function searchCustomers(query) {
    if (query.length < 1) {
      hideSuggestions();
      return;
    }
    
    suggestionsDiv.innerHTML = '<div class="autocomplete-loading">🔍 Searching...</div>';
    suggestionsDiv.classList.add('active');
    
    fetch('search_customers.php?query=' + encodeURIComponent(query))
      .then(response => response.json())
      .then(data => {
        if (data.success && data.data.length > 0) {
          suggestions = data.data;
          displaySuggestions(suggestions);
        } else {
          suggestionsDiv.innerHTML = '<div class="autocomplete-no-results">No customers found</div>';
        }
      })
      .catch(error => {
        console.error('Search error:', error);
        hideSuggestions();
      });
  }
  
  function displaySuggestions(customers) {
    suggestionsDiv.innerHTML = '';
    selectedIndex = -1;
    
    customers.forEach((customer, index) => {
      const item = document.createElement('div');
      item.className = 'autocomplete-item';
      item.dataset.index = index;
      
      item.innerHTML = `
        <div class="customer-name">
          <span class="customer-id">ID: ${escapeHtml(customer.id)}</span>
          ${escapeHtml(customer.name)}
        </div>
        <div class="customer-details">
          📱 ${escapeHtml(customer.phone)} 
          ${customer.address ? '| 📍 ' + escapeHtml(customer.address) : ''}
        </div>
      `;
      
      item.addEventListener('click', () => selectCustomer(customer));
      suggestionsDiv.appendChild(item);
    });
    
    suggestionsDiv.classList.add('active');
  }
  
  function selectCustomer(customer) {
    // Fill fields for convenience - user can modify any field
    customerIdInput.value = customer.id;
    customerNameInput.value = customer.name;
    customerPhoneInput.value = customer.phone;
    customerAddressInput.value = customer.address || '';
    if (customer.manufacturer) {
      manufacturerInput.value = customer.manufacturer;
    }
    hideSuggestions();
    
    customerNameInput.style.background = '#d4edda';
    setTimeout(() => {
      customerNameInput.style.background = '';
    }, 500);
  }
  
  function hideSuggestions() {
    suggestionsDiv.classList.remove('active');
    selectedIndex = -1;
  }
  
  function handleKeyNavigation(e) {
    const items = suggestionsDiv.querySelectorAll('.autocomplete-item');
    
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
      updateSelection(items);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      selectedIndex = Math.max(selectedIndex - 1, -1);
      updateSelection(items);
    } else if (e.key === 'Enter' && selectedIndex >= 0) {
      e.preventDefault();
      if (suggestions[selectedIndex]) {
        selectCustomer(suggestions[selectedIndex]);
      }
    } else if (e.key === 'Escape') {
      hideSuggestions();
    }
  }
  
  function updateSelection(items) {
    items.forEach((item, index) => {
      if (index === selectedIndex) {
        item.style.background = '#e7f3ff';
      } else {
        item.style.background = '';
      }
    });
    
    if (selectedIndex >= 0 && items[selectedIndex]) {
      items[selectedIndex].scrollIntoView({ block: 'nearest' });
    }
  }
  
  customerNameInput.addEventListener('input', function(e) {
    const query = this.value.trim();
    clearTimeout(searchTimeout);
    
    if (query.length >= 1) {
      searchTimeout = setTimeout(() => {
        searchCustomers(query);
      }, 300);
    } else {
      hideSuggestions();
    }
  });
  
  customerNameInput.addEventListener('keydown', handleKeyNavigation);
  
  document.addEventListener('click', function(e) {
    if (!wrapper.contains(e.target)) {
      hideSuggestions();
    }
  });
  
})();

// Phone Number Autocomplete
(function() {
  const customerPhoneInput = document.getElementById('customerPhone');
  const customerIdInput = document.getElementById('customerId');
  const customerNameInput = document.getElementById('customerName');
  const customerAddressInput = document.getElementById('customerAddress');
  const manufacturerInput = document.getElementById('manufacturer');
  
  const wrapper = document.createElement('div');
  wrapper.className = 'autocomplete-wrapper';
  customerPhoneInput.parentNode.insertBefore(wrapper, customerPhoneInput);
  wrapper.appendChild(customerPhoneInput);
  
  const suggestionsDiv = document.createElement('div');
  suggestionsDiv.className = 'autocomplete-suggestions';
  suggestionsDiv.id = 'phoneSuggestions';
  wrapper.appendChild(suggestionsDiv);
  
  let searchTimeout;
  let selectedIndex = -1;
  let suggestions = [];
  
  function searchCustomers(query) {
    if (query.length < 1) {
      hideSuggestions();
      return;
    }
    
    suggestionsDiv.innerHTML = '<div class="autocomplete-loading">🔍 Searching...</div>';
    suggestionsDiv.classList.add('active');
    
    fetch('search_customers.php?query=' + encodeURIComponent(query))
      .then(response => response.json())
      .then(data => {
        if (data.success && data.data.length > 0) {
          suggestions = data.data;
          displaySuggestions(suggestions);
        } else {
          suggestionsDiv.innerHTML = '<div class="autocomplete-no-results">No customers found</div>';
        }
      })
      .catch(error => {
        console.error('Search error:', error);
        hideSuggestions();
      });
  }
  
  function displaySuggestions(customers) {
    suggestionsDiv.innerHTML = '';
    selectedIndex = -1;
    
    customers.forEach((customer, index) => {
      const item = document.createElement('div');
      item.className = 'autocomplete-item';
      item.dataset.index = index;
      
      item.innerHTML = `
        <div class="customer-name">
          <span class="customer-id">ID: ${escapeHtml(customer.id)}</span>
          ${escapeHtml(customer.name)}
        </div>
        <div class="customer-details">
          📱 ${escapeHtml(customer.phone)} 
          ${customer.address ? '| 📍 ' + escapeHtml(customer.address) : ''}
        </div>
      `;
      
      item.addEventListener('click', () => selectCustomer(customer));
      suggestionsDiv.appendChild(item);
    });
    
    suggestionsDiv.classList.add('active');
  }
  
  function selectCustomer(customer) {
    customerIdInput.value = customer.id;
    customerNameInput.value = customer.name;
    customerPhoneInput.value = customer.phone;
    customerAddressInput.value = customer.address || '';
    if (customer.manufacturer) {
      manufacturerInput.value = customer.manufacturer;
    }
    hideSuggestions();
    
    customerPhoneInput.style.background = '#d4edda';
    setTimeout(() => {
      customerPhoneInput.style.background = '';
    }, 500);
  }
  
  function hideSuggestions() {
    suggestionsDiv.classList.remove('active');
    selectedIndex = -1;
  }
  
  function handleKeyNavigation(e) {
    const items = suggestionsDiv.querySelectorAll('.autocomplete-item');
    
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
      updateSelection(items);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      selectedIndex = Math.max(selectedIndex - 1, -1);
      updateSelection(items);
    } else if (e.key === 'Enter' && selectedIndex >= 0) {
      e.preventDefault();
      if (suggestions[selectedIndex]) {
        selectCustomer(suggestions[selectedIndex]);
      }
    } else if (e.key === 'Escape') {
      hideSuggestions();
    }
  }
  
  function updateSelection(items) {
    items.forEach((item, index) => {
      if (index === selectedIndex) {
        item.style.background = '#e7f3ff';
      } else {
        item.style.background = '';
      }
    });
    
    if (selectedIndex >= 0 && items[selectedIndex]) {
      items[selectedIndex].scrollIntoView({ block: 'nearest' });
    }
  }
  
  customerPhoneInput.addEventListener('input', function(e) {
    const query = this.value.trim();
    clearTimeout(searchTimeout);
    
    if (query.length >= 1) {
      searchTimeout = setTimeout(() => {
        searchCustomers(query);
      }, 300);
    } else {
      hideSuggestions();
    }
  });
  
  customerPhoneInput.addEventListener('keydown', handleKeyNavigation);
  
  document.addEventListener('click', function(e) {
    if (!wrapper.contains(e.target)) {
      hideSuggestions();
    }
  });
  
})();

// Customer ID search (fetch by ID)
document.getElementById('customerId').addEventListener('blur', function(){
  const id = this.value.trim();
  if (!id) return;
  
  fetch('fetch_customer_owc.php?id=' + encodeURIComponent(id))
    .then(r => r.json())
    .then(j => {
      if(j.success) {
        // Auto-fill fields for convenience - user can still modify
        document.getElementById('customerName').value = j.data.name || '';
        document.getElementById('customerPhone').value = j.data.phone || '';
        document.getElementById('customerAddress').value = j.data.address || '';
        if (j.data.manufacturer) {
          document.getElementById('manufacturer').value = j.data.manufacturer;
        }
        
        // Visual feedback
        const customerIdField = document.getElementById('customerId');
        customerIdField.style.background = '#d4edda';
        setTimeout(() => {
          customerIdField.style.background = '';
        }, 500);
      } else {
        // ID not found
        alert('Customer ID not found in database');
        document.getElementById('customerId').value = '';
        document.getElementById('customerId').focus();
      }
    })
    .catch(err => console.error('Customer fetch error:', err));
});

// QR Code Generation
function generateQRCode(bill) {
  return new Promise((resolve) => {
    try {
      const baseUrl = window.location.origin + window.location.pathname.replace('order.php', '');
      const qrText = `${baseUrl}view_bill.php?id=${bill.id || ''}`;
      const qr = qrcode(0, 'M');
      qr.addData(qrText);
      qr.make();
      
      try {
        const qrSvg = qr.createSvgTag(4, 2);
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const img = new Image();
        
        canvas.width = 120;
        canvas.height = 120;
        
        const svgBlob = new Blob([qrSvg], {type: 'image/svg+xml;charset=utf-8'});
        const url = URL.createObjectURL(svgBlob);
        
        img.onload = function() {
          try {
            ctx.fillStyle = 'white';
            ctx.fillRect(0, 0, 120, 120);
            ctx.drawImage(img, 0, 0, 120, 120);
            const dataURL = canvas.toDataURL('image/png', 0.9);
            URL.revokeObjectURL(url);
            resolve(dataURL);
          } catch (drawError) {
            URL.revokeObjectURL(url);
            fallbackQRGeneration();
          }
        };
        
        img.onerror = function() {
          URL.revokeObjectURL(url);
          fallbackQRGeneration();
        };
        
        setTimeout(() => {
          if (!img.complete) {
            URL.revokeObjectURL(url);
            fallbackQRGeneration();
          }
        }, 3000);
        
        img.src = url;
        
      } catch (svgError) {
        fallbackQRGeneration();
      }
      
      function fallbackQRGeneration() {
        try {
          const modules = qr.getModuleCount();
          const canvas = document.createElement('canvas');
          const ctx = canvas.getContext('2d');
          const cellSize = Math.max(2, Math.floor(120 / modules));
          
          canvas.width = cellSize * modules;
          canvas.height = cellSize * modules;
          
          ctx.fillStyle = 'white';
          ctx.fillRect(0, 0, canvas.width, canvas.height);
          
          ctx.fillStyle = 'black';
          for (let row = 0; row < modules; row++) {
            for (let col = 0; col < modules; col++) {
              if (qr.isDark(row, col)) {
                ctx.fillRect(col * cellSize, row * cellSize, cellSize, cellSize);
              }
            }
          }
          
          resolve(canvas.toDataURL('image/png', 0.9));
        } catch (fallbackError) {
          resolve(null);
        }
      }
      
    } catch (error) {
      resolve(null);
    }
  });
}

function testQRGeneration(bill) {
  generateQRCode(bill).then(dataURL => {
    const testArea = document.getElementById('qrTestArea');
    if (dataURL) {
      testArea.innerHTML = `<img src="${dataURL}" style="width: 100%; height: 100%; object-fit: contain;" alt="QR Test">`;
      testArea.style.display = 'block';
      setTimeout(() => testArea.style.display = 'none', 10000);
    } else {
      testArea.innerHTML = '<div style="color: red; font-size: 10px; text-align: center; padding: 10px;">QR Failed</div>';
      testArea.style.display = 'block';
      setTimeout(() => testArea.style.display = 'none', 5000);
    }
  });
}

// 80mm Receipt HTML
function buildReceiptHtml(bill, qrCodeDataURL = null) {
  let itemsHtml = '';
  if (Array.isArray(bill.items)) {
    bill.items.forEach((item, index) => {
      const weight = parseFloat(item.weight) || 0;
      const voriAnaRoti = convertGramToVoriAna(weight);
      
      const itemName = item.item_name || item.service_name || '';
      
      itemsHtml += `Purpose: ${escapeHtml(item.service_name || '')} | ${escapeHtml(item.karat || '')}<br>`;
      itemsHtml += `Item: ${escapeHtml(itemName)} | Qty: ${escapeHtml(item.quantity || '')}<br>`;
      itemsHtml += `Weight: ${weight.toFixed(2)} gm [${voriAnaRoti}]<br>`;
      if (index < bill.items.length - 1) itemsHtml += '<br>';
    });
  }

  let manufacturerHtml = bill.manufacturer && bill.manufacturer.trim() !== '' 
    ? `Manufacturer: ${escapeHtml(bill.manufacturer)}<br>` : '';
  let boxNoHtml = bill.box_no && bill.box_no.trim() !== '' 
    ? `Box No: ${escapeHtml(bill.box_no)}<br>` : '';

  let qrCodeHtml = '';
  if (qrCodeDataURL && qrCodeDataURL.startsWith('data:image')) {
    qrCodeHtml = `
    <div class="center" style="margin: 8px 0; background: white; padding: 4px; border: 1px solid #ddd;">
      <div style="font-size: 9px; margin-bottom: 2px; color: #666;">Scan for details</div>
      <img src="${qrCodeDataURL}" alt="QR Code" style="width: 85px; height: 85px; display: block; margin: 0 auto;">
    </div>`;
  } else {
    qrCodeHtml = `
    <div class="center" style="margin: 8px 0; background: #f8f9fa; padding: 4px; border: 2px dashed #ccc;">
      <div style="font-size: 8px; color: #999;">QR Code Here</div>
      <div style="width: 60px; height: 60px; border: 2px dashed #999; margin: 0 auto; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #999;">QR</div>
    </div>`;
  }

  const currentDate = new Date().toLocaleString('en-GB', {
    day: '2-digit',
    month: '2-digit', 
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: true
  });

  return `
  <html>
  <head>
    <meta charset="utf-8">
    <style>
      @import url('https://fonts.googleapis.com/css2?family=Alex+Brush&display=swap');
      body { 
        font-family: Arial, sans-serif; 
        font-size: 12px; 
        margin: 4px; 
        line-height: 1.3;
        max-width: 80mm;
      }
      .center { text-align: center; }
      .company-logo { 
        width: 66mm; 
        height: auto; 
        display: block; 
        margin: 0 auto; 
        max-width: 100%;
      }
      hr { 
        border: none; 
        border-top: 1px dashed #000; 
        margin: 4px 0; 
      }
      .footer { 
        text-align: center; 
        margin-top: 4px; 
        font-size: 10px; 
        color: #666;
      }
      .total-line { 
        font-weight: bold; 
        font-size: 14px; 
        text-align: left; 
        margin: 0; 
        padding: 0; 
      }
      .item-line { 
        font-size: 12px; 
        margin: 0; 
        padding: 0; 
        line-height: 1.2; 
      }
      .token-line { 
        font-size: 11px; 
        margin: 0; 
        padding: 0; 
        line-height: 1.4; 
      }
    </style>
  </head>
  <body>
    <div class="center">
      <img src="receiptheader.png" alt="Rajaiswari" class="company-logo" onerror="this.style.display='none';">
    </div>
    <hr>
    <div class="token-line">
      <strong>TOKEN</strong><br>
      Date: ${currentDate}<br>
      Token No: ${escapeHtml(bill.id || bill.order_id || '')}<br>
      Customer ID: ${escapeHtml(bill.customer_id || 'N/A')}<br>
      Name: ${escapeHtml(bill.customer_name || '')}<br>
      Mobile: ${escapeHtml(bill.customer_phone || '')}<br>
      ${bill.customer_address ? `Address: ${escapeHtml(bill.customer_address)}<br>` : ''}
      ${manufacturerHtml}${boxNoHtml}
    </div>
    <hr>
    <div class="item-line">
      <strong>ITEMS:</strong><br>
      ${itemsHtml}
    </div>
    <hr>
    <div class="total-line">
      Total Charge: ${parseFloat(bill.total_amount || 0).toFixed(2)} Tk<br>
      Payment Status: ${escapeHtml((bill.status || '').toUpperCase())}
    </div>
    <hr>
    ${qrCodeHtml}
    <div class="footer">
      THANK YOU | HAVE A GOOD DAY | CDev
    </div>
  </body>
  </html>`;
}

// A4 Format Receipt HTML
function buildA4ReceiptHtml(bill) {
  let itemsHtml = '';
  if (Array.isArray(bill.items)) {
    bill.items.forEach((item) => {
      const weight = parseFloat(item.weight) || 0;
      const itemName = item.item_name || '';
      const karat = item.karat || '';
      const serviceName = item.service_name || '';
      
      itemsHtml += `
        <tr>
          <td>${escapeHtml(itemName)}</td>
          <td>${escapeHtml(serviceName)}</td>
          <td>${weight.toFixed(2)}</td>
          <td>${escapeHtml(karat)}</td>
          <td>&nbsp;</td>
        </tr>`;
    });
  }

  // Add empty rows to fill space
  const emptyRowsNeeded = Math.max(0, 3 - (bill.items ? bill.items.length : 0));
  for (let i = 0; i < emptyRowsNeeded; i++) {
    itemsHtml += `
      <tr>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
      </tr>`;
  }

  const currentDate = new Date().toLocaleDateString('en-GB', {
    day: '2-digit',
    month: '2-digit', 
    year: 'numeric'
  });
  
  const currentTime = new Date().toLocaleTimeString('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true
  });

  return `
  <html>
  <head>
    <meta charset="utf-8">
    <style>
      @page {
        size: A4;
        margin: 20mm;
      }
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }
      body { 
        font-family: Arial, sans-serif; 
        font-size: 16px; 
        padding: 20px;
        width: 210mm;
        min-height: 148.5mm;
      }
      .header {
        text-align: center;
        font-weight: bold;
        font-size: 22px;
        text-decoration: underline;
        margin-bottom: 12px;
      }
      .customer-section {
        margin-bottom: 12px;
        padding-left: 20px;
      }
      .customer-row {
        display: flex;
        margin-bottom: 2px;
        min-height: 20px;
        align-items: center;
      }
      .customer-col {
        display: flex;
        align-items: center;
      }
      .customer-col.left {
        flex: 2;
        padding-right: 30px;
      }
      .customer-col.right {
        flex: 1;
      }
      .customer-col.right .field-label {
        min-width: 80px;
      }
      .field-label {
        font-weight: bold;
        min-width: 140px;
        white-space: nowrap;
      }
      .field-colon {
        margin: 0 5px 0 0;
      }
      .field-value {
        flex: 1;
      }
      .item-section {
        margin-top: 12px;
      }
      table {
        width: 100%;
        border-collapse: collapse;
        border: 2px solid #000;
      }
      .table-header {
        font-weight: bold;
        font-size: 18px;
        text-align: center;
        padding: 4px;
        border: 2px solid #000;
        background-color: #fff;
      }
      th {
        padding: 4px;
        text-align: center;
        border: 1px solid #000;
        font-weight: bold;
        font-size: 16px;
        background-color: #fff;
      }
      td {
        padding: 4px 6px;
        border: 1px solid #000;
        text-align: center;
        min-height: 24px;
        line-height: 1.2;
      }
      .footer-row {
        display: flex;
        border: 2px solid #000;
        border-top: 1px solid #000;
      }
      .footer-cell {
        flex: 1;
        padding: 4px 8px;
        font-weight: bold;
        font-size: 16px;
      }
      .footer-cell:first-child {
        border-right: 1px solid #000;
      }
      .note {
        font-size: 12px;
        margin-top: 6px;
        line-height: 1.5;
        padding-left: 20px;
      }
      @media print {
        body {
          padding: 20mm;
        }
      }
    </style>
  </head>
  <body>
    <div style="height: 1in; display: block;"></div>
    <div class="header">Customer Details</div>
    
    <div class="customer-section">
      <div class="customer-row">
        <div class="customer-col left">
          <span class="field-label">Customer No</span>
          <span class="field-colon">:</span>
          <span class="field-value">${escapeHtml(bill.customer_id || '')}</span>
        </div>
        <div class="customer-col right">
          <span class="field-label">Token No</span>
          <span class="field-colon">:</span>
          <span class="field-value">${escapeHtml(bill.id || bill.order_id || '')}</span>
        </div>
      </div>
      
      <div class="customer-row">
        <div class="customer-col left">
          <span class="field-label">Customer Name</span>
          <span class="field-colon">:</span>
          <span class="field-value">${escapeHtml(bill.customer_name || '')}</span>
        </div>
        <div class="customer-col right">
          <span class="field-label">Date</span>
          <span class="field-colon">:</span>
          <span class="field-value">${currentDate}</span>
        </div>
      </div>
      
      <div class="customer-row">
        <div class="customer-col left">
          <span class="field-label">Mobile</span>
          <span class="field-colon">:</span>
          <span class="field-value">${escapeHtml(bill.customer_phone || '')}</span>
        </div>
        <div class="customer-col right">
          <span class="field-label">Time</span>
          <span class="field-colon">:</span>
          <span class="field-value">${currentTime}</span>
        </div>
      </div>
      
      <div class="customer-row">
        <div class="customer-col left">
          <span class="field-label">Address</span>
          <span class="field-colon">:</span>
          <span class="field-value">${escapeHtml(bill.customer_address || '')}</span>
        </div>
      </div>
      
      <div class="customer-row">
        <div class="customer-col left">
          <span class="field-label">Manufacturer</span>
          <span class="field-colon">:</span>
          <span class="field-value">${escapeHtml(bill.manufacturer || '')}</span>
        </div>
      </div>
      
      <div class="customer-row">
        <div class="customer-col left">
          <span class="field-label">Box no</span>
          <span class="field-colon">:</span>
          <span class="field-value">${escapeHtml(bill.box_no || '')}</span>
        </div>
      </div>
    </div>

    <div class="item-section">
      <table>
        <thead>
          <tr>
            <th colspan="5" class="table-header">ITEM DETAILS</th>
          </tr>
          <tr>
            <th>Items</th>
            <th>Services</th>
            <th>Weight</th>
            <th>Hallmark</th>
            <th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          ${itemsHtml}
        </tbody>
      </table>

      <div class="footer-row">
        <div class="footer-cell">Total Amount : ${parseFloat(bill.total_amount || 0).toFixed(2)} TK</div>
        <div class="footer-cell">Payment Status : ${escapeHtml((bill.status || '').toUpperCase())}</div>
      </div>
    </div>

    <div class="note">
      The jewellery/article tested at the point of soldering chemical plated jewellery will show a low or fluctuating reading.<br>
      We are not responsible for any melting defect.<br>
      Maximum Diff: (+/-) 0.30%.
    </div>

  </body>
  </html>`;
}

function printSilent(html) {
  const iframe = document.createElement('iframe');
  iframe.style.position = 'fixed';
  iframe.style.right = '0';
  iframe.style.bottom = '0';
  iframe.style.width = '0';
  iframe.style.height = '0';
  iframe.style.border = '0';
  iframe.style.visibility = 'hidden';
  document.body.appendChild(iframe);

  const doc = iframe.contentDocument || iframe.contentWindow.document;
  doc.open();
  doc.write(html);
  doc.close();

  setTimeout(() => {
    try {
      iframe.contentWindow.print();
    } catch (printError) {
      console.error('Print error:', printError);
    }
  }, 500);

  setTimeout(() => {
    try {
      iframe.remove();
    } catch (cleanupError) {
      console.error('Cleanup error:', cleanupError);
    }
  }, 3000);
}

// UPDATED: Print both 80mm and A4 receipts
async function printBillTwice(bill) {
  if (!bill) return;
  
  try {
    // Generate QR code for 80mm receipt
    const qrCodeDataURL = await generateQRCode(bill);
    
    // Build both receipt HTMLs
    const html80mm = buildReceiptHtml(bill, qrCodeDataURL);
    const htmlA4 = buildA4ReceiptHtml(bill);
    
    // Print 80mm receipt first
    console.log('Printing 80mm receipt...');
    printSilent(html80mm);
    
    // Print A4 format after a short delay
    setTimeout(() => {
      console.log('Printing A4 receipt...');
      printSilent(htmlA4);
    }, 1500);
    
  } catch (error) {
    console.error('Error in printing:', error);
    try {
      // Fallback without QR code
      const fallbackHtml80mm = buildReceiptHtml(bill, null);
      const fallbackHtmlA4 = buildA4ReceiptHtml(bill);
      
      printSilent(fallbackHtml80mm);
      setTimeout(() => printSilent(fallbackHtmlA4), 1500);
    } catch (fallbackError) {
      console.error('Fallback print failed:', fallbackError);
    }
  }
}

document.addEventListener("DOMContentLoaded", () => {
  if (window.billData) {
    setTimeout(() => printBillTwice(window.billData), 2000);

    const printBtn = document.getElementById("printAgainBtn");
    if (printBtn) {
      printBtn.addEventListener("click", () => printBillTwice(window.billData));
    }

    const testQRBtn = document.getElementById("testQRBtn");
    if (testQRBtn) {
      testQRBtn.addEventListener("click", () => testQRGeneration(window.billData));
    }
  }

  updateGrand();
  updateAllConversions();
});

document.getElementById('orderForm').addEventListener('submit', function(e) {
  const customerName = document.getElementById('customerName').value.trim();
  const customerPhone = document.getElementById('customerPhone').value.trim();
  const paymentStatus = document.querySelector('input[name="payment_status"]:checked');
  
  if (!customerName) {
    e.preventDefault();
    alert('Customer name is required');
    document.getElementById('customerName').focus();
    return false;
  }
  
  if (!customerPhone) {
    e.preventDefault();
    alert('Customer phone is required');
    document.getElementById('customerPhone').focus();
    return false;
  }
  
  if (!paymentStatus) {
    e.preventDefault();
    alert('Payment status is required');
    return false;
  }
  
  const rows = document.querySelectorAll('.item-row');
  let hasValidSelection = false;
  
  rows.forEach(row => {
    const itemSelect = row.querySelector('.item-select');
    const serviceSelect = row.querySelector('.service-select');
    const qty = parseInt(row.querySelector('.quantity-input').value) || 0;
    
    if (qty > 0 && itemSelect && itemSelect.value !== '' && serviceSelect && serviceSelect.value !== '') {
      hasValidSelection = true;
    }
  });
  
  if (!hasValidSelection) {
    e.preventDefault();
    alert('At least one item with BOTH Item and Service selected is required');
    return false;
  }
  
  const submitBtn = this.querySelector('button[type="submit"]');
  submitBtn.disabled = true;
  submitBtn.textContent = 'Processing...';
});
</script>

</body>
</html>