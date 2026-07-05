<?php
require 'mydb.php';
require 'huid_helper.php';   // ← this file, unchanged

// Fetch all rows still missing a HUID
$result = mysqli_query($conn, "SELECT id FROM customer_reports WHERE huid IS NULL OR huid = ''");

$count = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $id = (int)$row['id'];
    $huid = assignHuid($conn, $id); // uses numberToHuid() internally, saves to DB
    echo "id=$id -> huid=$huid\n";
    $count++;
}

echo "\nDone. Updated $count rows.\n";