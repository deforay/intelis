<?php


use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = $request->getParsedBody();
/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');
/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if (isset($_POST['type']) && $_POST['type'] == 'vl') {
    $refTable = "form_vl";
    $refPrimaryColumn = "vl_sample_id";
    $sampleTypeColumn = "sample_type";
} elseif (isset($_POST['type']) && $_POST['type'] == 'eid') {
    $refTable = "form_eid";
    $refPrimaryColumn = "eid_id";
    $sampleTypeColumn = "specimen_type";
} elseif (isset($_POST['type']) && $_POST['type'] == 'covid19') {
    $refTable = "form_covid19";
    $refPrimaryColumn = "covid19_id";
    $sampleTypeColumn = "specimen_type";
} elseif (isset($_POST['type']) && $_POST['type'] == 'hepatitis') {
    $refTable = "form_hepatitis";
    $refPrimaryColumn = "hepatitis_id";
    $showPatientName = true;
    $sampleTypeColumn = "specimen_type";
} elseif (isset($_POST['type']) && $_POST['type'] == 'tb') {
    $refTable = "form_tb";
    $refPrimaryColumn = "tb_id";
    $sampleTypeColumn = "specimen_type";
} elseif (isset($_POST['type']) && $_POST['type'] == 'generic-tests') {
    $refTable = "form_generic";
    $refPrimaryColumn = "sample_id";
    $sampleTypeColumn = "sample_type";
}

$request = AppRegistry::get('request');
$_POST = $request->getParsedBody();

[$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
[$sampleReceivedStartDate, $sampleReceivedEndDate] = DateUtility::convertDateRange($_POST['sampleReceivedAtLab'] ?? '');

$query = "(SELECT vl.sample_code,vl.$refPrimaryColumn,vl.facility_id,vl.result_status,vl.sample_batch_id,f.facility_name,f.facility_code FROM $refTable as vl INNER JOIN facility_details as f ON vl.facility_id=f.facility_id ";

$where[] = " (vl.is_sample_rejected IS NULL OR vl.is_sample_rejected = '' OR vl.is_sample_rejected = 'no') AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection ='' OR vl.reason_for_sample_rejection = 0) AND (vl.result is NULL or vl.result = '') AND vl.sample_code!=''";

$sample = $_POST['sName'];

if (isset($_POST['batchId'])) {
    $where[] = " (sample_batch_id = '" . $_POST['batchId'] . "' OR sample_batch_id IS NULL OR sample_batch_id = '')";
} else {
    $where[] = " (sample_batch_id IS NULL OR sample_batch_id='')";
}

if (is_array($_POST['fName']) && !empty($_POST['fName'])) {
    $swhere[] = " vl.facility_id IN (" . implode(',', $_POST['fName']) . ")";
}

if (trim((string) $sample) != '') {
    $swhere[] = $where[] = " vl.$sampleTypeColumn='" . $sample . "'";
}

if (isset($_POST['genericTestType']) && $_POST['genericTestType'] != "") {
    $swhere[] = $where[] = " vl.test_type = '" . $_POST['genericTestType'] . "'";
}

if (!empty($_POST['sampleCollectionDate'])) {
    if (trim((string) $start_date) == trim((string) $end_date)) {
        $swhere[] = $where[] = ' DATE(sample_collection_date) = "' . $start_date . '"';
    } else {
        $swhere[] = $where[] = ' DATE(sample_collection_date) >= "' . $start_date . '" AND DATE(sample_collection_date) <= "' . $end_date . '"';
    }
}

if (isset($_POST['sampleReceivedAtLab']) && trim((string) $_POST['sampleReceivedAtLab']) != '') {
    if (trim((string) $sampleReceivedStartDate) == trim((string) $sampleReceivedEndDate)) {
        $swhere[] = $where[] = ' DATE(sample_received_at_lab_datetime) = "' . $sampleReceivedStartDate . '"';
    } else {
        $swhere[] = $where[] = ' DATE(sample_received_at_lab_datetime) >= "' . $sampleReceivedStartDate . '" AND DATE(sample_received_at_lab_datetime) <= "' . $sampleReceivedEndDate . '"';
    }
}

if (isset($_POST['fundingSource']) && trim((string) $_POST['fundingSource']) != '') {
    $swhere[] = $where[] = ' funding_source = "' . $_POST['fundingSource'] . '"';
}

if (!empty($where)) {
    $query = $query . ' WHERE ' . implode(" AND ", $where);
}
$query .= ")";
// $query = $query . " ORDER BY vl.sample_code ASC";
if (isset($_POST['batchId'])) {
    $squery = " UNION
        (SELECT vl.sample_code,vl.$refPrimaryColumn,vl.facility_id,vl.result_status,vl.sample_batch_id,f.facility_name,f.facility_code
        FROM $refTable as vl
    INNER JOIN facility_details as f ON vl.facility_id=f.facility_id ";
    $swhere[] = " (vl.sample_batch_id IS NULL OR vl.sample_batch_id = '')
        AND (vl.is_sample_rejected IS NULL
        OR vl.is_sample_rejected like ''
        OR vl.is_sample_rejected like 'no')
        AND (vl.reason_for_sample_rejection IS NULL
        OR vl.reason_for_sample_rejection like ''
        OR vl.reason_for_sample_rejection = 0)
        AND (vl.result is NULL or vl.result = '')
        AND vl.sample_code!=''";
    if (!empty($swhere)) {
        $squery = $squery . ' WHERE ' . implode(" AND ", $swhere);
    }
    $query .= $squery . " ORDER BY vl.last_modified_datetime ASC)";
}
// die($query);
$result = $db->rawQuery($query);
if (isset($_POST['batchId'])) {
    foreach ($result as $sample) {
        if (!isset($_POST['batchId']) || $_POST['batchId'] != $sample['sample_batch_id']) { ?>
            <option value="<?php echo $sample[$refPrimaryColumn]; ?>"><?php echo ($sample['sample_code']) . " - " . ($sample['facility_name']); ?></option>
    <?php }
    }
} else { ?>
    <script type="text/javascript" src="/assets/js/multiselect.min.js"></script>
    <script type="text/javascript" src="/assets/js/jasny-bootstrap.js"></script>
    <div class="col-md-5">
        <select name="sampleCode[]" id="search" class="form-control" size="8" multiple="multiple">
            <?php foreach ($result as $sample) {
                if (!isset($_POST['batchId']) || $_POST['batchId'] != $sample['sample_batch_id']) { ?>
                    <option value="<?php echo $sample[$refPrimaryColumn]; ?>" <?php echo (isset($_POST['batchId']) && $_POST['batchId'] == $sample['sample_batch_id']) ? "selected='selected'" : ""; ?>><?php echo ($sample['sample_code']) . " - " . ($sample['facility_name']); ?></option>
            <?php }
            } ?>
        </select>
        <div class="sampleCounterDiv"><?= _translate("Number of unselected samples"); ?> : <span id="unselectedCount"></span></div>
    </div>

    <div class="col-md-2">
        <button type="button" id="search_rightAll" class="btn btn-block"><em class="fa-solid fa-forward"></em></button>
        <button type="button" id="search_rightSelected" class="btn btn-block"><em class="fa-sharp fa-solid fa-chevron-right"></em></button>
        <button type="button" id="search_leftSelected" class="btn btn-block"><em class="fa-sharp fa-solid fa-chevron-left"></em></button>
        <button type="button" id="search_leftAll" class="btn btn-block"><em class="fa-solid fa-backward"></em></button>
    </div>

    <div class="col-md-5">
        <select name="to[]" id="search_to" class="form-control" size="8" multiple="multiple">
            <?php foreach ($result as $sample) {
                if (isset($_POST['batchId']) && $_POST['batchId'] == $sample['sample_batch_id']) { ?>
                    <option value="<?php echo $sample[$refPrimaryColumn]; ?>"><?php echo ($sample['sample_code']) . " - " . ($sample['facility_name']); ?></option>
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
