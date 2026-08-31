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

Docker is the fastest route. It supplies Apache, MySQL, and PHP.

```bash
git clone https://github.com/deforay/intelis.git
cd intelis
cp .env.example .env    # set MYSQL_ROOT_PASSWORD
docker compose up -d
```

Open <http://localhost/> and create the administrator account.

Full instructions: [Installing InteLIS with Docker](https://deforay.github.io/intelis/guides/installing-intelis-with-docker/). To install on a server instead, see [InteLIS on Ubuntu](https://deforay.github.io/intelis/guides/installing-intelis-on-ubuntu/) or [InteLIS on Windows](https://deforay.github.io/intelis/guides/installing-intelis-on-windows/).

---

## Documentation

| Audience | Start here |
| --- | --- |
| Lab staff | [Using InteLIS](https://deforay.github.io/intelis/user-guides/) |
| Bench and workstation | [Printable job aids](https://deforay.github.io/intelis/job-aids/) |
| Installing and updating | [Installation guides](https://deforay.github.io/intelis/guides/installing-intelis-with-docker/) |
| Keeping data safe | [Backup and restore](https://deforay.github.io/intelis/guides/restoring-from-backup/) |
| Developers | [Architecture](https://deforay.github.io/intelis/ARCHITECTURE/) |
| Integrators | [API reference](https://deforay.github.io/intelis/api/) |

---

## Requirements

Docker supplies all three. A server install needs them present.

- Apache 2.x with the `rewrite` and `headers` modules enabled
- MySQL 5.7 or higher
- PHP 8.2 to 8.5. The Ubuntu installer selects 8.4, or 8.5 on Ubuntu 26.04 and newer.

---

## License

InteLIS is free and open-source software released under the **GNU Affero General Public License v3.0 (AGPL-3.0)**.

Read the full text in [LICENSE.md](LICENSE.md).

---

## Support

- Email [support@deforay.com](mailto:support@deforay.com)
- Website [deforay.com](https://deforay.com/)
