<?php

namespace App\Services;

use MysqliDb;
use Whoops\Run;
use Whoops\Util\Misc;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Handler\JsonResponseHandler;

class SystemService
{

    protected ?MysqliDb $db = null;
    protected $applicationConfig = null;

    public function __construct($db = null, $applicationConfig = null)
    {
        $this->db = $db ?? ContainerRegistry::get('db');
        $this->applicationConfig = $applicationConfig;
    }

    public function setDb($db)
    {
        $this->db = $db;
    }

    // Application Bootstrap
    public function bootstrap(): SystemService
    {
        $this->setupTranslation();
        $this->setupDateTimeZone();

        return $this;
    }

    // Setup Translation
    public function setupTranslation($domain = "messages")
    {
        /** @var CommonService $general */
        $general = ContainerRegistry::get(\App\Services\CommonService::class);

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
        /** @var CommonService $general */
        $general = ContainerRegistry::get(\App\Services\CommonService::class);

        $_SESSION['APP_TIMEZONE'] = $_SESSION['APP_TIMEZONE'] ??
            $general->getGlobalConfig('default_time_zone') ?? 'UTC';
        date_default_timezone_set($_SESSION['APP_TIMEZONE']);
    }

    // Setup debugging
    public function debug($debugMode = false): SystemService
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

    public function getActiveTestModules(): array
    {
        $response = [];

        if (isset($this->applicationConfig['modules']['vl']) && $this->applicationConfig['modules']['vl'] === true) {
            $response[] = 'vl';
        }

        if (isset($this->applicationConfig['modules']['eid']) && $this->applicationConfig['modules']['eid'] === true) {
            $response[] = 'eid';
        }

        if (isset($this->applicationConfig['modules']['covid19']) && $this->applicationConfig['modules']['covid19'] === true) {
            $response[] = 'covid19';
        }

        if (isset($this->applicationConfig['modules']['hepatitis']) && $this->applicationConfig['modules']['hepatitis'] === true) {
            $response[] = 'hepatitis';
        }

        if (isset($this->applicationConfig['modules']['tb']) && $this->applicationConfig['modules']['tb'] === true) {
            $response[] = 'tb';
        }

        if (isset($this->applicationConfig['modules']['genericTests']) && $this->applicationConfig['modules']['genericTests'] === true) {
            $response[] = 'genericTests';
        }

        return $response;
    }
}
