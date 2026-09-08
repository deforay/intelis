<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\SystemException;
use App\HttpHandlers\LegacyRequestHandler;
use App\Services\CommonService;
use App\Services\QualityMonitoringService;
use App\Registries\ContainerRegistry;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * The EID Quality Monitoring endpoint, driven the way the page drives it.
 *
 * What this module has to get right is the split: a sample is either waiting on
 * the clinic side or waiting on the lab side, never both and never neither, and
 * a sample that has left the pipeline is waiting on nobody. The rows below sit
 * at one known point each, and the tests assert where the split puts them.
 *
 * LegacyRequestHandler requires a page with require_once, so a process can
 * drive it once: every test runs in its own process and drives exactly once.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class QualityMonitoringEndpointTest extends TestCase
{
    private const DATABASE = 'intelis_quality_monitoring_test';

    private const ENDPOINT = '/eid/qa/get-qa-monitoring-data.php';

    private const LAB_ID = 601;
    private const FACILITY_ID = 602;
    private const OTHER_FACILITY_ID = 603;

    private static function booted(): bool
    {
        return getenv('INTELIS_TEST_DB_HOST') !== false
            && getenv('INTELIS_TEST_DB_HOST') !== ''
            && getenv('INTELIS_TEST_DB_USER') !== false
            && getenv('INTELIS_TEST_DB_USER') !== '';
    }

    protected function setUp(): void
    {
        if (!self::booted()) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }

        $db = LegacyAppHarness::boot(self::DATABASE, [
            // Referenced tables first: form_vl carries a foreign key to
            // r_sample_status and will not create without it.
            'facility_details', 'r_implementation_partners', 'r_sample_status',
            'form_eid', 'form_vl', 'instruments', 'system_config', 'global_config',
        ]);
        // Superadmin: the endpoint's privilege guard passes, and no lab or
        // facility scoping narrows what it lists.
        LegacyAppHarness::withSession(['roleId' => 1]);

        $db->rawQuery(
            "INSERT INTO facility_details
                (facility_id, facility_name, facility_state, facility_state_id, facility_district,
                 facility_district_id, facility_type, vlsm_instance_id, status)
             VALUES (" . self::LAB_ID . ", 'Central Lab', 'North', 11, 'Capital', 21, 2, 'test', 'active'),
                    (" . self::FACILITY_ID . ", 'Riverside Clinic', 'North', 11, 'Capital', 21, 1, 'test', 'active'),
                    (" . self::OTHER_FACILITY_ID . ", 'Hilltop Clinic', 'South', 12, 'Coast', 22, 1, 'test', 'active')"
        );
        $db->rawQuery(
            "INSERT INTO r_implementation_partners (i_partner_id, i_partner_name) VALUES (7, 'Partner Seven')"
        );
        // The statuses the seeded rows carry. Named, because the message this
        // module writes about a self-contradicting record quotes the status.
        $db->rawQuery(
            "INSERT INTO r_sample_status (status_id, status_name) VALUES
                (1, 'On Hold'), (2, 'Lost or Missing'), (3, 'Reordered'), (4, 'Rejected'),
                (5, 'Test Failed'), (6, 'Received at Testing Lab'), (7, 'Accepted'),
                (8, 'Awaiting Approval'), (9, 'Received at Clinic'), (10, 'Expired'),
                (11, 'No Result'), (12, 'Cancelled'), (13, 'Referred')"
        );
        // The mother's own viral load request. Her name is here and nowhere on
        // the EID request, which is the case the VL lookup is for.
        $db->rawQuery(
            "INSERT INTO form_vl
                (vlsm_instance_id, patient_art_no, patient_first_name, patient_last_name, result_status)
             VALUES ('test', 'MO-VL-88', 'Rebecca', 'Felex', 6)"
        );

        $this->seedOneOfEverything();
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    /**
     * One form_eid row with the given milestones. Every row is collected 100
     * days ago so the collection date never decides the age; the most recent
     * milestone does.
     *
     * @param array<string, mixed> $columns
     */
    private function seed(array $columns): void
    {
        static $sequence = 0;
        $sequence++;

        $row = $columns + [
            'vlsm_instance_id' => 'test',
            'sample_code' => 'Q' . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
            'facility_id' => self::FACILITY_ID,
            'sample_collection_date' => self::daysAgo(100),
            'result_status' => 6,
        ];
        $names = [];
        $values = [];
        foreach ($row as $name => $value) {
            $names[] = "`$name`";
            if ($value === null) {
                $values[] = 'NULL';
            } elseif (is_string($value) && str_starts_with($value, 'DATE_SUB(')) {
                $values[] = $value;
            } else {
                $values[] = "'" . LegacyAppHarness::db()->escape((string) $value) . "'";
            }
        }
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO form_eid (" . implode(', ', $names) . ") VALUES (" . implode(', ', $values) . ")"
        );
    }

    private static function daysAgo(int $days): string
    {
        return "DATE_SUB(NOW(), INTERVAL $days DAY)";
    }

    /** @return array<string, mixed> decoded JSON */
    private function drive(array $post): array
    {
        $request = LegacyAppHarness::withPost($post, self::ENDPOINT);
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $body = (string) $handler->handle($request)->getBody();
        $json = json_decode($body, true);
        self::assertIsArray($json, "Endpoint did not return JSON: $body");
        return $json;
    }

    private function seedOneOfEverything(): void
    {
        // -- Waiting on the clinic side ------------------------------------
        // Registered 100 days ago, no lab has it.
        $this->seed([]);
        // Dispatched 20 days ago, still no lab receipt: the age counts from the
        // dispatch, but the sample is still the clinic's to explain.
        $this->seed(['sample_dispatched_datetime' => self::daysAgo(20)]);
        // Another province, so the province filter has something to exclude.
        $this->seed(['facility_id' => self::OTHER_FACILITY_ID]);

        // -- Waiting on the lab side ---------------------------------------
        // Received 40 days ago, not tested.
        $this->seed([
            'lab_id' => self::LAB_ID,
            'sample_received_at_lab_datetime' => self::daysAgo(40),
        ]);
        // Failed 3 days ago: back in the lab queue.
        $this->seed([
            'lab_id' => self::LAB_ID,
            'sample_received_at_lab_datetime' => self::daysAgo(10),
            'sample_tested_datetime' => self::daysAgo(3),
            'result_status' => 5,
        ]);
        // Tested 5 days ago with a result, not approved.
        $this->seed([
            'lab_id' => self::LAB_ID,
            'sample_received_at_lab_datetime' => self::daysAgo(9),
            'sample_tested_datetime' => self::daysAgo(5),
            'result' => 'negative',
            'result_status' => 8,
            'implementing_partner' => 7,
            'child_id' => 'CH-77',
            'child_name' => 'Amina',
            'child_surname' => 'Okello',
            'child_dob' => '2025-01-14',
            'child_age' => 8,
            'mother_id' => 'MO-77',
            'mother_name' => 'Grace',
            'mother_surname' => 'Okello',
        ]);

        // -- Waiting on nobody ---------------------------------------------
        // Approved and sitting unreleased is the lab's problem in the ageing
        // report, but there IS a result, so nobody is being asked why there
        // isn't one.
        $this->seed([
            'lab_id' => self::LAB_ID,
            'sample_tested_datetime' => self::daysAgo(75),
            'result' => 'negative',
            'result_status' => 7,
            'result_approved_datetime' => self::daysAgo(70),
        ]);
        // Released.
        $this->seed([
            'lab_id' => self::LAB_ID,
            'sample_tested_datetime' => self::daysAgo(12),
            'result' => 'positive',
            'result_status' => 7,
            'result_approved_datetime' => self::daysAgo(11),
            'result_mail_datetime' => self::daysAgo(10),
        ]);
        // The contradiction this module exists to surface: received at a lab,
        // never tested, no result -- and marked Accepted and approved anyway.
        $this->seed([
            'lab_id' => self::LAB_ID,
            'sample_received_at_lab_datetime' => self::daysAgo(30),
            'result_status' => 7,
            'result_approved_datetime' => self::daysAgo(2),
            'child_id' => 'CH-88',
            'mother_id' => 'MO-VL-88',
        ]);

        // Exits: rejected by status, rejected by flag alone, expired, lost, cancelled.
        $this->seed(['lab_id' => self::LAB_ID, 'result_status' => 4, 'is_sample_rejected' => 'yes']);
        $this->seed(['is_sample_rejected' => 'yes']);
        $this->seed(['result_status' => 10]);
        $this->seed(['result_status' => 2]);
        $this->seed(['result_status' => 12]);
    }

    #[RunInSeparateProcess]
    public function testSummaryCountsOnlyTheSamplesSomebodyStillOwes(): void
    {
        $json = $this->drive(['section' => 'summary', 'dateRange' => '']);
        self::assertArrayHasKey('summary', $json, json_encode($json));
        $summary = $json['summary'];

        self::assertSame(3, $summary['clinic']['total'], 'three registered with no lab receipt');
        self::assertSame(3, $summary['clinic']['late'], 'all three have been waiting 20 days or more');
        self::assertSame(2, $summary['clinic']['veryLate'], 'the dispatched one is only 20 days old');

        self::assertSame(4, $summary['lab']['total'], 'three in the lab queue plus one awaiting approval');
        self::assertSame(1, $summary['lab']['late'], 'only the one received 40 days ago');
        self::assertSame(1, $summary['lab']['veryLate']);

        self::assertSame(3, $summary['stages']['atLab']);
        self::assertSame(1, $summary['stages']['awaitingApproval']);
        self::assertSame(3, $summary['stages']['atFacility']);

        // Released, approved-unreleased and the five exits are nobody's to
        // explain, so they are in neither total.
        self::assertSame(7, $summary['clinic']['total'] + $summary['lab']['total']);
    }

    #[RunInSeparateProcess]
    public function testALabCarriesTheInstrumentsItHasActuallyTestedOn(): void
    {
        // None of the waiting rows names an instrument -- they have no result
        // yet -- so the line under the lab name has to come from its finished
        // work. Both ways of recording it are seeded: a managed instrument by
        // id, and the free text older rows carry in the platform field.
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO instruments (instrument_id, machine_name, lab_id, max_no_of_samples_in_a_batch, status)
             VALUES ('inst-gx-1', 'GeneXpert', " . self::LAB_ID . ", 16, 'active')"
        );
        $released = [
            'lab_id' => self::LAB_ID,
            'result' => 'negative',
            'result_status' => 7,
            'sample_tested_datetime' => self::daysAgo(20),
            'result_approved_datetime' => self::daysAgo(19),
            'result_mail_datetime' => self::daysAgo(18),
        ];
        // Two on the managed instrument and one on the typed-in platform, so
        // the ordering by how much each is used is also being asserted.
        $this->seed($released + ['instrument_id' => 'inst-gx-1']);
        $this->seed($released + ['instrument_id' => 'inst-gx-1']);
        $this->seed($released + ['eid_test_platform' => 'HRL/PCR/GNX/003']);
        // A finished sample at a lab nobody is waiting on must not leak into
        // another lab's line.
        $this->seed(['lab_id' => self::OTHER_FACILITY_ID, 'eid_test_platform' => 'Abbott'] + $released);

        $json = $this->drive(['section' => 'samples', 'view' => 'lab', 'dateRange' => '', 'iDisplayLength' => 25]);

        foreach ($json['aaData'] as $row) {
            self::assertSame(
                'GeneXpert, HRL/PCR/GNX/003',
                $row['labInstruments'],
                'most used first, and only this lab own instruments'
            );
        }
    }

    #[RunInSeparateProcess]
    public function testTheInstrumentFilterNarrowsToTheLabsThatRunIt(): void
    {
        // A second lab, testing on something the first one does not have.
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO facility_details
                (facility_id, facility_name, facility_state, facility_state_id, facility_district,
                 facility_district_id, facility_type, vlsm_instance_id, status)
             VALUES (901, 'Northern Lab', 'North', 11, 'Capital', 21, 2, 'test', 'active')"
        );
        $released = [
            'result' => 'negative',
            'result_status' => 7,
            'sample_tested_datetime' => self::daysAgo(20),
            'result_approved_datetime' => self::daysAgo(19),
            'result_mail_datetime' => self::daysAgo(18),
        ];
        $this->seed(['lab_id' => self::LAB_ID, 'eid_test_platform' => 'GeneXpert'] + $released);
        $this->seed(['lab_id' => 901, 'eid_test_platform' => 'Abbott m2000'] + $released);
        // Waiting at the second lab, so the filter has something to keep and
        // something to drop on the same tab.
        $this->seed(['lab_id' => 901, 'sample_received_at_lab_datetime' => self::daysAgo(6)]);

        // The service and not the endpoint: driving the endpoint requires a
        // process of its own, and this is four questions rather than one.
        /** @var QualityMonitoringService $service */
        $service = ContainerRegistry::get(QualityMonitoringService::class);

        $labsOf = static function (array $rows): array {
            return array_values(array_unique(array_column($rows, 'lab')));
        };

        $all = $service->getSamples($service->resolveFilters(['dateRange' => '']), 'lab', 0, 25);
        self::assertSame(5, $all['total'], 'the four already waiting plus the one at Northern Lab');

        $genexpert = $service->getSamples(
            $service->resolveFilters(['dateRange' => '', 'instrument' => 'GeneXpert']),
            'lab',
            0,
            25
        );
        self::assertSame(4, $genexpert['total'], 'only Central Lab tests on GeneXpert');
        self::assertSame(['Central Lab'], $labsOf($genexpert['rows']));

        $abbott = $service->getSamples(
            $service->resolveFilters(['dateRange' => '', 'instrument' => 'Abbott m2000']),
            'lab',
            0,
            25
        );
        self::assertSame(1, $abbott['total']);
        self::assertSame(['Northern Lab'], $labsOf($abbott['rows']));

        // A name nothing has been tested on is refused, not quietly matched
        // against everything, so an instrument cannot be used to smuggle SQL in.
        $this->expectException(SystemException::class);
        $service->resolveFilters(['dateRange' => '', 'instrument' => "GeneXpert' OR 1=1 -- "]);
    }

    #[RunInSeparateProcess]
    public function testEachViewListsOnlyItsOwnSideOldestFirst(): void
    {
        $json = $this->drive(['section' => 'samples', 'view' => 'lab', 'dateRange' => '', 'iDisplayLength' => 25]);
        self::assertSame(4, $json['iTotalRecords'], json_encode($json));

        $ages = array_column($json['aaData'], 'age');
        self::assertSame([40, 5, 3, 2], $ages, 'oldest first, counted from the most recent milestone');

        foreach ($json['aaData'] as $row) {
            self::assertContains($row['stage'], ['atLab', 'awaitingApproval']);
            self::assertSame('Central Lab', $row['lab']);
            self::assertNotSame('', $row['stageLabel'], 'the stage is named for the reader');
        }
    }

    #[RunInSeparateProcess]
    public function testTheClinicViewIsEverythingNoLabHasReceived(): void
    {
        $json = $this->drive(['section' => 'samples', 'view' => 'clinic', 'dateRange' => '', 'iDisplayLength' => 25]);
        self::assertSame(3, $json['iTotalRecords'], json_encode($json));

        foreach ($json['aaData'] as $row) {
            self::assertSame('atFacility', $row['stage']);
            self::assertSame('', $row['receivedAtLab'], 'nothing here has been received anywhere');
        }
    }

    #[RunInSeparateProcess]
    public function testTheListingCarriesIdentifiersAndNoPatientNames(): void
    {
        $json = $this->drive(['section' => 'samples', 'view' => 'lab', 'dateRange' => '', 'iDisplayLength' => 25]);

        $row = null;
        foreach ($json['aaData'] as $candidate) {
            if ($candidate['childId'] === 'CH-77') {
                $row = $candidate;
            }
        }
        self::assertNotNull($row, json_encode($json));

        self::assertSame('MO-77', $row['motherId']);
        self::assertNotSame('', $row['childDob'], 'a date of birth is formatted for reading');
        self::assertSame('8', $row['childAge']);

        // Both sides of the workflow read this page, so it carries no name for
        // the child or the mother even though the seeded rows have them.
        foreach ($json['aaData'] as $listed) {
            self::assertArrayNotHasKey('childName', $listed);
            self::assertArrayNotHasKey('motherName', $listed);
            self::assertArrayNotHasKey('motherNameFromVl', $listed);
        }
    }

    // The endpoint is required once per process, so each filter gets its own
    // test rather than three drives in one.

    #[RunInSeparateProcess]
    public function testTheProvinceFilterNarrowsToItsOwnFacilities(): void
    {
        $json = $this->drive([
            'section' => 'samples', 'view' => 'clinic', 'dateRange' => '',
            'provinceId' => 11, 'iDisplayLength' => 25,
        ]);
        self::assertSame(2, $json['iTotalRecords'], 'the two Riverside samples, not the Hilltop one');
    }

    #[RunInSeparateProcess]
    public function testTheAgeBucketNarrowsToHowLongTheSampleHasWaited(): void
    {
        $json = $this->drive([
            'section' => 'samples', 'view' => 'clinic', 'dateRange' => '',
            'bucket' => 'b2', 'iDisplayLength' => 25,
        ]);
        self::assertSame(1, $json['iTotalRecords'], 'only the one dispatched 20 days ago falls in 15-30');
        self::assertSame(20, $json['aaData'][0]['age']);
    }

    #[RunInSeparateProcess]
    public function testTheImplementingPartnerFilterNarrowsTheListing(): void
    {
        $json = $this->drive([
            'section' => 'samples', 'view' => 'lab', 'dateRange' => '',
            'partnerId' => 7, 'iDisplayLength' => 25,
        ]);
        self::assertSame(1, $json['iTotalRecords'], json_encode($json));
        self::assertSame('Partner Seven', $json['aaData'][0]['partner']);
    }

    #[RunInSeparateProcess]
    public function testARecordThatContradictsItselfIsListedAndSaidSoAbout(): void
    {
        $json = $this->drive(['section' => 'samples', 'view' => 'lab', 'dateRange' => '', 'iDisplayLength' => 25]);

        $row = null;
        foreach ($json['aaData'] as $candidate) {
            if ($candidate['childId'] === 'CH-88') {
                $row = $candidate;
            }
        }
        self::assertNotNull($row, json_encode($json));

        // It is where the dates say it is, not where the status claims.
        self::assertSame('atLab', $row['stage']);
        self::assertNotSame('', $row['dataIssue'], 'the contradiction is named, not hidden');
        self::assertSame(
            'Accepted, but no result is recorded',
            $row['dataIssue'],
            'the message quotes the status the record actually carries'
        );
    }

    #[RunInSeparateProcess]
    public function testSearchMatchesTheSampleAndTheFacility(): void
    {
        $json = $this->drive([
            'section' => 'samples', 'view' => 'clinic', 'dateRange' => '',
            'sSearch' => 'Hilltop', 'iDisplayLength' => 25,
        ]);
        self::assertSame(1, $json['iTotalRecords'], json_encode($json));
        self::assertSame('Hilltop Clinic', $json['aaData'][0]['facility']);
    }

    #[RunInSeparateProcess]
    public function testAnUnknownViewIsRefusedRatherThanGuessed(): void
    {
        $json = $this->drive(['section' => 'samples', 'view' => 'everything', 'dateRange' => '']);
        self::assertArrayHasKey('error', $json, json_encode($json));
        self::assertArrayNotHasKey('aaData', $json);
    }
}
