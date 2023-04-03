<?php
if (empty($_POST)) {
    exit(0);
}

$db = $db->where('facility_id', $_POST['facilityId']);
$facilityDetails = $db->getOne('facility_details', array('testing_points'));
$testingPoints = json_decode($facilityDetails['testing_points'],true);
?>
<?php if (count($testingPoints)>0) { ?>
    <option value=""><?php echo _("-- Select--"); ?></option>
    <?php foreach ($testingPoints as $point) { ?>
        <option value="<?php echo $point; ?>" <?php echo (isset($_POST['oldTestingPoint']) && !empty($_POST['oldTestingPoint']) && $_POST['oldTestingPoint'] == $point) ? "selected='selected'" : ""; ?>><?php echo $point; ?></option>
    <?php } ?>
<?php } else {
    echo 0;
 } ?>