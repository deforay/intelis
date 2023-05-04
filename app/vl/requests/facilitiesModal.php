<?php


$fQuery = "SELECT * FROM facility_type";
$fResult = $db->rawQuery($fQuery);
$type = $_GET['type'];
?>
<link rel="stylesheet" media="all" type="text/css" href="/assets/css/jquery-ui.min.css" />
<!-- Bootstrap 3.3.6 -->
<link rel="stylesheet" href="/assets/css/bootstrap.min.css">
<!-- Font Awesome -->
<link rel="stylesheet" href="/assets/css/font-awesome.min.css">
<!-- DataTables -->
<link rel="stylesheet" href="/assets/plugins/datatables/dataTables.bootstrap.css">
<link href="/assets/css/deforayModal.css" rel="stylesheet" />
<style>
  .content-wrapper {
    padding: 2%;
  }

  .center {
    text-align: center;
  }

  body {
    overflow-x: hidden;
    /*overflow-y: hidden;*/
  }
</style>
<script type="text/javascript" src="/assets/js/jquery.min.js"></script>
<script type="text/javascript" src="/assets/js/jquery-ui.min.js"></script>
<script src="/assets/js/deforayModal.js"></script>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <div class="pull-left" style="font-size:22px;">Search Clinics</div>
    <?php if ($type == 'all') { ?>
      <div class="pull-right"><a class="btn btn-primary" href="javascript:void(0);" onclick="showModal('addFacilityModal.php?type=all',900,520);" style="margin-bottom:20px;">Add Clinic</a></div>
    <?php } else { ?>
      <div class="pull-right"><a class="btn btn-primary" href="javascript:void(0);" onclick="showModal('addFacilityModal.php?type=lab',900,520);" style="margin-bottom:20px;">Add Clinic</a></div>
    <?php } ?>
  </section>
  <!-- Main content -->
  <section class="content">
    <div class="row">
      <div class="col-xs-12">
        <div class="box">
          <table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:10px;">
            <tr>
              <td><strong>Hub&nbsp;:</strong></td>
              <td>
                <input type="text" id="hub" name="hub" class="form-control" placeholder="Enter Hub" style="background:#fff;" />
              </td>
              <td>&nbsp;<strong>District/County&nbsp;:</strong></td>
              <td>
                <input type="text" id="district" name="district" class="form-control" placeholder="Enter District/County" />
              </td>
            </tr>
            <tr>
              <td><strong>Province/State&nbsp;:</strong></td>
              <td>
                <input type="text" id="state" name="state" class="form-control" placeholder="Enter Province/State" style="background:#fff;" />
              </td>
              <td>&nbsp;<strong>Clinic Type&nbsp;:</strong></td>
              <td>
                <?php
                if ($type == 'all') {
                ?>
                  <select class="form-control" id="facilityTypeName" name="facilityTypeName" title="Please select clinic type">
                    <option value=""> -- Select -- </option>
                    <?php
                    foreach ($fResult as $name) {
                    ?>
                      <option value="<?php echo $name['facility_type_id']; ?>"><?php echo ($name['facility_type_name']); ?></option>
                    <?php
                    }
                    ?>
                  </select>
                <?php } else { ?>
                  <input type="text" id="fName" name="fName" class="form-control" readonly="readonly" value="Lab" style="background:#fff;" />
                  <input type="hidden" id="facilityTypeName" name="facilityTypeName" value="2" />
                <?php } ?>
              </td>
            </tr>
            <tr>
              <td colspan="4">&nbsp;<input type="button" onclick="searchFacilityData();" value="Search" class="btn btn-success btn-sm">
                &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>Reset</span></button>

              </td>
            </tr>

          </table>
          <!-- /.box-header -->
          <div class="box-body">
            <table id="facilityModalDataTable" class="table table-bordered table-striped" aria-hidden="true" >
              <thead>
                <tr>
                  <th style="width:10%;">Select</th>
                  <th>Facility Code</th>
                  <th>Facility Name</th>
                  <th>Facility Type</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="5" class="dataTables_empty">Loading data from server</td>
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
<div id="dDiv" class="dialog">
  <div style="text-align:center"><span onclick="closeModal();" style="float:right;clear:both;" class="closeModal"></span></div>
  <iframe id="dFrame" src="" style="border:none;" scrolling="yes" marginwidth="0" marginheight="0" frameborder="0" vspace="0" hspace="0"><?= _("Unable to load this page or resource"); ?></iframe>
</div>
<!-- Bootstrap 3.3.6 -->
<script src="/assets/js/bootstrap.min.js"></script>
<!-- DataTables -->
<script src="/assets/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/assets/plugins/datatables/dataTables.bootstrap.min.js"></script>
<script>
  var oTable = null;
  $(document).ready(function() {
    oTable = $('#facilityModalDataTable').dataTable({
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
        }
      ],
      "aaSorting": [
        [1, "asc"]
      ],
      "bProcessing": true,
      "bServerSide": true,
      "sAjaxSource": "getFacilitiesModalDetails.php",
      "fnServerData": function(sSource, aoData, fnCallback) {
        aoData.push({
          "name": "hub",
          "value": $("#hub").val()
        });
        aoData.push({
          "name": "district",
          "value": $("#district").val()
        });
        aoData.push({
          "name": "state",
          "value": $("#state").val()
        });
        aoData.push({
          "name": "facilityName",
          "value": $("#facilityTypeName").val()
        });
        aoData.push({
          "name": "type",
          "value": '<?php echo $type; ?>'
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

  function getFacility(fDetails) {
    parent.closeModal();
    window.parent.setFacilityDetails(fDetails);
  }

  function getFacilityLab(fDetails) {
    parent.closeModal();
    window.parent.setFacilityLabDetails(fDetails);
  }

  function searchFacilityData() {
    oTable.fnDraw();
  }

  function showModal(url, w, h) {
    $('html, body').css('overflow-x', 'hidden');
    $('html, body').css('overflow-y', 'hidden');
    showdefModal('dDiv', w, h);
    document.getElementById('dFrame').style.height = h + 'px';
    document.getElementById('dFrame').style.width = w + 'px';
    document.getElementById('dFrame').src = url;
  }
</script>