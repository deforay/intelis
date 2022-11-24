<?php
$title = _("Sources of Requests");
require_once(APPLICATION_PATH . '/header.php');

$general = new \Vlsm\Models\General();
$facilityDb = new \Vlsm\Models\Facilities();
$labNameList = $facilityDb->getTestingLabs();

$geoLocationDb = new \Vlsm\Models\GeoLocations();
$stateNameList = $geoLocationDb->getProvinces("yes");
$activeTestModules = $general->getActiveTestModules();

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
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-circle-notch"></em> <?php echo _("Lab Sync Status"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
            <li class="active"><?php echo _("Lab Sync Status"); ?></li>
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
                            <td><strong><?php echo _("Lab Name"); ?>&nbsp;:</strong></td>
                            <td>
                                <select style="width:220px;" class="form-control select2" id="labName" name="labName" title="<?php echo _('Please select the Lab name'); ?>">
                                    <?php echo $general->generateSelectOptions($labNameList, null, '--Select--'); ?>
                                </select>
                            </td>
                            <td><strong><?php echo _("Test Types"); ?>&nbsp;:</strong></td>
                            <td>
                                <select type="text" id="testType" name="testType" class="form-control" placeholder="<?php echo _('Please select the Test types'); ?>">
                                    <?php if (!empty($activeTestModules) && in_array('vl', $activeTestModules)) { ?>
                                        <option value="vl"><?php echo _("Viral Load"); ?></option>
                                    <?php }
                                    if (!empty($activeTestModules) && in_array('eid', $activeTestModules)) { ?>
                                        <option value="eid"><?php echo _("Early Infant Diagnosis"); ?></option>
                                    <?php }
                                    if (!empty($activeTestModules) && in_array('covid19', $activeTestModules)) { ?>
                                        <option value="covid19"><?php echo _("Covid-19"); ?></option>
                                    <?php }
                                    if (!empty($activeTestModules) && in_array('hepatitis', $activeTestModules)) { ?>
                                        <option value='hepatitis'><?php echo _("Hepatitis"); ?></option>
                                    <?php }
                                    if (!empty($activeTestModules) && in_array('tb', $activeTestModules)) { ?>
                                        <option value='tb'><?php echo _("TB"); ?></option>
                                    <?php } ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <!-- /.box-header -->
                    <div class="box-body">
                        <table id="syncStatusDataTable" class="table table-bordered table-striped" aria-hidden="true">
                            <thead>
                                <tr>
                                    <th><?php echo _("Lab Name"); ?></th>
                                    <th><?php echo _("Status"); ?></th>
                                    <th><?php echo _("Last Sync done on"); ?></th>
                                </tr>
                            </thead>
                            <tbody id="syncStatusTable">
                                <tr>
                                    <td colspan="2" class="dataTables_empty"><?php echo _("No data available"); ?></td>
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
        $.blockUI();
        $.post("/admin/monitoring/get-sync-status.php", {
                province: $('#province').val(),
                district: $('#district').val(),
                labName: $('#labName').val(),
                testType: $('#testType').val()
			},
			function(data) {
				$("#syncStatusTable").html(data);
                $('#syncStatusDataTable').dataTable();
                $.unblockUI();
			});
    });
    
    function applyColor(){
        console.log("calling");
        $("span").remove();
    }

    function getDistrictByProvince(provinceId) {
		$("#district").html('');
		$.post("/common/get-district-by-province-id.php", {
				provinceId: provinceId,
			},
			function(data) {
				$("#district").html(data);
			});
	}
</script>
<?php
require_once(APPLICATION_PATH . '/footer.php');
?>