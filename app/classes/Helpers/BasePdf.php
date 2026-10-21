<?php

namespace App\Helpers;

use Override;
use App\Utilities\LoggerUtility;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Base class for every PDF the app builds.
 *
 * PDF HTML carries text from the database, such as patient and facility names,
 * and TCPDF 6 receives no security fixes. Each HTML tag passes through
 * openHTMLTagHandler() after TCPDF has parsed it, so the checks here see
 * exactly what TCPDF would act on:
 *  - an image must be raster data, or an image file inside WEB_ROOT
 *  - a link may only be #..., mailto: or http(s)://
 *  - form fields, forms and <tcpdf> tags render as plain text
 *  - on* attributes are removed
 * Anything else is dropped and logged. This stops injected HTML from fetching
 * URLs, embedding server files, adding scripts or script links, or stopping a
 * print run (TCPDF throws on a <form> without an action).
 *
 * It extends FPDI's TCPDF class, which itself extends TCPDF, so classes that
 * import pages and classes that do not share this one parent.
 */
class BasePdf extends Fpdi
{
    private const INERT_TAGS = ['form', 'input', 'textarea', 'select', 'tcpdf'];

    /** @var array<string, bool> */
    private static array $imageVerdicts = [];

    #[Override]
    protected function openHTMLTagHandler($dom, $key, $cell)
    {
        $tag = (string) ($dom[$key]['value'] ?? '');
        $attributes = $dom[$key]['attribute'] ?? [];

        if (is_array($attributes)) {
            foreach (array_keys($attributes) as $name) {
                if (str_starts_with(strtolower((string) $name), 'on')) {
                    unset($dom[$key]['attribute'][$name]);
                }
            }
        }

        if (in_array($tag, self::INERT_TAGS, true)) {
            self::logDropped($tag, (string) ($attributes['type'] ?? ''));
            $dom[$key]['value'] = 'span';
        } elseif ($tag === 'img' && isset($attributes['src'])) {
            $src = (string) $attributes['src'];
            if ($src !== '' && !self::isAllowedImage($src)) {
                self::logDropped('img', $src);
                $dom[$key]['attribute']['src'] = '';
            }
        } elseif ($tag === 'a' && isset($attributes['href'])) {
            $href = trim((string) $attributes['href']);
            if (!preg_match('~^(#|mailto:|https?://)~i', $href)) {
                self::logDropped('a', $href);
                unset($dom[$key]['attribute']['href']);
            }
        }

        return parent::openHTMLTagHandler($dom, $key, $cell);
    }

    private static function isAllowedImage(string $src): bool
    {
        $data = null;
        if ($src[0] === '@') {
            $data = base64_decode(substr($src, 1), true);
        } elseif (preg_match('@^data:image/[^;]*;base64,(.*)@s', $src, $match)) {
            $data = base64_decode($match[1], true);
        }

        if ($data !== null) {
            // Raster only. SVG can make TCPDF fetch the files it references.
            return $data !== false && @getimagesizefromstring($data) !== false;
        }

        // Data images are large and rarely repeat, so only file paths are cached
        $file = self::imageFileTcpdfOpens($src);
        if (!isset(self::$imageVerdicts[$file])) {
            $path = realpath($file);
            $root = realpath(WEB_ROOT);
            self::$imageVerdicts[$file] = $path !== false && $root !== false && is_file($path)
                && str_starts_with($path, $root . DIRECTORY_SEPARATOR)
                && @getimagesize($path) !== false;
        }
        return self::$imageVerdicts[$file];
    }

    /**
     * TCPDF prefixes DOCUMENT_ROOT to a root-relative src such as
     * /assets/img/cross.png, so the check has to look at that file and not at
     * the src as written. Without DOCUMENT_ROOT, as on the command line, TCPDF
     * opens the src as it is.
     */
    private static function imageFileTcpdfOpens(string $src): string
    {
        $documentRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
        if ($src[0] === '/' && $documentRoot !== '' && $documentRoot !== '/') {
            $found = strpos($src, $documentRoot);
            if ($found === false || $found > 1) {
                $src = rtrim($documentRoot, '/') . $src;
            }
            $src = urldecode($src);
        }
        return $src;
    }

    private static function logDropped(string $tag, string $value): void
    {
        LoggerUtility::logWarning('PDF HTML: dropped a disallowed <' . $tag . '> tag or attribute', [
            'value' => mb_substr($value, 0, 200),
        ]);
    }
}
