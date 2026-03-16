<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin/bootstrap.php';
requireLogin();

$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    exit('رقم السجل مطلوب.');
}

$stmt = $pdo->prepare("SELECT * FROM ayni_entries WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    exit('السجل غير موجود.');
}

$orgName  = function_exists('getSetting') ? getSetting('org_name', APP_NAME) : APP_NAME;
$orgPhone = function_exists('getSetting') ? getSetting('org_phone', '') : '';
$printAt  = date('Y-m-d H:i');
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>طباعة وارد عيني #<?= (int)$id ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
    --ink:#153a72;
    --line:#cdd7e5;
    --soft:#f5f8fd;
    --muted:#6e7d90;
}
*{box-sizing:border-box}
body{
    margin:0;
    font-family:'Cairo',sans-serif;
    color:#111;
    background:#fff;
}
.no-print{
    padding:12px;
    border-bottom:1px solid var(--line);
    display:flex;
    gap:8px;
}
.btn{
    border:1px solid var(--line);
    background:#fff;
    padding:7px 14px;
    border-radius:8px;
    text-decoration:none;
    color:#111;
    font-family:inherit;
    cursor:pointer;
}
.btn-primary{
    background:var(--ink);
    color:#fff;
    border-color:var(--ink);
}
.sheet{
    width:210mm;
    min-height:297mm;
    margin:0 auto;
    padding:12mm;
}
.header{
    border:2px solid var(--ink);
    border-radius:12px;
    padding:12px 14px;
    margin-bottom:12px;
}
.header-top{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:10px;
}
.org{
    font-size:22px;
    font-weight:800;
    color:var(--ink);
}
.sub{
    color:var(--muted);
    font-size:12px;
}
.badge{
    border:1px solid var(--ink);
    color:var(--ink);
    font-weight:800;
    padding:6px 12px;
    border-radius:999px;
}
.grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(0,1fr));
    gap:12px;
}
.card{
    border:1px solid var(--line);
    border-radius:12px;
    padding:10px 12px;
    background:#fff;
}
.label{
    font-size:12px;
    color:var(--muted);
    margin-bottom:4px;
}
.value{
    font-size:16px;
    font-weight:800;
    color:#1a2433;
    word-break:break-word;
}
.notes{
    margin-top:12px;
    border:1px solid var(--line);
    border-radius:12px;
    padding:12px;
    min-height:100px;
    background:var(--soft);
}
.footer{
    margin-top:18px;
    border:2px solid var(--ink);
    border-radius:12px;
    padding:12px;
}
.signs{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
}
.sign{
    text-align:center;
}
.sign-line{
    border-top:1px solid #333;
    margin-top:42px;
}
.sign-label{
    margin-top:8px;
    font-weight:700;
}
.print-note{
    margin-top:12px;
    color:var(--muted);
    font-size:12px;
    display:flex;
    justify-content:space-between;
}
@media print{
    .no-print{display:none!important}
    @page{size:A4; margin:8mm}
    .sheet{padding:0; width:auto; min-height:auto}
}
</style>
</head>
<body>

<div class="no-print">
    <button class="btn btn-primary" onclick="window.print()">طباعة</button>
    <a class="btn" href="javascript:history.back()">رجوع</a>
</div>

<div class="sheet">
    <div class="header">
        <div class="header-top">
            <div>
                <div class="org"><?= e($orgName) ?></div>
                <div class="sub">سجل واردات عينية<?= $orgPhone ? ' — هاتف: ' . e($orgPhone) : '' ?></div>
            </div>
            <div class="badge">سجل رقم #<?= (int)$row['id'] ?></div>
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <div class="label">اسم المتبرع</div>
            <div class="value"><?= e((string)$row['donor_name']) ?></div>
        </div>

        <div class="card">
            <div class="label">نوع التبرع</div>
            <div class="value"><?= e((string)$row['donation_type']) ?></div>
        </div>

        <div class="card">
            <div class="label">عدد التبرع</div>
            <div class="value"><?= e((string)$row['quantity']) ?></div>
        </div>

        <div class="card">
            <div class="label">عنوان التبرع</div>
            <div class="value"><?= e((string)($row['donation_title'] ?? '—')) ?></div>
        </div>

        <div class="card">
            <div class="label">تاريخ التبرع</div>
            <div class="value"><?= e((string)($row['donation_date'] ?? '—')) ?></div>
        </div>

        <div class="card">
            <div class="label">رقم الوصل</div>
            <div class="value"><?= e((string)($row['receipt_no'] ?? '—')) ?></div>
        </div>

        <div class="card">
            <div class="label">مقدّرة بالدينار</div>
            <div class="value"><?= e(number_format((float)($row['estimated_value'] ?? 0), 3)) ?></div>
        </div>

        <div class="card">
            <div class="label">تاريخ الإدخال</div>
            <div class="value"><?= e((string)($row['created_at'] ?? '—')) ?></div>
        </div>
    </div>

    <div class="notes">
        <div class="label">ملاحظات</div>
        <div class="value" style="font-size:14px; font-weight:600;">
            <?= e((string)($row['notes'] ?? '—')) ?>
        </div>
    </div>

    <div class="footer">
        <div class="signs">
            <div class="sign">
                <div class="sign-line"></div>
                <div class="sign-label">توقيع المستلم</div>
            </div>
            <div class="sign">
                <div class="sign-line"></div>
                <div class="sign-label">اعتماد المسؤول</div>
            </div>
        </div>

        <div class="print-note">
            <div>طُبع في: <?= e($printAt) ?></div>
            <div>رقم السجل: #<?= (int)$row['id'] ?></div>
        </div>
    </div>
</div>

</body>
</html>