<?php
$title = "Print EID Results";
#require_once('../../startup.php');
include_once(APPLICATION_PATH . '/header.php');


$general = new \Vlsm\Models\General();
$facilitiesDb = new \Vlsm\Models\Facilities();
$healthFacilites = $facilitiesDb->getHealthFacilities('eid');

$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, null, "-- Select --");


// $tsQuery = "SELECT * FROM r_sample_status";
// $tsResult = $db->rawQuery($tsQuery);


$batQuery = "SELECT batch_code FROM batch_details WHERE test_type = 'eid' AND batch_status='completed'";
$batResult = $db->rawQuery($batQuery);
// $fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
// $fundingSourceList = $db->query($fundingSourceQry);
// //Implementing partner list
// $implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
// $implementingPartnerList = $db->query($implementingPartnerQry);
?>
<style>
    .select2-selection__choice {
        color: #000000 !important;
    }

    .center {
        /*text-align:left;*/
    }
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><i class="fa fa-edit"></i> Print EID Results</h1>
        <ol class="breadcrumb">
            <li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>
            <li class="active">Print EID Results</li>
        </ol>
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <!-- /.box-header -->
                    <div class="box-body">
                        <div class="widget">
                            <div class="widget-content">
                                <div class="bs-example bs-example-tabs">
                                    <ul id="myTab" class="nav nav-tabs" style="font-size:1.4em;">
                                        <li class="active"><a href="#notPrintedData" data-toggle="tab">Results not yet Printed </a></li>
                                        <li><a href="#printedData" data-toggle="tab">Results already Printed </a></li>
                                    </ul>
                                    <div id="myTabContent" class="tab-content">
                                        <div class="tab-pane fade in active" id="notPrintedData">
                                            <table class="table" cellpadding="1" cellspacing="3" style="margin-left:1%;margin-top:20px;width:98%;">
                                                <tr>
                                                    <td><b>Sample Collection Date&nbsp;:</b></td>
                                                    <td>
                                                        <input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control" placeholder="Select Collection Date" readonly style="width:220px;background:#fff;" />
                                                    </td>
                                                    <td><b>Batch Code&nbsp;:</b></td>
                                                    <td>
                                                        <select class="form-control" id="batchCode" name="batchCode" title="Please select batch code" style="width:220px;">
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

                                                    <td><b>Sample Test Date&nbsp;:</b></td>
                                                    <td>
                                                        <input type="text" id="sampleTestDate" name="sampleTestDate" class="form-control" placeholder="Select Sample Test Date" readonly style="width:220px;background:#fff;" />
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><b>Facility Name :</b></td>
                                                    <td>
                                                        <select class="form-control" id="facility" name="facility" title="Please select facility name" multiple="multiple" style="width:220px;">
                                                            <?= $facilitiesDropdown; ?>
                                                        </select>
                                                    </td>
                                                    <td></td>
                                                    <td></td>
                                                    <td></td>
                                                    <td></td>
                                                </tr>

                                                <tr>
                                                    <td colspan="6">&nbsp;<input type="button" onclick="searchVlRequestData();" value="Search" class="btn btn-success btn-sm">
                                                        &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>Reset</span></button>
                                                        &nbsp;<button class="btn btn-default btn-sm" onclick="convertSearchResultToPdf('');"><span>Result PDF</span></button>
                                                        &nbsp;<button class="btn btn-primary btn-sm" onclick="$('#showhide').fadeToggle();return false;"><span>Manage Columns</span></button>
                                                    </td>
                                                </tr>

                                            </table>
                                            <span style="display: none;position:absolute;z-index: 9999 !important;color:#000;padding:5px;" id="showhide" class="">
                                                <div class="row" style="background:#e0e0e0;float: right !important;padding: 15px;margin-top: -30px;">
                                                    <div class="col-md-12">
                                                        <div class="col-md-3">
                                                            <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="1" id="iCol1" data-showhide="sample_code" class="showhideCheckBox" /> <label for="iCol1">Sample Code</label>
                                                        </div>
                                                        <?php $i = 1;
                                                        if ($sarr['sc_user_type'] != 'standalone') {
                                                            $i = 2; ?>
                                                            <div class="col-md-3">
                                                                <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i; ?>" id="iCol<?php echo $i; ?>" data-showhide="remote_sample_code" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Remote Sample Code</label>
                                                            </div>
                                                        <?php } ?>
                                                        <div class="col-md-3">
                                                            <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="batch_code" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Batch Code</label>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="patient_art_no" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Art No</label>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="patient_first_name" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Patient's Name</label> <br>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="facility_name" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Facility Name</label>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="sample_name" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Sample Type</label> <br>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="result" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Result</label>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="last_modified_datetime" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Last Modified On</label>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="status_name" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Status</label>
                                                        </div>

                                                    </div>
                                                </div>
                                            </span>

                                            <table id="vlRequestDataTable" class="table table-bordered table-striped">
                                                <thead>
                                                    <tr>
                                                        <th><input type="checkbox" id="checkRowsData" onclick="toggleAllVisible()" /></th>
                                                        <th>Sample Code</th>
                                                        <?php if ($sarr['sc_user_type'] != 'standalone') { ?>
                                                            <th>Remote Sample <br />Code</th>
                                                        <?php } ?>
                                                        <th>Batch Code</th>
                                                        <th>Child's ID</th>
                                                        <th>Child's Name</th>
                                                        <th>Facility Name</th>
                                                        <th>Result</th>
                                                        <th>Last Modified On</th>
                                                        <th>Status</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td colspan="10" class="dataTables_empty">Loading data from server</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                            <input type="hidden" name="checkedRows" id="checkedRows" />
                                            <input type="hidden" name="totalSamplesList" id="totalSamplesList" />
                                        </div>
                                        <div class="tab-pane fade" id="printedData">
                                            <table class="table" cellpadding="1" cellspacing="3" style="margin-left:1%;margin-top:20px;width:98%;">
                                                <tr>
                                                    <td><b>Sample Collection Date&nbsp;:</b></td>
                                                    <td>
                                                        <input type="text" id="printSampleCollectionDate" name="sampleCollectionDate" class="form-control" placeholder="Select Collection Date" readonly style="width:220px;background:#fff;" />
                                                    </td>
                                                    <td><b>Batch Code&nbsp;:</b></td>
                                                    <td>
                                                        <select class="form-control" id="printBatchCode" name="batchCode" title="Please select batch code" style="width:220px;">
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

                                                    <td><b>Sample Test Date&nbsp;:</b></td>
                                                    <td>
                                                        <input type="text" id="printSampleTestDate" name="sampleTestDate" class="form-control" placeholder="Select Sample Test Date" readonly style="width:220px;background:#fff;" />
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><b>Facility Name :</b></td>
                                                    <td>
                                                        <select class="form-control" id="printFacility" name="facility" title="Please select facility name" multiple="multiple" style="width:220px;">
                                                            <?= $facilitiesDropdown; ?>
                                                        </select>
                                                    </td>
                                                    <td></td>
                                                    <td></td>
                                                    <td></td>
                                                    <td></td>
                                                </tr>

                                                <tr>
                                                    <td colspan="6">&nbsp;<input type="button" onclick="searchPrintedVlRequestData();" value="Search" class="btn btn-success btn-sm">
                                                        &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>Reset</span></button>
                                                        &nbsp;<button class="btn btn-default btn-sm" onclick="convertSearchResultToPdf('','printData');"><span>Result PDF</span></button>
                                                        &nbsp;<button class="btn btn-primary btn-sm" onclick="$('#printShowhide').fadeToggle();return false;"><span>Manage Columns</span></button>
                                                    </td>
                                                </tr>

                                            </table>
                                            <span style="display: none;position:absolute;z-index: 9999 !important;color:#000;padding:5px;" id="printShowhide" class="">
                                                <div class="row" style="background:#e0e0e0;float: right !important;padding: 15px;margin-top: -30px;">
                                                    <div class="col-md-12">
                                                        <div class="col-md-3">
                                                            <input type="checkbox" onclick="javascript:printfnShowHide(this.value);" value="1" id="printiCol1" data-showhide="sample_code" class="printShowhideCheckBox" /> <label for="printiCol1">Sample Code</label>
                                                        </div>
                                                        <?php $i = 1;
                                                        if ($sarr['sc_user_type'] != 'standalone') {
                                                            $i = 2; ?>
                                                            <div class="col-md-3">
                                                                <input type="checkbox" onclick="javascript:printfnShowHide(this.value);" value="<?php echo $i; ?>" id="printiCol<?php echo $i; ?>" data-showhide="remote_sample_code" class="printShowhideCheckBox" /> <label for="printiCol<?php echo $i; ?>">Remote Sample Code</label>
                                                            </div>
                                                        <?php } ?>
                                                        <div class="col-md-3">
                                                            <input type="checkbox" onclick="javascript:printfnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="printiCol<?php echo $i; ?>" data-showhide="batch_code" class="printShowhideCheckBox" /> <label for="printiCol<?php echo $i; ?>">Batch Code</label>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <input type="checkbox" onclick="javascript:printfnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="printiCol<?php echo $i; ?>" data-showhide="patient_art_no" class="printShowhideCheckBox" /> <label for="printiCol<?php echo $i; ?>">Art No</label>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <input type="checkbox" onclick="javascript:printfnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="printiCol<?php echo $i; ?>" data-showhide="patient_first_name" class="printShowhideCheckBox" /> <label for="printiCol<?php echo $i; ?>">Patient's Name</label> <br>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <input type="checkbox" onclick="javascript:printfnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="printiCol<?php echo $i; ?>" data-showhide="facility_name" class="printShowhideCheckBox" /> <label for="printiCol<?php echo $i; ?>">Facility Name</label>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <input type="checkbox" onclick="javascript:printfnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="printiCol<?php echo $i; ?>" data-showhide="sample_name" class="printShowhideCheckBox" /> <label for="printiCol<?php echo $i; ?>">Sample Type</label> <br>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <input type="checkbox" onclick="javascript:printfnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="printiCol<?php echo $i; ?>" data-showhide="result" class="printShowhideCheckBox" /> <label for="printiCol<?php echo $i; ?>">Result</label>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <input type="checkbox" onclick="javascript:printfnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="printiCol<?php echo $i; ?>" data-showhide="last_modified_datetime" class="printShowhideCheckBox" /> <label for="printiCol<?php echo $i; ?>">Last Modified On</label>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <input type="checkbox" onclick="javascript:printfnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="printiCol<?php echo $i; ?>" data-showhide="status_name" class="printShowhideCheckBox" /> <label for="printiCol<?php echo $i; ?>">Status</label>
                                                        </div>

                                                    </div>
                                                </div>
                                            </span>
                                            <table id="printedVlRequestDataTable" class="table table-bordered table-striped">
                                                <thead>
                                                    <tr>
                                                        <th><input type="checkbox" id="checkPrintedRowsData" onclick="toggleAllPrintedVisible()" /></th>
                                                        <th>Sample Code</th>
                                                        <?php if ($sarr['sc_user_type'] != 'standalone') { ?>
                                                            <th>Remote Sample <br />Code</th>
                                                        <?php } ?>
                                                        <th>Batch Code</th>
                                                        <th>Child's ID</th>
                                                        <th>Child's Name</th>
                                                        <th>Facility Name</th>
                                                        <th>Result</th>
                                                        <th>Last Modified On</th>
                                                        <th>Status</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td colspan="10" class="dataTables_empty">Loading data from server</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                            <input type="hidden" name="checkedPrintedRows" id="checkedPrintedRows" />
                                            <input type="hidden" name="totalSamplesPrintedList" id="totalSamplesPrintedList" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div><!-- /.box-body -->
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
    var startDate = "";
    var endDate = "";
    var selectedRows = [];
    var selectedRowsId = [];
    var selectedPrintedRows = [];
    var selectedPrintedRowsId = [];
    var oTable = null;
    var opTable = null;
    $(document).ready(function() {
        $("#facility,#printFacility").select2({
            placeholder: "Select Facilities"
        });
        $('#sampleCollectionDate,#sampleTestDate,#printSampleCollectionDate,#printSampleTestDate').daterangepicker({
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
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                }
            },
            function(start, end) {
                startDate = start.format('YYYY-MM-DD');
                endDate = end.format('YYYY-MM-DD');
            });
        $('#sampleCollectionDate,#sampleTestDate,#printSampleCollectionDate,#printSampleTestDate').val("");
        loadVlRequestData();
        loadPrintedVlRequestData();
        $(".showhideCheckBox").change(function() {
            if ($(this).attr('checked')) {
                idpart = $(this).attr('data-showhide');
                $("#" + idpart + "-sort").show();
            } else {
                idpart = $(this).attr('data-showhide');
                $("#" + idpart + "-sort").hide();
            }
        });
        $(".printShowhideCheckBox").change(function() {
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
        $("#printShowhide").hover(function() {}, function() {
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
        for (colNo = 0; colNo <= i; colNo++) {
            $("#printiCol" + colNo).attr("checked", opTable.fnSettings().aoColumns[parseInt(colNo)].bVisible);
            if (opTable.fnSettings().aoColumns[colNo].bVisible) {
                $("#printiCol" + colNo + "-sort").show();
            } else {
                $("#printiCol" + colNo + "-sort").hide();
            }
        }
    });

    function fnShowHide(iCol) {
        var bVis = oTable.fnSettings().aoColumns[iCol].bVisible;
        oTable.fnSetColumnVis(iCol, bVis ? false : true);
    }

    function printfnShowHide(iCol) {
        var bVis = opTable.fnSettings().aoColumns[iCol].bVisible;
        opTable.fnSetColumnVis(iCol, bVis ? false : true);
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
            "bStateSave": true,
            "iDisplayLength": 100,
            //"bRetrieve": true,                    
            "aoColumns": [{
                    "sClass": "center",
                    "bSortable": false
                },
                {
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
                    "sClass": "center",
                    "bSortable": false
                },
            ],
            <?php if ($sarr['sc_user_type'] != 'standalone') { ?> "aaSorting": [
                    [8, "desc"]
                ],
            <?php } else { ?> "aaSorting": [
                    [7, "desc"]
                ],
            <?php } ?> "fnDrawCallback": function() {
                var checkBoxes = document.getElementsByName("chk[]");
                len = checkBoxes.length;
                for (c = 0; c < len; c++) {
                    if (jQuery.inArray(checkBoxes[c].id, selectedRowsId) != -1) {
                        checkBoxes[c].setAttribute("checked", true);
                    }
                }
            },
            "bProcessing": true,
            "bServerSide": true,
            "sAjaxSource": "/eid/results/get-results-for-print.php",
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
                    "value": $("#facility").val()
                });
                aoData.push({
                    "name": "vlPrint",
                    "value": 'print'
                });
                aoData.push({
                    "name": "sampleTestDate",
                    "value": $("#sampleTestDate").val()
                });

                $.ajax({
                    "dataType": 'json',
                    "type": "POST",
                    "url": sSource,
                    "data": aoData,
                    "success": function(json) {
                        $("#totalSamplesList").val(json.iTotalDisplayRecords);
                        fnCallback(json);
                    }
                });
            }
        });
        $.unblockUI();
    }

    function loadPrintedVlRequestData() {
        $.blockUI();
        opTable = $('#printedVlRequestDataTable').dataTable({
            "oLanguage": {
                "sLengthMenu": "_MENU_ records per page"
            },
            "bJQueryUI": false,
            "bAutoWidth": false,
            "bInfo": true,
            "bScrollCollapse": true,
            //"bStateSave" : true,
            "iDisplayLength": 100,
            "bRetrieve": true,
            "aoColumns": [{
                    "sClass": "center",
                    "bSortable": false
                },
                {
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
                    "sClass": "center",
                    "bSortable": false
                },
            ],
            <?php if ($sarr['sc_user_type'] != 'standalone') { ?> "aaSorting": [
                    [8, "desc"]
                ],
            <?php } else { ?> "aaSorting": [
                    [7, "desc"]
                ],
            <?php } ?> "fnDrawCallback": function() {
                var checkBoxes = document.getElementsByName("chkPrinted[]");
                len = checkBoxes.length;
                for (c = 0; c < len; c++) {
                    if (jQuery.inArray(checkBoxes[c].id, selectedPrintedRowsId) != -1) {
                        checkBoxes[c].setAttribute("checked", true);
                    }
                }
            },
            "bProcessing": true,
            "bServerSide": true,
            "sAjaxSource": "/eid/results/get-printed-results-for-print.php",
            "fnServerData": function(sSource, aoData, fnCallback) {
                aoData.push({
                    "name": "batchCode",
                    "value": $("#batchCode").val()
                });
                aoData.push({
                    "name": "sampleCollectionDate",
                    "value": $("#printSampleCollectionDate").val()
                });
                aoData.push({
                    "name": "facilityName",
                    "value": $("#prinFacility").val()
                });

                aoData.push({
                    "name": "vlPrint",
                    "value": 'print'
                });

                aoData.push({
                    "name": "sampleTestDate",
                    "value": $("#printSampleTestDate").val()
                });
                $.ajax({
                    "dataType": 'json',
                    "type": "POST",
                    "url": sSource,
                    "data": aoData,
                    "success": function(json) {
                        $("#totalSamplesPrintedList").val(json.iTotalDisplayRecords);
                        fnCallback(json);
                    }
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

    function searchPrintedVlRequestData() {
        $.blockUI();
        opTable.fnDraw();
        $.unblockUI();
    }

    function resultPDF(id, newData) {
        $.blockUI();
        <?php
        $path = '';
        $path = '/eid/results/generate-result-pdf.php';
        ?>
        $.post("<?php echo $path; ?>", {
                source: 'print',
                id: id,
                newData: newData
            },
            function(data) {
                if (data == "" || data == null || data == undefined) {
                    $.unblockUI();
                    alert('Unable to generate download');
                } else {
                    $.unblockUI();
                    oTable.fnDraw();
                    opTable.fnDraw();
                    window.open('/uploads/' + data, '_blank');
                }
            });
    }

    function convertSearchResultToPdf(id, newData = null) {
        $.blockUI();
        <?php
        $path = '';
        $path = '/eid/results/generate-result-pdf.php';
        ?>
        if (newData == null) {
            var rowsLength = selectedRows.length;
            var totalCount = $("#totalSamplesList").val();
            var checkedRow = $("#checkedRows").val();
        } else {
            var rowsLength = selectedPrintedRows.length;
            var totalCount = $("#totalSamplesPrintedList").val();
            var checkedRow = $("#checkedPrintedRows").val();
        }
        if (rowsLength != 0 && rowsLength > 100) {
            $.unblockUI();
            alert("You have selected " + rowsLength + " results out of the maximum allowed 100 at a time");
            return false;
        } else if (totalCount != 0 && totalCount > 100 && rowsLength == 0) {
            $.unblockUI();
            alert("Maximum 100 results allowed to print at a time");
            return false;
        } else {
            id = checkedRow;
        }
        $.post("<?php echo $path; ?>", {
                source: 'print',
                id: id,
                newData: newData
            },
            function(data) {
                if (data == "" || data == null || data == undefined) {
                    $.unblockUI();
                    alert('Unable to generate download');
                } else {
                    $.unblockUI();
                    if (newData == null) {
                        selectedRows = [];
                        $(".checkRows").prop('checked', false);
                        $("#checkRowsData").prop('checked', false);
                        oTable.fnDraw();
                    } else {
                        selectedPrintedRows = [];
                        $(".checkPrintedRows").prop('checked', false);
                        $("#checkPrintedRowsData").prop('checked', false);
                    }

                    window.open('/uploads/' + data, '_blank');
                }
            });
    }

    function checkedRow(obj) {
        if ($(obj).is(':checked')) {
            if ($.inArray(obj.value, selectedRows) == -1) {
                selectedRows.push(obj.value);
                selectedRowsId.push(obj.id);
            }
        } else {
            selectedRows.splice($.inArray(obj.value, selectedRows), 1);
            selectedRowsId.splice($.inArray(obj.id, selectedRowsId), 1);
            $("#checkRowsData").attr("checked", false);
        }
        $("#checkedRows").val(selectedRows.join());
    }

    function checkedPrintedRow(obj) {
        if ($(obj).is(':checked')) {
            if ($.inArray(obj.value, selectedRows) == -1) {
                selectedPrintedRows.push(obj.value);
                selectedPrintedRowsId.push(obj.id);
            }
        } else {
            selectedPrintedRows.splice($.inArray(obj.value, selectedPrintedRows), 1);
            selectedPrintedRowsId.splice($.inArray(obj.id, selectedPrintedRowsId), 1);
            $("#checkPrintedRowsData").attr("checked", false);
        }
        $("#checkedPrintedRows").val(selectedPrintedRows.join());
    }

    function toggleAllVisible() {
        //alert(tabStatus);
        $(".checkRows").each(function() {
            $(this).prop('checked', false);
            selectedRows.splice($.inArray(this.value, selectedRows), 1);
            selectedRowsId.splice($.inArray(this.id, selectedRowsId), 1);
        });
        if ($("#checkRowsData").is(':checked')) {
            $(".checkRows").each(function() {
                $(this).prop('checked', true);
                selectedRows.push(this.value);
                selectedRowsId.push(this.id);
            });
        } else {
            $(".checkRows").each(function() {
                $(this).prop('checked', false);
                selectedRows.splice($.inArray(this.value, selectedRows), 1);
                selectedRowsId.splice($.inArray(this.id, selectedRowsId), 1);
                $("#status").prop('disabled', true);
            });
        }
        $("#checkedRows").val(selectedRows.join());
    }

    function toggleAllPrintedVisible() {
        //alert(tabStatus);
        $(".checkPrintedRows").each(function() {
            $(this).prop('checked', false);
            selectedPrintedRows.splice($.inArray(this.value, selectedPrintedRows), 1);
            selectedPrintedRowsId.splice($.inArray(this.id, selectedPrintedRowsId), 1);
        });
        if ($("#checkPrintedRowsData").is(':checked')) {
            $(".checkPrintedRows").each(function() {
                $(this).prop('checked', true);
                selectedPrintedRows.push(this.value);
                selectedPrintedRowsId.push(this.id);
            });
        } else {
            $(".checkPrintedRows").each(function() {
                $(this).prop('checked', false);
                selectedPrintedRows.splice($.inArray(this.value, selectedPrintedRows), 1);
                selectedPrintedRowsId.splice($.inArray(this.id, selectedPrintedRowsId), 1);
                $("#status").prop('disabled', true);
            });
        }
        $("#checkedPrintedRows").val(selectedPrintedRows.join());
    }
</script>
<?php
include(APPLICATION_PATH . '/footer.php');
