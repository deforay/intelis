<?php


use App\Registries\ContainerRegistry;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

$fType = ($_POST['fType'] == 1) ? 4 : 1;
$facilityId = $_POST['facilityId'];
$vlfmQuery = "SELECT GROUP_CONCAT(DISTINCT vlfm.user_id SEPARATOR ',') as userId
                FROM user_facility_map as vlfm
                JOIN facility_details as fd ON fd.facility_id=vlfm.facility_id
                WHERE fd.facility_id = ?";
$vlfmResult = $db->rawQueryOne($vlfmQuery, array($facilityId));

$selectedUserIds = !empty($vlfmResult['userId']) ? explode(",", $vlfmResult['userId']) : [];


$uQuery = "SELECT * FROM user_details WHERE `status` like 'active' ORDER by user_name";

$uResult = $db->rawQuery($uQuery);
?>
<div class="col-md-12 col-lg-12">
    <h4 style="margin-left:20px; font-weight:bold;"><?php echo _("User-Facility Map"); ?></h4>
    <div class="col-xs-5">
        <select name="from[]" id="search" class="form-control" size="8" multiple="multiple">
            <?php
            foreach ($uResult as $uName) {
            ?>
                <option value="<?= $uName['user_id']; ?>" <?php echo (in_array($uName['user_id'], $selectedUserIds) ? "selected='selected'" : ''); ?>><?= ($uName['user_name']); ?></option>
            <?php
            }
            ?>
        </select>
    </div>

    <div class="col-xs-2">
        <button type="button" id="search_rightAll" class="btn btn-block"><em class="fa-solid fa-forward"></em></button>
        <button type="button" id="search_rightSelected" class="btn btn-block"><em class="fa-sharp fa-solid fa-chevron-right"></em></button>
        <button type="button" id="search_leftSelected" class="btn btn-block"><em class="fa-sharp fa-solid fa-chevron-left"></em></button>
        <button type="button" id="search_leftAll" class="btn btn-block"><em class="fa-solid fa-backward"></em></button>
    </div>

    <div class="col-xs-5">
        <select name="to[]" id="search_to" class="form-control" size="8" multiple="multiple"></select>
    </div>
</div>
<script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#search').multiselect({
            search: {
                left: '<input type="text" name="q" class="form-control" placeholder="Search..." />',
                right: '<input type="text" name="q" class="form-control" placeholder="Search..." />',
            },
            fireSearch: function(value) {
                return value.length > 3;
            }
        });
        setTimeout(function() {
            $("#search_rightSelected").trigger('click');
        }, 300);

    });
</script>