<?php
declare(strict_types=1);

/* DEBUG مؤقت */
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

require_once __DIR__ . '/../admin/bootstrap.php';
requireLogin();

$pdo = getPDO();

/* ───────────────────────── Helpers ───────────────────────── */

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

function ayniBackUrl(string $type, string $q, int $edit = 0): string
{
    $qs = [];

    if ($type !== '') {
        $qs[] = 'type=' . urlencode($type);
    }

    if ($q !== '') {
        $qs[] = 'q=' . urlencode($q);
    }

    if ($edit > 0) {
        $qs[] = 'edit=' . $edit;
    }

    return ADMIN_PATH . '/../ayni/index.php' . ($qs ? ('?' . implode('&', $qs)) : '');
}

function currentAdminIdOrNull(): ?int
{
    if (!empty($_SESSION['admin_id'])) {
        $id = (int)$_SESSION['admin_id'];
        return $id > 0 ? $id : null;
    }

    return null;
}

function normalizeAyniType(string $type): string
{
    $allowed = ['ملابس', 'طرود', 'منظفات', 'أخرى'];
    return in_array($type, $allowed, true) ? $type : 'أخرى';
}

function safeDecimal3($value): float
{
    $s = trim((string)$value);

    if ($s === '') {
        return 0.0;
    }

    $s = str_replace(',', '.', $s);
    $s = preg_replace('/[^0-9.]+/', '', $s) ?? '0';

    return (float)$s;
}

$types = ['ملابس', 'طرود', 'منظفات', 'أخرى'];

/* ───────────────────────── POST Actions ───────────────────────── */

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();

        $action = (string)($_POST['action'] ?? '');
        $filterType = trim((string)($_POST['filter_type'] ?? ''));
        $filterQ    = trim((string)($_POST['filter_q'] ?? ''));

        if ($action === 'save') {
            $id             = (int)($_POST['id'] ?? 0);
            $donorName      = trim((string)($_POST['donor_name'] ?? ''));
            $donationType   = normalizeAyniType(trim((string)($_POST['donation_type'] ?? '')));
            $quantity       = (int)($_POST['quantity'] ?? 0);
            $donationTitle  = trim((string)($_POST['donation_title'] ?? ''));
            $donationDate   = trim((string)($_POST['donation_date'] ?? ''));
            $receiptNo      = trim((string)($_POST['receipt_no'] ?? ''));
            $estimatedValue = safeDecimal3($_POST['estimated_value'] ?? 0);
            $notes          = trim((string)($_POST['notes'] ?? ''));

            $errors = [];
            if ($donorName === '') $errors[] = 'اسم المتبرع مطلوب.';
            if ($quantity < 0) $errors[] = 'العدد غير صالح.';

            if ($errors) {
                flashError(implode(' | ', $errors));
                redirect(ayniBackUrl($filterType, $filterQ, $id));
            }

            if ($id > 0) {
                $stmt = $pdo->prepare(
                    "UPDATE ayni_entries
                     SET donor_name = ?, donation_type = ?, quantity = ?, donation_title = ?, donation_date = ?,
                         receipt_no = ?, estimated_value = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?"
                );

                $stmt->execute([
                    $donorName,
                    $donationType,
                    $quantity,
                    ($donationTitle !== '' ? $donationTitle : null),
                    ($donationDate !== '' ? $donationDate : null),
                    ($receiptNo !== '' ? $receiptNo : null),
                    $estimatedValue,
                    ($notes !== '' ? $notes : null),
                    $id
                ]);

                flashSuccess('تم حفظ التعديلات.');
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO ayni_entries
                     (donor_name, donation_type, quantity, donation_title, donation_date, receipt_no, estimated_value, notes, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );

                $stmt->execute([
                    $donorName,
                    $donationType,
                    $quantity,
                    ($donationTitle !== '' ? $donationTitle : null),
                    ($donationDate !== '' ? $donationDate : null),
                    ($receiptNo !== '' ? $receiptNo : null),
                    $estimatedValue,
                    ($notes !== '' ? $notes : null),
                    currentAdminIdOrNull()
                ]);

                flashSuccess('تمت إضافة الوارد العيني بنجاح.');
            }

            redirect(ayniBackUrl($filterType, $filterQ));
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                flashError('معرّف غير صالح.');
                redirect(ayniBackUrl($filterType, $filterQ));
            }

            $stmt = $pdo->prepare("DELETE FROM ayni_entries WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                flashSuccess('تم حذف السجل.');
            } else {
                flashError('السجل غير موجود.');
            }

            redirect(ayniBackUrl($filterType, $filterQ));
        }
    }
} catch (Throwable $e) {
    echo '<pre style="padding:16px;background:#fff3f3;border:1px solid #f1b8b8;color:#900;font-family:monospace;direction:ltr">';
    echo "AYNI INDEX ERROR\n";
    echo "MESSAGE: " . $e->getMessage() . "\n";
    echo "FILE: " . $e->getFile() . "\n";
    echo "LINE: " . $e->getLine() . "\n\n";
    echo $e->getTraceAsString();
    echo '</pre>';
    exit;
}

/* ───────────────────────── GET: list + edit ───────────────────────── */

try {
    $filterType = trim((string)($_GET['type'] ?? ''));
    $filterQ    = trim((string)($_GET['q'] ?? ''));
    $editId     = (int)($_GET['edit'] ?? 0);

    $editRow = null;

    if ($editId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM ayni_entries WHERE id = ? LIMIT 1");
        $stmt->execute([$editId]);
        $editRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$editRow) {
            flashError('السجل غير موجود.');
            redirect(ayniBackUrl($filterType, $filterQ));
        }
    }

    $where = [];
    $params = [];

    if ($filterType !== '') {
        $where[] = 'donation_type = ?';
        $params[] = $filterType;
    }

    if ($filterQ !== '') {
        $where[] = '(
            donor_name LIKE ?
            OR donation_type LIKE ?
            OR donation_title LIKE ?
            OR receipt_no LIKE ?
            OR CAST(id AS CHAR) LIKE ?
        )';

        $like = '%' . $filterQ . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }

    $sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stmt = $pdo->prepare(
        "SELECT *
         FROM ayni_entries
         $sqlWhere
         ORDER BY donation_date DESC, id DESC"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    echo '<pre style="padding:16px;background:#fff3f3;border:1px solid #f1b8b8;color:#900;font-family:monospace;direction:ltr">';
    echo "AYNI LIST ERROR\n";
    echo "MESSAGE: " . $e->getMessage() . "\n";
    echo "FILE: " . $e->getFile() . "\n";
    echo "LINE: " . $e->getLine() . "\n\n";
    echo $e->getTraceAsString();
    echo '</pre>';
    exit;
}

require_once __DIR__ . '/../admin/layout.php';

renderPage('الواردات العينية', 'ayni', function() use ($types, $filterType, $filterQ, $editRow, $rows) {
?>
    <?= renderFlash() ?>

    <div class="card mb-3">
        <div class="card-header fw-bold"><?= $editRow ? 'تعديل وارد عيني' : 'إضافة وارد عيني جديد' ?></div>
        <div class="card-body">
            <form method="post" action="/zaka/ayni/index.php" class="row g-3">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int)($editRow['id'] ?? 0) ?>">
                <input type="hidden" name="filter_type" value="<?= e($filterType) ?>">
                <input type="hidden" name="filter_q" value="<?= e($filterQ) ?>">

                <div class="col-md-4">
                    <label class="form-label">اسم المتبرع *</label>
                    <input class="form-control" name="donor_name" required value="<?= e((string)($editRow['donor_name'] ?? '')) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">نوع التبرع *</label>
                    <select class="form-select" name="donation_type" required>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= e($t) ?>" <?= (($editRow['donation_type'] ?? 'أخرى') === $t) ? 'selected' : '' ?>>
                                <?= e($t) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">عدد التبرع</label>
                    <input type="number" min="0" class="form-control" name="quantity" value="<?= e((string)($editRow['quantity'] ?? '0')) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">عنوان التبرع</label>
                    <input class="form-control" name="donation_title" value="<?= e((string)($editRow['donation_title'] ?? '')) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">تاريخ التبرع</label>
                    <input type="date" class="form-control" name="donation_date" value="<?= e((string)($editRow['donation_date'] ?? '')) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">رقم الوصل</label>
                    <input class="form-control" name="receipt_no" value="<?= e((string)($editRow['receipt_no'] ?? '')) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">مقدّرة بالدينار</label>
                    <input type="number" step="0.001" min="0" class="form-control" name="estimated_value" value="<?= e((string)($editRow['estimated_value'] ?? '0.000')) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">طباعة</label>
                    <div class="d-flex gap-2">
                        <?php if (!empty($editRow['id'])): ?>
                            <a class="btn btn-outline-primary w-100"
                               target="_blank"
                               href="/zaka/ayni/print.php?id=<?= (int)$editRow['id'] ?>">
                                طباعة السجل
                            </a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary w-100" type="button" disabled>احفظ أولًا للطباعة</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-12">
                    <label class="form-label">ملاحظات</label>
                    <textarea class="form-control" name="notes" rows="2"><?= e((string)($editRow['notes'] ?? '')) ?></textarea>
                </div>

                <div class="col-12 d-flex gap-2 flex-wrap">
                    <button class="btn btn-primary" type="submit">حفظ</button>

                    <?php if ($editRow): ?>
                        <a class="btn btn-outline-secondary"
                           href="/zaka/ayni/index.php?type=<?= urlencode($filterType) ?>&q=<?= urlencode($filterQ) ?>">
                            إلغاء
                        </a>
                    <?php endif; ?>

                    <a class="btn btn-outline-dark"
                       target="_blank"
                       href="/zaka/ayni/print_list.php?type=<?= urlencode($filterType) ?>&q=<?= urlencode($filterQ) ?>">
                        طباعة الكشف
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-bold">البحث والفلترة</div>
        <div class="card-body">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <select name="type" class="form-select">
                        <option value="">جميع الأنواع</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= e($t) ?>" <?= $filterType === $t ? 'selected' : '' ?>>
                                <?= e($t) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-7">
                    <input class="form-control" name="q" value="<?= e($filterQ) ?>" placeholder="ابحث باسم المتبرع أو النوع أو العنوان أو رقم الوصل">
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-outline-primary w-100" type="submit">بحث</button>
                    <a class="btn btn-outline-secondary w-100" href="/zaka/ayni/index.php">مسح</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-bold d-flex justify-content-between align-items-center">
            <span>سجل الواردات العينية</span>
            <span class="badge bg-secondary"><?= count($rows) ?></span>
        </div>
        <div class="card-body p-0">
            <?php if (!$rows): ?>
                <div class="p-4 text-center text-muted">لا توجد سجلات.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                        <tr>
                            <th>الرقم</th>
                            <th>اسم المتبرع</th>
                            <th>نوع التبرع</th>
                            <th>عدد التبرع</th>
                            <th>عنوان التبرع</th>
                            <th>تاريخ التبرع</th>
                            <th>رقم الوصل</th>
                            <th>مقدّرة بالدينار</th>
                            <th style="width:260px">إجراءات</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td class="fw-bold"><?= (int)$r['id'] ?></td>
                                <td><?= e((string)$r['donor_name']) ?></td>
                                <td><span class="badge bg-primary"><?= e((string)$r['donation_type']) ?></span></td>
                                <td><?= e((string)$r['quantity']) ?></td>
                                <td><?= e((string)($r['donation_title'] ?? '—')) ?></td>
                                <td><?= e((string)($r['donation_date'] ?? '—')) ?></td>
                                <td><?= e((string)($r['receipt_no'] ?? '—')) ?></td>
                                <td><?= e(number_format((float)($r['estimated_value'] ?? 0), 3)) ?></td>
                                <td class="d-flex gap-1 flex-wrap">
                                    <a class="btn btn-sm btn-outline-primary"
                                       href="/zaka/ayni/index.php?edit=<?= (int)$r['id'] ?>&type=<?= urlencode($filterType) ?>&q=<?= urlencode($filterQ) ?>">
                                        تعديل
                                    </a>

                                    <a class="btn btn-sm btn-outline-dark"
                                       target="_blank"
                                       href="/zaka/ayni/print.php?id=<?= (int)$r['id'] ?>">
                                        طباعة
                                    </a>

                                    <form method="post" action="/zaka/ayni/index.php" class="d-inline"
                                          onsubmit="return confirm('حذف هذا السجل؟');">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                        <input type="hidden" name="filter_type" value="<?= e($filterType) ?>">
                                        <input type="hidden" name="filter_q" value="<?= e($filterQ) ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">حذف</button>
                                    </form>
                                </td>
                            </tr>

                            <?php if (!empty($r['notes'])): ?>
                                <tr>
                                    <td colspan="9" class="small text-muted bg-light">
                                        <strong>ملاحظات:</strong> <?= e((string)$r['notes']) ?>
                                    </td>
                                </tr>
                            <?php endif; ?>

                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php
});