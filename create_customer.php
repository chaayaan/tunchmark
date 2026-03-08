<?php
// All PHP logic FIRST — before any output so header() works
require 'auth.php';
require 'mydb.php';

$success = $error = "";
if (isset($_GET['success']) && isset($_GET['id'])) {
    $success = "Customer created successfully with ID: " . intval($_GET['id']);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name         = trim($_POST['name']);
    $phone        = trim($_POST['phone']);
    $address      = trim($_POST['address']);
    $manufacturer = trim($_POST['manufacturer']);

    if ($name === "" || $phone === "") {
        $error = "Name and Phone are required!";
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO customers (name, phone, address, manufacturer) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssss", $name, $phone, $address, $manufacturer);

        if (mysqli_stmt_execute($stmt)) {
            $newId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
            header("Location: create_customer.php?success=1&id=" . $newId);
            exit;
        } else {
            $error = "Error: " . mysqli_error($conn);
            mysqli_stmt_close($stmt);
        }
    }
}

// NOW include navbar (outputs HTML — must be after all header() calls)
include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New Customer — Rajaiswari</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:       #f1f3f6;
            --surface:  #ffffff;
            --s2:       #fafbfc;
            --border:   #e4e7ec;
            --bsoft:    #f0f1f3;
            --t1:       #111827;
            --t2:       #374151;
            --t3:       #6b7280;
            --t4:       #9ca3af;
            --blue:     #2563eb; --blue-bg: #eff6ff; --blue-b: #bfdbfe;
            --green:    #059669; --green-bg:#ecfdf5; --green-b:#a7f3d0;
            --red:      #dc2626; --red-bg:  #fef2f2; --red-b:  #fecaca;
            --r: 10px; --rs: 6px;
            --sh: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', -apple-system, sans-serif;
            font-size: 14px;
            background: var(--bg);
            color: var(--t1);
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
        }

        /* ── Shell ─────────────────────────────── */
        .page-shell {
            margin-left: 200px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Top bar ───────────────────────────── */
        .top-bar {
            position: sticky; top: 0; z-index: 200;
            height: 54px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            box-shadow: var(--sh);
            display: flex; align-items: center;
            padding: 0 22px; gap: 12px; flex-shrink: 0;
        }
        .tb-ico {
            width: 32px; height: 32px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; background: var(--green-bg); color: var(--green);
            flex-shrink: 0;
        }
        .tb-title { font-size: 1.0625rem; font-weight: 700; color: var(--t1); }
        .tb-sub   { font-size: .78rem; color: var(--t4); }
        .tb-right { margin-left: auto; display: flex; gap: 8px; align-items: center; }

        /* ── Buttons ───────────────────────────── */
        .btn-pos {
            display: inline-flex; align-items: center; gap: 6px;
            height: 34px; padding: 0 16px;
            border: none; border-radius: var(--rs);
            font-family: inherit; font-size: .875rem; font-weight: 600;
            cursor: pointer; transition: all .15s; text-decoration: none;
        }
        .btn-blue  { background: var(--blue);  color: #fff; }
        .btn-blue:hover  { background: #1d4ed8; color: #fff; }
        .btn-green { background: var(--green); color: #fff; }
        .btn-green:hover { background: #047857; color: #fff; }
        .btn-ghost {
            background: var(--surface); color: var(--t2);
            border: 1.5px solid var(--border);
        }
        .btn-ghost:hover { background: var(--s2); border-color: #9ca3af; color: var(--t1); }

        /* ── Main ──────────────────────────────── */
        .main {
            flex: 1;
            padding: 20px 22px 60px;
            display: flex; flex-direction: column; gap: 16px;
        }

        /* ── Alert ─────────────────────────────── */
        .pos-alert {
            display: flex; align-items: flex-start; gap: 11px;
            padding: 13px 16px; border-radius: var(--rs);
            font-size: .875rem;
        }
        .pos-alert i { font-size: .95rem; flex-shrink: 0; margin-top: 1px; }
        .pos-alert.success {
            background: var(--green-bg); border: 1px solid var(--green-b);
            border-left: 3px solid var(--green); color: #065f46;
        }
        .pos-alert.error {
            background: var(--red-bg); border: 1px solid var(--red-b);
            border-left: 3px solid var(--red); color: #991b1b;
        }

        /* ── Card ──────────────────────────────── */
        .pos-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            box-shadow: var(--sh);
            overflow: hidden;
            max-width: 860px;
        }

        .card-head {
            display: flex; align-items: center; gap: 10px;
            padding: 13px 20px;
            background: var(--s2);
            border-bottom: 1px solid var(--bsoft);
        }
        .ch-ico {
            width: 28px; height: 28px; border-radius: var(--rs);
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; flex-shrink: 0;
            background: var(--green-bg); color: var(--green);
        }
        .card-title { font-size: .9375rem; font-weight: 700; color: var(--t1); }

        /* ── Form body ─────────────────────────── */
        .form-body { padding: 22px 22px 8px; }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
        }

        .form-full { margin-bottom: 16px; }

        .lbl {
            display: block;
            font-size: .74rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .06em;
            color: var(--t3); margin-bottom: 5px;
        }
        .lbl .req { color: var(--red); margin-left: 2px; }

        .fc {
            width: 100%; height: 40px;
            padding: 0 12px;
            border: 1.5px solid var(--border); border-radius: var(--rs);
            font-family: inherit; font-size: .9375rem; color: var(--t2);
            background: var(--surface); outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        textarea.fc {
            height: 80px; padding: 10px 12px;
            resize: vertical; line-height: 1.5;
        }
        .fc:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37,99,235,.1);
        }
        .fc::placeholder { color: var(--t4); }

        /* ── Form footer ───────────────────────── */
        .form-footer {
            display: flex; align-items: center; gap: 8px;
            padding: 14px 22px;
            background: var(--s2);
            border-top: 1px solid var(--bsoft);
        }

        /* ── Responsive ────────────────────────── */
        @media (max-width: 991.98px) {
            .page-shell { margin-left: 0; }
            .top-bar    { top: 52px; }
            .main       { padding: 14px 14px 50px; }
            .pos-card   { max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="page-shell">

    <!-- Top Bar -->
    <header class="top-bar">
        <div class="tb-ico"><i class="fas fa-user-plus"></i></div>
        <div>
            <div class="tb-title">New Customer</div>
            <div class="tb-sub">Add a new customer record</div>
        </div>
        <div class="tb-right">
            <a href="customers_list.php" class="btn-pos btn-ghost">
                <i class="fas fa-arrow-left" style="font-size:.65rem;"></i> Back to List
            </a>
        </div>
    </header>

    <div class="main">

        <!-- Success alert -->
        <?php if ($success): ?>
        <div class="pos-alert success" style="max-width:860px;">
            <i class="fas fa-circle-check"></i>
            <div>
                <strong>Customer Created!</strong><br>
                <span style="font-size:.82rem;"><?= htmlspecialchars($success) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Error alert -->
        <?php if ($error): ?>
        <div class="pos-alert error" style="max-width:860px;">
            <i class="fas fa-circle-exclamation"></i>
            <div>
                <strong>Error</strong><br>
                <span style="font-size:.82rem;"><?= htmlspecialchars($error) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Form card -->
        <div class="pos-card">
            <div class="card-head">
                <span class="ch-ico"><i class="fas fa-user-plus"></i></span>
                <span class="card-title">Customer Information</span>
            </div>

            <form method="post" id="customerForm">
                <div class="form-body">

                    <div class="form-row">
                        <div>
                            <label class="lbl">Customer Name <span class="req">*</span></label>
                            <input type="text" name="name" class="fc" required
                                   placeholder="Enter full name"
                                   value="<?= isset($_POST['name']) && $error ? htmlspecialchars($_POST['name']) : '' ?>">
                        </div>
                        <div>
                            <label class="lbl">Phone <span class="req">*</span></label>
                            <input type="text" name="phone" class="fc" required
                                   placeholder="Mobile number"
                                   value="<?= isset($_POST['phone']) && $error ? htmlspecialchars($_POST['phone']) : '' ?>">
                        </div>
                    </div>

                    <div class="form-full">
                        <label class="lbl">Address</label>
                        <textarea name="address" class="fc"
                                  placeholder="Street, city, area (optional)"><?= isset($_POST['address']) && $error ? htmlspecialchars($_POST['address']) : '' ?></textarea>
                    </div>

                    <div class="form-full">
                        <label class="lbl">Manufacturer</label>
                        <input type="text" name="manufacturer" class="fc"
                               placeholder="Enter manufacturer name (optional)"
                               value="<?= isset($_POST['manufacturer']) && $error ? htmlspecialchars($_POST['manufacturer']) : '' ?>">
                    </div>

                </div>

                <div class="form-footer">
                    <button type="submit" class="btn-pos btn-green">
                        <i class="fas fa-plus" style="font-size:.65rem;"></i> Create Customer
                    </button>
                    <button type="reset" class="btn-pos btn-ghost">
                        <i class="fas fa-rotate-left" style="font-size:.65rem;"></i> Clear
                    </button>
                    <a href="customers_list.php" class="btn-pos btn-ghost" style="margin-left:auto;">
                        <i class="fas fa-list" style="font-size:.65rem;"></i> View Customers
                    </a>
                </div>
            </form>
        </div>

    </div><!-- /main -->
</div><!-- /page-shell -->

</body>
</html>