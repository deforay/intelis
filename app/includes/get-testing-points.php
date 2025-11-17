<?php

use App\Registries\AppRegistry;

if ($_POST === []) {
    exit(0);
}
// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$db->where('facility_id', $_POST['facilityId']);
$facilityDetails = $db->getOne('facility_details', ['testing_points']);
$testingPoints = json_decode((string) $facilityDetails['testing_points'], true);
?>
<?php

if (!empty($testingPoints)) { ?>
    <option value="">
        <?php echo _translate("-- Select --"); ?>
    </option>
    <?php foreach ($testingPoints as $point) { ?>
        <option value="<?= $point; ?>" <?php echo (!empty($_POST['oldTestingPoint']) && $_POST['oldTestingPoint'] == $point) ? "selected='selected'" : ""; ?>><?php echo $point; ?></option>
    <?php } ?>
<?php
} else {
    echo 0;
}
