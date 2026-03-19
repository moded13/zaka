<?php
/**
 * receipt_book_edit.php
 * Edit receipt book:
 * - GET shows form
 * - POST updates
 * Compatible with helper function name differences.
 */

require_once 'bootstrap.php';
require_once 'helpers.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    if (function_exists('set_flash')) set_flash('error', 'رقم الدفتر غير صحيح');
    header('Location: receipt_books.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM receipt_books WHERE id = ?");
$stmt->execute([$id]);
$book = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$book) {
    if (function_exists('set_flash')) set_flash('error', 'الدفتر غير موجود');
    header('Location: receipt_books.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (function_exists('verify_csrf')) {
        verify_csrf();
    } elseif (function_exists('verifyCsrf')) {
        verifyCsrf();
    }

    $book_no          = trim((string)($_POST['book_no'] ?? ''));
    $start_receipt_no = (int)($_POST['start_receipt_no'] ?? 0);
    $end_receipt_no   = (int)($_POST['end_receipt_no'] ?? 0);
    $received_date    = trim((string)($_POST['received_date'] ?? ''));
    $status           = trim((string)($_POST['status'] ?? 'open'));
    $notes            = trim((string)($_POST['notes'] ?? ''));

    $errors = [];
    if ($book_no === '') $errors[] = 'رقم الدفتر مطلوب';
    if ($start_receipt_no <= 0) $errors[] = 'رقم الوصول الأول غير صحيح';
    if ($end_receipt_no <= 0) $errors[] = 'رقم الوصول الأخير غير صحيح';
    if ($end_receipt_no < $start_receipt_no) $errors[] = 'رقم الوصول الأخير يجب أن يكون أكبر أو يساوي الأول';
    if ($received_date === '') $errors[] = 'تاريخ الاستلام مطلوب';
    if (!in_array($status, ['open', 'closed'], true)) $errors[] = 'الحالة غير صحيحة';

    if ($errors) {
        $msg = implode(' | ', $errors);
        if (function_exists('set_flash')) set_flash('error', $msg);
        header('Location: receipt_book_edit.php?id=' . $id);
        exit;
    }

    $total_receipts = (int)(($end_receipt_no - $start_receipt_no) + 1);

    // Prevent shrinking range below used receipts (only if finance_income has book_id)
    $chk = $pdo->prepare("SHOW COLUMNS FROM finance_income LIKE 'book_id'");
    $chk->execute();
    $hasBookId = (bool)$chk->fetch();

    if ($hasBookId) {
        $usedStmt = $pdo->prepare(
            "SELECT MIN(receipt_no) AS min_no, MAX(receipt_no) AS max_no, COUNT(*) AS cnt
             FROM finance_income
             WHERE book_id = ?"
        );
        $usedStmt->execute([$id]);
        $used = $usedStmt->fetch(PDO::FETCH_ASSOC);

        if ($used && (int)$used['cnt'] > 0) {
            $minUsed = (int)$used['min_no'];
            $maxUsed = (int)$used['max_no'];
            if ($start_receipt_no > $minUsed || $end_receipt_no < $maxUsed) {
                $msg = "لا يمكن تعديل النطاق ليصبح خارج الوصولات المستخدمة. أول وصول: {$minUsed} — آخر وصول: {$maxUsed}";
                if (function_exists('set_flash')) set_flash('error', $msg);
                header('Location: receipt_book_edit.php?id=' . $id);
                exit;
            }
        }
    }

    try {
        $upd = $pdo->prepare(
            "UPDATE receipt_books
             SET book_no=?,
                 start_receipt_no=?,
                 end_receipt_no=?,
                 total_receipts=?,
                 received_date=?,
                 status=?,
                 notes=?
             WHERE id=?"
        );
        $upd->execute([
            $book_no,
            $start_receipt_no,
            $end_receipt_no,
            $total_receipts,
            $received_date,
            $status,
            ($notes !== '' ? $notes : null),
            $id
        ]);

        if (function_exists('set_flash')) set_flash('success', 'تم تحديث الدفتر بنجاح');
        header('Location: receipt_books.php');
        exit;

    } catch (PDOException $e) {
        $err = (stripos($e->getMessage(), 'Duplicate') !== false)
            ? 'رقم الدفتر موجود مسبقًا'
            : ('خطأ أثناء التحديث: ' . $e->getMessage());
        if (function_exists('set_flash')) set_flash('error', $err);
        header('Location: receipt_book_edit.php?id=' . $id);
        exit;
    }
}

$page_title = 'تعديل دفتر الوصولات';
require 'layout.php';
?>

<div class="card">
    <h2>تعديل دفتر وصولات</h2>
    <div class="section-subtitle">تعديل بيانات الدفتر مع الحفاظ على الوصولات المستخدمة (إن وجدت).</div>

    <form method="post" action="receipt_book_edit.php" style="margin-top:12px;">
        <?php if (function_exists('csrf_field')): ?>
            <?= csrf_field() ?>
        <?php endif; ?>

        <input type="hidden" name="id" value="<?= (int)$book['id'] ?>">

        <div class="grid">
            <div class="col-4">
                <label>رقم الدفتر *</label>
                <input type="text" name="book_no" required value="<?= e($book['book_no']) ?>">
            </div>

            <div class="col-4">
                <label>رقم الوصول الأول *</label>
                <input type="number" name="start_receipt_no" required value="<?= (int)$book['start_receipt_no'] ?>">
            </div>

            <div class="col-4">
                <label>رقم الوصول الأخير *</label>
                <input type="number" name="end_receipt_no" required value="<?= (int)$book['end_receipt_no'] ?>">
            </div>

            <div class="col-4">
                <label>تاريخ الاستلام *</label>
                <input type="date" name="received_date" required value="<?= e($book['received_date']) ?>">
            </div>

            <div class="col-4">
                <label>الحالة *</label>
                <select name="status" required>
                    <option value="open" <?= $book['status'] === 'open' ? 'selected' : '' ?>>مفتوح</option>
                    <option value="closed" <?= $book['status'] === 'closed' ? 'selected' : '' ?>>مغلق</option>
                </select>
            </div>

            <div class="col-12">
                <label>ملاحظات</label>
                <textarea name="notes"><?= e((string)($book['notes'] ?? '')) ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">حفظ التعديلات</button>
            <a class="btn btn-light" href="receipt_books.php">إلغاء</a>
        </div>
    </form>
</div>

<div class="footer-note">© <?= e(date('Y')) ?> — قسم المالية</div>

</div>
</body>
</html>