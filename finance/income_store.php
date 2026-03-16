<?php
require_once 'bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('income.php');
}

$receipt_no = trim($_POST['receipt_no'] ?? '');
$income_date = trim($_POST['income_date'] ?? '');
$donor_name = trim($_POST['donor_name'] ?? '');
$category_id = (int)($_POST['category_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$payment_method = trim($_POST['payment_method'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if ($receipt_no === '' || $income_date === '' || $category_id <= 0 || $amount <= 0) {
    set_flash('error', 'الرجاء تعبئة الحقول المطلوبة بشكل صحيح');
    redirect('income.php');
}

$check = $pdo->prepare("SELECT COUNT(*) FROM finance_income WHERE receipt_no = ?");
$check->execute([$receipt_no]);

if ($check->fetchColumn() > 0) {
    set_flash('error', 'رقم الوصول مستخدم مسبقًا');
    redirect('income.php');
}

$stmt = $pdo->prepare("
    INSERT INTO finance_income
    (receipt_no, income_date, donor_name, category_id, amount, payment_method, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $receipt_no,
    $income_date,
    $donor_name,
    $category_id,
    $amount,
    $payment_method,
    $notes
]);

set_flash('success', 'تم حفظ الإيراد بنجاح');
redirect('income.php');