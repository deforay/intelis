<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;

require_once APPLICATION_PATH . '/header.php';

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);


$facilityQuery = "SELECT * FROM facility_details where facility_type = 2 AND status='active' Order By facility_name";

if ($general->isLISInstance() && isset($_SESSION['instance']['labId'])) {
  $facilityQuery .= " AND facility_id = " . $_SESSION['instance']['labId'];
}

$facilityResult = $db->rawQuery($facilityQuery);
?>
<link href="/assets/css/multi-select.css" rel="stylesheet" />
<style>
  .ms-container {
    width: 100%;
  }

  .select2-selection__choice {
    color: #000000 !important;
  }

  table.valign-mid td {
    vertical-align: middle !important;
  }
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><em class="fa-solid fa-calendar-check"></em>
      <?php echo _translate("VL Lab Weekly Report"); ?>
      <!--<ol class="breadcrumb">-->
      <!--  <li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>-->
      <!--  <li class="active">Export Result</li>-->
      <!--</ol>-->
    </h1>
  </section>
  <!-- Main content -->
  <section class="content">
    <div class="row">
      <div class="col-xs-12">
        <div class="box">
          <!-- /.box-header -->
          <div class="box-body">
            <div class="widget">
              <div class="widget-content">
                <div class="bs bs-tabs">
                  <ul id="myTab" class="nav nav-tabs">
                    <li class="active"><a href="#labReport" data-toggle="tab">
                        <?php echo _translate("VL Lab Weekly Report"); ?>
                      </a></li>
                    <li><a href="#femaleReport" data-toggle="tab">
                        <?php echo _translate("VL Lab Weekly Report - Female"); ?>
                      </a></li>
                  </ul>
                  <div id="myTabContent" class="tab-content">
                    <div class="tab-pane fade in active" id="labReport">
                      <table aria-describedby="table" class="table valign-mid pageFilters" cellpadding="1" cellspacing="3" style="margin-left:1%;margin-top:20px;width:90%;">
                        <tr>
                          <th scope="row" style="width:15%"><strong>
                              <?php echo _translate("Sample Test Date Range"); ?>&nbsp;:
                            </strong></th>
                          <td style="width:20% !important;">
                            <input type="text" id="sampleTestDate" name="sampleTestDate" class="form-control" placeholder="<?php echo _translate('Sample Test Date Range'); ?>" readonly style="background:#eee;font-size:0.9em" />
                          </td>
                          <th scope="row" style="width:8%"><strong>
                              <?php echo _translate("VL Lab(s)"); ?>&nbsp;:
                            </strong></th>
                          <td style="width:28%;">
                            <select id="lab" name="lab" class="form-control" title="<?php echo _translate('Please select lab'); ?>" multiple>
                              <option value="">
                                <?php echo _translate("-- Select --"); ?>
                              </option>
                              <?php
                              foreach ($facilityResult as $lab) {
                              ?>
                                <option value="<?php echo $lab['facility_id']; ?>"><?php echo ($lab['facility_name'] . "-" . $lab['facility_code']); ?></option>
                              <?php
                              }
                              ?>
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td colspan="6">
                            &nbsp;<input type="button" onclick="searchWeeklyData();" value="<?= _translate('Search'); ?>" class="btn btn-success btn-sm">
                            &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>
                                <?= _translate('Reset'); ?>
                              </span></button>
                            &nbsp;<button class="btn btn-info btn-sm" type="button" onclick="exportVLWeeklyReport()">
                              <?php echo _translate("Excel Export"); ?>
                            </button>
                          </td>
                        </tr>
                      </table>
                      <table aria-describedby="table" id="vlWeeklyReportDataTable" class="table table-bordered table-striped" aria-hidden="true">
                        <thead>
                          <tr>
                            <th scope="col" rowspan="2">
                              <?php echo _translate("Province/State"); ?>
                            </th>
                            <th scope="col" rowspan="2">
                              <?php echo _translate("District/County"); ?>
                            </th>
                            <th scope="col" rowspan="2">
                              <?php echo _translate("Site Name"); ?>
                            </th>
                            <!-- <th scope="row" rowspan="2">IPSL</th> -->
                            <th scope="col" rowspan="2">
                              <?php echo _translate("No. of Rejections"); ?>
                            </th>
                            <th scope="col" colspan="2" style="text-align:center;">
                              <?php echo _translate("Viral Load Results - Peds"); ?>
                            </th>
                            <th scope="col" colspan="4" style="text-align:center;">
                              <?php echo _translate("Viral Load Results - Adults"); ?>
                            </th>
                            <th scope="col" colspan="2" style="text-align:center;">
                              <?php echo _translate("Viral Load Results - Pregnant/Breastfeeding Female"); ?>
                            </th>
                            <th scope="col" colspan="2" style="text-align:center;">
                              <?php echo _translate("Age/Sex Unknown"); ?>
                            </th>
                            <th scope="col" colspan="2" style="text-align:center;">
                              <?php echo _translate("Totals"); ?>
                            </th>
                            <th scope="col" rowspan="2">
                              <?php echo _translate("Total Test per Clinic"); ?>
                            </th>
                          </tr>
                          <tr>
                            <th scope="row">
                              <?php echo _translate("<= 15 y"); ?> &amp;
                              <?php echo _translate("<=1000 cp/mL"); ?>
                            </th>
                            <th scope="row">
                              <?php echo _translate("<= 15 y"); ?> &amp;
                              <?php echo _translate(">1000 cp/mL"); ?>
                            </th>
                            <th scope="row">
                              <?php echo _translate("> 15 y"); ?> &amp;
                              <?php echo _translate("Male <=1000 cp/mL"); ?>
                            </th>
                            <th scope="row">
                              <?php echo _translate("> 15 y"); ?> &amp;
                              <?php echo _translate("Male >1000 cp/mL"); ?>
                            </th>
                            <th scope="row">
                              <?php echo _translate("> 15 y"); ?> &amp;
                              <?php echo _translate("Female <=1000 cp/mL"); ?>
                            </th>
                            <th scope="row">
                              <?php echo _translate("> 15 y"); ?> &amp;
                              <?php echo _translate("Female >1000 cp/mL"); ?>
                            </th>
                            <th scope="row">
                              <?php echo _translate("<=1000 cp/mL"); ?>
                            </th>
                            <th scope="row">
                              <?php echo _translate(">1000 cp/mL"); ?>
                            </th>
                            <th scope="row">
                              <?php echo _translate("Unknown Age/Sex <=1000 cp/mL"); ?>
                            </th>
                            <th scope="row">
                              <?php echo _translate("Unknown Age/Sex >1000 cp/mL"); ?>
                            </th>
                            <th scope="row">
                              <?php echo _translate("<=1000 cp/mL"); ?>
                            </th>
                            <th scope="row">
                              <?php echo _translate(">1000 cp/mL"); ?>
                            </th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td colspan="19" class="dataTables_empty">
                              <?php echo _translate("Loading data from server"); ?>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <div class="tab-pane fade" id="femaleReport">
                      <table aria-describedby="table" class="table valign-mid pageFilters" cellpadding="1" cellspacing="3" style="margin-left:1%;margin-top:20px;width:98%;">
                        <tr>
                          <th scope="row" style="width:13%"><strong>
                              <?php echo _translate("Sample Test Date Range"); ?>&nbsp;:
                            </strong></th>
                          <td style="width:20% !important;">
                            <input type="text" id="femaleSampleTestDate" name="femaleSampleTestDate" class="form-control daterange" placeholder="<?php echo _translate('Sample Test Date Range'); ?>" readonly style="background:#eee;font-size:0.9em" />
                          </td>
                          <th scope="row" style="width:8%"><strong>
                              <?php echo _translate("VL Lab(s)"); ?>&nbsp;:
                            </strong></th>
                          <td style="width:28%;">
                            <select id="femaleLab" name="femaleLab" class="form-control" title="<?php echo _translate('Please select lab'); ?>" multiple>
                              <option value="">
                                <?php echo _translate("-- Select --"); ?>
                              </option>
                              <?php
                              foreach ($facilityResult as $lab) {
                              ?>
                                <option value="<?php echo $lab['facility_id']; ?>"><?php echo ($lab['facility_name'] . "-" . $lab['facility_code']); ?></option>
                              <?php
                              }
                              ?>
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td colspan="6">
                            &nbsp;<input type="button" onclick="searchFemaleData();" value="<?= _translate('Search'); ?>" class="btn btn-success btn-sm">
                            &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>
                                <?= _translate('Reset'); ?>
                              </span></button>
                            &nbsp;<button class="btn btn-info btn-sm" type="button" onclick="exportFemaleVLWeeklyReport()">
                              <?php echo _translate("Excel Export"); ?>
                            </button>
                          </td>
                        </tr>
                      </table>
                      <table aria-describedby="table" id="vlWeeklyFemaleReportDataTable" class="table table-bordered table-striped" aria-hidden="true">
                        <thead>
                          <tr>
                            <th scope="col">
                              <?php echo _translate("Province/State"); ?>
                            </th>
                            <th scope="col">
                              <?php echo _translate("District/County"); ?>
                            </th>
                            <th scope="col">
                              <?php echo _translate("Site Name"); ?>
                            </th>
                            <th scope="col">
                              <?php echo _translate("Total Female"); ?>
                            </th>
                            <th scope="col">
                              <?php echo _translate("Pregnant <=1000 cp/mL"); ?>
                            </th>
                            <th scope="col">
                              <?php echo _translate("Pregnant >1000 cp/mL"); ?>
                            </th>
                            <th scope="col">
                              <?php echo _translate("Breastfeeding <=1000 cp/mL"); ?>
                            </th>
                            <th scope="col">
                              <?php echo _translate("Breastfeeding >1000 cp/mL"); ?>
                            </th>
                            <th scope="col">
                              <?php echo _translate("Age > 15 <=1000 cp/mL"); ?>
                            </th>
                            <th scope="col">
                              <?php echo _translate("Age > 15 >1000 cp/mL"); ?>
                            </th>
                            <th scope="col">
                              <?php echo _translate("Age Unknown <=1000 cp/mL"); ?>
                            </th>
                            <th scope="col">
                              <?php echo _translate("Age Unknown >1000 cp/mL"); ?>
                            </th>
                            <th scope="col">
                              <?php echo _translate("Age <=15 <=1000 cp/mL"); ?>
                            </th>
                            <th scope="col">
                              <?php echo _translate("Age <=15>1000 cp/mL"); ?>
                            </th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td colspan="13" class="dataTables_empty">
                              <?php echo _translate("Loading data from server"); ?>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>
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
  var startDate = "";
  var endDate = "";
  var oTable = null;
  var oTableFemale = null;
  $(document).ready(function() {
    $('#lab').select2({
      placeholder: "<?php echo _translate("All Labs"); ?>"
    });
    $('#femaleLab').select2({
      width: '250px',
      placeholder: "<?php echo _translate("All Labs"); ?>"
    });
    $('#sampleTestDate,#sampleCollectionDate,#femaleSampleTestDate,#femaleSampleCollectionDate').daterangepicker({
        locale: {
          cancelLabel: "<?= _translate("Clear", true); ?>",
          format: 'DD-MMM-YYYY',
          separator: ' to ',
        },
        startDate: moment().subtract(6, 'days'),
        endDate: moment(),
        maxDate: moment(),
        ranges: {
          'Today': [moment(), moment()],
          'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
          'Last 7 Days': [moment().subtract(6, 'days'), moment()],
          'Last 30 Days': [moment().subtract(29, 'days'), moment()],
          'This Month': [moment().startOf('month'), moment().endOf('month')],
          'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
          'Last 12 Months': [moment().subtract(12, 'month').startOf('month'), moment().endOf('month')],
          'Last 18 Months': [moment().subtract('month', 18).startOf('month'), moment().endOf('month')],
          'Last 24 Months': [moment().subtract('month', 24).startOf('month'), moment().endOf('month')],
          'Previous Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
          'Current Year To Date': [moment().startOf('year'), moment()]
        }
      },
      function(start, end) {
        startDate = start.format('YYYY-MM-DD');
        endDate = end.format('YYYY-MM-DD');
      });
    loadDataTable();
    loadFemaleDataTable();
  });

  function loadDataTable() {
    if (oTable != null) oTable.fnDestroy();
    oTable = $('#vlWeeklyReportDataTable').dataTable({
      "bJQueryUI": false,
      "bAutoWidth": false,
      "bInfo": true,
      "bScrollCollapse": true,
      "iDisplayLength": 10,
      "bRetrieve": true,
      "aoColumns": [{
          "sClass": "center"
        },
        {
          "sClass": "center"
        },
        // {"sClass":"center"},
        {
          "sClass": "center",
          "bSortable": false
        },
        {
          "sClass": "center",
          "bSortable": false
        },
        {
          "sClass": "center",
          "bSortable": false
        },
        {
          "sClass": "center",
          "bSortable": false
        },
        {
          "sClass": "center",
          "bSortable": false
        },
        {
          "sClass": "center",
          "bSortable": false
        },
        {
          "sClass": "center",
          "bSortable": false
        },
        {
          "sClass": "center",
          "bSortable": false
        },
        {
          "sClass": "center",
          "bSortable": false
        },
        {
          "sClass": "center",
          "bSortable": false
        },
        {
          "sClass": "center",
          "bSortable": false
        },
        {
          "sClass": "center",
          "bSortable": false
        },
        {
          "sClass": "center",
          "bSortable": false
        },
        {
          "sClass": "center",
          "bSortable": false
        },
        {
          "sClass": "center",
          "bSortable": false
        }
      ],
      "aaSorting": [
        [2, "asc"]
      ],
      "bProcessing": true,
      "bServerSide": true,
      "sAjaxSource": "/vl/program-management/getVlWeeklyReport.php",
      "fnServerData": function(sSource, aoData, fnCallback) {
        aoData.push({
          "name": "sampleTestDate",
          "value": $("#sampleTestDate").val()
        });
        aoData.push({
          "name": "sampleCollectionDate",
          "value": $("#sampleCollectionDate").val()
        });
        aoData.push({
          "name": "lab",
          "value": $("#lab").val()
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
  }

  function loadFemaleDataTable() {
    if (oTableFemale != null) oTableFemale.fnDestroy();
    oTableFemale = $('#vlWeeklyFemaleReportDataTable').dataTable({
      "bJQueryUI": false,
      "bAutoWidth": false,
      "bInfo": true,
      "bScrollCollapse": true,
      "iDisplayLength": 10,
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
          "sClass": "center",
          "bSortable": false
        },
        {
          "sClass": "center",
          "bSortable": false
        },
        {
          "sClass": "center",
          "bSortable": false
        },
        {
          "sClass": "center",
          "bSortable": false
        },
        {
          "sClass": "center",
          "bSortable": false
        },
        {
          "sClass": "center",
          "bSortable": false
        },
        {
          "sClass": "center",
          "bSortable": false
        },
        {
          "sClass": "center",
          "bSortable": false
        },
        {
          "sClass": "center",
          "bSortable": false
        },
        {
          "sClass": "center",
          "bSortable": false
        },
        {
          "sClass": "center",
          "bSortable": false
        },
      ],
      //"aaSorting": [[ 0, "asc" ]],
      "bProcessing": true,
      "bServerSide": true,
      "sAjaxSource": "getVlWeeklyFemaleReport.php",
      "fnServerData": function(sSource, aoData, fnCallback) {
        aoData.push({
          "name": "sampleTestDate",
          "value": $("#femaleSampleTestDate").val()
        });
        aoData.push({
          "name": "sampleCollectionDate",
          "value": $("#femaleSampleCollectionDate").val()
        });
        aoData.push({
          "name": "lab",
          "value": $("#femaleLab").val()
        });
        $.ajax({
          "dataType": 'json',
          "type": "POST",
          "url": sSource,
          "data": aoData,
          "success": fnCallback
        }).done(function() {

        });
      }
    });
  }

  function searchWeeklyData() {
    $.blockUI();
    loadDataTable();
    $.unblockUI();
  }

  function searchFemaleData() {
    $.blockUI();
    loadFemaleDataTable();
    $.unblockUI();
  }

  function exportVLWeeklyReport() {
    searchWeeklyData();
    $.blockUI();
    $.post("/vl/program-management/generateVlWeeklyReportExcel.php", {
        reportedDate: $("#sampleTestDate").val(),
        lab: ($("#lab").val() == null) ? '' : $("#lab").val().join(','),
        searchData: $('.dataTables_filter input').val()
      },
      function(data) {
        $.unblockUI();
        if (data == "" || data == null || data == undefined) {
          alert("<?php echo _translate("Unable to generate the excel file"); ?>");
        } else {
          $.unblockUI();
          location.href = '/temporary/' + data;
        }
      });
  }

  function exportFemaleVLWeeklyReport() {
    var labTexts = [];
    var texts = $("#femaleLab").select2('data');
    for (i = 0; i < texts.length; i++) {
      labTexts.push(texts[i].text);
    }
    searchFemaleData();
    $.blockUI();
    $.post("/vl/program-management/generateVlWeeklyFemaleReportExcel.php", {
        sample_test_date: $("#femaleSampleTestDate").val(),
        lab: (labTexts.length > 0) ? labTexts.join(',') : '',
        searchData: $('.dataTables_filter input').val()
      },
      function(data) {
        $.unblockUI();
        if (data == "" || data == null || data == undefined) {
          alert("<?php echo _translate("Unable to generate the excel file"); ?>");
        } else {
          $.unblockUI();
          location.href = '/temporary/' + data;
        }
      });
  }
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
