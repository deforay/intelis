<?php

use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\SystemService;
use App\Services\GeoLocationsService;


$title = _translate("Sources of Requests");
require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);
$facility = $facilitiesService->getHealthFacilities();
$labNameList = $facilitiesService->getTestingLabs();

$sources = [
    'vlsm' => 'VLSM',
    'vlsts' => 'STS',
    'app' => 'Tablet',
    'api' => 'API',
    'dhis2' => 'DHIS2'
];

$activeModules = SystemService::getActiveModules();
$state = $geolocationService->getProvinces("yes");

?>
<style>
    .select2-selection__choice {
        color: black !important;
    }

    th {
        display: revert !important;
    }

    .calc {
        margin: 10px;
        font-weight: bold;
        font-size: 15px;
    }
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-circle-notch"></em>
            <?php echo _translate("Sources of Requests Report"); ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em>
                    <?php echo _translate("Home"); ?>
                </a></li>
            <li class="active">
                <?php echo _translate("Sources of Requests Report"); ?>
            </li>
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <table aria-describedby="table" class="table pageFilters" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:98%;">
                        <tr>
                            <td><strong>
                                    <?= _translate('Date Range'); ?>&nbsp;:
                                </strong></td>
                            <td>
                                <input type="text" id="dateRange" name="dateRange" class="form-control daterangefield" placeholder="<?php echo _translate('Enter date range'); ?>" style="width:220px;background:#fff;" />
                            </td>
                            <td><strong>
                                    <?= _translate('Province/State'); ?>&nbsp;:
                                </strong></td>
                            <td>
                                <select class="form-control select2-element" id="state" onchange="getByProvince()" name="state" title="<?php echo _translate('Please select Province/State'); ?>" multiple="multiple">
                                    <?= $general->generateSelectOptions($state, null, _translate("-- Select --")); ?>
                                </select>
                            </td>
                            <td><strong>
                                    <?= _translate("District/County"); ?>&nbsp;:
                                </strong>
                            </td>
                            <td>
                                <select class="form-control select2-element" id="district" name="district" title="<?php echo _translate('Please select District/County'); ?>" onchange="getByDistrict(this.value)" multiple="multiple">
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <strong>
                                    <?= _translate("Name of the Clinic"); ?>&nbsp;:
                                </strong>
                            </td>
                            <td>
                                <select class="form-control isRequired " name="facilityId" id="facilityId" title="Please choose health facility" style="width:100%;" multiple="multiple">
                                    <?= $general->generateSelectOptions($facility, null, '--Select--'); ?>
                                </select>
                            </td>
                            <td>
                                <strong>
                                    <?= _translate("Name of the Testing Lab"); ?>&nbsp;:
                                </strong>
                            </td>
                            <td>
                                <select style="width:220px;" class="form-control select2" id="labName" name="labName" title="<?php echo _translate('Please select the Lab name'); ?>" multiple="multiple">
                                    <?= $general->generateSelectOptions($labNameList, null, '--Select--'); ?>
                                </select>
                            </td>
                            <td>
                                <strong>
                                    <?= _translate("Test Type"); ?>&nbsp;:
                                </strong>
                            </td>
                            <td>
                                <select id="testType" name="testType" class="form-control" placeholder="<?php echo _translate('Please select the Test types'); ?>" onchange="getSourceRequest(this.value);">
                                    <?php if (!empty($activeModules) && in_array('vl', $activeModules)) { ?>
                                        <option value="vl">
                                            <?= _translate("Viral Load"); ?>
                                        </option>
                                    <?php }
                                    if (!empty($activeModules) && in_array('eid', $activeModules)) { ?>
                                        <option value="eid">
                                            <?= _translate("Early Infant Diagnosis"); ?>
                                        </option>
                                    <?php }
                                    if (!empty($activeModules) && in_array('covid19', $activeModules)) { ?>
                                        <option value="covid19">
                                            <?= _translate("Covid-19"); ?>
                                        </option>
                                    <?php }
                                    if (!empty($activeModules) && in_array('hepatitis', $activeModules)) { ?>
                                        <option value='hepatitis'>
                                            <?= _translate("Hepatitis"); ?>
                                        </option>
                                    <?php }
                                    if (!empty($activeModules) && in_array('tb', $activeModules)) { ?>
                                        <option value='tb'>
                                            <?= _translate("TB"); ?>
                                        </option>
                                    <?php }
                                    if (!empty($activeModules) && in_array('cd4', $activeModules)) { ?>
                                        <option value='cd4'>
                                            <?= _translate("CD4"); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </td>

                        </tr>
                        <tr>
                            <td><strong>
                                    <?php echo _translate("Source of Request"); ?>&nbsp;:
                                </strong></td>
                            <td>
                                <select class="form-control" id="originalSourceOfRequest" name="originalSourceOfRequest" title="<?php echo _translate('Please select source of request'); ?>"></select>
                            </td>
                        </tr>
                        <tr>

                            <td colspan="6"><button onclick="searchRequestData();" value="Search" class="btn btn-primary btn-sm"><span>
                                        <?php echo _translate("Search"); ?>
                                    </span></button>
                                <button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>Reset</span></button>
                            </td>
                        </tr>
                    </table>
                    <div class="box-body">

                        <table aria-describedby="table" class="table table-bordered table-striped" aria-hidden="true">
                            <tr>
                                <th><?= _translate("No. of Samples Requested"); ?></th>
                                <th><?= _translate("No. of Samples Acknowledged"); ?></th>
                                <th><?= _translate("No. of Samples Received at Testing Lab"); ?></th>
                                <th><?= _translate("No. of Samples Tested"); ?></th>
                                <th><?= _translate("No. of Results Returned"); ?></th>
                            </tr>
                            <tr>
                                <td id="totalSamplesRequested"></td>
                                <td id="totalSamplesAck"></td>
                                <td id="totalSamplesReceived"></td>
                                <td id="totalSamplesTested"></td>
                                <td id="totalSamplesTrans"></td>
                            </tr>
                        </table>

                        <a class="btn btn-success btn-sm pull-right" style="margin-right:5px;" href="javascript:void(0);" onclick="exportTestRequests();"><em class="fa-solid fa-file-excel"></em>&nbsp;&nbsp;
                            <?php echo _translate("Export"); ?>
                        </a>
                        <table aria-describedby="table" id="sampleWiseReport" class="table table-bordered table-striped" aria-hidden="true">
                            <thead>
                                <tr>
                                    <th>
                                        <?php echo _translate("LIS Sample ID"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("STS Sample ID"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("External ID"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Name of the Clinic"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Name of the Testing Lab"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Electronic Test request Date and Time"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Request Acknowledged Date Time"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Samples Received At Lab"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Sample added to Batch on"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Sample Status"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Test Result"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Result Received/Entered Date and Time"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Result Approved Date and Time"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Result Return Date and Time"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Last Modified On"); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="10" class="dataTables_empty">
                                        <?php echo _translate("Please select the date range and test type to see the source of requests"); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript">
    var oTable = null;
    $(document).ready(function() {
        getSourceRequest('vl');


        $('#labName').select2({
            placeholder: "Select Lab to filter"
        });

        $('#state').select2({
            placeholder: "Select Province"
        });

        $('#district').select2({
            width: '200px',
            placeholder: "Select District"
        });

        $('#facilityId').select2({
            width: '200px',
            placeholder: "Select Name of the Clinic"
        });

        $('#dateRange').daterangepicker({
                locale: {
                    cancelLabel: "<?= _translate("Clear", true); ?>",
                    format: 'DD-MMM-YYYY',
                    separator: ' to ',
                },
                startDate: moment().subtract(179, 'days'),
                endDate: moment(),
                maxDate: moment(),
                ranges: {
                    'Today': [moment(), moment()],
                    'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'Last 90 Days': [moment().subtract(89, 'days'), moment()],
                    'Last 120 Days': [moment().subtract(119, 'days'), moment()],
                    'Last 180 Days': [moment().subtract(179, 'days'), moment()],
                    'Last 12 Months': [moment().subtract(12, 'month').startOf('month'), moment().endOf('month')],
                    'Previous Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
                    'Current Year To Date': [moment().startOf('year'), moment()]
                }
            },
            function(start, end) {
                startDate = start.format('YYYY-MM-DD');
                endDate = end.format('YYYY-MM-DD');
            });

        //searchRequestData();
    });


    function getSourcesOfRequestReport() {

        $.blockUI();
        oTable = $('#sampleWiseReport').dataTable({
            "bJQueryUI": false,
            "bAutoWidth": false,
            "bInfo": true,
            "bScrollCollapse": true,
            //"bStateSave" : true,
            "bRetrieve": true,
            "aoColumns": [{
                    "sClass": "center"
                },
                {
                    "sClass": "center",
                },
                {
                    "sClass": "center",
                },
                {
                    "sClass": "center",
                },
                {
                    "sClass": "center",
                },
                {
                    "sClass": "center",
                },
                {
                    "sClass": "center",
                },
                {
                    "sClass": "center",
                },
                {
                    "sClass": "center"
                },
                {
                    "sClass": "center"
                },
                {
                    "sClass": "center"
                },
                {
                    "sClass": "center"
                },
                {
                    "sClass": "center"
                },
                {
                    "sClass": "center"
                },
                {
                    "sClass": "center"
                }
            ],
            "aaSorting": [14, "desc"],
            "bProcessing": true,
            "bServerSide": true,
            "sAjaxSource": "/admin/monitoring/get-samplewise-report.php",
            "fnServerData": function(sSource, aoData, fnCallback) {
                aoData.push({
                    "name": "dateRange",
                    "value": $("#dateRange").val()
                });
                aoData.push({
                    "name": "testType",
                    "value": $("#testType").val()
                });
                aoData.push({
                    "name": "labName",
                    "value": $("#labName").val()
                });
                aoData.push({
                    "name": "state",
                    "value": $("#state").val()
                });
                aoData.push({
                    "name": "district",
                    "value": $("#district").val()
                });
                aoData.push({
                    "name": "facilityId",
                    "value": $("#facilityId").val()
                });
                aoData.push({
                    "name": "originalSourceOfRequest",
                    "value": $("#originalSourceOfRequest").val()
                });
                $.ajax({
                    "dataType": 'json',
                    "type": "POST",
                    "url": sSource,
                    "data": aoData,
                    "success": function(json) {
                        $("#totalSamplesRequested").html("");
                        $("#totalSamplesAck").html("");
                        $("#totalSamplesReceived").html("");
                        $("#totalSamplesTested").html("");
                        $("#totalSamplesTrans").html("");

                        obj = json.calculation;
                        if (obj != "") {
                            $("#totalSamplesRequested").html(obj[0][0]);
                            $("#totalSamplesAck").html(obj[0][1]);
                            $("#totalSamplesReceived").html(obj[0][2]);
                            $("#totalSamplesTested").html(obj[0][3]);
                            $("#totalSamplesTrans").html(obj[0][4]);
                        }
                        fnCallback(json);
                    }
                });
            }
        });
        $.unblockUI();
    }


    function getByProvince() {
        state = $('#state').val();
        $("#district").html('');
        $("#facilityId").html('');
        $("#labName").html('');
        $.post("/common/get-by-province-id.php", {
                provinceId: state,
                districts: true,
                facilities: true,
                labs: true,
            },
            function(data) {
                Obj = $.parseJSON(data);
                $("#district").append(Obj['districts']);
                $("#facilityId").append(Obj['facilities']);
                $("#labName").append(Obj['labs']);
            });

    }

    function searchRequestData() {
        $.blockUI();
        oTable.fnDraw();
        $.unblockUI();
    }

    function exportTestRequests() {

        $.blockUI();
        $.post("/admin/monitoring/export-samplewise-reports.php", {
                reqSampleType: $('#requestSampleType').val(),
                patientInfo: $('#patientInfo').val(),
            },
            function(data) {
                $.unblockUI();
                if (data === "" || data === null || data === undefined) {
                    alert("<?= _translate("Unable to generate the excel file", true); ?>");
                } else {
                    window.open('/download.php?d=a&f=' + data, '_blank');
                }
            });
    }

    function getSourceRequest(testType) {
        $.blockUI();
        $("#originalSourceOfRequest").empty();
        $.post("/admin/monitoring/get-source-request-list.php", {
            testType
        }, function(data) {
            $("#originalSourceOfRequest").html(data).val('api');
            getSourcesOfRequestReport();
            $.unblockUI();
        });
    }
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
