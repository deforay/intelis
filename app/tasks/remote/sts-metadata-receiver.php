<?php

// app/tasks/remote/sts-metadata-receiver.php
// Request STS to send metadata to this instance of LIS


ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

$cliMode = php_sapi_name() === 'cli';

if ($cliMode) {
    require_once __DIR__ . "/../../../bootstrap.php";
}

use JsonMachine\Items;
use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Utilities\FileCacheUtility;
use App\Registries\ContainerRegistry;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\ConsoleOutput;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);


$forceFlag = false;
$truncateFlag = false;
$systemConfig = SYSTEM_CONFIG;

// this script is only for LIS instances
if ($general->isLISInstance() === false) {
    exit(0);
}

if ($cliMode) {
    $io = new SymfonyStyle(new ArgvInput(), new ConsoleOutput());

    // Parse CLI arguments
    $options = getopt('ft', ['force', 'truncate']);
    if (isset($options['f']) || isset($options['force'])) {
        $forceFlag = true;
    }
    if (isset($options['t']) || isset($options['truncate'])) {
        $truncateFlag = true;
    }
    $io->title("Preparing to sync metadata...");
}


if ($_POST !== []) {
    try {
        $_POST = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);
        $forceFlag = $_POST['force'] ?? false;
        $remoteURL = $_POST['remoteURL'] ?? null;
    } catch (Throwable) {
        LoggerUtility::logError("Invalid Request. Please try again");
        exit(0);
    }
} else {
    $remoteURL = $general->getRemoteURL();
}

if (empty($remoteURL) || $remoteURL == '') {
    if ($cliMode) {
        $io->error(_translate("Please check if STS URL is set"));
    }
    LoggerUtility::logError("Please check if STS URL is set");
    exit(0);
}

$labId = $general->getSystemConfig('sc_testing_lab_id');

$version = VERSION;

if (false == CommonService::validateStsUrl($remoteURL, $labId)) {
    if ($cliMode) {
        $io->error(_translate("No network connectivity while trying STS medatata sync."));
    }
    LoggerUtility::logError("No network connectivity while trying STS medatata sync.");
    return false;
}

$instanceId = $general->getInstanceId();

$dataToSync = [];
$commonDataToSync = [];
$genericDataToSync = [];
$vlDataToSync = [];
$eidDataToSync = [];
$covid19DataToSync = [];
$hepatitisDataToSync = [];
$tbDataToSync = [];
$cd4DataToSync = [];


$payload = [
    'globalConfigLastModified' => $forceFlag ? null : $general->getLastModifiedDateTime('global_config'),
    'facilityLastModified' => $forceFlag ? null : $general->getLastModifiedDateTime('facility_details'),
    'healthFacilityLastModified' => $forceFlag ? null : $general->getLastModifiedDateTime('health_facilities'),
    'testingLabsLastModified' => $forceFlag ? null : $general->getLastModifiedDateTime('testing_labs'),
    'fundingSourcesLastModified' => $forceFlag ? null : $general->getLastModifiedDateTime('r_funding_sources'),
    'partnersLastModified' => $forceFlag ? null : $general->getLastModifiedDateTime('r_implementation_partners'),
    'geoDivisionsLastModified' => $forceFlag ? null : $general->getLastModifiedDateTime('geographical_divisions'),
    //'patientsLastModified'          => $forceFlag ? null : $general->getLastModifiedDateTime('patients'),
    'time' => time(),
    "instanceId" => $general->getInstanceId()
];

// This array is used to sync data that we will later receive from the API call
$commonDataToSync = [
    'globalConfig' => [
        'primaryKey' => 'name',
        'tableName' => 'global_config',
        'canTruncate' => false
    ],
    'users' => [
        'primaryKey' => 'user_id',
        'tableName' => 'user_details',
        'canTruncate' => false
    ],
    'facilities' => [
        'primaryKey' => 'facility_id',
        'tableName' => 'facility_details',
        'canTruncate' => true
    ],
    'healthFacilities' => [
        'primaryKey' => 'facility_id',
        'tableName' => 'health_facilities',
        'canTruncate' => true
    ],
    'testingLabs' => [
        'primaryKey' => 'facility_id',
        'tableName' => 'testing_labs',
        'canTruncate' => true
    ],
    'fundingSources' => [
        'primaryKey' => 'funding_source_id',
        'tableName' => 'r_funding_sources',
        'canTruncate' => true
    ],
    'partners' => [
        'primaryKey' => 'i_partner_id',
        'tableName' => 'r_implementation_partners',
        'canTruncate' => true
    ],
    'geoDivisions' => [
        'primaryKey' => 'geo_id',
        'tableName' => 'geographical_divisions',
        'canTruncate' => true
    ],
    // 'patients'  => [
    //     'primaryKey' => 'system_patient_code',
    //     'tableName'  =>  'patients',
    //     'canTruncate' => false
    // ]
];

// Receive data from STS
$url = "$remoteURL/remote/remote/sts-metadata-sender.php";

if (isset($systemConfig['modules']['generic-tests']) && $systemConfig['modules']['generic-tests'] === true) {
    $toSyncTables = [
        "r_test_types",
        "r_generic_test_methods",
        "r_generic_test_categories",
        "r_generic_sample_types",
        "r_generic_test_reasons",
        "r_generic_test_result_units",
        "r_generic_test_failure_reasons",
        "r_generic_sample_rejection_reasons",
        "r_generic_symptoms",
        "generic_test_methods_map",
        "generic_test_sample_type_map",
        "generic_test_reason_map",
        "generic_test_failure_reason_map",
        "generic_sample_rejection_reason_map",
        "generic_test_symptoms_map",
        "generic_test_result_units_map"
    ];
    foreach ($toSyncTables as $table) {
        $payload[$general->stringToCamelCase($table) . 'LastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime($table);

        $genericDataToSync[$general->stringToCamelCase($table)] = [
            "primaryKey" => $general->getPrimaryKeyField($table),
            "tableName" => $table,
            "canTruncate" => true
        ];
    }
}
if (isset($systemConfig['modules']['vl']) && $systemConfig['modules']['vl'] === true) {

    $payload['vlArtCodesLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_vl_art_regimen');
    $payload['vlRejectionReasonsLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_vl_sample_rejection_reasons');
    $payload['vlTestReasonsLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_vl_test_reasons');
    $payload['vlSampleTypesLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_vl_sample_type');
    $payload['vlFailureReasonsLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_vl_test_failure_reasons');
    $payload['vlResultsLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_vl_results');

    // This array is used to sync data that we will later receive from the API call
    $vlDataToSync = [
        'vlSampleTypes' => [
            'primaryKey' => 'sample_id',
            'tableName' => 'r_vl_sample_type',
            'canTruncate' => true
        ],
        'vlArtCodes' => [
            'primaryKey' => 'art_id',
            'tableName' => 'r_vl_art_regimen',
            'canTruncate' => true
        ],
        'vlRejectionReasons' => [
            'primaryKey' => 'rejection_reason_id',
            'tableName' => 'r_vl_sample_rejection_reasons',
            'canTruncate' => true
        ],
        'vlTestReasons' => [
            'primaryKey' => 'test_reason_id',
            'tableName' => 'r_vl_test_reasons',
            'canTruncate' => true
        ],
        'vlFailureReasons' => [
            'primaryKey' => 'failure_id',
            'tableName' => 'r_vl_test_failure_reasons',
            'canTruncate' => true
        ],
        'vlResults' => [
            'primaryKey' => 'result_id',
            'tableName' => 'r_vl_results',
            'canTruncate' => true
        ]
    ];
}

if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] === true) {
    $payload['eidRejectionReasonsLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_eid_sample_rejection_reasons');
    $payload['eidSampleTypesLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_eid_sample_type');
    $payload['eidResultsLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_eid_results ');
    $payload['eidReasonForTestingLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_eid_test_reasons  ');


    // This array is used to sync data that we will later receive from the API call
    $eidDataToSync = [
        'eidRejectionReasons' => [
            'primaryKey' => 'rejection_reason_id',
            'tableName' => 'r_eid_sample_rejection_reasons',
            'canTruncate' => true
        ],
        'eidSampleTypes' => [
            'primaryKey' => 'sample_id',
            'tableName' => 'r_eid_sample_type',
            'canTruncate' => true
        ],
        'eidResults' => [
            'primaryKey' => 'result_id',
            'tableName' => 'r_eid_results',
            'canTruncate' => true
        ],
        'eidReasonForTesting' => [
            'primaryKey' => 'test_reason_id',
            'tableName' => 'r_eid_test_reasons',
            'canTruncate' => true
        ]
    ];
}


if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] === true) {

    $payload['covid19RejectionReasonsLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_covid19_sample_rejection_reasons');
    $payload['covid19SampleTypesLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_covid19_sample_type');
    $payload['covid19ComorbiditiesLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_covid19_comorbidities');
    $payload['covid19ResultsLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_covid19_results');
    $payload['covid19SymptomsLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_covid19_symptoms');
    $payload['covid19ReasonForTestingLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_covid19_test_reasons');
    $payload['covid19QCTestKitsLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_covid19_qc_testkits');



    // This array is used to sync data that we will later receive from the API call
    $covid19DataToSync = [
        'covid19RejectionReasons' => [
            'primaryKey' => 'rejection_reason_id',
            'tableName' => 'r_covid19_sample_rejection_reasons',
            'canTruncate' => true
        ],
        'covid19SampleTypes' => [
            'primaryKey' => 'sample_id',
            'tableName' => 'r_covid19_sample_type',
            'canTruncate' => true
        ],
        'covid19Comorbidities' => [
            'primaryKey' => 'comorbidity_id',
            'tableName' => 'r_covid19_comorbidities',
            'canTruncate' => true
        ],
        'covid19Results' => [
            'primaryKey' => 'result_id',
            'tableName' => 'r_covid19_results',
            'canTruncate' => true
        ],
        'covid19Symptoms' => [
            'primaryKey' => 'symptom_id',
            'tableName' => 'r_covid19_symptoms',
            'canTruncate' => true
        ],
        'covid19ReasonForTesting' => [
            'primaryKey' => 'test_reason_id',
            'tableName' => 'r_covid19_test_reasons',
            'canTruncate' => true
        ],
        'covid19QCTestKits' => [
            'primaryKey' => 'testkit_id',
            'tableName' => 'r_covid19_qc_testkits',
            'canTruncate' => true
        ]
    ];
}

if (isset($systemConfig['modules']['hepatitis']) && $systemConfig['modules']['hepatitis'] === true) {
    $payload['hepatitisRejectionReasonsLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_hepatitis_sample_rejection_reasons');
    $payload['hepatitisSampleTypesLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_hepatitis_sample_type');
    $payload['hepatitisComorbiditiesLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_hepatitis_comorbidities');
    $payload['hepatitisResultsLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_hepatitis_results');
    $payload['hepatitisReasonForTestingLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_hepatitis_test_reasons');

    // This array is used to sync data that we will later receive from the API call
    $hepatitisDataToSync = [
        'hepatitisReasonForTesting' => [
            'primaryKey' => 'test_reason_id',
            'tableName' => 'r_hepatitis_test_reasons',
            'canTruncate' => true
        ],
        'hepatitisResults' => [
            'primaryKey' => 'result_id',
            'tableName' => 'r_hepatitis_results',
            'canTruncate' => true
        ],
        'hepatitisComorbidities' => [
            'primaryKey' => 'comorbidity_id',
            'tableName' => 'r_hepatitis_comorbidities',
            'canTruncate' => true
        ],
        'hepatitisSampleTypes' => [
            'primaryKey' => 'sample_id',
            'tableName' => 'r_hepatitis_sample_type',
            'canTruncate' => true
        ],
        'hepatitisRejectionReasons' => [
            'primaryKey' => 'rejection_reason_id',
            'tableName' => 'r_hepatitis_sample_rejection_reasons',
            'canTruncate' => true
        ]
    ];
}

if (isset($systemConfig['modules']['tb']) && $systemConfig['modules']['tb'] === true) {
    $payload['tbRejectionReasonsLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_tb_sample_rejection_reasons');
    $payload['tbSampleTypesLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_tb_sample_type');
    $payload['tbResultsLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_tb_results');
    $payload['tbReasonForTestingLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_tb_test_reasons');

    // This array is used to sync data that we will later receive from the API call
    $tbDataToSync = [
        'tbReasonForTesting' => [
            'primaryKey' => 'test_reason_id',
            'tableName' => 'r_tb_test_reasons',
            'canTruncate' => true
        ],
        'tbResults' => [
            'primaryKey' => 'result_id',
            'tableName' => 'r_tb_results',
            'canTruncate' => true
        ],
        'tbSampleTypes' => [
            'primaryKey' => 'sample_id',
            'tableName' => 'r_tb_sample_type',
            'canTruncate' => true
        ],
        'tbRejectionReasons' => [
            'primaryKey' => 'rejection_reason_id',
            'tableName' => 'r_tb_sample_rejection_reasons',
            'canTruncate' => true
        ]
    ];
}

if (isset($systemConfig['modules']['cd4']) && $systemConfig['modules']['cd4'] === true) {
    $payload['cd4RejectionReasonsLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_cd4_sample_rejection_reasons');
    $payload['cd4SampleTypesLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_cd4_sample_types');
    $payload['cd4ReasonForTestingLastModified'] = $forceFlag ? null : $general->getLastModifiedDateTime('r_cd4_test_reasons');

    // This array is used to sync data that we will later receive from the API call
    $cd4DataToSync = [
        'cd4SampleTypes' => [
            'primaryKey' => 'sample_id',
            'tableName' => 'r_cd4_sample_types',
            'canTruncate' => true
        ],
        'cd4RejectionReasons' => [
            'primaryKey' => 'rejection_reason_id',
            'tableName' => 'r_cd4_sample_rejection_reasons',
            'canTruncate' => true
        ],
        'cd4ReasonForTesting' => [
            'primaryKey' => 'test_reason_id',
            'tableName' => 'r_cd4_test_reasons',
            'canTruncate' => true
        ]
    ];
}

$dataToSync = array_merge(
    $commonDataToSync,
    $genericDataToSync,
    $vlDataToSync,
    $eidDataToSync,
    $covid19DataToSync,
    $hepatitisDataToSync,
    $tbDataToSync,
    $cd4DataToSync
);

$payload['labId'] = $labId;

try {
    $jsonResponse = $apiService->post($url, $payload, gzip: true);
    if (!empty($jsonResponse) && $jsonResponse != "[]") {

        if ($cliMode) {
            $io->success(_translate("Receieved metadata from STS"));
        }

        $options = [
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($jsonResponse, $options);
        $db->rawQuery("SET FOREIGN_KEY_CHECKS = 0;"); // Disable foreign key checks

        foreach ($parsedData as $dataType => $dataValues) {
            if ($dataValues instanceof \Traversable) {
                $dataValues = iterator_to_array($dataValues, true);
            }
            if (empty($dataType) || $dataType === '' || empty($dataToSync[$dataType]['tableName']) || $dataToSync[$dataType]['tableName'] === '' || empty($dataValues)) {
                continue;
            }

            try {

                $db->beginTransaction();
                // Truncate table if truncate flag is set and table can be truncated
                if ($cliMode && $truncateFlag && $dataToSync[$dataType]['canTruncate']) {
                    $db->rawQuery("TRUNCATE TABLE {$dataToSync[$dataType]['tableName']}");
                }

                if (isset($dataToSync[$dataType]) && !empty($dataValues)) {

                    // Before we update/insert data, some tables need to have some related data removed
                    if ($dataType === 'healthFacilities' && !empty($dataValues)) {
                        $updatedFacilities = array_unique(array_column($dataValues, 'facility_id'));
                        $db->where('facility_id', $updatedFacilities, 'IN');
                        $id = $db->delete('health_facilities');
                    } elseif ($dataType === 'testingLabs' && !empty($dataValues)) {
                        $updatedFacilities = array_unique(array_column($dataValues, 'facility_id'));
                        $db->where('facility_id', $updatedFacilities, 'IN');
                        $id = $db->delete('testing_labs');
                    }

                    // $emptyTableArray is used to get the table fields as an array
                    // so that we update only the matching keys in the table
                    $emptyTableArray = $general->getTableFieldsAsArray($dataToSync[$dataType]['tableName']);

                    if ($cliMode) {
                        $io->info(_translate("Syncing data for") . " " . $dataToSync[$dataType]['tableName']);
                    }


                    $totalRows = count($dataValues); // Get the total number of rows for the current table
                    foreach ($dataValues as $index => $tableDataValues) {
                        if ($cliMode) {
                            MiscUtility::progressBar($index + 1, $totalRows); // Update the progress bar for each row
                        }

                        $tableData = MiscUtility::updateMatchingKeysOnly($emptyTableArray, $tableDataValues);
                        $updateColumns = array_keys($tableData);
                        $primaryKey = $dataToSync[$dataType]['primaryKey'];

                        if ($dataToSync[$dataType]['tableName'] === 'user_details') {
                            foreach (['login_id', 'role_id', 'password', 'status'] as $unsetKey) {
                                unset($tableData[$unsetKey]);
                            }
                        }

                        $db->upsert($dataToSync[$dataType]['tableName'], $tableData, $updateColumns, [$primaryKey]);

                        // Updating logo and report template
                        if ($dataType === 'facilities') {
                            if (!empty($tableData['facility_logo'])) {
                                $labLogoFolder = UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $tableData['facility_id'];
                                MiscUtility::makeDirectory($labLogoFolder);

                                $remoteFileUrl = $remoteURL . '/uploads/facility-logo/' . $tableData['facility_id'] . '/' . "actual-" . $tableData['facility_logo'];
                                $localFilePath = $labLogoFolder . "/" . "actual-" . $tableData['facility_logo'];

                                $apiService->downloadFile($remoteFileUrl, $localFilePath);

                                $remoteFileUrl = $remoteURL . '/uploads/facility-logo/' . $tableData['facility_id'] . '/' . $tableData['facility_logo'];
                                $localFilePath = $labLogoFolder . "/" . $tableData['facility_logo'];
                                $apiService->downloadFile($remoteFileUrl, $localFilePath);
                            }

                            $facilityAttributes = empty($tableData['facility_attributes']) ? [] : json_decode((string) $tableData['facility_attributes'], true);

                            if (!empty($facilityAttributes['report_template'])) {
                                $labDataFolder = UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . "report-template" . DIRECTORY_SEPARATOR . $tableData['facility_id'];
                                MiscUtility::makeDirectory($labDataFolder);

                                $remoteFileUrl = $remoteURL . "/uploads/labs/{$tableData['facility_id']}/report-template/{$facilityAttributes['report_template']}";

                                _logdump($remoteFileUrl);
                                $localFilePath = $labDataFolder . "/" . $facilityAttributes['report_template'];
                                _logdump($localFilePath);
                                $x = $apiService->downloadFile($remoteFileUrl, $localFilePath);
                                _logdump($x);
                            }
                        }
                    }
                }
                // Global config report template sync
                if ($dataType === 'globalConfig') {
                    $reportFormat = empty($tableData['value']) ? [] : json_decode((string) $tableData['value'], true);
                    if (!empty($reportFormat) && $tableData['name'] = 'report_format') {
                        foreach ($reportFormat as $test => $row) {
                            $labDataFolder = UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . "report-template" . DIRECTORY_SEPARATOR . $test;
                            MiscUtility::makeDirectory($labDataFolder);

                            $remoteFileUrl = $general->getRemoteURL() . "/uploads/labs/report-template/{$test}/{$row['file']}";
                            _logdump($remoteFileUrl);
                            $localFilePath = $labDataFolder . "/" . $row['file'];
                            _logdump($localFilePath);
                            $x = $apiService->downloadFile($remoteFileUrl, $localFilePath);
                            _logdump($x);
                        }
                    }
                }
                if ($dataType === 'labReportSignatories') {
                    foreach ($dataValues as $key => $sign) {

                        // Delete old signatures, before we save new ones
                        $db->where('lab_id  = ' . $sign['lab_id']);
                        $id = $db->delete('lab_report_signatories');

                        $signaturesFolder = UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $sign['lab_id'] . DIRECTORY_SEPARATOR . 'signatures';

                        if (!file_exists($signaturesFolder)) {
                            // make new folder
                            MiscUtility::makeDirectory($signaturesFolder);
                        } else {
                            // in case folder exists, we can delete all old files
                            $images = glob("$signaturesFolder/*.{jpg,png,gif,jpeg}", GLOB_BRACE);
                            foreach ($images as $image) {
                                MiscUtility::deleteFile($image);
                            }
                        }
                        // Save Data to DB
                        unset($sign['signatory_id']);
                        if (isset($sign['signature']) && $sign['signature'] != "") {
                            /* To save file from the url */
                            $remoteFileUrl = $general->getRemoteURL() . '/uploads/labs/' . $sign['lab_id'] . '/signatures/' . $sign['signature'];
                            $localFileLocation = $signaturesFolder . DIRECTORY_SEPARATOR . $sign['signature'];
                            if ($apiService->downloadFile($remoteFileUrl, $localFileLocation)) {
                                $db->insert('lab_report_signatories', $sign);
                            }
                        }
                    }
                }

                // invalidate cache by tags or by deleting specific cache keys
                if ($dataToSync[$dataType]['tableName'] === 'r_vl_results') {
                    _invalidateFileCacheByTags(['r_vl_results']);
                } elseif ($dataToSync[$dataType]['tableName'] === 'r_implementation_partners') {
                    _invalidateFileCacheByTags(['r_implementation_partners']);
                } elseif ($dataToSync[$dataType]['tableName'] === 'r_funding_sources') {
                    _invalidateFileCacheByTags(['r_funding_sources']);
                } elseif ($dataToSync[$dataType]['tableName'] === 'global_config') {
                    // unset global config cache so that it can be reloaded with new values
                    // this is set in CommonService::getGlobalConfig()
                    (ContainerRegistry::get(FileCacheUtility::class))->delete('app_global_config');
                }

                $db->commitTransaction();
            } catch (Throwable $e) {
                $db->rollbackTransaction();

                if ($cliMode) {
                    $io->error(_translate("Error while syncing metadata from STS for") . " " . $dataToSync[$dataType]['tableName']);
                }
                LoggerUtility::logError(
                    "Error while syncing metadata from STS for " . $dataToSync[$dataType]['tableName'],
                    [
                        'dataType' => $dataType,
                        'tableName' => $dataToSync[$dataType]['tableName'],
                        'line' => $e->getLine(),
                        'file' => $e->getFile(),
                        'code' => $e->getCode(),
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'last_db_error' => $db->getLastError(),
                        'last_db_query' => $db->getLastQuery()
                    ]
                );
                continue;
            }
            if ($cliMode) {
                $io->info(_translate("Completed syncing data for") . " " . $dataToSync[$dataType]['tableName']);
            }
        }
    }
    $id = $db->update('s_vlsm_instance', ['last_remote_reference_data_sync' => DateUtility::getCurrentDateTime()]);
} catch (Throwable $e) {
    if ($cliMode) {
        $io->error(_translate("Error while syncing metadata from STS"));
    }
    LoggerUtility::logError("Error while syncing metadata from STS", [
        'line' => $e->getLine(),
        'file' => $e->getFile(),
        'code' => $e->getCode(),
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery()
    ]);
} finally {
    if ($cliMode) {
        $io->success("Metadata Synced");
    }
    $db->rawQuery("SET FOREIGN_KEY_CHECKS = 1;"); // Enable foreign key checks
}
