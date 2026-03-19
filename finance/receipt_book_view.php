<?php
require_once 'bootstrap.php';
require_once 'helpers.php';

$page_title = 'تفاصيل دفتر';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    if (function_exists('set_flash')) set_flash('error', 'رقم الدفتر غير صحيح');
    header('Location: receipt_books.php');
    exit;
}

$st = $pdo->prepare("SELECT * FROM receipt_books WHERE id=?");
$st->execute([$id]);
$book = $st->fetch(PDO::FETCH_ASSOC);
if (!$book) {
    if (function_exists('set_flash')) set_flash('error', 'الدفتر غير موجود');
    header('Location: receipt_books.php');
    exit;
}

// incomes for this book
$items = $pdo->prepare("
    SELECT fi.id, fi.receipt_no, fi.income_date, fi.donor_name, fi.amount, fi.payment_method,
           ic.name AS category_name
    FROM finance_income fi
    LEFT JOIN income_categories ic ON ic.id = fi.category_id
    WHERE fi.book_id = ?
    ORDER BY fi.receipt_no ASC, fi.id ASC
");
$items->execute([$id]);
$incomes = $items->fetchAll(PDO::FETCH_ASSOC);

// totals
$totSt = $pdo->prepare("SELECT IFNULL(SUM(amount),0) FROM finance_income WHERE book_id=?");
$totSt->execute([$id]);
$totalCollected = (float)$totSt->fetchColumn();

// used receipts distinct (because receipt_no may repeat across categories for same receipt)
$usedReceipts = [];
foreach ($incomes as $r) $usedReceipts[(int)$r['receipt_no']] = true;
$usedCount = count($usedReceipts);

$totalReceipts = (int)$book['total_receipts'];
$remaining = max(0, $totalReceipts - $usedCount);

// missing receipt numbers in range (based on usedReceipts keys)
$missing = [];
$start = (int)$book['start_receipt_no'];
$end   = (int)$book['end_receipt_no'];
for ($n = $start; $n <= $end; $n++) {
    if (!isset($usedReceipts[$n])) $missing[] = $n;
}

// totals by category for this book
$byCat = $pdo->prepare("
    SELECT ic.name AS category_name, IFNULL(SUM(fi.amount),0) AS total_amount
    FROM income_categories ic
    LEFT JOIN finance_income fi
      ON fi.category_id = ic.id AND fi.book_id = ?
    GROUP BY ic.id, ic.name
    ORDER BY ic.name ASC
");
$byCat->execute([$id]);
$catTotals = $byCat->fetchAll(PDO::FETCH_ASSOC);

require 'layout.php';
?>

<div class="card">
    <h2>تفاصيل الدفتر: <?= e($book['book_no']) ?></h2>
    <div class="section-subtitle">مجموع الدفتر + مجاميع التصنيفات + الوصولات المسجلة ضمن هذا الدفتر.</div>

    <div class="grid">
        <div class="stat blue col-3">
            <div class="label">النطاق</div>
            <div class="value"><?= (int)$book['start_receipt_no'] ?> - <?= (int)$book['end_receipt_no'] ?></div>
            <div class="sub">الإجمالي: <?= (int)$book['total_receipts'] ?></div>
        </div>
        <div class="stat green col-3">
            <div class="label">المستخدم</div>
            <div class="value"><?= (int)$usedCount ?></div>
            <div class="sub">متبقي: <?= (int)$remaining ?></div>
        </div>
        <div class="stat dark col-3">
            <div class="label">المُحصّل</div>
            <div class="value"><?= number_format($totalCollected, 2) ?></div>
            <div class="sub">حسب الوصولات المسجلة</div>
        </div>
        <div class="stat orange col-3">
            <div class="label">الحالة</div>
            <div class="value"><?= e($book['status']) ?></div>
            <div class="sub">تاريخ: <?= e($book['received_date']) ?></div>
        </div>
    </div>

    <div class="form-actions" style="justify-content:flex-start;margin-top:14px;">
        <a class="btn btn-primary" href="income_entry.php?book_id=<?= (int)$book['id'] ?>">+ إدخال وصل على هذا الدفتر</a>
        <a class="btn btn-light" href="receipt_book_print.php?id=<?= (int)$book['id'] ?>" target="_blank">طباعة تقرير الدفتر</a>
        <a class="btn btn-light" href="receipt_books.php">رجوع</a>
    </div>
</div>

<div class="grid">
    <div class="card col-6">
        <h2>مجاميع التصنيفات (داخل الدفتر)</h2>
        <div class="table-wrap">
            <table>
                <tr><th class="wrap">التصنيف</th><th>المجموع</th></tr>
                <?php foreach ($catTotals as $r): ?>
                    <tr>
                        <td class="wrap" style="text-align:right;"><?= e($r['category_name'] ?: '—') ?></td>
                        <td><?= number_format((float)$r['total_amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <div class="card col-6">
        <h2>الأرقام غير المستخدمة (المفقودة)</h2>
        <div class="section-subtitle">الأرقام ضمن النطاق والتي لم يتم تسجيل وصل لها بعد.</div>
        <div class="table-wrap">
            <table style="min-width:820px;">
                <tr><th>عدد المفقود</th><th class="wrap">الأرقام</th></tr>
                <tr>
                    <td><?= count($missing) ?></td>
                    <td class="wrap" style="text-align:right;white-space:normal;">
                        <?php if ($missing): ?>
                            <?= e(implode(', ', $missing)) ?>
                        <?php else: ?>
                            <span class="muted">لا يوجد</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <h2>الوصولات المسجلة</h2>
    <div class="section-subtitle">هذه قائمة وصولات الإيراد (قد يظهر نفس رقم الوصل أكثر من مرة بسبب تعدد التصنيفات).</div>

    <div class="table-wrap">
        <table>
            <tr>
                <th>رقم الوصل</th>
                <th>التاريخ</th>
                <th class="wrap">المتبرع</th>
                <th>التصنيف</th>
                <th>المبلغ</th>
                <th>طريقة الدفع</th>
            </tr>
            <?php if ($incomes): ?>
                <?php foreach ($incomes as $r): ?>
                    <tr>
                        <td><?= e($r['receipt_no']) ?></td>
                        <td><?= e($r['income_date']) ?></td>
                        <td class="wrap"><?= e($r['donor_name']) ?></td>
                        <td><?= e($r['category_name'] ?: '—') ?></td>
                        <td><?= number_format((float)$r['amount'], 2) ?></td>
                        <td><?= e($r['payment_method'] ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" class="empty-state">لا توجد وصولات مسجلة على هذا الدفتر بعد</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<div class="footer-note">© <?= e(date('Y')) ?> — قسم المالية</div>

</div>
</body>
</html>