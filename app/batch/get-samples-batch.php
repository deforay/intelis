<?php


use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();
/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');
/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if (isset($_POST['type']) && $_POST['type'] == 'vl') {
    $refTable = "form_vl";
    $refPrimaryColumn = "vl_sample_id";
} elseif (isset($_POST['type']) && $_POST['type'] == 'eid') {
    $refTable = "form_eid";
    $refPrimaryColumn = "eid_id";
} elseif (isset($_POST['type']) && $_POST['type'] == 'covid19') {
    $refTable = "form_covid19";
    $refPrimaryColumn = "covid19_id";
} elseif (isset($_POST['type']) && $_POST['type'] == 'hepatitis') {
    $refTable = "form_hepatitis";
    $refPrimaryColumn = "hepatitis_id";
    $showPatientName = true;
} elseif (isset($_POST['type']) && $_POST['type'] == 'tb') {
    $refTable = "form_tb";
    $refPrimaryColumn = "tb_id";
} elseif (isset($_POST['type']) && $_POST['type'] == 'generic-tests') {
    $refTable = "form_generic";
    $refPrimaryColumn = "sample_id";
}

$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$start_date = '';
$end_date = '';

[$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
[$sampleReceivedStartDate, $sampleReceivedEndDate] = DateUtility::convertDateRange($_POST['sampleReceivedAtLab'] ?? '');

$query = "(SELECT vl.sample_code,vl.$refPrimaryColumn,vl.facility_id,vl.result_status,vl.sample_batch_id,f.facility_name,f.facility_code FROM $refTable as vl INNER JOIN facility_details as f ON vl.facility_id=f.facility_id ";

$where[] = " (vl.is_sample_rejected IS NULL OR vl.is_sample_rejected = '' OR vl.is_sample_rejected = 'no') AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection ='' OR vl.reason_for_sample_rejection = 0) AND (vl.result is NULL or vl.result = '') AND vl.sample_code!=''";

if (isset($_POST['batchId'])) {
    $where[] = " (sample_batch_id = '" . $_POST['batchId'] . "' OR sample_batch_id IS NULL OR sample_batch_id = '')";
} else {
    $where[] = " (sample_batch_id IS NULL OR sample_batch_id='')";
}

if (is_array($_POST['fName']) && !empty($_POST['fName'])) {
    $swhere[] = $where[] = " vl.facility_id IN (" . implode(',', $_POST['fName']) . ")";
}

if (isset($_POST['genericTestType']) && $_POST['genericTestType'] != "") {
    $swhere[] = $where[] = " vl.test_type = '" . $_POST['genericTestType'] . "'";
}

if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
    if (trim($start_date) == trim($end_date)) {
        $swhere[] = $where[] = ' DATE(sample_collection_date) = "' . $start_date . '"';
    } else {
        $swhere[] = $where[] = ' DATE(sample_collection_date) >= "' . $start_date . '" AND DATE(sample_collection_date) <= "' . $end_date . '"';
    }
}

if (isset($_POST['sampleReceivedAtLab']) && trim($_POST['sampleReceivedAtLab']) != '') {
    if (trim($sampleReceivedStartDate) == trim($sampleReceivedEndDate)) {
        $swhere[] = $where[] = ' DATE(sample_received_at_lab_datetime) = "' . $sampleReceivedStartDate . '"';
    } else {
        $swhere[] = $where[] = ' DATE(sample_received_at_lab_datetime) >= "' . $sampleReceivedStartDate . '" AND DATE(sample_received_at_lab_datetime) <= "' . $sampleReceivedEndDate . '"';
    }
}
if (!empty($where)) {
    $query = $query . ' WHERE ' . implode(" AND ", $where);
}
$query .= ")";
// $query = $query . " ORDER BY vl.sample_code ASC";
if (isset($_POST['batchId'])) {
    $squery .= " UNION
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
    </div>
<?php } ?>

<script>
    $(document).ready(function() {
        $('#search').multiselect({
            search: {
                left: '<input type="text" name="q" class="form-control" placeholder="<?php echo _translate("Search"); ?>..." />',
                right: '<input type="text" name="q" class="form-control" placeholder="<?php echo _translate("Search"); ?>..." />',
            },
            fireSearch: function(value) {
                return value.length > 2;
            },
            afterMoveToRight: function($left, $right, $options) {
                const count = $right.find('option').length;
                if (count > 0) {
                    $('#alertText').html("<?php echo _translate("You have picked"); ?> " + $("#machine option:selected").text() + " <?php echo _translate("testing platform and it has limit of maximum"); ?> " + count + '/' + noOfSamples + " <?php echo _translate("samples per batch"); ?>");
                } else {
                    $('#alertText').html("<?php echo _translate("You have picked"); ?> " + $("#machine option:selected").text() + " <?php echo _translate("testing platform and it has limit of maximum"); ?> " + noOfSamples + " <?php echo _translate("samples per batch"); ?>");
                }
            },
            afterMoveToLeft: function($left, $right, $options) {
                const count = $right.find('option').length;
                if (count > 0) {
                    $('#alertText').html("<?php echo _translate("You have picked"); ?> " + $("#machine option:selected").text() + " <?php echo _translate("testing platform and it has limit of maximum"); ?> " + count + '/' + noOfSamples + " <?php echo _translate("samples per batch"); ?>");
                } else {
                    $('#alertText').html("<?php echo _translate("You have picked"); ?> " + $("#machine option:selected").text() + " <?php echo _translate("testing platform and it has limit of maximum"); ?> " + noOfSamples + " <?php echo _translate("samples per batch"); ?>");
                }
            }
        });
    });
</script>
