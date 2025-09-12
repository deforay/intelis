<?php

use App\Services\TbService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Helpers\PdfConcatenateHelper;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

try {
    // Sanitized values from $request object
    /** @var Laminas\Diactoros\ServerRequest $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody());

    $tableName1 = "activity_log";
    $tableName2 = "form_tb";

    /** @var DatabaseService $db */
    $db = ContainerRegistry::get(DatabaseService::class);

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);

    /** @var UsersService $users */
    $usersService = ContainerRegistry::get(UsersService::class);

    /** @var GeoLocationsService $geolocationService */
    $geolocationService = ContainerRegistry::get(GeoLocationsService::class);

    /** @var TbService $tbService */
    $tbService = ContainerRegistry::get(TbService::class);

    $formId = (int) $general->getGlobalConfig('vl_form');
    $key = (string) $general->getGlobalConfig('key');

    // Set print time
    $printedTime = date('Y-m-d H:i:s');
    $expStr = explode(" ", $printedTime);
    $printDate = DateUtility::humanReadableDateFormat($expStr[0]);
    $printDateTime = $expStr[1];

    // Build search query
    $allQuery = $_SESSION['tbPrintQuery'] ?? '';

    if (isset($_POST['id']) && trim((string) $_POST['id']) != '') {
        $searchQuery = "SELECT tb.*, f.*,
        ft.facility_type_name as facilityType, 
        g_d_s.geo_name as province, 
        g_d_d.geo_name as district,
        l.facility_name as labName,
        l.facility_emails as labEmail,
        l.address as labAddress,
        l.facility_mobile_numbers as labPhone,
        l.facility_state as labState,
        l.facility_district as labCounty,
        l.facility_logo as facilityLogo,
        l.report_format as reportFormat,
        l.facility_attributes,
        rip.i_partner_name,
        rsrr.rejection_reason_name,
        requestor_user.user_name as requestedBy,
        reviewer_user.user_name as reviewedBy,
        reviewer_user.user_id as reviewedByUserId,
        reviewer_user.user_signature as reviewedBySignature,
        approver_user.user_name as approvedBy,
        approver_user.user_id as approvedByUserId,
        approver_user.user_signature as approvedBySignature,
        reviser_user.user_name as revisedBy,
        reviser_user.user_id as revisedByUserId,
        reviser_user.user_signature as revisedBySignature,
        tester_user.user_name as testedBy,
        tester_user.user_id as testedByUserId,
        tester_user.user_signature as testedBySignature,
        rfs.funding_source_name,
        rst.sample_name,
        testres.test_reason_name as reasonForTesting,
        r_c_a.recommended_corrective_action_name

        FROM form_tb as tb
        LEFT JOIN facility_details as f ON tb.facility_id=f.facility_id
        LEFT JOIN facility_type as ft ON f.facility_type=ft.facility_type_id 
        LEFT JOIN facility_details as l ON l.facility_id=tb.lab_id 
        LEFT JOIN geographical_divisions as g_d_s ON g_d_s.geo_id = f.facility_state_id
        LEFT JOIN geographical_divisions as g_d_d ON g_d_d.geo_id = f.facility_district_id 
        LEFT JOIN user_details as reviewer_user ON reviewer_user.user_id=tb.result_reviewed_by
        LEFT JOIN user_details as approver_user ON approver_user.user_id=tb.result_approved_by
        LEFT JOIN user_details as reviser_user ON reviser_user.user_id=tb.revised_by
        LEFT JOIN user_details as tester_user ON tester_user.user_id = tb.tested_by
        LEFT JOIN user_details as requestor_user ON requestor_user.user_id=tb.request_created_by
        LEFT JOIN r_tb_test_reasons as testres ON testres.test_reason_id=tb.reason_for_tb_test
        LEFT JOIN r_tb_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id=tb.reason_for_sample_rejection
        LEFT JOIN r_recommended_corrective_actions as r_c_a ON r_c_a.recommended_corrective_action_id=tb.recommended_corrective_action
        LEFT JOIN r_implementation_partners as rip ON rip.i_partner_id=tb.implementing_partner
        LEFT JOIN r_funding_sources as rfs ON rfs.funding_source_id=tb.funding_source
        LEFT JOIN r_tb_sample_type as rst ON rst.sample_id=tb.specimen_type
        WHERE tb.tb_id IN(" . $_POST['id'] . ")";
    } else {
        $searchQuery = $allQuery;
    }

    if (empty($searchQuery)) {
        throw new Exception("No search query provided");
    }

    $requestResult = $db->query($searchQuery);
    $currentDateTime = DateUtility::getCurrentDateTime();

    // Track QR page views if applicable
    if (isset($_POST['type']) && $_POST['type'] == "qr" && !empty($requestResult)) {
        try {
            $general->trackQRPageViews('tb', $requestResult[0]['tb_id'], $requestResult[0]['sample_code']);
        } catch (Exception $exc) {
            error_log("QR tracking error: " . $exc->getMessage());
        }
    }

    $_SESSION['aliasPage'] = 1;
    $arr = $general->getGlobalConfig();

    // Set mandatory field array
    $mFieldArray = [];
    if (isset($arr['r_mandatory_fields']) && trim((string) $arr['r_mandatory_fields']) != '') {
        $mFieldArray = explode(',', (string) $arr['r_mandatory_fields']);
    }

    // Default report format files by country/form ID
    $fileArray = [
        COUNTRY\SOUTH_SUDAN => 'pdf/result-pdf-ssudan.php',
        COUNTRY\SIERRA_LEONE => 'pdf/result-pdf-sierraleone.php',
        COUNTRY\DRC => 'pdf/result-pdf-drc.php',
        COUNTRY\CAMEROON => 'pdf/result-pdf-cameroon.php',
        COUNTRY\PNG => 'pdf/result-pdf-png.php',
        COUNTRY\WHO => 'pdf/result-pdf-who.php',
        COUNTRY\RWANDA => 'pdf/result-pdf-rwanda.php',
        COUNTRY\BURKINA_FASO => 'pdf/result-pdf-burkina-faso.php'
    ];

    // Allowed report formats for security (whitelist)
    $allowedReportFormats = [
        'pdf/result-pdf-ssudan.php',
        'pdf/result-pdf-sierraleone.php',
        'pdf/result-pdf-drc.php',
        'pdf/result-pdf-cameroon.php',
        'pdf/result-pdf-png.php',
        'pdf/result-pdf-who.php',
        'pdf/result-pdf-rwanda.php',
        'pdf/result-pdf-burkina-faso.php',
        'pdf/result-pdf-tb-custom.php',
        'pdf/result-pdf-tb-standard.php'
    ];

    $resultFilename = '';

    if (!empty($requestResult)) {
        $_SESSION['rVal'] = MiscUtility::generateRandomString(6);
        $pathFront = TEMP_PATH . DIRECTORY_SEPARATOR . $_SESSION['rVal'];
        MiscUtility::makeDirectory($pathFront);

        $pages = [];
        $page = 1;

        // Cache for report format configurations
        static $formatCache = [];

        foreach ($requestResult as $result) {
            try {
                // Set print time
                if (isset($result['result_printed_datetime']) && $result['result_printed_datetime'] != "") {
                    $printedTime = date('Y-m-d H:i:s', strtotime((string) $result['result_printed_datetime']));
                } else {
                    $printedTime = DateUtility::getCurrentDateTime();
                }

                $expStr = explode(" ", $printedTime);
                $printDate = DateUtility::humanReadableDateFormat($expStr[0]);
                $printDateTime = $expStr[1];

                // Update print timestamps
                if (($general->isLISInstance()) && empty($result['result_printed_on_lis_datetime'])) {
                    $pData = array(
                        'result_printed_on_lis_datetime' => $currentDateTime,
                        'result_printed_datetime' => $currentDateTime
                    );
                    $db->where('tb_id', $result['tb_id']);
                    $db->update('form_tb', $pData);
                } elseif (($general->isSTSInstance()) && empty($result['result_printed_on_sts_datetime'])) {
                    $pData = array(
                        'result_printed_on_sts_datetime' => $currentDateTime,
                        'result_printed_datetime' => $currentDateTime
                    );
                    $db->where('tb_id', $result['tb_id']);
                    $db->update('form_tb', $pData);
                }

                // Get TB test information
                $tbTestQuery = "SELECT * from tb_tests where tb_id = ? ORDER BY tb_test_id ASC";
                $tbTestInfo = $db->rawQuery($tbTestQuery, [$result['tb_id']]);

                // Get facility details
                $facilityQuery = "SELECT * from form_tb as c19 INNER JOIN facility_details as fd ON c19.facility_id=fd.facility_id where tb_id = ? GROUP BY fd.facility_id LIMIT 1";
                $facilityInfo = $db->rawQueryOne($facilityQuery, [$result['tb_id']]);

                // Handle patient name decryption
                $patientFname = ($general->crypto('doNothing', $result['patient_name'], $result['patient_id']));
                $patientLname = ($general->crypto('doNothing', $result['patient_surname'], $result['patient_id']));

                if (!empty($result['is_encrypted']) && $result['is_encrypted'] == 'yes') {
                    $result['patient_id'] = $general->crypto('decrypt', $result['patient_id'], $key);
                    $patientFname = $general->crypto('decrypt', $patientFname, $key);
                    $patientLname = $general->crypto('decrypt', $patientLname, $key);
                }

                // Get lab report signatories
                $signQuery = "SELECT * from lab_report_signatories where lab_id = ? AND test_types like '%tb%' AND signatory_status like 'active' ORDER BY display_order ASC";
                $signResults = $db->rawQuery($signQuery, array($result['lab_id']));

                $_SESSION['aliasPage'] = $page;

                if (!isset($result['labName'])) {
                    $result['labName'] = '';
                }

                $draftTextShow = false;

                // ENHANCED REPORT FORMAT SELECTION
                $selectedReportFormats = [];
                $reportFormatFile = null;
                $cacheKey = ($result['lab_id'] ?? 'default') . '_' . md5($result['reportFormat'] ?? '');

                // Use cached format if available
                if (isset($formatCache[$cacheKey])) {
                    $selectedReportFormats = $formatCache[$cacheKey];
                } else {
                    // Parse the report format configuration
                    if (isset($result['reportFormat']) && trim($result['reportFormat']) !== '') {
                        $selectedReportFormats = json_decode($result['reportFormat'], true);

                        // Check for JSON decode errors
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            error_log("JSON decode error for reportFormat (TB ID: {$result['tb_id']}): " . json_last_error_msg() . " - Data: " . $result['reportFormat']);
                            $selectedReportFormats = [];
                        }
                    }

                    // Cache the parsed format
                    $formatCache[$cacheKey] = $selectedReportFormats;
                }

                // Try to use custom TB report format
                if (!empty($selectedReportFormats) && !empty($selectedReportFormats['tb'])) {
                    // Security check - validate against whitelist
                    $requestedFormat = $selectedReportFormats['tb'];

                    // Prevent path traversal attacks
                    $requestedFormat = str_replace(['../', '.\\', '..\\'], '', $requestedFormat);

                    if (in_array($requestedFormat, $allowedReportFormats)) {
                        $customFormatPath = __DIR__ . DIRECTORY_SEPARATOR . $requestedFormat;

                        if (file_exists($customFormatPath) && is_readable($customFormatPath)) {
                            $reportFormatFile = $customFormatPath;
                            error_log("Using custom TB report format for TB ID {$result['tb_id']}: " . $requestedFormat);
                        } else {
                            error_log("Custom TB report format not found or not readable for TB ID {$result['tb_id']}: " . $customFormatPath);
                        }
                    } else {
                        error_log("Unauthorized TB report format requested for TB ID {$result['tb_id']}: " . $requestedFormat);
                    }
                }

                // Fall back to default format if custom format not available
                if ($reportFormatFile === null) {
                    if (isset($fileArray[$formId]) && !empty($fileArray[$formId])) {
                        $defaultFormatPath = __DIR__ . DIRECTORY_SEPARATOR . $fileArray[$formId];

                        if (file_exists($defaultFormatPath) && is_readable($defaultFormatPath)) {
                            $reportFormatFile = $defaultFormatPath;
                            error_log("Using default TB report format for TB ID {$result['tb_id']}, formId {$formId}: " . $fileArray[$formId]);
                        } else {
                            error_log("Default TB report format not found for TB ID {$result['tb_id']}: " . $defaultFormatPath);
                            throw new Exception("Default TB report format file not found: " . $defaultFormatPath);
                        }
                    } else {
                        error_log("No default format defined for TB ID {$result['tb_id']}, formId: " . $formId);

                        // Ultimate fallback - try to find any working format
                        foreach ($fileArray as $fallbackFormId => $fallbackFormat) {
                            $fallbackPath = __DIR__ . DIRECTORY_SEPARATOR . $fallbackFormat;
                            if (file_exists($fallbackPath) && is_readable($fallbackPath)) {
                                $reportFormatFile = $fallbackPath;
                                error_log("Using emergency fallback TB format for TB ID {$result['tb_id']}: " . $fallbackFormat);
                                break;
                            }
                        }

                        if ($reportFormatFile === null) {
                            throw new Exception("No TB report format available for TB ID: " . $result['tb_id']);
                        }
                    }
                }

                // Include the selected report format file
                if ($reportFormatFile !== null) {
                    include_once($reportFormatFile);
                    error_log("TB Report page {$page} generated for TB ID {$result['tb_id']} using format: " . basename($reportFormatFile));
                } else {
                    throw new Exception("Critical error: No TB report format available for TB ID: " . $result['tb_id']);
                }

                $page++;
            } catch (Exception $e) {
                error_log("Error processing TB ID {$result['tb_id']}: " . $e->getMessage());
                // Continue with next result instead of failing completely
                continue;
            }
        }

        // Concatenate all pages into final PDF
        if (!empty($pages)) {
            try {
                $resultPdf = new PdfConcatenateHelper();
                $resultPdf->setFiles($pages);
                $resultPdf->setPrintHeader(false);
                $resultPdf->setPrintFooter(false);
                $resultPdf->concat();

                $resultFilename = 'VLSM-TB-Test-result-' . date('d-M-Y-H-i-s') . "-" . MiscUtility::generateRandomString(6) . '.pdf';
                $resultPdf->Output(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename, "F");

                error_log("TB PDF report successfully generated: " . $resultFilename);
            } catch (Exception $e) {
                error_log("Error concatenating TB PDF pages: " . $e->getMessage());
                throw new Exception("Failed to generate final TB PDF report: " . $e->getMessage());
            }
        } else {
            error_log("No pages generated for TB PDF report");
            throw new Exception("No valid TB report pages were generated");
        }

        // Clean up temporary directory
        if (isset($pathFront)) {
            MiscUtility::removeDirectory($pathFront);
        }
        unset($_SESSION['rVal']);
    } else {
        error_log("No TB results found for PDF generation");
        throw new Exception("No TB results found to generate PDF report");
    }

    // Return the file path
    echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename);
} catch (Exception $e) {
    error_log("Critical error in TB PDF generation: " . $e->getMessage());

    // Clean up on error
    if (isset($pathFront) && is_dir($pathFront)) {
        MiscUtility::removeDirectory($pathFront);
    }
    if (isset($_SESSION['rVal'])) {
        unset($_SESSION['rVal']);
    }

    // Return error response
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to generate TB PDF report: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    error_log("Fatal error in TB PDF generation: " . $e->getMessage());

    // Clean up on fatal error
    if (isset($pathFront) && is_dir($pathFront)) {
        MiscUtility::removeDirectory($pathFront);
    }
    if (isset($_SESSION['rVal'])) {
        unset($_SESSION['rVal']);
    }

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'System error occurred while generating TB PDF report'
    ]);
}
