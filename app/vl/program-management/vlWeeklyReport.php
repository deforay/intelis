<?php

require_once APPLICATION_PATH . '/header.php';

$facilityQuery = "SELECT * FROM facility_details where facility_type = 2 AND status='active'";

if (isset($_SESSION['instanceType']) && $_SESSION['instanceType'] == 'vluser') {
  $labId = $sarr['sc_testing_lab_id'];
  $facilityQuery .= " AND facility_id = " . $labId;
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
    <h1><em class="fa-solid fa-calendar-check"></em> <?php echo _("VL Lab Weekly Report"); ?>
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
                <div class="bs-example bs-example-tabs">
                  <ul id="myTab" class="nav nav-tabs">
                    <li class="active"><a href="#labReport" data-toggle="tab"><?php echo _("VL Lab Weekly Report"); ?></a></li>
                    <li><a href="#femaleReport" data-toggle="tab"><?php echo _("VL Lab Weekly Report - Female"); ?></a></li>
                  </ul>
                  <div id="myTabContent" class="tab-content">
                    <div class="tab-pane fade in active" id="labReport">
                      <table aria-describedby="table" class="table valign-mid" cellpadding="1" cellspacing="3" style="margin-left:1%;margin-top:20px;width:90%;">
                        <tr>
                          <th scope="row" style="width:15%"><strong><?php echo _("Sample Test Date Range"); ?>&nbsp;:</strong></th>
                          <td style="width:20% !important;">
                            <input type="text" id="sampleTestDate" name="sampleTestDate" class="form-control" placeholder="<?php echo _('Sample Test Date Range'); ?>" readonly style="background:#eee;font-size:0.9em" />
                          </td>
                          <th scope="row" style="width:8%"><strong><?php echo _("VL Lab(s)"); ?>&nbsp;:</strong></th>
                          <td style="width:28%;">
                            <select id="lab" name="lab" class="form-control" title="<?php echo _('Please select lab'); ?>" multiple>
                              <option value=""> <?php echo _("-- Select --"); ?> </option>
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
                            &nbsp;<input type="button" onclick="searchWeeklyData();" value="<?php echo _('Search'); ?>" class="btn btn-success btn-sm">
                            &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _("Reset"); ?></span></button>
                            &nbsp;<button class="btn btn-info btn-sm" type="button" onclick="exportVLWeeklyReport()"><?php echo _("Excel Export"); ?></button>
                          </td>
                        </tr>
                      </table>
                      <table aria-describedby="table" id="vlWeeklyReportDataTable" class="table table-bordered table-striped" aria-hidden="true">
                        <thead>
                          <tr>
                            <th scope="col" rowspan="2"><?php echo _("Province/State"); ?></th>
                            <th scope="col" rowspan="2"><?php echo _("District/County"); ?></th>
                            <th scope="col" rowspan="2"><?php echo _("Site Name"); ?></th>
                            <!-- <th scope="row" rowspan="2">IPSL</th> -->
                            <th scope="col" rowspan="2"><?php echo _("No. of Rejections"); ?></th>
                            <th scope="col" colspan="2" style="text-align:center;"><?php echo _("Viral Load Results - Peds"); ?></th>
                            <th scope="col" colspan="4" style="text-align:center;"><?php echo _("Viral Load Results - Adults"); ?></th>
                            <th scope="col" colspan="2" style="text-align:center;"><?php echo _("Viral Load Results - Pregnant/Breastfeeding Female"); ?></th>
                            <th scope="col" colspan="2" style="text-align:center;"><?php echo _("Age/Sex Unknown"); ?></th>
                            <th scope="col" colspan="2" style="text-align:center;"><?php echo _("Totals"); ?></th>
                            <th scope="col" rowspan="2"><?php echo _("Total Test per Clinic"); ?></th>
                          </tr>
                          <tr>
                            <th scope="row">
                              <?php echo _("<= 15 y"); ?> &amp; <?php echo _("<=1000 cp/ml"); ?>
                            </th>
                            <th scope="row">
                              <?php echo _("<= 15 y"); ?> &amp;<?php echo _(">1000 cp/ml"); ?>
                            </th>
                            <th scope="row"><?php echo _("> 15 y"); ?> &amp; <?php echo _("Male <=1000 cp/ml"); ?> </th>
                            <th scope="row"><?php echo _("> 15 y"); ?> &amp; <?php echo _("Male >1000 cp/ml"); ?></th>
                            <th scope="row"><?php echo _("> 15 y"); ?> &amp; <?php echo _("Female <=1000 cp/ml"); ?> </th>
                            <th scope="row"><?php echo _("> 15 y"); ?> &amp; <?php echo _("Female >1000 cp/ml"); ?></th>
                            <th scope="row"><?php echo _("<=1000 cp/ml"); ?> </th>
                            <th scope="row"><?php echo _(">1000 cp/ml"); ?></th>
                            <th scope="row"><?php echo _("Unknown Age/Sex <=1000 cp/ml"); ?> </th>
                            <th scope="row"><?php echo _("Unknown Age/Sex >1000 cp/ml"); ?></th>
                            <th scope="row"><?php echo _("<=1000 cp/ml"); ?> </th>
                            <th scope="row"><?php echo _(">1000 cp/ml"); ?></th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td colspan="19" class="dataTables_empty"><?php echo _("Loading data from server"); ?></td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <div class="tab-pane fade" id="femaleReport">
                      <table aria-describedby="table" class="table valign-mid" cellpadding="1" cellspacing="3" style="margin-left:1%;margin-top:20px;width:98%;">
                        <tr>
                          <th scope="row" style="width:13%"><strong><?php echo _("Sample Test Date Range"); ?>&nbsp;:</strong></th>
                          <td style="width:20% !important;">
                            <input type="text" id="femaleSampleTestDate" name="femaleSampleTestDate" class="form-control daterange" placeholder="<?php echo _('Sample Test Date Range'); ?>" readonly style="background:#eee;font-size:0.9em" />
                          </td>
                          <th scope="row" style="width:8%"><strong><?php echo _("VL Lab(s)"); ?>&nbsp;:</strong></th>
                          <td style="width:28%;">
                            <select id="femaleLab" name="femaleLab" class="form-control" title="<?php echo _('Please select lab'); ?>" multiple>
                              <option value=""> <?php echo _("-- Select --"); ?> </option>
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
                            &nbsp;<input type="button" onclick="searchFemaleData();" value="<?php echo _('Search'); ?>" class="btn btn-success btn-sm">
                            &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _("Reset"); ?></span></button>
                            &nbsp;<button class="btn btn-info btn-sm" type="button" onclick="exportFemaleVLWeeklyReport()"><?php echo _("Excel Export"); ?></button>
                          </td>
                        </tr>
                      </table>
                      <table aria-describedby="table" id="vlWeeklyFemaleReportDataTable" class="table table-bordered table-striped" aria-hidden="true">
                        <thead>
                          <tr>
                            <th scope="col"><?php echo _("Province/State"); ?></th>
                            <th scope="col"><?php echo _("District/County"); ?></th>
                            <th scope="col"><?php echo _("Site Name"); ?></th>
                            <th scope="col"><?php echo _("Total Female"); ?></th>
                            <th scope="col"><?php echo _("Pregnant <=1000 cp/ml"); ?> </th>
                            <th scope="col"><?php echo _("Pregnant >1000 cp/ml"); ?></th>
                            <th scope="col"><?php echo _("Breastfeeding <=1000 cp/ml"); ?> </th>
                            <th scope="col"><?php echo _("Breastfeeding >1000 cp/ml"); ?></th>
                            <th scope="col"><?php echo _("Age > 15 <=1000 cp/ml"); ?> </th>
                            <th scope="col"><?php echo _("Age > 15 >1000 cp/ml"); ?></th>
                            <th scope="col"><?php echo _("Age Unknown <=1000 cp/ml"); ?> </th>
                            <th scope="col"><?php echo _("Age Unknown >1000 cp/ml"); ?></th>
                            <th scope="col"><?php echo _("Age <=15 <=1000 cp/ml"); ?> </th>
                            <th scope="col"><?php echo _("Age <=15>1000 cp/ml"); ?></th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td colspan="13" class="dataTables_empty"><?php echo _("Loading data from server"); ?></td>
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
      placeholder: "<?php echo _("All Labs"); ?>"
    });
    $('#femaleLab').select2({
      width: '250px',
      placeholder: "<?php echo _("All Labs"); ?>"
    });
    $('#sampleTestDate,#sampleCollectionDate,#femaleSampleTestDate,#femaleSampleCollectionDate').daterangepicker({
        locale: {
          cancelLabel: "<?= _("Clear"); ?>",
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
          'Last 24 Months': [moment().subtract('month', 24).startOf('month'), moment().endOf('month')]
        }
      },
      function(start, end) {
        startDate = start.format('YYYY-MM-DD');
        endDate = end.format('YYYY-MM-DD');
      });
    loadDataTable();
    //loadFemaleDataTable();
  });

  function loadDataTable() {
    if (oTable != null) oTable.fnDestroy();
    oTable = $('#vlWeeklyReportDataTable').dataTable({
      "oLanguage": {
        "sLengthMenu": "_MENU_ records per page"
      },
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
      "sAjaxSource": "getVlWeeklyReport.php",
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
      "oLanguage": {
        "sLengthMenu": "_MENU_ records per page"
      },
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
          alert("<?php echo _("Unable to generate the excel file"); ?>");
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
          alert("<?php echo _("Unable to generate the excel file"); ?>");
        } else {
          $.unblockUI();
          location.href = '/temporary/' + data;
        }
      });
  }
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
?>