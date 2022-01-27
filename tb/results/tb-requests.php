<?php
$title = "TB | View All Requests";
#require_once('../../startup.php');


// echo "<pre>";
// var_dump($_SESSION['privileges']);die;

include_once(APPLICATION_PATH . '/header.php');

$general = new \Vlsm\Models\General();
$facilitiesDb = new \Vlsm\Models\Facilities();
$usersModel = new \Vlsm\Models\Users();
$healthFacilites = $facilitiesDb->getHealthFacilities('tb');
/* Global config data */
$arr = $general->getGlobalConfig();

$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, null, "-- Select --");
$formId = $general->getGlobalConfig('vl_form');

$batQuery = "SELECT batch_code FROM batch_details WHERE test_type ='tb' AND batch_status='completed'";
$batResult = $db->rawQuery($batQuery);
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
        <h1><i class="fa fa-edit"></i> TB Test Requests</h1>
        <ol class="breadcrumb">
            <li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>
            <li class="active">Test Request</li>
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <table id="advanceFilter" class="table" cellpadding="1" cellspacing="3" style="margin-left:1%;margin-top:20px;width: 98%;margin-bottom: 0px;display: none;">
                        <tr>
                            <td><b>Sample Collection Date :</b></td>
                            <td>
                                <input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control" placeholder="Select Collection Date" readonly style="background:#fff;" />
                            </td>
                            <td><b>Batch Code :</b></td>
                            <td>
                                <select class="form-control" id="batchCode" name="batchCode" title="Please select batch code">
                                    <option value=""> -- Select -- </option>
                                    <?php
                                    foreach ($batResult as $code) {
                                    ?>
                                        <option value="<?php echo $code['batch_code']; ?>"><?php echo $code['batch_code']; ?></option>
                                    <?php
                                    }
                                    ?>
                                </select>
                            </td>
                            <td><b>Req. Sample Type :</b></td>
                            <td>
                                <select class="form-control" id="requestSampleType" name="requestSampleType" title="Please select request sample type">
                                    <option value="">All</option>
                                    <option value="result">Sample With Result</option>
                                    <option value="noresult">Sample Without Result</option>
                                </select>
                            </td>

                        </tr>
                        <tr>
                            <td><b>Facility Name :</b></td>
                            <td>
                                <select class="form-control" id="facilityName" name="facilityName" multiple="multiple" title="Please select facility name" style="width:100%;">
                                    <?= $facilitiesDropdown; ?>
                                </select>
                            </td>
                            <td><b>Province/State&nbsp;:</b></td>
                            <td>
                                <input type="text" id="state" name="state" class="form-control" placeholder="Enter Province/State" style="background:#fff;" onkeyup="loadVlRequestStateDistrict()" />
                            </td>
                            <td><b>District/County :</b></td>
                            <td>
                                <input type="text" id="district" name="district" class="form-control" placeholder="Enter District/County" onkeyup="loadVlRequestStateDistrict()" />
                            </td>
                        </tr>
                        <tr>

                        </tr>
                        <tr>
                            <td colspan="2"><input type="button" onclick="searchVlRequestData();" value="Search" class="btn btn-default btn-sm">
                                &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>Reset</span></button>
                                &nbsp;<button class="btn btn-danger btn-sm" onclick="hideAdvanceSearch('advanceFilter','filter');"><span>Hide Advanced Search</span></button>
                            </td>
                            <td colspan="4">
                                <?php if (isset($_SESSION['privileges']) && in_array("tb-add-request.php", $_SESSION['privileges'])) { ?>
                                    <a style=" margin: 0px 5px; " href="/tb/requests/tb-add-request.php" class="btn btn-primary btn-sm pull-right"> <i class="fa fa-plus"></i> Add new TB Request</a>
                                <?php } ?>
                            </td>
                        </tr>
                    </table>
                    <table id="filter" class="table" cellpadding="1" cellspacing="3" style="margin-left:1%;margin-top:20px;width: 98%;margin-bottom: 0px;">
                        <tr id="">
                            <td>

                                <?php
                                if (isset($_SESSION['privileges']) && in_array("tb-add-request.php", $_SESSION['privileges'])) { ?>
                                    <a style=" margin: 0px 5px; " href="/tb/requests/tb-add-request.php" class="btn btn-primary btn-sm pull-right"> <i class="fa fa-plus"></i> Add new TB Request</a>
                                <?php } ?>
                                <button style=" margin: 0px 5px; " class="btn btn-primary btn-sm pull-right" style="margin-right:5px;" onclick="hideAdvanceSearch('filter','advanceFilter');"><span>Show Advanced Search</span></button>
                            </td>
                        </tr>
                    </table>

                    <!-- /.box-header -->
                    <div class="box-body">
                        <table id="vlRequestDataTable" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <!--<th><input type="checkbox" id="checkTestsData" onclick="toggleAllVisible()"/></th>-->
                                    <th>Sample Code</th>
                                    <?php if ($sarr['sc_user_type'] != 'standalone') { ?>
                                        <th>Remote Sample <br />Code</th>
                                    <?php } ?>
                                    <th>Sample Collection<br /> Date</th>
                                    <th>Batch Code</th>
                                    <th>Facility Name</th>
                                    <?php if ($formId == 1) { ?>
                                        <th>Case ID</th>
                                    <?php } else { ?>
                                        <th>Patient ID</th>
                                    <?php } ?>
                                    <th>Patient Name</th>
                                    <th>Province/State</th>
                                    <th>District/County</th>
                                    <th>Result</th>
                                    <th>Last Modified On</th>
                                    <th>Status</th>
                                    <?php if (isset($_SESSION['privileges']) && (in_array("tb-edit-request.php", $_SESSION['privileges'])) || (in_array("tb-view-request.php", $_SESSION['privileges']))) { ?>
                                        <th>Action</th>
                                    <?php } ?>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="15" class="dataTables_empty">Loading data from server</td>
                                </tr>
                            </tbody>
                        </table>
                        <?php
                        if (isset($global['bar_code_printing']) && $global['bar_code_printing'] == 'zebra-printer') {
                        ?>

                            <div id="printer_data_loading" style="display:none"><span id="loading_message">Loading Printer Details...</span><br />
                                <div class="progress" style="width:100%">
                                    <div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%">
                                    </div>
                                </div>
                            </div> <!-- /printer_data_loading -->
                            <div id="printer_details" style="display:none">
                                <span id="selected_printer">No printer selected!</span>
                                <button type="button" class="btn btn-success" onclick="changePrinter()">Change/Retry</button>
                            </div><br /> <!-- /printer_details -->
                            <div id="printer_select" style="display:none">
                                Zebra Printer Options<br />
                                Printer: <select id="printers"></select>
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
<script type="text/javascript" src="/assets/plugins/daterangepicker/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>

<?php
if (isset($global['bar_code_printing']) && $global['bar_code_printing'] != "off") {
    if ($global['bar_code_printing'] == 'dymo-labelwriter-450') {
?>
        <script src="/assets/js/DYMO.Label.Framework.js"></script>
        <script src="/configs/dymo-format.js"></script>
        <script src="/assets/js/dymo-print.js"></script>
    <?php
    } else if ($global['bar_code_printing'] == 'zebra-printer') {
    ?>
        <script src="/assets/js/zebra-browserprint.js.js"></script>
        <script src="/configs/zebra-format.js"></script>
        <script src="/assets/js/zebra-print.js"></script>
<?php
    }
}
?>



<script type="text/javascript">
    var startDate = "";
    var endDate = "";
    var selectedTests = [];
    var selectedTestsId = [];
    var oTable = null;
    $(document).ready(function() {
        <?php
        if (isset($_GET['barcode']) && $_GET['barcode'] == 'true') {
            echo "printBarcodeLabel('" . $_GET['s'] . "','" . $_GET['f'] . "');";
        }
        ?>
        $("#facilityName").select2({
            placeholder: "Select Facilities"
        });
        loadVlRequestData();
        $('#sampleCollectionDate').daterangepicker({
                locale: {
                    cancelLabel: 'Clear'
                },
                format: 'DD-MMM-YYYY',
                separator: ' to ',
                startDate: moment().subtract(29, 'days'),
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
        $('#sampleCollectionDate').val("");

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
                <?php if ($sarr['sc_user_type'] != 'standalone') { ?> {
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
                },
                <?php if (isset($_SESSION['privileges']) && (in_array("tb-edit-request.php", $_SESSION['privileges'])) || (in_array("tb-view-request.php", $_SESSION['privileges']))) { ?> {
                        "sClass": "center action",
                        "bSortable": false
                    },
                <?php } ?>
            ],
            "aaSorting": [
                [<?php echo ($sarr['sc_user_type'] == 'remoteuser' || $sarr['sc_user_type'] == 'vluser') ? 10 : 9 ?>, "desc"]
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


    function hideAdvanceSearch(hideId, showId) {
        $("#" + hideId).hide();
        $("#" + showId).show();
    }

    <?php if (isset($_SESSION['instanceType']) && $_SESSION['instanceType'] == 'vluser') { ?>
        var remoteUrl = '<?php echo $systemConfig['remoteURL']; ?>';

        function forceResultSync(sampleCode) {
            $.blockUI({
                message: '<h3>Trying to sync ' + sampleCode + '<br>Please wait...</h3>'
            });

            if (remoteSync && remoteUrl != null && remoteUrl != '') {
                var jqxhr = $.ajax({
                        url: "/remote/scheduled-jobs/syncResults.php?sampleCode=" + sampleCode + "&forceSyncModule=tb",
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
</script>
<?php
include(APPLICATION_PATH . '/footer.php');
?>