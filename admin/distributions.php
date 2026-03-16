<?php
/**
 * admin/distributions.php (PRO + Live Filter)
 * - Compatible with zaka.sql schema:
 *   distributions(beneficiary_type_id, title, distribution_date, distribution_kind, category, notes, created_by_admin_id)
 *   distribution_items(distribution_id, beneficiary_id, cash_amount, details_text, notes)
 * - No nested forms (HTML-valid)
 * - Server-side builder stored in SESSION for add/remove/update before save.
 * - Adds LIVE filtering (instant from first character) + type dropdown filter inside table
 *   with Arabic/English digits normalization (١٢٣ == 123). Does NOT clear added items.
 */

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
requireLogin();

$pdo = getPDO();
$types = getBeneficiaryTypes();
$admin = function_exists('currentAdmin') ? currentAdmin() : ['id' => null];

function bkey(int $distId): string { return $distId > 0 ? 'dist_edit_' . $distId : 'dist_new'; }

function &builder(string $key): array {
    if (!isset($_SESSION['dist_builder'])) $_SESSION['dist_builder'] = [];
    if (!isset($_SESSION['dist_builder'][$key])) {
        $_SESSION['dist_builder'][$key] = [
            'beneficiary_type_id' => 0,
            'title' => '',
            'distribution_date' => date('Y-m-d'),
            'distribution_kind' => 'cash', // cash|in_kind|mixed
            'category' => '',
            'notes' => '',
            'fallback_cash' => 20.0,
            // items: beneficiary_id => ['cash'=>float|null, 'details'=>string, 'notes'=>string]
            'items' => [],
        ];
    }
    return $_SESSION['dist_builder'][$key];
}

function clearBuilder(string $key): void {
    unset($_SESSION['dist_builder'][$key]);
}

function normalizeKind(string $kind): string {
    return in_array($kind, ['cash','in_kind','mixed'], true) ? $kind : 'cash';
}

function syncHeader(array &$b, array $src): void {
    if (isset($src['beneficiary_type_id'])) $b['beneficiary_type_id'] = (int)$src['beneficiary_type_id'];
    if (isset($src['title'])) $b['title'] = trim((string)$src['title']);
    if (isset($src['distribution_date'])) $b['distribution_date'] = trim((string)$src['distribution_date']);
    if (isset($src['distribution_kind'])) $b['distribution_kind'] = normalizeKind((string)$src['distribution_kind']);
    if (isset($src['category'])) $b['category'] = trim((string)$src['category']);
    if (isset($src['notes'])) $b['notes'] = trim((string)$src['notes']);
    if (isset($src['fallback_cash'])) $b['fallback_cash'] = (float)$src['fallback_cash'];
}

function fetchBeneficiaries(PDO $pdo, int $typeId, string $q): array {
    $where = ['b.status="active"'];
    $params = [];
    if ($typeId > 0) { $where[] = 'b.beneficiary_type_id = ?'; $params[] = $typeId; }
    if ($q !== '') {
        $where[] = '(b.full_name LIKE ? OR b.id_number LIKE ? OR b.phone LIKE ? OR b.file_number = ?)';
        $like = '%' . $q . '%';
        $digits = preg_replace('/\D+/', '', $q) ?? '';
        array_push($params, $like, $like, $like, (int)$digits);
    }
    $st = $pdo->prepare(
        'SELECT b.*, bt.name_ar AS type_name
         FROM beneficiaries b
         JOIN beneficiary_types bt ON bt.id=b.beneficiary_type_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY b.file_number'
    );
    $st->execute($params);
    return $st->fetchAll();
}

function fetchAddedRows(PDO $pdo, array $items): array {
    if (!$items) return [];
    $ids = array_keys($items);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare(
        "SELECT b.*, bt.name_ar AS type_name
         FROM beneficiaries b
         JOIN beneficiary_types bt ON bt.id=b.beneficiary_type_id
         WHERE b.id IN ($in)"
    );
    $st->execute(array_map('intval', $ids));
    $rows = $st->fetchAll();
    $idx = [];
    foreach ($rows as $r) $idx[(int)$r['id']] = $r;

    $out = [];
    foreach ($items as $bid => $it) {
        $bid = (int)$bid;
        if (!isset($idx[$bid])) continue;
        $r = $idx[$bid];
        $out[] = [
            'id' => $bid,
            'file_number' => (int)$r['file_number'],
            'full_name' => (string)$r['full_name'],
            'phone' => (string)($r['phone'] ?? ''),
            'type_name' => (string)$r['type_name'],
            'monthly_cash' => $r['monthly_cash'],
            'cash' => $it['cash'],
            'details' => (string)($it['details'] ?? ''),
            'notes' => (string)($it['notes'] ?? ''),
        ];
    }
    usort($out, fn($a,$b) => $a['file_number'] <=> $b['file_number']);
    return $out;
}

/* ───────────────────────────── POST actions ───────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = (string)($_POST['action'] ?? '');

    // Delete distribution (and items via FK cascade)
    if ($action === 'delete_dist') {
        $id = (int)($_POST['dist_id'] ?? 0);
        if ($id > 0) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare('DELETE FROM distributions WHERE id = ?')->execute([$id]);
                $pdo->commit();
                flashSuccess('تم حذف التوزيعة.');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flashError('خطأ أثناء الحذف: ' . $e->getMessage());
            }
        }
        redirect(ADMIN_PATH . '/distributions.php');
    }

    // Builder operations
    if (in_array($action, ['set_header','add_selected','remove_selected','update_items','save_dist','reset_builder'], true)) {
        $distId = (int)($_POST['dist_id'] ?? 0);
        $key = bkey($distId);
        $b = &builder($key);
        syncHeader($b, $_POST);

        if ($action === 'reset_builder') {
            clearBuilder($key);
            flashInfo('تم تفريغ بيانات التوزيعة (جلسة).');
            redirect(ADMIN_PATH . '/distributions.php?action=' . ($distId > 0 ? 'edit&id=' . $distId : 'new'));
        }

        if ($action === 'set_header') {
            // Auto PRG back with type
            redirect(ADMIN_PATH . '/distributions.php?action=' . ($distId > 0 ? 'edit&id=' . $distId : 'new') . '&type=' . (int)$b['beneficiary_type_id']);
        }

        if ($action === 'add_selected') {
            $selected = array_values(array_filter(array_map('intval', (array)($_POST['select_beneficiaries'] ?? []))));
            if (!$selected) {
                flashError('لم يتم تحديد أي مستفيد.');
                redirect(ADMIN_PATH . '/distributions.php?action=' . ($distId > 0 ? 'edit&id=' . $distId : 'new') . '&type=' . (int)$b['beneficiary_type_id']);
            }

            // Fetch monthly_cash + default_item for defaults
            $in = implode(',', array_fill(0, count($selected), '?'));
            $st = $pdo->prepare("SELECT id, beneficiary_type_id, monthly_cash, default_item FROM beneficiaries WHERE id IN ($in)");
            $st->execute($selected);
            $rows = $st->fetchAll();
            $idx = [];
            foreach ($rows as $r) $idx[(int)$r['id']] = $r;

            $added = 0;
            foreach ($selected as $bid) {
                if (!isset($idx[$bid])) continue;
                $row = $idx[$bid];

                // enforce type only if header type is set
                if ((int)$b['beneficiary_type_id'] > 0 && (int)$row['beneficiary_type_id'] !== (int)$b['beneficiary_type_id']) {
                    continue;
                }

                if (!isset($b['items'][$bid])) {
                    $mc = $row['monthly_cash'];
                    $cash = null;

                    // default cash logic:
                    // - cash/mixed => monthly_cash if exists else fallback_cash
                    // - in_kind => null (no cash)
                    if ($b['distribution_kind'] === 'cash' || $b['distribution_kind'] === 'mixed') {
                        if ($mc !== null && (float)$mc > 0) $cash = (float)$mc;
                        else $cash = (float)$b['fallback_cash'];
                    } else {
                        $cash = null;
                    }

                    $b['items'][$bid] = [
                        'cash' => $cash,
                        'details' => (string)($row['default_item'] ?? ''),
                        'notes' => '',
                    ];
                    $added++;
                }
            }

            flashSuccess('تمت إضافة ' . $added . ' مستفيد.');
            redirect(ADMIN_PATH . '/distributions.php?action=' . ($distId > 0 ? 'edit&id=' . $distId : 'new') . '&type=' . (int)$b['beneficiary_type_id']);
        }

        if ($action === 'remove_selected') {
            $remove = array_values(array_filter(array_map('intval', (array)($_POST['remove_beneficiaries'] ?? []))));
            $removed = 0;
            foreach ($remove as $bid) {
                if (isset($b['items'][$bid])) { unset($b['items'][$bid]); $removed++; }
            }
            flashSuccess('تم حذف ' . $removed . ' من القائمة.');
            redirect(ADMIN_PATH . '/distributions.php?action=' . ($distId > 0 ? 'edit&id=' . $distId : 'new') . '&type=' . (int)$b['beneficiary_type_id']);
        }

        if ($action === 'update_items') {
            $cashArr = (array)($_POST['cash'] ?? []);
            $detailsArr = (array)($_POST['details'] ?? []);
            $notesArr = (array)($_POST['item_notes'] ?? []);

            foreach ($b['items'] as $bid => $it) {
                $bidStr = (string)$bid;

                $b['items'][$bid]['details'] = trim((string)($detailsArr[$bidStr] ?? $b['items'][$bid]['details']));
                $b['items'][$bid]['notes'] = trim((string)($notesArr[$bidStr] ?? $b['items'][$bid]['notes']));

                // cash allowed only for cash/mixed
                if ($b['distribution_kind'] === 'cash' || $b['distribution_kind'] === 'mixed') {
                    $raw = $cashArr[$bidStr] ?? null;
                    if ($raw === '' || $raw === null) $b['items'][$bid]['cash'] = null;
                    else $b['items'][$bid]['cash'] = (float)$raw;
                } else {
                    $b['items'][$bid]['cash'] = null;
                }
            }

            flashSuccess('تم تحديث بنود التوزيعة.');
            redirect(ADMIN_PATH . '/distributions.php?action=' . ($distId > 0 ? 'edit&id=' . $distId : 'new') . '&type=' . (int)$b['beneficiary_type_id']);
        }

        if ($action === 'save_dist') {
            $errors = [];
            if ((int)$b['beneficiary_type_id'] <= 0) $errors[] = 'تصنيف التوزيعة مطلوب.';
            if (trim((string)$b['title']) === '') $errors[] = 'عنوان التوزيعة مطلوب.';
            if (trim((string)$b['distribution_date']) === '') $errors[] = 'تاريخ التوزيعة مطلوب.';
            if (empty($b['items'])) $errors[] = 'لم يتم إضافة أي مستفيد.';
            $b['distribution_kind'] = normalizeKind((string)$b['distribution_kind']);

            if ($errors) {
                flashError(implode(' | ', $errors));
                redirect(ADMIN_PATH . '/distributions.php?action=' . ($distId > 0 ? 'edit&id=' . $distId : 'new') . '&type=' . (int)$b['beneficiary_type_id']);
            }

            $pdo->beginTransaction();
            try {
                if ($distId > 0) {
                    $pdo->prepare(
                        'UPDATE distributions
                         SET beneficiary_type_id=?, title=?, distribution_date=?, distribution_kind=?, category=?, notes=?, updated_at=CURRENT_TIMESTAMP
                         WHERE id=?'
                    )->execute([
                        (int)$b['beneficiary_type_id'],
                        $b['title'],
                        $b['distribution_date'],
                        $b['distribution_kind'],
                        ($b['category'] !== '' ? $b['category'] : null),
                        ($b['notes'] !== '' ? $b['notes'] : null),
                        $distId,
                    ]);

                    // Rebuild items
                    $pdo->prepare('DELETE FROM distribution_items WHERE distribution_id=?')->execute([$distId]);
                    $saveId = $distId;
                } else {
                    $pdo->prepare(
                        'INSERT INTO distributions
                         (beneficiary_type_id, title, distribution_date, distribution_kind, category, notes, created_by_admin_id)
                         VALUES (?,?,?,?,?,?,?)'
                    )->execute([
                        (int)$b['beneficiary_type_id'],
                        $b['title'],
                        $b['distribution_date'],
                        $b['distribution_kind'],
                        ($b['category'] !== '' ? $b['category'] : null),
                        ($b['notes'] !== '' ? $b['notes'] : null),
                        ($admin['id'] ?? null) ?: null,
                    ]);
                    $saveId = (int)$pdo->lastInsertId();
                }

                $ins = $pdo->prepare(
                    'INSERT INTO distribution_items (distribution_id, beneficiary_id, cash_amount, details_text, notes)
                     VALUES (?,?,?,?,?)'
                );

                foreach ($b['items'] as $bid => $it) {
                    $cash = $it['cash'];
                    if ($b['distribution_kind'] === 'in_kind') $cash = null;

                    $ins->execute([
                        $saveId,
                        (int)$bid,
                        ($cash === null ? null : (float)$cash),
                        (trim((string)($it['details'] ?? '')) !== '' ? trim((string)$it['details']) : null),
                        (trim((string)($it['notes'] ?? '')) !== '' ? trim((string)$it['notes']) : null),
                    ]);
                }

                $pdo->commit();
                clearBuilder($key);
                flashSuccess('تم حفظ التوزيعة بنجاح. رقم التوزيعة: ' . $saveId);
                redirect(ADMIN_PATH . '/distributions.php?view=' . $saveId);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flashError('فشل الحفظ (SQL): ' . $e->getMessage());
                redirect(ADMIN_PATH . '/distributions.php?action=' . ($distId > 0 ? 'edit&id=' . $distId : 'new') . '&type=' . (int)$b['beneficiary_type_id']);
            }
        }
    }
}

/* ───────────────────────────── GET routing ───────────────────────────── */
$action = (string)($_GET['action'] ?? '');
$viewId = (int)($_GET['view'] ?? 0);
$editId = (int)($_GET['id'] ?? 0);
$typeFilter = (int)($_GET['type'] ?? 0);

/* ───────────────────────────── VIEW ───────────────────────────── */
if ($viewId > 0) {
    $st = $pdo->prepare(
        'SELECT d.*, bt.name_ar AS type_name, a.display_name AS created_by_name
         FROM distributions d
         JOIN beneficiary_types bt ON bt.id = d.beneficiary_type_id
         LEFT JOIN admins a ON a.id = d.created_by_admin_id
         WHERE d.id=?'
    );
    $st->execute([$viewId]);
    $dist = $st->fetch();
    if (!$dist) { flashError('التوزيعة غير موجودة.'); redirect(ADMIN_PATH . '/distributions.php'); }

    $it = $pdo->prepare(
        'SELECT di.*, b.full_name, b.file_number, b.id_number, b.phone
         FROM distribution_items di
         JOIN beneficiaries b ON b.id = di.beneficiary_id
         WHERE di.distribution_id=?
         ORDER BY b.file_number'
    );
    $it->execute([$viewId]);
    $items = $it->fetchAll();

    $totalCash = 0.0;
    foreach ($items as $r) $totalCash += (float)($r['cash_amount'] ?? 0);

    require_once __DIR__ . '/layout.php';
    renderPage('تفاصيل التوزيعة', 'distributions', function() use ($dist, $items, $totalCash, $viewId) {
        ?>
        <?= renderFlash() ?>

        <div class="mb-3 d-flex gap-2 flex-wrap">
            <a href="<?= e(ADMIN_PATH) ?>/distributions.php" class="btn btn-outline-secondary btn-sm">رجوع</a>
            <a href="<?= e(ADMIN_PATH) ?>/distributions.php?action=edit&id=<?= (int)$viewId ?>" class="btn btn-outline-primary btn-sm">تعديل</a>
            <a href="<?= e(ADMIN_PATH) ?>/print_distribution.php?id=<?= (int)$viewId ?>" target="_blank" class="btn btn-outline-secondary btn-sm">طباعة</a>
        </div>

        <div class="card mb-3">
            <div class="card-header fw-bold">بيانات التوزيعة</div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-5"><strong>العنوان:</strong> <?= e((string)$dist['title']) ?></div>
                    <div class="col-md-3"><strong>التاريخ:</strong> <?= e((string)$dist['distribution_date']) ?></div>
                    <div class="col-md-2"><strong>النوع:</strong> <?= e((string)$dist['distribution_kind']) ?></div>
                    <div class="col-md-2"><strong>الإجمالي:</strong> <?= formatAmount($totalCash) ?></div>

                    <div class="col-md-4"><strong>تصنيف المستفيدين:</strong> <?= e((string)$dist['type_name']) ?></div>
                    <div class="col-md-4"><strong>الفئة:</strong> <?= e((string)($dist['category'] ?? '—')) ?></div>
                    <div class="col-md-4"><strong>المنشئ:</strong> <?= e((string)($dist['created_by_name'] ?? '—')) ?></div>

                    <?php if (!empty($dist['notes'])): ?>
                        <div class="col-12 small text-muted"><strong>ملاحظات:</strong> <?= e((string)$dist['notes']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header fw-bold">المستفيدون <span class="badge bg-secondary ms-1"><?= count($items) ?></span></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr>
                            <th>ملف</th><th>الاسم</th><th>الهاتف</th><th>الهوية</th><th>المبلغ</th><th>تفاصيل</th><th>ملاحظات</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $r): ?>
                            <tr>
                                <td class="fw-bold text-primary"><?= e((string)$r['file_number']) ?></td>
                                <td><?= e((string)$r['full_name']) ?></td>
                                <td><?= e((string)($r['phone'] ?? '—')) ?></td>
                                <td class="text-muted"><?= e((string)($r['id_number'] ?? '—')) ?></td>
                                <td><?= ($r['cash_amount'] !== null && (float)$r['cash_amount'] > 0) ? formatAmount((float)$r['cash_amount']) : '—' ?></td>
                                <td class="small text-muted"><?= e((string)($r['details_text'] ?? '')) ?></td>
                                <td class="small text-muted"><?= e((string)($r['notes'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                        <tr class="table-light fw-bold">
                            <td colspan="4">الإجمالي</td>
                            <td><?= formatAmount($totalCash) ?></td>
                            <td colspan="2"></td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <?php
    });
    exit;
}

/* ───────────────────────────── NEW / EDIT ───────────────────────────── */
if ($action === 'new' || $action === 'edit') {
    $isEdit = ($action === 'edit');
    $distId = $isEdit ? $editId : 0;
    $key = bkey($distId);
    $b = &builder($key);

    // init from DB on edit (once)
    if ($isEdit && (int)$b['beneficiary_type_id'] === 0) {
        $st = $pdo->prepare('SELECT * FROM distributions WHERE id=?');
        $st->execute([$distId]);
        $row = $st->fetch();
        if (!$row) { flashError('التوزيعة غير موجودة.'); redirect(ADMIN_PATH . '/distributions.php'); }

        $b['beneficiary_type_id'] = (int)$row['beneficiary_type_id'];
        $b['title'] = (string)$row['title'];
        $b['distribution_date'] = (string)$row['distribution_date'];
        $b['distribution_kind'] = normalizeKind((string)$row['distribution_kind']);
        $b['category'] = (string)($row['category'] ?? '');
        $b['notes'] = (string)($row['notes'] ?? '');

        $it = $pdo->prepare('SELECT beneficiary_id, cash_amount, details_text, notes FROM distribution_items WHERE distribution_id=?');
        $it->execute([$distId]);
        foreach ($it->fetchAll() as $r) {
            $bid = (int)$r['beneficiary_id'];
            $b['items'][$bid] = [
                'cash' => ($r['cash_amount'] === null ? null : (float)$r['cash_amount']),
                'details' => (string)($r['details_text'] ?? ''),
                'notes' => (string)($r['notes'] ?? ''),
            ];
        }
    }

    if (!$isEdit && $typeFilter > 0 && (int)$b['beneficiary_type_id'] === 0) {
        $b['beneficiary_type_id'] = $typeFilter;
    }

    $q = trim((string)($_GET['q'] ?? ''));
    $bens = fetchBeneficiaries($pdo, (int)$b['beneficiary_type_id'], $q);
    $addedRows = fetchAddedRows($pdo, (array)$b['items']);

    require_once __DIR__ . '/layout.php';
    renderPage($isEdit ? 'تعديل التوزيعة' : 'توزيعة جديدة', 'distributions', function() use ($isEdit, $distId, $types, $b, $q, $bens, $addedRows) {
        ?>
        <?= renderFlash() ?>

        <div class="mb-3 d-flex gap-2 flex-wrap">
            <a href="<?= e(ADMIN_PATH) ?>/distributions.php" class="btn btn-outline-secondary btn-sm">رجوع</a>
            <form method="post" action="<?= e(ADMIN_PATH) ?>/distributions.php" class="d-inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="reset_builder">
                <input type="hidden" name="dist_id" value="<?= (int)$distId ?>">
                <button class="btn btn-outline-secondary btn-sm" type="submit" onclick="return confirm('تفريغ بيانات التوزيعة الحالية (جلسة)؟')">تفريغ</button>
            </form>
        </div>

        <!-- Header -->
        <div class="card mb-3">
            <div class="card-header fw-bold"><?= $isEdit ? 'تعديل بيانات التوزيعة' : 'بيانات التوزيعة' ?></div>
            <div class="card-body">
                <form method="post" action="<?= e(ADMIN_PATH) ?>/distributions.php" class="row g-3" id="headerForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="set_header">
                    <input type="hidden" name="dist_id" value="<?= (int)$distId ?>">

                    <div class="col-md-4">
                        <label class="form-label">عنوان التوزيعة <span class="text-danger">*</span></label>
                        <input class="form-control" name="title" required value="<?= e((string)$b['title']) ?>">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">التاريخ <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="distribution_date" required value="<?= e((string)$b['distribution_date']) ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">تصنيف المستفيدين <span class="text-danger">*</span></label>
                        <select class="form-select" name="beneficiary_type_id" required>
                            <option value="">— اختر النوع —</option>
                            <?php foreach ($types as $t): ?>
                                <option value="<?= (int)$t['id'] ?>" <?= (int)$b['beneficiary_type_id'] === (int)$t['id'] ? 'selected' : '' ?>>
                                    <?= e((string)$t['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">يمكنك تغيير التصنيف ثم سيُفلتر جدول المستفيدين مباشرة.</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">نوع التوزيعة</label>
                        <select class="form-select" name="distribution_kind">
                            <option value="cash" <?= $b['distribution_kind']==='cash'?'selected':'' ?>>نقد</option>
                            <option value="in_kind" <?= $b['distribution_kind']==='in_kind'?'selected':'' ?>>عيني</option>
                            <option value="mixed" <?= $b['distribution_kind']==='mixed'?'selected':'' ?>>مختلط</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">فئة (اختياري)</label>
                        <input class="form-control" name="category" value="<?= e((string)$b['category']) ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">افتراضي عند عدم وجود راتب</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="fallback_cash" value="<?= e((string)$b['fallback_cash']) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">ملاحظات</label>
                        <input class="form-control" name="notes" value="<?= e((string)$b['notes']) ?>">
                    </div>

                    <div class="col-12">
                        <button class="btn btn-outline-primary" type="submit">تحديث (اختياري)</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Beneficiaries selection -->
        <div class="card mb-3">
            <div class="card-header fw-bold">اختيار المستفيدين</div>
            <div class="card-body">

                <!-- Live filters (client-side) -->
                <div class="row g-2 align-items-center mb-2">
                    <div class="col-md-3">
                        <select id="typeLiveFilter" class="form-select">
                            <option value="0">كل التصنيفات</option>
                            <?php foreach ($types as $t): ?>
                                <option value="<?= (int)$t['id'] ?>" <?= (int)$b['beneficiary_type_id'] === (int)$t['id'] ? 'selected' : '' ?>>
                                    <?= e((string)$t['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">فلترة فورية داخل الجدول (لا تحذف المضافين).</div>
                    </div>

                    <div class="col-md-5">
                        <input id="liveFilter" class="form-control" placeholder="فلترة فورية: اسم/هوية/هاتف/رقم ملف (من أول حرف)">
                    </div>

                    <div class="col-md-4 small text-muted">
                        هذه فلترة داخل القائمة فقط (بدون بحث سيرفر).
                    </div>
                </div>

                <!-- Server search (optional, keeps existing behavior) -->
                <form method="get" class="row g-2 align-items-center mb-2">
                    <input type="hidden" name="action" value="<?= $isEdit ? 'edit' : 'new' ?>">
                    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$distId ?>"><?php endif; ?>
                    <input type="hidden" name="type" value="<?= (int)$b['beneficiary_type_id'] ?>">

                    <div class="col-md-6">
                        <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="بحث سيرفر (اختياري) بالاسم/الهوية/الهاتف/رقم ملف">
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-outline-secondary">بحث</button>
                    </div>
                </form>

                <form method="post" action="<?= e(ADMIN_PATH) ?>/distributions.php">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add_selected">
                    <input type="hidden" name="dist_id" value="<?= (int)$distId ?>">

                    <!-- keep header fields -->
                    <input type="hidden" name="beneficiary_type_id" value="<?= (int)$b['beneficiary_type_id'] ?>">
                    <input type="hidden" name="title" value="<?= e((string)$b['title']) ?>">
                    <input type="hidden" name="distribution_date" value="<?= e((string)$b['distribution_date']) ?>">
                    <input type="hidden" name="distribution_kind" value="<?= e((string)$b['distribution_kind']) ?>">
                    <input type="hidden" name="category" value="<?= e((string)$b['category']) ?>">
                    <input type="hidden" name="notes" value="<?= e((string)$b['notes']) ?>">
                    <input type="hidden" name="fallback_cash" value="<?= e((string)$b['fallback_cash']) ?>">

                    <div class="table-responsive" style="max-height:320px;overflow:auto">
                        <table class="table table-sm table-hover mb-0" id="beneficiariesTable">
                            <thead class="sticky-top">
                            <tr>
                                <th style="width:34px" class="text-center">
                                    <input type="checkbox" onclick="document.querySelectorAll('.ben-cb').forEach(x=>x.checked=this.checked)">
                                </th>
                                <th style="width:70px">ملف</th>
                                <th>الاسم</th>
                                <th style="width:120px">الهاتف</th>
                                <th style="width:110px">راتب (دينار)</th>
                                <th style="width:140px">الهوية</th>
                                <th style="width:90px">سجل</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($bens as $r): ?>
                                <?php
                                    $rawText = trim(
                                        ($r['full_name'] ?? '') . ' ' .
                                        ($r['id_number'] ?? '') . ' ' .
                                        ($r['phone'] ?? '') . ' ' .
                                        ($r['file_number'] ?? '')
                                    );
                                ?>
                                <tr data-type-id="<?= (int)$r['beneficiary_type_id'] ?>" data-filter-text="<?= e($rawText) ?>">
                                    <td class="text-center">
                                        <input class="ben-cb" type="checkbox" name="select_beneficiaries[]" value="<?= (int)$r['id'] ?>">
                                    </td>
                                    <td class="fw-bold text-primary"><?= e((string)$r['file_number']) ?></td>
                                    <td>
                                        <a href="<?= e(ADMIN_PATH) ?>/beneficiaries.php?edit=<?= (int)$r['id'] ?>&type=0&q=" target="_blank">
                                            <?= e((string)$r['full_name']) ?>
                                        </a>
                                    </td>
                                    <td><?= e((string)($r['phone'] ?? '—')) ?></td>
                                    <td class="fw-bold"><?= $r['monthly_cash'] !== null ? e(number_format((float)$r['monthly_cash'],2)) : '—' ?></td>
                                    <td class="text-muted"><?= e((string)($r['id_number'] ?? '—')) ?></td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="<?= e(ADMIN_PATH) ?>/beneficiaries.php?edit=<?= (int)$r['id'] ?>&type=0&q="
                                           target="_blank">فتح</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <button class="btn btn-outline-primary btn-sm mt-2" type="submit">إضافة المحدد</button>
                </form>
            </div>
        </div>

        <!-- Added -->
        <div class="card mb-3">
            <div class="card-header fw-bold">المستفيدون المضافون <span class="badge bg-secondary ms-1"><?= count($addedRows) ?></span></div>
            <div class="card-body">
                <?php if (!$addedRows): ?>
                    <div class="text-muted">لم يُضف أي مستفيد بعد.</div>
                <?php else: ?>

                    <form method="post" action="<?= e(ADMIN_PATH) ?>/distributions.php" class="mb-2">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_items">
                        <input type="hidden" name="dist_id" value="<?= (int)$distId ?>">

                        <!-- keep header -->
                        <input type="hidden" name="beneficiary_type_id" value="<?= (int)$b['beneficiary_type_id'] ?>">
                        <input type="hidden" name="title" value="<?= e((string)$b['title']) ?>">
                        <input type="hidden" name="distribution_date" value="<?= e((string)$b['distribution_date']) ?>">
                        <input type="hidden" name="distribution_kind" value="<?= e((string)$b['distribution_kind']) ?>">
                        <input type="hidden" name="category" value="<?= e((string)$b['category']) ?>">
                        <input type="hidden" name="notes" value="<?= e((string)$b['notes']) ?>">
                        <input type="hidden" name="fallback_cash" value="<?= e((string)$b['fallback_cash']) ?>">

                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th style="width:34px" class="text-center"></th>
                                    <th style="width:70px">ملف</th>
                                    <th>الاسم</th>
                                    <th style="width:120px">الهاتف</th>
                                    <th style="width:110px">المبلغ</th>
                                    <th>تفاصيل</th>
                                    <th>ملاحظات</th>
                                    <th style="width:90px">سجل</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($addedRows as $it): ?>
                                    <?php $bid = (int)$it['id']; ?>
                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox" class="rm-cb" form="removeForm" name="remove_beneficiaries[]" value="<?= $bid ?>">
                                        </td>
                                        <td class="fw-bold text-primary"><?= e((string)$it['file_number']) ?></td>
                                        <td>
                                            <a href="<?= e(ADMIN_PATH) ?>/beneficiaries.php?edit=<?= $bid ?>&type=0&q=" target="_blank">
                                                <?= e((string)$it['full_name']) ?>
                                            </a>
                                        </td>
                                        <td><?= e((string)($it['phone'] ?? '—')) ?></td>
                                        <td>
                                            <?php if ($b['distribution_kind'] === 'in_kind'): ?>
                                                <input class="form-control form-control-sm" value="—" disabled>
                                            <?php else: ?>
                                                <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                                       name="cash[<?= $bid ?>]" value="<?= e((string)($it['cash'] ?? '')) ?>">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <input class="form-control form-control-sm" name="details[<?= $bid ?>]" value="<?= e((string)$it['details']) ?>">
                                        </td>
                                        <td>
                                            <input class="form-control form-control-sm" name="item_notes[<?= $bid ?>]" value="<?= e((string)$it['notes']) ?>">
                                        </td>
                                        <td>
                                            <a class="btn btn-sm btn-outline-primary"
                                               href="<?= e(ADMIN_PATH) ?>/beneficiaries.php?edit=<?= $bid ?>&type=0&q="
                                               target="_blank">فتح</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-2 d-flex gap-2 flex-wrap">
                            <button class="btn btn-outline-secondary btn-sm" type="submit">تحديث البنود</button>
                        </div>
                    </form>

                    <form method="post" action="<?= e(ADMIN_PATH) ?>/distributions.php" id="removeForm" class="mb-2" onsubmit="return confirm('حذف المحدد من القائمة؟')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="remove_selected">
                        <input type="hidden" name="dist_id" value="<?= (int)$distId ?>">
                        <button class="btn btn-outline-danger btn-sm" type="submit">حذف المحدد</button>
                        <label class="small ms-2">
                            <input type="checkbox" onclick="document.querySelectorAll('.rm-cb').forEach(x=>x.checked=this.checked)"> تحديد الكل
                        </label>
                    </form>

                    <form method="post" action="<?= e(ADMIN_PATH) ?>/distributions.php" onsubmit="return confirm('تأكيد حفظ التوزيعة؟')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="save_dist">
                        <input type="hidden" name="dist_id" value="<?= (int)$distId ?>">

                        <!-- keep header -->
                        <input type="hidden" name="beneficiary_type_id" value="<?= (int)$b['beneficiary_type_id'] ?>">
                        <input type="hidden" name="title" value="<?= e((string)$b['title']) ?>">
                        <input type="hidden" name="distribution_date" value="<?= e((string)$b['distribution_date']) ?>">
                        <input type="hidden" name="distribution_kind" value="<?= e((string)$b['distribution_kind']) ?>">
                        <input type="hidden" name="category" value="<?= e((string)$b['category']) ?>">
                        <input type="hidden" name="notes" value="<?= e((string)$b['notes']) ?>">
                        <input type="hidden" name="fallback_cash" value="<?= e((string)$b['fallback_cash']) ?>">

                        <button class="btn btn-primary" type="submit">حفظ التوزيعة</button>
                    </form>

                <?php endif; ?>
            </div>
        </div>

        <!-- Live filter JS (Arabic/English digits + type dropdown) -->
        <script>
        (function () {
            const input = document.getElementById('liveFilter');
            const typeSel = document.getElementById('typeLiveFilter');
            const table = document.getElementById('beneficiariesTable');
            const headerTypeSelect = document.querySelector('#headerForm select[name="beneficiary_type_id"]');

            if (!table || !typeSel || !input) return;

            const rows = Array.from(table.querySelectorAll('tbody tr'));

            const arabicToLatinDigits = (s) => (s || '').replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d))
                                                      .replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
            const normalize = (s) => {
                s = (s || '').toString();
                s = arabicToLatinDigits(s);
                s = s.toLowerCase();
                s = s.replace(/\s+/g, ' ').trim();
                return s;
            };

            function applyFilters() {
                const q = normalize(input.value);
                const t = (typeSel.value || '0').toString();

                rows.forEach(tr => {
                    const trType = (tr.getAttribute('data-type-id') || '0').toString();
                    const text = normalize(tr.getAttribute('data-filter-text') || '');
                    const okType = (t === '0') || (trType === t);
                    const okText = (q === '') || text.includes(q);
                    tr.style.display = (okType && okText) ? '' : 'none';
                });
            }

            // Live filter from first char
            input.addEventListener('input', applyFilters);
            typeSel.addEventListener('change', () => {
                applyFilters();

                // Optional: keep header type in sync (without auto-save)
                if (headerTypeSelect && headerTypeSelect.value !== typeSel.value) {
                    headerTypeSelect.value = typeSel.value;
                }
            });

            // Initialize dropdown to header selected type if available
            if (headerTypeSelect && headerTypeSelect.value) {
                typeSel.value = headerTypeSelect.value;
            }

            applyFilters();
        })();
        </script>


<script>
(function () {
  const headerForm = document.getElementById('headerForm');
  if (!headerForm) return;

  const getVal = (name) => {
    const el = headerForm.querySelector(`[name="${name}"]`);
    return el ? el.value : '';
  };

  // Copy header fields into any form that has matching hidden inputs
  function syncToForm(form) {
    const fields = [
      'beneficiary_type_id',
      'title',
      'distribution_date',
      'distribution_kind',
      'category',
      'notes',
      'fallback_cash'
    ];
    fields.forEach((f) => {
      const hidden = form.querySelector(`input[type="hidden"][name="${f}"]`);
      if (hidden) hidden.value = getVal(f);
    });
  }

  // Attach to all POST forms on the page (add_selected, update_items, save_dist, remove_selected)
  document.querySelectorAll('form[method="post"]').forEach((form) => {
    form.addEventListener('submit', function () {
      // لا نلمس headerForm نفسه
      if (form.id === 'headerForm') return;
      syncToForm(form);
    }, true);
  });
})();
</script>


        <?php
    });
    exit;
}

/* ───────────────────────────── LIST ───────────────────────────── */
$distWhere = '';
$distParams = [];
if ($typeFilter > 0) { $distWhere = 'WHERE d.beneficiary_type_id = ?'; $distParams[] = $typeFilter; }

$st = $pdo->prepare(
    "SELECT d.*, bt.name_ar AS type_name,
            (SELECT COUNT(*) FROM distribution_items di WHERE di.distribution_id=d.id) AS item_count,
            (SELECT COALESCE(SUM(cash_amount),0) FROM distribution_items di WHERE di.distribution_id=d.id) AS total_cash
     FROM distributions d
     JOIN beneficiary_types bt ON bt.id=d.beneficiary_type_id
     $distWhere
     ORDER BY d.distribution_date DESC, d.id DESC"
);
$st->execute($distParams);
$rows = $st->fetchAll();

require_once __DIR__ . '/layout.php';
renderPage('التوزيعات', 'distributions', function() use ($rows, $types, $typeFilter) {
    ?>
    <?= renderFlash() ?>

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <form method="get" class="d-flex gap-2 align-items-center">
            <select name="type" class="form-select form-select-sm" style="width:220px" onchange="this.form.submit()">
                <option value="">جميع الأنواع</option>
                <?php foreach ($types as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= (int)$typeFilter === (int)$t['id'] ? 'selected' : '' ?>>
                        <?= e((string)$t['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <a href="<?= e(ADMIN_PATH) ?>/distributions.php?action=new" class="btn btn-primary">توزيعة جديدة</a>
    </div>

    <div class="card">
        <div class="card-header">قائمة التوزيعات <span class="badge bg-secondary ms-1"><?= count($rows) ?></span></div>
        <div class="card-body p-0">
            <?php if ($rows): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>العنوان</th>
                            <th>التصنيف</th>
                            <th>التاريخ</th>
                            <th>النوع</th>
                            <th>المستفيدون</th>
                            <th>الإجمالي</th>
                            <th>إجراءات</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $d): ?>
                            <tr>
                                <td><?= e((string)$d['id']) ?></td>
                                <td class="fw-semibold"><?= e((string)$d['title']) ?></td>
                                <td><span class="badge bg-primary bg-opacity-75"><?= e((string)$d['type_name']) ?></span></td>
                                <td><?= e((string)$d['distribution_date']) ?></td>
                                <td><?= e((string)$d['distribution_kind']) ?></td>
                                <td><?= e((string)$d['item_count']) ?></td>
                                <td><?= formatAmount((float)$d['total_cash']) ?></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a class="btn btn-sm btn-outline-primary" href="?view=<?= (int)$d['id'] ?>">عرض</a>
                                        <a class="btn btn-sm btn-outline-info" href="<?= e(ADMIN_PATH) ?>/distributions.php?action=edit&id=<?= (int)$d['id'] ?>">تعديل</a>
                                        <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= e(ADMIN_PATH) ?>/print_distribution.php?id=<?= (int)$d['id'] ?>">طباعة</a>
                                        <form method="post" action="<?= e(ADMIN_PATH) ?>/distributions.php" class="d-inline" onsubmit="return confirm('حذف التوزيعة؟')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete_dist">
                                            <input type="hidden" name="dist_id" value="<?= (int)$d['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">حذف</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-4 text-center text-muted">لا توجد توزيعات بعد.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php
});