<?php

namespace App\Utilities;

use App\Utilities\LoggerUtility;

final class JsonUtility
{
    // Validate if a string is valid JSON
    public static function isJSON($string, bool $logError = false, $checkUtf8Encoding = false): bool
    {
        if (!is_string($string) || $string === '') {
            return false;
        }

        // Optional check for UTF-8 encoding
        if ($checkUtf8Encoding && !mb_check_encoding($string, 'UTF-8')) {
            if ($logError) {
                LoggerUtility::log('error', 'String is not valid UTF-8.');
            }
            return false;
        }

        json_decode($string);

        if (json_last_error() === JSON_ERROR_NONE) {
            return true;
        } else {
            if ($logError) {
                LoggerUtility::log('error', 'JSON decoding error (' . json_last_error() . '): ' . json_last_error_msg());
                LoggerUtility::log('error', 'Invalid JSON: ' . self::previewString($string));
            }
            return false;
        }
    }

    private const MAX_LOG_PREVIEW = 2000;
    private static function previewString(string $s, int $max = self::MAX_LOG_PREVIEW): string
    {
        $len = mb_strlen($s, 'UTF-8');
        $p = mb_substr($s, 0, $max, 'UTF-8');
        $p = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $p);
        // redact common secrets
        $p = preg_replace('/("?(password|token|secret|authorization|api[_-]?key)"?\s*:\s*)"[^"]*"/i', '$1"***"', $p);
        return $len > $max ? ($p . '… (len=' . $len . ')') : $p . " (len={$len})";
    }



    // Encode data to JSON with UTF-8 encoding
    public static function encodeUtf8Json(mixed $data): ?string
    {
        if (is_string($data)) {
            if (self::isJSON($data, checkUtf8Encoding: true)) {
                return $data; // already valid JSON
            }
            $data = MiscUtility::toUtf8($data); // normalize string
        }
        return self::toJSON($data);
    }



    // Convert data to JSON string — handle scalars & objects too
    public static function toJSON(
        mixed $data,
        int $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    ): ?string {
        // If it’s already a valid JSON string, keep it.
        if (is_string($data) && self::isJSON($data)) {
            return $data;
        }
        // Encode ANY type to JSON (arrays, objects, scalars, null)
        $json = json_encode($data, $flags);
        if ($json === false) {
            LoggerUtility::log('error', 'Data could not be encoded as JSON: ' . json_last_error_msg());
            return null;
        }
        return $json;
    }



    // Pretty-print JSON
    public static function prettyJson(array|string $json): string
    {
        $decodedJson = is_array($json) ? $json : self::decodeJson($json);
        if ($decodedJson === null) {
            return htmlspecialchars("Error in JSON decoding: " . json_last_error_msg(), ENT_QUOTES, 'UTF-8');
        }

        $encodedJson = json_encode($decodedJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return htmlspecialchars("Error in JSON encoding: " . json_last_error_msg(), ENT_QUOTES, 'UTF-8');
        }

        return $encodedJson;
    }

    // Merge multiple JSON strings into one
    public static function mergeJson(...$jsonStrings): ?string
    {
        $mergedArray = [];

        foreach ($jsonStrings as $json) {
            $array = self::decodeJson($json);
            if ($array === null) {
                return null;
            }
            $mergedArray = array_merge_recursive($mergedArray, $array);
        }

        return self::toJSON($mergedArray);
    }

    // Extract specific data from JSON using a path
    public static function extractJsonData($json, $path): mixed
    {
        $data = self::decodeJson($json);
        if ($data === null) {
            return null;
        }

        foreach (explode('.', $path) as $segment) {
            if (!isset($data[$segment])) {
                return null;
            }
            $data = $data[$segment];
        }

        return $data;
    }

    // Decode JSON string to array or object
    public static function decodeJson(mixed $json, bool $returnAssociative = true): mixed
    {
        if (is_array($json) || is_object($json) || $json === null) {
            return $json; // already decoded / native
        }
        if (!is_string($json)) {
            // numeric/bool should be returned as-is
            return $json;
        }
        $data = json_decode($json, $returnAssociative);
        if (json_last_error() !== JSON_ERROR_NONE) {
            LoggerUtility::log('error', 'Error decoding JSON: ' . json_last_error_msg());
            return null;
        }
        return $data;
    }


    // Minify JSON string
    public static function minifyJson($json): string
    {
        $decoded = self::decodeJson($json);
        if ($decoded === null) {
            return '';
        }
        return self::toJSON($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?? '';
    }


    // Get keys from JSON object
    public static function getJsonKeys($json): array
    {
        $data = self::decodeJson($json);
        return is_array($data) ? array_keys($data) : [];
    }



    // Get values from JSON object

    public static function getJsonValues($json): array
    {
        $data = self::decodeJson($json);
        return is_array($data) ? array_values($data) : [];
    }

    // Convert a value to a JSON-compatible string representation
    private static function sqlQuote(string $s): string
    {
        // Double single quotes for MySQL/MariaDB string literal safety
        return "'" . str_replace("'", "''", $s) . "'";
    }

    public static function jsonValueToString($value): string
    {
        if ($value === null) return 'null';
        if (is_bool($value)) return $value ? 'true' : 'false';
        if (is_numeric($value)) return (string)$value;
        if (is_array($value) || is_object($value)) {
            $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            $json = str_replace("'", "''", $json);
            return "'" . $json . "'";
        }
        // string
        return self::sqlQuote((string)$value);
    }

    // Convert a JSON string to a string that can be used with JSON_SET()
    public static function jsonToSetString(?string $json, string $column, $newData = []): ?string
    {
        // Decode JSON string to array
        $jsonData = $json && self::isJSON($json) ? json_decode($json, true) : [];

        // Decode newData if it's a string
        if (is_string($newData) && self::isJSON($newData)) {
            $newData = json_decode($newData, true);
        }

        // Combine original data and new data
        $data = array_merge($jsonData, $newData);

        // Return null if there's nothing to set
        if (empty($data)) {
            return null;
        }

        // Build the set string
        $setString = '';
        foreach ($data as $key => $value) {
            $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            // Escape single quotes for SQL literal (standard MySQL escaping)
            $encoded = str_replace("'", "''", $encoded);

            $setString .= ', "$.' . $key . '", CAST(\'' . $encoded . '\' AS JSON)';
        }

        // Construct and return the JSON_SET query
        return 'JSON_SET(COALESCE(' . $column . ', "{}")' . $setString . ')';
    }
}
