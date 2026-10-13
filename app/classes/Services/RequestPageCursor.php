<?php

namespace App\Services;

/**
 * Where a lab's paged pull of a date or manifest window has got to, per module.
 *
 * A big window comes from the STS a page at a time (STS\RequestsService::getRequests),
 * and the lab saves each page before asking for the next. The marker is kept in a
 * file, so a pull that is interrupted -- a dropped link, a closed terminal -- and
 * run again with the same window carries on from the page it reached. A finished
 * window clears its marker. A dry run moves through the pages without keeping one.
 */
final class RequestPageCursor
{
    public const PAGE_SIZE = 500;

    /** @var array<string, string> */
    private array $markers = [];

    public function __construct(private readonly ?string $file)
    {
        if ($file !== null && is_file($file)) {
            $saved = json_decode((string) file_get_contents($file), true);
            $this->markers = is_array($saved) ? array_filter($saved, 'is_string') : [];
        }
    }

    /** One window of one module: the same pull run again finds its marker. */
    public static function key(string $module, ?string $since, ?string $manifestCode): string
    {
        return $module . '|' . ($since ?? '') . '|' . ($manifestCode ?? '');
    }

    public function get(string $key): ?string
    {
        return $this->markers[$key] ?? null;
    }

    /**
     * Records the page just saved. Returns false when the STS answered with the
     * marker the lab already had: it would ask for the same page forever.
     */
    public function advance(string $key, string $after): bool
    {
        if (($this->markers[$key] ?? null) === $after) {
            return false;
        }
        $this->markers[$key] = $after;
        $this->save();
        return true;
    }

    public function finish(string $key): void
    {
        if (array_key_exists($key, $this->markers)) {
            unset($this->markers[$key]);
            $this->save();
        }
    }

    private function save(): void
    {
        if ($this->file === null) {
            return;
        }
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $tmp = $this->file . '.tmp';
        if (@file_put_contents($tmp, json_encode($this->markers)) !== false) {
            @rename($tmp, $this->file);
        }
    }
}
