<?php

// app/system/di.php
use function DI\factory;
use function DI\get;
use function DI\create;
use DI\ContainerBuilder;
use App\Factories\DatabaseFactory;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\ErrorHandlers\ErrorResponseGenerator;

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

    if (extension_loaded('apcu')) {
        $builder->enableDefinitionCache($systemConfig['instance-name'] ?? '');
    }
}

// Configuration and DB
$builder->addDefinitions([
    'applicationConfig' => $systemConfig,
    'db' => factory(new DatabaseFactory()),
    DatabaseService::class => get('db')
]);

// Services
// Since useAutowiring(true) is enabled, we don't need to list every service here
// unless it needs specific configuration.
$builder->addDefinitions([
    // Add any services that need custom configuration here
]);

// Utilities, Helpers and Other Classes
$builder->addDefinitions([
    ErrorResponseGenerator::class => create(ErrorResponseGenerator::class)
        ->constructor($debugMode),
]);


$container = $builder->build();

// Putting $container into a singleton registry for access across the application
ContainerRegistry::setContainer($container);
