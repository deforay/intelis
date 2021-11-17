<?php
ob_start();
$title = "Edit Configuration";
include('../admin-header.php');
$globalConfigQuery ="SELECT * from system_config";
$configResult=$db->query($globalConfigQuery);
$arr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($configResult); $i++) {
    $arr[$configResult[$i]['name']] = $configResult[$i]['value'];
}
//get lab details
$fDetails ="SELECT * from facility_details where facility_type='2'";
$fResult=$db->query($fDetails);
?>
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1 class="fa fa-gears"> Edit System Configuration</h1>
      <ol class="breadcrumb">
        <li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>
        <li class="active">Manage System Config</li>
      </ol>
    </section>

    <!-- Main content -->
    <section class="content">
      
      <div class="box box-default">
        <!--<div class="box-header with-border">
          <div class="pull-right" style="font-size:15px;"> </div>
        </div>-->
        <!-- /.box-header -->
        <div class="box-body">
          <!-- form start -->
            <form class="form-horizontal" method='post' name='editSystemConfigForm' id='editSystemConfigForm' enctype="multipart/form-data" autocomplete="off" action="systemConfigHelper.php">
              <div class="box-body">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Instance Settings</h3>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-7">
                              <div class="form-group">
                                <label for="sc_user_type" class="col-lg-4 control-label">Instance Type <span class="mandatory">*</span></label>
                                <div class="col-lg-8">
                                  <select type="text" class="form-control" id="sc_user_type" name="sc_user_type" placeholder="User Type" title="Please choose user type" onchange="enableLab();">
                                    <option value="standalone" <?php echo ('standalone'==$arr['sc_user_type'])?"selected='selected'":""?>>Stand Alone</option>
                                    <option value="vluser" <?php echo ('vluser'==$arr['sc_user_type'])?"selected='selected'":""?>>Vl User</option>
                                    <option value="remoteuser" <?php echo ('remoteuser'==$arr['sc_user_type'])?"selected='selected'":""?>>Remote User</option>
                                  </select>
                                </div>
                              </div>
                            </div>
                            <div class="col-md-7 labName" style="<?php echo ($arr['sc_user_type']=='vluser')?'display:show':'display:none';?>">
                              <div class="form-group">
                                <label for="sc_testing_lab_id" class="col-lg-4 control-label">Lab Name</label>
                                <div class="col-lg-8">
                                    <select class="form-control" name="sc_testing_lab_id" id="sc_testing_lab_id" title="Please select the lab name">
                                        <option value="">-- Select --</option>
                                        <?php foreach($fResult as $labName){ ?>
                                            <option value="<?php echo $labName['facility_id'];?>" <?php echo ($labName['facility_id']==$arr['sc_testing_lab_id'])?"selected='selected'":""?>><?php echo $labName['facility_name'];?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                              </div>
                            </div>
                        </div>
                    </div>
                </div>
              </div>
              <div class="box-footer">
                <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Submit</a>
                <a href="index.php" class="btn btn-default"> Reload</a>
              </div>
            </form>
        </div>
      </div>
    </section>
</div>
<script>
    $(document).ready(function() {
        enableLab();
    });
    function enableLab()
    {
        if($("#sc_user_type").val()=='vluser'){
            $(".labName").show();
            $("#sc_testing_lab_id").addClass("isRequired").css('pointer-events','');
        }else{
            $(".labName").hide();
            $("#sc_testing_lab_id").removeClass("isRequired").css('pointer-events','none').val('');
        }
    }
    function validateNow(){
    flag = deforayValidator.init({
        formId: 'editSystemConfigForm'
    });
    if(flag){
        $.blockUI();
      document.getElementById('editSystemConfigForm').submit();
    }
  }
</script>
<?php
include('/system-admin/admin-footer.php');
?>