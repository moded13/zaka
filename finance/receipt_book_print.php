<?php
require_once 'bootstrap.php';
require_once 'helpers.php';

if (!receipt_books_exist()) {
    redirect('receipt_books.php');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die('المعرّف غير صحيح');
}

$stmt = $pdo->prepare("SELECT * FROM receipt_books WHERE id = ?");
$stmt->execute([$id]);
$book = $stmt->fetch();

if (!$book) {
    die('الدفتر غير موجود');
}

$receipts = [];
if (book_id_exists_in_income()) {
    $rs = $pdo->prepare("
        SELECT i.*, c.name AS category_name
        FROM finance_income i
        LEFT JOIN income_categories c ON i.category_id = c.id
        WHERE i.book_id = ?
        ORDER BY CAST(i.receipt_no AS UNSIGNED) ASC, i.id ASC
    ");
    $rs->execute([$id]);
    $receipts = $rs->fetchAll();
}

$usedNos    = array_map(fn($r) => (int)$r['receipt_no'], $receipts);
$missingNos = compute_missing_receipts(
    (int)$book['start_receipt_no'],
    (int)$book['end_receipt_no'],
    $usedNos
);
$usedCount  = count($receipts);
$totalReceipts = (int)$book['total_receipts'];
$remaining  = $totalReceipts - $usedCount;
$collected  = array_sum(array_column($receipts, 'amount'));

$orgName   = function_exists('getSetting') ? getSetting('org_name', 'الجمعية') : 'الجمعية';
$printDate = date('Y-m-d H:i');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>طباعة دفتر الوصولات - <?= e($book['book_no']) ?></title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; margin: 0; padding: 16px; direction: rtl; background: #fff; color: #111; }
        .print-bar { text-align: center; margin-bottom: 16px; }
        .print-bar .btn { display: inline-block; background: #163d7a; color: #fff; text-decoration: none; padding: 9px 16px; border-radius: 8px; margin: 0 5px; border: none; cursor: pointer; font-size: 14px; }
        .print-bar .btn-back { background: #6c757d; }
        .header { text-align: center; border-bottom: 2px solid #163d7a; padding-bottom: 12px; margin-bottom: 16px; }
        .header h1 { margin: 0 0 6px; font-size: 22px; color: #163d7a; }
        .header h2 { margin: 0; font-size: 16px; color: #444; }
        .meta-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 16px; }
        .meta-box { border: 1px solid #ccc; border-radius: 8px; padding: 10px; text-align: center; }
        .meta-box .label { font-size: 12px; color: #666; margin-bottom: 4px; }
        .meta-box .value { font-size: 18px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 13px; }
        table th, table td { border: 1px solid #bbb; padding: 7px 8px; text-align: center; }
        table th { background: #e8edf5; color: #163d7a; }
        .missing-section { margin-bottom: 16px; }
        .missing-section h3 { font-size: 14px; color: #b02a37; margin-bottom: 8px; }
        .missing-nos { display: flex; flex-wrap: wrap; gap: 6px; }
        .missing-no { border: 1px solid #b02a37; border-radius: 6px; padding: 3px 8px; font-size: 13px; color: #b02a37; }
        .total-row td { font-weight: bold; background: #f0f4fa; }
        .footer { text-align: center; font-size: 12px; color: #666; margin-top: 16px; border-top: 1px solid #ccc; padding-top: 10px; }
        @media print {
            .print-bar { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

<div class="print-bar">
    <button class="btn" onclick="window.print()">🖨️ طباعة</button>
    <a class="btn btn-back" href="receipt_book_view.php?id=<?= (int)$book['id'] ?>">رجوع</a>
</div>

<div class="header">
    <h1><?= e($orgName) ?></h1>
    <h2>كشف دفتر الوصولات &mdash; رقم الدفتر: <?= e($book['book_no']) ?></h2>
</div>

<div class="meta-grid">
    <div class="meta-box">
        <div class="label">نطاق الأرقام</div>
        <div class="value" style="font-size:14px;"><?= (int)$book['start_receipt_no'] ?>–<?= (int)$book['end_receipt_no'] ?></div>
    </div>
    <div class="meta-box">
        <div class="label">الإجمالي</div>
        <div class="value"><?= $totalReceipts ?></div>
    </div>
    <div class="meta-box">
        <div class="label">المستخدم</div>
        <div class="value"><?= $usedCount ?></div>
    </div>
    <div class="meta-box">
        <div class="label">المتبقي</div>
        <div class="value"><?= $remaining ?></div>
    </div>
    <div class="meta-box">
        <div class="label">المُحصّل</div>
        <div class="value" style="font-size:14px;"><?= number_format($collected, 2) ?></div>
    </div>
    <div class="meta-box">
        <div class="label">تاريخ الاستلام</div>
        <div class="value" style="font-size:13px;"><?= e($book['received_date']) ?></div>
    </div>
    <div class="meta-box">
        <div class="label">الحالة</div>
        <div class="value" style="font-size:13px;color:<?= $book['status'] === 'open' ? '#0e6d52' : '#b02a37' ?>">
            <?= $book['status'] === 'open' ? 'مفتوح' : 'مغلق' ?>
        </div>
    </div>
    <div class="meta-box">
        <div class="label">تاريخ الطباعة</div>
        <div class="value" style="font-size:12px;"><?= $printDate ?></div>
    </div>
</div>

<?php if ($missingNos): ?>
<div class="missing-section">
    <h3>الوصولات الناقصة (<?= count($missingNos) ?> وصل)</h3>
    <div class="missing-nos">
        <?php foreach ($missingNos as $n): ?>
            <span class="missing-no"><?= (int)$n ?></span>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($receipts): ?>
<table>
    <tr>
        <th>#</th>
        <th>رقم الوصول</th>
        <th>التاريخ</th>
        <th>المتبرع</th>
        <th>التصنيف</th>
        <th>المبلغ</th>
        <th>طريقة الدفع</th>
    </tr>
    <?php foreach ($receipts as $i => $r): ?>
    <tr>
        <td><?= $i + 1 ?></td>
        <td><?= e($r['receipt_no']) ?></td>
        <td><?= e($r['income_date']) ?></td>
        <td><?= e($r['donor_name']) ?></td>
        <td><?= e($r['category_name']) ?></td>
        <td><?= number_format((float)$r['amount'], 2) ?></td>
        <td><?= e($r['payment_method']) ?></td>
    </tr>
    <?php endforeach; ?>
    <tr class="total-row">
        <td colspan="5">الإجمالي</td>
        <td><?= number_format($collected, 2) ?></td>
        <td></td>
    </tr>
</table>
<?php else: ?>
<p style="text-align:center;color:#666;">لا توجد وصولات مرتبطة بهذا الدفتر</p>
<?php endif; ?>

<div class="footer">
    تم الطباعة في: <?= $printDate ?>
    <?php if ($book['notes']): ?> &mdash; ملاحظات: <?= e($book['notes']) ?><?php endif; ?>
</div>

</body>
</html>
