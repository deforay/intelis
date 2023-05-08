<footer class="main-footer">
  <span class="pull-right">v <?php echo VERSION; ?></span>
</footer>
</div>
<!-- ./wrapper -->


<!-- jQuery UI 1.11.4 -->
<!--<script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>-->
<!-- Resolve conflict in jQuery UI tooltip with Bootstrap tooltip -->
<script type="text/javascript" src="/assets/js/jquery-ui-timepicker-addon.js"></script>

<script src="/assets/js/select2.min.js"></script>
<!-- Bootstrap 3.3.6 -->
<script src="/assets/js/bootstrap.min.js"></script>
<!-- DataTables -->
<script src="/assets/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/assets/plugins/datatables/dataTables.bootstrap.min.js"></script>

<!-- AdminLTE App -->
<script src="/assets/js/app.min.js"></script>
<script src="/assets/js/main.js"></script>
<script src="/assets/js/deforayValidation.js"></script>
<script src="/assets/js/jquery.maskedinput.js"></script>
<script src="/assets/js/jquery.blockUI.js"></script>
<script src="/assets/js/moment.min.js"></script>

<script type="text/javascript">
  $(document).ready(function() {
    <?php
    if (isset($_SESSION['alertMsg']) && trim($_SESSION['alertMsg']) != "") {
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
    $(".manage").addClass('active');
    $(".allMenu").removeClass('active');
    $(".systemConfigmenu").addClass('active');
  } else if (splitsUrl == 'instanceIndex.php') {
    $(".manage").addClass('active');
    $(".allMenu").removeClass('active');
    $(".instanceOverviewMenu").addClass('active');
  } else if (splitsUrl == 'api-stats.php') {
    $(".manage").addClass('active');
    $(".allMenu").removeClass('active');
    $(".apiStatsMenu").addClass('active');
  } else if (splitsUrl == 'userLoginIndex.php') {
    $(".manage").addClass('active');
    $(".allMenu").removeClass('active');
    $(".userLoginMenu").addClass('active');
  } else {
    $(".allMenu").removeClass('active');
    $(".dashboardMenu").addClass('active');
  }
</script>
</body>

</html>