<?php

namespace App\Utilities;

use App\Utilities\LoggerUtility;

final class JsonUtility
{
    public static function isJSON($string, bool $logError = false): bool
    {
        if (empty($string) || !is_string($string)) {
            return false;
        }

        json_decode($string);

        if (json_last_error() === JSON_ERROR_NONE) {
            return true;
        } else {
            if ($logError) {
                LoggerUtility::log('error', 'JSON decoding error: ' . json_last_error_msg());
                LoggerUtility::log('error', 'Invalid JSON: ' . $string);
            }
            return false;
        }
    }

    public static function toUtf8(array|string|null $input): array|string|null
    {
        if (is_array($input)) {
            return array_map([self::class, 'toUtf8'], $input);
        }
        if (is_string($input)) {
            $encoding = mb_detect_encoding($input, mb_detect_order(), true) ?? 'UTF-8';
            return mb_convert_encoding($input, 'UTF-8', $encoding);
        }
        return $input;
    }

    public static function encodeUtf8Json(array|string|null $data): string
    {
        if (is_null($data)) {
            return '{}';
        }
        if (is_array($data) && empty($data)) {
            return '[]';
        }

        return self::toJSON(self::toUtf8($data));
    }

    public static function toJSON($data, int $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE): ?string
    {
        $json = json_encode($data, $flags);
        if ($json === false) {
            LoggerUtility::log('error', 'Data could not be encoded as JSON: ' . json_last_error_msg());
            return null;
        }
        return $json;
    }

    public static function prettyJson(array|string $json): string
    {
        $decodedJson = is_array($json) ? $json : self::decodeJson($json);
        if ($decodedJson === null) {
            return htmlspecialchars("Error in JSON decoding: " . json_last_error_msg(), ENT_QUOTES, 'UTF-8');
        }

        $encodedJson = json_encode($decodedJson, JSON_PRETTY_PRINT);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return htmlspecialchars("Error in JSON encoding: " . json_last_error_msg(), ENT_QUOTES, 'UTF-8');
        }

        return $encodedJson;
    }

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

    public static function decodeJson($json, bool $assoc = true): mixed
    {
        $data = json_decode($json, $assoc);
        if (json_last_error() !== JSON_ERROR_NONE) {
            LoggerUtility::log('error', 'Error decoding JSON: ' . json_last_error_msg());
            return null;
        }
        return $data;
    }

    public static function minifyJson($json): string
    {
        $decodedJson = self::decodeJson($json);
        if ($decodedJson === null) {
            return '';
        }

        return self::toJSON($decodedJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function getJsonKeys($json): array
    {
        $data = self::decodeJson($json);
        if ($data === null) {
            return [];
        }

        return array_keys($data);
    }

    public static function getJsonValues($json): array
    {
        $data = self::decodeJson($json);
        if ($data === null) {
            return [];
        }

        return array_values($data);
    }

    /**
     * Convert a value to a JSON-compatible string representation
     *
     * @param mixed $value The value to convert
     * @return string The JSON-compatible string representation
     */
    public static function jsonValueToString($value): string
    {
        if (is_null($value)) {
            return 'null';
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_numeric($value)) {
            return (string) $value;
        } elseif (is_array($value)) {
            return "'" . addslashes(json_encode($value)) . "'";
        } else {
            return "'" . addslashes((string) $value) . "'";
        }
    }

    /**
     * Convert a JSON string to a string that can be used with JSON_SET()
     *
     * @param string|null $json The JSON string to convert
     * @param string $column The name of the JSON column
     * @param array|string $newData An optional array or JSON string of new key-value pairs to add to the JSON
     * @return string|null The string that can be used with JSON_SET()
     */
    public static function jsonToSetString(?string $json, string $column, $newData = []): ?string
    {
        // Decode JSON string to array
        $jsonData = $json && self::isJSON($json) ? json_decode($json, true) : [];

        // Decode newData if it's a string
        if (is_string($newData)) {
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
            $setString .= ', "$.' . $key . '", ' . self::jsonValueToString($value);
            //$setString .= ', "$.' . $key . '", JSON_UNQUOTE(' . (string) $this->jsonValueToString($value) . ')';
        }

        // Construct and return the JSON_SET query
        return 'JSON_SET(COALESCE(' . $column . ', "{}")' . $setString . ')';
    }
}
