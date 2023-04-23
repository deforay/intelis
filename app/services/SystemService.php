<?php

namespace App\Services;

use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;
use Whoops\Util\Misc;

class SystemService
{

    public $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? \MysqliDb::getInstance();
    }

    public function setDb($db)
    {
        $this->db = $db;
    }

    // Application Bootstrap
    public function bootstrap()
    {
        $this->setupTranslation();
        $this->setupDateTimeZone();

        return $this;
    }

    // Setup Locale
    public function setupTranslation($domain = "messages")
    {
        $general = new CommonService($this->db);
        $locale = $_SESSION['APP_LOCALE'] = $_SESSION['APP_LOCALE'] ??
            $general->getGlobalConfig('app_locale') ?? 'en_US';

        putenv('LC_ALL=' . $locale);
        putenv('LANGUAGE=' . $locale);
        setlocale(LC_ALL, $locale);
        bindtextdomain($domain, APPLICATION_PATH . DIRECTORY_SEPARATOR . 'locales');
        bind_textdomain_codeset($domain, 'UTF-8');
        textdomain($domain);
    }

    // Setup Timezone
    public function setupDateTimeZone()
    {
        $general = new CommonService($this->db);
        $_SESSION['APP_TIMEZONE'] = $_SESSION['APP_TIMEZONE'] ??
            $general->getGlobalConfig('default_time_zone') ?? 'UTC';
        date_default_timezone_set($_SESSION['APP_TIMEZONE']);
    }

    // Setup debugging
    public function debug($debugMode = false)
    {

        if ($debugMode) {

            $whoops = new Run;

            // We want the error page to be shown by default, if this is a
            // regular request, so that's the first thing to go into the stack:
            $whoops->pushHandler(new PrettyPageHandler);

            // Now, we want a second handler that will run before the error page,
            // and immediately return an error message in JSON format, if something
            // goes awry.
            if (Misc::isAjaxRequest()) {
                $jsonHandler = new JsonResponseHandler;

                // You can also tell JsonResponseHandler to give you a full stack trace:
                // $jsonHandler->addTraceToOutput(true);

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

    public static function getActiveTestModules(): array
    {
        $response = [];

        if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) {
            $response[] = 'vl';
        }

        if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) {
            $response[] = 'eid';
        }

        if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) {
            $response[] = 'covid19';
        }

        if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis'] === true) {
            $response[] = 'hepatitis';
        }

        if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] === true) {
            $response[] = 'tb';
        }

        return $response;
    }
}
