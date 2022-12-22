<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$general = new \Vlsm\Models\General();

$sQuery = "SELECT f.facility_id, f.facility_name, (SELECT MAX(requested_on) FROM track_api_requests WHERE request_type = 'requests' AND facility_id = f.facility_id GROUP BY facility_id  ORDER BY requested_on DESC) AS request, (SELECT MAX(requested_on) FROM track_api_requests WHERE request_type = 'results' AND facility_id = f.facility_id GROUP BY facility_id ORDER BY requested_on DESC) AS results, tar.test_type, tar.requested_on  FROM facility_details AS f JOIN track_api_requests AS tar ON tar.facility_id = f.facility_id ";
if (isset($_POST['facilityName']) && trim($_POST['facilityName']) != '') {
    $sWhere[] = ' f.facility_id IN (' . $_POST['facilityName'] . ')';
}
if (isset($_POST['province']) && trim($_POST['province']) != '') {
    $sWhere[] = ' f.facility_state_id = "' . $_POST['province'] . '"';
}
if (isset($_POST['district']) && trim($_POST['district']) != '') {
    $sWhere[] = ' f.facility_district_id = "' . $_POST['district'] . '"';
}

if (isset($_POST['labId']) && trim($_POST['labId']) != '') {
    $sWhere[] = ' f.facility_id = "' . base64_decode($_POST['labId']) . '"';
}
if (!empty($sWhere)) {
    $sQuery = $sQuery . " WHERE " . implode(" AND ", $sWhere);
}
$sQuery = $sQuery . " GROUP BY f.facility_id ORDER BY tar.requested_on DESC";
// die($sQuery);
$rResult = $db->rawQuery($sQuery);
foreach ($rResult as $key => $aRow) { ?>
    <tr class="<?php echo $color; ?>">
        <td><?php echo ucwords($aRow['facility_name']); ?></td>
        <td><?php echo ucwords($aRow['test_type']); ?></td>
        <td><?php echo $general->humanReadableDateFormat($aRow['request'], true); ?></td>
        <td><?php echo $general->humanReadableDateFormat($aRow['results'], true); ?></td>
    </tr>
<?php } ?>