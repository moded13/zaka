<?php
require_once __DIR__ . '/../includes/bootstrap.php';

// NOTE: We don't have a public list of orphans yet.
// MVP: user enters beneficiary_id (or file no). We'll keep beneficiary_id for now.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $beneficiary_id  = (int)($_POST['beneficiary_id'] ?? 0);
    $name            = trim((string)($_POST['requester_name'] ?? ''));
    $phone           = trim((string)($_POST['requester_phone'] ?? ''));
    $q1              = trim((string)($_POST['q1'] ?? ''));
    $q2              = trim((string)($_POST['q2'] ?? ''));
    $q3              = trim((string)($_POST['q3'] ?? ''));

    $errors = [];
    if ($beneficiary_id <= 0) $errors[] = 'رقم الحالة/المنتفع مطلوب.';
    if ($name === '') $errors[] = 'الاسم مطلوب.';
    if ($phone === '') $errors[] = 'رقم الجوال مطلوب.';

    if (!$errors) {
        $answers = ['q1'=>$q1,'q2'=>$q2,'q3'=>$q3];

        $st = $pdo->prepare("
            INSERT INTO sponsor_access_requests
            (beneficiary_id, requester_name, requester_phone, answers_json, status, created_at)
            VALUES (?,?,?,?, 'pending', NOW())
        ");
        $st->execute([$beneficiary_id, $name, $phone, orphan_json($answers)]);

        header('Location: ' . ORPHAN_PUBLIC_BASE . '/public/verify.php?msg=sent');
        exit;
    }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>طلب وصول | الكفالات</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= ORPHAN_PUBLIC_BASE ?>/assets/style.css">
</head>
<body>
<div class="container">
  <div class="card">
    <h2>طلب الوصول لملف كفالة</h2>
    <p class="muted">بعد مراجعة الطلب سيتم إرسال كود واتساب يدويًا من الإدارة.</p>

    <?php if (!empty($errors)): ?>
      <div class="card" style="border-color:#f2c9cc;background:#fdeaea">
        <?= orphan_e(implode(' | ', $errors)) ?>
      </div>
    <?php endif; ?>

    <form method="post" class="grid">
      <div class="col-6">
        <label>رقم الحالة/المنتفع *</label>
        <input type="number" name="beneficiary_id" required>
        <div class="muted">حالياً ندخل رقم المنتفع يدويًا. لاحقاً سنعرض قائمة   يتام للنشر.</div>
      </div>
      <div class="col-6">
        <label>الاسم *</label>
        <input type="text" name="requester_name" required>
      </div>
      <div class="col-6">
        <label>رقم الجوال (واتساب) *</label>
        <input type="text" name="requester_phone" placeholder="مثال: 079xxxxxxx" required>
      </div>

      <div class="col-12">
        <h3>أسئلة سريعة</h3>
      </div>
      <div class="col-6">
        <label>لماذا تريد الكفالة؟</label>
        <input type="text" name="q1">
      </div>
      <div class="col-6">
        <label>هل سبق لك كفالة من قبل؟</label>
        <input type="text" name="q2">
      </div>
      <div class="col-12">
        <label>ملاحظات إضافية</label>
        <textarea name="q3"></textarea>
      </div>

      <div class="col-12 actions">
        <button class="btn" type="submit">إرسال الطلب</button>
        <a class="btn btn-light" href="<?= ORPHAN_PUBLIC_BASE ?>/index.php">رجوع</a>
      </div>
    </form>
  </div>
</div>
</body>
</html>