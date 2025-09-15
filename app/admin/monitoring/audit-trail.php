<?php
// audit-trail-viewer.php - Enhanced version with better change visualization

use GuzzleHttp\Client;
use App\Services\TestsService;
use App\Services\UsersService;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\SystemService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

$title = _translate("Audit Trail");
require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService  $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

try {
    // Sanitized values from request object
    /** @var Laminas\Diactoros\ServerRequest $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody());

    $activeModules = SystemService::getActiveModules(onlyTests: true);

    // Function to fetch unique_id based on sampleCode from formTable
    function getUniqueIdFromSampleCode($db, $tableName, $sampleCode)
    {
        $query = "SELECT unique_id FROM $tableName WHERE sample_code = ? OR remote_sample_code = ? OR external_sample_code = ?";
        $result = $db->rawQuery($query, [$sampleCode, $sampleCode, $sampleCode]);
        return $result[0]['unique_id'] ?? null; // Return unique_id if found, otherwise null
    }

    // Function to get column names for a specified table
    function getColumns($db, $tableName)
    {
        $columnsSql = "SELECT COLUMN_NAME
                        FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = ? AND table_name = ?
                        ORDER BY ordinal_position";
        return $db->rawQuery($columnsSql, [SYSTEM_CONFIG['database']['db'], $tableName]);
    }

    // Function to get CSV file path based on test type and unique ID
    function getAuditFilePath($testType, $uniqueId)
    {
        return ROOT_PATH . "/audit-trail/{$testType}/{$uniqueId}.csv.gz";
    }

    // Function to read data from CSV file
    function readAuditDataFromCsv($filePath)
    {
        $data = [];
        if (file_exists($filePath)) {
            $fileHandle = gzopen($filePath, 'r');
            if ($fileHandle !== false) {
                $headers = fgetcsv($fileHandle);
                while (($row = fgetcsv($fileHandle)) !== false) {
                    $record = [];
                    foreach ($headers as $i => $header) {
                        // Remove quotes and slashes from values
                        $value = $row[$i] ?? '';
                        if (substr($value, 0, 1) === '"' && substr($value, -1) === '"') {
                            $value = substr($value, 1, -1);
                        }
                        $record[$header] = stripslashes($value);
                    }
                    $data[] = $record;
                }
                gzclose($fileHandle);
            }
        }
        return $data;
    }

    $sampleCode = null;
    if (!empty($_POST)) {
        // Define $sampleCode from POST data
        $request = AppRegistry::get('request');
        $_POST = _sanitizeInput($request->getParsedBody());
        $sampleCode = $_POST['sampleCode'] ?? null;
    }

    if (isset($_POST['testType']) && $sampleCode) {
        $formTable = TestsService::getTestTableName($_POST['testType']);
        $auditTable = 'audit_' . $formTable;

        $filteredData = $_POST['hiddenColumns'] ?? '';

        // Get scheme (http or https)
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";

        // Get host (domain or IP with port if any)
        $host = $_SERVER['HTTP_HOST'];

        // Build the full URL
        $baseUrl = "{$scheme}://{$host}";

        // Archive latest audit data for this sample
        $client = new Client();
        try {
            $response = $client->get("{$baseUrl}/tasks/archive-audit-tables.php?sampleCode=$sampleCode", [
                'headers' => [
                    'X-CSRF-Token' => $_SESSION['csrf_token'],
                    'X-Requested-With' => 'XMLHttpRequest', // Spoof as AJAX to avoid ACL. Auth etc.
                ],
                'verify' => false,
            ]);

            if ($response->getStatusCode() === 200) {
                $uniqueId = getUniqueIdFromSampleCode($db, $formTable, $sampleCode);
            } else {
                echo "<h3 align='center'>Failed to archive the latest audit trail data. Please try again.</h3>";
                $uniqueId = null;
            }
        } catch (Exception $e) {
            LoggerUtility::log('error', 'Request to archive audit data failed: ' . $e->getMessage());
            $uniqueId = null;
        }
    } else {
        $formTable = "";
        $uniqueId = "";
        $filteredData = "";
    }

    // Include audit-specific columns explicitly
    $auditColumns = [
        ['COLUMN_NAME' => 'action'],
        ['COLUMN_NAME' => 'revision'],
        ['COLUMN_NAME' => 'dt_datetime']
    ];
    $dbColumns = $formTable ? getColumns($db, $formTable) : [];
    $resultColumn = array_merge($auditColumns, $dbColumns); // Merge audit columns with database columns
?>
    <style>
        /* Base styles */
        .current {
            display: block;
            overflow-x: auto;
            white-space: nowrap;
        }

        /* Enhanced diff styling */
        .diff-cell {
            background-color:rgb(255, 220, 164);
            transition: background-color 0.3s;
        }
        .diff-old {
            text-decoration: line-through;
            color: #d32f2f;
            padding-right: 5px;
        }
        .diff-new {
            font-weight: bold;
            color: #388e3c;
        }

        /* Timeline view */
        .audit-timeline {
            position: relative;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px 0;
        }
        .timeline-item {
            display: flex;
            margin-bottom: 20px;
            position: relative;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 30px;
            bottom: -20px;
            width: 2px;
            background-color: #ddd;
            z-index: 0;
        }
        .timeline-item:last-child::before {
            display: none;
        }
        .timeline-marker {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            text-align: center;
            line-height: 40px;
            font-size: 16px;
            color: white;
            margin-right: 15px;
            z-index: 1;
            flex-shrink: 0;
        }
        .timeline-content {
            background-color: #f9f9f9;
            border-radius: 4px;
            padding: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
            flex-grow: 1;
        }
        .timeline-content h4 {
            margin-top: 0;
            color: #333;
        }
        .timeline-changes {
            margin-top: 10px;
        }
        .change-item {
            margin-bottom: 5px;
            padding: 5px;
            background-color: #f5f5f5;
            border-radius: 3px;
        }
        .change-field {
            font-weight: bold;
        }
        .change-old {
            color: #d32f2f;
            text-decoration: line-through;
            margin-right: 5px;
        }
        .change-new {
            color: #388e3c;
            font-weight: bold;
        }

        /* Version comparison */
        .version-selector {
            margin: 20px 0;
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
        }
        .comparison-table {
            margin-top: 20px;
        }
        .diff-row {
            background-color: #fff3e0;
        }

        /* Tab styling */
        .nav-tabs {
            margin-bottom: 20px;
        }

        /* Change summary */
        .change-summary {
            margin-top: 20px;
        }
        .panel-title {
            font-size: 14px;
        }
        .old-value {
            color: #d32f2f;
            background-color: #ffebee;
        }
        .new-value {
            color: #388e3c;
            background-color: #e8f5e9;
        }
    </style>
    <link href="/assets/css/multi-select.css" rel="stylesheet" />
    <link href="/assets/css/buttons.dataTables.min.css" rel="stylesheet" />

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <section class="content-header">
            <h1><em class="fa-solid fa-clock-rotate-left"></em> <?php echo _translate("Audit Trail"); ?></h1>
            <ol class="breadcrumb">
                <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
                <li class="active"><?php echo _translate("Audit Trail"); ?></li>
            </ol>
        </section>

        <!-- Main content -->
        <section class="content">
            <div class="row">
                <div class="col-xs-12">
                    <div class="box">
                        <form name="form1" action="audit-trail.php" method="post" id="searchForm" autocomplete="off">
                            <table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:98%;">
                                <tr>
                                    <td><strong><?php echo _translate("Test Type"); ?><span class="mandatory">*</span>&nbsp;:</strong></td>
                                    <td>
                                        <select id="testType" name="testType" class="form-control isRequired">
                                            <option value="">-- Choose Test Type--</option>
                                            <?php foreach ($activeModules as $module): ?>
                                                <option value="<?php echo $module; ?>"
                                                    <?php echo (isset($_POST['testType']) && $_POST['testType'] == $module) ? "selected='selected'" : ""; ?>>
                                                    <?php echo strtoupper($module); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>&nbsp;<strong><?php echo _translate("Sample ID"); ?><span class="mandatory">*</span>&nbsp;:</strong></td>
                                    <td>
                                        <input type="text" value="<?= htmlspecialchars($_POST['sampleCode'] ?? ''); ?>" name="sampleCode" id="sampleCode" class="form-control isRequired" />
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="4">
                                        <input type="submit" value="<?php echo _translate("Submit"); ?>" class="btn btn-success btn-sm" onclick="validateNow();return false;">
                                        <button type="reset" class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?= _translate('Reset'); ?></span></button>
                                        <input type="hidden" name="hiddenColumns" id="hiddenColumns" value="<?= htmlspecialchars($_POST['hiddenColumns'] ?? ''); ?>" />
                                    </td>
                                </tr>
                            </table>
                        </form>
                    </div>
                </div>

                <?php
                $usernameFields = [
                    'tested_by',
                    'result_approved_by',
                    'result_reviewed_by',
                    'revised_by',
                    'request_created_by',
                    'last_modified_by'
                ];

                if (!empty($uniqueId)) {
                    $filePath = getAuditFilePath($_POST['testType'], $uniqueId);
                    $posts = readAuditDataFromCsv($filePath);
                    // Sort the records by revision ID
                    usort($posts, function ($a, $b) {
                        return $a['revision'] <=> $b['revision'];
                    });

                    // Fetch current data
                    $currentData = $db->rawQuery("SELECT * FROM $formTable WHERE unique_id = ?", [$uniqueId]);
                ?>
                    <div class="col-xs-12">
                        <div class="box">
                            <div class="box-body">
                                <?php if (!empty($posts)) { ?>
                                    <h3> Audit Trail for Sample <?php echo htmlspecialchars((string) $sampleCode); ?></h3>

                                    <!-- Column visibility control -->
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="auditColumn">Show/Hide Columns:</label>
                                                <select name="auditColumn[]" id="auditColumn" class="" multiple="multiple">
                                                    <?php
                                                    $i = 0;
                                                    foreach ($resultColumn as $col) {
                                                        $selected = "";
                                                        if(!empty($_POST['hiddenColumns']) && in_array($i, explode(",", $_POST['hiddenColumns']))){
                                                            $selected = "selected";
                                                        }
                                                        echo "<option value='$i' $selected>{$col['COLUMN_NAME']}</option>";
                                                        $i++;
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tab navigation -->
                                    <ul class="nav nav-tabs" role="tablist">
                                        <li role="presentation" class="active">
                                            <a href="#tabTable" aria-controls="tabTable" role="tab" data-toggle="tab">
                                                <i class="fa fa-table"></i> Table View
                                            </a>
                                        </li>
                                        <li role="presentation">
                                            <a href="#tabTimeline" aria-controls="tabTimeline" role="tab" data-toggle="tab">
                                                <i class="fa fa-clock-o"></i> Timeline View
                                            </a>
                                        </li>
                                        <li role="presentation">
                                            <a href="#tabChanges" aria-controls="tabChanges" role="tab" data-toggle="tab">
                                                <i class="fa fa-exchange"></i> Changes Only
                                            </a>
                                        </li>
                                        <li role="presentation">
                                            <a href="#tabCompare" aria-controls="tabCompare" role="tab" data-toggle="tab">
                                                <i class="fa fa-code-fork"></i> Compare Versions
                                            </a>
                                        </li>
                                    </ul>

                                    <!-- Tab content -->
                                    <div class="tab-content">
                                        <!-- Table View Tab -->
                                        <div role="tabpanel" class="tab-pane active" id="tabTable">
                                            <table aria-describedby="table" id="auditTable" class="table-bordered table table-striped table-hover" aria-hidden="true">
                                                <thead>
                                                    <tr>
                                                        <?php
                                                        $colArr = [];
                                                        foreach ($resultColumn as $col) {
                                                            $colArr[] = $col['COLUMN_NAME'];
                                                            echo "<th>{$col['COLUMN_NAME']}</th>";
                                                        }
                                                        ?>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $userCache = [];

                                                    for ($i = 0; $i < count($posts); $i++) {
                                                        echo "<tr>";
                                                        foreach ($colArr as $j => $colName) {
                                                            $value = $posts[$i][$colName] ?? '';
                                                            $previousValue = $i > 0 ? ($posts[$i - 1][$colName] ?? null) : null;

                                                            // Check if value changed from previous revision
                                                            if ($j > 2 && $previousValue !== null && $value !== $previousValue) {
                                                                // Format the value to show what changed
                                                                $displayValue = "<span class='diff-old'>" . htmlspecialchars($previousValue) . "</span> <span class='diff-new'>" . htmlspecialchars($value) . "</span>";
                                                                echo "<td class='diff-cell'>" . $displayValue . "</td>";
                                                            } else {
                                                                // Look up username for user IDs
                                                                if (in_array($colName, $usernameFields) && !empty($value)) {
                                                                    if (!isset($userCache[$value])) {
                                                                        $user = $usersService->getUserByID($value, ['user_name']);
                                                                        $userCache[$value] = $user['user_name'] ?? $value;
                                                                    }
                                                                    $value = $userCache[$value];
                                                                }
                                                                echo "<td>" . htmlspecialchars($value) . "</td>";
                                                            }
                                                        }
                                                        echo "</tr>";
                                                    }
                                                    ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <!-- Timeline View Tab -->
                                        <div role="tabpanel" class="tab-pane" id="tabTimeline">
                                            <div class="audit-timeline">
                                                <?php
                                                foreach ($posts as $i => $post) {
                                                    $action = $post['action'];
                                                    $date = $post['dt_datetime'];
                                                    $revision = $post['revision'];

                                                    $icon = $action == 'insert' ? 'fa fa-plus-circle' :
                                                           ($action == 'update' ? 'fa fa-edit' : 'fa fa-trash');

                                                    $color = $action == 'insert' ? 'success' :
                                                            ($action == 'update' ? 'warning' : 'danger');
                                                ?>
                                                <div class="timeline-item">
                                                    <div class="timeline-marker bg-<?= $color ?>">
                                                        <i class="<?= $icon ?>"></i>
                                                    </div>
													<div class="timeline-content">
                                                        <h4><?= ucfirst($action) ?> - Revision <?= $revision ?></h4>
                                                        <p><i class="fa fa-calendar"></i> <?= date('F j, Y, g:i a', strtotime($date)) ?></p>
                                                        <div class="timeline-changes">
                                                            <?php
                                                            // Show what changed in this revision
                                                            if ($i > 0) {
                                                                $prevPost = $posts[$i-1];
                                                                $changeCount = 0;

                                                                foreach ($colArr as $colName) {
                                                                    if ($colName != 'action' && $colName != 'revision' && $colName != 'dt_datetime') {
                                                                        $oldValue = $prevPost[$colName] ?? '';
                                                                        $newValue = $post[$colName] ?? '';

                                                                        if ($oldValue !== $newValue) {
                                                                            $changeCount++;

                                                                            // Format user IDs to names
                                                                            if (in_array($colName, $usernameFields)) {
                                                                                if (!empty($oldValue) && !isset($userCache[$oldValue])) {
                                                                                    $user = $usersService->getUserByID($oldValue, ['user_name']);
                                                                                    $userCache[$oldValue] = $user['user_name'] ?? $oldValue;
                                                                                }
                                                                                if (!empty($newValue) && !isset($userCache[$newValue])) {
                                                                                    $user = $usersService->getUserByID($newValue, ['user_name']);
                                                                                    $userCache[$newValue] = $user['user_name'] ?? $newValue;
                                                                                }
                                                                                $oldValue = !empty($oldValue) ? $userCache[$oldValue] : '';
                                                                                $newValue = !empty($newValue) ? $userCache[$newValue] : '';
                                                                            }

                                                                            echo "<div class='change-item'>";
                                                                            echo "<span class='change-field'>{$colName}:</span> ";
                                                                            echo "<span class='change-old'>" . htmlspecialchars($oldValue) . "</span> → ";
                                                                            echo "<span class='change-new'>" . htmlspecialchars($newValue) . "</span>";
                                                                            echo "</div>";
                                                                        }
                                                                    }
                                                                }

                                                                if ($changeCount === 0) {
                                                                    echo "<div class='change-item'>No fields were changed in this revision.</div>";
                                                                }
                                                            } else {
                                                                echo "<div class='change-item'>Initial record creation</div>";
                                                            }
                                                            ?>
                                                        </div>
                                                        <!-- Snapshot Export Button -->
                                                        <div class="mt-3">
                                                            <button class="btn btn-xs btn-info snapshot-btn" data-revision="<?= $revision ?>">
                                                                <i class="fa fa-download"></i> Export Snapshot at Rev <?= $revision ?>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php } ?>
                                            </div>
                                        </div>

                                        <!-- Changes Only Tab -->
                                        <div role="tabpanel" class="tab-pane" id="tabChanges">
                                            <div class="change-summary">
                                                <div class="panel-group" id="changeSummary">
                                                    <?php
                                                    for ($i = 1; $i < count($posts); $i++) {
                                                        $current = $posts[$i];
                                                        $previous = $posts[$i - 1];
                                                        $changes = [];

                                                        foreach ($colArr as $colName) {
                                                            if ($colName != 'action' && $colName != 'revision' && $colName != 'dt_datetime') {
                                                                $oldValue = $previous[$colName] ?? '';
                                                                $newValue = $current[$colName] ?? '';

                                                                if ($oldValue !== $newValue) {
                                                                    // Format user IDs to names
                                                                    if (in_array($colName, $usernameFields)) {
                                                                        if (!empty($oldValue) && !isset($userCache[$oldValue])) {
                                                                            $user = $usersService->getUserByID($oldValue, ['user_name']);
                                                                            $userCache[$oldValue] = $user['user_name'] ?? $oldValue;
                                                                        }
                                                                        if (!empty($newValue) && !isset($userCache[$newValue])) {
                                                                            $user = $usersService->getUserByID($newValue, ['user_name']);
                                                                            $userCache[$newValue] = $user['user_name'] ?? $newValue;
                                                                        }
                                                                        $oldValue = !empty($oldValue) ? $userCache[$oldValue] : '';
                                                                        $newValue = !empty($newValue) ? $userCache[$newValue] : '';
                                                                    }

                                                                    $changes[$colName] = [
                                                                        'from' => $oldValue,
                                                                        'to' => $newValue
                                                                    ];
                                                                }
                                                            }
                                                        }

                                                        if (!empty($changes)) {
                                                            ?>
                                                            <div class="panel panel-default">
                                                                <div class="panel-heading">
                                                                    <h4 class="panel-title">
                                                                        <a data-toggle="collapse" data-parent="#changeSummary" href="#collapse<?= $current['revision'] ?>">
                                                                            Revision <?= $current['revision'] ?> - <?= ucfirst($current['action']) ?>
                                                                            (<?= date('Y-m-d H:i:s', strtotime($current['dt_datetime'])) ?>)
                                                                        </a>
                                                                    </h4>
                                                                </div>
                                                                <div id="collapse<?= $current['revision'] ?>" class="panel-collapse collapse">
                                                                    <div class="panel-body">
                                                                        <table class="table table-striped">
                                                                            <thead>
                                                                                <tr>
                                                                                    <th>Field</th>
                                                                                    <th>Old Value</th>
                                                                                    <th>New Value</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                <?php foreach ($changes as $field => $change): ?>
                                                                                <tr>
                                                                                    <td><?= $field ?></td>
                                                                                    <td class="old-value"><?= htmlspecialchars($change['from']) ?></td>
                                                                                    <td class="new-value"><?= htmlspecialchars($change['to']) ?></td>
                                                                                </tr>
                                                                                <?php endforeach; ?>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <?php
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Compare Versions Tab -->
                                        <div role="tabpanel" class="tab-pane" id="tabCompare">
                                            <div class="version-selector">
                                                <div class="row">
                                                    <div class="col-md-5">
                                                        <div class="form-group">
                                                            <label for="versionFrom">From Version:</label>
                                                            <select id="versionFrom" class="form-control">
                                                                <?php foreach ($posts as $post): ?>
                                                                <option value="<?= $post['revision'] ?>">
                                                                    Revision <?= $post['revision'] ?>
                                                                    (<?= ucfirst($post['action']) ?> -
                                                                    <?= date('Y-m-d H:i:s', strtotime($post['dt_datetime'])) ?>)
                                                                </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-5">
                                                        <div class="form-group">
                                                            <label for="versionTo">To Version:</label>
                                                            <select id="versionTo" class="form-control">
                                                                <?php
                                                                foreach ($posts as $i => $post):
                                                                    $selected = ($i === count($posts) - 1) ? 'selected' : '';
                                                                ?>
                                                                <option value="<?= $post['revision'] ?>" <?= $selected ?>>
                                                                    Revision <?= $post['revision'] ?>
                                                                    (<?= ucfirst($post['action']) ?> -
                                                                    <?= date('Y-m-d H:i:s', strtotime($post['dt_datetime'])) ?>)
                                                                </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <div class="form-group">
                                                            <label>&nbsp;</label>
                                                            <button id="compareBtn" class="btn btn-primary form-control">
                                                                <i class="fa fa-exchange"></i> Compare
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div id="comparisonResult" class="comparison-table">
                                                <!-- Will be populated via JavaScript when Compare button is clicked -->
                                            </div>
                                        </div>
                                    </div>
                                <?php } else {
                                    echo '<h3 align="center">' . _translate("Records are not available for this Sample ID") . '</h3>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <!-- Current data section -->
                    <div class="col-xs-12">
                        <div class="box">
                            <div class="box-body">
                                <?php
                                // Display Current Data if available
                                if (!empty($currentData)) { ?>
                                    <h3> <?= _translate("Current Data for Sample"); ?> <?php echo htmlspecialchars($sampleCode ?? ''); ?></h3>
                                    <table id="currentDataTable" class="table-bordered table table-striped table-hover" aria-hidden="true">
                                        <thead>
                                            <tr>
                                                <?php
                                                // Display column headers
                                                foreach (array_keys($currentData[0]) as $colName) {
                                                    echo "<th>$colName</th>";
                                                }
                                                ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <?php
                                                // Display row data
                                                foreach ($currentData[0] as $colName => $value) {
                                                    if (in_array($colName, $usernameFields) && !empty($value)) {
                                                        if (!isset($userCache[$value])) {
                                                            $user = $usersService->getUserByID($value, ['user_name']);
                                                            $userCache[$value] = $user['user_name'] ?? $value;
                                                        }
                                                        $value = $userCache[$value];
                                                    }
                                                    echo '<td>' . htmlspecialchars(stripslashes($value)) . '</td>';
                                                }
                                                ?>
                                            </tr>
                                        </tbody>
                                    </table>
                                <?php } else {
                                    echo '<h3 align="center">' . _translate("Records are not available for this Sample ID") . '</h3>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                <?php } else {
                    echo '<h3 align="center">' . _translate("Please enter Sample ID and Test Type to view audit trail") . '</h3>';
                } ?>
            </div>
        </section>
    </div>

    <script type="text/javascript">
        $(document).ready(function() {
            // Initialize selectize
            $("#auditColumn").selectize({
                plugins: ["restore_on_backspace", "remove_button", "clear_button"],
                onClear: function(){
                    document.getElementById('searchForm').submit();
                }
            });

            // Initialize DataTables
            var table = $("#auditTable").DataTable({
                dom: 'Bfrtip',
                buttons: [{
                    extend: 'csvHtml5',
                    exportOptions: {
                        columns: ':visible'
                    },
                    text: 'Export To CSV',
                    title: 'AuditTrailSample-<?php echo $sampleCode ?? ""; ?>',
                    extension: '.csv'
                }],
                scrollY: '250vh',
                scrollX: true,
                scrollCollapse: true,
                paging: false,
                ordering: false, // Make table non-sortable
                order: [
                    [1, 'asc']
                ], // Order by revision ID (second column) by default
            });

            // Initialize current data table
            var ctable = $('#currentDataTable').DataTable({
                paging: false,
                searching: false,
                info: false,
                ordering: false,
                scrollX: true
            });

            // Apply column visibility based on selected columns
            var col = $("#hiddenColumns").val();

            if (col) {
                table.columns().visible(false);
                table.columns(col.split(',')).visible(true);
            }

            // Handle column visibility toggling
            $('#auditColumn').on("change", function(e) {
                var columns = $(this).val();
                $('#hiddenColumns').val(columns);
                if (columns === "" || columns == null) {
                    table.columns().visible(true);
                } else {
                    table.columns().visible(false);
                    table.columns(columns).visible(true);
                }
            });

            // Version comparison functionality
            $('#compareBtn').on('click', function() {
                const fromRevision = $('#versionFrom').val();
                const toRevision = $('#versionTo').val();

                if (fromRevision === toRevision) {
                    alert('Please select different revisions to compare');
                    return;
                }

                // Get audit data from PHP
                const auditData = <?php echo json_encode($posts ?? []); ?>;
                const fromData = auditData.find(item => item.revision == fromRevision);
                const toData = auditData.find(item => item.revision == toRevision);

                if (!fromData || !toData) {
                    $('#comparisonResult').html('<div class="alert alert-danger">Could not find one or both revisions to compare.</div>');
                    return;
                }

                // Create comparison HTML
                let html = '<h4>Comparing Revision ' + fromRevision + ' with Revision ' + toRevision + '</h4>';
                html += '<table class="table table-bordered">';
                html += '<thead><tr><th>Field</th><th>Revision ' + fromRevision + '</th><th>Revision ' + toRevision + '</th></tr></thead>';
                html += '<tbody>';

                const colArr = <?php echo json_encode($colArr ?? []); ?>;
                const usernameFields = <?php echo json_encode($usernameFields); ?>;
                const userCache = <?php echo json_encode($userCache ?? []); ?>;

                let changesFound = false;

                for (const colName of colArr) {
                    if (colName !== 'action' && colName !== 'revision' && colName !== 'dt_datetime') {
                        let fromValue = fromData[colName] || '';
                        let toValue = toData[colName] || '';

                        // Format user IDs to names
                        if (usernameFields.includes(colName)) {
                            if (fromValue && userCache[fromValue]) {
                                fromValue = userCache[fromValue];
                            }
                            if (toValue && userCache[toValue]) {
                                toValue = userCache[toValue];
                            }
                        }

                        const changed = fromValue !== toValue;
                        changesFound = changesFound || changed;

                        html += '<tr' + (changed ? ' class="diff-row"' : '') + '>';
                        html += '<td>' + colName + '</td>';
                        html += '<td>' + (fromValue || '') + '</td>';
                        html += '<td>' + (toValue || '') + '</td>';
                        html += '</tr>';
                    }
                }

                html += '</tbody></table>';

                if (!changesFound) {
                    html += '<div class="alert alert-info">No differences found between these revisions.</div>';
                }

                $('#comparisonResult').html(html);
            });

            // Snapshot export functionality
            $(document).on('click', '.snapshot-btn', function() {
                const revision = $(this).data('revision');
                const auditData = <?php echo json_encode($posts ?? []); ?>;
                const snapshot = auditData.find(item => item.revision == revision);

                if (!snapshot) {
                    alert('Could not find data for revision ' + revision);
                    return;
                }

                // Create CSV content
                const columns = <?php echo json_encode($colArr ?? []); ?>;

                // Add headers
                let csvContent = columns.join(',') + '\n';

                // Add data row
                const row = columns.map(col => {
                    const value = snapshot[col] || '';
                    // Properly escape quotes in CSV
                    return '"' + String(value).replace(/"/g, '""') + '"';
                }).join(',');

                csvContent += row;

                // Create download link
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.setAttribute('href', url);
                link.setAttribute('download', 'Snapshot_Rev' + revision + '_<?= htmlspecialchars($sampleCode ?? "") ?>.csv');
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        });

        function validateNow() {
            flag = deforayValidator.init({
                formId: 'searchForm'
            });

            if (flag) {
                $.blockUI();
                document.getElementById('searchForm').submit();
            }
        }
    </script>
<?php
} catch (Throwable $e) {
    LoggerUtility::logError(
        $e->getMessage(),
        [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'last_db_query' => $db->getLastQuery(),
            'last_db_error' => $db->getLastError(),
            'trace' => $e
        ]
    );
}

require_once APPLICATION_PATH . '/footer.php';
