<?php require 'auth.php'; ?>
<?php include 'navbar.php'; ?>
<?php include 'mydb.php'; ?>
<?php
// Pagination settings
$records_per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 1000;
// Validate records per page (only allow 500, 800, 1000)
if (!in_array($records_per_page, [500, 800, 1000])) {
    $records_per_page = 1000;
}

$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $records_per_page;

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
$params = [];
$types = '';

if (!empty($search)) {
    $search_param = '%' . $search . '%';
    $where_clause = "WHERE id LIKE ? OR name LIKE ? OR phone LIKE ?";
    $params = [$search, $search_param, $search_param];
    $types = 'sss';
}

// Count total records
$count_sql = "SELECT COUNT(*) as total FROM customers $where_clause";
if (!empty($params)) {
    $count_stmt = mysqli_prepare($conn, $count_sql);
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
} else {
    $count_result = mysqli_query($conn, $count_sql);
}
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch paginated records
$data_sql = "SELECT * FROM customers $where_clause ORDER BY id DESC LIMIT ? OFFSET ?";
if (!empty($params)) {
    $params[] = $records_per_page;
    $params[] = $offset;
    $types .= 'ii';
    $stmt = mysqli_prepare($conn, $data_sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
} else {
    $stmt = mysqli_prepare($conn, $data_sql);
    mysqli_stmt_bind_param($stmt, "ii", $records_per_page, $offset);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Build query string for pagination links
function buildQueryString($page, $search, $per_page) {
    $params = ['page' => $page];
    if (!empty($search)) $params['search'] = $search;
    if ($per_page != 1000) $params['per_page'] = $per_page; // Only add if not default
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Customer List</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .search-loading {
            position: absolute;
            right: 50px;
            top: 50%;
            transform: translateY(-50%);
            color: #0d6efd;
        }
        .search-wrapper {
            position: relative;
        }
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
            border-width: 0.15em;
        }
    </style>
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="card shadow-lg p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Customers</h2>
            <a href="create_customer.php" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> New Customer
            </a>
        </div>
        
        <!-- Search Form -->
        <div class="row mb-4">
            <div class="col-md-6">
                <form method="GET" id="searchForm" class="d-flex search-wrapper">
                    <input type="text" 
                           id="searchInput"
                           name="search" 
                           class="form-control me-2" 
                           placeholder="Search by ID, name, or phone..." 
                           value="<?= htmlspecialchars($search) ?>"
                           autocomplete="off">
                    <span class="search-loading" id="searchLoading" style="display: none;">
                        <div class="spinner-border spinner-border-sm" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </span>
                    <input type="hidden" name="per_page" value="<?= $records_per_page ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Search
                    </button>
                </form>
            </div>
            <?php if (!empty($search)): ?>
                <div class="col-md-6">
                    <a href="customers_list.php?per_page=<?= $records_per_page ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> Clear Search
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Results Info and Records Per Page Selector -->
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
            <div class="mb-2">
                <?php if (!empty($search)): ?>
                    <div class="alert alert-info mb-0 py-2">
                        <i class="bi bi-info-circle"></i> 
                        Search results for: <strong>"<?= htmlspecialchars($search) ?>"</strong>
                        (<span id="totalRecords"><?= number_format($total_records) ?></span> result(s) found)
                    </div>
                <?php else: ?>
                    <span class="text-muted">
                        <strong>Total: <span id="totalRecords"><?= number_format($total_records) ?></span> customers</strong>
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="d-flex align-items-center gap-3 mb-2">
                <div>
                    <label class="me-2">Show:</label>
                    <select class="form-select form-select-sm d-inline-block w-auto" 
                            id="perPageSelect"
                            onchange="window.location.href='<?= buildQueryString(1, $search, '') ?>&per_page=' + this.value">
                        <option value="500" <?= $records_per_page == 500 ? 'selected' : '' ?>>500</option>
                        <option value="800" <?= $records_per_page == 800 ? 'selected' : '' ?>>800</option>
                        <option value="1000" <?= $records_per_page == 1000 ? 'selected' : '' ?>>1000</option>
                    </select>
                    <span class="text-muted ms-1">per page</span>
                </div>
                
                <div class="text-muted">
                    Showing <span id="showingFrom"><?= number_format(min($offset + 1, $total_records)) ?></span> 
                    to <span id="showingTo"><?= number_format(min($offset + $records_per_page, $total_records)) ?></span>
                </div>
            </div>
        </div>
        
        <div id="tableContainer">
            <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Manufacturer</th>
                            </tr>
                        </thead>
                        <tbody id="customerTableBody">
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['id']) ?></td>
                                    <td><?= htmlspecialchars($row['name']) ?></td>
                                    <td><?= htmlspecialchars($row['phone']) ?></td>
                                    <td><?= htmlspecialchars($row['address'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($row['manufacturer'] ?? 'N/A') ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" id="paginationContainer">
                        <ul class="pagination justify-content-center">
                            <!-- First Page -->
                            <li class="page-item <?= $current_page == 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= buildQueryString(1, $search, $records_per_page) ?>">
                                    <i class="bi bi-chevron-bar-left"></i>
                                </a>
                            </li>
                            
                            <!-- Previous Page -->
                            <li class="page-item <?= $current_page == 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= buildQueryString($current_page - 1, $search, $records_per_page) ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                            
                            <!-- Page Numbers -->
                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= buildQueryString($i, $search, $records_per_page) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <!-- Next Page -->
                            <li class="page-item <?= $current_page == $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= buildQueryString($current_page + 1, $search, $records_per_page) ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                            
                            <!-- Last Page -->
                            <li class="page-item <?= $current_page == $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= buildQueryString($total_pages, $search, $records_per_page) ?>">
                                    <i class="bi bi-chevron-bar-right"></i>
                                </a>
                            </li>
                        </ul>
                        
                        <!-- Page Info -->
                        <p class="text-center text-muted mb-0">
                            Page <?= $current_page ?> of <?= number_format($total_pages) ?>
                        </p>
                    </nav>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="alert alert-warning text-center">
                    <?php if (!empty($search)): ?>
                        <h5>No customers found for "<?= htmlspecialchars($search) ?>"</h5>
                        <p>Try adjusting your search criteria or <a href="customers_list.php">view all customers</a>.</p>
                    <?php else: ?>
                        <h5>No customers found</h5>
                        <p>Start by creating your first customer.</p>
                    <?php endif; ?>
                    <a href="create_customer.php" class="btn btn-success">Create Customer</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Real-time search functionality for customer list
(function() {
    const searchInput = document.getElementById('searchInput');
    const searchLoading = document.getElementById('searchLoading');
    const searchForm = document.getElementById('searchForm');
    const perPageSelect = document.getElementById('perPageSelect');
    
    let searchTimeout;
    let currentRequest = null;
    
    // Auto-submit search with debounce
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        
        const query = this.value.trim();
        
        // Show loading indicator
        if (query.length >= 1) {
            searchLoading.style.display = 'block';
        } else {
            searchLoading.style.display = 'none';
        }
        
        // Debounce search for 500ms
        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, 500);
    });
    
    // Perform search
    function performSearch(query) {
        // Cancel previous request if exists
        if (currentRequest) {
            currentRequest.abort();
        }
        
        const perPage = perPageSelect.value;
        const url = query.length >= 1 
            ? `customers_list.php?search=${encodeURIComponent(query)}&per_page=${perPage}&page=1`
            : `customers_list.php?per_page=${perPage}`;
        
        // Update URL without reload
        window.history.pushState({}, '', url);
        
        // Make AJAX request
        currentRequest = new XMLHttpRequest();
        currentRequest.open('GET', url, true);
        currentRequest.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        currentRequest.onload = function() {
            if (this.status === 200) {
                updatePageContent(this.responseText);
                searchLoading.style.display = 'none';
            }
        };
        
        currentRequest.onerror = function() {
            searchLoading.style.display = 'none';
        };
        
        currentRequest.send();
    }
    
    // Update page content from AJAX response
    function updatePageContent(html) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        // Update table container
        const newTableContainer = doc.getElementById('tableContainer');
        const currentTableContainer = document.getElementById('tableContainer');
        if (newTableContainer && currentTableContainer) {
            currentTableContainer.innerHTML = newTableContainer.innerHTML;
        }
        
        // Update total records count
        const newTotalRecords = doc.getElementById('totalRecords');
        const currentTotalRecords = document.getElementById('totalRecords');
        if (newTotalRecords && currentTotalRecords) {
            currentTotalRecords.textContent = newTotalRecords.textContent;
        }
        
        // Update showing from/to
        const newShowingFrom = doc.getElementById('showingFrom');
        const currentShowingFrom = document.getElementById('showingFrom');
        if (newShowingFrom && currentShowingFrom) {
            currentShowingFrom.textContent = newShowingFrom.textContent;
        }
        
        const newShowingTo = doc.getElementById('showingTo');
        const currentShowingTo = document.getElementById('showingTo');
        if (newShowingTo && currentShowingTo) {
            currentShowingTo.textContent = newShowingTo.textContent;
        }
    }
    
    // Prevent form submission (we handle it with AJAX)
    searchForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const query = searchInput.value.trim();
        performSearch(query);
    });
    
})();
</script>

</body>
</html>