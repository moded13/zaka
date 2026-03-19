<?php
/**
 * Finance Helpers
 * - Money formatting
 * - Redirect helpers
 * - Date helpers
 * - DB schema detection helpers
 * - ✅ CSRF compatibility layer (fix "رمز الأمان غير صالح")
 * - Flash compatibility helpers
 */

if (!function_exists('finance_money')) {
    /**
     * Format an amount with currency symbol from settings.
     */
    function finance_money(float|string $amount, bool $withSymbol = true): string
    {
        $formatted = number_format((float)$amount, 2);
        if (!$withSymbol) {
            return $formatted;
        }
        $symbol = function_exists('getSetting') ? getSetting('currency_symbol', 'ريال') : 'ريال';
        return $formatted . ' ' . $symbol;
    }
}

if (!function_exists('finance_redirect')) {
    function finance_redirect(string $page): void
    {
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
        if (function_exists('redirect')) {
            redirect($base . '/' . ltrim($page, '/'));
            return;
        }
        header('Location: ' . $base . '/' . ltrim($page, '/'));
        exit;
    }
}

if (!function_exists('finance_date')) {
    /** Format date for display */
    function finance_date(string $date): string
    {
        if ($date === '' || $date === '0000-00-00') {
            return '—';
        }
        return $date;
    }
}

if (!function_exists('validate_date')) {
    function validate_date(string $date): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}

if (!function_exists('receipt_books_exist')) {
    /** Check if the receipt_books table has been migrated */
    function receipt_books_exist(): bool
    {
        static $exists = null;
        if ($exists === null) {
            try {
                $count = (int)getPDO()->query(
                    "SELECT COUNT(*) FROM information_schema.tables
                     WHERE table_schema = DATABASE() AND table_name = 'receipt_books'"
                )->fetchColumn();
                $exists = $count > 0;
            } catch (Throwable $e) {
                $exists = false;
            }
        }
        return $exists;
    }
}

if (!function_exists('book_id_exists_in_income')) {
    /** Check if book_id column exists in finance_income */
    function book_id_exists_in_income(): bool
    {
        static $exists = null;
        if ($exists === null) {
            try {
                $count = (int)getPDO()->query(
                    "SELECT COUNT(*) FROM information_schema.columns
                     WHERE table_schema = DATABASE()
                       AND table_name = 'finance_income'
                       AND column_name = 'book_id'"
                )->fetchColumn();
                $exists = $count > 0;
            } catch (Throwable $e) {
                $exists = false;
            }
        }
        return $exists;
    }
}

if (!function_exists('compute_missing_receipts')) {
    /**
     * Return receipt numbers in [start, end] that are NOT in $usedNos.
     *
     * @param int   $start
     * @param int   $end
     * @param int[] $usedNos sorted array of used receipt numbers
     * @return int[]
     */
    function compute_missing_receipts(int $start, int $end, array $usedNos): array
    {
        $usedSet = array_flip($usedNos);
        $missing = [];
        for ($n = $start; $n <= $end; $n++) {
            if (!isset($usedSet[$n])) {
                $missing[] = $n;
            }
        }
        return $missing;
    }
}

/* =========================================================
 * ✅ Flash compatibility (optional)
 * =======================================================*/
if (!function_exists('finance_flash_error')) {
    function finance_flash_error(string $msg): void
    {
        if (function_exists('set_flash')) {
            set_flash('error', $msg);
        } elseif (function_exists('flashError')) {
            flashError($msg);
        }
    }
}
if (!function_exists('finance_flash_success')) {
    function finance_flash_success(string $msg): void
    {
        if (function_exists('set_flash')) {
            set_flash('success', $msg);
        } elseif (function_exists('flashSuccess')) {
            flashSuccess($msg);
        }
    }
}

/* =========================================================
 * ✅ CSRF compatibility layer (fixes token mismatch)
 * - Always use finance_csrf_field() in forms
 * - Always call finance_verify_csrf() on POST
 * =======================================================*/
if (!function_exists('finance_csrf_field')) {
    function finance_csrf_field(): string
    {
        // Use system CSRF field if present
        if (function_exists('csrf_field')) {
            return (string)csrf_field();
        }
        if (function_exists('csrfField')) {
            return (string)csrfField();
        }

        // Fallback CSRF (only if system has no CSRF)
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['_finance_csrf'])) {
            $_SESSION['_finance_csrf'] = bin2hex(random_bytes(16));
        }
        return '<input type="hidden" name="_finance_csrf" value="' .
            htmlspecialchars($_SESSION['_finance_csrf'], ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('finance_verify_csrf')) {
    function finance_verify_csrf(): void
    {
        // Use system verifier if present
        if (function_exists('verify_csrf')) {
            verify_csrf();
            return;
        }
        if (function_exists('verifyCsrf')) {
            verifyCsrf();
            return;
        }

        // Fallback verify
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $sent = (string)($_POST['_finance_csrf'] ?? '');
        $ok   = isset($_SESSION['_finance_csrf']) && hash_equals((string)$_SESSION['_finance_csrf'], $sent);
        if (!$ok) {
            finance_flash_error('رمز الأمان غير صالح. يُرجى المحاولة مجدداً.');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
            exit;
        }
    }
}