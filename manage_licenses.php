<?php
require 'auth.php';
require 'mylicensedb.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO licenses (branch_name, branch_app_link, database_name, license_key, expire_date, last_renew_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['branch_name'],
                $_POST['branch_app_link'],
                $_POST['database_name'],
                $_POST['license_key'],
                $_POST['expire_date'],
                $_POST['last_renew_date'] ?: null,
                $_POST['status']
            ]);
            echo json_encode(['success' => true, 'message' => 'License added successfully']);
        } elseif ($_POST['action'] === 'update') {
            $stmt = $pdo->prepare("UPDATE licenses SET branch_name = ?, branch_app_link = ?, database_name = ?, license_key = ?, expire_date = ?, last_renew_date = ?, status = ? WHERE id = ?");
            $stmt->execute([
                $_POST['branch_name'],
                $_POST['branch_app_link'],
                $_POST['database_name'],
                $_POST['license_key'],
                $_POST['expire_date'],
                $_POST['last_renew_date'] ?: null,
                $_POST['status'],
                $_POST['id']
            ]);
            echo json_encode(['success' => true, 'message' => 'License updated successfully']);
        } elseif ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM licenses WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            echo json_encode(['success' => true, 'message' => 'License deleted successfully']);
        } elseif ($_POST['action'] === 'fetch') {
            $stmt = $pdo->query("SELECT * FROM licenses ORDER BY expire_date ASC");
            $licenses = $stmt->fetchAll();
            echo json_encode(['success' => true, 'licenses' => $licenses]);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            background: white;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h2 {
            color: #333;
            margin-bottom: 20px;
        }
        .table {
            margin-top: 20px;
            font-size: 0.85rem;
        }
        .table th {
            font-size: 0.8rem;
            padding: 8px 6px;
            white-space: nowrap;
        }
        .table td {
            padding: 6px 6px;
            vertical-align: middle;
        }
        .btn-edit {
            color: #0d6efd;
            background: none;
            border: none;
            cursor: pointer;
            padding: 2px 5px;
        }
        .btn-delete {
            color: #dc3545;
            background: none;
            border: none;
            cursor: pointer;
            padding: 2px 5px;
        }
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        .form-section {
            background: #fafafa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        .db-name-hidden {
            display: none;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <h2>License Management</h2>

        <!-- Add License Form -->
        <div class="form-section">
            <div style="background: #e9ecef; padding: 10px 15px; border-radius: 5px; margin-bottom: 20px;">
                <h5 style="margin: 0; color: #495057;">Add New License</h5>
            </div>
            <form id="addForm">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Branch Name *</label>
                        <input type="text" name="branch_name" class="form-control" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Branch App Link</label>
                        <input type="text" name="branch_app_link" class="form-control">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Database Name</label>
                        <input type="text" name="database_name" class="form-control">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">License Key *</label>
                        <input type="text" name="license_key" class="form-control" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Expiration Date *</label>
                        <input type="date" name="expire_date" class="form-control" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Last Renew Date</label>
                        <input type="date" name="last_renew_date" class="form-control">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Status *</label>
                        <select name="status" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="expired">Expired</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Add License</button>
            </form>
        </div>

        <!-- Licenses Table -->
        <div style="background: #e9ecef; padding: 10px 15px; border-radius: 5px; margin-bottom: 15px;">
            <h5 style="margin: 0; color: #495057;">All Licenses</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="table-light">
                    <tr>
                        <th>Branch Name</th>
                        <th>App Link</th>
                        <th>License Key</th>
                        <th>Expire Date</th>
                        <th>Days Left</th>
                        <th>Last Renew</th>
                        <th>Created At</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="licensesTable">
                    <tr><td colspan="9" class="text-center">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container"></div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            loadLicenses();
            
            // Check for toast message from session
            <?php if (isset($_SESSION['toast_message'])): ?>
                showToast('<?php echo $_SESSION['toast_message']; ?>', '<?php echo $_SESSION['toast_type']; ?>');
                <?php 
                    unset($_SESSION['toast_message']);
                    unset($_SESSION['toast_type']);
                ?>
            <?php endif; ?>
        });

        function loadLicenses() {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ajax=1&action=fetch'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayLicenses(data.licenses);
                }
            });
        }

        function displayLicenses(licenses) {
            const tbody = document.getElementById('licensesTable');
            
            if (licenses.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center">No licenses found</td></tr>';
                return;
            }

            tbody.innerHTML = licenses.map(license => {
                const expireDate = new Date(license.expire_date);
                const today = new Date();
                const diffTime = expireDate - today;
                const daysLeft = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                let daysDisplay = '';
                let rowStyle = '';
                
                if (daysLeft < 0) {
                    daysDisplay = '<span style="color: red; font-weight: bold;">EXPIRED</span>';
                    rowStyle = 'background-color: #ffe6e6;';
                } else {
                    daysDisplay = daysLeft + ' days';
                }

                // Format created_at date
                const createdAt = new Date(license.created_at);
                const createdAtFormatted = createdAt.toLocaleDateString('en-GB', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric'
                });

                return `
                    <tr style="${rowStyle}" data-database="${license.database_name || ''}">
                        <td>${license.branch_name}</td>
                        <td style="max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${license.branch_app_link || ''}">${license.branch_app_link || '-'}</td>
                        <td>${license.license_key}</td>
                        <td>${license.expire_date}</td>
                        <td>${daysDisplay}</td>
                        <td>${license.last_renew_date || '-'}</td>
                        <td>${createdAtFormatted}</td>
                        <td>${license.status}</td>
                        <td style="white-space: nowrap;">
                            <a href="edit_license.php?id=${license.id}" class="btn-edit" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button class="btn-delete" onclick="deleteLicense(${license.id})" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        document.getElementById('addForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('ajax', '1');
            formData.append('action', 'add');

            fetch('', {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    this.reset();
                    loadLicenses();
                } else {
                    showToast(data.message, 'danger');
                }
            });
        });

        function deleteLicense(id) {
            if (!confirm('Are you sure you want to delete this license?')) {
                return;
            }

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ajax=1&action=delete&id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    loadLicenses();
                } else {
                    showToast(data.message, 'danger');
                }
            });
        }

        function showToast(message, type) {
            const toastHtml = `
                <div class="toast align-items-center text-white bg-${type} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;
            
            const container = document.querySelector('.toast-container');
            container.innerHTML = toastHtml;
            const toastElement = container.querySelector('.toast');
            const toast = new bootstrap.Toast(toastElement);
            toast.show();
        }
    </script>
</body>
</html>