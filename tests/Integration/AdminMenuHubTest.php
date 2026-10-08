<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\AppMenuService;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;
use App\Registries\ContainerRegistry;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * sys/migrations/5.7.83.sql and the Test Settings hub it creates.
 *
 * The migration regroups the admin menu without changing any link, so no role
 * gains or loses a page. The seven per-test config groups move under one hub row
 * that the sidebar shows as a single link; the hub page and Spotlight list the
 * pages below it, filtered by active module and by role exactly as the sidebar
 * filtered them before.
 *
 * Each test runs in its own process: getMenu() reads the enabled modules from the
 * SYSTEM_CONFIG constant, which other tests define without any.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdminMenuHubTest extends TestCase
{
    private const DATABASE = 'intelis_admin_menu_hub_test';

    protected function setUp(): void
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        $database = self::DATABASE . '_' . getmypid();
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', [
                'database' => ['db' => $database],
                'modules' => ['vl' => true, 'eid' => true, 'tb' => false, 'cd4' => false, 'generic-tests' => false],
            ]);
        }
        $db = LegacyAppHarness::boot($database, ['s_app_menu', 'system_config', 'global_config']);
        $db->insert('system_config', ['name' => 'sc_user_type', 'value' => 'vluser']);
        $db->insert('system_config', ['name' => 'sc_version', 'value' => '5.7.82']);
        $this->seedMenu();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (getenv('INTELIS_TEST_DB_HOST') && getenv('INTELIS_TEST_DB_USER')) {
            LegacyAppHarness::shutdown();
        }
    }

    /** The admin menu as 5.7.82 left it, cut down to a few rows per group. */
    private function seedMenu(): void
    {
        $rows = [
            // id, module, sub_module, is_header, text, link, inner_pages, has_children, parent, order
            [2, 'admin', null, 'no', 'ADMIN', null, null, 'yes', 0, 2],
            [3, 'admin', null, 'no', 'Access Control', '', null, 'yes', 2, 3],
            [4, 'admin', null, 'no', 'Roles', '/roles/roles.php', null, 'no', 3, 4],
            [5, 'admin', null, 'no', 'Users', '/users/users.php', null, 'no', 3, 5],
            [6, 'admin', null, 'no', 'Facilities', '/facilities/facilities.php', null, 'no', 2, 6],
            [7, 'admin', null, 'no', 'Monitoring', null, null, 'yes', 2, 7],
            [15, 'admin', null, 'no', 'User Activity Log', '/admin/monitoring/activity-log.php', null, 'no', 7, 15],
            [529, 'admin', null, 'no', 'Sample Referral Network',
                '/reports/sample-referral-network.php', null, 'no', 7, 21],
            [531, 'admin', null, 'no', 'Instrument Activity',
                '/reports/interface-machine-activity.php', null, 'no', 7, 23],
            [532, 'admin', null, 'no', 'Lab Performance Indicators',
                '/reports/lab-performance-indicators.php', null, 'no', 7, 24],
            [8, 'admin', null, 'no', 'System Configuration', null, null, 'yes', 2, 8],
            [19, 'admin', null, 'no', 'General Configuration',
                '/global-config/editGlobalConfig.php', null, 'no', 8, 19],
            [21, 'admin', null, 'no', 'Geographical Divisions',
                '/common/reference/geographical-divisions-details.php', null, 'no', 8, 21],
            [9, 'admin', 'generic-tests', 'no', 'Other Lab Tests Config', null, null, 'yes', 2, 9],
            [24, 'admin', null, 'no', 'Sample Types',
                '/generic-tests/configuration/sample-types/generic-sample-type.php', null, 'no', 9, 24],
            [25, 'admin', null, 'no', 'Testing Reasons',
                '/generic-tests/configuration/testing-reasons/generic-testing-reason.php', null, 'no', 9, 25],
            [10, 'admin', 'vl', 'no', 'VL Config', null, null, 'yes', 2, 10],
            [33, 'admin', null, 'no', 'ART Regimen', '/vl/reference/vl-art-code-details.php', null, 'no', 10, 26],
            [34, 'admin', null, 'no', 'Rejection Reasons',
                '/vl/reference/vl-sample-rejection-reasons.php', null, 'no', 10, 27],
            [35, 'admin', null, 'no', 'Sample Type', '/vl/reference/vl-sample-type.php',
                '/vl/reference/add-vl-sample-type.php,/vl/reference/edit-vl-sample-type.php', 'no', 10, 28],
            [11, 'admin', 'eid', 'no', 'EID Config', null, null, 'yes', 2, 11],
            [40, 'admin', null, 'no', 'Sample Type', '/eid/reference/eid-sample-type.php', null, 'no', 11, 40],
            [14, 'admin', 'tb', 'no', 'TB Config', null, null, 'yes', 2, 14],
            [57, 'admin', null, 'no', 'Sample Type', '/tb/reference/tb-sample-type.php', null, 'no', 14, 57],
            [372, 'admin', 'cd4', 'no', 'CD4 Config', null, null, 'yes', 2, 42],
            [396, 'admin', 'cd4', 'no', 'CD4 Config', 'cd4-config', null, 'yes', 2, 42],
            [455, 'admin', 'cd4', 'no', 'CD4 Config', '#cd4-config', null, 'yes', 2, 42],
            [456, 'admin', 'cd4', 'no', 'Sample Type', '/cd4/reference/cd4-sample-type.php', null, 'no', 455, 43],
            [536, 'dashboard', null, 'no', 'SAMPLE AGEING REPORT', '/reports/sample-ageing.php', null, 'no', 0, 1],
        ];
        foreach ($rows as [$id, $module, $subModule, $isHeader, $text, $link, $inner, $hasChildren, $parent, $order]) {
            LegacyAppHarness::db()->insert('s_app_menu', [
                'id' => $id, 'module' => $module, 'sub_module' => $subModule, 'is_header' => $isHeader,
                'display_text' => $text, 'link' => $link, 'inner_pages' => $inner, 'show_mode' => 'always',
                'has_children' => $hasChildren, 'parent_id' => $parent, 'display_order' => $order,
                // 5.7.50 hid the Sample Ageing Report entries; the rest are live.
                'status' => $id === 536 ? 'inactive' : 'active',
            ]);
        }
    }

    private function migrate(): void
    {
        $sql = (string) file_get_contents(ROOT_PATH . '/sys/migrations/5.7.83.sql');
        $sql = preg_replace('/^--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', explode(";\n", (string) $sql))) as $statement) {
            LegacyAppHarness::db()->rawQuery(rtrim($statement, ';'));
        }
    }

    /** @return array<string, mixed> */
    private function row(string $where): array
    {
        return LegacyAppHarness::db()->rawQueryOne("SELECT * FROM s_app_menu WHERE $where") ?: [];
    }

    private function idOf(string $link): int
    {
        return (int) ($this->row("link = '$link'")['id'] ?? 0);
    }

    private function menuService(): AppMenuService
    {
        return ContainerRegistry::get(AppMenuService::class);
    }

    /** @return array<string, array<string, mixed>> ADMIN's visible children keyed by label */
    private function adminGroups(): array
    {
        $admin = array_values(array_filter($this->menuService()->getMenu(), fn($m) => (int) $m['id'] === 2));
        self::assertCount(1, $admin, 'ADMIN is visible');
        return array_column($admin[0]['children'], null, 'display_text');
    }

    public function testTheMigrationRegroupsTheMenuAndCanRunTwice(): void
    {
        $linksBefore = $this->pageLinks();
        $this->migrate();
        $afterFirstRun = $this->menuRows();
        $this->migrate();
        self::assertEquals($afterFirstRun, $this->menuRows());

        $hubId = $this->idOf('/admin/test-settings.php');
        $listsId = $this->idOf('#reference-lists');
        $reportsId = $this->idOf('#reports');
        self::assertGreaterThan(0, $hubId);
        self::assertSame('hub', $this->row("id = $hubId")['has_children']);
        self::assertSame('2', (string) $this->row("id = $hubId")['parent_id']);
        self::assertSame('Users & Roles', $this->row('id = 3')['display_text']);
        self::assertSame('Settings', $this->row('id = 8')['display_text']);
        self::assertSame((string) $listsId, (string) $this->row('id = 21')['parent_id']);
        self::assertSame('8', (string) $this->row('id = 19')['parent_id']);

        // Each config group is now a tab of the hub, named for its test.
        $groups = LegacyAppHarness::db()->rawQuery(
            "SELECT id, display_text FROM s_app_menu WHERE parent_id = $hubId ORDER BY display_order"
        );
        self::assertSame(
            ['HIV Viral Load', 'EID', 'TB', 'CD4', 'Custom Tests'],
            array_column($groups, 'display_text')
        );
        self::assertSame('Sample Types', $this->row('id = 35')['display_text']);
        self::assertSame('Test Reasons', $this->row('id = 25')['display_text']);
        // Same order on every tab: Sample Types before Rejection Reasons before test-specific lists.
        self::assertSame(
            ['Sample Types', 'Rejection Reasons', 'ART Regimen'],
            array_column(LegacyAppHarness::db()->rawQuery(
                'SELECT display_text FROM s_app_menu WHERE parent_id = 10 ORDER BY display_order'
            ), 'display_text')
        );

        // The empty CD4 headers are gone; the live one keeps its page.
        self::assertSame([], $this->row('id IN (372, 396)'));
        self::assertSame('456', (string) $this->row("parent_id = 455")['id']);

        // Reports leave Monitoring for the new top-level section, keeping their module.
        self::assertSame('yes', $this->row("id = $reportsId")['is_header']);
        foreach ([536, 532, 531, 529] as $order => $id) {
            $report = $this->row("id = $id");
            self::assertSame((string) $reportsId, (string) $report['parent_id']);
            self::assertSame((string) ($order + 1), (string) $report['display_order']);
        }
        self::assertSame('admin', $this->row('id = 532')['module']);
        self::assertSame('Sample Ageing Report', $this->row('id = 536')['display_text']);
        self::assertSame('inactive', $this->row('id = 536')['status'], 'still hidden, as 5.7.50 left it');
        self::assertSame('7', (string) $this->row('id = 15')['parent_id']);

        // No page link changed, so no privilege can have changed.
        self::assertSame($linksBefore, array_values(array_diff($this->pageLinks(), ['/admin/test-settings.php'])));
    }

    /**
     * Every row without updated_datetime, which each run refreshes.
     *
     * @return list<array<string, mixed>>
     */
    private function menuRows(): array
    {
        return array_map(function (array $row): array {
            unset($row['updated_datetime']);
            return $row;
        }, LegacyAppHarness::db()->rawQuery('SELECT * FROM s_app_menu ORDER BY id'));
    }

    /** @return list<string> */
    private function pageLinks(): array
    {
        return array_column(LegacyAppHarness::db()->rawQuery(
            "SELECT link FROM s_app_menu WHERE link LIKE '/%' ORDER BY link"
        ), 'link');
    }

    /**
     * A fresh install starts from init.sql, whose CD4 pages sit under a group
     * with no link beside an empty #cd4-config group: the reverse of the upgraded
     * menu seeded above. Every settings page must still end up on a hub tab.
     */
    public function testAFreshInstallMenuPutsEverySettingsPageOnATab(): void
    {
        $initSql = (string) file_get_contents(ROOT_PATH . '/sql/init.sql');
        self::assertSame(1, preg_match('/^INSERT INTO `s_app_menu` VALUES .*;$/m', $initSql, $seed));
        LegacyAppHarness::db()->rawQuery('DELETE FROM s_app_menu');
        LegacyAppHarness::db()->rawQuery(rtrim($seed[0], ';'));
        $settingsPages = $this->settingsPageIds();
        self::assertNotEmpty($settingsPages);

        $this->migrate();
        $afterFirstRun = $this->menuRows();
        $this->migrate();
        self::assertEquals($afterFirstRun, $this->menuRows());

        $hubId = $this->idOf('/admin/test-settings.php');
        $onTabs = array_map('intval', array_column(LegacyAppHarness::db()->rawQuery(
            "SELECT p.id FROM s_app_menu p JOIN s_app_menu g ON g.id = p.parent_id WHERE g.parent_id = $hubId"
        ), 'id'));
        self::assertSame([], array_values(array_diff($settingsPages, $onTabs)), 'every settings page is on a tab');
        self::assertSame([], $this->row("display_text = 'CD4 Config'"));
        $cd4 = $this->row("parent_id = $hubId AND sub_module = 'cd4'");
        self::assertSame('#cd4-config', $cd4['link']);
        self::assertSame('CD4', $cd4['display_text']);
    }

    /** @return list<int> pages under any per-test config group */
    private function settingsPageIds(): array
    {
        return array_map('intval', array_column(LegacyAppHarness::db()->rawQuery(
            "SELECT p.id FROM s_app_menu p JOIN s_app_menu g ON g.id = p.parent_id
              WHERE g.parent_id = 2 AND g.sub_module IS NOT NULL AND p.link LIKE '/%'"
        ), 'id'));
    }

    public function testTheHubIsOneSidebarLinkThatStaysHighlightedOnItsPages(): void
    {
        $this->migrate();
        $_SESSION = ['roleId' => 1];

        $hub = $this->adminGroups()['Test Settings'];

        self::assertSame('/admin/test-settings.php', $hub['link']);
        self::assertArrayNotHasKey('children', $hub, 'the sidebar draws no sub-menu for the hub');
        $pages = explode(',', (string) $hub['inner_pages']);
        self::assertContains('/vl/reference/vl-sample-type.php', $pages);
        self::assertContains('/vl/reference/edit-vl-sample-type.php', $pages);
        self::assertContains('/eid/reference/eid-sample-type.php', $pages);
        // Modules switched off stay off.
        self::assertNotContains('/tb/reference/tb-sample-type.php', $pages);
        self::assertSame(['HIV Viral Load', 'EID'], array_column($hub['hub_children'], 'display_text'));
    }

    public function testTheHubListsOnlyThePagesTheRoleCanOpen(): void
    {
        $this->migrate();
        $_SESSION = ['roleId' => 4, 'privileges' => ['/vl/reference/vl-sample-type.php' => true]];

        self::assertArrayHasKey('Test Settings', $this->adminGroups());
        $hub = $this->menuService()->getHub('/admin/test-settings.php');

        self::assertNotNull($hub);
        self::assertCount(1, $hub['hub_children']);
        $vl = reset($hub['hub_children']);
        self::assertSame('HIV Viral Load', $vl['display_text']);
        self::assertSame(['/vl/reference/vl-sample-type.php'], array_values(array_column($vl['children'], 'link')));
    }

    public function testARoleWithNoPageOnTheHubNeitherSeesNorOpensIt(): void
    {
        $this->migrate();
        $_SESSION = ['roleId' => 4, 'privileges' => ['/users/users.php' => true]];

        self::assertArrayNotHasKey('Test Settings', $this->adminGroups());
        self::assertNull($this->menuService()->getHub('/admin/test-settings.php'));
    }
}
