# InteLIS

> **Integrated Laboratory Information & Sample Tracking System**
>  Simple, open-source LIS to manage and track samples for HIV, EID, TB, Hepatitis, and COVID-19.

![PHP](https://img.shields.io/badge/PHP-8.2+-blue)
 ![Ubuntu](https://img.shields.io/badge/Ubuntu-22.04%2B-orange)
 ![Status](https://img.shields.io/badge/status-stable-success)
 ![License: InteLIS Community Copyleft License (Non-Commercial)](https://img.shields.io/badge/License-Community%20Copyleft%20v1.0-blue)

InteLIS (formerly **VLSM**) digitizes laboratory workflows — from sample collection to result dispatch — for national and sub-national health programs.

It’s lightweight, self-hostable, and works both online and offline.

------

## 📁 Table of Contents

- [InteLIS](#intelis)
  - [📁 Table of Contents](#-table-of-contents)
  - [⚖️ License](#️-license)
  - [📋 Pre-requisites](#-pre-requisites)
  - [🚀 Installation](#-installation)
    - [Option 1 — Automated Installation (Ubuntu LTS only)](#option-1--automated-installation-ubuntu-lts-only)
    - [Option 2 — Manual Installation](#option-2--manual-installation)
      - [Step 1 — Get the code](#step-1--get-the-code)
      - [Step 2 — Install dependencies](#step-2--install-dependencies)
      - [Step 3 — Set up the database](#step-3--set-up-the-database)
      - [Step 4 — Configure the application](#step-4--configure-the-application)
      - [Step 5 — Set up Apache virtual host](#step-5--set-up-apache-virtual-host)
      - [Step 6 — Set up the cron job](#step-6--set-up-the-cron-job)
  - [✅ Complete the Setup](#-complete-the-setup)
  - [🔄 Updating InteLIS](#-updating-intelis)
    - [Option 1 — Automated Update (Ubuntu LTS only)](#option-1--automated-update-ubuntu-lts-only)
    - [Option 2 — Manual Update](#option-2--manual-update)
  - [💬 Support](#-support)

------

## ⚖️ License

InteLIS is released under the **InteLIS Community Copyleft License (Non-Commercial), Version 1.0**.

This license allows **non-commercial use** — including public or private laboratories, healthcare programs, NGOs, research, and education — but **restricts commercial redistribution or resale** of the software.

> For commercial licensing inquiries, contact [support@deforay.com](mailto:support@deforay.com)

See the full license text in [LICENSE.md](https://chatgpt.com/c/LICENSE.md).

------

## 📋 Pre-requisites

- Apache 2.x (with `rewrite` and `headers` modules enabled)
- MySQL 5.7 or higher
- PHP 8.2.x
- [Composer](https://getcomposer.org/download/)

------

## 🚀 Installation

### Option 1 — Automated Installation (Ubuntu LTS only)

**Supports Ubuntu 22.04 and above (LTS versions only)**

```bash
cd ~
sudo wget -O setup.sh https://github.com/deforay/intelis/raw/master/scripts/setup.sh
sudo chmod +x setup.sh
sudo ./setup.sh
sudo rm setup.sh
exit
```

When prompted, enter:

- MySQL password
- STS URL

After the script completes:

1. Edit the config file with your MySQL details:

   ```bash
   sudo gedit /var/www/intelis/configs/config.production.php
   ```

2. Continue with [Complete the Setup](#✅-complete-the-setup).

------

### Option 2 — Manual Installation

#### Step 1 — Get the code

Download the source code and place it in your web root (`/var/www/` or `htdocs`).

#### Step 2 — Install dependencies

```bash
cd /var/www/intelis
composer install --no-scripts --no-autoloader --prefer-dist --no-dev
composer dump-autoload -o
composer post-install
```

> The `composer post-install` command is required after a fresh install.

#### Step 3 — Set up the database

1. Create a blank database called `intelis`.
2. Import `init.sql` into it (e.g., via phpMyAdmin or MySQL CLI).

#### Step 4 — Configure the application

Copy and edit the configuration file:

```bash
cp configs/config.production.dist.php configs/config.production.php
```

Edit `configs/config.production.php`:

```php
// Database Settings
$systemConfig['database']['host']     = 'localhost';
$systemConfig['database']['username'] = 'dbuser';
$systemConfig['database']['password'] = 'dbpassword';
$systemConfig['database']['db']       = 'intelis';
$systemConfig['database']['port']     = 3306;
$systemConfig['database']['charset']  = 'utf8mb4';
```

Enable or disable modules as needed:

```php
// Enable/Disable Modules
$systemConfig['modules']['vl'] = true;              // Viral Load
$systemConfig['modules']['eid'] = true;             // Early Infant Diagnosis
$systemConfig['modules']['covid19'] = false;        // Covid-19
$systemConfig['modules']['generic-tests'] = false;  // Generic Tests
$systemConfig['modules']['hepatitis'] = false;      // Hepatitis
$systemConfig['modules']['tb'] = false;             // Tuberculosis
```

#### Step 5 — Set up Apache virtual host

1. Ensure Apache rewrite module is enabled.

2. Add this to `/etc/hosts`:

   ```
   127.0.0.1  intelis.example.org
   ```

3. Create a virtual host configuration (assuming `/var/www/intelis`):

   ```apache
   <VirtualHost *:80>
      DocumentRoot "/var/www/intelis/public"
      ServerName intelis.example.org
   
      <Directory "/var/www/intelis/public">
          AddDefaultCharset UTF-8
          Options -Indexes -MultiViews +FollowSymLinks
          AllowOverride All
          Require all granted
      </Directory>
   </VirtualHost>
   ```

Need help? See: [How to set up Apache Virtual Hosts on Ubuntu](https://www.digitalocean.com/community/tutorials/how-to-set-up-apache-virtual-hosts-on-ubuntu-20-04)

#### Step 6 — Set up the cron job

```bash
sudo EDITOR=gedit crontab -e
```

Add this line:

```bash
* * * * * cd /var/www/intelis/ && ./vendor/bin/crunz schedule:run
```

------

## ✅ Complete the Setup

**Applies to both automated and manual installations.**

1. Visit the application in your browser:
   - **Manual:** http://intelis.example.org/
   - **Automated:** http://intelis/
2. Register and set up the admin user.
3. Log in and configure under **Admin → System Settings**:
   - Sample Types
   - Reasons for Testing
   - Rejection Reasons
   - Users, Provinces, Districts, Facilities
   - Other settings

------

## 🔄 Updating InteLIS

### Option 1 — Automated Update (Ubuntu LTS only)

```bash
sudo wget -O /usr/local/bin/intelis-update https://github.com/deforay/intelis/raw/master/scripts/upgrade.sh
sudo chmod +x /usr/local/bin/intelis-update
sudo intelis-update
```

When prompted, enter:

- MySQL password
- STS URL

------

### Option 2 — Manual Update

1. Pull the latest source or download it manually.

2. Update dependencies:

   ```bash
   cd /var/www/intelis
   composer install --no-scripts --no-autoloader --prefer-dist --no-dev
   composer dump-autoload -o
   composer post-update
   ```

   > The `composer post-update` command is required after code updates.

3. Apply any database migrations or config changes (see release notes).

4. Clear cache if needed.

5. Restart Apache:

   ```bash
   sudo systemctl restart apache2
   ```

------

## 💬 Support

Need help or commercial licensing?

- Email **[support@deforay.com](mailto:support@deforay.com)**
- Website: [https://deforay.com](https://deforay.com/)