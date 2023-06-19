<?php

namespace App\Interfaces;

interface TestServiceInterface
{
    public function generateSampleCode($params);
    public function insertSample($params, $returnSampleData = false);
}
