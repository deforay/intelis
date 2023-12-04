<?php

namespace App\Utilities;

use ZipArchive;

class MiscUtility
{

    public static function randomHexColor(): string
    {
        $hexColorPart = function () {
            return str_pad(dechex(random_int(0, 255)), 2, '0', STR_PAD_LEFT);
        };

        return strtoupper($hexColorPart() . $hexColorPart() . $hexColorPart());
    }

    public static function removeDirectory($dirname): bool
    {
        // Sanity check
        if (!file_exists($dirname)) {
            return false;
        }

        // Simple delete for a file
        if (is_file($dirname) || is_link($dirname)) {
            return unlink($dirname);
        }

        // Loop through the folder
        $dir = dir($dirname);
        while (false !== ($entry = $dir->read())) {
            // Skip pointers
            if ($entry == '.' || $entry == '..') {
                continue;
            }

            // Recurse
            self::removeDirectory($dirname . DIRECTORY_SEPARATOR . $entry);
        }

        // Clean up
        $dir->close();
        return rmdir($dirname);
    }

    //dump the contents of a variable to the error log in a readable format
    public static function dumpToErrorLog($object = null): void
    {
        ob_start();
        var_dump($object);
        error_log(ob_get_clean());
    }

    /**
     * Checks if the array contains any null or empty string values.
     *
     * @param array $array The array to check.
     * @return bool Returns true if any value is null or an empty string, false otherwise.
     */
    public static function hasEmpty(array $array): bool
    {
        foreach ($array as $value) {
            if ($value === null || trim((string) $value) === "") {
                return true;
            }
        }
        return false;
    }

    public static function isJSON($string): bool
    {
        if (empty($string) || !is_string($string)) {
            return false;
        }

        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }


    public static function toJSON($data): ?string
    {
        if (!empty($data)) {
            if (self::isJSON($data)) {
                return $data;
            } else {
                $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if ($json !== false) {
                    return $json;
                } else {
                    error_log('Data could not be encoded as JSON: ' . json_last_error_msg());
                }
            }
        }
        return null;
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

        return htmlspecialchars($encodedJson, ENT_QUOTES, 'UTF-8');
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


    public static function fileExists($filePath): bool
    {
        return !empty($filePath) && is_file($filePath) && (@filesize($filePath) > 0);
    }

    public static function imageExists($filePath): bool
    {
        return self::fileExists($filePath) && false !== @getimagesize($filePath);
    }

    public static function getMimeType($file, $allowedMimeTypes)
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return false;
        }

        $mime = finfo_file($finfo, $file);
        finfo_close($finfo);

        return in_array($mime, $allowedMimeTypes) ? $mime : false;
    }

    public static function makeDirectory($path, $mode = 0777, $recursive = true): bool
    {
        if (is_dir($path)) {
            return true;
        }

        return mkdir($path, $mode, $recursive);
    }

    // Regular expression to match scientific notation
    // Example: 2.00E+1, 1.23e4, -1.23E-4, etc.
    public static function isScientificNotation($str)
    {
        $scientificRegex = '/^-?\d+(\.\d+)?[eE][+-]?\d+$/';

        return preg_match($scientificRegex, (string) $str) === 1;
    }

    public static function generateCsv($headings, $data, $filename, $delimiter = ',', $enclosure = '"')
    {
        $handle = fopen($filename, 'w'); // Open file for writing

        // The headings first
        if (!empty($headings)) {
            fputcsv($handle, $headings, $delimiter, $enclosure);
        }
        // Then the data
        if (!empty($data)) {
            foreach ($data as $line) {
                fputcsv($handle, $line, $delimiter, $enclosure);
            }
        }

        unset($data); // Clear memory
        fclose($handle);
        return $filename;
    }
    public static function convert_to_utf8($input)
    {
        if (is_array($input)) {
            return array_map('self::convert_to_utf8', $input);
        }
        if (is_string($input)) {
            return mb_convert_encoding($input, 'UTF-8', 'UTF-8');
        }
        return $input;
    }

    public static function convert_to_utf8_and_encode($data)
    {
        $data = self::convert_to_utf8($data);
        return json_encode($data);
    }

    public static function getGenderFromString($gender)
    {
        return once(function () use ($gender) {
            return match (strtolower((string)$gender)) {
                'male', 'm' => 'M',
                'female', 'f' => 'F',
                'not_recorded', 'notrecorded', 'unreported' => 'Unreported',
                default => '',
            };
        });
    }
}
