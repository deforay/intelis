<?php

/**
 * General functions
 *
 * @author Thana
 */

namespace App\Utilities;

use Exception;

class Captcha
{

    public function getCaptcha($config = array())
    {
        ob_start();
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        if (!function_exists('gd_info')) {
            throw new Exception('Required GD library is missing');
        }

        // Default values
        $captchaConfig = array(
            'code' => '',
            'min_length' => 4,
            'max_length' => 5,
            'png_backgrounds' => array(
                WEB_ROOT . '/assets/img/captchabg/default.png',
                WEB_ROOT . '/assets/img/captchabg/ravenna.png'
            ),
            'fonts' => array(WEB_ROOT . '/assets/fonts/Idolwild/idolwild.ttf'),
            'characters' => '012345678901234567890123456789',
            'min_font_size' => 22,
            'max_font_size' => 26,
            'color' => '#111',
            'angleMin' => 0,
            'angleMax' => 10,
            'shadow' => true,
            'shadow_color' => '#bbb',
            'shadow_offset_x' => -2,
            'shadow_offset_y' => 1
        );

        // Overwrite defaults with custom config values
        if (is_array($config)) {
            foreach ($config as $key => $value) {
                $captchaConfig[$key] = $value;
            }
        }

        // Restrict certain values
        if ($captchaConfig['min_length'] < 1) {
            $captchaConfig['min_length'] = 1;
        }
        if ($captchaConfig['angleMin'] < 0) {
            $captchaConfig['angleMin'] = 0;
        }
        if ($captchaConfig['angleMax'] > 10) {
            $captchaConfig['angleMax'] = 10;
        }
        if ($captchaConfig['angleMax'] < $captchaConfig['angleMin']) {
            $captchaConfig['angleMax'] = $captchaConfig['angleMin'];
        }
        if ($captchaConfig['min_font_size'] < 10) {
            $captchaConfig['min_font_size'] = 10;
        }
        if ($captchaConfig['max_font_size'] < $captchaConfig['min_font_size']) {
            $captchaConfig['max_font_size'] = $captchaConfig['min_font_size'];
        }

        // Use milliseconds instead of seconds
        //srand((float) microtime() * 1000);

        // if it is development environment, then let us keep it simple
        // if(APPLICATION_ENV == "development"){
        //     $captchaConfig['code'] = "zaq";
        // }

        $seedLength = strlen($captchaConfig['characters']);

        $captchaConfig['code'] = '';
        $length = random_int($captchaConfig['min_length'], $captchaConfig['max_length']);
        while (strlen($captchaConfig['code']) < $length) {
            $captchaConfig['code'] .= substr(
                $captchaConfig['characters'],
                random_int(0, PHP_INT_MAX) % ($seedLength),
                1
            );
        }
        //$captchaConfig['code'] = str_shuffle(str_shuffle($captchaConfig['code']));


        // Generate image src
        // $imageSrc = substr(__FILE__, strlen($_SERVER['DOCUMENT_ROOT'])) . '?captcha&amp;t=' . urlencode(microtime());
        // $imageSrc = '/' . ltrim(preg_replace('/\\\\/', '/', $imageSrc), '/');

        $_SESSION['captchaCode'] = $captchaConfig['code'];

        if (!function_exists('hex2rgb')) {

            function hex2rgb($hexStr, $returnString = false, $separator = ',')
            {
                $hexStr = preg_replace("/[^0-9A-Fa-f]/", '', $hexStr); // Gets a proper hex string
                $rgbArray = array();
                if (strlen($hexStr) == 6) {
                    $colorVal = hexdec($hexStr);
                    $rgbArray['r'] = 0xFF & ($colorVal >> 0x10);
                    $rgbArray['g'] = 0xFF & ($colorVal >> 0x8);
                    $rgbArray['b'] = 0xFF & $colorVal;
                } elseif (strlen($hexStr) == 3) {
                    $rgbArray['r'] = hexdec(str_repeat(substr($hexStr, 0, 1), 2));
                    $rgbArray['g'] = hexdec(str_repeat(substr($hexStr, 1, 1), 2));
                    $rgbArray['b'] = hexdec(str_repeat(substr($hexStr, 2, 1), 2));
                } else {
                    return false;
                }
                return $returnString ? implode($separator, $rgbArray) : $rgbArray;
            }
        }

        srand((float) microtime() * 1000);

        // Pick random background, get info, and start captcha
        $background = $captchaConfig['png_backgrounds'][random_int(0, count($captchaConfig['png_backgrounds']) - 1)];
        list($bgWidth, $bgHeight, $bgType, $bgAttr) = getimagesize($background);
        // Create captcha object
        $captcha = imagecreatefrompng($background);
        imagealphablending($captcha, true);
        imagesavealpha($captcha, true);

        $color = hex2rgb($captchaConfig['color']);
        $color = imagecolorallocate($captcha, $color['r'], $color['g'], $color['b']);

        // Determine text angle
        $angle = random_int($captchaConfig['angleMin'], $captchaConfig['angleMax']) * (random_int(0, 1) == 1 ? -1 : 1);

        // Select font randomly
        $font = $captchaConfig['fonts'][random_int(0, count($captchaConfig['fonts']) - 1)];

        // Verify font file exists
        if (!file_exists($font)) {
            throw new Exception('Font file not found: ' . $font);
        }


        //Set the font size.
        $fontSize = random_int($captchaConfig['min_font_size'], $captchaConfig['max_font_size']);
        $textBoxSize = imagettfbbox($fontSize, $angle, $font, $captchaConfig['code']);

        // Determine text position
        $boxWidth = abs($textBoxSize[6] - $textBoxSize[2]);
        $boxHeight = abs($textBoxSize[5] - $textBoxSize[1]);
        $textPosXMin = 0;
        $textPosXMax = ($bgWidth) - ($boxWidth);
        $textPosX = random_int($textPosXMin, $textPosXMax);
        $textPosYMin = $boxHeight;
        $textPosYMax = ($bgHeight) - ($boxHeight / 2);
        $textPosY = random_int($textPosYMin, $textPosYMax);

        // Draw shadow
        if ($captchaConfig['shadow']) {
            $shadowColor = hex2rgb($captchaConfig['shadow_color']);
            $shadowColor = imagecolorallocate($captcha, $shadowColor['r'], $shadowColor['g'], $shadowColor['b']);
            imagettftext($captcha, $fontSize, $angle, $textPosX + $captchaConfig['shadow_offset_x'], $textPosY + $captchaConfig['shadow_offset_y'], $shadowColor, $font, $captchaConfig['code']);
        }

        // Draw text
        imagettftext($captcha, $fontSize, $angle, $textPosX, $textPosY, $color, $font, $captchaConfig['code']);

        // Output image
        header("Content-type: image/png");
        imagepng($captcha);
    }
}
