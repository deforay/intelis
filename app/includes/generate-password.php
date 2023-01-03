<?php 
use Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator;

$generator = new ComputerPasswordGenerator();

$generator
  ->setOptionValue(ComputerPasswordGenerator::OPTION_UPPER_CASE, true)
  ->setOptionValue(ComputerPasswordGenerator::OPTION_LOWER_CASE, true)
  ->setOptionValue(ComputerPasswordGenerator::OPTION_NUMBERS, true)
  ->setOptionValue(ComputerPasswordGenerator::OPTION_SYMBOLS, false)
;

$password = $generator->generatePassword();
echo $password;