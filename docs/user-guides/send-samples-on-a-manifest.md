# How to send samples to a testing lab on a manifest

A manifest is the packing list for a batch of samples travelling to a testing
lab. It records which Sample IDs are in the package, so the receiving lab
registers the whole package by typing one code.

Use this guide at a health facility sending samples for testing, or at a lab
referring samples on to another lab.

## Before starting

- The test requests registered in InteLIS, one per sample in the package. See
  [How to register a viral load test request](register-a-request.md)
- The testing lab the package goes to
- Permission to manage manifests

Register the requests before building the manifest. The manifest is assembled
from requests that already exist.

## Create the manifest

1. Go to **HIV VIRAL LOAD → Request Management → VL Manifest**.
2. Select **Add Specimen Referral Manifest**.
3. Choose the **Testing Lab** the package goes to.
4. Enter or accept the **Manifest Code**.
5. Enter the **Operator/Technician** packing the samples.
6. Set the **Sample Collection Point** if the facility collects at more than one
   point.

## Add the samples

1. Filter the sample list by **Sample Type** and **Sample Collection Date
   Range**.
2. Select **Search**.
3. Tick every sample going into the package.
4. Select **Save**.

Tick only samples physically in the package. A manifest listing a sample that is
not in the box leaves the receiving lab recording a sample it never got.

## Print the manifest

1. Go to **HIV VIRAL LOAD → Request Management → VL Manifest**.
2. Find the manifest.
3. Select **Print Manifest PDF**.

Put the printed manifest in the package. Keep a copy at the sending site.

The receiving lab needs the manifest code from this sheet to register the
package. See [How to receive samples sent on a manifest](receive-referred-samples.md).

## Change a manifest before it ships

Select **Edit** on the manifest row to add or remove samples.

Once a manifest is dispatched, **Edit** is disabled. A dispatched manifest is a
record of what physically left the site, so it does not change afterwards.

If a dispatched manifest is wrong, tell the receiving lab. They can reject or
hold the affected samples on arrival.

## Redirect manifests to a different lab

Where a testing lab is out of service, manifests already sent to it can be
reassigned.

1. Go to **HIV VIRAL LOAD → Request Management → VL Manifest**.
2. Select **Move Manifest**.
3. Set **Manifest From Testing Lab** and a **Date Range** to find the manifests.
4. Choose the destination in **Assign to Testing Lab**.
5. Enter the **Reason for Moving Manifest(s)**.
6. Save.

Enter a reason that explains the move. It is the only record of why the samples
went somewhere other than the lab first chosen.

Move the physical packages as well. Reassigning the manifest changes the records
only.

## Confirm it worked

The manifest appears in the list with the right **Number of Samples** and the
right **Testing Lab**.

After the receiving lab activates it, the samples carry a lab Sample ID
alongside the facility's own reference.
