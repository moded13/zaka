<?php
require_once __DIR__ . '/includes/bootstrap.php';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>قسم الكفالات | زاكا</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= ORPHAN_PUBLIC_BASE ?>/assets/style.css">
</head>
<body>
<div class="container">
  <div class="card">
    <h1>قسم الكفالات (تجريبي)</h1>
    <p class="muted">هذا القسم مخصص لطلبات الوصول لملفات حالات الكفالة. لا يتم عرض وثائق حساسة للزوار.</p>
    <div class="actions">
      <a class="btn" href="<?= ORPHAN_PUBLIC_BASE ?>/public/request_access.php">طلب الوصول لملف كفالة</a>
      <a class="btn btn-light" href="<?= ORPHAN_PUBLIC_BASE ?>/public/verify.php">تحقق بالكود</a>
      <a class="btn btn-light" href="<?= ORPHAN_PUBLIC_BASE ?>/admin/index.php">لوحة الأدمن (الطلبات/الوثائق)</a>
    </div>
  </div>
</div>
</body>
</html>