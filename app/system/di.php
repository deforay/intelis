<?php

use App\Registries\ContainerRegistry;
use DI\ContainerBuilder;
use App\Services\SystemService;
use App\Services\CommonService;
use App\Services\Covid19Service;
use App\Services\VlService;
use App\Services\EidService;
use App\Services\FacilitiesService;
use App\Services\GeoLocationsService;
use App\Services\HepatitisService;
use App\Services\InstrumentsService;
use App\Services\PatientsService;
use App\Services\TbService;
use App\Services\UserService;

$builder = new ContainerBuilder();

// Enable compilation for better performance in production
if (!empty(APPLICATION_ENV) && APPLICATION_ENV === 'production') {
    $builder->enableCompilation(ROOT_PATH . '/cache');
    $builder->writeProxiesToFile(true, ROOT_PATH . '/cache');

}

$builder->addDefinitions([
    'db' => \MysqliDb::getInstance(),
    SystemService::class => DI\create(SystemService::class)
        ->constructor(DI\get('db')),
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
    UserService::class => DI\create(UserService::class)
        ->constructor(DI\get('db')),
    GeoLocationsService::class => DI\create(GeoLocationsService::class)
        ->constructor(DI\get('db')),
    FacilitiesService::class => DI\create(FacilitiesService::class)
        ->constructor(DI\get('db')),
    SystemService::class => DI\create(SystemService::class)
        ->constructor(DI\get('db')),
    InstrumentsService::class => DI\create(InstrumentsService::class)
        ->constructor(DI\get('db')),
    PatientsService::class => DI\create(PatientsService::class)
        ->constructor(DI\get('db')),
]);

$container = $builder->build();

ContainerRegistry::setContainer($container);
