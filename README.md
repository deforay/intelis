# Viral Load Sample Management System #

A simple, open source Sample Management System for Viral Load, EID, Covid-19 and Hepatitis testing.

#### Pre-requisites
* Apache 2
* MySQL 5+
* PHP 7.4+


#### How do I get started?
* Download the Source Code and put it into your server's root folder (www or htdocs).
* Open phpMyAdmin or any MySQL client and create a blank database
* Import the initial sql file from the releases page
* Rename configs/config.production.dist.php to configs/config.production.php
* Enter Database User, Password, DB Name etc. 

```php
// config.production.php
// Database Settings
$systemConfig['dbHost']     = 'localhost';
$systemConfig['dbUser']     = 'dbuser';
$systemConfig['dbPassword'] = 'dbpassword';
$systemConfig['dbName']     = 'vlsm';
$systemConfig['dbPort']     = 3306;
$systemConfig['dbCharset']  = 'utf8mb4';
```
* You can enable or disable VL,EID or Covid-19 module by changing the following variables in config.production.php. If a module is disabled, then it does not appear on the User Interface.

```php
// config.production.php
// Enable/Disable Modules
// true => Enabled
// false => Disabled
$systemConfig['modules']['vl'] = true;
$systemConfig['modules']['eid'] = true;
$systemConfig['modules']['covid19'] = false;
$systemConfig['modules']['hepatitis'] = false;
$systemConfig['modules']['tb'] = false;
```

* Next we will set up virtual host for this application. You can find many guides online on this topic. For example to set up on Ubuntu you can follow this guide : https://www.digitalocean.com/community/tutorials/how-to-set-up-apache-virtual-hosts-on-ubuntu-18-04
* Before we set up the virtual host, ensure that the apache rewrite module is enabled in your Apache webserver settings.
* Edit your computer's hosts file to make an entry for this virtual host name.
* Next we create a virtual host pointing to the root folder of the source code. You can see an example below (assuming the full path to VLSM is "/var/www/vlsm") : 

```apache
<VirtualHost *:80>
   DocumentRoot "/var/www/vlsm"
   ServerName vlsm.example.org

   <Directory "/var/www/vlsm">
       php_value auto_prepend_file /var/www/vlsm/startup.php

       AddDefaultCharset UTF-8
       Options Indexes MultiViews FollowSymLinks
       AllowOverride All
       Order allow,deny
       Allow from all
   </Directory>
</VirtualHost>
```

#### Completing Setup

Add the following in crontab (or equivalent for your Operating System)

```
* * * * * cd /var/www/vlsm/ && ./vendor/bin/crunz schedule:run
```

* Once you have the application set up, you can visit the vlsm URL http://vlsm.example.org/ and set up admin user
* Once you login as admin user, add the Sample Types, Reasons for Testing, Rejection Reasons, Provinces etc. for each Test under the Admin menu 
* Now you can start adding Users, facilities and finish the System config.


#### Who do I talk to?
You can reach us at support (at) deforay (dot) com