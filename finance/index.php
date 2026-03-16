<?php
require_once 'bootstrap.php';

$page_title = 'الرئيسية';

// الملخص العام
$totalIncome = (float)$pdo->query("SELECT IFNULL(SUM(amount), 0) FROM finance_income")->fetchColumn();
$totalExpenses = (float)$pdo->query("SELECT IFNULL(SUM(amount), 0) FROM finance_expenses")->fetchColumn();
$balance = $totalIncome - $totalExpenses;

$incomeCount = (int)$pdo->query("SELECT COUNT(*) FROM finance_income")->fetchColumn();
$expenseCount = (int)$pdo->query("SELECT COUNT(*) FROM finance_expenses")->fetchColumn();

// آخر الإيرادات
$latestIncome = $pdo->query("
    SELECT 
        i.receipt_no,
        i.income_date,
        i.donor_name,
        i.amount,
        c.name AS category_name
    FROM finance_income i
    LEFT JOIN income_categories c ON i.category_id = c.id
    ORDER BY i.id DESC
    LIMIT 5
")->fetchAll();

// آخر المدفوعات
$latestExpenses = $pdo->query("
    SELECT 
        e.voucher_no,
        e.expense_date,
        e.beneficiary_name,
        e.amount,
        c.name AS category_name
    FROM finance_expenses e
    LEFT JOIN expense_categories c ON e.category_id = c.id
    ORDER BY e.id DESC
    LIMIT 5
")->fetchAll();

// الإيرادات حسب التصنيف
$incomeSummary = $pdo->query("
    SELECT 
        c.name AS category_name,
        COUNT(i.id) AS total_count,
        IFNULL(SUM(i.amount), 0) AS total_amount
    FROM income_categories c
    LEFT JOIN finance_income i ON i.category_id = c.id
    GROUP BY c.id, c.name
    ORDER BY c.name ASC
")->fetchAll();

// المدفوعات حسب التصنيف
$expenseSummary = $pdo->query("
    SELECT 
        c.name AS category_name,
        COUNT(e.id) AS total_count,
        IFNULL(SUM(e.amount), 0) AS total_amount
    FROM expense_categories c
    LEFT JOIN finance_expenses e ON e.category_id = c.id
    GROUP BY c.id, c.name
    ORDER BY c.name ASC
")->fetchAll();

require 'layout.php';
?>

<div class="card">
    <h2>الملخص المالي</h2>

    <div class="grid">
        <div class="stat green">
            <div>إجمالي الإيرادات</div>
            <strong><?= number_format($totalIncome, 2) ?></strong>
        </div>

        <div class="stat red">
            <div>إجمالي المدفوعات</div>
            <strong><?= number_format($totalExpenses, 2) ?></strong>
        </div>

        <div class="stat blue">
            <div>الرصيد الحالي</div>
            <strong><?= number_format($balance, 2) ?></strong>
        </div>

        <div class="stat dark">
            <div>عدد القيود</div>
            <strong><?= $incomeCount + $expenseCount ?></strong>
        </div>
    </div>

    <div class="grid" style="margin-top:15px;">
        <div class="card" style="margin-bottom:0;">
            <h2 style="font-size:18px; margin-bottom:8px;">عدد الوصولات</h2>
            <div style="font-size:28px; font-weight:bold; color:#118a7e;"><?= $incomeCount ?></div>
        </div>

        <div class="card" style="margin-bottom:0;">
            <h2 style="font-size:18px; margin-bottom:8px;">عدد سندات الصرف</h2>
            <div style="font-size:28px; font-weight:bold; color:#d63031;"><?= $expenseCount ?></div>
        </div>
    </div>
</div>

<div class="card">
    <h2>آخر الإيرادات</h2>

    <table>
        <tr>
            <th>رقم الوصول</th>
            <th>التاريخ</th>
            <th>المتبرع</th>
            <th>التصنيف</th>
            <th>المبلغ</th>
        </tr>

        <?php if ($latestIncome): ?>
            <?php foreach ($latestIncome as $row): ?>
                <tr>
                    <td><?= e($row['receipt_no']) ?></td>
                    <td><?= e($row['income_date']) ?></td>
                    <td><?= e($row['donor_name']) ?></td>
                    <td><?= e($row['category_name']) ?></td>
                    <td><?= number_format((float)$row['amount'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="5">لا توجد إيرادات بعد</td>
            </tr>
        <?php endif; ?>
    </table>
</div>

<div class="card">
    <h2>آخر المدفوعات</h2>

    <table>
        <tr>
            <th>رقم السند</th>
            <th>التاريخ</th>
            <th>المستفيد</th>
            <th>التصنيف</th>
            <th>المبلغ</th>
        </tr>

        <?php if ($latestExpenses): ?>
            <?php foreach ($latestExpenses as $row): ?>
                <tr>
                    <td><?= e($row['voucher_no']) ?></td>
                    <td><?= e($row['expense_date']) ?></td>
                    <td><?= e($row['beneficiary_name']) ?></td>
                    <td><?= e($row['category_name']) ?></td>
                    <td><?= number_format((float)$row['amount'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="5">لا توجد مدفوعات بعد</td>
            </tr>
        <?php endif; ?>
    </table>
</div>

<div class="grid">
    <div class="card">
        <h2>الإيرادات حسب النوع</h2>

        <table>
            <tr>
                <th>النوع</th>
                <th>عدد الوصولات</th>
                <th>المجموع</th>
            </tr>

            <?php if ($incomeSummary): ?>
                <?php foreach ($incomeSummary as $row): ?>
                    <tr>
                        <td><?= e($row['category_name']) ?></td>
                        <td><?= (int)$row['total_count'] ?></td>
                        <td><?= number_format((float)$row['total_amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3">لا توجد بيانات</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="card">
        <h2>المدفوعات حسب النوع</h2>

        <table>
            <tr>
                <th>النوع</th>
                <th>عدد السندات</th>
                <th>المجموع</th>
            </tr>

            <?php if ($expenseSummary): ?>
                <?php foreach ($expenseSummary as $row): ?>
                    <tr>
                        <td><?= e($row['category_name']) ?></td>
                        <td><?= (int)$row['total_count'] ?></td>
                        <td><?= number_format((float)$row['total_amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3">لا توجد بيانات</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

</div>
</body>
</html>