<?php

namespace App\Interfaces;

interface TestInterface
{
    public function generateSampleCode($params);
    public function insertSample($params, $returnSampleData = false);
}
