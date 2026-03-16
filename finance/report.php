<?php
require_once 'bootstrap.php';

$page_title = 'التقرير المالي';

// الفلاتر
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

// حماية بسيطة
if ($from === '') {
    $from = date('Y-m-01');
}
if ($to === '') {
    $to = date('Y-m-d');
}

// الإجماليات
$stmt = $pdo->prepare("
    SELECT IFNULL(SUM(amount), 0)
    FROM finance_income
    WHERE income_date BETWEEN ? AND ?
");
$stmt->execute([$from, $to]);
$totalIncome = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT IFNULL(SUM(amount), 0)
    FROM finance_expenses
    WHERE expense_date BETWEEN ? AND ?
");
$stmt->execute([$from, $to]);
$totalExpenses = (float)$stmt->fetchColumn();

$balance = $totalIncome - $totalExpenses;

// الإيرادات المفصلة
$stmt = $pdo->prepare("
    SELECT 
        i.receipt_no,
        i.income_date,
        i.donor_name,
        i.amount,
        i.payment_method,
        i.notes,
        c.name AS category_name
    FROM finance_income i
    LEFT JOIN income_categories c ON i.category_id = c.id
    WHERE i.income_date BETWEEN ? AND ?
    ORDER BY i.income_date ASC, i.id ASC
");
$stmt->execute([$from, $to]);
$incomeRows = $stmt->fetchAll();

// المدفوعات المفصلة
$stmt = $pdo->prepare("
    SELECT 
        e.voucher_no,
        e.expense_date,
        e.beneficiary_name,
        e.amount,
        e.payment_method,
        e.notes,
        c.name AS category_name
    FROM finance_expenses e
    LEFT JOIN expense_categories c ON e.category_id = c.id
    WHERE e.expense_date BETWEEN ? AND ?
    ORDER BY e.expense_date ASC, e.id ASC
");
$stmt->execute([$from, $to]);
$expenseRows = $stmt->fetchAll();

// ملخص الإيرادات حسب النوع
$stmt = $pdo->prepare("
    SELECT 
        c.name AS category_name,
        COUNT(i.id) AS total_count,
        IFNULL(SUM(i.amount), 0) AS total_amount
    FROM income_categories c
    LEFT JOIN finance_income i 
        ON i.category_id = c.id
        AND i.income_date BETWEEN ? AND ?
    GROUP BY c.id, c.name
    ORDER BY c.name ASC
");
$stmt->execute([$from, $to]);
$incomeSummary = $stmt->fetchAll();

// ملخص المدفوعات حسب النوع
$stmt = $pdo->prepare("
    SELECT 
        c.name AS category_name,
        COUNT(e.id) AS total_count,
        IFNULL(SUM(e.amount), 0) AS total_amount
    FROM expense_categories c
    LEFT JOIN finance_expenses e 
        ON e.category_id = c.id
        AND e.expense_date BETWEEN ? AND ?
    GROUP BY c.id, c.name
    ORDER BY c.name ASC
");
$stmt->execute([$from, $to]);
$expenseSummary = $stmt->fetchAll();

require 'layout.php';
?>

<style>
    .report-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 15px;
    }

    .report-meta {
        margin-top: 10px;
        color: #555;
        font-size: 15px;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 15px;
    }

    .summary-box {
        border-radius: 12px;
        padding: 18px;
        color: #fff;
        font-size: 18px;
        font-weight: bold;
    }

    .summary-box.green { background: #118a7e; }
    .summary-box.red { background: #d63031; }
    .summary-box.blue { background: #2d6cdf; }

    .print-only {
        display: none;
    }

    @media print {
        body {
            background: #fff !important;
        }

        .topbar,
        .report-actions,
        form,
        .btn {
            display: none !important;
        }

        .container {
            width: 100%;
            max-width: 100%;
            margin: 0;
            padding: 0;
        }

        .card {
            box-shadow: none !important;
            border: 1px solid #ccc;
            margin-bottom: 15px;
            page-break-inside: avoid;
        }

        .print-only {
            display: block;
            text-align: center;
            margin-bottom: 20px;
        }

        .print-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .print-subtitle {
            font-size: 16px;
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th,
        table td {
            border: 1px solid #999 !important;
            padding: 8px !important;
            font-size: 12px;
        }

        h2 {
            font-size: 20px;
            margin-bottom: 10px;
        }
    }
</style>

<div class="print-only">
    <div class="print-title">التقرير المالي</div>
    <div class="print-subtitle">
        الفترة من <?= e($from) ?> إلى <?= e($to) ?>
    </div>
</div>

<div class="card">
    <h2>فلترة التقرير</h2>

    <form method="get" action="report.php">
        <div class="row">
            <div>
                <label>من تاريخ</label>
                <input type="date" name="from" value="<?= e($from) ?>" required>
            </div>

            <div>
                <label>إلى تاريخ</label>
                <input type="date" name="to" value="<?= e($to) ?>" required>
            </div>
        </div>

        <div class="report-actions">
            <button type="submit" class="btn btn-primary">عرض التقرير</button>
            <button type="button" class="btn btn-secondary" onclick="window.print();">طباعة</button>
            <a href="report.php" class="btn btn-warning">إعادة تعيين</a>
        </div>
    </form>

    <div class="report-meta">
        الفترة الحالية: من <strong><?= e($from) ?></strong> إلى <strong><?= e($to) ?></strong>
    </div>
</div>

<div class="card">
    <h2>الملخص العام</h2>

    <div class="summary-grid">
        <div class="summary-box green">
            إجمالي الإيرادات<br>
            <?= number_format($totalIncome, 2) ?>
        </div>

        <div class="summary-box red">
            إجمالي المدفوعات<br>
            <?= number_format($totalExpenses, 2) ?>
        </div>

        <div class="summary-box blue">
            الرصيد<br>
            <?= number_format($balance, 2) ?>
        </div>
    </div>
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

<div class="card">
    <h2>تفصيل الإيرادات</h2>

    <table>
        <tr>
            <th>#</th>
            <th>رقم الوصول</th>
            <th>التاريخ</th>
            <th>اسم المتبرع</th>
            <th>النوع</th>
            <th>المبلغ</th>
            <th>طريقة الدفع</th>
            <th>ملاحظات</th>
        </tr>

        <?php if ($incomeRows): ?>
            <?php foreach ($incomeRows as $index => $row): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= e($row['receipt_no']) ?></td>
                    <td><?= e($row['income_date']) ?></td>
                    <td><?= e($row['donor_name']) ?></td>
                    <td><?= e($row['category_name']) ?></td>
                    <td><?= number_format((float)$row['amount'], 2) ?></td>
                    <td><?= e($row['payment_method']) ?></td>
                    <td><?= e($row['notes']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="8">لا توجد إيرادات ضمن هذه الفترة</td>
            </tr>
        <?php endif; ?>
    </table>
</div>

<div class="card">
    <h2>تفصيل المدفوعات</h2>

    <table>
        <tr>
            <th>#</th>
            <th>رقم السند</th>
            <th>التاريخ</th>
            <th>اسم المستفيد</th>
            <th>النوع</th>
            <th>المبلغ</th>
            <th>طريقة الدفع</th>
            <th>ملاحظات</th>
        </tr>

        <?php if ($expenseRows): ?>
            <?php foreach ($expenseRows as $index => $row): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= e($row['voucher_no']) ?></td>
                    <td><?= e($row['expense_date']) ?></td>
                    <td><?= e($row['beneficiary_name']) ?></td>
                    <td><?= e($row['category_name']) ?></td>
                    <td><?= number_format((float)$row['amount'], 2) ?></td>
                    <td><?= e($row['payment_method']) ?></td>
                    <td><?= e($row['notes']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="8">لا توجد مدفوعات ضمن هذه الفترة</td>
            </tr>
        <?php endif; ?>
    </table>
</div>

</div>
</body>
</html>