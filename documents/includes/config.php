<?php
/**
 * Documents module config
 * Paths are absolute on server.
 */

define('ORPHAN_MODULE_ROOT', __DIR__ . '/..'); // /var/www/vhosts/.../zaka/documents
define('ORPHAN_STORAGE_ROOT', ORPHAN_MODULE_ROOT . '/zaka_storage/beneficiaries_docs');

define('ORPHAN_MAX_UPLOAD_BYTES', 8 * 1024 * 1024); // 8MB
define('ORPHAN_ALLOWED_MIME', [
    'image/jpeg',
    'image/png',
    'application/pdf',
]);

define('ORPHAN_PUBLIC_BASE', '/zaka/documents'); // URL base