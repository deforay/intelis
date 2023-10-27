<?php

namespace App\Utilities;

class ValidationUtility
{
    public static function validateMandatoryFields($fields)
    {
        foreach ($fields as $field) {
            if (empty(trim($field))) {
                return false;
            }
        }
        return true;
    }
}
