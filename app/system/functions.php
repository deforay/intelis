<?php

use App\Services\SystemService;


function _translate($text)
{
    SystemService::translate($text);
}
