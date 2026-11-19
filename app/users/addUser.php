<?php

use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;


require_once APPLICATION_PATH . '/header.php';

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

// Cloud-LIS non-admin operators may only create non-admin, non-API testing-lab
// users (so they can never mint an admin or escalate). The save helper re-checks
// this server-side; the dropdown filter is just the matching UI. No-op otherwise.
$roleScope = $general->isCloudLisNonAdmin()
     ? " AND access_type='testing-lab' AND role_id != 1 AND (role_code IS NULL OR role_code != 'API') "
     : "";
$query = "SELECT * FROM roles where status='active' $roleScope GROUP BY role_code";
$result = $db->rawQuery($query);

// Per-user testing-lab assignment is an STS-only concept: a testing-lab user on
// STS (cloud-LIS) operates as exactly one lab. On LIS/standalone the lab is the
// install's sc_testing_lab_id (forced server-side), so the field is not shown.
$testingLabs = [];
if ($general->isSTSInstance()) {
     $testingLabs = $facilitiesService->getTestingLabs();
     // A restricted cloud-LIS operator may only assign users to their OWN lab, so
     // collapse the dropdown to that single lab (keyed by facility_id). Fail closed:
     // no resolved lab -> empty dropdown, never all labs.
     if ($general->isCloudLisNonAdmin()) {
          $testingLabs = array_intersect_key($testingLabs, [(int) ($_SESSION['labId'] ?? 0) => 1]);
     }
}



?>
<link href="/assets/css/jasny-bootstrap.min.css" rel="stylesheet" />

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
     <!-- Content Header (Page header) -->
     <section class="content-header">
          <h1><em class="fa-solid fa-user"></em> <?php echo _translate("Add User"); ?></h1>
          <ol class="breadcrumb">
               <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
               <li class="active"><?php echo _translate("Users"); ?></li>
          </ol>
     </section>

     <!-- Main content -->
     <section class="content">

          <div class="box box-default">
               <div class="box-header with-border">
                    <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span>
                         <?php echo _translate("indicates required fields"); ?> &nbsp;</div>
               </div>
               <!-- /.box-header -->
               <div class="box-body">
                    <!-- form start -->
                    <form class="form-horizontal" method='post' name='userForm' id='userForm' autocomplete="off"
                         action="addUserHelper.php" enctype="multipart/form-data">
                         <div class="box-body">
                              <div class="row">
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="userName"
                                                  class="col-lg-4 control-label"><?php echo _translate("Full Name"); ?>
                                                  <span class="mandatory">*</span></label>
                                             <div class="col-lg-7">
                                                  <input type="text" class="form-control isRequired" id="userName"
                                                       name="userName"
                                                       placeholder="<?php echo _translate('Full Name'); ?>"
                                                       title="<?php echo _htmlTranslate('Your Full Name'); ?>"
                                                       onblur='checkNameValidation("user_details","user_name",this,null,"<?php echo _translate("A user with this full name already exists. Please use a different name."); ?>",null)' />
                                             </div>
                                        </div>
                                   </div>
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="email"
                                                  class="col-lg-4 control-label"><?php echo _translate("Email"); ?>
                                             </label>
                                             <div class="col-lg-7">
                                                  <input type="text" class="form-control" id="email" name="email"
                                                       placeholder="<?php echo _translate('Email'); ?>"
                                                       title="<?php echo _translate('Please enter email'); ?>"
                                                       onblur='checkNameValidation("user_details","email",this,null,"<?php echo _translate("This email id that you entered already exists.Try another email id"); ?>",null)' />
                                             </div>
                                        </div>
                                   </div>
                              </div>
                              <div class="row">
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="phoneNo"
                                                  class="col-lg-4 control-label"><?php echo _translate("Phone Number"); ?>
                                             </label>
                                             <div class="col-lg-7">
                                                  <input type="text" class="form-control phone-number" id="phoneNo"
                                                       name="phoneNo"
                                                       placeholder="<?php echo _translate('Phone Number'); ?>"
                                                       title="<?php echo _translate('Please enter phone number'); ?>" />
                                             </div>
                                        </div>
                                   </div>
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="role" class="col-lg-4 control-label"><?= _translate("Role"); ?>
                                                  <span class="mandatory">*</span></label>
                                             <div class="col-lg-7">
                                                  <select class="form-control isRequired" name='role' id='role'
                                                       title="<?= _translate("Please select this user's role"); ?>">
                                                       <option value=""><?= _translate("--Select--"); ?></option>
                                                       <?php foreach ($result as $row) { ?>
                                                            <option value="<?= $row['role_id']; ?>"
                                                                 data-code="<?= $row['role_code']; ?>"
                                                                 data-access-type="<?= $row['access_type'] ?? ''; ?>">
                                                                 <?= $row['role_name']; ?>
                                                            </option>
                                                       <?php } ?>
                                                  </select>
                                             </div>
                                        </div>
                                   </div>
                              </div>
                              <?php if ($general->isSTSInstance()): ?>
                                   <div class="row testing-lab-row" id="testingLabRow" style="display: none;">
                                        <div class="col-md-6">
                                             <div class="form-group">
                                                  <label for="testingLabId"
                                                       class="col-lg-4 control-label"><?= _translate("Testing Lab"); ?>
                                                       <span class="mandatory">*</span></label>
                                                  <div class="col-lg-7">
                                                       <select class="form-control" name="testingLabId" id="testingLabId"
                                                            title="<?= _translate("Select the testing lab this user operates as"); ?>">
                                                            <?= $general->generateSelectOptions($testingLabs, null, _translate("-- Select --")); ?>
                                                       </select>
                                                  </div>
                                             </div>
                                        </div>
                                   </div>
                              <?php endif; ?>
                              <div class="row show-token" style="display: none;">
                                   <div class="col-md-12 col-lg-12">
                                        <div class="form-group">
                                             <label for="authToken"
                                                  class="col-lg-2 control-label"><?= _translate("AuthToken"); ?>
                                                  <span class="mandatory">*</span></label>
                                             <div class="col-lg-9">
                                                  <input type="text" class="form-control" id="authToken"
                                                       name="authToken" placeholder="<?= _translate('Auth Token'); ?>"
                                                       title="<?= _translate('Please generate the auth token'); ?>"
                                                       readonly>
                                                  <a style="display:block; margin-top:1em; width:30%;"
                                                       href="javascript:void(0);" class="btn btn-sm btn-primary"
                                                       onclick="generateToken('authToken');"><?= _translate("Generate Another Token"); ?></a>
                                             </div>
                                        </div>
                                   </div>
                              </div>
                              <div class="row">
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="interfaceUserName"
                                                  class="col-lg-4 control-label"><?= _translate("Interface User Name (from your Molecular testing machine)"); ?></label>
                                             <div class="col-lg-7">
                                                  <input type="text" class="form-control" id="interfaceUserName"
                                                       name="interfaceUserName"
                                                       placeholder="<?= _translate('Interface User Name'); ?>"
                                                       title="<?= _translate('Please enter interface user name'); ?>" />
                                             </div>
                                        </div>
                                   </div>
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="appAccessable"
                                                  class="col-lg-4 control-label"><?= _translate("Mobile App Access"); ?></label>
                                             <div class="col-lg-7">
                                                  <select class="form-control" name='appAccessable' id='appAccessable'
                                                       title="<?= _translate('Does this user have Mobile app access?'); ?>">
                                                       <option value=""><?= _translate("--Select--"); ?></option>
                                                       <option value="yes"><?= _translate("Yes"); ?></option>
                                                       <option value="no"><?= _translate("No"); ?></option>
                                                  </select>
                                             </div>
                                        </div>
                                   </div>
                              </div>

                              <div class="row">
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for=""
                                                  class="col-lg-4 control-label"><?php echo _translate("Signature"); ?>
                                                  <br><?php echo _translate("(Used to embed in Result PDF)"); ?></label>
                                             <div class="col-lg-8">
                                                  <div class="fileinput fileinput-new userSignature"
                                                       data-provides="fileinput">
                                                       <div class="fileinput-preview thumbnail" data-trigger="fileinput"
                                                            style="width:200px; height:150px;">



                                                       </div>
                                                       <div>
                                                            <span class="btn btn-default btn-file"><span
                                                                      class="fileinput-new"><?php echo _translate("Select Signature Image"); ?></span><span
                                                                      class="fileinput-exists"><?php echo _translate("Change"); ?></span>
                                                                 <input type="file" id="userSignature"
                                                                      name="userSignature"
                                                                      title="<?php echo _translate('Please select user signature'); ?>"
                                                                      onchange="">
                                                            </span>

                                                            <a href="#" class="btn btn-default fileinput-exists"
                                                                 data-dismiss="fileinput"><?php echo _translate("Remove"); ?></a>
                                                       </div>
                                                  </div>
                                                  <div class="box-body">
                                                       <?php echo _translate("Image Size"); ?> :
                                                       <code>100px x 100px</code>
                                                  </div>
                                             </div>
                                        </div>
                                   </div>
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="loginId"
                                                  class="col-lg-4 control-label"><?php echo _translate("Login ID"); ?>
                                                  <span class="mandatory">*</span></label>
                                             <div class="col-lg-7">
                                                  <input type="text" class="form-control isRequired" id="loginId"
                                                       name="loginId"
                                                       placeholder="<?php echo _translate('Login ID'); ?>"
                                                       title="<?php echo _translate('Please enter login id'); ?>"
                                                       onblur='checkNameValidation("user_details","login_id",this,null,"<?php echo _translate("This login id that you entered already exists.Try another login id"); ?>",null)' />
                                                  <small class="form-text text-muted">
                                                       <?php echo _translate("Only lowercase letters, numbers, hyphens (-), and underscores (_) are allowed. No spaces."); ?>
                                                  </small>
                                             </div>

                                        </div>
                                   </div>
                              </div>
                              <div class="row">
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="password"
                                                  class="col-lg-4 control-label"><?php echo _translate("Password"); ?>
                                                  <span class="mandatory">*</span></label>
                                             <div class="col-lg-7">
                                                  <div class="input-group">
                                                       <input type="password" class="form-control ppwd isRequired"
                                                            id="password" name="password"
                                                            placeholder="<?php echo _translate('Password'); ?>"
                                                            title="<?php echo _translate('Please enter the password'); ?>" />
                                                       <span class="input-group-btn">
                                                            <button class="btn btn-default" type="button"
                                                                 id="generatePassword" onclick="passwordType();"
                                                                 title="Generate Password">
                                                                 <i class="fa fa-random"></i>
                                                                 <?= _translate("Generate"); ?>
                                                            </button>

                                                       </span>
                                                  </div>
                                                  <small class="form-text text-muted">
                                                       <?= _translate("Password must be at least 8 characters long and must include AT LEAST one number, one alphabet and may have special characters.") ?>
                                                  </small>
                                             </div>
                                        </div>
                                   </div>
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="confirmPassword"
                                                  class="col-lg-4 control-label"><?php echo _translate("Confirm Password"); ?>
                                                  <span class="mandatory">*</span></label>
                                             <div class="col-lg-7">
                                                  <input type="password"
                                                       class="form-control cpwd isRequired confirmPassword"
                                                       id="confirmPassword" name="password"
                                                       placeholder="<?php echo _translate('Confirm Password'); ?>"
                                                       title="" />
                                             </div>
                                        </div>
                                   </div>
                              </div>
                         </div>

                         <!-- /.box-body -->
                         <div class="box-footer">
                              <a class="btn btn-primary" href="javascript:void(0);"
                                   onclick="validateNow();return false;"><?php echo _translate("Submit"); ?></a>
                              <a href="users.php" class="btn btn-default"> <?php echo _translate("Cancel"); ?></a>
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

<script type="text/javascript" src="/assets/js/jasny-bootstrap.js"></script>

<script type="text/javascript">
     provinceName = true;
     facilityName = true;

     function normalizeLoginId(value) {
          value = value.toLowerCase();
          value = value.replace(/\s+/g, '');
          return value.replace(/[^a-z0-9_-]/g, '');
     }

     jQuery(document).ready(function ($) {

          $('#loginId').on('input', function () {
               $(this).val(normalizeLoginId($(this).val()));
          });



          $('#role').change(function (e) {
               $('#authToken').val('');
               var selectedOption = $(this).find("option:selected");
               var selectedText = selectedOption.attr('data-code');
               if (selectedText == "API") {
                    $('.show-token').show();
                    $('#authToken').addClass('isRequired');
                    generateToken('authToken');
               } else {
                    $('.show-token').hide();
                    $('#authToken').removeClass('isRequired');
               }

               // Testing Lab applies only to testing-lab roles (STS / cloud-LIS).
               var accessType = selectedOption.attr('data-access-type');
               if (accessType === 'testing-lab') {
                    $('#testingLabRow').show();
                    $('#testingLabId').addClass('isRequired');
               } else {
                    $('#testingLabRow').hide();
                    $('#testingLabId').removeClass('isRequired').val('').trigger('change');
               }
          });

          $('#userSignature').change(function (e) {
               const file = this.files[0];
               const fileType = file['type'];
               const validImageTypes = ['image/jpg', 'image/jpeg', 'image/png'];
               if (!validImageTypes.includes(fileType)) {
                    $('#userSignature').val('');
                    alert("<?= _translate("Invalid file type. Please upload correct image format like JPG or JPEG or PNG", true); ?>");
                    return false;
               }
          });

          $("#role").select2({
               placeholder: '<?php echo _translate("Select Role", true); ?>',
               allowClear: true,
               width: '100%'
          });

          $("#testingLabId").select2({
               placeholder: '<?php echo _translate("Select Testing Lab", true); ?>',
               allowClear: true,
               width: '100%'
          });

     });

     function validateNow() {
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
               function (data) {
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
               alert("<?= _translate("Password must be at least 8 characters long and must include AT LEAST one number, one alphabet and may have special characters.") ?>");
               $('.ppwd').focus();
          }
          return regex.test(pwd);
     }

     function generateToken(id) {
          $.post("/includes/generate-auth-token.php", {
               s: 8
          },
               function (data) {
                    if (data != "") {
                         $("#" + id).val(data);
                    }
               });
     }

     async function passwordType() {
          document.getElementById('password').type = "text";
          document.getElementById('confirmPassword').type = "text";
          const data = await $.post("/includes/generate-password.php", {
               size: 32
          });
          $("#password").val(data);
          $("#confirmPassword").val(data);
          try {
               const success = await Utilities.copyToClipboard(data);
               if (success) {
                    toast.success("<?= _translate("Password generated and copied to clipboard", true); ?>");
               } else {
                    console.log('Failed to copy text');
               }
          } catch (error) {
               console.log(error);
          }
     }
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
