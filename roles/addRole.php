<?php
ob_start();
#require_once('../startup.php'); 
include_once(APPLICATION_PATH . '/header.php');

$activeModules = array('admin', 'common');

if (isset($systemConfig['modules']['vl']) && $systemConfig['modules']['vl'] == true) {
	$activeModules[] = 'vl';
}
if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] == true) {
	$activeModules[] = 'eid';
}
if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] == true) {
	$activeModules[] = 'covid19';
}
if (isset($systemConfig['modules']['hepatitis']) && $systemConfig['hepatitis']['tb'] == true) {
	$activeModules[] = 'hepatitis';
}
if (isset($systemConfig['modules']['tb']) && $systemConfig['modules']['tb'] == true) {
	$activeModules[] = 'tb';
}


$resourcesQuery = "SELECT module, GROUP_CONCAT( DISTINCT CONCAT(resources.resource_id,',',resources.display_name) ORDER BY resources.display_name SEPARATOR '##' ) as 'module_resources' FROM `resources` WHERE `module` IN ('" . implode("','", $activeModules) . "') GROUP BY `module` ORDER BY `module` ASC";
$rInfo = $db->query($resourcesQuery);
?>
<style>
	.labelName {
		font-size: 13px;
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><i class="fa fa-user"></i> Add Role</h1>
		<ol class="breadcrumb">
			<li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>
			<li class="active">Add Role</li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indicates required field &nbsp;</div>
			</div>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->
				<form class="form-horizontal" method='post' name='roleAddForm' id='roleAddForm' autocomplete="off" action="addRolesHelper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="userName" class="col-lg-4 control-label">Role Name <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="roleName" name="roleName" placeholder="Role Name" title="Please enter user name" onblur="checkNameValidation('roles','role_name',this,null,'This role name that you entered already exists.Try another role name',null)" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="email" class="col-lg-4 control-label">Role Code <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="roleCode" name="roleCode" placeholder="Role Code" title="Please enter role code" onblur="checkNameValidation('roles','role_code',this,null,'This role code that you entered already exists.Try another role code',null)" />
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="landingPage" class="col-lg-4 control-label">Landing Page</label>
									<div class="col-lg-7">
										<select class="form-control " name='landingPage' id='landingPage' title="Please select landing page">
											<option value=""> -- Select -- </option>
											<option value="dashboard/index.php">Dashboard</option>
											<option value="/vl/requests/addVlRequest.php">Add New Request</option>
											<option value="import-result/addImportResult.php">Add Import Result</option>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="status" class="col-lg-4 control-label">Status <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select class="form-control isRequired" name='status' id='status' title="Please select the status">
											<option value=""> -- Select -- </option>
											<option value="active">Active</option>
											<option value="inactive">Inactive</option>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="accessType" class="col-lg-4 control-label">Access Type <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select class="form-control isRequired" name='accessType' id='accessType' title="Please select access type">
											<option value=""> -- Select -- </option>
											<option value="testing-lab">Testing Lab</option>
											<option value="collection-site">Collection Site</option>
										</select>
									</div>
								</div>
							</div>
						</div>
						<fieldset>
							<div class="form-group">
								<label class="col-sm-2 control-label">Note:</label>
								<div class="col-sm-10">
									<p class="form-control-static">Unless you choose "access" the people belonging to this role will not be able to access other rights like "add", "edit" etc.</p>
								</div>
							</div>
							<div class="form-group" style="padding-left:138px;">
								<strong>Select All</strong> <a style="color: #333;" href="javascript:void(0);" id="cekAllPrivileges"><input type='radio' class='layCek' name='cekUnCekAll' /> <i class='fa fa-check'></i></a>
								&nbsp&nbsp&nbsp&nbsp<strong>Unselect All</strong> <a style="color: #333;" href="javascript:void(0);" id="unCekAllPrivileges"><input type='radio' class='layCek' name='cekUnCekAll' /> <i class='fa fa-times'></i></a>
							</div>

							<?php
							foreach ($rInfo as $moduleRow) {
								echo "<table class='table table-striped responsive-utilities jambo_table'>";
								echo "<tr><th class='bg-primary'><h3>" . strtoupper($moduleRow['module']) . "</h3></th></tr>";

								$moduleResources = explode("##", $moduleRow['module_resources']);

								foreach ($moduleResources as $mRes) {

									$mRes = explode(",", $mRes);

									echo "<tr>";
									echo "<th><h4>";
									echo ($mRes[1]);
							?>
									<small class="pull-right toggler">
										&nbsp;&nbsp;&nbsp;<input type='radio' class='' name='<?= $mRes[1]; ?>' onclick='togglePrivilegesForThisResource(<?= $mRes[0]; ?>,true);'> All
										&nbsp;&nbsp;&nbsp;<input type='radio' class='' name='<?= $mRes[1]; ?>' onclick='togglePrivilegesForThisResource(<?= $mRes[0]; ?>,false);'> None
									</small>
							<?php
									echo "</h4></th>";
									echo "</tr>";
									$pQuery = "SELECT * FROM privileges WHERE resource_id='" . $mRes[0] . "' order by display_name ASC";
									$pInfo = $db->query($pQuery);
									echo "<tr class=''>";
									echo "<td style='text-align:center;vertical-align:middle;' class='privilegesNode' id='" . $mRes[0] . "'>";
									foreach ($pInfo as $privilege) {
										echo "<div class='col-lg-3' style='margin-top:5px;border:1px solid #eee;padding:10px;'>
                                  <label class='labelName'>" . ($privilege['display_name']) . "</label>
                                  <br>
                                  <input type='radio' class='cekAll layCek'  name='resource[" . $privilege['privilege_id'] . "]" . "' value='allow' > <i class='fa fa-check'></i>
                                  <input type='radio' class='unCekAll layCek'  name='resource[" . $privilege['privilege_id'] . "]" . "' value='deny' >  <i class='fa fa-times'></i>
                            
                                </div>";
									}
									echo "</td></tr>";
								}
								echo "</table>";
							}
							?>

						</fieldset>


					</div>
					<!-- /.box-body -->
					<div class=" box-footer">
						<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Submit</a>
						<a href="roles.php" class="btn btn-default"> Cancel</a>
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
include(APPLICATION_PATH . '/footer.php');
?>