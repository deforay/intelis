# Updating InteLIS on a Windows Machine

InteLIS was previously called VLSM. The Windows install folder, the site
hostname, and the database are still named `vlsm`.

## 0. Backup

Take the backup with the application's own command, not with a phpMyAdmin export.
A phpMyAdmin `.zip` covers the main database only, leaves out the interfacing
database, uploads, attachments and configuration, and is rejected by
`setup.sh --db`, so a machine cannot be rebuilt from it.

- Open a command prompt and run:

  ```bat
  cd C:\wamp64\www\vlsm

  set PATH=C:\wamp64\bin\php\php8.4.1;%PATH%

  php composer.phar backup
  ```

- Confirm the dumps were written, and that their timestamps are from today:

  ```bat
  dir /o-d C:\wamp64\www\vlsm\backups\db
  ```

  Expect a `vlsm-*` file, plus an `interfacing-*` file where the interfacing
  database is in use.

- Copy the `backups` folder to a drive or share that is not this machine. A backup
  that only exists on the machine being updated protects nothing.

## 1. Download InteLIS

- Obtain InteLIS from <https://github.com/deforay/intelis>
- Extract the downloaded folder contents
- Copy all files into `C:\wamp64\www\vlsm`
- **Important:** Do not delete the existing folder. Copy the files into it.

## 2. Completing the Update

Open a terminal and run the following composer commands:

```bat
cd C:\wamp64\www\vlsm

set PATH=C:\wamp64\bin\php\php8.4.1;%PATH%

php composer.phar install --no-dev
php composer.phar dump-autoload -o

php composer.phar post-update
```

Open <http://vlsm> in a browser to verify the update completed successfully.
