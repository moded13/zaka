<?php
require_once 'bootstrap.php';

$page_title = 'دفتر الإيرادات';

$categories = $pdo->query("SELECT * FROM income_categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$category = $_GET['category'] ?? '';
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT i.*, c.name AS category_name
    FROM finance_income i
    LEFT JOIN income_categories c ON i.category_id = c.id
    WHERE 1=1
";
$params = [];

if ($from !== '') {
    $sql .= " AND i.income_date >= ?";
    $params[] = $from;
}

if ($to !== '') {
    $sql .= " AND i.income_date <= ?";
    $params[] = $to;
}

if ($category !== '') {
    $sql .= " AND i.category_id = ?";
    $params[] = $category;
}

if ($search !== '') {
    $sql .= " AND (i.receipt_no LIKE ? OR i.donor_name LIKE ? OR i.notes LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY i.id DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

require 'layout.php';
?>

<div class="card">
    <h2>إضافة إيراد جديد</h2>

    <form method="post" action="income_store.php">
        <div class="row">
            <div>
                <label>رقم الوصول *</label>
                <input type="text" name="receipt_no" required>
            </div>

            <div>
                <label>التاريخ *</label>
                <input type="date" name="income_date" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div>
                <label>اسم المتبرع</label>
                <input type="text" name="donor_name">
            </div>

            <div>
                <label>نوع الإيراد *</label>
                <select name="category_id" required>
                    <option value="">-- اختر --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>المبلغ *</label>
                <input type="number" name="amount" step="0.01" min="0.01" required>
            </div>

            <div>
                <label>طريقة الدفع</label>
                <input type="text" name="payment_method" placeholder="نقد / تحويل / شيك">
            </div>
        </div>

        <div>
            <label>ملاحظات</label>
            <textarea name="notes"></textarea>
        </div>

        <button type="submit" class="btn btn-success">حفظ الإيراد</button>
    </form>
</div>

<div class="card">
    <h2>فلترة الإيرادات</h2>

    <form method="get">
        <div class="row">
            <div>
                <label>من تاريخ</label>
                <input type="date" name="from" value="<?= e($from) ?>">
            </div>

            <div>
                <label>إلى تاريخ</label>
                <input type="date" name="to" value="<?= e($to) ?>">
            </div>

            <div>
                <label>التصنيف</label>
                <select name="category">
                    <option value="">الكل</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>" <?= ($category == $cat['id']) ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>بحث</label>
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="رقم الوصول / اسم المتبرع / ملاحظات">
            </div>
        </div>

        <button type="submit" class="btn btn-primary">تطبيق</button>
        <a href="income.php" class="btn btn-secondary">مسح</a>
    </form>
</div>

<div class="card">
    <h2>دفتر الإيرادات</h2>

    <table>
        <tr>
            <th>#</th>
            <th>رقم الوصول</th>
            <th>التاريخ</th>
            <th>المتبرع</th>
            <th>التصنيف</th>
            <th>المبلغ</th>
            <th>طريقة الدفع</th>
            <th>ملاحظات</th>
            <th>إجراءات</th>
        </tr>

        <?php if ($rows): ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= (int)$row['id'] ?></td>
                    <td><?= e($row['receipt_no']) ?></td>
                    <td><?= e($row['income_date']) ?></td>
                    <td><?= e($row['donor_name']) ?></td>
                    <td><?= e($row['category_name']) ?></td>
                    <td><?= number_format((float)$row['amount'], 2) ?></td>
                    <td><?= e($row['payment_method']) ?></td>
                    <td><?= e($row['notes']) ?></td>
<td class="actions">
    <a class="btn btn-light" target="_blank" href="income_print.php?id=<?= (int)$row['id'] ?>">طباعة</a>
    <a class="btn btn-warning" href="income_edit.php?id=<?= (int)$row['id'] ?>">تعديل</a>
    <a class="btn btn-danger" href="income_delete.php?id=<?= (int)$row['id'] ?>" onclick="return confirm('هل أنت متأكد من حذف هذا الإيراد؟');">حذف</a>
</td>                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="9">لا توجد بيانات</td></tr>
        <?php endif; ?>
    </table>
</div>

</div>
</body>
</html>