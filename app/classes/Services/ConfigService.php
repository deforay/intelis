<?php

namespace App\Services;

use App\Utilities\FileCacheUtility;
use Laminas\Config\Factory as ConfigFactory;
use Laminas\Config\Config as LaminasConfig;

final class ConfigService
{
    protected $fileCache;

    public function __construct(FileCacheUtility $fileCache)
    {
        $this->fileCache = $fileCache;
    }

    public function getConfigFile()
    {
        $configFile = ROOT_PATH . "/configs/config." . APPLICATION_ENV . ".php";
        if (!file_exists($configFile)) {
            $configFile = ROOT_PATH . "/configs/config.production.php";
        }

        return $configFile;
    }

    public function updateConfig($keyValuePairs)
    {
        $filePath = $this->getConfigFile();
        // Parse the current configuration file using Laminas\Config\Factory
        $config = new LaminasConfig(ConfigFactory::fromFile($filePath, true)->toArray(), true);

        // Update the values in the config array
        foreach ($keyValuePairs as $fullKey => $value) {
            // Split the key by dots or colons
            $keys = preg_split('/[.:]/', $fullKey);

            // Update the nested array
            $temp = &$config;
            $lastKey = array_pop($keys);
            foreach ($keys as $key) {
                if (!isset($temp[$key])) {
                    $temp[$key] = new LaminasConfig([], true);
                }
                $temp = &$temp[$key];
            }
            $temp[$lastKey] = $value;
        }

        // Write back the updated config using custom formatted output
        $this->writeFormattedConfig($filePath, $config->toArray());
        $this->fileCache->clear();
    }

    private function writeFormattedConfig($filePath, array $config)
    {
        $formattedConfigArrayDefinition = $this->formatArrayAsPhpCode($config);
        file_put_contents($filePath, "<?php\n\n" . $formattedConfigArrayDefinition . "\n\nreturn \$systemConfig;\n");
    }

    private function formatArrayAsPhpCode(array $array, $parentKey = '$systemConfig', $indentation = '', &$previousKey = '')
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
