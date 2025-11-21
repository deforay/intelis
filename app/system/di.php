<?php

// app/system/di.php
use function DI\factory;
use function DI\get;
use function DI\autowire;
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

// Auto-register all classes in app/classes to ensure they are compiled
$classesDir = APPLICATION_PATH . '/classes';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($classesDir));
$regex = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

// Classes to skip during auto-registration
$excludeClasses = [
    'App\\Interop\\Dhis2',
    'App\\Interop\\Fhir',
    'App\\Utilities\\ImageResizeUtility',
    // Add other classes to skip here
];

foreach ($regex as $file) {
    $filePath = $file[0];

    // Convert file path to namespace
    $relativePath = str_replace($classesDir . '/', '', $filePath);
    $className = 'App\\' . str_replace(['/', '.php'], ['\\', ''], $relativePath);

    if (class_exists($className)) {
        // Skip if in exclusion list
        if (in_array($className, $excludeClasses)) {
            continue;
        }

        // Only register instantiable classes (skips Interfaces, Traits, Abstract classes)
        $reflection = new ReflectionClass($className);

        if ($reflection->isInstantiable()) {
            $builder->addDefinitions([$className => autowire()]);
        }
    }
}

// Configuration and DB
$builder->addDefinitions([
    'applicationConfig' => $systemConfig,
    'db' => factory(DatabaseFactory::class),
    DatabaseService::class => get('db')
]);

// Services
$builder->addDefinitions([
    // If you need to manually wire a service, add it here.
    // The manual definition will automatically override the auto-registered one.
]);

// Utilities, Helpers and Other Classes
$builder->addDefinitions([
    ErrorResponseGenerator::class => create(ErrorResponseGenerator::class)
        ->constructor($debugMode),
]);


$container = $builder->build();

// Putting $container into a singleton registry for access across the application
ContainerRegistry::setContainer($container);
