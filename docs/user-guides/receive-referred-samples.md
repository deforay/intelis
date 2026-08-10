# How to receive samples sent on a manifest

Use this guide when a package of samples arrives from a health facility or
another lab with a manifest. Activating the manifest registers every sample in
the package at once, without retyping any of them.

## Before starting

- The package, physically checked against the manifest
- The manifest code, printed on the manifest sheet in the package
- The date the package arrived at the lab
- Permission to add samples from a manifest

## Check the package first

Count the tubes against the manifest before touching InteLIS. Activation marks
every sample on the manifest as received at the lab. Activating a manifest for a
package that is short leaves samples recorded as received that are not in the
building.

If tubes are missing or damaged, activate the manifest anyway, then reject the
affected samples individually. See
[How to handle failed and held samples](failed-and-held-samples.md).

## Activate the manifest

1. Go to **HIV VIRAL LOAD → Request Management → Add Samples from Manifest**.
2. Enter the manifest code in **Sample Manifest Code**.
3. Select **Submit**.

InteLIS lists every sample on that manifest.

<!-- SCREENSHOT NEEDED
     Page: HIV VIRAL LOAD → Request Management → Add Samples from Manifest
     Capture: the manifest code entered and the sample list loaded below it
     Highlight: the Sample Manifest Code field and the Submit button
-->

4. Check the listed count against the tubes on the bench.
5. Set **Sample Received at Testing Lab** to the date the package arrived.
6. Select **Activate Samples**.

InteLIS confirms with a message reading that the samples from the manifest have
been activated.

## What activation does

Activation issues a lab Sample ID for every sample on the manifest that does not
already have one, and records the received date entered in step 5.

Until the manifest is activated, the samples are not available for testing. The
health facility has already recorded them, but they carry the facility's own
reference, not a lab Sample ID.

The list shows two columns for this reason.

| Column | Meaning |
|---|---|
| Sample ID | The identifier issued by this lab, used on the analyzer and on the report |
| Remote Sample ID | The identifier the sending facility used, kept so the facility can trace the sample |

## If the code is not accepted

| Message | Cause | What to do |
|---|---|---|
| Enter a valid manifest code | The code does not match any manifest | Check for a mistyped character. Confirm the manifest was sent to this lab |
| Select when the samples were received | The received date is empty | Set the received date, then activate again |
| No samples listed | The manifest has already been activated | Search for one of its Sample IDs in View Test Requests |

A manifest created on the central system reaches the lab on a schedule. A
manifest generated minutes ago may not have arrived yet. Wait, then try again.

## Confirm it worked

Go to **HIV VIRAL LOAD → Request Management → View Test Requests** and search on
the manifest code.

Every sample from the package appears with a Sample ID and the status **Sample
Registered at Testing Lab**.

## Next

Add the activated samples to a batch. See
[How to batch samples for testing](batch-samples.md).
