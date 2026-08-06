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

```ini
bind-address        = 0.0.0.0
mysqlx-bind-address = 0.0.0.0
```

### Open Firewall and Restart MySQL

Allow port 3306 from the lab network only, then restart MySQL. Replace
`192.168.1.0/24` with your own subnet:

```bash
sudo ufw allow from 192.168.1.0/24 to any port 3306 proto tcp
sudo service mysql restart
```

Do not use `sudo ufw allow 3306/tcp`. That opens the database to every network
the server can reach.

### Create Database User

Open phpMyAdmin on the server and execute these SQL commands:

```sql
USE mysql;

CREATE USER 'interfaceadmin'@'%' IDENTIFIED BY 'interface@12345';

ALTER USER 'interfaceadmin'@'%' IDENTIFIED WITH mysql_native_password BY 'interface@12345';

GRANT ALL PRIVILEGES ON interfacing.* TO 'interfaceadmin'@'%';

FLUSH PRIVILEGES;
```

Use these credentials in the interfacing tool configuration.

Replace `interface@12345` with a password you choose. The value above is an
example and is published in this guide.

On MySQL 8.4 and newer, `mysql_native_password` is not enabled by default. Drop
the `ALTER USER` line there and keep the default authentication plugin.
