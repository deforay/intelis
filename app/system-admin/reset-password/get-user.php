<?php

use App\Services\UsersService;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$userId = $_GET['userId'];

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$userInfo = $usersService->getUserByID($userId);

?>
<?php if (!empty($userInfo) && $userInfo != null) { ?>
  <form class="form-horizontal" method='post' name='resetPasswordForm' id='resetPasswordForm' autocomplete="off"
    action="resetPasswordProcess.php" style="margin-top:20px;">
    <input type="hidden" name="userId" id="userId" value="<?php echo base64_encode((string) $userInfo['user_id']); ?>" />
    <div class="row">
      <div class="col-md-6">
        <div class="form-group">
          <label for="password" class="col-lg-4 control-label"><?php echo _translate("Password"); ?> <span
              class="mandatory">*</span></label>
          <div class="col-lg-7">
            <input type="password" class="form-control ppwd isRequired" id="password" name="password"
              placeholder="<?php echo _translate('Password'); ?>"
              title="<?php echo _translate('Please enter the password'); ?>" />
            <button type="button" id="generatePassword" onclick="passwordType();"
              class="btn btn-default"><strong><?= _translate("Generate Password"); ?></strong></button><br>
            <code><?= _translate("Password must be at least 8 characters long and must include AT LEAST one number, one alphabet and may have special characters.") ?></code>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="form-group">
          <label for="confirmPassword" class="col-lg-4 control-label"><?php echo _translate("Confirm Password"); ?> <span
              class="mandatory">*</span></label>
          <div class="col-lg-7">
            <input type="password" class="form-control cpwd confirmPassword" id="confirmPassword" name="password"
              placeholder="<?php echo _translate('Confirm Password'); ?>" title="" />
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-6">
        <div class="form-group">
          <label for="status" class="col-lg-4 control-label"><?php echo _translate("Status"); ?> <span
              class="mandatory">*</span></label>
          <div class="col-lg-7">
            <select class="form-control isRequired" name='status' id='status'
              title="<?php echo _translate('Please select the status'); ?>">
              <option value=""><?php echo _translate("-- Select --"); ?></option>
              <option value="active" <?php echo ($userInfo['status'] == 'active') ? "selected='selected'" : "" ?>>
                <?php echo _translate("Active"); ?></option>
              <option value="inactive" <?php echo ($userInfo['status'] == 'inactive') ? "selected='selected'" : "" ?>>
                <?php echo _translate("Inactive"); ?></option>
            </select>
          </div>
        </div>
      </div>
    </div>
    <div class="box-footer">
      <a class="btn btn-primary" href="javascript:void(0);"
        onclick="validateNow();return false;"><?php echo _translate("Submit"); ?></a>
      <a href="/system-admin/reset-password/reset-password.php" class="btn btn-default">
        <?php echo _translate("Reload"); ?></a>
    </div>
    <!-- /.box-footer -->
  </form>
<?php } ?>