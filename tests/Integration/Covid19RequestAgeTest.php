<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\RedirectException;
use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Throwable;
use Tests\Support\LegacyAppHarness;

/**
 * The COVID-19 request add and edit saved no patient age from most country forms.
 *
 * The Cameroon forms post the age as ageInYears; the DRC, PNG, Rwanda, Sierra Leone
 * and South Sudan forms post it as patientAge. Both helpers read only ageInYears, so
 * those forms saved no age on add and blanked the stored age on every edit.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class Covid19RequestAgeTest extends TestCase
{
    private const DATABASE = 'intelis_covid19_request_age';

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

        LegacyAppHarness::boot(self::DATABASE . '_' . getmypid(), [
            'r_sample_status', 'form_covid19', 'covid19_tests', 'covid19_patient_symptoms',
            'covid19_reasons_for_testing', 'covid19_patient_comorbidities', 'audit_log', 'activity_log',
            'system_config', 'global_config', 's_vlsm_instance', 'facility_details',
        ]);
        LegacyAppHarness::withSession();
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    private function drive(string $path, array $post): void
    {
        $request = LegacyAppHarness::withPost($post, $path);
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        try {
            $handler->handle($request);
        } catch (Throwable $e) {
            for ($cause = $e; $cause instanceof Throwable; $cause = $cause->getPrevious()) {
                if ($cause instanceof RedirectException) {
                    return;
                }
            }
            throw $e;
        }
    }

    private function seedCovid19(?string $age): int
    {
        LegacyAppHarness::db()->insert('form_covid19', [
            'vlsm_instance_id' => 'test',
            'sample_code' => 'C19-1',
            'sample_code_key' => 1,
            'sample_collection_date' => '2026-09-15 09:00:00',
            'facility_id' => 1,
            'lab_id' => 1,
            'patient_age' => $age,
            'result_status' => 6,
            'data_sync' => 1,
        ]);
        return (int) LegacyAppHarness::db()->getInsertId();
    }

    private function savedAge(int $covid19Id): ?string
    {
        $row = LegacyAppHarness::db()->rawQueryOne(
            'SELECT patient_age FROM form_covid19 WHERE covid19_id = ?',
            [$covid19Id]
        );
        return $row['patient_age'] === null ? null : (string) $row['patient_age'];
    }

    /** A request form as the country forms post it. */
    private static function requestPost(int $covid19Id, array $post): array
    {
        return $post + [
            'covid19SampleId' => (string) $covid19Id,
            'sampleCode' => 'C19-1',
            'formId' => '',
            'patientId' => 'P-1',
            'instanceId' => 'test',
            'facilityId' => '1',
            'labId' => '1',
            'sampleCollectionDate' => '15-Sep-2026 09:00',
            'oldStatus' => '6',
            'isSampleRejected' => 'no',
        ];
    }

    /** @return array<string, array{string}> */
    public static function helpers(): array
    {
        return [
            'add' => ['/covid-19/requests/covid-19-add-request-helper.php'],
            'edit' => ['/covid-19/requests/covid-19-edit-request-helper.php'],
        ];
    }

    #[DataProvider('helpers')]
    #[RunInSeparateProcess]
    public function testAgePostedAsPatientAgeIsSaved(string $helper): void
    {
        $id = $this->seedCovid19(null);

        $this->drive($helper, self::requestPost($id, ['patientAge' => '34']));

        self::assertSame('34', $this->savedAge($id));
    }

    #[DataProvider('helpers')]
    #[RunInSeparateProcess]
    public function testAgePostedAsAgeInYearsIsSaved(string $helper): void
    {
        $id = $this->seedCovid19(null);

        $this->drive($helper, self::requestPost($id, ['ageInYears' => '41']));

        self::assertSame('41', $this->savedAge($id));
    }

    #[RunInSeparateProcess]
    public function testEditKeepsAChangedAgeFromAPatientAgeForm(): void
    {
        $id = $this->seedCovid19('30');

        $this->drive(
            '/covid-19/requests/covid-19-edit-request-helper.php',
            self::requestPost($id, ['patientAge' => '31'])
        );

        self::assertSame('31', $this->savedAge($id));
    }

    #[RunInSeparateProcess]
    public function testCameroonAgeUnreportedClearsTheAge(): void
    {
        // The Cameroon form empties ageInYears when "unreported" is ticked.
        $id = $this->seedCovid19('30');

        $this->drive(
            '/covid-19/requests/covid-19-edit-request-helper.php',
            self::requestPost($id, ['ageInYears' => '', 'ageUnreported' => 'unreported'])
        );

        self::assertNull($this->savedAge($id));
    }
}
