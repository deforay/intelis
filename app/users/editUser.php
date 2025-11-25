<?php

use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);

$title = _translate("Edit User");

require_once APPLICATION_PATH . '/header.php';
// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());
$id = (isset($_GET['id'])) ? base64_decode((string) $_GET['id']) : null;


$userInfo = $db->rawQueryOne(
     'SELECT ud.*, r.role_id, r.role_name, r.role_code
          FROM user_details as ud LEFT JOIN roles as r ON ud.role_id=r.role_id
          WHERE user_id= ?',
     [$id]
);

$interfaceUsers = "";
if (!empty($userInfo['interface_user_name'])) {
     $interfaceUsers = implode(", ", json_decode((string) $userInfo['interface_user_name'], true));
}

$query = "SELECT * FROM roles WHERE status='active'";
$result = $db->rawQuery($query);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

$signatureImagePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature";

$activeFacilities = [];
$display = 'display:none';
$facilityMap = '';
$preselectedFacilities = [];
if ($general->isSTSInstance()) {

     $facilityMap = $facilitiesService->getUserFacilityMap($id);
     $preselectedFacilities = explode(",", (string) $facilityMap);


     $fResult = [];
     $fQuery = "SELECT facility_name,facility_id
                    FROM facility_details
                    WHERE status='active'
                    ORDER BY facility_name ASC";
     $fResult = $db->rawQuery($fQuery);


     foreach ($fResult as $ft) {
          if (in_array($ft['facility_id'], $preselectedFacilities)) {
               $activeFacilities[$ft['facility_id']] = $ft['facility_name'];
          } else {
               $activeFacilitiesNotAdded[$ft['facility_id']] = $ft['facility_name'];
          }
     }


     $display = 'display:block';
}

$geoLocationParentArray = $geolocationService->fetchActiveGeolocations();

?>

<link href="/assets/css/jasny-bootstrap.min.css" rel="stylesheet" />

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
     <!-- Content Header (Page header) -->
     <section class="content-header">
          <h1> <em class="fa-solid fa-user"></em> <?php echo _translate("Edit User"); ?></h1>
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
                    <form class="form-horizontal" method='post' name='userEditForm' id='userEditForm' autocomplete="off"
                         action="editUserHelper.php" enctype="multipart/form-data">
                         <div class="box-body">
                              <div class="row">
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="userName"
                                                  class="col-lg-4 control-label"><?php echo _translate("User Name"); ?>
                                                  <span class="mandatory">*</span></label>
                                             <div class="col-lg-7">
                                                  <input type="text" class="form-control isRequired" id="userName"
                                                       name="userName"
                                                       placeholder="<?php echo _translate('User Name'); ?>"
                                                       title="<?php echo _translate('Please enter user name'); ?>"
                                                       value="<?php echo $userInfo['user_name']; ?>" />
                                                  <input type="hidden" name="userId" id="userId"
                                                       value="<?php echo base64_encode((string) $userInfo['user_id']); ?>" />
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
                                                       value="<?php echo $userInfo['email']; ?>"
                                                       onblur="checkNameValidation('user_details','email',this,'<?php echo "user_id##" . $userInfo['user_id']; ?>','<?php echo _translate("This email id that you entered already exists.Try another email id"); ?>',null)" />
                                             </div>
                                        </div>
                                   </div>
                              </div>
                              <div class="row">
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="phoneNo"
                                                  class="col-lg-4 control-label"><?php echo _translate("Phone Number"); ?></label>
                                             <div class="col-lg-7">
                                                  <input type="text" class="form-control phone-number" id="phoneNo"
                                                       name="phoneNo"
                                                       placeholder="<?php echo _translate('Phone Number'); ?>"
                                                       title="<?php echo _translate('Please enter phone number'); ?>"
                                                       value="<?php echo $userInfo['phone_number']; ?>" />
                                             </div>
                                        </div>
                                   </div>

                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="role"
                                                  class="col-lg-4 control-label"><?php echo _translate("Role"); ?> <span
                                                       class="mandatory">*</span></label>
                                             <div class="col-lg-7">
                                                  <select class="form-control isRequired" name='role' id='role'
                                                       title="<?= _translate("Please select this user's role"); ?>">
                                                       <option value=""><?= _translate("--Select--"); ?></option>
                                                       <?php foreach ($result as $row) {
                                                            ?>
                                                            <option value="<?= $row['role_id']; ?>"
                                                                 data-code="<?= $row['role_code']; ?>" <?php echo (isset($userInfo['role_id']) && $userInfo['role_id'] == $row['role_id']) ? "selected='selected'" : ""; ?>>
                                                                 <?= $row['role_name']; ?>
                                                            </option>
                                                       <?php } ?>
                                                  </select>
                                             </div>
                                        </div>
                                   </div>
                              </div>


                              <div class="row show-token"
                                   style="display: <?php echo (!empty($userInfo['api_token']) || 'API' == ($userInfo['role_code'])) ? 'block' : 'none'; ?>;">
                                   <div class="col-md-12 col-lg-12">
                                        <div class="form-group">
                                             <label for="authToken"
                                                  class="col-lg-2 control-label"><?php echo _translate("AuthToken"); ?>
                                                  <span class="mandatory">*</span></label>
                                             <div class="col-lg-9">


                                                  <div class="input-group">
                                                       <input type="text" value="<?php echo $userInfo['api_token']; ?>"
                                                            class="form-control" id="authToken" name="authToken"
                                                            placeholder="<?php echo _translate('Auth Token'); ?>"
                                                            title="<?= _translate('Please generate the auth token'); ?>"
                                                            readonly style="; background-color: #fff;">
                                                       <span class="input-group-btn">
                                                            <button class="btn btn-default" type="button"
                                                                 onclick="generateToken('authToken');"
                                                                 title="Generate Another Token">
                                                                 <i class="fa fa-random"></i>
                                                                 <?= _translate("Generate Another Token"); ?>
                                                            </button>

                                                       </span>
                                                  </div>

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
                                                  <input type="text" value="<?= $interfaceUsers ?>" class="form-control"
                                                       id="interfaceUserName" name="interfaceUserName"
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
                                                       <option value="yes" <?php echo ($userInfo['app_access'] == 'yes') ? "selected='selected'" : "" ?>>
                                                            <?= _translate("Yes"); ?>
                                                       </option>
                                                       <option value="no" <?php echo ($userInfo['app_access'] == 'no') ? "selected='selected'" : "" ?>>
                                                            <?= _translate("No"); ?>
                                                       </option>
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
                                                       <div class="fileinput-preview thumbnail image-placeholder"
                                                            data-trigger="fileinput" style="width:200px; height:150px;">
                                                            <?php

                                                            if (isset($userInfo['user_signature']) && trim((string) $userInfo['user_signature']) !== '' && MiscUtility::isImageValid($signatureImagePath . DIRECTORY_SEPARATOR . $userInfo['user_signature'])) {
                                                                 $signFileName = basename((string) $userInfo['user_signature']);
                                                                 ?>
                                                                 <img src="/uploads/users-signature/<?php echo $signFileName; ?>"
                                                                      alt="Signature image">
                                                            <?php } else { ?>

                                                            <?php } ?>
                                                       </div>
                                                       <div>
                                                            <span class="btn btn-default btn-file"><span
                                                                      class="fileinput-new"><?php echo _translate("Select Signature Image"); ?></span><span
                                                                      class="fileinput-exists"><?php echo _translate("Change"); ?></span>
                                                                 <input type="file" id="userSignature"
                                                                      name="userSignature"
                                                                      accept="image/png,image/gpg,image/jpeg"
                                                                      title="<?php echo _translate('Please select user signature'); ?>"
                                                                      onchange="getNewSignatureImage('<?php echo $userInfo['user_signature']; ?>');">
                                                            </span>
                                                            <?php
                                                            if (isset($userInfo['user_signature']) && trim((string) $userInfo['user_signature']) !== '' && file_exists($userInfo['user_signature'])) {
                                                                 ?>
                                                                 <a id="clearUserSignature" href="javascript:void(0);"
                                                                      class="btn btn-default" data-dismiss="fileupload"
                                                                      onclick="clearUserSignature('<?php echo $userInfo['user_signature']; ?>')"><?php echo _translate("Clear"); ?></a>
                                                            <?php } ?>
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
                                                  class="col-lg-4 control-label"><?php echo _translate("Login Id"); ?>
                                                  <span class="mandatory">*</span></label>
                                             <div class="col-lg-7">
                                                  <input type="text" class="form-control isRequired" id="loginId"
                                                       name="loginId"
                                                       placeholder="<?php echo _translate('Login Id'); ?>"
                                                       title="<?php echo _translate('Please enter login id'); ?>"
                                                       value="<?php echo $userInfo['login_id']; ?>"
                                                       onblur="checkNameValidation('user_details','login_id',this,'<?php echo "user_id##" . $userInfo['user_id']; ?>','<?php echo _translate("This login id that you entered already exists.Try another login id"); ?>',null)" />
                                                  <small class="form-text text-muted">
                                                       <?php echo _translate("Only lowercase letters, numbers, hyphens (-), and underscores (_) are allowed. No spaces."); ?>
                                                  </small>
                                             </div>
                                        </div>
                                   </div>

                              </div>



                              <div class=" row">
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="password"
                                                  class="col-lg-4 control-label"><?php echo _translate("Password"); ?>
                                             </label>
                                             <div class="col-lg-7">
                                                  <div class="input-group">
                                                       <input type="password" class="form-control ppwd" id="password"
                                                            name="password"
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
                                                  class="col-lg-4 control-label"><?php echo _translate("Confirm Password"); ?></label>
                                             <div class="col-lg-7">
                                                  <input type="password" class="form-control cpwd confirmPassword"
                                                       id="confirmPassword" name="password"
                                                       placeholder="<?php echo _translate('Confirm Password'); ?>"
                                                       title="" />
                                             </div>
                                        </div>
                                   </div>



                                   <div class="row"></div>
                                   <div class="col-md-6">
                                        <div class="form-group">
                                             <label for="status"
                                                  class="col-lg-4 control-label"><?php echo _translate("User Status"); ?>
                                                  <span class="mandatory">*</span></label>
                                             <div class="col-lg-7">
                                                  <select class="form-control isRequired" name='status' id='status'
                                                       title="<?php echo _translate('Please select the status'); ?>">
                                                       <option value=""> <?php echo _translate("-- Select --"); ?>
                                                       </option>
                                                       <option value="active" <?php echo ($userInfo['status'] == 'active') ? "selected='selected'" : "" ?>>
                                                            <?php echo _translate("Active"); ?>
                                                       </option>
                                                       <option value="inactive" <?php echo ($userInfo['status'] == 'inactive') ? "selected='selected'" : "" ?>><?php echo _translate("Inactive"); ?></option>
                                                  </select>
                                             </div>
                                        </div>
                                   </div>
                              </div>


                              <div class="row" style="margin: 15px;<?php echo $display; ?>">
                                   <div class="col-md-12">
                                        <button type="button" class="btn btn-primary btn-sm pull-left"
                                             style="margin-right:5px;line-height: 2;"
                                             onclick="hideAdvanceSearch();"><span>Show Advanced Search
                                                  Options</span></button>
                                   </div>
                              </div>
                              <div class="row" style="margin: 15px;<?php echo $display; ?>">
                                   <div class="col-md-12">
                                        <table aria-describedby="table" id="advanceFilter" class="table"
                                             aria-hidden="true" style="display: none;">
                                             <tr>
                                                  <td><strong>
                                                            <?php echo _translate("Province/State"); ?>&nbsp;:
                                                       </strong></td>
                                                  <td>
                                                       <?php if (count($geoLocationParentArray) > 0) { ?>
                                                            <select name="stateId" id="stateId" class="form-control"
                                                                 title="<?php echo _translate('Please choose province/state'); ?>">
                                                                 <?= $general->generateSelectOptions($geoLocationParentArray, null, _translate("-- Select --")); ?>
                                                            </select>
                                                       <?php } ?>
                                                  </td>
                                                  <td><strong>
                                                            <?php echo _translate("District/County"); ?>&nbsp;:
                                                       </strong></td>
                                                  <td>
                                                       <select name="districtId" id="districtId" class="form-control"
                                                            title="<?php echo _translate('Please choose District/County'); ?>">
                                                            <option value="">
                                                                 <?php echo _translate("-- Select --"); ?>
                                                            </option>
                                                       </select>
                                                  </td>
                                                  <td>
                                                       <input type="button" name="filter" id="filter"
                                                            onclick="getFacilitiesToMap();" value="Search"
                                                            class="btn btn-primary btn-sm" />
                                                       <button class="btn btn-danger btn-sm" type="button"
                                                            onclick="document.location.href = document.location"><span>
                                                                 <?= _translate('Reset'); ?>
                                                            </span></button>
                                                  </td>
                                             </tr>
                                        </table>
                                   </div>
                              </div>
                              <div class="row" style="margin: 15px;<?php echo $display; ?>">

                                   <div class="col-md-12">
                                        <h4 style="font-weight:bold;">
                                             <?php echo _translate("Map User to Selected Facilities (optional)"); ?>
                                        </h4>

                                        <div class="col-md-5">
                                             <select name="mappedFacilities[]" id="search" class="form-control" size="8"
                                                  multiple="multiple">
                                             </select>
                                             <div class="sampleCounterDiv">
                                                  <?= _translate("Number of unselected facilities"); ?> : <span
                                                       id="unselectedCount"></span>
                                             </div>
                                        </div>

                                        <div class="col-md-2">
                                             <button type="button" id="search_rightAll" class="btn btn-block"><em
                                                       class="fa-solid fa-forward"></em></button>
                                             <button type="button" id="search_rightSelected" class="btn btn-block"><em
                                                       class="fa-sharp fa-solid fa-chevron-right"></em></button>
                                             <button type="button" id="search_leftSelected" class="btn btn-block"><em
                                                       class="fa-sharp fa-solid fa-chevron-left"></em></button>
                                             <button type="button" id="search_leftAll" class="btn btn-block"><em
                                                       class="fa-solid fa-backward"></em></button>
                                        </div>

                                        <div class="col-md-5">
                                             <select name="to[]" id="search_to" class="form-control" size="8"
                                                  multiple="multiple">
                                                  <?php foreach ($preselectedFacilities as $value) {
                                                       if ($activeFacilities !== [] && array_key_exists($value, $activeFacilities)) { ?>
                                                            <option value="<?php echo $value; ?>">
                                                                 <?php echo $activeFacilities[$value]; ?>
                                                            </option>
                                                       <?php }
                                                  } ?>
                                             </select>
                                             <div class="sampleCounterDiv">
                                                  <?= _translate("Number of selected facilities"); ?> : <span
                                                       id="selectedCount"></span>
                                             </div>
                                        </div>
                                   </div>
                              </div>
                         </div>

                         <!-- /.box-body -->
                         <div class="box-footer">
                              <input type="hidden" name="selectedFacility" id="selectedFacility" />
                              <input type="hidden" name="removedSignatureImage" id="removedSignatureImage" />
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
     function clearUserSignature(img) {
          $(".userSignature").fileinput("clear");
          $("#clearUserSignature").addClass("hide");
          $("#removedSignatureImage").val(img);
     }

     function getNewSignatureImage(img) {
          $("#clearUserSignature").addClass("hide");
          $("#removedSignatureImage").val(img);
     }


     jQuery(document).ready(function ($) {

          $('#loginId').on('input', function () {
               let val = $(this).val();
               val = val.toLowerCase(); // convert to lowercase
               val = val.replace(/\s+/g, ''); // remove all spaces
               val = val.replace(/[^a-z0-9_-]/g, ''); // allow only a-z, 0-9, _ and -
               $(this).val(val); // set sanitized value back
          });


          <?php if ($general->isSTSInstance()) { ?>
               getFacilitiesToMap();
          <?php } ?>

          $('#search').deforayDualBox({
               search: {
                    left: '<input type="text" name="q" class="form-control" placeholder="<?php echo _translate("Search"); ?>..." />',
                    right: '<input type="text" name="q" class="form-control" placeholder="<?php echo _translate("Search"); ?>..." />'
               },
               fireSearch: function (value) {
                    return value.length > 2;
               },
               autoSelectNext: true,
               keepRenderingSort: true
          });

          $("#showFilter").click(function () {
               $("#showFilter").hide();
               $("#facilityFilter,#hideFilter").fadeIn();
          });

          $("#hideFilter").click(function () {
               $("#facilityFilter,#hideFilter").hide();
               $("#showFilter").fadeIn();
          });

          $('#role').change(function (e) {
               var selectedText = $(this).find("option:selected").attr('data-code');
               if (selectedText == "API") {
                    $('.show-token').show();
                    $('#authToken').addClass('isRequired');
               } else {
                    $('.show-token').hide();
                    $('#authToken').removeClass('isRequired');
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

          $("#stateId").select2({
               placeholder: '<?php echo _translate("Select Province", true); ?>',
               allowClear: true,
               width: '100%'
          });

          $("#districtId").select2({
               placeholder: '<?php echo _translate("Select District", true); ?>',
               allowClear: true,
               width: '100%'
          });

          $("#stateId").change(function () {
               if ($(this).val() == 'other') {
                    $('#provinceNew').show();
               } else {
                    $('#provinceNew').hide();
                    $('#state').val($("#stateId option:selected").text());
               }
               $.blockUI();
               var pName = $(this).val();
               if ($.trim(pName) != '') {
                    $.post("/includes/siteInformationDropdownOptions.php", {
                         pName: pName,
                    },
                         function (data) {
                              if (data != "") {
                                   details = data.split("###");
                                   $("#districtId").html(details[1]);
                                   $("#districtId").append('<option value="other"><?php echo _translate("Other"); ?></option>');
                              }
                         });
               }
               $.unblockUI();
          });
     });
     pwdflag = true;

     function hideAdvanceSearch() {
          $('#advanceFilter').toggle();
     }

     function getFacilitiesToMap() {
          $.blockUI({
               message: '<h3><?= _translate("Trying to get mapped facilities", true); ?> <br><?php echo _translate("Please wait", true); ?>...</h3>'
          });
          $.post("getFacilitiesHelper.php", {
               provinceId: $('#stateId').val(),
               districtId: $('#districtId').val(),
               selectedFacility: '<?php echo $facilityMap; ?>'
          },
               function (toAppend) {
                    if (toAppend != "" && toAppend != null && toAppend != undefined) {
                         $('#search').html(toAppend)
                         setTimeout(function () {
                              $("#search_rightSelected").trigger('click');
                         }, 10);
                         var count = $('#search option').length;
                         $("#unselectedCount").html(count);

                    } else {
                         $('#search').html("");
                         //alert("<?= _translate("No facilities found for the selected facility type. Please add a new facility or edit an existing facility.", true); ?>");
                         $("#unselectedCount").html(0);
                    }
                    $.unblockUI();
               });
     }

     function validateNow() {
          $("#search").val(""); // THIS IS IMPORTANT. TO REDUCE NUMBER OF PHP VARIABLES
          var selVal = [];
          $('#search_to option').each(function (i, selected) {
               selVal[i] = $(selected).val();
          });
          const sqids = new Sqids()
          $("#selectedFacility").val(sqids.encode(selVal));

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

     function getProvinceDistricts() {
          $.blockUI();
          var pName = $("#province").val();
          if (pName != '') {
               $.post("/includes/siteInformationDropdownOptions.php", {
                    pName: pName,
                    fType: $("#facilityType").val(),
                    comingFromUser: 'yes'
               },
                    function (data) {
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
                    function (data) {
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
                    function (data) {
                         $("#search").html(data);
                    });
          }
          $.unblockUI();
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
include APPLICATION_PATH . '/footer.php';
