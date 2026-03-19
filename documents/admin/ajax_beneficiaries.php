<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$typeId = (int)($_GET['type_id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));

if ($typeId <= 0) {
    echo json_encode(['ok'=>false,'error'=>'type_id required'], JSON_UNESCAPED_UNICODE);
    exit;
}

// limit results
$params = [$typeId];
$where = "WHERE beneficiary_type_id = ?";

if ($q !== '') {
    $like = '%' . $q . '%';
    $digits = preg_replace('/\D+/', '', $q) ?? '';
    $where .= " AND (full_name LIKE ? OR id_number LIKE ? OR phone LIKE ? OR file_number = ?)";
    array_push($params, $like, $like, $like, (int)$digits);
}

$st = $pdo->prepare("
    SELECT id, file_number, full_name
    FROM beneficiaries
    $where
    ORDER BY file_number ASC
    LIMIT 200
");
$st->execute($params);

echo json_encode([
    'ok' => true,
    'data' => $st->fetchAll(PDO::FETCH_ASSOC),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);