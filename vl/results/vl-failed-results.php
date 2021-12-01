<?php
$title = "View All Requests";
#require_once('../../startup.php');
include_once(APPLICATION_PATH . '/header.php');


$general = new \Vlsm\Models\General();
$facilitiesDb = new \Vlsm\Models\Facilities();
$healthFacilites = $facilitiesDb->getHealthFacilities('vl');

$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, null, "-- Select --");


$sQuery = "SELECT * FROM r_vl_sample_type WHERE `status`='active'";
$sResult = $db->rawQuery($sQuery);

$batQuery = "SELECT batch_code FROM batch_details WHERE test_type = 'vl' AND batch_status='completed'";
$batResult = $db->rawQuery($batQuery);
?>
<style>
    .select2-selection__choice {
        color: black !important;
    }
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><i class="fa fa-edit"></i> Failed/Hold Samples</h1>
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
                            <td><b>Sample Type :</b></td>
                            <td>
                                <select class="form-control" id="sampleType" name="sampleType" title="Please select sample type">
                                    <option value=""> -- Select -- </option>
                                    <?php
                                    foreach ($sResult as $type) {
                                    ?>
                                        <option value="<?php echo $type['sample_id']; ?>"><?php echo ucwords($type['sample_name']); ?></option>
                                    <?php
                                    }
                                    ?>
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
                            <td><b>Req. Sample Type :</b></td>
                            <td colspan="5">
                                <select class="form-control" id="requestSampleType" name="requestSampleType" title="Please select request sample type" style="width:23%;">
                                    <option value="">All</option>
                                    <option value="result">Sample With Result</option>
                                    <option value="noresult">Sample Without Result</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2"><input type="button" onclick="searchVlRequestData();" value="Search" class="btn btn-default btn-sm">
                                &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>Reset</span></button>
                                &nbsp;<button class="btn btn-danger btn-sm" onclick="hideAdvanceSearch('advanceFilter','filter');"><span>Hide Advanced Search</span></button>
                            </td>
                            <td colspan="4">
                                &nbsp;<button class="btn btn-success btn-sm pull-right retest-btn" style="margin-right:5px;display:none;" onclick="retestSample('',true);"><span>Retest the selected samples</span></button>
                            </td>
                        </tr>
                    </table>
                    <table id="filter" class="table" cellpadding="1" cellspacing="3" style="margin-left:1%;margin-top:20px;width: 98%;margin-bottom: 0px;">
                        <tr id="">
                            <td>
                                &nbsp;<button class="btn btn-success btn-sm pull-right retest-btn" style="margin-right:5px;display:none;" onclick="retestSample('',true);"><span>Retest the selected samples</span></button>
                            </td>
                        </tr>
                    </table>
                    <span style="display: none;position:absolute;z-index: 9999 !important;color:#000;padding:5px;" id="showhide" class="">
                        <div class="row" style="background:#e0e0e0;float: right !important;padding: 15px;">
                            <div class="col-md-12">
                                <div class="col-md-3">
                                    <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="0" id="iCol0" data-showhide="sample_code" class="showhideCheckBox" /> <label for="iCol0">Sample Code</label>
                                </div>
                                <?php $i = 0;
                                if ($sarr['sc_user_type'] != 'standalone') {
                                    $i = 1; ?>
                                    <div class="col-md-3">
                                        <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i; ?>" id="iCol<?php echo $i; ?>" data-showhide="remote_sample_code" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Remote Sample Code</label>
                                    </div>
                                <?php } ?>
                                <div class="col-md-3">
                                    <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="sample_collection_date" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Sample Collection Date</label> <br>
                                </div>
                                <div class="col-md-3">
                                    <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="batch_code" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Batch Code</label>
                                </div>
                                <div class="col-md-3">
                                    <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="patient_art_no" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Art No</label> <br>
                                </div>
                                <div class="col-md-3">
                                    <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="patient_first_name" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Patient's Name</label>
                                </div>
                                <div class="col-md-3">
                                    <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="facility_name" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Facility Name</label>
                                </div>
                                <div class="col-md-3">
                                    <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="state" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Province/State</label>
                                </div>
                                <div class="col-md-3">
                                    <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="district" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">District/County</label>
                                </div>
                                <div class="col-md-3">
                                    <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="sample_name" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Sample Type</label>
                                </div>
                                <div class="col-md-3">
                                    <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="result" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Result</label>
                                </div>
                                <div class="col-md-3">
                                    <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="last_modified_datetime" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Last Modified Date</label>
                                </div>
                                <div class="col-md-3">
                                    <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="status_name" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Status</label>
                                </div>
                            </div>
                        </div>
                    </span>
                    <!-- /.box-header -->
                    <div class="box-body">
                        <input type="hidden" name="checkedTests" id="checkedTests" />
                        <table id="vlFailedResultDataTable" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="checkTestsData" onclick="toggleAllVisible()" /></th>
                                    <th>Sample Code</th>
                                    <?php if ($sarr['sc_user_type'] != 'standalone') { ?>
                                        <th>Remote Sample <br />Code</th>
                                    <?php } ?>
                                    <th>Sample Collection<br /> Date</th>
                                    <th>Batch Code</th>
                                    <th>Unique ART No</th>
                                    <th>Patient's Name</th>
                                    <th>Facility Name</th>
                                    <th>Province/State</th>
                                    <th>District/County</th>
                                    <th>Sample Type</th>
                                    <th>Result</th>
                                    <th>Last Modified Date</th>
                                    <th>Status</th>
                                    <?php if (isset($_SESSION['privileges']) && (in_array("editVlRequest.php", $_SESSION['privileges']))) { ?>
                                        <th>Action</th>
                                    <?php } ?>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="16" class="dataTables_empty">Loading data from server</td>
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
        var i = '<?php echo $i; ?>';
        for (colNo = 0; colNo <= i; colNo++) {
            $("#iCol" + colNo).attr("checked", oTable.fnSettings().aoColumns[parseInt(colNo)].bVisible);
            if (oTable.fnSettings().aoColumns[colNo].bVisible) {
                $("#iCol" + colNo + "-sort").show();
            } else {
                $("#iCol" + colNo + "-sort").hide();
            }
        }
    });

    function resetBtnShowHide() {
        var checkResult = false;
        $(".checkTests").each(function() {
            if ($(this).prop('checked')) {
                checkResult = true;
            }
        });
        if (checkResult) {
            $(".retest-btn").show();
        } else {
            $(".retest-btn").hide();
        }
    }

    function fnShowHide(iCol) {
        var bVis = oTable.fnSettings().aoColumns[iCol].bVisible;
        oTable.fnSetColumnVis(iCol, bVis ? false : true);
    }

    function loadVlRequestData() {
        $.blockUI();

        oTable = $('#vlFailedResultDataTable').dataTable({
            "oLanguage": {
                "sLengthMenu": "_MENU_ records per page"
            },
            "bJQueryUI": false,
            "bAutoWidth": false,
            "bInfo": true,
            "bScrollCollapse": true,
            "bStateSave": true,
            "bRetrieve": true,
            "aoColumns": [{
                    "sClass": "center",
                    "bSortable": false
                }, {
                    "sClass": "center"
                },
                <?php if ($sarr['sc_user_type'] != 'standalone') { ?> {
                        "sClass": "center"
                    },
                <?php } ?> {
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
                <?php if (isset($_SESSION['privileges']) && (in_array("editVlRequest.php", $_SESSION['privileges']))) { ?> {
                        "sClass": "center",
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
            "sAjaxSource": "getVlFailedResultsDetails.php",
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

    function toggleTest(obj) {
        if ($(obj).is(':checked')) {
            if ($.inArray(obj.value, selectedTests) == -1) {
                selectedTests.push(obj.value);
                selectedTestsId.push(obj.id);
            }
        } else {
            selectedTests.splice($.inArray(obj.value, selectedTests), 1);
            selectedTestsId.splice($.inArray(obj.id, selectedTestsId), 1);
            $("#checkTestsData").attr("checked", false);
        }
        $("#checkedTests").val(selectedTests.join());
        if (selectedTests.length != 0) {
            $("#status").prop('disabled', false);
        } else {
            $("#status").prop('disabled', true);
        }
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
        resetBtnShowHide();
    }

    function exportAllPendingVlRequest() {
        $.blockUI();
        var requestSampleType = $('#requestSampleType').val();
        $.post("generatePendingVlRequestExcel.php", {
                reqSampleType: requestSampleType
            },
            function(data) {
                $.unblockUI();
                if (data === "" || data === null || data === undefined) {
                    alert('Unable to generate the excel file');
                } else {
                    location.href = '/temporary/' + data;
                }
            });
    }

    function hideAdvanceSearch(hideId, showId) {
        $("#" + hideId).hide();
        $("#" + showId).show();
    }

    function retestSample(id, bulk = false) {
        if (bulk) {
            id = selectedTests;
        }
        if (id != "") {
            $.blockUI();
            $.post("failed-results-retest.php", {
                    vlId: id,
                    bulkIds: bulk
                },
                function(data) {
                    $.unblockUI();
                    if (data > 0) {
                        alert("Retest has been submitted.");
                        oTable.fnDraw();
                    } else {
                        alert("Something went wrong. Please try again later");
                    }
                });
        }
    }
</script>
<?php
include(APPLICATION_PATH . '/footer.php');
?>