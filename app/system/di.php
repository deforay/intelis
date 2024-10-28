<?php

use DI\ContainerBuilder;
use App\Services\TbService;
use App\Services\VlService;
use App\Services\ApiService;
use App\Services\CD4Service;
use App\Services\EidService;
use App\Services\BatchService;
use App\Services\TestsService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Helpers\BatchPdfHelper;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\ConfigService;
use App\Services\SystemService;
use App\Services\AppMenuService;
use App\Services\Covid19Service;
use App\Services\StorageService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\PatientsService;
use App\Utilities\CaptchaUtility;
use App\Services\HepatitisService;
use App\Services\ResultPdfService;
use App\Exceptions\SystemException;
use App\Helpers\PdfWatermarkHelper;
use App\Services\FacilitiesService;
use App\Utilities\FileCacheUtility;
use App\Services\InstrumentsService;
use App\Services\TestResultsService;
use App\Utilities\ValidationUtility;
use App\Helpers\PdfConcatenateHelper;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;
use App\Services\GeoLocationsService;
use App\Services\TestRequestsService;
use Psr\Container\ContainerInterface;
use App\Middlewares\App\AclMiddleware;
use App\Middlewares\App\CSRFMiddleware;
use App\HttpHandlers\LegacyRequestHandler;
use App\Middlewares\Api\ApiAuthMiddleware;
use App\Middlewares\App\AppAuthMiddleware;
use App\Middlewares\ErrorHandlerMiddleware;
use Laminas\Config\Factory as ConfigFactory;
use App\ErrorHandlers\ErrorResponseGenerator;
use App\Middlewares\SystemAdminAuthMiddleware;
use App\Middlewares\Api\ApiErrorHandlingMiddleware;
use App\Middlewares\Api\ApiLegacyFallbackMiddleware;
use App\Services\STS\TokensService as STSTokensService;
use App\Services\STS\ResultsService as STSResultsService;
use App\Services\STS\RequestsService as STSRequestsService;

try {
    // Load configuration
    $configFile = ROOT_PATH . "/configs/config." . APPLICATION_ENV . ".php";
    if (!file_exists($configFile)) {
        $configFile = ROOT_PATH . "/configs/config.production.php";
    }

    $systemConfig = ConfigFactory::fromFile($configFile);

    // Detect if debug mode is enabled
    $debugMode = $systemConfig['system']['debug_mode'] ?? false;

    // Detect if script is running in CLI mode
    $isCli = php_sapi_name() === 'cli';
} catch (Exception $e) {
    throw new SystemException("Error loading configuration file: Please ensure the config file is present");
}

$builder = new ContainerBuilder();
$builder->useAutowiring(true);

// Enable compilation for better performance in production
if (!$isCli && !empty($systemConfig['system']['cache_di']) && true === $systemConfig['system']['cache_di']) {

    if (!is_dir(CACHE_PATH)) {
        mkdir(CACHE_PATH, 0777, true);
    }
    $builder->enableCompilation(CACHE_PATH);
    $builder->enableDefinitionCache(CACHE_PATH);
}

// Configuration and DB
$builder->addDefinitions([
    'applicationConfig' => $systemConfig,
    'db' => DI\factory(
        function (ContainerInterface $c) {
            return new DatabaseService($c->get('applicationConfig')['database']);
        }
    ),
    DatabaseService::class => DI\get('db')
]);

// Services
$builder->addDefinitions([
    CommonService::class  => DI\autowire(),
    ConfigService::class  => DI\autowire(),
    SystemService::class  => DI\autowire(),
    ResultPdfService::class  => DI\autowire(),
    BatchService::class  => DI\autowire(),
    VlService::class => DI\autowire(),
    CD4Service::class => DI\autowire(),
    EidService::class =>  DI\autowire(),
    Covid19Service::class => DI\autowire(),
    HepatitisService::class => DI\autowire(),
    TbService::class => DI\autowire(),
    GenericTestsService::class => DI\autowire(),
    UsersService::class => DI\autowire(),
    GeoLocationsService::class => DI\autowire(),
    TestResultsService::class => DI\autowire(),
    AppMenuService::class => DI\autowire(),
    FacilitiesService::class => DI\autowire(),
    InstrumentsService::class => DI\autowire(),
    PatientsService::class => DI\autowire(),
    ApiService::class => DI\autowire(),
    TestsService::class => DI\autowire(),
    StorageService::class => DI\autowire(),
    TestRequestsService::class => DI\autowire(),
    STSRequestsService::class => DI\autowire(),
    STSResultsService::class => DI\autowire(),
    STSTokensService::class => DI\autowire(),
]);

// Middlewares
$builder->addDefinitions([
    LegacyRequestHandler::class => DI\autowire(),
    AppAuthMiddleware::class => DI\autowire(),
    SystemAdminAuthMiddleware::class => DI\autowire(),
    ApiAuthMiddleware::class => DI\autowire(),
    AclMiddleware::class => DI\autowire(),
    CSRFMiddleware::class => DI\autowire(),
    ErrorHandlerMiddleware::class => DI\autowire(),
    ApiErrorHandlingMiddleware::class => DI\autowire(),
    ApiLegacyFallbackMiddleware::class => DI\autowire(),
]);

// Utilities, Helpers and Other Classes
$builder->addDefinitions([
    DateUtility::class => DI\create(DateUtility::class),
    CaptchaUtility::class => DI\create(CaptchaUtility::class),
    FileCacheUtility::class => DI\create(FileCacheUtility::class),
    MiscUtility::class => DI\create(MiscUtility::class),
    LoggerUtility::class => DI\create(LoggerUtility::class),
    ValidationUtility::class => DI\create(ValidationUtility::class),
    ErrorResponseGenerator::class => DI\create(ErrorResponseGenerator::class)
        ->constructor($debugMode),
    PdfConcatenateHelper::class => DI\create(PdfConcatenateHelper::class),
    PdfWatermarkHelper::class => DI\create(PdfWatermarkHelper::class),
    BatchPdfHelper::class => DI\create(BatchPdfHelper::class),
    AppRegistry::class => DI\create(AppRegistry::class),
]);


$container = $builder->build();

// Putting $container into a singleton registry for access across the application
ContainerRegistry::setContainer($container);
