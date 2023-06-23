<?php

namespace App\Services;


use MysqliDb;
use Exception;
use DateTimeImmutable;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

/**
 * Ui functions
 *
 * @author Amit
 */

class UiService
{

    protected ?MysqliDb $db = null;
    protected string $table = 'menus';
    protected CommonService $commonService;

    public function __construct($db = null, $commonService = null)
    {
        $this->db = $db ?? ContainerRegistry::get('db');
        $this->commonService = $commonService;
    }

    public function getAllActiveMenus($menuId = 0, $parentId = 0)
    {
        $this->db->where('status', 'active');
        if (!empty($menuId) && $menuId > 0) {
            $this->db->where('id', $menuId);
        }
      
        $this->db->where('parent_id', $parentId);
        $this->db->orderBy("display_order", "asc");
        $response = $this->db->get("menus");
        return $response;
    }
}