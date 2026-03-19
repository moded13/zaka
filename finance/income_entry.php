<?php
require_once 'bootstrap.php';
require_once 'helpers.php';

$page_title = 'إدخال وصل إيراد';

/**
 * ✅ Column existence check (MariaDB-safe)
 */
function col_exists(PDO $pdo, string $table, string $col): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = ?
          AND column_name = ?
    ");
    $stmt->execute([$table, $col]);
    return (int)$stmt->fetchColumn() > 0;
}

function flash_error(string $m): void {
    if (function_exists('finance_flash_error')) finance_flash_error($m);
    elseif (function_exists('set_flash')) set_flash('error', $m);
}
function flash_success(string $m): void {
    if (function_exists('finance_flash_success')) finance_flash_success($m);
    elseif (function_exists('set_flash')) set_flash('success', $m);
}

// Preselect book_id (if came from receipt_book_view)
$prefBookId = (int)($_GET['book_id'] ?? 0);

// Receipt books
$books = [];
if (receipt_books_exist()) {
    $books = $pdo->query("SELECT id, book_no, start_receipt_no, end_receipt_no, status FROM receipt_books ORDER BY id DESC")
        ->fetchAll(PDO::FETCH_ASSOC);
}

// Income categories
$cats = $pdo->query("SELECT id, name FROM income_categories WHERE is_active=1 ORDER BY name ASC")
    ->fetchAll(PDO::FETCH_ASSOC);

// Paper-like order if category names exist
$catIdByName = [];
foreach ($cats as $c) $catIdByName[trim((string)$c['name'])] = (int)$c['id'];

$defaultCatNames = ['زكاة مال','كفالة أيتام','صدقة فطر','تبرعات','أخرى'];
$rows = [];
foreach ($defaultCatNames as $nm) {
    if (isset($catIdByName[$nm])) $rows[] = ['id'=>$catIdByName[$nm], 'name'=>$nm];
}
if (!$rows) {
    foreach ($cats as $c) $rows[] = ['id'=>(int)$c['id'], 'name'=>(string)$c['name']];
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    finance_verify_csrf();

    $book_id     = (int)($_POST['book_id'] ?? 0);
    $receipt_no  = trim((string)($_POST['receipt_no'] ?? ''));
    $income_date = trim((string)($_POST['income_date'] ?? date('Y-m-d')));
    $donor_name  = trim((string)($_POST['donor_name'] ?? ''));
    $method      = trim((string)($_POST['payment_method'] ?? 'نقد'));
    $notes       = trim((string)($_POST['notes'] ?? ''));

    $amounts = (array)($_POST['amount'] ?? []);
    $clean = [];
    $total = 0.0;

    foreach ($amounts as $cid => $val) {
        $cid = (int)$cid;
        $v = str_replace(',', '.', (string)$val);
        $amt = (float)$v;
        if ($amt > 0) {
            $clean[$cid] = $amt;
            $total += $amt;
        }
    }

    $errors = [];
    if ($book_id <= 0) $errors[] = 'اختر دفتر وصولات';
    if ($receipt_no === '' || !ctype_digit($receipt_no)) $errors[] = 'رقم الوصل مطلوب ويجب أن يكون رقمًا';
    if ($income_date === '') $errors[] = 'التاريخ مطلوب';
    if ($donor_name === '') $errors[] = 'اسم المتبرع مطلوب';
    if ($total <= 0) $errors[] = 'أدخل مبلغًا واحدًا على الأقل ضمن التصنيفات';

    // Validate selected book exists + range
    $book = null;
    $st = $pdo->prepare("SELECT * FROM receipt_books WHERE id=?");
    $st->execute([$book_id]);
    $book = $st->fetch(PDO::FETCH_ASSOC);
    if (!$book) $errors[] = 'دفتر غير موجود';

    if ($book) {
        $rno = (int)$receipt_no;
        $start = (int)$book['start_receipt_no'];
        $end   = (int)$book['end_receipt_no'];
        if ($rno < $start || $rno > $end) {
            $errors[] = "رقم الوصل خارج نطاق الدفتر ({$start} - {$end})";
        }
    }

    // receipt_no unique globally (across all categories rows)
    $dup = $pdo->prepare("SELECT COUNT(*) FROM finance_income WHERE receipt_no=?");
    $dup->execute([$receipt_no]);
    if ((int)$dup->fetchColumn() > 0) $errors[] = 'رقم الوصل مستخدم مسبقًا';

    if ($errors) {
        flash_error(implode(' | ', $errors));
        header('Location: income_entry.php' . ($book_id ? ('?book_id=' . $book_id) : ''));
        exit;
    }

    // Detect columns (compatible with your DB)
    $hasCreatedByAdmin = col_exists($pdo, 'finance_income', 'created_by_admin_id');
    $hasCreatedBy      = col_exists($pdo, 'finance_income', 'created_by');
    $hasUpdatedAt      = col_exists($pdo, 'finance_income', 'updated_at');

    // Current admin id
    $adminId = null;
    if (function_exists('current_admin_id')) $adminId = current_admin_id();
    elseif (function_exists('currentAdmin')) { $a = currentAdmin(); $adminId = $a['id'] ?? null; }
    $adminId = ($adminId !== null && (int)$adminId > 0) ? (int)$adminId : null;

    try {
        $pdo->beginTransaction();

        foreach ($clean as $catId => $amt) {
            if ($hasCreatedByAdmin) {
                $sql = "INSERT INTO finance_income
                        (book_id, receipt_no, income_date, donor_name, category_id, amount, payment_method, notes, created_by_admin_id"
                        . ($hasUpdatedAt ? ", updated_at" : "") . ")
                        VALUES (?,?,?,?,?,?,?,?,?" . ($hasUpdatedAt ? ", NOW()" : "") . ")";
                $ins = $pdo->prepare($sql);
                $ins->execute([
                    $book_id,
                    $receipt_no,
                    $income_date,
                    $donor_name,
                    $catId,
                    $amt,
                    ($method !== '' ? $method : null),
                    ($notes !== '' ? $notes : null),
                    $adminId
                ]);
            } elseif ($hasCreatedBy) {
                $sql = "INSERT INTO finance_income
                        (book_id, receipt_no, income_date, donor_name, category_id, amount, payment_method, notes, created_by"
                        . ($hasUpdatedAt ? ", updated_at" : "") . ")
                        VALUES (?,?,?,?,?,?,?,?,?" . ($hasUpdatedAt ? ", NOW()" : "") . ")";
                $ins = $pdo->prepare($sql);
                $ins->execute([
                    $book_id,
                    $receipt_no,
                    $income_date,
                    $donor_name,
                    $catId,
                    $amt,
                    ($method !== '' ? $method : null),
                    ($notes !== '' ? $notes : null),
                    $adminId
                ]);
            } else {
                $sql = "INSERT INTO finance_income
                        (book_id, receipt_no, income_date, donor_name, category_id, amount, payment_method, notes"
                        . ($hasUpdatedAt ? ", updated_at" : "") . ")
                        VALUES (?,?,?,?,?,?,?,?" . ($hasUpdatedAt ? ", NOW()" : "") . ")";
                $ins = $pdo->prepare($sql);
                $ins->execute([
                    $book_id,
                    $receipt_no,
                    $income_date,
                    $donor_name,
                    $catId,
                    $amt,
                    ($method !== '' ? $method : null),
                    ($notes !== '' ? $notes : null),
                ]);
            }
        }

        $pdo->commit();
        flash_success('تم حفظ الوصل بنجاح. مجموع الوصل: ' . number_format($total, 2));
        header('Location: receipt_book_view.php?id=' . (int)$book_id);
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_error('فشل الحفظ: ' . $e->getMessage());
        header('Location: income_entry.php' . ($book_id ? ('?book_id=' . $book_id) : ''));
        exit;
    }
}

// Show book totals if selected
$bookAgg = null;
$catAgg = [];
if ($prefBookId > 0) {
    $st = $pdo->prepare("SELECT * FROM receipt_books WHERE id=?");
    $st->execute([$prefBookId]);
    $bookAgg = $st->fetch(PDO::FETCH_ASSOC);

    if ($bookAgg) {
        $tot = $pdo->prepare("SELECT IFNULL(SUM(amount),0) FROM finance_income WHERE book_id=?");
        $tot->execute([$prefBookId]);
        $bookAgg['collected_total'] = (float)$tot->fetchColumn();

        $byCat = $pdo->prepare("
            SELECT ic.name AS category_name, IFNULL(SUM(fi.amount),0) AS total_amount
            FROM income_categories ic
            LEFT JOIN finance_income fi
              ON fi.category_id=ic.id AND fi.book_id=?
            GROUP BY ic.id, ic.name
            ORDER BY ic.name ASC
        ");
        $byCat->execute([$prefBookId]);
        $catAgg = $byCat->fetchAll(PDO::FETCH_ASSOC);
    }
}

require 'layout.php';
?>

<div class="card">
    <h2>إدخال وصل إيراد</h2>
    <div class="section-subtitle">أدخل قيمة الوصل موزعة على التصنيفات، وسيتم جمع الدفتر والتصنيفات تلقائيًا.</div>

    <form method="post" action="income_entry.php<?= $prefBookId ? '?book_id=' . (int)$prefBookId : '' ?>" class="grid">
        <?= finance_csrf_field() ?>

        <div class="col-4">
            <label>دفتر الوصولات *</label>
            <select name="book_id" required>
                <option value="">— اختر —</option>
                <?php foreach ($books as $b): ?>
                    <option value="<?= (int)$b['id'] ?>" <?= $prefBookId === (int)$b['id'] ? 'selected' : '' ?>>
                        <?= e($b['book_no']) ?> (<?= (int)$b['start_receipt_no'] ?>-<?= (int)$b['end_receipt_no'] ?>) — <?= e($b['status']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-4">
            <label>رقم الوصل *</label>
            <input type="text" name="receipt_no" inputmode="numeric" required>
        </div>

        <div class="col-4">
            <label>التاريخ *</label>
            <input type="date" name="income_date" value="<?= e(date('Y-m-d')) ?>" required>
        </div>

        <div class="col-6">
            <label>اسم المتبرع *</label>
            <input type="text" name="donor_name" required>
        </div>

        <div class="col-3">
            <label>طريقة الدفع</label>
            <select name="payment_method">
                <option value="نقد">نقد</option>
                <option value="تحويل">تحويل</option>
                <option value="شيك">شيك</option>
            </select>
        </div>

        <div class="col-3">
            <label>ملاحظات</label>
            <input type="text" name="notes">
        </div>

        <div class="col-12">
            <h3>مبالغ التصنيفات</h3>
            <div class="table-wrap">
                <table>
                    <tr><th class="wrap">التصنيف</th><th>المبلغ</th></tr>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="wrap" style="text-align:right;"><?= e($r['name']) ?></td>
                            <td>
                                <input type="number" step="0.01" min="0" name="amount[<?= (int)$r['id'] ?>]" value="0" style="text-align:center;">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <div class="form-actions" style="margin-top:12px;">
                <button type="submit" class="btn btn-primary">حفظ الوصل</button>
                <a class="btn btn-light" href="receipt_books.php">العودة لدفاتر الوصولات</a>
            </div>
        </div>
    </form>
</div>

<?php if ($bookAgg): ?>
<div class="card">
    <h2>ملخص الدفتر: <?= e($bookAgg['book_no']) ?></h2>

    <div class="grid">
        <div class="stat blue col-4">
            <div class="label">نطاق الدفتر</div>
            <div class="value"><?= (int)$bookAgg['start_receipt_no'] ?> - <?= (int)$bookAgg['end_receipt_no'] ?></div>
            <div class="sub">إجمالي الوصولات: <?= (int)$bookAgg['total_receipts'] ?></div>
        </div>
        <div class="stat green col-4">
            <div class="label">المُحصّل</div>
            <div class="value"><?= number_format((float)$bookAgg['collected_total'], 2) ?></div>
            <div class="sub">مجموع الوصولات داخل الدفتر</div>
        </div>
        <div class="stat dark col-4">
            <div class="label">الحالة</div>
            <div class="value"><?= e($bookAgg['status']) ?></div>
            <div class="sub">تاريخ الاستلام: <?= e($bookAgg['received_date']) ?></div>
        </div>
    </div>

    <div style="margin-top:14px;" class="table-wrap">
        <table>
            <tr><th class="wrap">التصنيف</th><th>المجموع</th></tr>
            <?php foreach ($catAgg as $r): ?>
                <tr>
                    <td class="wrap" style="text-align:right;"><?= e($r['category_name'] ?: '—') ?></td>
                    <td><?= number_format((float)$r['total_amount'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="form-actions" style="margin-top:12px;justify-content:flex-start;">
        <a class="btn btn-primary" href="receipt_book_view.php?id=<?= (int)$bookAgg['id'] ?>">فتح تفاصيل الدفتر</a>
    </div>
</div>
<?php endif; ?>

<div class="footer-note">© <?= e(date('Y')) ?> — قسم المالية</div>

</div>
</body>
</html>