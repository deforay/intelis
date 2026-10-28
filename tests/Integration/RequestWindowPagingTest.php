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
use Tests\Support\TestRun;

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
        $db = LegacyAppHarness::boot(TestRun::database('intelis_request_paging_test'), [
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
    private static function pageThrough(
        ?string $manifest = null,
        ?callable $betweenPages = null,
        ?array &$remaining = null
    ): array {
        $pages = [];
        $remaining = [];
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
            $remaining[] = $data['remaining'];
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
    public function testEachPageSaysHowManyRowsOfTheWindowFollowIt(): void
    {
        $this->boot();

        self::pageThrough(null, null, $remaining);
        self::assertSame([18, 13, 8, 3, 0], $remaining, '23 rows, 5 to a page');

        // The manifest's window: the lab's 24 samples on M-1.
        self::pageThrough('M-1', null, $remaining);
        self::assertSame([19, 14, 9, 4, 0], $remaining);
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
        self::assertArrayNotHasKey('remaining', $data);
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

    #[RunInSeparateProcess]
    public function testRequestsPhpSendsAPageAndNamesTheNextToALabThatAsks(): void
    {
        $this->boot();

        $body = json_decode((string) self::post([
            'labId' => 7, 'testType' => 'vl', 'syncSinceDate' => self::SINCE, 'page' => ['size' => 5],
        ])->getBody(), true);

        self::assertCount(5, $body['requests']);
        self::assertTrue($body['paged']);
        self::assertMatchesRegularExpression('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d\|\d+$/', $body['nextAfter']);
        self::assertSame(18, $body['remaining']);
    }

    #[RunInSeparateProcess]
    public function testRequestsPhpNamesNoNextPageAfterTheLast(): void
    {
        $this->boot();
        $last = RequestsService::pageMarker('2026-08-07 10:00:00', 19);

        $body = json_decode((string) self::post([
            'labId' => 7, 'testType' => 'vl', 'syncSinceDate' => self::SINCE, 'page' => ['size' => 5, 'after' => $last],
        ])->getBody(), true);

        self::assertSame(['u-20', 'u-21', 'u-22', 'u-23'], array_column($body['requests'], 'unique_id'));
        self::assertTrue($body['paged']);
        self::assertNull($body['nextAfter']);
        self::assertSame(0, $body['remaining']);
    }

    /** data_sync of each row of lab 7's window, by unique_id. */
    private static function dataSync(): array
    {
        $rows = LegacyAppHarness::db()->rawQuery(
            "SELECT unique_id, data_sync FROM form_vl WHERE lab_id = 7 AND vl_sample_id <= 23"
        );
        return array_map('intval', array_column($rows, 'data_sync', 'unique_id'));
    }

    /**
     * A newer lab pages its scheduled window under "windowPage", with receipts. A
     * page sent under "page" is marked sent at once, with no receipt, so a row the
     * lab could not save was never sent again. An STS that does not know the key
     * sends the whole window with receipts, as it always has.
     */
    #[RunInSeparateProcess]
    public function testAWindowPageWithReceiptsWaitsForTheLabsReceipt(): void
    {
        $this->boot();
        $migration = (string) file_get_contents(ROOT_PATH . '/sys/migrations/5.7.75.sql');
        preg_match('/^CREATE TABLE IF NOT EXISTS `request_sync_failures` \(.*?^\) ENGINE=[^;]*;/ms', $migration, $sql);
        LegacyAppHarness::db()->rawQuery($sql[0]);

        $body = json_decode((string) self::post([
            'labId' => 7, 'testType' => 'vl', 'syncSinceDate' => self::SINCE, 'receipts' => 1,
            'windowPage' => ['size' => 5],
        ])->getBody(), true);

        $sent = array_column($body['requests'], 'unique_id');
        self::assertCount(5, $sent, 'one page, not the whole window');
        self::assertTrue($body['paged']);
        self::assertNotNull($body['nextAfter']);
        // In flight is what x-request-receipts goes with: the lab sends a receipt.
        // header() sends nothing under the CLI, so the rows are what is checked.
        $sync = self::dataSync();
        foreach ($sent as $uniqueId) {
            self::assertSame(RequestsService::IN_FLIGHT, $sync[$uniqueId], 'sent, waiting for the receipt');
        }
        self::assertSame(18, count(array_filter($sync, static fn(int $s): bool => $s === 0)), 'the rest untouched');
    }

    #[RunInSeparateProcess]
    public function testAWindowPageWithoutReceiptsGetsTheWholeWindowAsBefore(): void
    {
        $this->boot();

        $body = json_decode((string) self::post([
            'labId' => 7, 'testType' => 'vl', 'syncSinceDate' => self::SINCE, 'windowPage' => ['size' => 5],
        ])->getBody(), true);

        self::assertEqualsCanonicalizing(self::windowIds(), array_column($body['requests'], 'unique_id'));
        self::assertArrayNotHasKey('paged', $body);
        self::assertSame([1], array_values(array_unique(self::dataSync())), 'marked sent, as without receipts');
    }

    #[RunInSeparateProcess]
    public function testAWindowPageIsSentWithoutReceiptsWhenTheStsCannotTakeThem(): void
    {
        // No request_sync_failures table: a receipt could not be recorded.
        $this->boot();

        $body = json_decode((string) self::post([
            'labId' => 7, 'testType' => 'vl', 'syncSinceDate' => self::SINCE, 'receipts' => 1,
            'windowPage' => ['size' => 5],
        ])->getBody(), true);

        self::assertCount(5, $body['requests']);
        self::assertSame(5, count(array_filter(self::dataSync(), static fn(int $s): bool => $s === 1)), 'marked sent');
        self::assertNotContains(RequestsService::IN_FLIGHT, self::dataSync(), 'no receipt can come for it');
    }

    private static function withFailuresTable(): void
    {
        $migration = (string) file_get_contents(ROOT_PATH . '/sys/migrations/5.7.75.sql');
        preg_match('/^CREATE TABLE IF NOT EXISTS `request_sync_failures` \(.*?^\) ENGINE=[^;]*;/ms', $migration, $sql);
        LegacyAppHarness::db()->rawQuery($sql[0]);
    }

    /** data_sync of every row of lab 7, by unique_id: the manifest's 24 and u-26 off it. */
    private static function labSync(): array
    {
        $rows = LegacyAppHarness::db()->rawQuery("SELECT unique_id, data_sync FROM form_vl WHERE lab_id = 7");
        return array_map('intval', array_column($rows, 'data_sync', 'unique_id'));
    }

    /** A request of lab 7 still waiting, in the window but on another manifest. */
    private static function waitingOffTheManifest(): void
    {
        self::row(LegacyAppHarness::db(), 26, '2026-08-03 10:00:00', ['sample_package_code' => 'M-2']);
    }

    /**
     * An older lab may send "receipts" with a manifest pull. It never sends a
     * receipt for one, so the manifest is marked sent at once, as it always was.
     */
    #[RunInSeparateProcess]
    public function testAManifestPullFromAnOlderLabIsMarkedSentAtOnce(): void
    {
        $this->boot();
        self::withFailuresTable();
        self::waitingOffTheManifest();

        $body = json_decode((string) self::post([
            'labId' => 7, 'testType' => 'vl', 'manifestCode' => 'M-1', 'receipts' => 1,
        ])->getBody(), true);

        self::assertCount(24, $body['requests']);
        $sync = self::labSync();
        self::assertSame(0, $sync['u-26'], 'not on the manifest');
        unset($sync['u-26']);
        self::assertSame([1], array_values(array_unique($sync)), 'marked sent, no receipt expected');
    }

    #[RunInSeparateProcess]
    public function testAManifestTheLabConfirmsWaitsForTheReceipt(): void
    {
        $this->boot();
        self::withFailuresTable();
        self::waitingOffTheManifest();

        $body = json_decode((string) self::post([
            'labId' => 7, 'testType' => 'vl', 'manifestCode' => 'M-1', 'receipts' => 1, 'manifestReceipts' => 1,
        ])->getBody(), true);

        $sent = array_column($body['requests'], 'unique_id');
        self::assertCount(24, $sent, "the manifest's requests, not the lab's other waiting ones");
        $sync = self::labSync();
        self::assertSame(0, $sync['u-26'], 'waiting, and left for the lab\'s own pull');
        unset($sync['u-26']);
        self::assertSame([RequestsService::IN_FLIGHT], array_values(array_unique($sync)), 'waiting for the receipt');
    }

    #[RunInSeparateProcess]
    public function testAManifestPageTheLabConfirmsWaitsForTheReceipt(): void
    {
        $this->boot();
        self::withFailuresTable();

        $body = json_decode((string) self::post([
            'labId' => 7, 'testType' => 'vl', 'manifestCode' => 'M-1', 'receipts' => 1, 'manifestReceipts' => 1,
            'page' => ['size' => 5],
        ])->getBody(), true);

        self::assertCount(5, $body['requests']);
        self::assertTrue($body['paged']);
        $sync = self::labSync();
        foreach (array_column($body['requests'], 'unique_id') as $uniqueId) {
            self::assertSame(RequestsService::IN_FLIGHT, $sync[$uniqueId]);
        }
        self::assertSame(19, count(array_filter($sync, static fn(int $s): bool => $s === 0)), 'the rest untouched');
    }

    #[RunInSeparateProcess]
    public function testAManifestIsConfirmedOnlyWithReceiptsAsked(): void
    {
        $this->boot();
        self::withFailuresTable();

        self::post(['labId' => 7, 'testType' => 'vl', 'manifestCode' => 'M-1', 'manifestReceipts' => 1]);

        self::assertSame([1], array_values(array_unique(self::labSync())), 'marked sent');
    }

    #[RunInSeparateProcess]
    public function testAManifestIsSentWithoutReceiptsWhenTheStsCannotTakeThem(): void
    {
        // No request_sync_failures table: a receipt could not be recorded.
        $this->boot();

        self::post([
            'labId' => 7, 'testType' => 'vl', 'manifestCode' => 'M-1', 'receipts' => 1, 'manifestReceipts' => 1,
        ]);

        self::assertSame([1], array_values(array_unique(self::labSync())), 'marked sent');
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

        $response = json_decode((string) self::post($body)->getBody(), true);

        self::assertEqualsCanonicalizing(self::windowIds(), array_column($response['requests'], 'unique_id'));
        self::assertArrayNotHasKey('paged', $response, 'an older lab sees the body it always did');
        self::assertArrayNotHasKey('nextAfter', $response);
    }
}
