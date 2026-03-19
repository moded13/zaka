<?php
require_once __DIR__ . '/../includes/bootstrap.php';

requireLogin();
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>مركز الوثائق</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= ORPHAN_PUBLIC_BASE ?>/assets/style.css">
</head>
<body>
<div class="container">
  <div class="card">
    <h1>مركز الوثائق</h1>
    <p class="muted">إدارة وثائق جميع المستفيدين (رفع، استعراض، حذف).</p>
    <div class="actions">
      <a class="btn btn-light" href="<?= ORPHAN_PUBLIC_BASE ?>/admin/beneficiary_documents.php">وثائق المستفيدين</a>
      <a class="btn btn-light" href="/zaka/admin/dashboard.php">العودة للوحة الإدارة</a>
    </div>
  </div>
</div>
</body>
</html>