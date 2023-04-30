<?php

use App\Utilities\CaptchaUtility;

$captcha = new CaptchaUtility();
echo $captcha->getCaptcha();
