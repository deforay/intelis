<?php

use const SAMPLE_STATUS\TEST_FAILED;
use const SAMPLE_STATUS\PENDING_APPROVAL;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\REORDERED_FOR_TESTING;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\ACCEPTED;
use App\Exceptions\SystemException;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

try {
    /** @var DatabaseService $db */
    $db = ContainerRegistry::get(DatabaseService::class);

    $importedBy = $_SESSION['userId'] ?? null;
    if (empty($importedBy)) {
        throw new SystemException('User ID is not set in session.');
    }

    // Update failed/error results to ON_HOLD
    $db->where('imported_by', $importedBy);
    $db->where("IFNULL(result,'') !=''");
    $db->where("(result LIKE 'fail%' OR result = 'failed' OR result LIKE 'err%' OR result LIKE 'error')");
    $db->update('temp_sample_import', [
        'result_status' => TEST_FAILED
    ]);

    // Update eligible rows to ACCEPTED
    $statusCodes = [
        PENDING_APPROVAL,
        RECEIVED_AT_TESTING_LAB,
        REORDERED_FOR_TESTING,
        RECEIVED_AT_CLINIC
    ];
    $statusCodes = implode(",", $statusCodes);
    $eligible = "(IFNULL(result_status,'') = '' OR result_status IN ($statusCodes))";

    // Named before they are passed over, because the point of saying anything is
    // that somebody has to go and look at them. A machine file whose result
    // column did not parse still produces a row here, and Accept All used to
    // sweep those up with the rest: process-vl.php and its siblings then wrote
    // them through to the form table as Accepted holding no result at all.
    // The failed-result update just above already checks the result is present;
    // this one did not.
    $skipped = $db->rawQueryOne(
        "SELECT COUNT(*) AS total,
                GROUP_CONCAT(sample_code ORDER BY sample_code SEPARATOR ', ') AS codes
           FROM temp_sample_import
          WHERE imported_by = ? AND $eligible AND IFNULL(result,'') = ''",
        [$importedBy]
    );

    $db->where('imported_by', $importedBy);
    $db->where($eligible);
    $db->where("IFNULL(result,'') != ''");
    $db->update('temp_sample_import', [
        'result_status' => ACCEPTED
    ]);

    $skippedCount = (int) ($skipped['total'] ?? 0);
    $message = '';
    if ($skippedCount > 0) {
        $codes = (string) ($skipped['codes'] ?? '');
        $message = sprintf(
            _translate('Not accepted because no result was read from the file: %s'),
            $codes !== '' ? $codes : $skippedCount
        );
    }

    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'ok',
        'skipped' => $skippedCount,
        'message' => $message,
    ]);
} catch (Throwable $e) {
    LoggerUtility::log("error", $e->getMessage(), [
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'last_db_query' => $db?->getLastQuery(),
        'last_db_error' => $db?->getLastError(),
    ]);
    throw $e;
}
