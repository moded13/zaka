<?php
require_once 'bootstrap.php';
require_once 'helpers.php';

if (!receipt_books_exist()) {
    redirect('receipt_books.php');
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    set_flash('error', 'المعرّف غير صحيح');
    redirect('receipt_books.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $book_no       = trim($_POST['book_no'] ?? '');
    $start_receipt = (int)($_POST['start_receipt_no'] ?? 0);
    $end_receipt   = (int)($_POST['end_receipt_no'] ?? 0);
    $received_date = trim($_POST['received_date'] ?? '');
    $status        = in_array($_POST['status'] ?? '', ['open', 'closed']) ? $_POST['status'] : 'open';
    $notes         = trim($_POST['notes'] ?? '');

    if ($book_no === '' || $start_receipt <= 0 || $end_receipt <= 0 || $received_date === '') {
        set_flash('error', 'الرجاء تعبئة جميع الحقول المطلوبة');
        redirect('receipt_book_edit.php?id=' . $id);
    }

    if ($end_receipt < $start_receipt) {
        set_flash('error', 'رقم الوصول الأخير يجب أن يكون أكبر من أو مساوياً للأول');
        redirect('receipt_book_edit.php?id=' . $id);
    }

    $check = $pdo->prepare("SELECT COUNT(*) FROM receipt_books WHERE book_no = ? AND id != ?");
    $check->execute([$book_no, $id]);
    if ($check->fetchColumn() > 0) {
        set_flash('error', 'رقم الدفتر مستخدم في سجل آخر');
        redirect('receipt_book_edit.php?id=' . $id);
    }

    $total_receipts = $end_receipt - $start_receipt + 1;

    $stmt = $pdo->prepare(
        "UPDATE receipt_books
         SET book_no = ?, start_receipt_no = ?, end_receipt_no = ?, total_receipts = ?,
             received_date = ?, status = ?, notes = ?
         WHERE id = ?"
    );
    $stmt->execute([$book_no, $start_receipt, $end_receipt, $total_receipts, $received_date, $status, $notes, $id]);

    set_flash('success', 'تم تعديل الدفتر بنجاح');
    redirect('receipt_books.php');
}

$stmt = $pdo->prepare("SELECT * FROM receipt_books WHERE id = ?");
$stmt->execute([$id]);
$book = $stmt->fetch();

if (!$book) {
    set_flash('error', 'الدفتر غير موجود');
    redirect('receipt_books.php');
}

$page_title = 'تعديل دفتر الوصولات';
require 'layout.php';
?>

<div class="card">
    <h2>تعديل دفتر الوصولات</h2>

    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= (int)$book['id'] ?>">

        <div class="row">
            <div>
                <label>رقم الدفتر *</label>
                <input type="text" name="book_no" value="<?= e($book['book_no']) ?>" required>
            </div>
            <div>
                <label>رقم الوصول الأول *</label>
                <input type="number" name="start_receipt_no" value="<?= (int)$book['start_receipt_no'] ?>" required min="1">
            </div>
            <div>
                <label>رقم الوصول الأخير *</label>
                <input type="number" name="end_receipt_no" value="<?= (int)$book['end_receipt_no'] ?>" required min="1">
            </div>
            <div>
                <label>تاريخ الاستلام *</label>
                <input type="date" name="received_date" value="<?= e($book['received_date']) ?>" required>
            </div>
            <div>
                <label>الحالة</label>
                <select name="status">
                    <option value="open" <?= $book['status'] === 'open' ? 'selected' : '' ?>>مفتوح</option>
                    <option value="closed" <?= $book['status'] === 'closed' ? 'selected' : '' ?>>مغلق</option>
                </select>
            </div>
        </div>

        <div>
            <label>ملاحظات</label>
            <textarea name="notes"><?= e($book['notes']) ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-success">حفظ التعديل</button>
            <a href="receipt_books.php" class="btn btn-secondary">رجوع</a>
        </div>
    </form>
</div>

</div>
</body>
</html>
