# Lab VM

An Ubuntu machine in a container, for testing the things that only happen on a
lab machine.

## Why this exists

`docker-compose.yml` runs InteLIS as an application container with MySQL
alongside it as a second container. That is the right shape for running the
application, and the wrong shape for testing `setup.sh` or `upgrade.sh`, because
both manage the operating system *around* the application: apt packages, the PHP
version, service restarts, and MySQL's configuration and data directory.

A remote upgrade queued from the STS against the compose stack fails on exactly
that — a `chown` against a `mysql` user that is not there, then a restart of a
service that was never local. Not a bug: the database is another machine.

This image runs systemd as PID 1 with MySQL, Apache and PHP all installed on the
same machine by `setup.sh`, the way a lab is. `upgrade.sh` cannot tell it from a
real one, which is the point.

## Running it

```bash
docker build -t intelis-labvm dev/lab-vm
docker run -d --privileged --cgroupns=host \
    -v /sys/fs/cgroup:/sys/fs/cgroup:rw \
    -p 8082:80 --name labvm intelis-labvm
```

`--privileged` and the cgroup mount are what let systemd start. Without them
`systemctl` reports "Failed to connect to bus" and every service test is
meaningless.

Confirm it booted before going further:

```bash
docker exec labvm systemctl is-system-running   # "running" or "degraded"
```

## Installing InteLIS in it

`setup.sh` asks questions. Two files answer them, so a run is unattended:

```bash
docker exec labvm bash -c '
mkdir -p /usr/local/lib/intelis
cat > /usr/local/lib/intelis/setup-answers.env <<ANSWERS
lis_path="/var/www/intelis"
hostname="intelis"
is_lis=true
is_sts=false
DB_STRATEGY_FLAG=""
remote_sts_url="https://your-sts.example.org"
run_maintenance_scripts=false
maintenance_scripts_mode=""
ANSWERS
chmod 600 /usr/local/lib/intelis/setup-answers.env

# The MySQL root password prompt is skipped when ~/.my.cnf already has one.
printf "[client]\nuser=root\npassword=ChangeMe@2026\n" > /root/.my.cnf
chmod 600 /root/.my.cnf'
```

Then the documented install, downloaded to a file rather than piped:

```bash
docker exec labvm bash -c 'cd /root \
  && wget -q -O setup.sh "https://raw.githubusercontent.com/deforay/intelis/master/scripts/setup.sh?v=$(date +%s)" \
  && printf "yes\n" | setsid bash setup.sh > /root/setup.log 2>&1 < /dev/null'
```

Allow the better part of an hour. Watch it with
`docker exec labvm tail -f /root/setup.log`.

## What this is for

Anything that needs a lab machine rather than an application container:

- `setup.sh` end to end, including the parts that touch MySQL and systemd
- `upgrade.sh`, by hand or queued remotely from the STS
- the remote command plane executing a root command that actually completes
- `intelis check` reporting on a real Apache and a real local database

## What it is not

Not a way to deploy InteLIS. It is a privileged container running systemd,
which is a testing convenience and not a production posture. To run InteLIS in
containers, use `docker-compose.yml`.
