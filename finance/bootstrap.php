<?php
/**
 * Finance Bootstrap
 * Reuses admin bootstrap (session, PDO, auth, CSRF, flash) and adds backward-compat wrappers.
 */

require_once __DIR__ . '/../admin/bootstrap.php';

// ── Backward compat: $pdo global ─────────────────────────────────────────────
$pdo = getPDO();

// ── Enforce login ─────────────────────────────────────────────────────────────
requireLogin();

// ── Backward compat: set_flash / get_flash / old ──────────────────────────────
if (!function_exists('set_flash')) {
    function set_flash(string $type, string $message): void
    {
        match ($type) {
            'success' => flashSuccess($message),
            'error'   => flashError($message),
            default   => flashInfo($message),
        };
    }
}

if (!function_exists('get_flash')) {
    function get_flash(): ?array
    {
        $flash = getFlash();
        if (!$flash) {
            return null;
        }
        $map = ['success' => 'success', 'error' => 'error', 'info' => 'info'];
        foreach ($flash as $type => $msg) {
            return ['type' => $map[$type] ?? 'info', 'message' => (string)$msg];
        }
        return null;
    }
}

if (!function_exists('old')) {
    function old(string $key, string $default = ''): string
    {
        return (string)($_POST[$key] ?? $default);
    }
}
