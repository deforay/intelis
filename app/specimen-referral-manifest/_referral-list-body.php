<?php

/**
 * Shared "Referral List" page.
 *
 * One body for the two modules that have a referral flow (tb, generic-tests).
 * The thin per-module page under /<module>/results/ only builds a
 * $referralListPage config array and requires this file; everything below --
 * header, the bulk-reassign panel, the server-side referral grid and the page
 * scripts -- is identical across the two modules.
 *
 * $referralListPage keys:
 *   testType   string  module slug, e.g. 'tb' / 'generic-tests'. Also POSTed as
 *                      `type` to the bulk-update helper and used for getTestingLabs.
 *   title      string  already-translated browser/page title (set before header).
 *   heading    string  already-translated H1 + breadcrumb label.
 *   referUrl   string  absolute URL of the "Refer Samples" page.
 *   ajaxSource string  relative URL of the grid data source (resolves against the
 *                      per-module page URL, which is unchanged).
 *   updateUrl  string  relative URL of the bulk-update helper.
 *   pdfUrl     string  absolute URL of the manifest PDF generator.
 */

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

/** @var array $referralListPage */
$referralListPage ??= [];

$testType   = $referralListPage['testType'] ?? '';
$title      = $referralListPage['title'] ?? _translate("Referral List");
$heading    = $referralListPage['heading'] ?? _translate("Referral List");
$referUrl   = $referralListPage['referUrl'] ?? '#';
$ajaxSource = $referralListPage['ajaxSource'] ?? '';
$updateUrl  = $referralListPage['updateUrl'] ?? '';
$pdfUrl     = $referralListPage['pdfUrl'] ?? '';

require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/* Testing lab list for bulk update */
$testingLabs = $facilitiesService->getTestingLabs($testType);
?>

<style>
    .select2-selection__choice {
        color: #000000 !important;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <h1><em class="fa-solid fa-list"></em> <?php echo htmlspecialchars($heading); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
            <li class="active"><?php echo htmlspecialchars($heading); ?></li>
        </ol>
    </section>

    <section class="content">
        <div class="box box-default">
            <div class="box-header with-border">
                <a href="<?php echo htmlspecialchars($referUrl); ?>" class="btn btn-primary pull-right">
                    <em class="fa-solid fa-plus"></em> <?php echo _translate("Refer Samples"); ?>
                </a>
            </div>

            <div class="box-body">
                <!-- Bulk Action Section -->
                <div class="row" id="bulkActionSection" style="display: none; margin-bottom: 20px;">
                    <div class="col-md-12">
                        <div class="alert alert-info">
                            <div class="row">
                                <div class="col-md-4">
                                    <label><?php echo _translate("Reassign Selected Samples to Lab"); ?></label>
                                    <select name="bulkReferralLabId" id="bulkReferralLabId" class="form-control select2">
                                        <?= $general->generateSelectOptions($testingLabs, null, '-- Select Lab --'); ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label><?php echo _translate("Reason for Referral Lab Change"); ?></label>
                                    <textarea name="reasonForBulkReferralLab" id="reasonForBulkReferralLab" placeholder="Enter the reason for bulk referral lab change" title="Please enter the reason for bulk referral lab change" class="form-control"></textarea>
                                </div>
                                <div class="col-md-4" style="padding-top: 25px;">
                                    <button type="button" class="btn btn-primary" onclick="bulkUpdateReferral();">
                                        <em class="fa-solid fa-save"></em> <?php echo _translate("Update Selected"); ?>
                                    </button>
                                    <button type="button" class="btn btn-default" onclick="clearSelection();">
                                        <em class="fa-solid fa-times"></em> <?php echo _translate("Clear Selection"); ?>
                                    </button>
                                </div>
                                <span id="selectedCountLabel" class="text-primary text-bold" style="margin-left: 15px;border-radius: 10px;border: 1px solid white;background: white;padding: 5px;"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DataTable -->
                <table aria-describedby="referral-list-table" id="referralDataTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <!-- <th scope="col" style="width: 3%;">
                                <input type="checkbox" id="selectAll" />
                            </th> -->
                            <th scope="col"><?php echo _translate("Referral Manifest Code"); ?></th>
                            <th scope="col"><?php echo _translate("No of Samples"); ?></th>
                            <th scope="col"><?php echo _translate("Referred To Lab"); ?></th>
                            <th scope="col"><?php echo _translate("Referral Date"); ?></th>
                            <th scope="col"><?php echo _translate("Reason for Referral"); ?></th>
                            <th scope="col"><?php echo _translate("Action"); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="6" class="dataTables_empty"><?php echo _translate("Loading data from server"); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<script type="text/javascript">
    var oTable = null;
    var selectedSamples = [];

    $(document).ready(function() {
        $.blockUI();

        // Initialize select2
        $("#bulkReferralLabId").select2({
            width: '100%',
            placeholder: "<?php echo _translate("Select Referral Lab"); ?>"
        });

        // Initialize DataTable
        oTable = $('#referralDataTable').dataTable({
            "bJQueryUI": false,
            "bAutoWidth": false,
            "bInfo": true,
            "bScrollCollapse": true,
            "bStateSave": true,
            "bRetrieve": true,
            "aoColumns": [{
                    "sClass": "center"
                },
                {
                    "sClass": "center"
                },
                {
                    "sClass": "center"
                },
                {
                    "sClass": "center"
                },
                {
                    "sClass": "center"
                },
                {
                    "sClass": "center",
                    "bSortable": false
                }
            ],
            "aaSorting": [
                [3, "desc"]
            ],
            "bProcessing": true,
            "bServerSide": true,
            "sAjaxSource": "<?php echo htmlspecialchars($ajaxSource); ?>",
            "fnServerData": function(sSource, aoData, fnCallback) {
                $.ajax({
                    "dataType": 'json',
                    "type": "POST",
                    "url": sSource,
                    "data": aoData,
                    "success": fnCallback
                });
            }
        });

        $.unblockUI();

        // Handle select all checkbox
        $('#selectAll').on('click', function() {
            var isChecked = $(this).is(':checked');
            $('.sample-checkbox:visible').prop('checked', isChecked);
            updateSelectedSamples();
        });

        // Handle individual checkbox click
        $(document).on('change', '.sample-checkbox', function() {
            updateSelectedSamples();

            // Update select all checkbox state
            var totalVisible = $('.sample-checkbox:visible').length;
            var totalChecked = $('.sample-checkbox:checked').length;
            $('#selectAll').prop('checked', totalVisible > 0 && totalVisible === totalChecked);
        });
    });

    function updateSelectedSamples() {
        selectedSamples = [];
        $('.sample-checkbox:checked').each(function() {
            selectedSamples.push($(this).val());
        });

        if (selectedSamples.length > 0) {
            $('#bulkActionSection').show();
            $('#selectedCountLabel').text('<?php echo _translate("Selected"); ?>: ' + selectedSamples.length);
        } else {
            $('#bulkActionSection').hide();
            $('#selectedCountLabel').text('');
        }
    }

    function clearSelection() {
        $('.sample-checkbox').prop('checked', false);
        $('#selectAll').prop('checked', false);
        updateSelectedSamples();
    }

    function bulkUpdateReferral() {
        var newReferralLabId = $('#bulkReferralLabId').val();
        var reasonForReferralLabChange = $('#reasonForBulkReferralLab').val();

        if (!newReferralLabId) {
            alert("<?php echo _translate("Please select a referral lab"); ?>");
            return;
        }

        if (selectedSamples.length === 0) {
            alert("<?php echo _translate("Please select at least one sample"); ?>");
            return;
        }

        var confirmMsg = "<?php echo _translate("Are you sure you want to reassign"); ?> " +
            selectedSamples.length +
            " <?php echo _translate("sample(s) to the selected lab?"); ?>";

        if (!confirm(confirmMsg)) {
            return;
        }

        $.blockUI();

        $.post("<?php echo htmlspecialchars($updateUrl); ?>", {
            type: '<?php echo htmlspecialchars($testType); ?>',
            newReferralLabId: newReferralLabId,
            reasonForReferralLabChange: reasonForReferralLabChange,
            sampleIds: selectedSamples
        }, function(response) {
            $.unblockUI();

            if (response.status === 'success') {
                alert(response.message);
                clearSelection();
                oTable.fnDraw();
            } else {
                alert(response.message || "<?php echo _translate("An error occurred"); ?>");
            }
        }, 'json').fail(function() {
            $.unblockUI();
            alert("<?php echo _translate("An error occurred. Please try again."); ?>");
        });
    }

    function generateManifestPDF(pId) {

        $.post('<?php echo htmlspecialchars($pdfUrl); ?>', {
                id: pId
            },
            function(data) {
                if (data == "" || data == null || data == undefined) {
                    alert('Unable to generate manifest PDF');
                } else {
                    // Serve through the masked, web-root-validated download handler
                    // instead of exposing the raw /temporary/ path.
                    window.open('/download.php?f=' + encodeURIComponent(btoa('sample-manifests/' + String(data).trim())), '_blank');
                }

            });
    }
</script>

<?php require_once APPLICATION_PATH . '/footer.php'; ?>
