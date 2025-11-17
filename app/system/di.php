<?php

// app/system/di.php
use function DI\factory;
use function DI\get;
use function DI\autowire;
use function DI\create;
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
use App\Utilities\MemoUtility;
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
use App\Services\AuditArchiveService;
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

    // Load configuration directly using PHP's include
    $systemConfig = include $configFile;

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
    'db' => factory(function (ContainerInterface $c): DatabaseService {
        $dbConfig = $c->get('applicationConfig')['database'] ?? [];

        try {
            return new DatabaseService($dbConfig);
        } catch (Throwable $e) {

            // Always log the full message for debugging
            error_log('[DB INIT ERROR] ' . $e->getMessage());

            // Detect CLI vs Web early
            $isCli = (php_sapi_name() === 'cli');

            if ($isCli) {
                fwrite(STDERR, "❌ Database connection failed: {$e->getMessage()}\n");
                exit(1);
            }

            // For web, send a clean 500 response before app stack loads
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');

            echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>System Error</title>
<style>
body {
  font-family: system-ui, sans-serif;
  background: #fafafa;
  color: #333;
  margin: 4rem;
}
h1 { color: #c00; }
p  { max-width: 600px; }
</style>
</head>
<body>
<h1>Database Connection Failed</h1>
<p>The application could not connect to its database. Please check your configuration file or database server.</p>
</body>
</html>
HTML;

            exit;
        }
    }),
    DatabaseService::class => get('db')
]);

// Services
$builder->addDefinitions([
    CommonService::class  => autowire(),
    ConfigService::class  => autowire(),
    SystemService::class  => autowire(),
    ResultPdfService::class  => autowire(),
    BatchService::class  => autowire(),
    VlService::class => autowire(),
    CD4Service::class => autowire(),
    EidService::class =>  autowire(),
    Covid19Service::class => autowire(),
    HepatitisService::class => autowire(),
    TbService::class => autowire(),
    GenericTestsService::class => autowire(),
    UsersService::class => autowire(),
    GeoLocationsService::class => autowire(),
    TestResultsService::class => autowire(),
    AuditArchiveService::class => autowire(),
    AppMenuService::class => autowire(),
    FacilitiesService::class => autowire(),
    InstrumentsService::class => autowire(),
    PatientsService::class => autowire(),
    ApiService::class => autowire(),
    TestsService::class => autowire(),
    StorageService::class => autowire(),
    TestRequestsService::class => autowire(),
    STSRequestsService::class => autowire(),
    STSResultsService::class => autowire(),
    STSTokensService::class => autowire(),
]);

// Middlewares
$builder->addDefinitions([
    LegacyRequestHandler::class => autowire(),
    AppAuthMiddleware::class => autowire(),
    SystemAdminAuthMiddleware::class => autowire(),
    ApiAuthMiddleware::class => autowire(),
    AclMiddleware::class => autowire(),
    CSRFMiddleware::class => autowire(),
    ErrorHandlerMiddleware::class => autowire(),
    ApiErrorHandlingMiddleware::class => autowire(),
    ApiLegacyFallbackMiddleware::class => autowire(),
]);

// Utilities, Helpers and Other Classes
$builder->addDefinitions([
    DateUtility::class => create(DateUtility::class),
    CaptchaUtility::class => create(CaptchaUtility::class),
    FileCacheUtility::class => create(FileCacheUtility::class),
    MiscUtility::class => create(MiscUtility::class),
    MemoUtility::class => create(MemoUtility::class),
    LoggerUtility::class => create(LoggerUtility::class),
    ValidationUtility::class => create(ValidationUtility::class),
    ErrorResponseGenerator::class => create(ErrorResponseGenerator::class)
        ->constructor($debugMode),
    PdfConcatenateHelper::class => create(PdfConcatenateHelper::class),
    PdfWatermarkHelper::class => create(PdfWatermarkHelper::class),
    BatchPdfHelper::class => create(BatchPdfHelper::class),
    AppRegistry::class => create(AppRegistry::class),
]);


$container = $builder->build();

// Putting $container into a singleton registry for access across the application
ContainerRegistry::setContainer($container);
