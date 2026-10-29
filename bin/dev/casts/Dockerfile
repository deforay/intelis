# A disposable Ubuntu machine for recording terminal sessions for the docs.
#
# systemd runs as PID 1 because setup.sh, intelis update and the doctor all
# restart apache2 and mysql through systemctl. The container must be started
# privileged with the host cgroup tree; bin/dev/record-casts does that.
#
# cron is here because every Ubuntu machine has it and the scripts rely on it:
# without it, intelis-refresh fails, and the update never hands the app tree
# back to www-data.
FROM ubuntu:24.04

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      systemd systemd-sysv dbus sudo curl wget ca-certificates gnupg lsb-release \
      tzdata git asciinema expect locales cron \
 && locale-gen en_US.UTF-8 \
 && rm -rf /var/lib/apt/lists/*

# Units that only make sense on a real console or real hardware. Left in, they
# fail at boot and leave the system "degraded" for no reason.
RUN rm -f /lib/systemd/system/multi-user.target.wants/getty* \
          /lib/systemd/system/sysinit.target.wants/systemd-firstboot.service \
          /etc/systemd/system/*.wants/getty* \
 && systemctl mask systemd-firstboot.service getty.target console-getty.service

# The account the recordings are typed from: an ordinary user with sudo rights,
# which is how an operator works on a lab machine. The password only ever goes
# to sudo, which does not echo it, and record-casts fails any recording that
# contains it.
RUN useradd -m -s /bin/bash -G sudo labuser \
 && echo 'labuser:Rec0rd-Only-Pw' | chpasswd

# The shared scripts set LC_ALL=en_US.UTF-8, as on a lab machine.
ENV LANG=en_US.UTF-8

STOPSIGNAL SIGRTMIN+3
CMD ["/sbin/init"]
