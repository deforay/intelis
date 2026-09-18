# Connect an Instrument to InteLIS

Send an analyzer's results into InteLIS through the Interfacing Tool, so nobody
types them in.

InteLIS must already be installed on the lab's Ubuntu machine. The analyzer must
be connected to the lab network.

**Choose the situation that fits, then follow its steps from top to bottom.**

=== "Tool on this machine"

    Use this when the Interfacing Tool runs on the InteLIS machine itself.

    ### Install the Interfacing Tool

    1. Open a terminal on the InteLIS machine and download the installer:

        ```bash
        cd ~ && wget -O install-interfacing.sh "https://raw.githubusercontent.com/deforay/intelis-interfacing/master/scripts/install.sh?v=$(date +%s)"
        ```

    2. Run it, and type the password when `sudo` asks for it:

        ```bash
        bash install-interfacing.sh
        ```

        ??? info "To install a particular version"

            Add the version at the end:

            ```bash
            bash install-interfacing.sh --tag v4.2.1
            ```

    ### Add the analyzer in the tool

    3. Open the Interfacing Tool from the applications menu.
    4. Sign in with Login ID `admin` and password `admin`. The first sign-in
       opens **Settings**.
    5. Under **System**, set **Auto-connect on startup** to **Yes**. With **No**,
       the tool stops listening after every restart until somebody signs in.
    6. Under **Instruments**, select **+ Add Instrument** and fill in these fields:

        | Field | Value |
        | --- | --- |
        | Connection Mode | **TCP Server** if the analyzer connects to this machine. **TCP Client** if this machine connects to the analyzer. The analyzer's manual says which. |
        | Communication Protocol | **ASTM**, **ASTM (with checksum)** or **HL7**, as set on the analyzer. |
        | IP Address | TCP Server: this machine's address on the lab network. TCP Client: the analyzer's address. |
        | Port Number | The port set on the analyzer. |
        | Analyzer Type | The analyzer model. |
        | Instrument Name/Code | A name for this analyzer. Step 15 gives InteLIS the same name. |

        ??? info "If the lab has more than one analyzer"

            Add each one here with its own port. One Interfacing Tool serves
            them all.

    7. Select **Save Settings**.
    8. Run one sample on the analyzer. Check that it appears under the received
       results on the tool's console.

        ??? failure "If the result does not appear in the tool"

            Read the log on the instrument's tab. `Server bound and listening on`
            means the tool is waiting and the analyzer has not connected. Follow
            the tool's own
            [troubleshooting guide](https://deforay.github.io/intelis-interfacing/guide/troubleshooting/).

            Do not carry on until the tool shows the result. InteLIS can only
            import what the tool holds.

    ### Connect InteLIS to the tool

    9. In the terminal, run:

        ```bash
        sudo intelis interface setup
        ```

        ??? info "If it shows **What is configured now**"

            Interfacing is set up already. To replace it, answer **Yes** to
            `Set interfacing up again, replacing this?`. To leave it as it is,
            press Enter.

        ??? failure "If it stops with `The InteLIS database is not reachable`"

            Run `sudo intelis fix-database`. Then repeat step 9.

        ??? failure "If `intelis` is not recognised"

            The install is older. Either
            [update InteLIS](updating-intelis-on-ubuntu.md) and repeat step 9,
            or set it up by hand:

            1. Open MySQL:

                ```bash
                sudo mysql
                ```

            2. Create the database and the account. Choose a long password in
               place of `A-LONG-PASSWORD`:

                ```sql
                CREATE DATABASE IF NOT EXISTS interfacing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                CREATE USER 'interfacing'@'localhost' IDENTIFIED BY 'A-LONG-PASSWORD';
                CREATE USER 'interfacing'@'127.0.0.1' IDENTIFIED BY 'A-LONG-PASSWORD';
                GRANT ALL PRIVILEGES ON interfacing.* TO 'interfacing'@'localhost';
                GRANT ALL PRIVILEGES ON interfacing.* TO 'interfacing'@'127.0.0.1';
                EXIT;
                ```

            3. Open the InteLIS settings file. On older installs, the folder
               is `/var/www/vlsm`:

                ```bash
                sudo nano /var/www/intelis/configs/config.production.php
                ```

            4. Set these lines, with the same password:

                ```php
                $systemConfig['interfacing']['enabled'] = true;
                $systemConfig['interfacing']['database']['host'] = '127.0.0.1';
                $systemConfig['interfacing']['database']['username'] = 'interfacing';
                $systemConfig['interfacing']['database']['password'] = 'A-LONG-PASSWORD';
                $systemConfig['interfacing']['database']['db'] = 'interfacing';
                $systemConfig['interfacing']['database']['port'] = 3306;
                ```

            5. Save with **Ctrl+O**, then close with **Ctrl+X**.

            Go on to step 12, and enter host `127.0.0.1`, port `3306`, database
            `interfacing`, username `interfacing` and that password. The tool
            creates its tables the first time it connects.

    10. Answer the questions:

        | Question | Answer |
        | --- | --- |
        | How does the Interfacing Tool store the results InteLIS should read? | Press Enter. **MySQL on this machine** is already selected. |
        | MySQL port | Press Enter (`3306`). |
        | Database name | Press Enter (`interfacing`). |
        | Where is the Interfacing Tool installed? | Press Enter. **On this machine** is already selected. |
        | Enter a MySQL administrator account to use? | Asked only when setup finds no account of its own. Press Enter (Yes), then type the MySQL `root` username and password. |
        | Name for the account the tool and InteLIS will use | Press Enter (`interfacing`). |
        | Reuse it, and set its password to the one chosen next? | Asked only when the account exists already. Answer **Yes** if an earlier interfacing setup made it. Otherwise press Enter (No) and choose another name. |
        | Password for that account | Press Enter. **Use a generated password** is already selected. |

        ??? info "To have InteLIS read the tool's SQLite file instead"

            This is the simplest route, but it carries results only. Instrument
            activity and daily usage reporting need MySQL.

            1. At the first question, choose **The tool's own SQLite file on this machine**.
            2. At `Which file?`, press Enter. The tool's file is already
               selected. If no file is found, open the tool once, then repeat
               step 9.
            3. If asked `Grant www-data just enough access to read it?`, press
               Enter (Yes).

            Setup creates no database and no account on this route. Do step 11,
            then skip steps 12 to 14 and go on to step 15.

        ??? info "If the interfacing database is on another server"

            Setup creates nothing on another server. The database and its
            account must exist there already.

            1. At the first question, choose **MySQL on another server**.
            2. Enter the server's address, the MySQL port and the database name.
            3. Enter the username and password of the account InteLIS connects
               as.

            Setup checks the connection and looks for the tool's `orders`
            table. In step 12, enter that server's details, not the values
            below.

    11. Wait for `interfacing is now switched on`. Setup then prints a table
        under **Enter these in the Interfacing Tool**. Keep the terminal open.

        ??? failure "If it prints **Still to do**"

            Run each command it lists, in order. If it says `This MySQL no
            longer offers mysql_native_password`, the tool may fail to connect
            in step 13. Contact support with the tool's version.

    ### Enter the database in the tool

    12. In the tool, open **Settings**, then **MySQL**. Enter the values setup
        printed:

        | Printed setting | Tool field |
        | --- | --- |
        | Host | **MySQL Host** |
        | Port | **MySQL Port** |
        | Database | **Database Name** |
        | Username | **Database User** |
        | Password | **Database Password** |

    13. Select **Test Connection**.

        ??? failure "If the tool cannot connect"

            Compare each value with the printed table. The password is also
            saved in `/var/www/intelis/configs/config.production.php`, on the
            line with `['interfacing']['database']['password']`.

    14. Select **Save Settings**.

    ### Register the analyzer in InteLIS

    15. Add the analyzer in InteLIS by following
        [How to set up instruments and interfacing](../user-guides/admin-instruments.md).
        Enter the tool's **Instrument Name/Code** as the **Machine Name**.

    ### Check a result arrives

    16. Run one sample on the analyzer, or re-send a finished result from the
        analyzer's screen.
    17. In the terminal, import it now rather than waiting a minute:

        ```bash
        sudo intelis interface
        ```

        Expect the result counted:

        ```text
        Connected to MySQL
        # of records from MySQL : 1
        Processing 1 filtered results from Interface Tool
        ```

        On the SQLite route, the first two lines read `Connected to sqlite` and
        `# of records from SQLITE3 : 1`.

        ??? failure "If it counts `0` records"

            The result has not reached the database InteLIS reads.

            - Check the tool's **Sync Status** column. If every result is
              `Pending`, repeat step 13.
            - Only final results and failed runs are read. A result the
              analyzer has not finalised is left in the tool.

        ??? failure "If it prints `Error while syncing interface results`"

            Open the newest file in `/var/www/intelis/var/logs` and search for
            the error. Send it to support if it does not name a fix.

    18. Open the sample in InteLIS. Check that the result is there.

        ??? failure "If the result is counted but not in InteLIS"

            The sample ID on the analyzer must match the sample code of a
            registered request in InteLIS, character for character. Register
            the request, or correct the ID on the analyzer, then repeat steps
            16 to 18.

    19. Leave the tool running. InteLIS now imports results every minute.

        ??? failure "If results arrive by hand but not on their own"

            The scheduler is not running. Check it is installed:

            ```bash
            sudo crontab -l | grep cron.sh
            ```

            Expect this line:

            ```text
            * * * * * cd /var/www/intelis && ./cron.sh
            ```

            If the line is missing, or calls `crunz` directly, see
            [Maintenance scripts](maintenance.md). If the line is there, check
            that this file's time is within the last minute:

            ```bash
            ls -l /var/www/intelis/var/.cron_heartbeat
            ```

=== "Tool on another computer"

    Use this when the Interfacing Tool runs on a different computer from
    InteLIS, such as a Windows computer beside the analyzer.

    ### Install the Interfacing Tool

    1. On the tool's computer, open a terminal and download the installer:

        ```bash
        cd ~ && wget -O install-interfacing.sh "https://raw.githubusercontent.com/deforay/intelis-interfacing/master/scripts/install.sh?v=$(date +%s)"
        ```

        ??? info "If the tool's computer runs Windows"

            Download the file ending in `-setup.exe` from the
            [releases page](https://github.com/deforay/intelis-interfacing/releases)
            and run it. Then go on to step 3.

    2. Run it, and type the password when `sudo` asks for it:

        ```bash
        bash install-interfacing.sh
        ```

        ??? info "To install a particular version"

            Add the version at the end:

            ```bash
            bash install-interfacing.sh --tag v4.2.1
            ```

    ### Add the analyzer in the tool

    3. Open the Interfacing Tool from the applications menu.
    4. Sign in with Login ID `admin` and password `admin`. The first sign-in
       opens **Settings**.
    5. Under **System**, set **Auto-connect on startup** to **Yes**. With **No**,
       the tool stops listening after every restart until somebody signs in.
    6. Under **Instruments**, select **+ Add Instrument** and fill in these fields:

        | Field | Value |
        | --- | --- |
        | Connection Mode | **TCP Server** if the analyzer connects to this computer. **TCP Client** if this computer connects to the analyzer. The analyzer's manual says which. |
        | Communication Protocol | **ASTM**, **ASTM (with checksum)** or **HL7**, as set on the analyzer. |
        | IP Address | TCP Server: this computer's address on the lab network. TCP Client: the analyzer's address. |
        | Port Number | The port set on the analyzer. |
        | Analyzer Type | The analyzer model. |
        | Instrument Name/Code | A name for this analyzer. Step 22 gives InteLIS the same name. |

        ??? info "If the lab has more than one analyzer"

            Add each one here with its own port. One Interfacing Tool serves
            them all.

    7. Select **Save Settings**.
    8. Run one sample on the analyzer. Check that it appears under the received
       results on the tool's console.

        ??? failure "If the result does not appear in the tool"

            Read the log on the instrument's tab. `Server bound and listening on`
            means the tool is waiting and the analyzer has not connected. Follow
            the tool's own
            [troubleshooting guide](https://deforay.github.io/intelis-interfacing/guide/troubleshooting/).

            Do not carry on until the tool shows the result. InteLIS can only
            import what the tool holds.

    9. Find this computer's address on the lab network, and write it down. On
       Ubuntu, run `hostname -I` and take the first address. On Windows, run
       `ipconfig` and take the **IPv4 Address**.

        ??? info "If this computer's address changes after a restart"

            Ask the network administrator to give it a fixed address first.
            InteLIS admits the tool from this one address only. A new address
            means repeating steps 10 to 21.

    ### Connect InteLIS to the tool

    10. On the InteLIS machine, open a terminal and run:

        ```bash
        sudo intelis interface setup
        ```

        ??? info "If it shows **What is configured now**"

            Interfacing is set up already. To replace it, answer **Yes** to
            `Set interfacing up again, replacing this?`. To leave it as it is,
            press Enter.

        ??? failure "If it stops with `The InteLIS database is not reachable`"

            Run `sudo intelis fix-database`. Then repeat step 10.

        ??? failure "If `intelis` is not recognised"

            The install is older. Either
            [update InteLIS](updating-intelis-on-ubuntu.md) and repeat step 10,
            or set it up by hand:

            1. Open MySQL:

                ```bash
                sudo mysql
                ```

            2. Create the database and the account. Put the address from step
               9 in place of `TOOL_IP`, and a long password in place of
               `A-LONG-PASSWORD`:

                ```sql
                CREATE DATABASE IF NOT EXISTS interfacing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                CREATE USER 'interfacing'@'localhost' IDENTIFIED BY 'A-LONG-PASSWORD';
                CREATE USER 'interfacing'@'127.0.0.1' IDENTIFIED BY 'A-LONG-PASSWORD';
                CREATE USER 'interfacing'@'TOOL_IP' IDENTIFIED BY 'A-LONG-PASSWORD';
                GRANT ALL PRIVILEGES ON interfacing.* TO 'interfacing'@'localhost';
                GRANT ALL PRIVILEGES ON interfacing.* TO 'interfacing'@'127.0.0.1';
                GRANT ALL PRIVILEGES ON interfacing.* TO 'interfacing'@'TOOL_IP';
                EXIT;
                ```

            3. Open the InteLIS settings file. On older installs, the folder
               is `/var/www/vlsm`:

                ```bash
                sudo nano /var/www/intelis/configs/config.production.php
                ```

            4. Set these lines, with the same password:

                ```php
                $systemConfig['interfacing']['enabled'] = true;
                $systemConfig['interfacing']['database']['host'] = '127.0.0.1';
                $systemConfig['interfacing']['database']['username'] = 'interfacing';
                $systemConfig['interfacing']['database']['password'] = 'A-LONG-PASSWORD';
                $systemConfig['interfacing']['database']['db'] = 'interfacing';
                $systemConfig['interfacing']['database']['port'] = 3306;
                ```

            5. Save with **Ctrl+O**, then close with **Ctrl+X**.
            6. Open the firewall to the tool's computer only:

                ```bash
                sudo ufw allow from TOOL_IP to any port 3306 proto tcp
                ```

            Then do steps 14 to 17. For the `bind-address`, use this machine's
            first address from `hostname -I`. In step 19, enter that address as
            the host, port `3306`, database `interfacing`, username
            `interfacing` and that password. The tool creates its tables the
            first time it connects.

    11. Answer the questions:

        | Question | Answer |
        | --- | --- |
        | How does the Interfacing Tool store the results InteLIS should read? | Press Enter. **MySQL on this machine** is already selected. |
        | MySQL port | Press Enter (`3306`). |
        | Database name | Press Enter (`interfacing`). |
        | Where is the Interfacing Tool installed? | **On another computer on the lab network**. |
        | Address of the computer running the tool | The address from step 9. Do not enter `%`, which admits every computer. |
        | Enter a MySQL administrator account to use? | Asked only when setup finds no account of its own. Press Enter (Yes), then type the MySQL `root` username and password. |
        | Name for the account the tool and InteLIS will use | Press Enter (`interfacing`). |
        | Reuse it, and set its password to the one chosen next? | Asked only when the account exists already. Answer **Yes** if an earlier interfacing setup made it. Otherwise press Enter (No) and choose another name. |
        | Password for that account | Press Enter. **Use a generated password** is already selected. |
        | Open port 3306 to the tool's address? | Asked only when the firewall is on and the port is closed. Press Enter (Yes). |

        ??? info "If the interfacing database is on another server"

            Setup creates nothing on another server. The database and its
            account must exist there already, and must admit the tool's
            computer.

            1. At the first question, choose **MySQL on another server**.
            2. Enter the server's address, the MySQL port and the database name.
            3. Enter the username and password of the account InteLIS connects
               as.

            Setup checks the connection and looks for the tool's `orders`
            table. Skip steps 13 to 18. In step 19, enter that server's
            details, not the values below.

    12. Wait for `interfacing is now switched on`. Setup then prints a table
        under **Enter these in the Interfacing Tool**. Write the five values
        down. The host is this machine's address on the lab network.

    ### Let the tool's computer reach MySQL

    13. Read the **Still to do** list under the table. If it has no line
        starting `Set bind-address`, go on to step 18.
    14. Open the MySQL settings file:

        ```bash
        sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
        ```

    15. Change the `bind-address` line to the address the note gives, then save
        with **Ctrl+O** and close with **Ctrl+X**. For example:

        ```ini
        bind-address = 192.168.1.10
        ```

        Do not use `0.0.0.0`. It offers the database to every network the
        machine is attached to.

    16. Check the file, then restart MySQL:

        ```bash
        sudo mysqld --validate-config && sudo systemctl restart mysql
        ```

        ??? failure "If `--validate-config` prints an error"

            MySQL was not restarted. Open the file again, correct the line the
            error names, and repeat step 16.

        ??? failure "If MySQL will not start"

            Follow [MySQL will not start](mysql-will-not-start.md), then come
            back to step 17.

    17. Check that MySQL listens on the lab address:

        ```bash
        sudo ss -lntp | grep 3306
        ```

        Expect the address from step 15, not `127.0.0.1`.

    18. If **Still to do** says port 3306 is closed, run the `sudo ufw allow`
        command it prints.

    ### Enter the database in the tool

    19. On the tool's computer, open **Settings**, then **MySQL**. Enter the
        values setup printed:

        | Printed setting | Tool field |
        | --- | --- |
        | Host | **MySQL Host** |
        | Port | **MySQL Port** |
        | Database | **Database Name** |
        | Username | **Database User** |
        | Password | **Database Password** |

    20. Select **Test Connection**.

        ??? failure "If the tool cannot connect"

            - Compare each value with the printed table. The password is also
              saved on the InteLIS machine in
              `/var/www/intelis/configs/config.production.php`, on the line
              with `['interfacing']['database']['password']`.
            - On the InteLIS machine, repeat the check in step 17.
            - On the InteLIS machine, run `sudo ufw status`. Port 3306 must be
              allowed from the address in step 9.
            - Run step 9 again on the tool's computer. If the address has
              changed, repeat steps 10 to 20 with the new one.
            - If **Still to do** said `This MySQL no longer offers
              mysql_native_password`, contact support with the tool's version.

    21. Select **Save Settings**.

    ### Register the analyzer in InteLIS

    22. Add the analyzer in InteLIS by following
        [How to set up instruments and interfacing](../user-guides/admin-instruments.md).
        Enter the tool's **Instrument Name/Code** as the **Machine Name**.

    ### Check a result arrives

    23. Run one sample on the analyzer, or re-send a finished result from the
        analyzer's screen.
    24. On the InteLIS machine, import it now rather than waiting a minute:

        ```bash
        sudo intelis interface
        ```

        Expect the result counted:

        ```text
        Connected to MySQL
        # of records from MySQL : 1
        Processing 1 filtered results from Interface Tool
        ```

        ??? failure "If it counts `0` records"

            The result has not reached the database InteLIS reads.

            - Check the tool's **Sync Status** column. If every result is
              `Pending`, repeat step 20.
            - Only final results and failed runs are read. A result the
              analyzer has not finalised is left in the tool.

        ??? failure "If it prints `Error while syncing interface results`"

            Open the newest file in `/var/www/intelis/var/logs` and search for
            the error. Send it to support if it does not name a fix.

    25. Open the sample in InteLIS. Check that the result is there.

        ??? failure "If the result is counted but not in InteLIS"

            The sample ID on the analyzer must match the sample code of a
            registered request in InteLIS, character for character. Register
            the request, or correct the ID on the analyzer, then repeat steps
            23 to 25.

    26. Leave the tool running. InteLIS now imports results every minute.

        ??? failure "If results arrive by hand but not on their own"

            The scheduler is not running. Check it is installed:

            ```bash
            sudo crontab -l | grep cron.sh
            ```

            Expect this line:

            ```text
            * * * * * cd /var/www/intelis && ./cron.sh
            ```

            If the line is missing, or calls `crunz` directly, see
            [Maintenance scripts](maintenance.md). If the line is there, check
            that this file's time is within the last minute:

            ```bash
            ls -l /var/www/intelis/var/.cron_heartbeat
            ```

        ??? info "To add a second tool computer"

            1. On the first tool computer, open **Settings**, then **Backup &
               Restore**, and select **Export Settings**. Choose **Settings
               and Credentials**.
            2. Install the tool on the second computer, as in steps 1 to 4.
            3. On the second computer, select **Import Settings** and open the
               exported file.
            4. Change the instrument names and ports to match the analyzers
               beside the second computer.
            5. On the InteLIS machine, repeat steps 10 to 12 with the second
               computer's address. Answer **Yes** to replace the current setup,
               and **Yes** to reuse the account. The first computer keeps its
               own password and goes on working.
            6. Open the firewall to the second computer. Setup does not offer
               this once port 3306 is open to the first one:

                ```bash
                sudo ufw allow from SECOND_TOOL_IP to any port 3306 proto tcp
                ```

            7. On the second computer, enter the newly printed values as in
               steps 19 to 21.
