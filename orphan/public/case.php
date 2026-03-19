<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$rid = (int)($_GET['rid'] ?? 0);
if ($rid <= 0) { http_response_code(404); exit('Not found'); }

$st = $pdo->prepare("SELECT * FROM sponsor_access_requests WHERE id=?");
$st->execute([$rid]);
$req = $st->fetch(PDO::FETCH_ASSOC);
if (!$req) { http_response_code(404); exit('Not found'); }

// Minimal case info for now (no public photos yet)
// We'll display beneficiary basic info if you want, but depends on your beneficiaries table columns.
// MVP: show beneficiary_id only.
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>معلومات الحالة</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= ORPHAN_PUBLIC_BASE ?>/assets/style.css">
</head>
<body>
<div class="container">
  <div class="card">
    <h2>تم التحقق بنجاح</h2>
    <p>رقم الحالة/المنتفع: <strong><?= (int)$req['beneficiary_id'] ?></strong></p>
    <p class="muted">هذه صفحة معلومات موسّعة (بدون وثائق حساسة). سنطورها لاحقًا لعرض صورة اليتيم العامة وملخص الحالة.</p>

    <div class="actions">
      <a class="btn btn-light" href="<?= ORPHAN_PUBLIC_BASE ?>/index.php">الصفحة الرئيسية</a>
    </div>
  </div>
</div>
</body>
</html>