<?php
require_once 'bootstrap.php';
require_once 'helpers.php';

$page_title = 'دفاتر الوصولات';

// Show add form
$showAdd = (($_GET['add'] ?? '') === '1');

// Load books + metrics (used receipts distinct + collected)
$books = $pdo->query("
    SELECT rb.*,
           COUNT(DISTINCT fi.receipt_no) AS used_receipts,
           IFNULL(SUM(fi.amount), 0) AS collected
    FROM receipt_books rb
    LEFT JOIN finance_income fi ON fi.book_id = rb.id
    GROUP BY rb.id
    ORDER BY rb.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Totals by category per book
$catTotals = [];
$rows = $pdo->query("
    SELECT fi.book_id, ic.name AS category_name, IFNULL(SUM(fi.amount),0) AS total_amount
    FROM finance_income fi
    LEFT JOIN income_categories ic ON ic.id = fi.category_id
    WHERE fi.book_id IS NOT NULL
    GROUP BY fi.book_id, ic.id, ic.name
    ORDER BY fi.book_id DESC, ic.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $r) {
    $bid = (int)$r['book_id'];
    if (!isset($catTotals[$bid])) $catTotals[$bid] = [];
    $catTotals[$bid][] = [
        'category_name' => (string)($r['category_name'] ?? '—'),
        'total_amount'  => (float)$r['total_amount'],
    ];
}

require 'layout.php';
?>

<div class="card">
    <h2>دفاتر الوصولات</h2>
    <div class="section-subtitle">
        أنشئ دفتر وصولات ثم أدخل الوصولات من صفحة
        <a href="income_entry.php" class="btn btn-light" style="padding:6px 10px;margin-inline:6px;">إدخال وصل</a>
        وسيتم احتساب مجموع الدفتر ومجاميع التصنيفات تلقائيًا.
    </div>

    <div class="form-actions" style="justify-content:flex-start;">
        <a class="btn btn-primary" href="receipt_books.php?add=1">+ إضافة دفتر جديد</a>
        <a class="btn btn-light" href="income_entry.php">+ إدخال وصل</a>
    </div>
</div>

<?php if ($showAdd): ?>
<div class="card">
    <h2>إضافة دفتر وصولات</h2>

    <form method="post" action="receipt_book_store.php" class="grid">
        <!-- ✅ this is the missing piece that caused (sent=no, sess=no) -->
        <?= finance_csrf_field() ?>

        <div class="col-3">
            <label>رقم الدفتر *</label>
            <input type="text" name="book_no" required>
        </div>

        <div class="col-3">
            <label>رقم الوصول الأول *</label>
            <input type="number" name="start_receipt_no" required>
        </div>

        <div class="col-3">
            <label>رقم الوصول الأخير *</label>
            <input type="number" name="end_receipt_no" required>
        </div>

        <div class="col-3">
            <label>تاريخ الاستلام *</label>
            <input type="date" name="received_date" value="<?= e(date('Y-m-d')) ?>" required>
        </div>

        <div class="col-3">
            <label>الحالة *</label>
            <select name="status" required>
                <option value="open" selected>مفتوح</option>
                <option value="closed">مغلق</option>
            </select>
        </div>

        <div class="col-9">
            <label>ملاحظات</label>
            <input type="text" name="notes" placeholder="اختياري">
        </div>

        <div class="col-12">
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">حفظ الدفتر</button>
                <a class="btn btn-light" href="receipt_books.php">إلغاء</a>
            </div>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h2>قائمة الدفاتر</h2>

    <div class="table-wrap">
        <table>
            <tr>
                <th>رقم الدفتر</th>
                <th>من</th>
                <th>إلى</th>
                <th>الإجمالي</th>
                <th>المستخدم</th>
                <th>المتبقي</th>
                <th>المُحصّل</th>
                <th>الحالة</th>
                <th class="wrap">مجاميع التصنيفات</th>
                <th>إجراءات</th>
            </tr>

            <?php if ($books): ?>
                <?php foreach ($books as $b):
                    $total = (int)$b['total_receipts'];
                    $used  = (int)$b['used_receipts'];
                    $rem   = max(0, $total - $used);
                    $bid   = (int)$b['id'];
                ?>
                    <tr>
                        <td><?= e($b['book_no']) ?></td>
                        <td><?= (int)$b['start_receipt_no'] ?></td>
                        <td><?= (int)$b['end_receipt_no'] ?></td>
                        <td><?= $total ?></td>
                        <td><?= $used ?></td>
                        <td><?= $rem ?></td>
                        <td><?= number_format((float)$b['collected'], 2) ?></td>
                        <td>
                            <?= $b['status'] === 'open'
                                ? '<span class="badge badge-success">مفتوح</span>'
                                : '<span class="badge badge-danger">مغلق</span>' ?>
                        </td>

                        <td class="wrap" style="text-align:right;white-space:normal;">
                            <?php if (!empty($catTotals[$bid])): ?>
                                <?php foreach ($catTotals[$bid] as $ct): ?>
                                    <div style="display:flex;justify-content:space-between;gap:10px;">
                                        <span><?= e($ct['category_name'] ?: '—') ?></span>
                                        <strong><?= number_format((float)$ct['total_amount'], 2) ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="muted">لا يوجد</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="form-actions" style="justify-content:center;">
                                <a class="btn btn-light" href="income_entry.php?book_id=<?= $bid ?>">+ إدخال وصل</a>
                                <a class="btn btn-light" href="receipt_book_view.php?id=<?= $bid ?>">عرض</a>
                                <a class="btn btn-light" href="receipt_book_edit.php?id=<?= $bid ?>">تعديل</a>
                                <a class="btn btn-light" href="receipt_book_print.php?id=<?= $bid ?>" target="_blank">طباعة</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="10" class="empty-state">لا توجد دفاتر وصولات بعد</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<div class="footer-note">© <?= e(date('Y')) ?> — قسم المالية</div>

</div>
</body>
</html>