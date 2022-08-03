<?php
if (empty($_POST)) {
    exit(0);
}
$db = $db->where('facility_id', $_POST['facilityId']);
$facilityDetails = $db->getOne('facility_details', array('facility_attributes'));
$facilityAttributes = json_decode($facilityDetails['facility_attributes'], true);
if (!empty($_POST['testType'])) {
    $table = 'r_' . $_POST['testType'] . '_sample_type';
}
if (isset($facilityAttributes) && isset($facilityAttributes['sampleType']) && !empty($facilityAttributes['sampleType'])) {
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
<?php } ?>