<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\VlService;
use App\Utilities\FileCacheUtility;
use App\Utilities\MemoUtility;
use Psr\Container\ContainerInterface;
use ReflectionClass;

/**
 * Builds a VlService that can interpret results without a database.
 *
 * Its result interpretation is pure arithmetic and string handling, but it reads one
 * setting through CommonService and memoises through a cache resolved from the
 * container. Both are answered from memory here, so the interpretation can be driven
 * directly in a unit test -- including with vl_interpret_and_convert_results switched
 * on, which changes what ends up in `result` and is otherwise hard to exercise.
 */
final class VlServiceFactory
{
    /**
     * @param array<string, string> $globalConfig
     */
    public static function build(array $globalConfig = []): VlService
    {
        MemoUtility::clear();

        // MemoUtility::remember() resolves a cache out of the container. Pass-through:
        // compute every time, touch no filesystem.
        $passThroughCache = new class extends FileCacheUtility {
            public function __construct() {}

            public function get(string $key, callable $computeValueCallback, ?array $tags = [], int $expiration = 3600): mixed
            {
                return $computeValueCallback();
            }
        };

        ContainerRegistry::setContainer(new class ($passThroughCache) implements ContainerInterface {
            public function __construct(private readonly FileCacheUtility $cache) {}

            public function get(string $id): mixed
            {
                return $this->cache;
            }

            public function has(string $id): bool
            {
                return true;
            }
        });

        // getGlobalConfig() reads global_config through the same cache. Answer with the
        // settings under test instead of hitting the database.
        $configCache = new class ($globalConfig) extends FileCacheUtility {
            /** @param array<string, string> $config */
            public function __construct(private readonly array $config) {}

            public function get(string $key, callable $computeValueCallback, ?array $tags = [], int $expiration = 3600): mixed
            {
                return $this->config;
            }
        };

        $commonRef = new ReflectionClass(CommonService::class);
        $commonService = $commonRef->newInstanceWithoutConstructor();

        $fileCacheProp = $commonRef->getProperty('fileCache');
        $fileCacheProp->setAccessible(true);
        $fileCacheProp->setValue($commonService, $configCache);

        // getGlobalConfig() reads $this->db into a local before handing the cache a
        // callback it never runs here, so the property has to exist. It is never used.
        $dbProp = $commonRef->getProperty('db');
        $dbProp->setAccessible(true);
        $dbProp->setValue($commonService, (new ReflectionClass(DatabaseService::class))->newInstanceWithoutConstructor());

        // No constructor: it would open a database connection and none is needed here.
        $vlService = (new ReflectionClass(VlService::class))->newInstanceWithoutConstructor();
        $vlService->commonService = $commonService;

        return $vlService;
    }
}
