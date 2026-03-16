<?php
/**
 * beneficiaries_print.php (CUSTOM PRINT SHEET v2)
 *
 * Updates per request:
 * 1) اسم بعرض "تلقائي" (لا نحدد له width) + التوقيع يأخذ المساحة الزائدة.
 * 2) هيدر + فوتر للطباعة مثل النموذج:
 *    - ترويسة: وزارة... / صندوق الزكاة / المساعدات النقدية / لجنة...
 *    - حقول: رقم قرار اللجنة / تاريخ القرار / تاريخ التوزيع / رقم الكشف
 *    - فوتر: المجموع الإجمالي + نص الإقرار + أماكن توقيع/ختم
 * 3) 20 سطر في الصفحة + خلايا منسقة للطباعة.
 *
 * Usage:
 *  - Preview: /beneficiaries_print.php?ids=1,2,3
 *  - Print:   /beneficiaries_print.php?ids=1,2,3&print=1
 */

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
requireLogin();

$pdo = getPDO();

$idsRaw = trim((string)($_GET['ids'] ?? ''));
$print  = (int)($_GET['print'] ?? 0) === 1;

$ids = array_values(array_filter(array_map('intval', preg_split('/\s*,\s*/', $idsRaw))));
if (!$ids) {
    flashError('لم يتم تحديد أي مستفيد للطباعة.');
    redirect(ADMIN_PATH . '/beneficiaries.php');
}

$in = implode(',', array_fill(0, count($ids), '?'));

/**
 * "المبلغ/طرد":
 * - نعرض monthly_cash إن وجد
 * - وإلا (—)
 */
$stmt = $pdo->prepare(
    "SELECT b.id, b.full_name, b.id_number, b.phone, b.monthly_cash
     FROM beneficiaries b
     WHERE b.id IN ($in)
     ORDER BY b.id ASC"
);
$stmt->execute($ids);
$rows = $stmt->fetchAll();

/* pagination: 20 rows per printed page */
$perPage = 20;
$pages = array_chunk($rows, $perPage);

function fmtMoney($v): string {
    if ($v === null) return '—';
    $f = (float)$v;
    if ($f <= 0) return '—';
    return number_format($f, 2);
}

$total = 0.0;
foreach ($rows as $r) $total += (float)($r['monthly_cash'] ?? 0);
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>كشف تسليم</title>

  <style>
    /* ---- Page setup ---- */
    @page { size: A4; margin: 8mm 8mm 10mm 8mm; }

    body {
      font-family: "Cairo", Arial, sans-serif;
      background: #fff;
      color: #000;
      margin: 0;
    }

    .no-print { display: block; }
    @media print { .no-print { display: none !important; } }

    .toolbar {
      padding: 10px 12px;
      display: flex;
      gap: 8px;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid #ddd;
      position: sticky;
      top: 0;
      background: #fff;
      z-index: 10;
    }
    .btn {
      border: 1px solid #333;
      background: #f7f7f7;
      padding: 6px 10px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 700;
      text-decoration: none;
      color: #000;
      display: inline-block;
    }
    .btn-primary { background: #0d6efd; color: #fff; border-color: #0d6efd; }

    /* Printed page wrapper */
    .page { page-break-after: always; }
    .page:last-child { page-break-after: auto; }

    /* Header block (fixed height for consistent 20 rows) */
    .print-header {
      border: 1px solid #000;
      padding: 8px 10px;
      margin: 0 0 6px;
    }
    .header-title {
      text-align: center;
      line-height: 1.35;
    }
    .header-title .l1 { font-weight: 800; font-size: 14px; }
    .header-title .l2 { font-weight: 700; font-size: 13px; margin-top: 2px; }
    .header-title .l3 { font-weight: 800; font-size: 14px; margin-top: 2px; }
    .header-title .l4 { font-weight: 700; font-size: 13px; margin-top: 2px; }

    .header-fields {
      margin-top: 6px;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 6px 14px;
      font-size: 12px;
    }
    .field {
      display: flex;
      gap: 8px;
      align-items: baseline;
      white-space: nowrap;
    }
    .field .label { font-weight: 700; }
    .dots {
      flex: 1;
      border-bottom: 1px dotted #000;
      height: 10px;
      transform: translateY(-2px);
    }

    /* Table */
    table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
      font-size: 12px;
    }
    th, td {
      border: 1px solid #000;
      padding: 6px 6px;
      vertical-align: middle;
      text-align: center;
      word-wrap: break-word;
    }
    thead th { font-weight: 800; background: #f2f2f2; }

    /* Width control:
       - we do NOT set width for name column (auto)
       - signature takes remaining width via largest % */
    .col-no    { width: 7%; }
    .col-id    { width: 18%; }
    .col-phone { width: 16%; }
    .col-amt   { width: 12%; }
    .col-sign  { width: 25%; } /* أكبر عمود */
    /* name column auto: no width set; it will take remaining space */

    .name-cell { text-align: right; }

    /* Row height so 20 fit nicely under header/footer */
    tbody tr { height: 30px; }

    .sign-box { height: 20px; }

    /* Footer */
    .print-footer {
      border: 1px solid #000;
      padding: 8px 10px;
      margin: 6px 0 0;
      font-size: 12px;
    }
    .footer-total {
      display: grid;
      grid-template-columns: 1fr 160px;
      gap: 10px;
      align-items: center;
      margin-bottom: 8px;
    }
    .total-label { font-weight: 800; color: #c00; text-align: left; }
    .total-value { border: 1px solid #000; padding: 6px 8px; text-align: center; font-weight: 800; }

    .declaration {
      margin: 6px 0 10px;
      font-size: 12px;
      line-height: 1.6;
      text-align: center;
    }

    .signatures {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 14px;
      align-items: end;
    }
    .sig {
      display: grid;
      grid-template-rows: auto 16px;
      gap: 6px;
      text-align: center;
    }
    .sig .dotsline { border-bottom: 1px dotted #000; height: 16px; }
    .sig .label { font-weight: 700; }

  </style>
</head>
<body>

<div class="toolbar no-print">
  <div>
    <strong>معاينة الطباعة</strong>
    <span style="color:#666; font-size:12px;">— عدد السجلات: <?= count($rows) ?> — <?= date('Y-m-d H:i') ?></span>
  </div>
  <div style="display:flex; gap:8px;">
    <a class="btn" href="<?= e(ADMIN_PATH) ?>/beneficiaries.php">رجوع</a>
    <a class="btn btn-primary" href="?ids=<?= urlencode($idsRaw) ?>&print=1" target="_blank">طباعة</a>
  </div>
</div>

<?php foreach ($pages as $pageIndex => $pageRows): ?>
  <div class="page">

    <!-- Print Header (مثل النموذج) -->
    <div class="print-header">
      <div class="header-title">
        <div class="l1">وزارة الأوقاف، والشؤون، والمقدسات الإسلامية</div>
        <div class="l2">صندوق الزكاة</div>
        <div class="l3">المساعدات النقدية</div>
        <div class="l4">لجنة زكاة وصدقات - (اكتب اسم اللجنة هنا)</div>
      </div>

      <div class="header-fields">
        <div class="field"><span class="label">رقم قرار اللجنة:</span><span class="dots"></span></div>
        <div class="field"><span class="label">تاريخ القرار:</span><span class="dots"></span></div>
        <div class="field"><span class="label">تاريخ التوزيع:</span><span class="dots"></span></div>
        <div class="field"><span class="label">رقم الكشف:</span><span class="dots"></span></div>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th class="col-no">الرقم<br>التسلسلي</th>
          <th>الاسم الرباعي</th>
          <th class="col-id">رقم الهوية</th>
          <th class="col-phone">رقم الهاتف</th>
          <th class="col-amt">المبلغ/طرد</th>
          <th class="col-sign">التوقيع</th>
        </tr>
      </thead>
      <tbody>
        <?php
          $startNo = $pageIndex * $perPage;
          foreach ($pageRows as $i => $r):
            $no = $startNo + $i + 1;
        ?>
          <tr>
            <td class="col-no"><?= $no ?></td>
            <td class="name-cell"><?= e((string)$r['full_name']) ?></td>
            <td class="col-id"><?= e((string)($r['id_number'] ?? '')) ?></td>
            <td class="col-phone"><?= e((string)($r['phone'] ?? '')) ?></td>
            <td class="col-amt"><?= e(fmtMoney($r['monthly_cash'] ?? null)) ?></td>
            <td class="col-sign"><div class="sign-box"></div></td>
          </tr>
        <?php endforeach; ?>

        <?php
          // Pad to exactly 20 rows per page
          $missing = $perPage - count($pageRows);
          for ($k=0; $k<$missing; $k++):
        ?>
          <tr>
            <td class="col-no"></td>
            <td class="name-cell"></td>
            <td class="col-id"></td>
            <td class="col-phone"></td>
            <td class="col-amt"></td>
            <td class="col-sign"><div class="sign-box"></div></td>
          </tr>
        <?php endfor; ?>
      </tbody>
    </table>

    <!-- Print Footer (مثل النموذج) -->
    <div class="print-footer">
      <div class="footer-total">
        <div class="total-label">المجموع الإجمالي:</div>
        <div class="total-value"><?= number_format($total, 2) ?></div>
      </div>

      <div class="declaration">
        أشهد أني قمت بتسليم المبالغ المذكورة أعلاه إلى ذوي الاستحقاق كلٌّ إزاء اسمه وحسب الأصول.
      </div>

      <div class="signatures">
        <div class="sig">
          <div class="dotsline"></div>
          <div class="label">عضو اللجنة</div>
        </div>
        <div class="sig">
          <div class="dotsline"></div>
          <div class="label">توقيع أمين صندوق اللجنة</div>
        </div>
        <div class="sig">
          <div class="dotsline"></div>
          <div class="label">ختم اللجنة</div>
        </div>
      </div>
    </div>

  </div>
<?php endforeach; ?>

<?php if ($print): ?>
<script>
  window.addEventListener('load', () => window.print());
</script>
<?php endif; ?>
</body>
</html>