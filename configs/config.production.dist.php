<?php

$systemConfig = array();

// System Locale
$systemConfig['locale'] = 'en_US';

// VLSTS URL
$systemConfig['remoteURL'] = '';

// Enable/Disable Modules
// true => Enabled
// false => Disabled
$systemConfig['modules']['vl'] = true;
$systemConfig['modules']['eid'] = true;
$systemConfig['modules']['covid19'] = true;
$systemConfig['modules']['hepatitis'] = false;
$systemConfig['modules']['tb'] = false;

$systemConfig['instanceName'] = '';


// Database Settings
$systemConfig['dbHost']     = '';
$systemConfig['dbUser']     = '';
$systemConfig['dbPassword'] = '';
$systemConfig['dbName']     = 'vlsm';
$systemConfig['dbPort']     = 3306;
$systemConfig['dbCharset'] = 'utf8mb4';


$systemConfig['passwordSalt'] = 'PUT-A-RANDOM-STRING-HERE';
$systemConfig['tryCrypt'] = 'PUT-A-RANDOM-STRING-HERE';


//Please use only GMAIL ID AND PASSWORD
$systemConfig['adminEmailUserName'] = '';
$systemConfig['adminEmailPassword'] = '';

// Windows : Change it to the mysqldump.exe path for your computer for eg.
// $systemConfig['mysqlDump'] = 'C:\wamp\mysql\bin\mysqldump.exe';

// Linux : default for Ubuntu 20.04+, may be different for your distribution
$systemConfig['mysqlDump'] = '/usr/bin/mysqldump';



$systemConfig['interfacing'] = array();


// Enable/Disable Interfacing
// true => Enabled
// false => Disabled
$systemConfig['interfacing']['enabled'] = false;

// Interfacing Database Details (not needed if above feature set to false)
$systemConfig['interfacing']['dbHost'] = '';
$systemConfig['interfacing']['dbUser'] = '';
$systemConfig['interfacing']['dbPassword'] = '';
$systemConfig['interfacing']['dbName'] = 'interfacing';
$systemConfig['interfacing']['dbPort'] = 3306;
$systemConfig['interfacing']['dbCharset'] = 'utf8mb4';

$systemConfig['recency'] = array();

// Domain URL of the Recency Web Application
$systemConfig['recency']['url'] = '';



// Enable/Disable Recency Viral Load tests sync 
// true => Enabled
// false => Disabled
$systemConfig['recency']['vlsync'] = false;


// Enable/Disable Cross Login with Recency
// true => Enabled
// false => Disabled
$systemConfig['recency']['crosslogin'] = false;

// This Salt should match the Salt on Recency Web app 
$systemConfig['recency']['crossloginSalt'] = "PUT-A-RANDOM-STRING-HERE";

return $systemConfig;