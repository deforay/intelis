#!/usr/bin/env bash
#
# Runs inside the recording container, as root: installs InteLIS at an older
# commit, so a recording of `intelis update` has something real to update.
#
# Usage: install.sh <commit-sha>
#
# setup.sh clones whatever `stable` points at. Pointing it at a local bare repo
# whose `stable` is the older commit installs that commit through the normal
# code path. The mirror is then pointed back at GitHub, which is where the
# recorded update fetches from, exactly as on a lab machine.
set -euo pipefail

old_sha="$1"
local_repo=/srv/intelis.git

git init -q --bare "$local_repo"
git -C "$local_repo" fetch -q --depth 1 https://github.com/deforay/intelis.git "${old_sha}:refs/heads/stable"

# setup.sh reads the MySQL root password from /root/.my.cnf when it exists, the
# file it writes on every lab machine. With that in place and no stdin, every
# other question takes its default: a lab machine (LIS) at /var/www/intelis,
# with no STS.
printf '[client]\nuser=root\npassword=RootPass123\n' > /root/.my.cnf
chmod 600 /root/.my.cnf
MASTER_GIT_URL="file://${local_repo}" INTELIS_TRACK=stable \
    bash /casts/setup.sh --db-strategy=drop </dev/null

installed="$(head -n1 /var/www/intelis/VERSION.txt | tr -d '\r\n')"
if [ "$installed" != "$old_sha" ]; then
    echo "install.sh: installed ${installed:-nothing}, expected ${old_sha}" >&2
    exit 1
fi

git -C /usr/local/lib/intelis/src remote set-url origin https://github.com/deforay/intelis.git
echo "install.sh: InteLIS installed at ${old_sha}"
