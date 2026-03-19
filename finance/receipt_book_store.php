<?php
/**
 * receipt_book_store.php
 * Create receipt book (POST only)
 * ✅ Fix CSRF mismatch by using finance_verify_csrf()
 * ✅ Uses created_by_admin_id (matches your DB)
 */

require_once 'bootstrap.php';
require_once 'helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    finance_flash_error('طريقة الطلب غير صحيحة');
    header('Location: receipt_books.php');
    exit;
}

finance_verify_csrf();

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
    finance_flash_error(implode(' | ', $errors));
    header('Location: receipt_books.php?add=1');
    exit;
}

$total_receipts = (int)(($end_receipt_no - $start_receipt_no) + 1);

// Current admin id -> created_by_admin_id (nullable)
$adminId = null;
if (function_exists('current_admin_id')) {
    $adminId = current_admin_id();
} elseif (function_exists('currentAdmin')) {
    $a = currentAdmin();
    $adminId = $a['id'] ?? null;
}
$adminId = ($adminId !== null && (int)$adminId > 0) ? (int)$adminId : null;

try {
    $stmt = $pdo->prepare(
        "INSERT INTO receipt_books
         (book_no, start_receipt_no, end_receipt_no, total_receipts, received_date, status, notes, created_by_admin_id)
         VALUES
         (:book_no, :start_no, :end_no, :total, :received_date, :status, :notes, :created_by_admin_id)"
    );

    $stmt->execute([
        ':book_no'             => $book_no,
        ':start_no'            => $start_receipt_no,
        ':end_no'              => $end_receipt_no,
        ':total'               => $total_receipts,
        ':received_date'       => $received_date,
        ':status'              => $status,
        ':notes'               => ($notes !== '' ? $notes : null),
        ':created_by_admin_id' => $adminId,
    ]);

    finance_flash_success('تم إنشاء دفتر الوصولات بنجاح');
} catch (PDOException $e) {
    if (stripos($e->getMessage(), 'Duplicate') !== false) {
        finance_flash_error('رقم الدفتر موجود مسبقًا');
    } else {
        finance_flash_error('خطأ أثناء الحفظ: ' . $e->getMessage());
    }
}

header('Location: receipt_books.php');
exit;