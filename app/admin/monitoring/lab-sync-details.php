<?php

use App\Services\TestsService;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

$title = _translate("Sources of Requests");
require_once APPLICATION_PATH . '/header.php';

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$facilityId = base64_decode((string) $_GET['labId']);

$facilityDetails = $facilitiesService->getAllFacilities();
foreach ($facilityDetails as $row) {
    $facilityNameList[$row['facility_id']] = $row['facility_name'];
}
$stateNameList = $geolocationService->getProvinces("yes");

$activeTests = TestsService::getActiveTests();

$sQuery = "SELECT f.facility_id, f.facility_name,
                    (SELECT MAX(requested_on)
                        FROM track_api_requests
                        WHERE request_type = 'requests'
                        AND facility_id = f.facility_id
                        GROUP BY facility_id
                        ORDER BY requested_on DESC) AS request,
                    (SELECT MAX(requested_on)
                        FROM track_api_requests
                        WHERE request_type = 'results'
                        AND facility_id = f.facility_id
                        GROUP BY facility_id ORDER BY requested_on DESC) AS results,
                    tar.test_type, tar.requested_on
                FROM facility_details AS f
                JOIN track_api_requests AS tar ON tar.facility_id = f.facility_id
                WHERE f.facility_id = ?
                GROUP BY f.facility_id
                ORDER BY tar.requested_on DESC";
$labInfo = $db->rawQueryOne($sQuery, [$facilityId]);
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

    #syncStatusTable tr:hover {
        cursor: pointer;
        background: #eee !important;
    }
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-sync"></em>
            <?php echo _translate("Lab Sync Details for ") ?><span style="font-weight: 500;">
                <?php echo $labInfo['facility_name']; ?>
            </span>
        </h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em>
                    <?php echo _translate("Home"); ?>
                </a></li>
            <li class="active">
                <?php echo _translate("Lab Sync Details"); ?>
            </li>
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <table aria-describedby="table" class="table" aria-hidden="true"
                        style="margin-left:1%;margin-top:20px;width:98%;">
                        <tr>
                            <td><strong>
                                    <?php echo _translate("Province/State"); ?>&nbsp;:
                                </strong></td>
                            <td>
                                <select name="province" id="province" onchange="getDistrictByProvince(this.value)"
                                    class="form-control"
                                    title="<?php echo _translate('Please choose Province/State/Region'); ?>"
                                    onkeyup="searchVlRequestData()">
                                    <?= $general->generateSelectOptions($stateNameList, null, _translate("-- Select --")); ?>
                                </select>
                            </td>
                            <td><strong>
                                    <?php echo _translate("District/County"); ?> :
                                </strong></td>
                            <td>
                                <select class="form-control" id="district" name="district"
                                    title="<?php echo _translate('Please select Province/State'); ?>">
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>
                                    <?php echo _translate("Facility Name"); ?>&nbsp;:
                                </strong></td>
                            <td>
                                <select class="form-control select2" id="facilityName" name="facilityName"
                                    title="<?php echo _translate('Please select the Lab name'); ?>">
                                    <?php echo $general->generateSelectOptions($facilityNameList, null, '--Select--'); ?>
                                </select>
                            </td>
                            <td>
                                <strong>
                                    <?php echo _translate("Test Type"); ?>&nbsp;:
                                </strong>
                            </td>
                            <td>
                                <select id="testType" name="testType" class="form-control"
                                    placeholder="<?php echo _translate('Please select the Test types'); ?>">
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
                        </tr>
                        <tr>
                            <td colspan="4">
                                &nbsp;<a class="btn btn-success pull-right" style="margin-right:5px;"
                                    href="javascript:void(0);" onclick="exportSyncStatus();"><em
                                        class="fa-solid fa-file-excel"></em>&nbsp;&nbsp;
                                    <?php echo _translate("Export Excel"); ?>
                                </a>
                                &nbsp;<button class="btn btn-danger pull-right"
                                    onclick="document.location.href = document.location"><span>
                                        <?= _translate('Reset'); ?>
                                    </span></button>
                                <input type="button" onclick="loadData();" value="<?= _translate('Search'); ?>"
                                    class="btn btn-default pull-right">
                            </td>
                        </tr>
                    </table>
                    <!-- /.box-header -->
                    <div class="box-body">
                        <table aria-describedby="table" class="table table-bordered table-striped" style="width: 70%;">
                            <tr>
                                <th scope="row">Last Request Sent from STS :</th>
                                <td align="left">
                                    <?php echo $labInfo['request']; ?>
                                </td>
                                <th scope="row">Last Result Received from Lab</th>
                                <td align="left">
                                    <?php echo $labInfo['results']; ?>
                                </td>
                            </tr>
                        </table>
                        <hr>
                        <table aria-describedby="table" id="syncStatusDataTable"
                            class="table table-bordered table-striped table-hover" aria-hidden="true">
                            <thead>
                                <tr>
                                    <th class="center" scope="col">
                                        <?php echo _translate("Facility Name"); ?>
                                    </th>
                                    <th class="center" scope="col">
                                        <?php echo _translate("Test Type"); ?>
                                    </th>
                                    <th class="center" scope="col">
                                        <?php echo _translate("Province"); ?>
                                    </th>
                                    <th class="center" scope="col">
                                        <?php echo _translate("District"); ?>
                                    </th>
                                    <th class="center" scope="col">
                                        <?php echo _translate("Last Request Sent from STS"); ?>
                                    </th>
                                    <th class="center" scope="col">
                                        <?php echo _translate("Last Result Received From Lab"); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="syncStatusTable">
                                <tr>
                                    <td colspan="6" class="dataTables_empty">
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
    var oTable = 0;
    $(document).ready(function () {
        $('#facilityName').select2({
            width: '100%',
            placeholder: "Select Facility Name"
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
        $('#syncStatusDataTable tbody').on('click', 'tr', function () {
            let url = $(this).attr('data-url');
            let facilityId = $(this).attr('data-facilityId');
            let labId = $(this).attr('data-labId');
            let link = url + "?facilityId=" + facilityId + "&labId=" + labId;
            window.open(link);
        });
    });

    function loadData() {
        $.blockUI();
        $.post("/admin/monitoring/get-sync-status-details.php", {
            labId: '<?php echo $_GET['labId']; ?>',
            testType: $('#testType').val(),
            province: $('#province').val(),
            district: $('#district').val(),
            facilityName: $('#facilityName').val()
        },
            function (data) {
                $("#syncStatusTable").html(data);
                if (oTable == 0) {
                    $('#syncStatusDataTable').dataTable({
                        "ordering": false
                    });
                    oTable = 1;
                }
                $.unblockUI();
            });
    }

    function getDistrictByProvince(provinceId) {
        $("#district").html('');
        $.post("/common/get-by-province-id.php", {
            provinceId: provinceId,
            districts: true,
        },
            function (data) {
                Obj = $.parseJSON(data);
                $("#district").html(Obj['districts']);
            });
    }

    function exportSyncStatus() {
        // $.blockUI();
        $.post("generate-lab-sync-status-details-report.php", {},
            function (data) {
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
