# Installing InteLIS on a Windows Machine

InteLIS was previously called VLSM. The Windows install folder, the site
hostname, and the database are still named `vlsm`, and this guide keeps those
names so they match an existing installation.

## 0. Download

- Notepad++ or Microsoft VS Code
- WampServer from <https://www.wampserver.com/en/>, 32 or 64 bit to match the machine
- VC Packages from <https://wampserver.aviatechno.net/files/vcpackages/all_vc_redist_x86_x64.zip>

## 1. Installing WAMP Server

- Update Windows fully
- Install the VC Packages. Install all of them on 64-bit, and only the 32-bit packages on a 32-bit system
- Reboot the machine
- Launch WampServer and confirm the tray icon is green

## 2. Configuring PHP and MySQL

### 2.1 PHP Setup

- Download `cacert.pem` from <https://curl.se/docs/caextract.html> and place it in `C:\wamp\` or `C:\wamp64\`
- Switch the PHP version to 8.4.1 or newer: WampServer > PHP > version > 8.4.x.
  The committed `composer.lock` pins the platform to PHP 8.4.1 and contains
  packages that require it, so `composer install` fails on PHP 8.2 or 8.3.
- Open `php.ini` via WampServer > PHP > php.ini and change:
  - `memory_limit` from 128M to 2G, or higher if the machine allows
  - `post_max_size` from 8M to 500M
  - `upload_max_filesize` from 2M to 500M
  - `;openssl.cafile=` to `openssl.cafile='C:\wamp64\cacert.pem'`
  - `;curl.cainfo =` to `curl.cainfo ='C:\wamp64\cacert.pem'`
  - `error_reporting` to `error_reporting = E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED & ~E_WARNING`
  - `max_execution_time` to `max_execution_time = 1200`
- Repeat the same edits in `C:\wamp64\bin\php\php8.4.1\php.ini`

### 2.2 MySQL Setup

#### Fix the MySQL mode

- Open the WampServer icon > MySQL > my.ini
- Find `sql_mode` and comment it out with a leading `;`
- Add these lines:

  ```ini
  sql_mode =
  innodb_strict_mode = 0
  ```

- Find `innodb_default_row_format=compact` and change it to
  `innodb_default_row_format=dynamic`. Add the line if it is missing.
- Save and close

#### Change the MySQL password

- Open the WampServer icon > MySQL > MySQL Console
- Username: `root`
- Password: leave blank and press Enter
- Run:

  ```sql
  ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'PASSWORD';
  FLUSH PRIVILEGES;
  exit;
  ```

#### Final steps

- Restart all WampServer services
- Download the latest Composer from <https://getcomposer.org/download/>

## 3. Setting up InteLIS

### 3.1 InteLIS Application Setup

- Clone or download InteLIS from <https://github.com/deforay/intelis>
- Extract it into `C:\wamp64\www\vlsm`
- Place `composer.phar` in that folder
- Open a terminal and run:

  ```bat
  cd C:\wamp64\www\vlsm
  set PATH=C:\wamp64\bin\php\php8.4.1;%PATH%
  php composer.phar install --no-dev
  php composer.phar dump-autoload -o
  ```

#### Database setup

- Open phpMyAdmin at <http://localhost/phpmyadmin>
- Click SQL and run:

  ```sql
  CREATE DATABASE `vlsm` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
  ```

- Import `sql/init.sql` into the `vlsm` database

#### Configuration

- Rename `configs/config.production.dist.php` to `configs/config.production.php`
- Edit that file and set:
  - STS URL: `$systemConfig['remoteURL'] = 'https://STSURL';`
  - Module settings, enabled or disabled as needed
  - Database credentials
  - Interfacing database details

#### Virtual host setup

- Open `C:\windows\system32\drivers\etc\hosts` as administrator
- Add: `127.0.0.1 vlsm`
- Edit `C:\wamp64\bin\apache\apache2.4.54.2\conf\extra\httpd-vhosts.conf`:

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

  `Require local` allows the site to be opened only from this machine. Where other
  workstations in the lab use InteLIS on this server, replace it with the lab's
  own subnet, for example:

  ```apache
  Require ip 192.168.1
  ```

  Use `Require all granted` only where the machine is not reachable from outside
  the lab network. After changing this, confirm the site opens from a second
  workstation, not just from the server itself.

- Restart all WampServer services

#### Application initialization

- Run in a command prompt:

  ```bat
  cd C:\wamp64\www\vlsm
  set PATH=C:\wamp64\bin\php\php8.4.1;%PATH%
  php composer.phar post-install
  ```

- Generate the audit triggers. Run this after `post-install`, because the
  generator reads the migrated schema:

  ```bat
  php bin\setup\regenerate-audit-triggers.php --apply install
  ```

- Open <http://vlsm>
- Register the admin user and log in
- Click "Force Remote Sync" and wait for it to finish

#### System admin setup

- Open <http://vlsm/system-admin>
- Read the secret key from `C:\wamp64\www\vlsm\var\secret-key.txt`
- Register the system admin user
- Set **Instance Type**. The list offers **Standalone**,
  **LIS - LAB INFORMATION SYSTEM** and **STS - SAMPLE TRACKING SYSTEM**. A lab
  machine that syncs to a central instance is **LIS - LAB INFORMATION SYSTEM**;
  choose the lab name once that is selected. A machine with no central instance
  is **Standalone**.
- Sign out

### 3.2 Task Scheduler

- Open Task Scheduler and create a new task named "InteLIS Task"
- Select "Run whether user is logged on or not"
- On the Triggers tab, create a trigger:
  - Select Daily
  - Check "Repeat Task Every" and set it to 1 minute indefinitely
  - Check "Stop task if runs longer than". The default of 3 days is fine.
- On the Actions tab, create an action:
  - Program: `C:\wamp64\bin\php\php8.4.1\php.exe`
  - Arguments: `C:\wamp64\www\vlsm\vendor\bin\crunz schedule:run`
  - **Start in:** `C:\wamp64\www\vlsm`

  The **Start in** field is required. Task Scheduler otherwise starts the action
  in a system directory, and Crunz locates `crunz.yml` relative to the current
  working directory. Without it the task runs and exits silently, and scheduled
  backups, synchronisation and result imports never happen.

- After the first scheduled run, confirm the schedule is alive: the file
  `C:\wamp64\www\vlsm\var\.cron_heartbeat` must have a current timestamp.
- Enter the Windows user password when prompted

## 4. Setting up Interfacing

- Open phpMyAdmin at <http://localhost/phpmyadmin>
- Run these statements. Replace `interface@12345` with a password chosen for this
  machine, because the value below is published in this guide, and replace
  `192.168.1.50` with the address of the machine running the Interface Tool.

  Scoping the account to that one address matters: `'interfaceadmin'@'%'` accepts
  password attempts from every host that can reach MySQL, which on a lab network
  includes any machine an instrument or a visitor plugs in.

  ```sql
  CREATE DATABASE `interfacing` CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

  CREATE USER 'interfaceadmin'@'192.168.1.50' IDENTIFIED
  WITH mysql_native_password BY 'interface@12345';

  GRANT USAGE ON *.* TO 'interfaceadmin'@'192.168.1.50' REQUIRE NONE
  WITH MAX_QUERIES_PER_HOUR 0
  MAX_CONNECTIONS_PER_HOUR 0
  MAX_UPDATES_PER_HOUR 0 MAX_USER_CONNECTIONS 0;

  GRANT ALL PRIVILEGES ON `interfacing`.* TO 'interfaceadmin'@'192.168.1.50';
  ```

  Where the Interface Tool runs on this same machine, use
  `'interfaceadmin'@'localhost'` throughout instead.

- Import the interfacing database SQL file
- Download and install the latest Interfacing executable
- Log in with `admin` / `admin`
- Configure the MySQL details and the instrument interface settings
- Confirm the connection status before releasing results from instruments
