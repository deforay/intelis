<?php
$title = _("Covid19 | View All Requests");


require_once(APPLICATION_PATH . '/header.php');

$general = new \Vlsm\Models\General();
$facilitiesDb = new \Vlsm\Models\Facilities();
$usersModel = new \Vlsm\Models\Users();
$healthFacilites = $facilitiesDb->getHealthFacilities('covid19');

$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, null, "-- Select --");

$formId = $general->getGlobalConfig('vl_form');

$batQuery = "SELECT batch_code FROM batch_details where test_type = 'covid19' AND batch_status='completed'";
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
        <h1><i class="fa fa-edit"></i> <?php echo _("Failed/Hold Samples");?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><i class="fa fa-dashboard"></i> <?php echo _("Home");?></a></li>
            <li class="active"><?php echo _("Test Request");?></li>
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <table id="advanceFilter" class="table" cellpadding="1" cellspacing="3" style="margin-left:1%;margin-top:20px;width: 98%;margin-bottom: 0px;display: none;">
                        <tr>
                            <td><b><?php echo _("Sample Collection Date");?> :</b></td>
                            <td>
                                <input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control" placeholder="<?php echo _('Select Collection Date');?>" readonly style="background:#fff;" />
                            </td>
                            <td><b><?php echo _("Batch Code");?> :</b></td>
                            <td>
                                <select class="form-control" id="batchCode" name="batchCode" title="<?php echo _('Please select batch code');?>">
                                    <option value=""> <?php echo _("-- Select --");?> </option>
                                    <?php
                                    foreach ($batResult as $code) {
                                    ?>
                                        <option value="<?php echo $code['batch_code']; ?>"><?php echo $code['batch_code']; ?></option>
                                    <?php
                                    }
                                    ?>
                                </select>
                            </td>
                            <td><b><?php echo _("Req. Sample Type");?> :</b></td>
                            <td>
                                <select class="form-control" id="requestSampleType" name="requestSampleType" title="<?php echo _('Please select request sample type');?>">
                                    <option value=""><?php echo _("All");?></option>
                                    <option value="result"><?php echo _("Sample With Result");?></option>
                                    <option value="noresult"><?php echo _("Sample Without Result");?></option>
                                </select>
                            </td>

                        </tr>
                        <tr>
                            <td><b><?php echo _("Facility Name");?> :</b></td>
                            <td>
                                <select class="form-control" id="facilityName" name="facilityName" multiple="multiple" title="<?php echo _('Please select facility name');?>" style="width:100%;">
                                    <?= $facilitiesDropdown; ?>
                                </select>
                            </td>
                            <td><b><?php echo _("Province/State");?>&nbsp;:</b></td>
                            <td>
                                <input type="text" id="state" name="state" class="form-control" placeholder="<?php echo _('Enter Province/State');?>" style="background:#fff;" onkeyup="loadVlRequestStateDistrict()" />
                            </td>
                            <td><b><?php echo _("District/County");?> :</b></td>
                            <td>
                                <input type="text" id="district" name="district" class="form-control" placeholder="<?php echo _('Enter District/County');?>" onkeyup="loadVlRequestStateDistrict()" />
                            </td>
                        </tr>
                        <tr>

                        </tr>
                        <tr>
                            <td colspan="2"><input type="button" onclick="searchVlRequestData();" value="<?php echo _("Search");?>" class="btn btn-default btn-sm">
                                &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _("Reset");?></span></button>
                                &nbsp;<button class="btn btn-danger btn-sm" onclick="hideAdvanceSearch('advanceFilter','filter');"><span><?php echo _("Hide Advanced Search Options");?></span></button>
                            </td>
                            <td colspan="4">
                                &nbsp;<button class="btn btn-success btn-sm pull-right retest-btn" style="margin-right:5px;display:none;" onclick="retestSample('',true);"><span><?php echo _("Retest the selected samples");?></span></button>
                            </td>
                        </tr>
                    </table>
                    <table id="filter" class="table" cellpadding="1" cellspacing="3" style="margin-left:1%;margin-top:20px;width: 98%;margin-bottom: 0px;">
                        <tr id="">
                            <td>
                                &nbsp;<button class="btn btn-success btn-sm pull-right retest-btn" style="margin-right:5px;display:none;" onclick="retestSample('',true);"><span><?php echo _("Retest the selected samples");?></span></button>
                            </td>
                        </tr>
                    </table>

                    <!-- /.box-header -->
                    <div class="box-body">
                        <input type="hidden" name="checkedTests" id="checkedTests" />
                        <table id="covid19FailedRequestDataTable" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="checkTestsData" onclick="toggleAllVisible()" /></th>
                                    <th><?php echo _("Sample Code");?></th>
                                    <?php if ($sarr['sc_user_type'] != 'standalone') { ?>
                                        <th><?php echo _("Remote Sample");?> <br /><?php echo _("Code");?></th>
                                    <?php } ?>
                                    <th><?php echo _("Sample Collection");?><br /> <?php echo _("Date");?></th>
                                    <th><?php echo _("Batch Code");?></th>
                                    <th><?php echo _("Facility Name");?></th>
                                    <th><?php echo _("Patient ID");?></th>
                                    <th><?php echo _("Patient First Name");?></th>
                                    <th><?php echo _("Patient Last Name");?></th>
                                    <th><?php echo _("Province/State");?></th>
                                    <th><?php echo _("District/County");?></th>
                                    <th><?php echo _("Result");?></th>
                                    <th><?php echo _("Last Modified On");?></th>
                                    <th><?php echo _("Status");?></th>
                                    <?php if (isset($_SESSION['privileges']) && (in_array("covid19-edit-request.php", $_SESSION['privileges']))) { ?>
                                        <th><?php echo _("Action");?></th>
                                    <?php } ?>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="15" class="dataTables_empty"><?php echo _("Loading data from server");?></td>
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
    var startDate = "";
    var endDate = "";
    var selectedTests = [];
    var selectedTestsId = [];
    var oTable = null;
    $(document).ready(function() {
        
        $("#facilityName").select2({
            placeholder: "<?php echo _("Select Facilities");?>"
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
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
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
        oTable = $('#covid19FailedRequestDataTable').dataTable({
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
                <?php if (isset($_SESSION['privileges']) && (in_array("covid-19-edit-request.php", $_SESSION['privileges']))) { ?> {
                        "sClass": "center",
                        "bSortable": false
                    },
                <?php } ?>
            ],
            "aaSorting": [
                [<?php echo ($sarr['sc_user_type'] == 'remoteuser' || $sarr['sc_user_type'] == 'vluser') ? 12 : 11 ?>, "desc"]
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
            "sAjaxSource": "/covid-19/results/get-failed-results.php",
            "fnServerData": function(sSource, aoData, fnCallback) {
               
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


    function hideAdvanceSearch(hideId, showId) {
        $("#" + hideId).hide();
        $("#" + showId).show();
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

    function retestSample(id, bulk = false) {
        if (bulk) {
            id = selectedTests;
        }
        if (id != "") {
            $.blockUI();
            $.post("failed-results-retest.php", {
                    covid19Id: id,
                    bulkIds: bulk
                },
                function(data) {
                    $.unblockUI();
                    if (data > 0) {
                        alert("<?php echo _("Selected Sample(s) ready for testing");?>");
                        oTable.fnDraw();
                    } else {
                        alert("<?php echo _("Something went wrong. Please try again later");?>");
                    }
                });
        }
    }
</script>
<?php
require_once(APPLICATION_PATH . '/footer.php');
?>