# Updating InteLIS on a Windows Machine

InteLIS was previously called VLSM. The Windows install folder, the site
hostname, and the database are still named `vlsm`.

## 0. Backup

- Access phpMyAdmin via <http://localhost/phpmyadmin> in your browser
- Navigate to the `vlsm` database and select the `Export` tab
- Choose `Custom - display all possible options`
- In the **Output** section, select `Zipped` compression
- Scroll down and click `Export` to download the backup file
- Store the downloaded file securely

## 1. Download InteLIS

- Obtain InteLIS from <https://github.com/deforay/intelis>
- Extract the downloaded folder contents
- Copy all files into `C:\wamp64\www\vlsm`
- **Important:** Do not delete the existing folder. Copy the files into it.

## 2. Completing the Update

Open a terminal and run the following composer commands:

```bat
cd C:\wamp64\www\vlsm

set PATH=C:\wamp64\bin\php\php8.2.13;%PATH%

php composer.phar install --no-dev
php composer.phar dump-autoload -o

php composer.phar post-update
```

Open <http://vlsm> in your browser to verify the update completed successfully.
