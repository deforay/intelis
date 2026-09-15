<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use Slim\Psr7\Factory\ServerRequestFactory;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * Pages that print a value from the request, driven with one that tries to add
 * its own markup.
 *
 * The input sanitizer strips tags but leaves quotes, and some pages decode the
 * value after it ran, so these values used to reach the page able to close an
 * attribute or open a tag: a link carrying one ran script in whoever opened it.
 * Each page must now print the value as text, and a legitimate value exactly as
 * it always has.
 *
 * One drive per test: the handler uses require_once. Set INTELIS_TEST_DB_HOST/
 * _PORT/_USER/_PASS to run; skipped without them.
 */
final class RequestValueEscapingTest extends TestCase
{
    protected function setUp(): void
    {
        if ((getenv('INTELIS_TEST_DB_HOST') ?: '') === '' || (getenv('INTELIS_TEST_DB_USER') ?: '') === '') {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }

        // Named per process: two runs against one MySQL would drop each other's fixtures.
        $db = LegacyAppHarness::boot('intelis_request_value_escaping_' . getmypid(), [
            'roles', 'facility_details', 'r_sample_status', 'batch_details', 'user_details',
            'r_vl_sample_type', 'r_vl_sample_rejection_reasons', 'r_vl_test_reasons', 'r_vl_art_regimen',
            'r_funding_sources', 'r_implementation_partners', 'form_vl', 'r_covid19_symptoms',
            'covid19_patient_symptoms', 'activity_log', 'system_config', 'global_config',
        ]);
        LegacyAppHarness::withSession(['roleId' => 1]);

        $db->rawQuery(
            "INSERT INTO r_covid19_symptoms (symptom_id, symptom_name, parent_symptom, symptom_status)
             VALUES (2, 'Dry cough', 1, 'active')"
        );
    }

    protected function tearDown(): void
    {
        if ((getenv('INTELIS_TEST_DB_HOST') ?: '') !== '') {
            LegacyAppHarness::shutdown();
        }
    }

    private static function drive(\Psr\Http\Message\ServerRequestInterface $request): string
    {
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        return (string) $handler->handle($request)->getBody();
    }

    private static function symptoms(string $parent): string
    {
        return self::drive(LegacyAppHarness::withPost(
            ['symptomParent' => $parent],
            '/covid-19/requests/getSymptomsByParentId.php'
        ));
    }

    /** @param array<string, string> $query */
    private static function get(string $path, array $query): string
    {
        $_GET = $query;
        return self::drive(
            (new ServerRequestFactory())
                ->createServerRequest('GET', $path)
                ->withQueryParams($query)
                ->withHeader('User-Agent', 'intelis-tests')
                ->withHeader('X-Forwarded-For', '127.0.0.1')
        );
    }

    #[RunInSeparateProcess]
    public function testASymptomParentCannotAddAttributesToTheRowsItBuilds(): void
    {
        $body = self::symptoms('1" onmouseover="alert(1)');

        self::assertStringContainsString('Dry cough', $body);
        self::assertStringNotContainsString('onmouseover="', $body);
        self::assertStringContainsString('id="1&quot; onmouseover=&quot;alert(1)"', $body);
    }

    #[RunInSeparateProcess]
    public function testALegitimateSymptomParentBuildsTheSameRows(): void
    {
        $body = self::symptoms('1');

        self::assertStringContainsString('class="symptomRow1 hide-symptoms" id="1"', $body);
        self::assertStringContainsString('name="symptomDetails[1][]"', $body);
    }

    #[RunInSeparateProcess]
    public function testAPatientSearchDecodedAfterSanitizingIsPrintedAsText(): void
    {
        // The page urldecodes the term after the sanitizer, so an encoded tag
        // arrived intact.
        $body = self::get('/vl/requests/patientModal.php', ['artNo' => '%3Csvg%20onload%3Dalert(1)%3E']);

        self::assertStringNotContainsString('<svg', $body);
        self::assertStringContainsString('&lt;svg onload=alert(1)&gt;', $body);
    }

    #[RunInSeparateProcess]
    public function testALegitimatePatientSearchTermIsPrintedAsTyped(): void
    {
        $body = self::get('/vl/requests/patientModal.php', ['artNo' => 'ART-0042']);

        self::assertStringContainsString('ART-0042', $body);
    }
}
