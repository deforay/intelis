# Connect an Instrument to InteLIS

This guide connects a laboratory analyzer to InteLIS through the Instrument
Interfacing Tool, so that results arrive without anybody typing them.

## How results travel

```text
Analyzer  ──TCP (ASTM or HL7)──►  Interfacing Tool  ──►  shared database  ──►  InteLIS
```

The Interfacing Tool is a desktop application. It listens for the analyzer,
stores every result it receives in its own local SQLite database, and can
additionally write to a MySQL database. InteLIS reads whichever of those two it
has been given, every minute, and attaches each result to the matching test
request.

Two consequences worth knowing before starting:

- The tool has to be **running** for results to arrive. It is an application on a
  desktop, not a service. Nothing is lost while it is closed, because the
  analyzer holds its own results, but nothing reaches InteLIS either.
- Results are never sent from InteLIS to the analyzer. The traffic is one way.

## The only real decision: where the tool runs

Everything else in this guide follows from this one choice.

| | Tool on the InteLIS machine | Tool on a separate machine |
|---|---|---|
| InteLIS reads | the tool's SQLite file | a MySQL database over the network |
| MySQL changes needed | none | a dedicated user, a bind address, a firewall rule |
| Instrument activity and usage reporting | not available | available |
| Setup effort | a few minutes | most of this guide |

**Choose the InteLIS machine whenever the analyzer can reach it.** It needs no
database exposed to the network, and it is the shorter path by a wide margin.

Choose a separate machine when the analyzer must connect to a computer that is
not the InteLIS server — most often because of where the analyzer physically
sits, or because the instrument's software only runs on Windows.

## 1. Install the Interfacing Tool

On Ubuntu, download the installer and run it. Do not pipe it into a shell: it
calls `sudo`, and a piped run cannot answer the password prompt.

```bash
cd ~
wget -O install-interfacing.sh "https://raw.githubusercontent.com/deforay/vlsm-interfacing/master/scripts/install.sh?v=$(date +%s)"
bash install-interfacing.sh
```

To install a specific version rather than the latest:

```bash
bash install-interfacing.sh --tag v4.0.3
```

On Windows, download the installer from the
[releases page](https://github.com/deforay/vlsm-interfacing/releases) and run
it.

## 2. Add the analyzer in the tool

Open the tool and add the instrument in its settings. The tool's
[User Guide](https://github.com/deforay/vlsm-interfacing/blob/master/USER_GUIDE.md)
covers the fields per analyzer model. What matters for every instrument:

- **Protocol** — ASTM or HL7, as the analyzer's manual specifies
- **Connection mode** — TCP Server if the analyzer connects to this computer, TCP
  Client if this computer connects to the analyzer
- **Port** — as configured on the analyzer

Turn on **auto-connect on startup**. Without it, a reboot leaves the tool open
but not listening, and results stop arriving with nothing visibly wrong.

Before configuring InteLIS, confirm the tool itself is receiving. Run one sample
on the analyzer and watch it appear in the tool's results table. If it does not
arrive here, no amount of InteLIS configuration will help.

## 3. Point InteLIS at the results

Both paths edit `configs/config.production.php` in the InteLIS installation
directory.

```bash
sudo nano /var/www/intelis/configs/config.production.php
```

### Path A — the tool runs on the InteLIS machine

Set the path to the tool's SQLite file, and switch interfacing on:

```php
$systemConfig['interfacing']['enabled'] = true;
$systemConfig['interfacing']['sqlite3Path'] = '/home/OPERATOR/.config/vlsm-interfacing/interface.db';
```

Replace `OPERATOR` with the account that runs the tool. On Windows the same file
lives at `%APPDATA%\vlsm-interfacing\interface.db`.

The file does not exist until the tool has run at least once, so complete step 2
first. InteLIS reads it as the web server account, so that account must be able
to reach it:

```bash
sudo -u www-data test -r /home/OPERATOR/.config/vlsm-interfacing/interface.db && echo readable
```

If that prints nothing, the home directory is too restrictive. Grant traversal
on the directories above the file rather than loosening the file itself:

```bash
sudo setfacl -m u:www-data:x /home/OPERATOR /home/OPERATOR/.config /home/OPERATOR/.config/vlsm-interfacing
sudo setfacl -m u:www-data:r /home/OPERATOR/.config/vlsm-interfacing/interface.db
```

Path A is now complete. Skip to step 4.

### Path B — the tool runs on a separate machine

The tool writes to a MySQL database on the InteLIS machine, so that machine has
to accept a connection from the tool's machine. Grant exactly that, and nothing
wider.

**Create a dedicated database and user.** Not `root`, and not open to every host.
Substitute the tool machine's address for `TOOL_IP` and choose a long password.

```bash
sudo mysql
```

```sql
CREATE DATABASE IF NOT EXISTS interfacing CHARACTER SET utf8mb4;
CREATE USER 'interfacing'@'TOOL_IP' IDENTIFIED BY 'A-LONG-PASSWORD-HERE';
GRANT SELECT, INSERT, UPDATE, DELETE ON interfacing.* TO 'interfacing'@'TOOL_IP';
FLUSH PRIVILEGES;
```

**Let MySQL answer on the local network.** Edit its configuration:

```bash
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

Set the bind address to the InteLIS machine's own address on the lab network —
not `0.0.0.0`, which offers the database to every network the machine is
attached to:

```ini
bind-address = 192.168.1.10
```

**Open the firewall to the tool's machine only:**

```bash
sudo ufw allow from TOOL_IP to any port 3306 proto tcp
sudo systemctl restart mysql
```

**Tell InteLIS where that database is:**

```php
$systemConfig['interfacing']['enabled'] = true;
$systemConfig['interfacing']['database']['host'] = 'localhost';
$systemConfig['interfacing']['database']['username'] = 'interfacing';
$systemConfig['interfacing']['database']['password'] = 'A-LONG-PASSWORD-HERE';
$systemConfig['interfacing']['database']['db'] = 'interfacing';
```

**Then enter the same details in the tool**, in its MySQL settings, using the
InteLIS machine's network address as the host.

## 4. Confirm interfacing is switched on

`$systemConfig['interfacing']['enabled'] = true;` is not decoration. The import
jobs are only scheduled when it is true
([`sys/cron/ScheduledTasks.php`](https://github.com/deforay/intelis/blob/master/sys/cron/ScheduledTasks.php)).
With it left false, nothing runs, nothing fails, and nothing is written to any
log — which looks exactly like a broken analyzer.

This is the single most common reason a correctly configured interface delivers
nothing.

## 5. Confirm the scheduler is running

The import is a scheduled task, so the schedule itself has to be active. It is
installed with InteLIS, and this is what it looks like:

```bash
sudo crontab -l | grep crunz
```

Expect a line running `crunz schedule:run` every minute. If there is none, see
[Maintenance scripts](maintenance.md).

## 6. Verify a result actually arrives

Do not wait on the schedule the first time. Run the import by hand and read what
it prints:

```bash
cd /var/www/intelis
sudo -u www-data php bin/interface.php
```

It reports which sources it connected to and how many records it found in each,
which tells the whole story at a glance:

```text
Connected to sqlite
# of records from SQLITE3 : 1
```

Then open InteLIS and find the sample. A result that reached the tool and was
counted here but is not visible in InteLIS almost always means the sample ID on
the analyzer does not match a registered request.

## Setting up additional machines

Do not repeat this for each analyzer. Configure one machine completely, then use
the tool's **Export settings**, and **Import settings** on the others. Adjust
only the instrument name and port per machine.

## If results do not arrive

Work down this list; it is ordered by how often each one is the cause.

| Check | How |
|---|---|
| Interfacing is switched on | `enabled` is `true` in `config.production.php` |
| The tool is running and connected | Its console shows the instrument connected, not just the app open |
| The tool received the result at all | It appears in the tool's own results table |
| The scheduler is running | `sudo crontab -l \| grep crunz` |
| InteLIS can read the source | Run step 6 by hand and read the record counts |
| The sample ID matches | A registered request exists in InteLIS with exactly that ID |

## What each path does and does not carry

Results arrive on both paths. Instrument activity and daily usage statistics are
read only from the MySQL database, so Path A delivers results but no instrument
reporting. For a lab whose purpose is getting results in, that is usually the
right trade.
