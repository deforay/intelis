<?php

use App\Services\TestsService;
use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
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
$referToLab = $_POST['labId'];
$table = TestsService::getTestTableName($testType);
$primaryKeyColumn = TestsService::getPrimaryColumn($testType);
$patientIdColumn = TestsService::getPatientIdColumn($testType);
// Eligible-for-referral samples belong to the sending lab. A user with a definite
// operating lab (LIS / cloud-LIS) is pinned to it from the session, authoritative
// over any POST value. Everyone else -- an all-labs cloud-LIS operator with no
// assigned lab included -- refers for the lab they chose in the "Referred From"
// selector that add-tb-referral.php renders for exactly that case. This mirrors
// app/generic-tests/results/get-referral-samples.php, which already did both.
//
// The old fallback also dereferenced $general, which this file never builds, so it
// fatalled outright for any session whose labId was null -- masked until now only
// because ?? short-circuits whenever a lab IS resolved.
$lisLabId = $_SESSION['labId'] ?? $labId;

// The lab axis alone does not isolate this endpoint: it is an AJAX endpoint, the
// sending lab can be a POST value whenever the session has no lab of its own, and
// the response carries sample codes, patient IDs and facility names. Apply the
// facility axis too, exactly as every sample grid does, so a user can never
// enumerate patients from facilities outside their map by naming another lab.
// facilityMap is a clean integer CSV normalised at its source
// (FacilitiesService::getUserFacilityMap), so it interpolates safely.
$facilityScope = '';
if (!empty($_SESSION['facilityMap'])) {
    $facilityScope = " AND vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
}


$queryParams = [];
$condition = "(COALESCE(vl.referred_to_lab_id, 0) = 0 OR vl.referred_to_lab_id = '')";
if (isset($packageCodeId) && !empty($packageCodeId)) {
    if(isset($referToLab) && !empty($referToLab)){
        $labId = $referToLab;
    }
    $condition = "(COALESCE(vl.referred_to_lab_id, 0) = 0 OR vl.referred_to_lab_id = '' OR vl.referred_to_lab_id = ?)";
    $queryParams[] = (int) $labId;
}
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
            AND (vl.lab_id IS NOT NULL AND vl.lab_id = ?)
            $facilityScope
          ORDER BY vl.sample_code ASC";
$queryParams[] = (int) $lisLabId;
$result = $db->rawQuery($query, $queryParams);

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
    <option value="<?php echo $sample[$primaryKeyColumn]; ?>" <?php echo (isset($packageCodeId) && isset($sample['referral_manifest_code']) && $sample['referral_manifest_code'] == $packageCodeId) ? 'selected="selected"' : ''; ?>><?php echo htmlspecialchars((string) $displayText); ?></option>
    <?php
}
?>