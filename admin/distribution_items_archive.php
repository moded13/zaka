<?php
/**
 * admin/distribution_items_archive.php
 * View + Search archived distribution items after beneficiaries were deleted.
 * Includes CSV export + single/bulk delete archive rows.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
requireLogin();

$pdo   = getPDO();
$types = getBeneficiaryTypes();

/* فوال باك لو ما كانت موجودة */
if (!function_exists('flashSuccess')) {
    function flashSuccess(string $msg): void
    {
        $_SESSION['flash_success'] = $msg;
    }
}
if (!function_exists('flashError')) {
    function flashError(string $msg): void
    {
        $_SESSION['flash_error'] = $msg;
    }
}
if (!function_exists('renderFlash')) {
    function renderFlash(): string
    {
        $out = '';
        if (!empty($_SESSION['flash_success'])) {
            $out .= '<div class="alert alert-success">' . e((string)$_SESSION['flash_success']) . '</div>';
            unset($_SESSION['flash_success']);
        }
        if (!empty($_SESSION['flash_error'])) {
            $out .= '<div class="alert alert-danger">' . e((string)$_SESSION['flash_error']) . '</div>';
            unset($_SESSION['flash_error']);
        }
        return $out;
    }
}

$type   = (int)($_GET['type'] ?? 0);
$q      = trim((string)($_GET['q'] ?? ''));
$export = (string)($_GET['export'] ?? ''); // csv

function digitsOnly(string $s): string
{
    return preg_replace('/\D+/', '', $s) ?? '';
}

function archiveBackUrl(int $type, string $q): string
{
    $qs = [];
    if ($type > 0) $qs[] = 'type=' . $type;
    if ($q !== '') $qs[] = 'q=' . urlencode($q);
    return ADMIN_PATH . '/distribution_items_archive.php' . ($qs ? ('?' . implode('&', $qs)) : '');
}

function typeLabelFromRow(array $row): string
{
    return (string)($row['type_name'] ?? $row['name_ar'] ?? $row['name'] ?? '');
}

/* ───────────────────────────── POST Actions ───────────────────────────── */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action = (string)($_POST['action'] ?? '');
    $type   = (int)($_POST['type'] ?? 0);
    $q      = trim((string)($_POST['q'] ?? ''));

    // حذف فردي
    if ($action === 'delete_archive_item') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            flashError('معرّف الأرشيف غير صالح.');
            redirect(archiveBackUrl($type, $q));
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM distribution_items_archive WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                flashSuccess('تم حذف سجل الأرشيف.');
            } else {
                flashError('السجل غير موجود أو تم حذفه مسبقًا.');
            }
        } catch (Throwable $e) {
            flashError('خطأ أثناء حذف سجل الأرشيف: ' . $e->getMessage());
        }

        redirect(archiveBackUrl($type, $q));
    }

    // حذف جماعي
    if ($action === 'bulk_delete_archive') {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));

        if (!$ids) {
            flashError('لم يتم تحديد أي سجل أرشيف.');
            redirect(archiveBackUrl($type, $q));
        }

        try {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM distribution_items_archive WHERE id IN ($in)");
            $stmt->execute($ids);

            $count = (int)$stmt->rowCount();
            flashSuccess("تم حذف {$count} سجل أرشيف.");
        } catch (Throwable $e) {
            flashError('خطأ أثناء الحذف الجماعي: ' . $e->getMessage());
        }

        redirect(archiveBackUrl($type, $q));
    }

    redirect(archiveBackUrl($type, $q));
}

/* ───────────────────────────── Filters ───────────────────────────── */

$where  = [];
$params = [];

if ($type > 0) {
    $where[] = 'a.beneficiary_type_id = ?';
    $params[] = $type;
}

if ($q !== '') {
    $qDigits = digitsOnly($q);

    if ($qDigits !== '') {
        $where[] = '(
            a.full_name LIKE ?
            OR a.id_number LIKE ?
            OR a.phone LIKE ?
            OR a.file_number = ?
            OR a.distribution_id = ?
        )';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, (int)$qDigits, (int)$qDigits);
    } else {
        $where[] = '(
            a.full_name LIKE ?
            OR a.id_number LIKE ?
            OR a.phone LIKE ?
        )';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like);
    }
}

$sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ───────────────────────────── CSV Export ───────────────────────────── */

if ($export === 'csv') {
    $stmt = $pdo->prepare(
        "SELECT
            a.*,
            bt.name_ar AS type_name
         FROM distribution_items_archive a
         LEFT JOIN beneficiary_types bt ON bt.id = a.beneficiary_type_id
         $sqlWhere
         ORDER BY a.archived_at DESC, a.id DESC"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="distribution_items_archive.csv"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, [
        'archive_id',
        'archived_at',
        'distribution_id',
        'beneficiary_id',
        'beneficiary_type_id',
        'beneficiary_type_name',
        'file_number',
        'full_name',
        'id_number',
        'phone',
        'cash_amount',
        'details_text',
        'notes',
        'archived_by_admin_id'
    ]);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'] ?? '',
            $r['archived_at'] ?? '',
            $r['distribution_id'] ?? '',
            $r['beneficiary_id'] ?? '',
            $r['beneficiary_type_id'] ?? '',
            typeLabelFromRow($r),
            $r['file_number'] ?? '',
            $r['full_name'] ?? '',
            $r['id_number'] ?? '',
            $r['phone'] ?? '',
            $r['cash_amount'] ?? '',
            $r['details_text'] ?? '',
            $r['notes'] ?? '',
            $r['archived_by_admin_id'] ?? '',
        ]);
    }

    fclose($out);
    exit;
}

/* ───────────────────────────── Page Results ───────────────────────────── */

$stmt = $pdo->prepare(
    "SELECT
        a.*,
        bt.name_ar AS type_name
     FROM distribution_items_archive a
     LEFT JOIN beneficiary_types bt ON bt.id = a.beneficiary_type_id
     $sqlWhere
     ORDER BY a.archived_at DESC, a.id DESC
     LIMIT 2000"
);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/layout.php';

renderPage('أرشيف سجلات التوزيع', 'archive', function() use ($types, $type, $q, $rows) {
    $exportUrl = ADMIN_PATH . '/distribution_items_archive.php?export=csv'
        . '&type=' . (int)$type
        . '&q=' . urlencode($q);
?>
    <?= renderFlash() ?>

    <div class="card mb-3">
        <div class="card-header fw-bold d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span>البحث والفلترة</span>
            <a class="btn btn-sm btn-outline-success" href="<?= e($exportUrl) ?>">
                تصدير CSV
            </a>
        </div>

        <div class="card-body">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <select name="type" class="form-select" onchange="this.form.submit()">
                        <option value="0">جميع الأنواع</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= (int)$t['id'] ?>" <?= (int)$type === (int)$t['id'] ? 'selected' : '' ?>>
                                <?= e((string)($t['name'] ?? $t['name_ar'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-7">
                    <input class="form-control"
                           name="q"
                           value="<?= e($q) ?>"
                           placeholder="ابحث بالاسم أو الهوية أو الهاتف أو رقم الملف أو رقم التوزيعة">
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-outline-primary w-100" type="submit">بحث</button>
                    <a class="btn btn-outline-secondary w-100" href="<?= e(ADMIN_PATH) ?>/distribution_items_archive.php">مسح</a>
                </div>

                <div class="col-12 small text-muted mt-2">
                    يعرض آخر <strong>2000</strong> سجل داخل الصفحة للأداء.
                    زر <strong>تصدير CSV</strong> يصدر كل النتائج حسب الفلترة والبحث.
                </div>
            </form>
        </div>
    </div>

    <form method="post" action="<?= e(ADMIN_PATH) ?>/distribution_items_archive.php" id="archiveBulkForm">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="bulk_delete_archive">
        <input type="hidden" name="type" value="<?= (int)$type ?>">
        <input type="hidden" name="q" value="<?= e($q) ?>">

        <div class="card">
            <div class="card-header fw-bold d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    النتائج <span class="badge bg-secondary ms-1"><?= count($rows) ?></span>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge bg-dark" id="selectedArchiveCount">المحدد: 0</span>

                    <button class="btn btn-sm btn-outline-danger" type="submit"
                            onclick="return confirmArchiveBulkDelete();">
                        حذف المحدد
                    </button>
                </div>
            </div>

            <div class="card-body p-0">
                <?php if (!$rows): ?>
                    <div class="p-4 text-center text-muted">لا توجد سجلات أرشيف.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle" id="archiveTable">
                            <thead>
                            <tr>
                                <th style="width:40px" class="text-center">
                                    <input type="checkbox" id="checkAllArchive">
                                </th>
                                <th>#</th>
                                <th>تاريخ الأرشفة</th>
                                <th>رقم التوزيعة</th>
                                <th>رقم الملف</th>
                                <th>الاسم</th>
                                <th>النوع</th>
                                <th>الهوية</th>
                                <th>الهاتف</th>
                                <th>المبلغ</th>
                                <th>تفاصيل</th>
                                <th>ملاحظات</th>
                                <th style="width:220px">إجراءات</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td class="text-center">
                                        <input class="archive-rowcb" type="checkbox" name="ids[]" value="<?= (int)($r['id'] ?? 0) ?>">
                                    </td>
                                    <td><?= e((string)($r['id'] ?? '')) ?></td>
                                    <td class="text-muted small"><?= e((string)($r['archived_at'] ?? '')) ?></td>
                                    <td class="fw-bold"><?= e((string)($r['distribution_id'] ?? '')) ?></td>
                                    <td class="fw-bold text-primary"><?= e((string)($r['file_number'] ?? '—')) ?></td>
                                    <td><?= e((string)($r['full_name'] ?? '—')) ?></td>
                                    <td>
                                        <?php $typeName = (string)($r['type_name'] ?? ''); ?>
                                        <?= $typeName !== '' ? e($typeName) : '—' ?>
                                    </td>
                                    <td class="text-muted"><?= e((string)($r['id_number'] ?? '—')) ?></td>
                                    <td><?= e((string)($r['phone'] ?? '—')) ?></td>
                                    <td>
                                        <?php
                                        $cash = $r['cash_amount'] ?? null;
                                        echo ($cash !== null && (float)$cash > 0)
                                            ? e(number_format((float)$cash, 2))
                                            : '—';
                                        ?>
                                    </td>
                                    <td class="small text-muted"><?= e((string)($r['details_text'] ?? '')) ?></td>
                                    <td class="small text-muted"><?= e((string)($r['notes'] ?? '')) ?></td>
                                    <td class="d-flex gap-1 flex-wrap">
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="<?= e(ADMIN_PATH) ?>/print_distribution.php?id=<?= (int)($r['distribution_id'] ?? 0) ?>"
                                           target="_blank">
                                            عرض التوزيعة
                                        </a>

                                        <button type="button"
                                                class="btn btn-sm btn-outline-danger"
                                                onclick="submitSingleArchiveDelete(<?= (int)($r['id'] ?? 0) ?>)">
                                            حذف الأرشيف
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <form method="post" action="<?= e(ADMIN_PATH) ?>/distribution_items_archive.php" id="singleArchiveDeleteForm" class="d-none">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="delete_archive_item">
        <input type="hidden" name="id" id="singleArchiveId" value="">
        <input type="hidden" name="type" value="<?= (int)$type ?>">
        <input type="hidden" name="q" value="<?= e($q) ?>">
    </form>

    <script>
    (function () {
        const table = document.getElementById('archiveTable');
        const checkAll = document.getElementById('checkAllArchive');
        const countBadge = document.getElementById('selectedArchiveCount');

        if (!table || !checkAll || !countBadge) return;

        const checkboxes = Array.from(table.querySelectorAll('.archive-rowcb'));

        function updateCount() {
            const checked = checkboxes.filter(cb => cb.checked).length;
            countBadge.textContent = 'المحدد: ' + checked;

            if (!checkboxes.length) {
                checkAll.checked = false;
                checkAll.indeterminate = false;
                return;
            }

            if (checked === 0) {
                checkAll.checked = false;
                checkAll.indeterminate = false;
            } else if (checked === checkboxes.length) {
                checkAll.checked = true;
                checkAll.indeterminate = false;
            } else {
                checkAll.checked = false;
                checkAll.indeterminate = true;
            }
        }

        checkAll.addEventListener('change', function () {
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
            });
            updateCount();
        });

        checkboxes.forEach(cb => cb.addEventListener('change', updateCount));

        window.confirmArchiveBulkDelete = function () {
            const checked = checkboxes.filter(cb => cb.checked).length;
            if (!checked) {
                alert('حدد سجل أرشيف واحدًا على الأقل.');
                return false;
            }
            return confirm('سيتم حذف ' + checked + ' سجل أرشيف نهائيًا. هل تريد المتابعة؟');
        };

        window.submitSingleArchiveDelete = function (id) {
            if (!confirm('هل تريد حذف سجل الأرشيف هذا نهائيًا؟')) return false;

            const input = document.getElementById('singleArchiveId');
            const form  = document.getElementById('singleArchiveDeleteForm');

            if (!input || !form) return false;

            input.value = String(id);
            form.submit();
            return true;
        };

        updateCount();
    })();
    </script>
<?php
});