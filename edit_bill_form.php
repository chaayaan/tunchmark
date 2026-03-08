<?php 
require 'auth.php';
include 'mydb.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}


// Fetch items and services from database
$items_list = [];
$services_list = [];

$itemsQuery = "SELECT id, name FROM items WHERE is_active = 1 ORDER BY name ASC";
$itemsResult = mysqli_query($conn, $itemsQuery);
if ($itemsResult) {
    while ($row = mysqli_fetch_assoc($itemsResult)) {
        $items_list[] = $row;
    }
}

$servicesQuery = "SELECT id, name, price FROM services WHERE is_active = 1 ORDER BY name ASC";
$servicesResult = mysqli_query($conn, $servicesQuery);
if ($servicesResult) {
    while ($row = mysqli_fetch_assoc($servicesResult)) {
        $services_list[] = $row;
    }
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) die("Invalid order id.");

// Fetch order
$stmt = mysqli_prepare($conn, "SELECT * FROM orders WHERE order_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$order) die("Order not found.");

// Fetch order items
$itemsStmt = mysqli_prepare($conn, "
    SELECT bi.*, 
           i.id as item_id, i.name as item_name,
           s.id as service_id, s.name as service_name, s.price as service_price
    FROM bill_items bi
    LEFT JOIN items i ON bi.item_id = i.id
    LEFT JOIN services s ON bi.service_id = s.id
    WHERE bi.order_id = ?
    ORDER BY bi.bill_item_id ASC
");
mysqli_stmt_bind_param($itemsStmt, "i", $id);
mysqli_stmt_execute($itemsStmt);
$itemsRes = mysqli_stmt_get_result($itemsStmt);

$items = [];
$totalAmount = 0;
while ($item = mysqli_fetch_assoc($itemsRes)) {
    $items[] = $item;
    $totalAmount += floatval($item['total_price']);
}
mysqli_stmt_close($itemsStmt);

// If no items, add one empty row
if (empty($items)) {
    $items[] = [
        'bill_item_id' => 0,
        'item_id' => 0,
        'item_name' => '',
        'service_id' => 0,
        'service_name' => '',
        'karat' => '',
        'weight' => 0,
        'quantity' => 1,
        'unit_price' => 0,
        'total_price' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Order #<?= htmlspecialchars($order['order_id']) ?></title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .main-card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background-color: #495057;
            color: white;
            border: none;
            padding: 1.25rem 1.5rem;
        }
        
        .section-card {
            background-color: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }
        
        .section-header {
            background-color: #f8f9fa;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
            display: flex;
            justify-content: between;
            align-items: center;
        }
        
        .item-row {
            padding: 0.5rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .item-row:last-child {
            border-bottom: none;
        }
        
        .total-display {
            background-color: #e7f3ff;
            border-left: 4px solid #0d6efd;
            padding: 1rem;
            border-radius: 4px;
            font-size: 1.25rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container-fluid py-4" style="max-width: 1400px;">
    <div class="main-card">
        <!-- Header -->
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h3 class="mb-0"><i class="fas fa-edit"></i> Edit Order #<?= htmlspecialchars($order['order_id']) ?></h3>
                <small class="text-white-50">Modify order details and items</small>
            </div>
            <a href="edit_orders.php" class="btn btn-light btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Orders
            </a>
        </div>

        <div class="card-body p-4">
            <form id="editOrderForm" method="post" action="update_bill.php">
                <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">

                <!-- Customer Information Section -->
                <div class="section-card">
                    <div class="section-header">
                        <i class="fas fa-user me-2"></i> Customer Information
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Customer Name *</label>
                                <input type="text" name="customer_name" class="form-control" required 
                                       value="<?= htmlspecialchars($order['customer_name']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Customer Phone *</label>
                                <input type="text" name="customer_phone" class="form-control" required 
                                       value="<?= htmlspecialchars($order['customer_phone']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Customer Address</label>
                                <input type="text" name="customer_address" class="form-control" 
                                       value="<?= htmlspecialchars($order['customer_address'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Information Section -->
                <div class="section-card">
                    <div class="section-header">
                        <i class="fas fa-info-circle me-2"></i> Additional Information
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Manufacturer</label>
                                <input type="text" name="manufacturer" class="form-control" 
                                       value="<?= htmlspecialchars($order['manufacturer'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Box No</label>
                                <input type="text" name="box_no" class="form-control" 
                                       value="<?= htmlspecialchars($order['box_no'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Items Section -->
                <div class="section-card">
                    <div class="section-header">
                        <span><i class="fas fa-list me-2"></i> Order Items</span>
                        <button type="button" id="addRow" class="btn btn-sm btn-success ms-auto">
                            <i class="fas fa-plus"></i> Add Item
                        </button>
                    </div>
                    <div class="card-body">
                        <!-- Column Headers -->
                        <div class="row g-2 mb-3 fw-semibold text-muted small">
                            <div class="col-md-2">Item Name</div>
                            <div class="col-md-2">Service</div>
                            <div class="col-md-1">Karat</div>
                            <div class="col-md-1">Weight (g)</div>
                            <div class="col-md-1">Quantity</div>
                            <div class="col-md-2">Unit Price</div>
                            <div class="col-md-2">Total</div>
                            <div class="col-md-1">Action</div>
                        </div>

                        <div id="itemsWrapper">
                            <?php foreach ($items as $i => $item): ?>
                                <div class="row g-2 mb-2 item-row" data-is-existing="true">
                                    <input type="hidden" name="items[<?= $i ?>][bill_item_id]" value="<?= $item['bill_item_id'] ?>">
                                    
                                    <div class="col-md-2">
                                        <select name="items[<?= $i ?>][item_id]" class="form-select form-select-sm" required>
                                            <option value="">Select Item</option>
                                            <?php foreach ($items_list as $itm): ?>
                                                <option value="<?= $itm['id'] ?>" 
                                                        <?= $item['item_id'] == $itm['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($itm['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <select name="items[<?= $i ?>][service_id]" class="form-select form-select-sm service-select" data-row-index="<?= $i ?>" required>
                                            <option value="">Select Service</option>
                                            <?php foreach ($services_list as $srv): ?>
                                                <option value="<?= $srv['id'] ?>" 
                                                        data-price="<?= $srv['price'] ?>"
                                                        <?= $item['service_id'] == $srv['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($srv['name']) ?>
                                                    <?php if ($srv['price'] > 0): ?>
                                                        - ৳<?= number_format($srv['price'], 2) ?>
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-1">
                                        <input type="text" name="items[<?= $i ?>][karat]" class="form-control form-control-sm" 
                                               placeholder="22K" value="<?= htmlspecialchars($item['karat'] ?? '') ?>">
                                    </div>
                                    
                                    <div class="col-md-1">
                                        <input type="number" step="0.01" min="0" name="items[<?= $i ?>][weight]" 
                                               class="form-control form-control-sm" placeholder="0.00" 
                                               value="<?= number_format($item['weight'], 2) ?>">
                                    </div>
                                    
                                    <div class="col-md-1">
                                        <input type="number" step="1" min="1" name="items[<?= $i ?>][quantity]" 
                                               class="form-control form-control-sm qty-input" placeholder="Qty" required 
                                               value="<?= intval($item['quantity']) ?>">
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <input type="number" step="0.01" min="0" name="items[<?= $i ?>][unit_price]" 
                                               class="form-control form-control-sm price-input" placeholder="0.00" required 
                                               value="<?= number_format($item['unit_price'], 2) ?>">
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <input type="text" readonly class="form-control form-control-sm total-cell" 
                                               value="<?= number_format($item['total_price'], 2) ?>">
                                        <input type="hidden" name="items[<?= $i ?>][total_price]" class="hidden-total" 
                                               value="<?= number_format($item['total_price'], 2, '.', '') ?>">
                                    </div>
                                    
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-danger btn-sm remove-row w-100">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Grand Total -->
                        <div class="row mt-4">
                            <div class="col-md-8"></div>
                            <div class="col-md-4">
                                <div class="total-display text-end">
                                    Grand Total: ৳<span id="grandTotalDisplay"><?= number_format($totalAmount, 2) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Section -->
                <div class="section-card">
                    <div class="section-header">
                        <i class="fas fa-credit-card me-2"></i> Payment Status
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Order Status *</label>
                                <select name="status" class="form-select" required>
                                    <option value="paid" <?= $order['status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                                    <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="d-flex justify-content-end gap-2">
                    <a href="edit_bills.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Update Order
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
// Pass PHP data to JavaScript
const dbItems = <?= json_encode($items_list) ?>;
const dbServices = <?= json_encode($services_list) ?>;

function buildItemOptions(selectedId = 0) {
    let html = '<option value="">Select Item</option>';
    dbItems.forEach(item => {
        const selected = item.id == selectedId ? 'selected' : '';
        html += `<option value="${item.id}" ${selected}>${escapeHtml(item.name)}</option>`;
    });
    return html;
}

function buildServiceOptions(selectedId = 0) {
    let html = '<option value="">Select Service</option>';
    dbServices.forEach(service => {
        const selected = service.id == selectedId ? 'selected' : '';
        const priceText = service.price > 0 ? ` - ৳${parseFloat(service.price).toFixed(2)}` : '';
        html += `<option value="${service.id}" data-price="${service.price}" ${selected}>${escapeHtml(service.name)}${priceText}</option>`;
    });
    return html;
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

(function(){
    function updateRowTotal(row) {
        const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        const price = parseFloat(row.querySelector('.price-input').value) || 0;
        const total = qty * price;
        row.querySelector('.total-cell').value = total.toFixed(2);
        row.querySelector('.hidden-total').value = total.toFixed(2);
        updateGrandTotal();
    }
    
    function updateGrandTotal() {
        let sum = 0;
        document.querySelectorAll('.hidden-total').forEach(function(inp){
            sum += parseFloat(inp.value) || 0;
        });
        document.getElementById('grandTotalDisplay').textContent = sum.toFixed(2);
    }

    // Service select change - auto-fill price ONLY for NEW rows
    document.getElementById('itemsWrapper').addEventListener('change', function(e){
        if (e.target.classList.contains('service-select')) {
            const row = e.target.closest('.item-row');
            const isExisting = row.getAttribute('data-is-existing') === 'true';
            
            // Only auto-fill price for NEW rows, not existing ones from database
            if (!isExisting) {
                const selectedOption = e.target.options[e.target.selectedIndex];
                const price = parseFloat(selectedOption.getAttribute('data-price')) || 0;
                const priceInput = row.querySelector('.price-input');
                
                if (price > 0) {
                    priceInput.value = price.toFixed(2);
                    updateRowTotal(row);
                }
            }
        }
    });

    // Update totals on input
    document.getElementById('itemsWrapper').addEventListener('input', function(e){
        if (e.target.classList.contains('qty-input') || e.target.classList.contains('price-input')) {
            const row = e.target.closest('.item-row');
            updateRowTotal(row);
        }
    });

    // Add new row
    document.getElementById('addRow').addEventListener('click', function(){
        const rows = document.querySelectorAll('.item-row');
        const index = rows.length;
        const el = document.createElement('div');
        el.className = 'row g-2 mb-2 item-row';
        el.setAttribute('data-is-existing', 'false'); // Mark as new row
        el.innerHTML = `
            <input type="hidden" name="items[${index}][bill_item_id]" value="0">
            <div class="col-md-2">
                <select name="items[${index}][item_id]" class="form-select form-select-sm" required>
                    ${buildItemOptions()}
                </select>
            </div>
            <div class="col-md-2">
                <select name="items[${index}][service_id]" class="form-select form-select-sm service-select" data-row-index="${index}" required>
                    ${buildServiceOptions()}
                </select>
            </div>
            <div class="col-md-1">
                <input type="text" name="items[${index}][karat]" class="form-control form-control-sm" placeholder="22K">
            </div>
            <div class="col-md-1">
                <input type="number" step="0.01" min="0" name="items[${index}][weight]" class="form-control form-control-sm" placeholder="0.00">
            </div>
            <div class="col-md-1">
                <input type="number" step="1" min="1" name="items[${index}][quantity]" class="form-control form-control-sm qty-input" placeholder="Qty" required value="1">
            </div>
            <div class="col-md-2">
                <input type="number" step="0.01" min="0" name="items[${index}][unit_price]" class="form-control form-control-sm price-input" placeholder="0.00" required>
            </div>
            <div class="col-md-2">
                <input type="text" readonly class="form-control form-control-sm total-cell" value="0.00">
                <input type="hidden" name="items[${index}][total_price]" class="hidden-total" value="0.00">
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-danger btn-sm remove-row w-100">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        document.getElementById('itemsWrapper').appendChild(el);
    });

    // Remove row
    document.getElementById('itemsWrapper').addEventListener('click', function(e){
        if (e.target.closest('.remove-row')) {
            const rows = document.querySelectorAll('.item-row');
            if (rows.length > 1) {
                e.target.closest('.item-row').remove();
                // Reindex
                document.querySelectorAll('.item-row').forEach(function(r, idx){
                    r.querySelectorAll('input[name], select[name]').forEach(function(inp){
                        const name = inp.getAttribute('name');
                        const newName = name.replace(/items\[\d+\]/, 'items['+idx+']');
                        inp.setAttribute('name', newName);
                    });
                });
                updateGrandTotal();
            } else {
                alert('You must have at least one item.');
            }
        }
    });

    // Initial calculation
    document.querySelectorAll('.item-row').forEach(function(r){
        updateRowTotal(r);
    });
})();
</script>
</body>
</html>