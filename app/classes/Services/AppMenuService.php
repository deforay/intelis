<?php

namespace App\Services;

use App\Services\CommonService;
use App\Services\SystemService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

final class AppMenuService
{
    protected DatabaseService $db;
    protected string $table = 's_app_menu';

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
        $activeModules = SystemService::getActiveModules();
        $activeModulesInfo = implode("','", $activeModules);
        $this->db->where("module IN ('$activeModulesInfo') AND (sub_module IN ('$activeModulesInfo') OR sub_module IS NULL)");
        $this->db->where('status', 'active');
        if (!empty($menuId) && $menuId > 0) {
            $this->db->where('id', $menuId);
        }

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
            '/admin/monitoring/log-files.php',
        ];

        $response = [];
        foreach ($menuData as $key => $menu) {
            $menu['access'] = true;
            if ($menu['link'] != "" && !empty($menu['link']) && !str_starts_with((string) $menu['link'], '#')) {
                $menu['access'] = _isAllowed($menu['link']);
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
            }

            if ($menu['access']) {
                $response[$key] = $menu;
            }
        }
        return $response;
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
            LoggerUtility::logError("Failed to insert " . $menuData['module'] . ":" . $menuData['parent_id'] . ":" . $menuData['display_text'] . " menu");
            return false;
        } else {
            return $this->db->getInsertId();
        }
    }
}
