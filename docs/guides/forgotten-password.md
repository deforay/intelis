---
description: Set a new password for an InteLIS user who cannot sign in, including the only admin account, from a terminal on the InteLIS machine.
audience: [system-admin, lab-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-10-26
reviewed_against: 5.8.3
---
# Forgotten Password

Use this page when a user has forgotten their password and no admin can reset
it from the browser. It also covers the case where the only admin is the one
locked out. The reset runs in a terminal on the InteLIS machine. It needs no
other InteLIS account.

## Reset the password on Ubuntu

1. Open a terminal on the InteLIS machine, or connect to it over SSH.
2. Run:

    ```bash
    sudo intelis reset-password
    ```

    If it prints `Command "reset-password" is not defined`, the machine has an
    older `intelis` command. Run the script directly instead:

    ```bash
    cd /var/www/intelis
    sudo -u www-data php bin/reset-user-password.php
    ```

    On older installs the directory is `/var/www/vlsm`.

3. Type part of the login ID or name to filter the list of active users.
4. Select the account with the arrow keys and press Enter.
5. Check the login ID, name and role the script prints.
6. Press Enter at `Generate a strong password?`.
7. Write down the login ID and password the script prints.

To type a chosen password instead, enter `n` at step 6. The script asks for
it twice. A password has at least 8 characters, with at least one letter and one
number.

## Reset the password on Windows

1. Open `C:\wamp64\bin\php` in File Explorer. Note the name of the folder that
   starts with `php8.4`, for example `php8.4.1`. The steps below call it
   `php8.4.x`.
2. Open **Command Prompt**.
3. Run the reset script. Replace `php8.4.x` with the folder name from step 1:

    ```bat
    cd C:\wamp64\www\vlsm
    set PATH=C:\wamp64\bin\php\php8.4.x;%PATH%
    php bin\reset-user-password.php
    ```

4. Type the number of the account and press Enter.
5. Press Enter at `Generate a strong password?`.
6. Write down the login ID and password the script prints.

On Windows, a typed password shows on screen. Use the generated password at
step 5.

## Common variations

| Situation | Command |
| --- | --- |
| The login ID is known | `sudo intelis reset-password --login admin` |
| The account is inactive, so it is missing from the list | `sudo intelis reset-password --login admin --activate` |
| The user must choose a new password after signing in | Add `--force-reset` to any command above |

Replace `admin` with the account's login ID. The direct script and the Windows
command take the same options.

!!! warning "Do not edit the password in phpMyAdmin"

    InteLIS stores a hash of the password, not the password. A value typed into
    the `password` column of `user_details` does not work as a password.

## Check that it worked

Sign in at the InteLIS login page with the login ID and password the script
printed:

```text
Password reset successfully.
Lab Admin signs in with:

    Login ID   admin
    Password   Xk4mT9pq27RwLa83
```

If the login page shows a captcha code, type it in. The page asks for one after
three failed attempts.

The new password stays after the next STS sync. STS sync never changes a lab
user's password.

After the admin signs in, the admin resets other users' passwords from
**Admin > Access Control > Users**.
