<?php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\FacilitiesService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Services\CommonService;


$title = "EID | Edit Request";

require_once APPLICATION_PATH . '/header.php';
?>
<style>
    .ui_tpicker_second_label,
    .ui_tpicker_second_slider,
    .ui_tpicker_millisec_label,
    .ui_tpicker_millisec_slider,
    .ui_tpicker_microsec_label,
    .ui_tpicker_microsec_slider,
    .ui_tpicker_timezone_label,
    .ui_tpicker_timezone {
        display: none !important;
    }

    .ui_tpicker_time_input {
        width: 100%;
    }
</style>



<?php


$labFieldDisabled = '';



/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var CommonService $commonService */
$general = ContainerRegistry::get(CommonService::class);

//Funding source list
$fundingSourceList = $general->getFundingSources();

//Implementing partner list
$implementingPartnerList = $general->getImplementationPartners();

$healthFacilities = $facilitiesService->getHealthFacilities('eid');
$healthFacilitiesAllColumns = $facilitiesService->getHealthFacilities('eid', false, true);

$testingLabs = $facilitiesService->getTestingLabs('eid');
$userResult = $usersService->getActiveUsers($_SESSION['facilityMap']);
$userInfo = [];
foreach ($userResult as $user) {
    $userInfo[$user['user_id']] = ($user['user_name']);
}

$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_eid_sample_rejection_reasons WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);

//sample rejection reason
$rejectionQuery = "SELECT * FROM r_eid_sample_rejection_reasons where rejection_reason_status = 'active'";
$rejectionResult = $db->rawQuery($rejectionQuery);

// $condition = "status = 'active'";
// if (isset($vlfmResult[0]['facilityId'])) {
//     $condition = $condition . " AND facility_id IN(" . $vlfmResult[0]['facilityId'] . ")";
// }
// $fResult = $general->fetchDataFromTable('facility_details', $condition);


// //get lab facility details
// $condition = "facility_type='2' AND status='active'";
// $lResult = $general->fetchDataFromTable('facility_details', $condition);


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = $request->getQueryParams();
$id = (isset($_GET['id'])) ? base64_decode((string) $_GET['id']) : null;

//$id = ($_GET['id']);
$eidQuery = "SELECT * from form_eid where eid_id=?";
$eidInfo = $db->rawQueryOne($eidQuery, array($id));


$sampleResult = $general->fetchDataFromTable('r_eid_sample_type', "status = 'active'");

$arr = $general->getGlobalConfig();


if ($arr['eid_sample_code'] == 'auto' || $arr['eid_sample_code'] == 'auto2' || $arr['eid_sample_code'] == 'alphanumeric') {
    $sampleClass = '';
    $maxLength = '';
    if ($arr['eid_max_length'] != '' && $arr['eid_sample_code'] == 'alphanumeric') {
        $maxLength = $arr['eid_max_length'];
        $maxLength = "maxlength=" . $maxLength;
    }
} else {
    $sampleClass = '';
    $maxLength = '';
    if ($arr['eid_max_length'] != '') {
        $maxLength = $arr['eid_max_length'];
        $maxLength = "maxlength=" . $maxLength;
    }
}

$iResultQuery = "select * from  instrument_machines";
$iResult = $db->rawQuery($iResultQuery);
$machine = [];
foreach ($iResult as $val) {
    $machine[$val['config_machine_id']] = $val['config_machine_name'];
}

$testPlatformResult = $general->getTestingPlatforms('eid');
foreach ($testPlatformResult as $row) {
    $testPlatformList[$row['machine_name']] = $row['machine_name'];
}


if (isset($eidInfo['sample_collection_date']) && trim((string) $eidInfo['sample_collection_date']) != '' && $eidInfo['sample_collection_date'] != '0000-00-00 00:00:00') {
    $sampleCollectionDate = $eidInfo['sample_collection_date'];
    $expStr = explode(" ", (string) $eidInfo['sample_collection_date']);
    $eidInfo['sample_collection_date'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
    $sampleCollectionDate = '';
    $eidInfo['sample_collection_date'] = '';
}
if (isset($eidInfo['sample_received_at_lab_datetime']) && trim((string) $eidInfo['sample_received_at_lab_datetime']) != '' && $eidInfo['sample_received_at_lab_datetime'] != '0000-00-00 00:00:00') {
    $sampleCollectionDate = $eidInfo['sample_received_at_lab_datetime'];
    $expStr = explode(" ", (string) $eidInfo['sample_received_at_lab_datetime']);
    $eidInfo['sample_received_at_lab_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
    $sampleCollectionDate = '';
    $eidInfo['sample_received_at_lab_datetime'] = '';
}
if (isset($eidInfo['sample_tested_datetime']) && trim((string) $eidInfo['sample_tested_datetime']) != '' && $eidInfo['sample_tested_datetime'] != '0000-00-00 00:00:00') {
    $sampleCollectionDate = $eidInfo['sample_tested_datetime'];
    $expStr = explode(" ", (string) $eidInfo['sample_tested_datetime']);
    $eidInfo['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
    $sampleCollectionDate = '';
    $eidInfo['sample_tested_datetime'] = '';
}

if (isset($eidInfo['result_approved_datetime']) && trim((string) $eidInfo['result_approved_datetime']) != '' && $eidInfo['result_approved_datetime'] != '0000-00-00 00:00:00') {
    $expStr = explode(" ", (string) $eidInfo['result_approved_datetime']);
    $eidInfo['result_approved_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
    $eidInfo['result_approved_datetime'] = '';
}

if (isset($eidInfo['result_reviewed_datetime']) && trim((string) $eidInfo['result_reviewed_datetime']) != '' && $eidInfo['result_reviewed_datetime'] != '0000-00-00 00:00:00') {
    $expStr = explode(" ", (string) $eidInfo['result_reviewed_datetime']);
    $eidInfo['result_reviewed_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
    $eidInfo['result_reviewed_datetime'] = '';
}

if (isset($eidInfo['result_dispatched_datetime']) && trim((string) $eidInfo['result_dispatched_datetime']) != '' && $eidInfo['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
    $expStr = explode(" ", (string) $eidInfo['result_dispatched_datetime']);
    $eidInfo['result_dispatched_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
    $eidInfo['result_dispatched_datetime'] = '';
}
//Recommended corrective actions
$condition = "status ='active' AND test_type='eid'";
$correctiveActions = $general->fetchDataFromTable('r_recommended_corrective_actions', $condition);

if (!empty($eidInfo['is_encrypted']) && $eidInfo['is_encrypted'] == 'yes') {
    $key = (string) $general->getGlobalConfig('key');
    $eidInfo['child_id'] = $general->crypto('decrypt', $eidInfo['child_id'], $key);
    $eidInfo['mother_id'] = $general->crypto('decrypt', $eidInfo['mother_id'], $key);

    if ($eidInfo['child_name'] != '') {
        $eidInfo['child_name'] = $general->crypto('decrypt', $eidInfo['child_name'], $key);
    }
    if ($eidInfo['mother_name'] != '') {
        $eidInfo['mother_name'] = $general->crypto('decrypt', $eidInfo['mother_name'], $key);
    }

    if ($eidInfo['child_surname'] != '') {
        $eidInfo['child_surname'] = $general->crypto('decrypt', $eidInfo['child_surname'], $key);
    }

    if ($eidInfo['mother_surname'] != '') {
        $eidInfo['mother_surname'] = $general->crypto('decrypt', $eidInfo['mother_surname'], $key);
    }
}

$minPatientIdLength = 0;
if (isset($arr['eid_min_patient_id_length']) && $arr['eid_min_patient_id_length'] != "") {
    $minPatientIdLength = $arr['eid_min_patient_id_length'];
}


$fileArray = array(
    COUNTRY\SOUTH_SUDAN => 'forms/edit-southsudan.php',
    COUNTRY\SIERRA_LEONE => 'forms/edit-sierraleone.php',
    COUNTRY\DRC => 'forms/edit-drc.php',
    COUNTRY\CAMEROON => 'forms/edit-cameroon.php',
    COUNTRY\PNG => 'forms/edit-png.php',
    COUNTRY\WHO => 'forms/edit-who.php',
    COUNTRY\RWANDA => 'forms/edit-rwanda.php'
);

require_once($fileArray[$arr['vl_form']]);

?>

<script>
    function checkSampleNameValidation(tableName, fieldName, id, fnct, alrt) {
        if ($.trim($("#" + id).val()) != '') {
            $.blockUI();
            $.post("/eid/requests/check-sample-duplicate.php", {
                    tableName: tableName,
                    fieldName: fieldName,
                    value: $("#" + id).val(),
                    fnct: fnct,
                    format: "html"
                },
                function(data) {
                    if (data != 0) {
                        // Toastify({
                        //     text: "<?= _translate('This Sample Code already exists', true) ?>",
                        //     duration: 3000,
                        //     style: {
                        //         background: 'red',
                        //     }
                        // }).showToast();
                    }
                });
            $.unblockUI();
        }
    }

    function updateSampleResult() {
        if ($('#isSampleRejected').val() == "yes") {
            $('.rejected').show();
            $('#sampleRejectionReason').addClass('isRequired');
            $('#sampleTestedDateTime,#result').val('');
            $('#sampleTestedDateTime,#result').removeClass('isRequired');
            $(".result-optional").removeClass("isRequired");
        } else if ($('#isSampleRejected').val() == "no") {
            $('.rejected').hide();
            $('#sampleRejectionReason').val('');
            $('#sampleRejectionReason').removeClass('isRequired');
            $('#sampleTestedDateTime').addClass('isRequired');
            $('#result').addClass('isRequired');
        } else {
            $('.rejected').hide();
            $('#sampleRejectionReason').val('');
            $('#sampleRejectionReason').removeClass('isRequired');
            $('#sampleTestedDateTime').removeClass('isRequired');
            $('#result').removeClass('isRequired');
        }

        if ($('#result').val() == "") {
            $('#sampleTestedDateTime').removeClass('isRequired');
            $('#result').removeClass('isRequired');
        } else {
            $('#sampleTestedDateTime').addClass('isRequired');
            $('#result').addClass('isRequired');
        }
    }
    var patientSearchTimeout = null;

    function showPatientList(patientCode, timeOutDuration) {
        if (patientSearchTimeout != null) {
            clearTimeout(patientSearchTimeout);
        }
        patientSearchTimeout = setTimeout(function() {
            patientSearchTimeout = null;

            $("#showEmptyResult").hide();
            if ($.trim(patientCode) != '') {
                $.post("/eid/requests/search-patients.php", {
                        artPatientNo: $.trim(patientCode)
                    },
                    function(data) {
                        if (data >= '1') {
                            showModal('patientModal.php?artNo=' + $.trim(patientCode), 900, 520);
                        } else {
                            $("#showEmptyResult").show();
                        }
                    });
            }


        }, timeOutDuration);

    }

    $(document).ready(function() {
        updateSampleResult();
        $("#isSampleRejected,#result").on("change", function() {
            updateSampleResult();
        });

        $('.date').datepicker({
            changeMonth: true,
            changeYear: true,
            onSelect: function() {
                $(this).change();
            },
            dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
            timeFormat: "HH:mm",
            maxDate: "Today",
            yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });


        $("#childDob").datepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
            minDate: "-48m",
            maxDate: "Today",
            onSelect: function(dateText, inst) {
                $("#sampleCollectionDate").datepicker("option", "minDate", $("#childDob").datepicker("getDate"));
                $(this).change();
            }
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });

        $("#mothersDob").datepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
            maxDate: "Today",
            yearRange: <?php echo (date('Y') - 120); ?> + ":" + "<?= date('Y') ?>",
            onSelect: function(dateText, inst) {
                $("#sampleCollectionDate").datepicker("option", "minDate", $("#patientDob").datepicker("getDate"));
                $(this).change();
            }
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });


        $('.dateTime').datetimepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
            timeFormat: "HH:mm",
            maxDate: "Today",
            onChangeMonthYear: function(year, month, widget) {
                setTimeout(function() {
                    $('.ui-datepicker-calendar').show();
                });
            },
            yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });

        $('#sampleCollectionDate').datetimepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
            timeFormat: "HH:mm",
            maxDate: "Today",
            onChangeMonthYear: function(year, month, widget) {
                setTimeout(function() {
                    $('.ui-datepicker-calendar').show();
                });
            },
            onSelect: function(e) {
                $('#sampleReceivedDate').val('');
                $('#sampleReceivedDate').datetimepicker('option', 'minDate', e);
            },
            yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });

        $('#sampleReceivedDate').datetimepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
            timeFormat: "HH:mm",
            maxDate: "Today",
            onChangeMonthYear: function(year, month, widget) {
                setTimeout(function() {
                    $('.ui-datepicker-calendar').show();
                });
            },
            onSelect: function(e) {
                $('#sampleTestedDateTime').val('');
                $('#sampleTestedDateTime').datetimepicker('option', 'minDate', e);
            },
            yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });

        $('#sampleTestedDateTime').datetimepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
            timeFormat: "HH:mm",
            maxDate: "Today",
            onChangeMonthYear: function(year, month, widget) {
                setTimeout(function() {
                    $('.ui-datepicker-calendar').show();
                });
            },
            onSelect: function(e) {
                $('#approvedOnDateTime').val('');
                $('#approvedOnDateTime').datetimepicker('option', 'minDate', e);
            },
            yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });

        $('#approvedOnDateTime').datetimepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
            timeFormat: "HH:mm",
            maxDate: "Today",
            onChangeMonthYear: function(year, month, widget) {
                setTimeout(function() {
                    $('.ui-datepicker-calendar').show();
                });
            },
            onSelect: function(e) {},
            yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });
        //$('.date').mask('<?= $_SESSION['jsDateFormatMask'] ?? '99-aaa-9999'; ?>');
        //$('.dateTime').mask('<?= $_SESSION['jsDateFormatMask'] ?? '99-aaa-9999' ?> 99:99');
        $('.result-focus').change(function(e) {
            var status = false;
            $(".result-focus").each(function(index) {
                if ($(this).val() != "") {
                    status = true;
                }
            });
            if (status) {
                $('.change-reason').show();
                $('#reasonForChanging').addClass('isRequired');
            } else {
                $('.change-reason').hide();
                $('#reasonForChanging').removeClass('isRequired');
            }
        });
    });


    function calculateAgeInMonths() {
        var dateOfBirth = moment($("#childDob").val(), '<?= $_SESSION['jsDateRangeFormat'] ?? 'DD-MMM-YYYY'; ?>');
        $("#childAge").val(moment().diff(dateOfBirth, 'months'));
    }
</script>



<?php

require_once APPLICATION_PATH . '/footer.php';
