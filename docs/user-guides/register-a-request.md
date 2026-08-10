# How to register a viral load test request

Use this guide when a sample arrives at the lab with a paper request form and no
Sample ID. Registering the request gives the sample an ID and puts it in the
queue for testing.

For samples that arrive in a package with a manifest, do not register them one
by one. See [How to receive samples sent on a manifest](receive-referred-samples.md).

## Before starting

- A completed paper request form
- Permission to add test requests

## Open the form

Go to **HIV VIRAL LOAD → Request Management → Add New Request**.

The form is set up for each country, so the exact fields differ between
installations. The sections below appear on most forms. Fields marked with a red
asterisk are mandatory. InteLIS refuses to save until every one of them is
filled.

Fill the optional fields too where the paper form has the information. Reports
can only count what was recorded.

<!-- SCREENSHOT NEEDED
     Page: HIV VIRAL LOAD → Request Management → Add New Request
     Capture: the whole form collapsed to show its section headings
     Highlight: the red asterisk legend at the top
-->

## Fill the clinic information

This section records where the sample came from and where it goes.

| Field | What to enter |
|---|---|
| State/Province | The province of the requesting facility |
| District/County | The district, which filters to the chosen province |
| Clinic/Health Center | The facility that collected the sample |
| Implementing Partner | The partner supporting the facility, if the lab tracks this |
| Funding Source | The funder covering the test, if the lab tracks this |
| Testing Lab | The lab that runs the test |

Choose the province first. The district list shows only districts in that
province, and the facility list shows only facilities in that district.

If the facility is missing from the list, it has not been created yet, or it has
not been linked to viral load testing. Ask the administrator to add it. See
[How to administer InteLIS](administer-intelis.md).

## Fill the patient information

Enter the patient identifier exactly as it appears on the paper form. On most
country forms this is the ART number.

As soon as the identifier is entered, InteLIS looks for earlier requests for the
same patient and shows what it finds:

- the number of times a test has been requested for this patient
- the date the last request was added
- the collection date of the last request

Use this to catch a duplicate before saving. A second request for a sample
already registered creates two records for one sample.

Enter the date of birth if the paper form has it. If it does not, enter the age
in years instead, or the age in months for a patient under one year old.

## Fill the sample information

| Field | What to enter |
|---|---|
| Date of Sample Collection | The date the sample was drawn |
| Sample Dispatched On | The date the sample left the facility |
| Sample Type | The specimen type, such as plasma or dried blood spot |

The collection date drives the turnaround time reports and the sample expiry
check. Enter the date from the paper form, not the date of data entry.

## Fill the treatment information and the indication

These sections record the patient's treatment and the reason the test was
requested. On most country forms the indication is a single choice from a list
such as routine monitoring, repeat test after adherence counselling, or
suspected treatment failure.

The indication drives the clinical reports. A request saved without one still
tests correctly, but it disappears from those reports.

## Leave the laboratory section empty

The laboratory section holds the test date, the analyzer, the result, and the
signatures. Leave it empty at registration. It is filled after the sample has
been tested. See [How to capture viral load results](capture-results.md).

## Save

Two buttons save the request.

| Button | What happens |
|---|---|
| **Save** | Saves the request and returns to the request list |
| **Save and Next** | Saves the request and opens a fresh form for the next sample |

Use **Save and Next** when working through a stack of paper forms. Some labs
configure it to carry the clinic details over to the next form. Where that is
turned on, check the carried-over fields against the next paper form before
saving.

InteLIS generates the Sample ID when the request is saved. Do not try to type
one in.

<!-- SCREENSHOT NEEDED
     Page: HIV VIRAL LOAD → Request Management → Add New Request, foot of the form
     Highlight: the Save and Save and Next buttons side by side
-->

## Print the barcode label

Where the lab uses barcode labels, the form has a **Print Barcode Label**
option. Set it before saving.

If no printer is listed, select **Change/Retry** to pick one. The label carries
the Sample ID as a barcode. Stick it on the specimen tube.

## Confirm it worked

Go to **HIV VIRAL LOAD → Request Management → View Test Requests** and search
for the patient identifier or the Sample ID.

The request appears with the status **Sample Registered at Testing Lab**. That
status means the sample is registered and waiting to be tested.

To correct a mistake, select **Edit** on the row. Requests lock after a number
of days set by the administrator. A locked request cannot be edited.

## Next

Add the registered samples to a batch. See
[How to batch samples for testing](batch-samples.md).
