<?php

function orphan_e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function orphan_json(array $a): string {
    return json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function orphan_now(): string {
    return date('Y-m-d H:i:s');
}

function orphan_make_code(int $len = 6): string {
    // Numeric code for WhatsApp (easy to type)
    $code = '';
    for ($i=0; $i<$len; $i++) $code .= (string)random_int(0, 9);
    return $code;
}

function orphan_storage_ensure(): void {
    if (!is_dir(ORPHAN_STORAGE_ROOT)) {
        @mkdir(ORPHAN_STORAGE_ROOT, 0775, true);
    }
}

function orphan_validate_upload(array $f): array {
    if (!isset($f['error']) || $f['error'] !== UPLOAD_ERR_OK) {
        return [false, 'فشل رفع الملف.'];
    }
    if (($f['size'] ?? 0) <= 0 || ($f['size'] ?? 0) > ORPHAN_MAX_UPLOAD_BYTES) {
        return [false, 'حجم الملف غير مسموح.'];
    }

    $tmp = $f['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return [false, 'ملف غير صالح.'];
    }

    $mime = '';
    if (function_exists('mime_content_type')) {
        $mime = (string)@mime_content_type($tmp);
    }
    if ($mime === '') {
        // fallback by extension (less accurate)
        $mime = 'application/octet-stream';
    }

    if (!in_array($mime, ORPHAN_ALLOWED_MIME, true)) {
        return [false, 'نوع الملف غير مسموح. المسموح: JPG/PNG/PDF.'];
    }
    return [true, $mime];
}

function orphan_admin_id(): ?int {
    if (function_exists('current_admin_id')) {
        $id = current_admin_id();
        return ($id && (int)$id > 0) ? (int)$id : null;
    }
    if (function_exists('currentAdmin')) {
        $a = currentAdmin();
        $id = $a['id'] ?? null;
        return ($id && (int)$id > 0) ? (int)$id : null;
    }
    return null;
}

/**
 * Finance-like CSRF (independent) for orphan admin forms.
 */
function orphan_csrf_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['_orphan_csrf'])) {
        $_SESSION['_orphan_csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['_orphan_csrf'];
}

function orphan_csrf_field(): string {
    return '<input type="hidden" name="_orphan_csrf" value="' . orphan_e(orphan_csrf_token()) . '">';
}

function orphan_verify_csrf(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $sent = (string)($_POST['_orphan_csrf'] ?? '');
    $sess = (string)($_SESSION['_orphan_csrf'] ?? '');
    if ($sent === '' || $sess === '' || !hash_equals($sess, $sent)) {
        // Use admin flash if available
        if (function_exists('flashError')) flashError('رمز الأمان غير صالح. يُرجى المحاولة مجدداً.');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? ORPHAN_PUBLIC_BASE . '/admin/index.php'));
        exit;
    }
}