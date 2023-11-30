<?php

use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\GeoLocationsService;
use App\Services\UsersService;

$title = _translate("TB | View All Requests");
$hidesrcofreq = false;
$dateRange = $labName = $srcOfReq = $srcStatus = null;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_GET = $request->getQueryParams();

if (!empty($_GET['id'])) {
    $params = explode("##", base64_decode((string) $_GET['id']));
    $dateRange = $params[0];
    $labName = $params[1];
    $srcOfReq = $params[2];
    $srcStatus = $params[3];
    $hidesrcofreq = true;
}

$facilityId = null;
$labId = null;
if (isset($_GET['facilityId']) && $_GET['facilityId'] != "" && isset($_GET['labId']) && $_GET['labId'] != "") {
    $facilityId = base64_decode((string) $_GET['facilityId']);
    $labId = base64_decode((string) $_GET['labId']);
}
require_once APPLICATION_PATH . '/header.php';

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);
$state = $geolocationService->getProvinces("yes");
$healthFacilites = $facilitiesService->getHealthFacilities('tb');
/* Global config data */
$arr = $general->getGlobalConfig();

$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, $facilityId, "-- Select --");
$formId = $general->getGlobalConfig('vl_form');
$testingLabs = $facilitiesService->getTestingLabs('tb');
$testingLabsDropdown = $general->generateSelectOptions($testingLabs, $labId, "-- Select --");

//Funding source list
$fundingSourceList = $general->getFundingSources();

//Implementing partner list
$implementingPartnerList = $general->getImplementationPartners();

$batQuery = "SELECT batch_code FROM batch_details WHERE test_type ='tb' AND batch_status='completed'";
$batResult = $db->rawQuery($batQuery);

// Src of alert req
$srcQuery = "SELECT DISTINCT source_of_request from form_tb where source_of_request is not null AND source_of_request not like ''";
$srcResults = $db->rawQuery($srcQuery);
$srcOfReqList = [];
foreach ($srcResults as $list) {
    $srcOfReqList[$list['source_of_request']] = strtoupper((string) $list['source_of_request']);
}
?>
<style>
    .select2-selection__choice {
        color: black !important;
    }

    th {
        display: revert !important;
    }

    <?php if (!empty($_GET['id'])) { ?>header {
        display: none;
    }

    .main-sidebar {
        z-index: -9;
    }

    .content-wrapper {
        margin-left: 0px;
    }

    <?php } ?>
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <?php if (!$hidesrcofreq) { ?>
        <!-- Content Header (Page header) -->
        <section class="content-header">
            <h1><em class="fa-solid fa-pen-to-square"></em> <?php echo _translate("TB Test Requests"); ?></h1>
            <ol class="breadcrumb">
                <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
                <li class="active"><?php echo _translate("Test Request"); ?></li>
            </ol>
        </section>
    <?php } ?>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <table aria-describedby="table" id="advanceFilter" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width: 98%;margin-bottom: 0px;display: none;">
                        <tr>
                            <td><strong><?php echo _translate("Sample Collection Date"); ?> :</strong></td>
                            <td>
                                <input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control" placeholder="<?php echo _translate('Select Collection Date'); ?>" readonly style="background:#fff;" />
                            </td>
                            <td><strong><?php echo _translate("Select Sample Received Date At Lab"); ?> :</strong></td>
                            <td>
                                <input type="text" id="sampleReceivedDateAtLab" name="sampleReceivedDateAtLab" class="form-control" placeholder="<?php echo _translate('Select Sample Received Date At Lab'); ?>" readonly style="background:#fff;" />
                            </td>
                            <td><strong><?php echo _translate("Source of Request"); ?> :</strong></td>
                            <td>
                                <select class="form-control" id="srcOfReq" name="srcOfReq" title="<?php echo _translate('Please select source of request'); ?>">
                                    <?= $general->generateSelectOptions($srcOfReqList, null, "--Select--"); ?>
                                </select>
                            </td>

                        </tr>
                        <tr>

                            <td><strong><?php echo _translate("Sample Tested Date"); ?> :</strong></td>
                            <td>
                                <input type="text" id="sampleTestedDate" name="sampleTestedDate" class="form-control" placeholder="<?php echo _translate('Select Tested Date'); ?>" readonly style="background:#fff;" />
                            </td>
                            <td><strong><?php echo _translate("Batch Code"); ?> :</strong></td>
                            <td>
                                <select class="form-control" id="batchCode" name="batchCode" title="<?php echo _translate('Please select batch code'); ?>">
                                    <option value=""> <?php echo _translate("-- Select --"); ?> </option>
                                    <?php
                                    foreach ($batResult as $code) {
                                    ?>
                                        <option value="<?php echo $code['batch_code']; ?>"><?php echo $code['batch_code']; ?></option>
                                    <?php
                                    }
                                    ?>
                                </select>
                            </td>
                            <td><strong><?php echo _translate("Funding Sources"); ?>&nbsp;:</strong></td>
                            <td>
                                <select class="form-control" name="fundingSource" id="fundingSource" title="<?php echo _translate('Please choose funding source'); ?>">
                                    <option value=""> <?php echo _translate("-- Select --"); ?> </option>
                                    <?php
                                    foreach ($fundingSourceList as $fundingSource) {
                                    ?>
                                        <option value="<?php echo base64_encode((string) $fundingSource['funding_source_id']); ?>"><?= $fundingSource['funding_source_name']; ?></option>
                                    <?php } ?>
                                </select>
                            </td>
                        </tr>
                        <tr>

                            <td><strong><?php echo _translate("Implementing Partners"); ?>&nbsp;:</strong></td>
                            <td>
                                <select class="form-control" name="implementingPartner" id="implementingPartner" title="<?php echo _translate('Please choose implementing partner'); ?>">
                                    <option value=""> <?php echo _translate("-- Select --"); ?> </option>
                                    <?php
                                    foreach ($implementingPartnerList as $implementingPartner) {
                                    ?>
                                        <option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>"><?= $implementingPartner['i_partner_name']; ?></option>
                                    <?php } ?>
                                </select>
                            </td>
                            <td><strong><?php echo _translate("Req. Sample Type"); ?> :</strong></td>
                            <td>
                                <select class="form-control" id="requestSampleType" name="requestSampleType" title="<?php echo _translate('Please select request sample type'); ?>">
                                    <option value=""><?php echo _translate("All"); ?></option>
                                    <option value="result"><?php echo _translate("Sample With Result"); ?></option>
                                    <option value="noresult"><?php echo _translate("Sample Without Result"); ?></option>
                                </select>
                            </td>
                            <td><strong><?php echo _translate("Gender"); ?>&nbsp;:</strong></td>
                            <td>
                                <select name="gender" id="gender" class="form-control" title="<?php echo _translate('Please choose gender'); ?>" style="width:220px;" onchange="hideFemaleDetails(this.value)">
                                    <option value=""> <?php echo _translate("-- Select --"); ?> </option>
                                    <option value="male"><?php echo _translate("Male"); ?></option>
                                    <option value="female"><?php echo _translate("Female"); ?></option>
                                    <option value="not_recorded"><?php echo _translate("Not Recorded"); ?></option>
                                </select>
                            </td>

                        </tr>
                        <tr>

                            <td><strong><?php echo _translate("Status"); ?>&nbsp;:</strong></td>
                            <td>
                                <select name="status" id="status" class="form-control" title="<?php echo _translate('Please choose status'); ?>" onchange="checkSampleCollectionDate();">
                                    <option value="" selected=selected><?php echo _translate("All Status"); ?></option>
                                    <option value="7"><?php echo _translate("Accepted"); ?></option>
                                    <option value="4"><?php echo _translate("Rejected"); ?></option>
                                    <option value="8"><?php echo _translate("Awaiting Approval"); ?></option>
                                    <option value="6"><?php echo _translate("Registered At Testing Lab"); ?></option>
                                    <option value="10"><?php echo _translate("Expired"); ?></option>
                                </select>
                            </td>
                            <td><strong><?php echo _translate("Show only Reordered Samples"); ?>&nbsp;:</strong></td>
                            <td>
                                <select name="showReordSample" id="showReordSample" class="form-control" title="<?php echo _translate('Please choose record sample'); ?>">
                                    <option value=""> <?php echo _translate("-- Select --"); ?> </option>
                                    <option value="yes"><?php echo _translate("Yes"); ?></option>
                                    <option value="no"><?php echo _translate("No"); ?></option>
                                </select>
                            </td>

                            <td><strong><?php echo _translate("Province/State"); ?>&nbsp;:</strong></td>
                            <td>
                                <select name="state" id="state" onchange="getByProvince(this.value)" class="form-control" title="<?php echo _translate('Please choose Province/State/Region'); ?>" onkeyup="searchVlRequestData()">
                                    <?= $general->generateSelectOptions($state, null, _translate("-- Select --")); ?>
                                </select>
                            </td>
                        </tr>
                        <tr>

                            <td><strong><?php echo _translate("District/County"); ?> :</strong></td>
                            <td>
                                <select class="form-control" id="district" onchange="getByDistrict(this.value)" name="district" title="<?php echo _translate('Please select Province/State'); ?>">
                                </select>
                            </td>
                            <td><strong><?php echo _translate("Facility Name"); ?> :</strong></td>
                            <td>
                                <select class="form-control" id="facilityName" name="facilityName" multiple="multiple" title="<?php echo _translate('Please select facility name'); ?>" style="width:100%;">
                                    <?= $facilitiesDropdown; ?>
                                </select>
                            </td>
                            <td><strong><?php echo _translate("Testing Lab"); ?> :</strong></td>
                            <td>
                                <select class="form-control" id="vlLab" name="vlLab" title="<?php echo _translate('Please select vl lab'); ?>" style="width:220px;">
                                    <?= $testingLabsDropdown; ?>
                                </select>
                            </td>

                        </tr>
                        <tr>
                            <td><strong><?php echo _translate("Export with Patient ID and Name"); ?>&nbsp;:</strong></td>
                            <td>
                                <select name="patientInfo" id="patientInfo" class="form-control" title="<?php echo _translate('Please choose community sample'); ?>" style="width:100%;">
                                    <option value="yes"><?php echo _translate("Yes"); ?></option>
                                    <option value="no"><?php echo _translate("No"); ?></option>
                                </select>

                            </td>
                            <td><strong><?php echo _translate("Patient ID"); ?>&nbsp;:</strong></td>
                            <td>
                                <input type="text" id="patientId" name="patientId" class="form-control" placeholder="<?php echo _translate('Enter Patient ID'); ?>" style="background:#fff;" />
                            </td>
                            <td><strong><?php echo _translate("Patient Name"); ?>&nbsp;:</strong></td>
                            <td>
                                <input type="text" id="patientName" name="patientName" class="form-control" placeholder="<?php echo _translate('Enter Patient Name'); ?>" style="background:#fff;" />
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php echo _translate("Show Rejected Samples"); ?>&nbsp;:</strong></td>
                            <td>
                                <select name="rejectedSamples" id="rejectedSamples" class="form-control" title="<?php echo _translate('Please choose show rejected samples'); ?>" style="width:100%;">
                                    <option value="yes"><?php echo _translate("Yes"); ?></option>
                                    <option value="no" selected="selected"><?php echo _translate("No"); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2"><input type="button" onclick="searchVlRequestData();" value="<?php echo _translate("Search"); ?>" class="btn btn-default btn-sm">
                                &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?= _translate('Reset'); ?></span></button>
                                &nbsp;<button class="btn btn-danger btn-sm" onclick="hideAdvanceSearch('advanceFilter','filter');"><span><?php echo _translate("Hide Advanced Search Options"); ?></span></button>
                            </td>
                            <td colspan="4">
                                <?php if (isset($_SESSION['privileges']) && in_array("/tb/requests/tb-add-request.php", $_SESSION['privileges']) && !$hidesrcofreq) { ?>
                                    <a style=" margin: 0px 5px; " href="/tb/requests/tb-add-request.php" class="btn btn-primary btn-sm pull-right"> <em class="fa-solid fa-plus"></em> <?php echo _translate("Add new TB Request"); ?></a>
                                <?php } ?>
                                <?php if (isset($_SESSION['privileges']) && in_array("/tb/requests/export-tb-requests.php", $_SESSION['privileges'])) { ?>
                                    <a class="btn btn-success btn-sm pull-right" href="javascript:void(0);" onclick="exportAllPendingTbRequest();"><span><?php echo _translate("Export Requests"); ?></span></a>
                                <?php } ?>
                            </td>
                        </tr>
                    </table>
                    <table aria-describedby="table" id="filter" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width: 98%;margin-bottom: 0px;">
                        <tr id="">
                            <td>

                                <?php
                                if (isset($_SESSION['privileges']) && in_array("/tb/requests/tb-add-request.php", $_SESSION['privileges']) && !$hidesrcofreq) { ?>
                                    <a style=" margin: 0px 5px; " href="/tb/requests/tb-add-request.php" class="btn btn-primary btn-sm pull-right"> <em class="fa-solid fa-plus"></em> <?php echo _translate("Add new TB Request"); ?></a>
                                <?php } ?>
                                <?php if (isset($_SESSION['privileges']) && in_array("/tb/requests/export-tb-requests.php", $_SESSION['privileges'])) { ?>
                                    <a class="btn btn-success btn-sm pull-right" href="javascript:void(0);" onclick="exportAllPendingTbRequest();"><span><?php echo _translate("Export Requests"); ?></span></a>
                                <?php } ?>
                                <button style=" margin: 0px 5px; " class="btn btn-primary btn-sm pull-right" style="margin-right:5px;" onclick="hideAdvanceSearch('filter','advanceFilter');"><span><?php echo _translate("Show Advanced Search Options"); ?></span></button>
                            </td>
                        </tr>
                    </table>

                    <!-- /.box-header -->
                    <div class="box-body">
                        <table aria-describedby="table" id="vlRequestDataTable" class="table table-bordered table-striped" aria-hidden="true">
                            <thead>
                                <tr>
                                    <!--<th><input type="checkbox" id="checkTestsData" onclick="toggleAllVisible()"/></th>-->
                                    <th><?php echo _translate("Sample ID"); ?></th>
                                    <?php if ($_SESSION['instanceType'] != 'standalone') { ?>
                                        <th><?php echo _translate("Remote Sample ID"); ?></th>
                                    <?php } ?>
                                    <th><?php echo _translate("Sample Collection Date"); ?></th>
                                    <th><?php echo _translate("Batch Code"); ?></th>
                                    <th scope="row"><?php echo _translate("Testing Lab"); ?></th>
                                    <th scope="row"><?php echo _translate("Facility Name"); ?></th>
                                    <?php if ($formId == COUNTRY\SOUTH_SUDAN) { ?>
                                        <th><?php echo _translate("Case ID"); ?></th>
                                    <?php } else { ?>
                                        <th><?php echo _translate("Patient ID"); ?></th>
                                    <?php } ?>
                                    <th><?php echo _translate("Patient Name"); ?></th>
                                    <th><?php echo _translate("Province/State"); ?></th>
                                    <th><?php echo _translate("District/County"); ?></th>
                                    <th><?php echo _translate("Result"); ?></th>
                                    <th><?php echo _translate("Last Modified On"); ?></th>
                                    <th scope="row"><?php echo _translate("Status"); ?></th>
                                    <?php if (isset($_SESSION['privileges']) && (in_array("/tb/requests/tb-edit-request.php", $_SESSION['privileges'])) || (in_array("tb-view-request.php", $_SESSION['privileges'])) && !$hidesrcofreq) { ?>
                                        <th><?php echo _translate("Action"); ?></th>
                                    <?php } ?>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="15" class="dataTables_empty"><?php echo _translate("Loading data from server"); ?></td>
                                </tr>
                            </tbody>
                        </table>
                        <?php
                        if (isset($global['bar_code_printing']) && $global['bar_code_printing'] == 'zebra-printer') {
                        ?>

                            <div id="printer_data_loading" style="display:none"><span id="loading_message"><?php echo _translate("Loading Printer Details"); ?>...</span><br />
                                <div class="progress" style="width:100%">
                                    <div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%">
                                    </div>
                                </div>
                            </div> <!-- /printer_data_loading -->
                            <div id="printer_details" style="display:none">
                                <span id="selected_printer"><?php echo _translate("No printer selected"); ?>!</span>
                                <button type="button" class="btn btn-success" onclick="changePrinter()"><?php echo _translate("Change/Retry"); ?></button>
                            </div><br /> <!-- /printer_details -->
                            <div id="printer_select" style="display:none">
                                <?php echo _translate("Zebra Printer Options"); ?><br />
                                <?php echo _translate("Printer"); ?>: <select id="printers"></select>
                            </div> <!-- /printer_select -->

                        <?php
                        }
                        ?>

                    </div>

                </div>
                <!-- /.box -->

            </div>
            <!-- /.col -->
        </div>
        <!-- /.row -->
    </section>
    <!-- /.content -->
</div>
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>

<?php
if (isset($global['bar_code_printing']) && $global['bar_code_printing'] != "off") {
    if ($global['bar_code_printing'] == 'dymo-labelwriter-450') {
?>
        <script src="/assets/js/DYMO.Label.Framework.js"></script>
        <script src="/uploads/barcode-formats/dymo-format.js"></script>
        <script src="/assets/js/dymo-print.js"></script>
    <?php
    } else if ($global['bar_code_printing'] == 'zebra-printer') {
    ?>
        <script src="/assets/js/zebra-browserprint.js.js"></script>
        <script src="/uploads/barcode-formats/zebra-format.js"></script>
        <script src="/assets/js/zebra-print.js"></script>
<?php
    }
}
?>



<script type="text/javascript">
    let searchExecuted = false;
    var startDate = "";
    var endDate = "";
    var selectedTests = [];
    var selectedTestsId = [];
    var oTable = null;
    $(document).ready(function() {
        <?php
        if (isset($_GET['barcode']) && $_GET['barcode'] == 'true') {
            echo "printBarcodeLabel('" . htmlspecialchars((string) $_GET['s']) . "','" . htmlspecialchars((string) $_GET['f']) . "');";
        }
        ?>
        $("#facilityName").select2({
            placeholder: "<?php echo _translate("Select Facilities"); ?>"
        });
        $("#vlLab").select2({
            placeholder: "<?php echo _translate("Select Vl Lab"); ?>"
        });
        $("#batchCode").select2({
            placeholder: "<?php echo _translate("Select Batch Code"); ?>"
        });
        loadVlRequestData();
        $('#sampleCollectionDate, #sampleReceivedDateAtLab, #sampleTestedDate').daterangepicker({
                locale: {
                    cancelLabel: "<?= _translate("Clear", true); ?>",
                    format: 'DD-MMM-YYYY',
                    separator: ' to ',
                },
                showDropdowns: true,
                alwaysShowCalendars: false,
                startDate: moment().subtract(28, 'days'),
                endDate: moment(),
                maxDate: moment(),
                ranges: {
                    'Today': [moment(), moment()],
                    'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                }
            },
            function(start, end) {
                startDate = start.format('YYYY-MM-DD');
                endDate = end.format('YYYY-MM-DD');
            });
        $('#sampleCollectionDate, #sampleReceivedDateAtLab, #sampleTestedDate').val("");

        $(".showhideCheckBox").change(function() {
            if ($(this).attr('checked')) {
                idpart = $(this).attr('data-showhide');
                $("#" + idpart + "-sort").show();
            } else {
                idpart = $(this).attr('data-showhide');
                $("#" + idpart + "-sort").hide();
            }
        });

        $("#showhide").hover(function() {}, function() {
            $(this).fadeOut('slow')
        });

        $("#advanceFilter input, #advanceFilter select").on("change", function() {
            searchExecuted = false;
        });

    });

    function fnShowHide(iCol) {
        var bVis = oTable.fnSettings().aoColumns[iCol].bVisible;
        oTable.fnSetColumnVis(iCol, bVis ? false : true);
    }

    function loadVlRequestData() {
        $.blockUI();
        oTable = $('#vlRequestDataTable').dataTable({
            "oLanguage": {
                "sLengthMenu": "_MENU_ records per page"
            },
            "bJQueryUI": false,
            "bAutoWidth": false,
            "bInfo": true,
            "bScrollCollapse": true,
            //"bStateSave" : true,
            "bRetrieve": true,
            "aoColumns": [{
                    "sClass": "center"
                },
                <?php if ($_SESSION['instanceType'] != 'standalone') { ?> {
                        "sClass": "center"
                    },
                <?php } ?> {
                    "sClass": "center"
                }, {
                    "sClass": "center"
                }, {
                    "sClass": "center"
                }, {
                    "sClass": "center"
                }, {
                    "sClass": "center"
                }, {
                    "sClass": "center"
                }, {
                    "sClass": "center"
                }, {
                    "sClass": "center"
                }, {
                    "sClass": "center"
                }, {
                    "sClass": "center"
                }, {
                    "sClass": "center"
                },
                <?php if (isset($_SESSION['privileges']) && (in_array("/tb/requests/tb-edit-request.php", $_SESSION['privileges'])) || (in_array("tb-view-request.php", $_SESSION['privileges'])) && !$hidesrcofreq) { ?> {
                        "sClass": "center action",
                        "bSortable": false
                    },
                <?php } ?>
            ],
            "aaSorting": [
                [<?php echo ($sarr['sc_user_type'] == 'remoteuser' || $sarr['sc_user_type'] == 'vluser') ? 11 : 10 ?>, "desc"]
            ],
            "fnDrawCallback": function() {
                var checkBoxes = document.getElementsByName("chk[]");
                len = checkBoxes.length;
                for (c = 0; c < len; c++) {
                    if (jQuery.inArray(checkBoxes[c].id, selectedTestsId) != -1) {
                        checkBoxes[c].setAttribute("checked", true);
                    }
                }
            },
            "bProcessing": true,
            "bServerSide": true,
            "sAjaxSource": "/tb/requests/get-request-list.php",
            "fnServerData": function(sSource, aoData, fnCallback) {
                aoData.push({
                    "name": "batchCode",
                    "value": $("#batchCode").val()
                });
                aoData.push({
                    "name": "sampleCollectionDate",
                    "value": $("#sampleCollectionDate").val()
                });
                aoData.push({
                    "name": "facilityName",
                    "value": $("#facilityName").val()
                });
                aoData.push({
                    "name": "sampleType",
                    "value": $("#sampleType").val()
                });
                aoData.push({
                    "name": "district",
                    "value": $("#district").val()
                });
                aoData.push({
                    "name": "state",
                    "value": $("#state").val()
                });
                aoData.push({
                    "name": "reqSampleType",
                    "value": $("#requestSampleType").val()
                });
                aoData.push({
                    "name": "vlLab",
                    "value": $("#vlLab").val()
                });
                aoData.push({
                    "name": "gender",
                    "value": $("#gender").val()
                });
                aoData.push({
                    "name": "status",
                    "value": $("#status").val()
                });
                aoData.push({
                    "name": "showReordSample",
                    "value": $("#showReordSample").val()
                });
                aoData.push({
                    "name": "fundingSource",
                    "value": $("#fundingSource").val()
                });
                aoData.push({
                    "name": "implementingPartner",
                    "value": $("#implementingPartner").val()
                });
                aoData.push({
                    "name": "sampleReceivedDateAtLab",
                    "value": $("#sampleReceivedDateAtLab").val()
                });
                aoData.push({
                    "name": "sampleTestedDate",
                    "value": $("#sampleTestedDate").val()
                });
                aoData.push({
                    "name": "srcOfReq",
                    "value": $("#srcOfReq").val()
                });
                aoData.push({
                    "name": "patientId",
                    "value": $("#patientId").val()
                });
                aoData.push({
                    "name": "patientName",
                    "value": $("#patientName").val()
                });
                aoData.push({
                    "name": "dateRangeModel",
                    "value": '<?php echo $dateRange; ?>'
                });
                aoData.push({
                    "name": "labIdModel",
                    "value": '<?php echo $labName; ?>'
                });
                aoData.push({
                    "name": "srcOfReqModel",
                    "value": '<?php echo $srcOfReq; ?>'
                });
                aoData.push({
                    "name": "srcStatus",
                    "value": '<?php echo $srcStatus; ?>'
                });
                aoData.push({
                    "name": "hidesrcofreq",
                    "value": '<?php echo $hidesrcofreq; ?>'
                });
                aoData.push({
                    "name": "rejectedSamples",
                    "value": $("#rejectedSamples").val()
                });
                $.ajax({
                    "dataType": 'json',
                    "type": "POST",
                    "url": sSource,
                    "data": aoData,
                    "success": fnCallback
                });
            }
        });
        $.unblockUI();
    }

    function searchVlRequestData() {
        searchExecuted = true;
        $.blockUI();
        oTable.fnDraw();
        $.unblockUI();
    }

    function loadVlRequestStateDistrict() {
        oTable.fnDraw();
    }

    function toggleAllVisible() {
        //alert(tabStatus);
        $(".checkTests").each(function() {
            $(this).prop('checked', false);
            selectedTests.splice($.inArray(this.value, selectedTests), 1);
            selectedTestsId.splice($.inArray(this.id, selectedTestsId), 1);
            $("#status").prop('disabled', true);
        });
        if ($("#checkTestsData").is(':checked')) {
            $(".checkTests").each(function() {
                $(this).prop('checked', true);
                selectedTests.push(this.value);
                selectedTestsId.push(this.id);
            });
            $("#status").prop('disabled', false);
        } else {
            $(".checkTests").each(function() {
                $(this).prop('checked', false);
                selectedTests.splice($.inArray(this.value, selectedTests), 1);
                selectedTestsId.splice($.inArray(this.id, selectedTestsId), 1);
                $("#status").prop('disabled', true);
            });
        }
        $("#checkedTests").val(selectedTests.join());
    }

    function exportAllPendingTbRequest() {
        if (searchExecuted === false) {
            searchVlRequestData();
        }
        $.blockUI();
        $.post("/tb/requests/export-tb-requests.php", {
                reqSampleType: $('#requestSampleType').val(),
                patientInfo: $('#patientInfo').val(),
            },
            function(data) {
                $.unblockUI();
                if (data === "" || data === null || data === undefined) {
                    alert("<?php echo _translate("Unable to generate the excel file"); ?>");
                } else {
                    window.open('/download.php?d=a&f=' + data, '_blank');
                }
            });
    }

    function hideAdvanceSearch(hideId, showId) {
        $("#" + hideId).hide();
        $("#" + showId).show();
    }

    <?php if (isset($_SESSION['instanceType']) && $_SESSION['instanceType'] == 'vluser') { ?>
        var remoteUrl = '<?php echo SYSTEM_CONFIG['remoteURL']; ?>';

        function forceResultSync(sampleCode) {
            $.blockUI({
                message: "<h3><?php echo _translate("Trying to sync"); ?> " + sampleCode + "<br><?php echo _translate("Please wait", true); ?>...</h3>"
            });

            if (remoteSync && remoteUrl != null && remoteUrl != '') {
                var jqxhr = $.ajax({
                        url: "/scheduled-jobs/remote/resultsSync.php?sampleCode=" + sampleCode + "&forceSyncModule=tb",
                    })
                    .done(function(data) {
                        //console.log(data);
                        //alert( "success" );
                    })
                    .fail(function() {
                        $.unblockUI();
                    })
                    .always(function() {
                        oTable.fnDraw();
                        $.unblockUI();
                    });
            }
        }
    <?php } ?>

    function getByProvince(provinceId) {
        $("#district").html('');
        $("#facilityName").html('');
        $("#vlLab").html('');
        $.post("/common/get-by-province-id.php", {
                provinceId: provinceId,
                districts: true,
                facilities: true,
                labs: true,
            },
            function(data) {
                Obj = $.parseJSON(data);
                $("#district").html(Obj['districts']);
                $("#facilityName").html(Obj['facilities']);
                $("#vlLab").html(Obj['labs']);
            });
    }

    function getByDistrict(districtId) {
        $("#facilityName").html('');
        $("#vlLab").html('');
        $.post("/common/get-by-district-id.php", {
                districtId: districtId,
                facilities: true,
                labs: true,
            },
            function(data) {
                Obj = $.parseJSON(data);
                $("#facilityName").html(Obj['facilities']);
                $("#vlLab").html(Obj['labs']);
            });
    }
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
