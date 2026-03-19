<?php
require_once 'bootstrap.php';
require_once 'helpers.php';

$page_title = 'الرئيسية';

$totalIncome   = (float)$pdo->query("SELECT IFNULL(SUM(amount), 0) FROM finance_income")->fetchColumn();
$totalExpenses = (float)$pdo->query("SELECT IFNULL(SUM(amount), 0) FROM finance_expenses")->fetchColumn();
$balance       = $totalIncome - $totalExpenses;
$incomeCount   = (int)$pdo->query("SELECT COUNT(*) FROM finance_income")->fetchColumn();
$expenseCount  = (int)$pdo->query("SELECT COUNT(*) FROM finance_expenses")->fetchColumn();

$latestIncome = $pdo->query("
    SELECT i.receipt_no, i.income_date, i.donor_name, i.amount, c.name AS category_name
    FROM finance_income i
    LEFT JOIN income_categories c ON i.category_id = c.id
    ORDER BY i.id DESC LIMIT 5
")->fetchAll();

$latestExpenses = $pdo->query("
    SELECT e.voucher_no, e.expense_date, e.beneficiary_name, e.amount, c.name AS category_name
    FROM finance_expenses e
    LEFT JOIN expense_categories c ON e.category_id = c.id
    ORDER BY e.id DESC LIMIT 5
")->fetchAll();

$incomeSummary = $pdo->query("
    SELECT c.name AS category_name, COUNT(i.id) AS total_count, IFNULL(SUM(i.amount), 0) AS total_amount
    FROM income_categories c
    LEFT JOIN finance_income i ON i.category_id = c.id
    GROUP BY c.id, c.name ORDER BY c.name ASC
")->fetchAll();

$expenseSummary = $pdo->query("
    SELECT c.name AS category_name, COUNT(e.id) AS total_count, IFNULL(SUM(e.amount), 0) AS total_amount
    FROM expense_categories c
    LEFT JOIN finance_expenses e ON e.category_id = c.id
    GROUP BY c.id, c.name ORDER BY c.name ASC
")->fetchAll();

// Receipt books summary (graceful if not migrated)
$booksCount     = 0;
$openBooksCount = 0;
$booksTotal     = 0.0;
$recentBooks    = [];
if (receipt_books_exist()) {
    $booksCount     = (int)$pdo->query("SELECT COUNT(*) FROM receipt_books")->fetchColumn();
    $openBooksCount = (int)$pdo->query("SELECT COUNT(*) FROM receipt_books WHERE status = 'open'")->fetchColumn();

    // Only join if column exists
    if (book_id_exists_in_income()) {
        $booksTotal = (float)$pdo->query(
            "SELECT IFNULL(SUM(fi.amount), 0)
             FROM finance_income fi
             INNER JOIN receipt_books rb ON fi.book_id = rb.id"
        )->fetchColumn();
    }

    $recentBooks = $pdo->query(
        "SELECT rb.*,
                COUNT(fi.id) AS used_receipts,
                IFNULL(SUM(fi.amount), 0) AS collected
         FROM receipt_books rb
         LEFT JOIN finance_income fi ON fi.book_id = rb.id
         GROUP BY rb.id
         ORDER BY rb.id DESC LIMIT 5"
    )->fetchAll();
}

require 'layout.php';
?>

<div class="card">
    <h2>الملخص المالي</h2>
    <div class="section-subtitle">نظرة عامة سريعة على الإيرادات والمدفوعات ودفاتر الوصولات</div>

    <div class="grid">
        <div class="stat green col-3">
            <div class="label">إجمالي الإيرادات</div>
            <div class="value"><?= number_format($totalIncome, 2) ?></div>
            <div class="sub">عدد الوصولات: <?= (int)$incomeCount ?></div>
        </div>

        <div class="stat red col-3">
            <div class="label">إجمالي المدفوعات</div>
            <div class="value"><?= number_format($totalExpenses, 2) ?></div>
            <div class="sub">عدد السندات: <?= (int)$expenseCount ?></div>
        </div>

        <div class="stat blue col-3">
            <div class="label">الرصيد الحالي</div>
            <div class="value"><?= number_format($balance, 2) ?></div>
            <div class="sub"><?= $balance >= 0 ? 'رصيد دائن' : 'رصيد مدين' ?></div>
        </div>

        <div class="stat dark col-3">
            <div class="label">إجمالي القيود</div>
            <div class="value"><?= (int)($incomeCount + $expenseCount) ?></div>
            <div class="sub">إيرادات + مدفوعات</div>
        </div>
    </div>

    <div class="divider"></div>

    <div class="grid">
        <div class="card col-4" style="margin-bottom:0;">
            <h3 style="margin:0 0 8px;font-size:18px;">عدد الوصولات</h3>
            <div style="font-size:30px;font-weight:800;color:var(--success);"><?= (int)$incomeCount ?></div>
            <div class="muted">إجمالي وصولات الإيرادات المسجلة</div>
        </div>

        <div class="card col-4" style="margin-bottom:0;">
            <h3 style="margin:0 0 8px;font-size:18px;">عدد سندات الصرف</h3>
            <div style="font-size:30px;font-weight:800;color:var(--danger);"><?= (int)$expenseCount ?></div>
            <div class="muted">إجمالي سندات الصرف المسجلة</div>
        </div>

        <?php if ($booksCount > 0): ?>
        <div class="card col-4" style="margin-bottom:0;">
            <h3 style="margin:0 0 8px;font-size:18px;">دفاتر الوصولات</h3>
            <div style="font-size:30px;font-weight:800;color:var(--primary);"><?= (int)$booksCount ?></div>
            <div class="muted">مفتوح: <?= (int)$openBooksCount ?><?= $booksTotal ? ' — المُحصّل: ' . number_format((float)$booksTotal, 2) : '' ?></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($recentBooks): ?>
<div class="card">
    <h2>دفاتر الوصولات الأخيرة</h2>
    <div class="section-subtitle">آخر 5 دفاتر مع الإحصائيات الأساسية</div>

    <div class="table-wrap">
        <table>
            <tr>
                <th>رقم الدفتر</th>
                <th>النطاق</th>
                <th>الإجمالي</th>
                <th>المستخدم</th>
                <th>المُحصّل</th>
                <th>الحالة</th>
                <th>إجراء</th>
            </tr>
            <?php foreach ($recentBooks as $bk): ?>
            <tr>
                <td><?= e($bk['book_no']) ?></td>
                <td><?= (int)$bk['start_receipt_no'] ?>–<?= (int)$bk['end_receipt_no'] ?></td>
                <td><?= (int)$bk['total_receipts'] ?></td>
                <td><?= (int)$bk['used_receipts'] ?></td>
                <td><?= number_format((float)$bk['collected'], 2) ?></td>
                <td><?= $bk['status'] === 'open'
                    ? '<span class="badge badge-success">مفتوح</span>'
                    : '<span class="badge badge-danger">مغلق</span>' ?></td>
                <td><a href="receipt_book_view.php?id=<?= (int)$bk['id'] ?>" class="btn btn-light">عرض</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div style="margin-top:12px;">
        <a href="receipt_books.php" class="btn btn-primary">جميع الدفاتر</a>
    </div>
</div>
<?php endif; ?>

<div class="grid">
    <div class="card col-6">
        <h2>آخر الإيرادات</h2>
        <div class="section-subtitle">آخر 5 وصولات تم تسجيلها</div>

        <div class="table-wrap">
            <table>
                <tr>
                    <th>رقم الوصول</th>
                    <th>التاريخ</th>
                    <th class="wrap">المتبرع</th>
                    <th>التصنيف</th>
                    <th>المبلغ</th>
                </tr>

                <?php if ($latestIncome): ?>
                    <?php foreach ($latestIncome as $row): ?>
                        <tr>
                            <td><?= e($row['receipt_no']) ?></td>
                            <td><?= e($row['income_date']) ?></td>
                            <td class="wrap"><?= e($row['donor_name']) ?></td>
                            <td><?= e($row['category_name']) ?></td>
                            <td><?= number_format((float)$row['amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="empty-state">لا توجد إيرادات بعد</td></tr>
                <?php endif; ?>
            </table>
        </div>

        <div style="margin-top:12px;">
            <a class="btn btn-primary" href="income.php">فتح دفتر الإيرادات</a>
        </div>
    </div>

    <div class="card col-6">
        <h2>آخر المدفوعات</h2>
        <div class="section-subtitle">آخر 5 سندات صرف تم تسجيلها</div>

        <div class="table-wrap">
            <table>
                <tr>
                    <th>رقم السند</th>
                    <th>التاريخ</th>
                    <th class="wrap">المستفيد</th>
                    <th>التصنيف</th>
                    <th>المبلغ</th>
                </tr>

                <?php if ($latestExpenses): ?>
                    <?php foreach ($latestExpenses as $row): ?>
                        <tr>
                            <td><?= e($row['voucher_no']) ?></td>
                            <td><?= e($row['expense_date']) ?></td>
                            <td class="wrap"><?= e($row['beneficiary_name']) ?></td>
                            <td><?= e($row['category_name']) ?></td>
                            <td><?= number_format((float)$row['amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="empty-state">لا توجد مدفوعات بعد</td></tr>
                <?php endif; ?>
            </table>
        </div>

        <div style="margin-top:12px;">
            <a class="btn btn-primary" href="expenses.php">فتح دفتر المدفوعات</a>
        </div>
    </div>
</div>

<div class="grid">
    <div class="card col-6">
        <h2>الإيرادات حسب النوع</h2>
        <div class="section-subtitle">تجميع حسب تصنيف الإيرادات</div>

        <div class="table-wrap">
            <table>
                <tr>
                    <th class="wrap">النوع</th>
                    <th>عدد الوصولات</th>
                    <th>المجموع</th>
                </tr>

                <?php if ($incomeSummary): ?>
                    <?php foreach ($incomeSummary as $row): ?>
                        <tr>
                            <td class="wrap"><?= e($row['category_name']) ?></td>
                            <td><?= (int)$row['total_count'] ?></td>
                            <td><?= number_format((float)$row['total_amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="empty-state">لا توجد بيانات</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <div class="card col-6">
        <h2>المدفوعات حسب النوع</h2>
        <div class="section-subtitle">تجميع حسب تصنيف المدفوعات</div>

        <div class="table-wrap">
            <table>
                <tr>
                    <th class="wrap">النوع</th>
                    <th>عدد السندات</th>
                    <th>المجموع</th>
                </tr>

                <?php if ($expenseSummary): ?>
                    <?php foreach ($expenseSummary as $row): ?>
                        <tr>
                            <td class="wrap"><?= e($row['category_name']) ?></td>
                            <td><?= (int)$row['total_count'] ?></td>
                            <td><?= number_format((float)$row['total_amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="empty-state">لا توجد بيانات</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<div class="footer-note">© <?= e(date('Y')) ?> — قسم المالية</div>

</div>
</body>
</html>