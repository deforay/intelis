<?php
if (empty($_POST)) {
    exit(0);
}
// Sanitize values before using them below
$_POST = array_map('htmlspecialchars', $_POST);

$db = $db->where('facility_id', $_POST['facilityId']);
$facilityDetails = $db->getOne('facility_details', array('testing_points'));
$testingPoints = json_decode($facilityDetails['testing_points'], true);
?>
<?php

if (!empty($testingPoints)) { ?>
    <option value=""><?php echo _("-- Select--"); ?></option>
    <?php foreach ($testingPoints as $point) { ?>
        <option value="<?= $point; ?>" <?php echo (isset($_POST['oldTestingPoint']) && !empty($_POST['oldTestingPoint']) && $_POST['oldTestingPoint'] == $point) ? "selected='selected'" : ""; ?>><?php echo $point; ?></option>
    <?php } ?>
<?php
} else {
    echo 0;
}
