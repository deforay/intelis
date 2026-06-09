<?php

use App\Services\TestsService;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

$testType = 'generic-tests';
$title = "Custom Tests | Add Referral";
require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/* Testing lab list */
$testingLabs = $facilitiesService->getTestingLabs('generic-tests');

/* Active custom test types - the selected test drives the manifest code prefix */
$testTypeResult = $db->rawQuery("SELECT test_type_id, test_short_code, test_standard_name
                                 FROM r_test_types
                                 WHERE test_status = 'active'
                                 ORDER BY test_standard_name ASC");

/* Random suffix shared by the generated manifest code (prefix is set on test selection) */
$manifestCodeSuffix = strtoupper(date('ymdH') . MiscUtility::generateRandomString(4));

$isLisInstance = $general->isLISInstance();

$fromLabId = null;
if ($isLisInstance) {
    $fromLabId = $general->getSystemConfig('sc_testing_lab_id');
}

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
        <h1><em class="fa-solid fa-pen-to-square"></em> <?php echo _translate("Add Referral"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
            <li class="active"><?php echo _translate("Add Referral"); ?></li>
        </ol>
    </section>

    <section class="content">
        <div class="box box-default">
            <form class="form-horizontal" method="post" name="referralForm" id="referralForm" autocomplete="off"
                action="/generic-tests/results/save-generic-referral-helper.php">

                <div class="box-body" style="margin-top:20px;">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <div style="margin-left:3%;">
                                <label for="testType" class="control-label">
                                    <?php echo _translate("Test Type"); ?>
                                    <span class="mandatory">*</span></label>
                                <select name="testType" id="testType" class="form-control select2 isRequired"
                                    title="<?php echo _translate("Please select test type"); ?>"
                                    onchange="updateManifestCode();" required>
                                    <option value=""> -- <?php echo _translate("Select"); ?> -- </option>
                                    <?php foreach ($testTypeResult as $row) { ?>
                                        <option value="<?php echo $row['test_type_id']; ?>"
                                            data-short="<?php echo htmlspecialchars((string) $row['test_short_code']); ?>">
                                            <?php echo htmlspecialchars((string) $row['test_standard_name']); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group col-md-6">
                            <div style="margin-left:3%;">
                                <label for="packageCode" class="control-label">
                                    <?php echo _translate("Referral Manifest Code"); ?> <span
                                        class="mandatory">*</span></label>
                                <input type="text" class="form-control isRequired" id="packageCode" name="packageCode"
                                    placeholder="<?php echo _htmlTranslate("Select a test type to generate the code"); ?>"
                                    title="<?php echo _htmlTranslate("Please enter manifest code"); ?>" readonly
                                    value="" />
                                <input type="hidden" class="form-control isRequired" id="module" name="module"
                                    placeholder="" title="" readonly value="generic-tests" />
                            </div>
                        </div>
                    </div>
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
                    <div class="row" style="margin-top: 30px;">
                        <div class="col-md-12">
                            <button type="button" class="btn btn-primary btn-sm" onclick="loadSamples();">
                                <em class="fa-solid fa-search"></em> <?php echo _translate("Load Available Samples"); ?>
                            </button>
                        </div>
                    </div>

                    <div class="row sampleSelectionArea" style="margin-top: 20px; display: none;">
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
                    <div class="row sampleSelectionArea" style="margin-top: 30px;display:none;">
                        <div class="form-group col-md-6">
                            <div style="margin-left:3%;">
                                <label for="referralToLabId" class="control-label">
                                    <?php echo _translate("Receiving Lab"); ?> <span class="mandatory">*</span></label>
                                <select name="referralToLabId" id="referralToLabId"
                                    class="form-control select2 isRequired"
                                    title="<?php echo _translate("Please select receiving lab"); ?>" required>
                                    <?= $general->generateSelectOptions($testingLabs, null, '-- Select --'); ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="referralReason" class="control-label">
                                <?php echo _translate("Reason for Referral"); ?> <span
                                    class="mandatory">*</span></label>
                            <textarea type="text" class="form-control isRequired" id="referralReason"
                                name="referralReason" placeholder="Enter referral reason"
                                title="Please enter reerral reason"></textarea>
                        </div>
                    </div>
                    <div class="box-footer sampleSelectionArea" style="margin-top: 20px;display:none;">
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
        $("#testType").select2({
            width: '100%',
            placeholder: "<?php echo _translate("Select Test Type"); ?>"
        });
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
    });

    function updateManifestCode() {
        const shortCode = $("#testType").find(":selected").attr("data-short") || "";
        // Hide any previously loaded samples since they may belong to another test type
        $(".sampleSelectionArea").hide();
        if (shortCode !== "") {
            $("#packageCode").val("R" + shortCode.toUpperCase() + "<?php echo $manifestCodeSuffix; ?>");
            <?php if ($isLisInstance && !empty($fromLabId)) { ?>
                // On a LIS instance the sending lab is fixed, so load samples as soon as a test type is chosen
                loadSamples();
            <?php } ?>
        } else {
            $("#packageCode").val("");
        }
    }

    function loadSamples() {
        const testTypeId = $("#testType").val();
        const referralLabId = $("#referralLabId").val();

        if (!testTypeId) {
            alert("<?php echo _translate("Please select the test type"); ?>");
            return;
        }

        if (!referralLabId) {
            alert("<?php echo _translate("Please select the sending laboratory"); ?>");
            return;
        }

        $.blockUI();

        $.post("/generic-tests/results/get-referral-samples.php", {
            type: 'generic-tests',
            testTypeId: testTypeId,
            referralLabId: referralLabId
        }, function(data) {
            if (data && data.trim() !== "") {
                $("#search").html(data);
                $(".sampleSelectionArea").show();
                initializeMultiselect();
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
            keepRenderingSort: true
        });
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