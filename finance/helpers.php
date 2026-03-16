<?php
/**
 * Finance Helpers
 * Money formatting, receipt validation, date helpers, redirect shortcuts.
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
        // Derive path from current script location for portability
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
        redirect($base . '/' . ltrim($page, '/'));
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
     * Uses a DB-side set subtraction to avoid loading huge PHP arrays.
     *
     * @param int   $start
     * @param int   $end
     * @param int[] $usedNos sorted array of used receipt numbers
     * @return int[]
     */
    function compute_missing_receipts(int $start, int $end, array $usedNos): array
    {
        $usedSet    = array_flip($usedNos);
        $missing    = [];
        for ($n = $start; $n <= $end; $n++) {
            if (!isset($usedSet[$n])) {
                $missing[] = $n;
            }
        }
        return $missing;
    }
}
