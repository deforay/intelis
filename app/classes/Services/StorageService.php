<?php

namespace App\Services;


use Throwable;
use App\Utilities\DateUtility;
use App\Utilities\MemoUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

final class StorageService
{
    protected string $table = 'lab_storage';
    protected DatabaseService $db;

    public function __construct(DatabaseService $db, protected CommonService $commonService)
    {
        $this->db = $db ?? ContainerRegistry::get(DatabaseService::class);
    }

    public function getLabStorage($allColumns = false, $condition = null, $onlyActive = true): mixed
    {
        return MemoUtility::remember(function () use ($allColumns, $condition, $onlyActive) {

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

            if (empty($params['storageCode'])) {
                return false;
            }

            if (isset($params['storageId']) && $params['storageId'] != "" && !empty($params['storageId'])) {
                $data = ['storage_code'     => $params['storageCode'], 'storage_status' => $params['storageStatus'], 'updated_datetime'    => DateUtility::getCurrentDateTime()];
                $this->db->where('storage_id', base64_decode((string) $params['storageId']));
                $save = $this->db->update($this->table, $data);
            } else {
                $data = ['storage_id' => MiscUtility::generateULID(), 'storage_code'     => $params['storageCode'], 'lab_id'     => $_SESSION['instance']['labId'], 'storage_status' => $params['storageStatus'], 'updated_datetime' => DateUtility::getCurrentDateTime()];
                $save = $this->db->insert($this->table, $data);
            }

            $this->db->commitTransaction();
            return $save;
        } catch (Throwable $e) {
            $this->db->rollbackTransaction();
            throw $e;
        }
    }

    public function getStorageById(?string $storageId = null): ?array
    {
        return MemoUtility::remember(function () use ($storageId) {
            if ($storageId !== null && $storageId !== '' && $storageId !== '0') {
                $this->db->where('storage_id', $storageId);
                $this->db->where('storage_status', 'active');
                $return = $this->db->getOne("$this->table");
            } else {
                $return = null;
            }
            return $return;
        });
    }

    public function getStorageByCode(?string $storageCode = null): ?array
    {
        return MemoUtility::remember(function () use ($storageCode) {
            if ($storageCode !== null && $storageCode !== '' && $storageCode !== '0') {
                $this->db->where('storage_code', $storageCode);
                $this->db->where('storage_status', 'active');
                $return = $this->db->getOne("$this->table");
            } else {
                $return = null;
            }
            return $return;
        });
    }

    public function getFreezerHistoryById($historyId): mixed
    {
        return MemoUtility::remember(function () use ($historyId) {
            if (!empty($historyId)) {
                $this->db->where('history_id', $historyId);
                $return = $this->db->getOne("lab_storage_history");
            } else {
                $return = null;
            }
            return $return;
        });
    }

    public function updateSampleStorageStatus($data)
    {
        return $this->db->insert('lab_storage_history', $data);
    }

    public function getFreezerListByLabId($labId)
    {
        $query = "SELECT storage_id, storage_code
        FROM lab_storage
        WHERE lab_id = $labId";
        return $this->db->rawQuery($query);
    }
}
