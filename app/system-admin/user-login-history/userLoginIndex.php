<?php
$title = _("User Login History") . " - " . _("System Admin");

require_once(APPLICATION_PATH . '/system-admin/admin-header.php');
$sQuery = "SELECT * FROM user_login_history";
$sResult = $db->rawQuery($sQuery);
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1> <em class="fa-solid fa-gears"></em> <?php echo _("User Login History"); ?></h1>
    <ol class="breadcrumb">
      <li><a href="/system-admin/edit-config/index.php"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
      <li class="active"><?php echo _("Manage User Login History"); ?></li>
    </ol>
  </section>

  <!-- Main content -->
  <section class="content">
    <div class="row">
      <div class="col-xs-12">


        <div class="box">
          <table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:98%;">
            <tr>
              <td><strong><?php echo _("Date"); ?>&nbsp;:</strong></td>
              <td>
                <input type="text" id="userDate" name="userDate" class="form-control daterangefield" placeholder="<?php echo _('Select User Date'); ?>" readonly style="width:220px;background:#fff;" />
              </td>

              <td><strong><?php echo _("Login ID"); ?>&nbsp;:</strong></td>
              <td>
                <select style="width:220px;" class="form-control" id="loginId" name="loginId" title="<?php echo _('Please select login id'); ?>">
                  <option value=""> <?php echo _("-- Select --"); ?> </option>
                  <?php
                  foreach ($sResult as $type) {
                  ?>
                    <option value="<?php echo $type['login_id']; ?>"><?php echo ($type['login_id']); ?></option>
                  <?php
                  }
                  ?>
                </select>
              </td>
            </tr>
            <tr>
              <td colspan="6">
                &nbsp;<button onclick="searchVlRequestData();" value="Search" class="btn btn-primary btn-sm"><span><?php echo _("Search"); ?></span></button>

                &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _("Clear Search"); ?></span></button>
              </td>
            </tr>

          </table>
          <!-- /.box-header -->
          <div class="box-body">
            <table aria-describedby="table" id="userLoginHistoryDataTable" class="table table-bordered table-striped" aria-hidden="true" >
              <thead>
                <tr>
                  <th><?php echo _("Login Id"); ?></th>
                  <th><?php echo _("Attempted Datetime"); ?></th>
                  <th><?php echo _("IP Address"); ?></th>
                  <th><?php echo _("Browser"); ?></th>
                  <th><?php echo _("Operating System"); ?></th>
                  <th scope="row"><?php echo _("Status"); ?></th>

                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="6" class="dataTables_empty"><?php echo _("Loading data from server"); ?></td>
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
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script>
  var startDate = "";
  var endDate = "";
  var oTable = null;


  function loadUserLoginRequestData() {
    $.blockUI();
    oTable = $('#userLoginHistoryDataTable').dataTable({
      "oLanguage": {
        "sLengthMenu": "_MENU_ records per page"
      },
      "bJQueryUI": false,
      "bAutoWidth": false,
      "bInfo": true,
      "bScrollCollapse": true,

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
          "sClass": "center"
        },
      ],
      "aaSorting": [
        [0, "desc"]
      ],
      "bProcessing": true,
      "bServerSide": true,
      "sAjaxSource": "getUserLoginHistoryDetails.php",
      "fnServerData": function(sSource, aoData, fnCallback) {
        aoData.push({
          "name": "userDate",
          "value": $("#userDate").val()
        });
        aoData.push({
          "name": "loginId",
          "value": $("#loginId").val()
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
    $.unblockUI();
  }

  function searchVlRequestData() {
    $.blockUI();
    oTable.fnDraw();
    $.unblockUI();
  }

  $(document).ready(function() {

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

    $('#userDate').val("");

    loadUserLoginRequestData();

  });
</script>
<?php
require_once(APPLICATION_PATH . '/system-admin/admin-footer.php');
