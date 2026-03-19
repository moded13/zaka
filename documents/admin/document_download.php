<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); exit('Not found'); }

$st = $pdo->prepare("SELECT * FROM beneficiary_documents WHERE id=?");
$st->execute([$id]);
$doc = $st->fetch(PDO::FETCH_ASSOC);
if (!$doc) { http_response_code(404); exit('Not found'); }

$rel = (string)$doc['stored_path'];
$abs = ORPHAN_MODULE_ROOT . '/' . ltrim($rel, '/');

// Ensure path inside module root only
$real = realpath($abs);
$root = realpath(ORPHAN_MODULE_ROOT);
if (!$real || !$root || strpos($real, $root) !== 0) {
    http_response_code(403);
    exit('Forbidden');
}

if (!is_file($real)) {
    http_response_code(404);
    exit('File missing');
}

$mime = (string)$doc['mime_type'];
$orig = (string)$doc['original_name'];

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');

// inline for pdf/images; you can force attachment if you prefer
header('Content-Disposition: inline; filename="' . rawurlencode($orig) . '"');
header('Content-Length: ' . filesize($real));

readfile($real);
exit;