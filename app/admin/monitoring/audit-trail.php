<?php
// app/admin/monitoring/audit-trail.php

use App\Services\TestsService;
use App\Services\UsersService;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\SystemService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\AuditArchiveService;

$title = _translate("Audit Trail");
require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);
$userCache = [];

try {
    // Sanitized values from request object
    /** @var Laminas\Diactoros\ServerRequest $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody());

    $activeModules = SystemService::getActiveModules(onlyTests: true);

    /**
     * Get column names for a specified table
     */
    function getColumns($db, $tableName)
    {
        $columnsSql = "SELECT COLUMN_NAME
                        FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = ? AND table_name = ?
                        ORDER BY ordinal_position";
        return $db->rawQuery($columnsSql, [SYSTEM_CONFIG['database']['db'], $tableName]);
    }

    // Process form submission
    $sampleCode = null;
    $testType = null;
    $formTable = "";
    $filteredData = "";
    $posts = [];

    if (!empty($_POST['testType']) && !empty($_POST['sampleCode'])) {
        $testType = $_POST['testType'];
        $sampleCode = trim((string)$_POST['sampleCode']);
        $filteredData = $_POST['hiddenColumns'] ?? '';

        // Use AuditArchiveService to get audit data
        try {
            $archiveService = new AuditArchiveService($db);

            // Read audit data (with hot archive enabled by default)
            $posts = $archiveService->readAudit($testType, $sampleCode, hotArchive: true);
        } catch (Throwable $e) {
            LoggerUtility::logError(
                'Failed to read audit data: ' . $e->getMessage(),
                ['trace' => $e->getTraceAsString()]
            );
            echo "<div class='alert alert-danger' style='margin:10px 0;'>Failed to load audit trail.</div>";
        }

        // Get form table for column info
        $formTable = TestsService::getTestTableName($testType);
    }

    // Include audit-specific columns
    $auditColumns = [
        ['COLUMN_NAME' => 'action'],
        ['COLUMN_NAME' => 'revision'],
        ['COLUMN_NAME' => 'dt_datetime']
    ];
    $dbColumns = $formTable ? getColumns($db, $formTable) : [];
    $resultColumn = array_merge($auditColumns, $dbColumns);
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
            background-color: rgb(255, 220, 164);
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

        .diff-row {
            background-color: #fff9c4;
        }

        /* Export button styles */
        .snapshot-btn {
            margin-left: 5px;
            padding: 2px 8px;
            font-size: 12px;
        }

        /* Comparison section */
        #comparisonResult {
            margin-top: 20px;
        }

        /* Version selector styles */
        .version-selector {
            margin: 15px 0;
            padding: 15px;
            background-color: #f5f5f5;
            border-radius: 5px;
        }
    </style>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Content Header -->
        <section class="content-header">
            <h1><em class="fa-solid fa-clock-rotate-left"></em> <?php echo _translate("Audit Trail"); ?></h1>
            <ol class="breadcrumb">
                <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
                <li class="active"><?php echo _translate("Audit Trail"); ?></li>
            </ol>
        </section>

        <!-- Main content -->
        <section class="content">
            <div class="box box-default">
                <div class="box-body">
                    <!-- Search Form -->
                    <form method='post' name='searchForm' id='searchForm' autocomplete="off">
                        <div class="row">
                            <div class="col-xs-4 col-md-3 col-lg-3">
                                <div class="form-group">
                                    <label for="sampleCode"><?= _translate("Sample ID"); ?> <span class="mandatory">*</span></label>
                                    <input type="text" class="form-control" id="sampleCode" name="sampleCode"
                                        placeholder="<?= _translate("Enter Sample ID"); ?>"
                                        title="<?= _translate("Please enter sample ID"); ?>"
                                        value="<?= htmlspecialchars($sampleCode ?? ''); ?>" required />
                                </div>
                            </div>
                            <div class="col-xs-4 col-md-3 col-lg-3">
                                <div class="form-group">
                                    <label for="testType"><?= _translate("Test Type"); ?> <span class="mandatory">*</span></label>
                                    <select class="form-control" id="testType" name="testType"
                                        title="<?= _translate("Please select test type"); ?>" required>
                                        <option value=""><?= _translate("-- Select --"); ?></option>
                                        <?php foreach ($activeModules as $key => $module) { ?>
                                            <option value="<?= $key; ?>"
                                                <?= (isset($testType) && $testType == $key) ? 'selected="selected"' : ''; ?>>
                                                <?= $module['name'] ?? $key; ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-xs-4 col-md-3 col-lg-3">
                                <div class="form-group">
                                    <label for="auditColumn"><?= _translate("Select Column"); ?></label>
                                    <select class="form-control" id="auditColumn" name="auditColumn[]"
                                        title="<?= _translate("Please select column"); ?>" multiple>
                                        <?php
                                        $c = 0;
                                        foreach ($resultColumn as $column) {
                                        ?>
                                            <option value="<?= $c; ?>"><?= $column['COLUMN_NAME']; ?></option>
                                        <?php
                                            $c++;
                                        }
                                        ?>
                                    </select>
                                    <input type="hidden" id="hiddenColumns" name="hiddenColumns" value="<?= $filteredData; ?>" />
                                </div>
                            </div>
                            <div class="col-md-3 col-lg-3">
                                <br />
                                <button type="button" onclick="validateNow();" class="btn btn-primary btn-sm">
                                    <span><?= _translate("Search"); ?></span>
                                </button>
                                <button type="button" class="btn btn-default btn-sm" onclick="document.location.href = document.location">
                                    <span><?= _translate("Reset"); ?></span>
                                </button>
                            </div>
                        </div>
                    </form>

                    <?php
                    if (!empty($posts)) {
                        // Build column array
                        $colArr = array_keys($posts[0] ?? []);

                        // Username fields for formatting
                        $usernameFields = [
                            'lab_assigned_to',
                            'approved_by',
                            'revised_by',
                            'locked_by',
                            'last_modified_by',
                            'result_approved_by'
                        ];

                        // Cache usernames
                        foreach ($posts as $post) {
                            foreach ($usernameFields as $field) {
                                if (!empty($post[$field]) && !isset($userCache[$post[$field]])) {
                                    $userCache[$post[$field]] = $usersService->getUserName($post[$field]);
                                }
                            }
                        }

                        // Get current form data from database for comparison
                        $uniqueId = $posts[0]['unique_id'] ?? null;
                        $currentFormData = [];
                        if ($uniqueId && $formTable) {
                            $db->where('unique_id', $uniqueId);
                            $currentFormData = $db->getOne($formTable) ?: [];
                        }
                    ?>
                        <!-- Version Comparison Section -->
                        <div class="version-selector">
                            <h4><?= _translate("Compare Revisions"); ?></h4>
                            <div class="row">
                                <div class="col-md-3">
                                    <label><?= _translate("From Revision"); ?></label>
                                    <select class="form-control" id="versionFrom">
                                        <?php foreach ($posts as $post) { ?>
                                            <option value="<?= htmlspecialchars($post['revision']); ?>">
                                                <?= _translate("Revision"); ?> <?= htmlspecialchars($post['revision']); ?>
                                                (<?= date('Y-m-d H:i', strtotime($post['dt_datetime'])); ?>)
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label><?= _translate("To Revision"); ?></label>
                                    <select class="form-control" id="versionTo">
                                        <?php foreach ($posts as $post) { ?>
                                            <option value="<?= htmlspecialchars($post['revision']); ?>"
                                                <?= ($post === end($posts)) ? 'selected' : ''; ?>>
                                                <?= _translate("Revision"); ?> <?= htmlspecialchars($post['revision']); ?>
                                                (<?= date('Y-m-d H:i', strtotime($post['dt_datetime'])); ?>)
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <br />
                                    <button type="button" class="btn btn-info" id="compareBtn">
                                        <?= _translate("Compare"); ?>
                                    </button>
                                </div>
                            </div>
                            <div id="comparisonResult"></div>
                        </div>

                        <!-- Current Data Table -->
                        <div class="box box-primary collapsed-box">
                            <div class="box-header with-border">
                                <h3 class="box-title"><?= _translate("Current Data"); ?></h3>
                                <div class="box-tools pull-right">
                                    <button type="button" class="btn btn-box-tool" data-widget="collapse">
                                        <i class="fa fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="box-body current">
                                <table class="table table-bordered table-striped" id="currentDataTable">
                                    <thead>
                                        <tr>
                                            <?php foreach ($colArr as $col) {
                                                if ($col !== 'action' && $col !== 'revision' && $col !== 'dt_datetime') { ?>
                                                    <th><?= htmlspecialchars($col); ?></th>
                                            <?php }
                                            } ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <?php foreach ($colArr as $col) {
                                                if ($col !== 'action' && $col !== 'revision' && $col !== 'dt_datetime') {
                                                    $value = $currentFormData[$col] ?? '';

                                                    // Format username fields
                                                    if (in_array($col, $usernameFields) && !empty($value)) {
                                                        if (!isset($userCache[$value])) {
                                                            $userCache[$value] = $usersService->getUserName($value);
                                                        }
                                                        $value = $userCache[$value];
                                                    }
                                            ?>
                                                    <td><?= htmlspecialchars($value); ?></td>
                                            <?php }
                                            } ?>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Audit History Table -->
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title"><?= _translate("Audit History"); ?></h3>
                            </div>
                            <div class="box-body">
                                <table class="table table-bordered table-striped" id="auditTable">
                                    <thead>
                                        <tr>
                                            <th><?= _translate("Action"); ?></th>
                                            <?php foreach ($colArr as $col) { ?>
                                                <th><?= htmlspecialchars($col); ?></th>
                                            <?php } ?>
                                            <th><?= _translate("Export"); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $prevRow = null;
                                        foreach ($posts as $idx => $post) {
                                        ?>
                                            <tr>
                                                <td>
                                                    <?php
                                                    $action = $post['action'] ?? '';
                                                    if ($action === 'insert') {
                                                        echo '<span class="label label-success">' . strtoupper($action) . '</span>';
                                                    } elseif ($action === 'update') {
                                                        echo '<span class="label label-warning">' . strtoupper($action) . '</span>';
                                                    } elseif ($action === 'delete') {
                                                        echo '<span class="label label-danger">' . strtoupper($action) . '</span>';
                                                    } else {
                                                        echo '<span class="label label-default">' . strtoupper($action) . '</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <?php
                                                foreach ($colArr as $col) {
                                                    $cellValue = $post[$col] ?? '';
                                                    $isDifferent = false;

                                                    // Check if value changed from previous revision
                                                    if ($prevRow !== null && isset($prevRow[$col])) {
                                                        $isDifferent = ($cellValue !== $prevRow[$col]);
                                                    }

                                                    // Format username fields
                                                    $displayValue = $cellValue;
                                                    if (in_array($col, $usernameFields) && !empty($cellValue)) {
                                                        $displayValue = $userCache[$cellValue] ?? $cellValue;
                                                    }

                                                    $cellClass = $isDifferent ? 'diff-cell' : '';
                                                ?>
                                                    <td class="<?= $cellClass; ?>">
                                                        <?php
                                                        if ($isDifferent && $prevRow !== null) {
                                                            $oldValue = $prevRow[$col] ?? '';
                                                            if (in_array($col, $usernameFields) && !empty($oldValue)) {
                                                                $oldValue = $userCache[$oldValue] ?? $oldValue;
                                                            }
                                                            echo '<span class="diff-old">' . htmlspecialchars($oldValue) . '</span>';
                                                            echo '<span class="diff-new">' . htmlspecialchars($displayValue) . '</span>';
                                                        } else {
                                                            echo htmlspecialchars($displayValue);
                                                        }
                                                        ?>
                                                    </td>
                                                <?php } ?>
                                                <td>
                                                    <button type="button" class="btn btn-xs btn-info snapshot-btn"
                                                        data-revision="<?= htmlspecialchars($post['revision']); ?>">
                                                        <i class="fa fa-download"></i> <?= _translate("Export"); ?>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php
                                            $prevRow = $post;
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php } elseif ($testType && $sampleCode) {
                        echo '<h3 align="center">' . _translate("Records are not available for this Sample ID") . '</h3>';
                    } else {
                        echo '<h3 align="center">' . _translate("Please enter Sample ID and Test Type to view audit trail") . '</h3>';
                    } ?>
                </div>
            </div>
        </section>
    </div>

    <script type="text/javascript">
        $(document).ready(function() {
            // Initialize selectize
            $("#auditColumn").selectize({
                plugins: ["restore_on_backspace", "remove_button", "clear_button"],
                onClear: function() {
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
                ordering: false,
                order: [
                    [1, 'asc']
                ]
            });

            // Initialize current data table
            var ctable = $('#currentDataTable').DataTable({
                paging: false,
                searching: false,
                info: false,
                ordering: false,
                scrollX: true
            });

            // Apply column visibility
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

                const auditData = <?php echo json_encode($posts ?? []); ?>;
                const fromData = auditData.find(item => item.revision == fromRevision);
                const toData = auditData.find(item => item.revision == toRevision);

                if (!fromData || !toData) {
                    $('#comparisonResult').html('<div class="alert alert-danger">Could not find one or both revisions to compare.</div>');
                    return;
                }

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

                const columns = <?php echo json_encode($colArr ?? []); ?>;
                let csvContent = columns.join(',') + '\n';

                const row = columns.map(col => {
                    const value = snapshot[col] || '';
                    return '"' + String(value).replace(/"/g, '""') + '"';
                }).join(',');

                csvContent += row;

                const blob = new Blob([csvContent], {
                    type: 'text/csv;charset=utf-8;'
                });
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
