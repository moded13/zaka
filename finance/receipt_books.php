<?php
require_once 'bootstrap.php';
require_once 'helpers.php';

if (!receipt_books_exist()) {
    set_flash('error', 'يجب تشغيل ملف الترحيل (finance_migration.sql) أولاً لتفعيل دفاتر الوصولات.');
    redirect('index.php');
}

$page_title = 'دفاتر الوصولات';

// All books with computed metrics
$books = $pdo->query("
    SELECT rb.*,
           COUNT(fi.id)          AS used_receipts,
           IFNULL(SUM(fi.amount), 0) AS collected_total
    FROM receipt_books rb
    LEFT JOIN finance_income fi ON fi.book_id = rb.id
    GROUP BY rb.id
    ORDER BY rb.id DESC
")->fetchAll();

require 'layout.php';
?>

<div class="card">
    <h2>دفاتر الوصولات</h2>
    <p class="section-subtitle">كل دفتر يحتوي على نطاق من أرقام الوصولات. يمكن ربط وصولات الإيراد بدفتر معين.</p>

    <div style="margin-bottom:18px;">
        <button class="btn btn-primary" onclick="var f=document.getElementById('addForm');f.style.display=f.style.display==='none'?'block':'none'">
            + إضافة دفتر جديد
        </button>
    </div>

    <div id="addForm" style="display:none;background:#f4f7fb;border-radius:14px;padding:20px;margin-bottom:20px;">
        <h3 style="margin-top:0;">إضافة دفتر وصولات</h3>
        <form method="post" action="receipt_book_store.php">
            <?= csrfField() ?>
            <div class="row">
                <div>
                    <label>رقم الدفتر *</label>
                    <input type="text" name="book_no" required placeholder="مثال: 001">
                </div>
                <div>
                    <label>رقم الوصول الأول *</label>
                    <input type="number" name="start_receipt_no" required min="1">
                </div>
                <div>
                    <label>رقم الوصول الأخير *</label>
                    <input type="number" name="end_receipt_no" required min="1">
                </div>
                <div>
                    <label>تاريخ الاستلام *</label>
                    <input type="date" name="received_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div>
                    <label>الحالة</label>
                    <select name="status">
                        <option value="open">مفتوح</option>
                        <option value="closed">مغلق</option>
                    </select>
                </div>
            </div>
            <div>
                <label>ملاحظات</label>
                <textarea name="notes" style="min-height:70px;"></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-success">حفظ الدفتر</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('addForm').style.display='none'">إلغاء</button>
            </div>
        </form>
    </div>

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
            <th>تاريخ الاستلام</th>
            <th>الحالة</th>
            <th>إجراءات</th>
        </tr>

        <?php if ($books): ?>
            <?php foreach ($books as $bk):
                $total     = (int)$bk['total_receipts'];
                $used      = (int)$bk['used_receipts'];
                $remaining = $total - $used;
                $collected = (float)$bk['collected_total'];
            ?>
            <tr>
                <td><strong><?= e($bk['book_no']) ?></strong></td>
                <td><?= (int)$bk['start_receipt_no'] ?></td>
                <td><?= (int)$bk['end_receipt_no'] ?></td>
                <td><?= $total ?></td>
                <td><?= $used ?></td>
                <td><?= $remaining ?></td>
                <td><?= number_format($collected, 2) ?></td>
                <td><?= e($bk['received_date']) ?></td>
                <td>
                    <?= $bk['status'] === 'open'
                        ? '<span class="badge badge-success">مفتوح</span>'
                        : '<span class="badge badge-danger">مغلق</span>' ?>
                </td>
                <td class="actions">
                    <a class="btn btn-light" href="receipt_book_view.php?id=<?= (int)$bk['id'] ?>">عرض</a>
                    <a class="btn btn-warning" href="receipt_book_edit.php?id=<?= (int)$bk['id'] ?>">تعديل</a>
                    <a class="btn btn-light" target="_blank" href="receipt_book_print.php?id=<?= (int)$bk['id'] ?>">طباعة</a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="10" class="empty-state">لا توجد دفاتر وصولات بعد</td></tr>
        <?php endif; ?>
    </table>
    </div>
</div>

</div>
</body>
</html>
