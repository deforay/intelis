<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CommonService;
use App\Services\Covid19ExportService;
use App\Services\Covid19Service;
use App\Services\DatabaseService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tests\Support\VlServiceFactory;

/**
 * With covid19_report_type = rwanda, the COVID-19 export gives the old Rwanda
 * sheet: its 26 headings in its order, not the universal column list.
 */
final class Covid19RwandaExportColumnsTest extends TestCase
{
    /** The headings of the old generate-export-rwanda.php, in order. */
    private const OLD_HEADINGS = [
        'S. No.', 'Sample ID', 'Remote Sample ID', 'Testing Lab Name', 'Sample Received On',
        'Health Facility Name', 'Health Facility Code', 'District/County', 'Province/State',
        'Patient ID', 'Patient Name', 'Patient DoB', 'Patient Age', 'Patient Sex',
        'Sample Collection Date', 'Symptoms Presented in last 14 days', 'Co-morbidities',
        'Is Sample Rejected?', 'Rejection Reason', 'Recommended Corrective Action', 'Sample Tested On',
        'Result', 'Date Result Dispatched', 'Comments', 'Funding Source', 'Implementing Partner',
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

    /** @param array<string, string> $config */
    private function headings(array $config, bool $withPatientInfo = true, bool $withDetails = false): array
    {
        $general = VlServiceFactory::build($config)->commonService;
        $service = new Covid19ExportService(
            (new ReflectionClass(DatabaseService::class))->newInstanceWithoutConstructor(),
            $general,
            (new ReflectionClass(Covid19Service::class))->newInstanceWithoutConstructor()
        );
        return array_column($service->columns($withPatientInfo, $withDetails), 'heading');
    }

    public function testRwandaReportTypeGivesTheOldSheet(): void
    {
        $headings = $this->headings(['vl_form' => '7', 'covid19_report_type' => 'rwanda']);
        self::assertCount(26, $headings);
        self::assertSame(self::OLD_HEADINGS, $headings);
    }

    public function testDetailsDoNotAddColumnsToTheRwandaSheet(): void
    {
        self::assertSame(
            self::OLD_HEADINGS,
            $this->headings(['vl_form' => '7', 'covid19_report_type' => 'rwanda'], true, true)
        );
    }

    public function testRwandaSheetLeavesOutPatientIdAndNameWithoutPatientInfo(): void
    {
        self::assertSame(
            array_values(array_diff(self::OLD_HEADINGS, ['Patient ID', 'Patient Name'])),
            $this->headings(['vl_form' => '7', 'covid19_report_type' => 'rwanda'], false)
        );
    }

    public function testStandaloneRwandaSheetLeavesOutRemoteSampleId(): void
    {
        $_SESSION['instance']['type'] = 'standalone';
        self::assertSame(
            array_values(array_diff(self::OLD_HEADINGS, ['Remote Sample ID'])),
            $this->headings(['vl_form' => '7', 'covid19_report_type' => 'rwanda'])
        );
    }

    public function testComorbiditiesColumnShowsComorbidities(): void
    {
        $general = VlServiceFactory::build(['vl_form' => '7', 'covid19_report_type' => 'rwanda'])->commonService;
        $service = new Covid19ExportService(
            (new ReflectionClass(DatabaseService::class))->newInstanceWithoutConstructor(),
            $general,
            (new ReflectionClass(Covid19Service::class))->newInstanceWithoutConstructor()
        );
        $columns = array_column($service->columns(true), null, 'heading');
        $row = ['symptoms' => 'Cough', 'comorbidities' => 'Diabetes, Asthma'];
        self::assertSame('Diabetes, Asthma', ($columns['Co-morbidities']['value'])($row, 1));
        self::assertSame('Cough', ($columns['Symptoms Presented in last 14 days']['value'])($row, 1));
    }
}
