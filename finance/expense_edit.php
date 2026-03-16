<?php
require_once 'bootstrap.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    set_flash('error', 'المعرّف غير صحيح');
    redirect('expenses.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $voucher_no = trim($_POST['voucher_no'] ?? '');
    $expense_date = trim($_POST['expense_date'] ?? '');
    $beneficiary_name = trim($_POST['beneficiary_name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_method = trim($_POST['payment_method'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($voucher_no === '' || $expense_date === '' || $category_id <= 0 || $amount <= 0) {
        set_flash('error', 'الرجاء تعبئة الحقول المطلوبة');
        redirect('expense_edit.php?id=' . $id);
    }

    $check = $pdo->prepare("SELECT COUNT(*) FROM finance_expenses WHERE voucher_no = ? AND id != ?");
    $check->execute([$voucher_no, $id]);

    if ($check->fetchColumn() > 0) {
        set_flash('error', 'رقم السند مستخدم في سجل آخر');
        redirect('expense_edit.php?id=' . $id);
    }

    $stmt = $pdo->prepare("
        UPDATE finance_expenses
        SET voucher_no = ?, expense_date = ?, beneficiary_name = ?, category_id = ?, amount = ?, payment_method = ?, notes = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $voucher_no,
        $expense_date,
        $beneficiary_name,
        $category_id,
        $amount,
        $payment_method,
        $notes,
        $id
    ]);

    set_flash('success', 'تم تعديل المدفوع بنجاح');
    redirect('expenses.php');
}

$stmt = $pdo->prepare("SELECT * FROM finance_expenses WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    set_flash('error', 'السجل غير موجود');
    redirect('expenses.php');
}

$categories = $pdo->query("SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

$page_title = 'تعديل المدفوع';
require 'layout.php';
?>

<div class="card">
    <h2>تعديل المدفوع</h2>

    <form method="post">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

        <div class="row">
            <div>
                <label>رقم السند *</label>
                <input type="text" name="voucher_no" value="<?= e($row['voucher_no']) ?>" required>
            </div>

            <div>
                <label>التاريخ *</label>
                <input type="date" name="expense_date" value="<?= e($row['expense_date']) ?>" required>
            </div>

            <div>
                <label>اسم المستفيد</label>
                <input type="text" name="beneficiary_name" value="<?= e($row['beneficiary_name']) ?>">
            </div>

            <div>
                <label>نوع المدفوع *</label>
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

        <button type="submit" class="btn btn-success">حفظ التعديل</button>
        <a href="expenses.php" class="btn btn-secondary">رجوع</a>
    </form>
</div>

</div>
</body>
</html>