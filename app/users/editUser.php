<?php
ob_start();
$title = _("Edit User");

require_once(APPLICATION_PATH . '/header.php');
$id = base64_decode($_GET['id']);

$userInfo = $db->rawQueryOne('SELECT ud.*, r.role_id, r.role_name from user_details as ud LEFT JOIN roles as r ON ud.role_id=r.role_id where user_id= ?', Array ($id));


$interfaceUsers = "";
if (!empty($userInfo['interface_user_name'])) {
     $interfaceUsers = implode(", ", json_decode($userInfo['interface_user_name'], true));
}

$query = "SELECT * FROM roles WHERE status='active'";
$result = $db->rawQuery($query);


$fResult = array();
$display = 'display:none';
if ($_SESSION['instanceType'] == 'remoteuser') {
     //get all facility list with lab,clinic
     $fQuery = "SELECT facility_name,facility_id FROM facility_details";
     $fResult = $db->rawQuery($fQuery);
     $display = 'display:block';
}

$selectedQuery = "SELECT * FROM user_facility_map as vlfm join user_details as ud ON ud.user_id=vlfm.user_id join facility_details as fd ON fd.facility_id=vlfm.facility_id where vlfm.user_id = '" . $id . "'";
$selectedResult = $db->rawQuery($selectedQuery);

//province Stratt
$pdQuery = "SELECT * from geographical_divisions WHERE geo_parent = 0";
$pdResult = $db->query($pdQuery);
$province = '';
$province .= "<option value=''> -- Select -- </option>";
foreach ($pdResult as $provinceName) {
     $province .= "<option value='" . $provinceName['geo_name'] . "##" . $provinceName['geo_code'] . "'>" . ucwords($provinceName['geo_name']) . "</option>";
}

//Province Details  Ends
$fQuery = "SELECT * FROM facility_type";
$ftResult = $db->rawQuery($fQuery);
?>

<link href="/assets/css/jasny-bootstrap.min.css" rel="stylesheet" />

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
     <!-- Content Header (Page header) -->
     <section class="content-header">
          <h1> <i class="fa-solid fa-user"></i> <?php echo _("Edit User"); ?></h1>
          <ol class="breadcrumb">
               <li><a href="/"><i class="fa-solid fa-chart-pie"></i> <?php echo _("Home"); ?></a></li>
               <li class="active"><?php echo _("Users"); ?></li>
          </ol>
     </section>

     <!-- Main content -->
     <section class="content">

          <div class="box box-default">
               <div class="box-header with-border">
                    <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?php echo _("indicates required field"); ?> &nbsp;</div>
               </div>
               <!-- /.box-header -->
               <div class="box-body">
                    <!-- form start -->
                    <form class="form-horizontal" method='post' name='userEditForm' id='userEditForm' autocomplete="off" action="editUserHelper.php" enctype="multipart/form-data">
                         <div class="box-body">
                              <div class="row">
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="userName" class="col-lg-4 control-label"><?php echo _("User Name"); ?> <span class="mandatory">*</span></label>
                                             <div class="col-lg-7">
                                                  <input type="text" class="form-control isRequired" id="userName" name="userName" placeholder="<?php echo _('User Name'); ?>" title="<?php echo _('Please enter user name'); ?>" value="<?php echo $userInfo['user_name']; ?>" />
                                                  <input type="hidden" name="userId" id="userId" value="<?php echo base64_encode($userInfo['user_id']); ?>" />
                                             </div>
                                        </div>
                                   </div>
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="email" class="col-lg-4 control-label"><?php echo _("Email"); ?> </label>
                                             <div class="col-lg-7">
                                                  <input type="text" class="form-control" id="email" name="email" placeholder="<?php echo _('Email'); ?>" title="<?php echo _('Please enter email'); ?>" value="<?php echo $userInfo['email']; ?>" onblur="checkNameValidation('user_details','email',this,'<?php echo "user_id##" . $userInfo['user_id']; ?>','<?php echo _("This email id that you entered already exists.Try another email id"); ?>',null)" />
                                             </div>
                                        </div>
                                   </div>
                              </div>
                              <div class="row">
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="phoneNo" class="col-lg-4 control-label"><?php echo _("Phone Number"); ?></label>
                                             <div class="col-lg-7">
                                                  <input type="text" class="form-control" id="phoneNo" name="phoneNo" placeholder="<?php echo _('Phone Number'); ?>" title="<?php echo _('Please enter phone number'); ?>" value="<?php echo $userInfo['phone_number']; ?>" />
                                             </div>
                                        </div>
                                   </div>

                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="role" class="col-lg-4 control-label"><?php echo _("Role"); ?> <span class="mandatory">*</span></label>
                                             <div class="col-lg-7">
                                                  <select class="form-control isRequired" name='role' id='role' title="<?php echo _('Please select the role'); ?>">
                                                       <option value=""><?php echo _("--Select--"); ?></option>
                                                       <?php foreach ($result as $row) {
                                                            $roleCode = (isset($userInfo['role_id']) && $userInfo['role_id'] == $row['role_id']) ? $row['role_code'] : ""
                                                       ?>
                                                            <option value="<?php echo $row['role_id']; ?>" data-code="<?php echo $row['role_code']; ?>" <?php echo (isset($userInfo['role_id']) && $userInfo['role_id'] == $row['role_id']) ? "selected='selected'" : ""; ?>><?php echo ucwords(($row['role_name'])); ?></option>
                                                       <?php } ?>
                                                  </select>
                                             </div>
                                        </div>
                                   </div>
                              </div>

                              <div class="row show-token" style="display: <?php echo ($roleCode != "" && $roleCode == "API") ? 'block' : 'none'; ?>;">
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="authToken" class="col-lg-4 control-label"><?php echo _("AuthToken"); ?> <span class="mandatory">*</span></label>
                                             <div class="col-lg-7">
                                                  <input type="text" value="<?php echo $userInfo['api_token']; ?>" class="form-control" id="authToken" name="authToken" placeholder="<?php echo _('Auth Token'); ?>" title="<?php echo _('Please Generate the auth token'); ?>" readonly>
                                             </div>
                                        </div>
                                   </div>
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <a href="javascript:void(0);" class="btn btn-sm btn-primary" onclick="generateToken('authToken');"><?php echo _("Generate Token"); ?></a>
                                        </div>
                                   </div>
                              </div>
                              <div class="row">
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="interfaceUserName" class="col-lg-4 control-label"><?php echo _("Interface User Name (from your Molecular testing machine)"); ?></label>
                                             <div class="col-lg-7">
                                                  <input type="text" value="<?php echo $interfaceUsers ?>" class="form-control" id="interfaceUserName" name="interfaceUserName" placeholder="<?php echo _('Interface User Name'); ?>" title="<?php echo _('Please enter interface user name'); ?>" />
                                             </div>
                                        </div>
                                   </div>
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="appAccessable" class="col-lg-4 control-label"><?php echo _("Mobile App Access"); ?></label>
                                             <div class="col-lg-7">
                                                  <select class="form-control" name='appAccessable' id='appAccessable' title="<?php echo _('Please select the mobile App access or not'); ?>?">
                                                       <option value=""><?php echo _("--Select--"); ?></option>
                                                       <option value="yes" <?php echo ($userInfo['app_access'] == 'yes') ? "selected='selected'" : "" ?>><?php echo _("Yes"); ?></option>
                                                       <option value="no" <?php echo ($userInfo['app_access'] == 'no') ? "selected='selected'" : "" ?>><?php echo _("No"); ?></option>
                                                  </select>
                                             </div>
                                        </div>
                                   </div>
                              </div>

                              <div class="row">
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="" class="col-lg-4 control-label"><?php echo _("Signature"); ?> <br><?php echo _("(Used to embed in Result PDF)"); ?></label>
                                             <div class="col-lg-8">
                                                  <div class="fileinput fileinput-new userSignature" data-provides="fileinput">
                                                       <div class="fileinput-preview thumbnail image-placeholder" data-trigger="fileinput" style="width:200px; height:150px;">
                                                            <?php
                                                            if (isset($userInfo['user_signature']) && trim($userInfo['user_signature']) != '' && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $userInfo['user_signature'])) {
                                                            ?>
                                                                 <img src="/uploads/users-signature/<?php echo $userInfo['user_signature']; ?>" alt="Signature image">
                                                            <?php } else { ?>
                                                                 
                                                            <?php } ?>
                                                       </div>
                                                       <div>
                                                            <span class="btn btn-default btn-file"><span class="fileinput-new"><?php echo _("Select Signature Image"); ?></span><span class="fileinput-exists"><?php echo _("Change"); ?></span>
                                                                 <input type="file" id="userSignature" name="userSignature" accept="image/png,image/gpg,image/jpeg" title="<?php echo _('Please select user signature'); ?>" onchange="getNewSignatureImage('<?php echo $userInfo['user_signature']; ?>');">
                                                            </span>
                                                            <?php
                                                            if (isset($userInfo['user_signature']) && trim($userInfo['user_signature']) != '' && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $userInfo['user_signature'])) {
                                                            ?>
                                                                 <a id="clearUserSignature" href="javascript:void(0);" class="btn btn-default" data-dismiss="fileupload" onclick="clearUserSignature('<?php echo $userInfo['user_signature']; ?>')"><?php echo _("Clear"); ?></a>
                                                            <?php } ?>
                                                            <a href="#" class="btn btn-default fileinput-exists" data-dismiss="fileinput"><?php echo _("Remove"); ?></a>
                                                       </div>
                                                  </div>
                                                  <div class="box-body">
                                                       <?php echo _("Image Size"); ?> : <code>100px x 100px</code>
                                                  </div>
                                             </div>
                                        </div>
                                   </div>


                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="loginId" class="col-lg-4 control-label"><?php echo _("Login Id"); ?> <span class="mandatory">*</span></label>
                                             <div class="col-lg-7">
                                                  <input type="text" class="form-control isRequired" id="loginId" name="loginId" placeholder="<?php echo _('Login Id'); ?>" title="<?php echo _('Please enter login id'); ?>" value="<?php echo $userInfo['login_id']; ?>" onblur="checkNameValidation('user_details','login_id',this,'<?php echo "user_id##" . $userInfo['user_id']; ?>','<?php echo _("This login id that you entered already exists.Try another login id"); ?>',null)" />
                                             </div>
                                        </div>
                                   </div>

                              </div>

                              <div class="row">
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="password" class="col-lg-4 control-label"><?php echo _("Password"); ?> </label>
                                             <div class="col-lg-7">
                                                  <input type="password" class="form-control ppwd" id="confirmPassword" name="password" placeholder="<?php echo _('Password'); ?>" title="<?php echo _('Please enter the password'); ?>" />
                                                  <code><?= _("Password must be at least 8 characters long and must include AT LEAST one number, one alphabet and may have special characters.") ?></code>
                                             </div>
                                        </div>
                                   </div>
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="confirmPassword" class="col-lg-4 control-label"><?php echo _("Confirm Password"); ?></label>
                                             <div class="col-lg-7">
                                                  <input type="password" class="form-control cpwd confirmPassword" id="confirmPassword" name="password" placeholder="<?php echo _('Confirm Password'); ?>" title="" />
                                             </div>
                                        </div>
                                   </div>

                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="status" class="col-lg-4 control-label"><?php echo _("User Status"); ?> <span class="mandatory">*</span></label>
                                             <div class="col-lg-7">
                                                  <select class="form-control isRequired" name='status' id='status' title="<?php echo _('Please select the status'); ?>">
                                                       <option value=""> <?php echo _("-- Select --"); ?> </option>
                                                       <option value="active" <?php echo ($userInfo['status'] == 'active') ? "selected='selected'" : "" ?>><?php echo _("Active"); ?></option>
                                                       <option value="inactive" <?php echo ($userInfo['status'] == 'inactive') ? "selected='selected'" : "" ?>><?php echo _("Inactive"); ?></option>
                                                  </select>
                                             </div>
                                        </div>
                                   </div>
                              </div>

                              <div class="row" style=<?php echo $display; ?>>
                                   <div class="col-md-12">
                                        <a href="javascript:void(0);" id="showFilter" class="btn btn-primary"><?php echo _("Show Advanced Search Options"); ?></a>
                                        <a href="javascript:void(0);" style="display:none;" id="hideFilter" class="btn btn-danger"><?php echo _("Hide Advanced Search Options"); ?></a>
                                   </div>
                                   <div id="facilityFilter" style="display:none;">
                                        <h4 style="padding:36px 0px 0px 14px;"> <?php echo _("Filter Facilities by Province & Districts"); ?></h4>
                                        <div class="col-md-4">
                                             <div class="form-group">
                                                  <label for="province" class="col-lg-4 control-label"><?php echo _("Province"); ?> </label>
                                                  <div class="col-lg-7">
                                                       <select class="form-control " name="province" id="province" title="<?php echo _('Please choose province'); ?>" style="width:100%;" onchange="getProvinceDistricts();">
                                                            <?php echo $province; ?>
                                                       </select>
                                                  </div>
                                             </div>
                                        </div>
                                        <div class="col-md-4">
                                             <div class="form-group">
                                                  <label for="province" class="col-lg-4 control-label"><?php echo _("District"); ?> </label>
                                                  <div class="col-lg-7">
                                                       <select class="form-control " name="district" id="district" title="<?php echo _('Please choose district'); ?>" style="width:100%;" onchange="getFacilities();">
                                                            <option value=""> <?php echo _("-- Select --"); ?> </option>
                                                       </select>
                                                  </div>
                                             </div>
                                        </div>
                                        <div class="col-md-4">
                                             <div class="form-group">
                                                  <label for="province" class="col-lg-4 control-label"><?php echo _("Facility Type"); ?> </label>
                                                  <div class="col-lg-7">
                                                       <select class="form-control" id="facilityType" name="facilityType" title="<?php echo _('Please select facility type'); ?>" onchange="getFacility()">
                                                            <option value=""> <?php echo _("-- Select --"); ?> </option>
                                                            <?php
                                                            foreach ($ftResult as $type) {
                                                            ?>
                                                                 <option value="<?php echo $type['facility_type_id']; ?>"><?php echo ucwords($type['facility_type_name']); ?></option>
                                                            <?php
                                                            }
                                                            ?>
                                                       </select>
                                                  </div>
                                             </div>
                                        </div>
                                   </div>
                              </div>
                              <div class="row" style="margin: 15px; <?php echo $display; ?>">
                                   <h4 style=" margin-left: 15px;"> <?php echo _("Facility User Map Details"); ?></h4>
                                   <div class="col-xs-5">
                                        <select name="from[]" id="search" class="form-control" size="8" multiple="multiple">
                                             <?php
                                             if ($fResult > 0) {
                                                  foreach ($fResult as $fName) {
                                             ?>
                                                       <option value="<?php echo $fName['facility_id']; ?>"><?php echo ucwords($fName['facility_name']); ?></option>
                                             <?php
                                                  }
                                             }
                                             ?>
                                        </select>
                                   </div>

                                   <div class="col-xs-2">
                                        <button type="button" id="search_rightAll" class="btn btn-block"><i class="glyphicon glyphicon-forward"></i></button>
                                        <button type="button" id="search_rightSelected" class="btn btn-block"><i class="glyphicon glyphicon-chevron-right"></i></button>
                                        <button type="button" id="search_leftSelected" class="btn btn-block"><i class="glyphicon glyphicon-chevron-left"></i></button>
                                        <button type="button" id="search_leftAll" class="btn btn-block"><i class="glyphicon glyphicon-backward"></i></button>
                                   </div>

                                   <div class="col-xs-5">
                                        <select name="to[]" id="search_to" class="form-control" size="8" multiple="multiple">
                                             <?php
                                             foreach ($selectedResult as $uName) {
                                             ?>
                                                  <option value="<?php echo $uName['facility_id']; ?>" selected="selected"><?php echo ucwords($uName['facility_name']); ?></option>
                                             <?php
                                             }
                                             ?>
                                        </select>
                                   </div>
                              </div>
                         </div>

                         <!-- /.box-body -->
                         <div class="box-footer">
                              <input type="hidden" name="selectedFacility" id="selectedFacility" />
                              <input type="hidden" name="removedSignatureImage" id="removedSignatureImage" />
                              <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _("Submit"); ?></a>
                              <a href="users.php" class="btn btn-default"> <?php echo _("Cancel"); ?></a>
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
<script type="text/javascript" src="/assets/js/multiselect.min.js"></script>
<script type="text/javascript" src="/assets/js/jasny-bootstrap.js"></script>
<script type="text/javascript">
     function clearUserSignature(img) {
          $(".userSignature").fileinput("clear");
          $("#clearUserSignature").addClass("hide");
          $("#removedSignatureImage").val(img);
     }

     function getNewSignatureImage(img) {
          $("#clearUserSignature").addClass("hide");
          $("#removedSignatureImage").val(img);
     }


     jQuery(document).ready(function($) {
          $('#search').multiselect({
               search: {
                    left: '<input type="text" name="q" class="form-control" placeholder="<?php echo _("Search"); ?>..." />',
                    right: '<input type="text" name="q" class="form-control" placeholder="<?php echo _("Search"); ?>..." />',
               },
               fireSearch: function(value) {
                    return value.length > 3;
               }
          });
          $("#showFilter").click(function() {
               $("#showFilter").hide();
               $("#facilityFilter,#hideFilter").fadeIn();
          });

          $("#hideFilter").click(function() {
               $("#facilityFilter,#hideFilter").hide();
               $("#showFilter").fadeIn();
          });

          $('#role').change(function(e) {
               var selectedText = $(this).find("option:selected").attr('data-code');
               if (selectedText == "API") {
                    $('.show-token').show();
                    $('#authToken').addClass('isRequired');
               } else {
                    $('.show-token').hide();
                    $('#authToken').removeClass('isRequired');
               }
          });

          $('#userSignature').change(function(e) {
               const file = this.files[0];
               const fileType = file['type'];
               const validImageTypes = ['image/jpg', 'image/jpeg', 'image/png'];
               if (!validImageTypes.includes(fileType)) {
                    $('#userSignature').val('');
                    alert('<?php echo _("Invalid file type. Please upload correct image format like JPG or JPEG or PNG"); ?>');
                    return false;
               }
          });
     });
     pwdflag = true;

     function validateNow() {
          var selVal = [];
          $('#search_to option').each(function(i, selected) {
               selVal[i] = $(selected).val();
          });
          $("#selectedFacility").val(selVal);

          flag = deforayValidator.init({
               formId: 'userEditForm'
          });

          if (flag) {
               if ($('.ppwd').val() != '') {
                    pwdflag = checkPasswordLength();
               }
               if (pwdflag) {
                    $.blockUI();
                    document.getElementById('userEditForm').submit();
               }
          }
     }

     function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
          var removeDots = obj.value.replace(/\,/g, "");
          //str=obj.value;
          removeDots = removeDots.replace(/\s{2,}/g, ' ');
          $.post("/includes/checkDuplicate.php", {
                    tableName: tableName,
                    fieldName: fieldName,
                    value: removeDots.trim(),
                    fnct: fnct,
                    format: "html"
               },
               function(data) {
                    if (data === '1') {
                         alert(alrt);
                         document.getElementById(obj.id).value = "";
                    }
               });
     }

     function checkPasswordLength() {
          var pwd = $('#confirmPassword').val();
          var regex = /^(?=.*[0-9])(?=.*[a-zA-Z])([a-zA-Z0-9!@#\$%\^\&*\)\(+=. _-]+){8,}$/;
          if (regex.test(pwd) == false) {
               alert("<?= _("Password must be at least 8 characters long and must include AT LEAST one number, one alphabet and may have special characters.") ?>");
               $('.ppwd').focus();
          }
          return regex.test(pwd);
     }

     function getProvinceDistricts() {
          $.blockUI();
          var pName = $("#province").val();
          if (pName != '') {
               $.post("/includes/siteInformationDropdownOptions.php", {
                         pName: pName,
                         fType: $("#facilityType").val(),
                         comingFromUser: 'yes'
                    },
                    function(data) {
                         if (data != "") {
                              details = data.split("###");
                              $("#district").html(details[1]);
                              $("#search").html(details[0]);
                         }
                    });
          }
          $.unblockUI();
     }

     function getFacilities() {
          $.blockUI();
          var dName = $("#district").val();
          if (dName != '') {
               $.post("/includes/siteInformationDropdownOptions.php", {
                         dName: dName,
                         fType: $("#facilityType").val(),
                         comingFromUser: 'yes'
                    },
                    function(data) {
                         if (data != "") {
                              details = data.split("###");
                              $("#search").html(details[0]);
                         }
                    });
          }
          $.unblockUI();
     }

     function getFacility() {
          $.blockUI();
          var pName = $("#province").val();
          var dName = $("#district").val();
          var fType = $("#facilityType").val();
          if (dName != '') {
               getFacilities();
          } else if (pName != '') {
               getProvinceDistricts();
          } else if (fType != '') {
               $.post("/includes/siteInformationDropdownOptions.php", {
                         fType: fType,
                         comingFromUser: 'yes'
                    },
                    function(data) {
                         $("#search").html(data);
                    });
          }
          $.unblockUI();
     }

     function generateToken(id) {
          $.post("/includes/generate-auth-token.php", {
                    size: 32
               },
               function(data) {
                    if (data != "") {
                         $("#" + id).val(data);
                    }
               });
     }
</script>
<?php
include APPLICATION_PATH . '/footer.php';
?>