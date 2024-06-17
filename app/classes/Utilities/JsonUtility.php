<?php

namespace App\Utilities;

use ZipArchive;
use App\Utilities\LoggerUtility;

final class JsonUtility
{

    public static function isJSON($string, $logError = false): bool
    {
        if (empty($string) || !is_string($string)) {
            return false;
        }

        json_decode($string);

        if (json_last_error() === JSON_ERROR_NONE) {
            return true;
        } else {
            if ($logError === true) {
                LoggerUtility::log('error', 'JSON decoding error: ' . json_last_error_msg());
                LoggerUtility::log('error', 'JSON decoding error: ' . $string);
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

    public static function toJSON($data): ?string
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            LoggerUtility::log('error', 'Data could not be encoded as JSON: ' . json_last_error_msg());
            return null;
        }
        return $json;
    }


    public static function prettyJson($json): string
    {
        if (is_array($json)) {
            $encodedJson = json_encode($json, JSON_PRETTY_PRINT);
        } else {
            $decodedJson = json_decode((string) $json);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Handle the error, maybe log it and return a safe error message
                return htmlspecialchars("Error in JSON decoding: " . json_last_error_msg(), ENT_QUOTES, 'UTF-8');
            }
            $encodedJson = json_encode($decodedJson, JSON_PRETTY_PRINT);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Handle the error, maybe log it and return a safe error message
            return htmlspecialchars("Error in JSON encoding: " . json_last_error_msg(), ENT_QUOTES, 'UTF-8');
        }

        return $encodedJson;
    }

    /**
     * Unzips a JSON file and displays its contents in a pretty format.
     *
     * @param string $zipFile The path to the zip file.
     * @param string $jsonFile The name of the JSON file inside the zip archive.
     */
    public static function getJsonFromZip(string $zipFile, string $jsonFile): string
    {
        if (!file_exists($zipFile)) {
            return "{}";
        }
        $zip = new ZipArchive;
        if ($zip->open($zipFile) === true) {
            $json = $zip->getFromName($jsonFile);
            $zip->close();

            return $json;
        } else {
            return "{}";
        }
    }

    /**
     * Zips a JSON string.
     *
     * @param string $json The JSON string to zip.
     * @param string $fileName The FULL PATH of the file inside the zip archive.
     * @return bool Returns true on success, false on failure.
     */
    public static function zipJson(string $json, string $fileName)
    {
        $result = false;
        if (!empty($json) && !empty($fileName)) {
            $zip = new ZipArchive();
            $zipPath = $fileName . '.zip';

            if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
                $zip->addFromString(basename($fileName), $json);

                if ($zip->status == ZIPARCHIVE::ER_OK) {
                    $result = true;
                }
                $zip->close();
            }
        }
        return $result;
    }
}
