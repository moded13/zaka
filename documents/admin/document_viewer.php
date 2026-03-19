<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$beneficiary_id = (int)($_GET['beneficiary_id'] ?? 0);
if ($beneficiary_id <= 0) { http_response_code(404); exit('Not found'); }

$st = $pdo->prepare("
    SELECT bd.*, b.full_name
    FROM beneficiary_documents bd
    JOIN beneficiaries b ON b.id = bd.beneficiary_id
    WHERE bd.beneficiary_id=?
    ORDER BY bd.id ASC
");
$st->execute([$beneficiary_id]);
$docs = $st->fetchAll(PDO::FETCH_ASSOC);

$name = $docs ? (string)$docs[0]['full_name'] : '';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>استعراض الوثائق</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= ORPHAN_PUBLIC_BASE ?>/assets/style.css">
  <style>
    .viewer{background:#fff;border:1px solid #e6ebf2;border-radius:16px;overflow:hidden}
    .snap{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;gap:10px;padding:10px}
    .slide{flex:0 0 100%;scroll-snap-align:start;border-radius:14px;border:1px solid #edf1f6;overflow:hidden;background:#0b1220;display:flex;align-items:center;justify-content:center;min-height:60vh}
    .slide img{max-width:100%;max-height:80vh;object-fit:contain}
    .slide .pdf{color:#fff;text-align:center;padding:20px}
    .top{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
  </style>
</head>
<body>
<div class="container">
  <div class="card">
    <div class="top">
      <div>
        <h2>استعرا�� الوثائق (سلايدر)</h2>
        <div class="muted"><?= orphan_e($name) ?> — ID: <?= (int)$beneficiary_id ?></div>
        <div class="muted">اسحب يمين/يسار للتنقل بين الوثائق.</div>
      </div>
      <div class="actions">
        <a class="btn btn-light" href="beneficiary_documents.php?beneficiary_id=<?= (int)$beneficiary_id ?>">رجوع للوثائق</a>
      </div>
    </div>
  </div>

  <div class="viewer">
    <div class="snap" id="snap">
      <?php foreach ($docs as $d): ?>
        <?php
          $mime = (string)$d['mime_type'];
          $url = 'document_download.php?id=' . (int)$d['id'];
        ?>
        <div class="slide">
          <?php if (str_starts_with($mime, 'image/')): ?>
            <img src="<?= orphan_e($url) ?>" alt="">
          <?php else: ?>
            <div class="pdf">
              <h3>ملف PDF</h3>
              <p><?= orphan_e((string)$d['original_name']) ?></p>
              <a class="btn" href="<?= orphan_e($url) ?>" target="_blank">فتح/تحميل</a>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <?php if (!$docs): ?>
        <div class="slide" style="background:#fff;color:#6c7a92">
          لا توجد وثائق.
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>