<?php


require_once APPLICATION_PATH . '/header.php';
$query = "SELECT * FROM roles where status='active' GROUP BY role_code";
$result = $db->rawQuery($query);


$activeFacilities = [];
$display = 'display:none';
if ($_SESSION['instanceType'] == 'remoteuser') {
     $fResult = [];
     $fQuery = "SELECT facility_name,facility_id
                    FROM facility_details
                    WHERE status='active'
                    ORDER BY facility_name ASC";
     $fResult = $db->rawQuery($fQuery);

     foreach ($fResult as $ft) {
          $activeFacilities[] = array(
               'id' => $ft['facility_id'],
               'text' => $ft['facility_name']
          );
     }

     $display = 'display:block';
}
//province Stratt
$rKey = '';
$pdQuery = "SELECT * from geographical_divisions WHERE geo_parent = 0";
$pdResult = $db->query($pdQuery);
$province = "<option value=''> -- Select -- </option>";
foreach ($pdResult as $provinceName) {
     $province .= "<option value='" . $provinceName['geo_name'] . "##" . $provinceName['geo_code'] . "'>" . ($provinceName['geo_name']) . "</option>";
}

// $facility = '';
// $facility.="<option data-code='' data-emails='' data-mobile-nos='' data-contact-person='' value=''> -- Select -- </option>";
//province end
$ftResult = [];

$fQuery = "SELECT * FROM facility_type where facility_type_id IN(1,2)";
$ftResult = $db->rawQuery($fQuery);

?>
<link href="/assets/css/jasny-bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
     <!-- Content Header (Page header) -->
     <section class="content-header">
          <h1><em class="fa-solid fa-user"></em> <?php echo _("Add User"); ?></h1>
          <ol class="breadcrumb">
               <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
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
                    <form class="form-horizontal" method='post' name='userForm' id='userForm' autocomplete="off" action="addUserHelper.php" enctype="multipart/form-data">
                         <div class="box-body">
                              <div class="row">
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="userName" class="col-lg-4 control-label"><?php echo _("Full Name"); ?> <span class="mandatory">*</span></label>
                                             <div class="col-lg-7">
                                                  <input type="text" class="form-control isRequired" id="userName" name="userName" placeholder="<?php echo _('Full Name'); ?>" title="<?php echo _('Please enter user name'); ?>" />
                                             </div>
                                        </div>
                                   </div>
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="email" class="col-lg-4 control-label"><?php echo _("Email"); ?> </label>
                                             <div class="col-lg-7">
                                                  <input type="text" class="form-control" id="email" name="email" placeholder="<?php echo _('Email'); ?>" title="<?php echo _('Please enter email'); ?>" onblur='checkNameValidation("user_details","email",this,null,"<?php echo _("This email id that you entered already exists.Try another email id"); ?>",null)' />
                                             </div>
                                        </div>
                                   </div>
                              </div>
                              <div class="row">
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="phoneNo" class="col-lg-4 control-label"><?php echo _("Phone Number"); ?> </label>
                                             <div class="col-lg-7">
                                                  <input type="text" class="form-control" id="phoneNo" name="phoneNo" placeholder="<?php echo _('Phone Number'); ?>" title="<?php echo _('Please enter phone number'); ?>" />
                                             </div>
                                        </div>
                                   </div>
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="role" class="col-lg-4 control-label"><?php echo _("Role"); ?> <span class="mandatory">*</span></label>
                                             <div class="col-lg-7">
                                                  <select class="form-control isRequired" name='role' id='role' title="<?php echo _('Please select the role'); ?>">
                                                       <option value=""><?php echo _("--Select--"); ?></option>
                                                       <?php foreach ($result as $row) { ?>
                                                            <option value="<?php echo $row['role_id']; ?>" data-code="<?php echo $row['role_code']; ?>"><?php echo (($row['role_name'])); ?></option>
                                                       <?php } ?>
                                                  </select>
                                             </div>
                                        </div>
                                   </div>
                              </div>
                              <div class="row show-token" style="display: none;">
                                   <div class="col-md-12 col-lg-12">
                                        <div class="form-group">
                                             <label for="authToken" class="col-lg-2 control-label"><?php echo _("AuthToken"); ?> <span class="mandatory">*</span></label>
                                             <div class="col-lg-9">
                                                  <input type="text" class="form-control" id="authToken" name="authToken" placeholder="<?php echo _('Auth Token'); ?>" title="<?php echo _('Please Generate the auth token'); ?>" readonly>
                                                  <a style="display:block; margin-top:1em; width:30%;" href="javascript:void(0);" class="btn btn-sm btn-primary" onclick="generateToken('authToken');"><?php echo _("Generate Another Token"); ?></a>
                                             </div>
                                        </div>
                                   </div>
                              </div>
                              <div class="row">
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="interfaceUserName" class="col-lg-4 control-label"><?php echo _("Interface User Name (from your Molecular testing machine)"); ?></label>
                                             <div class="col-lg-7">
                                                  <input type="text" class="form-control" id="interfaceUserName" name="interfaceUserName" placeholder="<?php echo _('Interface User Name'); ?>" title="<?php echo _('Please enter interface user name'); ?>" />
                                             </div>
                                        </div>
                                   </div>
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="appAccessable" class="col-lg-4 control-label"><?php echo _("Mobile App Access"); ?></label>
                                             <div class="col-lg-7">
                                                  <select class="form-control" name='appAccessable' id='appAccessable' title="<?php echo _('Please select the mobile App access or not'); ?>?">
                                                       <option value=""><?php echo _("--Select--"); ?></option>
                                                       <option value="yes"><?php echo _("Yes"); ?></option>
                                                       <option value="no"><?php echo _("No"); ?></option>
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
                                                       <div class="fileinput-preview thumbnail" data-trigger="fileinput" style="width:200px; height:150px;">



                                                       </div>
                                                       <div>
                                                            <span class="btn btn-default btn-file"><span class="fileinput-new"><?php echo _("Select Signature Image"); ?></span><span class="fileinput-exists"><?php echo _("Change"); ?></span>
                                                                 <input type="file" id="userSignature" name="userSignature" title="<?php echo _('Please select user signature'); ?>" onchange="">
                                                            </span>

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
                                             <label for="loginId" class="col-lg-4 control-label"><?php echo _("Login ID"); ?> <span class="mandatory">*</span></label>
                                             <div class="col-lg-7">
                                                  <input type="text" class="form-control isRequired" id="loginId" name="loginId" placeholder="<?php echo _('Login Id'); ?>" title="<?php echo _('Please enter login id'); ?>" onblur='checkNameValidation("user_details","login_id",this,null,"<?php echo _("This login id that you entered already exists.Try another login id"); ?>",null)' />
                                             </div>
                                        </div>
                                   </div>
                              </div>
                              <div class="row">
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="password" class="col-lg-4 control-label"><?php echo _("Password"); ?> <span class="mandatory">*</span></label>
                                             <div class="col-lg-7">
                                                  <input type="password" class="form-control ppwd isRequired" id="password" name="password" placeholder="<?php echo _('Password'); ?>" title="<?php echo _('Please enter the password'); ?>" maxlength="16" /><br>
                                                  <button type="button" id="generatePassword" onclick="passwordType();" class="btn btn-default"><strong>Generate Random Password</strong></button><br>
                                                  <code><?= _("Password must be at least 12 characters long and must include AT LEAST one number, one alphabet and may have special characters.") ?></code>
                                             </div>
                                        </div>
                                   </div>
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="confirmPassword" class="col-lg-4 control-label"><?php echo _("Confirm Password"); ?> <span class="mandatory">*</span></label>
                                             <div class="col-lg-7">
                                                  <input type="password" class="form-control cpwd isRequired confirmPassword" id="confirmPassword" name="password" placeholder="<?php echo _('Confirm Password'); ?>" title="" maxlength="16" />
                                             </div>
                                        </div>
                                   </div>
                              </div>
                              <!-- <div class="row" style=<?php echo $display; ?>>
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
                                                            <?php foreach ($ftResult as $type) { ?>
                                                                 <option value="<?php echo $type['facility_type_id']; ?>"><?php echo ($type['facility_type_name']); ?></option>
                                                            <?php } ?>
                                                       </select>
                                                  </div>
                                             </div>
                                        </div>
                                   </div>
                              </div> -->

                              <div class="row" style="margin: 15px;<?php echo $display; ?>">

                                   <div class="col-md-12">
                                        <h4 style="font-weight:bold;"> <?php echo _("Map User to Selected Facilities (optional)"); ?></h4>
                                        <input id="mappedFacilities" style="width:100%;" placeholder="Type facility name" />
                                   </div>
                              </div>

                         </div>


                         <!-- /.box-body -->
                         <div class="box-footer">
                              <input type="hidden" name="selectedFacility" id="selectedFacility" />
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
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

<script type="text/javascript">
     provinceName = true;
     facilityName = true;

     jQuery(document).ready(function($) {

          $('#mappedFacilities').select2({
               data: <?= json_encode($activeFacilities) ?>,
               placeholder: 'Type facility name',
               multiple: true,
               // query with pagination
               query: function(q) {
                    var pageSize,
                         results,
                         that = this;
                    pageSize = 20; // or whatever pagesize
                    results = [];
                    if (q.term && q.term !== '') {
                         // HEADS UP; for the _.filter function i use underscore (actually lo-dash) here
                         results = _.filter(that.data, function(e) {
                              return e.text.toUpperCase().indexOf(q.term.toUpperCase()) >= 0;
                         });
                    } else if (q.term === '') {
                         results = that.data;
                    }
                    q.callback({
                         results: results.slice((q.page - 1) * pageSize, q.page * pageSize),
                         more: results.length >= q.page * pageSize,
                    });
               },
          });

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
               $('#authToken').val('');
               var selectedText = $(this).find("option:selected").attr('data-code');
               if (selectedText == "API") {
                    $('.show-token').show();
                    $('#authToken').addClass('isRequired');
                    generateToken('authToken');
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

     function validateNow() {

          let mappedFacilities = ($('#mappedFacilities').select2('data'));
          let selVal = [];
          $(mappedFacilities).each(
               function(index, value) {
                    selVal[index] = (value.id);
               }
          );

          $("#selectedFacility").val(selVal);

          flag = deforayValidator.init({
               formId: 'userForm'
          });
          if (flag) {
               pwdflag = checkPasswordLength();
               if (pwdflag) {
                    $.blockUI();
                    document.getElementById('userForm').submit();
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
          var regex = /^(?=.*[0-9])(?=.*[a-zA-Z])([a-zA-Z0-9!@#\$%\^\&*\)\(+=. _-]+){12,}$/;
          if (regex.test(pwd) == false) {
               alert("<?= _("Password must be at least 12 characters long and must include AT LEAST one number, one alphabet and may have special characters.") ?>");
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
          }
          if (pName != '') {
               getProvinceDistricts();
          }
          if (fType != '') {
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
                    size: 6
               },
               function(data) {
                    if (data != "") {
                         $("#" + id).val(data);
                    }
               });
     }

     function passwordType() {
          document.getElementById('password').type = "text";
          document.getElementById('confirmPassword').type = "text";
          $.post("/includes/generate-password.php", {
                    size: 32
               },
               function(data) {
                    // alert(data);
                    $("#password").val(data);
                    $("#confirmPassword").val(data);
                    var cpy = copyToClipboard(document.getElementById("confirmPassword"));
                    if (cpy == true) {
                         // alert("Password copied to clipboard!");
                         Toastify({
                              text: "Random password generated and copied to clipboard",
                              duration: 3000,
                         }).showToast();
                    }
               });
     }

     function copyToClipboard(elem) {
          // create hidden text element, if it doesn't already exist
          var targetId = "_hiddenCopyText_";
          var isInput = elem.tagName === "INPUT" || elem.tagName === "TEXTAREA";
          var origSelectionStart, origSelectionEnd;
          if (isInput) {
               // can just use the original source element for the selection and copy
               target = elem;
               origSelectionStart = elem.selectionStart;
               origSelectionEnd = elem.selectionEnd;
          } else {
               // must use a temporary form element for the selection and copy
               target = document.getElementById(targetId);
               if (!target) {
                    var target = document.createElement("textarea");
                    target.style.position = "absolute";
                    target.style.left = "-9999px";
                    target.style.top = "0";
                    target.id = targetId;
                    document.body.appendChild(target);
               }
               target.textContent = elem.textContent;
          }
          // select the content
          var currentFocus = document.activeElement;
          target.focus();
          target.setSelectionRange(0, target.value.length);

          // copy the selection
          var succeed;
          try {
               succeed = document.execCommand("copy");
          } catch (e) {
               succeed = false;
          }
          // restore original focus
          if (currentFocus && typeof currentFocus.focus === "function") {
               currentFocus.focus();
          }

          if (isInput) {
               // restore prior selection
               elem.setSelectionRange(origSelectionStart, origSelectionEnd);
          } else {
               // clear temporary content
               target.textContent = "";
          }
          return succeed;
     }
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
