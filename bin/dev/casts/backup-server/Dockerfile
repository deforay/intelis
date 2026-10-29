# The backup machine in the backup and restore recordings: a plain Ubuntu
# machine with the SSH server installed, as in step 1 of "Another Linux
# machine" in setting-up-off-machine-backups.md.
#
# labadmin plays the account created when Ubuntu was installed: it has sudo
# rights and a password. Setup logs in as labadmin and creates lisbackup
# itself. record-casts fails any recording that contains the password.
#
# The lab's ssh passes on its locale (en_US.UTF-8). Without it here, every
# login prints a setlocale warning that a real Ubuntu machine does not.
FROM ubuntu:24.04

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
 && apt-get install -y --no-install-recommends openssh-server sudo rsync locales \
 && locale-gen en_US.UTF-8 \
 && rm -rf /var/lib/apt/lists/* \
 && useradd -m -s /bin/bash -G sudo labadmin \
 && echo 'labadmin:Srv-Admin-Pw' | chpasswd \
 && mkdir -p /run/sshd

CMD ["/usr/sbin/sshd", "-D", "-e"]
