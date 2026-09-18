# Browser Shows PHP Code Instead of InteLIS

The browser shows text starting with `<?php declare(strict_types=1);`, or offers
a file to download, instead of the InteLIS sign-in page.

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

    ??? tip "To look without changing anything"

        Add `--check`. The doctor then only reports what it finds.

        ```bash
        sudo intelis doctor --check
        ```

2. Answer its questions. It asks before each repair.
3. Reload the browser when the doctor reports that the site works again.

    ??? failure "If the site still does not open"

        The doctor puts `site-report.txt` on the Desktop, or in the home folder
        when there is no Desktop. Send that file when asking for help.
        Passwords are removed from it.

4. Run an update once the sign-in page loads:

    ```bash
    intelis update
    ```

    An interrupted setup or release upgrade can also leave file ownership and
    database migrations unfinished. The update settles both. See
    [Updating InteLIS on Ubuntu](updating-intelis-on-ubuntu.md).

## If the site opens but shows an error

The web server works and the fault is in the application, most often the
database. The doctor detects this and offers to run the database doctor. To go
straight there, run:

```bash
sudo intelis fix-database
```

See [MySQL will not start](mysql-will-not-start.md).

??? info "What the doctor fixes"

    Apache is serving `index.php` as a file instead of running it. This usually
    follows an Ubuntu release upgrade, an `apt upgrade` that changed the PHP
    version, or an interrupted setup. The database and uploaded files are not
    affected.

    Three faults show the same page:

    - **Apache has no PHP module.** The PHP module works only under the
      `prefork` worker. Ubuntu enables the `event` worker by default.
    - **The PHP packages cannot be installed.** A release upgrade disables the
      PHP repository, so apt has nothing to install.
    - **Apache has the right settings but never loaded them.** Every setting
      check passes. Only a restart of Apache fixes it.

    The doctor requests a page from the site and reads the answer, so it finds
    the third fault too. It installs PHP 8.4, or 8.5 on Ubuntu 26.04 and later.
