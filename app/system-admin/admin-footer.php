<footer class="main-footer">
  <span class="pull-right">v <?php echo VERSION; ?></span>
</footer>
</div>
<!-- ./wrapper -->



<!-- Resolve conflict in jQuery UI tooltip with Bootstrap tooltip -->
<script type="text/javascript" src="/assets/js/jquery-ui-timepicker-addon.js"></script>

<script type="text/javascript" src="/assets/js/select2.min.js"></script>
<!-- Bootstrap 3.3.6 -->
<script type="text/javascript" src="/assets/js/bootstrap.min.js"></script>
<!-- DataTables -->
<script type="text/javascript" src="/assets/plugins/datatables/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="/assets/plugins/datatables/dataTables.bootstrap.min.js"></script>

<!-- AdminLTE App -->
<script type="text/javascript" src="/assets/js/app.min.js"></script>

<script type="text/javascript" src="/assets/js/jquery.maskedinput.js"></script>
<script type="text/javascript" src="/assets/js/jquery.blockUI.js"></script>
<script type="text/javascript" src="/assets/js/moment.min.js"></script>
<?php require_once(WEB_ROOT . '/assets/js/main.js.php'); ?>
<?php require_once(WEB_ROOT . '/assets/js/dates.js.php'); ?>
<script type="text/javascript">
  $(document).ready(function() {
    <?php
    if (isset($_SESSION['alertMsg']) && trim((string) $_SESSION['alertMsg']) !== "") {
    ?>
      alert("<?php echo $_SESSION['alertMsg']; ?>");
    <?php
      $_SESSION['alertMsg'] = '';
      unset($_SESSION['alertMsg']);
    }
    ?>
  });
  str = $(location).attr('pathname');
  splitsUrl = str.substr(str.lastIndexOf('/') + 1);
  if (splitsUrl == 'index.php') {
    $(".treeview").addClass('active');
    $(".allMenu").removeClass('active');
    $(".systemConfigmenu").addClass('active');
  } else if (splitsUrl == 'instanceIndex.php') {
    $(".treeview").addClass('active');
    $(".allMenu").removeClass('active');
    $(".instanceOverviewMenu").addClass('active');
  } else if (splitsUrl == 'api-stats.php') {
    $(".treeview").addClass('active');
    $(".allMenu").removeClass('active');
    $(".apiStatsMenu").addClass('active');
  } else if (splitsUrl == 'userLoginIndex.php') {
    $(".treeview").addClass('active');
    $(".allMenu").removeClass('active');
    $(".userLoginMenu").addClass('active');
  } else if (splitsUrl == 'log-files.php') {
    $(".treeview").addClass('active');
    $(".allMenu").removeClass('active');
    $(".logFileMenu").addClass('active');
  } else if (splitsUrl == 'reset-password.php') {
    $(".treeview").addClass('active');
    $(".allMenu").removeClass('active');
    $(".resetPasswordMenu").addClass('active');
  } else {
    $(".allMenu").removeClass('active');
    $(".dashboardMenu").addClass('active');
  }
</script>
</body>

</html>