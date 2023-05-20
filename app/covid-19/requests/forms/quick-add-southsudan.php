<?php
// imported in covid-19-add-request.php based on country in global config

use App\Registries\ContainerRegistry;
use App\Services\Covid19Service;



//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);
/* To get testing platform names */
$testPlatformResult = $general->getTestingPlatforms('covid19');

foreach ($testPlatformResult as $row) {
    $testPlatformList[$row['machine_name']] = $row['machine_name'];
}
//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);




// Getting the list of Provinces, Districts and Facilities


/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);


//$covid19Results = $covid19Service->getCovid19Results();
$specimenTypeResult = $covid19Service->getCovid19SampleTypes();
//$covid19ReasonsForTesting = $covid19Service->getCovid19ReasonsForTesting();
//$covid19Symptoms = $covid19Service->getCovid19Symptoms();
//$covid19Comorbidities = $covid19Service->getCovid19Comorbidities();


$rKey = '';
$sKey = '';
$sFormat = '';
$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
if ($_SESSION['instanceType'] == 'remoteuser') {
    $sampleCodeKey = 'remote_sample_code_key';
    $sampleCode = 'remote_sample_code';
    //check user exist in user_facility_map table
    $chkUserFcMapQry = "SELECT user_id FROM user_facility_map WHERE user_id='" . $_SESSION['userId'] . "'";
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
        <h1><em class="fa-solid fa-pen-to-square"></em> <?php echo _("COVID-19 VIRUS LABORATORY TEST REQUEST FORM"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
            <li class="active"><?php echo _("Add New Request"); ?></li>
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
                <form class="form-horizontal" method="post" name="addCovid19RequestForm" id="addCovid19RequestForm" autocomplete="off" action="covid-19-add-request-helper.php">
                    <div class="box-body">
                        <div class="box box-default">
                            <div class="box-body">
                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title">QUICK ADD FORM</h3>
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <?php if ($_SESSION['instanceType'] == 'remoteuser') { ?>
                                            <th scope="row"><label for="sampleCode">Sample ID </label></th>
                                            <td>
                                                <span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"></span>
                                                <input type="hidden" id="sampleCode" name="sampleCode" />
                                            </td>
                                        <?php } else { ?>
                                            <td><label for="sampleCode">Sample ID </label><span class="mandatory">*</span></td>
                                            <td>
                                                <input type="text" class="form-control isRequired" id="sampleCode" name="sampleCode" readonly="readonly" placeholder="Sample ID" title="Please enter sample code" style="width:100%;" onchange="checkSampleNameValidation('form_covid19','<?php echo $sampleCode; ?>',this.id,null,'The sample id that you entered already exists. Please try another sample id',null)" />
                                            </td>
                                        <?php } ?>
                                        <td><label for="sourceOfAlertPOE">Source of Alert / POE</label></td>
                                        <td>
                                            <select class="form-control" name="sourceOfAlertPOE" id="sourceOfAlertPOE" title="Please choose source of Alert / POE" style="width:100%;">
                                                <option value=""> -- Select -- </option>
                                                <option value="hotline">Hotline</option>
                                                <option value="community-surveillance">Community Surveillance</option>
                                                <option value="poe">POE</option>
                                                <option value="contact-tracing">Contact Tracing</option>
                                                <option value="clinic">Clinic</option>
                                                <option value="sentinel-site">Sentinel Site</option>
                                                <option value="screening">Screening</option>
                                                <option value="others">Others</option>
                                            </select>
                                        </td>
                                        <td class="show-alert-poe" style="display: none;"><label for="sourceOfAlertPOE">Source of Alert / POE Others<span class="mandatory">*</span></label></td>
                                        <td class="show-alert-poe" style="display: none;">
                                            <input type="text" class="form-control" name="alertPoeOthers" id="alertPoeOthers" placeholder="Source of Alert / POE Others" title="Please choose source of Alert / POE" style="width:100%;">
                                        </td>

                                        <td><label for="facilityId">Facility </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control isRequired " name="facilityId" id="facilityId" title="Please choose service provider" style="width:100%;">
                                                <?php echo $facility; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="patientId">Case ID <span class="mandatory">*</span> </label></th>
                                        <td>
                                            <input type="text" class="form-control isRequired" id="patientId" name="patientId" placeholder="Case Identification" title="Please enter Case ID" style="width:100%;" onchange="" />
                                        </td>

                                        <th scope="row"><label for="externalSampleCode">DHIS2 Case ID </label></th>
                                        <td><input type="text" class="form-control" id="externalSampleCode" name="externalSampleCode" placeholder="DHIS2 Case ID" title="Please enter DHIS2 Case ID" style="width:100%;" /></td>
                                        <th scope="row">Specimen Type <span class="mandatory">*</span></th>
                                        <td>
                                            <select name="specimenType" id="specimenType" class="form-control isRequired" title="Please choose specimen type" style="width:100%">
                                                <?php echo $general->generateSelectOptions($specimenTypeResult, null, '-- Select --'); ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Sample Collection Date <span class="mandatory">*</span> </th>
                                        <td>
                                            <input class="form-control isRequired" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" onchange="sampleCodeGeneration();" />
                                        </td>

                                        <th scope="row"><label for="">Sample Received Date <span class="mandatory">*</span></label></th>
                                        <td>
                                            <input type="text" class="form-control isRequired" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _("Please enter date"); ?>" title="Please enter sample receipt date" <?php echo (isset($labFieldDisabled) && trim($labFieldDisabled) != '') ? $labFieldDisabled : ''; ?> onchange="" style="width:100%;" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Is Sample Rejected? <span class="mandatory">*</span> </th>
                                        <td>
                                            <select class="form-control isRequired" name="isSampleRejected" id="isSampleRejected">
                                                <option value=''> -- Select -- </option>
                                                <option value="yes"> Yes </option>
                                                <option value="no"> No </option>
                                            </select>
                                        </td>

                                        <th scope="row" class="show-rejection" style="display:none;">Reason for Rejection</th>
                                        <td class="show-rejection" style="display:none;">
                                            <select class="form-control" name="sampleRejectionReason" id="sampleRejectionReason">
                                                <option> -- Select -- </option>
                                                <?php echo $rejectionReason; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><label for="labId">Lab Name <span class="mandatory">*</span></label> </td>
                                        <td>
                                            <select name="labId" id="labId" class="form-control isRequired" title="Please select Testing Lab name" style="width:100%;" onchange="getTestingPoints();">
                                                <?= $general->generateSelectOptions($testingLabs, null, '-- Select --'); ?>
                                            </select>
                                        </td>

                                        <th scope="row" class="testingPointField" style="display:none;"><label for="">Testing Point </label></th>
                                        <td class="testingPointField" style="display:none;">
                                            <select name="testingPoint" id="testingPoint" class="form-control" title="Please select a Testing Point" style="width:100%;">
                                            </select>
                                        </td>
                                        <th scope="row"></th>
                                        <td></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <!-- /.box-body -->
                        <div class="box-footer">
                            <?php if ($arr['covid19_sample_code'] == 'auto' || $arr['covid19_sample_code'] == 'YY' || $arr['covid19_sample_code'] == 'MMYY') { ?>
                                <input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat" value="<?php echo $sFormat; ?>" />
                                <input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="<?php echo $sKey; ?>" />
                                <input type="hidden" name="saveNext" id="saveNext" />
                                <input type="hidden" name="quickForm" id="quickForm" value="quick" />
                                <!-- <input type="hidden" name="pageURL" id="pageURL" value="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" /> -->
                            <?php } ?>
                            <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
                            <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();$('#saveNext').val('next');return false;">Save and Next</a>
                            <input type="hidden" name="formId" id="formId" value="<?php echo $arr['vl_form']; ?>" />
                            <input type="hidden" name="covid19SampleId" id="covid19SampleId" value="" />
                            <a href="/covid-19/requests/covid-19-requests.php" class="btn btn-default"> Cancel</a>
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
    function validateNow() {
        flag = deforayValidator.init({
            formId: 'addCovid19RequestForm'
        });
        if (flag) {
            <?php if ($arr['covid19_sample_code'] == 'auto' || $arr['covid19_sample_code'] == 'YY' || $arr['covid19_sample_code'] == 'MMYY') { ?>
                insertSampleCode('addCovid19RequestForm', 'covid19SampleId', 'sampleCode', 'sampleCodeKey', 'sampleCodeFormat', 3, 'sampleCollectionDate');
            <?php } else { ?>
                document.getElementById('addCovid19RequestForm').submit();
            <?php } ?>
        }
    }

    function getTestingPoints() {
        var labId = $("#labId").val();
        var selectedTestingPoint = null;
        if (labId) {
            $.post("/includes/getTestingPoints.php", {
                    labId: labId,
                    selectedTestingPoint: selectedTestingPoint
                },
                function(data) {
                    if (data != "") {
                        $(".testingPointField").show();
                        $("#testingPoint").html(data);
                    } else {
                        $(".testingPointField").hide();
                        $("#testingPoint").html('');
                    }
                });
        }
    }

    function sampleCodeGeneration() {
        var pName = $("#province").val();
        var sDate = $("#sampleCollectionDate").val();
        if (pName != '' && sDate != '') {
            $.post("/covid-19/requests/generateSampleCode.php", {
                    sDate: sDate,
                    pName: pName
                },
                function(data) {
                    var sCodeKey = JSON.parse(data);
                    $("#sampleCode").val(sCodeKey.sampleCode);
                    $("#sampleCodeInText").html(sCodeKey.sampleCodeInText);
                    $("#sampleCodeFormat").val(sCodeKey.sampleCodeFormat);
                    $("#sampleCodeKey").val(sCodeKey.sampleCodeKey);
                });
        }
    }

    $(document).ready(function() {
        $('#facilityId').select2({
            placeholder: "Select Clinic/Health Center"
        });

        $('#sourceOfAlertPOE').change(function(e) {
            if (this.value == 'others') {
                $('.show-alert-poe').show();
                $('#alertPoeOthers').addClass('isRequired');
            } else {
                $('.show-alert-poe').hide();
                $('#alertPoeOthers').removeClass('isRequired');
            }
        });
    });
</script>
