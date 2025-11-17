<?php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if ($_POST === []) {
    exit(0);
}

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$db->where('facility_id', $_POST['facilityId']);
$facilityDetails = $db->getOne('facility_details', ['facility_attributes']);
$facilityAttributes = json_decode((string) $facilityDetails['facility_attributes'], true);
if (!empty($_POST['testType'])) {
    $table = 'r_' . $_POST['testType'] . '_sample_type';
}
if (!empty($facilityAttributes['sampleType'])) {
    $db->where("sample_id IN(" . $facilityAttributes['sampleType'][$_POST['testType']] . ")");
}
$db->where("status = 'active'");
$sampleTypes = $db->get($table);
?>
<?php if (!empty($sampleTypes)) { ?>
    <option value="">
        <?php echo _translate("-- Select --"); ?>
    </option>
    <?php foreach ($sampleTypes as $sample) { ?>
        <option value="<?php echo $sample['sample_id']; ?>" <?php echo (!empty($_POST['sampleId']) && $_POST['sampleId'] == $sample['sample_id']) ? "selected='selected'" : ""; ?>><?php echo $sample['sample_name']; ?>
        </option>
    <?php } ?>
<?php } else { ?>
    <option value="">
        <?php echo _translate("-- Select --"); ?>
    </option>
<?php
}
?>
