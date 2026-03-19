<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: beneficiary_documents.php'); exit; }
orphan_verify_csrf();

$beneficiary_id = (int)($_POST['beneficiary_id'] ?? 0);

// If beneficiary_id not provided, resolve by (beneficiary_type_id + file_number)
if ($beneficiary_id <= 0) {
    $beneficiary_type_id = (int)($_POST['beneficiary_type_id'] ?? 0);
    $file_number         = (int)($_POST['file_number'] ?? 0);

    if ($beneficiary_type_id <= 0 || $file_number <= 0) {
        if (function_exists('flashError')) flashError('اختر نوع المستفيد ثم أدخل رقم الملف.');
        header('Location: beneficiary_documents.php');
        exit;
    }

    $find = $pdo->prepare("SELECT id FROM beneficiaries WHERE beneficiary_type_id=? AND file_number=? LIMIT 1");
    $find->execute([$beneficiary_type_id, $file_number]);
    $beneficiary_id = (int)($find->fetchColumn() ?: 0);

    if ($beneficiary_id <= 0) {
        if (function_exists('flashError')) flashError('لم يتم العثور على منتفع بهذا النوع ورقم الملف.');
        header('Location: beneficiary_documents.php');
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
    if (function_exists('flashError')) flashError('بيانات غير صحيحة.');
    header('Location: beneficiary_documents.php?beneficiary_id=' . $beneficiary_id);
    exit;
}

$f = $_FILES['file'] ?? null;
if (!$f) {
    if (function_exists('flashError')) flashError('اختر ملف.');
    header('Location: beneficiary_documents.php?beneficiary_id=' . $beneficiary_id);
    exit;
}

orphan_storage_ensure();
[$ok, $mimeOrErr] = orphan_validate_upload($f);
if (!$ok) {
    if (function_exists('flashError')) flashError($mimeOrErr);
    header('Location: beneficiary_documents.php?beneficiary_id=' . $beneficiary_id);
    exit;
}
$mime = $mimeOrErr;

$orig = (string)($f['name'] ?? 'file');
$ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
if ($ext === '') {
    $ext = $mime === 'application/pdf' ? 'pdf' : 'jpg';
}

$uuid = bin2hex(random_bytes(16));
$storedName = $beneficiary_id . '_' . $doc_type . ($doc_side ? ('_' . $doc_side) : '') . '_' . $uuid . '.' . $ext;

$benefFolder = ORPHAN_STORAGE_ROOT . '/' . $beneficiary_id;
if (!is_dir($benefFolder)) @mkdir($benefFolder, 0775, true);

$absPath = $benefFolder . '/' . $storedName;
if (!move_uploaded_file($f['tmp_name'], $absPath)) {
    if (function_exists('flashError')) flashError('فشل حفظ الملف.');
    header('Location: beneficiary_documents.php?beneficiary_id=' . $beneficiary_id);
    exit;
}

// Save relative path
$relPath = 'zaka_storage/beneficiaries_docs/' . $beneficiary_id . '/' . $storedName;
$adminId = orphan_admin_id();

$st = $pdo->prepare("
    INSERT INTO beneficiary_documents
    (beneficiary_id, doc_type, doc_side, title, stored_path, original_name, mime_type, size_bytes, uploaded_by_admin_id, is_shareable, created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?, NOW())
");
$st->execute([
    $beneficiary_id,
    $doc_type,
    ($doc_side !== '' ? $doc_side : null),
    ($title !== '' ? $title : null),
    $relPath,
    $orig,
    $mime,
    (int)($f['size'] ?? 0),
    $adminId,
    ($is_shareable === 1 ? 1 : 0),
]);

if (function_exists('flashSuccess')) flashSuccess('تم رفع الوثيقة بنجاح.');
header('Location: beneficiary_documents.php?beneficiary_id=' . $beneficiary_id);
exit;