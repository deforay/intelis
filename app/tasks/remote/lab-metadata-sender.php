<?php

$cliMode = php_sapi_name() === 'cli';

$forceFlag = false;
if ($cliMode) {
    require_once __DIR__ . "/../../../bootstrap.php";

    // Parse CLI arguments
    $options = getopt('f', ['force']);
    if (isset($options['f']) || isset($options['force'])) {
        $forceFlag = true;
    }
}

// this file gets the data from the local database and updates the remote database
use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Utilities\SyncConsole;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\LabMetadataBatchReader;
use App\Services\RejectionReasonMappingService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

// The web runs this too, with no console to talk to.
$console = $cliMode ? SyncConsole::cli() : SyncConsole::none();

// only for LIS instances
if ($general->isLISInstance() === false) {
    exit(0);
}

$labId = $general->getSystemConfig('sc_testing_lab_id');
$version = VERSION;

// putting this into a variable to make this editable
$systemConfig = SYSTEM_CONFIG;

$lastUpdatedOn = $db->getValue('s_vlsm_instance', 'last_lab_metadata_sync');

$remoteURL = $general->getRemoteURL();

$console->title(_translate('Sending lab metadata to the STS'));
if ($forceFlag) {
    $console->line(_translate('Sending everything, not only the changes since the last run.'));
}

if (empty($remoteURL)) {
    $console->fail(_translate('The STS URL is not set in System Config. Exiting.'));
    LoggerUtility::logError("Please check if STS URL is set");
    exit(0);
}

try {
    // Checking if the network connection is available
    $console->line(_translate('Checking the connection to the STS...'));
    if (false == CommonService::validateStsUrl($remoteURL, $labId)) {
        $console->fail(sprintf(
            _translate('Cannot reach the STS at %s. Check the internet connection. The next run tries again.'),
            $remoteURL
        ));
        LoggerUtility::logError("No network connectivity while trying remote sync.");
        return false;
    }

    $transactionId = MiscUtility::generateULID();

    $payload = [
        "labId" => $labId,
        "x-api-key" => MiscUtility::generateUUID(),
    ];

    $url = "$remoteURL/remote/remote/lab-metadata-receiver.php";

    $lastUpdatedOnCondition = "(updated_datetime > '$lastUpdatedOn' OR updated_datetime IS NULL)";

    $metadataTables = [
        'lab_storage' => 'labStorage',
        'instruments' => 'instruments',
        'instrument_machines' => 'instrumentMachines',
        'instrument_controls' => 'instrumentControls',
    ];

    // Append-only tables that grow with the lab's work: what the Interface Tool
    // reported, and every time a sample was stored or moved. These do not belong with
    // the tables above, which are a small fixed set of rows. Each carries its own
    // watermark and goes a capped batch per run, so a backlog (or a forced sync, which
    // starts them over) cannot hold up the metadata sync or arrive as one enormous
    // payload held in memory.
    $batchedTables = [
        'instrument_activity_log' => [
            'payloadKey' => 'instrumentActivity',
            'deltaColumn' => 'received_at',
            'watermark' => 'last_instrument_activity_sync',
            'limit' => 5000,
        ],
        'instrument_usage_statistics_daily' => [
            'payloadKey' => 'instrumentUsageStatistics',
            'deltaColumn' => 'updated_at',
            'watermark' => 'last_instrument_usage_sync',
            'limit' => 5000,
        ],
        // Rows are only ever added, so the lab's own id orders them and never ties.
        'lab_storage_history' => [
            'payloadKey' => 'labStorageHistory',
            'deltaColumn' => 'history_id',
            'watermark' => 'last_lab_storage_history_id',
            'limit' => 5000,
        ],
    ];

    // Rejection reasons this lab holds, sent WHOLE every run rather than by delta.
    //
    // A reason typed into the "Other" box gets a local auto-increment id that means
    // nothing anywhere else, and the id is what travels on the sample -- so STS ends
    // up with rejections pointing at reasons it has never heard of. STS resolves them
    // by name and records what each of this lab's ids means (see
    // RejectionReasonMappingService).
    //
    // Whole, not delta, because the reasons that need resolving were minted years ago
    // and would never cross an updated_datetime watermark again. These are a few dozen
    // rows per module, so sending all of them every run costs nothing worth measuring.
    $reasonTables = [];
    foreach (RejectionReasonMappingService::syncableTestTypes() as $reasonTestType) {
        $reasonTable = RejectionReasonMappingService::reasonTableFor($reasonTestType);
        if (!empty($reasonTable)) {
            $reasonTables[$reasonTable] = RejectionReasonMappingService::payloadKeyFor($reasonTestType);
        }
    }

    // +1 for users table
    $totalSteps = count($metadataTables) + count($reasonTables) + count($batchedTables) + 1;
    $console->line(_translate('Collecting what changed since the last run...'));
    $bar = $console->progress($totalSteps, _translate('Collecting'));

    // Machines go whole every run: a few rows per lab. STS releases before 5.7.78
    // dropped a machine whose id another lab also used, and a machine that is not
    // edited again would never cross the watermark to reach STS once it can keep it.
    $sentWhole = ['instrument_machines'];

    foreach ($metadataTables as $table => $payloadKey) {
        $db->reset();
        if ($forceFlag === false && !empty($lastUpdatedOn) && !in_array($table, $sentWhole, true)) {
            $db->where($lastUpdatedOnCondition);
        }
        $records = $db->get($table);
        if (!empty($records)) {
            $payload[$payloadKey] = $records;
        }
        $bar->advance();
    }

    foreach ($reasonTables as $table => $payloadKey) {
        $db->reset();
        $records = $db->get($table);
        if (!empty($records)) {
            $payload[$payloadKey] = $records;
        }
        $bar->advance();
    }

    // Watermarks are advanced only after STS has accepted the payload, so a failed run
    // resends rather than skipping. Re-sending is free: STS matches each row to the one
    // it already holds, so a row it has is updated in place, not added again.
    /** @var LabMetadataBatchReader $batchReader */
    $batchReader = ContainerRegistry::get(LabMetadataBatchReader::class);
    $batchedWatermarks = [];
    foreach ($batchedTables as $table => $spec) {
        $sentUpTo = $db->getValue('s_vlsm_instance', $spec['watermark']);
        $records = $batchReader->next($table, $spec['deltaColumn'], $sentUpTo, $spec['limit'], $forceFlag);

        if (!empty($records)) {
            $payload[$spec['payloadKey']] = $records;
            // The watermark follows the last row actually sent, not the clock, so rows
            // written while this run was in flight are picked up next time.
            $batchedWatermarks[$spec['watermark']] =
                end($records)[$spec['deltaColumn']] ?? null;
        }
        $bar->advance();
    }

    // USERS
    if ($forceFlag === false && !empty($lastUpdatedOn)) {
        $db->where($lastUpdatedOnCondition);
    }
    $db->where("login_id IS NOT NULL");
    $db->where("status like 'active'");
    $users = $db->get('user_details');

    // Add signature images to the users payload
    $signatureImagePathBase = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature";
    MiscUtility::makeDirectory($signatureImagePathBase);
    $signatureImagePathBase = realpath($signatureImagePathBase);

    if (!empty($users)) {
        foreach ($users as &$user) {
            $signatureImagePath = isset($user['user_signature'])
                ? $signatureImagePathBase . DIRECTORY_SEPARATOR . $user['user_signature']
                : null;
            if ($signatureImagePath && MiscUtility::isImageValid($signatureImagePath)) {
                $user['signature_image_content'] = base64_encode(file_get_contents($signatureImagePath));
                $user['signature_image_filename'] = $user['user_signature'];
            } else {
                $user['signature_image_content'] = null;
                $user['signature_image_filename'] = null;
            }

            // Unset unnecessary fields
            foreach (['login_id', 'password', 'role_id', 'status'] as $key) {
                unset($user[$key]);
            }
        }
        $payload["users"] = $users;
    }
    $bar->advance();
    $bar->finish();

    // Authenticate to STS with this lab's token, the same one the result sender and
    // request receiver use, so STS can tie the payload to the facility it came from.
    $apiService->setBearerToken((string) $general->getSTSToken());

    $console->line(_translate('Sending lab metadata to the STS...'));
    $wait = $console->wait();
    $response = $apiService->post(
        $url,
        $payload,
        gzip: true,
        returnWithStatusCode: true,
        onProgress: $cliMode ? $wait->track() : null
    );
    $wait->done();
    $httpStatusCode = (int) ($response['httpStatusCode'] ?? 0);

    // post() swallows HTTP errors and hands back the body, so the status has to be
    // read explicitly. Without this a rejected upload -- an expired token above all --
    // looked like a success, advanced the watermarks below, and quietly dropped the
    // metadata it had just failed to deliver.
    if ($httpStatusCode < 200 || $httpStatusCode > 299) {
        $reason = !empty($response['body']) ? substr((string) $response['body'], 0, 500) : 'no response body';
        throw new Exception(
            $httpStatusCode === 0
                ? "STS could not be reached for the lab metadata upload ($url)"
                : "STS rejected the lab metadata upload with HTTP $httpStatusCode: $reason"
        );
    }

    // Only reached when the post above did not throw, so the watermarks advance solely
    // on a delivery STS accepted. Each instrument watermark moves to the last row that
    // was actually sent; a table with nothing new leaves its watermark untouched.
    $syncUpdate = ['last_lab_metadata_sync' => DateUtility::getCurrentDateTime()];
    foreach ($batchedWatermarks as $watermarkColumn => $watermarkValue) {
        if ($watermarkValue !== null) {
            $syncUpdate[$watermarkColumn] = $watermarkValue;
        }
    }

    $instanceId = $general->getInstanceId();
    $db->where('vlsm_instance_id', $instanceId);
    $id = $db->update('s_vlsm_instance', $syncUpdate);

    $console->ok(_translate('Lab metadata sent.'));
} catch (Exception $exc) {
    $console->fail(sprintf(
        _translate('Could not send lab metadata: %s. The next run tries again.'),
        rtrim($exc->getMessage(), '.')
    ));
    $console->stsError((string) ($url ?? ''), $response ?? null);
    LoggerUtility::log("error", __FILE__ . ":" . $exc->getMessage(), [
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
        'file' => $exc->getFile(),
        'line' => $exc->getLine(),
        'trace' => $exc->getTraceAsString(),
    ]);
}
