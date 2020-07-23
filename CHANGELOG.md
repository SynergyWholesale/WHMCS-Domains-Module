# Change Log

Synergy Wholesale WHMCS Domains Module

## Unreleased Version [Updated xx/xx/2020]
### Added
-

### Changed
-

### Fixed
-

### Removed
-


## 2.1.8 [Updated xx/xx/2020]
### Added
- Added whmcs.json file. Fixes [#24](https://github.com/SynergyWholesale/WHMCS-Domains-Module/issues/24) 

### Fixed
- Fixed syncing .com.au Registrant details Fixes [#23](https://github.com/SynergyWholesale/WHMCS-Domains-Module/issues/23)
- Fixed an issue syncing Rememption/Grace fees days. Fixes [#25](https://github.com/SynergyWholesale/WHMCS-Domains-Module/issues/25) [#26](https://github.com/SynergyWholesale/WHMCS-Domains-Module/issues/26) 

## 2.1.7 [Updated 26/05/2020]
### Fixed
- Fixed an issue with updating technical contacts. Fixes [#21](https://github.com/SynergyWholesale/WHMCS-Domains-Module/issues/21)

## 2.1.6 [Updated 14/05/2020]

### Fixed
- Fixed an unclosed quote in the header when importing a JS asset. Fixes [#16](https://github.com/SynergyWholesale/WHMCS-Domains-Module/issues/16)

## 2.1.5 [Updated 13/05/2020]

### Fixed
- Fixed domain sync not working on transferred away domains. Fixes [#14](https://github.com/SynergyWholesale/WHMCS-Domains-Module/issues/14)

## 2.1.4 [Updated 20/04/2020]

### Fixed
- Fixed Registrar Lock option displaying for .au domain names. Fixes [#8](https://github.com/SynergyWholesale/WHMCS-Domains-Module/issues/8)

## 2.1.3 [Updated 16/04/2020]

### Fixed
- Fixed error "Registry Error: Data missing" on pending domain transfers in WHMCS versions 7.6 and later. Fixes [#10](https://github.com/SynergyWholesale/WHMCS-Domains-Module/issues/10)
- Fixed transfer price for .au domains on pricing import. The transfer price will be set to "0.00" for all .au domains. Fixes [#9](https://github.com/SynergyWholesale/WHMCS-Domains-Module/issues/9)
- Fixed style sheet rules conflicting with themes by prefixing any CSS rules with `sw-`. Fixes [#7](https://github.com/SynergyWholesale/WHMCS-Domains-Module/issues/7)

### Changed
- Updated `Makefile` and `hooks.php` to insert module version as a parameter to prevent browsers loading cached assets after new releases.

## 2.1.2 [Updated 30/03/2020]
### Fixed
- Fix introduced type error: Carbon\Carbon provided, expected WHMCS\Carbon

## 2.1.1 [Updated 30/03/2020]
### Fixed
- Fixed "Get EPP Code" not displaying the EPP Code on screen. Fixes [#6](https://github.com/SynergyWholesale/WHMCS-Domains-Module/issues/6)


## 2.1.0 [Updated 25/03/2020]
### Added
- Added support for [GetDomainInformation](https://developers.whmcs.com/domain-registrars/domain-information/) method for newer WHMCS 7.6+. This should improve load times by reducing the number of required API calls required.
- Added support for [GetTldPricing](https://developers.whmcs.com/domain-registrars/tld-pricing-sync/) to enable the syncing of TLD pricing and settings. This feature is available in WHMCS 7.10+ Fixes [#4](https://github.com/SynergyWholesale/WHMCS-Domains-Module/issues/4).

## Fixed
- Fixed DNS Management page not loading properly when Cloudflare Rocket Loader is active.

## Changed
- Replaced the Synergy Wholesale logo to a higher resolution and white background instead

## 2.0.2 [Updated 18/02/2020]
### Changed
- PHP Version sent in API requests no longer include the `[extra]` version details

### Fixed
- Fixed "Next Due Date" not accounting for `Sync Next Due Date` setting on domain sync. Fixes [#1](https://github.com/SynergyWholesale/WHMCS-Domains-Module/issues/2)

## 2.0.1 [Updated 07/02/2020]
### Fixed
- Fixed support for PHP 7.2 and below. Fixes [#1](https://github.com/SynergyWholesale/WHMCS-Domains-Module/issues/1)

## 2.0.0 [Updated 21/02/2020]
### Added
- Premium domain support for registration, transfers and renewal
- Premium detection to the domain sync
- Domain "Push" button to admin panel. Allows approval of outbound transfers from within WHMCS.
- WHMCS Module Version, WHMCS Version, and PHP Version to each API request for analytics.
- Domain sync will update the status of ID Protection in WHMCS

### Changed
- Completed a refactor of the module
- Merged and (lightely) tidied up templates, CSS, and JS
- Replaced green "pencil" for save/update DNS/URL/Email management buttons with a tick

### Removed
- Removed `gears.gif` loader

## 1.9.16 [Updated 18/09/2019]
### Added
- Added `specialConditionsAgree` parameter on to domain registration API request.

## 1.9.15 [Updated 03/09/2019]
### Fixed
- Domain sync now factors `DomainSyncNextDueDateDays` into the domain expiry date

## 1.9.14 [Updated 22/05/2019]
### Fixed
- Fixed a bug where `whois.json` wouldn't update when WHMCS is installed in a sub directory

## 1.9.13 [Updated 10/05/2019]
### Fixed
- Asset paths now use `$WEB_ROOT` variable, now they can be fetched when WHMCS is not in web root.

## 1.9.12 [Updated 14/02/2019]
### Fixed
- Check if renewal is required before submitting domain for transfer

## 1.9.11 [Updated 06/02/2019]
### Added
- Force sync option added to force pull the latest WHOIS.json on registrar config. 
- Fixed a bug with managing nameserver records in the DNS Manager 
- Fixed incorrect format in the module sync

## 1.9.10 [Updated 23/03/2018]
### Added
- "Sync" Domain Button. Performs a sync on the specified domain (same as the domain sync cron).

### Changed
- Transferred away domains are marked as "Transferred Away" instead of "Cancelled"

### Fixed
- Syncing domains pending transfer will no longer incorrectly be marked as expired.
- API Calls with a response status of 'OUTBOUND' would be interpreted as a failure.

## 1.9.9 [Updated 20/03/2018]
### Fixed
- Fixed an issue with phone number formatting when updating domain contacts. 
- Fixed an issue with syncing a domain undergoing a CoR.

## 1.9.8 [Updated 26/02/2018]
### Fixed
- Fixed an issue with domain syncing where a sync could fail if a domain had certain statuses

## 1.9.7 [Updated 31/01/2018]
### Fixed
- Fixed an issue with domains not syncing with specific statuses
- Fixed an issue with phone number formatting when registering domain names with a non-AU phone number

## Version 1.9.6 [Updated 18/01/2018]
### Fixed
- Fix URL forward not creating correctly if protocol is supplied

### Version 1.9.5 [Updated 13/12/2017]
### Fixed
- AU registration data is now synchronised and stored locally
- Fixed an issue where the DNS manager may time out too early

### Version 1.9.4 [Updated 05/12/2017]
### Fixed
- Allow additional records to be created on the root domain via DNS manager

### Version 1.9.3 [Updated 02/05/2017]
### Fixed
- Updated the transfer domain sync cron code to function correctly
- Fixed incorrectly named variables preventing correct sync on domains

## Version 1.9.2 [Updated 24/04/2017]
### Added 
- Ability to handle SRV record types from the DNS Hosting / URL Forwarding module
- Minified all .js files, while keeping the non minified version
- Toastr support
- Added whois.json file to override the standard WHMCS checker defaults

### Changed
- Fixed up reference location for the Synergy Wholesale Domains CSS
- Relocated assets into the our module folder to keep everything neat and tidy
- Updated hooks file to not include our CSS and JS functions file for the entire ClientArea
- Relocated page dependant JS for domaindnsurlforwarding.tpl to it's own js/domain.url.js file
- Relocated page dependant JS for domaindnsemailforwarding.tpl to it's own js/email.js file
- Relocated page dependant JS for domainchildhosts.tpl into the functions.js file
- Implemented a regex via preg_replace to locate host records in dns managament that end with the domain name, and strip it away to make it easier for customers

### Fixed
- Updated module name to synergywholesaledomains in the logModuleCall function

## Version 1.9.1 [Updated 12/12/2016]
### Removed
- Removed dependency on /includes/countriescallingcodes.php since it no longer exists in WHMCS 7 from what we can tell and more importantly, it is not used in our module anyway.

### Changed
- Updated the Changelog file and increased version to 1.9.1

## Version 1.9 [Updated 08/12/2016]
### Added
- Added CHANGELOG and README.
- Added an alert to "Manage DNSSEC Records" to display when there are no configured records to tell the user there are no configured records. 
- Added an alert to "Domain Options" to display a confirmation dialoge when changing the dns type
- Added safety check to the ClientArea hook
- Ability to register .us domain names via module
- Added ability to detect when loading legacy dns and email forwarder pages and redirect to our custom ones
- Added configuration option to convert the db from ventraip to synergywholesaledomains

### Removed
- The domain name that you're performing actions upon will no longer appear at the top of the page in an alert box (it's in the breadcrumbs anyway).
- Removed any duplicate HTML.
- Removed admin area custom button entries and the resendTransferEmail function since it cannot be used properly at present

### Fixed
- Fixed a bug which would stop you from enabling registrar lock.
- Childhost and DNSSEC tables no longer have an 'X' overflow.

### Changed
- Childhost and DNSSEC data tables will no longer display unless there is data.
- Some phrasing and abbreviations for various messages.
- Table action buttons are aligned to the right the table.
- Email forwarding no longer uses "=>" to denote the destination, it now uses a font-awesome "long-arrow-right"
- Added some extra comments into various functions
- Have modified the default dns/url/email forwarding page functions to not process any requested updates, etc - these have moved to the custom designed pages / templates.
- Removed error message from legacy dns and email forwarder pages and added in a var to define the registrarModule for use by the hooks
- Modified all legacy mysql calls to use the new Capsule functionality                             
