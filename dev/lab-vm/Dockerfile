# An Ubuntu machine, not an application container.
#
# The compose stack cannot answer whether a remote upgrade completes: upgrade.sh
# configures MySQL's data directory and restarts services, and there MySQL is a
# second container. This runs systemd as PID 1 with MySQL, Apache and PHP all on
# the same machine, which is what a lab is. upgrade.sh cannot tell it from one.
#
# Run privileged, with a cgroup mount:
#   docker run -d --privileged --cgroupns=host \
#     -v /sys/fs/cgroup:/sys/fs/cgroup:rw --name labvm intelis-labvm
FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive
ENV container=docker

RUN apt-get update && apt-get install -y --no-install-recommends \
        systemd systemd-sysv dbus dbus-user-session \
        ca-certificates curl wget sudo gnupg lsb-release \
        cron rsync unzip zip git jq \
    && rm -rf /var/lib/apt/lists/*

# systemd units that only make sense on real hardware, and which otherwise fail
# noisily at boot in a container.
RUN find /etc/systemd/system /lib/systemd/system \
      -path '*.wants/*' \
      \( -name '*getty*' -o -name '*udev*' -o -name '*mount*' -o -name '*swap*' \) \
      -exec rm -f {} + 2>/dev/null || true

STOPSIGNAL SIGRTMIN+3
CMD ["/lib/systemd/systemd"]
