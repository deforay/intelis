<?php

declare(strict_types=1);

namespace App\Utilities;

/**
 * Escaping for values a page prints, by where they land.
 *
 * The global helpers _sanitizeOutput(), _escapeRequestValue() and _jsEscape()
 * are one-line wrappers over these, so pages keep calling the short names and
 * tests exercise the same code the pages run.
 */
final class OutputEscapeUtility
{
    /** HTML text or a quoted attribute; every & is encoded. */
    public static function html(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * HTML text or a quoted attribute, for a value from the request.
     *
     * _sanitizeInput() strips tags but leaves quotes, so its output can still close
     * an attribute. It does turn & into &amp;, and echoing that raw is what pages
     * have always done, so entities already present are kept rather than encoded a
     * second time: a value renders exactly as before, but cannot break out. (A
     * character produced by an entity is text to the HTML parser, never a quote or
     * tag delimiter.)
     *
     * Not for inline event handlers (onclick="...") or <script>: there the decoded
     * text becomes JS source, so use jsInAttribute() or js() instead.
     */
    public static function requestValue(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8', false);
    }

    /**
     * A complete, quoted JS literal, safe inside <script> (no </script> breakout)
     * and, wrapped in html(), inside an inline handler. Do not add quotes around it.
     */
    public static function js(mixed $value): string
    {
        // json_encode returns false on invalid UTF-8 (e.g. attacker-supplied bytes);
        // fall back to an empty JS string so the output is always a valid literal.
        return json_encode(
            $value ?? '',
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
        ) ?: '""';
    }

    /**
     * A complete, quoted JS literal for an inline handler: onclick="fn(<?= ... ?>)".
     *
     * The browser decodes the attribute before running the handler, so the literal
     * is HTML-escaped on top: its quotes survive the decode as quotes of one JS
     * string, never as the end of the attribute or of the string.
     */
    public static function jsInAttribute(mixed $value): string
    {
        return self::html(self::js($value));
    }
}
