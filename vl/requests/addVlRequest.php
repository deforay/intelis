<?php
ob_start();
$title = "VL | Add New Request";

require_once(APPLICATION_PATH . '/header.php');

$labFieldDisabled = '';


$facilitiesDb = new \Vlsm\Models\Facilities();
$usersModel = new \Vlsm\Models\Users();

$healthFacilities = $facilitiesDb->getHealthFacilities('vl');
$testingLabs = $facilitiesDb->getTestingLabs('vl');

//get import config
$condition = "status = 'active'";
$importResult = $general->fetchDataFromTable('import_config', $condition);
$facilityMap = $facilitiesDb->getFacilityMap($_SESSION['userId']);
$userResult = $usersModel->getActiveUsers($facilityMap);
$userInfo = array();
foreach ($userResult as $user) {
    $userInfo[$user['user_id']] = ucwords($user['user_name']);
}

//sample rejection reason
$condition = "rejection_reason_status ='active'";
$rejectionResult = $general->fetchDataFromTable('r_vl_sample_rejection_reasons', $condition);

//rejection type
$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_vl_sample_rejection_reasons WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);

//get active sample types
$condition = "status = 'active'";
$sResult = $general->fetchDataFromTable('r_vl_sample_type', $condition);


//get vltest reason details
$testReason = $general->fetchDataFromTable('r_vl_test_reasons');
$pdResult = $general->fetchDataFromTable('province_details');
//get suspected treatment failure at
$suspectedTreatmentFailureAtQuery = "SELECT DISTINCT vl_sample_suspected_treatment_failure_at FROM form_vl where vlsm_country_id='" . $arr['vl_form'] . "'";
$suspectedTreatmentFailureAtResult = $db->rawQuery($suspectedTreatmentFailureAtQuery);
?>
<style>
    .ui_tpicker_second_label {
        display: none !important;
    }

    .ui_tpicker_second_slider {
        display: none !important;
    }

    .ui_tpicker_millisec_label {
        display: none !important;
    }

    .ui_tpicker_millisec_slider {
        display: none !important;
    }

    .ui_tpicker_microsec_label {
        display: none !important;
    }

    .ui_tpicker_microsec_slider {
        display: none !important;
    }

    .ui_tpicker_timezone_label {
        display: none !important;
    }

    .ui_tpicker_timezone {
        display: none !important;
    }

    .ui_tpicker_time_input {
        width: 100%;
    }
</style>
<?php
// if ($arr['vl_form'] == 1) {
//     require_once('forms/add-ssudan.php');
// } else if ($arr['vl_form'] == 2) {
//     require_once('forms/add-zimbabwe.php');
// } else if ($arr['vl_form'] == 3) {
//     require_once('forms/add-drc.php');
// } else if ($arr['vl_form'] == 4) {
//     require_once('forms/add-zambia.php');
// } else if ($arr['vl_form'] == 5) {
//     require_once('forms/add-png.php');
// } else if ($arr['vl_form'] == 6) {
//     require_once('forms/add-who.php');
// } else if ($arr['vl_form'] == 7) {
//     require_once('forms/add-rwanda.php');
// } else if ($arr['vl_form'] == 8) {
//     require_once('forms/add-angola.php');
// }



$fileArray = array(
    1 => 'forms/add-southsudan.php',
    2 => 'forms/add-zimbabwe.php',
    3 => 'forms/add-drc.php',
    4 => 'forms/add-zambia.php',
    5 => 'forms/add-png.php',
    6 => 'forms/add-who.php',
    7 => 'forms/add-rwanda.php',
    8 => 'forms/add-angola.php',
);
// print_r($arr['vl_form']);die;
// $arr['vl_form'] = 8;
require_once($fileArray[$arr['vl_form']]);

?>
<script>
    $(document).ready(function() {
        $('.date').datepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: 'dd-M-yy',
            timeFormat: "hh:mm TT",
            maxDate: "Today",
            yearRange: <?php echo (date('Y') - 100); ?> + ":" + "<?php echo (date('Y')) ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });
        $('.dateTime').datetimepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: 'dd-M-yy',
            timeFormat: "HH:mm",
            maxDate: "Today",
            onChangeMonthYear: function(year, month, widget) {
                setTimeout(function() {
                    $('.ui-datepicker-calendar').show();
                });
            },
            yearRange: <?php echo (date('Y') - 100); ?> + ":" + "<?php echo (date('Y')) ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });
        $('.date').mask('99-aaa-9999');
        $('.dateTime').mask('99-aaa-9999 99:99');
    });

    function checkSampleReceviedDate() {
        var sampleCollectionDate = $("#sampleCollectionDate").val();
        var sampleReceivedDate = $("#sampleReceivedDate").val();
        if ($.trim(sampleCollectionDate) != '' && $.trim(sampleReceivedDate) != '') {
            var scdf = $("#sampleCollectionDate").val().split(' ');
            var srdf = $("#sampleReceivedDate").val().split(' ');
            var scd = changeFormat(scdf[0]);
            var srd = changeFormat(srdf[0]);
            if (moment(scd + ' ' + scdf[1]).isAfter(srd + ' ' + srdf[1])) {
                <?php if ($arr['vl_form'] == '3') { ?>
                    //french
                    alert("L'échantillon de données reçues ne peut pas être antérieur à la date de collecte de l'échantillon!");
                <?php } else if ($arr['vl_form'] == '8') { ?>
                    //portugese
                    alert("Amostra de Data Recebida no Laboratório de Teste não pode ser anterior ao Data Hora de colheita!");
                <?php } else { ?>
                    alert("Sample Received Date cannot be earlier than Sample Collection Date!");
                <?php } ?>
                $('#sampleReceivedDate').val('');
            }
        }
    }

    function checkSampleReceviedAtHubDate() {
        var sampleCollectionDate = $("#sampleCollectionDate").val();
        var sampleReceivedAtHubOn = $("#sampleReceivedAtHubOn").val();
        if ($.trim(sampleCollectionDate) != '' && $.trim(sampleReceivedAtHubOn) != '') {
            var scdf = $("#sampleCollectionDate").val().split(' ');
            var stdl = $("#sampleReceivedAtHubOn").val().split(' ');
            var scd = changeFormat(scdf[0]);
            var std = changeFormat(stdl[0]);
            if (moment(scd + ' ' + scdf[1]).isAfter(std + ' ' + stdl[1])) {
                <?php if ($arr['vl_form'] == '3') { ?>
                    //french
                    alert("L'échantillon de données reçues ne peut pas être antérieur à la date de collecte de l'échantillon!");
                <?php } else if ($arr['vl_form'] == '8') { ?>
                    //portugese
                    alert("Amostra de Data Recebida no Laboratório de Teste não pode ser anterior ao Data Hora de colheita!");
                <?php } else { ?>
                    alert("Sample Received Date cannot be earlier than Sample Collection Date!");
                <?php } ?>
                $("#sampleTestingDateAtLab").val("");
            }
        }
    }

    function checkSampleTestingDate() {
        var sampleCollectionDate = $("#sampleCollectionDate").val();
        var sampleTestingDate = $("#sampleTestingDateAtLab").val();
        if ($.trim(sampleCollectionDate) != '' && $.trim(sampleTestingDate) != '') {
            var scdf = $("#sampleCollectionDate").val().split(' ');
            var stdl = $("#sampleTestingDateAtLab").val().split(' ');
            var scd = changeFormat(scdf[0]);
            var std = changeFormat(stdl[0]);
            if (moment(scd + ' ' + scdf[1]).isAfter(std + ' ' + stdl[1])) {
                <?php if ($arr['vl_form'] == '3') { ?>
                    //french
                    alert("La date d'essai de l'échantillon ne peut pas être antérieure à la date de collecte de l'échantillon!");
                <?php } else if ($arr['vl_form'] == '8') { ?>
                    //french
                    alert("Data de Teste de Amostras não pode ser anterior ao Data Hora de colheita!");
                <?php } else { ?>
                    alert("Sample Testing Date cannot be earlier than Sample Collection Date!");
                <?php } ?>
                $("#sampleTestingDateAtLab").val("");
            }
        }
    }

    function checkARTInitiationDate() {
        var dob = changeFormat($("#dob").val());
        var artInitiationDate = $("#dateOfArtInitiation").val();
        if ($.trim(dob) != '' && $.trim(artInitiationDate) != '') {
            var artInitiationDate = changeFormat($("#dateOfArtInitiation").val());
            if (moment(dob).isAfter(artInitiationDate)) {
                <?php if ($arr['vl_form'] == '3') { ?>
                    //french
                    alert("La date d'ouverture de l'ART ne peut pas être antérieure à!");
                <?php } else if ($arr['vl_form'] == '8') { ?>
                    //portugese
                    alert("Data de início de TARV não pode ser anterior ao Data de nascimento!");
                <?php } else { ?>
                    alert("ART Initiation Date cannot be earlier than DOB!");
                <?php } ?>
                $("#dateOfArtInitiation").val("");
            }
        }
    }

    function showPatientList() {
        $("#showEmptyResult").hide();
        if ($.trim($("#artPatientNo").val()) != '') {
            $.post("/vl/requests/checkPatientExist.php", {
                    artPatientNo: $("#artPatientNo").val()
                },
                function(data) {
                    if (data >= '1') {
                        showModal('patientModal.php?artNo=' + $.trim($("#artPatientNo").val()), 900, 520);
                    } else {
                        $("#showEmptyResult").show();
                    }
                });
        }
    }

    function checkPatientDetails(tableName, fieldName, obj, fnct) {
        if ($.trim(obj.value) != '') {
            $.post("/includes/checkDuplicate.php", {
                    tableName: tableName,
                    fieldName: fieldName,
                    value: obj.value,
                    fnct: fnct,
                    format: "html"
                },
                function(data) {
                    if (data === '1') {
                        showModal('patientModal.php?artNo=' + obj.value, 900, 520);
                    }
                });
        }
    }

    function checkSampleNameValidation(tableName, fieldName, id, fnct, alrt) {
        if ($.trim($("#" + id).val()) != '') {
            //$.blockUI();
            $.post("/vl/requests/checkSampleDuplicate.php", {
                    tableName: tableName,
                    fieldName: fieldName,
                    value: $("#" + id).val(),
                    fnct: fnct,
                    format: "html"
                },
                function(data) {
                    if (data != 0) {
                        <?php if (isset($sarr['sc_user_type']) && ($sarr['sc_user_type'] == 'remoteuser' || $sarr['sc_user_type'] == 'standalone')) { ?>
                            alert(alrt);
                            $("#" + id).val('');
                            <?php if ($arr['vl_form'] == '3') { ?>
                                $("#sampleCodeValue").html('').hide();
                            <?php }
                        } else { ?>
                            data = data.split("##");
                            document.location.href = "editVlRequest.php?id=" + data[0] + "&c=" + data[1];
                        <?php } ?>
                    }
                });
            //$.unblockUI();
        }
    }

    function insertSampleCode(formId, vlSampleId, sampleCode, sampleCodeKey, sampleCodeFormat, countryId, sampleCollectionDate, provinceCode = null, provinceId = null) {
        $.blockUI();
        $.post("/vl/requests/insertNewSample.php", {
                sampleCode: $("#" + sampleCode).val(),
                sampleCodeKey: $("#" + sampleCodeKey).val(),
                sampleCodeFormat: $("#" + sampleCodeFormat).val(),
                countryId: countryId,
                sampleCollectionDate: $("#" + sampleCollectionDate).val(),
                provinceCode: provinceCode,
                provinceId: provinceId
            },
            function(data) {
                if (data > 0) {
                    $.unblockUI();
                    document.getElementById("vlSampleId").value = data;
                    document.getElementById(formId).submit();
                } else {
                    $.unblockUI();
                    //$("#sampleCollectionDate").val('');
                    sampleCodeGeneration();
                    alert("We could not save this form. Please try saving again.");
                }
            });
    }

    function checkARTRegimenValue() {
        var artRegimen = $("#artRegimen").val();
        if (artRegimen == 'other') {
            $(".newArtRegimen").show();
            $("#newArtRegimen").addClass("isRequired");
            $("#newArtRegimen").focus();
        } else {
            $(".newArtRegimen").hide();
            $("#newArtRegimen").removeClass("isRequired");
            $('#newArtRegimen').val("");
        }
    }

    function getAge() {
        var agYrs = '';
        var agMnths = '';
        var dob = changeFormat($("#dob").val());
        if ($.trim(dob) != '') {
            //calculate age
            var years = moment().diff(dob, 'years', false);
            var months = (years == 0) ? moment().diff(dob, 'months', false) : '';
            $("#ageInYears").val(years); // Gives difference as years
            $("#ageInMonths").val(months); // Gives difference as months
        }
    }

    function clearDOB(val) {
        if ($.trim(val) != "") {
            $("#dob").val("");
        }
    }

    function changeFormat(date) {
        splitDate = date.split("-");
        var fDate = new Date(splitDate[1] + splitDate[2] + ", " + splitDate[0]);
        var monthDigit = fDate.getMonth();
        var fMonth = isNaN(monthDigit) ? 1 : (parseInt(monthDigit) + parseInt(1));
        fMonth = (fMonth < 10) ? '0' + fMonth : fMonth;
        return splitDate[2] + '-' + fMonth + '-' + splitDate[0];
    }
</script>
<?php include APPLICATION_PATH . '/footer.php'; ?>