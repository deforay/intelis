# Permission denied errors

InteLIS reports `Permission denied` when the web server cannot write to one of
its folders.

1. Open a terminal on the InteLIS machine and run:

    ```bash
    intelis check
    ```

    Each folder the web server cannot write to shows as a failed row, such as
    `var/cache writable`. A `How to fix` row follows with the commands to run.

    ??? info "If `intelis check` is not recognised"

        The install is older. Run the same check under its earlier name:

        ```bash
        intelis preflight
        ```

2. Run the repair it prints:

    ```bash
    intelis provision
    ```

    It creates missing folders and resets their ownership and permissions. It
    asks for the administrator password.

    ??? failure "If `intelis provision` fails because `var/cache` belongs to root"

        `intelis provision` starts the application to find its folders. A
        `var/cache` folder owned by root stops it before it can repair
        anything. This usually follows a `composer` command run as root.

        Give the folder back first. `intelis check` prints these lines with the
        exact folders under `if that cannot start`:

        ```bash
        sudo chown -R $(logname):www-data /var/www/intelis/var/cache
        sudo chmod -R g+w /var/www/intelis/var/cache
        ```

        Then run `intelis provision` again.

3. Run the check again:

    ```bash
    intelis check
    ```

    Every `writable` row passes.

    ??? failure "If a folder still fails"

        Run the full permission repair. It also resets ownership across the
        installation after a copy or a restore:

        ```bash
        sudo intelis-refresh -p /var/www/intelis -m full
        ```

        On older installs, use `/var/www/vlsm` in place of `/var/www/intelis`.

??? warning "Do not widen permissions across /var/www"

    A command such as `setfacl -R -m u:www-data:rwx /var/www` gives the web
    server write access to every file of every site on the machine. A flaw in
    any one site then reaches all of them. The error also returns after the next
    update, because the real fault is still there.

    If one folder must be shared with another account, grant access on that
    folder only.
