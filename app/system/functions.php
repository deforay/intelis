<?php

use App\Services\SystemService;


function _translate($text)
{
    return SystemService::translate($text);
}
