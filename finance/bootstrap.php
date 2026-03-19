<?php
/**
 * Finance Bootstrap
 * Reuses admin bootstrap (session, PDO, auth, CSRF, flash) and adds backward-compat wrappers.
 *
 * ✅ Fix CSRF/session mismatch:
 * - Ensure session is started after admin bootstrap (some stacks delay it).
 * - Force cookie params to be consistent on www/non-www.
 * - Provide finance_* CSRF functions used by finance module forms/actions.
 */

require_once __DIR__ . '/../admin/bootstrap.php';

/**
 * ✅ Make sure session is active (critical for CSRF + flash).
 * If admin/bootstrap.php already started it, this does nothing.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    // Try to make cookie valid for both www and non-www (best-effort; harmless if ignored)
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    $domain = $host;
    // If host is www.example.com -> domain .example.com (covers both)
    if (stripos($host, 'www.') === 0) {
        $domain = '.' . substr($host, 4);
    } elseif (substr_count($host, '.') >= 2) {
        // If host is something like a.b.c keep as-is (avoid breaking)
        $domain = $host;
    }

    // Only set cookie params when session not active yet
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $params = session_get_cookie_params();

    session_set_cookie_params([
        'lifetime' => $params['lifetime'] ?? 0,
        'path'     => $params['path'] ?? '/',
        'domain'   => $domain ?: ($params['domain'] ?? ''),
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

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

/* =============================================================================
 * ✅ Finance CSRF (independent + stable)
 * Why: admin CSRF helpers may use a different token name/flow.
 * Use these in finance forms/actions:
 *   - finance_csrf_field()
 *   - finance_verify_csrf()
 * ============================================================================= */
if (!function_exists('finance_csrf_token')) {
    function finance_csrf_token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['_finance_csrf'])) {
            $_SESSION['_finance_csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['_finance_csrf'];
    }
}

if (!function_exists('finance_csrf_field')) {
    function finance_csrf_field(): string
    {
        $t = finance_csrf_token();
        return '<input type="hidden" name="_finance_csrf" value="' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('finance_verify_csrf')) {
    function finance_verify_csrf(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $sent = (string)($_POST['_finance_csrf'] ?? '');
        $sess = (string)($_SESSION['_finance_csrf'] ?? '');

        $ok = ($sent !== '' && $sess !== '' && hash_equals($sess, $sent));
        if (!$ok) {
            // Provide a clear error (and helpful debug)
            $dbg = 'رمز الأمان غير صالح. يُرجى المحاولة مجدداً.'
                 . ' (sent=' . ($sent !== '' ? 'yes' : 'no')
                 . ', sess=' . ($sess !== '' ? 'yes' : 'no')
                 . ', sid=' . (session_id() ?: 'none') . ')';

            set_flash('error', $dbg);
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
            exit;
        }
    }
}