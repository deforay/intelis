<?php
$title = _("Sources of Requests");
require_once(APPLICATION_PATH . '/header.php');

$general = new \Vlsm\Models\General();
$facilityDb = new \Vlsm\Models\Facilities();
$labNameList = $facilityDb->getTestingLabs();

$sources = array(
    'vlsm' => 'VLSM',
    'vlsts' => 'VLSTS',
    'app' => 'Tablet',
    'api' => 'API',
    'dhis2' => 'DHIS2'
);

?>
<style>
    .select2-selection__choice {
        color: black !important;
    }

    th {
        display: revert !important;
    }
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><i class="fa-solid fa-pen-to-square"></i> <?php echo _("Sources of Requests Report"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><i class="fa-solid fa-chart-pie"></i> <?php echo _("Home"); ?></a></li>
            <li class="active"><?php echo _("Sources of Requests Report"); ?></li>
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <table class="table" cellpadding="1" cellspacing="3" style="margin-left:1%;margin-top:20px;width:98%;">
                        <tr>
                            <td><b><?php echo _("Date Range"); ?>&nbsp;:</b></td>
                            <td>
                                <input type="text" id="dateRange" name="dateRange" class="form-control daterangefield" placeholder="<?php echo _('Enter date range'); ?>" style="width:220px;background:#fff;" />
                            </td>
                            <td><b><?php echo _("Test Types"); ?>&nbsp;:</b></td>
                            <td>
                                <select type="text" id="testType" name="testType" class="form-control" placeholder="<?php echo _('Please select the Test types'); ?>">
                                    <?php if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] == true) { ?>
                                        <option value="vl"><?php echo _("Viral Load"); ?></option>
                                    <?php }
                                    if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] == true) { ?>
                                        <option value="eid"><?php echo _("Early Infant Diagnosis"); ?></option>
                                    <?php }
                                    if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] == true) { ?>
                                        <option value="covid19"><?php echo _("Covid-19"); ?></option>
                                    <?php }
                                    if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis'] == true) { ?>
                                        <option value='hepatitis'><?php echo _("Hepatitis"); ?></option>
                                    <?php }
                                    if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] == true) { ?>
                                        <option value='tb'><?php echo _("TB"); ?></option>
                                    <?php } ?>
                                </select>
                            </td>
                            <td><b><?php echo _("Lab Name"); ?>&nbsp;:</b></td>
                            <td>
                                <select style="width:220px;" class="form-control select2" id="labName" name="labName" title="<?php echo _('Please select the Lab name'); ?>">
                                    <?php echo $general->generateSelectOptions($labNameList, null, '--Select--'); ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td><b><?php echo _("Source of Request"); ?>&nbsp;:</b></td>
                            <td>
                                <select style="width:220px;" class="form-control" id="srcRequest" name="srcRequest" title="<?php echo _('Source of Requests'); ?>">
                                    <?php echo $general->generateSelectOptions(array('api' => 'api', 'app' => 'app', 'web' => 'web', 'hl7' => 'hl7'), null, '--All--'); ?>
                                </select>
                            </td>
                            <td><button onclick="oTable.fnDraw();" value="Search" class="btn btn-primary btn-sm"><span><?php echo _("Search"); ?></span></button></td>
                        </tr>
                    </table>
                    <!-- /.box-header -->
                    <div class="box-body">
                        <table id="sampleReportsDataTable" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th><?php echo _("Lab Name"); ?></th>
                                    <th><?php echo _("Test Type"); ?></th>
                                    <th><?php echo _("No. of Samples Collected"); ?></th>
                                    <th><?php echo _("No. of Samples with Test Result"); ?></th>
                                    <th><?php echo _("No. of Samples Rejected"); ?></th>
                                    <th><?php echo _("Source of Request"); ?></th>
                                    <th><?php echo _("Last Request Created On"); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="7" class="dataTables_empty"><?php echo _("Please select the date range and test type to see the source of requests"); ?></td>
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
<script type="text/javascript" src="/assets/plugins/daterangepicker/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript">
    var oTable = null;
    $(document).ready(function() {
        $('#labName').select2({
            placeholder: "Select Lab to filter"
        });

        getSourcesOfRequestReport();
        getSrcList();

        $('#dateRange').daterangepicker({
                locale: {
                    cancelLabel: 'Clear'
                },
                format: 'DD-MMM-YYYY',
                separator: ' to ',
                startDate: moment().subtract(12, 'months'),
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
                    'Last 12 Months': [moment().subtract(12, 'month').startOf('month'), moment().endOf('month')]
                }
            },
            function(start, end) {
                startDate = start.format('YYYY-MM-DD');
                endDate = end.format('YYYY-MM-DD');
            });

        $("#testType").change(function() {
            getSrcList();
        });
    });

    // function getSourcesOfRequestReport() {
    //     if ($("#dateRange").val() == "" || $("#testType").val() == "") {
    //         alert("Please select the date range and test type to see the source of requests");
    //         return false;
    //     } else {
    //         oTable.fnDraw();
    //     }
    // }

    function getSourcesOfRequestReport() {

        $.blockUI();
        oTable = $('#sampleReportsDataTable').dataTable({
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
            }],
            "aaSorting": [2, "desc"],
            "bProcessing": true,
            "bServerSide": true,
            "sAjaxSource": "/admin/monitoring/get-sources-of-requests.php",
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
                    "name": "srcRequest",
                    "value": $("#srcRequest").val()
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

    function getSrcList() {
        $.post("/admin/monitoring/get-src-of-requests-list.php", {
                testType: $("#testType").val(),
                format: "html"
            },
            function(data) {
                if (data != '') {
                    $("#srcRequest").html(data);
                }
            });
    }
</script>
<?php
require_once(APPLICATION_PATH . '/footer.php');
?>