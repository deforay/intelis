---
description: Install InteLIS on a new Ubuntu LTS machine as a lab machine or a central STS server.
audience: [system-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
# Installing InteLIS on Ubuntu 24.04 or above (only Ubuntu LTS)

Install InteLIS on a new Ubuntu machine, as a lab machine or as a central server.

The machine must run **Ubuntu 24.04 LTS or a later LTS release** and be
connected to the internet. The account used needs `sudo` rights.

InteLIS requires **PHP 8.4+ (minimum 8.4.1)**. PHP 8.2, 8.3, and older
versions are not supported. The installer selects PHP 8.4, or 8.5 on Ubuntu
26.04 and newer.

To move an existing lab onto a new machine with its data, follow
[Migrating from one Ubuntu machine to another](migrating-ubuntu-machines.md)
instead.

**Choose the situation that fits, then follow its steps from top to bottom.**

=== "Lab machine (LIS)"

    ### Install

    1. Open a terminal and download the installer:

        ```bash
        cd ~ && wget -O setup.sh "https://raw.githubusercontent.com/deforay/intelis/master/scripts/setup.sh?v=$(date +%s)"
        ```

    2. Start the installer:

        ```bash
        sudo bash setup.sh
        ```

        ??? failure "If it stops with `This script requires an Ubuntu LTS release` or `requires Ubuntu 22.04 or newer`"

            The machine runs a non-LTS or too-old Ubuntu release. Reinstall the
            machine with Ubuntu 24.04 LTS or later, then start again from
            step 1.

    3. Answer the installer's questions:

        | Question | Answer |
        | --- | --- |
        | Installation directory | Press Enter. |
        | What is this machine? | **Lab machine (LIS)**. |
        | Remote STS URL | The STS address, for example `https://sts.example.org`. Ask the national programme for it. Leave it empty if the lab has no STS. |
        | New MySQL root password | A new password for this machine, typed twice. Write it down. |
        | Is this correct? | Check the summary, then press Enter (Yes). |

        ??? failure "If it says `Failed to validate the provided STS URL`"

            The installer could not reach `<STS address>/api/version.php`.
            Check the address for typing errors and type it again. After three
            failed tries the installer carries on without an STS. Enter the
            address later, in step 8.

        ??? info "If it asks `Reuse your previous setup answers and skip the prompts?`"

            An earlier run on this machine stopped part way. Press Enter (Yes)
            to use the same answers again.

        ??? info "If it does not ask for a MySQL password"

            MySQL is already set up on this machine. The installer reads its
            password from `/root/.my.cnf`.

        ??? info "If it says `An existing database was found`"

            Setup is being run again on a machine that already holds lab data.
            Choose **Keep a copy, then start fresh** to set the old data aside
            without losing it.

    4. Wait for the installer to finish. It checks the new installation, then
       ends with `Setup complete`.
    5. Read the `Install Check` list printed above `Setup complete`. Each
       line starts with one of these words:

        | Word | Meaning |
        | --- | --- |
        | `PASS` | The check succeeded. |
        | `WARN` | InteLIS runs, but the setting is wrong for a lab. The line says what to change, and most give the command that changes it. |
        | `FAIL` | InteLIS cannot run properly until this is fixed. The line gives the command that fixes it. |
        | `SKIP` | The check does not apply to this machine. |

        Run each command the list gives, then check again:

        ```bash
        intelis check
        ```

    ### Set up in the browser

    6. Open <http://intelis/> in a browser on this machine. From another
       computer on the network, use this machine's IP address instead. The
       **Database Setup** page opens.
    7. The database details are already filled in. Select **Next**.
    8. On **Instance Setup**, fill in:

        | Field | Answer |
        | --- | --- |
        | Instance type | **LIS with Remote Ordering Enabled**. If the lab has no STS, choose **Standalone (no Remote Ordering)**. |
        | STS URL | Already filled in from step 3. If it is empty, type the STS address. |
        | Testing lab | The lab this machine serves. The list comes from the STS. If it is empty, select the refresh button next to the STS URL. |
        | Modules to enable | The tests this lab runs. |
        | Country of installation | The country's request form. |
        | Timezone | The lab's time zone. |
        | System language | The language for the screens. |

    9. Select **Next**.
    10. On **Admin Setup**, enter the email ID, full name, login ID and
        password of the first administrator. The password needs at least 8
        characters, with at least one letter and one number.
    11. Select **Finish**. The login page opens.

    ### Set up backups

    12. In a terminal, choose where backups are sent:

        ```bash
        intelis backup setup
        ```

        Run it without `sudo`. It asks for the administrator password when it
        needs it. It offers another Linux machine, a shared Windows folder, or
        a USB drive. [Setting up off-machine backups](setting-up-off-machine-backups.md)
        walks through the questions for each destination.

    ### Check the lab

    13. Log in with the login ID and password from step 10.
    14. Check the lab settings under **Admin → System Configuration → General Configuration**.
    15. In a terminal, confirm the backups work:

        ```bash
        intelis backup status
        ```

    To update this machine later, follow
    [Updating InteLIS on Ubuntu](updating-intelis-on-ubuntu.md).

=== "Central server (STS)"

    ### Install

    1. Point the server's web address at this machine in DNS, for example
       `sts.health.gov.zm`. Labs use this address to reach the STS. To use the
       machine's IP address instead, skip this step.
    2. Open a terminal and download the installer:

        ```bash
        cd ~ && wget -O setup.sh "https://raw.githubusercontent.com/deforay/intelis/master/scripts/setup.sh?v=$(date +%s)"
        ```

    3. Start the installer:

        ```bash
        sudo bash setup.sh
        ```

        ??? failure "If it stops with `This script requires an Ubuntu LTS release` or `requires Ubuntu 22.04 or newer`"

            The machine runs a non-LTS or too-old Ubuntu release. Reinstall the
            machine with Ubuntu 24.04 LTS or later, then start again from
            step 2.

    4. Answer the installer's questions:

        | Question | Answer |
        | --- | --- |
        | Installation directory | Press Enter. |
        | What is this machine? | **Central server (STS)**. |
        | Web address of this server | The address from step 1, without `http://`. Press Enter if the labs use the IP address. |
        | New MySQL root password | A new password for this machine, typed twice. Write it down. |
        | Is this correct? | Check the summary, then press Enter (Yes). |

        ??? failure "If it says the address `already resolves to` another address"

            DNS points the web address at a different machine. Answer No to
            `Use … anyway?`. Then type the correct address, or press Enter to
            use the IP address.

        ??? info "If it asks `Reuse your previous setup answers and skip the prompts?`"

            An earlier run on this machine stopped part way. Press Enter (Yes)
            to use the same answers again.

        ??? info "If it does not ask for a MySQL password"

            MySQL is already set up on this machine. The installer reads its
            password from `/root/.my.cnf`.

        ??? info "If it says `An existing database was found`"

            Setup is being run again on a machine that already holds data.
            Choose **Keep a copy, then start fresh** to set the old data aside
            without losing it.

    5. Wait for the installer to finish. It checks the new installation, then
       ends with `Setup complete`.
    6. Read the `Install Check` list printed above `Setup complete`. Each
       line starts with one of these words:

        | Word | Meaning |
        | --- | --- |
        | `PASS` | The check succeeded. |
        | `WARN` | InteLIS runs, but the setting is wrong for a server. The line says what to change, and most give the command that changes it. |
        | `FAIL` | InteLIS cannot run properly until this is fixed. The line gives the command that fixes it. |
        | `SKIP` | The check does not apply to this machine. |

        Run each command the list gives, then check again:

        ```bash
        intelis check
        ```

    ### Set up in the browser

    7. Open the web address from step 4 in a browser, for example
       `http://sts.health.gov.zm/`. If the labs use the IP address, open
       `http://` followed by the IP address. The **Database Setup** page opens.
    8. The database details are already filled in. Select **Next**.
    9. On **Instance Setup**, fill in:

        | Field | Answer |
        | --- | --- |
        | Instance type | **Sample Tracking System(STS)**. |
        | Modules to enable | The tests the labs run. |
        | Country of installation | The country's request form. |
        | Timezone | The country's time zone. |
        | System language | The language for the screens. |

    10. Select **Next**.
    11. On **Admin Setup**, enter the email ID, full name, login ID and
        password of the first administrator. The password needs at least 8
        characters, with at least one letter and one number.
    12. Select **Finish**. The login page opens.

    ### Set up backups

    13. In a terminal, choose where backups are sent:

        ```bash
        intelis backup setup
        ```

        Run it without `sudo`. It asks for the administrator password when it
        needs it. It offers another Linux machine, a shared Windows folder, or
        a USB drive. [Setting up off-machine backups](setting-up-off-machine-backups.md)
        walks through the questions for each destination.

    ### Check the server

    14. Log in with the login ID and password from step 11.
    15. Check the settings under **Admin → System Configuration → General Configuration**.
    16. From a lab computer, open the web address from step 4. The InteLIS
        login page appears.
    17. In a terminal, confirm the backups work:

        ```bash
        intelis backup status
        ```

    To update this machine later, follow
    [Updating InteLIS on Ubuntu](updating-intelis-on-ubuntu.md).
