<?php

namespace App\Utilities;

use App\Utilities\MiscUtility;
use Gregwar\Captcha\PhraseBuilder;
use Gregwar\Captcha\CaptchaBuilder;

final class CaptchaUtility
{

    public function getCaptcha(): void
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        $phrase = null;
        //if it is development environment, then let us keep it simple
        if (APPLICATION_ENV === "development") {
            $phrase = "zaq";
        }
        $phraseBuilder = new PhraseBuilder(4, '0123456789');
        $builder = new CaptchaBuilder($phrase, $phraseBuilder);

        $builder = new CaptchaBuilder($phrase);
        $builder->setDistortion(false);
        $builder->build(200, 100);


        $_SESSION['captchaCode'] = $phrase; //$builder->getPhrase();


        header('Content-type: image/jpeg');
        $builder->output();
    }
}
