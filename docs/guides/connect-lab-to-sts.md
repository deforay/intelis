---
description: Connect a lab's InteLIS to the STS, or repair the connection, with sudo intelis sts-setup.
audience: [system-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-09-22
reviewed_against: 5.7.78
---
# Connect a Lab to the STS

A lab's InteLIS sends results to the STS and receives requests from it. Both
need three things in place: the STS address, the lab this machine serves, and
an STS token. One command sets or repairs all three:

```bash
sudo intelis sts-setup
```

Run it on the lab's InteLIS machine, never on the STS itself.

**Choose the situation that fits, then follow its steps from top to bottom.**

=== "First connection"

    Use this when the lab has never been connected to the STS, or was installed
    with the STS address left empty.

    1. Get the STS address from the national programme, for example
       `https://sts.example.org`.
    2. Open a terminal on the InteLIS machine and run:

        ```bash
        sudo intelis sts-setup
        ```

    3. At `Enter STS URL (or press Enter to skip)`, type the STS address.
       Setup checks that the STS answers before it saves the address.

        ??? failure "If it prints `Cannot connect to STS at this URL`"

            Setup tries `https://` first, then `http://`. Neither reached the
            STS.

            - Check the address for typing mistakes.
            - Open the address in a browser on this machine. If it does not
              load, the machine has no route to the STS. Check the internet
              connection or the VPN.
            - Type the address again. Setup allows five attempts.

        ??? warning "If it warns `This STS URL uses plain HTTP`"

            The token and every synced result travel unencrypted. Use the
            `https://` address as soon as the STS has a certificate, and repeat
            these steps.

    4. Wait for `Refreshing Database Metadata`. Setup downloads the
       facilities, labs and lists from the STS. The first download can run
       for several minutes with no output.
    5. At **Select lab**, type part of the lab's name, and press Enter on the
       right one. If the STS lists only one lab, setup picks it without asking.

        ??? failure "If it prints `No active testing labs found`"

            The STS holds no active testing lab. Ask the STS administrator to
            add the lab, or to mark it active. Then repeat step 2.

    6. Check the last lines. Expect both:

        ```text
        [OK] STS setup complete!
        [OK] Token generated: ...
        ```

        ??? failure "If it prints `Failed to generate token`"

            The STS refused this machine. Send the full message to the STS
            administrator. Once they fix it, get a new token alone:

            ```bash
            sudo intelis token
            ```

    7. Run the first sync. Use the **Check the connection** situation.

=== "Wrong STS or wrong lab"

    Use this when the STS address changed, or the machine is set to the wrong
    lab.

    1. Run:

        ```bash
        sudo intelis sts-setup
        ```

    2. Setup shows `Current STS URL`. At `Is this STS URL correct?`:
        - To keep it, press Enter.
        - To change it, answer **no**, then type the new address.
    3. Setup shows the current lab. At `Is this the correct lab?`:
        - To keep it, press Enter. Then at `Do you want to refresh the
          metadata?`, press Enter to download the latest lists from the STS.
        - To change it, answer **no**. Setup downloads the lists, then asks
          for the lab. Choose it as in **First connection**, step 5.

        !!! warning "Change the lab only to correct a mistake"

            Results and requests follow the lab set here. Change it only when
            the machine was set to the wrong lab.

    4. Check the last lines for `STS setup complete!` and `Token generated`.
    5. Run a sync. Use the **Check the connection** situation.

=== "Check the connection"

    Use this after setup, or to confirm a lab is still connected.

    1. Run a full sync now, instead of waiting for the scheduled one:

        ```bash
        sudo intelis sync-sts
        ```

        It refreshes the token, then exchanges lists, results, requests and
        remote commands with the STS.

    2. Read the output for errors. If a step fails, see
       [Results Not Reaching the STS](sts-sync-not-working.md).

## When setup stops early

??? failure "`STS setup can only be run for LIS instances.`"

    The machine is not set up as a lab. Its instance type is **Standalone** or
    **STS**. To change it, see the **Instance Type** warning in
    [System Admin Area](../user-guides/admin-system-administration.md). A
    Standalone lab has no STS, so it needs no connection.

??? failure "`No STS URL configured; skipping metadata refresh`"

    The STS address was skipped. Repeat the steps with the address ready.

??? failure "`Metadata refresh failed`"

    The STS answered the address check but failed during the download. Run
    the download again on its own to see the error:

    ```bash
    sudo intelis metadata-sync
    ```

    Then run `sudo intelis sts-setup` again.

??? failure "`intelis` is not recognised"

    The install is older. [Update InteLIS](updating-intelis-on-ubuntu.md),
    then repeat the steps.
