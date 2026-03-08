<?php require 'auth.php'; ?>
<?php include 'navbar.php'; ?>
<?php include 'mydb.php'; // your DB connection file?>
<?php
// create_customer.php

$success = $error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $manufacturer = trim($_POST['manufacturer']);

    if ($name === "" || $phone === "") {
        $error = "Name and Phone are required!";
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO customers (name, phone, address, manufacturer) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssss", $name, $phone, $address, $manufacturer);

        if (mysqli_stmt_execute($stmt)) {
            $success = "Customer created successfully with ID: " . mysqli_insert_id($conn);
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Customer</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="card shadow-lg p-4">
        <h2 class="mb-4">New Customer Entry</h2>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label class="form-label">Customer Name *</label>
                <input type="text" name="name" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Phone *</label>
                <input type="text" name="phone" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Manufacturer</label>
                <input type="text" name="manufacturer" class="form-control" placeholder="Enter manufacturer name">
            </div>

            <button type="submit" class="btn btn-primary">Create Customer</button>
            <a href="customers_list.php" class="btn btn-secondary">View Customers</a>
        </form>
    </div>
</div>
</body>
</html>