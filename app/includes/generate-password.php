<?php

use Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator;
use Hackzilla\PasswordGenerator\Generator\RequirementPasswordGenerator;

$generator = new RequirementPasswordGenerator();

$generator
  ->setLength(12)
  ->setOptionValue(ComputerPasswordGenerator::OPTION_UPPER_CASE, true)
  ->setOptionValue(ComputerPasswordGenerator::OPTION_LOWER_CASE, true)
  ->setOptionValue(ComputerPasswordGenerator::OPTION_NUMBERS, true)
  ->setOptionValue(ComputerPasswordGenerator::OPTION_SYMBOLS, false)
  ->setMinimumCount(ComputerPasswordGenerator::OPTION_UPPER_CASE, 2)
  ->setMinimumCount(ComputerPasswordGenerator::OPTION_LOWER_CASE, 2)
  ->setMinimumCount(ComputerPasswordGenerator::OPTION_NUMBERS, 2)
;

$password = $generator->generatePassword();
echo $password;