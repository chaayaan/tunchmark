<?php
require 'auth.php';
require 'mydb.php';
require_engineering();               // admin + engineer employee only.
// To restrict this page to administrators only, replace the line above with:  require_admin();

if (isset($conn)) { $conn->set_charset('utf8mb4'); }
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['csrf_mr'])) $_SESSION['csrf_mr'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_mr'];

$errors = [];
$flashMap = ['added' => 'Action added.', 'updated' => 'Action updated. Existing reports keep their original wording.',
             'activated' => 'Action activated — it now appears in the report form.',
             'deactivated' => 'Action deactivated — hidden from new reports; existing reports are unchanged.'];

// ---------- POST handlers ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Invalid or expired form token. Please go back, refresh the page and try again.');
    }
    $op   = $_POST['op'] ?? '';
    $id   = (int)($_POST['id'] ?? 0);
    $text = trim((string)($_POST['action_text'] ?? ''));
    $sort = trim((string)($_POST['sort_order'] ?? ''));

    try {
        if ($op === 'toggle') {
            if ($id <= 0) throw new RuntimeException('Invalid action.');
            $stmt = $conn->prepare("UPDATE machines_report_action_list SET is_active = 1 - is_active WHERE id = ?");
            $stmt->bind_param("i", $id); $stmt->execute();
            $ok = $stmt->affected_rows > 0; $stmt->close();
            $st = $conn->prepare("SELECT is_active FROM machines_report_action_list WHERE id = ?");
            $st->bind_param("i", $id); $st->execute();
            $row = $st->get_result()->fetch_assoc(); $st->close();
            if (!$row) throw new RuntimeException('Action not found.');
            header("Location: machines_report_action_list.php?ok=" . ((int)$row['is_active'] ? 'activated' : 'deactivated')); exit;
        }

        if (in_array($op, ['add', 'edit'], true)) {
            if ($text === '')                  $errors[] = "Action text is required.";
            elseif (mb_strlen($text) < 3)      $errors[] = "Action text must be at least 3 characters.";
            elseif (mb_strlen($text) > 255)    $errors[] = "Action text must be at most 255 characters.";
            if ($sort !== '' && (!ctype_digit($sort) || (int)$sort > 9999)) $errors[] = "Order must be a whole number between 0 and 9999.";
            if ($op === 'edit' && $id <= 0)    $errors[] = "Invalid action.";

            if (!$errors) {                    // duplicate check
                $stmt = $conn->prepare("SELECT id FROM machines_report_action_list WHERE action_text = ? AND id <> ? LIMIT 1");
                $stmt->bind_param("si", $text, $id); $stmt->execute();
                if ($stmt->get_result()->num_rows) $errors[] = "That action already exists.";
                $stmt->close();
            }
            if (!$errors) {
                if ($op === 'add') {
                    if ($sort === '') $so = (int)$conn->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM machines_report_action_list")->fetch_row()[0];
                    else $so = (int)$sort;
                    $stmt = $conn->prepare("INSERT INTO machines_report_action_list (action_text, sort_order, is_active) VALUES (?, ?, 1)");
                    $stmt->bind_param("si", $text, $so); $stmt->execute(); $stmt->close();
                    header("Location: machines_report_action_list.php?ok=added"); exit;
                }
                $so = $sort === '' ? 0 : (int)$sort;
                $stmt = $conn->prepare("UPDATE machines_report_action_list SET action_text = ?, sort_order = ? WHERE id = ?");
                $stmt->bind_param("sii", $text, $so, $id); $stmt->execute(); $stmt->close();
                header("Location: machines_report_action_list.php?ok=updated"); exit;
            }
        }
    } catch (Throwable $e) {
        error_log('machines_report_action_list: ' . $e->getMessage());
        $errors[] = "Could not complete the request. Please try again.";
    }
}

$flash = $flashMap[$_GET['ok'] ?? ''] ?? '';

$actions = $conn->query(
    "SELECT l.*, (SELECT COUNT(*) FROM machines_report_action a WHERE a.action_text = l.action_text AND a.is_other = 0) AS used_count
     FROM machines_report_action_list l ORDER BY l.is_active DESC, l.sort_order, l.id"
)->fetch_all(MYSQLI_ASSOC);
$activeCount = count(array_filter($actions, fn($a) => (int)$a['is_active'] === 1));
$reopen = $errors && in_array($_POST['op'] ?? '', ['add', 'edit'], true) ? $_POST['op'] : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Report Action List — Rajaiswari</title>
  <link rel="icon" type="image/png" href="favicon.png">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root{--bg:#f1f3f6;--surface:#fff;--surface-2:#fafbfc;--border:#e4e7ec;--bsoft:#f0f1f3;--t1:#111827;--t2:#374151;--t3:#6b7280;--t4:#9ca3af;
      --blue:#2563eb;--blue-bg:#eff6ff;--blue-b:#bfdbfe;--green:#059669;--green-bg:#ecfdf5;--green-b:#a7f3d0;--amber:#d97706;--amber-bg:#fffbeb;--amber-b:#fde68a;
      --red:#dc2626;--red-bg:#fef2f2;--red-b:#fecaca;--teal:#0f766e;--teal-bg:#f0fdfa;--r:10px;--rs:6px;--sh:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);}
    body{font-family:'DM Sans',-apple-system,sans-serif;font-size:14.5px;background:var(--bg);color:var(--t1);-webkit-font-smoothing:antialiased;min-height:100vh;}
    .page-shell{margin-left:200px;min-height:100vh;display:flex;flex-direction:column;}
    .top-bar{position:sticky;top:0;z-index:200;min-height:54px;background:var(--surface);border-bottom:1px solid var(--border);box-shadow:var(--sh);display:flex;align-items:center;flex-wrap:wrap;padding:6px 22px;gap:10px;}
    .tb-ico{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;background:var(--teal-bg);color:var(--teal);}
    .tb-title{font-size:1.0625rem;font-weight:700;line-height:1.2;}.tb-sub{font-size:.8rem;color:var(--t4);}
    .tb-right{margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;}
    .btn-add{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 16px;background:var(--green);border:none;border-radius:var(--rs);font-size:.875rem;font-weight:700;color:#fff;}
    .btn-add:hover{background:#047857;color:#fff;}
    .btn-soft{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 14px;background:var(--surface-2);border:1.5px solid var(--border);border-radius:var(--rs);font-size:.82rem;font-weight:600;color:var(--t2);text-decoration:none;}
    .btn-soft:hover{background:var(--border);color:var(--t1);}
    .main{flex:1;padding:20px 22px 60px;display:flex;flex-direction:column;gap:14px;max-width:1000px;width:100%;margin:0 auto;}
    .sec{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;}
    .lbl{display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t3);margin-bottom:4px;}
    .form-control{font-size:.9rem;border:1.5px solid var(--border);border-radius:var(--rs);min-height:38px;}
    .form-control:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
    .alert-flash{border-radius:var(--rs);padding:12px 16px;font-size:.875rem;display:flex;align-items:center;gap:9px;background:var(--green-bg);border:1px solid var(--green-b);border-left:3px solid var(--green);color:#065f46;}
    .alert-err{background:var(--red-bg);border:1px solid var(--red-b);border-left:3px solid var(--red);border-radius:var(--rs);padding:13px 16px;}
    .alert-err-title{font-size:.875rem;font-weight:700;color:var(--red);margin-bottom:6px;}.alert-err ul{margin:0;padding-left:16px;}.alert-err li{font-size:.82rem;color:var(--red);}
    .note{background:var(--blue-bg);border:1px solid var(--blue-b);border-left:3px solid var(--blue);border-radius:var(--rs);padding:10px 14px;font-size:.82rem;color:#1e3a8a;}
    .utbl{width:100%;border-collapse:collapse;margin:0;}
    .utbl thead th{padding:10px 14px;text-align:left;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--t4);background:var(--surface-2);border-bottom:1px solid var(--border);white-space:nowrap;}
    .utbl tbody td{padding:11px 14px;border-bottom:1px solid var(--bsoft);vertical-align:middle;font-size:.875rem;}
    .utbl tbody tr:last-child td{border-bottom:none;}.utbl tbody tr.off td{background:#fafafa;color:var(--t4);}
    .mono{font-family:'DM Mono',monospace;font-size:.82rem;}
    .pill{display:inline-flex;align-items:center;gap:5px;padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:700;border:1px solid;}
    .pill-on{background:var(--green-bg);color:var(--green);border-color:var(--green-b);}.pill-off{background:#f3f4f6;color:var(--t3);border-color:var(--border);}
    .btn-act{display:inline-flex;align-items:center;gap:5px;height:30px;padding:0 12px;border-radius:var(--rs);font-size:.78rem;font-weight:700;border:1.5px solid;background:#fff;}
    .btn-edit{background:var(--amber-bg);color:#92400e;border-color:var(--amber-b);}.btn-edit:hover{background:var(--amber);color:#fff;}
    .btn-off{background:var(--red-bg);color:#991b1b;border-color:var(--red-b);}.btn-off:hover{background:var(--red);color:#fff;}
    .btn-on{background:var(--green-bg);color:#065f46;border-color:var(--green-b);}.btn-on:hover{background:var(--green);color:#fff;}
    @media(max-width:991.98px){.page-shell{margin-left:0;padding-top:52px;}.top-bar{top:52px;padding:8px 14px;}.main{padding:14px 14px 50px;}}
    @media(max-width:767.98px){
      .utbl thead{display:none;}.utbl,.utbl tbody,.utbl tr,.utbl td{display:block;width:100%;}
      .utbl tbody tr{border-bottom:8px solid var(--bg);padding:6px 0;}
      .utbl tbody td{border:none;padding:5px 14px;display:flex;gap:10px;justify-content:space-between;text-align:right;}
      .utbl tbody td::before{content:attr(data-label);font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t4);text-align:left;flex-shrink:0;}
      .utbl tbody td.td-act{justify-content:flex-end;padding-bottom:10px;}.utbl tbody td.td-act::before{display:none;}
      .utbl tbody td.td-text{text-align:left;}.btn-act{height:36px;flex:1;justify-content:center;}
    }
  </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page-shell">
  <header class="top-bar">
    <div class="tb-ico"><i class="fas fa-list-check"></i></div>
    <div><div class="tb-title">Report Action List</div><div class="tb-sub"><?= $activeCount ?> active · <?= count($actions) - $activeCount ?> inactive</div></div>
    <div class="tb-right">
      <a href="machines_report_list.php" class="btn-soft"><i class="fas fa-arrow-left"></i> Reports</a>
      <button type="button" class="btn-add" id="btnAdd"><i class="fas fa-plus"></i> Add Action</button>
    </div>
  </header>

  <main class="main">
    <?php if ($flash): ?><div class="alert-flash"><i class="fas fa-circle-check"></i><?= h($flash) ?></div><?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert-err"><div class="alert-err-title"><i class="fas fa-circle-exclamation me-1"></i>Please fix the following:</div>
      <ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <div class="note"><i class="fas fa-circle-info me-1"></i>Active actions appear as checkboxes in the report form. Reports store their own copy of each ticked action, so editing or deactivating an action here never changes existing reports or PDFs.</div>

    <div class="sec">
      <?php if (!$actions): ?>
        <div class="text-center text-muted p-5">No actions yet. Click <b>Add Action</b> to create the first one.</div>
      <?php else: ?>
      <table class="utbl">
        <thead><tr><th style="width:70px">Order</th><th>Action</th><th>Status</th><th>Used in</th><th style="text-align:right">Manage</th></tr></thead>
        <tbody>
        <?php foreach ($actions as $a): $on = (int)$a['is_active'] === 1; ?>
          <tr class="<?= $on ? '' : 'off' ?>">
            <td data-label="Order" class="mono"><?= (int)$a['sort_order'] ?></td>
            <td data-label="Action" class="td-text"><?= h($a['action_text']) ?></td>
            <td data-label="Status"><span class="pill <?= $on ? 'pill-on' : 'pill-off' ?>"><?= $on ? 'Active' : 'Inactive' ?></span></td>
            <td data-label="Used in" class="text-muted"><?= (int)$a['used_count'] ?> report<?= (int)$a['used_count'] === 1 ? '' : 's' ?></td>
            <td class="td-act" style="text-align:right;white-space:nowrap;">
              <div class="d-flex gap-2 justify-content-end">
                <button type="button" class="btn-act btn-edit js-edit" data-id="<?= (int)$a['id'] ?>" data-text="<?= h($a['action_text']) ?>" data-sort="<?= (int)$a['sort_order'] ?>"><i class="fas fa-pen"></i> Edit</button>
                <form method="post" class="m-0">
                  <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="op" value="toggle"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                  <button class="btn-act <?= $on ? 'btn-off' : 'btn-on' ?>"><i class="fas fa-<?= $on ? 'eye-slash' : 'eye' ?>"></i> <?= $on ? 'Deactivate' : 'Activate' ?></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- Add / Edit modal -->
<div class="modal fade" id="actModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="op" id="mOp" value="add">
      <input type="hidden" name="id" id="mId" value="0">
      <div class="modal-header"><h5 class="modal-title fs-6 fw-bold" id="mTitle">Add Action</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="lbl" for="mText">Action Text</label>
          <textarea class="form-control" name="action_text" id="mText" rows="3" maxlength="255" required><?= h($_POST['action_text'] ?? '') ?></textarea></div>
        <div><label class="lbl" for="mSort">Display Order</label>
          <input type="number" class="form-control" name="sort_order" id="mSort" min="0" max="9999" placeholder="Leave blank to add at the end" value="<?= h($_POST['sort_order'] ?? '') ?>"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success fw-bold" id="mSave"><i class="fas fa-check"></i> Save</button></div>
    </form>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  var modalEl = document.getElementById('actModal'), modal = new bootstrap.Modal(modalEl);
  var $ = function (id) { return document.getElementById(id); };
  function openAdd() { $('mOp').value = 'add'; $('mId').value = 0; $('mTitle').textContent = 'Add Action'; $('mText').value = ''; $('mSort').value = ''; modal.show(); }
  function openEdit(b) { $('mOp').value = 'edit'; $('mId').value = b.dataset.id; $('mTitle').textContent = 'Edit Action'; $('mText').value = b.dataset.text; $('mSort').value = b.dataset.sort; modal.show(); }
  $('btnAdd').addEventListener('click', openAdd);
  document.querySelectorAll('.js-edit').forEach(function (b) { b.addEventListener('click', function () { openEdit(b); }); });
  modalEl.addEventListener('shown.bs.modal', function () { $('mText').focus(); });
<?php if ($reopen): ?>
  $('mOp').value = <?= json_encode($reopen) ?>; $('mId').value = <?= (int)($_POST['id'] ?? 0) ?>;
  $('mTitle').textContent = <?= json_encode($reopen === 'edit' ? 'Edit Action' : 'Add Action') ?>; modal.show();
<?php endif; ?>
})();
</script>
</body>
</html>
