<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\InterfacingService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\PENDING_APPROVAL;

/**
 * What the analyzer import does with an order that carries no result.
 *
 * bin/interface.php pulls an order on the Interface Tool's own ready flag alone,
 * never on the result being present, so a row flagged ready whose result did not
 * parse reaches the builders with nothing in it. The viral load builder has
 * always said so in as many words -- "nothing to approve if there is no result"
 * -- while the EID and hepatitis builders stamped whatever auto-approval was set
 * to. On an instance with auto-approval on, that wrote Accepted onto samples
 * holding no result, which is why EID grids carried them and viral load did not.
 *
 * The EID builder is private but takes no dependencies beyond the cached
 * auto-approve flag, so it is driven directly rather than through a database.
 * buildHepatitisData() carries the identical guard and is not driven here: it
 * resolves the tester through UsersService, which is final and reaches the
 * database, so pinning it would mean booting one for two lines that mirror
 * these.
 */
final class InterfacingAutoApproveTest extends TestCase
{
    private function service(bool $autoApprove): InterfacingService
    {
        $reflection = new ReflectionClass(InterfacingService::class);
        $service = $reflection->newInstanceWithoutConstructor();

        // autoApprove() memoises into this property, so setting it stands in for
        // the global config without booting CommonService.
        $property = $reflection->getProperty('autoApprove');
        $property->setValue($service, $autoApprove);

        return $service;
    }

    /** @return array<string, mixed> */
    private function buildEid(bool $autoApprove, ?string $results): array
    {
        $method = new ReflectionClass(InterfacingService::class)->getMethod('buildEidData');

        return $method->invoke($this->service($autoApprove), [
            'results' => $results,
            'tested_by' => 'Analyzer',
            'authorised_date_time' => null,
            'result_accepted_date_time' => null,
            'machine_used' => 'GeneXpert',
        ], null, 1);
    }

    public function testAnEidResultIsAcceptedWhenAutoApprovalIsOn(): void
    {
        $data = $this->buildEid(true, 'Positive');

        self::assertSame(ACCEPTED, $data['result_status']);
        self::assertNotEmpty($data['result']);
    }

    /**
     * The status is left out of the payload rather than lowered: an order that
     * reports no result says nothing about where the sample got to, so whatever
     * the lab already recorded stands.
     */
    public function testAnEidOrderWithNoResultDoesNotMoveTheStatusAtAll(): void
    {
        $data = $this->buildEid(true, '');

        self::assertArrayNotHasKey('result_status', $data);
        self::assertNull($data['result']);
    }

    public function testAnEidOrderWithOnlyWhitespaceIsTreatedAsHavingNoResult(): void
    {
        self::assertArrayNotHasKey('result_status', $this->buildEid(true, '   '));
    }

    /** Without auto-approval the result still goes to the approval queue. */
    public function testAnEidResultAwaitsApprovalWhenAutoApprovalIsOff(): void
    {
        self::assertSame(PENDING_APPROVAL, $this->buildEid(false, 'Negative')['result_status']);
    }
}
