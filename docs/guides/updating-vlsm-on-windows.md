---
layout: default
title: Updating VLSM on a Windows Machine
---

# Updating VLSM on a Windows Machine

## 0. Backup

- Access phpMyAdmin via http://localhost/phpmyadmin in your browser
- Navigate to the `vlsm` database and select the `Export` tab
- Choose `Custom - display all possible options`
- In the **Output** section, select `Zipped` compression
- Scroll down and click `Export` to download the backup file
- Store the downloaded file securely

## 1. Download VLSM

- Obtain VLSM from https://github.com/deforay/vlsm
- Extract the VLSM folder contents
- Copy all files into `C:\wamp64\www\vlsm`
- **Important:** DON'T DELETE THE EXISTING VLSM FOLDER, JUST COPY FILES INTO IT

## 2. Completing the Update

Open terminal and execute the following composer commands:

```
cd C:\wamp64\www\vlsm

set PATH=C:\wamp64\bin\php\php8.2.13;%PATH%

php composer.phar install --no-dev
php composer.phar dump-autoload -o

php composer.phar post-update
```

Open http://vlsm in your browser to verify the update completed successfully.
