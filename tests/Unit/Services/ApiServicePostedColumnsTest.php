<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Registries\ContainerRegistry;
use App\Services\ApiService;
use App\Utilities\FileCacheUtility;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * ApiService::postedColumns(): the fields a save endpoint took on after clients
 * were already posting to it are written only when a record carries their key.
 */
final class ApiServicePostedColumnsTest extends TestCase
{
    private const FIELDS = [
        'cvNumber' => ['cv_number'],
        'noOfPregnancyWeeks' => ['no_of_pregnancy_weeks', 'int'],
        'conservationTemperature' => ['plasma_conservation_temperature', 'float'],
        'cd4Date' => ['last_cd4_date', 'date'],
        'testRequestDate' => ['test_request_date', 'datetime'],
        'childTreatment' => ['child_treatment'],
    ];

    /**
     * Date parsing resolves a cache through MemoUtility, which reads it from the
     * container: a pass-through cache, as in PatientAgeTest.
     */
    protected function setUp(): void
    {
        $passThroughCache = new class extends FileCacheUtility {
            public function __construct()
            {
            }

            public function get(
                string $key,
                callable $computeValueCallback,
                ?array $tags = [],
                int $expiration = 3600
            ): mixed {
                return $computeValueCallback();
            }
        };

        ContainerRegistry::setContainer(new class ($passThroughCache) implements ContainerInterface {
            public function __construct(private readonly FileCacheUtility $cache)
            {
            }

            public function get(string $id): mixed
            {
                return $this->cache;
            }

            public function has(string $id): bool
            {
                return true;
            }
        });
    }

    public function testAKeyTheRecordDoesNotCarryIsNotWritten(): void
    {
        self::assertSame([], ApiService::postedColumns(['appSampleCode' => 'A-1'], self::FIELDS));
    }

    public function testAKeySentEmptyClearsItsColumn(): void
    {
        self::assertSame(
            ['cv_number' => null, 'no_of_pregnancy_weeks' => null, 'last_cd4_date' => null, 'child_treatment' => null],
            ApiService::postedColumns(
                ['cvNumber' => '  ', 'noOfPregnancyWeeks' => null, 'cd4Date' => '', 'childTreatment' => []],
                self::FIELDS
            )
        );
    }

    public function testValuesAreStoredAsTheFormStoresThem(): void
    {
        self::assertSame(
            [
                'cv_number' => 'CV-12',
                'no_of_pregnancy_weeks' => 14,
                'plasma_conservation_temperature' => 4.5,
                'last_cd4_date' => '2026-08-01',
                'test_request_date' => '2026-09-02 10:30:00',
                'child_treatment' => 'nvp,azt',
            ],
            ApiService::postedColumns([
                'cvNumber' => ' CV-12 ',
                'noOfPregnancyWeeks' => '14',
                'conservationTemperature' => '4.5',
                'cd4Date' => '01-Aug-2026',
                'testRequestDate' => '2026-09-02 10:30',
                'childTreatment' => ['nvp', 'azt'],
            ], self::FIELDS)
        );
    }

    /**
     * Under the fleet's empty sql_mode MySQL stores 'abc' in an INT column as 0 and
     * an unparseable date as a zero date, and clearing the column would lose a value
     * that was right: the saved value stands.
     */
    public function testANumberOrDateThatDoesNotParseIsNotWritten(): void
    {
        self::assertSame(
            ['cv_number' => 'CV-1'],
            ApiService::postedColumns(
                [
                    'cvNumber' => 'CV-1', 'noOfPregnancyWeeks' => 'twelve',
                    'conservationTemperature' => 'cold', 'cd4Date' => 'not a date',
                ],
                self::FIELDS
            )
        );
    }
}
