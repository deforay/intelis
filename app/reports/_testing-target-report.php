<?php

// The Testing Target Report page, shared by every test. Each test's own page
// sets $testType, $title, $dataUrl and $exportUrl, and $targetKind for a target
// other than tests, then includes this.
// One row is one testing lab for one month, set against that lab's monthly
// test target. TestingTargetReportService explains how each count is taken.

use App\Services\CommonService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\TestingTargetReportService;

require_once __DIR__ . '/_testing-target-common.php';
require_once APPLICATION_PATH . '/header.php';

$targetKind ??= TestingTargetReportService::KIND_TESTS;
$columns = _testingTargetColumns($targetKind);
// How each count is taken, shown above the table.
$notes = $targetKind === TestingTargetReportService::KIND_SUPPRESSED
    ? [
        _translate("Tested counts samples tested in the month that were not rejected."),
        _translate("Suppressed counts those whose result is suppressed."),
    ]
    : [
        _translate("Received counts samples received at the lab in the month."),
        _translate("Rejected counts those received in the month and rejected."),
        _translate("Tested counts samples tested in the month that were not rejected."),
    ];
$notes[] = _translate("Cancelled samples are left out.");

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

$testingLabsDropdown = $general->generateSelectOptions($facilitiesService->getTestingLabs($testType), null, false);

?>
<link rel="stylesheet" href="/assets/css/jquery.multiselect.css" type="text/css" />
<div class="content-wrapper">
  <section class="content-header">
    <h1><em class="fa-solid fa-book"></em> <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
  </section>
  <section class="content">
    <div class="row">
      <div class="col-xs-12">
        <div class="box" id="filterDiv">
          <table aria-describedby="table" class="table" aria-hidden="true"
            style="margin-left:1%;margin-top:20px;width:98%;">
            <tr>
              <td><strong><?= _htmlTranslate("Date Range"); ?>&nbsp;:</strong></td>
              <td>
                <input type="text" id="dateRange" name="dateRange" class="form-control" readonly
                  style="background:#fff;"
                  title="<?= _htmlTranslate('Choose the months to show'); ?>" />
              </td>
              <td><strong><?= _htmlTranslate("Testing Lab"); ?>&nbsp;:</strong></td>
              <td style="width: 30%;">
                <select id="labIds" name="labIds" multiple title="<?= _htmlTranslate('Please select lab name'); ?>">
                  <?= $testingLabsDropdown; ?>
                </select>
              </td>
            </tr>
            <tr>
              <td><strong><?= _htmlTranslate("Target Type"); ?>&nbsp;:</strong></td>
              <td>
                <select class="form-control" id="targetType" name="targetType"
                  title="<?= _htmlTranslate('Please select Target Type'); ?>">
                  <option value="1"><?= _htmlTranslate("Monthly Targets not met"); ?></option>
                  <option value="2"><?= _htmlTranslate("Monthly Targets met"); ?></option>
                  <option value="3"><?= _htmlTranslate("Show all"); ?></option>
                </select>
              </td>
            </tr>
            <tr>
              <td colspan="4">
                <input type="button" onclick="searchTargets();" value="<?= _htmlTranslate("Search"); ?>"
                  class="btn btn-success btn-sm">
                &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location">
                  <span><?= _htmlTranslate('Reset'); ?></span>
                </button>
                &nbsp;<button class="btn btn-info btn-sm" type="button" onclick="exportTargets()">
                  <?= _htmlTranslate("Export to excel"); ?>
                </button>
              </td>
            </tr>
          </table>
          <div class="box-body">
            <p class="text-muted">
              <?= htmlspecialchars(implode(' ', $notes), ENT_QUOTES, 'UTF-8'); ?>
            </p>
            <table aria-describedby="table" id="targetTable" class="table table-bordered table-striped"
              aria-hidden="true">
              <thead>
                <tr>
                  <?php foreach ($columns as [$label]) { ?>
                    <th scope="col"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></th>
                  <?php } ?>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="<?= count($columns); ?>" class="dataTables_empty">
                    <?= _htmlTranslate("Loading data from server"); ?>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>
<script type="text/javascript" src="/assets/js/jquery.multiselect.js"></script>
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="<?= _asset('/assets/plugins/daterangepicker/daterangepicker.js') ?>"></script>
<script type="text/javascript">
  let targetTable = null;

  function targetFilters() {
    return {
      dateRange: $("#dateRange").val(),
      labIds: $("#labIds").val() || [],
      targetType: $("#targetType").val()
    };
  }

  $(document).ready(function() {
    $("#labIds").multipleSelect({
      placeholder: "<?= _jsTranslate("Select lab name"); ?>",
      width: '100%'
    });
    // The last 12 months, this one included, unless the reader picks others.
    $('#dateRange').daterangepicker({
      locale: {
        cancelLabel: "<?= _jsTranslate("Clear"); ?>",
        format: 'DD-MMM-YYYY',
        separator: ' to '
      },
      showDropdowns: true,
      startDate: moment().subtract(11, 'months').startOf('month'),
      endDate: moment(),
      maxDate: moment(),
      ranges: {
        "<?= _jsTranslate("This Month"); ?>": [moment().startOf('month'), moment()],
        "<?= _jsTranslate("Last Month"); ?>": [
          moment().subtract(1, 'month').startOf('month'),
          moment().subtract(1, 'month').endOf('month')
        ],
        "<?= _jsTranslate("Last 3 Months"); ?>": [
          moment().subtract(3, 'month').startOf('month'),
          moment().subtract(1, 'month').endOf('month')
        ],
        "<?= _jsTranslate("Last 12 Months"); ?>": [moment().subtract(11, 'months').startOf('month'), moment()]
      }
    });

    targetTable = $('#targetTable').dataTable({
      "bAutoWidth": false,
      "bInfo": true,
      "iDisplayLength": 25,
      "bRetrieve": true,
      "aoColumns": [{}].concat(Array(<?= count($columns) - 1; ?>).fill({"sClass": "center"})),
      "aaSorting": [[0, "asc"]],
      "bProcessing": true,
      "bServerSide": true,
      "sAjaxSource": <?= json_encode($dataUrl); ?>,
      "fnServerData": function(sSource, aoData, fnCallback) {
        const filters = targetFilters();
        aoData.push({"name": "dateRange", "value": filters.dateRange});
        aoData.push({"name": "targetType", "value": filters.targetType});
        filters.labIds.forEach(function(id) {
          aoData.push({"name": "labIds[]", "value": id});
        });
        $.ajax({
          "dataType": 'json',
          "type": "POST",
          "url": sSource,
          "data": aoData,
          "success": fnCallback
        });
      }
    });
  });

  function searchTargets() {
    targetTable.fnDraw();
  }

  function exportTargets() {
    // Runs in the background. Progress shows in the Exports menu and the file
    // downloads when it is ready.
    IntelisExport.start(<?= json_encode($exportUrl); ?>, targetFilters(), <?= json_encode($title); ?>);
  }
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
