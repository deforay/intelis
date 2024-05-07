<?php

namespace App\Utilities;

use App\Utilities\MiscUtility;
use Gregwar\Captcha\CaptchaBuilder;

final class CaptchaUtility
{

    public function getCaptcha(): void
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        $phrase = MiscUtility::generateRandomString(4);

        $builder = new CaptchaBuilder($phrase);
        $builder->setDistortion(false);
        $builder->build(200, 100);


        $_SESSION['captchaCode'] = $phrase; //$builder->getPhrase();


        header('Content-type: image/jpeg');
        $builder->output();
    }
}
