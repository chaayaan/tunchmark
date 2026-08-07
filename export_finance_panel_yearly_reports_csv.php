<?php
/**
 * export_reports_csv.php
 * ---------------------------------------------------------------
 * Exports a single CSV containing MONTHLY REPORT and YEARLY REPORT
 * side by side (horizontally), same data/filters as view_report.php.
 *
 * No external libraries needed — pure PHP.
 * ---------------------------------------------------------------
 */

require 'auth.php';
include 'mydb.php';

date_default_timezone_set('Asia/Dhaka');

/* ============================================================
   1. READ INPUT PARAMETERS (same names as view_report.php)
   ============================================================ */
$selectedYear  = (int)($_GET['year']  ?? date('Y'));
$selectedMonth = (int)($_GET['month'] ?? date('m'));
if ($selectedYear  < 2000 || $selectedYear  > 2030) $selectedYear  = (int)date('Y');
if ($selectedMonth < 1    || $selectedMonth > 12)   $selectedMonth = (int)date('m');

$months = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',
           7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];

function money($n) { return number_format((float)$n, 2, '.', ''); }

/* ============================================================
   2. FETCH CATEGORIES / BRANCHES
   ============================================================ */
$branches = $expenseCategories = $incomeCategories = [];
$r = mysqli_query($conn, "SELECT id, name FROM branches ORDER BY name");
if ($r) while ($row = mysqli_fetch_assoc($r)) $branches[] = $row;
$r = mysqli_query($conn, "SELECT id, name FROM expense_categories ORDER BY name");
if ($r) while ($row = mysqli_fetch_assoc($r)) $expenseCategories[] = $row;
$r = mysqli_query($conn, "SELECT id, name FROM income_categories ORDER BY name");
if ($r) while ($row = mysqli_fetch_assoc($r)) $incomeCategories[] = $row;

/* ============================================================
   3. MONTHLY REPORT DATA
   ============================================================ */
$monthlyIncomeRows = []; // [category, [branch amounts...], total]
$monthlyIncomeTotalByBranch = array_fill(0, count($branches), 0);
$monthlyIncomeGrand = 0;
foreach ($incomeCategories as $cat) {
    $branchAmounts = []; $catTotal = 0;
    foreach ($branches as $bi => $b) {
        $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(income),0) t FROM branch_income WHERE branch_id=? AND category_id=? AND YEAR(created_at)=? AND MONTH(created_at)=?");
        mysqli_stmt_bind_param($stmt, "iiii", $b['id'], $cat['id'], $selectedYear, $selectedMonth);
        mysqli_stmt_execute($stmt);
        $amt = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['t'];
        mysqli_stmt_close($stmt);
        $branchAmounts[] = $amt; $catTotal += $amt;
    }
    if ($catTotal <= 0) continue;
    foreach ($branchAmounts as $bi => $amt) $monthlyIncomeTotalByBranch[$bi] += $amt;
    $monthlyIncomeGrand += $catTotal;
    $monthlyIncomeRows[] = [$cat['name'], $branchAmounts, $catTotal];
}

$monthlyExpenseRows = []; // [category, amount]
$monthlyExpenseGrand = 0;
foreach ($expenseCategories as $cat) {
    $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(expense),0) t FROM branch_expenses WHERE category_id=? AND YEAR(created_at)=? AND MONTH(created_at)=?");
    mysqli_stmt_bind_param($stmt, "iii", $cat['id'], $selectedYear, $selectedMonth);
    mysqli_stmt_execute($stmt);
    $amt = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['t'];
    mysqli_stmt_close($stmt);
    if ($amt <= 0) continue;
    $monthlyExpenseRows[] = [$cat['name'], $amt];
    $monthlyExpenseGrand += $amt;
}
$monthlyNetMargin = $monthlyIncomeGrand - $monthlyExpenseGrand;

/* ============================================================
   4. YEARLY REPORT DATA
   ============================================================ */
$yearlyIncomeByMonth = []; // [month, [branch amounts...], total]
$yearlyIncomeTotalByBranch = array_fill(0, count($branches), 0);
$yearlyIncomeGrand = 0;
for ($m = 1; $m <= 12; $m++) {
    $branchAmounts = []; $monthTotal = 0;
    foreach ($branches as $bi => $b) {
        $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(income),0) t FROM branch_income WHERE branch_id=? AND YEAR(created_at)=? AND MONTH(created_at)=?");
        mysqli_stmt_bind_param($stmt, "iii", $b['id'], $selectedYear, $m);
        mysqli_stmt_execute($stmt);
        $amt = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['t'];
        mysqli_stmt_close($stmt);
        $branchAmounts[] = $amt; $monthTotal += $amt;
    }
    foreach ($branchAmounts as $bi => $amt) $yearlyIncomeTotalByBranch[$bi] += $amt;
    $yearlyIncomeGrand += $monthTotal;
    $yearlyIncomeByMonth[] = [$months[$m], $branchAmounts, $monthTotal];
}

$yearlyExpenseByMonth = []; // [month, amount]
$yearlyExpenseGrand = 0;
for ($m = 1; $m <= 12; $m++) {
    $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(expense),0) t FROM branch_expenses WHERE YEAR(created_at)=? AND MONTH(created_at)=?");
    mysqli_stmt_bind_param($stmt, "ii", $selectedYear, $m);
    mysqli_stmt_execute($stmt);
    $amt = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['t'];
    mysqli_stmt_close($stmt);
    $yearlyExpenseByMonth[] = [$months[$m], $amt];
    $yearlyExpenseGrand += $amt;
}

$yearlyIncomeCatRows = []; // [category, total]
foreach ($incomeCategories as $cat) {
    $catTotal = 0;
    foreach ($branches as $b) {
        $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(income),0) t FROM branch_income WHERE branch_id=? AND category_id=? AND YEAR(created_at)=?");
        mysqli_stmt_bind_param($stmt, "iii", $b['id'], $cat['id'], $selectedYear);
        mysqli_stmt_execute($stmt);
        $catTotal += (float) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['t'];
        mysqli_stmt_close($stmt);
    }
    if ($catTotal > 0) $yearlyIncomeCatRows[] = [$cat['name'], $catTotal];
}

$yearlyExpenseCatRows = []; // [category, total]
foreach ($expenseCategories as $cat) {
    $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(expense),0) t FROM branch_expenses WHERE category_id=? AND YEAR(created_at)=?");
    mysqli_stmt_bind_param($stmt, "ii", $cat['id'], $selectedYear);
    mysqli_stmt_execute($stmt);
    $amt = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['t'];
    mysqli_stmt_close($stmt);
    if ($amt > 0) $yearlyExpenseCatRows[] = [$cat['name'], $amt];
}
$yearlyNetMargin = $yearlyIncomeGrand - $yearlyExpenseGrand;

/* ============================================================
   5. BUILD AS A GRID  grid[row][col] = value
   Two panels placed side by side, both starting at row 1,
   separated by one blank gap column.
   ============================================================ */
$grid = [];
$maxRow = 1;
$maxCol = 1;

function setCell(&$grid, &$maxRow, &$maxCol, $row, $col, $val) {
    $grid[$row][$col] = $val;
    if ($row > $maxRow) $maxRow = $row;
    if ($col > $maxCol) $maxCol = $col;
}

// ---------- PANEL 1: MONTHLY REPORT (columns start at 1) ----------
$p1Col = 1;
$row = 1;
setCell($grid, $maxRow, $maxCol, $row, $p1Col, 'MONTHLY REPORT');
$row++;
setCell($grid, $maxRow, $maxCol, $row, $p1Col, "{$months[$selectedMonth]} {$selectedYear}");
$row += 2;

setCell($grid, $maxRow, $maxCol, $row, $p1Col, 'Income by Category & Branch');
$row++;
setCell($grid, $maxRow, $maxCol, $row, $p1Col, 'Category');
foreach ($branches as $bi => $b) setCell($grid, $maxRow, $maxCol, $row, $p1Col + 1 + $bi, $b['name']);
setCell($grid, $maxRow, $maxCol, $row, $p1Col + 1 + count($branches), 'Total');
$row++;

foreach ($monthlyIncomeRows as [$catName, $amts, $catTotal]) {
    setCell($grid, $maxRow, $maxCol, $row, $p1Col, $catName);
    foreach ($amts as $bi => $amt) setCell($grid, $maxRow, $maxCol, $row, $p1Col + 1 + $bi, money($amt));
    setCell($grid, $maxRow, $maxCol, $row, $p1Col + 1 + count($branches), money($catTotal));
    $row++;
}
setCell($grid, $maxRow, $maxCol, $row, $p1Col, 'Total Income');
foreach ($monthlyIncomeTotalByBranch as $bi => $amt) setCell($grid, $maxRow, $maxCol, $row, $p1Col + 1 + $bi, money($amt));
setCell($grid, $maxRow, $maxCol, $row, $p1Col + 1 + count($branches), money($monthlyIncomeGrand));
$row += 2;

setCell($grid, $maxRow, $maxCol, $row, $p1Col, 'Expenses by Category');
$row++;
setCell($grid, $maxRow, $maxCol, $row, $p1Col, 'Category');
setCell($grid, $maxRow, $maxCol, $row, $p1Col + 1, 'Amount');
$row++;
foreach ($monthlyExpenseRows as [$catName, $amt]) {
    setCell($grid, $maxRow, $maxCol, $row, $p1Col, $catName);
    setCell($grid, $maxRow, $maxCol, $row, $p1Col + 1, money($amt));
    $row++;
}
setCell($grid, $maxRow, $maxCol, $row, $p1Col, 'Total Expenses');
setCell($grid, $maxRow, $maxCol, $row, $p1Col + 1, money($monthlyExpenseGrand));
$row += 2;

setCell($grid, $maxRow, $maxCol, $row, $p1Col, 'Net Margin (Income - Expenses)');
setCell($grid, $maxRow, $maxCol, $row, $p1Col + 1, money($monthlyNetMargin));

$p1Width = max(count($branches) + 2, 2);

// ---------- PANEL 2: YEARLY REPORT ----------
$p2Col = $p1Col + $p1Width + 1; // one gap column
$row = 1;
setCell($grid, $maxRow, $maxCol, $row, $p2Col, 'YEARLY REPORT');
$row++;
setCell($grid, $maxRow, $maxCol, $row, $p2Col, "{$selectedYear}");
$row += 2;

setCell($grid, $maxRow, $maxCol, $row, $p2Col, 'Monthly Income by Branch');
$row++;
setCell($grid, $maxRow, $maxCol, $row, $p2Col, 'Month');
foreach ($branches as $bi => $b) setCell($grid, $maxRow, $maxCol, $row, $p2Col + 1 + $bi, $b['name']);
setCell($grid, $maxRow, $maxCol, $row, $p2Col + 1 + count($branches), 'Total');
$row++;
foreach ($yearlyIncomeByMonth as [$mName, $amts, $mTotal]) {
    setCell($grid, $maxRow, $maxCol, $row, $p2Col, $mName);
    foreach ($amts as $bi => $amt) setCell($grid, $maxRow, $maxCol, $row, $p2Col + 1 + $bi, money($amt));
    setCell($grid, $maxRow, $maxCol, $row, $p2Col + 1 + count($branches), money($mTotal));
    $row++;
}
setCell($grid, $maxRow, $maxCol, $row, $p2Col, 'Yearly Total');
foreach ($yearlyIncomeTotalByBranch as $bi => $amt) setCell($grid, $maxRow, $maxCol, $row, $p2Col + 1 + $bi, money($amt));
setCell($grid, $maxRow, $maxCol, $row, $p2Col + 1 + count($branches), money($yearlyIncomeGrand));
$row += 2;

setCell($grid, $maxRow, $maxCol, $row, $p2Col, 'Monthly Expenses');
$row++;
setCell($grid, $maxRow, $maxCol, $row, $p2Col, 'Month');
setCell($grid, $maxRow, $maxCol, $row, $p2Col + 1, 'Amount');
$row++;
foreach ($yearlyExpenseByMonth as [$mName, $amt]) {
    setCell($grid, $maxRow, $maxCol, $row, $p2Col, $mName);
    setCell($grid, $maxRow, $maxCol, $row, $p2Col + 1, money($amt));
    $row++;
}
setCell($grid, $maxRow, $maxCol, $row, $p2Col, 'Yearly Total');
setCell($grid, $maxRow, $maxCol, $row, $p2Col + 1, money($yearlyExpenseGrand));
$row += 2;

setCell($grid, $maxRow, $maxCol, $row, $p2Col, 'Income Categories - Annual');
$row++;
setCell($grid, $maxRow, $maxCol, $row, $p2Col, 'Category');
setCell($grid, $maxRow, $maxCol, $row, $p2Col + 1, 'Amount');
$row++;
foreach ($yearlyIncomeCatRows as [$catName, $amt]) {
    setCell($grid, $maxRow, $maxCol, $row, $p2Col, $catName);
    setCell($grid, $maxRow, $maxCol, $row, $p2Col + 1, money($amt));
    $row++;
}
$row++;

setCell($grid, $maxRow, $maxCol, $row, $p2Col, 'Expense Categories - Annual');
$row++;
setCell($grid, $maxRow, $maxCol, $row, $p2Col, 'Category');
setCell($grid, $maxRow, $maxCol, $row, $p2Col + 1, 'Amount');
$row++;
foreach ($yearlyExpenseCatRows as [$catName, $amt]) {
    setCell($grid, $maxRow, $maxCol, $row, $p2Col, $catName);
    setCell($grid, $maxRow, $maxCol, $row, $p2Col + 1, money($amt));
    $row++;
}
$row++;
setCell($grid, $maxRow, $maxCol, $row, $p2Col, 'Net Margin (Income - Expenses)');
setCell($grid, $maxRow, $maxCol, $row, $p2Col + 1, money($yearlyNetMargin));

/* ============================================================
   6. OUTPUT AS CSV
   ============================================================ */
$filename = 'reports_export_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel renders ৳ / non-ASCII correctly

for ($r = 1; $r <= $maxRow; $r++) {
    $line = [];
    for ($c = 1; $c <= $maxCol; $c++) {
        $line[] = $grid[$r][$c] ?? '';
    }
    fputcsv($out, $line);
}

fclose($out);
exit;