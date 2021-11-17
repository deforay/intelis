
  <footer class="main-footer">
    <a href="http://taskforce.org/">Funded by TaskForce</a>
    <span class="pull-right">v <?php echo VERSION; ?></span>
  </footer>
</div>
<!-- ./wrapper -->


<!-- jQuery UI 1.11.4 -->
<!--<script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>-->
<!-- Resolve conflict in jQuery UI tooltip with Bootstrap tooltip -->
<script type="text/javascript" src="./assets/js/jquery-ui-timepicker-addon.js"></script>

<script src="/assets/js/select2.js"></script>
<!-- Bootstrap 3.3.6 -->
<script src="/assets/js/bootstrap.min.js"></script>
<!-- DataTables -->
<script src="./assets/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="./assets/plugins/datatables/dataTables.bootstrap.min.js"></script>

<!-- AdminLTE App -->
<script src="../../dist/js/app.min.js"></script>
<script src="/assets/js/deforayValidation.js"></script>
<script src="/assets/js/jquery.maskedinput.js"></script>
<script src="/assets/js/jquery.blockUI.js"></script>
<script src="/assets/js/moment.min.js"></script>

<script type="text/javascript">
  $(document).ready(function(){
  <?php
  if(isset($_SESSION['alertMsg']) && trim($_SESSION['alertMsg'])!=""){
  ?>
    alert('<?php echo $_SESSION['alertMsg']; ?>');
  <?php
  $_SESSION['alertMsg']='';
  unset($_SESSION['alertMsg']);
  }
  ?>
  });
  str=$(location).attr('pathname');
  splitsUrl=str.substr(str.lastIndexOf('/') + 1);
  if (splitsUrl=='index.php' ) {
    $(".manage").addClass('active');
    $(".allMenu").removeClass('active');
    $(".systemConfigmenu").addClass('active');
  }else{
    $(".allMenu").removeClass('active');
    $(".dashboardMenu").addClass('active');
  }
  
  function showModal(url, w, h) {
      showdefModal('dDiv', w, h);
      document.getElementById('dFrame').style.height = h + 'px';
      document.getElementById('dFrame').style.width = w + 'px';
      document.getElementById('dFrame').src = url;
  }
  function closeModal() {
      document.getElementById('dFrame').src = "";
      hidedefModal('dDiv');
  }
  jQuery(".checkNum").keydown(function (e) {
	      // Allow: backspace, delete, tab, escape, enter and .
	      if ($.inArray(e.keyCode, [46, 8, 9, 27, 13, 110, 190]) !== -1 ||
		       // Allow: Ctrl+A
		      (e.keyCode == 65 && e.ctrlKey === true) || 
		       // Allow: home, end, left, right
		      (e.keyCode >= 35 && e.keyCode <= 39)) {
			       // let it happen, don't do anything
			       return;
	      }
	      // Ensure that it is a number and stop the keypress
	      if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
		      e.preventDefault();
	      }
  });
</script>
</body>
</html>