<?php

namespace App\Services;

use DateTime;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use Laminas\Diactoros\ServerRequest;
use App\Registries\ContainerRegistry;

class StorageService
{

    protected ?DatabaseService $db;
    protected string $table = 'lab_storage';
    protected CommonService $commonService;

    public function __construct(?DatabaseService $db, ?CommonService $commonService)
    {
        $this->db = $db ?? ContainerRegistry::get(DatabaseService::class);
        $this->commonService = $commonService;
    }

    public function getLabStorage($allColumns = false, $condition = null, $onlyActive = true)
    {
        return once(function () use ($allColumns, $condition, $onlyActive) {
            
            if ($onlyActive) {
                $this->db->where('storage_status', 'active');
            }

            if (!empty($condition)) {
                $this->db->where($condition);
            }

            $this->db->orderBy("storage_code", "asc");

            if ($allColumns) {
                return $this->db->get("lab_storage");
            } else {
                $response = [];
                $results = $this->db->get("lab_storage", null, "storage_id,storage_code");
                foreach ($results as $row) {
                    $response[$row['storage_id']] = $row['storage_code'];
                }
                return $response;
            }
        });
    }

    public function saveLabStorage($params)
    {
        try {
            
            if (isset($params['storageId']) && $params['storageId'] != "") {
                $data = array(
                    'storage_code' 	=> $params['storageCode'],
                    'lab_id' 	=> $params['labId'],
                    'storage_status' => $params['storageStatus'],
                    'updated_datetime'	=> DateUtility::getCurrentDateTime()
                );
                $this->db->where('storage_id', base64_decode((string) $params['storageId']));
                $save = $this->db->update($this->table, $data);
            } else {
                $data = array(
                    'storage_id' => $this->commonService->generateUUID(),
                    'storage_code' 	=> $params['storageCode'],
                    'lab_id' 	=> $params['labId'],
                    'storage_status' => $params['storageStatus'],
                    'updated_datetime' => DateUtility::getCurrentDateTime()
                );
                $save = $this->db->insert($this->table, $data);
            }
    
            $this->db->commitTransaction();
            return $save;
        } catch (Exception $e) {
            $this->db->rollbackTransaction();
            throw $e;
        }
    }

    public function getStorageById(?string $storageId = null): ?array
    {
        return once(function () use ($storageId) {
            if (!empty($storageId)) {
                $this->db->where('storage_id', $storageId);
                $this->db->where('storage_status', 'active');
                $return = $this->db->getOne("$this->table");
            } else {
                $return = null;
            }
            return $return;
        });
    }

}