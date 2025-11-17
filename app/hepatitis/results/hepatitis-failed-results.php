<?php

use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\GeoLocationsService;
use App\Services\UsersService;

$title = _translate("Hepatitis | View All Requests");


require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);

$healthFacilites = $facilitiesService->getHealthFacilities('hepatitis');

$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, null, "-- Select --");

$formId = (int) $general->getGlobalConfig('vl_form');

$batQuery = "SELECT batch_code FROM batch_details where test_type = 'hepatitis' AND batch_status='completed'";
$batResult = $db->rawQuery($batQuery);
$state = $geolocationService->getProvinces("yes");

$sQuery = "SELECT * FROM r_hepatitis_sample_type WHERE `status`='active'";
$sResult = $db->rawQuery($sQuery);
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
        <h1><em class="fa-solid fa-list-check"></em>
            <?php echo _translate("Failed/Hold Samples"); ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em>
                    <?php echo _translate("Home"); ?>
                </a></li>
            <li class="active">
                <?php echo _translate("Test Request"); ?>
            </li>
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <table aria-describedby="table" id="advanceFilter" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width: 98%;margin-bottom: 0px;">
                        <tr>
                            <td><strong>
                                    <?php echo _translate("Sample Collection Date"); ?> :
                                </strong></td>
                            <td>
                                <input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control" placeholder="<?php echo _translate('Select Collection Date'); ?>" readonly style="background:#fff;" />
                            </td>
                            <td><strong>
                                    <?php echo _translate("Province/State"); ?>&nbsp;:
                                </strong></td>
                            <td>
                                <select class="form-control select2-element" id="state" onchange="getByProvince(this.value)" name="state" title="<?php echo _translate('Please select Province/State'); ?>">
                                    <?= $general->generateSelectOptions($state, null, _translate("-- Select --")); ?>
                                </select>
                            </td>
                            <td><strong>
                                    <?php echo _translate("District/County"); ?> :
                                </strong></td>
                            <td>
                                <select class="form-control select2-element" id="district" name="district" title="<?php echo _translate('Please select Province/State'); ?>" onchange="getByDistrict(this.value		)">
                                </select>
                            </td>

                        </tr>
                        <tr>
                            <td><strong>
                                    <?php echo _translate("Facility Name"); ?> :
                                </strong></td>
                            <td>
                                <select class="form-control" id="facilityName" name="facilityName" multiple="multiple" title="<?php echo _translate('Please select facility name'); ?>" style="width:100%;">
                                    <?= $facilitiesDropdown; ?>
                                </select>
                            </td>
                            <td><strong>
                                    <?php echo _translate("Testing Lab"); ?> :
                                </strong></td>
                            <td>
                                <select class="form-control" id="vlLab" name="vlLab" title="<?php echo _translate('Please select Testing Lab'); ?>" style="width:220px;">
                                    <?= $testingLabsDropdown; ?>
                                </select>
                            </td>
                            <td><strong>
                                    <?php echo _translate("Sample Type"); ?> :
                                </strong></td>
                            <td>
                                <select class="form-control" id="sampleType" name="sampleType" title="<?php echo _translate('Please select sample type'); ?>">
                                    <option value="">
                                        <?php echo _translate("-- Select --"); ?>
                                    </option>
                                    <?php
                                    foreach ($sResult as $type) {
                                    ?>
                                        <option value="<?php echo $type['sample_id']; ?>"><?= $type['sample_name']; ?>
                                        </option>
                                    <?php
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>
                                    <?php echo _translate("Result Status"); ?>&nbsp;:
                                </strong></td>
                            <td>
                                <select name="status[]" id="status" multiple="multiple" title="<?php echo _translate('Please choose Sample Status'); ?>">
                                    <option value="<?= SAMPLE_STATUS\TEST_FAILED; ?>" selected="selected"><?php echo _translate("Failed"); ?></option>
                                    <option value="<?= SAMPLE_STATUS\ON_HOLD; ?>" selected="selected"><?php echo _translate("Hold"); ?></option>
                                    <option value="<?= SAMPLE_STATUS\LOST_OR_MISSING; ?>"><?php echo _translate("Lost"); ?></option>
                                    <!-- <option value="<?= SAMPLE_STATUS\EXPIRED; ?>"><?php echo _translate("Expired"); ?></option> -->
                                </select>
                            </td>
                            <td><strong>
                                    <?php echo _translate("Patient ID"); ?>&nbsp;:
                                </strong></td>
                            <td>
                                <input type="text" id="patientId" name="patientId" class="form-control" placeholder="<?php echo _translate('Enter Patient ID'); ?>" style="background:#fff;" />
                            </td>

                            <td><strong>
                                    <?php echo _translate("Patient Name"); ?>&nbsp;:
                                </strong></td>
                            <td>
                                <input type="text" id="patientName" name="patientName" class="form-control" placeholder="<?php echo _translate('Enter Patient Name'); ?>" style="background:#fff;" />
                            </td>
                        </tr>

                        <tr>
                            <td colspan="2"><input type="button" onclick="searchVlRequestData();" value="<?= _translate('Search'); ?>" class="btn btn-primary btn-sm">
                                &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>
                                        <?= _translate('Reset'); ?>
                                    </span></button>
                            </td>
                            <td colspan="4">
                                &nbsp;<button class="btn btn-success btn-sm pull-right retest-btn" style="margin-right:5px;display:none;" onclick="retestSample('',true);"><span>
                                        <?php echo _translate("Retest the selected samples"); ?>
                                    </span></button>
                            </td>
                        </tr>
                    </table>
                    <!-- /.box-header -->
                    <div class="box-body">
                        <input type="hidden" name="checkedTests" id="checkedTests" />
                        <table aria-describedby="table" id="hepatitisFailedRequestDataTable" class="table table-bordered table-striped" aria-hidden="true">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="checkTestsData" onclick="toggleAllVisible()" /></th>
                                    <th>
                                        <?php echo _translate("Sample ID"); ?>
                                    </th>
                                    <?php if (!$general->isStandaloneInstance()) { ?>
                                        <th>
                                            <?php echo _translate("Remote Sample ID"); ?>
                                        </th>
                                    <?php } ?>
                                    <th>
                                        <?php echo _translate("Sample Collection Date"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Batch Code"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Facility Name"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Patient ID"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Patient's Name"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Province/State"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("District/County"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Result"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Last Modified On"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Status"); ?>
                                    </th>
                                    <th>
                                        <?php echo _translate("Action"); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="15" class="dataTables_empty">
                                        <?php echo _translate("Loading data from server"); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <?php if (isset($global['bar_code_printing']) && $global['bar_code_printing'] == 'zebra-printer') { ?>

                            <div id="printer_data_loading" style="display:none"><span id="loading_message">
                                    <?php echo _translate("Loading Printer Details"); ?>...
                                </span><br />
                                <div class="progress" style="width:100%">
                                    <div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%">
                                    </div>
                                </div>
                            </div> <!-- /printer_data_loading -->
                            <div id="printer_details" style="display:none">
                                <span id="selected_printer">
                                    <?php echo _translate("No printer selected"); ?>!
                                </span>
                                <button type="button" class="btn btn-success" onclick="changePrinter()">
                                    <?php echo _translate("Change/Retry"); ?>
                                </button>
                            </div><br /> <!-- /printer_details -->
                            <div id="printer_select" style="display:none">
                                <?php echo _translate("Zebra Printer Options"); ?><br />
                                <?php echo _translate("Printer"); ?>: <select id="printers"></select>
                            </div> <!-- /printer_select -->
                        <?php } ?>
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

<?= CommonService::barcodeScripts(); ?>

<script type="text/javascript">
    var startDate = "";
    var endDate = "";
    var selectedTests = [];
    var selectedTestsId = [];
    var oTable = null;
    $(document).ready(function() {
        $("#status").selectize({
            plugins: ["restore_on_backspace", "remove_button", "clear_button"],
        });
        $("#state").select2({
            placeholder: "<?php echo _translate("Select Province"); ?>"
        });
        $("#district").select2({
            placeholder: "<?php echo _translate("Select District"); ?>"
        });
        $("#vlLab").select2({
            placeholder: "<?php echo _translate("Select Labs"); ?>"
        });
        $("#facilityName").select2({
            placeholder: "<?php echo _translate("Select Facilities"); ?>"
        });
        <?php
        if (isset($_GET['barcode']) && $_GET['barcode'] == 'true') {
            $sampleCode = htmlspecialchars((string) $_GET['s']);
            $facilityCode = htmlspecialchars((string) $_GET['f']);
            $patientID = htmlspecialchars((string) $_GET['p']);
            echo "printBarcodeLabel('$sampleCode','$facilityCode','$patientID');";
        }
        ?>

        loadVlRequestData();
        $('#sampleCollectionDate').daterangepicker({
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
        oTable = $('#hepatitisFailedRequestDataTable').dataTable({
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
                <?php if (!$general->isStandaloneInstance()) { ?> {
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
                {
                    "sClass": "center",
                    "bSortable": false
                },
            ],
            "aaSorting": [
                [<?php echo ($general->isSTSInstance() || $general->isLISInstance()) ? 12 : 11 ?>, "desc"]
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
            "sAjaxSource": "/hepatitis/results/get-failed-results.php",
            "fnServerData": function(sSource, aoData, fnCallback) {

                aoData.push({
                    "name": "sampleCollectionDate",
                    "value": $("#sampleCollectionDate").val()
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
                    "name": "facilityName",
                    "value": $("#facilityName").val()
                });
                aoData.push({
                    "name": "vlLab",
                    "value": $("#vlLab").val()
                });
                aoData.push({
                    "name": "sampleType",
                    "value": $("#sampleType").val()
                });
                aoData.push({
                    "name": "status",
                    "value": $("#status").val()
                });
                aoData.push({
                    "name": "patientId",
                    "value": $("#patientId").val()
                });
                aoData.push({
                    "name": "patientName",
                    "value": $("#patientName").val()
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
                    hepatitisId: id,
                    bulkIds: bulk
                },
                function(data) {
                    $.unblockUI();
                    if (data > 0) {
                        alert("<?php echo _translate("Selected Sample(s) ready for testing"); ?>");
                        oTable.fnDraw();
                        // Clear selectedTests and selectedTestsId arrays
                        selectedTests = [];
                        selectedTestsId = [];
                        $(".retest-btn").hide();
                        $("#checkTestsData").prop('checked', false);
                    } else {
                        alert("<?php echo _translate("Something went wrong. Please try again later"); ?>");
                    }
                });
        }
    }

    function getByProvince(provinceId) {
        $("#district").html('');
        $("#facilityName").html('');
        $("#vlLab").html('');
        $.post("/common/get-by-province-id.php", {
                provinceId: provinceId,
                districts: true,
                facilities: true,
                labs: true
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
                labs: true
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
