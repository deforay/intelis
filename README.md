# Viral Load Sample Management System #

A simple, open source Sample Management System for Viral Load, EID, Covid-19 and Hepatitis testing.

#### Pre-requisites
* Apache 2.x  (Make sure apache rewrite and headers modules are enabled)
* MySQL 5.7.x
* PHP 7.4.x
* [Composer](https://getcomposer.org/download/)

### Ubuntu Setup
If you are using Ubuntu 22.04 (recommended), then you can find setup instructions here : [Ubuntu Setup](docs/Ubuntu-Setup.md)

#### How do I get started?
* Download the Source Code and put it into your server's root folder (www or htdocs).
* Run `composer update`. For e.g. you might have placed the code in `/var/www/vlsm` path. Then you would run `composer update` inside `/var/www/vlsm` folder.
* Open phpMyAdmin or any MySQL client and create a blank database called `vlsm`
* Import the [init.sql file from sql folder](./sql/init.sql) into the blank database.
* Copy the file configs/config.production.dist.php to configs/config.production.php
* Enter Database User, Password, DB Name etc. 

```php
// config.production.php
// Database Settings
$systemConfig['database']['host']     = 'localhost';
$systemConfig['database']['username'] = 'dbuser';
$systemConfig['database']['password'] = 'dbpassword';
$systemConfig['database']['db']       = 'vlsm';
$systemConfig['database']['port']     = 3306;
$systemConfig['database']['charset']  = 'utf8mb4';
```
* You can enable or disable VL,EID, Covid-19 etc. modules by changing the following variables in config.production.php. If a module is disabled, then it does not appear on the User Interface.

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

* Next we will set up virtual host for this application. You can find many guides online on this topic. For example to set up on Ubuntu you can follow this guide : [https://www.digitalocean.com/community/tutorials/how-to-set-up-apache-virtual-hosts-on-ubuntu-20-04](https://www.digitalocean.com/community/tutorials/how-to-set-up-apache-virtual-hosts-on-ubuntu-20-04)
* Before we set up the virtual host, ensure that the apache rewrite module is enabled in your Apache webserver settings.
* Edit your computer's hosts file to make an entry for this virtual host name.
* Next we create a virtual host pointing to the root folder of the source code. You can see an example below (assuming the full path to VLSM is `/var/www/vlsm`) : 

```apache
<VirtualHost *:80>
   DocumentRoot "/var/www/vlsm/public"
   
   ServerName vlsm.example.org

   <Directory "/var/www/vlsm/public">
       AddDefaultCharset UTF-8
       Options -Indexes -MultiViews +FollowSymLinks
       AllowOverride All
       Order allow,deny
       Allow from all
   </Directory>
</VirtualHost>
```

#### Completing Setup

* Add the following in the crontab (or equivalent scheduler for your Operating System)

    For e.g. if using Ubuntu, in the terminal type : `sudo EDITOR=gedit crontab -e`

    This opens the crontab file. At the end of the crontab file, type this line :


```bash
* * * * * cd /var/www/vlsm/ && ./vendor/bin/crunz schedule:run
```

* Once you have the application set up, you can visit the vlsm virtualhost URL http://vlsm.example.org/ and set up the admin user.
* Once you log in as the admin user, add the Sample Types, Reasons for Testing, Rejection Reasons etc. for each Test under the Admin menu.
* Continue the setup by adding users, provinces, districts, facilities etc. and complete the system configuration.


#### Who do I talk to?
You can reach us at support (at) deforay (dot) com