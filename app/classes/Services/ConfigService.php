<?php

namespace App\Services;

use App\Utilities\FileCacheUtility;

final class ConfigService
{
    protected $fileCache;

    public function __construct(FileCacheUtility $fileCache)
    {
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

    private function formatArrayAsPhpCode(array $array, $indentation = '')
    {
        $output = "[\n";
        $indentation .= '    ';
        foreach ($array as $key => $value) {
            $formattedKey = var_export($key, true);
            if (is_array($value)) {
                $formattedValue = $this->formatArrayAsPhpCode($value, $indentation);
            } else {
                $formattedValue = var_export($value, true);
            }
            $output .= "{$indentation}{$formattedKey} => {$formattedValue},\n";
        }
        $indentation = substr($indentation, 0, -4);
        $output .= "{$indentation}]";

        return "\$systemConfig = " . $output . ";";
    }
}
