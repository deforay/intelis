<?php

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

$serverSettings = $systemService->getServerSettings();
$folderPermissions = $systemService->checkFolderPermissions();
$diskSpaceUtilization = $systemService->diskSpaceUtilization();


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

		
				<div class="col-xs-12">
					<div class="box">
						<!-- /.box-header -->
						<div class="box-body">
						
            <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
               <tbody>
                 <tr><th colspan="5"><h4><?= _translate("Server Settings"); ?></h4></th></tr>         
                 <tr>
                   <th scope="row"><?= _translate("Memory Limit"); ?></th>
                   <td>
                   <?php echo $serverSettings['memory_limit']; ?></td>
                   <th scope="row"><?= _translate("Maximum Uploaded Filesize"); ?></th>
                   <td><?php echo $serverSettings['upload_max_filesize']; ?></td>
                 </tr>
                 <tr>
                   <th scope="row"><?= _translate("Maximum size of post data allowed"); ?></th>
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
                 <tr><th colspan="5"><h4><?= _translate("Folder Permission Settings"); ?></h4></th></tr>     
                 <tr>
                   <th><?= _translate("File Path"); ?></th>
                   <th><?= _translate("Exists"); ?></th>
                   <th><?= _translate("Readable / Writeable"); ?></th>
                 </tr>    
                 <tr>
                   <td scope="row"><?php echo CACHE_PATH; ?></td>
                   <td>
                   <?php  
                     echo ($folderPermissions['CACHE_PATH']['exists']==1) ? "Yes" : "No";
                   ?>
                   </td>
                   <td scope="row"><?php echo ($folderPermissions['CACHE_PATH']['readable']==1) ? "Yes" : "No"; ?>
                   <?php echo ($folderPermissions['CACHE_PATH']['writable']==1) ? " / Yes" : " / No"; ?></td>
                 </tr>
                 <tr>
                   <td scope="row"><?php echo UPLOAD_PATH; ?></td>
                   <td>
                   <?php  
                     echo ($folderPermissions['UPLOAD_PATH']['exists']==1) ? "Yes" : "No";
                   ?>
                   </td>
                   <td scope="row"><?php echo ($folderPermissions['UPLOAD_PATH']['readable']==1) ? "Yes" : "No"; ?>
                   <?php echo ($folderPermissions['UPLOAD_PATH']['writable']==1) ? " / Yes" : " / No"; ?></td>
                 </tr>
                 <tr>
                   <td scope="row"><?php echo TEMP_PATH; ?></td>
                   <td>
                   <?php  
                     echo ($folderPermissions['TEMP_PATH']['exists']==1) ? "Yes" : "No";
                   ?>
                   </td>
                   <td scope="row"><?php echo ($folderPermissions['TEMP_PATH']['readable']==1) ? "Yes" : "No"; ?>
                   <?php echo ($folderPermissions['TEMP_PATH']['writable']==1) ? " / Yes" : " / No"; ?></td>
                 </tr>
                 <tr>
                   <td scope="row"><?php echo ROOT_PATH . DIRECTORY_SEPARATOR . 'logs'; ?></td>
                   <td>
                   <?php  
                     echo ($folderPermissions['LOGS_PATH']['exists']==1) ? "Yes" : "No";
                   ?>
                   </td>
                   <td scope="row"><?php echo ($folderPermissions['LOGS_PATH']['readable']==1) ? "Yes" : "No"; ?>
                   <?php echo ($folderPermissions['LOGS_PATH']['writable']==1) ? " / Yes" : " / No"; ?></td>
                 </tr>
                 <tr><th colspan="5"><h4><?= _translate("Disk Space Utilization"); ?></h4></th></tr>         
                 <tr>
                   <th scope="row"><?= _translate("Total Server Memory Space"); ?></th>
                   <td><?php echo $diskSpaceUtilization['total_server_space']; ?></td>
                   <th scope="row"><?= _translate("Free Server Memory Space"); ?></th>
                   <td style="text-align:left"><?php echo $diskSpaceUtilization['free_server_space']; ?></td>
                 </tr>
                 <tr>
                   <th scope="row"><?= _translate("Used Server Memory Space"); ?></th>
                   <td><?php echo $diskSpaceUtilization['used_server_space']; ?></td>
                   <th scope="row"><?= _translate("VLSM used Memory Space"); ?></th>
                   <td><?php echo $diskSpaceUtilization['vlsm_used_space']; ?></td>
                 </tr>
                 <tr>
                   <th scope="row"><?= _translate("Web Root Used Memory Space"); ?></th>
                   <td><?php echo $diskSpaceUtilization['web_root_used_space']; ?></td>
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
