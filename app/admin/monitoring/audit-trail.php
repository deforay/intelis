<style>
	.current {
		display: block;
		overflow-x: auto;
		white-space: nowrap;
	}
</style>
<?php
$title = _("Audit Trail");
require_once(APPLICATION_PATH . '/header.php');
$general = new \Vlsm\Models\General();

$activeTestModules = $general->getActiveTestModules();

if (isset($_POST['testType'])) {
	$tableName = $_POST['testType'];
	$sampleCode = $_POST['sampleCode'];
	$tableName2 = str_replace('audit_', '', $tableName);
} else {
	$tableName = "";
	$sampleCode = "";
	$tableName2 = "";
}

function getColumns($db, $tableName)
{
	$columnsSql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND table_name=?";
	return $db->rawQuery($columnsSql, array(SYSTEM_CONFIG['dbName'], $tableName));
}

function getColumnValues($db, $tableName, $sampleCode)
{
	$sql = "SELECT a.*, modifier.user_name as last_modified_by, creator.user_name as req_created_by,tester.user_name as tested_by,approver.user_name as result_approved_by,riewer.user_name as result_reviewed_by
				from $tableName as a 
				LEFT JOIN user_details as creator ON a.request_created_by = creator.user_id 
				LEFT JOIN user_details as modifier ON a.last_modified_by = modifier.user_id
				LEFT JOIN user_details as tester ON a.tested_by = tester.user_id 
				LEFT JOIN user_details as approver ON a.result_approved_by = approver.user_id
				LEFT JOIN user_details as riewer ON a.result_reviewed_by = riewer.user_id 
				WHERE sample_code = ? OR remote_sample_code = ? OR unique_id like ?";
	return $db->rawQuery($sql, array($sampleCode, $sampleCode, $sampleCode));
}
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-clock-rotate-left"></em> <?php echo _("Audit Trail"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
			<li class="active"><?php echo _("Audit Trail"); ?></li>
		</ol>
	</section>
	<!-- Main content -->
	<section class="content">
		<div class="row">

			<div class="col-xs-12">
				<div class="box">
					<form name="form1" action="audit-trail.php" method="post" id="searchForm">

						<table class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:98%;">
							<tr>
								<td><strong><?php echo _("Test Type"); ?>&nbsp;:</strong></td>
								<td>
									<select type="text" id="testType" name="testType" class="form-control" placeholder="<?php echo _('Please select the Test types'); ?>">
										<option value="">-- Choose Test Type--</option>
										<?php if (!empty($activeTestModules) && in_array('vl', $activeTestModules)) { ?>
											<option <?php echo (isset($_POST['testType']) && $_POST['testType'] == 'audit_form_vl') ? "selected='selected'" : ""; ?> value="audit_form_vl"><?php echo _("Viral Load"); ?></option>
										<?php }
										if (!empty($activeTestModules) && in_array('eid', $activeTestModules)) { ?>
											<option <?php echo (isset($_POST['testType']) && $_POST['testType'] == 'audit_form_eid') ? "selected='selected'" : ""; ?> value="audit_form_eid"><?php echo _("Early Infant Diagnosis"); ?></option>
										<?php }
										if (!empty($activeTestModules) && in_array('covid19', $activeTestModules)) { ?>
											<option <?php echo (isset($_POST['testType']) && $_POST['testType'] == 'audit_form_covid19') ? "selected='selected'" : ""; ?> value="audit_form_covid19"><?php echo _("Covid-19"); ?></option>
										<?php }
										if (!empty($activeTestModules) && in_array('hepatitis', $activeTestModules)) { ?>
											<option <?php echo (isset($_POST['testType']) && $_POST['testType'] == 'audit_form_hepatitis') ? "selected='selected'" : ""; ?> value='audit_form_hepatitis'><?php echo _("Hepatitis"); ?></option>
										<?php }
										if (!empty($activeTestModules) && in_array('tb', $activeTestModules)) { ?>
											<option <?php echo (isset($_POST['testType']) && $_POST['testType'] == 'audit_form_tb') ? "selected='selected'" : ""; ?> value='audit_form_tb'><?php echo _("TB"); ?></option>
										<?php } ?>
									</select>
								</td>
								<td>&nbsp;<strong><?php echo _("Sample Code"); ?>&nbsp;:</strong></td>
								<td>
									<input type="text" value="<?= htmlspecialchars($_POST['sampleCode']);  ?>" name="sampleCode" id="sampleCode" class="form-control" />
								</td>
							<tr>
								<td colspan="4">&nbsp;<input type="submit" value="<?php echo _("Submit"); ?>" class="btn btn-success btn-sm">
									&nbsp;<button type="reset" class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _("Reset"); ?></span></button>
								</td>
							</tr>
						</table>
					</form>
				</div>
			</div>
			<?php
			if (!empty($sampleCode)) {
				$posts = getColumnValues($db, $tableName, $sampleCode);
			?>
				<div class="col-xs-12">
					<div class="box">
						<!-- /.box-header -->
						<div class="box-body">
							<h3> Audit Trail for Sample <?php echo htmlspecialchars($sampleCode); ?></h3>
							<table id="auditTable" class="table-bordered table table-striped table-hover" aria-hidden="true">
								<thead>
									<tr>
										<?php
										$resultColumn = getColumns($db, $tableName);
										$colArr = array();
										foreach ($resultColumn as $col) {
											$colArr[] = $col['COLUMN_NAME'];
										?>
											<th>
												<?php
												echo $col['COLUMN_NAME'];
												?>
											</th>
										<?php } ?>
									</tr>
								</thead>
								<tbody>
									<?php
									if (!empty($posts)) {
										for ($i = 0; $i < count($posts); $i++) {
									?>
											<tr>
												<?php
												for ($j = 0; $j < count($colArr); $j++) {

													if (($j > 3) && ($i > 0) && $posts[$i][$colArr[$j]] != $posts[$i - 1][$colArr[$j]]) {
														echo '<td style="background: orange; color:black;" >' . $posts[$i][$colArr[$j]] . '</td>';
													} else {
														echo '<td>' . $posts[$i][$colArr[$j]] . '</td>';
													}
												?>
												<?php }
												?>
											</tr>
									<?php
										}
									} else {
										echo "<tr align='center'><td colspan='10'>No records available</td></tr>";
									}
									?>

								</tbody>

							</table>

							<p>
							<h3> Current Record for Sample <?php echo $sampleCode; ?></h3>
							</p>
							<table class="current table table-striped table-hover table-bordered" aria-hidden="true">
								<thead>
									<tr>
										<?php
										$resultColumn = getColumns($db, $tableName2);
										$posts = getColumnValues($db, $tableName2, $sampleCode);
										foreach ($resultColumn as $col) {
										?>
											<th>
												<?php
												echo $col['COLUMN_NAME'];
												?>
											</th>
										<?php } ?>
									</tr>
								</thead>
								<tbody>
									<?php
									if (!empty($posts)) {
										for ($i = 0; $i < count($posts); $i++) {
									?>
											<tr>
												<?php
												for ($j = 3; $j < count($colArr); $j++) {
												?>
													<td>
														<?php
														echo $posts[$i][$colArr[$j]];
														?>
													</td>
												<?php }
												?>
											</tr>
									<?php
										}
									} else {
										echo "<tr align='center'><td colspan='10'>No records available</td></tr>";
									}
									?>
								</tbody>

							</table>

						</div>
					</div>
					<!-- /.box -->
				</div>
				<!-- /.col -->
			<?php
			}
			?>
		</div>
		<!-- /.row -->
	</section>
	<!-- /.content -->
</div>

<?php
require_once(APPLICATION_PATH . '/footer.php');
?>

<script type="text/javascript">
	$(function() {
		$("#auditTable").DataTable({
			scrollY: '50vh',
			scrollX: true,
			scrollCollapse: true,
			paging: false,
			"aaSorting": [1, "asc"]
		});
	});
</script>