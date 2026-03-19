<?php
require_once 'bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('income.php');
}

verifyCsrf();

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    set_flash('error', 'المعرّف غير صحيح');
    redirect('income.php');
}

$stmt = $pdo->prepare("DELETE FROM finance_income WHERE id = ?");
$stmt->execute([$id]);

set_flash('success', 'تم حذف الإيراد بنجاح');
redirect('income.php');
