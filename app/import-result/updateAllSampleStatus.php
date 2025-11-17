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
    $db->where('imported_by', $importedBy);
    $db->where("(IFNULL(result_status,'') = '' OR result_status IN ($statusCodes))");
    $id = $db->update('temp_sample_import', [
        'result_status' => ACCEPTED
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
}
