<?php
/**
 * HUID Helper
 * Converts a sequential integer (the customer_reports.id) into a
 * fixed-length 6-character code using a 57-symbol safe charset
 * (no 0/O/1/I/l confusion). Capacity: 57^6 = 34,296,447,249 codes.
 */

define('HUID_CHARSET', '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz');
define('HUID_LENGTH', 6);

function numberToHuid($number, $charset = HUID_CHARSET, $length = HUID_LENGTH) {
    $base = strlen($charset);
    $huid = '';
    for ($i = 0; $i < $length; $i++) {
        $huid = $charset[$number % $base] . $huid;
        $number = intdiv($number, $base);
    }
    return $huid;
}

/**
 * Generates the HUID for a given report id and saves it back onto
 * the customer_reports row. Call this right after inserting the row
 * and getting its new auto-increment id.
 */
function assignHuid($conn, $report_id) {
    $huid = numberToHuid($report_id);
    $stmt = mysqli_prepare($conn, "UPDATE customer_reports SET huid = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $huid, $report_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $huid;
}