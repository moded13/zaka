<?php
/**
 * admin/import.php (AUTO FILE_NUMBER + CASH + DETAILS)
 *
 * Your Excel columns (common):
 *  B: full_name
 *  C: id_number (may contain letters like T...)
 *  D: phone
 *  E: cash (دينار)
 *  F: details_text (المبلغ كتابة) [optional]
 *
 * Accepted input (TAB/CSV):
 *  - 4 cols: name | id_number | phone | cash
 *  - 5 cols: name | id_number | phone | cash | details_text
 *
 * System behavior:
 * - id is auto-increment (system serial)
 * - file_number is auto-generated sequentially per beneficiary_type_id (max+1, max+2...)
 * - monthly_cash comes from cash column
 * - default_item can be set from details_text (optional) or the "default_item for all" field
 */

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
requireLogin();

$pdo   = getPDO();
$types = getBeneficiaryTypes();

function digitsOnly(string $s): string
{
    return preg_replace('/\D+/', '', $s) ?? '';
}

function detectSeparator(string $raw): string
{
    $lines = preg_split('/\r\n|\r|\n/', trim($raw));
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (str_contains($line, "\t")) return "\t";
        if (str_contains($line, ';'))  return ';';
        if (str_contains($line, ','))  return ',';
        break;
    }
    return "\t";
}

function parseRows(string $raw): array
{
    $rows  = [];
    $lines = preg_split('/\r\n|\r|\n/', trim($raw));
    $sep   = detectSeparator($raw);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        $cols = array_map('trim', explode($sep, $line));
        while (count($cols) > 0 && $cols[array_key_last($cols)] === '') array_pop($cols);

        $rows[] = $cols;
    }

    return $rows;
}

function isHeaderLikeRow(array $row): bool
{
    $joined = mb_strtolower(trim(implode(' ', $row)));
    $keys = ['الاسم', 'الرقم', 'الهوية', 'الوطني', 'الهاتف', 'ملف', 'دينار', 'راتب', 'مبلغ', 'كتابة'];
    foreach ($keys as $k) {
        if (str_contains($joined, mb_strtolower($k))) return true;
    }
    return false;
}

function toCash(?string $s): ?float
{
    $s = trim((string)$s);
    if ($s === '') return null;
    $s = str_replace(',', '.', $s);
    $s = preg_replace('/[^0-9.]+/', '', $s) ?? '';
    if ($s === '') return null;
    return (float)$s;
}

/**
 * Map your row to fields (WITHOUT file_number; we'll generate it).
 * Returns: ['full_name','id_number','phone','monthly_cash','details_text']
 */
function mapRowCash(array $row): ?array
{
    $row = array_values(array_map(fn($x) => trim((string)$x), $row));
    while (count($row) > 0 && $row[array_key_last($row)] === '') array_pop($row);

    $c = count($row);
    if ($c < 4) return null;

    $name = trim((string)($row[0] ?? ''));
    if ($name === '') return null;

    // IMPORTANT: keep id_number as-is (may contain letters)
    $idNumber = trim((string)($row[1] ?? ''));
    $phone    = digitsOnly((string)($row[2] ?? ''));

    $cash = toCash((string)($row[3] ?? ''));
    $details = trim((string)($row[4] ?? ''));

    return [
        'full_name' => $name,
        'id_number' => ($idNumber !== '' ? $idNumber : null),
        'phone' => ($phone !== '' ? $phone : null),
        'monthly_cash' => $cash,
        'details_text' => ($details !== '' ? $details : null),
    ];
}

$step = (string)($_GET['step'] ?? 'form');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $step = (string)($_POST['step'] ?? 'form');
}

$typeId = (int)($_POST['beneficiary_type_id'] ?? $_GET['beneficiary_type_id'] ?? 0);
$defaultItemAll = trim((string)($_POST['default_item'] ?? ''));
$raw = (string)($_POST['raw'] ?? '');

// Options
$renumberAfterImport = (int)($_POST['renumber_after_import'] ?? 0) === 1; // optional
// NOTE: skip_duplicates by file_number doesn't apply since file_number is generated.
// We'll instead offer skip by (type + id_number) if needed later.
$skipDuplicatesById = (int)($_POST['skip_duplicates_by_id'] ?? 0) === 1;

$preview = [];
$errors = [];
$stats = ['total' => 0, 'valid' => 0, 'skipped_header' => 0, 'invalid' => 0, 'skipped_dup' => 0];

if ($step === 'preview') {
    if ($typeId <= 0) $errors[] = 'يرجى اختيار نوع المستفيدين.';
    if (trim($raw) === '') $errors[] = 'يرجى لصق البيانات أولاً.';

    if (!$errors) {
        $rows = parseRows($raw);
        foreach ($rows as $r) {
            $stats['total']++;

            if (isHeaderLikeRow($r)) { $stats['skipped_header']++; continue; }

            $mapped = mapRowCash($r);
            if (!$mapped) { $stats['invalid']++; continue; }

            $preview[] = $mapped;
            $stats['valid']++;
        }

        if ($stats['valid'] === 0) {
            $errors[] = 'لم يتم العثور على صفوف صالحة. التنسيق المطلوب: الاسم | الرقم الوطني | الهاتف | دينار | (اختياري: المبلغ كتابة).';
        }
    }
}

if ($step === 'commit') {
    if ($typeId <= 0) $errors[] = 'يرجى اختيار نوع المستفيدين.';
    if (trim($raw) === '') $errors[] = 'يرجى لصق البيانات أولاً.';

    if (!$errors) {
        $rows = parseRows($raw);

        $pdo->beginTransaction();
        try {
            // Start file_number from max + 1 for this type
            $st = $pdo->prepare('SELECT COALESCE(MAX(file_number),0) FROM beneficiaries WHERE beneficiary_type_id = ?');
            $st->execute([$typeId]);
            $nextFile = (int)$st->fetchColumn() + 1;

            $insert = $pdo->prepare(
                'INSERT INTO beneficiaries
                 (beneficiary_type_id, file_number, full_name, id_number, phone, monthly_cash, default_item, status)
                 VALUES (?,?,?,?,?,?,?, "active")'
            );

            $checkDup = $pdo->prepare(
                'SELECT id FROM beneficiaries WHERE beneficiary_type_id = ? AND id_number = ? LIMIT 1'
            );

            $imported = 0;
            $skippedDup = 0;

            foreach ($rows as $r) {
                if (isHeaderLikeRow($r)) continue;

                $mapped = mapRowCash($r);
                if (!$mapped) continue;

                if ($skipDuplicatesById && !empty($mapped['id_number'])) {
                    $checkDup->execute([$typeId, $mapped['id_number']]);
                    if ($checkDup->fetch()) {
                        $skippedDup++;
                        continue;
                    }
                }

                // choose default_item:
                // - if details_text exists, use it
                // - else use defaultItemAll (if provided)
                $defaultItem = $mapped['details_text'] ?? null;
                if (($defaultItem === null || $defaultItem === '') && $defaultItemAll !== '') {
                    $defaultItem = $defaultItemAll;
                }

                $insert->execute([
                    $typeId,
                    $nextFile,
                    $mapped['full_name'],
                    $mapped['id_number'],
                    $mapped['phone'],
                    $mapped['monthly_cash'],
                    ($defaultItem !== '' ? $defaultItem : null),
                ]);

                $nextFile++;
                $imported++;
            }

            if ($renumberAfterImport && $typeId > 0) {
                renumberBeneficiariesForType($typeId);
            }

            $pdo->commit();
            $msg = "تم الاستيراد بنجاح. (مضاف: {$imported}";
            if ($skipDuplicatesById) $msg .= " | تم تخطي (تكرار هوية): {$skippedDup}";
            $msg .= ")";
            flashSuccess($msg);
            redirect(ADMIN_PATH . '/beneficiaries.php?type=' . $typeId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flashError('فشل الاستيراد: ' . $e->getMessage());
            redirect(ADMIN_PATH . '/import.php');
        }
    }
}

require_once __DIR__ . '/layout.php';
renderPage('استيراد البيانات', 'import', function() use ($types, $typeId, $defaultItemAll, $raw, $step, $preview, $errors, $stats, $renumberAfterImport, $skipDuplicatesById) {
    ?>
    <?= renderFlash() ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header fw-bold">استيراد بيانات المستفيدين</div>
        <div class="card-body">

            <form method="post" action="<?= e(ADMIN_PATH) ?>/import.php">
                <?= csrfField() ?>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">نوع المستفيدين *</label>
                        <select class="form-select" name="beneficiary_type_id" required>
                            <option value="">— اختر النوع —</option>
                            <?php foreach ($types as $t): ?>
                                <option value="<?= (int)$t['id'] ?>" <?= (int)$typeId === (int)$t['id'] ? 'selected' : '' ?>>
                                    <?= e((string)$t['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label">وصف افتراضي للجميع (اختياري)</label>
                        <input class="form-control" name="default_item" value="<?= e($defaultItemAll) ?>" placeholder="مثال: رواتب الأسر / كفالة">
                        <div class="form-text">إذا كان عندك عمود (المبلغ كتابة) سيتم استخدامه بدل هذا الحقل.</div>
                    </div>

                    <div class="col-12">
                        <div class="alert alert-info mb-2">
                            <strong>التنسيق المعتمد:</strong>
                            الاسم | الرقم الوطني | الهاتف | دينار | (اختياري: المبلغ كتابة)
                            <div class="small text-muted mt-1">الصق من Excel مباشرة (TAB). رقم الملف يُنشأ تلقائيًا تصاعديًا من النظام.</div>
                        </div>

                        <textarea class="form-control" rows="8" name="raw" placeholder="ألصق البيانات هنا..."><?= e($raw) ?></textarea>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="skip_duplicates_by_id" value="1" id="skip_by_id" <?= $skipDuplicatesById ? 'checked' : '' ?>>
                            <label class="form-check-label" for="skip_by_id">تخطي المكرر حسب (نوع المستفيد + رقم الهوية)</label>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="renumber_after_import" value="1" id="renumber" <?= $renumberAfterImport ? 'checked' : '' ?>>
                            <label class="form-check-label" for="renumber">إعادة ترتيب أرقام الملفات بعد الاستيراد (قد يغيّر أرقام الملفات)</label>
                        </div>
                    </div>

                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-outline-primary" name="step" value="preview">معاينة قبل الاستيراد</button>
                        <?php if ($step === 'preview' && !$errors && $preview): ?>
                            <button class="btn btn-success" name="step" value="commit" onclick="return confirm('تأكيد الاستيراد؟')">تأكيد الاستيراد</button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <?php if ($step === 'preview' && !$errors): ?>
                <hr>
                <div class="d-flex gap-3 flex-wrap small text-muted mb-2">
                    <div>إجمالي الأسطر: <?= (int)$stats['total'] ?></div>
                    <div>صالحة: <?= (int)$stats['valid'] ?></div>
                    <div>ترويسات متجاهلة: <?= (int)$stats['skipped_header'] ?></div>
                    <div>غير صالحة: <?= (int)$stats['invalid'] ?></div>
                </div>

                <div class="table-responsive" style="max-height:420px; overflow:auto;">
                    <table class="table table-sm table-striped">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>الاسم</th>
                            <th>الرقم الوطني</th>
                            <th>الهاتف</th>
                            <th>دينار</th>
                            <th>المبلغ كتابة/وصف</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($preview as $i => $r): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= e((string)$r['full_name']) ?></td>
                                <td><?= e((string)($r['id_number'] ?? '—')) ?></td>
                                <td><?= e((string)($r['phone'] ?? '—')) ?></td>
                                <td><?= $r['monthly_cash'] !== null ? e(number_format((float)$r['monthly_cash'], 2)) : '—' ?></td>
                                <td><?= e((string)($r['details_text'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </div>
    <?php
});