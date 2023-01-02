<?php 
use Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator;

$generator = new ComputerPasswordGenerator();

$generator
  ->setUppercase()
  ->setLowercase()
  ->setNumbers()
  ->setSymbols(false)
  ->setLength(8);

$password = $generator->generatePasswords(8);
echo $password[0];