<?php

namespace App\Services;

use App\Utilities\MiscUtility;
use App\Utilities\FileCacheUtility;

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

    public function getConfig(): array
    {
        return include $this->getConfigFile();
    }

    public function updateConfig(array $keyValuePairs)
    {
        $filePath = $this->getConfigFile();
        $config = $this->getConfig();

        // Update nested values
        foreach ($keyValuePairs as $fullKey => $value) {
            $keys = preg_split('/[.:]/', $fullKey);
            $lastKey = array_pop($keys);
            $temp = &$config;

            foreach ($keys as $key) {
                if (!isset($temp[$key]) || !is_array($temp[$key])) {
                    $temp[$key] = [];
                }
                $temp = &$temp[$key];
            }

            $temp[$lastKey] = $value;
            unset($temp);
        }

        $this->writeFormattedConfig($filePath, $config);
        $this->fileCache->clear();
    }

    private function writeFormattedConfig(string $filePath, array $config)
    {
        $formattedConfigArrayDefinition = $this->formatArrayAsPhpCode($config);
        file_put_contents($filePath, "<?php\n\n\$systemConfig = [];\n\n" . $formattedConfigArrayDefinition . "\n\nreturn \$systemConfig;\n");
    }

    private function formatArrayAsPhpCode(array $array, string $parentKey = '$systemConfig'): string
    {
        $sections = [];
        $currentSection = '';
        $lines = [];

        $this->buildArrayLines($array, $parentKey, $lines, $currentSection, $sections);

        // Combine all sections with proper spacing
        $result = '';
        foreach ($sections as $section => $sectionLines) {
            $result .= implode("\n", $sectionLines) . "\n\n";
        }

        return rtrim($result);
    }

    private function buildArrayLines(array $array, string $currentKey, array &$lines, string &$currentSection, array &$sections)
    {
        foreach ($array as $key => $value) {
            $formattedKey = var_export($key, true);
            $newKey = "{$currentKey}[{$formattedKey}]";

            // Determine section from the top-level key
            $topLevelSection = $this->getTopLevelSection($newKey);

            // If section changed, start a new section
            if ($topLevelSection !== $currentSection) {
                if (!empty($lines) && !empty($currentSection)) {
                    $sections[$currentSection] = $lines;
                }
                $currentSection = $topLevelSection;
                $lines = [];
            }

            if (is_array($value)) {
                $this->buildArrayLines($value, $newKey, $lines, $currentSection, $sections);
            } else {
                $formattedValue = var_export($value, true);
                $lines[] = "{$newKey} = {$formattedValue};";
            }
        }

        // Add the last section
        if (!empty($lines) && !empty($currentSection)) {
            $sections[$currentSection] = $lines;
        }
    }

    private function getTopLevelSection(string $key): string
    {
        // Extract the top-level key from the full key path
        if (preg_match('/\$systemConfig\[(\'|")([^\'"]*)/', $key, $matches)) {
            return $matches[2];
        }
        return '';
    }

    public static function generateAPIKeyForSTS($domain = null)
    {
        if (empty($domain)) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $domain = $protocol . $_SERVER['HTTP_HOST'];
        }

        // Remove any trailing slashes
        $domain = rtrim($domain, '/');
        MiscUtility::dumpToErrorLog("Generating API key for domain: $domain");
        return MiscUtility::generateUUIDv5($domain);
    }
}
