<?php
require_once 'bootstrap.php';
require_once 'helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('income.php');
}

verifyCsrf();

$receipt_no     = trim($_POST['receipt_no'] ?? '');
$income_date    = trim($_POST['income_date'] ?? '');
$donor_name     = trim($_POST['donor_name'] ?? '');
$category_id    = (int)($_POST['category_id'] ?? 0);
$amount         = (float)($_POST['amount'] ?? 0);
$payment_method = trim($_POST['payment_method'] ?? '');
$notes          = trim($_POST['notes'] ?? '');
$book_id        = (int)($_POST['book_id'] ?? 0) ?: null;

if ($receipt_no === '' || $income_date === '' || $category_id <= 0 || $amount <= 0) {
    set_flash('error', 'الرجاء تعبئة الحقول المطلوبة بشكل صحيح');
    redirect('income.php');
}

if (!validate_date($income_date)) {
    set_flash('error', 'تنسيق التاريخ غير صحيح');
    redirect('income.php');
}

// Uniqueness check
$check = $pdo->prepare("SELECT COUNT(*) FROM finance_income WHERE receipt_no = ?");
$check->execute([$receipt_no]);
if ($check->fetchColumn() > 0) {
    set_flash('error', 'رقم الوصول مستخدم مسبقًا');
    redirect('income.php');
}

// Book range validation (if book_id provided and receipt_books migrated)
if ($book_id && receipt_books_exist()) {
    $bookRow = $pdo->prepare("SELECT * FROM receipt_books WHERE id = ?");
    $bookRow->execute([$book_id]);
    $book = $bookRow->fetch();
    if ($book) {
        $rno = (int)$receipt_no;
        if ($rno < (int)$book['start_receipt_no'] || $rno > (int)$book['end_receipt_no']) {
            set_flash('error', 'رقم الوصول خارج نطاق الدفتر المحدد (' . (int)$book['start_receipt_no'] . '–' . (int)$book['end_receipt_no'] . ')');
            redirect('income.php');
        }
    }
}

// Insert: book_id is NULL if column doesn't exist yet or no book selected
$pdo->beginTransaction();
try {
    if (book_id_exists_in_income()) {
        $stmt = $pdo->prepare(
            "INSERT INTO finance_income
             (book_id, receipt_no, income_date, donor_name, category_id, amount, payment_method, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$book_id, $receipt_no, $income_date, $donor_name, $category_id, $amount, $payment_method, $notes]);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO finance_income
             (receipt_no, income_date, donor_name, category_id, amount, payment_method, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$receipt_no, $income_date, $donor_name, $category_id, $amount, $payment_method, $notes]);
    }
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    set_flash('error', 'حدث خطأ أثناء الحفظ: ' . $ex->getMessage());
    redirect('income.php');
}

set_flash('success', 'تم حفظ الإيراد بنجاح');
redirect('income.php');
