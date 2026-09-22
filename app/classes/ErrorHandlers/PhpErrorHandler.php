<?php

declare(strict_types=1);

namespace App\ErrorHandlers;

use ErrorException;
use App\Utilities\LoggerUtility;

/**
 * PHP's warnings, notices and deprecations, as bootstrap.php routes them.
 *
 * Two things the old closure got wrong. It ignored the @ operator, so a call the
 * code had deliberately silenced because it handles the failure itself (provision's
 * best-effort chmod on a directory it does not own) was still logged. And in debug
 * mode it logged every warning and notice at ERROR, where it sat in the error index
 * and the recurring-errors panel next to real failures.
 */
final class PhpErrorHandler
{
    private const array FATAL = [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE];

    public function __construct(private readonly bool $verbose = false)
    {
    }

    public function __invoke(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        // Silenced with @, or below the reporting level. Since PHP 8, @ leaves only
        // the fatal levels in error_reporting(), so a warning fails this test.
        if ((error_reporting() & $severity) === 0) {
            return true;
        }

        $exception = new ErrorException($message, 0, $severity, $file, $line);
        $fatal = in_array($severity, self::FATAL, true);

        if ($fatal || $this->verbose) {
            LoggerUtility::log(self::level($severity), $message, [
                'exception' => $exception,
                'trace' => debug_backtrace(),
            ]);
        }
        if ($fatal) {
            throw $exception;
        }

        // Handled: PHP's own handler would only print or log it a second time.
        return true;
    }

    private static function level(int $severity): string
    {
        return match ($severity) {
            E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => 'warning',
            E_NOTICE, E_USER_NOTICE => 'notice',
            E_DEPRECATED, E_USER_DEPRECATED => 'info',
            default => 'error',
        };
    }
}
