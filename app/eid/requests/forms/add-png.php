<?php
// imported in eid-add-request.php based on country in global config

use App\Registries\ContainerRegistry;
use App\Services\EidService;



//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);

//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);




// Getting the list of Provinces, Districts and Facilities


/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);
$eidResults = $eidService->getEidResults();


$rKey = '';
$sKey = '';
$sFormat = '';
$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
if ($_SESSION['instanceType'] == 'remoteuser') {
    $sampleCodeKey = 'remote_sample_code_key';
    $sampleCode = 'remote_sample_code';
    //check user exist in user_facility_map table
    $chkUserFcMapQry = "Select user_id from user_facility_map where user_id='" . $_SESSION['userId'] . "'";
    $chkUserFcMapResult = $db->query($chkUserFcMapQry);
    if ($chkUserFcMapResult) {
        $pdQuery = "SELECT DISTINCT gd.geo_name,gd.geo_id,gd.geo_code FROM geographical_divisions as gd JOIN facility_details as fd ON fd.facility_state_id=gd.geo_id JOIN user_facility_map as vlfm ON vlfm.facility_id=fd.facility_id where gd.geo_parent = 0 AND gd.geo_status='active' AND vlfm.user_id='" . $_SESSION['userId'] . "'";
    }
    $rKey = 'R';
} else {
    $sampleCodeKey = 'sample_code_key';
    $sampleCode = 'sample_code';
    $rKey = '';
}
$pdResult = $db->query($pdQuery);
$province = "<option value=''> -- Select -- </option>";
foreach ($pdResult as $provinceName) {
    $province .= "<option data-code='" . $provinceName['geo_code'] . "' data-province-id='" . $provinceName['geo_id'] . "' data-name='" . $provinceName['geo_name'] . "' value='" . $provinceName['geo_name'] . "##" . $provinceName['geo_code'] . "'>" . ($provinceName['geo_name']) . "</option>";
}

$facility = $general->generateSelectOptions($healthFacilities, null, '-- Select --');

?>

<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-pen-to-square"></em> <?php echo _("EARLY INFANT DIAGNOSIS (EID) LABORATORY REQUEST FORM"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
            <li class="active"><?php echo _("Add EID Request"); ?></li>
        </ol>
    </section>
    <!-- Main content -->
    <section class="content">

        <div class="box box-default">
            <div class="box-header with-border">
                <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indicates required field &nbsp;</div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <!-- form start -->
                <form class="form-horizontal" method="post" name="addEIDRequestForm" id="addEIDRequestForm" autocomplete="off" action="eid-add-request-helper.php">
                    <div class="box-body">
                        <div class="box box-default">
                            <div class="box-body">
                                <div class="box-header with-border">
                                    <h3 class="box-title">A. CHILD and MOTHER INFORMATION</h3>
                                </div>
                                <div class="box-header with-border">
                                    <h3 class="box-title" style="font-size:1em;">To be filled by requesting Clinician/Nurse</h3>
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <?php if ($_SESSION['instanceType'] == 'remoteuser') { ?>
                                            <td><label for="sampleCode">Sample ID </label></td>
                                            <td>
                                                <span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"></span>
                                                <input type="hidden" id="sampleCode" name="sampleCode" />
                                            </td>
                                        <?php } else { ?>
                                            <td><label for="sampleCode">Sample ID </label><span class="mandatory">*</span></td>
                                            <td>
                                                <input type="text" class="form-control isRequired" id="sampleCode" name="sampleCode" placeholder="Sample ID" title="Please enter sample id" style="width:100%;" onchange="checkSampleNameValidation('form_eid','<?php echo $sampleCode; ?>',this.id,null,'The sample id that you entered already exists. Please try another sample id',null)" />
                                            </td>
                                        <?php } ?>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td><label for="province">Province </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control isRequired" name="province" id="province" title="Please choose province" onchange="getfacilityDetails(this);" style="width:100%;">
                                                <?php echo $province; ?>
                                            </select>
                                        </td>
                                        <td><label for="district">District </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control isRequired" name="district" id="district" title="Please choose district" style="width:100%;" onchange="getfacilityDistrictwise(this);">
                                                <option value=""> -- Select -- </option>
                                            </select>
                                        </td>
                                        <td><label for="facilityId">Health Facility </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control isRequired " name="facilityId" id="facilityId" title="Please choose service provider" style="width:100%;" onchange="getfacilityProvinceDetails(this);">
                                                <?php echo $facility; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><label for="supportPartner">Implementing Partner </label></td>
                                        <td>
                                            <!-- <input type="text" class="form-control" id="supportPartner" name="supportPartner" placeholder="Partenaire dappui" title="Please enter partenaire dappui" style="width:100%;"/> -->
                                            <select class="form-control" name="implementingPartner" id="implementingPartner" title="Please choose partenaire de mise en œuvre" style="width:100%;">
                                                <option value=""> -- Select -- </option>
                                                <?php
                                                foreach ($implementingPartnerList as $implementingPartner) {
                                                ?>
                                                    <option value="<?php echo ($implementingPartner['i_partner_id']); ?>"><?= $implementingPartner['i_partner_name']; ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <td><label for="fundingSource">Funding Partner</label></td>
                                        <td>
                                            <select class="form-control" name="fundingSource" id="fundingSource" title="Please choose source de financement" style="width:100%;">
                                                <option value=""> -- Select -- </option>
                                                <?php
                                                foreach ($fundingSourceList as $fundingSource) {
                                                ?>
                                                    <option value="<?php echo ($fundingSource['funding_source_id']); ?>"><?= $fundingSource['funding_source_name']; ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <?php if ($_SESSION['instanceType'] == 'remoteuser') { ?>
                                            <!-- <tr> -->
                                            <td><label for="labId">Lab Name <span class="mandatory">*</span></label> </td>
                                            <td>
                                                <select name="labId" id="labId" class="form-control isRequired" title="Please select Testing Lab name" style="width:100%;">
                                                    <?= $general->generateSelectOptions($testingLabs, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                            <!-- </tr> -->
                                        <?php } ?>
                                    </tr>
                                </table>
                                <br><br>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">

                                    <tr>
                                        <th scope="row" style="width:15% !important"><label for="childId">Exposed Infant Identification <span class="mandatory">*</span> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control isRequired" id="childId" name="childId" placeholder="Exposed Infant Identification (Patient)" title="Please enter Exposed Infant Identification" style="width:100%;" onchange="" />
                                        </td>
                                        <th scope="row" style="width:15% !important"><label for="childName">Infant name </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control " id="childName" name="childName" placeholder="Infant name" title="Please enter Infant Name" style="width:100%;" onchange="" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="childDob">Date of Birth </label></th>
                                        <td>
                                            <input type="text" class="form-control date" id="childDob" name="childDob" placeholder="Date of birth" title="Please enter Date of birth" style="width:100%;" onchange="" />
                                        </td>
                                        <th scope="row"><label for="childGender">Gender </label></th>
                                        <td>
                                            <select class="form-control " name="childGender" id="childGender">
                                                <option value=''> -- Select -- </option>
                                                <option value='male'> Male </option>
                                                <option value='female'> Female </option>

                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Infant Age (months)</th>
                                        <td><input type="number" max=9 maxlength="1" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control " id="childAge" name="childAge" placeholder="Age" title="Age" style="width:100%;" onchange="" /></td>
                                        <th scope="row">Mother ART Number</th>
                                        <td><input type="text" class="form-control " id="mothersId" name="mothersId" placeholder="Mother ART Number" title="Mother ART Number" style="width:100%;" onchange="" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Caretaker phone number</th>
                                        <td><input type="text" class="form-control " id="caretakerPhoneNumber" name="caretakerPhoneNumber" placeholder="Caretaker Phone Number" title="Caretaker Phone Number" style="width:100%;" onchange="" /></td>

                                        <th scope="row">Infant caretaker address</th>
                                        <td><textarea class="form-control " id="caretakerAddress" name="caretakerAddress" placeholder="Caretaker Address" title="Caretaker Address" style="width:100%;" onchange=""></textarea></td>

                                    </tr>


                                </table>



                                <br><br>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <th scope="row" colspan=4>
                                            <h4>Infant and Mother's Health Information</h4>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:15% !important">Mother's HIV Status:</th>
                                        <td style="width:35% !important">
                                            <select class="form-control" name="mothersHIVStatus" id="mothersHIVStatus">
                                                <option value=''> -- Select -- </option>
                                                <option value="positive"> Positive </option>
                                                <option value="negative"> Negative </option>
                                                <option value="unknown"> Unknown </option>
                                            </select>
                                        </td>

                                        <th scope="row" style="width:15% !important">ART given to the Mother during:</th>
                                        <td style="width:35% !important">
                                            <input type="checkbox" name="motherTreatment[]" value="No ART given" /> No ART given <br>
                                            <input type="checkbox" name="motherTreatment[]" value="Pregnancy" /> Pregnancy <br>
                                            <input type="checkbox" name="motherTreatment[]" value="Labour/Delivery" /> Labour/Delivery <br>
                                            <input type="checkbox" name="motherTreatment[]" value="Postnatal" /> Postnatal <br>
                                            <!-- <input type="checkbox" name="motherTreatment[]" value="Other" onclick="$('#motherTreatmentOther').prop('disabled', function(i, v) { return !v; });" /> Other (Please specify): <input class="form-control" style="max-width:200px;display:inline;" disabled="disabled" placeholder="Other" type="text" name="motherTreatmentOther" id="motherTreatmentOther" /> <br> -->
                                            <input type="checkbox" name="motherTreatment[]" value="Unknown" /> Unknown
                                        </td>
                                    </tr>

                                    <tr>
                                        <th scope="row">Infant Rapid HIV Test Done</th>
                                        <td>
                                            <select class="form-control" name="rapidTestPerformed" id="rapidTestPerformed">
                                                <option value=''> -- Select -- </option>
                                                <option value="yes"> Yes </option>
                                                <option value="no"> No </option>
                                            </select>
                                        </td>

                                        <th scope="row">If yes, test date :</th>
                                        <td>
                                            <input class="form-control date" type="text" name="rapidtestDate" id="rapidtestDate" placeholder="if yes, test date" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Rapid Test Result</th>
                                        <td>
                                            <select class="form-control" name="rapidTestResult" id="rapidTestResult">
                                                <option value=''> -- Select -- </option>
                                                <?php foreach ($eidResults as $eidResultKey => $eidResultValue) { ?>
                                                    <option value="<?php echo $eidResultKey; ?>"> <?php echo $eidResultValue; ?> </option>
                                                <?php } ?>

                                            </select>
                                        </td>

                                        <th scope="row">Infant stopped breastfeeding ?</th>
                                        <td>
                                            <select class="form-control" name="hasInfantStoppedBreastfeeding" id="hasInfantStoppedBreastfeeding">
                                                <option value=''> -- Select -- </option>
                                                <option value="yes"> Yes </option>
                                                <option value="no"> No </option>
                                                <option value="unknown"> Unknown </option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Age (months) breastfeeding stopped :</th>
                                        <td>
                                            <input type="number" class="form-control" style="max-width:200px;display:inline;" placeholder="Age (months) breastfeeding stopped" type="text" name="ageBreastfeedingStopped" id="ageBreastfeedingStopped" />
                                        </td>

                                        <th scope="row">PCR test performed on child before :</th>
                                        <td>
                                            <select class="form-control" name="pcrTestPerformedBefore" id="pcrTestPerformedBefore">
                                                <option value=''> -- Select -- </option>
                                                <option value="yes"> Yes </option>
                                                <option value="no"> No </option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Previous PCR test date :</th>
                                        <td>
                                            <input class="form-control date" type="text" name="previousPCRTestDate" id="previousPCRTestDate" placeholder="if yes, test date" />
                                        </td>

                                        <th scope="row">Reason for 2nd PCR :</th>
                                        <td>
                                            <select class="form-control" name="pcrTestReason" id="pcrTestReason">
                                                <option value=''> -- Select -- </option>
                                                <option value="Confirmation of positive first EID PCR test result"> Confirmation of positive first EID PCR test result </option>
                                                <option value="Repeat EID PCR test 6 weeks after stopping breastfeeding for children < 9 months"> Repeat EID PCR test 6 weeks after stopping breastfeeding for children < 9 months </option>
                                                <option value="Positive HIV rapid test result at 9 months or later"> Positive HIV rapid test result at 9 months or later </option>
                                                <option value="1st Test Positive"> 1st Test Positive </option>
                                                <option value="DBS Invalid"> DBS Invalid </option>
                                                <option value="Indeterminate"> Indeterminate </option>
                                                <option value="Infant Still breastfeeding"> Infant Still breastfeeding </option>
                                                <option value="Infact <2 months post cessation of breastfeeding"> Infact <2 months post cessation of breastfeeding </option>
                                                <option value="Infants less than 6 weeks"> Infants less than 6 weeks </option>
                                                <option value="Inadequate feeding history"> Inadequate feeding history </option>

                                                <option value="Other"> Other </option>
                                            </select>
                                        </td>
                                    </tr>


                                </table>

                                <br><br>
                                <table aria-describedby="table" class="table" aria-hidden="true">
                                    <tr>
                                        <th scope="row" colspan=4>
                                            <h4>Sample Information</h4>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:15% !important">Sample Collection Date <span class="mandatory">*</span> </th>
                                        <td style="width:35% !important;">
                                            <input class="form-control dateTime isRequired" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" onchange="sampleCodeGeneration();" />
                                        </td>
                                        <th style="width:15% !important;"></th>
                                        <td style="width:35% !important;"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Requesting Officer</th>
                                        <td>
                                            <input class="form-control" type="text" name="sampleRequestorName" id="sampleRequestorName" placeholder="Requesting Officer" />
                                        </td>
                                        <th scope="row">Sample Requestor Phone</th>
                                        <td>
                                            <input class="form-control" type="text" name="sampleRequestorPhone" id="sampleRequestorPhone" placeholder="Requesting Officer Phone" />
                                        </td>
                                    </tr>

                                </table>


                            </div>
                        </div>
                        <?php if ($_SESSION['instanceType'] != 'remoteuser') { ?>
                            <div class="box box-primary">
                                <div class="box-body">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">B. Reserved for Laboratory Use </h3>
                                    </div>
                                    <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                        <tr>
                                            <th scope="row"><label for="">Sample Received Date </label></th>
                                            <td>
                                                <input type="text" class="form-control dateTime" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _("Please enter date"); ?>" title="Please enter date de réception de léchantillon" <?php echo $labFieldDisabled; ?> onchange="" style="width:100%;" />
                                            </td>
                                            <td><label for="labId">Lab Name </label> </td>
                                            <td>
                                                <select name="labId" id="labId" class="form-control" title="Please select Testing Lab name" style="width:100%;">
                                                    <?= $general->generateSelectOptions($testingLabs, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                        <tr>
                                            <th scope="row">Is Sample Rejected?</th>
                                            <td>
                                                <select class="form-control" name="isSampleRejected" id="isSampleRejected">
                                                    <option value=''> -- Select -- </option>
                                                    <option value="yes"> Yes </option>
                                                    <option value="no"> No </option>
                                                </select>
                                            </td>

                                            <th scope="row" class="rejected" style="display: none;">Reason for Rejection</th>
                                            <td class="rejected" style="display: none;">
                                                <select class="form-control" name="sampleRejectionReason" id="sampleRejectionReason">
                                                    <option value=''> -- Select -- </option>
                                                    <?php echo $rejectionReason; ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr class="show-rejection rejected" style="display:none;">
                                            <td>Rejection Date<span class="mandatory">*</span></td>
                                            <td><input class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Select Rejection Date" /></td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td style="width:25%;"><label for="">Sample Test Date </label></td>
                                            <td style="width:25%;">
                                                <input type="text" class="form-control dateTime" id="sampleTestedDateTime" name="sampleTestedDateTime" placeholder="<?= _("Please enter date"); ?>" title="Test effectué le" <?php echo $labFieldDisabled; ?> onchange="" style="width:100%;" />
                                            </td>


                                            <th scope="row">Result</th>
                                            <td>
                                                <select class="form-control" name="result" id="result">
                                                    <option value=''> -- Select -- </option>
                                                    <?php foreach ($eidResults as $eidResultKey => $eidResultValue) { ?>
                                                        <option value="<?php echo $eidResultKey; ?>"> <?php echo $eidResultValue; ?> </option>
                                                    <?php } ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Reviewed On</th>
                                            <td><input type="text" name="reviewedOn" id="reviewedOn" class="dateTime disabled-field form-control" placeholder="Reviewed on" title="Please enter the Reviewed on" /></td>
                                            <th scope="row">Reviewed By</th>
                                            <td>
                                                <select name="reviewedBy" id="reviewedBy" class="select2 form-control" title="Please choose reviewed by" style="width: 100%;">
                                                    <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Approved On</th>
                                            <td><input type="text" name="approvedOn" id="approvedOn" class="dateTime disabled-field form-control" placeholder="Approved on" title="Please enter the Approved on" /></td>
                                            <th scope="row">Approved By</th>
                                            <td>
                                                <select name="approvedBy" id="approvedBy" class="select2 form-control" title="Please choose approved by" style="width: 100%;">
                                                    <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        <?php } ?>

                    </div>
                    <!-- /.box-body -->
                    <div class="box-footer">
                        <?php if (isset($arr['eid_sample_code'])) { ?>
                            <input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat" value="<?php echo $sFormat; ?>" />
                            <input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="<?php echo $sKey; ?>" />
                        <?php } ?>
                        <a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
                        <input type="hidden" name="formId" id="formId" value="5" />
                        <input type="hidden" name="eidSampleId" id="eidSampleId" value="" />
                        <input type="hidden" name="sampleCodeTitle" id="sampleCodeTitle" value="<?php echo $arr['sample_code']; ?>" />
                        <input type="hidden" name="provinceId" id="provinceId" />
                        <a href="/eid/requests/eid-requests.php" class="btn btn-default"> Cancel</a>
                    </div>
                    <!-- /.box-footer -->
                </form>
                <!-- /.row -->
            </div>
        </div>
        <!-- /.box -->
    </section>
    <!-- /.content -->
</div>



<script type="text/javascript">
    let changeProvince = true;
    let changeFacility = true;
    let provinceName = true;
    let facilityName = true;
    let machineName = true;
    let sampleCodeGenerationEvent = null

    function getfacilityDetails(obj) {

        $.blockUI();
        var cName = $("#facilityId").val();
        var pName = $("#province").val();
        if (pName != '' && provinceName && facilityName) {
            facilityName = false;
        }
        if ($.trim(pName) != '') {
            //if (provinceName) {
            $.post("/includes/siteInformationDropdownOptions.php", {
                    pName: pName,
                    testType: 'eid'
                },
                function(data) {
                    if (data != "") {
                        details = data.split("###");
                        $("#facilityId").html(details[0]);
                        $("#district").html(details[1]);
                        //$("#clinicianName").val(details[2]);
                    }
                });
            //}
            sampleCodeGeneration();
        } else if (pName == '') {
            provinceName = true;
            facilityName = true;
            $("#province").html("<?php echo $province; ?>");
            $("#facilityId").html("<?php echo $facility; ?>");
            $("#facilityId").select2("val", "");
            $("#district").html("<option value=''> -- Select -- </option>");
        }
        $.unblockUI();
    }


    function sampleCodeGeneration() {
        if (sampleCodeGenerationEvent) {
            sampleCodeGenerationEvent.abort();
        }

        var pName = $("#province").val();
        var sDate = $("#sampleCollectionDate").val();
        if (pName != '' && sDate != '') {
            $.blockUI();
            var provinceCode = ($("#province").find(":selected").attr("data-code") == null || $("#province").find(":selected").attr("data-code") == '') ? $("#province").find(":selected").attr("data-name") : $("#province").find(":selected").attr("data-code");
            sampleCodeGenerationEvent = $.post("/eid/requests/generateSampleCode.php", {
                    sDate: sDate,
                    autoTyp: 'auto2',
                    provinceCode: provinceCode,
                    'sampleFrom': 'png',
                    'provinceId': $("#province").find(":selected").attr("data-province-id")
                },
                function(data) {
                    var sCodeKey = JSON.parse(data);
                    $("#sampleCode").val(sCodeKey.sampleCode);
                    $("#sampleCodeFormat").val(sCodeKey.sampleCodeFormat);
                    $("#sampleCodeKey").val(sCodeKey.maxId);
                    $("#provinceId").val($("#province").find(":selected").attr("data-province-id"));
                    checkSampleNameValidation('form_vl', '<?php echo $sampleCode; ?>', 'sampleCode', null, 'The laboratory ID that you entered already exists. Please try another ID', null)
                    $.unblockUI();
                });
        }
    }
    // function sampleCodeGeneration() {
    //     var pName = $("#province").find(":selected").attr("data-code");
    //     var sDate = $("#sampleCollectionDate").val();

    //     if (pName != '' && sDate != '') {
    //         provinceCode
    //         $.post("/eid/requests/generateSampleCode.php", {
    //                 sDate: sDate,
    //                 pName: pName,
    //                 autoTyp: 'auto2',
    //                 //provinceCode: $("#province").find(":selected").attr("data-code"),
    //                 sampleFrom: 'png'
    //             },
    //             function(data) {
    //                 var sCodeKey = JSON.parse(data);
    //                 $("#sampleCode").val(sCodeKey.sampleCode);
    //                 $("#sampleCodeInText").html(sCodeKey.sampleCodeInText);
    //                 $("#sampleCodeFormat").val(sCodeKey.sampleCodeFormat);
    //                 $("#sampleCodeKey").val(sCodeKey.sampleCodeKey);
    //                 $("#provinceId").val($("#province").find(":selected").attr("data-province-id"));
    //             });
    //     }
    // }

    function getfacilityDistrictwise(obj) {
        $.blockUI();
        var dName = $("#district").val();
        var cName = $("#facilityId").val();
        if (dName != '') {
            $.post("/includes/siteInformationDropdownOptions.php", {
                    dName: dName,
                    cliName: cName,
                    testType: 'eid'
                },
                function(data) {
                    if (data != "") {
                        details = data.split("###");
                        $("#facilityId").html(details[0]);
                    }
                });
        } else {
            $("#facilityId").html("<option value=''> -- Sélectionner -- </option>");
        }
        $.unblockUI();
    }

    function getfacilityProvinceDetails(obj) {
        $.blockUI();
        //check facility name
        var cName = $("#facilityId").val();
        var pName = $("#province").val();
        if (cName != '' && provinceName && facilityName) {
            provinceName = false;
        }
        if (cName != '' && facilityName) {
            $.post("/includes/siteInformationDropdownOptions.php", {
                    cName: cName,
                    testType: 'eid'
                },
                function(data) {
                    if (data != "") {
                        details = data.split("###");
                        $("#province").html(details[0]);
                        $("#district").html(details[1]);
                        $("#clinicianName").val(details[2]);
                    }
                });
        } else if (pName == '' && cName == '') {
            provinceName = true;
            facilityName = true;
            $("#province").html("<?php echo $province; ?>");
            $("#facilityId").html("<?php echo $facility; ?>");
        }
        $.unblockUI();
    }


    function validateNow() {
        $.blockUI();
        var provinceId = $("#province").find(":selected").attr("data-province-id");
        flag = deforayValidator.init({
            formId: 'addEIDRequestForm'
        });
        if (flag) {
            $('.btn-disabled').attr('disabled', 'yes');
            $(".btn-disabled").prop("onclick", null).off("click");

            var provinceCode = ($("#province").find(":selected").attr("data-code") == null || $("#province").find(":selected").attr("data-code") == '') ? $("#province").find(":selected").attr("data-name") : $("#province").find(":selected").attr("data-code");

            <?php
            if (isset($arr['eid_sample_code'])) {
            ?>
                insertSampleCode('addEIDRequestForm', 'eidSampleId', 'sampleCode', 'sampleCodeKey', 'sampleCodeFormat', $("#formId").val(), 'sampleCollectionDate', provinceCode, provinceId);
            <?php
            } else {
            ?>
                document.getElementById('addEIDRequestForm').submit();
            <?php
            } ?>
        }

    }

    function updateMotherViralLoad() {
        //var motherVl = $("#motherViralLoadCopiesPerMl").val();
        var motherVlText = $("#motherViralLoadText").val();
        if (motherVlText != '') {
            $("#motherViralLoadCopiesPerMl").val('');
        }
    }

    $(document).ready(function() {

        $('#facilityId').select2({
            placeholder: "Select Clinic/Health Center"
        });
        $('#labId').select2({
            placeholder: "Select Lab Name"
        });
        $('#reviewedBy').select2({
            placeholder: "Select Reviewed By"
        });
        $('#approvedBy').select2({
            placeholder: "Select Approved By"
        });
        $('#district').select2({
            placeholder: "District"
        });
        $('#province').select2({
            placeholder: "Province"
        });
        $("#motherViralLoadCopiesPerMl").on("change keyup paste", function() {
            var motherVl = $("#motherViralLoadCopiesPerMl").val();
            //var motherVlText = $("#motherViralLoadText").val();
            if (motherVl != '') {
                $("#motherViralLoadText").val('');
            }
        });

    });
</script>
