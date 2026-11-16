<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Abstracts\AbstractSampleExportService;
use App\Services\Cd4ExportService;
use App\Services\Covid19ExportService;
use App\Services\Covid19Service;
use App\Services\DatabaseService;
use App\Services\GenericTestsExportService;
use App\Services\HepatitisExportService;
use App\Services\TbExportService;
use App\Services\TbService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tests\Support\VlServiceFactory;

use const COUNTRY\SOUTH_SUDAN;

/**
 * The default export headings of CD4, Hepatitis, TB, COVID-19 (universal sheet)
 * and Custom Tests, in order. Labs use these sheets as they are, so a change to
 * a heading or to the column order has to be made here too, on purpose.
 *
 * The form is South Sudan's: no column of these modules is tagged for it, so
 * the COVID-19 sheet is the universal one. The instance is not standalone, so
 * Remote Sample ID is shown.
 */
final class SampleExportHeadingsTest extends TestCase
{
    private const CD4 = [
        'S.No.', 'Sample ID', 'Remote Sample ID', 'Testing Lab', 'Sample Reception Date',
        'Health Facility Name', 'Health Facility Code', 'District/County', 'Province/State',
        'Unique ART No.', 'Patient Name', 'Date of Birth', 'Age', 'Sex', 'Patient Cellphone Number',
        'Date of Sample Collection', 'Sample Type', 'Date of Treatment Initiation', 'Current Regimen',
        'Date of Initiation of Current Regimen', 'Is Patient Pregnant?', 'Is Patient Breastfeeding?',
        'ARV Adherence', 'Indication for CD4 Testing', 'Requesting Clinician',
        'Requesting Clinician Cellphone Number', 'Request Date', 'Is Sample Rejected?', 'Rejection Reason',
        'Recommended Corrective Action', 'Sample Tested On', 'CD4 Result', 'Result Printed Date',
        'CD4 Result (%)', 'Comments', 'Funding Source', 'Implementing Partner', 'Request Created On',
        'Result Sent On', 'Collection to Lab Reception (days)',
    ];

    private const HEPATITIS = [
        'S.No.', 'Sample ID', 'Remote Sample ID', 'Testing Lab Name', 'Sample Received On',
        'Health Facility Name', 'Health Facility Code', 'District/County', 'Province/State',
        'Patient ID', 'Patient Name', 'Patient DoB', 'Patient Age', 'Patient Sex', 'Sample Collection Date',
        'Is Sample Rejected?', 'Rejection Reason', 'Sample Tested On', 'HCV VL Result', 'HBV VL Result',
        'Date Result Dispatched', 'Result Status', 'Comments', 'Funding Source', 'Implementing Partner',
        'Result Sent On', 'Collection to Lab Reception (days)', 'Request Created On',
    ];

    private const TB = [
        'S. No.', 'Sample ID', 'Remote Sample ID', 'Testing Lab Name', 'Date specimen Received',
        'Lab staff Assigned', 'Health Facility/POE County', 'Health Facility/POE State', 'Health Facility/POE',
        'Case ID', 'Patient Name', 'Patient DoB', 'Patient Age', 'Patient Sex', 'Date specimen collected',
        'Reason for Test Request', 'Date specimen Entered', 'Specimen Status', 'Specimen Type',
        'Is Sample Rejected?', 'Rejection Reason', 'Recommended Corrective Action', 'Date specimen Tested',
        'Testing Platform', 'Test Method', 'Result', 'Date result released', 'Result Sent On',
        'Collection to Lab Reception (days)',
    ];

    private const COVID19 = [
        'S. No.', 'Sample ID', 'Remote Sample ID', 'Testing Lab Name', 'Date specimen received',
        'Testing Point', 'Lab staff Assigned', 'Source Of Alert / POE', 'Health Facility/POE County',
        'Health Facility/POE State', 'Health Facility/POE', 'Case ID', 'Patient Name', 'Patient DoB',
        'Patient Age', 'Patient Sex', 'Is Patient Pregnant', 'Patient Phone Number', 'Patient Email',
        'Patient Address', 'Patient State', 'Patient County', 'Patient City/Village', 'Nationality',
        'Fever/Temperature', 'Temprature Measurement', 'Symptoms Detected', 'Medical History',
        'Comorbidities', 'Recenty Hospitalized?', 'Patient Lives With Children', 'Patient Cares for Children',
        'Close Contacts', 'Has Recent Travel History', 'Country Names', 'Travel Return Date', 'Airline',
        'Seat No.', 'Arrival Date/Time', 'Departure Airport', 'Transit', 'Reason of Visit',
        'Number of Days Sick', 'Date of Symptoms Onset', 'Date of Initial Consultation',
        'Sample Collection Date', 'Reason for Test Request', 'Date specimen registered', 'Specimen Condition',
        'Specimen Status', 'Specimen Type', 'Is Sample Rejected?', 'Rejection Reason', 'Sample Tested Date',
        'Testing Platform', 'Test Method', 'Result', 'Date result released', 'Result Sent On',
        'Collection to Lab Reception (days)',
    ];

    /** The fixed columns only: each test type adds its own form fields at export time. */
    private const CUSTOM_TESTS = [
        'No.', 'Test Type', 'Sample ID', 'Remote Sample ID', 'Health Facility Name', 'Testing Lab',
        'Sample Receipt Date', 'Health Facility Code', 'District/County', 'Province/State',
        'Patient ID.', 'Patient Name', 'Date of Birth', 'Age', 'Sex', 'Date of Sample Collection',
        'Sample Type', 'Date of Treatment Initiation', 'Is Patient Pregnant?', 'Is Patient Breastfeeding?',
        'Reason for Testing', 'Requesting Clinician', 'Request Date', 'Is Sample Rejected?',
        'Rejection Reason', 'Sample Tested On', 'Result', 'Date Result Dispatched', 'Comments',
        'Funding Source', 'Implementing Partner', 'Request Created On', 'Result Sent On',
        'Collection to Lab Reception (days)',
    ];

    private mixed $session;

    protected function setUp(): void
    {
        $this->session = $_SESSION ?? null;
        $_SESSION = ['instance' => ['type' => 'vluser']];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->session;
    }

    /** @param class-string<AbstractSampleExportService> $class */
    private function headings(string $class, bool $withPatientInfo): array
    {
        $general = VlServiceFactory::build(['vl_form' => (string) SOUTH_SUDAN])->commonService;
        $db = (new ReflectionClass(DatabaseService::class))->newInstanceWithoutConstructor();
        $service = match ($class) {
            TbExportService::class => new TbExportService(
                $db,
                $general,
                (new ReflectionClass(TbService::class))->newInstanceWithoutConstructor()
            ),
            Covid19ExportService::class => new Covid19ExportService(
                $db,
                $general,
                (new ReflectionClass(Covid19Service::class))->newInstanceWithoutConstructor()
            ),
            default => new $class($db, $general),
        };
        return array_column($service->columns($withPatientInfo, false), 'heading');
    }

    /** @param list<string> $headings */
    private static function without(array $headings, string ...$left): array
    {
        return array_values(array_diff($headings, $left));
    }

    public function testCd4Headings(): void
    {
        self::assertSame(self::CD4, $this->headings(Cd4ExportService::class, true));
    }

    public function testCd4HeadingsWithoutPatientInfo(): void
    {
        self::assertSame(
            self::without(self::CD4, 'Unique ART No.', 'Patient Name', 'Patient Cellphone Number'),
            $this->headings(Cd4ExportService::class, false)
        );
    }

    public function testHepatitisHeadings(): void
    {
        self::assertSame(self::HEPATITIS, $this->headings(HepatitisExportService::class, true));
    }

    public function testHepatitisHeadingsWithoutPatientInfo(): void
    {
        self::assertSame(
            self::without(self::HEPATITIS, 'Patient ID', 'Patient Name'),
            $this->headings(HepatitisExportService::class, false)
        );
    }

    public function testTbHeadings(): void
    {
        self::assertSame(self::TB, $this->headings(TbExportService::class, true));
    }

    public function testTbHeadingsWithoutPatientInfo(): void
    {
        self::assertSame(
            self::without(self::TB, 'Case ID', 'Patient Name'),
            $this->headings(TbExportService::class, false)
        );
    }

    public function testCovid19UniversalHeadings(): void
    {
        self::assertSame(self::COVID19, $this->headings(Covid19ExportService::class, true));
    }

    public function testCovid19UniversalHeadingsWithoutPatientInfo(): void
    {
        self::assertSame(
            self::without(
                self::COVID19,
                'Case ID',
                'Patient Name',
                'Patient Phone Number',
                'Patient Email',
                'Patient Address'
            ),
            $this->headings(Covid19ExportService::class, false)
        );
    }

    public function testCustomTestsFixedHeadings(): void
    {
        self::assertSame(self::CUSTOM_TESTS, $this->headings(GenericTestsExportService::class, true));
    }

    public function testCustomTestsFixedHeadingsWithoutPatientInfo(): void
    {
        self::assertSame(
            self::without(self::CUSTOM_TESTS, 'Patient ID.', 'Patient Name'),
            $this->headings(GenericTestsExportService::class, false)
        );
    }
}
