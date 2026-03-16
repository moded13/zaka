<?php
/**
 * admin/beneficiaries.php
 *
 * Features:
 * - List + Add + Edit beneficiaries
 * - Server search + type filter
 * - Live filter inside table
 * - Bulk actions:
 *   - Soft disable
 *   - Hard delete with archive
 * - Single actions:
 *   - Soft disable
 *   - Hard delete with archive
 * - Preview/Print selected visible rows
 * - Fixed bulk delete/select-all behavior
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
requireLogin();

$pdo   = getPDO();
$types = getBeneficiaryTypes();

/* ───────────────────────────── Helpers ───────────────────────────── */

function normalizeDigits(string $s): string
{
    return preg_replace('/\D+/', '', $s) ?? '';
}

function normalizeStatus(string $s): string
{
    $s = trim($s);
    return in_array($s, ['active', 'inactive'], true) ? $s : 'active';
}

function safeFloatOrNull($v): ?float
{
    if ($v === null) return null;
    $s = trim((string)$v);
    if ($s === '') return null;
    $s = str_replace(',', '.', $s);
    $s = preg_replace('/[^0-9.]+/', '', $s) ?? '';
    if ($s === '') return null;
    return (float)$s;
}

function backUrl(int $type, string $q, int $edit = 0): string
{
    $qs = [];
    if ($edit > 0) $qs[] = 'edit=' . $edit;
    if ($type > 0) $qs[] = 'type=' . $type;
    if ($q !== '') $qs[] = 'q=' . urlencode($q);
    return ADMIN_PATH . '/beneficiaries.php' . ($qs ? ('?' . implode('&', $qs)) : '');
}

function getAdminIdOrNull(): ?int
{
    if (function_exists('currentAdmin')) {
        $a = currentAdmin();
        $id = (int)($a['id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    if (!empty($_SESSION['admin_id'])) {
        $id = (int)$_SESSION['admin_id'];
        return $id > 0 ? $id : null;
    }

    return null;
}

/**
 * Archives distribution_items for a set of beneficiaries, with snapshot of beneficiary data.
 */
function archiveDistributionItemsForBeneficiaries(PDO $pdo, array $beneficiaryIds, ?int $archivedByAdminId = null): void
{
    $beneficiaryIds = array_values(array_unique(array_filter(array_map('intval', $beneficiaryIds))));
    if (!$beneficiaryIds) return;

    $in = implode(',', array_fill(0, count($beneficiaryIds), '?'));

    $sql =
        "INSERT INTO distribution_items_archive
         (distribution_id, beneficiary_id, original_distribution_item_id,
          beneficiary_type_id, file_number, full_name, id_number, phone,
          cash_amount, details_text, notes, archived_by_admin_id)
         SELECT
          di.distribution_id,
          di.beneficiary_id,
          di.id,
          b.beneficiary_type_id,
          b.file_number,
          b.full_name,
          b.id_number,
          b.phone,
          di.cash_amount,
          di.details_text,
          di.notes,
          ?
         FROM distribution_items di
         JOIN beneficiaries b ON b.id = di.beneficiary_id
         WHERE di.beneficiary_id IN ($in)";

    $params = array_merge([$archivedByAdminId], $beneficiaryIds);
    $pdo->prepare($sql)->execute($params);
}

function softDisableBeneficiaries(PDO $pdo, array $ids): int
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return 0;

    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("UPDATE beneficiaries SET status='inactive', updated_at=CURRENT_TIMESTAMP WHERE id IN ($in)");
    $stmt->execute($ids);
    return $stmt->rowCount();
}

/**
 * Hard delete beneficiaries:
 * - archive their distribution_items
 * - delete distribution_items
 * - delete beneficiaries
 * Returns: [deletedCount, archivedItemsCount]
 */
function hardDeleteBeneficiariesWithArchive(PDO $pdo, array $ids, ?int $archivedByAdminId = null): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return [0, 0];

    $in = implode(',', array_fill(0, count($ids), '?'));

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM distribution_items WHERE beneficiary_id IN ($in)");
    $cnt->execute($ids);
    $toArchive = (int)$cnt->fetchColumn();

    archiveDistributionItemsForBeneficiaries($pdo, $ids, $archivedByAdminId);

    $pdo->prepare("DELETE FROM distribution_items WHERE beneficiary_id IN ($in)")->execute($ids);

    $del = $pdo->prepare("DELETE FROM beneficiaries WHERE id IN ($in)");
    $del->execute($ids);

    return [(int)$del->rowCount(), $toArchive];
}

/* ───────────────────────────── POST Actions ───────────────────────────── */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action = (string)($_POST['action'] ?? '');
    $type   = (int)($_POST['type'] ?? 0);
    $q      = trim((string)($_POST['q'] ?? ''));

    // SAVE (add/edit)
    if ($action === 'save') {
        $editId = (int)($_POST['id'] ?? 0);

        $beneficiaryTypeId = (int)($_POST['beneficiary_type_id'] ?? 0);
        $fileNumber        = (int)normalizeDigits((string)($_POST['file_number'] ?? ''));
        $fullName          = trim((string)($_POST['full_name'] ?? ''));
        $idNumber          = normalizeDigits((string)($_POST['id_number'] ?? ''));
        $phone             = normalizeDigits((string)($_POST['phone'] ?? ''));
        $monthlyCash       = safeFloatOrNull($_POST['monthly_cash'] ?? null);
        $defaultItem       = trim((string)($_POST['default_item'] ?? ''));
        $notes             = trim((string)($_POST['notes'] ?? ''));
        $status            = normalizeStatus((string)($_POST['status'] ?? 'active'));

        $errs = [];
        if ($beneficiaryTypeId <= 0) $errs[] = 'نوع المستفيد مطلوب.';
        if ($fileNumber <= 0) $errs[] = 'رقم الملف مطلوب.';
        if ($fullName === '') $errs[] = 'الاسم الكامل مطلوب.';

        if ($errs) {
            flashError(implode(' | ', $errs));
            redirect(backUrl($type, $q, $editId));
        }

        try {
            if ($editId > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE beneficiaries
                     SET beneficiary_type_id=?, file_number=?, full_name=?, id_number=?, phone=?, monthly_cash=?,
                         default_item=?, notes=?, status=?, updated_at=CURRENT_TIMESTAMP
                     WHERE id=?'
                );
                $stmt->execute([
                    $beneficiaryTypeId,
                    $fileNumber,
                    $fullName,
                    ($idNumber !== '' ? $idNumber : null),
                    ($phone !== '' ? $phone : null),
                    $monthlyCash,
                    ($defaultItem !== '' ? $defaultItem : null),
                    ($notes !== '' ? $notes : null),
                    $status,
                    $editId
                ]);
                flashSuccess('تم حفظ التعديلات.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO beneficiaries
                     (beneficiary_type_id, file_number, full_name, id_number, phone, monthly_cash, default_item, notes, status)
                     VALUES (?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $beneficiaryTypeId,
                    $fileNumber,
                    $fullName,
                    ($idNumber !== '' ? $idNumber : null),
                    ($phone !== '' ? $phone : null),
                    $monthlyCash,
                    ($defaultItem !== '' ? $defaultItem : null),
                    ($notes !== '' ? $notes : null),
                    $status
                ]);
                flashSuccess('تمت الإضافة بنجاح.');
            }
        } catch (Throwable $e) {
            flashError('خطأ أثناء الحفظ: ' . $e->getMessage());
        }

        redirect(backUrl($type, $q));
    }

    // SINGLE: soft delete
    if ($action === 'soft_delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flashError('معرّف غير صالح.');
            redirect(backUrl($type, $q));
        }

        try {
            $pdo->prepare("UPDATE beneficiaries SET status='inactive', updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);
            flashSuccess('تم تعطيل المستفيد (حذف ناعم).');
        } catch (Throwable $e) {
            flashError('خطأ: ' . $e->getMessage());
        }

        redirect(backUrl($type, $q));
    }

    // SINGLE: hard delete with archive
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flashError('معرّف غير صالح.');
            redirect(backUrl($type, $q));
        }

        $stmt = $pdo->prepare('SELECT beneficiary_type_id FROM beneficiaries WHERE id = ?');
        $stmt->execute([$id]);
        $typeId = (int)($stmt->fetchColumn() ?: 0);

        $pdo->beginTransaction();
        try {
            $adminId = getAdminIdOrNull();
            [$deletedCount, $archivedItems] = hardDeleteBeneficiariesWithArchive($pdo, [$id], $adminId);

            if ($typeId > 0 && function_exists('renumberBeneficiariesForType')) {
                renumberBeneficiariesForType($typeId);
            }

            $pdo->commit();
            flashSuccess("تم حذف المستفيد نهائيًا. (تمت أرشفة {$archivedItems} سجل توزيع)");
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flashError('خطأ أثناء الحذف: ' . $e->getMessage());
        }

        redirect(backUrl($type, $q));
    }

    // BULK: soft delete
    if ($action === 'bulk_soft_delete') {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));
        if (!$ids) {
            flashError('لم يتم تحديد أي مستفيد.');
            redirect(backUrl($type, $q));
        }

        try {
            $count = softDisableBeneficiaries($pdo, $ids);
            flashSuccess("تم تعطيل {$count} مستفيد (حذف ناعم).");
        } catch (Throwable $e) {
            flashError('خطأ: ' . $e->getMessage());
        }

        redirect(backUrl($type, $q));
    }

    // BULK: hard delete with archive
    if ($action === 'bulk_delete') {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));
        if (!$ids) {
            flashError('لم يتم تحديد أي مستفيد.');
            redirect(backUrl($type, $q));
        }

        $in = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $pdo->prepare("SELECT DISTINCT beneficiary_type_id FROM beneficiaries WHERE id IN ($in)");
        $stmt->execute($ids);
        $typeIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        $pdo->beginTransaction();
        try {
            $adminId = getAdminIdOrNull();
            [$deletedCount, $archivedItems] = hardDeleteBeneficiariesWithArchive($pdo, $ids, $adminId);

            if (function_exists('renumberBeneficiariesForType')) {
                foreach ($typeIds as $tid) {
                    if ($tid > 0) renumberBeneficiariesForType($tid);
                }
            }

            $pdo->commit();
            flashSuccess("تم حذف {$deletedCount} مستفيد نهائيًا. (تمت أرشفة {$archivedItems} سجل توزيع) وتمت إعادة ترتيب أرقام الملفات.");
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flashError('خطأ أثناء الحذف: ' . $e->getMessage());
        }

        redirect(backUrl($type, $q));
    }

    redirect(backUrl($type, $q));
}

/* ───────────────────────────── GET: list + edit ───────────────────────────── */

$type = (int)($_GET['type'] ?? 0);
$q    = trim((string)($_GET['q'] ?? ''));
$edit = (int)($_GET['edit'] ?? 0);

$editRow = null;
if ($edit > 0) {
    $stmt = $pdo->prepare('SELECT * FROM beneficiaries WHERE id = ?');
    $stmt->execute([$edit]);
    $editRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$editRow) {
        flashError('المستفيد غير موجود.');
        redirect(backUrl($type, $q));
    }
}

$where = [];
$params = [];

if ($type > 0) {
    $where[] = 'b.beneficiary_type_id = ?';
    $params[] = $type;
}
if ($q !== '') {
    $where[] = '(b.full_name LIKE ? OR b.id_number LIKE ? OR b.phone LIKE ? OR b.file_number = ?)';
    $like = '%' . $q . '%';
    $digits = normalizeDigits($q);
    array_push($params, $like, $like, $like, (int)$digits);
}

$sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$stmt = $pdo->prepare(
    "SELECT b.*, bt.name_ar AS type_name
     FROM beneficiaries b
     JOIN beneficiary_types bt ON bt.id = b.beneficiary_type_id
     $sqlWhere
     ORDER BY b.beneficiary_type_id, b.file_number, b.id DESC"
);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ───────────────────────────── UI ───────────────────────────── */

require_once __DIR__ . '/layout.php';

renderPage('المستفيدون', 'beneficiaries', function() use ($types, $type, $q, $rows, $editRow) {
?>
    <?= renderFlash() ?>

    <div class="card mb-3">
        <div class="card-header fw-bold"><?= $editRow ? 'تعديل بيانات المستفيد' : 'إضافة مستفيد جديد' ?></div>
        <div class="card-body">
            <form method="post" action="<?= e(ADMIN_PATH) ?>/beneficiaries.php" class="row g-3">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int)($editRow['id'] ?? 0) ?>">
                <input type="hidden" name="type" value="<?= (int)$type ?>">
                <input type="hidden" name="q" value="<?= e($q) ?>">

                <div class="col-md-3">
                    <label class="form-label">نوع المستفيد *</label>
                    <select class="form-select" name="beneficiary_type_id" required>
                        <option value="">— اختر النوع —</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= (int)$t['id'] ?>" <?= (int)($editRow['beneficiary_type_id'] ?? $type) === (int)$t['id'] ? 'selected' : '' ?>>
                                <?= e((string)($t['name'] ?? $t['name_ar'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">رقم الملف *</label>
                    <input class="form-control" name="file_number" required value="<?= e((string)($editRow['file_number'] ?? '')) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">الاسم الكامل *</label>
                    <input class="form-control" name="full_name" required value="<?= e((string)($editRow['full_name'] ?? '')) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">الحالة</label>
                    <select class="form-select" name="status">
                        <option value="active" <?= (($editRow['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>نشط</option>
                        <option value="inactive" <?= (($editRow['status'] ?? 'active') === 'inactive') ? 'selected' : '' ?>>غير نشط</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">رقم الهوية</label>
                    <input class="form-control" name="id_number" value="<?= e((string)($editRow['id_number'] ?? '')) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">رقم الهاتف</label>
                    <input class="form-control" name="phone" value="<?= e((string)($editRow['phone'] ?? '')) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">راتب نقدي (دينار)</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="monthly_cash" value="<?= e((string)($editRow['monthly_cash'] ?? '')) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">المادة/الوصف الافتراضي</label>
                    <input class="form-control" name="default_item" value="<?= e((string)($editRow['default_item'] ?? '')) ?>">
                </div>

                <div class="col-md-12">
                    <label class="form-label">ملاحظات</label>
                    <input class="form-control" name="notes" value="<?= e((string)($editRow['notes'] ?? '')) ?>">
                </div>

                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">حفظ</button>
                    <?php if ($editRow): ?>
                        <a class="btn btn-outline-secondary" href="<?= e(ADMIN_PATH) ?>/beneficiaries.php?type=<?= (int)$type ?>&q=<?= urlencode($q) ?>">إلغاء</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
        <form method="get" class="d-flex gap-2 align-items-center">
            <select name="type" class="form-select" style="width:220px" onchange="this.form.submit()">
                <option value="0">جميع الأنواع</option>
                <?php foreach ($types as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= (int)$type === (int)$t['id'] ? 'selected' : '' ?>>
                        <?= e((string)($t['name'] ?? $t['name_ar'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="بحث سيرفر: الاسم/الهوية/الهاتف/رقم الملف">
            <button class="btn btn-outline-primary" type="submit">بحث</button>
            <a class="btn btn-outline-secondary" href="<?= e(ADMIN_PATH) ?>/beneficiaries.php">مسح</a>
        </form>

        <div class="d-flex gap-2 align-items-center flex-wrap">
            <input id="liveFilterBen" class="form-control" style="width:280px"
                   placeholder="فلترة فورية داخل الجدول (من أول حرف)">
            <span class="badge bg-dark" id="selectedCountBadge">المحدد: 0</span>

            <button type="button" class="btn btn-outline-primary" id="previewSelectedBtn">
                معاينة المحدد
            </button>
            <button type="button" class="btn btn-outline-secondary" id="printSelectedBtn">
                طباعة المحدد
            </button>
        </div>
    </div>

    <form method="post" action="<?= e(ADMIN_PATH) ?>/beneficiaries.php" id="bulkForm">
        <?= csrfField() ?>
        <input type="hidden" name="type" value="<?= (int)$type ?>">
        <input type="hidden" name="q" value="<?= e($q) ?>">

        <div class="d-flex gap-2 flex-wrap mb-2">
            <button class="btn btn-outline-secondary" name="action" value="bulk_soft_delete" type="submit"
                    onclick="return confirmBulkAction('soft');">
                تعطيل المحدد
            </button>

            <button class="btn btn-outline-danger" name="action" value="bulk_delete" type="submit"
                    onclick="return confirmBulkAction('delete');">
                حذف نهائي (مع أرشفة)
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle" id="beneficiariesTable">
                <thead>
                <tr>
                    <th style="width:40px" class="text-center">
                        <input type="checkbox" id="checkAllVisible">
                    </th>
                    <th>رقم الملف</th>
                    <th>النوع</th>
                    <th>الاسم الكامل</th>
                    <th>رقم الهوية</th>
                    <th>الهاتف</th>
                    <th>راتب (دينار)</th>
                    <th>الحالة</th>
                    <th style="width:260px">إجراءات</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php
                        $rawText = trim(
                            ($r['full_name'] ?? '') . ' ' .
                            ($r['id_number'] ?? '') . ' ' .
                            ($r['phone'] ?? '') . ' ' .
                            ($r['file_number'] ?? '') . ' ' .
                            ($r['type_name'] ?? '')
                        );
                    ?>
                    <tr data-filter-text="<?= e($rawText) ?>">
                        <td class="text-center">
                            <input class="rowcb" type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>">
                        </td>
                        <td class="fw-bold text-primary"><?= e((string)$r['file_number']) ?></td>
                        <td><span class="badge bg-primary bg-opacity-75"><?= e((string)$r['type_name']) ?></span></td>
                        <td><?= e((string)$r['full_name']) ?></td>
                        <td class="text-muted"><?= e((string)($r['id_number'] ?? '—')) ?></td>
                        <td><?= e((string)($r['phone'] ?? '—')) ?></td>
                        <td><?= $r['monthly_cash'] !== null ? e(number_format((float)$r['monthly_cash'], 2)) : '—' ?></td>
                        <td><?= ($r['status'] === 'active') ? 'نشط' : 'غير نشط' ?></td>
                        <td class="d-flex gap-1 flex-wrap">
                            <a class="btn btn-sm btn-outline-primary"
                               href="<?= e(ADMIN_PATH) ?>/beneficiaries.php?edit=<?= (int)$r['id'] ?>&type=<?= (int)$type ?>&q=<?= urlencode($q) ?>">
                                تعديل
                            </a>

                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    onclick="submitSingleAction('soft_delete', <?= (int)$r['id'] ?>, 'تعطيل هذا المستفيد (حذف ناعم)؟')">
                                تعطيل
                            </button>

                            <button type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    onclick="submitSingleAction('delete', <?= (int)$r['id'] ?>, 'حذف نهائي؟ سيتم أرشفة سجلات التوزيع التابعة ثم حذفها.')">
                                حذف نهائي
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">لا توجد بيانات.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>

    <!-- نموذج مخفي للإجراءات الفردية -->
    <form method="post" action="<?= e(ADMIN_PATH) ?>/beneficiaries.php" id="singleActionForm" class="d-none">
        <?= csrfField() ?>
        <input type="hidden" name="action" id="singleActionInput" value="">
        <input type="hidden" name="id" id="singleIdInput" value="">
        <input type="hidden" name="type" value="<?= (int)$type ?>">
        <input type="hidden" name="q" value="<?= e($q) ?>">
    </form>

    <script>
    (function () {
        const filterInput = document.getElementById('liveFilterBen');
        const table = document.getElementById('beneficiariesTable');
        const previewBtn = document.getElementById('previewSelectedBtn');
        const printBtn = document.getElementById('printSelectedBtn');
        const checkAllVisible = document.getElementById('checkAllVisible');
        const bulkForm = document.getElementById('bulkForm');
        const selectedCountBadge = document.getElementById('selectedCountBadge');

        if (!table || !filterInput || !previewBtn || !printBtn || !checkAllVisible || !bulkForm) return;

        const rows = Array.from(table.querySelectorAll('tbody tr'));

        const arabicToLatinDigits = (s) => (s || '')
            .replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d))
            .replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d));

        const norm = (s) => arabicToLatinDigits((s || '').toString())
            .toLowerCase()
            .replace(/\s+/g, ' ')
            .trim();

        function visibleRows() {
            return rows.filter(tr => tr.style.display !== 'none');
        }

        function visibleCheckboxes() {
            return visibleRows()
                .map(tr => tr.querySelector('input.rowcb'))
                .filter(Boolean);
        }

        function selectedCheckboxes() {
            return rows
                .map(tr => tr.querySelector('input.rowcb'))
                .filter(cb => cb && cb.checked);
        }

        function updateSelectedCount() {
            const count = selectedCheckboxes().length;
            if (selectedCountBadge) {
                selectedCountBadge.textContent = 'المحدد: ' + count;
            }

            const visibleCbs = visibleCheckboxes();
            if (!visibleCbs.length) {
                checkAllVisible.checked = false;
                checkAllVisible.indeterminate = false;
                return;
            }

            const checkedVisible = visibleCbs.filter(cb => cb.checked).length;
            checkAllVisible.checked = checkedVisible > 0 && checkedVisible === visibleCbs.length;
            checkAllVisible.indeterminate = checkedVisible > 0 && checkedVisible < visibleCbs.length;
        }

        function applyFilter() {
            const q = norm(filterInput.value);

            rows.forEach(tr => {
                const hay = norm(tr.getAttribute('data-filter-text') || '');
                tr.style.display = (!q || hay.includes(q)) ? '' : 'none';
            });

            updateSelectedCount();
        }

        function getVisibleSelectedIds() {
            return visibleRows().map(tr => tr.querySelector('input.rowcb'))
                .filter(cb => cb && cb.checked)
                .map(cb => cb.value);
        }

        function openPreview(print) {
            const ids = getVisibleSelectedIds();
            if (!ids.length) {
                alert('حدد مستفيدين أولاً من الصفوف الظاهرة.');
                return;
            }

            const url = '<?= e(ADMIN_PATH) ?>/beneficiaries_print.php?ids=' + encodeURIComponent(ids.join(','))
                      + (print ? '&print=1' : '');
            window.open(url, '_blank');
        }

        checkAllVisible.addEventListener('change', function () {
            visibleCheckboxes().forEach(cb => {
                cb.checked = this.checked;
            });
            updateSelectedCount();
        });

        rows.forEach(tr => {
            const cb = tr.querySelector('input.rowcb');
            if (cb) {
                cb.addEventListener('change', updateSelectedCount);
            }
        });

        filterInput.addEventListener('input', applyFilter);
        previewBtn.addEventListener('click', () => openPreview(false));
        printBtn.addEventListener('click', () => openPreview(true));

        bulkForm.addEventListener('submit', function (e) {
            const checked = selectedCheckboxes();
            if (!checked.length) {
                e.preventDefault();
                alert('حدد مستفيدًا واحدًا على الأقل.');
                return false;
            }
        });

        window.confirmBulkAction = function (kind) {
            const count = selectedCheckboxes().length;
            if (!count) {
                alert('حدد مستفيدًا واحدًا على الأقل.');
                return false;
            }

            if (kind === 'soft') {
                return confirm('سيتم تعطيل ' + count + ' مستفيد. هل تريد المتابعة؟');
            }

            return confirm('سيتم حذف ' + count + ' مستفيد نهائيًا مع أرشفة سجلات التوزيع التابعة لهم. هل تريد المتابعة؟');
        };

        window.submitSingleAction = function (action, id, message) {
            if (!confirm(message)) return false;

            const form = document.getElementById('singleActionForm');
            const actionInput = document.getElementById('singleActionInput');
            const idInput = document.getElementById('singleIdInput');

            if (!form || !actionInput || !idInput) return false;

            actionInput.value = action;
            idInput.value = String(id);
            form.submit();
            return true;
        };

        applyFilter();
        updateSelectedCount();
    })();
    </script>
<?php
});
