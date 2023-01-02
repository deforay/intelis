<?php 
use Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator;

$generator = new ComputerPasswordGenerator();

$generator
  ->setUppercase(true)
  ->setLowercase(true)
  ->setNumbers(true)
  ->setSymbols()
  ->setLength(8);

$password = $generator->generatePasswords(8);
echo $password[0];