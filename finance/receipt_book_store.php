<?php
require_once 'bootstrap.php';
require_once 'helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('receipt_books.php');
}

if (!receipt_books_exist()) {
    redirect('receipt_books.php');
}

verifyCsrf();

$book_no         = trim($_POST['book_no'] ?? '');
$start_receipt   = (int)($_POST['start_receipt_no'] ?? 0);
$end_receipt     = (int)($_POST['end_receipt_no'] ?? 0);
$received_date   = trim($_POST['received_date'] ?? '');
$status          = in_array($_POST['status'] ?? '', ['open', 'closed']) ? $_POST['status'] : 'open';
$notes           = trim($_POST['notes'] ?? '');
$created_by      = currentAdmin()['id'] ?? null;

if ($book_no === '' || $start_receipt <= 0 || $end_receipt <= 0 || $received_date === '') {
    set_flash('error', 'الرجاء تعبئة جميع الحقول المطلوبة');
    redirect('receipt_books.php');
}

if ($end_receipt < $start_receipt) {
    set_flash('error', 'رقم الوصول الأخير يجب أن يكون أكبر من أو مساوياً للأول');
    redirect('receipt_books.php');
}

if (!validate_date($received_date)) {
    set_flash('error', 'تنسيق التاريخ غير صحيح');
    redirect('receipt_books.php');
}

// Unique book_no check
$check = $pdo->prepare("SELECT COUNT(*) FROM receipt_books WHERE book_no = ?");
$check->execute([$book_no]);
if ($check->fetchColumn() > 0) {
    set_flash('error', 'رقم الدفتر مستخدم مسبقًا');
    redirect('receipt_books.php');
}

$total_receipts = $end_receipt - $start_receipt + 1;

$stmt = $pdo->prepare(
    "INSERT INTO receipt_books
     (book_no, start_receipt_no, end_receipt_no, total_receipts, received_date, status, notes, created_by)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);
$stmt->execute([$book_no, $start_receipt, $end_receipt, $total_receipts, $received_date, $status, $notes, $created_by]);

set_flash('success', 'تم حفظ دفتر الوصولات بنجاح');
redirect('receipt_books.php');
