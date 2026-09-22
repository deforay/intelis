<?php

namespace App\Services\STS;

use Throwable;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;

/**
 * Stores the table-mapped metadata a lab sends the STS (remote/remote/lab-metadata-receiver.php):
 * its storage, instruments and users. The endpoint authenticates the lab and runs this
 * inside its transaction; kept out of the script so it can be tested.
 */
final class LabMetadataService
{
    public function __construct(
        private readonly DatabaseService $db,
        private readonly CommonService $general
    ) {
    }

    /**
     * @param array{primaryKey?: array<int, string>, table?: array<int, string>, data?: array<int, iterable>} $tableInfo
     *        by position: each table, the column rows are matched on, and its rows
     * @return int rows processed
     */
    public function storeTables(array $tableInfo): int
    {
        $counter = 0;
        if ($tableInfo === []) {
            return $counter;
        }
        foreach (array_keys($tableInfo['table']) as $j) {
            $primaryKey = $checkColumn = $tableInfo['primaryKey'][$j];
            $tableName = $tableInfo['table'][$j];

            $emptyTableArray = $this->general->getTableFieldsAsArray($tableName);
            if (empty($emptyTableArray)) {
                continue;
            }
            $dataResultSet = $tableInfo['data'][$j];
            $deletedId = [];
            foreach ($dataResultSet as $key => $resultRow) {
                $counter++;
                // A row that is not a row: skipped here, because thrown it would have
                // rolled back the lab's whole metadata sync.
                if (!is_array($resultRow) || $resultRow === []) {
                    continue;
                }
                // Only the columns the lab sent that this table has. A column the lab
                // does not have (it runs an older release) is left alone, not set to NULL.
                $data = array_intersect_key($resultRow, $emptyTableArray);
                $data['updated_datetime'] = DateUtility::getCurrentDateTime();

                try {
                    if ($tableName === 'instrument_machines') {
                        $this->storeMachine($data);
                    } elseif ($tableName === 'instrument_controls') {
                        if (
                            (in_array($data['instrument_id'], $deletedId)) === false &&
                            !empty($data['instrument_id'])
                        ) {
                            $deletedId[] = $data['instrument_id'];
                            $this->db->where('instrument_id', $data['instrument_id']);
                            $this->db->delete($tableName);
                        }
                        $id = $this->db->setQueryOption(['IGNORE'])->insert($tableName, $data);
                    } else {
                        if ($tableName === 'user_details') {
                            // Unset unwanted columns
                            foreach (['login_id', 'role_id', 'password', 'status'] as $unsetKey) {
                                unset($data[$unsetKey]);
                            }

                            // From the row as sent: the image is not a column, so it
                            // was filtered out above and never stored.
                            self::saveUserSignature($resultRow);

                            // Invalidate file cache for users count
                            _invalidateFileCacheByTags(['users_count']);
                        }

                        $sResult = null;
                        if (!empty($data[$checkColumn])) {
                            $this->db->reset();
                            $this->db->where($checkColumn, $data[$checkColumn]);
                            $sResult = $this->db->getOne($tableName, [$primaryKey]);
                        }
                        if (!empty($sResult)) {
                            $this->db->where($primaryKey, $sResult[$primaryKey]);
                            $id = $this->db->update($tableName, $data);
                        } else {
                            $id = $this->db->upsert($tableName, $data);
                        }
                    }
                } catch (Throwable $e) {
                    LoggerUtility::logError("Error when processing for $tableName : " . $e->getMessage(), [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'last_db_errno' => $this->db->getLastErrno(),
                        'last_db_query' => $this->db->getLastQuery(),
                        'last_db_error' => $this->db->getLastError(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    continue;
                }
            }
        }
        return $counter;
    }

    /**
     * A lab's machine, matched on its instrument and the lab's own id for it: every
     * lab numbers its machines from 1, and results carry that id (import_machine_name)
     * next to the instrument. A lab never deletes a machine, so one missing from a
     * batch stays.
     *
     * Before 5.7.78 the key is config_machine_id alone, and another lab's machine
     * with the same id stops the insert rather than being overwritten.
     */
    private function storeMachine(array $data): void
    {
        $instrumentId = $data['instrument_id'] ?? null;
        $machineId = filter_var($data['config_machine_id'] ?? null, FILTER_VALIDATE_INT);
        if (empty($instrumentId) || $machineId === false) {
            // Stored without both, each resend would add another copy.
            return;
        }
        $data['config_machine_id'] = $machineId;

        $exists = $this->db->rawQueryOne(
            'SELECT 1 AS found FROM instrument_machines WHERE instrument_id = ? AND config_machine_id = ?',
            [$instrumentId, $machineId]
        );
        if (!empty($exists)) {
            $this->db->where('instrument_id', $instrumentId);
            $this->db->where('config_machine_id', $machineId);
            $this->db->update('instrument_machines', $data);
            return;
        }
        $this->db->setQueryOption(['IGNORE'])->insert('instrument_machines', $data);
    }

    /** Image types a signature may be stored as. */
    private const array SIGNATURE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    /**
     * Store the signature image a lab sent with a user. The name comes from the lab,
     * so only its last part is used, and only as an image: a name that climbs out of
     * the signatures folder, or a script, is never written.
     */
    private static function saveUserSignature(array $row): void
    {
        if (empty($row['signature_image_content']) || empty($row['signature_image_filename'])) {
            return;
        }
        $filename = basename(str_replace('\\', '/', (string) $row['signature_image_filename']));
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($filename === '' || $filename[0] === '.' || !in_array($extension, self::SIGNATURE_EXTENSIONS, true)) {
            LoggerUtility::logWarning('Skipped a user signature with an unusable file name', [
                'user_id' => $row['user_id'] ?? null,
            ]);
            return;
        }
        $content = base64_decode((string) $row['signature_image_content'], true);
        if ($content === false || $content === '') {
            return;
        }

        $signatureDir = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'users-signature';
        MiscUtility::makeDirectory($signatureDir);
        file_put_contents($signatureDir . DIRECTORY_SEPARATOR . $filename, $content);
    }
}
