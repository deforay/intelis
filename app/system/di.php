<?php

use DI\ContainerBuilder;
use App\Services\TbService;
use App\Services\VlService;
use App\Services\ApiService;
use App\Services\EidService;
use App\Helpers\ResultsHelper;
use App\Services\UsersService;
use App\Services\GenericTestsService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\SystemService;
use App\Services\Covid19Service;
use App\Services\PatientsService;
use App\Utilities\CaptchaUtility;
use App\Services\HepatitisService;
use App\Helpers\PdfWatermarkHelper;
use App\Services\FacilitiesService;
use App\Services\InstrumentsService;
use App\Helpers\PdfConcatenateHelper;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;
use App\Utilities\ImageResizeUtility;
use Psr\Container\ContainerInterface;
use App\HttpHandlers\LegacyRequestHandler;
use App\Middlewares\Api\ApiAuthMiddleware;
use App\Middlewares\App\AppAuthMiddleware;
use Laminas\Config\Factory as ConfigFactory;
use App\ErrorHandlers\ErrorResponseGenerator;
use App\Middlewares\SystemAdminAuthMiddleware;
use App\Middlewares\Api\ApiLegacyFallbackMiddleware;

try {
    // Load configuration
    $configFile = ROOT_PATH . "/configs/config." . APPLICATION_ENV . ".php";
    if (!file_exists($configFile)) {
        $configFile = ROOT_PATH . "/configs/config.production.php";
    }

    $systemConfig = ConfigFactory::fromFile($configFile);
    $debugMode = $systemConfig['system']['debug_mode'] ?? false;
} catch (Exception $e) {
    echo "Error loading configuration file: Please ensure the config file is present";
    exit;
}

$builder = new ContainerBuilder();

// Enable compilation for better performance in production
if (!empty($systemConfig) && isset($systemConfig['cache_di']) && $systemConfig['cache_di']) {
    $builder->enableCompilation(ROOT_PATH . '/cache');
    // $builder->writeProxiesToFile(true, ROOT_PATH . '/cache');

}


// Configuration and DB
$builder->addDefinitions([
    'applicationConfig' => $systemConfig,
    'db' => DI\factory(
        function (ContainerInterface $c) {
            return new MysqliDb($c->get('applicationConfig')['database']);
        }
    )
]);

// Services
$builder->addDefinitions([
    SystemService::class => DI\create(SystemService::class)
        ->constructor(DI\get('db'), DI\get('applicationConfig')),
    CommonService::class => DI\create(CommonService::class)
        ->constructor(DI\get('db')),
    VlService::class => DI\create(VlService::class)
        ->constructor(DI\get('db')),
    EidService::class => DI\create(EidService::class)
        ->constructor(DI\get('db')),
    Covid19Service::class => DI\create(Covid19Service::class)
        ->constructor(DI\get('db')),
    HepatitisService::class => DI\create(HepatitisService::class)
        ->constructor(DI\get('db')),
    TbService::class => DI\create(TbService::class)
        ->constructor(DI\get('db')),
    UsersService::class => DI\create(UsersService::class)
        ->constructor(DI\get('db'), DI\get('applicationConfig')),
    GenericTestsService::class => DI\create(GenericTestsService::class)
        ->constructor(DI\get('db'), DI\get('applicationConfig')),
    GeoLocationsService::class => DI\create(GeoLocationsService::class)
        ->constructor(DI\get('db')),
    FacilitiesService::class => DI\create(FacilitiesService::class)
        ->constructor(DI\get('db')),
    InstrumentsService::class => DI\create(InstrumentsService::class)
        ->constructor(DI\get('db')),
    PatientsService::class => DI\create(PatientsService::class)
        ->constructor(DI\get('db')),
    ApiService::class => DI\create(ApiService::class)
        ->constructor(DI\get('db')),
]);

// Middlewares
$builder->addDefinitions([
    LegacyRequestHandler::class => DI\create(LegacyRequestHandler::class),
    AppAuthMiddleware::class => DI\create(AppAuthMiddleware::class),
    SystemAdminAuthMiddleware::class => DI\create(SystemAdminAuthMiddleware::class),
    ApiAuthMiddleware::class => DI\create(ApiAuthMiddleware::class)
        ->constructor(DI\get(UsersService::class)),
    ErrorHandlerMiddleware::class => DI\create(ErrorHandlerMiddleware::class)
        ->constructor(DI\get(ErrorResponseGenerator::class)),
    ApiLegacyFallbackMiddleware::class => DI\create(ApiLegacyFallbackMiddleware::class)
]);

// Utilities, Helpers and Other Classes
$builder->addDefinitions([
    DateUtility::class => DI\create(DateUtility::class),
    ImageResizeUtility::class => DI\create(ImageResizeUtility::class),
    CaptchaUtility::class => DI\create(CaptchaUtility::class),
    MiscUtility::class => DI\create(MiscUtility::class),
    ErrorResponseGenerator::class => DI\create(ErrorResponseGenerator::class)
        ->constructor($debugMode),
    PdfConcatenateHelper::class => DI\create(PdfConcatenateHelper::class),
    PdfWatermarkHelper::class => DI\create(PdfWatermarkHelper::class),
    ResultsHelper::class => DI\create(ResultsHelper::class),
]);


$container = $builder->build();

// Putting $container into a singleton registry for access across the application
ContainerRegistry::setContainer($container);
