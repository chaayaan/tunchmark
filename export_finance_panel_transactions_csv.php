<?php
/**
 * export_transactions_csv.php
 * ---------------------------------------------------------------
 * Exports a single CSV containing ONLY the Transactions list,
 * same records/filters as transactions_list.php.
 *
 * Totals (Income / Expenses / Net Balance) are shown at the TOP,
 * right under the filter line, instead of at the bottom.
 *
 * No external libraries needed — pure PHP fputcsv().
 * ---------------------------------------------------------------
 */

require 'auth.php';
include 'mydb.php';

date_default_timezone_set('Asia/Dhaka');

/* ============================================================
   1. READ INPUT PARAMETERS (same names as transactions_list.php)
   ============================================================ */
$filterType     = $_GET['type']      ?? 'all';
$filterDateFrom = $_GET['date_from'] ?? date('Y-m-01');
$filterDateTo   = $_GET['date_to']   ?? date('Y-m-d');

function money($n) { return number_format((float)$n, 2, '.', ''); }

/* ============================================================
   2. FETCH TRANSACTIONS
   ============================================================ */
$incomeQ = "SELECT bi.id, 'income' as type, bi.created_at as date, ic.name as category,
             b.name as branch, bi.income as amount, bi.notes
             FROM branch_income bi
             LEFT JOIN income_categories ic ON bi.category_id = ic.id
             LEFT JOIN branches b ON bi.branch_id = b.id WHERE 1=1";
$expenseQ = "SELECT be.id, 'expense' as type, be.created_at as date, ec.name as category,
              'N/A' as branch, be.expense as amount, be.notes
              FROM branch_expenses be
              LEFT JOIN expense_categories ec ON be.category_id = ec.id WHERE 1=1";

$dateWhere = '';
if (!empty($filterDateFrom)) $dateWhere .= " AND DATE(created_at) >= '" . mysqli_real_escape_string($conn, $filterDateFrom) . "'";
if (!empty($filterDateTo))   $dateWhere .= " AND DATE(created_at) <= '" . mysqli_real_escape_string($conn, $filterDateTo) . "'";

$incomeQ  .= str_replace('created_at', 'bi.created_at', $dateWhere);
$expenseQ .= str_replace('created_at', 'be.created_at', $dateWhere);

$unionParts = [];
if ($filterType === 'all' || $filterType === 'income')   $unionParts[] = $incomeQ;
if ($filterType === 'all' || $filterType === 'expenses') $unionParts[] = $expenseQ;
$unionSQL = implode(' UNION ALL ', $unionParts);

$transactions = [];
if ($unionSQL) {
    $r = mysqli_query($conn, "SELECT * FROM ($unionSQL) t ORDER BY date DESC");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $transactions[] = $row;
}

$totalIncome = $totalExpenses = 0;
foreach ($transactions as $t) {
    if ($t['type'] === 'income') $totalIncome += $t['amount'];
    else $totalExpenses += $t['amount'];
}
$netBalance = $totalIncome - $totalExpenses;

/* ============================================================
   3. OUTPUT AS CSV
   ============================================================ */
$filename = 'transactions_export_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel renders ৳ / non-ASCII correctly

fputcsv($out, ['TRANSACTIONS']);
fputcsv($out, ["Period: {$filterDateFrom} to {$filterDateTo}", "Type: " . ucfirst($filterType)]);
fputcsv($out, []);

// --- Totals block at the TOP ---
fputcsv($out, ['Total Income', money($totalIncome)]);
fputcsv($out, ['Total Expenses', money($totalExpenses)]);
fputcsv($out, ['Net Balance', money($netBalance)]);
fputcsv($out, []);

// --- Table ---
fputcsv($out, ['ID', 'Type', 'Date', 'Category', 'Branch', 'Amount', 'Notes']);
foreach ($transactions as $t) {
    fputcsv($out, [
        $t['id'],
        $t['type'] === 'income' ? 'Income' : 'Expense',
        date('Y-m-d H:i', strtotime($t['date'])),
        $t['category'],
        $t['branch'] !== 'N/A' ? $t['branch'] : 'Company-wide',
        money($t['amount']),
        $t['notes'] ?? '',
    ]);
}
if (empty($transactions)) {
    fputcsv($out, ['No transactions found for the selected filters.']);
}

fclose($out);
exit;