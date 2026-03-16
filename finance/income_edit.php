<?php
require_once 'bootstrap.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    set_flash('error', 'المعرّف غير صحيح');
    redirect('income.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $receipt_no     = trim($_POST['receipt_no'] ?? '');
    $income_date    = trim($_POST['income_date'] ?? '');
    $donor_name     = trim($_POST['donor_name'] ?? '');
    $category_id    = (int)($_POST['category_id'] ?? 0);
    $amount         = (float)($_POST['amount'] ?? 0);
    $payment_method = trim($_POST['payment_method'] ?? '');
    $notes          = trim($_POST['notes'] ?? '');

    if ($receipt_no === '' || $income_date === '' || $category_id <= 0 || $amount <= 0) {
        set_flash('error', 'الرجاء تعبئة الحقول المطلوبة');
        redirect('income_edit.php?id=' . $id);
    }

    $check = $pdo->prepare("SELECT COUNT(*) FROM finance_income WHERE receipt_no = ? AND id != ?");
    $check->execute([$receipt_no, $id]);
    if ($check->fetchColumn() > 0) {
        set_flash('error', 'رقم الوصول مستخدم في سجل آخر');
        redirect('income_edit.php?id=' . $id);
    }

    $stmt = $pdo->prepare(
        "UPDATE finance_income
         SET receipt_no = ?, income_date = ?, donor_name = ?, category_id = ?,
             amount = ?, payment_method = ?, notes = ?
         WHERE id = ?"
    );
    $stmt->execute([$receipt_no, $income_date, $donor_name, $category_id, $amount, $payment_method, $notes, $id]);

    set_flash('success', 'تم تعديل الإيراد بنجاح');
    redirect('income.php');
}

$stmt = $pdo->prepare("SELECT * FROM finance_income WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    set_flash('error', 'السجل غير موجود');
    redirect('income.php');
}

$categories = $pdo->query("SELECT * FROM income_categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

$page_title = 'تعديل الإيراد';
require 'layout.php';
?>

<div class="card">
    <h2>تعديل الإيراد</h2>

    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

        <div class="row">
            <div>
                <label>رقم الوصول *</label>
                <input type="text" name="receipt_no" value="<?= e($row['receipt_no']) ?>" required>
            </div>
            <div>
                <label>التاريخ *</label>
                <input type="date" name="income_date" value="<?= e($row['income_date']) ?>" required>
            </div>
            <div>
                <label>اسم المتبرع</label>
                <input type="text" name="donor_name" value="<?= e($row['donor_name']) ?>">
            </div>
            <div>
                <label>نوع الإيراد *</label>
                <select name="category_id" required>
                    <option value="">-- اختر --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>" <?= ($row['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>المبلغ *</label>
                <input type="number" name="amount" step="0.01" min="0.01" value="<?= e($row['amount']) ?>" required>
            </div>
            <div>
                <label>طريقة الدفع</label>
                <input type="text" name="payment_method" value="<?= e($row['payment_method']) ?>">
            </div>
        </div>

        <div>
            <label>ملاحظات</label>
            <textarea name="notes"><?= e($row['notes']) ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-success">حفظ التعديل</button>
            <a href="income.php" class="btn btn-secondary">رجوع</a>
        </div>
    </form>
</div>

</div>
</body>
</html>
