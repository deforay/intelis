<?php

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

$testType = 'tb';
$title = "TB | Edit Referral";
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

$id = base64_decode((string) $_GET['id']);
$codeId = base64_decode((string) $_GET['code']);

$db->where('referral_manifest_code', $codeId);
$db->where('reason_for_referral != ""');
$db->where('reason_for_referral IS NOT NULL');
$tbResult = $db->getOne('form_tb');
/* Testing lab list */
$testingLabs = $facilitiesService->getTestingLabs('tb');
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
        <h1><em class="fa-solid fa-pen-to-square"></em> <?php echo _translate("Referral Labs"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
            <li class="active"><?php echo _translate("Referral Labs"); ?></li>
        </ol>
    </section>
    <pre><?php print_r($tbResult['referral_manifest_code']); ?></pre>
    <section class="content">
        <div class="box box-default">
            <form class="form-horizontal" method="post" name="referralForm" id="referralForm" autocomplete="off"
                action="save-tb-referral-helper.php">
                <div class="box-body" style="margin-top:20px;">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <div style="margin-left:3%;">
                                <label for="referralLabId" class="control-label">
                                    <?php echo _translate("Referral Lab"); ?> <span class="mandatory">*</span></label>
                                <select name="referralLabId" id="referralLabId" class="form-control select2 isRequired"
                                    title="<?php echo _translate("Please select referral Laboratory"); ?>" required>
                                    <?= $general->generateSelectOptions($testingLabs, $id, '-- Select --'); ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group col-md-6">
                            <div style="margin-left:3%;">
                                <label for="packageCode" class="control-label">
                                    <?php echo _translate("Referral Manifest Code"); ?> <span
                                        class="mandatory">*</span></label>
                                <input type="hidden" id="manifestId" name="manifestId"
                                    value="<?php echo $tbResult['referral_manifest_id']; ?>" />
                                <input type="text" class="form-control isRequired" id="packageCode" name="packageCode"
                                    placeholder="Manifest Code" title="Please enter manifest code" readonly
                                    value="<?php echo $tbResult['referral_manifest_code']; ?>" />
                                <input type="hidden" class="form-control isRequired" id="module" name="module"
                                    placeholder="" title="" readonly
                                    value="<?= htmlspecialchars((string) $module); ?>" />
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
                    <div class="row sampleSelectionArea" style="margin-top: 30px;display:none;">
                        <div class="form-group col-md-6">
                            <div style="margin-left:3%;">
                                <label for="referralToLabId" class="control-label">
                                    <?php echo _translate("Referral To Lab"); ?> <span
                                        class="mandatory">*</span></label>
                                <select name="referralToLabId" id="referralToLabId"
                                    class="form-control select2 isRequired"
                                    title="<?php echo _translate("Please select referral To Laboratory"); ?>" required>
                                    <?= $general->generateSelectOptions($testingLabs, $tbResult['referred_to_lab_id'], '-- Select --'); ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="referralReason" class="control-label">
                                <?php echo _translate("Reason for Referral"); ?> <span
                                    class="mandatory">*</span></label>
                            <textarea type="text" class="form-control isRequired" id="referralReason"
                                name="referralReason" placeholder="Enter referral reason"
                                title="Please enter reerral reason"><?php echo $tbResult['reason_for_referral']; ?></textarea>
                        </div>
                    </div>
                    <div class="box-footer sampleSelectionArea" style="margin-top: 20px;display:none;">
                        <input type="hidden" name="type" id="type" value="<?php echo $testType; ?>" />
                        <button type="submit" class="btn btn-primary" onclick="return validateForm();">
                            <em class="fa-solid fa-save"></em> <?php echo _translate("Save Referral"); ?>
                        </button>
                        <a href="/tb/results/tb-referral-list.php" class="btn btn-default">
                            <em class="fa-solid fa-times"></em> <?php echo _translate("Cancel"); ?>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>


<script type="text/javascript">
    $(document).ready(function () {
        $("#referralLabId").select2({
            width: '100%',
            placeholder: "<?php echo _translate("Select Referral Lab"); ?>"
        });
        loadSamples();
    });

    function loadSamples() {
        const referralLabId = $("#referralLabId").val();

        if (!referralLabId) {
            alert("<?php echo _translate("Please select a referral lab first"); ?>");
            return;
        }

        $.blockUI();

        $.post("/tb/results/get-referral-samples.php", {
            type: 'tb',
            labId: '<?php echo $id; ?>',
            packageCode: '<?php echo $codeId; ?>',
            referralLabId: referralLabId
        }, function (data) {
            if (data && data.trim() !== "") {
                $("#search").html(data);
                $(".sampleSelectionArea").show();

                // Move pre-selected items to the right box BEFORE initializing the plugin
                $("#search option[selected='selected']").each(function () {
                    $(this).prop('selected', false).removeAttr('selected');
                    $("#search_to").append($(this));
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
            fireSearch: function (value) {
                return value.length > 2;
            },
            autoSelectNext: true,
            keepRenderingSort: true,
            moveCallback: function () {
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
        const referralLabId = $("#referralLabId").val();
        const selectedSamples = $("#search_to option").length;

        if (!referralLabId) {
            alert("<?php echo _translate("Please select a referral lab"); ?>");
            return false;
        }

        if (selectedSamples === 0) {
            alert("<?php echo _translate("Please select at least one sample"); ?>");
            return false;
        }

        // Select all options in the right box before submitting
        $("#search_to option").prop('selected', true);

        $.blockUI();
        return true;
    }
</script>

<?php require_once APPLICATION_PATH . '/footer.php'; ?>