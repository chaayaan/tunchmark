<?php
// monthly_service_chart.php
require 'auth.php';
require 'mydb.php';

$username = htmlspecialchars($_SESSION['username']);
$userRole = $_SESSION['role'] ?? 'employee';

// Get year from query parameter or default to current year
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Get all active services
$servicesQuery = "SELECT id, name FROM services WHERE is_active = 1 ORDER BY name ASC";
$servicesResult = mysqli_query($conn, $servicesQuery);
$services = [];
while ($row = mysqli_fetch_assoc($servicesResult)) {
    $services[] = $row;
}

// Get available years from orders
$yearsQuery = "SELECT DISTINCT YEAR(created_at) as year FROM orders ORDER BY year DESC";
$yearsResult = mysqli_query($conn, $yearsQuery);
$availableYears = [];
while ($row = mysqli_fetch_assoc($yearsResult)) {
    $availableYears[] = $row['year'];
}

// If no years available, add current year
if (empty($availableYears)) {
    $availableYears[] = date('Y');
}

// Get monthly data for each service
$monthlyData = [];
for ($month = 1; $month <= 12; $month++) {
    $monthlyData[$month] = [
        'month_name' => date('M', mktime(0, 0, 0, $month, 1)),
        'services' => []
    ];
    
    foreach ($services as $service) {
        $monthlyData[$month]['services'][$service['name']] = 0;
    }
}

// Query to get monthly income by service for paid orders
$dataQuery = "
    SELECT 
        MONTH(o.created_at) as month,
        s.name as service_name,
        COALESCE(SUM(bi.total_price), 0) as total_income
    FROM orders o
    LEFT JOIN bill_items bi ON o.order_id = bi.order_id
    LEFT JOIN services s ON bi.service_id = s.id
    WHERE o.status = 'paid' 
    AND YEAR(o.created_at) = ?
    AND s.is_active = 1
    GROUP BY MONTH(o.created_at), s.name
    ORDER BY MONTH(o.created_at), s.name
";

$stmt = mysqli_prepare($conn, $dataQuery);
mysqli_stmt_bind_param($stmt, "i", $selectedYear);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $month = intval($row['month']);
    $serviceName = $row['service_name'];
    $income = floatval($row['total_income']);
    
    if (isset($monthlyData[$month]['services'][$serviceName])) {
        $monthlyData[$month]['services'][$serviceName] = $income;
    }
}
mysqli_stmt_close($stmt);

// Define 20 distinct professional colors for services
$colorPalette = [
    '#3b82f6', '#10b981', '#ef4444', '#f59e0b', '#06b6d4',
    '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#6366f1',
    '#84cc16', '#f43f5e', '#0ea5e9', '#a855f7', '#22c55e',
    '#fb923c', '#2dd4bf', '#fbbf24', '#fb7185', '#64748b'
];

// Assign colors to services dynamically based on their order
$serviceColors = [];
$colorIndex = 0;
foreach ($services as $service) {
    $serviceColors[$service['name']] = $colorPalette[$colorIndex % count($colorPalette)];
    $colorIndex++;
}

// Prepare data for Chart.js
$chartLabels = [];
$chartDatasets = [];

// Build labels (months)
foreach ($monthlyData as $month => $data) {
    $chartLabels[] = $data['month_name'];
}

// Build datasets (one per service)
foreach ($services as $service) {
    $serviceName = $service['name'];
    $serviceData = [];
    
    foreach ($monthlyData as $month => $data) {
        $serviceData[] = $data['services'][$serviceName];
    }
    
    $chartDatasets[] = [
        'label' => $serviceName,
        'data' => $serviceData,
        'backgroundColor' => $serviceColors[$serviceName] ?? '#94a3b8',
        'borderColor' => $serviceColors[$serviceName] ?? '#94a3b8',
        'borderWidth' => 1
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Monthly Service Income - <?= $selectedYear ?></title>
  <link rel="icon" type="image/png" href="favicon.png">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    body { 
      background: #f5f7fa;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    }
    
    .page-title {
      font-size: 1.5rem;
      font-weight: 600;
      color: #1e293b;
    }
    
    /* Compact Stats Cards */
    .stat-card-compact {
      background: white;
      border-radius: 8px;
      padding: 12px;
      border: 1px solid #e2e8f0;
      display: flex;
      align-items: center;
      gap: 12px;
      transition: all 0.2s ease;
    }
    
    .stat-card-compact:hover {
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
      border-color: #cbd5e1;
    }
    
    .stat-icon {
      width: 40px;
      height: 40px;
      background: #f1f5f9;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #3b82f6;
      font-size: 18px;
      flex-shrink: 0;
    }
    
    .stat-label {
      font-size: 0.75rem;
      color: #64748b;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 2px;
    }
    
    .stat-value {
      font-size: 1.125rem;
      font-weight: 700;
      color: #1e293b;
      line-height: 1;
    }
    
    .stat-value-sm {
      font-size: 0.875rem;
      font-weight: 600;
      color: #1e293b;
      line-height: 1.3;
    }
    
    /* Chart Container */
    .chart-container-compact {
      position: relative;
      height: 380px;
    }
    
    /* Table Styling */
    .table {
      font-size: 0.875rem;
    }
    
    .table thead th {
      font-weight: 600;
      color: #475569;
      border-bottom: 2px solid #e2e8f0;
    }
    
    .table tbody tr:hover {
      background-color: #f8fafc;
    }
    
    .card {
      border-radius: 8px;
    }
    
    .card-header {
      border-radius: 8px 8px 0 0 !important;
    }
    
    .card-header h6 {
      font-weight: 600;
      color: #1e293b;
    }
    
    @media (max-width: 768px) {
      .page-title {
        font-size: 1.25rem;
      }
      
      .chart-container-compact {
        height: 300px;
      }
      
      .stat-value {
        font-size: 1rem;
      }
      
      .table {
        font-size: 0.8125rem;
      }
    }
  </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container-fluid p-4">
  <!-- Compact Header -->
  <div class="row align-items-center mb-3">
    <div class="col-md-8">
      <h2 class="page-title mb-2">
        <i class="fas fa-chart-bar me-2"></i>Monthly Service Income - <?= $selectedYear ?>
      </h2>
      <p class="text-muted mb-0">Year-round service revenue analysis</p>
    </div>
    <div class="col-md-4">
      <div class="d-flex gap-2 justify-content-md-end mt-3 mt-md-0">
        <select id="yearSelect" class="form-select form-select-sm" onchange="changeYear(this.value)" style="max-width: 150px;">
          <?php foreach ($availableYears as $year): ?>
            <option value="<?= $year ?>" <?= $year == $selectedYear ? 'selected' : '' ?>>
              <?= $year ?>
            </option>
          <?php endforeach; ?>
        </select>
        <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
          <i class="fas fa-arrow-left me-1"></i>Back
        </a>
      </div>
    </div>
  </div>

  <!-- Compact Stats -->
  <div class="row g-2 mb-3">
    <?php 
    $totalIncome = 0;
    $highestMonth = ['name' => '', 'amount' => 0];
    $lowestMonth = ['name' => '', 'amount' => PHP_INT_MAX];
    
    foreach ($monthlyData as $month => $data) {
      $monthTotal = array_sum($data['services']);
      $totalIncome += $monthTotal;
      if ($monthTotal > $highestMonth['amount']) {
        $highestMonth = ['name' => $data['month_name'], 'amount' => $monthTotal];
      }
      if ($monthTotal < $lowestMonth['amount'] && $monthTotal > 0) {
        $lowestMonth = ['name' => $data['month_name'], 'amount' => $monthTotal];
      }
    }
    $avgIncome = $totalIncome / 12;
    ?>
    
    <div class="col-6 col-md-3">
      <div class="stat-card-compact">
        <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
        <div>
          <div class="stat-label">Total Income</div>
          <div class="stat-value">৳<?= number_format($totalIncome, 0) ?></div>
        </div>
      </div>
    </div>
    
    <div class="col-6 col-md-3">
      <div class="stat-card-compact">
        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
        <div>
          <div class="stat-label">Average/Month</div>
          <div class="stat-value">৳<?= number_format($avgIncome, 0) ?></div>
        </div>
      </div>
    </div>
    
    <div class="col-6 col-md-3">
      <div class="stat-card-compact">
        <div class="stat-icon text-success"><i class="fas fa-arrow-up"></i></div>
        <div>
          <div class="stat-label">Best Month</div>
          <div class="stat-value-sm"><?= $highestMonth['name'] ?> - ৳<?= number_format($highestMonth['amount'], 0) ?></div>
        </div>
      </div>
    </div>
    
    <div class="col-6 col-md-3">
      <div class="stat-card-compact">
        <div class="stat-icon text-danger"><i class="fas fa-arrow-down"></i></div>
        <div>
          <div class="stat-label">Lowest Month</div>
          <div class="stat-value-sm"><?= $lowestMonth['name'] ?> - ৳<?= number_format($lowestMonth['amount'], 0) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Chart Card -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3">
      <div class="chart-container-compact">
        <canvas id="monthlyChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Compact Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom">
      <h6 class="mb-0"><i class="fas fa-table me-2"></i>Monthly Breakdown</h6>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Month</th>
              <?php foreach ($services as $service): ?>
                <th class="text-end"><?= htmlspecialchars($service['name']) ?></th>
              <?php endforeach; ?>
              <th class="text-end pe-3"><strong>Total</strong></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($monthlyData as $month => $data): ?>
            <tr>
              <td class="ps-3"><strong><?= $data['month_name'] ?></strong></td>
              <?php 
              $monthTotal = 0;
              foreach ($services as $service): 
                $amount = $data['services'][$service['name']];
                $monthTotal += $amount;
              ?>
                <td class="text-end"><?= $amount > 0 ? '৳'.number_format($amount, 0) : '-' ?></td>
              <?php endforeach; ?>
              <td class="text-end pe-3"><strong>৳<?= number_format($monthTotal, 0) ?></strong></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot class="table-light">
            <tr class="fw-bold">
              <td class="ps-3">TOTAL</td>
              <?php 
              $grandTotal = 0;
              foreach ($services as $service): 
                $serviceTotal = 0;
                foreach ($monthlyData as $month => $data) {
                  $serviceTotal += $data['services'][$service['name']];
                }
                $grandTotal += $serviceTotal;
              ?>
                <td class="text-end">৳<?= number_format($serviceTotal, 0) ?></td>
              <?php endforeach; ?>
              <td class="text-end pe-3">৳<?= number_format($grandTotal, 0) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
  // Year selector
  function changeYear(year) {
    window.location.href = '?year=' + year;
  }

  // Chart.js configuration
  const ctx = document.getElementById('monthlyChart').getContext('2d');
  
  const chartData = {
    labels: <?= json_encode($chartLabels) ?>,
    datasets: <?= json_encode($chartDatasets) ?>
  };
  
  const config = {
    type: 'bar',
    data: chartData,
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        title: {
          display: true,
          text: 'Monthly Service Income for <?= $selectedYear ?>',
          font: {
            size: 16,
            weight: 'bold'
          },
          padding: 15,
          color: '#1e293b'
        },
        legend: {
          display: true,
          position: 'bottom',
          labels: {
            boxWidth: 12,
            font: {
              size: 11
            },
            padding: 10,
            color: '#64748b'
          }
        },
        tooltip: {
          mode: 'index',
          intersect: false,
          backgroundColor: 'rgba(0, 0, 0, 0.8)',
          titleFont: {
            size: 13,
            weight: 'bold'
          },
          bodyFont: {
            size: 12
          },
          padding: 10,
          callbacks: {
            label: function(context) {
              let label = context.dataset.label || '';
              if (label) {
                label += ': ';
              }
              if (context.parsed.y !== null) {
                label += '৳' + context.parsed.y.toLocaleString();
              }
              return label;
            },
            footer: function(tooltipItems) {
              let sum = 0;
              tooltipItems.forEach(function(tooltipItem) {
                sum += tooltipItem.parsed.y;
              });
              return 'Total: ৳' + sum.toLocaleString();
            }
          }
        }
      },
      scales: {
        x: {
          stacked: true,
          grid: {
            display: false
          },
          ticks: {
            font: {
              size: 11,
              weight: '500'
            },
            color: '#64748b'
          }
        },
        y: {
          stacked: true,
          grid: {
            color: 'rgba(0, 0, 0, 0.05)',
            drawBorder: false
          },
          ticks: {
            font: {
              size: 11,
              weight: '500'
            },
            color: '#64748b',
            callback: function(value) {
              return '৳' + (value >= 1000 ? (value/1000).toFixed(0) + 'k' : value);
            }
          }
        }
      },
      animation: {
        duration: 1000,
        easing: 'easeInOutQuart'
      }
    }
  };
  
  const monthlyChart = new Chart(ctx, config);
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
