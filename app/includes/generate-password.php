<?php

use Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator;
use Hackzilla\PasswordGenerator\Generator\RequirementPasswordGenerator;

$generator = new RequirementPasswordGenerator();

$generator
  ->setLength(16)
  ->setOptionValue(ComputerPasswordGenerator::OPTION_UPPER_CASE, true)
  ->setOptionValue(ComputerPasswordGenerator::OPTION_LOWER_CASE, true)
  ->setOptionValue(ComputerPasswordGenerator::OPTION_NUMBERS, true)
  ->setOptionValue(ComputerPasswordGenerator::OPTION_SYMBOLS, false)
  ->setMinimumCount(ComputerPasswordGenerator::OPTION_UPPER_CASE, 2)
  ->setMinimumCount(ComputerPasswordGenerator::OPTION_LOWER_CASE, 2)
  ->setMinimumCount(ComputerPasswordGenerator::OPTION_NUMBERS, 4);

echo $generator->generatePassword();
