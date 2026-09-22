<?php

namespace App\Utilities;

use Throwable;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Handler\AbstractProcessingHandler;

/**
 * Copies error-level log entries into the SQLite error index
 * (see ErrorIndexUtility). It sits beside the file handler, never instead of
 * it, and it never stops the record from reaching the other handlers.
 */
final class ErrorIndexLogHandler extends AbstractProcessingHandler
{
    public function __construct(Level $level = Level::Error)
    {
        parent::__construct($level, bubble: true);
    }

    protected function write(LogRecord $record): void
    {
        try {
            $context = $record->context;
            ErrorIndexUtility::record([
                'logged_at' => $record->datetime->format('Y-m-d H:i:s'),
                'level' => $record->level->getName(),
                'message' => $record->message,
                'error_id' => isset($context['error_id']) && is_string($context['error_id']) ? $context['error_id'] : null,
                'exception_class' => isset($context['exception_class']) && is_string($context['exception_class']) ? $context['exception_class'] : null,
                'file' => isset($context['file']) && is_string($context['file']) ? $context['file'] : null,
                'line' => isset($context['line']) && is_numeric($context['line']) ? (int) $context['line'] : null,
                'url' => self::currentUrl(),
                'user_id' => isset($_SESSION['userId']) && is_scalar($_SESSION['userId']) ? (string) $_SESSION['userId'] : null,
                'ip' => isset($context['ip_address']) && is_string($context['ip_address'])
                    ? $context['ip_address']
                    : ($_SERVER['REMOTE_ADDR'] ?? null),
            ]);
        } catch (Throwable) {
            // The file handler has the entry; the index is only a shortcut to it.
        }
    }

    /** Path only: a query string can carry search terms, tokens or patient details. */
    private static function currentUrl(): ?string
    {
        if (PHP_SAPI === 'cli') {
            $script = $_SERVER['argv'][0] ?? null;
            return is_string($script) ? 'cli: ' . basename($script) : 'cli';
        }
        $uri = $_SERVER['REQUEST_URI'] ?? null;
        if (!is_string($uri) || $uri === '') {
            return null;
        }
        $path = strtok($uri, '?');
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        return trim($method . ' ' . ($path === false ? $uri : $path));
    }
}
