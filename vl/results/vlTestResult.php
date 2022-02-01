<?php
$title = "Enter VL Result";

include_once(APPLICATION_PATH . '/header.php');


$general = new \Vlsm\Models\General();
$facilitiesDb = new \Vlsm\Models\Facilities();
$healthFacilites = $facilitiesDb->getHealthFacilities('vl');

$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, null, "-- Select --");


$sQuery = "SELECT * FROM r_vl_sample_type where status='active'";
$sResult = $db->rawQuery($sQuery);

$batQuery = "SELECT batch_code FROM batch_details where test_type = 'vl' AND batch_status='completed'";
$batResult = $db->rawQuery($batQuery);
//check filters
$collectionDate = '';
$batchCode = '';
$sampleType = '';
$facilityName = array();
$gender = '';
$status = 'no_result';
$lastUrl1 = '';
$lastUrl2 = '';
if (isset($_SERVER['HTTP_REFERER'])) {
  $lastUrl1 = strpos($_SERVER['HTTP_REFERER'], "updateVlTestResult.php");
  $lastUrl2 = strpos($_SERVER['HTTP_REFERER'], "vlTestResult.php");
}
if ($lastUrl1 != '' || $lastUrl2 != '') {
  $collectionDate = (isset($_COOKIE['collectionDate']) && $_COOKIE['collectionDate'] != '') ? $_COOKIE['collectionDate'] : '';
  $batchCode = (isset($_COOKIE['batchCode']) && $_COOKIE['batchCode'] != '') ? $_COOKIE['batchCode'] : '';
  $sampleType = (isset($_COOKIE['sampleType']) && $_COOKIE['sampleType'] != '') ? $_COOKIE['sampleType'] : '';
  $facilityName = (isset($_COOKIE['facilityName']) && $_COOKIE['facilityName'] != '') ? explode(',', $_COOKIE['facilityName']) : array();
  $gender = (isset($_COOKIE['gender']) && $_COOKIE['gender'] != '') ? $_COOKIE['gender'] : '';
  $status = (isset($_COOKIE['status']) && $_COOKIE['status'] != '') ? $_COOKIE['status'] : '';
}
?>
<style>
  .select2-selection__choice {
    color: black !important;
  }
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><i class="fa fa-edit"></i> Enter VL Result</h1>
    <ol class="breadcrumb">
      <li><a href="/dashboard/index.php"><i class="fa fa-dashboard"></i> Home </a></li>
      <li class="active">Enter VL Result</li>
    </ol>
  </section>
  <!-- Main content -->
  <section class="content">
    <div class="row">
      <div class="col-xs-12">
        <div class="box">
          <table class="table" cellpadding="1" cellspacing="3" style="margin-left:1%;margin-top:20px;width:98%;margin-bottom: 0px;">
            <tr>
              <td><b>Sample Collection Date&nbsp;:</b></td>
              <td>
                <input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control" placeholder="Select Collection Date" readonly style="width:220px;background:#fff;" value="<?php echo $collectionDate; ?>" />
              </td>
              <td>&nbsp;<b>Batch Code&nbsp;:</b></td>
              <td>
                <select class="form-control" id="batchCode" name="batchCode" title="Please select batch code" style="width:220px;">
                  <option value=""> -- Select -- </option>
                  <?php
                  foreach ($batResult as $code) {
                  ?>
                    <option value="<?php echo $code['batch_code']; ?>" <?php echo ($batchCode == $code['batch_code']) ? "selected='selected'" : "" ?>><?php echo $code['batch_code']; ?></option>
                  <?php
                  }
                  ?>
                </select>
              </td>

              <td><b>Sample Type&nbsp;:</b></td>
              <td>
                <select style="width:220px;" class="form-control" id="sampleType" name="sampleType" title="Please select sample type">
                  <option value=""> -- Select -- </option>
                  <?php
                  foreach ($sResult as $type) {
                  ?>
                    <option value="<?php echo $type['sample_id']; ?>" <?php echo ($sampleType == $type['sample_id']) ? "selected='selected'" : "" ?>><?php echo ucwords($type['sample_name']); ?></option>
                  <?php
                  }
                  ?>
                </select>
              </td>
            </tr>
            <tr>
              <td><b>Facility Name :</b></td>
              <td>
                <select class="form-control" id="facilityName" name="facilityName" title="Please select facility name" multiple="multiple" style="width:220px;">
                  <?= $facilitiesDropdown; ?>
                </select>
              </td>


              <td><b>Gender&nbsp;:</b></td>
              <td>
                <select name="gender" id="gender" class="form-control" title="Please choose gender" style="width:220px;">
                  <option value=""> -- Select -- </option>
                  <option value="male" <?php echo ($gender == 'male') ? "selected='selected'" : "" ?>>Male</option>
                  <option value="female" <?php echo ($gender == 'female') ? "selected='selected'" : "" ?>>Female</option>
                  <option value="not_recorded" <?php echo ($gender == 'not_recorded') ? "selected='selected'" : "" ?>>Not Recorded</option>
                </select>
              </td>
              <!-- <td><b>Status&nbsp;:</b></td>
		  <td>
		      <select style="width: 220px;" name="status" id="status" class="form-control" title="Please choose status">
			<option value="">-- Select --</option>
			<option value="7"< ?php echo ($status=='7')?"selected='selected'":""?>>Accepted</option>
			<option value="4"< ?php echo ($status=='4')?"selected='selected'":""?>>Rejected</option>
		      </select>
		    </td> -->
            </tr>
            <tr>
              <td colspan="6">&nbsp;<input type="button" onclick="searchVlRequestData();" value="Search" class="btn btn-default btn-sm">
                &nbsp;<button class="btn btn-danger btn-sm" onclick="reset();"><span>Reset</span></button>
                &nbsp;<button class="btn btn-default btn-sm" onclick="convertSearchResultToPdf('');"><span>Result PDF</span></button>
                &nbsp;<a class="btn btn-success btn-sm" href="javascript:void(0);" onclick="exportAllVlTestResult();"><i class="fa fa-cloud-download" aria-hidden="true"></i> Export Excel</a>
                &nbsp;<button class="btn btn-primary btn-sm" onclick="$('#showhide').fadeToggle();return false;"><span>Manage Columns</span></button>
              </td>
            </tr>
          </table>
          <span style="display: none;position:absolute;z-index: 9999 !important;color:#000;padding:5px;" id="showhide" class="">
            <div class="row" style="background:#e0e0e0;padding: 15px;margin-top: -5px;">
              <div class="col-md-12">
                <div class="col-md-3">
                  <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="0" id="iCol0" data-showhide="sample_code" class="showhideCheckBox" /> <label for="iCol0">Sample Code</label>
                </div>
                <?php $i = 0;
                if ($sarr['sc_user_type'] != 'standalone') {
                  $i = 1; ?>
                  <div class="col-md-3">
                    <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i; ?>" id="iCol<?php echo $i; ?>" data-showhide="remote_sample_code" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Remote Sample Code</label>
                  </div>
                <?php } ?>
                <div class="col-md-3">
                  <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="batch_code" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Batch Code</label>
                </div>
                <div class="col-md-3">
                  <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="patient_art_no" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Art No</label>
                </div>
                <div class="col-md-3">
                  <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="patient_first_name" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Patient's Name</label> <br>
                </div>
                <div class="col-md-3">
                  <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="facility_name" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Facility Name</label>
                </div>
                <div class="col-md-3">
                  <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="sample_name" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Sample Type</label> <br>
                </div>
                <div class="col-md-3">
                  <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="result" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Result</label>
                </div>
                <div class="col-md-3">
                  <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="modified_on" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Modified On</label>
                </div>
                <div class="col-md-3">
                  <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="status_name" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Status</label>
                </div>

              </div>
            </div>
          </span>
          <!-- /.box-header -->
          <div class="box-body">
            <div class="">
              <select name="status" id="status" class="form-control" title="Please choose result status" style="width:220px;margin-top:30px;" onchange="searchVlRequestData();">
                <option value=""> -- Select -- </option>
                <option value="no_result" <?php echo ($status == 'no_result') ? "selected='selected'" : "" ?>>Results Not Recorded</option>
                <option value="result" <?php echo ($status == 'result') ? "selected='selected'" : "" ?>>Results Recorded</option>
                <option value="reject" <?php echo ($status == 'reject') ? "selected='selected'" : "" ?>>Rejected Samples</option>
              </select>
            </div>
            <table id="vlRequestDataTable" class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>Sample Code</th>
                  <?php if ($sarr['sc_user_type'] != 'standalone') { ?>
                    <th>Remote Sample <br />Code</th>
                  <?php } ?>
                  <th>Batch Code</th>
                  <th>Unique ART No</th>
                  <th>Patient's Name</th>
                  <th>Facility Name</th>
                  <th>Sample Type</th>
                  <th>Result</th>
                  <th>Modified On</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="10" class="dataTables_empty">Loading data from server</td>
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
<script type="text/javascript" src="/assets/plugins/daterangepicker/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript">
  var startDate = "";
  var endDate = "";
  var selectedTests = [];
  var selectedTestsId = [];
  var oTable = null;
  $(document).ready(function() {
    $("#facilityName").select2({
      placeholder: "Select Facilities"
    });
    $('#sampleCollectionDate').daterangepicker({
        locale: {
          cancelLabel: 'Clear'
        },
        format: 'DD-MMM-YYYY',
        separator: ' to ',
        startDate: moment().subtract(29, 'days'),
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
    <?php
    if (!isset($_COOKIE['collectionDate']) || $_COOKIE['collectionDate'] == '') {
    ?>
      $('#sampleCollectionDate').val("");
    <?php
    } else if (($lastUrl1 != '' || $lastUrl2 != '') && isset($_COOKIE['collectionDate'])) { ?>
      $('#sampleCollectionDate').val("<?php echo $_COOKIE['collectionDate']; ?>");
    <?php } ?>

    loadVlRequestData();
    $(".showhideCheckBox").change(function() {
      if ($(this).attr('checked')) {
        idpart = $(this).attr('data-showhide');
        $("#" + idpart + "-sort").show();
      } else {
        idpart = $(this).attr('data-showhide');
        $("#" + idpart + "-sort").hide();
      }
    });

    $("#showhide").hover(function() {}, function() {
      $(this).fadeOut('slow')
    });
    var i = '<?php echo $i; ?>';
    for (colNo = 0; colNo <= i; colNo++) {
      $("#iCol" + colNo).attr("checked", oTable.fnSettings().aoColumns[parseInt(colNo)].bVisible);
      if (oTable.fnSettings().aoColumns[colNo].bVisible) {
        $("#iCol" + colNo + "-sort").show();
      } else {
        $("#iCol" + colNo + "-sort").hide();
      }
    }
  });

  function fnShowHide(iCol) {
    var bVis = oTable.fnSettings().aoColumns[iCol].bVisible;
    oTable.fnSetColumnVis(iCol, bVis ? false : true);
  }

  function loadVlRequestData() {
    $.blockUI();
    oTable = $('#vlRequestDataTable').dataTable({
      "oLanguage": {
        "sLengthMenu": "_MENU_ records per page"
      },
      "bJQueryUI": false,
      "bAutoWidth": false,
      "bInfo": true,
      "bScrollCollapse": true,
      //"bStateSave" : true,
      "iDisplayLength": 100,
      "bRetrieve": true,
      "drawCallback": function(settings) {
        $.unblockUI();
      },
      "aoColumns": [{
          "sClass": "center"
        },
        <?php if ($sarr['sc_user_type'] != 'standalone') { ?> {
            "sClass": "center"
          },
        <?php } ?> {
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
      <?php if ($sarr['sc_user_type'] != 'standalone') { ?> "aaSorting": [
          [8, "desc"]
        ],
      <?php } else { ?> "aaSorting": [
          [7, "desc"]
        ],
      <?php } ?> "bProcessing": true,
      "bServerSide": true,
      "sAjaxSource": "/vl/results/getVlTestResultDetails.php",
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
        aoData.push({
          "name": "status",
          "value": $("#status").val()
        });
        aoData.push({
          "name": "gender",
          "value": $("#gender").val()
        });
        aoData.push({
          "name": "from",
          "value": "enterresult"
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
    $.blockUI();
    oTable.fnDraw();
    document.cookie = "collectionDate=" + $("#sampleCollectionDate").val();
    document.cookie = "batchCode=" + $("#batchCode").val();
    document.cookie = "sampleType=" + $("#sampleType").val();
    document.cookie = "facilityName=" + $("#facilityName").val();
    document.cookie = "gender=" + $("#gender").val();
    document.cookie = "status=" + $("#status").val();
    $.unblockUI();
  }

  function convertSearchResultToPdf(id, newData) {
    $.blockUI();
    <?php
    $path = '';
    $path = '/vl/results/pdf/vlRequestSearchResultPdf.php';
    ?>
    $.post("<?php echo $path; ?>", {
        source: 'print',
        id: id,
        newData: newData
      },
      function(data) {
        if (data == "" || data == null || data == undefined) {
          alert('Unable to generate download');
        } else {
          window.open('/uploads/' + data, '_blank');
        }
      });
    $.unblockUI();
  }

  function exportAllVlTestResult() {
    $.blockUI();
    $.post("/vl/results/generateVlTestResultExcel.php", {},
      function(data) {
        $.unblockUI();
        if (data === "" || data === null || data === undefined) {
          alert('Unable to generate the excel file');
        } else {
          location.href = '/temporary/' + data;
        }
      });
  }

  function reset() {
    document.cookie = "collectionDate=";
    document.cookie = "batchCode=";
    document.cookie = "sampleType=";
    document.cookie = "facilityName=";
    document.cookie = "gender=";
    document.cookie = "status=";
    window.location.reload();
  }
</script>
<?php
include(APPLICATION_PATH . '/footer.php');
?>