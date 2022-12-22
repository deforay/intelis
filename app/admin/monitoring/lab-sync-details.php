<?php
$title = _("Sources of Requests");
require_once(APPLICATION_PATH . '/header.php');

$facilityDb = new \Vlsm\Models\Facilities();
$geoLocationDb = new \Vlsm\Models\GeoLocations();
$facilityDetails = $facilityDb->getAllFacilities();
foreach($facilityDetails as $row){
    $facilityNameList[$row['facility_id']] = $row['facility_name'];
}
$stateNameList = $geoLocationDb->getProvinces("yes");

$sQuery = "SELECT f.facility_id, f.facility_name, (SELECT MAX(requested_on) FROM track_api_requests WHERE request_type = 'requests' AND facility_id = f.facility_id GROUP BY facility_id  ORDER BY requested_on DESC) AS request, (SELECT MAX(requested_on) FROM track_api_requests WHERE request_type = 'results' AND facility_id = f.facility_id GROUP BY facility_id ORDER BY requested_on DESC) AS results, tar.test_type, tar.requested_on  FROM facility_details AS f JOIN track_api_requests AS tar ON tar.facility_id = f.facility_id WHERE f.facility_id = ".base64_decode($_GET['labId']) ." GROUP BY f.facility_id ORDER BY tar.requested_on DESC";
$labInfo = $db->rawQueryOne($sQuery);
?>
<style>˝
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
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-sync"></em> <?php echo _("Lab Sync Details For ") ?><span style="font-weight: 500;"><?php echo $labInfo['facility_name'];?></span></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
            <li class="active"><?php echo _("Lab Sync Details"); ?></li>
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                <table class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:98%;">
                        <tr>
                            <td><strong><?php echo _("Province/State"); ?>&nbsp;:</strong></td>
                            <td>
                                <select name="province" id="province" onchange="getDistrictByProvince(this.value)" class="form-control" title="<?php echo _('Please choose Province/State/Region'); ?>" onkeyup="searchVlRequestData()">
                                    <?= $general->generateSelectOptions($stateNameList, null, _("-- Select --")); ?>
                                </select>
                            </td>
                            <td><strong><?php echo _("District/County"); ?> :</strong></td>
                            <td>
                                <select class="form-control" id="district" name="district" title="<?php echo _('Please select Province/State'); ?>">
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php echo _("Facility Name"); ?>&nbsp;:</strong></td>
                            <td>
                                <select class="form-control select2" id="facilityName" name="facilityName" title="<?php echo _('Please select the Lab name'); ?>">
                                    <?php echo $general->generateSelectOptions($facilityNameList, null, '--Select--'); ?>
                                </select>
                            </td>
                            <td colspan="2">
                                <!-- &nbsp;<a class="btn btn-success pull-right" style="margin-right:5px;" href="javascript:void(0);" onclick="exportSyncStatus();"><em class="fa-solid fa-file-excel"></em>&nbsp;&nbsp; <?php echo _("Export Excel"); ?></a> -->
                                &nbsp;<button class="btn btn-danger pull-right" onclick="document.location.href = document.location"><span><?php echo _("Reset"); ?></span></button>
                                <input type="button" onclick="loadData();" value="<?php echo _('Search'); ?>" class="btn btn-default pull-right">
                            </td>
                        </tr>
                    </table>
                    <!-- /.box-header -->
                    <div class="box-body">
                        <table class="table table-bordered table-striped">
                            <tr>
                                <th>Last Request Sent from VLSTS :</th>
                                <td align="left"><?php echo $labInfo['request'];?></td>
                                <th>Last Result Received from Lab</th>
                                <td align="left"><?php echo $labInfo['results'];?></td>
                            </tr>
                        </table>
                        <table id="syncStatusDataTable" class="table table-bordered table-striped" aria-hidden="true">
                            <thead>
                                <tr>
                                    <th class="center"><?php echo _("Facility Name"); ?></th>
                                    <th class="center"><?php echo _("Test Type"); ?></th>
                                    <th class="center"><?php echo _("Last Request Sent from VLSTS"); ?></th>
                                    <th class="center"><?php echo _("Last Result Received From Lab"); ?></th>
                                </tr>
                            </thead>
                            <tbody id="syncStatusTable">
                                <tr>
                                    <td colspan="4" class="dataTables_empty"><?php echo _("No data available"); ?></td>
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
    });

    function loadData() {
        $.blockUI();
        $.post("/admin/monitoring/get-sync-status-details.php", {
                labId: '<?php echo $_GET['labId'];?>',
                province: $('#province').val(),
                district: $('#district').val(),
                facilityName: $('#facilityName').val()
            },
            function(data) {
                $("#syncStatusTable").html(data);
                $('#syncStatusDataTable').dataTable();
                $.unblockUI();
            });
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
</script>
<?php
require_once(APPLICATION_PATH . '/footer.php');