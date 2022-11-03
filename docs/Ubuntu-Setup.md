# Setting up Ubuntu 22.04


#### Initial Ubuntu OS Setup

* Install Ubuntu 22.04 using a flash disk or CD-ROM
* Make sure to choose a secure password when creating the Ubuntu user during installation
* Once installed and logged in, start the Software Updater and wait for the full software update process to complete.
* Start Terminal (ctrl + alt + t) and run the following commands :

	```
	sudo apt update && sudo apt upgrade -y;
	sudo apt autoremove -y;
	sudo apt install apt-transport-https ca-certificates lsb-release gnupg software-properties-common apt-transport-https wget vim zip unzip curl snapd rsync git gdebi -y;

	```
	
	Optiionally, install Visual Studio Code (useful for editing PHP and other config files)
	
	```
	sudo snap install --classic code
	```

* Now Ubuntu setup is complete. We can now install Apache, MySQL and PHP.

#### Apache Setup
Run the following commands in the terminal:

```
sudo apt install apache2 -y;
sudo a2enmod rewrite headers deflate;
sudo service apache2 restart;
```

#### MySQL Setup

Run the following command in the terminal:

```sudo apt install mysql-server -y```

Now let us enter the MySQL terminal to set up the root user password

```sudo mysql```

Once you are inside MySQL prompt type the following commands one by one : 

```ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '<PASSWORD>';```

```FLUSH PRIVILEGES;```

```exit;```

Once you are back in Bash terminal:

```sudo gedit /etc/mysql/mysql.conf.d/mysqld.cnf```

Search for the line which has ```skip-external-locking``` or ```mysqlx-bind-address = 127.0.0.1``` and add the following new lines after that : 

```
sql_mode = 
innodb_strict_mode = 0
```
Save and Close file. Then restart mysql :

```sudo service mysql restart```

#### PHP Setup

Run the following command in the terminal :

``` 
sudo add-apt-repository ppa:ondrej/php
```
Press ENTER when prompted.


Now let us install PHP 7.4:

```
sudo apt update;
sudo apt -y install php7.4 openssl php7.4-common php7.4-cli php7.4-json php7.4-common php7.4-mysql php7.4-zip php7.4-gd php7.4-mbstring php7.4-curl php7.4-xml php7.4-xmlrpc php7.4-bcmath php7.4-gmp php7.4-zip php7.4-intl php7.4-imagick php-mime-type phpmyadmin;
sudo ln -s /etc/phpmyadmin/apache.conf /etc/apache2/conf-enabled/phpmyadmin.conf;
sudo service apache2 restart;

```
Ensuring the right version of PHP is configured :

```
sudo a2dismod php8.*;
sudo a2enmod php7.4;
sudo service apache2 restart;

sudo update-alternatives --set php /usr/bin/php7.4;
sudo update-alternatives --set phar /usr/bin/phar7.4;
sudo update-alternatives --set phar.phar /usr/bin/phar.phar7.4;
sudo service apache2 restart;

```

Now when you run `php -v` in the terminal, you should see version 7.4


Finally, to install [Composer](https://getcomposer.org/download/), run the following commands

```
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');";
php composer-setup.php;
php -r "unlink('composer-setup.php');";
sudo mv composer.phar /usr/local/bin/composer;

```

