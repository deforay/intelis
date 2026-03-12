<?php

namespace App\Factories;

use Throwable;
use App\Services\DatabaseService;
use App\Utilities\LoggerUtility;
use Psr\Container\ContainerInterface;

class DatabaseFactory
{
    public function __invoke(ContainerInterface $c): DatabaseService
    {
        $dbConfig = $c->get('applicationConfig')['database'] ?? [];

        try {
            return new DatabaseService($dbConfig);
        } catch (Throwable $e) {
            LoggerUtility::fatalError('Database Connection Failed', $e);
        }
    }
}
