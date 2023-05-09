<?php


$sQuery = "SELECT * FROM r_vl_sample_type";
$sResult = $db->rawQuery($sQuery);
$fQuery = "SELECT * FROM facility_details where status='active'";
$fResult = $db->rawQuery($fQuery);
$batQuery = "SELECT batch_code FROM batch_details where test_type = 'vl' AND batch_status='completed'";
$batResult = $db->rawQuery($batQuery);
?>
<link rel="stylesheet" media="all" type="text/css" href="/assets/css/jquery-ui.min.css" />
<!-- Bootstrap 3.3.6 -->
<link rel="stylesheet" href="/assets/css/bootstrap.min.css">
<!-- Font Awesome -->
<link rel="stylesheet" href="/assets/css/font-awesome.min.css">
<!-- DataTables -->
<link rel="stylesheet" href="/assets/plugins/datatables/dataTables.bootstrap.css">
<link href="/assets/plugins/daterangepicker/daterangepicker.css" rel="stylesheet" />
<style>
  .content-wrapper {
    padding: 2%;
  }

  .center {
    text-align: center;
  }
</style>
<script type="text/javascript" src="/assets/js/jquery.min.js"></script>
<script type="text/javascript" src="/assets/js/jquery-ui.min.js"></script>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h3 style="margin:0;">Search Patients</h3>
  </section>
  <!-- Main content -->
  <section class="content">
    <div class="row">
      <div class="col-xs-12">
        <div class="box">
          <table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:10px;width: 98%;">
            <tr>
              <td><strong>Sample Collection Date&nbsp;:</strong></td>
              <td>
                <input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control" placeholder="Select Collection Date" readonly style="width:220px;background:#fff;" />
              </td>
              <td>&nbsp;<strong>Batch Code&nbsp;:</strong></td>
              <td>
                <select class="form-control" id="batchCode" name="batchCode" title="Please select batch code">
                  <option value=""> -- Select -- </option>
                  <?php
                  foreach ($batResult as $code) {
                  ?>
                    <option value="<?php echo $code['batch_code']; ?>"><?php echo $code['batch_code']; ?></option>
                  <?php
                  }
                  ?>
                </select>
              </td>
            </tr>
            <tr>
              <td>&nbsp;<strong>Sample Type&nbsp;:</strong></td>
              <td>
                <select style="width:220px;" class="form-control" id="sampleType" name="sampleType" title="Please select sample type">
                  <option value=""> -- Select -- </option>
                  <?php
                  foreach ($sResult as $type) {
                  ?>
                    <option value="<?php echo $type['sample_id']; ?>"><?php echo ($type['sample_name']); ?></option>
                  <?php
                  }
                  ?>
                </select>
              </td>

              <td>&nbsp;<strong>Facility Name & Code&nbsp;:</strong></td>
              <td>
                <select class="form-control" id="facilityName" name="facilityName" title="Please select facility name">
                  <option value=""> -- Select -- </option>
                  <?php
                  foreach ($fResult as $name) {
                  ?>
                    <option value="<?php echo $name['facility_id']; ?>"><?php echo ($name['facility_name'] . "-" . $name['facility_code']); ?></option>
                  <?php
                  }
                  ?>
                </select>
              </td>
            </tr>
            <tr>
              <td colspan="3">&nbsp;<input type="button" onclick="searchVlRequestData();" value="Search" class="btn btn-success btn-sm">
                &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>Reset</span></button>

              </td>
            </tr>

          </table>
          <!-- /.box-header -->
          <div class="box-body">
            <table aria-describedby="table" id="vlRequestDataTable" class="table table-bordered table-striped" aria-hidden="true" >
              <thead>
                <tr>
                  <th>Select</th>
                  <th>Sample Code</th>
                  <th>Sample Collection Date</th>
                  <th>Batch Code</th>
                  <th>Unique ART No</th>
                  <th>Patient's Name</th>
                  <th>Facility Name</th>
                  <th>Sample Type</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="8" class="dataTables_empty">Loading data from server</td>
                </tr>
              </tbody>
            </table>
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
<!-- Bootstrap 3.3.6 -->
<script src="/assets/js/bootstrap.min.js"></script>
<!-- DataTables -->
<script src="/assets/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/assets/plugins/datatables/dataTables.bootstrap.min.js"></script>
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript">
  var startDate = "";
  var endDate = "";
  $(document).ready(function() {
    $('#sampleCollectionDate').daterangepicker({
        locale: {
          cancelLabel: 'Clear'
        },
        format: 'DD-MMM-YYYY',
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
    $('#sampleCollectionDate').val("");
    loadVlRequestData();
  });

  var oTable = null;

  function loadVlRequestData() {
    oTable = $('#vlRequestDataTable').dataTable({
      "oLanguage": {
        "sLengthMenu": "_MENU_ records per page"
      },
      "bJQueryUI": false,
      "bAutoWidth": false,
      "bInfo": true,
      "bScrollCollapse": true,

      "bRetrieve": true,
      "aoColumns": [{
          "sClass": "center",
          "bSortable": false
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
          "sClass": "center"
        },
        {
          "sClass": "center"
        },
        {
          "sClass": "center"
        }
      ],
      "aaSorting": [
        [2, "desc"]
      ],
      "bProcessing": true,
      "bServerSide": true,
      "sAjaxSource": "getVlRequestModalDetails.php",
      "fnServerData": function(sSource, aoData, fnCallback) {
        aoData.push({
          "name": "batchCode",
          "value": $("#batchCode").val()
        });
        aoData.push({
          "name": "sampleCollectionDate",
          "value": $("#sampleCollectionDate").val()
        });
        aoData.push({
          "name": "facilityName",
          "value": $("#facilityName").val()
        });
        aoData.push({
          "name": "sampleType",
          "value": $("#sampleType").val()
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

  function searchVlRequestData() {
    oTable.fnDraw();
  }

  function getPatient(ptDetails) {
    parent.closeModal();
    window.parent.setPatientDetails(ptDetails);
  }
</script>