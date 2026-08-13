# Installing InteLIS with Docker

**Prerequisites:** [Docker](https://docs.docker.com/get-docker/) and [Docker Compose](https://docs.docker.com/compose/install/) must be installed on your system.

Docker is the quickest way to get InteLIS running. The traditional setup
(`setup.sh`) installs and configures PHP, Apache, MySQL, Composer, virtual
hosts, cron jobs, file permissions and MySQL tuning; Docker does all of that in
one command.

!!! note "Docker installs are updated differently"

    A container is not updated by `upgrade.sh`. That script manages the operating
    system around the application — apt packages, PHP versions, systemd units,
    MySQL tuning — none of which a container owns. Use
    [`scripts/docker-upgrade.sh`](#updating-intelis) instead, which pulls the new
    code, refreshes dependencies and restarts the stack so the entrypoint runs
    migrations.

    One consequence today: **remote upgrades from the STS are not offered to a
    containerised instance.** The command plane's runner drives `upgrade.sh`, so
    a container declines to advertise the verb rather than accept a command it
    cannot honour. Remote updates for Docker installs are a solvable problem —
    the runner would need to sit on the host and call `docker-upgrade.sh` — and
    it has not been built yet.

    Two things to keep in mind about backups. `remote-backup.sh` recognises an
    installation by finding `configs/config.production.php` and `public/` on the
    machine it runs on, and MySQL's data lives in the `intelis_db_data` volume
    rather than the installation folder — so what protects you is the scheduled
    `db-tools` job writing dumps into `backups/db`. Both work because
    `docker-compose.yml` mounts the source into the container, putting those
    paths on the host. Keep that mount.

## Installation Steps

### 1. Clone the Repository

```bash
git clone https://github.com/deforay/intelis.git
cd intelis
```

### 2. Configure Environment Variables

Copy the example environment file and edit it:

```bash
cp .env.example .env
```

`MYSQL_ROOT_PASSWORD` is deliberately empty in the example. Set it before
starting anything:

```ini
DOMAIN=intelis
APACHE_PORT=80

MYSQL_ROOT_PASSWORD=your_secure_password
MYSQL_PORT=3306
MYSQL_DATABASE=vlsm
```

`APACHE_PORT` and `MYSQL_PORT` are **host** ports only — the ports you reach the
containers on from this machine. Inside the containers Apache always listens on
80 and MySQL on 3306. Change them when something already holds those ports
locally, which on a developer machine is common.

### 3. Start the Containers

```bash
docker compose up -d
```

This starts two services:

- **intelis** — PHP 8.4 / Apache application server
- **intelis-db** — MySQL 8.4 database server

The entrypoint script automatically handles everything that `setup.sh` does manually:

- Configures Apache virtual host and `/etc/hosts`
- Initializes the main database from `sql/init.sql`
- Creates and configures the interfacing database (if enabled)
- Generates `config.production.php` with the correct database credentials
- Installs Composer dependencies if they are absent. The image installs them
  during the build, but the compose file mounts your working copy over
  `/var/www/html` and hides that copy, so on a fresh clone the entrypoint
  installs them into the mount instead
- Runs database migrations (`composer post-update`) then generates Audit Trail v2
  triggers (`composer db:repair`, which calls `bin/setup/regenerate-audit-triggers.php
  --apply install` + `bin/reset-seq.php`)
- Executes any run-once scripts
- Starts the cron service for background tasks
- Starts Apache in the foreground

### 4. Access InteLIS

Once the containers are running, open your browser at the `APACHE_PORT` you set:

```text
http://localhost/          # APACHE_PORT=80
http://localhost:8080/     # APACHE_PORT=8080
```

InteLIS then prompts you to finalize the configuration and create an
administrator account.

The first start takes a few minutes: it initialises the database, runs every
migration, and installs dependencies. Watch it with
`docker compose logs -f intelis` and wait for Apache's
`resuming normal operations`.

### 5. Check it came up clean

```bash
docker compose exec intelis intelis check
```

Every line should say PASS. Anything that says FAIL prints the command that
fixes it.

The `intelis` command works inside the container exactly as it does on an Ubuntu
install, so `intelis backup status`, `intelis health` and the rest all apply —
run them with `docker compose exec intelis intelis <command>`.

## Environment Variables Reference

| Variable               | Default          | Description                          |
| ---------------------- | ---------------- | ------------------------------------ |
| `DOMAIN`               | `intelis`        | Application domain name              |
| `APACHE_PORT`          | `80`             | Host port for the web server         |
| `MYSQL_ROOT_PASSWORD`  | `root_password`  | MySQL root password                  |
| `MYSQL_PORT`           | `3306`           | Host port for MySQL                  |
| `MYSQL_DATABASE`       | `vlsm`           | Main database name                   |
| `INTERFACING_ENABLED`  | `true`           | Enable interfacing database          |
| `INTERFACE_DB_HOST`    | `intelis-db`     | Interfacing DB host                  |
| `INTERFACE_DB_PORT`    | `3306`           | Interfacing DB port                  |
| `INTERFACE_DB_USER`    | `root`           | Interfacing DB username              |
| `INTERFACE_DB_PASSWORD`| *(root password)*| Interfacing DB password              |
| `INTERFACE_DB_NAME`    | `interfacing`    | Interfacing database name            |

## Updating InteLIS

On a traditional Ubuntu installation, updating requires running `upgrade.sh` — a ~1200-line script that handles Ubuntu package updates, PHP version switching, OPcache configuration, MySQL performance tuning (buffer pool sizing based on RAM, SSD detection, slow query logs), Composer updates, Apache config validation, database backups, vendor checksum verification, directory structure migrations, cron job setup, run-once scripts, file permissions, and multi-instance coordination.

With Docker, updating is one command:

```bash
cd intelis
sudo ./scripts/docker-upgrade.sh
```

It backs up the database first, downloads the current release over the
installation (leaving `.env`, `configs/`, uploads, `var/` and `backups/` alone),
refreshes Composer dependencies only when `composer.json` or `composer.lock`
changed, and restarts the stack so the entrypoint runs migrations and the
run-once scripts. `-b` skips the backup prompt; `-s` restarts without updating
the code, which is the way to re-run migrations on their own.

If you are working from a git checkout and want only the code, `git pull &&
docker compose up -d --build` does that much — but it skips the backup and the
dependency check.

The container rebuild picks up the new code, and the entrypoint script automatically runs database migrations, repairs, composer updates, and any run-once scripts — the same post-update tasks that `upgrade.sh` handles, without needing to worry about system-level configuration.

!!! tip
    The PHP configuration is baked into the image (`docker/php-apache/custom-php.ini`)
    and the MySQL one is mounted from `docker/mysql/my.cnf`, so neither needs tuning
    by hand. `docker/php-apache/dev-php.ini` is mounted on top for development: it
    turns OPcache revalidation back on, so an edit on your machine takes effect on
    the next request instead of waiting for a restart.

## Common Commands

```bash
# Start containers
docker compose up -d

# View logs
docker compose logs -f intelis

# Stop containers
docker compose down

# Rebuild — only for Dockerfile or dependency changes. Editing PHP needs
# nothing: the source is mounted and OPcache revalidates on every request.
docker compose up -d --build

# Access the application container shell
docker compose exec intelis bash

# Access MySQL CLI
docker compose exec intelis-db mysql -u root -p vlsm
```

## Data Persistence

The MySQL data is stored in a named Docker volume (`intelis_db_data`). Your data persists across container restarts and rebuilds.

To completely reset the database:

```bash
docker compose down -v
docker compose up -d
```

!!! warning
    `docker compose down -v` **deletes all database data**. Use it only for a fresh start.
