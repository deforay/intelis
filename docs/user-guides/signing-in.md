---
description: Steps to sign in, read the InteLIS screen, change the password, filter list pages and sign out.
audience: [lab-staff, lab-supervisor, requesting-facility]
module: [all]
type: how-to
reviewed: 2026-11-19
reviewed_against: 5.8.9
---
# Sign in and navigate InteLIS

New users need a login and a short tour of the screen before they can do
anything else. This guide covers both.

## Before starting

- A Login ID and password issued by the administrator
- The InteLIS web address for the lab

InteLIS does not allow self-registration. Only an administrator creates logins.
If no login exists, ask the administrator.

## Sign in

1. Open the InteLIS web address in a browser.
2. Enter the **Login ID** and **Password**.

    ??? question "If an image with text appears under the password"

        The image appears when the Login ID has 3 or more failed sign-in
        attempts in the last 15 minutes. Type the text from the image into the
        box below it. To get a clearer image, select **Get New Image**.

3. Select **Login**.

    ??? failure "If sign-in fails"

        | Message | Cause | What to do |
        | --- | --- | --- |
        | Please check your login credentials | The Login ID or password is wrong, or the account is inactive | Check both, then try again. After 3 failures, ask the administrator |
        | Text you entered from the image is incorrect. Please try again | The text does not match the image | Type the text from the new image |
        | You have exhausted the maximum number of login attempts. Please retry login after some time. | The image text was missing or wrong | Enter the Login ID again, then type the text from the image |

InteLIS opens on the page set for the role. For most lab users that is the
dashboard.

On the first sign-in, and after the administrator resets the password, InteLIS
opens **Edit Profile** with the message **Please change your password to
proceed.** Set a new password there before continuing.

## Read the screen

The screen has three parts.

| Part | What it holds |
|---|---|
| Left sidebar | The menu, grouped by test type and by admin function |
| Top bar | The account menu on the right, and the sidebar toggle on the left |
| Main area | The page itself |

Viral load work lives under **HIV VIRAL LOAD** in the sidebar. That group has
three sections.

| Section | Purpose |
|---|---|
| Request Management | Register requests, activate manifests, build batches |
| Test Result Management | Import, enter, approve, and email results |
| Management | Reports, exports, and printing |

The menu only shows what the role allows. A menu item missing from the sidebar
means the role does not permit it, not that the feature is absent.

## Check for the training banner

Some installations run a separate training copy. A training copy shows a scrolling
banner across the top of the screen. Its wording is set by the administrator and
reads **TRAINING SERVER** unless it has been changed, so treat any banner as the
mark of a training copy rather than looking for those two words.

The banner marks the copy. It does not stop the copy from sending results. A
training copy connected to the live STS sends results the same way the live
system does. Before practising on a training copy, ask the administrator
whether it is kept apart from the live system.

Check the banner before entering real results. Enter real results only on the
live system.

## Change the password

1. Select the account name in the top right.
2. Select **Edit Profile**.
3. Enter the new password in **Password** and **Confirm Password**. It needs
   at least 8 characters, with at least one number and one letter.
4. Select **Submit**.

If the account menu has no **Edit Profile** entry, the administrator has turned
off profile editing. Ask the administrator to change the password.

## Use the filters on a list page

Every list page in InteLIS works the same way.

1. If the filters are folded away, select **Filters** at the top of the page
   to open them.
2. Set one or more filters.
3. Select **Search**.
4. To clear one filter, select the **×** on its label above the list. To clear
   every filter, select **Reset**.

Some list pages also offer **Manage Columns**, which hides or shows columns in
the table. The choice lasts until the page is reloaded.

## Find a sample that is not in a batch or on a manifest

Use this when a sample is missing from a list, for example when making a
batch or a manifest.

1. Go to **View Test Requests** under the test's **Request Management** menu.
   For viral load, that is **HIV VIRAL LOAD → Request Management → View Test
   Requests**.
2. Open **Filters**.
3. In **Batch / Manifest**, choose **Not in a batch** or **Not on a
   manifest**.
4. Select **Search**.

Under each **Sample ID**, the list shows the sample's **Batch Code** and
**Manifest Code** when it has them. A sample with a batch code is already in a
batch. It cannot join another batch.

## Act on the Needs attention box

On the viral load and EID **View Test Requests** pages, a **Needs attention**
box can appear above the list. It shows to users who can edit requests, and
only when something is wrong. It covers samples from the last 90 days.

1. Read each row. It gives the number of samples, what is wrong, and how to fix
   it.
2. Select **Show them** on a row. The list shows only those samples.
3. Open each sample and correct it as the row says.
4. To hide the box, select the **×** in its corner. The box comes back when the
   numbers change.

## Sign out

1. Select the account name in the top right.
2. Select **Sign out**.

Sign out at the end of a shift. Leaving a session open on a shared lab computer
lets the next person work under the wrong name, which then appears in the
activity log as the wrong person.

## Confirm it worked

After signing in, the account name appears in the top right and the sidebar
lists at least one menu group. If the sidebar is empty, the role has no
permissions assigned. Ask the administrator.
