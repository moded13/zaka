<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: requests.php'); exit; }
orphan_verify_csrf();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { header('Location: requests.php'); exit; }

$code = orphan_make_code(6);
$expires = date('Y-m-d H:i:s', time() + 24 * 3600);
$adminId = orphan_admin_id();

$st = $pdo->prepare("
    UPDATE sponsor_access_requests
    SET status='approved',
        access_code=?,
        code_expires_at=?,
        approved_by_admin_id=?,
        approved_at=NOW()
    WHERE id=? AND status='pending'
");
$st->execute([$code, $expires, $adminId, $id]);

header('Location: requests.php');
exit;