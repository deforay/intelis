<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use ZipArchive;

/**
 * Smart single-file compressor/decompressor with fallback:
 * zstd (.zst) -> pigz/gzip (.gz) -> zip (.zip)
 *
 * Public surface:
 *  - compressFile(string $src, string $dst, ?string $backend=null): string
 *  - compressContent(string $content, string $dst, ?string $backend=null): string
 *  - decompressToFile(string $src, string $dstDir): string
 *  - decompressToString(string $src): string
 *
 * Notes:
 *  - $dst may be given without the final extension; we append based on backend.
 *  - If $backend is null, we auto-pick best available.
 *  - For .zip we store as a 1-file zip (entry name = basename($dst) without .zip or
 *    basename($src) if $dst has no stem).
 */
final class ArchiveService
{
    public const BACKEND_ZSTD = 'zstd';
    public const BACKEND_PIGZ = 'pigz';
    public const BACKEND_GZIP = 'gzip';
    public const BACKEND_ZIP  = 'zip';

    /** ---------- Public API ---------- */

    public static function compressFile(string $src, string $dst, ?string $backend = null): string
    {
        if (!is_file($src)) {
            throw new RuntimeException("Source file not found: $src");
        }

        $backend   = $backend ?? self::pickBestBackend();
        $dst       = self::ensureExtension($dst, $backend);

        switch ($backend) {
            case self::BACKEND_ZSTD:
                self::ensureCmd('zstd');
                self::runOrThrow('zstd -T0 -q -19 -f -o ' . escapeshellarg($dst) . ' ' . escapeshellarg($src));
                break;

            case self::BACKEND_PIGZ:
                self::ensureCmd('pigz');
                self::runOrThrow('pigz -c ' . escapeshellarg($src) . ' > ' . escapeshellarg($dst));
                break;

            case self::BACKEND_GZIP:
                self::ensureCmd('gzip');
                self::runOrThrow('gzip -c ' . escapeshellarg($src) . ' > ' . escapeshellarg($dst));
                break;

            case self::BACKEND_ZIP:
                self::zipOne($src, $dst);
                break;

            default:
                throw new RuntimeException("Unsupported backend: $backend");
        }

        if (!is_file($dst)) {
            throw new RuntimeException("Compression produced no file: $dst");
        }
        return $dst;
    }

    public static function compressContent(string $content, string $dst, ?string $backend = null): string
    {
        $tmp = self::tmpFile();
        file_put_contents($tmp, $content);
        try {
            return self::compressFile($tmp, $dst, $backend);
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Decompress an archive (.zst|.gz|.zip) into $dstDir, returning the
     * extracted file path.
     */
    public static function decompressToFile(string $src, string $dstDir): string
    {
        if (!is_file($src)) {
            throw new RuntimeException("Archive not found: $src");
        }
        if (!is_dir($dstDir) && !@mkdir($dstDir, 0777, true)) {
            throw new RuntimeException("Failed to create dir: $dstDir");
        }

        $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));

        // Plain CSV: copy as-is (viewer supports it)
        if ($ext === 'csv') {
            $out = rtrim($dstDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($src);
            if (!@copy($src, $out)) {
                throw new RuntimeException("Failed to copy CSV: $src");
            }
            return $out;
        }

        if ($ext === 'zst') {
            self::ensureCmd('zstd');
            $out = rtrim($dstDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::stripLastExt(basename($src));
            self::runOrThrow('zstd -dc ' . escapeshellarg($src) . ' > ' . escapeshellarg($out));
            return $out;
        }

        if ($ext === 'gz') {
            $out = rtrim($dstDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::stripLastExt(basename($src));
            // Prefer pigz if available
            $dc = self::hasCmd('pigz') ? 'pigz -dc' : 'gzip -dc';
            self::runOrThrow($dc . ' ' . escapeshellarg($src) . ' > ' . escapeshellarg($out));
            return $out;
        }

        if ($ext === 'zip') {
            $zip = new ZipArchive();
            if ($zip->open($src) !== true) {
                throw new RuntimeException("Failed to open zip: $src");
            }
            if ($zip->numFiles < 1) {
                $zip->close();
                throw new RuntimeException("Zip is empty: $src");
            }
            $name = $zip->getNameIndex(0);
            // Optional hardening:
            $safeName = basename($name);
            $tmpDir = rtrim($dstDir, DIRECTORY_SEPARATOR);
            if (!$zip->extractTo($tmpDir, [$name])) {
                $zip->close();
                throw new RuntimeException("Failed to extract $name from zip: $src");
            }
            $zip->close();
            // normalize to basename path if needed
            $extracted = $tmpDir . DIRECTORY_SEPARATOR . (is_file($tmpDir . DIRECTORY_SEPARATOR . $safeName) ? $safeName : $name);
            return $extracted;
        }

        throw new RuntimeException("Unknown archive type for: $src");
    }


    /** Convenience: read the uncompressed bytes as a string. */
    public static function decompressToString(string $src): string
    {
        $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));

        if ($ext === 'csv') {
            $data = @file_get_contents($src);
            if ($data === false) throw new RuntimeException("Failed to read CSV: $src");
            return $data;
        }
        if ($ext === 'zst') {
            self::ensureCmd('zstd');
            return self::runOrThrowCapture('zstd -dc ' . escapeshellarg($src));
        }
        if ($ext === 'gz') {
            $dc = self::hasCmd('pigz') ? 'pigz -dc' : 'gzip -dc';
            return self::runOrThrowCapture($dc . ' ' . escapeshellarg($src));
        }
        if ($ext === 'zip') {
            $zip = new ZipArchive();
            if ($zip->open($src) !== true) throw new RuntimeException("Failed to open zip: $src");
            if ($zip->numFiles < 1) {
                $zip->close();
                throw new RuntimeException("Zip is empty: $src");
            }
            $name = $zip->getNameIndex(0);
            $data = $zip->getFromName($name);
            $zip->close();
            if ($data === false) throw new RuntimeException("Failed to read $name from zip: $src");
            return $data;
        }
        throw new RuntimeException("Unknown archive type for: $src");
    }


    /** Return the preferred extension for a backend. */
    public static function extensionForBackend(string $backend): string
    {
        return match ($backend) {
            self::BACKEND_ZSTD => '.zst',
            self::BACKEND_PIGZ,
            self::BACKEND_GZIP => '.gz',
            self::BACKEND_ZIP  => '.zip',
            default            => '',
        };
    }

    /** Pick best available backend (zstd > pigz > gzip > zip). */
    public static function pickBestBackend(): string
    {
        if (self::hasCmd('zstd')) return self::BACKEND_ZSTD;
        if (self::hasCmd('pigz')) return self::BACKEND_PIGZ;
        if (self::hasCmd('gzip')) return self::BACKEND_GZIP;
        if (class_exists(ZipArchive::class)) return self::BACKEND_ZIP;
        throw new RuntimeException('No supported compressor found (need zstd/pigz/gzip or ZipArchive).');
    }

    /** ---------- Internals ---------- */

    private static function hasCmd(string $cmd): bool
    {
        $out = @shell_exec('command -v ' . escapeshellarg($cmd) . ' 2>/dev/null');
        return is_string($out) && trim($out) !== '';
    }

    private static function ensureCmd(string $cmd): void
    {
        if (!self::hasCmd($cmd)) {
            throw new RuntimeException("Required command not found: $cmd");
        }
    }

    private static function ensureExtension(string $dst, string $backend): string
    {
        $ext = strtolower(pathinfo($dst, PATHINFO_EXTENSION));
        if ($ext === 'zst' || $ext === 'gz' || $ext === 'zip') {
            return $dst; // caller already set it
        }
        return $dst . self::extensionForBackend($backend);
    }

    private static function stripLastExt(string $filename): string
    {
        $pos = strrpos($filename, '.');
        return $pos === false ? $filename : substr($filename, 0, $pos);
    }

    private static function zipOne(string $src, string $dst): void
    {
        $zip = new ZipArchive();
        if ($zip->open($dst, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Failed to open zip: $dst");
        }
        // Use the destination stem (without .zip) as the entry name for stability
        $entry = basename(self::stripLastExt($dst));
        // If dst stem has no extension, keep original src basename
        if ($entry === '' || $entry === basename($dst)) {
            $entry = basename($src);
        }
        if (!$zip->addFile($src, $entry)) {
            $zip->close();
            throw new RuntimeException("Failed to add file to zip: $src");
        }
        $zip->close();
    }

    private static function tmpFile(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'archv_');
        if ($tmp === false) throw new RuntimeException('Failed to create temp file');
        return $tmp;
    }

    private static function runOrThrow(string $cmd): void
    {
        // Use a real shell so redirection, pipes, env, etc. work.
        $des = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $p   = proc_open('/bin/sh -lc ' . escapeshellarg($cmd), $des, $pipes);
        if (!\is_resource($p)) throw new RuntimeException("Failed to start: $cmd");
        $out = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($p);
        if ($code !== 0) throw new RuntimeException("Command failed ($code): $cmd\n$err$out");
    }

    private static function runOrThrowCapture(string $cmd): string
    {
        $des = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $p   = proc_open('/bin/sh -lc ' . escapeshellarg($cmd), $des, $pipes);
        if (!\is_resource($p)) throw new RuntimeException("Failed to start: $cmd");
        $out = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($p);
        if ($code !== 0) throw new RuntimeException("Command failed ($code): $cmd\n$err$out");
        return (string)$out;
    }
}
