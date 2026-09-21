<?php

declare(strict_types=1);

namespace App\Utilities;

use Generator;
use InvalidArgumentException;
use JsonException;
use UnexpectedValueException;

final class ResultSyncPayload
{
    /**
     * Accept the endpoint's decoded request and legacy JSON-string callers.
     *
     * @return array<array-key, mixed>|null
     */
    public static function decode(mixed $input): ?array
    {
        if (is_array($input)) {
            return $input;
        }
        if (!is_string($input)) {
            return null;
        }
        try {
            $decoded = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * Measure complete uncompressed requests, including envelope and manifests.
     * A sample is indivisible. An oversized single sample is flagged, never dropped.
     *
     * @param iterable<array<array-key, array<string, mixed>>> $chunks
     * @param callable(array<array-key, array<string, mixed>>): array<string, mixed> $buildPayload
     * @param callable(): int $recordLimit
     * @return Generator<int, array{payload: array<string, mixed>, json: string, bytes: int, oversized: bool}>
     */
    public static function requests(
        iterable $chunks,
        callable $buildPayload,
        int $maximumBytes,
        callable $recordLimit
    ): Generator {
        if ($maximumBytes < 1) {
            throw new InvalidArgumentException('Payload byte limit must be positive.');
        }
        foreach ($chunks as $chunk) {
            // Build child data and manifests once, then split the prepared payload.
            $pending = [$buildPayload($chunk)];
            while ($pending !== []) {
                $payload = array_pop($pending);
                $results = $payload['results'] ?? null;
                if (!is_array($results) || $results === []) {
                    throw new UnexpectedValueException('Result payload must contain a nonempty results array.');
                }
                $limit = max(1, $recordLimit());
                $json = JsonUtility::encodeUtf8Json($payload);
                if ($json === null) {
                    throw new UnexpectedValueException('Could not encode result sync payload.');
                }
                $bytes = strlen($json);
                if (count($results) > $limit || ($bytes > $maximumBytes && count($results) > 1)) {
                    $cut = count($results) > $limit ? $limit : (int) ceil(count($results) / 2);
                    // Stack the right half first so original record order survives splitting.
                    $pending[] = self::subset($payload, array_slice($results, $cut, null, true));
                    $pending[] = self::subset($payload, array_slice($results, 0, $cut, true));
                    continue;
                }
                yield [
                    'payload' => $payload, 'json' => $json, 'bytes' => $bytes,
                    'oversized' => $bytes > $maximumBytes,
                ];
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<array-key, mixed> $results
     * @return array<string, mixed>
     */
    private static function subset(array $payload, array $results): array
    {
        $payload['results'] = $results;
        if (isset($payload['manifests']) && is_array($payload['manifests'])) {
            $codes = [];
            foreach ($results as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $form = $row['form_data'] ?? $row;
                $code = is_array($form) ? ($form['referral_manifest_code'] ?? null) : null;
                if (is_string($code) && $code !== '') {
                    $codes[$code] = true;
                }
            }
            $payload['manifests'] = array_values(array_filter(
                $payload['manifests'],
                static fn($manifest): bool => is_array($manifest)
                    && is_string($manifest['manifest_code'] ?? null)
                    && isset($codes[$manifest['manifest_code']])
            ));
        }
        return $payload;
    }
}
