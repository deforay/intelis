<?php

use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\GeoLocationsService;

$title = _("Export High VL and Virologic Failure Report");

require_once APPLICATION_PATH . '/header.php';
/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

$healthFacilites = $facilitiesService->getHealthFacilities('vl');
$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, null, "-- Select --");
?>
<style>
    .select2-selection__choice {
        color: black !important;
    }

    .select2-selection--multiple {
        max-height: 100px;
        width: auto;
        overflow-y: scroll !important;
    }
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-book"></em>
            <?php echo _("High VL and Virologic Failure Report"); ?>
        </h1>
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box" id="filterDiv">
                    <table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:98%;">
                        <tr>
                            <td><strong><?php echo _("Sample Collection Date"); ?>&nbsp;:</strong></td>
                            <td>
                                <input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control daterangefield" placeholder="<?php echo _('Select Collection Date'); ?>" style="width:220px;background:#fff;" />
                            </td>
                            <td><strong><?php echo _("Sample Tested Date"); ?>&nbsp;:</strong></td>
                            <td>
                                <input type="text" id="sampleTestDate" name="sampleTestDate" class="form-control daterangefield" placeholder="<?php echo _('Select Tested Date'); ?>" style="width:220px;background:#fff;" />
                            </td>
                            <td><strong><?php echo _("Facility Name"); ?> :</strong></td>
                            <td>
                                <select class="form-control" id="facilityName" name="facilityName" title="<?php echo _('Please select facility name'); ?>" style="width:220px;">
                                    <?= $facilitiesDropdown; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="6">
                                &nbsp;<button onclick="exportInexcel();" value="Search" class="btn btn-primary btn-sm"><span><?php echo _("Generate report"); ?></span></button>

                                &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _("Clear Search"); ?></span></button>

                            </td>
                        </tr>
                    </table>
                    <!-- /.box-body -->
                </div>
                <!-- /.box -->
            </div>
            <!-- /.col -->
        </div>
        <!-- /.row -->
    </section>
    <!-- /.content -->
</div>
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript">
    $(document).ready(function() {
        $("#facilityName").select2({
            placeholder: "<?php echo _("Select Facilities"); ?>"
        });
        $('.daterangefield').daterangepicker({
                locale: {
                    cancelLabel: "<?= _("Clear"); ?>",
                    format: 'DD-MMM-YYYY',
                    separator: ' to ',
                },
                showDropdowns: true,
                alwaysShowCalendars: false,
                startDate: moment().subtract(28, 'days'),
                endDate: moment(),
                maxDate: moment(),
                ranges: {
                    'Today': [moment(), moment()],
                    'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'Last 90 Days': [moment().subtract(89, 'days'), moment()],
                    'Last 120 Days': [moment().subtract(119, 'days'), moment()],
                    'Last 180 Days': [moment().subtract(179, 'days'), moment()],
                    'Last 12 Months': [moment().subtract(12, 'month').startOf('month'), moment().endOf('month')]
                }
            },
            function(start, end) {
                startDate = start.format('YYYY-MM-DD');
                endDate = end.format('YYYY-MM-DD');
            });

        $('.daterangefield').on('cancel.daterangepicker', function(ev, picker) {
            $(this).val('');
        });
    });

    function exportInexcel() {
        if($('#facilityName').val() == ""){
            alert("Please choose facility name.")
            return false;
        }
        // $.blockUI();
        $.post('export-vl-vlns-reports.php', {
            sampleCollectionDate: $('#sampleCollectionDate').val(),
            sampleTestDate: $('#sampleTestDate').val(),
            facilityName: $('#facilityName').val(),
            withAlphaNum: 'yes',
            },
            function(data) {
                if (data == "" || data == null || data == undefined) {
                    // $.unblockUI();
                    alert("<?php echo _("Unable to generate excel"); ?>.");
                } else {
                    // $.unblockUI();
                    window.open('/download.php?f=' + data, '_blank');
                }
            });
    }
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
