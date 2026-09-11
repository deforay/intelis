<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\PageUsageService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PageUsageServiceTest extends TestCase
{
    /** @return array<string, array{0:string, 1:string}> */
    public static function fileNames(): array
    {
        return [
            'acronym first' => ['/eid/qa/eid-quality-monitoring.php', 'EID Quality Monitoring'],
            'acronym inside' => ['/vl/results/vl-result-status.php', 'VL Result Status'],
            'digits kept' => ['/cd4/requests/cd4-requests.php', 'CD4 Requests'],
            'type parameter ignored' => ['/batch/batches.php?type=tb', 'Batches'],
            'plain words' => ['/admin/monitoring/page-usage.php', 'Page Usage'],
        ];
    }

    #[DataProvider('fileNames')]
    public function testPrettyNameKeepsAcronymsInCapitals(string $page, string $expected): void
    {
        $this->assertSame($expected, PageUsageService::prettyName($page));
    }

    public function testDisplayNameCorrectsAStoredFileName(): void
    {
        $page = '/eid/qa/eid-quality-monitoring.php';
        $this->assertSame('EID Quality Monitoring', PageUsageService::displayName('Eid Quality Monitoring', $page));
        $this->assertSame('EID Quality Monitoring', PageUsageService::displayName('', $page));
    }

    public function testDisplayNameKeepsAMenuLabel(): void
    {
        $this->assertSame('DASHBOARD', PageUsageService::displayName('DASHBOARD', '/dashboard/index.php'));
        $this->assertSame(
            'Add New Request',
            PageUsageService::displayName('Add New Request', '/vl/requests/addVlRequest.php')
        );
    }

    public function testModuleFromPath(): void
    {
        $this->assertSame('eid', PageUsageService::moduleFromPath('/eid/qa/eid-quality-monitoring.php'));
        $this->assertSame('covid19', PageUsageService::moduleFromPath('/covid-19/requests/covid-19-requests.php'));
    }
}
