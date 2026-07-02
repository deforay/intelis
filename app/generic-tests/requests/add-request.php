<?php

use App\Services\UsersService;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = $GLOBALS['request'];
$_GET = _sanitizeInput($request->getQueryParams());

$title = " Add New Test";

require_once APPLICATION_PATH . '/header.php';

$labFieldDisabled = '';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var GenericTestsService $genericTestsService */
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

//Funding source list
$fundingSourceList = $general->getFundingSources();

//Implementing partner list
$implementingPartnerList = $general->getImplementationPartners();

$arr = $general->getGlobalConfig();

$healthFacilities = $facilitiesService->getHealthFacilities('generic-tests');
$testingLabs = $facilitiesService->getTestingLabs('generic-tests');

// get instruments
$condition = "status = 'active'";
$importResult = $general->fetchDataFromTable('instruments', $condition);
$userResult = $usersService->getActiveUsers($_SESSION['facilityMap']);
$reasonForFailure = $genericTestsService->getReasonForFailure();

/* To get testing platform names */
$testPlatformResult = $general->getTestingPlatforms('generic-tests');
foreach ($testPlatformResult as $row) {
     $testPlatformList[$row['machine_name']] = $row['machine_name'];
}

$userInfo = [];
foreach ($userResult as $user) {
     $userInfo[$user['user_id']] = ($user['user_name']);
}

//sample rejection reason
$condition = "rejection_reason_status ='active'";
$rejectionResult = $general->fetchDataFromTable('r_generic_sample_rejection_reasons', $condition);

//rejection type
$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_generic_sample_rejection_reasons WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);

//get active sample types
$condition1 = "sample_type_status = 'active'";
$sResult = $general->fetchDataFromTable('r_generic_sample_types', $condition1);
//echo '<pre>'; print_r($sResult); die;
//get vltest reason details
$testReason = $general->fetchDataFromTable('r_generic_test_reasons');
$pdResult = $general->fetchDataFromTable('geographical_divisions');

$testResultUnits = $general->getDataByTableAndFields("r_generic_test_result_units", ["unit_id", "unit_name"], true, "unit_status='active'");

$lResult = $facilitiesService->getTestingLabs('generic-tests', true, true);

if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'alphanumeric' || $arr['sample_code'] == 'MMYY' || $arr['sample_code'] == 'YY') {
     $sampleClass = '';
     $maxLength = '';
     if ($arr['max_length'] != '' && $arr['sample_code'] == 'alphanumeric') {
          $maxLength = $arr['max_length'];
          $maxLength = "maxlength=" . $maxLength;
     }
} else {
     $sampleClass = '';
     $maxLength = '';
     if ($arr['max_length'] != '') {
          $maxLength = $arr['max_length'];
          $maxLength = "maxlength=" . $maxLength;
     }
}
// check if STS
$rKey = '';

if ($_SESSION['accessType'] == 'collection-site') {
     $sampleCodeKey = 'remote_sample_code_key';
     $sampleCode = 'remote_sample_code';
     $rKey = 'R';
} else {
     $sampleCodeKey = 'sample_code_key';
     $sampleCode = 'sample_code';
     $rKey = '';
}

$province = $general->getUserMappedProvinces($_SESSION['facilityMap']);
$facility = $general->generateSelectOptions($healthFacilities, null, '-- Select --');


$sKey = '';
$sFormat = '';

$testTypeQuery = "SELECT * FROM r_test_types where test_status='active' ORDER BY test_standard_name ASC";
$testTypeResult = $db->rawQuery($testTypeQuery);
$mandatoryClass = "";
if (!empty($_SESSION['instance']['type']) && $general->isLISInstance()) {
     $mandatoryClass = "isRequired";
}

$minPatientIdLength = 0;
if (isset($arr['generic_min_patient_id_length']) && $arr['generic_min_patient_id_length'] != "") {
     $minPatientIdLength = $arr['generic_min_patient_id_length'];
}

// Multi-test (TB-style) Test Results section on the add form -- LIS / cloud-LIS
// only (treatAsLIS). The test type is chosen client-side, so the section itself is
// rendered by getTestTypeForm.php (multiTest flag) and injected into the
// #genericTestSectionAjax placeholder; entry is opt-in via the section's
// "Enter test results now?" toggle. Collection sites / STS admins never see it.
$multiTestResults = $general->treatAsLIS();
?>
<link rel="stylesheet" href="/assets/css/jquery.multiselect.css" type="text/css" />

<style>
     .ms-choice {
          border: 0px solid #aaa;
     }

     .table>tbody>tr>td {
          border-top: none;
     }

     .form-control {
          width: 100% !important;
     }

     .row {
          margin-top: 6px;
     }

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

     .facilitySectionInput,
     .patientSectionInput,
     .caseInformationInput,
     #otherSection .col-md-6 {
          margin: 3px 0px;
     }

     .facilitySectionInput,
     .patientSectionInput .select2,
     .caseInformationInput .select2,
     #otherSection .col-md-6 .select2 {
          margin: 3px 0px;
     }
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
     <!-- Content Header (Page header) -->
     <section class="content-header">
          <h1><em class="fa-solid fa-pen-to-square"></em> <?= _translate("LABORATORY REQUEST FORM"); ?> </h1>
          <ol class="breadcrumb">
               <li><a href="/dashboard/index.php"><em class="fa-solid fa-chart-pie"></em> <?= _translate("Home"); ?></a>
               </li>
               <li class="active"><?= _translate("Add Request"); ?></li>
          </ol>
     </section>
     <!-- Main content -->
     <section class="content">
          <div class="box box-default">
               <div class="box-header with-border">
                    <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span>
                         <?= _translate("indicates required fields"); ?> &nbsp;</div>
               </div>
               <div class="box-body">
                    <!-- form start -->
                    <form class="form-inline" method="post" name="vlRequestFormSs" id="vlRequestFormSs"
                         autocomplete="off" action="add-request-helper.php">
                         <?php include __DIR__ . '/_request-form-body.php'; ?>

     </section>
</div>
<?= CommonService::barcodeScripts(); ?>
<script type="text/javascript" src="/assets/js/jquery.multiselect.js"></script>

<script type="text/javascript"
     src="/assets/js/datalist-css.min.js?v=<?= filemtime(WEB_ROOT . "/assets/js/datalist-css.min.js") ?>"></script>
<script type="text/javascript" src="/assets/js/moment.min.js"></script>
<script>
     let provinceName = true;
     let facilityName = true;
     let testCounter = 1;
     // Multi-test (TB-style) result entry via _test-section.php cards, fetched from
     // getTestTypeForm.php per chosen test type. When on, the legacy single-result
     // table must not be injected into .subTestResultSection.
     var gtMultiTest = <?php echo !empty($multiTestResults) ? 'true' : 'false'; ?>;

     $(document).ready(function () {
          $("#subTestResult").multipleSelect({
               placeholder: '<?php echo _translate("Select Sub Tests", true); ?>',
               width: '100%'
          });

          $("#labId,#facilityId,#sampleCollectionDate").on('change', function () {

               if ($("#labId").val() != '' && $("#labId").val() == $("#facilityId").val() && $("#sampleDispatchedDate").val() == "") {
                    $('#sampleDispatchedDate').val($('#sampleCollectionDate').val());
               }
               if ($("#labId").val() != '' && $("#labId").val() == $("#facilityId").val() && $("#sampleReceivedDate").val() == "") {
                    $('#sampleReceivedDate').val($('#sampleCollectionDate').val());
                    $('#sampleReceivedAtHubOn').val($('#sampleCollectionDate').val());
               }
          });



          $("#specimenType").select2({
               width: '100%',
               placeholder: "<?php echo _translate("Select Specimen Type", true); ?>"
          });
          $("#testType").select2({
               width: '100%',
               placeholder: "<?php echo _translate("Select Test Type", true); ?>"
          });

          // Restore the test type selection from the URL so a page refresh keeps the chosen test.
          var urlTestType = new URLSearchParams(window.location.search).get('testType');
          if (urlTestType && $("#testType option[value='" + urlTestType + "']").length > 0) {
               // Triggering 'change' updates the select2 display and runs the dropdown's
               // onchange handler (getSubTestList -> getTestTypeForm) exactly once.
               $("#testType").val(urlTestType).trigger('change');
          }
          $('#labId').select2({
               width: '100%',
               placeholder: "<?php echo _translate("Select Testing Lab", true); ?>"
          });
          $('#facilityId').select2({
               width: '100%',
               placeholder: "<?php echo _translate("Select Clinic/Health Center", true); ?>"
          });
          $('#reviewedBy').select2({
               width: '100%',
               placeholder: "<?php echo _translate("Select Reviewed By", true); ?>"
          });
          $('#testedBy').select2({
               width: '100%',
               placeholder: "<?php echo _translate("Select Tested By", true); ?>"
          });

          $('#approvedBy').select2({
               width: '100%',
               placeholder: "<?php echo _translate("Select Approved By", true); ?>"
          });
          $('#facilityId').select2({
               width: '100%',
               placeholder: "<?php echo _translate("Select Clinic/Health Center", true); ?>"
          });
          $('#district').select2({
               width: '100%',
               placeholder: "<?php echo _translate("District", true); ?>"
          });
          $('#province').select2({
               width: '100%',
               placeholder: "<?php echo _translate("Province", true); ?>"
          });
          $('#implementingPartner').select2({
               width: '100%',
               placeholder: "<?php echo _translate("Implementing Partner", true); ?>"
          });
          $('#fundingSource').select2({
               width: '100%',
               placeholder: "<?php echo _translate("Funding Source", true); ?>"
          });



          // BARCODESTUFF START
          <?php
          if (isset($_GET['barcode']) && $_GET['barcode'] == 'true') {
               $sampleCode = htmlspecialchars((string) $_GET['s']);
               $facilityCode = htmlspecialchars((string) $_GET['f']);
               $patientID = htmlspecialchars((string) $_GET['p']);
               echo "printBarcodeLabel('$sampleCode','$facilityCode','$patientID');";
          }
          ?>
          // BARCODESTUFF END

          $("#reqClinician").select2({
               placeholder: "<?= _translate('Enter Request Clinician name'); ?>",
               minimumInputLength: 0,
               width: '100%',
               allowClear: true,
               id: function (bond) {
                    return bond._id;
               },
               ajax: {
                    placeholder: "<?= _translate('Type one or more character to search'); ?>",
                    url: "/includes/get-data-list.php",
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                         return {
                              fieldName: 'request_clinician_name',
                              tableName: 'form_generic',
                              q: params.term, // search term
                              page: params.page
                         };
                    },
                    processResults: function (data, params) {
                         params.page = params.page || 1;
                         return {
                              results: data.result,
                              pagination: {
                                   more: (params.page * 30) < data.total_count
                              }
                         };
                    },
                    //cache: true
               },
               escapeMarkup: function (markup) {
                    return markup;
               }
          });

          $("#reqClinician").change(function () {
               $.blockUI();
               var search = $(this).val();
               if ($.trim(search) != '') {
                    $.get("/includes/get-data-list.php", {
                         fieldName: 'request_clinician_name',
                         tableName: 'form_generic',
                         returnField: 'request_clinician_phone_number',
                         limit: 1,
                         q: search,
                    },
                         function (data) {
                              if (data != "") {
                                   $("#reqClinicianPhoneNumber").val(data);
                              }
                         });
               }
               $.unblockUI();
          });

          $("#vlFocalPerson").select2({
               placeholder: "<?= _translate('Enter Request Focal name', true); ?>",
               minimumInputLength: 0,
               width: '100%',
               allowClear: true,
               id: function (bond) {
                    return bond._id;
               },
               ajax: {
                    placeholder: "<?= _translate('Type one or more character to search'); ?>",
                    url: "/includes/get-data-list.php",
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                         return {
                              fieldName: 'testing_lab_focal_person',
                              tableName: 'form_generic',
                              q: params.term, // search term
                              page: params.page
                         };
                    },
                    processResults: function (data, params) {
                         params.page = params.page || 1;
                         return {
                              results: data.result,
                              pagination: {
                                   more: (params.page * 30) < data.total_count
                              }
                         };
                    },
                    //cache: true
               },
               escapeMarkup: function (markup) {
                    return markup;
               }
          });

          $("#vlFocalPerson").change(function () {
               $.blockUI();
               var search = $(this).val();
               if ($.trim(search) != '') {
                    $.get("/includes/get-data-list.php", {
                         fieldName: 'testing_lab_focal_person',
                         tableName: 'form_generic',
                         returnField: 'testing_lab_focal_person_phone_number',
                         limit: 1,
                         q: search,
                    },
                         function (data) {
                              if (data != "") {
                                   $("#vlFocalPersonPhoneNumber").val(data);
                              }
                         });
               }
               $.unblockUI();
          });
     });

     function getProvinceDistricts(obj) {
          $.blockUI();
          var cName = $("#facilityId").val();
          var pName = $("#province").val();
          if (pName != '' && provinceName && facilityName) {
               facilityName = false;
          }
          if (pName != '') {
               if (provinceName) {
                    $.post("/includes/siteInformationDropdownOptions.php", {
                         pName: pName,
                         testType: 'generic-tests'
                    },
                         function (data) {
                              if (data != "") {
                                   details = data.split("###");
                                   $("#district").html(details[1]);
                                   $("#facilityId").html("<option data-code='' data-emails='' data-mobile-nos='' data-contact-person='' value=''> -- Select -- </option>");
                                   $("#facilityCode").val('');
                                   $(".facilityDetails").hide();
                                   $(".facilityEmails").html('');
                                   $(".facilityMobileNumbers").html('');
                                   $(".facilityContactPerson").html('');
                              }
                         });
               }
               generateSampleCode();
          } else if (pName == '' && cName == '') {
               provinceName = true;
               facilityName = true;
               $("#province").html("<?php echo $province; ?>");
               $("#facilityId").html("<?php echo $facility; ?>");
          }
          $.unblockUI();
     }

     function generateSampleCode() {
          var testType = $("#testType").val();
          var pName = $("#province").val();
          var sDate = $("#sampleCollectionDate").val();

          var provinceCode = ($("#province").find(":selected").attr("data-code") == null || $("#province").find(":selected").attr("data-code") == '') ? $("#province").find(":selected").attr("data-name") : $("#province").find(":selected").attr("data-code");
          $("#provinceId").val($("#province").find(":selected").attr("data-province-id"));
          if (pName != '' && sDate != '' && testType != '') {
               $.post("/generic-tests/requests/generateSampleCode.php", {
                    sampleCollectionDate: sDate,
                    provinceCode: provinceCode,
                    testType: $('#testType').find(':selected').data('short')
               },
                    function (data) {
                         var sCodeKey = JSON.parse(data);
                         $("#sampleCode").val(sCodeKey.sampleCode);
                         $("#sampleCodeInText").html(sCodeKey.sampleCode);
                         $("#sampleCodeFormat").val(sCodeKey.sampleCodeFormat);
                         $("#sampleCodeKey").val(sCodeKey.maxId);
                         checkSampleNameValidation('form_generic', '<?php echo $sampleCode; ?>', 'sampleCode', null, 'This sample number already exists.Try another number', null)
                    });
          }
     }

     function getFacilities(obj) {
          $.blockUI();
          var dName = $("#district").val();
          var cName = $("#facilityId").val();
          if (dName != '') {
               $.post("/includes/siteInformationDropdownOptions.php", {
                    dName: dName,
                    cliName: cName,
                    testType: 'generic-tests'
               },
                    function (data) {
                         if (data != "") {
                              details = data.split("###");
                              $("#facilityId").html(details[0]);
                              // $("#labId").html(details[1]);
                              $(".facilityDetails").hide();
                              $(".facilityEmails").html('');
                              $(".facilityMobileNumbers").html('');
                              $(".facilityContactPerson").html('');
                         }
                    });
          }
          $.unblockUI();
     }

     function fillFacilityDetails() {
          $("#facilityCode").val($('#facilityId').find(':selected').data('code'));
          var femails = $('#facilityId').find(':selected').data('emails');
          var fmobilenos = $('#facilityId').find(':selected').data('mobile-nos');
          var fContactPerson = $('#facilityId').find(':selected').data('contact-person');
          if ($.trim(femails) != '' || $.trim(fmobilenos) != '' || fContactPerson != '') {
               $(".facilityDetails").show();
          } else {
               $(".facilityDetails").hide();
          }
          ($.trim(femails) != '') ? $(".femails").show() : $(".femails").hide();
          ($.trim(femails) != '') ? $(".facilityEmails").html(femails) : $(".facilityEmails").html('');
          ($.trim(fmobilenos) != '') ? $(".fmobileNumbers").show() : $(".fmobileNumbers").hide();
          ($.trim(fmobilenos) != '') ? $(".facilityMobileNumbers").html(fmobilenos) : $(".facilityMobileNumbers").html('');
          ($.trim(fContactPerson) != '') ? $(".fContactPerson").show() : $(".fContactPerson").hide();
          ($.trim(fContactPerson) != '') ? $(".facilityContactPerson").html(fContactPerson) : $(".facilityContactPerson").html('');
     }
     $("input:radio[name=gender]").click(function () {
          if ($(this).val() == 'male' || $(this).val() == 'unreported') {
               $('.femaleSection').hide();
               $('input[name="breastfeeding"]').prop('checked', false);
               $('input[name="patientPregnant"]').prop('checked', false);
          } else if ($(this).val() == 'female') {
               $('.femaleSection').show();
          }
     });
     $("#sampleTestingDateAtLab").on("change", function () {
          if ($(this).val() != "") {
               $(".result-fields").attr("disabled", false);
               $(".result-fields").addClass("isRequired");
               $(".result-span").show();
               $('.vlResult').css('display', 'block');
               $('.rejectionReason').hide();
               $('#rejectionReason').removeClass('isRequired');
               $('#rejectionDate').removeClass('isRequired');
               $('#rejectionReason').val('');
               $(".review-approve-span").hide();
          }
     });
     $("#isSampleRejected").on("change", function () {


          if ($(this).val() == 'yes') {
               $('.rejectionReason').show();
               $('.vlResult').css('display', 'none');
               $("#sampleTestingDateAtLab, #vlResult").val("");
               $(".result-fields").val("");
               $(".result-fields").attr("disabled", true);
               $(".result-fields").removeClass("isRequired");
               $(".result-span").hide();
               $(".review-approve-span").show();
               $('#rejectionReason').addClass('isRequired');
               $('#rejectionDate').addClass('isRequired');
               $('#reviewedBy').addClass('isRequired');
               $('#reviewedOn').addClass('isRequired');
               $('#approvedBy').addClass('isRequired');
               $('#approvedOn').addClass('isRequired');
               $(".result-optional").removeClass("isRequired");
               $("#reasonForFailure").removeClass('isRequired');
          } else if ($(this).val() == 'no') {
               $(".result-fields").attr("disabled", false);
               $(".result-fields").addClass("isRequired");
               $(".result-span").show();
               $(".review-approve-span").show();
               $('.vlResult').css('display', 'block');
               $('.rejectionReason').hide();
               $('#rejectionReason').removeClass('isRequired');
               $('#rejectionDate').removeClass('isRequired');
               $('#rejectionReason').val('');
               $('#reviewedBy').addClass('isRequired');
               $('#reviewedOn').addClass('isRequired');
               $('#approvedBy').addClass('isRequired');
               $('#approvedOn').addClass('isRequired');
          } else {
               $(".result-fields").attr("disabled", false);
               $(".result-fields").removeClass("isRequired");
               $(".result-optional").removeClass("isRequired");
               $(".result-span").show();
               $('.vlResult').css('display', 'block');
               $('.rejectionReason').hide();
               $(".result-span").hide();
               $(".review-approve-span").hide();
               $('#rejectionReason').removeClass('isRequired');
               $('#rejectionDate').removeClass('isRequired');
               $('#rejectionReason').val('');
               $('#reviewedBy').removeClass('isRequired');
               $('#reviewedOn').removeClass('isRequired');
               $('#approvedBy').removeClass('isRequired');
               $('#approvedOn').removeClass('isRequired');
          }
     });


     $('#vlResult').on('change', function () {
          if ($(this).val().trim().toLowerCase() == 'failed' || $(this).val().trim().toLowerCase() == 'error') {
               if ($(this).val().trim().toLowerCase() == 'failed') {
                    $('.reasonForFailure').show();
                    $('#reasonForFailure').addClass('isRequired');
               }
          } else {
               $('.reasonForFailure').hide();
               $('#reasonForFailure').removeClass('isRequired');
          }
     });

     function checkRejectionReason() {
          var rejectionReason = $("#rejectionReason").val();
          if (rejectionReason == "other") {
               $("#newRejectionReason").show();
               $("#newRejectionReason").addClass("isRequired");
          } else {
               $("#newRejectionReason").hide();
               $("#newRejectionReason").removeClass("isRequired");
               $('#newRejectionReason').val("");
          }
     }

     $('#testingPlatform').on("change", function () {
          $(".vlResult").show();
          //$('#vlResult, #isSampleRejected').addClass('isRequired');
          $("#isSampleRejected").val("");
          //$("#isSampleRejected").trigger("change");
     });



     function setSampleDispatchDate() {
          if ($("#labId").val() != "" && $("#labId").val() == $("#facilityId").val() && $('#sampleDispatchedDate').val() == "") {
               $('#sampleDispatchedDate').val($("sampleCollectionDate").val());
          }
     }

     function validateNow() {
          var format = '<?php echo $arr['sample_code']; ?>';
          var sCodeLentgh = $("#sampleCode").val();
          var minLength = '<?php echo $arr['min_length']; ?>';
          if ((format == 'alphanumeric' || format == 'numeric') && sCodeLentgh.length < minLength && sCodeLentgh != '') {
               alert("Sample ID length must be a minimum length of " + minLength + " characters");
               return false;
          }

          flag = deforayValidator.init({
               formId: 'vlRequestFormSs'
          });
          $('.isRequired').each(function () {
               ($(this).val() == '') ? $(this).css('background-color', '#FFFF99') : $(this).css('background-color', '#FFFFFF')
          });
          if (flag) {
               $('.btn-disabled').attr('disabled', 'yes');
               $(".btn-disabled").prop("onclick", null).off("click");
               $.blockUI();
               var provinceCode = ($("#province").find(":selected").attr("data-code") == null || $("#province").find(":selected").attr("data-code") == '') ? $("#province").find(":selected").attr("data-name") : $("#province").find(":selected").attr("data-code");
               <?php if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'auto2' || $arr['sample_code'] == 'YY' || $arr['sample_code'] == 'MMYY') { ?>
                    insertSampleCode('vlRequestFormSs', 'requestSampleId', 'sampleCode', 'sampleCodeKey', 'sampleCodeFormat', '1', 'sampleCollectionDate', provinceCode, $("#province").find(":selected").attr("data-province-id"));
               <?php } else { ?>
                    document.getElementById('vlRequestFormSs').submit();
               <?php } ?>
          }
     }

     function validateSaveNow(option = 'next') {
          var format = '<?php echo $arr['sample_code']; ?>';
          var sCodeLentgh = $("#sampleCode").val();
          var minLength = '<?php echo $arr['min_length']; ?>';
          if ((format == 'alphanumeric' || format == 'numeric') && sCodeLentgh.length < minLength && sCodeLentgh != '') {
               alert("Sample ID length must be a minimum length of " + minLength + " characters");
               return false;
          }
          flag = deforayValidator.init({
               formId: 'vlRequestFormSs'
          });
          $('.isRequired').each(function () {
               ($(this).val() == '') ? $(this).css('background-color', '#FFFF99') : $(this).css('background-color', '#FFFFFF')
          });
          $("#saveNext").val(option);
          if (flag) {
               $('.btn-disabled').attr('disabled', 'yes');
               $(".btn-disabled").prop("onclick", null).off("click");
               $.blockUI();
               var provinceCode = ($("#province").find(":selected").attr("data-code") == null || $("#province").find(":selected").attr("data-code") == '') ? $("#province").find(":selected").attr("data-name") : $("#province").find(":selected").attr("data-code");
               <?php if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'auto2' || $arr['sample_code'] == 'YY' || $arr['sample_code'] == 'MMYY') { ?>
                    insertSampleCode('vlRequestFormSs', 'requestSampleId', 'sampleCode', 'sampleCodeKey', 'sampleCodeFormat', 1, 'sampleCollectionDate', provinceCode, $("#province").find(":selected").attr("data-province-id"));
               <?php } else { ?>
                    document.getElementById('vlRequestFormSs').submit();
               <?php } ?>
          }
     }

     function checkPatientReceivesms(val) {
          if (val == 'yes') {
               $('#patientPhoneNumber').addClass('isRequired');
          } else {
               $('#patientPhoneNumber').removeClass('isRequired');
          }
     }

     function autoFillFocalDetails() {
          labId = $("#labId").val();
          if ($.trim(labId) != '') {
               $("#vlFocalPerson").val($('#labId option:selected').attr('data-focalperson'));
               $("#vlFocalPersonPhoneNumber").val($('#labId option:selected').attr('data-focalphone'));
          }
     }

     function setPatientDetails(pDetails) {
          $("#selectedSample").val(pDetails);
          var patientArray = JSON.parse(pDetails);
          //  alert(pDetails);
          $("#patientFirstName").val(patientArray['name']);
          $("#patientPhoneNumber").val(patientArray['mobile']);
          if ($.trim(patientArray['dob']) != '') {
               $("#dob").val(patientArray['dob']);
               getAge();
          } else if ($.trim(patientArray['age_in_years']) != '' && $.trim(patientArray['age_in_years']) != 0) {
               $("#ageInYears").val(patientArray['age_in_years']);
          } else if ($.trim(patientArray['age_in_months']) != '') {
               $("#ageInMonths").val(patientArray['age_in_months']);
          }

          if ($.trim(patientArray['gender']) != '') {
               $('#breastfeedingYes').removeClass('isRequired');
               $('#pregYes').removeClass('isRequired');
               if (patientArray['gender'] == 'male' || patientArray['gender'] == 'unreported') {
                    $('.femaleSection').hide();
                    $('input[name="breastfeeding"]').prop('checked', false);
                    $('input[name="patientPregnant"]').prop('checked', false);
                    if (patientArray['gender'] == 'male') {
                         $("#genderMale").prop('checked', true);
                    } else {
                         $("#genderUnreported").prop('checked', true);
                    }
               } else if (patientArray['gender'] == 'female') {
                    $('.femaleSection').show();
                    $("#genderFemale").prop('checked', true);
                    $('#breastfeedingYes').addClass('isRequired');
                    $('#pregYes').addClass('isRequired');
                    if ($.trim(patientArray['is_pregnant']) != '') {
                         if ($.trim(patientArray['is_pregnant']) == 'yes') {
                              $("#pregYes").prop('checked', true);
                         } else if ($.trim(patientArray['is_pregnant']) == 'no') {
                              $("#pregNo").prop('checked', true);
                         }
                    }
                    if ($.trim(patientArray['is_pregnant']) != '') {
                         if ($.trim(patientArray['is_pregnant']) == 'yes') {
                              $("#breastfeedingYes").prop('checked', true);
                         } else if ($.trim(patientArray['is_pregnant']) == 'no') {
                              $("#breastfeedingNo").prop('checked', true);
                         }
                    }
               }
          }
          if ($.trim(patientArray['consent_to_receive_sms']) != '') {
               if (patientArray['consent_to_receive_sms'] == 'yes') {
                    $("#receivesmsYes").prop('checked', true);
               } else if (patientArray['consent_to_receive_sms'] == 'no') {
                    $("#receivesmsNo").prop('checked', true);
               }
          }
          if ($.trim(patientArray['patient_id']) != '') {
               $("#artNo").val($.trim(patientArray['patient_id']));
          }

          if ($.trim(patientArray['sample_tested_datetime']) != '') {
               $("#rmTestingLastVLDate").val($.trim(patientArray['sample_tested_datetime']));
               $("#repeatTestingLastVLDate").val($.trim(patientArray['sample_tested_datetime']));
               $("#suspendTreatmentLastVLDate").val($.trim(patientArray['sample_tested_datetime']));

          }
          if ($.trim(patientArray['result']) != '') {
               $("#rmTestingVlValue").val($.trim(patientArray['result']));
               $("#repeatTestingVlValue").val($.trim(patientArray['result']));
               $("#suspendTreatmentVlValue").val($.trim(patientArray['result']));
          }

     }


     function vlResultChange(value) {
          if (value != "") {
               $('#vlResult').val(value);
          }
     }

     function showPatientList() {
          $("#showEmptyResult").hide();
          if ($.trim($("#artPatientNo").val()) != '') {
               $.post("/generic-tests/requests/search-patients.php", {
                    artPatientNo: $.trim($("#artPatientNo").val()),
                    testType: $.trim($("#testType").val())
               },
                    function (data) {
                         if (data >= '1') {
                              showModal('patientModal.php?artNo=' + $.trim($("#artPatientNo").val()) + '&testType=' + $.trim($("#testType").val()), 900, 520);
                         } else {
                              $("#showEmptyResult").show();
                         }
                    });
          }
     }

     function checkPatientDetails(tableName, fieldName, obj, fnct) {
          //if ($.trim(obj.value).length == 10) {
          if ($.trim(obj.value) != '') {
               $.post("/includes/checkDuplicate.php", {
                    tableName: tableName,
                    fieldName: fieldName,
                    value: obj.value,
                    fnct: fnct,
                    format: "html"
               },
                    function (data) {
                         if (data === '1') {
                              showModal('patientModal.php?artNo=' + obj.value + '&testType=' + $.trim($("#testType").val()), 900, 520);
                         }
                    });
          }
     }

     function checkSampleNameValidation(tableName, fieldName, id, fnct, alrt) {
          if ($.trim($("#" + id).val()) != '') {
               //$.blockUI();
               $.post("/generic-tests/requests/checkSampleDuplicate.php", {
                    tableName: tableName,
                    fieldName: fieldName,
                    value: $("#" + id).val(),
                    fnct: fnct,
                    format: "html"
               },
                    function (data) {
                         if (data != 0) {

                         }
                    });
               $.unblockUI();
          }
     }

     function insertSampleCode(formId, requestSampleId = null, sampleCode = null, sampleCodeKey = null, sampleCodeFormat = null, countryId = null, sampleCollectionDate = null, provinceCode = null, provinceId = null) {
          $.blockUI();
          let formData = $("#" + formId).serialize();
          formData += "&provinceCode=" + encodeURIComponent(provinceCode);
          formData += "&provinceId=" + encodeURIComponent(provinceId);
          formData += "&countryId=" + encodeURIComponent(countryId);
          formData += "&testType=" + encodeURIComponent($('#testType').find(':selected').data('short'))
          $.post("/generic-tests/requests/insert-sample.php", formData,
               function (data) {
                    //alert(data);
                    if (data > 0) {
                         $.unblockUI();
                         document.getElementById("requestSampleId").value = data;
                         document.getElementById(formId).submit();
                    } else {
                         $.unblockUI();
                         $("#sampleCollectionDate").val('');
                         generateSampleCode();
                         alert("<?= _translate("Could not save this form. Please try again."); ?>");
                    }
               });
     }

     function clearDOB(val) {
          if ($.trim(val) != "") {
               $("#dob").val("");
          }
     }

     function getfacilityProvinceDetails(obj) {
          $.blockUI();
          //check facility name`
          var cName = $("#facilityId").val();
          var pName = $("#province").val();
          if (cName != '' && provinceName && facilityName) {
               provinceName = false;
          }
          if (cName != '' && facilityName) {
               $.post("/includes/siteInformationDropdownOptions.php", {
                    cName: cName,
                    testType: 'generic-tests'
               },
                    function (data) {
                         if (data != "") {
                              details = data.split("###");
                              $("#province").html(details[0]);
                              $("#district").html(details[1]);
                         }
                    });
          } else if (pName == '' && cName == '') {
               provinceName = true;
               facilityName = true;
               $("#province").html("<?php echo $province ?? ""; ?>");
               $("#facilityId").html("<?php echo $facility ?? ""; ?>");
          }
          $.unblockUI();
     }

     function updateTestTypeUrl(testType) {
          // Keep the chosen test type in the URL so the selection survives a page refresh.
          var url = new URL(window.location.href);
          if (testType != "" && testType != null) {
               url.searchParams.set('testType', testType);
          } else {
               url.searchParams.delete('testType');
          }
          window.history.replaceState({}, '', url.toString());
     }

     // Apply a test type's optional "Advanced Configuration" (from getTestTypeForm.php):
     // toggle a few non-dynamic fields and override the Patient ID label. Missing/empty
     // config => every field shown, label unchanged (backward compatible).
     function applyAdvancedFormConfig(cfg) {
          cfg = cfg || {};
          var toggleField = function (selector, on) {
               var $wrap = $(selector).first().closest('[class*="col-md-"]');
               if (!$wrap.length) { return; }
               $wrap.toggle(on);
               if (!on) { $wrap.find('.isRequired').removeClass('isRequired'); }
          };
          // Sex-coupled fields: when allowed, keep them under the female-section/sex
          // logic; when hidden by config, detach so a later sex change can't re-show them.
          var toggleFemaleField = function (selector, on) {
               var $wrap = $(selector).first().closest('[class*="col-md-"]');
               if (!$wrap.length) { return; }
               if (on) {
                    var isFemale = $('input:radio[name=gender]:checked').val() === 'female';
                    $wrap.addClass('femaleSection').toggle(isFemale);
               } else {
                    $wrap.removeClass('femaleSection').hide().find('.isRequired').removeClass('isRequired');
               }
          };
          toggleField('#implementingPartner', cfg.showImplementingPartner !== 'no');
          toggleField('#fundingSource', cfg.showFundingSource !== 'no');
          toggleField('#patientFirstName', cfg.showPatientName !== 'no');
          toggleField('#laboratoryNumber', cfg.showLaboratoryNumber !== 'no');
          toggleField('#ageInMonths', cfg.showAgeInMonths !== 'no');
          toggleFemaleField('#pregYes', cfg.showPregnancy !== 'no');
          toggleFemaleField('#breastfeedingYes', cfg.showBreastfeeding !== 'no');
          if (cfg.patientIdLabel && String(cfg.patientIdLabel).trim() !== '') {
               var lbl = String(cfg.patientIdLabel).trim();
               $('#artNoLabelText').text(lbl);
               $('#artNo').attr('placeholder', '<?= _jsTranslate('Enter') ?> ' + lbl).attr('title', lbl);
          }
     }

     function getTestTypeForm() {
          var testType = $("#testType").val();
          if (testType != "") {
               getTestTypeConfigList(testType);
               $(".selectTestTypePrompt").slideUp(150);
               $(".requestForm").show();
               $.post("/generic-tests/requests/getTestTypeForm.php", {
                    result: $('#result').val(),
                    testType: testType,
                    subTests: $('#subTestResult').val(),
                    multiTest: gtMultiTest ? 1 : 0,
               },
                    function (data) {
                         if (data != undefined && data !== null) {

                              console.log(data.result);
                              data = JSON.parse(data);
                              applyAdvancedFormConfig(data.advancedFormConfig);
                              $("#facilitySection,#labSection,#caseInformation,.subTestResultSection,#otherSection").html('');
                              $('.patientSectionInput').remove();
                              if (typeof (data.facilitySection) != "undefined" && data.facilitySection !== null && data.facilitySection.length > 0) {
                                   $("#facilitySection").html(data.facilitySection);
                              }
                              if (typeof (data.caseInformation) != "undefined" && data.caseInformation !== null && data.caseInformation.length > 0) {
                                   $("#caseInformation").html(data.caseInformation);
                                   $("#caseInformationBox").show();
                              } else {
                                   $("#caseInformationBox").hide();
                              }
                              if (typeof (data.patientSection) != "undefined" && data.patientSection !== null && data.patientSection.length > 0) {
                                   $("#patientSection").after(data.patientSection);
                              }
                              if (typeof (data.labSection) != "undefined" && data.labSection !== null && data.labSection.length > 0) {
                                   $("#labSection").html(data.labSection);
                              }
                              if (!gtMultiTest && typeof (data.result) != "undefined" && data.result !== null && data.result.length > 0) {
                                   $(".subTestResultSection").html(data.result).show();
                              } else {
                                   $('.subTestResultSection').hide();
                              }
                              if (typeof (data.specimenSection) != "undefined" && data.specimenSection !== null && data.specimenSection.length > 0) {
                                   $("#specimenSection").after(data.specimenSection);
                              }
                              if (typeof (data.otherSection) != "undefined" && data.otherSection !== null && data.otherSection.length > 0) {
                                   $("#otherSection").html(data.otherSection);
                              }
                              // Multi-test Test Results section for the chosen test type (its inline
                              // script re-registers the gt* handlers; injecting before the picker
                              // inits below lets initDatePicker/initDateTimePicker bind its fields).
                              if (gtMultiTest && typeof (data.testSection) != "undefined" && data.testSection !== null && data.testSection.length > 0) {
                                   $("#genericTestSectionAjax").html(data.testSection);
                              }



                              $(".dynamicFacilitySelect2").select2({
                                   width: '100%',
                                   placeholder: "<?php echo _translate("-- Select --"); ?>"
                              });
                              $(".dynamicSelect2").select2({
                                   width: '100%',
                                   placeholder: "<?php echo _translate("-- Select --"); ?>"
                              });

                              $(".multipleSelectize").selectize({
                                   plugins: ["restore_on_backspace", "remove_button", "clear_button"],
                              });

                              // Attach date pickers to any date fields just injected
                              // (the document-ready init ran before they existed).
                              if (typeof initDatePicker === 'function') { initDatePicker(); }
                              if (typeof initDateTimePicker === 'function') { initDateTimePicker(); }

                              if ($('#resultType').val() == 'qualitative') {
                                   $('.final-result-row').attr('colspan', 4)
                                   $('.testResultUnit').hide();
                              } else {
                                   $('.final-result-row').attr('colspan', 5)
                                   $('.testResultUnit').show();
                              }
                         }
                    });

          } else {
               $(".facilitySection").html('');
               $(".patientSectionInput").remove();
               $("#labSection").html('');
               $(".specimenSectionInput").remove();
               $("#caseInformation").html('');
               $("#caseInformationBox").hide();
               $("#otherSection").html('');
               $(".requestForm").hide();
               $(".selectTestTypePrompt").slideDown(150);
          }


     }

     function loadSubTests() {
          // Per-card result entry (multi-test mode) does not use the legacy sub-test flow.
          if (gtMultiTest) {
               return;
          }
          // While getSubTestList() populates the picker, multipleSelect fires a
          // change on #subTestResult. Ignore it -- getTestTypeForm() (called right
          // after, with the selected sub-tests) already loads the result table, so
          // acting on this change would be a redundant second getTestTypeForm.php hit.
          if (window.subTestPickerLoading) {
               return;
          }
          var testType = $("#testType").val();
          var subTestResult = $("#subTestResult").val();
          console.log(subTestResult);
          if (testType != "") {
               $(".requestForm").show();
               $.post("/generic-tests/requests/getTestTypeForm.php", {
                    result: $('#result').val(),
                    testType: testType,
                    subTests: subTestResult,
               },
                    function (data) {
                         data = JSON.parse(data);
                         applyAdvancedFormConfig(data.advancedFormConfig);
                         $(".subTestResultSection").html('');
                         if (typeof (data.result) != "undefined" && data.result !== null && data.result.length > 0) {
                              $(".subTestResultSection").html(data.result);
                              $('.subTestResultSection').show();
                         } else {
                              $('.subTestResultSection').hide();
                         }

                    });
          } else {
               $(".subTestResultSection").hide();
          }

     }

     function getTestTypeConfigList(testTypeId) {

          $.post("/includes/get-test-type-config.php", {
               testTypeId: testTypeId
          },
               function (data) {
                    Obj = $.parseJSON(data);
                    if (data != "") {
                         $("#specimenType").html(Obj['sampleTypes']);
                         $("#reasonForTesting").html(Obj['testReasons']);
                    }
               });

     }

     function getSubTestList(testType) {

          // No test type selected -> just let getTestTypeForm() clear the form.
          if (testType == "") {
               getTestTypeForm();
               return;
          }

          $.post("/generic-tests/requests/get-sub-test-list.php", {
               testTypeId: testType
          },
               function (data) {
                    // Suppress the change multipleSelect fires while we populate the
                    // picker, so loadSubTests() doesn't fire a redundant fetch.
                    window.subTestPickerLoading = true;
                    if (data != "") {
                         $("#subTestResult").append(data);
                         $("#subTestResult").multipleSelect({
                              placeholder: '<?php echo _translate("Select Sub Tests"); ?>',
                              width: '100%'
                         });
                         var length = $('#subTestResult > option').length;
                         if (length > 1) {
                              $('.subTestFields').show();
                         } else {
                              $('.subTestFields').hide();
                         }
                    }
                    // Picker is populated (first sub-test auto-selected server-side),
                    // so now load sections + result table in one getTestTypeForm.php call.
                    getTestTypeForm();
                    // Release the guard on the next tick, after any change multipleSelect
                    // may fire (sync or deferred) from the populate above has been swallowed.
                    setTimeout(function () {
                         window.subTestPickerLoading = false;
                    }, 0);
               });
     }

     function addTestRow(row, subTest) {
          var unitTest = '';
          subrow = document.getElementById("testKitNameTable" + row).rows.length
          $('.ins-row-' + row + subrow).attr('disabled', true);
          $('.ins-row-' + row + subrow).addClass('disabled');
          testCounter = (subrow + 1);
          // Prepend the default "-- Select --" option; the datalist only holds the real result options.
          options = '<option value="">-- Select --</option>' + $("#resultListQl" + row).html();
          testMethodOptions = $("#testName" + row + (testCounter - 1)).html();
          if ($('.qualitative-field').hasClass('testResultUnit')) {
               unitTest = `<td class="testResultUnit">
                    <select class="form-control resultUnit" id="testResultUnit${row}${testCounter}" name="testResultUnit[${subTest}][]" placeholder='<?php echo _translate("Enter test result unit"); ?>' title='<?php echo _translate("Please enter test result unit"); ?>'>
                         <option value="">--Select--</option>
                         <?php foreach ($testResultUnits as $key => $unit) { ?>
                                   <option value="<?php echo $key; ?>"><?php echo $unit; ?></option>
                         <?php } ?>
                    </select>
                    </td>`;
          }
          let rowString = `<tr>
                    <td class="text-center">${(subrow + 1)}</td>
                    <td>
                         <select class="form-control test-name-table-input" id="testName${row}${testCounter}" name="testName[${subTest}][]" title="Please enter the name of the Testkit (or) Test Method used">${testMethodOptions}</select>
                         <input type="text" name="testNameOther[${subTest}][]" id="testNameOther${row}${testCounter}" class="form-control testNameOther${testCounter}" title="Please enter the name of the Testkit (or) Test Method used" placeholder="<?php echo _translate('Please enter the name of the Testkit (or) Test Method used'); ?>" style="display: none;margin-top: 10px;" />
                    </td>
                    <td><input type="text" name="testDate[${subTest}][]" id="testDate${row}${testCounter}" class="form-control test-name-table-input dateTime" placeholder="<?php echo _translate('Tested on'); ?>" title="Please enter the tested on for row ${testCounter}" /></td>
                    <td><select name="testingPlatform[${subTest}][]" id="testingPlatform${row}${testCounter}" class="form-control test-name-table-input" title="Please select the Testing Platform for ${testCounter}"><?= $general->generateSelectOptions($testPlatformList, null, '-- Select --'); ?></select></td>
                    <td class="kitlabels" style="display: none;"><input type="text" name="lotNo[${subTest}][]" id="lotNo${row}${testCounter}" class="form-control kit-fields${testCounter}" placeholder="<?php echo _translate('Kit lot no'); ?>" title="Please enter the kit lot no. for row ${testCounter}" style="display:none;"/></td>
                    <td class="kitlabels" style="display: none;"><input type="text" name="expDate[${subTest}][]" id="expDate${row}${testCounter}" class="form-control expDate kit-fields${testCounter}" placeholder="<?php echo _translate('Expiry date'); ?>" title="Please enter the expiry date for row ${testCounter}" style="display:none;"/></td>
                    <td><select class="form-control result-select" name="testResult[${subTest}][]" id="testResult${row}${testCounter}" title="Enter result">${options}</select></td>
                    ${unitTest}
                    <td style="vertical-align:middle;text-align: center;width:100px;">
                         <a class="btn btn-xs btn-primary ins-row-${row}${testCounter} test-name-table" href="javascript:void(0);" onclick="addTestRow(${row}, \'${subTest}\');"><em class="fa-solid fa-plus"></em></a>&nbsp;
                         <a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeTestRow(this.parentNode.parentNode, ${row},${subrow});"><em class="fa-solid fa-minus"></em></a>
                    </td>
               </tr>`;
          $("#testKitNameTable" + row).append(rowString);

          $('.date').datepicker({
               changeMonth: true,
               changeYear: true,
               onSelect: function () {
                    $(this).change();
               },
               dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
               timeFormat: "HH:mm",
               maxDate: "Today",
               yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
          }).click(function () {
               $('.ui-datepicker-calendar').show();
          });

          $('.expDate').datepicker({
               changeMonth: true,
               changeYear: true,
               onSelect: function () {
                    $(this).change();
               },
               dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
               timeFormat: "HH:mm",
               // minDate: "Today",
               yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
          }).click(function () {
               $('.ui-datepicker-calendar').show();
          });



          if ($('.kitlabels').is(':visible') == true) {
               $('.kitlabels').show();
          }

          if ($('#resultType').val() == 'qualitative') {
               $('.final-result-row').attr('colspan', 4)
               $('.testResultUnit').hide();
          } else {
               $('.final-result-row').attr('colspan', 5)
               $('.testResultUnit').show();
          }
     }

     function removeTestRow(el, row, subrow) {
          $('.ins-row-' + row + subrow).attr('disabled', false);
          $('.ins-row-' + row + subrow).removeClass('disabled');
          $(el).fadeOut("slow", function () {
               el.parentNode.removeChild(el);
               rl = document.getElementById("testKitNameTable" + row).rows.length;
               if (rl == 0) {
                    testCounter = 0;
                    addTestRow(row, (subrow + 1));
               }
          });
     }

     function updateInterpretationResult(obj) {
          if (obj.value) {
               $.post("get-result-interpretation.php", {
                    result: obj.value,
                    resultType: $('#resultType').val(),
                    testType: $('#testType').val()
               },
                    function (interpretation) {
                         if (interpretation != "") {
                              $('#resultInterpretation').val(interpretation);
                         } else {
                              $('#resultInterpretation').val('');
                         }
                    });
          }
     }
</script>

<?php include APPLICATION_PATH . '/footer.php';
