<?php
$title = _("Audit Trail");
require_once(APPLICATION_PATH . '/header.php');

if (isset($_POST['testType'])) {
	$tableName = $_POST['testType'];
	$sampleCode = $_POST['sampleCode'];
	$tableName2 = str_replace('audit_', '', $tableName);
} else {
	$tableName = "";
	$sampleCode = "";
	$tableName2 = "";
}

function getDifference($arr1, $arr2)
{
	$diff = array_merge(array_diff_assoc($arr1, $arr2), array_diff_assoc($arr2, $arr1));
	return $diff;
}

function getColumns($db, $tableName)
{
	$columns_sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND table_name=?";
	$result_column = $db->rawQuery($columns_sql, array(SYSTEM_CONFIG['dbName'], $tableName));
	return $result_column;
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
				WHERE sample_code = ? OR remote_sample_code = ?";
	$posts = $db->rawQuery($sql, array($sampleCode, $sampleCode));
	return $posts;
}
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><i class="fa-solid fa-pen-to-square"></i> <?php echo _("Audit Trail"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><i class="fa-solid fa-chart-pie"></i> <?php echo _("Home"); ?></a></li>
			<li class="active"><?php echo _("Audit Trail"); ?></li>
		</ol>
	</section>
	<!-- Main content -->
	<section class="content">
		<div class="row">

			<div class="col-xs-12">
				<div class="box">
					<form name="form1" action="audit-trail.php" method="post" id="searchForm">

						<table class="table" cellpadding="1" cellspacing="3" style="margin-left:1%;margin-top:20px;width:98%;">
							<tr>
								<td><b><?php echo _("Test Type"); ?>&nbsp;:</b></td>
								<td>
									<select style="width:220px;" class="form-control" id="testType" name="testType" title="<?php echo _('Type of Test'); ?>">
										<option value="">--Choose Test Type--</option>
										<option <?php if (isset($_POST['testType']) && $_POST['testType'] == "audit_form_vl") echo "selected='selected'"; ?> value="audit_form_vl">VL</option>
										<option <?php if (isset($_POST['testType']) && $_POST['testType'] == "audit_form_eid") echo "selected='selected'"; ?> value="audit_form_eid">EID</option>
										<option <?php if (isset($_POST['testType']) && $_POST['testType'] == "audit_form_covid19") echo "selected='selected'"; ?> value="audit_form_covid19">Covid-19</option>
										<option <?php if (isset($_POST['testType']) && $_POST['testType'] == "audit_form_hepatitis") echo "selected='selected'"; ?> value="audit_form_hepatitis">Hepatitis</option>
										<option <?php if (isset($_POST['testType']) && $_POST['testType'] == "audit_form_tb") echo "selected='selected'"; ?> value="audit_form_tb">TB</option>
									</select>
								</td>
								<td>&nbsp;<b><?php echo _("Sample Code"); ?>&nbsp;:</b></td>
								<td>
									<input type="text" value="<?php if (isset($_POST['sampleCode'])) echo $_POST['sampleCode'];
																else echo ""; ?>" name="sampleCode" id="sampleCode" class="form-control" />
								</td>

							<tr>
								<td colspan="4">&nbsp;<input type="submit" value="<?php echo _("Submit"); ?>" class="btn btn-success btn-sm">
									&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _("Reset"); ?></span></button>
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
							<h3> Audit Trail for Sample <?php echo $sampleCode; ?></h3>
							<table class="table table-striped table-hover">
								<thead>
									<tr>
										<?php
										$result_column = getColumns($db, $tableName);
										$col_arr = array();
										foreach ($result_column as $col) {
											$col_arr[] = $col['COLUMN_NAME'];
										?>
											<th>
												<?php //echo ucwords(str_replace('_',' ',$col['COLUMN_NAME'])); 
												echo $col['COLUMN_NAME'];
												?>
											</th>
										<?php } ?>
									</tr>
								</thead>
								<tbody>

									<?php


									if (count($posts) > 0) {
										for ($i = 0; $i < count($posts); $i++) {
											$k = ($i - 1);
											$arrDiff = getDifference($posts[$i], $posts[$k]);

									?>
											<tr>
												<?php

												for ($j = 0; $j < count($col_arr); $j++) {
												?>
													<td class="compare_col-<?php echo $i . '-' . $j; ?>">
														<?php
														if ($i > 0) {
															if (!empty($arrDiff[$col_arr[$j]]) && $arrDiff[$col_arr[$j]] != $posts[$i][$col_arr[$j]] && !empty($posts[$i][$col_arr[$j]])) {
																echo '<style type="text/css">
								.compare_col-' . $i . '-' . $j . ' {
								background: orange;
								color:black;
								}
								</style>';
															} else {
																echo '<style type="text/css">
								.compare_col-' . $i . '-' . $j . ' {
								background: white;
								color:black;
								}
								</style>';
															}
														}

														echo $posts[$i][$col_arr[$j]];
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

							<p>
							<h3> Current Record for Sample <?php echo $sampleCode; ?></h3>
							</p>
							<table class="table table-striped table-hover">
								<thead>
									<tr>
										<?php
										$result_column = getColumns($db, $tableName2);
										$col_arr = array();
										$posts = getColumnValues($db, $tableName2, $sampleCode);
										foreach ($result_column as $col) {
											$col_arr[] = $col['COLUMN_NAME'];
										?>
											<th>
												<?php //echo ucwords(str_replace('_',' ',$col['COLUMN_NAME'])); 
												echo $col['COLUMN_NAME'];
												?>
											</th>
										<?php } ?>
									</tr>
								</thead>
								<tbody>
									<?php


									if (count($posts) > 0) {
										for ($i = 0; $i < count($posts); $i++) {
											$k = ($i - 1);
											$arrDiff = getDifference($posts[$i], $posts[$k]);

									?>
											<tr>
												<?php

												for ($j = 0; $j < count($col_arr); $j++) {
												?>
													<td class="compare_col-<?php echo $i . '-' . $j; ?>">
														<?php
														if ($i > 0) {
															if (!empty($arrDiff[$col_arr[$j]]) && $arrDiff[$col_arr[$j]] != $posts[$i][$col_arr[$j]] && !empty($posts[$i][$col_arr[$j]])) {
																echo '<style type="text/css">
								.compare_col-' . $i . '-' . $j . ' {
								background: orange;
								color:black;
								}
								</style>';
															} else {
																echo '<style type="text/css">
								.compare_col-' . $i . '-' . $j . ' {
								background: white;
								color:black;
								}
								</style>';
															}
														}

														echo $posts[$i][$col_arr[$j]];
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
<style>
	.box-body {
		overflow: scroll;
	}

	.box-body td,
	.box-body th {
		border: 1px solid #999;
		padding: 20px;
	}

	td {
		background: white;
	}

	.primary {
		background-color: brown;
		position: sticky;
	}

	.box-body>th {
		background: white;
		font-size: 20px;
		color: black;
		border-radius: 0;
		top: 0;
		padding: 10px;
	}

	.box-body>tbody>tr:hover {
		background-color: #ffc107;
	}
</style>
<script>
	$(function() {
		$(".table").dataTable();
	});
</script>