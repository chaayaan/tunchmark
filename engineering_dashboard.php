<?php
require 'auth.php';
require 'mydb.php';
require_engineering();               // admin + engineer employee only (server-side, re-checked against DB)

if (!function_exists('h')) { function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

// Navigation only: no queries, no counts, no charts.
$cards = [
    ['machines_report_list.php',        'fa-screwdriver-wrench',   'Service/Installation Report', 'Installation and service reports', 'violet'],
    ['machines_billing_list.php',       'fa-file-invoice-dollar',  'Annual Maintenance Schedule Bills Report', 'Annual maintenance bills', 'teal'],
    ['quotation_list.php',              'fa-file-signature',       'Quotations',       'Machine and software quotations',         'blue'],
    ['conveyance_list.php',             'fa-route',                'Conveyance Bills', 'Engineer travel and conveyance',          'amber'],
    ['gold_labs_list.php',              'fa-flask-vial',           'Gold Testing Labs','Client laboratories: add, edit, history', 'blue'],
    ['gold_lab_machines.php',           'fa-microchip',            'Lab Machines',     'Machines installed at each laboratory',   'red'],
    ['gold_lab_history.php',            'fa-clock-rotate-left',    'All Labs History', 'Every report and bill of every laboratory', 'violet'],
    ['machines_report_action_list.php', 'fa-list-check',           'Report Actions',   'Action checklist used in reports',        'green'],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#0f766e">
  <link rel="icon" type="image/png" href="favicon.png">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <title>Engineering — Rajaiswari</title>
  <style>
    :root{--bg:#f1f3f6;--surface:#fff;--surface-2:#fafbfc;--border:#e4e7ec;--bsoft:#f0f1f3;--t1:#111827;--t2:#374151;--t3:#6b7280;--t4:#9ca3af;
      --blue:#2563eb;--blue-bg:#eff6ff;--blue-b:#bfdbfe;--green:#059669;--green-bg:#ecfdf5;--green-b:#a7f3d0;--amber:#d97706;--amber-bg:#fffbeb;--amber-b:#fde68a;
      --red:#dc2626;--red-bg:#fef2f2;--red-b:#fecaca;--violet:#7c3aed;--violet-bg:#f5f3ff;--violet-b:#ddd6fe;--teal:#0f766e;--teal-bg:#f0fdfa;--teal-b:#99f6e4;
      --gray-bg:#f3f4f6;--gray-b:#d1d5db;--r:10px;--rs:6px;--sh:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);}
    body{font-family:'DM Sans',-apple-system,sans-serif;font-size:14.5px;background:var(--bg);color:var(--t1);-webkit-font-smoothing:antialiased;min-height:100vh;}
    .page-shell{margin-left:200px;min-height:100vh;display:flex;flex-direction:column;}
    .top-bar{position:sticky;top:0;z-index:200;min-height:54px;background:var(--surface);border-bottom:1px solid var(--border);box-shadow:var(--sh);display:flex;align-items:center;flex-wrap:wrap;padding:6px 22px;gap:10px;}
    .tb-ico{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;background:var(--teal-bg);color:var(--teal);}
    .tb-title{font-size:1.0625rem;font-weight:700;line-height:1.2;}.tb-sub{font-size:.8rem;color:var(--t4);}
    .tb-right{margin-left:auto;display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
    .btn-add{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 16px;background:var(--green);border:none;border-radius:var(--rs);font-size:.875rem;font-weight:700;color:#fff;text-decoration:none;cursor:pointer;}
    .btn-add:hover{background:#047857;color:#fff;}
    .btn-soft{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 14px;background:var(--surface-2);border:1.5px solid var(--border);border-radius:var(--rs);font-size:.82rem;font-weight:600;color:var(--t2);text-decoration:none;cursor:pointer;}
    .btn-soft:hover{background:var(--border);color:var(--t1);}
    .main{flex:1;padding:20px 22px 60px;display:flex;flex-direction:column;gap:14px;}
    .sec{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;}
    .sec-head{display:flex;align-items:center;gap:9px;padding:12px 18px;background:var(--surface-2);border-bottom:1px solid var(--bsoft);flex-wrap:wrap;}
    .sec-ico{width:28px;height:28px;border-radius:var(--rs);display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;}
    .i-blue{background:var(--blue-bg);color:var(--blue);}.i-violet{background:var(--violet-bg);color:var(--violet);}.i-amber{background:var(--amber-bg);color:var(--amber);}
    .i-green{background:var(--green-bg);color:var(--green);}.i-red{background:var(--red-bg);color:var(--red);}.i-teal{background:var(--teal-bg);color:var(--teal);}
    .sec-title{font-size:.9375rem;font-weight:700;}.sec-meta{margin-left:auto;display:flex;gap:8px;align-items:center;font-size:.78rem;color:var(--t4);}
    .sec-body{padding:16px 18px;}
    .lbl{display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t3);margin-bottom:4px;}
    .lbl .req{color:var(--red);margin-left:2px;}
    .form-control,.form-select{font-size:.9rem;border:1.5px solid var(--border);border-radius:var(--rs);min-height:38px;}
    textarea.form-control{min-height:auto;}
    .form-control:focus,.form-select:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
    .alert-flash{border-radius:var(--rs);padding:12px 16px;font-size:.875rem;display:flex;align-items:center;gap:9px;}
    .af-success{background:var(--green-bg);border:1px solid var(--green-b);border-left:3px solid var(--green);color:#065f46;}
    .af-danger{background:var(--red-bg);border:1px solid var(--red-b);border-left:3px solid var(--red);color:#7f1d1d;}
    .alert-err{background:var(--red-bg);border:1px solid var(--red-b);border-left:3px solid var(--red);border-radius:var(--rs);padding:11px 14px;margin-bottom:14px;}
    .alert-err-title{font-size:.85rem;font-weight:700;color:var(--red);margin-bottom:4px;}
    .alert-err ul{margin:0;padding-left:16px;}.alert-err li{font-size:.82rem;color:var(--red);}
    .utbl{width:100%;border-collapse:collapse;margin:0;}
    .utbl thead th{padding:10px 14px;text-align:left;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--t4);background:var(--surface-2);border-bottom:1px solid var(--border);white-space:nowrap;}
    .utbl tbody td{padding:11px 14px;border-bottom:1px solid var(--bsoft);vertical-align:middle;font-size:.875rem;}
    .utbl tbody tr:last-child td{border-bottom:none;}.utbl tbody tr:hover td{background:#fafbff;}
    .mono{font-family:'DM Mono',monospace;font-size:.82rem;}
    .muted{color:var(--t4);font-size:.78rem;}
    .pill{display:inline-flex;align-items:center;gap:5px;padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:700;border:1px solid;white-space:nowrap;}
    .pill-installation{background:var(--violet-bg);color:var(--violet);border-color:var(--violet-b);}
    .pill-service{background:var(--blue-bg);color:var(--blue);border-color:var(--blue-b);}
    .pill-billing{background:var(--teal-bg);color:var(--teal);border-color:var(--teal-b);}
    .pill-other{background:var(--gray-bg);color:var(--t2);border-color:var(--gray-b);}
    .pill-count{background:var(--surface-2);color:var(--t2);border-color:var(--border);}
    .btn-act{display:inline-flex;align-items:center;gap:5px;height:30px;padding:0 12px;border-radius:var(--rs);font-size:.78rem;font-weight:700;text-decoration:none;border:1.5px solid;background:none;cursor:pointer;white-space:nowrap;}
    .btn-edit{background:var(--amber-bg);color:#92400e;border-color:var(--amber-b);}.btn-edit:hover{background:var(--amber);color:#fff;border-color:var(--amber);}
    .btn-pdf{background:var(--red-bg);color:#991b1b;border-color:var(--red-b);}.btn-pdf:hover{background:var(--red);color:#fff;border-color:var(--red);}
    .btn-view{background:var(--blue-bg);color:#1d4ed8;border-color:var(--blue-b);}.btn-view:hover{background:var(--blue);color:#fff;border-color:var(--blue);}
    .btn-mach{background:var(--teal-bg);color:var(--teal);border-color:var(--teal-b);}.btn-mach:hover{background:var(--teal);color:#fff;border-color:var(--teal);}
    .btn-hist{background:var(--violet-bg);color:var(--violet);border-color:var(--violet-b);}.btn-hist:hover{background:var(--violet);color:#fff;border-color:var(--violet);}
    .empty{text-align:center;padding:48px 16px;color:var(--t4);}.empty i{font-size:2rem;margin-bottom:10px;display:block;}
    .pager{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;}
    .pager .info{font-size:.82rem;color:var(--t3);}
    .btn-pg{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 14px;border:1.5px solid var(--border);border-radius:var(--rs);background:var(--surface);font-size:.82rem;font-weight:600;color:var(--t2);text-decoration:none;}
    .btn-pg:hover{background:var(--surface-2);color:var(--t1);}.btn-pg.disabled{opacity:.45;pointer-events:none;}
    .kv{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px 18px;}
    .kv .k{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t4);margin-bottom:2px;}
    .kv .v{font-size:.9rem;color:var(--t1);word-break:break-word;}.kv .wide{grid-column:1/-1;}
    .modal-header{background:var(--surface-2);}.modal-title{font-size:1rem;font-weight:700;}
    .modal-footer{background:var(--surface-2);}
    .btn-submit{display:inline-flex;align-items:center;gap:7px;height:40px;padding:0 22px;border:none;border-radius:7px;font-weight:700;color:#fff;background:var(--green);}
    .btn-submit.edit{background:var(--amber);}.btn-submit:hover{filter:brightness(.92);}
    .btn-ghost{display:inline-flex;align-items:center;gap:6px;height:40px;padding:0 18px;background:var(--surface);border:1.5px solid var(--border);border-radius:7px;font-weight:500;color:var(--t2);}
    .btn-ghost:hover{background:var(--surface-2);color:var(--t1);}
    @media(max-width:991.98px){.page-shell{margin-left:0;padding-top:52px;}.top-bar{top:52px;padding:8px 14px;}.main{padding:14px 14px 50px;}}
    @media(max-width:767.98px){
      .kv{grid-template-columns:1fr 1fr;}
      .utbl thead{display:none;}
      .utbl,.utbl tbody,.utbl tr,.utbl td{display:block;width:100%;}
      .utbl tbody tr{border-bottom:8px solid var(--bg);padding:6px 0;}
      .utbl tbody td{border:none;padding:5px 14px;display:flex;gap:10px;justify-content:space-between;text-align:right;}
      .utbl tbody td::before{content:attr(data-label);font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--t4);text-align:left;flex-shrink:0;padding-top:2px;}
      .utbl tbody td.td-act{justify-content:flex-end;padding-bottom:10px;}.utbl tbody td.td-act::before{display:none;}
      .btn-act{height:36px;flex:1;justify-content:center;}.td-act{gap:8px!important;}.td-act>div{width:100%;}
    }
    .dgrid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;}
    .dcard{display:flex;align-items:center;gap:16px;padding:22px 20px;background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);text-decoration:none;color:var(--t1);transition:transform .15s,box-shadow .15s,border-color .15s;}
    .dcard:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(0,0,0,.08);border-color:#cbd5e1;color:var(--t1);}
    .dcard .d-ico{width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
    .dcard .d-t{font-weight:700;font-size:1rem;}.dcard .d-s{font-size:.8rem;color:var(--t3);margin-top:2px;}
    .dcard .d-go{margin-left:auto;color:var(--t4);}
    @media(max-width:1199.98px){.dgrid{grid-template-columns:repeat(2,minmax(0,1fr));}}
    @media(max-width:575.98px){.dgrid{grid-template-columns:1fr;}}

    /* ===== Mobile: native-app style list (phones) — desktop view above is untouched ===== */
    @media(max-width:767.98px){
      body{-webkit-tap-highlight-color:transparent;}
      .top-bar{min-height:50px;padding:6px 14px;}
      .tb-title{font-size:1rem;}
      .main{padding:14px 14px calc(30px + env(safe-area-inset-bottom));gap:10px;}
      .dgrid{grid-template-columns:1fr;gap:10px;}
      .dcard{gap:14px;padding:14px 16px;min-height:72px;border:0;border-radius:16px;box-shadow:0 1px 4px rgba(0,0,0,.1);touch-action:manipulation;transition:transform .1s,background .1s;}
      .dcard:hover{transform:none;box-shadow:0 1px 4px rgba(0,0,0,.1);}
      .dcard:active{transform:scale(.98);background:#f8fafc;}
      .dcard .d-ico{width:46px;height:46px;border-radius:14px;font-size:19px;}
      .dcard .d-text{min-width:0;flex:1;}
      .dcard .d-t{font-size:.98rem;line-height:1.25;}
      .dcard .d-s{font-size:.78rem;line-height:1.3;}
      .dcard .d-go{font-size:.8rem;}
    }
  </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page-shell">
  <header class="top-bar">
    <div class="tb-ico"><i class="fas fa-helmet-safety"></i></div>
    <div><div class="tb-title">Engineering</div><div class="tb-sub">Choose a section</div></div>
  </header>
  <main class="main">
    <div class="dgrid">
      <?php foreach ($cards as [$href, $icon, $title, $sub, $tone]): ?>
        <a class="dcard" href="<?= h($href) ?>">
          <div class="d-ico i-<?= h($tone) ?>"><i class="fas <?= h($icon) ?>"></i></div>
          <div class="d-text"><div class="d-t"><?= h($title) ?></div><div class="d-s"><?= h($sub) ?></div></div>
          <i class="fas fa-chevron-right d-go"></i>
        </a>
      <?php endforeach; ?>
    </div>
  </main>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>