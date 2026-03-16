<?php
require_once 'bootstrap.php';
$flash = get_flash();
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
            --radius: 18px;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            direction: rtl;
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

        a {
            text-decoration: none;
        }

        .container {
            width: min(95%, 1280px);
            margin: 24px auto 40px;
        }

        .topbar {
            background: linear-gradient(135deg, var(--primary), var(--primary-2));
            color: #fff;
            border-radius: 24px;
            padding: 26px 28px 22px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
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
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .brand h1 {
            margin: 0;
            font-size: 38px;
            font-weight: 700;
            letter-spacing: 0;
        }

        .brand p {
            margin: 8px 0 0;
            color: rgba(255,255,255,0.88);
            font-size: 15px;
        }

        .topbar-badge {
            position: relative;
            z-index: 1;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.18);
            color: #fff;
            padding: 10px 14px;
            border-radius: 999px;
            font-size: 14px;
            backdrop-filter: blur(6px);
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
            padding: 11px 16px;
            border-radius: 12px;
            font-size: 15px;
            transition: 0.2s ease;
            backdrop-filter: blur(6px);
        }

        .nav a:hover {
            transform: translateY(-1px);
            background: rgba(255,255,255,0.2);
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 22px;
            margin-bottom: 22px;
            box-shadow: var(--shadow);
        }

        .card h2 {
            margin: 0 0 18px;
            font-size: 30px;
            line-height: 1.4;
            color: var(--primary);
        }

        .card h3 {
            margin: 0 0 14px;
            font-size: 20px;
            color: var(--dark);
        }

        .section-subtitle {
            color: var(--muted);
            margin-top: -8px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
        }

        .row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 14px;
        }

        .stat {
            border-radius: 20px;
            padding: 22px 20px;
            color: #fff;
            position: relative;
            overflow: hidden;
            min-height: 120px;
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

        .stat .label {
            font-size: 16px;
            opacity: 0.95;
            margin-bottom: 8px;
        }

        .stat .value {
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .stat .sub {
            margin-top: 6px;
            font-size: 13px;
            opacity: 0.92;
        }

        .stat.blue { background: linear-gradient(135deg, #2d6cdf, #1d5ac8); }
        .stat.green { background: linear-gradient(135deg, #118a7e, #0e6f66); }
        .stat.red { background: linear-gradient(135deg, #d63031, #b82628); }
        .stat.dark { background: linear-gradient(135deg, #31475e, #233142); }
        .stat.orange { background: linear-gradient(135deg, #f39c12, #d68910); }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
            color: #24344d;
            font-size: 15px;
        }

        input[type="text"],
        input[type="number"],
        input[type="date"],
        input[type="email"],
        input[type="password"],
        select,
        textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #dbe2ec;
            border-radius: 12px;
            background: #fff;
            color: #24344d;
            font-size: 15px;
            outline: none;
            transition: 0.2s ease;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: #8eb3f8;
            box-shadow: 0 0 0 4px rgba(45,108,223,0.12);
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: none;
            border-radius: 12px;
            padding: 11px 16px;
            font-size: 15px;
            cursor: pointer;
            transition: 0.2s ease;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn:hover {
            transform: translateY(-1px);
            filter: brightness(0.98);
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
        }

        .btn-success {
            background: var(--success);
            color: #fff;
        }

        .btn-danger {
            background: var(--danger);
            color: #fff;
        }

        .btn-warning {
            background: var(--warning);
            color: #fff;
        }

        .btn-secondary {
            background: #7b8794;
            color: #fff;
        }

        .btn-light {
            background: #eef3fb;
            color: var(--primary);
            border: 1px solid #dbe5f3;
        }

        .flash {
            padding: 15px 18px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-size: 15px;
            border: 1px solid transparent;
            box-shadow: var(--shadow);
        }

        .flash.success {
            background: #e5f7ef;
            color: #146c43;
            border-color: #c8ead8;
        }

        .flash.error {
            background: #fdeaea;
            color: #b02a37;
            border-color: #f2c9cc;
        }

        .flash.warning {
            background: #fff6df;
            color: #946200;
            border-color: #f1dfab;
        }

        .flash.info {
            background: #eaf2ff;
            color: #184eaa;
            border-color: #cbddff;
        }

        .table-wrap {
            overflow-x: auto;
            border-radius: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            min-width: 760px;
        }

        table th,
        table td {
            padding: 12px 10px;
            text-align: center;
            border-bottom: 1px solid #edf1f6;
            vertical-align: middle;
        }

        table th {
            background: var(--table-head);
            color: var(--primary);
            font-size: 15px;
            font-weight: 700;
            position: sticky;
            top: 0;
        }

        table tr:hover td {
            background: #fbfcfe;
        }

        .actions {
            display: flex;
            gap: 6px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
        }

        .badge-success {
            background: #dff5ea;
            color: #0e6d52;
        }

        .badge-danger {
            background: #fde7e7;
            color: #bb2d2d;
        }

        .badge-info {
            background: #e7f0ff;
            color: #1d5ac8;
        }

        .muted {
            color: var(--muted);
            font-size: 14px;
        }

        .empty-state {
            text-align: center;
            padding: 26px 16px;
            color: var(--muted);
            font-size: 15px;
        }

        .footer-note {
            text-align: center;
            color: var(--muted);
            font-size: 13px;
            margin-top: 10px;
        }

        .divider {
            height: 1px;
            background: linear-gradient(to left, transparent, #dbe3ef, transparent);
            margin: 18px 0;
        }

        .quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .quick-links a {
            background: #f2f6fc;
            color: var(--primary);
            border: 1px solid #dbe5f3;
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 14px;
        }

        .quick-links a:hover {
            background: #e9f0fb;
        }

        @media (max-width: 768px) {
            .container {
                width: 94%;
                margin-top: 16px;
            }

            .topbar {
                padding: 20px 18px 18px;
                border-radius: 18px;
            }

            .brand h1 {
                font-size: 30px;
            }

            .card {
                padding: 16px;
                border-radius: 16px;
            }

            .card h2 {
                font-size: 24px;
            }

            .stat .value {
                font-size: 28px;
            }

            .nav {
                gap: 8px;
            }

            .nav a {
                padding: 10px 12px;
                font-size: 14px;
            }
        }

        @media print {
            body {
                background: #fff !important;
            }

            .topbar,
            .flash,
            .footer-note,
            .quick-links {
                display: none !important;
            }

            .container {
                width: 100%;
                max-width: 100%;
                margin: 0;
            }

            .card {
                border: 1px solid #ccc;
                box-shadow: none !important;
                margin-bottom: 14px;
                break-inside: avoid;
            }

            .btn {
                display: none !important;
            }

            table th, table td {
                border: 1px solid #aaa !important;
            }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="topbar">
        <div class="topbar-header">
            <div class="brand">
                <h1>الإدارة المالية</h1>
                <p>نظام مبسط لإدارة الإيرادات والمدفوعات والتقارير المالية</p>
            </div>

            <div class="topbar-badge">
                <?= e(date('Y-m-d')) ?>
            </div>
        </div>

        <div class="nav">
            <a href="index.php">الرئيسية</a>
            <a href="income.php">دفتر الإيرادات</a>
            <a href="expenses.php">دفتر المدفوعات</a>
            <a href="report.php">التقرير المالي</a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="flash <?= e($flash['type']) ?>">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>