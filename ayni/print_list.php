<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin/bootstrap.php';
requireLogin();

$pdo = getPDO();

$type = trim((string)($_GET['type'] ?? ''));
$q    = trim((string)($_GET['q'] ?? ''));

$where = [];
$params = [];

if ($type !== '') {
    $where[] = 'donation_type = ?';
    $params[] = $type;
}

if ($q !== '') {
    $where[] = '(
        donor_name LIKE ?
        OR donation_type LIKE ?
        OR donation_title LIKE ?
        OR receipt_no LIKE ?
        OR CAST(id AS CHAR) LIKE ?
    )';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}

$sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare(
    "SELECT *
     FROM ayni_entries
     $sqlWhere
     ORDER BY donation_date DESC, id DESC"
);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$orgName  = function_exists('getSetting') ? getSetting('org_name', APP_NAME) : APP_NAME;
$orgPhone = function_exists('getSetting') ? getSetting('org_phone', '') : '';
$printAt  = date('Y-m-d H:i');
$totalValue = array_sum(array_map(static fn($r) => (float)($r['estimated_value'] ?? 0), $rows));
$totalQty   = array_sum(array_map(static fn($r) => (int)($r['quantity'] ?? 0), $rows));
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>طباعة كشف الواردات العينية</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
    --ink:#153a72;
    --line:#cfd7e6;
    --head:#eef4fb;
    --muted:#6f7d91;
}
*{box-sizing:border-box}
body{
    margin:0;
    font-family:'Cairo',sans-serif;
    background:#fff;
    color:#111;
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
    padding:10mm;
}
.header{
    border:2px solid var(--ink);
    border-radius:12px;
    padding:12px 14px;
    margin-bottom:10px;
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
.meta{
    display:flex;
    flex-wrap:wrap;
    gap:10px 16px;
    border-top:1px dashed var(--line);
    margin-top:10px;
    padding-top:8px;
    font-size:12px;
}
table{
    width:100%;
    border-collapse:collapse;
    table-layout:fixed;
    border:1px solid var(--line);
}
th,td{
    border:1px solid var(--line);
    padding:6px;
    vertical-align:middle;
    font-size:11px;
}
thead th{
    background:var(--head);
    font-weight:800;
    text-align:center;
}
.text-center{text-align:center}
.small{font-size:10px}
.footer{
    margin-top:10px;
    display:flex;
    justify-content:space-between;
    color:var(--muted);
    font-size:12px;
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
                <div class="sub">كشف الواردات العينية<?= $orgPhone ? ' — هاتف: ' . e($orgPhone) : '' ?></div>
            </div>
            <div class="sub">عدد السجلات: <?= count($rows) ?></div>
        </div>

        <div class="meta">
            <div><strong>النوع:</strong> <?= e($type !== '' ? $type : 'جميع الأنواع') ?></div>
            <div><strong>البحث:</strong> <?= e($q !== '' ? $q : '—') ?></div>
            <div><strong>إجمالي العدد:</strong> <?= (int)$totalQty ?></div>
            <div><strong>إجمالي القيمة:</strong> <?= e(number_format((float)$totalValue, 3)) ?></div>
        </div>
    </div>

    <table>
        <thead>
        <tr>
            <th style="width:5%">الرقم</th>
            <th style="width:22%">اسم المتبرع</th>
            <th style="width:12%">نوع التبرع</th>
            <th style="width:8%">العدد</th>
            <th style="width:18%">عنوان التبرع</th>
            <th style="width:11%">تاريخ التبرع</th>
            <th style="width:10%">رقم الوصل</th>
            <th style="width:14%">مقدّرة بالدينار</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr>
                <td colspan="8" class="text-center">لا توجد بيانات</td>
            </tr>
        <?php else: ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="text-center"><?= (int)$r['id'] ?></td>
                    <td><?= e((string)$r['donor_name']) ?></td>
                    <td class="text-center"><?= e((string)$r['donation_type']) ?></td>
                    <td class="text-center"><?= e((string)$r['quantity']) ?></td>
                    <td><?= e((string)($r['donation_title'] ?? '—')) ?></td>
                    <td class="text-center"><?= e((string)($r['donation_date'] ?? '—')) ?></td>
                    <td class="text-center"><?= e((string)($r['receipt_no'] ?? '—')) ?></td>
                    <td class="text-center"><?= e(number_format((float)($r['estimated_value'] ?? 0), 3)) ?></td>
                </tr>
                <?php if (!empty($r['notes'])): ?>
                    <tr>
                        <td></td>
                        <td colspan="7" class="small"><strong>ملاحظات:</strong> <?= e((string)$r['notes']) ?></td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        <div>طُبع في: <?= e($printAt) ?></div>
        <div>كشف الواردات العينية</div>
    </div>
</div>

</body>
</html>