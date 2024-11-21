<?php

namespace App\Services;

use Whoops\Run;
use Whoops\Util\Misc;
use FilesystemIterator;
use Gettext\Loader\MoLoader;
use RecursiveIteratorIterator;
use App\Services\CommonService;
use RecursiveDirectoryIterator;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Handler\JsonResponseHandler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;


final class SystemService
{
    protected CommonService $commonService;

    public function __construct(CommonService $commonService)
    {
        $this->commonService = $commonService;
    }

    // Application Bootstrap
    public function bootstrap(): SystemService
    {
        $this->setLocale();
        $this->setDateTimeZone();

        return $this;
    }

    // Setup Translation
    public function setLocale($locale = null, $domain = "messages"): void
    {
        // Set the default locale
        $defaultLocale = 'en_US';

        // Determine the locale to use
        $_SESSION['APP_LOCALE'] = $locale ?? $_SESSION['userLocale'] ?? $_SESSION['APP_LOCALE'] ?? $this->commonService->getGlobalConfig('app_locale') ?? $defaultLocale;


        // Construct the path to the .mo file
        $moFilePath = sprintf(
            '%s%slocales%s%s%sLC_MESSAGES%s%s.mo',
            APPLICATION_PATH,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
            $_SESSION['APP_LOCALE'],
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
            $domain
        );

        // Initialize translations to null
        $_SESSION['translations'] = null;

        // Load translations if the locale is not the default and the .mo file exists
        if ($_SESSION['APP_LOCALE'] !== $defaultLocale && file_exists($moFilePath)) {
            $loader = new MoLoader();
            $translations = $loader->loadFile($moFilePath);

            // Store translations in the session
            $_SESSION['translations'] = $translations;
        }
    }

    public static function translate(?string $text)
    {
        if (empty($text) || empty($_SESSION['translations']) || empty($_SESSION['translations']->find(null, $text))) {
            return $text;
        } else {
            return $_SESSION['translations']->find(null, $text)->getTranslation();
        }
    }

    public function getDateFormat($category = null, $inputFormat = null)
    {
        $dateFormat = $inputFormat ?? $this->commonService->getGlobalConfig('gui_date_format') ?? 'd-M-Y';

        $dateFormatArray = ['phpDateFormat' => $dateFormat];

        if ($dateFormat == 'd-m-Y') {
            $dateFormatArray['jsDateFieldFormat'] = 'dd-mm-yy';
            $dateFormatArray['dayjsDateFieldFormat'] = 'DD-MM-YYYY';
            $dateFormatArray['jsDateRangeFormat'] = 'DD-MM-YYYY';
            $dateFormatArray['jsDateFormatMask'] = '99-99-9999';
            $dateFormatArray['mysqlDateFormat'] = '%d-%m-%Y';
        } else {
            $dateFormatArray['jsDateFieldFormat'] = 'dd-M-yy';
            $dateFormatArray['dayjsDateFieldFormat'] = 'DD-MMM-YYYY';
            $dateFormatArray['jsDateRangeFormat'] = 'DD-MMM-YYYY';
            $dateFormatArray['jsDateFormatMask'] = '99-aaa-9999';
            $dateFormatArray['mysqlDateFormat'] = '%d-%b-%Y';
        }

        if (empty($category)) {
            // Return all date formats
            return $dateFormatArray;
        } elseif ($category == 'php') {
            return $dateFormatArray['phpDateFormat'] ?? 'd-m-Y';
        } elseif ($category == 'js') {
            return $dateFormatArray['jsDateFieldFormat'] ?? 'dd-mm-yy';
        } elseif ($category == 'dayjs') {
            return $dateFormatArray['dayjsDateFieldFormat'] ?? 'DD-MM-YYYY';
        } elseif ($category == 'jsDateRange') {
            return $dateFormatArray['jsDateRangeFormat'] ?? 'DD-MM-YYYY';
        } elseif ($category == 'jsMask') {
            return $dateFormatArray['jsDateFormatMask'] ?? '99-99-9999';
        } elseif ($category == 'mysql') {
            return $dateFormatArray['mysqlDateFormat'] ?? '%d-%b-%Y';
        } else {
            return null;
        }
    }

    public function setGlobalDateFormat($inputFormat = null)
    {
        $dateFormatArray = $this->getDateFormat(null, $inputFormat);
        foreach ($dateFormatArray as $key => $value) {
            $_SESSION[$key] = $value;
        }
    }

    // Setup Timezone
    public function setDateTimeZone(): void
    {
        $this->setGlobalDateFormat();

        $_SESSION['APP_TIMEZONE'] = $_SESSION['APP_TIMEZONE'] ?? $this->getTimezone();
        date_default_timezone_set($_SESSION['APP_TIMEZONE']);
    }

    public function getTimezone(): string
    {
        return  $this->commonService->getGlobalConfig('default_time_zone') ?? 'UTC';
    }

    // Setup debugging
    public function debug($debugMode = false): SystemService
    {

        if ($debugMode) {

            error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
            $whoops = new Run;

            // We want the error page to be shown by default, if this is a
            // regular request, so that's the first thing to go into the stack:
            $whoops->pushHandler(new PrettyPageHandler);

            // Now, we want a second handler that will run before the error page,
            // and immediately return an error message in JSON format, if something
            // goes awry.
            if (Misc::isAjaxRequest()) {
                $jsonHandler = new JsonResponseHandler;

                // Setup JsonResponseHandler to give a full stack trace:
                $jsonHandler->addTraceToOutput(true);

                // Return a result compliant to the json:api spec
                // re: http://jsonapi.org/examples/#error-objects
                // tl;dr: error[] becomes errors[[]]
                $jsonHandler->setJsonApi(true);
                $whoops->pushHandler($jsonHandler);
            }
            $whoops->register();
        }
        return $this;
    }

    public static function getActiveModules(bool $onlyTests = false): array
    {
        $activeModules = [];

        if ($onlyTests === false) {
            $activeModules = ['admin', 'dashboard', 'common'];
        }
        return array_merge($activeModules, array_keys(array_filter(SYSTEM_CONFIG['modules'])));
    }

    public function getServerSettings(): array
    {
        return [
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time'),
            'max_input_time' => ini_get('max_input_time'),
            'display_errors' => ini_get('display_errors'),
            'error_reporting' => ini_get('error_reporting'),
        ];
    }
    public function checkFolderPermissions(): array
    {
        // Define folder paths
        $folders = [
            'CACHE_PATH' => CACHE_PATH,
            'UPLOAD_PATH' => UPLOAD_PATH,
            'TEMP_PATH' => TEMP_PATH,
            'LOGS_PATH' => ROOT_PATH . DIRECTORY_SEPARATOR . 'logs'
        ];

        $folderPermissions = [];

        foreach ($folders as $folderName => $folderPath) {
            $folderPermissions[$folderName] = [
                'exists' => is_dir($folderPath),
                'readable' => is_readable($folderPath),
                'writable' => is_writable($folderPath)
            ];
        }

        return $folderPermissions;
    }
}
