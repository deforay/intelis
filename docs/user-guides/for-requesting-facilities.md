# How to use InteLIS at a requesting facility

Staff at a health facility use InteLIS to register the samples they collect,
pack them for the testing lab, and read the results back. This guide covers that
work.

A facility account reaches fewer pages than a lab account. Recording results,
approving them, and running batches happen at the testing lab.

## Before starting

- A login issued by the administrator
- Permission to add test requests

See [How to sign in and navigate InteLIS](signing-in.md).

## The work in order

1. Register a test request for each sample collected.
2. Pack the samples and build a manifest listing them.
3. Print the manifest and send it with the package.
4. Track the samples until results come back.
5. Read or print the results.

## Register a request

Go to **HIV VIRAL LOAD → Request Management → Add New Request**.

Fill the clinic, patient, sample, and treatment sections. Leave the laboratory
section empty. The testing lab fills it in.

Use **Save and Next** to move to the next sample without returning to the list.

For the full field-by-field walkthrough, see
[How to register a viral load test request](register-a-request.md).

Registering the request at the facility, rather than letting the lab type it
from the paper form later, has two effects. The lab receives the package by
typing one code, and the clinical detail is recorded by the people who have it.

## Build the manifest

Once the package is ready to travel, list its samples on a manifest.

Go to **HIV VIRAL LOAD → Request Management → VL Manifest** and follow
[How to send samples to a testing lab on a manifest](send-samples-on-a-manifest.md).

Print the manifest and put it in the package.

## Track the samples

Go to **HIV VIRAL LOAD → Request Management → View Test Requests** and search on
the manifest code, the patient identifier, or the collection date.

The status column shows where each sample is.

| Status | Meaning |
|---|---|
| Sample Currently Registered at Health Center | Registered here, not yet received by the lab |
| Sample Registered at Testing Lab | The lab has received it and it is waiting to be tested |
| Awaiting Approval | Tested, waiting for the lab to sign off the result |
| Accepted | The result is approved and available |
| Rejected | The lab could not test the sample. The reason is on the record |
| Failed/Invalid | The test did not produce a usable result. The lab decides whether to retest |

The full list is on the [sample statuses](sample-statuses.md) page.

A sample sitting at **Sample Currently Registered at Health Center** long after
the package went out usually means the lab has not activated the manifest yet.
Contact the lab with the manifest code.

## Get the results

Approved results reach the facility in one of three ways, depending on how the
lab works.

| Route | What to do |
|---|---|
| Emailed by the lab | Watch the facility's email address |
| Printed by the lab | Collect the printed reports |
| Available in InteLIS | Print them at the facility |

To print at the facility, go to **HIV VIRAL LOAD → Management → Print Result**,
filter to the facility, and print the results. See
[How to release results to the requesting facility](release-results.md).

If emailed results are not arriving, ask the administrator to check the email
addresses recorded against the facility.

## Look up a patient's history

Go to **HIV VIRAL LOAD → Management → Clinic Reports** and open the **Patient
Test History Report** tab. Search on the patient identifier.

## Reports available at a facility

| Report | Content |
|---|---|
| Sample Status Report | Charts of sample status, suppression, and turnaround time |
| Export Results | A spreadsheet of results matching the filters |
| Print Result | Patient report PDFs |
| Clinic Reports | High viral load, rejections, results not available, patient history |
| Sample Rejection Report | Rejected samples with the reason for each |

See [Viral load reports](reports.md) for what each one contains.

## What to do when something is wrong

| Problem | Action |
|---|---|
| Facility missing from the request form | Ask the administrator to link the facility to viral load testing |
| A request was entered twice | Ask the testing lab to cancel the duplicate |
| Sample rejected | Read the rejection reason on the record, then collect and send again |
| Result looks wrong for the patient | Contact the lab with the Sample ID. Do not act on it clinically first |
| Package sent but samples still show as at the health center | Contact the lab with the manifest code |
