<?php

namespace App\Utilities;

use App\Utilities\MiscUtility;
use Gregwar\Captcha\CaptchaBuilder;

final class CaptchaUtility
{

    public function getCaptcha(): void
    {
        $phrase = null;
        //if it is development environment, then let us keep it simple
        $phrase = APPLICATION_ENV == "development" ? "zaq" : MiscUtility::generateRandomNumber(4);

        $builder = new CaptchaBuilder($phrase);
        $builder->setDistortion(false);
        $builder->build(200, 70);


        $_SESSION['captchaCode'] = $phrase ?? $builder->getPhrase();


        header('Content-type: image/jpeg');
        $builder->output();
    }
}
