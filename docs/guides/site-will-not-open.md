---
description: Use intelis doctor to find out why the InteLIS site will not open, repair it, and collect a report when it cannot.
audience: [system-admin, lab-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-10-23
reviewed_against: 5.8.2
---
# Site Will Not Open: Using the Doctor

`intelis doctor` finds out why the InteLIS site will not open in the browser.
It explains what it found and asks before each repair. When the database is at
fault, it runs the database doctor itself.

Run it on the InteLIS machine, in a terminal. It does not work from another
computer.

**Choose what the browser shows, then follow its steps from top to bottom.**

=== "An error page"

    The browser shows `System Temporarily Unavailable`, `Internal Server Error`,
    or a blank page. phpMyAdmin may show `The mysqli extension is missing`.

    1. Open a terminal on the InteLIS machine and run:

        ```bash
        sudo intelis doctor
        ```

        ??? info "If `intelis` is not recognised, or `doctor` does not exist"

            The install is older. Run the same tool straight from the internet:

            ```bash
            sudo bash -c "$(wget -qO- https://raw.githubusercontent.com/deforay/intelis/master/scripts/intelis-doctor.sh)"
            ```

            Type it exactly as shown. Piping the download into `bash` instead
            feeds the script its own text as the answers to its questions.

    2. Enter the password for the account when asked.
    3. Read the box at the top. It names the problem, and the list under it
       shows each check with ✔ or ✘.
    4. Answer `y` at each `Do this now?` question under **Repairs**.

        ??? info "If it says `PHP is missing parts InteLIS needs`"

            The web server's PHP has not loaded an extension that InteLIS uses.
            Without `mysqli`, neither InteLIS nor phpMyAdmin can reach the
            database, however healthy the database is. The doctor installs the
            missing extensions, switches them on, and restarts the web server.

        ??? info "If it says `PHP was updated, but the web server is still running the old copy`"

            PHP was updated with `apt upgrade` and the web server was not
            restarted. The doctor restarts it. If it then prints
            `Run sudo intelis doctor again to repair that.`, run the doctor a
            second time.

        ??? info "If it says `This PHP was built without parts InteLIS needs`"

            The doctor cannot repair this. Reinstall PHP with the command it
            prints, for example:

            ```bash
            sudo switch-php 8.4 --force
            ```

        ??? info "If the box says `The database has stopped`"

            The doctor checks the database next, in a section headed
            **Database**. When none of the known causes match, it offers
            `Start the database again.` That start is the usual repair after a
            crash or a power cut. If the database refuses to start, the doctor
            prints the last errors it wrote. See
            [MySQL Will Not Start](mysql-will-not-start.md).

    5. Reload the browser when the doctor shows `InteLIS is working again.`

        ??? failure "If the site still does not open"

            Send the report. See [Send the report](#send-the-report).

=== "PHP code"

    The browser shows text starting with `<?php`, or offers a file to download,
    instead of the InteLIS sign-in page.

    1. Open a terminal on the InteLIS machine and run:

        ```bash
        sudo intelis doctor
        ```

        ??? info "If `intelis` is not recognised, or `doctor` does not exist"

            The install is older. Run the same tool straight from the internet:

            ```bash
            sudo bash -c "$(wget -qO- https://raw.githubusercontent.com/deforay/intelis/master/scripts/intelis-doctor.sh)"
            ```

            Type it exactly as shown. Piping the download into `bash` instead
            feeds the script its own text as the answers to its questions.

    2. Enter the password for the account when asked.
    3. Read the box at the top. It names the problem, and the list under it
       shows each check with ✔ or ✘.
    4. Answer `y` at each `Do this now?` question under **Repairs**.

        The doctor installs the PHP module for the web server, restores the PHP
        package repository when an Ubuntu upgrade removed it, and restarts the
        web server. [Browser Shows PHP Code](browser-shows-php-code.md) explains
        each cause.

    5. Reload the browser when the doctor shows `InteLIS is working again.`

        ??? failure "If the site still does not open"

            Send the report. See [Send the report](#send-the-report).

    6. Run an update once the sign-in page loads:

        ```bash
        intelis update
        ```

        An interrupted setup or release upgrade can also leave file ownership
        and database migrations unfinished. The update settles both.

=== "Upgrade in progress"

    The browser shows `Upgrade in progress` long after an update finished or
    stopped.

    1. Open a terminal on the InteLIS machine and run:

        ```bash
        sudo intelis doctor
        ```

        ??? info "If `intelis` is not recognised, or `doctor` does not exist"

            The install is older. Run the same tool straight from the internet:

            ```bash
            sudo bash -c "$(wget -qO- https://raw.githubusercontent.com/deforay/intelis/master/scripts/intelis-doctor.sh)"
            ```

            Type it exactly as shown. Piping the download into `bash` instead
            feeds the script its own text as the answers to its questions.

    2. Enter the password for the account when asked.
    3. Answer `y` at `Take the site out of update mode.`
    4. Reload the browser.
    5. Run the update again, because the earlier one did not finish:

        ```bash
        intelis update
        ```

        ??? failure "If the update stops at `Running database migrations...`"

            See [Update Stuck at Database Migrations](update-stuck-at-migrations.md).

=== "Nothing loads"

    The browser says the site cannot be reached, the connection was refused,
    or it keeps loading.

    1. Open a terminal on the InteLIS machine and run:

        ```bash
        sudo intelis doctor
        ```

        ??? info "If `intelis` is not recognised, or `doctor` does not exist"

            The install is older. Run the same tool straight from the internet:

            ```bash
            sudo bash -c "$(wget -qO- https://raw.githubusercontent.com/deforay/intelis/master/scripts/intelis-doctor.sh)"
            ```

            Type it exactly as shown. Piping the download into `bash` instead
            feeds the script its own text as the answers to its questions.

    2. Enter the password for the account when asked.
    3. Read the box at the top. It names the problem, and the list under it
       shows each check with ✔ or ✘.
    4. Answer `y` at each `Do this now?` question under **Repairs**.

        ??? info "If it says `Another program has taken the address the site is served on`"

            A second web server holds port 80. If it was installed by mistake,
            answer `y` to stop it. If it was installed on purpose, answer `n`
            and ask for help, because stopping it breaks whatever uses it.

        ??? info "If it says `The web server's configuration has an error in it`"

            The doctor does not edit the web server configuration. It prints the
            file and line at fault. Send the report.

        ??? info "If it says `This machine has almost no disk space left`"

            The doctor lists the largest folders and deletes nothing. See
            [Free disk space](maintenance.md#free-disk-space).

    5. Reload the browser when the doctor shows `InteLIS is working again.`

        ??? failure "If the browser on another computer still cannot reach the site"

            The doctor tests the site from the InteLIS machine itself. If the
            site opens there and not on other computers, check the network
            cable, the machine's IP address, and the firewall.

## Send the report

Every run writes a report. Passwords are removed from it.

1. Open the **Files** app on the InteLIS machine.
2. Find `site-report.txt` on the Desktop, or in the home folder when there is no
   Desktop.
3. Attach it to the message asking for help.

When the doctor also checked the database, send `mysql-report.txt` as well.
It is in the same place.

??? info "Where the full reports are kept"

    The doctor keeps only its latest report:
    `/var/log/intelis-doctor-<timestamp>.log`. The database doctor writes
    `/var/log/intelis-mysql-doctor-<timestamp>.log`.

## Run the doctor without repairs

To look without changing anything, add `--check`:

```bash
sudo intelis doctor --check
```

To apply every safe repair without questions, for example from a remote
session, add `--yes`:

```bash
sudo intelis doctor --yes
```

`--yes` never stops another program that holds port 80. That decision needs a
person.

??? info "What the doctor checks"

    | Check | What the doctor does |
    | --- | --- |
    | The web server is installed and running | Starts it |
    | The web server configuration is valid | Prints the file and line, changes nothing |
    | Another program holds port 80 | Stops it, after asking |
    | The PHP package repository is available | Restores it |
    | The web server can run PHP | Installs and enables the PHP module |
    | The web server runs the installed PHP | Restarts the web server |
    | PHP has every extension InteLIS needs | Installs and enables the missing ones |
    | An update left the `Upgrade in progress` page on | Removes it |
    | The web server can read and write the InteLIS folders | Repairs ownership of `var` and `public/uploads` |
    | The disk has space left | Lists the largest folders, deletes nothing |
    | The database is running and answering | Checks why it stopped, then starts it |
    | The site answers without an error | Checks the database for the cause |

    The doctor requests a page from the site and reads the answer, so it also
    finds a web server that has the right settings but never loaded them.
