<?php

use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\GeoLocationsService;
use App\Services\SystemService;

$title = _translate("Sources of Requests");
require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);
$labNameList = $facilitiesService->getTestingLabs();


/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);
$stateNameList = $geolocationService->getProvinces("yes");

$activeModules = SystemService::getActiveModules();

?>
<style>
    .select2-selection__choice {
        color: black !important;
    }

    th {
        display: revert !important;
    }

    .red {
        background: lightcoral !important;
    }

    .green {
        background: lightgreen !important;
    }

    .yellow {
        background: yellow !important;
    }

    .center {
        text-align: center;
    }

    #syncStatusTable tr:hover {
        cursor: pointer;
        background: #eee !important;
    }
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-traffic-light"></em>
            <?php echo _translate("Lab Sync Status"); ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em>
                    <?php echo _translate("Home"); ?>
                </a></li>
            <li class="active">
                <?php echo _translate("Lab Sync Status"); ?>
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
                                    <?php echo _translate("Province/State"); ?>&nbsp;:
                                </strong></td>
                            <td>
                                <select name="province" id="province" onchange="getDistrictByProvince(this.value)" class="form-control" title="<?php echo _translate('Please choose Province/State/Region'); ?>" onkeyup="searchVlRequestData()">
                                    <?= $general->generateSelectOptions($stateNameList, null, _translate("-- Select --")); ?>
                                </select>
                            </td>
                            <td><strong>
                                    <?php echo _translate("District/County"); ?> :
                                </strong></td>
                            <td>
                                <select class="form-control" id="district" name="district" title="<?php echo _translate('Please select Province/State'); ?>">
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>
                                    <?php echo _translate("Lab Name"); ?>&nbsp;:
                                </strong></td>
                            <td>
                                <select class="form-control select2" id="labName" name="labName" title="<?php echo _translate('Please select the Lab name'); ?>">
                                    <?php echo $general->generateSelectOptions($labNameList, null, '--Select--'); ?>
                                </select>
                            </td>

                            <td>
                                <!-- <strong><?php echo _translate("Test Type"); ?>&nbsp;:</strong> -->
                            </td>
                            <td>
                                <!-- <select id="testType" name="testType" class="form-control" placeholder="<?php echo _translate('Please select the Test types'); ?>">
                                    <option value=""><?php echo _translate("--Select--"); ?></option>
                                    <?php if (!empty($activeModules) && in_array('vl', $activeModules)) { ?>
                                        <option value="vl"><?php echo _translate("Viral Load"); ?></option>
                                    <?php }
                                    if (!empty($activeModules) && in_array('eid', $activeModules)) { ?>
                                        <option value="eid"><?php echo _translate("Early Infant Diagnosis"); ?></option>
                                    <?php }
                                    if (!empty($activeModules) && in_array('covid19', $activeModules)) { ?>
                                        <option value="covid19"><?php echo _translate("Covid-19"); ?></option>
                                    <?php }
                                    if (!empty($activeModules) && in_array('hepatitis', $activeModules)) { ?>
                                        <option value='hepatitis'><?php echo _translate("Hepatitis"); ?></option>
                                    <?php }
                                    if (!empty($activeModules) && in_array('tb', $activeModules)) { ?>
                                        <option value='tb'><?php echo _translate("TB"); ?></option>
                                    <?php } ?>
                                </select> -->
                            </td>
                        </tr>
                        <tr>
                            <td colspan="4">
                                &nbsp;<a class="btn btn-success pull-right" style="margin-right:5px;" href="javascript:void(0);" onclick="exportSyncStatus();"><em class="fa-solid fa-file-excel"></em>&nbsp;&nbsp;
                                    <?php echo _translate("Export Excel"); ?>
                                </a>
                                &nbsp;<button class="btn btn-danger pull-right" onclick="document.location.href = document.location"><span>
                                        <?= _translate('Reset'); ?>
                                    </span></button>
                                <input type="button" onclick="loadData();" value="<?= _translate('Search'); ?>" class="btn btn-default pull-right">
                            </td>
                        </tr>
                    </table>
                    <!-- /.box-header -->
                    <div class="box-body">
                        <table aria-describedby="table" id="syncStatusDataTable" class="table table-bordered table-striped table-hover" aria-hidden="true">
                            <thead>
                                <tr>
                                    <th class="center">
                                        <?php echo _translate("Lab Name"); ?>
                                    </th>
                                    <!-- <th><?php echo _translate("Request Type"); ?></th> -->
                                    <th class="center">
                                        <?php echo _translate("Last Synced on"); ?>
                                    </th>
                                    <th class="center">
                                        <?php echo _translate("Last Results Sync from Lab"); ?>
                                    </th>
                                    <th class="center">
                                        <?php echo _translate("Last Requests Sync from STS"); ?>
                                    </th>
                                    <th class="center">
                                        <?php echo _translate("Version"); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="syncStatusTable">
                                <tr>
                                    <td colspan="4" class="dataTables_empty">
                                        <?php echo _translate("No data available"); ?>
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
        $('#labName').select2({
            width: '100%',
            placeholder: "Select Testing Lab"
        });

        $('#province').select2({
            width: '100%',
            placeholder: "Select Province"
        });

        $('#district').select2({
            width: '100%',
            placeholder: "Select District"
        });
        loadData();

        $('#syncStatusDataTable tbody').on('click', 'tr', function() {
            let facilityId = $(this).attr('data-facilityId');
            let link = "lab-sync-details.php?labId=" + facilityId;
            window.open(link);
        });
    });

    function loadData() {
        $.blockUI();
        $.post("/admin/monitoring/get-sync-status.php", {
                province: $('#province').val(),
                district: $('#district').val(),
                labName: $('#labName').val(),
                testType: $('#testType').val()
            },
            function(data) {
                $("#syncStatusTable").html(data);
                //$('#syncStatusDataTable').dataTable();
                $.unblockUI();
            });
    }

    function applyColor() {
        console.log("calling");
        $("span").remove();
    }

    function getDistrictByProvince(provinceId) {
        $("#district").html('');
        $.post("/common/get-by-province-id.php", {
                provinceId: provinceId,
                districts: true,
            },
            function(data) {
                Obj = $.parseJSON(data);
                $("#district").html(Obj['districts']);
            });
    }

    function exportSyncStatus() {
        $.blockUI();
        $.post("generate-lab-sync-status-report.php", {},
            function(data) {
                $.unblockUI();
                if (data === "" || data === null || data === undefined) {
                    alert("<?= _translate("Unable to generate the excel file", true); ?>");
                } else {
                    window.open('/download.php?f=' + data, '_blank');
                }
            });
    }
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
