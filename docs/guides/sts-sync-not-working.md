---
description: Find why a lab's results or requests are not moving between InteLIS and the STS, and send them now.
audience: [system-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-09-22
reviewed_against: 5.7.78
---
# Results Not Reaching the STS

A lab's InteLIS syncs with the STS every 5 minutes. Each sync sends results
and lab details up, and brings requests and lists down. When the STS is
missing a lab's results, or the lab is missing requests, the fault is almost
always on the lab machine.

The lab must already be connected to the STS. If it never was, follow
[Connect a Lab to the STS](connect-lab-to-sts.md) first.

**Choose the situation that fits, then follow its steps from top to bottom.**

=== "Find where it stops"

    Use this first, whatever is missing.

    1. Open a terminal on the lab's InteLIS machine and check the connection
       settings:

        ```bash
        sudo intelis check
        ```

    2. Read the rows `STS URL`, `STS hosts entry`, `Instance registered` and
       `STS token`. Fix any row that is not `OK`, using
       [Messages and fixes](#messages-and-fixes) below.
    3. Run a full sync now:

        ```bash
        sudo intelis sync-sts
        ```

        It gets a token, then exchanges lists, results, requests and remote
        commands with the STS. The run takes up to a few minutes.

    4. Read the output from top to bottom. Find each message that names an
       error in [Messages and fixes](#messages-and-fixes).

        `Metadata Synced` is printed even when the lists failed. Read the lines
        above it.

    5. Check that the sync reached the STS. Follow the **LIS** tab of
       [Check that data reached the STS](../user-guides/admin-monitoring.md#check-that-data-reached-the-sts).

        ??? failure "If a sync by hand works, but nothing syncs on its own"

            The scheduler is not running. Follow
            [Check the scheduled tasks are running](maintenance.md#check-the-scheduled-tasks-are-running).

=== "Results missing on the STS"

    Use this when the sync runs without errors, but results from a period are
    missing on the STS.

    A normal sync sends only results not sent before. A result that the STS
    lost, or that was sent before the lab was set up correctly, is not sent
    again on its own.

    1. See what would be sent, without sending anything. Replace `vl` with the
       module, and `7` with the number of days to go back:

        ```bash
        sudo -u www-data php /var/www/intelis/app/tasks/remote/results-sender.php vl 7 --dry-run
        ```

    2. Check the count of results it would send.
    3. Send them:

        ```bash
        sudo -u www-data php /var/www/intelis/app/tasks/remote/results-sender.php vl 7
        ```

        ??? info "Module names and other forms"

            | Module | Name |
            | --- | --- |
            | Viral Load | `vl` |
            | Early Infant Diagnosis | `eid` |
            | COVID-19 | `covid19` |
            | Hepatitis | `hepatitis` |
            | Tuberculosis | `tb` |
            | CD4 | `cd4` |
            | Custom Tests | `generic-tests` |

            - Leave out the module to send every module.
            - Give a date as `YYYY-MM-DD` in place of the days, for example
              `2026-09-01`.

        ??? failure "If it prints `Another results sync is already running. Exiting.`"

            The scheduled sync is sending now. Wait 5 minutes, then repeat
            step 3.

    4. Check that the results arrived. Follow the **LIS** tab of
       [Check that data reached the STS](../user-guides/admin-monitoring.md#check-that-data-reached-the-sts).

=== "Requests missing at the lab"

    Use this when the STS holds requests for the lab that do not appear in the
    lab's InteLIS.

    1. Pull the requests again. Replace `vl` with the module, and `7` with the
       number of days to go back:

        ```bash
        sudo -u www-data php /var/www/intelis/app/tasks/remote/requests-receiver.php -t vl 7
        ```

        ??? info "To pull the requests of one manifest"

            Add `-m` and the manifest code, and leave out the days:

            ```bash
            sudo -u www-data php /var/www/intelis/app/tasks/remote/requests-receiver.php -t vl -m MANIFEST-CODE
            ```

    2. Open the list of requests in InteLIS. Check that the missing requests
       are there.

        ??? failure "If a request still does not arrive"

            The lab refused to save it. On the STS, open **ADMIN → Monitoring
            → Lab Sync Status** and select the lab. **Requests the Lab Could
            Not Save** gives the reason for each one.

=== "Lab details missing after an update"

    Use this when users, storage or instruments changed on the lab are missing
    on the STS, and the lab was updated from a version older than
    5.7.22.

    Older versions reported their lab details as sent even when the STS
    refused them. The details are not sent again on their own.

    1. Bring the lists down from the STS, ignoring the last sync time:

        ```bash
        sudo -u www-data php /var/www/intelis/app/tasks/remote/sts-metadata-receiver.php -f
        ```

    2. Send the lab details up, ignoring the last sync time:

        ```bash
        sudo -u www-data php /var/www/intelis/app/tasks/remote/lab-metadata-sender.php -f
        ```

    3. Expect `Sync complete.` at the end.

    The STS administrator can do the same from the STS, with the **Metadata
    resync (force)** command of the
    [Remote command plane](remote-command-plane.md).

    !!! warning "Do not use `intelis reset-metadata` for this"

        It empties the facility and lab lists before bringing them down again.
        Use it only when support asks for it.

## Messages and fixes

??? failure "`STS URL`: `not set`, or `STS URL is not set`, or `Testing Lab ID is not set in System Config`"

    The lab is not connected. Follow
    [Connect a Lab to the STS](connect-lab-to-sts.md).

??? failure "`This instance is not configured as LIS. Exiting.`"

    The machine is set up as Standalone or as an STS. Only a lab machine
    syncs. See the **Instance Type** warning in
    [System Admin Area](../user-guides/admin-system-administration.md).

??? failure "`STS hosts entry`: `/etc/hosts maps ... to ...`"

    A line in `/etc/hosts` sends the STS address to another server, so
    nothing reaches the STS.

    1. Open the file:

        ```bash
        sudo nano /etc/hosts
        ```

    2. Delete the line that holds the STS host name.
    3. Save with Ctrl+O, then Enter. Close with Ctrl+X.
    4. Run `sudo intelis check` again.

??? failure "`No internet connectivity while trying remote sync.`, or `Checking connectivity... FAILED`"

    The machine cannot reach the STS. The dot in the page header of InteLIS
    also shows `STS server is unreachable`.

    - Open the STS address in a browser on this machine. If it does not load,
      check the internet connection or the VPN.
    - If the address loads, run `sudo intelis check` and read the `STS URL`
      row. The address saved on the lab may be wrong. Correct it with
      [Connect a Lab to the STS](connect-lab-to-sts.md).

??? failure "`HTTP 401`, or `Unauthorized Access on ... sync`, or `STS token`: `no STS token is stored`"

    The STS did not accept the lab's token. The reason follows
    `Unauthorized Access on ... sync:`. It is also in the STS's own log.

    1. Get the current token from the STS:

        ```bash
        sudo intelis token
        ```

    2. Expect `Token generated`.
    3. Run `sudo intelis sync-sts` again.

    If the 401 comes back, ask the STS administrator to send **Rotate STS
    token** from the [Remote command plane](remote-command-plane.md). Send
    them the reason printed after `Unauthorized Access`.

??? failure "`Instance registered`: `not registered, but an STS is configured`"

    Setup in the browser was never finished, so the STS cannot tell which lab
    is syncing. Open `/setup/index.php` on the lab's address, finish the
    form, then run `sudo intelis check` again.

??? failure "`Could not deliver the receipt for ...; it will be sent next run.`"

    Nothing is wrong. The lab saved the requests, and tells the STS on the next
    sync.

??? info "Where the sync logs are"

    Errors go to `/var/www/intelis/var/logs`, one file per day, for example
    `2026-09-22-logfile.log`. **ADMIN → Monitoring → Log File Viewer** shows
    the same files. Send the newest one when contacting support.
