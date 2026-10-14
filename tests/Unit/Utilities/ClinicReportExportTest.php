<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\DataQualityReportUtility;
use App\Utilities\PatientTimelineUtility;
use App\Utilities\SampleTestingReportUtility;
use PHPUnit\Framework\TestCase;

/**
 * The pieces the clinic report exports share: the Sample Testing columns, the
 * per-module session entry they replay, and the Data Quality listing key.
 */
final class ClinicReportExportTest extends TestCase
{
    public function testEachModuleKeepsItsOwnSampleTestingQuery(): void
    {
        $types = ['vl', 'eid', 'covid19', 'hepatitis', 'tb', 'cd4', 'generic-tests'];
        $keys = array_map(SampleTestingReportUtility::sessionKey(...), $types);

        $this->assertCount(count($types), array_unique($keys));
        $this->assertNotContains('sampleTestingReport', $keys);
    }

    public function testExportColumnsFollowTheTab(): void
    {
        // Facility details and the total, then every bucket in stacking order,
        // then whatever no bucket holds: the tab's facility table, column for column.
        $expected = [
            'facility',
            'state',
            'district',
            'total',
            ...array_keys(SampleTestingReportUtility::BUCKETS),
            'other',
        ];

        $this->assertSame($expected, array_keys(SampleTestingReportUtility::columns()));
    }

    public function testExportRowCarriesEveryBucketAndTheRemainder(): void
    {
        $row = [
            'facility_id' => 7,
            'facility_name' => 'Clinic A',
            'facility_state' => 'North',
            'facility_district' => null,
            'total' => '20',
            'tested' => '9',
            'awaitingApproval' => '1',
            'awaitingTesting' => '2',
            'notAtLab' => '0',
            'failed' => '3',
            'rejected' => '1',
        ];

        $this->assertSame(
            ['Clinic A', 'North', '', 20, 9, 1, 2, 0, 3, 1, 4],
            SampleTestingReportUtility::exportRow($row)
        );
    }

    public function testExportRowMatchesTheTabRow(): void
    {
        $row = [
            'facility_name' => 'Clinic B',
            'facility_state' => 'South',
            'facility_district' => 'East',
            'total' => 5,
            'tested' => 5,
            'awaitingApproval' => 0,
            'awaitingTesting' => 0,
            'notAtLab' => 0,
            'failed' => 0,
            'rejected' => 0,
        ];

        $shaped = SampleTestingReportUtility::shapeRow($row);
        $this->assertSame(
            array_map(fn(string $column) => $shaped[$column], array_keys(SampleTestingReportUtility::columns())),
            SampleTestingReportUtility::exportRow($row)
        );
        $this->assertSame(0, $shaped['other']);
    }

    public function testListingKeyIgnoresRequestPlumbingAndOrder(): void
    {
        $listing = [
            'testType' => 'vl',
            'sampleCollectionDate' => '01-Jan-2026 to 31-Jan-2026',
            'facilityName' => ['3', '5'],
            'action' => 'samples',
            'check' => 'patient_dob',
            'facilityId' => '',
            'ignore' => '',
        ];
        $export = [
            'ignore' => '',
            'facilityId' => '',
            'check' => 'patient_dob',
            'facilityName' => ['3', '5'],
            'sampleCollectionDate' => '01-Jan-2026 to 31-Jan-2026',
            'testType' => 'vl',
            'async' => 'yes',
            'csrf_token' => 'abc',
        ];

        $this->assertSame(
            DataQualityReportUtility::listingKey($listing),
            DataQualityReportUtility::listingKey($export)
        );
    }

    public function testListingKeyTellsListingsApart(): void
    {
        $base = ['testType' => 'vl', 'check' => 'patient_dob', 'facilityId' => ''];

        $this->assertNotSame(
            DataQualityReportUtility::listingKey($base),
            DataQualityReportUtility::listingKey(['facilityId' => '12'] + $base)
        );
        $this->assertNotSame(
            DataQualityReportUtility::listingKey($base),
            DataQualityReportUtility::listingKey(['testType' => 'eid'] + $base)
        );
    }

    public function testTimelineCountBelongsToItsOwnPatient(): void
    {
        $_SESSION = [];
        PatientTimelineUtility::rememberCount('P-WITH-SAMPLES', 12);
        // Opened later, in another browser tab, with nothing visible.
        PatientTimelineUtility::rememberCount('P-EMPTY', 0);

        $this->assertSame(12, PatientTimelineUtility::rememberedCount('P-WITH-SAMPLES'));
        $this->assertSame(0, PatientTimelineUtility::rememberedCount('P-EMPTY'));
        $this->assertNull(PatientTimelineUtility::rememberedCount('P-NEVER-OPENED'));
    }

    public function testTimelineCountsStaySmall(): void
    {
        $_SESSION = [];
        for ($i = 1; $i <= 50; $i++) {
            PatientTimelineUtility::rememberCount((string) $i, $i);
        }
        // Reopening a patient already dropped makes it the newest again.
        PatientTimelineUtility::rememberCount('5', 5);

        $this->assertCount(20, $_SESSION['patientTimelineCounts']);
        $this->assertSame(5, PatientTimelineUtility::rememberedCount('5'));
        $this->assertSame(50, PatientTimelineUtility::rememberedCount('50'));
        // The oldest of the rest makes room.
        $this->assertNull(PatientTimelineUtility::rememberedCount('31'));
        $this->assertSame(32, PatientTimelineUtility::rememberedCount('32'));
    }
}
