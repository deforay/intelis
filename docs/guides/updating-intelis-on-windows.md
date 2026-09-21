# Updating InteLIS on a Windows Machine

Update an InteLIS installation on a Windows machine that runs WampServer.

The installation lives in `C:\wamp64\www\vlsm` and opens at <http://vlsm>.
WampServer and command-line PHP must run **PHP 8.4+ (minimum 8.4.1)**.
PHP 8.2, 8.3, and older versions are not supported. This guide uses PHP 8.4
paths. If you use PHP 8.5, substitute its installed folder name.

??? info "Why the folder is named `vlsm`"

    InteLIS was previously called VLSM. The Windows install folder, the site
    hostname and the database still carry that name.

**Follow the steps from top to bottom.**

## Back up

1. Open `C:\wamp64\bin\php` in File Explorer. Note the name of the folder that
   starts with `php8.4`, for example `php8.4.1`. The steps below call it
   `php8.4.x`.
2. Open **Command Prompt**.
3. Take a backup. Replace `php8.4.x` with the folder name from step 1:

    ```bat
    cd C:\wamp64\www\vlsm
    set PATH=C:\wamp64\bin\php\php8.4.x;%PATH%
    php composer.phar backup
    ```

4. List the backup files, newest first:

    ```bat
    dir /o-d C:\wamp64\www\vlsm\backups\db
    ```

    The top file carries today's date in its name, for example `20260918`.

    ??? failure "If the backup stops with an error, or no file carries today's date"

        Export the databases from phpMyAdmin instead:

        1. Open <http://localhost/phpmyadmin> and log in as `root`.
        2. Select the `vlsm` database in the left panel.
        3. Select **Export**, then **Export** or **Go** at the bottom of the page.
        4. Save the `.sql` file.
        5. If the lab uses the interfacing tool, repeat steps 2 to 4 for the
           `interfacing` database.
        6. Copy the `C:\wamp64\www\vlsm\configs` folder next to the exported
           files.

        Send the error from step 3 to support.

5. Copy the `C:\wamp64\www\vlsm\backups` folder to a USB drive or a network
   share. Include the phpMyAdmin exports if step 4 needed them.

## Replace the files

6. Download the current release:
   <https://github.com/deforay/intelis/archive/refs/heads/stable.zip>
7. Extract the zip file. It holds one folder, `intelis-stable`.
8. Open `intelis-stable`. Press **Ctrl+A**, then **Ctrl+C** to copy everything
   inside it.
9. Open `C:\wamp64\www\vlsm`. Press **Ctrl+V**, then choose
   **Replace the files in the destination**.

    Do not delete or rename `C:\wamp64\www\vlsm` first. It holds the
    configuration, the uploaded files and the backups, and the zip file does
    not contain them.

## Update

10. In the Command Prompt window, install the packages:

    ```bat
    cd C:\wamp64\www\vlsm
    set PATH=C:\wamp64\bin\php\php8.4.x;%PATH%
    php composer.phar install --no-dev --no-scripts
    php composer.phar dump-autoload -o
    ```

    ??? failure "If composer reports that the PHP version does not satisfy a requirement"

        The `set PATH` line points to a PHP older than 8.4.1. Check the folder
        name from step 1, then repeat step 10.

11. Apply the database changes:

    ```bat
    php composer.phar post-update
    ```

    Wait until the prompt returns.

## Check the update

12. Open <http://vlsm> in the browser and log in.
13. Check the page footer. It shows the version, for example `v5.7.72`. The
    number matches the `"version"` line in `C:\wamp64\www\vlsm\composer.json`.

    ??? failure "If the footer shows a red `DB ver.` warning"

        The database changes did not finish. Run step 11 again and send its
        whole output to support.
