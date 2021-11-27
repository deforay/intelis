<?php
ob_start();
#require_once('../../startup.php');
include_once(APPLICATION_PATH . '/header.php');

$sCode = $labFieldDisabled = '';


$facilitiesDb = new \Vlsm\Models\Facilities();
$usersModel = new \Vlsm\Models\Users();
$healthFacilities = $facilitiesDb->getHealthFacilities('vl');
$testingLabs = $facilitiesDb->getTestingLabs('vl');


if ($_SESSION['instanceType'] == 'remoteuser') {
     $labFieldDisabled = 'disabled="disabled"';
}

$id = base64_decode($_GET['id']);

//get import config
$importQuery = "SELECT * FROM import_config WHERE status = 'active'";
$importResult = $db->query($importQuery);

$facilityMap = $facilitiesDb->getFacilityMap($_SESSION['userId']);
$userResult = $usersModel->getActiveUsers($facilityMap);
$userInfo = array();
foreach ($userResult as $user) {
     $userInfo[$user['user_id']] = ucwords($user['user_name']);
}
//sample rejection reason
$rejectionQuery = "SELECT * FROM r_vl_sample_rejection_reasons where rejection_reason_status = 'active'";
$rejectionResult = $db->rawQuery($rejectionQuery);
//rejection type
$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_vl_sample_rejection_reasons WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);
//sample status
$statusQuery = "SELECT * FROM r_sample_status WHERE `status` = 'active' AND status_id NOT IN(9,8)";
$statusResult = $db->rawQuery($statusQuery);

$pdQuery = "SELECT * FROM province_details";
$pdResult = $db->query($pdQuery);

$sQuery = "SELECT * FROM r_vl_sample_type WHERE status='active'";
$sResult = $db->query($sQuery);

//get vl test reason list
$vlTestReasonQuery = "SELECT * FROM r_vl_test_reasons WHERE test_reason_status = 'active'";
$vlTestReasonResult = $db->query($vlTestReasonQuery);

//get suspected treatment failure at
$suspectedTreatmentFailureAtQuery = "SELECT DISTINCT vl_sample_suspected_treatment_failure_at FROM vl_request_form where vlsm_country_id='" . $arr['vl_form'] . "'";
$suspectedTreatmentFailureAtResult = $db->rawQuery($suspectedTreatmentFailureAtQuery);

$vlQuery = "SELECT * FROM vl_request_form WHERE vl_sample_id=?";
$vlQueryInfo = $db->rawQueryOne($vlQuery, array($id));
if (isset($vlQueryInfo['patient_dob']) && trim($vlQueryInfo['patient_dob']) != '' && $vlQueryInfo['patient_dob'] != '0000-00-00') {
     $vlQueryInfo['patient_dob'] = $general->humanDateFormat($vlQueryInfo['patient_dob']);
} else {
     $vlQueryInfo['patient_dob'] = '';
}

if (isset($vlQueryInfo['sample_collection_date']) && trim($vlQueryInfo['sample_collection_date']) != '' && $vlQueryInfo['sample_collection_date'] != '0000-00-00 00:00:00') {
     $sampleCollectionDate = $vlQueryInfo['sample_collection_date'];
     $expStr = explode(" ", $vlQueryInfo['sample_collection_date']);
     $vlQueryInfo['sample_collection_date'] = $general->humanDateFormat($expStr[0]) . " " . $expStr[1];
} else {
     $sampleCollectionDate = '';
     $vlQueryInfo['sample_collection_date'] = $general->getDateTime();
}

if (isset($vlQueryInfo['sample_dispatched_datetime']) && trim($vlQueryInfo['sample_dispatched_datetime']) != '' && $vlQueryInfo['sample_dispatched_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", $vlQueryInfo['sample_dispatched_datetime']);
	$vlQueryInfo['sample_dispatched_datetime'] = $general->humanDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$vlQueryInfo['sample_dispatched_datetime'] = '';
}

if (isset($vlQueryInfo['result_approved_datetime']) && trim($vlQueryInfo['result_approved_datetime']) != '' && $vlQueryInfo['result_approved_datetime'] != '0000-00-00 00:00:00') {
     $sampleCollectionDate = $vlQueryInfo['result_approved_datetime'];
     $expStr = explode(" ", $vlQueryInfo['result_approved_datetime']);
     $vlQueryInfo['result_approved_datetime'] = $general->humanDateFormat($expStr[0]) . " " . $expStr[1];
} else {
     $sampleCollectionDate = '';
     $vlQueryInfo['result_approved_datetime'] = $general->humanDateFormat($general->getDateTime());
}

if (isset($vlQueryInfo['treatment_initiated_date']) && trim($vlQueryInfo['treatment_initiated_date']) != '' && $vlQueryInfo['treatment_initiated_date'] != '0000-00-00') {
     $vlQueryInfo['treatment_initiated_date'] = $general->humanDateFormat($vlQueryInfo['treatment_initiated_date']);
} else {
     $vlQueryInfo['treatment_initiated_date'] = '';
}

if (isset($vlQueryInfo['date_of_initiation_of_current_regimen']) && trim($vlQueryInfo['date_of_initiation_of_current_regimen']) != '' && $vlQueryInfo['date_of_initiation_of_current_regimen'] != '0000-00-00') {
     $vlQueryInfo['date_of_initiation_of_current_regimen'] = $general->humanDateFormat($vlQueryInfo['date_of_initiation_of_current_regimen']);
} else {
     $vlQueryInfo['date_of_initiation_of_current_regimen'] = '';
}

if (isset($vlQueryInfo['test_requested_on']) && trim($vlQueryInfo['test_requested_on']) != '' && $vlQueryInfo['test_requested_on'] != '0000-00-00') {
     $vlQueryInfo['test_requested_on'] = $general->humanDateFormat($vlQueryInfo['test_requested_on']);
} else {
     $vlQueryInfo['test_requested_on'] = '';
}


if (isset($vlQueryInfo['sample_received_at_hub_datetime']) && trim($vlQueryInfo['sample_received_at_hub_datetime']) != '' && $vlQueryInfo['sample_received_at_hub_datetime'] != '0000-00-00 00:00:00') {
     $expStr = explode(" ", $vlQueryInfo['sample_received_at_hub_datetime']);
     $vlQueryInfo['sample_received_at_hub_datetime'] = $general->humanDateFormat($expStr[0]) . " " . $expStr[1];
} else {
     $vlQueryInfo['sample_received_at_hub_datetime'] = '';
}


if (isset($vlQueryInfo['sample_received_at_vl_lab_datetime']) && trim($vlQueryInfo['sample_received_at_vl_lab_datetime']) != '' && $vlQueryInfo['sample_received_at_vl_lab_datetime'] != '0000-00-00 00:00:00') {
     $expStr = explode(" ", $vlQueryInfo['sample_received_at_vl_lab_datetime']);
     $vlQueryInfo['sample_received_at_vl_lab_datetime'] = $general->humanDateFormat($expStr[0]) . " " . $expStr[1];
} else {
     $vlQueryInfo['sample_received_at_vl_lab_datetime'] = '';
}


if (isset($vlQueryInfo['sample_tested_datetime']) && trim($vlQueryInfo['sample_tested_datetime']) != '' && $vlQueryInfo['sample_tested_datetime'] != '0000-00-00 00:00:00') {
     $expStr = explode(" ", $vlQueryInfo['sample_tested_datetime']);
     $vlQueryInfo['sample_tested_datetime'] = $general->humanDateFormat($expStr[0]) . " " . $expStr[1];
} else {
     $vlQueryInfo['sample_tested_datetime'] = '';
}

if (isset($vlQueryInfo['result_dispatched_datetime']) && trim($vlQueryInfo['result_dispatched_datetime']) != '' && $vlQueryInfo['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
     $expStr = explode(" ", $vlQueryInfo['result_dispatched_datetime']);
     $vlQueryInfo['result_dispatched_datetime'] = $general->humanDateFormat($expStr[0]) . " " . $expStr[1];
} else {
     $vlQueryInfo['result_dispatched_datetime'] = '';
}
if (isset($vlQueryInfo['last_viral_load_date']) && trim($vlQueryInfo['last_viral_load_date']) != '' && $vlQueryInfo['last_viral_load_date'] != '0000-00-00') {
     $vlQueryInfo['last_viral_load_date'] = $general->humanDateFormat($vlQueryInfo['last_viral_load_date']);
} else {
     $vlQueryInfo['last_viral_load_date'] = '';
}
//Set Date of demand
if (isset($vlQueryInfo['date_test_ordered_by_physician']) && trim($vlQueryInfo['date_test_ordered_by_physician']) != '' && $vlQueryInfo['date_test_ordered_by_physician'] != '0000-00-00') {
     $vlQueryInfo['date_test_ordered_by_physician'] = $general->humanDateFormat($vlQueryInfo['date_test_ordered_by_physician']);
} else {
     $vlQueryInfo['date_test_ordered_by_physician'] = '';
}
//Has patient changed regimen section
if (trim($vlQueryInfo['has_patient_changed_regimen']) == "yes") {
     if (isset($vlQueryInfo['regimen_change_date']) && trim($vlQueryInfo['regimen_change_date']) != '' && $vlQueryInfo['regimen_change_date'] != '0000-00-00') {
          $vlQueryInfo['regimen_change_date'] = $general->humanDateFormat($vlQueryInfo['regimen_change_date']);
     } else {
          $vlQueryInfo['regimen_change_date'] = '';
     }
} else {
     $vlQueryInfo['reason_for_regimen_change'] = '';
     $vlQueryInfo['regimen_change_date'] = '';
}
//Set Dispatched From Clinic To Lab Date
if (isset($vlQueryInfo['date_dispatched_from_clinic_to_lab']) && trim($vlQueryInfo['date_dispatched_from_clinic_to_lab']) != '' && $vlQueryInfo['date_dispatched_from_clinic_to_lab'] != '0000-00-00 00:00:00') {
     $expStr = explode(" ", $vlQueryInfo['date_dispatched_from_clinic_to_lab']);
     $vlQueryInfo['date_dispatched_from_clinic_to_lab'] = $general->humanDateFormat($expStr[0]) . " " . $expStr[1];
} else {
     $vlQueryInfo['date_dispatched_from_clinic_to_lab'] = '';
}
//Set Date of result printed datetime
if (isset($vlQueryInfo['result_printed_datetime']) && trim($vlQueryInfo['result_printed_datetime']) != "" && $vlQueryInfo['result_printed_datetime'] != '0000-00-00 00:00:00') {
     $expStr = explode(" ", $vlQueryInfo['result_printed_datetime']);
     $vlQueryInfo['result_printed_datetime'] = $general->humanDateFormat($expStr[0]) . " " . $expStr[1];
} else {
     $vlQueryInfo['result_printed_datetime'] = '';
}
//reviewed datetime
if (isset($vlQueryInfo['result_reviewed_datetime']) && trim($vlQueryInfo['result_reviewed_datetime']) != '' && $vlQueryInfo['result_reviewed_datetime'] != null && $vlQueryInfo['result_reviewed_datetime'] != '0000-00-00 00:00:00') {
     $expStr = explode(" ", $vlQueryInfo['result_reviewed_datetime']);
     $vlQueryInfo['result_reviewed_datetime'] = $general->humanDateFormat($expStr[0]) . " " . $expStr[1];
} else {
     $vlQueryInfo['result_reviewed_datetime'] = '';
}

if ($vlQueryInfo['patient_first_name'] != '') {
     $patientFirstName = $general->crypto('decrypt', $vlQueryInfo['patient_first_name'], $vlQueryInfo['patient_art_no']);
} else {
     $patientFirstName = '';
}
if ($vlQueryInfo['patient_middle_name'] != '') {
     $patientMiddleName = $general->crypto('decrypt', $vlQueryInfo['patient_middle_name'], $vlQueryInfo['patient_art_no']);
} else {
     $patientMiddleName = '';
}
if ($vlQueryInfo['patient_last_name'] != '') {
     $patientLastName = $general->crypto('decrypt', $vlQueryInfo['patient_last_name'], $vlQueryInfo['patient_art_no']);
} else {
     $patientLastName = '';
}

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


if ($arr['vl_form'] == 1) {
     require_once('forms/edit-southsudan.php');
} else if ($arr['vl_form'] == 2) {
     require_once('forms/edit-zimbabwe.php');
} else if ($arr['vl_form'] == 3) {
     require_once('forms/edit-drc.php');
} else if ($arr['vl_form'] == 4) {
     require_once('forms/edit-zambia.php');
} else if ($arr['vl_form'] == 5) {
     require_once('forms/edit-png.php');
} else if ($arr['vl_form'] == 6) {
     require_once('forms/edit-who.php');
} else if ($arr['vl_form'] == 7) {
     require_once('forms/edit-rwanda.php');
} else if ($arr['vl_form'] == 8) {
     require_once('forms/edit-angola.php');
}
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

          $('.result-focus').change(function(e) {
               var status = false;
               $(".result-focus").each(function(index) {
                    if ($(this).val() != "") {
                         status = true;
                    }
               });
               if (status) {
                    $('.change-reason').show();
                    $('#reasonForResultChanges').addClass('isRequired');
               } else {
                    $('.change-reason').hide();
                    $('#reasonForResultChanges').removeClass('isRequired');
               }
          });
     });

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

     function checkSampleNameValidation(tableName, fieldName, id, fnct, alrt) {
          if ($.trim($("#" + id).val()) != '') {
               $.blockUI();
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
                              <?php } else { ?>
                                   data = data.split("##");
                                   document.location.href = "editVlRequest.php?id=" + data[0] + "&c=" + data[1];
                              <?php } ?>
                         }
                    });
               $.unblockUI();
          }
     }

     function getAge() {
          var agYrs = '';
          var agMnths = '';
          var dob = changeFormat($("#dob").val());
          if (agYrs == '' && $("#dob").val() != '') {
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

     function changeFormat(date) {
          splitDate = date.split("-");
          var fDate = new Date(splitDate[1] + splitDate[2] + ", " + splitDate[0]);
          var monthDigit = fDate.getMonth();
          var fMonth = isNaN(monthDigit) ? 1 : (parseInt(monthDigit) + parseInt(1));
          fMonth = (fMonth < 10) ? '0' + fMonth : fMonth;
          format = splitDate[2] + '-' + fMonth + '-' + splitDate[0];
          return format;
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
</script>
<?php include(APPLICATION_PATH . '/footer.php'); ?>