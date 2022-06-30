# Setting up Ubuntu 20.04



#### Initial Ubuntu OS Setup

* Install Ubuntu 22.04 using a flash disk or CD-ROM
* Make sure to choose a secure password when creating the Ubuntu user during installation
* Once installed and logged in, start the Software Updater and wait for the full software update process to complete.
* Start Terminal (ctrl + alt + t) and type the following commands :

	```sudo apt update && sudo apt upgrade -y```

	```sudo apt autoremove -y```

	```sudo apt install software-properties-common apt-transport-https wget vim zip unzip curl snapd rsync gdebi -y```

	```sudo snap install --classic code```

* Now Ubuntu setup is complete. We can now install Apache, MySQL and PHP.

#### Apache Setup
Type the following commands in the terminal:

```sudo apt install apache2 -y```

 ```sudo a2enmod rewrite headers```

 ```sudo service apache2 restart```

#### MySQL Setup

Type the following commands in the terminal:

```sudo apt install mysql-server -y```

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

```sudo apt install php libapache2-mod-php php-common php-mysql php-zip php-curl php-gd php-imagick php-intl php-bcmath php-json php-mbstring php-xml php-mime-type phpmyadmin -y```

```sudo ln -s /etc/phpmyadmin/apache.conf /etc/apache2/conf-enabled/phpmyadmin.conf```

```sudo service apache2 restart```

Finally use this link to install [Composer](https://getcomposer.org/download/)
