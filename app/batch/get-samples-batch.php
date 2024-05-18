<?php


use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if (empty($_POST['type'])) {
    echo "";
    exit;
}

$table = TestsService::getTestTableName($_POST['type']);
$primaryKeyColumn = TestsService::getTestPrimaryKeyColumn($_POST['type']);
$sampleTypeColumn = TestsService::getSpecimenTypeColumn($_POST['type']);
$patientIdColumn = TestsService::getPatientIdColumn($_POST['type']);
$resultColumn = 'result';
$testType = $_POST['type'];


if ($_POST['type'] == 'cd4') {
    $resultColumn = 'cd4_result';
}

[$startDate, $endDate] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
[$sampleReceivedStartDate, $sampleReceivedEndDate] = DateUtility::convertDateRange($_POST['sampleReceivedAtLab'] ?? '');
[$lastModifiedStartDate, $lastModifiedEndDate] = DateUtility::convertDateRange($_POST['lastModifiedDateTime'] ?? '');

$query = "(SELECT vl.sample_code,
                    vl.$primaryKeyColumn,
                    vl.$patientIdColumn,
                    vl.facility_id,
                    vl.result_status,
                    vl.sample_batch_id,
                    f.facility_name,
                    f.facility_code
                    FROM $table as vl
                    INNER JOIN facility_details as f ON vl.facility_id=f.facility_id ";

$where[] = " (vl.is_sample_rejected IS NULL OR vl.is_sample_rejected = '' OR vl.is_sample_rejected = 'no' OR IFNULL(vl.is_sample_rejected, 'no') = 'no') AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection ='' OR vl.reason_for_sample_rejection = 0) AND (vl.$resultColumn is NULL or vl.$resultColumn = '') AND (vl.sample_code NOT LIKE '' AND vl.sample_code IS NOT NULL)";

if (isset($_POST['batchId'])) {
    $where[] = " (sample_batch_id = '" . $_POST['batchId'] . "' OR sample_batch_id IS NULL OR sample_batch_id = '')";
} else {
    $where[] = " (sample_batch_id IS NULL OR sample_batch_id='')";
}

if (!empty($_POST['facilityId']) && is_array($_POST['facilityId'])) {
    $where[] = $swhere[] = " vl.facility_id IN (" . implode(',', $_POST['facilityId']) . ")";
}

if (!empty($_POST['sName'])) {
    $swhere[] = $where[] = " vl.$sampleTypeColumn='" . $_POST['sName'] . "'";
}

if (!empty($_POST['testType'])) {
    $swhere[] = $where[] = " vl.test_type = '" . $_POST['testType'] . "'";
}

if (!empty($_POST['sampleCollectionDate'])) {
    if (trim((string) $startDate) == trim((string) $endDate)) {
        $swhere[] = $where[] = ' DATE(sample_collection_date) = "' . $startDate . '"';
    } else {
        $swhere[] = $where[] = ' DATE(sample_collection_date) BETWEEN "' . $startDate . '" AND "' . $endDate . '"';
    }
}

if (!empty($_POST['sampleReceivedAtLab']) && trim((string) $_POST['sampleReceivedAtLab']) != '') {
    if (trim((string) $sampleReceivedStartDate) == trim((string) $sampleReceivedEndDate)) {
        $swhere[] = $where[] = ' DATE(sample_received_at_lab_datetime) = "' . $sampleReceivedStartDate . '"';
    } else {
        $swhere[] = $where[] = ' DATE(sample_received_at_lab_datetime) BETWEEN "' . $sampleReceivedStartDate . '" AND "' . $sampleReceivedEndDate . '"';
    }
}

if (!empty($_POST['lastModifiedDateTime']) && trim((string) $_POST['lastModifiedDateTime']) != '') {
    if (trim((string) $lastModifiedStartDate) == trim((string) $lastModifiedEndDate)) {
        $swhere[] = $where[] = ' DATE(last_modified_datetime) = "' . $lastModifiedStartDate . '"';
    } else {
        $swhere[] = $where[] = ' DATE(last_modified_datetime) BETWEEN "' . $lastModifiedStartDate . '" AND "' . $lastModifiedEndDate . '"';
    }
}

if (!empty($_POST['fundingSource']) && trim((string) $_POST['fundingSource']) != '') {
    $swhere[] = $where[] = ' funding_source = "' . $_POST['fundingSource'] . '"';
}

if (!empty($_POST['userId']) && trim((string) $_POST['userId']) != '') {
    $swhere[] = $where[] = ' vl.request_created_by = "' . $_POST['userId'] . '"';
}

if (!empty($where)) {
    $query = $query . ' WHERE ' . implode(" AND ", $where);
}
$query .= ")";

if (isset($_POST['batchId'])) {
    $squery = " UNION
        (SELECT
        vl.sample_code,
        vl.$primaryKeyColumn,
        vl.$patientIdColumn,
        vl.facility_id,
        vl.result_status,
        vl.sample_batch_id,
        f.facility_name,
        f.facility_code
        FROM $table as vl
        INNER JOIN facility_details as f ON vl.facility_id=f.facility_id ";
    $swhere[] = " (vl.sample_batch_id IS NULL OR vl.sample_batch_id = '')
        AND (vl.is_sample_rejected IS NULL
        OR vl.is_sample_rejected like ''
        OR vl.is_sample_rejected like 'no')
        AND (vl.reason_for_sample_rejection IS NULL
        OR vl.reason_for_sample_rejection like ''
        OR vl.reason_for_sample_rejection = 0)
        AND (vl.$resultColumn is NULL or vl.$resultColumn = '')
        AND (vl.sample_code NOT LIKE '' AND vl.sample_code IS NOT NULL)";
    if (!empty($swhere)) {
        $squery = $squery . ' WHERE ' . implode(" AND ", $swhere);
    }
    $query .= $squery . " ORDER BY vl.last_modified_datetime ASC)";
}

$result = $db->rawQuery($query);
if (isset($_POST['batchId'])) {
?>
    <?php
    foreach ($result as $sample) {
        if (!isset($_POST['batchId']) || $_POST['batchId'] != $sample['sample_batch_id']) { ?>
            <option value="<?php echo $sample[$primaryKeyColumn]; ?>"><?= $sample['sample_code'] . " - " . $sample[$patientIdColumn] . " - " . $sample['facility_name']; ?></option>
    <?php }
    }
} else { ?>

    <div class="col-md-5" id="sampleDetails">
        <select name="unbatchedSamples[]" id="search" class="form-control" size="8" multiple="multiple">
            <?php foreach ($result as $sample) {
                if (!isset($_POST['batchId']) || $_POST['batchId'] != $sample['sample_batch_id']) { ?>
                    <option value="<?php echo $sample[$primaryKeyColumn]; ?>" <?php echo (isset($_POST['batchId']) && $_POST['batchId'] == $sample['sample_batch_id']) ? "selected='selected'" : ""; ?>><?php echo $sample['sample_code'] . " - " . $sample[$patientIdColumn] . " - " . ($sample['facility_name']); ?></option>
            <?php }
            } ?>
        </select>
        <div class="sampleCounterDiv"><?= _translate("Number of unselected samples"); ?> : <span id="unselectedCount"></span></div>
    </div>

    <div class="col-md-2">
        <button type="button" id="search_undo" class="btn btn-block"><em class="fa-solid fa-rotate-left"></em> <?= _translate("Undo"); ?></button>
        <button type="button" id="search_rightAll" class="btn btn-block"><em class="fa-solid fa-forward"></em></button>
        <button type="button" id="search_rightSelected" class="btn btn-block"><em class="fa-sharp fa-solid fa-chevron-right"></em></button>
        <button type="button" id="search_leftSelected" class="btn btn-block"><em class="fa-sharp fa-solid fa-chevron-left"></em></button>
        <button type="button" id="search_leftAll" class="btn btn-block"><em class="fa-solid fa-backward"></em></button>
        <button type="button" id="search_redo" class="btn btn-block"><em class="fa-solid fa-rotate-right"></em> <?= _translate("Redo"); ?></button>
    </div>

    <div class="col-md-5">
        <select name="to[]" id="search_to" class="form-control" size="8" multiple="multiple">
            <?php foreach ($result as $sample) {
                if (isset($_POST['batchId']) && $_POST['batchId'] == $sample['sample_batch_id']) { ?>
                    <option value="<?php echo $sample[$primaryKeyColumn]; ?>"><?= $sample['sample_code'] . " - " . $sample[$patientIdColumn] . " - " . $sample['facility_name']; ?></option>
            <?php }
            } ?>
        </select>
        <div class="sampleCounterDiv"><?= _translate("Number of selected samples"); ?> : <span id="selectedCount"></span></div>
    </div>
<?php } ?>

<script>
    function updateCounts($left, $right) {
        let selectedCount = $right.find('option').length;
        $("#unselectedCount").html($left.find('option').length);
        $("#selectedCount").html(selectedCount);
        let alertText = selectedCount > 0 ?
            "<?php echo _translate('Number of samples selected out of maximum number of samples allowed for the selected platform'); ?> : " + selectedCount + '/' + noOfSamples :
            "<?php echo _translate('Maximum number of samples allowed for the selected platform'); ?> : " + noOfSamples;
        $('#alertText').html(alertText);
    }
    $(document).ready(function() {

        $('#search').multiselect({
            search: {
                left: '<input type="text" name="q" class="form-control" placeholder="<?php echo _translate("Search"); ?>..." />',
                right: '<input type="text" name="q" class="form-control" placeholder="<?php echo _translate("Search"); ?>..." />',
            },
            fireSearch: function(value) {
                return value.length > 2;
            },
            startUp: function($left, $right) {
                updateCounts($left, $right);
            },
            afterMoveToRight: function($left, $right, $options) {
                updateCounts($left, $right);
            },
            afterMoveToLeft: function($left, $right, $options) {
                updateCounts($left, $right);
            }
        });

    });
</script>
