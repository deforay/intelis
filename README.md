# InteLIS

> **Integrated Laboratory Information & Sample Tracking System**
> Open-source LIS to manage and track samples for HIV viral load, EID, TB, hepatitis, COVID-19, CD4, and other priority diseases.

![PHP](https://img.shields.io/badge/PHP-8.4+-blue) ![Ubuntu](https://img.shields.io/badge/Ubuntu-22.04%2B-orange) ![Status](https://img.shields.io/badge/status-stable-success) ![License: AGPL v3](https://img.shields.io/badge/License-AGPL%20v3-blue)

InteLIS digitizes laboratory workflows from sample collection to result dispatch, for national and sub-national health programs. It is lightweight, self-hostable, and works both online and offline.

InteLIS was previously called VLSM. Some paths and the database keep the old name.

**[Read the documentation](https://deforay.github.io/intelis/)** · **[Documentation en français](https://deforay.github.io/intelis/fr/)**

---

## What it does

- Register test requests and track each sample from collection to released result
- Send and receive samples on manifests between facilities and testing labs
- Batch samples for testing and load the analyzer
- Capture results by analyzer interfacing, file import, or manual entry
- Review, approve, and release results by print, email, or export
- Record where each sample is stored
- Report on turnaround time, testing volumes, failures, and rejections
- Run modules for HIV viral load, EID, TB, hepatitis, COVID-19, CD4, and custom tests
- Work in English or French, and integrate over a REST API

---

## Quick start

Ubuntu 22.04 LTS or later is the recommended install. The installer sets up Apache, MySQL, PHP, cron jobs, and the `intelis` command that runs the machine from then on.

```bash
# Download the script to a file, then run it. Do NOT pipe it (curl ... | bash).
cd ~ && wget -O setup.sh "https://github.com/deforay/intelis/raw/master/scripts/setup.sh?v=$(date +%s)" && sudo bash setup.sh
```

Open <http://intelis/> and create the administrator account.

Full instructions: [InteLIS on Ubuntu](https://deforay.github.io/intelis/guides/installing-intelis-on-ubuntu/).

[Docker](https://deforay.github.io/intelis/guides/installing-intelis-with-docker/) is quicker to stand up and suits evaluation and development. It is updated with its own script rather than `intelis update`, and remote upgrades are not yet offered to a containerised instance. Windows is also supported: see [InteLIS on Windows](https://deforay.github.io/intelis/guides/installing-intelis-on-windows/).

---

## Documentation

| Audience | Start here |
| --- | --- |
| Lab staff | [Using InteLIS](https://deforay.github.io/intelis/user-guides/) |
| Bench and workstation | [Printable job aids](https://deforay.github.io/intelis/job-aids/) |
| Installing and updating | [Installation guides](https://deforay.github.io/intelis/guides/installing-intelis-on-ubuntu/) |
| Keeping data safe | [Backup and restore](https://deforay.github.io/intelis/guides/restoring-from-backup/) |
| Developers | [Architecture](https://deforay.github.io/intelis/ARCHITECTURE/) |
| Integrators | [API reference](https://deforay.github.io/intelis/api/) |

---

## Related projects

InteLIS works alongside two other open-source projects from the same team.

| Project | What it does |
| --- | --- |
| [InteLIS Interfacing](https://github.com/deforay/intelis-interfacing) | Desktop application that reads results from laboratory analyzers over ASTM and HL7, stores them locally, and passes them to InteLIS |
| [Smart Connect](https://github.com/deforay/smart-connect) | National dashboard that collects data from connected InteLIS installations and reports on viral load, EID, and COVID-19 |

---

## Requirements

The Ubuntu installer and the Docker image both supply these. A manual install needs them present.

- Apache 2.x with the `rewrite` and `headers` modules enabled
- MySQL 5.7 or higher
- PHP 8.2 to 8.5. The Ubuntu installer selects 8.4, or 8.5 on Ubuntu 26.04 and newer.

---

## Funding and partners

InteLIS is developed with funding from the United States Government (USG). Over the years, the project has benefited from the support and collaboration of partners including the African Society for Laboratory Medicine (ASLM), the American Society for Microbiology (ASM), the African Field Epidemiology Network (AFENET), Emory University, and the Maryland Global Initiatives Corporation (MGIC), among others.

---

## License

InteLIS is free and open-source software released under the **GNU Affero General Public License v3.0 (AGPL-3.0)**.

Read the full text in [LICENSE.md](LICENSE.md).

---

## Support

- Email [support@deforay.com](mailto:support@deforay.com)
- Website [deforay.com](https://deforay.com/)
