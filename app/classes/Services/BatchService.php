<?php



namespace App\Services;

use MysqliDb;
use Generator;
use App\Registries\ContainerRegistry;

class BatchService
{

    protected ?MysqliDb $db = null;

    public function __construct(?MysqliDb $db = null)
    {
        $this->db = $db ?? ContainerRegistry::get('db');
    }

    public function doesBatchCodeExist($code)
    {
        $this->db->where("batch_code", $code);
        return $this->db->getOne("batch_details");
    }

    public function createBatchCode()
    {
        $batchQuery = 'SELECT IFNULL(MAX(batch_code_key), 0) + 1 AS maxId
                        FROM batch_details as bd
                        WHERE DATE(bd.request_created_datetime) = CURRENT_DATE';
        $batchResult = $this->db->rawQueryOne($batchQuery);

        $batchCode  = date('Ymd') . sprintf("%03s", $batchResult['maxId']);

        return [$batchResult['maxId'], $batchCode];
    }


    public function excelColumnRange($lower, $upper): Generator
    {
        ++$upper;
        for ($i = $lower; $i !== $upper; ++$i) {
            yield $i;
        }
    }
}
