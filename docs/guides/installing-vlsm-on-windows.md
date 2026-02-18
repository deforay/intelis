# Installing VLSM on a Windows Machine

## 0. Download

- Notepad++ or Microsoft VS Code
- WampServer from https://www.wampserver.com/en/ (select 32 or 64 bit based on your system)
- VC Packages from https://wampserver.aviatechno.net/files/vcpackages/all_vc_redist_x86_x64.zip

## 1. Installing WAMP Server

- Ensure Windows system is fully updated
- Install VC Packages (all packages for 64-bit; only 32-bit packages for 32-bit systems)
- Reboot the machine
- Launch WampServer and verify the icon displays green

## 2. Configuring PHP and MySQL

### 2.1 PHP Setup

- Download cacert.pem from https://curl.se/docs/caextract.html and place in `C:\wamp\` or `C:\wamp64\`
- Switch PHP version to 8.2.13: WampServer > PHP > version > 8.2.13
- Open php.ini via WampServer > PHP > php.ini and modify:
  - `memory_limit`: change from 128mb to 2G (or higher if available)
  - `post_max_size`: change from 8M to 500M
  - `upload_max_filesize`: change from 2M to 500M
  - `;openssl.cafile=` to `openssl.cafile='C:\wamp\cacert.pem'` or `openssl.cafile='C:\wamp64\cacert.pem'`
  - `;curl.cainfo =` to `curl.cainfo ='C:\wamp\cacert.pem'` or `curl.cainfo ='C:\wamp64\cacert.pem'`
  - `error_reporting` to `error_reporting = E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED & ~E_WARNING`
  - `max_execution_time` to `max_execution_time = 1200`
- Repeat these edits in `C:\wamp64\bin\php\php8.2.13\php.ini`

### 2.2 MySQL Setup

**Fixing MySQL mode:**
- Open WampServer icon > MySQL > my.ini
- Search for `sql_mode` and comment it out with `;` at line start
- Add these lines:
  ```
  sql_mode =
  innodb_strict_mode = 0
  ```
- Search for `innodb_default_row_format=compact` and change to `innodb_default_row_format=dynamic` (or add if missing)
- Save and close

**Changing MySQL password:**
- WampServer icon > MySQL > MySQL Console
- Username: `root`
- Password: (blank — press Enter)
- Execute:
  ```sql
  ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'PASSWORD';
  FLUSH PRIVILEGES;
  exit;
  ```

**Final steps:**
- Restart all WampServer services
- Download latest Composer from https://getcomposer.org/download/

## 3. Setting up VLSM

### 3.1 VLSM Application Setup

- Clone/download VLSM from https://github.com/deforay/vlsm
- Extract and place in `C:\wamp\www\vlsm` or `C:\wamp64\www\vlsm`
- Place composer.phar in the vlsm folder
- Open terminal and run:
  ```
  cd C:\wamp64\www\vlsm
  set PATH=C:\wamp64\bin\php\php8.2.13;%PATH%
  php composer.phar install --no-dev
  php composer.phar dump-autoload -o
  ```

**Database setup:**
- Access phpMyAdmin at http://localhost/phpmyadmin
- Click SQL and execute:
  ```sql
  CREATE DATABASE `vlsm` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
  ```
- Import `sql/init.sql` into the vlsm database
- Run SQL commands from https://github.com/deforay/vlsm/blob/master/sql/audit-triggers.sql

**Configuration:**
- Rename `configs/config.production.dist.php` to `configs/config.production.php`
- Edit configuration with:
  - VLSTS URL: `$systemConfig['remoteURL'] = 'https://STSURL';`
  - Module settings (enable/disable as needed)
  - Database credentials
  - Interfacing database details

**Virtual host setup:**
- Open `C:\windows\system32\drivers\etc\hosts` as administrator
- Add: `127.0.0.1 vlsm`
- Edit `C:\wamp\bin\apache\apache2.4.54.2\conf\extra\httpd-vhosts.conf`:
  ```apache
  <VirtualHost *:80>
    ServerName localhost
    ServerAlias vlsm
    DocumentRoot "${INSTALL_DIR}/www/vlsm/public"
    <Directory "${INSTALL_DIR}/www/vlsm/public/">
      AddDefaultCharset UTF-8
      Options +Indexes +Includes +FollowSymLinks +MultiViews
      AllowOverride All
      Require local
    </Directory>
  </VirtualHost>
  ```
- Restart all WampServer services

**Application initialization:**
- Run in command prompt:
  ```
  cd C:\wamp64\www\vlsm
  set PATH=C:\wamp64\bin\php\php8.2.13;%PATH%
  php composer.phar post-install
  ```
- Access http://vlsm
- Register admin user and log in
- Select instance type as "BOTH"
- Click "Force Remote Sync" and wait for completion

**System admin setup:**
- Access http://vlsm/system-admin
- Retrieve secret key from `C:\wamp64\www\vlsm\app\system-admin\secretKey.txt`
- Register system admin user
- Select instance type as "Lab Instance" and choose lab name
- Sign out

### 3.2 Task Scheduler

- Open Task Scheduler and create new task named "VLSM TASK"
- Select "Run whether user is logged on or not"
- Under Triggers tab: Create new trigger
  - Select Daily
  - Check "Repeat Task Every" and set to 1 minute indefinitely
  - Check "Stop task if runs longer than" (default 3 days is acceptable)
- Under Actions tab: Create new action
  - Program: `C:\wamp64\bin\php\php8.2.13\php.exe`
  - Arguments: `C:\wamp64\www\vlsm\vendor\bin\crunz schedule:run`
- Enter Windows user password when prompted

## 4. Setting up Interfacing

- Access phpMyAdmin at http://localhost/phpmyadmin
- Execute SQL commands:
  ```sql
  CREATE DATABASE `interfacing` CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

  CREATE USER 'interfaceadmin'@'%' IDENTIFIED
  WITH mysql_native_password AS 'interface@12345';

  GRANT USAGE ON *.* TO 'interfaceadmin'@'%' REQUIRE NONE
  WITH MAX_QUERIES_PER_HOUR 0
  MAX_CONNECTIONS_PER_HOUR 0
  MAX_UPDATES_PER_HOUR 0 MAX_USER_CONNECTIONS 0;

  GRANT ALL PRIVILEGES ON `interfacing`.* TO 'interfaceadmin'@'%';
  ```
- Import interfacing database SQL file
- Download and install latest Interfacing executable
- Log in with credentials: `admin` / `admin`
- Configure MySQL details and instrument interface settings
- Verify connection status before releasing results from instruments
