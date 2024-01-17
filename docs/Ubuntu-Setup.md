
## Installing VLSM on Ubuntu 22.04

A script to install VLSM on a fresh instance of Ubuntu 22.04 is available. This script is **ONLY FOR A FRESH OS**, it will install Apache, MySQL 8.0 & PHP 8.2 and could OVERWRITE any existing web setup on the machine.

It also does not set up mail settings or configure system security so you will have to do those separately. You can use the script as a reference if you’re installing on a non-fresh machine.


```
cd ~;
sudo -s;
wget -O setup.sh https://raw.githubusercontent.com/deforay/vlsm/master/docs/setup.sh
sudo chmod +x setup.sh;
./setup.sh;
rm setup.sh;
exit;exit;

```

##### PLEASE NOTE :
- During the script installation, you will be prompted to enter a few critical details
	- **VLSM Folder Location**: You can type the full folder path for VLSM installation. Or, you can press enter if you want to use the default location.
	- **MySQL Password**: You will be prompted to enter a password for MySQL root user. Please enter a password of your choice and remember it.
	- **Hostname** : Type the hostname you want to use. Press enter for default : `vlsm`
	- **STS URL**: You will also be prompted to enter the Sample Tracking System (STS) URL. Please enter the URL of the STS server you want to use. You can leave it blank if you don’t want to use STS.


## Finishing up VLSM Setup

- After the script finishes installation, edit config.production.php and enter correct MySQL details and other config
  `sudo gedit /var/www/vlsm/configs/config.production.php`
- Now open http://vlsm/
- Register new admin user
- After successful registration login as admin http://vlsm/
- After you login, Fill the instance details in the popup. Select __LIS with Remote Ordering Enabled__
- Select the lab from the dropdown
- Click on Force Sync and wait for sync to finish
