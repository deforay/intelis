<?php

namespace App\Helpers;

use Override;
use App\Utilities\LoggerUtility;
use TCPDF_IMAGES;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Base class for every PDF the app builds.
 *
 * PDF HTML carries text from the database, such as patient and facility names,
 * and TCPDF 6 receives no security fixes. Two hooks keep that HTML from
 * fetching URLs, embedding server files, adding scripts or script links, or
 * stopping a print run (TCPDF throws on a <form> without an action):
 *
 * getHtmlDomArray() runs before TCPDF parses the HTML. It removes <link> tags,
 * because TCPDF fetches a linked stylesheet while it parses.
 *
 * openHTMLTagHandler() sees each tag after TCPDF has parsed it:
 *  - an image must be raster data, or a raster file inside WEB_ROOT. An
 *    approved file is passed to TCPDF as its real path, with DOCUMENT_ROOT
 *    hidden, so TCPDF loads exactly the file that was checked.
 *  - a list may not use an image as its bullet
 *  - a link may only be #... or http(s)://
 *  - form fields, forms and <tcpdf> tags render as plain text
 *  - on* attributes are removed
 * Anything else is dropped and logged.
 *
 * It extends FPDI's TCPDF class, which itself extends TCPDF, so classes that
 * import pages and classes that do not share this one parent.
 */
class BasePdf extends Fpdi
{
    private const INERT_TAGS = ['form', 'input', 'textarea', 'select', 'tcpdf'];

    /** @var array<string, string|false> real path of an approved image file, or false when refused */
    private static array $imageSources = [];

    #[Override]
    protected function getHtmlDomArray($html)
    {
        $html = preg_replace_callback('/<link([^\>]*?)>/is', function (array $match): string {
            self::logDropped('link', $match[1]);
            return '';
        }, (string) $html);

        return parent::getHtmlDomArray($html);
    }

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

        // A list item takes its bullet from its parent list. An image bullet
        // ("img|type|width|height|file") would load that file unchecked.
        foreach ([$key, $dom[$key]['parent'] ?? null] as $listKey) {
            if ($listKey === null || !isset($dom[$listKey])) {
                continue;
            }
            foreach (['type', 'listtype'] as $field) {
                $value = $field === 'type'
                    ? ($dom[$listKey]['attribute']['type'] ?? null)
                    : ($dom[$listKey]['listtype'] ?? null);
                if (is_string($value) && str_starts_with(strtolower(trim($value)), 'img|')) {
                    self::logDropped('list image', $value);
                    if ($field === 'type') {
                        unset($dom[$listKey]['attribute']['type']);
                    } else {
                        $dom[$listKey]['listtype'] = '';
                    }
                }
            }
        }

        if (in_array($tag, self::INERT_TAGS, true)) {
            self::logDropped($tag, (string) ($attributes['type'] ?? ''));
            $dom[$key]['value'] = 'span';
        } elseif ($tag === 'img' && isset($attributes['src'])) {
            $src = (string) $attributes['src'];
            if ($src !== '') {
                $source = self::imageSource($src);
                if ($source === false) {
                    self::logDropped('img', $src);
                    $source = '';
                }
                $dom[$key]['attribute']['src'] = $source;
            }
        } elseif ($tag === 'a' && isset($attributes['href'])) {
            $href = trim((string) $attributes['href']);
            if (!preg_match('~^(#|https?://)~i', $href)) {
                self::logDropped('a', $href);
                unset($dom[$key]['attribute']['href']);
            }
        }

        if ($tag !== 'img') {
            return parent::openHTMLTagHandler($dom, $key, $cell);
        }

        // An approved file src is already the real path. TCPDF prefixes
        // DOCUMENT_ROOT to a path that does not contain it, and then tries other
        // locations and URLs for a file it cannot find, so it reads no
        // DOCUMENT_ROOT while it loads the image.
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
        $_SERVER['DOCUMENT_ROOT'] = '';
        try {
            return parent::openHTMLTagHandler($dom, $key, $cell);
        } finally {
            $_SERVER['DOCUMENT_ROOT'] = $documentRoot;
        }
    }

    /**
     * The src TCPDF should load for an image, or false to drop it.
     *
     * A data image becomes data:image/<type>, with the type read from its
     * bytes, because TCPDF treats an '@' stream that contains "<svg" anywhere
     * as SVG, even a PNG with that text in its metadata. A file becomes its
     * real path, so TCPDF stores an image used many times only once.
     */
    private static function imageSource(string $src): string|false
    {
        $data = null;
        if ($src[0] === '@') {
            $data = base64_decode(substr($src, 1), true);
        } elseif (preg_match('@^data:image/[^;]*;base64,(.*)@s', $src, $match)) {
            $data = base64_decode($match[1], true);
        }

        if ($data !== null) {
            // Raster only. SVG can make TCPDF fetch the files it references.
            $size = $data === false ? false : @getimagesizefromstring($data);
            return $size === false ? false : 'data:' . $size['mime'] . ';base64,' . base64_encode($data);
        }

        // Data images are large and rarely repeat, so only files are cached
        if (!array_key_exists($src, self::$imageSources)) {
            $file = self::imageFileInsideWebRoot($src);
            // TCPDF picks its parser for a file from the extension: .svg, .ai
            // and .eps go to the vector parsers whatever the file holds.
            $vector = $file !== null
                && in_array(TCPDF_IMAGES::getImageFileType($file), ['svg', 'ai', 'eps'], true);
            self::$imageSources[$src] = $file !== null && !$vector && @getimagesize($file) !== false
                ? $file
                : false;
        }
        return self::$imageSources[$src];
    }

    /**
     * Result PDFs use root-relative paths such as /assets/img/cross.png and
     * absolute paths such as UPLOAD_PATH signatures. Both resolve inside
     * WEB_ROOT, on the web and on the command line alike.
     */
    private static function imageFileInsideWebRoot(string $src): ?string
    {
        $root = realpath(WEB_ROOT);
        if ($root === false) {
            return null;
        }
        $candidates = [$src];
        if ($src[0] === '/') {
            $candidates[] = $root . urldecode($src);
        }
        foreach ($candidates as $candidate) {
            $path = realpath($candidate);
            if ($path !== false && is_file($path) && str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
                return $path;
            }
        }
        return null;
    }

    private static function logDropped(string $tag, string $value): void
    {
        LoggerUtility::logWarning('PDF HTML: dropped a disallowed <' . $tag . '> tag or attribute', [
            'value' => mb_substr($value, 0, 200),
        ]);
    }
}
