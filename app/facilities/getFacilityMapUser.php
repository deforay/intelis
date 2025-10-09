<?php


use App\Services\UsersService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$fType = ($_POST['fType'] == 1) ? 4 : 1;
$facilityId = $_POST['facilityId'];
$vlfmQuery = "SELECT GROUP_CONCAT(DISTINCT vlfm.user_id SEPARATOR ',') as userId
                FROM user_facility_map as vlfm
                JOIN facility_details as fd ON fd.facility_id=vlfm.facility_id
                WHERE fd.facility_id = ?";
$vlfmResult = $db->rawQueryOne($vlfmQuery, [$facilityId]);

$selectedUserIds = !empty($vlfmResult['userId']) ? explode(",", (string) $vlfmResult['userId']) : [];

$uResult = $usersService->getActiveUsers();

?>
<div class="col-md-12 col-lg-12">
    <h4 style="margin-left:20px; font-weight:bold;"><?php echo _translate("User-Facility Map"); ?></h4>
    <div class="col-xs-5">
        <select name="from[]" id="search" class="form-control" size="8" multiple="multiple">
            <?php
            foreach ($uResult as $uName) {
                if (!in_array($uName['user_id'], $selectedUserIds)) {
            ?>
                    <option value="<?= $uName['user_id']; ?>"><?= ($uName['user_name']); ?></option>
            <?php }
            }
            ?>
        </select>
        <div class="sampleCounterDiv"><?= _translate("Number of unselected samples"); ?> : <span id="unselectedCount"></span></div>

    </div>

    <div class="col-xs-2">
        <button type="button" id="search_rightAll" class="btn btn-block"><em class="fa-solid fa-forward"></em></button>
        <button type="button" id="search_rightSelected" class="btn btn-block"><em class="fa-sharp fa-solid fa-chevron-right"></em></button>
        <button type="button" id="search_leftSelected" class="btn btn-block"><em class="fa-sharp fa-solid fa-chevron-left"></em></button>
        <button type="button" id="search_leftAll" class="btn btn-block"><em class="fa-solid fa-backward"></em></button>
    </div>

    <div class="col-xs-5">
        <select name="to[]" id="search_to" class="form-control" size="8" multiple="multiple">
            <?php foreach ($uResult as $uName) {
                if (isset($selectedUserIds) && in_array($uName['user_id'], $selectedUserIds)) { ?>
                    <option value="<?= $uName['user_id']; ?>"><?= ($uName['user_name']); ?></option>
            <?php }
            } ?>
        </select>
        <div class="sampleCounterDiv"><?= _translate("Number of selected samples"); ?> : <span id="selectedCount"></span></div>

    </div>
</div>
<script type="text/javascript">
    $(document).ready(function() {

        $('#search').deforayDualBox({
            search: {
                left: '<input type="text" name="q" class="form-control" placeholder="<?php echo _translate("Search"); ?>..." />',
                right: '<input type="text" name="q" class="form-control" placeholder="<?php echo _translate("Search"); ?>..." />',
            },
            fireSearch: function(value) {
                return value.length > 2;
            },
            autoSelectNext: true,
            keepRenderingSort: true
        });

    });
</script>