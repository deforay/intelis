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
            // The health probe (public/api/index.php) wants the failure back so it
            // can answer 503 as JSON; fatalError() would print an HTML page and exit
            // before that catch ever runs.
            if (defined('INTELIS_HEALTH_PROBE') && INTELIS_HEALTH_PROBE === true) {
                throw $e;
            }
            LoggerUtility::fatalError('Database Connection Failed', $e);
        }
    }
}
