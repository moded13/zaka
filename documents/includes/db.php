<?php
/**
 * Uses admin bootstrap to reuse DB connection/auth/flash if available.
 * For public pages we only need PDO.
 */

require_once __DIR__ . '/../../admin/bootstrap.php';

$pdo = getPDO();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}