<?php 
use Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator;

$generator = new ComputerPasswordGenerator();

$generator
  ->setUppercase(true)
  ->setLowercase(true)
  ->setNumbers(true)
  ->setSymbols(true)
  ->setLength(10);

$password = $generator->generatePasswords(10);
echo $password[0];