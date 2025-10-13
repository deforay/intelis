<?php

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\SystemService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

require_once APPLICATION_PATH . '/header.php';

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());
$id = (isset($_GET['id'])) ? base64_decode((string) $_GET['id']) : null;

$isSuperAdmin = false;
if ((int) $id === 1) {
	$isSuperAdmin = true;
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$roleQuery = "SELECT * FROM roles WHERE role_id= ?";
$roleInfo = $db->rawQueryOne($roleQuery, [$id]);
/* Not allowed to edit API role */
if (isset($roleInfo['role_code']) && $roleInfo['role_code'] == 'API') {
	header("Location:roles.php");
}
$activeModules = SystemService::getActiveModules();

$resourcesQuery = "SELECT module,
					GROUP_CONCAT( DISTINCT CONCAT(resources.resource_id,',',resources.display_name)
									ORDER BY resources.display_name SEPARATOR '##' ) as 'module_resources'
					FROM `resources`
					WHERE `module` IN ('" . implode("','", $activeModules) . "')
					GROUP BY `module` ORDER BY `module` ASC";
$rInfo = $db->query($resourcesQuery);

$priQuery = "SELECT * from roles_privileges_map where role_id=$id";
$priInfo = $db->query($priQuery);
$priId = [];
if ($priInfo) {
	foreach ($priInfo as $id) {
		$priId[] = $id['privilege_id'];
	}
}
?>
<style>
	.labelName {
		font-size: 13px;
	}

	.privilege-switch {
		display: flex;
		overflow: hidden;
	}

	.privilege-switch input {
		position: absolute !important;
		clip: rect(0, 0, 0, 0);
		height: 1px;
		width: 1px;
		border: 0;
		overflow: hidden;
	}

	.privilege-switch label {
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

	.privilege-switch label:hover {
		cursor: pointer;
	}

	.privilege-switch input:checked+label {
		/*background-color: #87CEFA;*/
		box-shadow: none;
	}

	.privilege-switch label:first-of-type {
		border-radius: 4px 0 0 4px;
	}

	.privilege-switch label:last-of-type {
		border-radius: 0 4px 4px 0;
	}

	.deny-label {
		background-color: #d9534f !important;
		color: white !important;
	}

	.allow-label {
		background-color: #398439 !important;
		color: white !important;
	}

	.normal-label {
		background-color: #e4e4e4 !important;
		color: black !important;
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
		<h1><em class="fa-solid fa-user"></em>
			<?php echo _translate("Edit Role"); ?>
		</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em>
					<?php echo _translate("Home"); ?>
				</a></li>
			<li class="active">
				<?php echo _translate("Roles"); ?>
			</li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span>
					<?php echo _translate("indicates required fields"); ?> &nbsp;
				</div>
			</div>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->
				<form class="form-horizontal" method='post' name='roleEditForm' id='roleEditForm' autocomplete="off" action="editRolesHelper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="userName" class="col-lg-4 control-label">
										<?php echo _translate("Role Name"); ?> <span class="mandatory">*</span>
									</label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="roleName" name="roleName" placeholder="<?php echo _translate('Role Name'); ?>" title="<?php echo _translate('Please enter a name for this role'); ?>" value="<?php echo $roleInfo['role_name']; ?>" onblur="checkNameValidation('roles','role_name',this,'<?php echo "role_id##" . $roleInfo['role_id']; ?>','<?php echo _translate("This role name that you entered already exists.Try another role name"); ?>',null)" <?= ($isSuperAdmin) ? 'readonly="readonly"' : ''; ?> />
										<input type="hidden" name="roleId" id="roleId" value="<?php echo base64_encode((string) $roleInfo['role_id']); ?>" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="email" class="col-lg-4 control-label">
										<?php echo _translate("Role Code"); ?> <span class="mandatory">*</span>
									</label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="roleCode" name="roleCode" placeholder="<?php echo _translate('Role Code'); ?>" title="<?php echo _translate('Please enter role code'); ?>" value="<?php echo $roleInfo['role_code']; ?>" onblur="checkNameValidation('roles','role_code',this,'<?php echo "role_id##" . $roleInfo['role_id']; ?>','<?php echo _translate("This role code that you entered already exists.Try another role code"); ?>',null)" <?= ($isSuperAdmin) ? 'readonly="readonly"' : ''; ?> />
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="landingPage" class="col-lg-4 control-label">
										<?php echo _translate("Landing Page"); ?>
									</label>
									<div class="col-lg-7">
										<select class="form-control " name='landingPage' id='landingPage' title="<?php echo _translate('Please select landing page'); ?>" <?= ($isSuperAdmin) ? 'readonly="readonly"' : ''; ?>>
											<option value="">
												<?php echo _translate("-- Select --"); ?>
											</option>
											<option value="/dashboard/index.php" <?php echo ($roleInfo['landing_page'] == '/dashboard/index.php') ? "selected='selected'" : "" ?>><?php echo _translate("Dashboard"); ?></option>
											<option value="/vl/requests/addVlRequest.php" <?php echo ($roleInfo['landing_page'] == '/vl/requests/addVlRequest.php') ? "selected='selected'" : "" ?>><?php echo _translate("Add New VL Request"); ?>
											</option>
											<option value="/import-result/import-file.php?t=vl" <?php echo ($roleInfo['landing_page'] == 'import-result/import-file.php?t=vl') ? "selected='selected'" : "" ?>><?php echo _translate("Import VL Result"); ?>
											</option>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="status" class="col-lg-4 control-label">
										<?php echo _translate("Status"); ?> <span class="mandatory">*</span>
									</label>
									<div class="col-lg-7">
										<select class="form-control isRequired" name='status' id='status' title="<?php echo _translate('Please select the status'); ?>" <?= ($isSuperAdmin) ? 'readonly="readonly"' : ''; ?>>
											<option value="">
												<?php echo _translate("-- Select --"); ?>
											</option>
											<option value="active" <?php echo ($roleInfo['status'] == 'active') ? "selected='selected'" : "" ?>><?php echo _translate("Active"); ?></option>
											<option value="inactive" <?php echo ($roleInfo['status'] == 'inactive') ? "selected='selected'" : "" ?>><?php echo _translate("Inactive"); ?></option>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="accessType" class="col-lg-4 control-label">
										<?php echo _translate("Access Type"); ?> <span class="mandatory">*</span>
									</label>
									<div class="col-lg-7">
										<select class="form-control isRequired" name='accessType' id='accessType' title="<?php echo _translate('Please select access type'); ?>">
											<option value="">
												<?php echo _translate("-- Select --"); ?>
											</option>
											<option value="testing-lab" <?php echo ($roleInfo['access_type'] == 'testing-lab') ? "selected='selected'" : "" ?>><?php echo _translate("Testing Lab"); ?></option>
											<option value="collection-site" <?php echo ($roleInfo['access_type'] == 'collection-site') ? "selected='selected'" : "" ?>><?php echo _translate("Collection Site"); ?></option>
										</select>
									</div>
								</div>
							</div>
						</div>
						<fieldset>
							<div class="form-group">
								<label class="col-sm-2 control-label">
									<?php echo _translate("Note"); ?>:
								</label>
								<div class="col-sm-10">
									<p class="form-control-static">
										<?php echo _translate('Unless you choose the access privilege, the people belonging to this role will not be able to access other privileges like add, edit etc.'); ?>.
									</p>
								</div>
							</div>
							<div class="form-group" style="padding-left:138px;">
								<div class="privilege-switch super-switch">

									<input type="radio" class='' id="allowAllPrivileges" name="allPrivilegesRadio" value="yes" /></a>
									<label for="allowAllPrivileges">
										<?php echo _translate("Select All"); ?>
									</label>
									<input type="radio" class='' name="allPrivilegesRadio" id="denyAllPrivileges" name="switch-one" value="no" /></a>
									<label for="denyAllPrivileges">
										<?php echo _translate("Unselect All"); ?>
									</label>
								</div>
							</div>

							<div class="row">
								<div class="col-md-12 col-lg-12">
									<input type="text" class="form-control " id="searchInput" placeholder="Search Permissions..." onkeyup="searchPermissions()">
								</div>
							</div>
							<br>
							<div class="bs bs-tabs">
								<ul id="myTab" class="nav nav-tabs" style="font-size:1.4em;">
									<?php
									$a = 0;

									foreach ($rInfo as $moduleRow) {
										$moduleName = ($moduleRow['module'] == 'generic-tests') ? "Other Lab Tests" : $moduleRow['module'];
										if ($a == 0) {
											$liClass = "active";
										} else {
											$liClass = "";
										}
									?>
										<li class="<?= $liClass; ?>"><a href="#<?= $moduleRow['module']; ?>" data-toggle="tab" class="bg-primary"><?php echo strtoupper((string) $moduleName); ?> </a></li>
									<?php
										$a++;
									} ?>
								</ul>

								<div id="myTabContent" class="tab-content">
									<?php
									$b = 0;
									$j = 1;
									foreach ($rInfo as $moduleRow) {
										if ($b == 0) {
											$tabCls = "active";
										} else {
											$tabCls = "";
										}
										echo '<div class="tab-pane fade in ' . $tabCls . '" id="' . $moduleRow['module'] . '">';
										echo "<table aria-describedby='table' class='table table-striped responsive-utilities jambo_table'>";

										$moduleResources = explode("##", (string) $moduleRow['module_resources']);
										$i = 1;
										foreach ($moduleResources as $mRes) {

											$mRes = explode(",", $mRes);

											echo "<tr class ='togglerTr'>";
											echo "<th>";

									?>
											<small class="toggler">
												<h4 style="font-weight: bold;">
													<?= $mRes[1]; ?>
												</h4>
												<div class="super-switch privilege-switch pull-right">
													<input type='radio' class='' id='all<?= $mRes[0]; ?>' name='<?= $mRes[1]; ?>' onclick='togglePrivilegesForThisResource("<?= $mRes[0]; ?>",true);'>
													<label for='all<?= $mRes[0]; ?>'><?php echo _translate("All"); ?></label>
													<input type='radio' class='' id='none<?= $mRes[0]; ?>' name='<?= $mRes[1]; ?>' onclick='togglePrivilegesForThisResource("<?= $mRes[0]; ?>",false);'>
													<label for='none<?= $mRes[0]; ?>'><?php echo _translate("None"); ?></label>
												</div>
											</small>
									<?php
											echo "</th>";
											echo "</tr>";

											$mode = " AND (show_mode like 'always')";
											if ($general->isSTSInstance()) {
												$mode = " AND (show_mode like 'sts' or show_mode like 'always')";
											} elseif ($general->isLISInstance()) {
												$mode = " AND (show_mode like 'lis' or show_mode like 'always')";
											}

											$pQuery = "SELECT * FROM privileges WHERE resource_id= ? $mode ORDER BY display_order ASC";
											$pInfo = $db->rawQuery($pQuery, [$mRes[0]]);
											echo "<tr class='permissionTr'>";
											echo "<td style='text-align:center;vertical-align:middle;' class='privilegesNode' id='" . $mRes[0] . "'>";

											foreach ($pInfo as $privilege) {
												if (in_array($privilege['privilege_id'], $priId)) {
													$allowChecked = " checked='' ";
													$denyChecked = "";
													$allowStyle = "allow-label";
													$denyStyle = "";
												} else {
													$denyChecked = " checked='' ";
													$allowChecked = "";
													$denyStyle = "deny-label";
													$allowStyle = "";
												}
												echo "<div class='col-lg-3 privilege-div' data-privilegeid='" . $privilege['privilege_id'] . "' id='div" . $privilege['privilege_id'] . "'>
														<strong class='privilege-label' data-privilegeid='" . $privilege['privilege_id'] . "' id='label" . $privilege['privilege_id'] . "'>" . _translate($privilege['display_name']) . "</strong>
														<br>

														<div class='privilege-switch' data-privilegeid='" . $privilege['privilege_id'] . "' id='switch" . $privilege['privilege_id'] . "' style='margin: 30px 0 36px 90px;'>
															<input type='radio' class='selectPrivilege' name='resource[" . $privilege['privilege_id'] . "]" . "' value='allow' id='selectPrivilege" . $privilege['privilege_id'] . "' $allowChecked ><label for='selectPrivilege" . $privilege['privilege_id'] . "' class='$allowStyle'>Yes</label>
															<input type='radio' class='unselectPrivilege' name='resource[" . $privilege['privilege_id'] . "]" . "' value='deny' id='unselectPrivilege" . $privilege['privilege_id'] . "' $denyChecked > <label for='unselectPrivilege" . $privilege['privilege_id'] . "' class='$denyStyle'> No</label>
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
					<div class="box-footer">
						<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">
							<?php echo _translate("Submit"); ?>
						</a>
						<a href="roles.php" class="btn btn-default">
							<?php echo _translate("Cancel"); ?>
						</a>
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
			formId: 'roleEditForm'
		});

		if (flag) {
			$.blockUI();
			document.getElementById('roleEditForm').submit();
		}
	}

	$("#allowAllPrivileges").click(function() {
		$('.unselectPrivilege').prop('checked', false);
		$('.selectPrivilege').prop('checked', true);
		$('.unselectPrivilege').next('label').addClass('normal-label');
		$('.selectPrivilege').next('label').addClass('allow-label');
		$(this).next('label').addClass('allow-label');
		$("#denyAllPrivileges").next('label').addClass('normal-label');

		$('.unselectPrivilege').next('label').removeClass('deny-label');
		$('.selectPrivilege').next('label').removeClass('normal-label');
		$(this).next('label').removeClass('deny-label');
		$("#allowAllPrivileges").next('label').removeClass('normal-label');
	});

	$("#denyAllPrivileges").click(function() {
		$('.selectPrivilege').prop('checked', false);
		$('.unselectPrivilege').prop('checked', true);
		$('.unselectPrivilege').next('label').addClass('deny-label');
		$('.selectPrivilege').next('label').addClass('normal-label');
		$(this).next('label').addClass('deny-label');
		$("#allowAllPrivileges").next('label').addClass('normal-label');

		$('.unselectPrivilege').next('label').removeClass('normal-label');
		$('.selectPrivilege').next('label').removeClass('allow-label');
		$(this).next('label').removeClass('allow-label');
		$("#denyAllPrivileges").next('label').removeClass('normal-label');

	});


	$('.privilege-switch input').click(function() {
		val = $(this).val();
		if (val == "deny") {
			$(this).closest('.privilege-switch').find('.unselectPrivilege').next('label').addClass('deny-label');
			$(this).closest('.privilege-switch').find('.selectPrivilege').next('label').addClass('normal-label');
			$(this).closest('.privilege-switch').find('.unselectPrivilege').next('label').removeClass('normal-label');
			$(this).closest('.privilege-switch').find('.selectPrivilege').next('label').removeClass('allow-label');
			//$(this).closest('.privilege-switch').find('.unselectPrivilege').next('label').css('background-color', '#d9534f');
			//$(this).closest('.privilege-switch').find('.selectPrivilege').next('label').css('background-color', '#e4e4e4');
		} else if (val == "allow") {
			$(this).closest('.privilege-switch').find('.unselectPrivilege').next('label').addClass('normal-label');
			$(this).closest('.privilege-switch').find('.selectPrivilege').next('label').addClass('allow-label');
			$(this).closest('.privilege-switch').find('.unselectPrivilege').next('label').removeClass('deny-label');
			$(this).closest('.privilege-switch').find('.selectPrivilege').next('label').removeClass('normal-label');
		}
	});




	function togglePrivilegesForThisResource(obj, checked) {
		if (checked == true) {
			$("#" + obj).find('.selectPrivilege').prop('checked', true);
			$("#" + obj).find('.unselectPrivilege').prop('checked', false);
			$("#" + obj).find('.unselectPrivilege').next('label').addClass('normal-label');
			$("#" + obj).find('.selectPrivilege').next('label').addClass('allow-label');
			$("#all" + obj).next('label').addClass('allow-label');
			$("#none" + obj).next('label').addClass('normal-label');

			$("#" + obj).find('.unselectPrivilege').next('label').removeClass('deny-label');
			$("#" + obj).find('.selectPrivilege').next('label').removeClass('normal-label');
			$("#all" + obj).next('label').removeClass('normal-label');
			$("#none" + obj).next('label').removeClass('deny-label');

		} else if (checked == false) {
			$("#" + obj).find('.selectPrivilege').prop('checked', false);
			$("#" + obj).find('.unselectPrivilege').prop('checked', true);
			$("#" + obj).find('.unselectPrivilege').next('label').addClass('deny-label');
			$("#" + obj).find('.selectPrivilege').next('label').addClass('normal-label');
			$("#all" + obj).next('label').addClass('normal-label');
			$("#none" + obj).next('label').addClass('deny-label');

			$("#" + obj).find('.unselectPrivilege').next('label').removeClass('normal-label');
			$("#" + obj).find('.selectPrivilege').next('label').removeClass('allow-label');
			$("#all" + obj).next('label').removeClass('allow-label');
			$("#none" + obj).next('label').removeClass('normal-label');

		}
	}

	function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
		var removeDots = obj.value.replace(/\./g, "");
		removeDots = removeDots.replace(/\,/g, "");
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


	function searchPermissions() {
		let filter = $('#searchInput').val().toUpperCase();

		// Handle visibility of the super-switch elements
		if (filter) {
			$('.super-switch').hide();
		} else {
			$('.super-switch').show();
		}

		// Iterate through each tab
		$('#myTabContent .tab-pane').each(function() {
			let $tab = $(this);
			let $tabLink = $('#myTab a[href="#' + $tab.attr('id') + '"]').closest('li');

			// Remove any previous hiddenTr class
			$tab.find('.hiddenTr').removeClass('hiddenTr');

			// Iterate through each togglerTr in the current tab
			$tab.find('.togglerTr').each(function() {
				let $togglerTr = $(this);
				let $nextPermissionTr = $togglerTr.next('tr');
				let togglerText = $togglerTr.find('h4').text().toUpperCase();

				if (togglerText.indexOf(filter) > -1) {
					$togglerTr.show();
					$nextPermissionTr.show().removeClass('hiddenTr');
					$nextPermissionTr.find('.privilege-div').show();
					$nextPermissionTr.find('.privilege-label').addClass('highlight');
				} else {
					let hasVisiblePrivilege = false;

					$nextPermissionTr.find('.privilege-label').each(function() {
						let $label = $(this);
						let labelText = $label.text().toUpperCase();
						let $parentDiv = $label.closest('div.privilege-div');

						if (labelText.indexOf(filter) > -1) {
							$parentDiv.show();
							$label.addClass('highlight');
							hasVisiblePrivilege = true;
						} else {
							$parentDiv.hide();
							$label.removeClass('highlight');
						}
					});

					if (hasVisiblePrivilege) {
						$togglerTr.show();
						$nextPermissionTr.show().removeClass('hiddenTr');
					} else {
						$togglerTr.hide();
						$nextPermissionTr.hide().addClass('hiddenTr');
					}
				}
			});

			// Adjust tab visibility based on the search result
			let hiddenTrsCount = $tab.find('.hiddenTr').length;
			let totalTrsCount = $tab.find('.permissionTr').length;

			if (filter === '') {
				$tabLink.show();
				$tab.find('tr').show().removeClass('hiddenTr');
			} else if (hiddenTrsCount === totalTrsCount) {
				$tabLink.hide();
			} else {
				$tabLink.show();
			}

			let firstVisibleTabLink = $('#myTab li:visible:first a');
			if (firstVisibleTabLink.length > 0) {
				// Deactivate all tabs
				$('#myTab li').removeClass('active');
				$('#myTabContent .tab-pane').removeClass('active in');

				// Activate the first visible tab
				firstVisibleTabLink.tab('show');
			}
		});
	}
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
