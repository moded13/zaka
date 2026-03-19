<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: beneficiary_documents.php'); exit; }
orphan_verify_csrf();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { header('Location: beneficiary_documents.php'); exit; }

$st = $pdo->prepare("SELECT * FROM beneficiary_documents WHERE id=?");
$st->execute([$id]);
$doc = $st->fetch(PDO::FETCH_ASSOC);
if (!$doc) { header('Location: beneficiary_documents.php'); exit; }

$rel = (string)$doc['stored_path'];
$abs = ORPHAN_MODULE_ROOT . '/' . ltrim($rel, '/');
$real = realpath($abs);
$root = realpath(ORPHAN_MODULE_ROOT);

if ($real && $root && strpos($real, $root) === 0 && is_file($real)) {
    @unlink($real);
}

$pdo->prepare("DELETE FROM beneficiary_documents WHERE id=?")->execute([$id]);

if (function_exists('flashSuccess')) flashSuccess('تم حذف الوثيقة.');
header('Location: beneficiary_documents.php?beneficiary_id=' . (int)$doc['beneficiary_id']);
exit;