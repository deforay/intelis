<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();
if(isset($_POST['userName']) && trim($_POST['userName'])!="" && trim($_POST['userName'])!=''){
    ?>
        <script>window.parent.location.href=window.parent.location.href;</script>
<?php 
}
?>
  <link rel="stylesheet" media="all" type="text/css" href="/assets/css/jquery-ui.1.11.0.css" />
  <!-- Bootstrap 3.3.6 -->
  <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="/assets/css/font-awesome.min.4.5.0.css">
   <!-- DataTables -->
  <link rel="stylesheet" href="/assets/plugins/datatables/dataTables.bootstrap.css">
  <link href="/assets/css/deforayModal.css" rel="stylesheet" />    
   <style>
    .content-wrapper{
      padding:2%;
    }
  </style> 
  <script type="text/javascript" src="/assets/js/jquery.min.js"></script>
  <script type="text/javascript" src="/assets/js/jquery-ui.1.11.0.js"></script>
  <script src="assets/js/deforayModal.js"></script>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h3>To Share Details</h3>
    </section>

    <!-- Main content -->
    <section class="content">
      
      <div class="box box-default">
        <div class="box-header with-border">
          <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indicates required field &nbsp;</div>
        </div>
        <!-- /.box-header -->
        <div class="box-body">
          <!-- form start -->
            <form class="form-vertical" method='post' name='addFacilityModalForm' id='addFacilityModalForm' autocomplete="off" action="#">
              <div class="box-body">
                <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="details" class="col-lg-4 control-label">Name </label>
                        <div class="col-lg-7">
                        <input type="text" class="form-control" id="userName" name="userName" value="<?php echo $_SESSION['userName']; ?>" style="width:100%;" readonly>
                        </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="details" class="col-lg-4 control-label">Subject <span class="mandatory">*</span></label>
                        <div class="col-lg-7">
                        <select class="form-control isRequired" id="subject" name="subject" title="Select the subject" style="width:100%;">
                        <option value=""> -- Select -- </option>
                        <option value="standard">Standard - Free</option>  
                        <option value="prof">Professional - Paid</option>  
                        </select>
                        </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="details" class="col-lg-4 control-label">Message <span class="mandatory">*</span></label>
                        <div class="col-lg-7">
                        <textarea class="form-control isRequired" id="details" name="details" placeholder="Enter the text" title="Enter the text" style="width:100%;"></textarea>
                        </div>
                    </div>
                  </div>
                   <div class="col-md-6">
                    <div class="form-group">
                        <label for="image" class="col-lg-4 control-label">Do you want to attach the screenshot of the current page? <span class="mandatory">*</span></label>
                        <div class="col-lg-7">
                        <input type="checkbox" name="screenShot" value="yes" /> Yes &nbsp;
											  <input type="checkbox" name="screenShot" value="no" /> No <br>
                        <img src="/uploads/screenshot/1.jpg" style="width: 20%;">
                        </div>
                    </div>
                  </div>
                </div>
                
               
              </div>
              <!-- /.box-body -->
              <div class="box-footer">
                <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Submit</a>
                <a href="javascript:void(0);" class="btn btn-default" onclick="goBack();"> Cancel</a>
              </div>
              <!-- /.box-footer -->
            </form>
          <!-- /.row -->
        </div>
       
      </div>
      <!-- /.box -->

    </section>
    <!-- /.content -->
  </div>
  <div id="dDiv" class="dialog">
      <div style="text-align:center"><span onclick="closeModal();" style="float:right;clear:both;" class="closeModal"></span></div> 
      <iframe id="dFrame" src="" style="border:none;" scrolling="yes" marginwidth="0" marginheight="0" frameborder="0" vspace="0" hspace="0">some problem</iframe> 
  </div>
  <!-- Bootstrap 3.3.6 -->
  <script src="/assets/js/bootstrap.min.js"></script>
  <!-- DataTables -->
  <script src="/assets/plugins/datatables/jquery.dataTables.min.js"></script>
  <script src="/assets/plugins/datatables/dataTables.bootstrap.min.js"></script>
  <script src="/assets/js/deforayValidation.js"></script>
  <script type="text/javascript">
   function validateNow(){
    flag = deforayValidator.init({
        formId: 'addFacilityModalForm'
    });
    
    if(flag){
      document.getElementById('addFacilityModalForm').submit();
    }
   }
  
   function showModal(url, w, h) {
      showdefModal('dDiv', w, h);
      document.getElementById('dFrame').style.height = h + 'px';
      document.getElementById('dFrame').style.width = w + 'px';
      document.getElementById('dFrame').src = url;
    }
    
    function goBack(){
        window.parent.location.href=window.parent.location.href;
    }
  </script>
