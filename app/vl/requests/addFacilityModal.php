<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

  

$type=$_GET['type'];
if(isset($_POST['facilityName']) && trim($_POST['facilityName'])!="" && trim($_POST['facilityCode'])!=''){
    $tableName="facility_details";
    $data=array(
        'facility_name'=>$_POST['facilityName'],
        'facility_code'=>$_POST['facilityCode'],
        'vlsm_instance_id'=>$_SESSION['instanceId'],
        'other_id'=>$_POST['otherId'],
        'facility_mobile_numbers'=>$_POST['phoneNo'],
        'address'=>$_POST['address'],
        'country'=>$_POST['country'],
        'facility_state'=>$_POST['state'],
        'facility_district'=>$_POST['district'],
        'facility_hub_name'=>$_POST['hubName'],
        'facility_emails'=>$_POST['email'],
        'contact_person'=>$_POST['contactPerson'],
	'facility_type'=>$_POST['facilityType'],
        'status'=>'active'
        );
        //print_r($data);die;
        $db->insert($tableName,$data);
    ?>
        <script>window.parent.location.href=window.parent.location.href;</script>
<?php 
}
$fQuery="SELECT * FROM facility_type";
$fResult = $db->rawQuery($fQuery);
$pQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
$pResult = $db->rawQuery($pQuery);
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
    .content-wrapper{
      padding:2%;
    }
  </style> 
  <script type="text/javascript" src="/assets/js/jquery.min.js"></script>
  <script type="text/javascript" src="/assets/js/jquery-ui.min.js"></script>
  <script src="/assets/js/deforayModal.js"></script>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h3>Add Facility</h3>
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
                        <label for="facilityName" class="col-lg-4 control-label">Facility Name <span class="mandatory">*</span></label>
                        <div class="col-lg-7">
                        <input type="text" class="form-control isRequired" id="facilityName" name="facilityName" placeholder="Facility Name" title="Please enter facility name" />
                        </div>
                    </div>
                  </div>
                   <div class="col-md-6">
                    <div class="form-group">
                        <label for="facilityCode" class="col-lg-4 control-label">Facility Code</label>
                        <div class="col-lg-7">
                        <input type="text" class="form-control" id="facilityCode" name="facilityCode" placeholder="Facility Code" title="Please enter facility code" onblur="checkNameValidation('facility_details','facility_code',this,null,'This code already exists.Try another code',null)"/>
                        </div>
                    </div>
                  </div>
                </div>
                
                <div class="row">
		    <div class="col-md-6">
                    <div class="form-group">
                        <label for="otherId" class="col-lg-4 control-label">Other Id </label>
                        <div class="col-lg-7">
                        <input type="text" class="form-control" id="otherId" name="otherId" placeholder="Other Id" />
                        </div>
                    </div>
                  </div>
                    <div class="col-md-6">
                    <div class="form-group">
                        <label for="facilityType" class="col-lg-4 control-label">Clinic Type <span class="mandatory">*</span> </label>
                        <div class="col-lg-7">
			    <?php if($type=='all'){ ?>
                        <select class="form-control isRequired" id="facilityType" name="facilityType" title="Please select clinic type">
                          <option value=""> -- Select -- </option>
                            <?php
                            foreach($fResult as $type){
                             ?>
                             <option value="<?php echo $type['facility_type_id'];?>"><?php echo ($type['facility_type_name']);?></option>
                             <?php
                            }
                            ?>
                          </select>
			<?php } else { ?>
			    <input type="hidden" class="form-control" id="facilityType" name="facilityType" value="2" />
			    <input type="text" class="form-control readonly" id="facilityTypeName" name="facilityTypeName" value="Lab" readonly="readonly" style="background-color: #fff;"/>
			<?php } ?>
                        </div>
                    </div>
                  </div>
                    <div class="col-md-6">
                    <div class="form-group">
                        <label for="email" class="col-lg-4 control-label">Email </label>
                        <div class="col-lg-7">
                        <input type="text" class="form-control isEmail" id="email" name="email" placeholder="Email" />
                        </div>
                    </div>
                  </div>
                   
                </div>
                
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="contactPerson" class="col-lg-4 control-label">Contact Person</label>
                        <div class="col-lg-7">
                        <input type="text" class="form-control" id="contactPerson" name="contactPerson" placeholder="Contact Person" />
                        </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="phoneNo" class="col-lg-4 control-label">Phone Number</label>
                        <div class="col-lg-7">
                        <input type="text" class="form-control" id="phoneNo" name="phoneNo" placeholder="Phone Number" />
                        </div>
                    </div>
                  </div>
                  
                </div>
                
                <div class="row">
                   <div class="col-md-6">
                    <div class="form-group">
                        <label for="state" class="col-lg-4 control-label">State/Province</label>
                        <div class="col-lg-7">
			<select name="state" id="state" class="form-control isRequired" title="Please choose state/province">
                          <option value=""> -- Select -- </option>
                          <?php
                          foreach($pResult as $province){
                            ?>
                            <option value="<?php echo $province['geo_name'];?>"><?php echo $province['geo_name'];?></option>
                            <?php
                          }
                          ?>
                        </select>
                        </div>
                    </div>
                  </div>
		   <div class="col-md-6">
                    <div class="form-group">
                        <label for="state" class="col-lg-4 control-label">District</label>
                        <div class="col-lg-7">
                        <input type="text" class="form-control" id="district" name="district" placeholder="District" />
                        </div>
                    </div>
                  </div>
                </div>
               
               <div class="row">
		<div class="col-md-6">
                    <div class="form-group">
                        <label for="hubName" class="col-lg-4 control-label">Linked Hub Name (If Applicable)</label>
                        <div class="col-lg-7">
                        <input type="text" class="form-control" id="hubName" name="hubName" placeholder="Hub Name" title="Please enter hub name" />
                        </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="address" class="col-lg-4 control-label">Address</label>
                        <div class="col-lg-7">
                        <textarea class="form-control" name="address" id="address" placeholder="Address"></textarea>
                        </div>
                    </div>
                  </div>
                </div>
	       <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="country" class="col-lg-4 control-label">Country</label>
                        <div class="col-lg-7">
                        <input type="text" class="form-control" id="country" name="country" placeholder="Country"/>
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
      <iframe id="dFrame" src="" style="border:none;" scrolling="yes" marginwidth="0" marginheight="0" frameborder="0" vspace="0" hspace="0"><?= _("Unable to load this page or resource"); ?></iframe> 
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
  
   function checkNameValidation(tableName,fieldName,obj,fnct,alrt,callback){
        var removeDots=obj.value.replace(/\./g,"");
        var removeDots=removeDots.replace(/\,/g,"");
        //str=obj.value;
        removeDots = removeDots.replace(/\s{2,}/g,' ');

        $.post("/includes/checkDuplicate.php", { tableName: tableName,fieldName : fieldName ,value : removeDots.trim(),fnct : fnct, format: "html"},
        function(data){
            if(data==='1'){
                alert(alrt);
                document.getElementById(obj.id).value="";
            }
        });
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
