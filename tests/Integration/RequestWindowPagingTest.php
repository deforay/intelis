<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\STS\RequestsService;
use Psr\Http\Message\ResponseInterface;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\LegacyAppHarness;

/**
 * A lab pulling a date or manifest window in pages (requests.php with "page").
 *
 * One response for a big window outlasted the lab's request timeout and the lab
 * saved nothing. A lab that asks gets the window a page at a time, in (last
 * modified, id) order, until no next page is named; every row of the window
 * arrives, none twice. A lab that does not ask -- every lab before this -- gets
 * the whole window and no paging headers, exactly as before.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class RequestWindowPagingTest extends TestCase
{
    private const SINCE = '2026-08-01';

    private bool $booted = false;

    protected function tearDown(): void
    {
        if ($this->booted) {
            LegacyAppHarness::shutdown();
        }
    }

    private function boot(): mixed
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        require_once ROOT_PATH . '/app/system/version.php';
        $db = LegacyAppHarness::boot('intelis_request_paging_test_' . getmypid(), [
            'r_sample_status', 'form_vl', 'facility_details', 'testing_lab_health_facilities_map',
            'track_api_requests', 'global_config', 'system_config', 's_vlsm_instance',
        ]);
        $this->booted = true;
        $db->rawQuery("INSERT INTO r_sample_status (status_id, status_name) VALUES (6, 'Registered')");
        $db->insert('global_config', [
            'display_name' => 'Sync interval', 'name' => 'data_sync_interval', 'value' => '30',
        ]);
        $db->insert('facility_details', [
            'facility_id' => 7, 'facility_name' => 'Lab Seven', 'facility_type' => 2, 'status' => 'active',
            'sts_token' => 'lab-seven-token', 'sts_token_expiry' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);
        // 23 in the window, several sharing a modification time, on one manifest;
        // one from before it; one of another lab.
        for ($id = 1; $id <= 23; $id++) {
            self::row($db, $id, '2026-08-' . sprintf('%02d', 2 + intdiv($id, 4)) . ' 10:00:00');
        }
        self::row($db, 24, '2026-07-01 10:00:00');
        self::row($db, 25, '2026-08-05 10:00:00', ['lab_id' => 9]);
        return $db;
    }

    private static function row($db, int $id, string $modified, array $extra = []): void
    {
        $db->insert('form_vl', $extra + [
            'vl_sample_id' => $id, 'unique_id' => "u-$id", 'sample_code' => "S-$id", 'lab_id' => 7,
            'facility_id' => 10, 'data_sync' => 0, 'last_modified_datetime' => $modified,
            'vlsm_instance_id' => 'sts', 'result_status' => 6, 'sample_package_code' => 'M-1',
        ]);
    }

    private static function sts(): RequestsService
    {
        return ContainerRegistry::get(RequestsService::class);
    }

    /**
     * Pages through the window as a lab would, and what each page held.
     *
     * @return list<list<string>>
     */
    private static function pageThrough(?string $manifest = null, ?callable $betweenPages = null): array
    {
        $pages = [];
        $after = null;
        do {
            $data = self::sts()->getRequests(
                'vl',
                7,
                null,
                $manifest,
                $manifest === null ? self::SINCE : null,
                false,
                false,
                ['size' => 5, 'after' => $after]
            );
            $pages[] = array_column($data['requests'], 'unique_id');
            $after = $data['nextAfter'];
            if ($betweenPages !== null) {
                $betweenPages(count($pages));
            }
            self::assertLessThan(20, count($pages), 'the pages come to an end');
        } while ($after !== null);
        return $pages;
    }

    /** @return list<string> */
    private static function windowIds(): array
    {
        return array_map(static fn(int $id): string => "u-$id", range(1, 23));
    }

    #[RunInSeparateProcess]
    public function testAWindowComesInPagesWithNothingMissedOrRepeated(): void
    {
        $this->boot();

        $pages = self::pageThrough();

        self::assertCount(5, $pages, '23 rows, 5 to a page');
        foreach (array_slice($pages, 0, -1) as $page) {
            self::assertCount(5, $page);
        }
        $all = array_merge(...$pages);
        self::assertSame(count($all), count(array_unique($all)), 'no row twice');
        self::assertEqualsCanonicalizing(self::windowIds(), $all, 'every row of the window, nothing else');
    }

    #[RunInSeparateProcess]
    public function testARowChangedDuringThePullStillArrives(): void
    {
        $db = $this->boot();

        $pages = self::pageThrough(null, static function (int $page) use ($db): void {
            if ($page === 1) {
                // Edited on the STS between pages: one already sent, one not yet.
                $db->rawQuery(
                    "UPDATE form_vl SET last_modified_datetime = '2026-09-01 10:00:00' WHERE vl_sample_id IN (1, 20)"
                );
            }
        });

        $all = array_merge(...$pages);
        self::assertEqualsCanonicalizing(self::windowIds(), array_values(array_unique($all)), 'nothing skipped');
        self::assertContains('u-1', array_merge(...array_slice($pages, 1)), 'the edit is sent again');
    }

    #[RunInSeparateProcess]
    public function testAManifestComesInPagesToo(): void
    {
        $this->boot();

        $all = array_merge(...self::pageThrough('M-1'));

        // The manifest holds the lab's 24 samples, the one from July included.
        self::assertEqualsCanonicalizing([...self::windowIds(), 'u-24'], $all);
    }

    #[RunInSeparateProcess]
    public function testALabThatDoesNotAskGetsTheWholeWindowAsBefore(): void
    {
        $this->boot();

        $data = self::sts()->getRequests('vl', 7, null, null, self::SINCE);

        self::assertEqualsCanonicalizing(self::windowIds(), array_column($data['requests'], 'unique_id'));
        self::assertArrayNotHasKey('paged', $data);
        self::assertArrayNotHasKey('nextAfter', $data);
    }

    private static function post(array $body): ResponseInterface
    {
        // One per test: the legacy handler loads requests.php once a process.
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/remote/v2/requests.php')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer lab-seven-token')
            ->withBody((new StreamFactory())->createStream((string) json_encode($body)));
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        return $handler->handle($request);
    }

    /** @return list<string> */
    private static function sentBy(ResponseInterface $response): array
    {
        return array_column(json_decode((string) $response->getBody(), true)['requests'] ?? [], 'unique_id');
    }

    #[RunInSeparateProcess]
    public function testRequestsPhpSendsAPageToALabThatAsks(): void
    {
        $this->boot();

        $response = self::post([
            'labId' => 7, 'testType' => 'vl', 'syncSinceDate' => self::SINCE, 'page' => ['size' => 5],
        ]);

        self::assertCount(5, self::sentBy($response));
    }

    /** @return iterable<string, array{?array<string, mixed>}> */
    public static function noUsablePage(): iterable
    {
        yield 'an older lab, which never asks' => [null];
        yield 'a page of no rows' => [['size' => 0]];
        yield 'a marker that is not one' => [['size' => 5, 'after' => "x' OR 1=1 --"]];
    }

    /** @param array<string, mixed>|null $page */
    #[RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\DataProvider('noUsablePage')]
    public function testRequestsPhpSendsTheWholeWindowWithoutAUsablePage(?array $page): void
    {
        $this->boot();
        $body = ['labId' => 7, 'testType' => 'vl', 'syncSinceDate' => self::SINCE];
        if ($page !== null) {
            $body['page'] = $page;
        }

        self::assertEqualsCanonicalizing(self::windowIds(), self::sentBy(self::post($body)));
    }

    public function testPagingHeadersGoOnlyToALabThatAskedForPages(): void
    {
        self::assertSame([], RequestsService::pagingHeaders(['requests' => []]), 'an older lab sees no change');
        self::assertSame(
            ['x-requests-paged' => 1, 'x-requests-next-after' => '2026-08-20 10:00:00|42'],
            RequestsService::pagingHeaders(['paged' => true, 'nextAfter' => '2026-08-20 10:00:00|42'])
        );
        self::assertSame(
            ['x-requests-paged' => 1],
            RequestsService::pagingHeaders(['paged' => true, 'nextAfter' => null]),
            'the last page names no next one'
        );
    }
}
