<?php

use DI\ContainerBuilder;
use App\Services\TbService;
use App\Services\VlService;
use App\Services\ApiService;
use App\Services\EidService;
use App\Services\UsersService;
use App\Services\CommonService;
use App\Services\SystemService;
use App\Services\Covid19Service;
use App\Services\PatientsService;
use App\Services\HepatitisService;
use App\Services\FacilitiesService;
use App\Services\InstrumentsService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;
use Laminas\Config\Factory as ConfigFactory;
use Psr\Container\ContainerInterface;

try {
    // Load configuration
    $configFile = ROOT_PATH . "/configs/config." . APPLICATION_ENV . ".php";
    if (!file_exists($configFile)) {
        $configFile = ROOT_PATH . "/configs/config.production.php";
    }

    $systemConfig = ConfigFactory::fromFile($configFile);
} catch (\Exception $e) {
    echo "Error loading configuration file: Please ensure the config file is present";
    exit;
}

$builder = new ContainerBuilder();

// Enable compilation for better performance in production
if (!empty(APPLICATION_ENV) && APPLICATION_ENV === 'production') {
    $builder->enableCompilation(ROOT_PATH . '/cache');
    // $builder->writeProxiesToFile(true, ROOT_PATH . '/cache');

}


$builder->addDefinitions([
    'applicationConfig' => $systemConfig,
    'db' => DI\factory(
        function (ContainerInterface $c) {
            return new MysqliDb($c->get('applicationConfig')['database']);
        }
    ),
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

$container = $builder->build();

// Putting $container into a singleton registry for access across the application
ContainerRegistry::setContainer($container);
