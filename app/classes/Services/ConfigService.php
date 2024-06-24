<?php

namespace App\Services;

use App\Services\DatabaseService;
use App\Utilities\FileCacheUtility;

final class ConfigService
{
    protected DatabaseService $db;
    protected $fileCache;

    public function __construct(DatabaseService $db, FileCacheUtility $fileCache)
    {
        $this->db = $db;
        $this->fileCache = $fileCache;
    }

    public function updateConfig($filePath, $keyValuePairs)
    {
        // Include the current configuration file
        $systemConfig = require $filePath;

        // Update the values in the config array
        foreach ($keyValuePairs as $fullKey => $value) {
            // Split the key by dots or colons
            $keys = preg_split('/[.:]/', $fullKey);

            // Update the nested array
            $temp = &$systemConfig;
            foreach ($keys as $key) {
                if (!isset($temp[$key])) {
                    $temp[$key] = [];
                }
                $temp = &$temp[$key];
            }
            $temp = $value;
        }

        // Convert the updated config array back to a formatted string
        $formattedConfigArrayDefinition = $this->formatArrayAsPhpCode($systemConfig);

        // Write back to the file
        file_put_contents($filePath, "<?php\n\n" . $formattedConfigArrayDefinition . "\n\nreturn \$systemConfig;\n");
    }

    public function formatArrayAsPhpCode(array $array, $parentKey = '$systemConfig', $indentation = '', &$previousKey = '')
    {
        $output = '';
        foreach ($array as $key => $value) {
            $formattedKey = var_export($key, true);
            $fullKey = "{$parentKey}[{$formattedKey}]";
            $section = explode("['", $fullKey)[1] ?? '';
            $currentSection = explode("']", $section)[0] ?? '';

            if ($currentSection && $previousKey !== $currentSection) {
                if ($previousKey) {
                    $output .= "\n"; // Add a new line between different sections
                }
                $previousKey = $currentSection;
            }

            if (is_array($value)) {
                $output .= $this->formatArrayAsPhpCode($value, $fullKey, $indentation, $previousKey);
            } else {
                $formattedValue = var_export($value, true);
                $output .= "{$indentation}{$fullKey} = {$formattedValue};\n";
            }
        }
        return $output;
    }
}
