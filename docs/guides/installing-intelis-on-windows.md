---
description: Install InteLIS on a Windows machine with WampServer, from PHP and MySQL settings to scheduled tasks.
audience: [system-admin]
module: [all]
type: how-to
platform: windows
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
# Installing InteLIS on a Windows Machine

Install InteLIS on a Windows machine with WampServer.

The machine needs an internet connection and an account with administrator
rights. The installation goes into `C:\wamp64\www\vlsm` and opens at
<http://vlsm>.

InteLIS requires **PHP 8.4+ (minimum 8.4.1)** for both WampServer and the
command line. PHP 8.2, 8.3, and older versions are not supported. This guide
uses PHP 8.4 paths. If you use PHP 8.5, substitute its installed folder name.

??? info "Why the folder is named `vlsm`"

    InteLIS was previously called VLSM. The Windows install folder, the site
    hostname and the database still carry that name, so they match existing
    installations.

**Follow the steps from top to bottom. Each part ends with a check. Do not go
on until the check passes.**

## Prepare Windows

1. Install all pending Windows updates.
2. Install a text editor: Notepad++ or Microsoft VS Code.
3. Download the Visual C++ packages:
   <https://wampserver.aviatechno.net/files/vcpackages/all_vc_redist_x86_x64.zip>
4. Extract the zip file and install the packages inside it. On 64-bit Windows,
   install all of them. On 32-bit Windows, install only the x86 packages.
5. Restart the machine.
6. Download WampServer from <https://www.wampserver.com/en/>. Choose the 64-bit
   installer on 64-bit Windows.
7. Install WampServer into `C:\wamp64`.
8. Start WampServer.

**Check:** the WampServer icon in the system tray turns green.

## Configure PHP

9. Download `cacert.pem` from <https://curl.se/docs/caextract.html> and save it
   as `C:\wamp64\cacert.pem`.
10. Select the WampServer tray icon, then **PHP → Version**. Choose the newest
    version that starts with `8.4`.
11. Open `C:\wamp64\bin\php` in File Explorer. Note the name of the folder that
    starts with `php8.4`, for example `php8.4.1`. The steps below call it
    `php8.4.x`.
12. Select the WampServer tray icon, then **PHP → php.ini**. Change these
    settings, then save the file:

    | Setting | New value |
    | --- | --- |
    | `memory_limit` | `2G`, or higher if the machine allows |
    | `post_max_size` | `500M` |
    | `upload_max_filesize` | `500M` |
    | `max_execution_time` | `1200` |
    | `error_reporting` | `E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED & ~E_WARNING` |
    | `;openssl.cafile=` | `openssl.cafile='C:\wamp64\cacert.pem'` (remove the leading `;`) |
    | `;curl.cainfo =` | `curl.cainfo ='C:\wamp64\cacert.pem'` (remove the leading `;`) |

13. Make the same changes in `C:\wamp64\bin\php\php8.4.x\php.ini`. The command
    line uses this file.
14. Turn on the PHP extensions InteLIS needs: `bcmath`, `curl`, `fileinfo`,
    `gd`, `gettext`, `intl`, `json`, `mbstring`, `openssl`, `pdo`,
    `pdo_mysql`, `sodium`, `zip` and `zlib`.

    - Select the WampServer tray icon, then **PHP → PHP extensions**. Select
      each of these extensions that has no tick. An extension missing from
      the list is built into PHP.
    - In `C:\wamp64\bin\php\php8.4.x\php.ini`, remove the leading `;` from
      the `extension=` line of each of these extensions, for example
      `;extension=intl`. Save the file.

15. Open **Command Prompt** and run:

    ```bat
    set PATH=C:\wamp64\bin\php\php8.4.x;%PATH%
    php -v
    php -i | findstr memory_limit
    php -m
    ```

**Check:** `php -v` reports PHP 8.4.1 or newer, the second command prints
`memory_limit => 2G`, and `php -m` lists every extension from step 14.

## Configure MySQL

16. Select the WampServer tray icon, then **MySQL → my.ini**.
17. Find the `sql_mode` line and put `;` at its start.
18. Add these lines below it:

    ```ini
    sql_mode =
    innodb_strict_mode = 0
    ```

19. Find `innodb_default_row_format=compact` and change it to
    `innodb_default_row_format=dynamic`. If the line is missing, add
    `innodb_default_row_format=dynamic`.
20. Save the file.
21. Select the WampServer tray icon, then **MySQL → MySQL Console**. Log in as
    `root` with an empty password.
22. Set a root password. Replace `PASSWORD` with a new password, and write it
    down:

    ```sql
    ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'PASSWORD';
    FLUSH PRIVILEGES;
    exit;
    ```

    ??? failure "If MySQL rejects `mysql_native_password`"

        Newer MySQL versions do not load `mysql_native_password`, and reply
        `Plugin 'mysql_native_password' is not loaded`. Use MySQL's default
        method instead. PHP connects with it.

        ```sql
        ALTER USER 'root'@'localhost' IDENTIFIED BY 'PASSWORD';
        FLUSH PRIVILEGES;
        exit;
        ```

23. Select the WampServer tray icon, then **Restart All Services**.

**Check:** the tray icon turns green again, and <http://localhost/phpmyadmin>
accepts `root` with the new password.

## Get InteLIS

24. Download the current release:
    <https://github.com/deforay/intelis/archive/refs/heads/stable.zip>
25. Extract the zip file. It holds one folder, `intelis-stable`.
26. Create the folder `C:\wamp64\www\vlsm`.
27. Copy everything inside `intelis-stable` into `C:\wamp64\www\vlsm`.
28. Download `composer.phar` from
    <https://getcomposer.org/download/latest-stable/composer.phar> and save it
    in `C:\wamp64\www\vlsm`.
29. In Command Prompt, install the packages:

    ```bat
    cd C:\wamp64\www\vlsm
    set PATH=C:\wamp64\bin\php\php8.4.x;%PATH%
    php composer.phar install --no-dev --no-scripts
    php composer.phar dump-autoload -o
    ```

**Check:** the file `C:\wamp64\www\vlsm\vendor\autoload.php` exists.

## Create the database

30. Open <http://localhost/phpmyadmin> and log in as `root`.
31. Select **SQL**, run this, and select **Go**:

    ```sql
    CREATE DATABASE `vlsm` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
    ```

32. Select the `vlsm` database in the left panel.
33. Select **Import**, choose `C:\wamp64\www\vlsm\sql\init.sql`, and select
    **Import** or **Go** at the bottom of the page.

**Check:** the `vlsm` database in the left panel lists its tables.

## Configure InteLIS

34. Copy `C:\wamp64\www\vlsm\configs\config.production.dist.php` to
    `C:\wamp64\www\vlsm\configs\config.production.php`.
35. Open `config.production.php` in the text editor and set the database
    details:

    ```php
    $systemConfig['database']['host']       = 'localhost';
    $systemConfig['database']['username']   = 'root';
    $systemConfig['database']['password']   = 'PASSWORD';
    $systemConfig['database']['db']         = 'vlsm';
    ```

    Replace `PASSWORD` with the root password from step 22.

36. If the lab sends results to an STS, set its address in the same file:

    ```php
    $systemConfig['remoteURL'] = 'https://sts.example.org';
    ```

37. Save the file.

## Set up the web server

38. Open Notepad as administrator. Open
    `C:\Windows\System32\drivers\etc\hosts` and add this line at the end:

    ```text
    127.0.0.1 vlsm
    ```

39. Open `C:\wamp64\bin\apache`. Inside the folder that starts with `apache2.4`,
    open `conf\extra\httpd-vhosts.conf` and add:

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

    ??? info "If other computers in the lab open InteLIS on this machine"

        `Require local` accepts only this machine. Replace it with the lab
        network's address range, for example:

        ```apache
        Require ip 192.168.1
        ```

        Use `Require all granted` only on a machine that cannot be reached from
        outside the lab network. After the change, open InteLIS from a second
        computer to confirm.

40. Select the WampServer tray icon, then **Restart All Services**.

## Initialize InteLIS

41. In Command Prompt, run:

    ```bat
    cd C:\wamp64\www\vlsm
    set PATH=C:\wamp64\bin\php\php8.4.x;%PATH%
    php composer.phar post-install
    ```

42. Answer the STS questions at the end:

    | Question | Answer |
    | --- | --- |
    | Is this STS URL correct? | Press Enter (Yes). |
    | Enter STS URL (or press Enter to skip) | The STS address. Press Enter if the lab has no STS. |
    | Number to select, text to filter | Type the number of this lab, then press Enter. Type part of the lab name to shorten the list. |

    InteLIS asks only the questions that apply. With a single lab on the STS,
    it selects that lab without asking.

43. Wait for `STS setup complete!`.

    ??? info "If the lab has no STS"

        After Enter at the STS question, the run ends with
        `Setup complete. Configure STS URL before proceeding.` and Composer
        reports an error code for `sts-setup`. Every earlier part of
        `post-install` has finished. Continue with step 44.

    ??? failure "If it prints `Cannot connect to STS at this URL`"

        Check the address and the internet connection, then type the address
        again. If it still fails, run `php composer.phar sts-setup` later to
        repeat only the STS questions.

    ??? failure "If it prints `Metadata refresh failed`"

        The STS did not send the lab list. Check the internet connection, then
        run `php composer.phar sts-setup`.

## Complete setup in the browser

44. Open <http://vlsm>. The setup page opens.
45. Under **Database Setup**, check the values match `config.production.php`.
    Select **Next**.
46. Fill in **Instance Setup**, then select **Next**:

    | Field | Value |
    | --- | --- |
    | Instance type | **LIS with Remote Ordering Enabled** if the lab sends results to an STS. **Standalone (no Remote Ordering)** if it does not. |
    | STS URL | The STS address. It is filled in from `config.production.php`. |
    | Testing lab | The lab chosen in step 42. If the list is empty, select the refresh button next to the STS URL. |
    | Choose Modules to Enable | The tests this lab runs. |
    | Country of installation | The country the lab is in. |
    | Timezone | The lab's time zone. |
    | Choose System Language | The language of the screens. |

47. Fill in **Admin Setup** with the email, full name, login ID and password of
    the lab's first administrator. Select **Finish**.
48. Log in with that administrator account.

**Check:** the dashboard opens, and the page footer shows the version, for
example `v5.7.72`.

## Register the system administrator

49. Open <http://vlsm/system-admin>. The **Register new System Admin** page
    opens.
50. Open `C:\wamp64\www\vlsm\var\secret-key.txt` in the text editor. Copy the
    key.
51. Paste the key into **Secret Key**. Fill in the user name, email, login ID
    and password. Select **Submit**.
52. Sign out of the system admin area.

## Schedule background tasks

53. Open **Task Scheduler** and select **Create Task**. Name it
    `InteLIS Task`.
54. On the **General** tab, select **Run whether user is logged on or not**.
55. On the **Triggers** tab, create a trigger:

    - Select **Daily**.
    - Select **Repeat task every** and set it to 1 minute, for a duration of
      **Indefinitely**.
    - Select **Stop task if it runs longer than** and keep 3 days.

56. On the **Actions** tab, create an action:

    | Field | Value |
    | --- | --- |
    | Program/script | `C:\wamp64\bin\php\php8.4.x\php.exe` |
    | Add arguments | `C:\wamp64\www\vlsm\vendor\bin\crunz schedule:run` |
    | Start in | `C:\wamp64\www\vlsm` |

    **Start in** is required. Without it, the task runs but does nothing: no
    backups, no synchronisation and no result imports.

57. Select **OK** and enter the Windows password when asked.

**Check:** after two minutes, the file `C:\wamp64\www\vlsm\var\.cron_heartbeat`
shows a modified time from the last minute or two.

## Connect instruments

58. If the lab connects instruments, follow
    [Connect an Instrument](setting-up-interfacing-tool.md).

    ??? info "Differences on Windows"

        - `intelis interface setup` does not exist on Windows. Where the guide
          offers it, use its steps for doing the same work by hand.
        - Create the `interfacing` database by importing
          `C:\wamp64\www\vlsm\sql\interface-init.sql` in phpMyAdmin.
        - Run the guide's SQL in phpMyAdmin's **SQL** tab.
        - Edit `C:\wamp64\www\vlsm\configs\config.production.php` instead of
          the Ubuntu path.
        - Skip the Ubuntu-only commands: `nano`, `setfacl`, `ufw` and
          `systemctl`.
