# Setting up Interfacing Tool on a Client Ubuntu Machine

## Client Ubuntu Machine Setup

Execute these commands to prepare the client:

```bash
sudo apt-get update && sudo apt-get upgrade -y;

sudo apt-get install mysql-client;
```

## Server Ubuntu Machine Configuration

### Modify MySQL Configuration

Edit the MySQL configuration file:

```bash
sudo gedit /etc/mysql/mysql.conf.d/mysqld.cnf
```

Update the bind addresses to allow remote connections:

```
bind-address        = 0.0.0.0
mysqlx-bind-address = 0.0.0.0
```

### Open Firewall and Restart MySQL

Allow port 3306 through the firewall and restart the MySQL service:

```bash
sudo ufw allow 3306/tcp
sudo service mysql restart
```

### Create Database User

Open phpMyAdmin on the server and execute these SQL commands:

```sql
USE mysql;

CREATE USER 'interfaceadmin'@'%' IDENTIFIED BY 'interface@12345';

ALTER USER 'interfaceadmin'@'%' IDENTIFIED WITH mysql_native_password BY 'interface@12345';

GRANT ALL PRIVILEGES ON interfacing.* TO 'interfaceadmin'@'%';

FLUSH PRIVILEGES;
```

The newly created user credentials can then be used in your interface tool configuration.
