# Synergy Wholesale WHMCS Domains Module

![Tests](https://github.com/synergywholesale/whmcs-domains-module/workflows/Tests/badge.svg?branch=master&event=push)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![Contributors welcome](https://img.shields.io/badge/Contributors-welcome-brightgreen.svg)](https://github.com/synergywholesale/whmcs-domains-module/blob/master/CONTRIBUTING.md)

This repository contains the source code for the [Synergy Wholsale](https://synergywholesale.com/) WHMCS domains module.

## Features
---
- Full Premium Domain Support
- Domain Registration
- Domain Transfer
- Domain Renewal
- Domin Management
	- Update Nameservers
	- Configure/Manage Child hosts
	- Configure/Manage DNSSEC
	- ID Protection
	- Update Contacts
- DNS Hosting
	- Supports: A, AAAA, CNAME, MX, NS, SRV, and TXT
- URL Forwarding
	- HTTP 301 Redirect
	- HTML Frame (Cloaking)
- Email Forwarding
- Advanced Domain/Transfer Sync
	- Includes retrospective premium domain identification
	- Manual sync button in the admin area
- API Connectivity Tester
- WHOIS.json updater

# Installation
---
The [following guide](https://synergywholesale.com/faq/article/installing-the-whmcs-domain-registrar-module/) will assist you in installation and configuration of the Synergy Wholesale WHMCS Domain Registrar module into your WHMCS installation

## Minimum Requirements
---
This module supports the [minimum WHMCS system requirements](https://docs.whmcs.com/System_Requirements). This module may work with [EOL versions of WHMCS](https://docs.whmcs.com/Long_Term_Support#WHMCS_Version_.26_LTS_Schedule) including those < 7.0, however no support will be offered for such versions.

### PHP Requirements
---
This module utilises the **PHP SOAP** module. In order to connect to use our API you must have this PHP extension enabled.
  

# Contributing
If you need some help getting started, please see our [contribution guide](CONTRIBUTING.md).

# License
This project is licensed under the GNU General Public License v3.0. See the [LICENSE](LICENSE) file for further information.