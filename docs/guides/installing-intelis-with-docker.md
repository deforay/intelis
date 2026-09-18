# Installing InteLIS with Docker

Run InteLIS in Docker containers, on a lab server or on a developer machine.

The machine needs [Docker](https://docs.docker.com/get-docker/) with the
[Docker Compose](https://docs.docker.com/compose/install/) plugin, `git`, and
an internet connection. The lab server steps assume an Ubuntu host.

**Choose the situation that fits, then follow its steps from top to bottom.**

=== "Lab server"

    ### Install

    1. Open a terminal and download InteLIS:

        ```bash
        cd ~ && git clone https://github.com/deforay/intelis.git
        ```

    2. Create the settings file:

        ```bash
        cd ~/intelis && cp .env.example .env
        ```

    3. Open the settings file:

        ```bash
        nano .env
        ```

    4. Set a new MySQL root password on the `MYSQL_ROOT_PASSWORD=` line. Write
       it down. Do not change it after the first start.

        ```ini
        MYSQL_ROOT_PASSWORD=choose-a-strong-password
        ```

        If the line is left empty, the database uses the password
        `root_password`.

        ??? info "If port 80 or 3306 is already in use on this machine"

            Change `APACHE_PORT` or `MYSQL_PORT`, for example to `8080` or
            `3307`. These are the ports on this machine only. Inside the
            containers, Apache always listens on 80 and MySQL on 3306.

        ??? info "All settings in `.env`"

            | Setting | Default | What it sets |
            | --- | --- | --- |
            | `DOMAIN` | `intelis` | The name Apache answers to. Any other address that reaches the server also works. |
            | `APACHE_PORT` | `80` | The port on this machine for the web server. |
            | `UBUNTU_VERSION` | `24.04` | The Ubuntu release the container image is built on. |
            | `PHP_VERSION` | `8.4` | The PHP version installed in the container image. |
            | `MYSQL_ROOT_PASSWORD` | `root_password` when empty | The MySQL root password. |
            | `MYSQL_PORT` | `3306` | The port on this machine for MySQL. |
            | `MYSQL_DATABASE` | `vlsm` | The main database name. |
            | `INTERFACING_ENABLED` | `true` | Creates the interfacing database. |
            | `INTERFACE_DB_HOST` | `intelis-db` | The interfacing database host. |
            | `INTERFACE_DB_PORT` | `3306` | The interfacing database port. |
            | `INTERFACE_DB_USER` | `root` | The interfacing database user. |
            | `INTERFACE_DB_PASSWORD` | `MYSQL_ROOT_PASSWORD` when empty | The interfacing database password. |
            | `INTERFACE_DB_NAME` | `interfacing` | The interfacing database name. |

            A change to `UBUNTU_VERSION` or `PHP_VERSION` takes effect only
            after `docker compose up -d --build`.

    5. Save the file with **Ctrl+O**, press Enter, then close it with **Ctrl+X**.
    6. Start InteLIS:

        ```bash
        docker compose up -d
        ```

        ??? failure "If it says `permission denied` for `docker.sock`"

            The account is not allowed to run Docker. Put `sudo` in front of
            every `docker compose` command on this page.

    7. Follow the first start:

        ```bash
        docker compose logs -f intelis
        ```

        The first start builds the container image, creates the databases
        and runs every migration. It takes several minutes. Wait for a line
        that starts with `Run-once:`, then press **Ctrl+C** to stop following.
        The containers keep running.

        ??? failure "If the log shows `Access denied for user 'root'`"

            The password in `.env` differs from the one the database was
            first started with. The database keeps its first password. Put
            that password back in `.env`, then run `docker compose up -d`
            again.

    8. Check the installation:

        ```bash
        docker compose exec intelis intelis check
        ```

        Each line starts with one of these words:

        | Word | Meaning |
        | --- | --- |
        | `PASS` | The check succeeded. |
        | `WARN` | InteLIS runs, but the setting is wrong for a lab. The line says what to change. |
        | `FAIL` | InteLIS cannot run properly until this is fixed. The line gives the command that fixes it. |
        | `SKIP` | The check does not apply to this machine. |

        Run each `intelis` command it gives inside the container, as
        `docker compose exec intelis intelis <command>`. Then check again.

    ### Set up in the browser

    9. From a computer on the network, open `http://` followed by the
       server's IP address, for example `http://192.168.1.20/`. On the server
       itself, open <http://localhost/>. If `APACHE_PORT` is not 80, add it
       after the address, for example `http://192.168.1.20:8080/`. The
       **Database Setup** page opens.
    10. The database details are already filled in. Select **Next**.
    11. On **Instance Setup**, fill in:

        | Field | Answer |
        | --- | --- |
        | Instance type | **LIS with Remote Ordering Enabled**. If the lab has no STS, choose **Standalone (no Remote Ordering)**. |
        | STS URL | The STS address, for example `https://sts.example.org`. Ask the national programme for it. |
        | Testing lab | The lab this server serves. Select the refresh button next to the STS URL to load the list from the STS. |
        | Modules to enable | The tests this lab runs. |
        | Country of installation | The country's request form. |
        | Timezone | The lab's time zone. |
        | System language | The language for the screens. |

    12. Select **Next**.
    13. On **Admin Setup**, enter the email ID, full name, login ID and
        password of the first administrator. The password needs at least 8
        characters, with at least one letter and one number.
    14. Select **Finish**. The login page opens.

    ### Set up backups

    The container saves a database backup every six hours into
    `~/intelis/backups/db` on the server. Copying it off the server runs on
    the server itself, outside the containers.

    15. In a terminal on the server, start the backup setup:

        ```bash
        cd ~/intelis && sudo bash scripts/remote-backup.sh
        ```

    16. At `InteLIS folder path`, type the full path of the `intelis` folder,
        for example `/home/labadmin/intelis`. The command `echo ~/intelis`
        prints it.
    17. Answer the remaining questions. [Setting up off-machine backups](setting-up-off-machine-backups.md)
        walks through them for each destination.

    ### Check the lab

    18. Log in with the login ID and password from step 13.
    19. Check the lab settings under **Admin → System Configuration → General Configuration**.
    20. In a terminal on the server, confirm the off-server backups work:

        ```bash
        sudo /usr/local/bin/intelis-backup.sh --status
        ```

    !!! danger "Never run `docker compose down -v` on a lab server"

        The `-v` deletes the database volume and every record in it, with no
        prompt and no undo.

=== "Update a lab server"

    Use this on a lab server installed with Docker.

    1. Open a terminal and go to the InteLIS folder:

        ```bash
        cd ~/intelis
        ```

    2. Take a fresh backup:

        ```bash
        docker compose exec intelis intelis backup
        ```

        Wait for `Database and settings saved on this machine`. When it then
        asks `Set up off-machine backups now?`, press Enter (No). The
        off-server copy runs outside the containers.

    3. Start the update:

        ```bash
        sudo ./scripts/docker-upgrade.sh -b
        ```

        `-b` skips the script's own backup question, because step 2 already
        took a backup. The script:

        - downloads the newest code on the `master` branch of
          `github.com/deforay/intelis` over this folder
        - leaves `.env`, `configs/config.production.php`,
          `docker-compose.override.yml`, `public/uploads/`, `public/temporary/`,
          `var/` and `backups/` untouched
        - refreshes the Composer dependencies only when `composer.json` or
          `composer.lock` changed
        - restarts the containers, which runs the migrations and the run-once
          scripts

    4. Wait for `Upgrade complete!`.
    5. Follow the restart:

        ```bash
        docker compose logs -f intelis
        ```

        Wait for a line that starts with `Run-once:`, then press **Ctrl+C**.

        ??? failure "If the update stopped part way"

            Restart the containers and run the migrations again, without
            downloading the code a second time:

            ```bash
            sudo ./scripts/docker-upgrade.sh -b -s
            ```

            If it fails again, save the log and send it to support:

            ```bash
            docker compose logs intelis > update-log.txt
            ```

    6. Check the installation:

        ```bash
        docker compose exec intelis intelis check
        ```

        A `FAIL` line gives the command that fixes it. Run it as
        `docker compose exec intelis intelis <command>`.

    7. Log in to InteLIS in the browser.

=== "Developer machine"

    ### Start

    1. Download the code:

        ```bash
        git clone https://github.com/deforay/intelis.git
        cd intelis
        ```

    2. Create the settings file:

        ```bash
        cp .env.example .env
        ```

    3. Set `MYSQL_ROOT_PASSWORD` in `.env`. If it is left empty, the database
       uses the password `root_password`.
    4. If port 80 or 3306 is already in use, change `APACHE_PORT` or
       `MYSQL_PORT` in `.env`. These are the ports on the host only. Inside
       the containers, Apache always listens on 80 and MySQL on 3306.

        | Setting | Default | What it sets |
        | --- | --- | --- |
        | `DOMAIN` | `intelis` | The name Apache answers to. `localhost` also works. |
        | `APACHE_PORT` | `80` | The host port for the web server. |
        | `UBUNTU_VERSION` | `24.04` | The Ubuntu release the container image is built on. |
        | `PHP_VERSION` | `8.4` | The PHP version installed in the container image. |
        | `MYSQL_ROOT_PASSWORD` | `root_password` when empty | The MySQL root password. |
        | `MYSQL_PORT` | `3306` | The host port for MySQL. |
        | `MYSQL_DATABASE` | `vlsm` | The main database name. |
        | `INTERFACING_ENABLED` | `true` | Creates the interfacing database. |
        | `INTERFACE_DB_HOST` | `intelis-db` | The interfacing database host. |
        | `INTERFACE_DB_PORT` | `3306` | The interfacing database port. |
        | `INTERFACE_DB_USER` | `root` | The interfacing database user. |
        | `INTERFACE_DB_PASSWORD` | `MYSQL_ROOT_PASSWORD` when empty | The interfacing database password. |
        | `INTERFACE_DB_NAME` | `interfacing` | The interfacing database name. |

    5. Start the containers:

        ```bash
        docker compose up -d
        ```

        This starts two services: `intelis` (Ubuntu with PHP and Apache) and
        `intelis-db` (MySQL 8.4).

    6. Follow the first start:

        ```bash
        docker compose logs -f intelis
        ```

        Wait for a line that starts with `Run-once:`, then press **Ctrl+C**.
        On a fresh clone, the first start also installs the Composer
        dependencies into the working copy.

    7. Open <http://localhost/>, or `http://localhost:<APACHE_PORT>/` when
       `APACHE_PORT` is not 80.
    8. Complete the browser setup. On **Database Setup**, select **Next**. On
       **Instance Setup**, choose **Standalone (no Remote Ordering)** unless an
       STS is available. On **Admin Setup**, create the administrator, then
       select **Finish**.

    ### Work on the code

    9. Edit files in the working copy. The compose file mounts it into the
       container at `/var/www/html`. An edited PHP file takes effect on the
       next request.

        `docker-compose.yml` mounts `docker/php-apache/dev-php.ini` on every
        Docker install, lab servers included. It turns OPcache revalidation
        on and shows PHP errors on screen.

    10. Run `intelis` commands inside the container:

        ```bash
        docker compose exec intelis intelis migrate
        ```

    11. Open a shell or the MySQL client when needed:

        ```bash
        docker compose exec intelis bash
        docker compose exec intelis-db mysql -u root -p vlsm
        ```

    12. After a `git pull`, restart the application container so it runs the
        new migrations:

        ```bash
        docker compose restart intelis
        ```

    13. After a change to the `Dockerfile`, `UBUNTU_VERSION` or `PHP_VERSION`,
        rebuild the image:

        ```bash
        docker compose up -d --build
        ```

    14. Stop the containers:

        ```bash
        docker compose down
        ```

    ### Start over with an empty database

    15. List the database volume and the latest backups, and confirm nothing
        in them is needed:

        ```bash
        docker volume ls | grep intelis_db_data
        ls -lt backups/db | head
        ```

    16. Delete the containers and the database volume:

        ```bash
        docker compose down -v
        ```

        !!! danger "This deletes the database"

            `-v` deletes the database volume and every record in it, with no
            prompt and no undo.

    17. Start again. The first start creates a fresh database:

        ```bash
        docker compose up -d
        ```
