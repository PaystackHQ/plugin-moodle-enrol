<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     enrol_paystack
 * @copyright   2019 Paystack
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addtogroup'] = 'Add to group';
$string['addtogroup_help'] = 'If you select a group here, then when a user completes the payment process and is enrolled in this course, they will be added to this group.';
$string['assignrole'] = 'Assign role';
$string['assignrole_help'] = 'If you select a role here, then when a user completes the payment process and is enrolled in this course, they will be assigned this role.';
$string['btntext']= 'Pay Now';
$string['billingaddress'] = 'Require users to enter their billing address';
$string['billingaddress_desc'] = 'This sets the Paystack payment option for whether the user should be asked to input their billing address. It is off by default, but it is a good idea to turn it on.';
$string['cost'] = 'Enrol cost';
$string['costerror'] = 'The enrolment cost is not numeric';
$string['costorkey'] = 'Please choose one of the following methods of enrolment.';
$string['currency'] = 'Currency';
$string['customfields'] = 'Profile fields to be used as custom fields';
$string['customfields_desc'] = 'Which user profile fields can be used during enrolment';
$string['customwelcomemessage'] = 'Custom welcome message';
$string['customwelcomemessage_help'] = 'If you enter some text here, it will be shown instead of the standard text "This course requires a payment for entry." on the Enrolment options page that students see when they attempt to access a course they are not enrolled in. If you leave this blank, the standard text will be used.';
$string['defaultrole'] = 'Default role assignment';
$string['defaultrole_desc'] = 'Select role which should be assigned to users during Paystack enrolments';
$string['enrolenddate'] = 'End date';
$string['enrolenddate_help'] = 'If enabled, users can be enrolled until this date only.';
$string['enrolenddaterror'] = 'Enrolment end date cannot be earlier than start date';
$string['enrolmentnew']='New Enrolment';
$string['enrolmentnewuser']='New User Enrolment';
$string['enrolperiod'] = 'Enrolment duration';
$string['enrolperiod_desc'] = 'Default length of time that the enrolment is valid. If set to zero, the enrolment duration will be unlimited by default.';
$string['enrolperiod_help'] = 'Length of time that the enrolment is valid, starting with the moment the user is enrolled. If disabled, the enrolment duration will be unlimited.';
$string['enrolstartdate'] = 'Start date';
$string['enrolstartdate_help'] = 'If enabled, users can be enrolled from this date onward only.';
$string['expiredaction'] = 'Enrolment expiration action';
$string['expiredaction_help'] = 'Select action to carry out when user enrolment expires. Please note that some user data and settings are purged from course during course unenrolment.';
$string['live_secretkey'] = 'Paystack Live Secret Key';
$string['live_publickey'] = 'Paystack Live Public Key';
$string['live_secretkey_desc'] = 'The Live API Secret Key of Paystack account';
$string['live_publickey_desc'] = 'The Live API Public Key of Paystack account';
$string['mailadmins'] = 'Notify admin';
$string['mailstudents'] = 'Notify students';
$string['mailteachers'] = 'Notify teachers';
$string['messageprovider:paystack_enrolment'] = 'Paystack enrolment messages';
$string['mode'] = 'Paystack Connection Mode';
$string['mode_desc'] = 'Set api configuration to use when Paystack charges for enrolment into a course by default.';
$string['mode_live'] = 'Live Mode';
$string['mode_test'] = 'Test Mode';
$string['nocost'] = 'There is no cost associated with enrolling in this course!';
$string['paymentthanks']='Thanks for your payment';
$string['paystack:config'] = 'Configure Paystack enrol instances';
$string['paystack:manage'] = 'Manage enrolled users';
$string['paystack:unenrol'] = 'Unenrol users from course';
$string['paystack:unenrolself'] = 'Unenrol self from the course';
$string['paystackaccepted'] = 'Paystack payments accepted';
$string['pluginname'] = 'Paystack';
$string['pluginname_desc'] = 'The Paystack module allows you to set up paid courses. If the cost for any course is zero, then students are not asked to pay for entry.  There is a site-wide cost that you set here as a default for the whole site and then a course setting that you can set for each course individually. The course cost overrides the site cost.';
$string['sendpaymentbutton'] = 'Send payment via Paystack';
$string['status'] = 'Allow Paystack enrolments';
$string['status_desc'] = 'Allow users to use Paystack to enrol into a course by default.';
$string['test_secretkey'] = 'Paystack Test Secret Key';
$string['test_publickey'] = 'Paystack Test Public Key';
$string['test_secretkey_desc'] = 'The Test API Secret Key of Paystack account';
$string['test_publickey_desc'] = 'The Test API Public Key of Paystack account';
$string['unenrolselfconfirm'] = 'Do you really want to unenrol yourself from course "{$a}"?';
$string['messageprovider:paystackpayment_enrolment'] = 'Message Provider';
$string['validatezipcode'] = 'Validate the billing postal code';
$string['validatezipcode_desc'] = 'This sets the Paystack payment option for whether the billing address should be verified as part of processing the payment. They strongly recommend that this option should be on, to reduce fraud.';
$string['maxenrolled'] = 'Max enrolled users';
$string['maxenrolled_help'] = 'Specifies the maximum number of users that can paystackpayment enrol. 0 means no limit.';
$string['maxenrolledreached'] = 'Maximum number of users allowed to paystackpayment-enrol was already reached.';
$string['canntenrol'] = 'Enrolment is disabled or inactive';
$string['paystackpayment:config'] = 'Configure paystackpayment'; 
$string['paystackpayment:manage'] = 'Manage paystackpayment'; 
$string['paystackpayment:unenrol'] = 'Unenrol paystackpayment';
$string['paystackpayment:unenrolself'] = 'Unenrolself paystackpayment'; 
$string['charge_description1'] = "Create customer for email receipt";
$string['charge_description2'] = 'Charge for Course Enrolment Cost.';
$string['paystack_sorry'] = "Sorry, you can not use the script that way.";
$string['webhook'] = 'Paystack Webhook Url';
$string['webhook_desc'] = 'Add this Webhook Url "{$a->webhook}" to your paystack account developer settings page <a href="{$a->url}">here</a>';
$string['test_webhooksecret'] = 'Test webhook signing secret';
$string['live_webhooksecret'] = 'Live webhook signing secret';
$string['webhooksecret_desc'] = 'Required. Without it every webhook is rejected — including on sites upgraded from 1.4, which used to check webhooks with the secret key. Starts with whsec_. Create it in the Paystack Dashboard under Settings > API Keys & Webhooks. Students are still enrolled when they return from checkout.';
$string['webhooksecret_invalid'] = 'The webhook signing secret must start with whsec_ and be at least 22 characters with no spaces.';
$string['secret_saved'] = 'Saved. Leave blank to keep it.';
$string['secret_saved_last4'] = 'Saved: …{$a}. Leave blank to keep it.';
$string['live_restrictedkey'] = 'Live restricted key';
$string['live_restrictedkey_desc'] = 'A live restricted key, starting with rk_live_.';
$string['test_restrictedkey'] = 'Test restricted key';
$string['test_restrictedkey_desc'] = 'A test restricted key, starting with rk_test_.';
$string['restrictedkeys'] = 'Restricted keys';
$string['restrictedkeys_desc'] = '<ol>
<li>Create a restricted key in your <a href="https://dashboard.paystack.com/v2/developers/api-configuration" target="_blank" rel="noopener">Paystack Dashboard</a>.</li>
<li>Turn on only Account: read and Transactions: read. Leave every other permission off.</li>
<li>Paste a test key in Test and a live key in Live.</li>
<li>You do not need a public key.</li>
</ol>';
$string['webhooksecrets'] = 'Webhook signing secrets';
$string['webhooksecrets_desc'] = 'Used to verify that webhooks really come from Paystack.';
$string['secretkeys'] = 'Use secret keys instead';
$string['secretkeys_desc'] = 'Only if you can\'t use a restricted key. A saved restricted key replaces the secret key for that mode, and a secret key can\'t be saved while a restricted key is saved for the same mode.';
$string['rk_mode_live'] = 'live';
$string['rk_mode_test'] = 'test';
$string['rk_not_saved'] = 'Other settings on this page were saved; the previous key is still in use.';
$string['rk_put_secret_below'] = 'This field takes a restricted key (rk_…). Put a secret key under "Use secret keys instead".';
$string['rk_invalid_shape'] = 'This is not a restricted key. It should start with rk_test_ or rk_live_ and be copied in full.';
$string['rk_wrong_mode'] = 'This is a {$a->key} key; paste it in the {$a->field} field.';
$string['rk_invalid_key'] = 'Paystack doesn\'t recognise this key. Check it was copied in full, hasn\'t been revoked, and that restricted keys are enabled for your Paystack account.';
$string['rk_missing_scope'] = 'This key is missing permissions the plugin needs: {$a}. Turn them on in the Paystack Dashboard.';
$string['rk_sensitive_scope'] = 'This key has permissions the plugin doesn\'t need: {$a}. Turn them off in the Paystack Dashboard, or create a key with only Account: read and Transactions: read.';
$string['rk_blocked'] = 'Paystack refused the check from this server. If your integration uses an IP whitelist, add this server\'s outgoing IP address.';
$string['rk_transient'] = 'Couldn\'t reach Paystack to check this key. Try saving again.';
$string['rk_account'] = 'Couldn\'t confirm this key\'s public key with Paystack. Make sure the key has Account: read and is a {$a} key.';
$string['sk_invalid'] = 'The secret key must not contain spaces or other invalid characters.';
$string['sk_put_rk_above'] = 'This is a restricted key. Put it under "Restricted keys" above.';
$string['sk_wrong_mode'] = 'This is a {$a->key} key; paste it in the {$a->field} field.';
$string['sk_remove_rk_first'] = 'Remove the saved {$a} restricted key first (tick \'Remove the saved key\' under it), then save your secret key.';
$string['secret_remove'] = 'Remove the saved key';
$string['rk_scope_account:read'] = 'Account: read';
$string['rk_scope_transactions:read'] = 'Transactions: read';
$string['rk_scope_transactions:write'] = 'Transactions: write';
$string['rk_scope_disputes_refunds'] = 'Disputes and refunds';
$string['rk_scope_bulk_charges'] = 'Bulk charges';
$string['rk_scope_direct_debit'] = 'Direct debit';
$string['rk_scope_preauthorizations'] = 'Preauthorizations';
$string['rk_scope_settlements'] = 'Settlements';
$string['rk_scope_subaccounts'] = 'Subaccounts';
$string['rk_scope_transfers'] = 'Transfers';
$string['rk_scope_virtual_accounts'] = 'Virtual accounts';
$string['rk_scope_webhooks'] = 'Webhooks';
$string['rk_scope_connect'] = 'Connect';
$string['rk_scope_payouts'] = 'Payouts';
$string['keyfailure_401'] = "Paystack rejected the restricted key: it may be invalid or revoked, or restricted keys aren't enabled for your Paystack account. Check the key in the plugin settings.";
$string['keyfailure_403'] = "Paystack refused the restricted key: it needs Account: read and Transactions: read, or your integration's IP whitelist is blocking this server.";
$string['warning_nowebhooksecret'] = 'Webhooks are rejected until you add the {$a} webhook signing secret. Students are still enrolled when they return from checkout.';
$string['warning_rkunchecked'] = "The {\$a} restricted key hasn't been checked with Paystack. Paste it again and save.";
$string['warning_rkinsecretfield'] = 'The {$a} secret key field holds a restricted key. Paste it into the {$a} restricted key field instead.';
$string['notconfigured'] = 'Paystack payments aren\'t set up for this course yet. Please contact the site administrator.';
$string['warning_nokey'] = 'There is no usable {$a} key, so students can\'t pay. Add a restricted key, or a secret key and public key.';
