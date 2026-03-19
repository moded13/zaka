<?php
require_once 'bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('expenses.php');
}

verifyCsrf();

$voucher_no       = trim($_POST['voucher_no'] ?? '');
$expense_date     = trim($_POST['expense_date'] ?? '');
$beneficiary_name = trim($_POST['beneficiary_name'] ?? '');
$category_id      = (int)($_POST['category_id'] ?? 0);
$amount           = (float)($_POST['amount'] ?? 0);
$payment_method   = trim($_POST['payment_method'] ?? '');
$notes            = trim($_POST['notes'] ?? '');

if ($voucher_no === '' || $expense_date === '' || $category_id <= 0 || $amount <= 0) {
    set_flash('error', 'الرجاء تعبئة الحقول المطلوبة بشكل صحيح');
    redirect('expenses.php');
}

$check = $pdo->prepare("SELECT COUNT(*) FROM finance_expenses WHERE voucher_no = ?");
$check->execute([$voucher_no]);
if ($check->fetchColumn() > 0) {
    set_flash('error', 'رقم السند مستخدم مسبقًا');
    redirect('expenses.php');
}

$stmt = $pdo->prepare(
    "INSERT INTO finance_expenses
     (voucher_no, expense_date, beneficiary_name, category_id, amount, payment_method, notes)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);
$stmt->execute([$voucher_no, $expense_date, $beneficiary_name, $category_id, $amount, $payment_method, $notes]);

set_flash('success', 'تم حفظ المدفوع بنجاح');
redirect('expenses.php');
