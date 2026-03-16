<?php
require_once 'bootstrap.php';
require_once 'helpers.php';

if (!receipt_books_exist()) {
    redirect('receipt_books.php');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'المعرّف غير صحيح');
    redirect('receipt_books.php');
}

$stmt = $pdo->prepare("SELECT * FROM receipt_books WHERE id = ?");
$stmt->execute([$id]);
$book = $stmt->fetch();

if (!$book) {
    set_flash('error', 'الدفتر غير موجود');
    redirect('receipt_books.php');
}

// Associated income records
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

$usedCount     = count($receipts);
$totalReceipts = (int)$book['total_receipts'];
$remaining     = $totalReceipts - $usedCount;
$collected     = array_sum(array_column($receipts, 'amount'));

// Missing receipt numbers within range
$usedNos    = array_map(fn($r) => (int)$r['receipt_no'], $receipts);
sort($usedNos);
$allNos     = range((int)$book['start_receipt_no'], (int)$book['end_receipt_no']);
$missingNos = array_values(array_diff($allNos, $usedNos));

$page_title = 'دفتر الوصولات: ' . $book['book_no'];
require 'layout.php';
?>

<div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
        <h2 style="margin:0;">دفتر رقم: <?= e($book['book_no']) ?></h2>
        <div class="actions">
            <a class="btn btn-light" target="_blank" href="receipt_book_print.php?id=<?= (int)$book['id'] ?>">طباعة</a>
            <a class="btn btn-warning" href="receipt_book_edit.php?id=<?= (int)$book['id'] ?>">تعديل</a>
            <a class="btn btn-secondary" href="receipt_books.php">رجوع</a>
        </div>
    </div>

    <div class="grid">
        <div class="stat blue">
            <div class="label">إجمالي الوصولات</div>
            <div class="value"><?= $totalReceipts ?></div>
        </div>
        <div class="stat green">
            <div class="label">المستخدم</div>
            <div class="value"><?= $usedCount ?></div>
        </div>
        <div class="stat orange">
            <div class="label">المتبقي</div>
            <div class="value"><?= $remaining ?></div>
        </div>
        <div class="stat dark">
            <div class="label">المُحصّل</div>
            <div class="value"><?= number_format($collected, 2) ?></div>
        </div>
    </div>

    <div class="divider"></div>

    <div class="grid" style="margin-top:8px;">
        <div class="box" style="border:1px solid var(--border);border-radius:12px;padding:14px;">
            <div style="color:var(--muted);margin-bottom:4px;">نطاق الأرقام</div>
            <strong><?= (int)$book['start_receipt_no'] ?> – <?= (int)$book['end_receipt_no'] ?></strong>
        </div>
        <div class="box" style="border:1px solid var(--border);border-radius:12px;padding:14px;">
            <div style="color:var(--muted);margin-bottom:4px;">تاريخ الاستلام</div>
            <strong><?= e($book['received_date']) ?></strong>
        </div>
        <div class="box" style="border:1px solid var(--border);border-radius:12px;padding:14px;">
            <div style="color:var(--muted);margin-bottom:4px;">الحالة</div>
            <?= $book['status'] === 'open'
                ? '<span class="badge badge-success">مفتوح</span>'
                : '<span class="badge badge-danger">مغلق</span>' ?>
        </div>
        <?php if ($book['notes']): ?>
        <div class="box" style="border:1px solid var(--border);border-radius:12px;padding:14px;">
            <div style="color:var(--muted);margin-bottom:4px;">ملاحظات</div>
            <?= nl2br(e($book['notes'])) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($missingNos): ?>
<div class="card">
    <h2>أرقام الوصولات الناقصة (<?= count($missingNos) ?>)</h2>
    <p class="section-subtitle">الأرقام الآتية في النطاق لم تُستخدم بعد:</p>
    <div style="display:flex;flex-wrap:wrap;gap:8px;">
        <?php foreach ($missingNos as $n): ?>
            <span class="badge badge-danger"><?= (int)$n ?></span>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <h2>وصولات الدفتر (<?= $usedCount ?>)</h2>
    <?php if ($receipts): ?>
    <div class="table-wrap">
    <table>
        <tr>
            <th>رقم الوصول</th>
            <th>التاريخ</th>
            <th>المتبرع</th>
            <th>التصنيف</th>
            <th>المبلغ</th>
            <th>طريقة الدفع</th>
            <th>إجراءات</th>
        </tr>
        <?php foreach ($receipts as $r): ?>
        <tr>
            <td><?= e($r['receipt_no']) ?></td>
            <td><?= e($r['income_date']) ?></td>
            <td><?= e($r['donor_name']) ?></td>
            <td><?= e($r['category_name']) ?></td>
            <td><?= number_format((float)$r['amount'], 2) ?></td>
            <td><?= e($r['payment_method']) ?></td>
            <td class="actions">
                <a class="btn btn-light" target="_blank" href="income_print.php?id=<?= (int)$r['id'] ?>">طباعة</a>
                <a class="btn btn-warning" href="income_edit.php?id=<?= (int)$r['id'] ?>">تعديل</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <tr style="font-weight:bold;background:#f0f4fa;">
            <td colspan="4">الإجمالي</td>
            <td><?= number_format($collected, 2) ?></td>
            <td colspan="2"></td>
        </tr>
    </table>
    </div>
    <?php else: ?>
        <div class="empty-state">لا توجد وصولات مرتبطة بهذا الدفتر بعد.</div>
    <?php endif; ?>
</div>

</div>
</body>
</html>
