## Installing InteLIS on Ubuntu 24.04

A script to install InteLIS on a fresh instance of Ubuntu 24.04 is available. This script is **ONLY FOR A FRESH OS**, it will install Apache, MySQL 8.0 & PHP 8.4 and could OVERWRITE any existing web setup on the machine.

It also does not set up mail settings or configure system security so you will have to do those separately. You can use the script as a reference if you’re installing on a non-fresh machine.

```
cd ~;
sudo -s;
wget -O setup.sh https://raw.githubusercontent.com/deforay/intelis/master/scripts/setup.sh
sudo chmod +x setup.sh;
./setup.sh;
rm setup.sh;
exit;exit;

```

##### PLEASE NOTE :

- During the script installation, you will be prompted to enter a few critical details
  - **InteLIS Folder Location**: You can type the full folder path for InteLIS installation. Or, you can press enter if you want to use the default location (recommended).
  - **MySQL Password**: You will be prompted to enter a password for MySQL root user. Please enter a password of your choice and remember it.
  - **Hostname** : Type the hostname you want to use. Press enter for default : `intelis`
  - **STS URL**: You will also be prompted to enter the Sample Tracking System (STS) URL. Please enter the URL of the STS server you want to use. You can leave it blank if you don’t want to use STS.

## Finishing up InteLIS Setup

- After the script finishes installation, edit config.production.php and enter correct MySQL details and other config
  `sudo gedit /var/www/intelis/configs/config.production.php`
- Now open http://intelis/ or yourr chosen hostname
- Complete the LIS Setup and register a new admin user
- After successful registration login as the new admin user
