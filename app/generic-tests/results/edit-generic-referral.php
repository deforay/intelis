<?php

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\TestsService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

$testType = 'generic-tests';
$title = "Custom Tests | Edit Referral";
require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);


// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$table = TestsService::getTestTableName($testType);
$primaryKeyColumn = TestsService::getPrimaryColumn($testType);
$patientIdColumn = TestsService::getPatientIdColumn($testType);


$id = base64_decode((string) $_GET['id']);
$codeId = base64_decode((string) $_GET['code']);
// The encoded id is a lab facility id -> reject anything non-numeric.
if (!is_numeric($id)) {
    $id = '';
}
/*$db->where('referral_manifest_code', $codeId);
$db->where('reason_for_referral != ""');
$db->where('reason_for_referral IS NOT NULL');
$genericResult = $db->getOne('form_generic');*/

/* Testing lab list */
$testingLabs = $facilitiesService->getTestingLabs('generic-tests');


$isLisInstance = $general->isLISInstance();
$fromLabId = null;
if ($isLisInstance) {
    $fromLabId = $general->getSystemConfig('sc_testing_lab_id');
}

//get samples
$sQuery = "SELECT 
vl.referred_to_lab_id, 
vl.reason_for_referral, 
vl.referral_manifest_code, 
vl.$primaryKeyColumn,
vl.$patientIdColumn,
f2.facility_name as referral_lab_name, 
f2.facility_code as referral_lab_code, 
MAX(vl.last_modified_datetime) as referral_date,
vl.sample_code,
vl.facility_id
            FROM $table as vl 
LEFT JOIN facility_details as f2 ON vl.referred_to_lab_id = f2.facility_id 
WHERE vl.referred_to_lab_id IS NOT NULL 
    AND vl.referred_to_lab_id != '' 
    AND vl.referred_to_lab_id != 0 
    AND vl.referral_manifest_code = '$codeId' 
GROUP BY vl.referral_manifest_code, f2.facility_name, f2.facility_code";

$genericResult = $db->rawQuery($sQuery);


?>

<link href="/assets/css/multi-select.css" rel="stylesheet" />
<style>
    .select2-selection__choice {
        color: #000000 !important;
    }

    .sampleCounterDiv {
        margin-top: 10px;
        font-weight: bold;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <h1><em class="fa-solid fa-pen-to-square"></em> <?php echo _translate("Edit Referral"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
            <li class="active"><?php echo _translate("Edit Referral"); ?></li>
        </ol>
    </section>

    <section class="content">
        <div class="box box-default">
            <form class="form-horizontal" method="post" name="referralForm" id="referralForm" autocomplete="off"
                action="/generic-tests/results/save-generic-referral-helper.php">
                <div class="box-body" style="margin-top:20px;">
                    <div class="row">
                        <?php if ($isLisInstance && !empty($fromLabId)) { ?>
                        <input type="hidden" name="referralLabId" id="referralLabId"
                            value="<?php echo htmlspecialchars((string) $fromLabId); ?>" />
                    <?php } else { ?>
                        <div class="row">
                            <div class="form-group col-md-6">
                                <div style="margin-left:3%;">
                                    <label for="referralLabId" class="control-label">
                                        <?php echo _translate("Referred By"); ?>
                                        <span class="mandatory">*</span></label>
                                    <select name="referralLabId" id="referralLabId"
                                        class="form-control select2 isRequired"
                                        title="<?php echo _translate("Please select sending lab"); ?>" required>
                                        <?= $general->generateSelectOptions($testingLabs, null, '-- Select --'); ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                        <div class="form-group col-md-6">
                            <div style="margin-left:3%;">
                                <label for="packageCode" class="control-label">
                                    <?php echo _translate("Referral Manifest Code"); ?> <span
                                        class="mandatory">*</span></label>
                               
                                <input type="text" class="form-control isRequired" id="packageCode" name="packageCode"
                                    placeholder="Manifest Code" title="Please enter manifest code" readonly
                                    value="<?php echo htmlspecialchars((string) ($genericResult[0]['referral_manifest_code'] ?? ''), ENT_QUOTES); ?>" />
                                <input type="hidden" class="form-control isRequired" id="module" name="module"
                                    placeholder="" title="" readonly
                                    value="generic-tests" />
                                 <input type="hidden" class="form-control isRequired" id="testType" name="testType"
                                    placeholder="" title="" readonly
                                    value="<?php echo $genericResult[0]['test_type'] ?>" />
                            </div>
                        </div>
                    </div>
                    <div class="row" style="margin-top: 30px;">
                        <div class="col-md-12">
                            <button type="button" class="btn btn-primary btn-sm" onclick="loadSamples();">
                                <em class="fa-solid fa-search"></em> <?php echo _translate("Load Available Samples"); ?>
                            </button>
                        </div>
                    </div>

                    <div class="row sampleSelectionArea" style="margin-top: 20px;">
                        <div class="col-md-5">
                            <label><?php echo _translate("Available Samples"); ?></label>
                            <select name="availableSamples" id="search" class="form-control" size="10"
                                multiple="multiple">
                            </select>
                            <div class="sampleCounterDiv">
                                <?= _translate("Available samples"); ?> : <span id="unselectedCount">0</span>
                            </div>
                        </div>

                        <div class="col-md-2" style="padding-top: 40px;">
                            <button type="button" id="search_rightAll" class="btn btn-block btn-default">
                                <em class="fa-solid fa-forward"></em>
                            </button>
                            <button type="button" id="search_rightSelected" class="btn btn-block btn-primary">
                                <em class="fa-sharp fa-solid fa-chevron-right"></em>
                            </button>
                            <button type="button" id="search_leftSelected" class="btn btn-block btn-warning">
                                <em class="fa-sharp fa-solid fa-chevron-left"></em>
                            </button>
                            <button type="button" id="search_leftAll" class="btn btn-block btn-default">
                                <em class="fa-solid fa-backward"></em>
                            </button>
                        </div>

                        <div class="col-md-5">
                            <label><?php echo _translate("Selected Samples for Referral"); ?></label>
                            <select name="referralSamples[]" id="search_to" class="form-control" size="10"
                                multiple="multiple">
                             
                            </select>
                            <div class="sampleCounterDiv">
                                <?= _translate("Selected samples"); ?> : <span id="selectedCount">0</span>
                            </div>
                        </div>
                    </div>
                    <div class="row sampleSelectionArea" style="margin-top: 30px;">
                        <div class="form-group col-md-6">
                            <div style="margin-left:3%;">
                                <label for="referralToLabId" class="control-label">
                                    <?php echo _translate("Receiving Lab"); ?> <span class="mandatory">*</span></label>
                                <select name="referralToLabId" id="referralToLabId"
                                    class="form-control select2 isRequired"
                                    title="<?php echo _translate("Please select receiving lab"); ?>" required>
                                    <?= $general->generateSelectOptions($testingLabs, $genericResult[0]['referred_to_lab_id'], '-- Select --'); ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="referralReason" class="control-label">
                                <?php echo _translate("Reason for Referral"); ?> <span
                                    class="mandatory">*</span></label>
                            <textarea type="text" class="form-control isRequired" id="referralReason"
                                name="referralReason" placeholder="Enter referral reason"
                                title="Please enter reerral reason"><?php echo htmlspecialchars((string) ($genericResult[0]['reason_for_referral'] ?? ''), ENT_QUOTES); ?></textarea>
                        </div>
                    </div>
                    <div class="box-footer sampleSelectionArea" style="margin-top: 20px;">
                        <input type="hidden" name="type" id="type" value="<?php echo $testType; ?>" />
                        <button type="submit" class="btn btn-primary" onclick="return validateForm();">
                            <em class="fa-solid fa-save"></em> <?php echo _translate("Save Referral"); ?>
                        </button>
                        <a href="/generic-tests/results/generic-referral-list.php" class="btn btn-default">
                            <em class="fa-solid fa-times"></em> <?php echo _translate("Cancel"); ?>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>


<script type="text/javascript">
    $(document).ready(function() {
        <?php if (!($isLisInstance && !empty($fromLabId))) { ?>
            $("#referralLabId").select2({
                width: '100%',
                placeholder: "<?php echo _translate("Select Referral Lab"); ?>"
            });
        <?php } ?>
        $("#referralToLabId").select2({
            width: '100%',
            placeholder: "<?php echo _translate("Select Receiving Lab"); ?>"
        });
        loadSamples();
    });

    function loadSamples() {
        const testTypeId = $("#testType").val();
        const referralLabId = $("#referralLabId").val();


        if (!referralLabId) {
            alert("<?php echo _translate("Please select the sending lab first"); ?>");
            return;
        }

        $.blockUI();

        $.post("/generic-tests/results/get-referral-samples.php", {
            type: 'generic-tests',
            labId: <?php echo json_encode($id); ?>,
            packageCode: <?php echo json_encode($codeId); ?>,
            referralLabId: referralLabId,
            testTypeId: testTypeId
        }, function(data) {
            if (data && data.trim() !== "") {
                $("#search").html(data);
                $(".sampleSelectionArea").show();

               // Move pre-selected items to the right box BEFORE initializing the plugin
                $("#search option[selected='selected']").each(function() {
                    $(this).prop('selected', false).removeAttr('selected');
                    $("#search_to").html($(this));
                });

                initializeMultiselect();
                updateCounters();
            } else {
                alert("<?php echo _translate("No samples available for referral"); ?>");
            }
            $.unblockUI();
        });
    }

    function initializeMultiselect() {
        $('#search').deforayDualBox({
            search: {
                left: '<input type="text" name="q" class="form-control" placeholder="<?php echo _translate("Search"); ?>..." />',
                right: '<input type="text" name="q" class="form-control" placeholder="<?php echo _translate("Search"); ?>..." />',
            },
            fireSearch: function(value) {
                return value.length > 2;
            },
            autoSelectNext: true,
            keepRenderingSort: true,
            moveCallback: function() {
                updateCounters();
            }
        });
    }

    function updateCounters() {
        const unselectedCount = $("#search option").length;
        const selectedCount = $("#search_to option").length;
        $("#unselectedCount").text(unselectedCount);
        $("#selectedCount").text(selectedCount);
    }


    function validateForm() {
        const referralLabId = $("#referralToLabId").val();
        const referralReason = $("#referralReason").val();
        const selectedSamples = $("#search_to option").length;

        if (!referralLabId) {
            alert("<?php echo _translate("Please select a referral lab"); ?>");
            return false;
        }

        if (!referralReason) {
            alert("<?php echo _translate("Please enter the referral reason"); ?>");
            return false;
        }

        if (selectedSamples === 0) {
            alert("<?php echo _translate("Please select at least one sample"); ?>");
            return false;
        }

        $.blockUI();
        return true;
    }
</script>

<?php require_once APPLICATION_PATH . '/footer.php'; ?>