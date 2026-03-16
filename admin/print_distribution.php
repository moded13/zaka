<?php
/**
 * print_distribution.php (A4 Pro) - SAME DESIGN, ONLY REQUESTED CHANGES (v3)
 *
 * Based on your current "previous design" (header card + table card + footer card).
 *
 * Implemented per image (requested):
 * - Center ALL header text (ministry lines + meta line).
 * - Reduce/trim footer height so it NEVER goes to next page.
 * - Remove the extra "witness box" in the middle footer (the part you X'ed in the image).
 * - Keep 20 rows + header + footer in ONE page.
 * - Make page total ("مجموع الصفحة") RED.
 * - Keep: remove file_number column, amount numeric only, column title "الرقم".
 * - Add Excel-like borders: table borders already black; add strong borders + consistent grid.
 */

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
requireLogin();

$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    exit('رقم التوزيعة مطلوب.');
}

// Detect created_by existence (optional)
$hasCreatedBy = (int)$pdo->query(
    "SELECT COUNT(*)
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'distributions'
       AND COLUMN_NAME = 'created_by'"
)->fetchColumn() > 0;

// Distribution header
if ($hasCreatedBy) {
    $stmt = $pdo->prepare(
        'SELECT d.*,
                bt.name_ar AS type_name,
                a.display_name AS created_by_name
         FROM distributions d
         LEFT JOIN beneficiary_types bt ON bt.id = d.beneficiary_type_id
         LEFT JOIN admins a ON a.id = d.created_by
         WHERE d.id = ?'
    );
} else {
    $stmt = $pdo->prepare(
        'SELECT d.*,
                bt.name_ar AS type_name,
                NULL AS created_by_name
         FROM distributions d
         LEFT JOIN beneficiary_types bt ON bt.id = d.beneficiary_type_id
         WHERE d.id = ?'
    );
}
$stmt->execute([$id]);
$dist = $stmt->fetch();

if (!$dist) {
    http_response_code(404);
    exit('التوزيعة غير موجودة.');
}

// Items
$stmt = $pdo->prepare(
    'SELECT di.*,
            b.full_name, b.id_number, b.phone
     FROM distribution_items di
     JOIN beneficiaries b ON b.id = di.beneficiary_id
     WHERE di.distribution_id = ?
     ORDER BY b.file_number'
);
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$orgName  = getSetting('org_name', APP_NAME);
$orgPhone = getSetting('org_phone', '');

$perPage = 20;
$totalItems = count($items);
$totalPages = (int)max(1, ceil($totalItems / $perPage));
$pages = array_chunk($items, $perPage);

$totalCash = array_sum(array_map(fn($r) => (float)($r['cash_amount'] ?? 0), $items));
$printDate = date('Y-m-d H:i');

function pageTotal(array $pageItems): float
{
    $s = 0.0;
    foreach ($pageItems as $r) $s += (float)($r['cash_amount'] ?? 0);
    return $s;
}

function amtNum($v): string
{
    $f = (float)$v;
    if ($f <= 0) return '—';
    return number_format($f, 2);
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>كشف توزيع #<?= (int)$id ?> — <?= e($orgName) ?></title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">

<style>
:root{
  --ink:#0f2c5e;
  --ink2:#0a1e40;
  --line:#d5deee;
  --muted:#5e6b80;
  --headbg:#eef3fb;
  --grid:#000;
  --accent:#0b4aa2;
  --danger:#c00;
}
*{ box-sizing:border-box; }
body{
  font-family:'Cairo',sans-serif;
  font-size:12px;
  margin:0;
  color:#111;
  background:#fff;
}
.no-print{
  padding:10px 12px;
  display:flex;
  gap:8px;
  border-bottom:1px solid var(--line);
}
.btn{
  border:1px solid var(--line);
  background:#fff;
  padding:6px 10px;
  border-radius:10px;
  font-family:inherit;
  cursor:pointer;
}
.btn-primary{ background:var(--ink); color:#fff; border-color:var(--ink); }

.sheet{ padding:0; }
.page{
  width: 210mm;
  min-height: 297mm;
  padding: 7mm;                 /* ✅ tighter to fit all */
  margin: 0 auto;
  page-break-after: always;

  /* subtle page frame */
  border: 1px solid var(--grid);
  border-radius: 12px;
}
.page:last-child{ page-break-after: auto; }

/* Header card */
.header{
  border:2px solid var(--ink);
  border-radius:14px;
  padding:7px 10px;            /* ✅ smaller */
  margin-bottom:6px;
}
.header-top{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:10px;
}
.brand{
  display:flex;
  gap:10px;
  align-items:flex-start;
}
.logo{
  width:38px;height:38px;border-radius:12px;
  background: linear-gradient(135deg,var(--ink),#1a4a8a);
  flex:0 0 auto;
}
.brand h1{
  margin:0;
  font-size:16px;
  font-weight:900;
  color:var(--ink);
  line-height:1.1;
}
.brand .sub{
  margin-top:2px;
  color:var(--muted);
  font-size:11px;
}

/* ✅ center the ministry block */
.ministry{
  margin-top:6px;
  padding-top:5px;
  border-top:1px dashed var(--line);
  text-align:center;
  line-height:1.35;
}
.ministry .l1{ font-weight:900; font-size:13.5px; text-align:center; }
.ministry .l2{ font-weight:800; font-size:12.5px; text-align:center; }
.ministry .l3{ font-weight:900; font-size:13.5px; text-align:center; }
.ministry .l4{ font-weight:800; font-size:12.5px; color:var(--accent); text-align:center; }

.docbox{ text-align:left; }
.badge{
  display:inline-block;
  padding:4px 10px;
  background:var(--ink2);
  color:#fff;
  border-radius:999px;
  font-weight:800;
  font-size:11px;
}
.docbox .sub{
  margin-top:6px;
  color:var(--muted);
  font-size:11px;
}

/* ✅ meta centered */
.meta{
  display:flex;
  flex-wrap:wrap;
  justify-content:center;
  text-align:center;
  gap:4px 14px;
  margin-top:7px;
  padding-top:6px;
  border-top:1px dashed var(--line);
  font-size:12.8px;            /* ✅ slightly bigger */
  font-weight:700;
}
.meta b{ color:#000; font-weight:900; }

/* Table card */
.table-wrap{
  border:1.5px solid var(--grid);
  border-radius:14px;
  overflow:hidden;
}

/* ✅ Excel-like grid lines (strong) */
table{
  width:100%;
  border-collapse:collapse;
  table-layout:fixed;
}
thead th{
  background:var(--headbg);
  border:1px solid var(--grid);
  font-weight:900;
  font-size:11.5px;
  padding:6px 6px;
  text-align:center;
}
tbody td{
  border:1px solid var(--grid);
  padding:4px 6px;             /* ✅ tighter */
  vertical-align:middle;
}

/* Column widths */
.col-n{ width: 8%;  text-align:center; }
.col-name{ width: 38%; }
.col-id{ width: 18%; text-align:center; }
.col-phone{ width: 16%; text-align:center; }
.col-amt{ width: 12%; text-align:center; }
.col-sign{ width: auto; }

/* ✅ fit 20 rows */
tbody tr{ height: 24px; }

.muted{ color:var(--muted); }
.small{ font-size:11px; }

.wrap{
  overflow:hidden;
  text-overflow:ellipsis;
  white-space:normal;
  word-break:break-word;
  line-height:1.12;
}
.name{
  font-weight:800;
  font-size:12px;
}
.name-cell{ text-align:right; }

/* Footer card (smaller, no middle box) */
.footer{
  margin-top:6px;
  border:2px solid var(--ink);
  border-radius:14px;
  padding:7px 10px;           /* ✅ smaller */
  display:grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap:8px;
  align-items:end;
  font-size:12.8px;           /* ✅ bigger */
  font-weight:700;
}
.sig{ text-align:center; }
.sig .line{
  margin-top:14px;            /* ✅ smaller */
  border-top:1px solid #333;
  width:92%;
  margin-inline:auto;
}
.sig .label{
  font-weight:900;
  font-size:12px;
  margin-top:4px;
}

/* ✅ page total, red */
.page-total{
  margin-top:6px;
  border:1.5px solid var(--grid);
  border-radius:12px;
  padding:6px 8px;
  display:flex;
  justify-content:space-between;
  gap:10px;
  font-weight:900;
  color:var(--danger);
}
.page-total .val{ color:var(--danger); }

.page-note{
  margin-top:4px;
  display:flex;
  justify-content:space-between;
  font-size:10.5px;
  color:var(--muted);
}

/* Print */
@media print{
  .no-print{ display:none !important; }
  body{ background:#fff; }
  @page{ size:A4; margin:0; }
  .page{ margin:0; }
  tr{ page-break-inside: avoid; }
  table{ page-break-inside: avoid; }
}
</style>
</head>
<body>

<div class="no-print">
  <button class="btn btn-primary" onclick="window.print()">طباعة A4</button>
  <a class="btn" href="javascript:history.back()">رجوع</a>
</div>

<div class="sheet">
<?php foreach ($pages as $pi => $pageItems): ?>
  <?php $pTotal = pageTotal($pageItems); ?>
  <div class="page">
    <div class="header">
      <div class="header-top">
        <div class="brand">
          <div class="logo"></div>
          <div>
            <h1>لجنة الزكاة</h1>
            <div class="sub"><?= e($orgName) ?><?= $orgPhone ? (' — هاتف: ' . e($orgPhone)) : '' ?></div>

            <div class="ministry">
              <div class="l1">وزارة الأوقاف، والشؤون، والمقدسات الإسلامية</div>
              <div class="l2">صندوق الزكاة</div>
              <div class="l3">المساعدات النقدية</div>
              <div class="l4">لجنة زكاة وصدقات - مخيم حطين المركزية</div>
            </div>
          </div>
        </div>

        <div class="docbox">
          <div class="badge">كشف توزيع رقم #<?= (int)$id ?></div>
          <div class="sub">صفح   <?= (int)($pi+1) ?> / <?= (int)$totalPages ?></div>
        </div>
      </div>

      <div class="meta">
        <div><b>عنوان التوزيعة:</b> <?= e((string)$dist['title']) ?></div>
        <div><b>تصنيف المستفيدين:</b> <?= e((string)($dist['type_name'] ?? '—')) ?></div>
        <div><b>تاريخ التوزيع:</b> <?= e((string)$dist['distribution_date']) ?></div>
        <div><b>عدد الأسماء:</b> <?= (int)$totalItems ?></div>
        <div><b>إجمالي الصرف:</b> <?= e(number_format((float)$totalCash, 2)) ?></div>
      </div>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th class="col-n">الرقم</th>
            <th class="col-name">الاسم الكامل</th>
            <th class="col-id">رقم الهوية</th>
            <th class="col-phone">رقم الهاتف</th>
            <th class="col-amt">المبلغ/طرد</th>
            <th class="col-sign">التوقيع</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pageItems as $i => $it): ?>
            <?php
              $n = ($pi * $perPage) + $i + 1;
              $cash = (float)($it['cash_amount'] ?? 0);
            ?>
            <tr>
              <td class="col-n"><?= (int)$n ?></td>
              <td class="col-name name-cell">
                <div class="name wrap"><?= e((string)$it['full_name']) ?></div>
              </td>
              <td class="col-id muted small"><?= e((string)($it['id_number'] ?? '')) ?></td>
              <td class="col-phone"><?= e((string)($it['phone'] ?? '')) ?></td>
              <td class="col-amt"><?= e(amtNum($cash)) ?></td>
              <td class="col-sign"></td>
            </tr>
          <?php endforeach; ?>

          <?php
            $missing = $perPage - count($pageItems);
            for ($k=0; $k<$missing; $k++):
          ?>
            <tr>
              <td class="col-n">&nbsp;</td>
              <td class="col-name">&nbsp;</td>
              <td class="col-id">&nbsp;</td>
              <td class="col-phone">&nbsp;</td>
              <td class="col-amt">&nbsp;</td>
              <td class="col-sign">&nbsp;</td>
            </tr>
          <?php endfor; ?>
        </tbody>
      </table>
    </div>

    <div class="footer">
      <div class="sig">
        <div class="line"></div>
        <div class="label">توقيع المستلم</div>
      </div>

      <div>
        <div style="text-align:center; font-weight:900;">وزعت بحضوري</div>
        <div class="page-total">
          <div>مجموع الصفحة</div>
          <div class="val"><?= e(number_format((float)$pTotal, 2)) ?></div>
        </div>
      </div>

      <div class="sig">
        <div class="line"></div>
        <div class="label">توقيع مسؤول اللجنة</div>
      </div>
    </div>

    <div class="page-note">
      <div>طُبع في: <?= e($printDate) ?></div>
      <div>
        <?php if (!empty($dist['created_by_name'])): ?>
          أُنشئ بواسطة: <?= e((string)$dist['created_by_name']) ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>

</body>
</html>