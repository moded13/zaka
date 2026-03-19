<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$msg = (string)($_GET['msg'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim((string)($_POST['requester_phone'] ?? ''));
    $code  = trim((string)($_POST['access_code'] ?? ''));

    $st = $pdo->prepare("
        SELECT *
        FROM sponsor_access_requests
        WHERE requester_phone = ?
          AND access_code = ?
          AND status = 'approved'
          AND code_expires_at IS NOT NULL
          AND code_expires_at >= NOW()
        ORDER BY id DESC
        LIMIT 1
    ");
    $st->execute([$phone, $code]);
    $req = $st->fetch(PDO::FETCH_ASSOC);

    if ($req) {
        header('Location: ' . ORPHAN_PUBLIC_BASE . '/public/case.php?rid=' . (int)$req['id']);
        exit;
    } else {
        $err = 'الكود غير صحيح أو منتهي الصلاحية.';
    }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>تحقق بالكود | الكفالات</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= ORPHAN_PUBLIC_BASE ?>/assets/style.css">
</head>
<body>
<div class="container">
  <div class="card">
    <h2>تحقق بالكود</h2>
    <?php if ($msg === 'sent'): ?>
      <p class="muted">تم إرسال الطلب. بعد الموافقة سيتم إرسال كود عبر واتساب.</p>
    <?php endif; ?>
    <?php if (!empty($err)): ?>
      <div class="card" style="border-color:#f2c9cc;background:#fdeaea"><?= orphan_e($err) ?></div>
    <?php endif; ?>

    <form method="post" class="grid">
      <div class="col-6">
        <label>رقم الجوال *</label>
        <input type="text" name="requester_phone" required>
      </div>
      <div class="col-6">
        <label>الكود *</label>
        <input type="text" name="access_code" inputmode="numeric" required>
      </div>

      <div class="col-12 actions">
        <button class="btn" type="submit">دخول</button>
        <a class="btn btn-light" href="<?= ORPHAN_PUBLIC_BASE ?>/index.php">رجوع</a>
      </div>
    </form>
  </div>
</div>
</body>
</html>