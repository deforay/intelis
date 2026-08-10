<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\VlService;
use App\Utilities\FileCacheUtility;
use App\Utilities\MemoUtility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;

/**
 * A "Log" unit says how a result is labelled, not what an operator actually typed.
 * interpretViralLoadNumericResult() used to take that label at its word and raise ten
 * to whatever number arrived, so a copies value entered on a form configured in log
 * units was exponentiated: "< 40" produced an absolute of "< 1.0E+40" and a decimal of
 * 1e40, while `result` went on reading "< 40". The row looked correct on screen and was
 * wrong in every numeric field, which is why it went unnoticed -- the API publishes the
 * decimal, and 183 rows in one client database carried figures of that size.
 *
 * The service is built without its constructor and given a cache that answers from
 * memory, so these cases run without a database.
 */
final class VlServiceLogUnitTest extends TestCase
{
    private VlService $vlService;

    protected function setUp(): void
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
        $configCache = new class extends FileCacheUtility {
            public function __construct() {}

            public function get(string $key, callable $computeValueCallback, ?array $tags = [], int $expiration = 3600): mixed
            {
                return ['vl_interpret_and_convert_results' => 'no'];
            }
        };

        $commonService = (new ReflectionClass(CommonService::class))->newInstanceWithoutConstructor();
        $commonRef = new ReflectionClass(CommonService::class);
        $fileCacheProp = $commonRef->getProperty('fileCache');
        $fileCacheProp->setAccessible(true);
        $fileCacheProp->setValue($commonService, $configCache);

        // getGlobalConfig() reads $this->db into a local before handing the cache a
        // callback it never runs here, so the property has to exist. It is never used.
        $dbProp = $commonRef->getProperty('db');
        $dbProp->setAccessible(true);
        $dbProp->setValue($commonService, (new ReflectionClass(DatabaseService::class))->newInstanceWithoutConstructor());

        // No constructor: it would open a database connection and none is needed here.
        $this->vlService = (new ReflectionClass(VlService::class))->newInstanceWithoutConstructor();
        $this->vlService->commonService = $commonService;
    }

    /**
     * The regression. Each of these was entered as copies on a log-configured form.
     */
    public static function copiesEnteredUnderALogUnitProvider(): array
    {
        return [
            'below detection, 40 copies' => ['< 40', 'Log copies/mL', 40.0, 1.6],
            'below detection, 20 copies' => ['< 20', 'Log cp/mL', 20.0, 1.3],
            'bare copies figure'         => ['839', 'Log', 839.0, 2.92],
        ];
    }

    #[DataProvider('copiesEnteredUnderALogUnitProvider')]
    public function testCopiesEnteredUnderALogUnitAreNotExponentiated(
        string $result,
        string $unit,
        float $expectedDecimal,
        float $expectedLog
    ): void {
        $interpreted = $this->vlService->interpretViralLoadNumericResult($result, $unit);

        $this->assertEqualsWithDelta($expectedDecimal, (float) $interpreted['absDecimalVal'], 0.01);
        $this->assertEqualsWithDelta($expectedLog, (float) $interpreted['logVal'], 0.01);
        $this->assertLessThan(1.0E+9, (float) $interpreted['absDecimalVal']);
    }

    /**
     * The bound must not cost us the conversion it exists for: a figure that really is
     * a log still becomes copies.
     */
    public static function genuineLogValuesProvider(): array
    {
        return [
            'log 1.6 is 40 copies'    => ['1.6', 'Log', 40.0, 1.6],
            'log 2.36 is 229 copies'  => ['2.36', 'Log copies/mL', 229.0, 2.36],
            'log 5 is 100000 copies'  => ['5', 'Log', 100000.0, 5.0],
        ];
    }

    #[DataProvider('genuineLogValuesProvider')]
    public function testGenuineLogValuesAreStillConvertedToCopies(
        string $result,
        string $unit,
        float $expectedDecimal,
        float $expectedLog
    ): void {
        $interpreted = $this->vlService->interpretViralLoadNumericResult($result, $unit);

        $this->assertEqualsWithDelta($expectedDecimal, (float) $interpreted['absDecimalVal'], 1.0);
        $this->assertEqualsWithDelta($expectedLog, (float) $interpreted['logVal'], 0.01);
    }

    /**
     * With no unit given, a number has always been read as copies. Unchanged.
     */
    public function testResultWithNoUnitIsReadAsCopies(): void
    {
        $interpreted = $this->vlService->interpretViralLoadNumericResult('< 40', null);

        $this->assertEqualsWithDelta(40.0, (float) $interpreted['absDecimalVal'], 0.01);
        $this->assertEqualsWithDelta(1.6, (float) $interpreted['logVal'], 0.01);
    }
}
