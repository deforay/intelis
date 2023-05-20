<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\SystemService;



require_once APPLICATION_PATH . '/header.php';
/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var SystemService $systemService */
$systemService = ContainerRegistry::get(SystemService::class);

$activeTestModules = $systemService->getActiveTestModules();

$activeModules = array('admin', 'common');

if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) {
	$activeModules[] = 'vl';
}
if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) {
	$activeModules[] = 'eid';
}
if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) {
	$activeModules[] = 'covid19';
}
if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis']) {
	$activeModules[] = 'hepatitis';
}
if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] === true) {
	$activeModules[] = 'tb';
}
if (isset(SYSTEM_CONFIG['modules']['genericTests']) && SYSTEM_CONFIG['modules']['genericTests'] === true) {
	$activeModules[] = 'generic-tests';
}

$resourcesQuery = "SELECT module, GROUP_CONCAT( DISTINCT CONCAT(resources.resource_id,',',resources.display_name) ORDER BY resources.display_name SEPARATOR '##' ) as 'module_resources' FROM `resources` WHERE `module` IN ('" . implode("','", $activeModules) . "') GROUP BY `module` ORDER BY `module` ASC";
$rInfo = $db->query($resourcesQuery);
?>
<style>
	.labelName {
		font-size: 13px;
	}

	.switch-field {
		display: flex;
		overflow: hidden;
	}

	.switch-field input {
		position: absolute !important;
		clip: rect(0, 0, 0, 0);
		height: 1px;
		width: 1px;
		border: 0;
		overflow: hidden;
	}

	.switch-field label {
		background-color: #e4e4e4;
		color: rgba(0, 0, 0, 0.6);
		font-size: 14px;
		line-height: 1;
		text-align: center;
		padding: 8px 16px;
		margin-right: -1px;
		border: 1px solid rgba(0, 0, 0, 0.2);
		box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.3), 0 1px rgba(255, 255, 255, 0.1);
		transition: all 0.1s ease-in-out;
	}

	.switch-field label:hover {
		cursor: pointer;
	}

	.switch-field input:checked+label {
		background-color: #87CEFA;
		box-shadow: none;
	}

	.switch-field label:first-of-type {
		border-radius: 4px 0 0 4px;
	}

	.switch-field label:last-of-type {
		border-radius: 0 4px 4px 0;
	}

	/* This is just for CodePen. */

	.form {
		max-width: 600px;
		font-family: "Lucida Grande", Tahoma, Verdana, sans-serif;
		font-weight: normal;
		line-height: 1.625;
		margin: 8px auto;
		padding: 16px;
	}

	h2 {
		font-size: 18px;
		margin-bottom: 8px;
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-user"></em> <?php echo _("Add Role"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
			<li class="active"><?php echo _("Add Role"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?php echo _("indicates required field"); ?> &nbsp;</div>
			</div>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->
				<form class="form-horizontal" method='post' name='roleAddForm' id='roleAddForm' autocomplete="off" action="addRolesHelper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="userName" class="col-lg-4 control-label"><?php echo _("Role Name"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="roleName" name="roleName" placeholder="<?php echo _('Role Name'); ?>" title="<?php echo _('Please enter a name for this role'); ?>" onblur='checkNameValidation("roles","role_name",this,null,"<?php echo _("This role name that you entered already exists.Try another role name"); ?>",null)' />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="email" class="col-lg-4 control-label"><?php echo _("Role Code"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="roleCode" name="roleCode" placeholder="<?php echo _('Role Code'); ?>" title="<?php echo _('Please enter role code'); ?>" onblur='checkNameValidation("roles","role_code",this,null,"<?php echo _("This role code that you entered already exists.Try another role code"); ?>",null)' />
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="landingPage" class="col-lg-4 control-label"><?php echo _("Landing Page"); ?></label>
									<div class="col-lg-7">
										<select class="form-control " name='landingPage' id='landingPage' title="<?php echo _('Please select landing page'); ?>">
											<option value=""> <?php echo _("-- Select --"); ?> </option>
											<option value="/dashboard/index.php"><?php echo _("Dashboard"); ?></option>
											<!--<option value="/import-result/addImportResult.php"><?php echo _("Import VL Result"); ?></option>-->
											<?php if (!empty($activeTestModules) && in_array('vl', $activeTestModules)) { ?>
												<option value="/vl/requests/addVlRequest.php"><?php echo _("Add New VL Request"); ?></option>
												<option value="/vl/requests/vlRequest.php"><?php echo _("VL View Test Requests"); ?></option>
											<?php }
											if (!empty($activeTestModules) && in_array('eid', $activeTestModules)) { ?>
												<option value="/eid/requests/eid-add-request.php"><?php echo _("Add New EID Request"); ?></option>
												<option value="/eid/requests/eid-requests.php"><?php echo _("EID View Test Requests"); ?></option>
											<?php }
											if (!empty($activeTestModules) && in_array('covid19', $activeTestModules)) { ?>
												<option value="/covid-19/requests/covid-19-add-request.php"><?php echo _("Add New Covid-19 Request"); ?></option>
												<option value="/covid-19/requests/covid-19-requests.php"><?php echo _("Covid-19 View Test Requests"); ?></option>
											<?php }
											if (!empty($activeTestModules) && in_array('hepatitis', $activeTestModules)) { ?>
												<option value="/hepatitis/requests/hepatitis-add-request.php"><?php echo _("Add New Hepatitis Request"); ?></option>
												<option value='/hepatitis/requests/hepatitis-requests.php'><?php echo _("Hepatitis View Test Requests"); ?></option>
											<?php }
											if (!empty($activeTestModules) && in_array('tb', $activeTestModules)) { ?>
												<option value="/tb/requests/tb-add-request.php"><?php echo _("Add New TB Request"); ?></option>
												<option value='/tb/requests/tb-requests.php'><?php echo _("TB View Test Requests"); ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="status" class="col-lg-4 control-label"><?php echo _("Status"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select class="form-control isRequired" name='status' id='status' title="<?php echo _('Please select the status'); ?>">
											<option value=""> <?php echo _("-- Select --"); ?> </option>
											<option value="active"><?php echo _("Active"); ?></option>
											<option value="inactive"><?php echo _("Inactive"); ?></option>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="accessType" class="col-lg-4 control-label"><?php echo _("Access Type"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select class="form-control isRequired" name='accessType' id='accessType' title="<?php echo _('Please select access type'); ?>">
											<option value=""> <?php echo _("-- Select --"); ?> </option>
											<option value="testing-lab"><?php echo _("Testing Lab"); ?></option>
											<option value="collection-site"><?php echo _("Collection Site"); ?></option>
										</select>
									</div>
								</div>
							</div>
						</div>
						<fieldset>
							<div class="form-group">
								<label class="col-sm-2 control-label"><?php echo _("Note"); ?>:</label>
								<div class="col-sm-10">
									<p class="form-control-static"><?php echo _('Unless you choose "access" the people belonging to this role will not be able to access other rights like "add", "edit" etc'); ?>.</p>
								</div>
							</div>
							<div class="form-group" style="padding-left:138px;">
								<div class="switch-field">

									<input type="radio" class='layCek' id="cekAllPrivileges" name='cekUnCekAll' value="yes" /></a>
									<label for="cekAllPrivileges"><?php echo _("Select All"); ?></label>
									<input type="radio" class='layCek' name='cekUnCekAll' id="unCekAllPrivileges" name="switch-one" value="no" /></a>
									<label for="unCekAllPrivileges"><?php echo _("Unselect All"); ?></label>
								</div>
							</div>
							<div class="bs-example bs-example-tabs">
								<ul id="myTab" class="nav nav-tabs" style="font-size:1.4em;">
									<?php
									$a = 0;

									foreach ($rInfo as $moduleRow) {
										$moduleName = ($moduleRow['module'] == 'generic-tests') ? "Lab Tests" : $moduleRow['module'];
										if ($a == 0)
											$cls = "active";
										else
											$cls = "";
									?>
										<li class="<?= $cls; ?>"><a href="#<?= $moduleRow['module']; ?>" data-toggle="tab" class="bg-primary"><?php echo strtoupper($moduleName); ?> </a></li>
									<?php
										$a++;
									}
									?>
								</ul>

								<div id="myTabContent" class="tab-content">
									<?php
									$b = 0;
									$j = 1;
									foreach ($rInfo as $moduleRow) {
										if ($b == 0)
											$tabCls = "active";
										else
											$tabCls = "";
										echo '<div class="tab-pane fade in ' . $tabCls . '" id="' . $moduleRow['module'] . '">';
										echo "<table aria-describedby='table' class='table table-striped responsive-utilities jambo_table'>";

										$moduleResources = explode("##", $moduleRow['module_resources']);
										$i = 1;
										foreach ($moduleResources as $mRes) {

											$mRes = explode(",", $mRes);

											echo "<tr>";
											echo "<th>";
									?>
											<small class="toggler">
												<h4 style="font-weight: bold;"><?= $mRes[1]; ?></h4>
												<div class="switch-field pull-right">
													<input type='radio' class='' id='all<?= $mRes[0]; ?>' name='<?= $mRes[1]; ?>' onclick='togglePrivilegesForThisResource("<?= $mRes[0]; ?>",true);'> <label for='all<?= $mRes[0]; ?>'><?php echo _("All"); ?></label>
													<input type='radio' class='' id='none<?= $mRes[0]; ?>' name='<?= $mRes[1]; ?>' onclick='togglePrivilegesForThisResource("<?= $mRes[0]; ?>",false);'> <label for='none<?= $mRes[0]; ?>'><?php echo _("None"); ?></label>
												</div>
											</small>
									<?php
											echo "</th>";
											echo "</tr>";
											$pQuery = "SELECT * FROM privileges WHERE resource_id='" . $mRes[0] . "' order by display_name ASC";
											$pInfo = $db->query($pQuery);
											echo "<tr class=''>";
											echo "<td style='text-align:center;vertical-align:middle;' class='privilegesNode' id='" . $mRes[0] . "'>";
											foreach ($pInfo as $privilege) {
												echo "<div class='col-lg-3' style='margin-top:5px;border:1px solid #eee;padding:10px;'>
                                  <strong>" . ($privilege['display_name']) . "</strong>
                                  <br>

								  <div class='switch-field' style='margin: 30px 0 36px 90px;'>
								  <input type='radio' class='cekAll layCek'  name='resource[" . $privilege['privilege_id'] . "]" . "' value='allow' id='radio-one" . $privilege['privilege_id'] . "'><label for='radio-one" . $privilege['privilege_id'] . "'>Yes</label>
								  <input type='radio' class='unCekAll layCek'  name='resource[" . $privilege['privilege_id'] . "]" . "' value='deny' id='radio-two" . $privilege['privilege_id'] . "'> <label for='radio-two" . $privilege['privilege_id'] . "'> No</label>
                            </div>
                                </div>";
											}
											echo "</td></tr>";
											$i++;
										}
										echo "</table></div>";
										$b++;
										$j++;
									}
									?>
								</div>

							</div>
					</div>
					<!-- /.box-body -->
					<div class=" box-footer">
						<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _("Submit"); ?></a>
						<a href="roles.php" class="btn btn-default"> <?php echo _("Cancel"); ?></a>
					</div>
					<!-- /.box-footer -->
				</form>
				<!-- /.row -->
			</div>
		</div>
		<!-- /.box -->

	</section>
	<!-- /.content -->
</div>


<script type="text/javascript">
	function validateNow() {
		flag = deforayValidator.init({
			formId: 'roleAddForm'
		});

		if (flag) {
			$.blockUI();
			document.getElementById('roleAddForm').submit();
		}
	}

	$("#cekAllPrivileges").click(function() {
		$('.unCekAll').prop('checked', false);
		$('.cekAll').prop('checked', true);
	});

	$("#unCekAllPrivileges").click(function() {
		$('.cekAll').prop('checked', false);
		$('.unCekAll').prop('checked', true);
	});

	function togglePrivilegesForThisResource(obj, checked) {
		if (checked == true) {
			$("#" + obj).find('.cekAll').prop('checked', true);
			$("#" + obj).find('.unCekAll').prop('checked', false);
		} else if (checked == false) {
			$("#" + obj).find('.cekAll').prop('checked', false);
			$("#" + obj).find('.unCekAll').prop('checked', true);
		}
	}

	function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
		var removeDots = obj.value.replace(/\./g, "");
		var removeDots = removeDots.replace(/\,/g, "");
		//str=obj.value;
		removeDots = removeDots.replace(/\s{2,}/g, ' ');

		$.post("/includes/checkDuplicate.php", {
				tableName: tableName,
				fieldName: fieldName,
				value: removeDots.trim(),
				fnct: fnct,
				format: "html"
			},
			function(data) {
				if (data === '1') {
					alert(alrt);
					document.getElementById(obj.id).value = "";
				}
			});
	}
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
