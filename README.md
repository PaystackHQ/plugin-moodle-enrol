<p align="center"><a href="https://paystack.com/"><img src="./pix/banner.png?raw=true" alt="Payment Forms for Paystack"></a></p>


# Paystack Enrolment Plugin

Enrolment in Moodle using the Paystack gateway for paid courses

This plugin helps admins and webmasters use Paystack as the payment gateway. This plugin has all the settings for development as well as for production usage. Its easy to install, set up and effective. 

## Requirements

- PHP with the 21 Oct 2026 security fixes for critical remote code execution flaws (GHSA-w8hw-w9fx-j79w, GHSA-6wmf-4rv5-p3rp; [php.net advisory](https://www.php.net/releases/8_2_35.php)): **8.2.35, 8.3.36, 8.4.27, 8.5.12 or later**. PHP 8.1 and older no longer receive security fixes.
- A Moodle version that supports your PHP version ([Moodle and PHP](https://docs.moodle.org/dev/Moodle_and_PHP)). Moodle 4.5 requires PHP 8.1 or later; Moodle 5.0 and 5.1 require PHP 8.2 or later and support PHP 8.4. The plugin has been tested on Moodle 4.5 with PHP 8.3.36 and on Moodle 5.1 with PHP 8.4.27.

## Installation

Login to your moodle site as an “admin user” and follow the steps.

1) Upload the zip package from Site administration > Plugins > Install plugins. Choose Plugin type 'Enrolment method (enrol)'. Upload the ZIP package, check the acknowledgement and install.

2) Go to Enrolments > Manage enrol plugins > Enable 'Paystack' from list

3) Click 'Settings' which will lead to the settings page of the plugin

4) Provide merchant credentials for Paystack. Note that, you will get all the details from your merchant account. Now select the checkbox as per requirement. 
Choose the paystack connection mode, for test mode it uses the test api keys and for live mode uses the live api keys. Save the settings.

5) Select any course from course listing page.

6) Go to Course administration > Users > Enrolment methods > Add method 'Paystack' from the dropdown. Set 'Custom instance name', 'Enrol cost' etc and add the method.

This completes all the steps from the administrator end. Now registered users can login to the Moodle site and view the course after a successful payment.

## Using a restricted key

The settings page leads with a Paystack restricted key (`rk_test_…` / `rk_live_…`), one per mode. No public key is needed: the plugin gets it from Paystack when you save. Create the key in your Paystack Dashboard with **only** these permissions:

- **Account: read**: lets the plugin confirm the key with Paystack and fetch its public key
- **Transactions: read**: verifies payments

Leave every other permission off. The plugin only checks payments: the payment popup runs on the public key, so it never needs Transactions: write.

When you save, the plugin checks the key with Paystack and refuses it, naming what to fix, if:

- it is invalid or revoked, or restricted keys aren't enabled for your Paystack account
- it is missing one of the permissions above
- it has a permission the plugin doesn't need, such as Transactions: write, Disputes and refunds, Transfers, Settlements, Subaccounts, Payouts or another money-moving permission
- Paystack can't be reached, or an IP whitelist on your integration blocks your Moodle server

A restricted key replaces that mode's secret key. Once it is saved, roll your secret key in the Paystack Dashboard. You can still use a secret key and public key instead, under **Use secret keys instead**, if you can't use a restricted key.

Saved keys are never shown again; the page shows only their last four characters. Leave a field blank to keep the saved key.

The permission check runs only when a key is saved on the settings page. A key set with `admin/cli/cfg.php` or `$CFG->forced_plugin_settings` isn't checked, and the settings page warns about it.

## Webhooks and the webhook signing secret

Webhooks are accepted only when they are signed with your **webhook signing secret** (`whsec_…`). This applies whichever key type you use. Create the secret in the Paystack Dashboard under Settings > API Keys & Webhooks, and paste it into the matching Test or Live field.

Without a signing secret, every webhook is rejected and the settings page shows a warning. Students who complete payment are still enrolled when they return from checkout.

**Upgrading from 1.4.x:** webhooks are no longer checked with your secret key. Add a webhook signing secret after upgrading, or webhooks will stop enrolling students who close the payment window before returning to Moodle.



## Contribution

Here you can browse the source, look at open issues and keep track of development. 

## License ##

2019 Paystack

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <http://www.gnu.org/licenses/>.
