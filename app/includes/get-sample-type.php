<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if (empty($_POST)) {
    exit(0);
}

// Sanitize values before using them below
$_POST = array_map('htmlspecialchars', $_POST);
if (isset($_POST['testTypeId'])) {
    $testTypeId = $_POST['testTypeId'];
    $sampleTypeList = $general->getSampleType($testTypeId);
    if (!empty($sampleTypeList)) { ?>
        <option value=""><?php echo _("-- Select--"); ?></option>
        <?php foreach ($sampleTypeList as $sample) { ?>
            <option value="<?php echo $sample['sample_type_id']; ?>" <?php echo (isset($_POST['sampleTypeId']) && !empty($_POST['sampleTypeId']) && $_POST['sampleTypeId'] == $sample['sample_type_id']) ? "selected='selected'" : ""; ?>><?php echo $sample['sample_type_name']; ?></option>
        <?php }
    } else { ?>
        <option value=""><?php echo _("-- Select--"); ?></option>
    <?php }
} else {
    $db = $db->where('facility_id', $_POST['facilityId']);
    $facilityDetails = $db->getOne('facility_details', array('facility_attributes'));
    $facilityAttributes = json_decode($facilityDetails['facility_attributes'], true);
    if (!empty($_POST['testType'])) {
        $table = 'r_' . $_POST['testType'] . '_sample_type';
    }
    if (isset($facilityAttributes['sampleType']) && !empty($facilityAttributes['sampleType'])) {
        $db->where("sample_id IN(" . $facilityAttributes['sampleType'][$_POST['testType']] . ")");
    }
    $db->where("status = 'active'");
    $sampleTypes = $db->get($table);
    ?>
    <?php if (!empty($sampleTypes)) { ?>
        <option value=""><?php echo _("-- Select--"); ?></option>
        <?php foreach ($sampleTypes as $sample) { ?>
            <option value="<?php echo $sample['sample_id']; ?>" <?php echo (isset($_POST['sampleId']) && !empty($_POST['sampleId']) && $_POST['sampleId'] == $sample['sample_id']) ? "selected='selected'" : ""; ?>><?php echo $sample['sample_name']; ?></option>
        <?php } ?>
    <?php } else { ?>
        <option value=""><?php echo _("-- Select--"); ?></option>
<?php }
}
?>
