<?php

use App\Services\TestsService;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


$title = _translate("Test Results Metadata");
require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$activeTests = TestsService::getActiveTests();

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
        <h1><em class="fa-solid fa-list"></em>
            <?php echo _translate("Test Results Metadata Report"); ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em>
                    <?php echo _translate("Home"); ?>
                </a></li>
            <li class="active">
                <?php echo _translate("Test Results Metadata Report"); ?>
            </li>
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:98%;">
                        <tr>
                            <td><strong>
                                    <?php echo _translate("Test Type"); ?>&nbsp;:
                                </strong>
                            </td>
                            <td>
                                <select id="testType" name="testType" class="form-control" placeholder="<?php echo _translate('Please select the Test types'); ?>">
                                    <?php if ($activeTests !== [] && in_array('vl', $activeTests)) { ?>
                                        <option value="vl">
                                            <?php echo _translate("Viral Load"); ?>
                                        </option>
                                    <?php }
                                    if ($activeTests !== [] && in_array('eid', $activeTests)) { ?>
                                        <option value="eid">
                                            <?php echo _translate("Early Infant Diagnosis"); ?>
                                        </option>
                                    <?php }
                                    if ($activeTests !== [] && in_array('covid19', $activeTests)) { ?>
                                        <option value="covid19">
                                            <?php echo _translate("Covid-19"); ?>
                                        </option>
                                    <?php }
                                    if ($activeTests !== [] && in_array('hepatitis', $activeTests)) { ?>
                                        <option value='hepatitis'>
                                            <?php echo _translate("Hepatitis"); ?>
                                        </option>
                                    <?php }
                                    if ($activeTests !== [] && in_array('tb', $activeTests)) { ?>
                                        <option value='tb'>
                                            <?php echo _translate("TB"); ?>
                                        </option>
                                    <?php }
                                    if ($activeTests !== [] && in_array('cd4', $activeTests)) { ?>
                                        <option value='cd4'>
                                            <?php echo _translate("CD4"); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </td>
                            <td><strong>
                                    <?php echo _translate("Sample Test Date"); ?>&nbsp;:
                                </strong></td>
                            <td>
                                <input type="text" id="sampleTestDate" name="sampleTestDate" class="form-control" placeholder="<?php echo _translate('Select Sample Test Date'); ?>" readonly style="background:#fff;" />
                            </td>
                            <td><strong><?php echo _translate("Sample ID/Batch Code"); ?>&nbsp;:</strong></td>
                            <td>
                                <input type="text" id="sampleBatchCode" name="sampleBatchCode" class="form-control autocomplete" placeholder="<?php echo _translate('Enter Batch Code'); ?>" style="background:#fff;" />
                            </td>
                        </tr>

                        <tr>

                            <td><button onclick="searchData();" value="Search" class="btn btn-primary btn-sm"><span>
                                        <?php echo _translate("Search"); ?>
                                    </span></button>
                                <button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>Reset</span></button>
                            </td>
                        </tr>
                    </table>
                    <!-- /.box-header -->
                    <div class="box-body">

                        <a class="btn btn-success btn-sm pull-right" style="margin-right:5px;" href="javascript:void(0);" onclick="exportTestRequests();"><em class="fa-solid fa-file-excel"></em>&nbsp;&nbsp;
                            <?php echo _translate("Export To Excel"); ?>
                        </a>
                        <table aria-describedby="table" id="testResultReport" class="table table-bordered table-striped" aria-hidden="true">
                            <thead>
                                <tr>
                                    <th>
                                        <?php echo _translate("Sample ID"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Remote Sample ID"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Sample Collection Date"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Sample Recieved On"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Sample Tested On"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Result"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Tested By"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Test Platform/Instrument"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Result Status"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Manual Result Entry"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Is Sample Rejected"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Rejection Reason"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Was Result Changed?"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Reason for Changing"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Last Modified On"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("File Link"); ?>
                                    </th>

                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="16" class="dataTables_empty">
                                        <?php echo _translate("Please select Sample Test Date or Sample ID/Batch Code to get result meta data"); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
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
<script type="text/javascript">
    var oTable = null;

    $(document).ready(function() {


        $('#sampleTestDate').daterangepicker({
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
        $('#sampleTestDate').val("");
        if ($("#sampleTestDate").val() != "" || $("#sampleBatchCode").val() != "") {
            getMetaResultDataReport();
        }
    });


    function getMetaResultDataReport() {

        $.blockUI();
        oTable = $('#testResultReport').dataTable({
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
                }, {
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
            "sAjaxSource": "/admin/monitoring/get-test-results-report.php",
            "fnServerData": function(sSource, aoData, fnCallback) {
                aoData.push({
                    "name": "sampleTestDate",
                    "value": $("#sampleTestDate").val()
                });
                aoData.push({
                    "name": "testType",
                    "value": $("#testType").val()
                });
                aoData.push({
                    "name": "sampleBatchCode",
                    "value": $("#sampleBatchCode").val()
                });
                $.ajax({
                    "dataType": 'json',
                    "type": "POST",
                    "url": sSource,
                    "data": aoData,
                    "success": function(json) {
                        if (json != "") {
                            fnCallback(json);
                        }
                    }
                });
            }
        });
        $.unblockUI();
    }


    function searchRequestData() {

        $.blockUI();
        oTable.fnDraw();
        $.unblockUI();

    }

    function searchData() {
        if ($("#sampleTestDate").val() == "" && $("#sampleBatchCode").val() == "") {
            document.location.href = document.location;
        } else {
            getMetaResultDataReport();
            searchRequestData();
        }
    }

    function exportTestRequests() {

        $.blockUI();
        $.post("/admin/monitoring/export-test-results-report.php", {
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
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
