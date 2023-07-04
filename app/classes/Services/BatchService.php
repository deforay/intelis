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
        $batchQuery = 'SELECT MAX(batch_code_key)
                        FROM batch_details as bd
                        WHERE DATE(bd.request_created_datetime) = CURRENT_DATE';
        $batchResult = $this->db->query($batchQuery);

        if (!empty($batchResult[0]['MAX(batch_code_key)'])) {
            $code = $batchResult[0]['MAX(batch_code_key)'] + 1;
            $length = strlen($code);
            if ($length == 1) {
                $code = "00" . $code;
            } elseif ($length == 2) {
                $code = "0" . $code;
            }
        } else {
            $code = '001';
        }
        return $code;
    }

    public function excelColumnRange($lower, $upper): Generator
    {
        ++$upper;
        for ($i = $lower; $i !== $upper; ++$i) {
            yield $i;
        }
    }

}
