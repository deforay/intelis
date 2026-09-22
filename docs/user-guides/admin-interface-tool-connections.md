---
description: Generate a one-time connection code to link, reconnect or revoke an Interface Tool installation for a testing lab.
audience: [lab-admin, system-admin]
module: [all]
type: how-to
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
# How to connect the Interface Tool with a connection code

Link one installation of the Interface Tool to a testing lab, so its analyzer
results reach InteLIS. Each lab computer running the tool connects once.

To install the tool and add the analyzer in it, see
[Connect an Instrument to InteLIS](../guides/setting-up-interfacing-tool.md).

## Before starting

- On the STS, an account with the built-in Admin role, or an account with the
  testing lab mapped to it. On a standalone installation, an account with the
  testing lab mapped to it
- The testing lab created under **ADMIN → Facilities**
- The Interface Tool installed on the lab computer
- The **Interface API Enabled** setting switched on. No page shows this
  setting. InteLIS support switches it on. Until then, the **Interface Tool
  Connections** panel is missing from every lab

??? info "On a LIS"

    The Facilities list on a LIS has no **Edit** button, so the panel cannot be
    reached from the menu. Contact InteLIS support.

**Choose the situation that fits, then follow its steps from top to bottom.**

=== "First connection"

    1. Go to **ADMIN → Facilities**.
    2. Select **Edit** on the testing lab.
    3. Scroll to **Interface Tool Connections**.
    4. Select **Generate Connection Code**.
    5. In the Interface Tool on the lab computer, enter the **InteLIS URL** shown
       on the page. **Copy** puts the value on the clipboard.
    6. Enter the three groups of the **Connection Code** in the Interface Tool.

        ??? info "The code works once"

            The code is shown only once and can be used only once. It expires
            after 30 minutes, when the **Expires in** countdown reaches 00:00.
            If it expires, select **Generate Connection Code** again.

            The page holds one code at a time. To make another before it
            expires, select **Cancel Code** first. Reloading the page hides the
            code but does not cancel it.

    7. Reload the page. The installation appears under **Connected
       Installations**.

=== "Reconnect or reinstall"

    Use this when the lab computer is rebuilt, or the tool is reinstalled.

    1. Go to **ADMIN → Facilities**.
    2. Select **Edit** on the testing lab.
    3. Scroll to **Interface Tool Connections**.
    4. Under **Connected Installations**, find the installation by its **Display
       Name**.
    5. Select **Reconnect / Reinstall**. A new code appears.
    6. In the Interface Tool on the lab computer, enter the **InteLIS URL** and
       the three groups of the code.
    7. Reload the page. The installation shows a recent **Last Seen**.

    ??? info "Connect this tool"

        An installation marked **Reporting via importer** sends results without
        a connection yet. Its button reads **Connect this tool**. Follow the
        same steps with that button.

=== "Revoke"

    Use this when a lab computer is retired or lost.

    1. Go to **ADMIN → Facilities**.
    2. Select **Edit** on the testing lab.
    3. Scroll to **Interface Tool Connections**.
    4. Under **Connected Installations**, find the installation by its **Display
       Name**.
    5. Select **Revoke**.
    6. Confirm the prompt.

    The revoked installation can no longer send results. Other installations of
    the lab are unaffected. To bring a revoked installation back, use
    **Reconnect / Reinstall** on its row.

## Confirm it worked

| Check | Expected |
| --- | --- |
| Connected Installations | The installation is listed, with a recent **Last Seen** |
| Results | A result run on the analyzer appears in InteLIS |

When results stop arriving, a stale **Last Seen** means the tool is not
reaching InteLIS. See also **Interface Machine Activity** in
[Monitoring and audit](admin-monitoring.md#check-that-an-analyzer-is-still-sending).
