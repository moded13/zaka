<?php
require_once 'bootstrap.php';
$flash = function_exists('get_flash') ? get_flash() : null;

// active nav helper
$curr = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
$isActive = fn(string $f) => $curr === $f;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?= isset($page_title) ? e($page_title) . ' | الإدارة المالية' : 'الإدارة المالية' ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        :root {
            --primary: #163d7a;
            --primary-2: #1f4f9a;
            --secondary: #5b6b88;
            --success: #118a7e;
            --danger: #d63031;
            --warning: #f39c12;
            --info: #2d6cdf;
            --dark: #233142;
            --muted: #6c7a92;
            --bg: #f4f7fb;
            --card: #ffffff;
            --border: #e6ebf2;
            --table-head: #f0f4fa;
            --shadow: 0 10px 30px rgba(27, 39, 94, 0.08);
            --shadow-2: 0 18px 50px rgba(27,39,94,.10);
            --radius: 18px;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            direction: rtl;
            max-width: 100%;
            overflow-x: hidden;
        }

        body {
            font-family: Tahoma, Arial, sans-serif;
            background:
                radial-gradient(circle at top right, rgba(45,108,223,0.08), transparent 28%),
                radial-gradient(circle at top left, rgba(17,138,126,0.06), transparent 25%),
                var(--bg);
            color: #1f2937;
            line-height: 1.7;
        }

        a { text-decoration: none; }

        .container {
            width: min(96%, 1360px);
            margin: 18px auto 40px;
            max-width: 100%;
        }

        .topbar {
            background: linear-gradient(135deg, var(--primary), var(--primary-2));
            color: #fff;
            border-radius: 24px;
            padding: 22px 24px 18px;
            margin-bottom: 18px;
            box-shadow: var(--shadow-2);
            position: relative;
            overflow: hidden;
        }

        .topbar::before {
            content: "";
            position: absolute;
            left: -40px;
            top: -40px;
            width: 180px;
            height: 180px;
            background: rgba(255,255,255,0.07);
            border-radius: 50%;
        }

        .topbar::after {
            content: "";
            position: absolute;
            left: 120px;
            bottom: -60px;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }

        .topbar-header {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .brand h1 {
            margin: 0;
            font-size: 34px;
            font-weight: 800;
            line-height: 1.15;
        }

        .brand p {
            margin: 6px 0 0;
            color: rgba(255,255,255,0.86);
            font-size: 14px;
        }

        .topbar-badge {
            position: relative;
            z-index: 1;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.18);
            color: #fff;
            padding: 10px 14px;
            border-radius: 999px;
            font-size: 13px;
            backdrop-filter: blur(6px);
            display:flex;
            align-items:center;
            gap:8px;
            white-space: nowrap;
        }

        .topbar-badge .dot{
            width:8px;height:8px;border-radius:50%;
            background: rgba(255,255,255,.9);
            opacity:.85;
        }

        .nav {
            position: relative;
            z-index: 1;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .nav a {
            color: #fff;
            background: rgba(255,255,255,0.13);
            border: 1px solid rgba(255,255,255,0.14);
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 14px;
            transition: 0.2s ease;
            backdrop-filter: blur(6px);
            display:inline-flex;
            align-items:center;
            gap:8px;
            white-space: nowrap;
        }

        .nav a:hover { transform: translateY(-1px); background: rgba(255,255,255,0.2); }

        .nav a.active {
            background: rgba(255,255,255,0.22);
            border-color: rgba(255,255,255,0.30);
            font-weight: 800;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 18px;
            margin-bottom: 18px;
            box-shadow: var(--shadow);
            max-width: 100%;
        }

        .card h2 {
            margin: 0 0 14px;
            font-size: 26px;
            line-height: 1.3;
            color: var(--primary);
        }

        .card h3 { margin: 0 0 10px; font-size: 18px; color: var(--dark); }

        .section-subtitle {
            color: var(--muted);
            margin-top: -8px;
            margin-bottom: 14px;
            font-size: 13px;
        }

        .grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 14px; }
        .col-12{ grid-column: span 12; }
        .col-8{ grid-column: span 8; }
        .col-6{ grid-column: span 6; }
        .col-4{ grid-column: span 4; }
        .col-3{ grid-column: span 3; }

        .stat {
            border-radius: 18px;
            padding: 18px 18px;
            color: #fff;
            position: relative;
            overflow: hidden;
            min-height: 108px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            box-shadow: var(--shadow);
        }
        .stat::after {
            content: "";
            position: absolute;
            left: -25px;
            bottom: -25px;
            width: 95px;
            height: 95px;
            border-radius: 50%;
            background: rgba(255,255,255,0.09);
        }
        .stat .label { font-size: 14px; opacity: .95; margin-bottom: 6px; }
        .stat .value { font-size: 28px; font-weight: 800; }
        .stat .sub { margin-top: 6px; font-size: 12px; opacity: .92; }

        .stat.blue { background: linear-gradient(135deg, #2d6cdf, #1d5ac8); }
        .stat.green { background: linear-gradient(135deg, #118a7e, #0e6f66); }
        .stat.red { background: linear-gradient(135deg, #d63031, #b82628); }
        .stat.dark { background: linear-gradient(135deg, #31475e, #233142); }
        .stat.orange { background: linear-gradient(135deg, #f39c12, #d68910); }

        label { display:block; margin-bottom:8px; font-weight:800; color:#24344d; font-size:14px; }
        input[type="text"], input[type="number"], input[type="date"], select, textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #dbe2ec;
            border-radius: 12px;
            background: #fff;
            color: #24344d;
            font-size: 14px;
            outline: none;
            transition: 0.2s ease;
        }
        input:focus, select:focus, textarea:focus {
            border-color: #8eb3f8;
            box-shadow: 0 0 0 4px rgba(45,108,223,0.12);
        }
        textarea { min-height: 110px; resize: vertical; }

        .form-actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:10px; justify-content:flex-end; }

        .btn {
            display:inline-flex; align-items:center; justify-content:center; gap:8px;
            border:none; border-radius:12px; padding:10px 14px;
            font-size:14px; cursor:pointer; transition:.2s ease; white-space:nowrap;
        }
        .btn:hover { transform: translateY(-1px); filter: brightness(0.98); }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-light { background: #eef3fb; color: var(--primary); border: 1px solid #dbe5f3; }

        .flash {
            padding: 14px 16px; border-radius: 14px; margin-bottom: 16px;
            font-size: 14px; border: 1px solid transparent; box-shadow: var(--shadow);
        }
        .flash.success { background: #e5f7ef; color: #146c43; border-color: #c8ead8; }
        .flash.error { background: #fdeaea; color: #b02a37; border-color: #f2c9cc; }
        .flash.warning { background: #fff6df; color: #946200; border-color: #f1dfab; }
        .flash.info { background: #eaf2ff; color: #184eaa; border-color: #cbddff; }

        .table-wrap {
            overflow-x: auto; border-radius: 14px; max-width: 100%;
            -webkit-overflow-scrolling: touch;
            border: 1px solid #edf1f6;
        }
        table { width:100%; border-collapse:collapse; background:#fff; min-width: 820px; }
        table th, table td {
            padding: 10px 10px; text-align:center; border-bottom:1px solid #edf1f6;
            vertical-align:middle; white-space:nowrap;
        }
        table th {
            background: var(--table-head); color: var(--primary); font-size: 14px; font-weight: 800;
            position: sticky; top: 0; z-index: 2;
        }
        td.wrap, th.wrap { white-space: normal; }
        .empty-state { text-align:center; padding:24px 14px; color:var(--muted); font-size:14px; }

        .badge {
            display:inline-flex; align-items:center; justify-content:center;
            padding: 6px 10px; border-radius: 999px; font-size: 13px; font-weight: 800;
        }
        .badge-success { background:#dff5ea; color:#0e6d52; }
        .badge-danger { background:#fde7e7; color:#bb2d2d; }
        .badge-info { background:#e7f0ff; color:#1d5ac8; }

        .muted { color: var(--muted); font-size: 13px; }
        .footer-note { text-align:center; color: var(--muted); font-size: 13px; margin-top: 10px; }
        .divider { height: 1px; background: linear-gradient(to left, transparent, #dbe3ef, transparent); margin: 18px 0; }

        @media (max-width: 1024px) { .col-8,.col-6,.col-4,.col-3{ grid-column: span 12; } }
        @media (max-width: 768px) {
            .container { width: 94%; margin-top: 14px; }
            .topbar { padding: 18px 16px 16px; border-radius: 18px; }
            .brand h1 { font-size: 28px; }
            .card { padding: 14px; border-radius: 16px; }
            .card h2 { font-size: 22px; }
            .stat .value { font-size: 24px; }
            .nav a { padding: 9px 10px; font-size: 13px; border-radius: 12px; }
        }

        @media print {
            body { background:#fff !important; }
            .topbar, .flash { display:none !important; }
            .container { width:100%; max-width:100%; margin:0; }
            .card { border:1px solid #ccc; box-shadow:none !important; margin-bottom:14px; break-inside:avoid; }
            .btn { display:none !important; }
            table th, table td { border: 1px solid #aaa !important; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="topbar">
        <div class="topbar-header">
            <div class="brand">
                <h1>الإدارة المالية</h1>
                <p>واجهة ERP مبسطة لإدارة الإيرادات والمدفوعات ودفاتر الوصولات والتقارير</p>
            </div>
            <div class="topbar-badge">
                <span class="dot"></span>
                <?= e(date('Y-m-d')) ?>
            </div>
        </div>

        <div class="nav">
            <a class="<?= $isActive('index.php') ? 'active':'' ?>" href="index.php">الرئيسية</a>
            <a class="<?= $isActive('income.php') ? 'active':'' ?>" href="income.php">دفتر الإيرادات</a>
            <a class="<?= $isActive('expenses.php') ? 'active':'' ?>" href="expenses.php">دفتر المدفوعات</a>

            <!-- ✅ دفاتر الوصولات + إدخال وصل -->
            <a class="<?= $isActive('receipt_books.php') ? 'active':'' ?>" href="receipt_books.php">دفاتر الوصولات</a>
            <a class="<?= $isActive('income_entry.php') ? 'active':'' ?>" href="income_entry.php">إدخال وصل</a>

            <a class="<?= $isActive('report.php') ? 'active':'' ?>" href="report.php">التقرير المالي</a>
            <a href="<?= e(ADMIN_PATH) ?>/dashboard.php" style="background:rgba(255,255,255,0.08);">← لوحة الإدارة</a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="flash <?= e($flash['type']) ?>">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>