<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Utilities\FileCacheUtility;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * getOwnLabId() is the single chokepoint for "which lab am I?", so every request
 * and result helper writes lab_id from whatever it returns.
 *
 * It used to fall back to the install-wide sc_testing_lab_id whenever the session
 * carried no lab. That is right for a single-lab LIS install, but cloud-LIS is many
 * labs sharing ONE STS install: there the fallback handed an unassigned operator the
 * STS's own setup lab, and resolveRequestLabId() then stamped that invented lab onto
 * the requests they edited -- and, once stored, its keep-the-saved-lab branch made the
 * value permanent, so the Testing Lab could never be corrected through the form again.
 *
 * loginProcess.php already draws the correct line (fallback for LIS/standalone only,
 * null for an unmapped cloud-LIS user). These tests hold the two in agreement. An
 * unmapped cloud-LIS operator is an all-labs user: null here means "do not scope",
 * which is what labScopeWhere() and resolveRequestLabId() both expect.
 */
final class CommonServiceOwnLabIdTest extends TestCase
{
    public const INSTALL_LAB = '42';

    private array $originalSession;

    protected function setUp(): void
    {
        $this->originalSession = $_SESSION ?? [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->originalSession;
    }

    /**
     * CommonService is final, so it is built for real. Its constructor only assigns
     * promoted properties, and getOwnLabId() touches neither the database nor the
     * facilities service -- those are handed in unconstructed purely to satisfy the
     * type hints. Only the cache is live, pre-answering the one system_config read
     * so getSystemConfig() resolves without a database. The instance-type helpers
     * (isLISInstance/isCloudLISMode/treatAsLIS) run for real off $_SESSION.
     */
    private function service(string $instanceType, ?string $accessType, ?int $sessionLabId): CommonService
    {
        $_SESSION = [
            'instance' => ['type' => $instanceType],
            'accessType' => $accessType,
            'labId' => $sessionLabId,
        ];

        $cache = new class extends FileCacheUtility {
            public function __construct()
            {
                // Deliberately skips parent::__construct(): it builds filesystem
                // adapters this stub never reads.
            }

            public function get(
                string $key,
                callable $computeValueCallback,
                ?array $tags = [],
                int $expiration = 3600
            ): mixed {
                return ['sc_testing_lab_id' => CommonServiceOwnLabIdTest::INSTALL_LAB];
            }
        };

        return new CommonService(
            (new ReflectionClass(DatabaseService::class))->newInstanceWithoutConstructor(),
            (new ReflectionClass(FacilitiesService::class))->newInstanceWithoutConstructor(),
            $cache
        );
    }

    public function testLisInstanceFallsBackToInstallLabWhenSessionHasNone(): void
    {
        self::assertSame(42, $this->service('lismode', null, null)->getOwnLabId());
    }

    public function testLisInstancePrefersSessionLab(): void
    {
        self::assertSame(7, $this->service('lismode', null, 7)->getOwnLabId());
    }

    public function testCloudLisUsesTheOperatorsAssignedLab(): void
    {
        self::assertSame(9, $this->service('stsmode', 'testing-lab', 9)->getOwnLabId());
    }

    /** The regression: an unassigned cloud-LIS operator must NOT inherit sc_testing_lab_id. */
    public function testCloudLisWithNoAssignedLabResolvesToNull(): void
    {
        self::assertNull($this->service('stsmode', 'testing-lab', null)->getOwnLabId());
    }

    /** Zero is "unassigned" too -- the column is nullable but has been seen holding 0. */
    public function testCloudLisWithZeroLabResolvesToNull(): void
    {
        self::assertNull($this->service('stsmode', 'testing-lab', 0)->getOwnLabId());
    }

    public function testStsCollectionSiteUserDoesNotActAsALab(): void
    {
        self::assertNull($this->service('stsmode', 'collection-site', null)->getOwnLabId());
    }

    public function testStandaloneInstanceDoesNotActAsALab(): void
    {
        self::assertNull($this->service('standalone', null, null)->getOwnLabId());
    }
}
