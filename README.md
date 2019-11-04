# CiviCRM: Campaign Advocacy

CiviCRM extension providing specific e-advocacy functionality.

![Screenshot](/images/screenshot.png)

Based on data provided by the [Electoral extension](https://github.com/josephlacey/com.jlacey.electoral), this extension provides the following tools and features to support e-advocacy efforts:

* Existence of relationship type "Constituent of public official / Public official for constituent"
* Automatic maintenance of relationships of this type between individuals marked as public officials for a given office and individuals marked as constituents of that office, per data provided by the Electoral extension.  This allows easy searching for "all constitents of Rep. John Smith".
* Existence of a "Preferred contact method" custom field, intended to record an email address or online contact form URL as the preferred contact method for individuals serving as public officials.
* Tokens allowing inclusion of various details (display name, address block, "Preferred contact method", etc.) for a given public official, and the means to indicate which public official is being referenced, in emails, mailings (traditional and Mosaico-based), and document merges.

The extension is licensed under [GPL-3.0](LICENSE.txt).

## Requirements

* [Electoral extension](https://github.com/josephlacey/com.jlacey.electoral)
* CiviCRM >= 5.0

## Installation (Web UI)

This extension has not yet been published for installation via the web UI.

## Installation (CLI, Zip)

Sysadmins and developers may download the `.zip` file for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
cd <extension-dir>
cv dl campaignadv@https://github.com/twomice/campaignadv/archive/master.zip
```

## Installation (CLI, Git)

Sysadmins and developers may clone the [Git](https://en.wikipedia.org/wiki/Git) repo for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
git clone https://github.com/twomice/campaignadv.git
cv en campaignadv
```

## Usage

* Relevant relationship types and custom fields are automatically created upon installation.
* A Scheduled Job to update relationships is auomtaically created upon installation, set to run daily.
* Relationships are updated automatically for a contat any time the Electoral extension updates that contact's data.
* Use Advanced Search to search for constituents based on the "Constituent / Official" relationship.
* On any mailing or document merge form:
  * Insert tokens under the "Public Official" token group to include data about a specific public official.
  * Use the "Select Public Official" button to specify the public official for those tokens. (This will insert a special token in the form `{PublicOfficial.filter_cid___N}` where `N` is the contact ID of the selected official.)
* Important notes:
  * Public Official" tokens will be blank if the "Select Public Official" feature is not used to specify a public official.
  * Only one public official may be specified per mailing or merge document.  Specifying more than one (by including multiple `{PublicOfficial.filter_cid___N}` tokens) will yield unpredictable results.
