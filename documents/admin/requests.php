<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$rows = $pdo->query("
    SELECT *
    FROM sponsor_access_requests
    ORDER BY id DESC
    LIMIT 200
")->fetchAll(PDO::FETCH_ASSOC);

$baseVerify = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'www.shneler.com') . ORPHAN_PUBLIC_BASE . '/public/verify.php';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>طلبات الوصول</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= ORPHAN_PUBLIC_BASE ?>/assets/style.css">
</head>
<body>
<div class="container">

  <div class="card">
    <h2>طلبات الوصول</h2>
    <p class="muted">عند الموافقة سيتم توليد كود وإظهار رسالة جاهزة لنسخها وإرسالها عبر واتساب يدويًا.</p>
    <div class="actions">
      <a class="btn btn-light" href="<?= ORPHAN_PUBLIC_BASE ?>/admin/index.php">رجوع</a>
    </div>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table>
        <tr>
          <th>#</th>
          <th>رقم المنتفع</th>
          <th>الاسم</th>
          <th>الجوال</th>
          <th>الحالة</th>
          <th>الكود</th>
          <th>انتهاء الصلاحية</th>
          <th>تاريخ الطلب</th>
          <th>إجراءات</th>
        </tr>

        <?php foreach ($rows as $r): ?>
          <?php
            $status = (string)$r['status'];
            $badge = $status === 'approved' ? 'badge-a' : ($status === 'rejected' ? 'badge-r' : 'badge-p');
          ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= (int)$r['beneficiary_id'] ?></td>
            <td><?= orphan_e((string)$r['requester_name']) ?></td>
            <td><?= orphan_e((string)$r['requester_phone']) ?></td>
            <td><span class="badge <?= $badge ?>"><?= orphan_e($status) ?></span></td>
            <td><?= orphan_e((string)($r['access_code'] ?? '—')) ?></td>
            <td><?= orphan_e((string)($r['code_expires_at'] ?? '—')) ?></td>
            <td><?= orphan_e((string)$r['created_at']) ?></td>
            <td>
              <div class="actions" style="justify-content:center">
                <?php if ($status === 'pending'): ?>
                  <form method="post" action="request_approve.php" style="margin:0">
                    <?= orphan_csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn" type="submit">موافقة + كود</button>
                  </form>

                  <form method="post" action="request_reject.php" style="margin:0">
                    <?= orphan_csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-light" type="submit">رفض</button>
                  </form>
                <?php else: ?>
                  <a class="btn btn-light" href="#msg<?= (int)$r['id'] ?>">رسالة واتساب</a>
                <?php endif; ?>
              </div>
            </td>
          </tr>

          <?php if ($status === 'approved'): ?>
          <tr id="msg<?= (int)$r['id'] ?>">
            <td colspan="9" style="text-align:right;white-space:normal">
              <?php
                $phone = (string)$r['requester_phone'];
                $code = (string)$r['access_code'];
                $text = "تمت الموافقة على طلبك.\n"
                      . "كود الدخول: {$code}\n"
                      . "رابط التحقق: {$baseVerify}\n"
                      . "أدخل رقم الجوال + الكود خلال 24 ساعة.";
              ?>
              <strong>انسخ الرسالة التالية وأرسلها عبر واتساب:</strong>
              <pre style="background:#f8fafc;border:1px solid #e6ebf2;padding:10px;border-radius:12px;white-space:pre-wrap"><?= orphan_e($text) ?></pre>
              <div class="muted">الجوال: <?= orphan_e($phone) ?></div>
            </td>
          </tr>
          <?php endif; ?>

        <?php endforeach; ?>
      </table>
    </div>
  </div>

</div>
</body>
</html>