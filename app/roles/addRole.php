<?php
ob_start();
 
require_once(APPLICATION_PATH . '/header.php');

$activeModules = array('admin', 'common');

if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] == true) {
	$activeModules[] = 'vl';
}
if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] == true) {
	$activeModules[] = 'eid';
}
if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] == true) {
	$activeModules[] = 'covid19';
}
if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['hepatitis']['tb'] == true) {
	$activeModules[] = 'hepatitis';
}
if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] == true) {
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
		<h1><i class="fa-solid fa-user"></i> <?php echo _("Add Role");?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><i class="fa-solid fa-chart-pie"></i> <?php echo _("Home");?></a></li>
			<li class="active"><?php echo _("Add Role");?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?php echo _("indicates required field");?> &nbsp;</div>
			</div>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->
				<form class="form-horizontal" method='post' name='roleAddForm' id='roleAddForm' autocomplete="off" action="addRolesHelper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="userName" class="col-lg-4 control-label"><?php echo _("Role Name");?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="roleName" name="roleName" placeholder="<?php echo _('Role Name');?>" title="<?php echo _('Please enter a name for this role');?>" onblur='checkNameValidation("roles","role_name",this,null,"<?php echo _("This role name that you entered already exists.Try another role name");?>",null)' />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="email" class="col-lg-4 control-label"><?php echo _("Role Code");?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="roleCode" name="roleCode" placeholder="<?php echo _('Role Code');?>" title="<?php echo _('Please enter role code');?>" onblur='checkNameValidation("roles","role_code",this,null,"<?php echo _("This role code that you entered already exists.Try another role code");?>",null)' />
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="landingPage" class="col-lg-4 control-label"><?php echo _("Landing Page");?></label>
									<div class="col-lg-7">
										<select class="form-control " name='landingPage' id='landingPage' title="<?php echo _('Please select landing page');?>">
											<option value=""> <?php echo _("-- Select --");?> </option>
											<option value="/dashboard/index.php"><?php echo _("Dashboard");?></option>
											<option value="/vl/requests/addVlRequest.php"><?php echo _("Add New VL Request");?></option>
											<option value="import-result/addImportResult.php"><?php echo _("Import VL Result");?></option>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="status" class="col-lg-4 control-label"><?php echo _("Status");?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select class="form-control isRequired" name='status' id='status' title="<?php echo _('Please select the status');?>">
											<option value=""> <?php echo _("-- Select --");?> </option>
											<option value="active"><?php echo _("Active");?></option>
											<option value="inactive"><?php echo _("Inactive");?></option>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="accessType" class="col-lg-4 control-label"><?php echo _("Access Type");?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select class="form-control isRequired" name='accessType' id='accessType' title="<?php echo _('Please select access type');?>">
											<option value=""> <?php echo _("-- Select --");?> </option>
											<option value="testing-lab"><?php echo _("Testing Lab");?></option>
											<option value="collection-site"><?php echo _("Collection Site");?></option>
										</select>
									</div>
								</div>
							</div>
						</div>
						<fieldset>
							<div class="form-group">
								<label class="col-sm-2 control-label"><?php echo _("Note");?>:</label>
								<div class="col-sm-10">
									<p class="form-control-static"><?php echo _('Unless you choose "access" the people belonging to this role will not be able to access other rights like "add", "edit" etc');?>.</p>
								</div>
							</div>
							<div class="form-group" style="padding-left:138px;">
								<strong><?php echo _("Select All");?></strong> <a style="color: #333;" href="javascript:void(0);" id="cekAllPrivileges"><input type='radio' class='layCek' name='cekUnCekAll' /> <i class='fa fa-check'></i></a>
								&nbsp&nbsp&nbsp&nbsp<strong><?php echo _("Unselect All");?></strong> <a style="color: #333;" href="javascript:void(0);" id="unCekAllPrivileges"><input type='radio' class='layCek' name='cekUnCekAll' /> <i class='fa fa-times'></i></a>
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
										&nbsp;&nbsp;&nbsp;<input type='radio' class='' name='<?= $mRes[1]; ?>' onclick='togglePrivilegesForThisResource(<?= $mRes[0]; ?>,true);'> <?php echo _("All");?>
										&nbsp;&nbsp;&nbsp;<input type='radio' class='' name='<?= $mRes[1]; ?>' onclick='togglePrivilegesForThisResource(<?= $mRes[0]; ?>,false);'> <?php echo _("None");?>
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
						<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _("Submit");?></a>
						<a href="roles.php" class="btn btn-default"> <?php echo _("Cancel");?></a>
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
require_once(APPLICATION_PATH . '/footer.php');
?>