<?php

namespace App\Services;

use Whoops\Run;
use Whoops\Util\Misc;
use App\Services\CommonService;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Handler\JsonResponseHandler;

class SystemService
{
    protected array $applicationConfig = [];
    protected ?CommonService $commonService;

    public function __construct(
        ?array $applicationConfig,
        CommonService $commonService
    ) {
        $this->applicationConfig = $applicationConfig ?? SYSTEM_CONFIG;
        $this->commonService = $commonService;
    }

    // Application Bootstrap
    public function bootstrap(): SystemService
    {
        $this->setupTranslation();
        $this->setupDateTimeZone();

        return $this;
    }

    // Setup Translation
    public function setupTranslation($domain = "messages"): void
    {
        $_SESSION['APP_LOCALE'] = $_SESSION['userLocale'] ?? $_SESSION['APP_LOCALE'] ?? $this->commonService->getGlobalConfig('app_locale') ?? 'en_US';
        setlocale(LC_ALL, $_SESSION['APP_LOCALE']);
        bindtextdomain($domain, APPLICATION_PATH . DIRECTORY_SEPARATOR . 'locales');
        bind_textdomain_codeset($domain, 'UTF-8');
        textdomain($domain);
    }

    // Setup Timezone
    public function setupDateTimeZone(): void
    {
        $_SESSION['APP_TIMEZONE'] = $_SESSION['APP_TIMEZONE'] ??
            $this->commonService->getGlobalConfig('default_time_zone') ?? 'UTC';
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

    public function getActiveTestModules(): array
    {
        $response = [];

        $modules = ['vl', 'eid', 'covid19', 'hepatitis', 'tb', 'genericTests'];

        foreach ($modules as $module) {
            if (
                isset($this->applicationConfig['modules'][$module]) &&
                $this->applicationConfig['modules'][$module] === true
            ) {
                $response[] = $module;
            }
        }

        return $response;
    }
}
