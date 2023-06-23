<?php

namespace App\Services;


use MysqliDb;
use App\Services\UsersService;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;

/**
 * Ui functions
 *
 * @author Amit
 */

class AppMenuService
{

    protected ?MysqliDb $db = null;
    protected string $table = 's_app_menu';
    protected CommonService $commonService;
    protected UsersService $usersService;

    public function __construct(?MysqliDb $db, CommonService $commonService, UsersService $usersService)
    {
        $this->db = $db ?? ContainerRegistry::get('db');
        $this->commonService = $commonService;
        $this->usersService = $usersService;
    }

    public function getAllActiveMenus($parentId = 0, $menuId = 0)
    {

        /** @var UsersService $usersService */
        $usersService = ContainerRegistry::get(UsersService::class);
        $this->db->where('status', 'active');
        if (!empty($menuId) && $menuId > 0) {
            $this->db->where('id', $menuId);
        }
        $response = [];
        $this->db->where('parent_id', $parentId);
        $this->db->orderBy("display_order", "asc");
        $menuData = $this->db->get($this->table);
        foreach ($menuData as $key => $menu) {
            $menu['access'] = true;
            if ($menu['link']  != "" && !empty($menu['link'])) {
                $menu['access'] = $usersService->isAllowed($menu['link']);
            }

            if ($menu['has_children'] == 'yes') {
                $menu['children'] = $this->getAllActiveMenus($menu['id']);
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
}
