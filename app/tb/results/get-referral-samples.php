<?php

use App\Services\TestsService;
use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

if (empty($_POST['type'])) {
    echo "";
    exit;
}

$testType = $_POST['type'];
$packageCodeId = $_POST['packageCode'];
$labId = $_POST['referralLabId'];
$table = TestsService::getTestTableName($testType);
$primaryKeyColumn = TestsService::getPrimaryColumn($testType);
$patientIdColumn = TestsService::getPatientIdColumn($testType);
$lisLabId = $general->getSystemConfig('sc_testing_lab_id');

$condition = "(COALESCE(vl.referred_to_lab_id, 0) = 0 OR vl.referred_to_lab_id = '')";
if (isset($packageCodeId) && !empty($packageCodeId))
    $condition = "(COALESCE(vl.referred_to_lab_id, 0) = 0 OR vl.referred_to_lab_id = '' OR vl.referred_to_lab_id = '$labId')";
// Query to get samples that are eligible for referral
// Samples should be received at lab but not yet referred
$query = "SELECT 
            vl.sample_code,
            vl.$primaryKeyColumn,
            vl.$patientIdColumn,
            vl.facility_id,
            vl.referred_to_lab_id, 
            vl.referral_manifest_code, 
            f.facility_name,
            f.facility_code
          FROM $table as vl
          INNER JOIN facility_details as f ON vl.facility_id = f.facility_id
          WHERE $condition
            AND (COALESCE(vl.is_sample_rejected, '') = '' OR vl.is_sample_rejected = 'no')
            AND (vl.sample_code IS NOT NULL AND vl.sample_code != '')
            AND (vl.lab_id IS NOT NULL AND vl.lab_id = '$lisLabId')
          ORDER BY vl.sample_code ASC";
$result = $db->rawQuery($query);

// Output options for the select box
foreach ($result as $sample) {
    $displayText = $sample['sample_code'];
    if (!empty($sample[$patientIdColumn])) {
        $displayText .= " - " . $sample[$patientIdColumn];
    }
    if (!empty($sample['facility_name'])) {
        $displayText .= " - " . $sample['facility_name'];
    }
?>
    <option value="<?php echo $sample[$primaryKeyColumn]; ?>" <?php echo (isset($packageCodeId) && isset($sample['referral_manifest_code']) && $sample['referral_manifest_code'] == $packageCodeId) ? 'selected="selected"' : ''; ?>><?php echo htmlspecialchars($displayText); ?></option>
<?php
}
?>