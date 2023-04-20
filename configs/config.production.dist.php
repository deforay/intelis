<?php

$systemConfig = [];

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
$systemConfig['database']['host']       = '';
$systemConfig['database']['username']   = '';
$systemConfig['database']['password']   = '';
$systemConfig['database']['db']         = 'vlsm';
$systemConfig['database']['port']       = 3306;
$systemConfig['database']['charset']    = 'utf8mb4';

$systemConfig['passwordSalt'] = 'PUT-A-RANDOM-STRING-HERE';
$systemConfig['tryCrypt'] = 'PUT-A-RANDOM-STRING-HERE';


//Please use only GMAIL ID AND PASSWORD
$systemConfig['adminEmailUserName'] = '';
$systemConfig['adminEmailPassword'] = '';

// Windows : Change it to the mysqldump.exe path for your computer for eg.
// $systemConfig['mysqlDump'] = 'C:\wamp\mysql\bin\mysqldump.exe';

// Linux : default for Ubuntu 20.04+, may be different for your distribution
$systemConfig['mysqlDump'] = '/usr/bin/mysqldump';


// SFTP Settings for automated backups (optional, but recommended)
$systemConfig['sftp']['host'] = ''; // eg. 'sftp.example.com'
$systemConfig['sftp']['port'] = '22'; // usually 22
$systemConfig['sftp']['path'] = ''; // eg. '/home/username/backups/labname'
$systemConfig['sftp']['username'] = ''; // eg. 'username'
$systemConfig['sftp']['privateKey'] = ''; // path to the private key file (recommended method to connect to SFTP)
$systemConfig['sftp']['privateKeyPassphrase'] = ''; // if passphrase was set for the private key
$systemConfig['sftp']['password'] = ''; // Optional Password for SFTP, if privateKey is not provided (not recommended)



$systemConfig['interfacing'] = [];


// Enable/Disable Interfacing
// true => Enabled
// false => Disabled
$systemConfig['interfacing']['enabled'] = false;

// Interfacing Database Details (not needed if above feature set to false)
$systemConfig['interfacing']['database']['host'] = '';
$systemConfig['interfacing']['database']['username'] = '';
$systemConfig['interfacing']['database']['password'] = '';
$systemConfig['interfacing']['database']['db'] = 'interfacing';
$systemConfig['interfacing']['database']['port'] = 3306;
$systemConfig['interfacing']['database']['charset'] = 'utf8mb4';

$systemConfig['interfacing']['sqlite3Path'] = '';

$systemConfig['recency'] = [];

// Enable/Disable Cross Login with Recency
// true => Enabled
// false => Disabled
$systemConfig['recency']['crosslogin'] = false;

// Domain URL of the Recency Web Application
$systemConfig['recency']['url'] = '';



// Enable/Disable Recency Viral Load tests sync
// true => Enabled
// false => Disabled
$systemConfig['recency']['vlsync'] = false;


// This Salt should match the Salt on Recency Web app
$systemConfig['recency']['crossloginSalt'] = "VALID LIBSODIUM KEY";


$systemConfig['system'] =[
    'debug_mode' => false
];

return $systemConfig;
