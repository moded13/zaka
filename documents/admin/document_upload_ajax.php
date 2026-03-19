<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

orphan_verify_csrf();

// Resolve beneficiary_id either directly or via (type + file_number)
$beneficiary_id = (int)($_POST['beneficiary_id'] ?? 0);
if ($beneficiary_id <= 0) {
    $beneficiary_type_id = (int)($_POST['beneficiary_type_id'] ?? 0);
    $file_number         = (int)($_POST['file_number'] ?? 0);

    if ($beneficiary_type_id <= 0 || $file_number <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'اختر نوع المستفيد ثم أدخل رقم الملف.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $find = $pdo->prepare("SELECT id FROM beneficiaries WHERE beneficiary_type_id=? AND file_number=? LIMIT 1");
    $find->execute([$beneficiary_type_id, $file_number]);
    $beneficiary_id = (int)($find->fetchColumn() ?: 0);

    if ($beneficiary_id <= 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'لم يتم العثور على منتفع بهذا النوع ورقم الملف.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$doc_type = trim((string)($_POST['doc_type'] ?? ''));
$doc_side = trim((string)($_POST['doc_side'] ?? ''));
$title    = trim((string)($_POST['title'] ?? ''));
$is_shareable = (int)($_POST['is_shareable'] ?? 0);

$allowedTypes = ['id_card','birth_cert','father_death_cert'];
$allowedSides = ['front','back',''];

if (!in_array($doc_type, $allowedTypes, true) || !in_array($doc_side, $allowedSides, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'نوع الوثيقة/الجهة غير صحيح.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$f = $_FILES['file'] ?? null;
if (!$f) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'لم يتم إرسال ملف.'], JSON_UNESCAPED_UNICODE);
    exit;
}

orphan_storage_ensure();
[$ok, $mimeOrErr] = orphan_validate_upload($f);
if (!$ok) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $mimeOrErr], JSON_UNESCAPED_UNICODE);
    exit;
}
$mime = $mimeOrErr;

$tmp = (string)($f['tmp_name'] ?? '');
$sha = $tmp ? hash_file('sha256', $tmp) : null;

// Prevent duplicates (same beneficiary + same sha)
if ($sha) {
    $dup = $pdo->prepare("SELECT id FROM beneficiary_documents WHERE beneficiary_id=? AND sha256=? LIMIT 1");
    $dup->execute([$beneficiary_id, $sha]);
    $dupId = (int)($dup->fetchColumn() ?: 0);
    if ($dupId > 0) {
        http_response_code(409);
        echo json_encode(['ok'=>false,'error'=>'هذه الوثيقة مرفوعة مسبقًا لنفس المنتفع.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$orig = (string)($f['name'] ?? 'file');
$ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
if ($ext === '') $ext = ($mime === 'application/pdf' ? 'pdf' : 'jpg');

$uuid = bin2hex(random_bytes(16));

$benefFolder = ORPHAN_STORAGE_ROOT . '/' . $beneficiary_id;
if (!is_dir($benefFolder)) @mkdir($benefFolder, 0775, true);

$storedName = $beneficiary_id . '_' . $doc_type . ($doc_side ? ('_' . $doc_side) : '') . '_' . $uuid . '.' . $ext;
$absPath = $benefFolder . '/' . $storedName;

if (!move_uploaded_file($f['tmp_name'], $absPath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'فشل حفظ الملف على السيرفر.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Default: store original as-is
$relPath = 'zaka_storage/beneficiaries_docs/' . $beneficiary_id . '/' . $storedName;
$thumbRel = null;

// If image: create optimized + thumb
if (str_starts_with($mime, 'image/')) {
    $baseNoExt = pathinfo($storedName, PATHINFO_FILENAME);
    $optName = $baseNoExt . '.webp';
    $thName  = $baseNoExt . '_th.webp';

    $optAbs = $benefFolder . '/' . $optName;
    $thAbs  = $benefFolder . '/' . $thName;

    [$ok2, $err2] = orphan_make_thumb_and_optimize($absPath, $optAbs, $thAbs);
    if ($ok2) {
        // Use optimized as main stored file (keep original too)
        $relPath = 'zaka_storage/beneficiaries_docs/' . $beneficiary_id . '/' . $optName;
        $thumbRel = 'zaka_storage/beneficiaries_docs/' . $beneficiary_id . '/' . $thName;
    }
}

$adminId = orphan_admin_id();

$st = $pdo->prepare("
    INSERT INTO beneficiary_documents
    (beneficiary_id, doc_type, doc_side, title, stored_path, thumb_path, original_name, mime_type, size_bytes, sha256, uploaded_by_admin_id, is_shareable, created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?, NOW())
");
$st->execute([
    $beneficiary_id,
    $doc_type,
    ($doc_side !== '' ? $doc_side : null),
    ($title !== '' ? $title : null),
    $relPath,
    $thumbRel,
    $orig,
    $mime,
    (int)($f['size'] ?? 0),
    $sha,
    $adminId,
    ($is_shareable === 1 ? 1 : 0),
]);

$docId = (int)$pdo->lastInsertId();

echo json_encode([
    'ok' => true,
    'doc' => [
        'id' => $docId,
        'beneficiary_id' => $beneficiary_id,
        'doc_type' => $doc_type,
        'doc_side' => ($doc_side !== '' ? $doc_side : null),
        'title' => ($title !== '' ? $title : null),
        'original_name' => $orig,
        'mime_type' => $mime,
        'size_bytes' => (int)($f['size'] ?? 0),
        'is_shareable' => ($is_shareable === 1 ? 1 : 0),
        'thumb_path' => $thumbRel,
        'created_at' => date('Y-m-d H:i:s'),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);