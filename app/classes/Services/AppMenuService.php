<?php

namespace App\Services;

use App\Services\CommonService;
use App\Services\SystemService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

use const COUNTRY\DRC;

final class AppMenuService
{
    protected DatabaseService $db;
    protected string $table = 's_app_menu';

    /**
     * has_children value of a menu row the sidebar shows as one link to a page
     * that lists the rows below it (Admin > Test Settings).
     */
    public const string HUB = 'hub';

    /**
     * Pages that only the DRC request forms feed (freezer, rack, box, position,
     * volume). Other countries never record storage, so these stay hidden there.
     */
    private const array DRC_ONLY_LINKS = [
        '/common/reference/lab-storage.php',
        '/vl/program-management/sample-storage-reports.php',
    ];

    public function __construct(DatabaseService $db, protected CommonService $commonService)
    {
        $this->db = $db ?? ContainerRegistry::get(DatabaseService::class);
    }

    public function getMenuDisplayTexts(): array
    {
        $this->db->where('status', 'active');
        $this->db->orderBy("display_order", "asc");
        $menuData = $this->db->get($this->table, null, 'display_text');
        $response = [];
        foreach ($menuData as $menu) {
            $response[] = $menu['display_text'];
        }
        return $response;
    }

    public function getMenu($parentId = 0, $menuId = 0): array
    {
        // Settled before the query is built: on a cache miss the instance type is
        // read through the same shared DatabaseService, which would otherwise run
        // that read with this query's WHERE conditions attached.
        if ($this->commonService->isSTSInstance()) {
            $actsAsLab = ($_SESSION['accessType'] ?? '') === 'testing-lab';
            $mode = $actsAsLab
                ? "(IFNULL(show_mode,'') IN ('', 'sts', 'lis', 'always'))"
                : "(IFNULL(show_mode,'') = '' OR show_mode = 'sts' OR show_mode = 'always')";
        } elseif ($this->commonService->isLISInstance()) {
            $mode = "(IFNULL(show_mode,'') = '' OR show_mode = 'lis' OR show_mode = 'always')";
        } else {
            $mode = "(IFNULL(show_mode,'') = '' OR show_mode = 'always')";
        }

        $activeModules = SystemService::getActiveModules();
        $activeModulesInfo = implode("','", $activeModules);
        $this->db->where(
            "module IN ('$activeModulesInfo') AND (sub_module IN ('$activeModulesInfo') OR sub_module IS NULL)"
        );
        $this->db->where('status', 'active');
        if (!empty($menuId) && $menuId > 0) {
            $this->db->where('id', $menuId);
        }
        $this->db->where($mode);
        $this->db->where('parent_id', $parentId);
        $this->db->orderBy("display_order", "asc");
        $menuData = $this->db->get($this->table);

        // Cloud-LIS non-admin operators get a stripped admin section: User
        // management, Instruments, Audit Trail (lab-scoped) and the Log File Viewer
        // (instance-wide). This is a WHITELIST (fails closed), so any future admin
        // page is hidden by default until explicitly allowed here. No-op for the
        // super-admin and every non-cloud-LIS user. Headers with no surviving child
        // are pruned by the empty-children check below.
        $restrictAdmin = $this->commonService->isCloudLisNonAdmin();
        $allowedAdminLinks = [
            '/users/users.php',
            '/instruments/instruments.php',
            '/admin/monitoring/audit-trail.php',
            '/admin/monitoring/activity-log.php',
            '/admin/monitoring/log-files.php',
        ];

        $isDrc = (int) $this->commonService->getGlobalConfig('vl_form') === DRC;

        $response = [];
        foreach ($menuData as $key => $menu) {
            $isHub = $menu['has_children'] === self::HUB;
            $menu['access'] = true;
            // A hub page has no privilege of its own: it opens for anyone who can
            // open at least one page listed on it (checked below).
            if (!$isHub && !empty($menu['link']) && !str_starts_with((string) $menu['link'], '#')) {
                $menu['access'] = _isAllowed($menu['link']);
            }

            if (!$isDrc && in_array($menu['link'], self::DRC_ONLY_LINKS, true)) {
                $menu['access'] = false;
            }

            if (
                $restrictAdmin
                && ($menu['module'] ?? '') === 'admin'
                && !empty($menu['link'])
                && !str_starts_with((string) $menu['link'], '#')
                && !in_array($menu['link'], $allowedAdminLinks, true)
            ) {
                $menu['access'] = false;
            }

            if ($menu['has_children'] == 'yes') {
                $menu['children'] = $this->getMenu($menu['id']);
                if (empty($menu['children'])) {
                    $menu['access'] = false;
                }
            } elseif ($isHub && $menu['access']) {
                // The sidebar draws a hub as a single link, so its pages go in
                // 'hub_children' (read by the hub page and Spotlight) rather than
                // 'children', and into inner_pages so the link stays highlighted
                // on every one of them.
                $menu['hub_children'] = $this->getMenu($menu['id']);
                if (empty($menu['hub_children'])) {
                    $menu['access'] = false;
                } else {
                    $menu['inner_pages'] = implode(',', array_unique(array_filter([
                        ...explode(',', (string) $menu['inner_pages']),
                        ...self::collectPages($menu['hub_children']),
                    ])));
                }
            }

            if ($menu['access']) {
                $response[$key] = $menu;
            }
        }
        return $response;
    }

    /**
     * The hub row at $link with its allowed groups in 'hub_children', or null
     * when the user can open none of the pages listed on it.
     */
    public function getHub(string $link): ?array
    {
        $this->db->where('link', $link);
        $this->db->where('has_children', self::HUB);
        $row = $this->db->getOne($this->table, ['id', 'parent_id']);
        if (empty($row)) {
            return null;
        }
        $hub = $this->getMenu((int) $row['parent_id'], (int) $row['id']);
        return $hub === [] ? null : reset($hub);
    }

    /**
     * Every link and inner page under $menuItems, at any depth.
     */
    private static function collectPages(array $menuItems): array
    {
        $pages = [];
        foreach ($menuItems as $item) {
            $link = (string) ($item['link'] ?? '');
            if ($link !== '' && !str_starts_with($link, '#')) {
                $pages[] = $link;
            }
            if (!empty($item['inner_pages'])) {
                $pages = [...$pages, ...explode(',', (string) $item['inner_pages'])];
            }
            if (!empty($item['children'])) {
                $pages = [...$pages, ...self::collectPages($item['children'])];
            }
        }
        return $pages;
    }

    /**
     * Insert a new menu item into the database.
     *
     * @param array $menuData Associative array of the menu item fields and their values.
     * @return bool Returns true if the item was successfully inserted, false otherwise.
     */
    public function insertMenu(array $menuData): bool|int
    {
        // Check if the item already exists based on parent_id and link
        $this->db->where('module', $menuData['module']);
        $this->db->where('link', $menuData['link']);
        $exists = $this->db->getOne($this->table);

        if ($exists) {
            // Menu item already exists, do not insert
            return $exists['id'];
        }

        // Insert the new menu item
        $inserted = $this->db->insert($this->table, $menuData);
        if (!$inserted) {
            LoggerUtility::logError(
                "Failed to insert " . $menuData['module'] . ":" . $menuData['parent_id'] . ":"
                    . $menuData['display_text'] . " menu"
            );
            return false;
        } else {
            return $this->db->getInsertId();
        }
    }
}
