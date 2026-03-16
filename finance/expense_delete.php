<?php
require_once 'bootstrap.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('error', 'المعرّف غير صحيح');
    redirect('expenses.php');
}

$stmt = $pdo->prepare("DELETE FROM finance_expenses WHERE id = ?");
$stmt->execute([$id]);

set_flash('success', 'تم حذف المدفوع بنجاح');
redirect('expenses.php');