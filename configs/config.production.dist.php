<?php
// This is a template file
require_once(APPLICATION_PATH . '/system/system.php');


$systemConfig = array();

// System Locale
$systemConfig['locale'] = 'en_US';


// Enable/Disable Modules
// true => Enabled
// false => Disabled
$systemConfig['modules']['vl'] = true;
$systemConfig['modules']['eid'] = true;
$systemConfig['modules']['covid19'] = false;
$systemConfig['modules']['hepatitis'] = false;
$systemConfig['modules']['tb'] = false;

$systemConfig['instanceName'] = '';


// Database Settings
$systemConfig['dbHost']     = '';
$systemConfig['dbUser']     = '';
$systemConfig['dbPassword'] = '';
$systemConfig['dbName']     = '';
$systemConfig['dbPort']     = 3306;
$systemConfig['dbCharset'] = 'utf8mb4';


$systemConfig['passwordSalt'] = 'PUT-A-RANDOM-STRING-HERE';
$systemConfig['tryCrypt'] = 'XTOTESTTHECRYTPD';


//Please use only GMAIL ID AND PASSWORD
$systemConfig['adminEmailUserName'] = '';
$systemConfig['adminEmailPassword'] = '';

// If using WAMP default settings, then uncomment the following 2 lines
//$systemConfig['databasePassword'] = '';
//$systemConfig['databasePortNumber'] = 3306;

// Portable Uniform Server : following is the path in the portable Uniform Server
//$systemConfig['mysqlDump'] = __DIR__.'\..\..\core\mysql\bin\mysqldump.exe';

// Windows : Change it to the mysqldump.exe path for your computer for eg.
// $systemConfig['mysqlDump'] = 'C:\wamp\mysql\bin\mysqldump.exe';

// Linux : default for Ubuntu 16.04, may be different for your distribution
$systemConfig['mysqlDump'] = '/usr/bin/mysqldump';


// VLSTS URL
$systemConfig['remoteURL'] = '';


$interfaceConfig = array();
// Enable/Disable Interfacing
// true => Enabled
// false => Disabled
$interfaceConfig['enabled'] = false;

// Interfacing Database Details (not needed if above feature set to false)
$interfaceConfig['dbHost'] = '';
$interfaceConfig['dbUser'] = '';
$interfaceConfig['dbPassword'] = '';
$interfaceConfig['dbName'] = '';
$interfaceConfig['dbPort'] = 3306;
$interfaceConfig['dbCharset'] = 'utf8mb4';

$recencyConfig = array();

// Domain URL of the Recency Web Application
$recencyConfig['url'] = '';



// Enable/Disable Recency Viral Load tests sync 
// true => Enabled
// false => Disabled
$recencyConfig['vlsync'] = false;


// Enable/Disable Cross Login with Recency
// true => Enabled
// false => Disabled
$recencyConfig['crosslogin'] = false;

// This Salt should match the Salt on Recency Web app 
$recencyConfig['crossloginSalt'] = "PUT-A-RANDOM-STRING-HERE";
