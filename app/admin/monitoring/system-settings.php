<?php

use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\SystemService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

$title = _translate("System Settings");
require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var SystemService $systemService */
$systemService = ContainerRegistry::get(SystemService::class);

/** @var UsersService $userService */
$userService = ContainerRegistry::get(UsersService::class);


$serverSettings = $systemService->getServerSettings();
$folderPermissions = $systemService->checkFolderPermissions();

$activeUsers = $userService->getActiveUsers();
$noOfActiveUsers = count($activeUsers);

$userQry = "SELECT * FROM activity_log WHERE DATE(date_time) = CURDATE()";
$userResult = $db->rawQuery($userQry);
$noOfUsersLoggedInToday = count($userResult);


$labId = $_SESSION['instance']['labId'];
//echo $labId; die;
$sQuery = "SELECT * FROM s_vlsm_instance";
$syncInfo = $db->rawQueryOne($sQuery);

$subQuery = "SELECT f.facility_id,
                f.facility_name,
                tar.requested_on,
                (facility_attributes->>'$.version') as `version`,
                (facility_attributes->>'$.lastHeartBeat') as `lastHeartBeat`,
                (facility_attributes->>'$.lastResultsSync') as `lastResultsSync`,
                (facility_attributes->>'$.lastRequestsSync') as `lastRequestsSync`,
                GREATEST(
                    COALESCE(facility_attributes->>'$.lastHeartBeat', 0),
                    COALESCE(facility_attributes->>'$.lastResultsSync', 0),
                    COALESCE(facility_attributes->>'$.lastRequestsSync', 0),
                    COALESCE(tar.requested_on, 0)
                ) as `latest`
            FROM `facility_details`as f
            LEFT JOIN track_api_requests as tar ON tar.facility_id = f.facility_id
            LEFT JOIN testing_labs as lab ON lab.facility_id = f.facility_id WHERE f.facility_type = 2 AND f.status = 'active' GROUP BY f.facility_id";


$mainQuery = "SELECT main_query.*,
                CASE
                    WHEN latest > DATE_SUB(NOW(), INTERVAL 2 WEEK) THEN 'green'
                    WHEN latest <= DATE_SUB(NOW(), INTERVAL 4 WEEK) THEN 'red'
                    WHEN latest <= DATE_SUB(NOW(), INTERVAL 2 WEEK) THEN 'yellow'
                    ELSE 'red'
                END AS color
                FROM ($subQuery) as main_query";


$labSyncInfo = $db->rawQueryGenerator($mainQuery);
$c = 0;
foreach ($labSyncInfo as $aRow) {
  $color = $aRow['color'];
  if ($color == 'green') {
    $c++;
  }
}

$apiTrack = "SELECT * FROM track_api_requests WHERE response_data IS NOT NULL AND DATE(requested_on) = CURDATE()";
$apiTrackResult = $db->rawQuery($userQry);
$apiTrackResultCount = count($apiTrackResult);
?>
<style>
  .current {
    display: block;
    overflow-x: auto;
    white-space: nowrap;
  }
</style>
<link href="/assets/css/multi-select.css" rel="stylesheet" />
<link href="/assets/css/buttons.dataTables.min.css" rel="stylesheet" />


<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><em class="fa-solid fa-display"></em> <?php echo _translate("System Settings"); ?></h1>
    <ol class="breadcrumb">
      <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
      <li class="active"><?php echo _translate("System Settings"); ?></li>
    </ol>
  </section>
  <!-- Main content -->
  <section class="content">
    <div class="row">

      <div class="col-md-3">
        <div class="box box-solid box-info">
          <div class="box-header">
            <h3 class="box-title">No. of Active Users</h3>
          </div>
          <div class="box-body">
            <?php echo $noOfActiveUsers; ?>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="box box-solid box-info">
          <div class="box-header">
            <h3 class="box-title">No. of Users Logged in Today</h3>
          </div>
          <div class="box-body">
            <?php echo $noOfUsersLoggedInToday; ?>
          </div>
        </div>
      </div>


      <?php if ($general->isLISInstance()) { ?>

        <div class="col-md-3">
          <div class="box box-solid box-info">
            <div class="box-header">
              <h3 class="box-title">Last STS Sync</h3>
            </div>
            <div class="box-body">
              <?php echo DateUtility::humanReadableDateFormat($syncInfo['last_remote_requests_sync']); ?>
            </div>
          </div>
        </div>
        <?php if ($syncInfo['vl_last_dash_sync'] != "") { ?>
          <div class="col-md-3">
            <div class="box box-solid box-info">
              <div class="box-header">
                <h3 class="box-title">Last VL Dashboard Sync</h3>
              </div>
              <div class="box-body">
                <?php echo DateUtility::humanReadableDateFormat($syncInfo['vl_last_dash_sync']); ?>
              </div>
            </div>
          </div>
      <?php }
      } ?>

      <?php if ($general->isSTSInstance()) { ?>

        <div class="col-md-3">
          <div class="box box-solid box-danger">
            <div class="box-header">
              <h3 class="box-title">Number of Labs synced today</h3>
            </div>
            <div class="box-body">
              <?php echo $c; ?>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="box box-solid box-danger">
            <div class="box-header">
              <h3 class="box-title">Number of API calls responded today</h3>
            </div>
            <div class="box-body">
              <?php echo $apiTrackResultCount; ?>
            </div>
          </div>
        </div>
      <?php } ?>


      <div class="col-xs-12">
        <div class="box">
          <!-- /.box-header -->
          <div class="box-body">

            <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
              <tbody>
                <tr>
                  <th colspan="5">
                    <h4><?= _translate("Server Settings"); ?></h4>
                  </th>
                </tr>
                <tr>
                  <th scope="row"><?= _translate("Memory Limit"); ?></th>
                  <td>
                    <?php echo $serverSettings['memory_limit']; ?></td>
                  <th scope="row"><?= _translate("Maximum Upload Filesize"); ?></th>
                  <td><?php echo $serverSettings['upload_max_filesize']; ?></td>
                </tr>
                <tr>
                  <th scope="row"><?= _translate("Maximum POST size"); ?></th>
                  <td><?php echo $serverSettings['post_max_size']; ?></td>
                  <th scope="row"><?= _translate("Maximum Time of Execution"); ?></th>
                  <td><?php echo $serverSettings['max_execution_time']; ?></td>
                </tr>
                <tr>
                  <th scope="row"><?= _translate("Max time to parse input data"); ?></th>
                  <td><?php echo $serverSettings['max_input_time']; ?></td>
                  <th scope="row"><?= _translate("Config to show Error in web pages"); ?></th>
                  <td><?php echo $serverSettings['display_errors']; ?></td>
                </tr>
                <tr>
                  <th scope="row"><?= _translate("Specifies which errors are reported"); ?></th>
                  <td><?php echo $serverSettings['error_reporting']; ?></td>
                </tr>
                <tr>
                  <th colspan="5">
                    <h4><?= _translate("Folder Permission Settings"); ?></h4>
                  </th>
                </tr>
                <tr>
                  <th><?= _translate("File Path"); ?></th>
                  <th><?= _translate("Exists"); ?></th>
                  <th><?= _translate("Readable / Writeable"); ?></th>
                </tr>
                <tr>
                  <td scope="row"><?php echo CACHE_PATH; ?></td>
                  <td>
                    <?php
                    echo ($folderPermissions['CACHE_PATH']['exists'] == 1) ? "Yes" : "No";
                    ?>
                  </td>
                  <td scope="row"><?php echo ($folderPermissions['CACHE_PATH']['readable'] == 1) ? "Yes" : "No"; ?>
                    <?php echo ($folderPermissions['CACHE_PATH']['writable'] == 1) ? " / Yes" : " / No"; ?></td>
                </tr>
                <tr>
                  <td scope="row"><?php echo UPLOAD_PATH; ?></td>
                  <td>
                    <?php
                    echo ($folderPermissions['UPLOAD_PATH']['exists'] == 1) ? "Yes" : "No";
                    ?>
                  </td>
                  <td scope="row"><?php echo ($folderPermissions['UPLOAD_PATH']['readable'] == 1) ? "Yes" : "No"; ?>
                    <?php echo ($folderPermissions['UPLOAD_PATH']['writable'] == 1) ? " / Yes" : " / No"; ?></td>
                </tr>
                <tr>
                  <td scope="row"><?php echo TEMP_PATH; ?></td>
                  <td>
                    <?php
                    echo ($folderPermissions['TEMP_PATH']['exists'] == 1) ? "Yes" : "No";
                    ?>
                  </td>
                  <td scope="row"><?php echo ($folderPermissions['TEMP_PATH']['readable'] == 1) ? "Yes" : "No"; ?>
                    <?php echo ($folderPermissions['TEMP_PATH']['writable'] == 1) ? " / Yes" : " / No"; ?></td>
                </tr>
                <tr>
                  <td scope="row"><?php echo LOG_PATH; ?></td>
                  <td>
                    <?php
                    echo ($folderPermissions['LOG_PATH']['exists'] == 1) ? "Yes" : "No";
                    ?>
                  </td>
                  <td scope="row"><?php echo ($folderPermissions['LOG_PATH']['readable'] == 1) ? "Yes" : "No"; ?>
                    <?php echo ($folderPermissions['LOG_PATH']['writable'] == 1) ? " / Yes" : " / No"; ?></td>
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



<?php
require_once APPLICATION_PATH . '/footer.php';
