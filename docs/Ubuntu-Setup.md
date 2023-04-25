# Setting up Ubuntu 22.04


#### Initial Ubuntu OS Setup

* Install Ubuntu 22.04 using a flash disk or CD-ROM
* Make sure to choose a secure password when creating the Ubuntu user during installation
* Once installed and logged in, start the Software Updater and wait for the full software update process to complete.
* Start Terminal (`ctrl + alt + t`) and run the following commands :


```bash
sudo apt update && sudo apt upgrade -y;
sudo apt autoremove -y;
sudo locale-gen en_US
sudo locale-gen en_US.UTF-8
export LANG=en_US.UTF-8;
export LC_ALL=en_US.UTF-8;
sudo update-locale ;
sudo apt install software-properties-common  \
gnupg apt-transport-https ca-certificates \
lsb-release wget vim zip unzip curl \
acl snapd rsync git gdebi wget gedit -y;
```

Optionally, install Visual Studio Code (useful for editing PHP and other config files)
	
```bash
sudo snap install --classic code
```

* Now Ubuntu setup is complete. We can now install Apache, MySQL and PHP.

#### Apache Setup
Run the following commands in the terminal:

```bash
sudo apt install apache2 -y;
sudo a2enmod rewrite headers deflate env;
sudo service apache2 restart;
sudo setfacl -R -m u:$USER:rwx /var/www;
sudo setfacl -R -m u:www-data:rwx /var/www;
```

#### MySQL Setup

Run the following command in the terminal:

```bash
sudo apt install mysql-server -y;
```

Now let us enter the MySQL terminal to set up the root user password

```bash
sudo mysql;
```

Once you are inside MySQL prompt type the following commands one by one : 

`ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '<PASSWORD>';`

`FLUSH PRIVILEGES;`

`exit;`

Once you are back in Bash terminal:

```bash
sudo gedit /etc/mysql/mysql.conf.d/mysqld.cnf
```

Search for the line which has `skip-external-locking` or `mysqlx-bind-address = 127.0.0.1` and add the following new lines after that : 

```text
sql_mode = 
innodb_strict_mode = 0
```
Save and Close file. Then restart mysql :

```bash
sudo service mysql restart;
```

#### PHP Setup

Now let us install PHP 7.4:

```bash
sudo add-apt-repository ppa:ondrej/php -y;
sudo apt update;
sudo apt purge php8* -y && apt autoremove -y;
sudo apt -y install php7.4 openssl php7.4-common php7.4-cli \
php7.4-json php7.4-common php7.4-mysql php7.4-zip php7.4-gd \
php7.4-mbstring php7.4-curl php7.4-xml php7.4-xmlrpc php7.4-bcmath \
php7.4-gmp php7.4-zip php7.4-intl php7.4-imagick php-mime-type;
sudo service apache2 restart;
```

Ensuring the right version of PHP is configured :

```bash
sudo a2dismod php8.*;
sudo a2enmod php7.4;
sudo service apache2 restart;

sudo update-alternatives --set php /usr/bin/php7.4;
sudo update-alternatives --set phar /usr/bin/phar7.4;
sudo update-alternatives --set phar.phar /usr/bin/phar.phar7.4;
sudo service apache2 restart;
```

Now when you run `php -v` in the terminal, you should see version PHP version 7.4


Finally, to install [Composer](https://getcomposer.org/download/), run the following commands

```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');";
php composer-setup.php;
php -r "unlink('composer-setup.php');";
sudo mv composer.phar /usr/local/bin/composer;

```

Now you can continue with [VLSM setup](../README.md).

