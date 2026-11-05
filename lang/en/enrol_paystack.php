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
$string['live_secretkey_desc'] = 'The Live API Secret Key (sk_live_...) or Restricted Key (rk_live_...) of Paystack account';
$string['live_publickey_desc'] = 'The Live API Public Key of Paystack account';
$string['live_webhooksecret'] = 'Paystack Live Webhook Signing Secret';
$string['live_webhooksecret_desc'] = 'Starts with whsec_. Create it on the Paystack Dashboard under Settings > API Keys & Webhooks. Required once you use a restricted key (rk_live_...) instead of a secret key.';
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
$string['test_secretkey_desc'] = 'The Test API Secret Key (sk_test_...) or Restricted Key (rk_test_...) of Paystack account';
$string['test_publickey_desc'] = 'The Test API Public Key of Paystack account';
$string['test_webhooksecret'] = 'Paystack Test Webhook Signing Secret';
$string['test_webhooksecret_desc'] = 'Starts with whsec_. Create it on the Paystack Dashboard under Settings > API Keys & Webhooks. Required once you use a restricted key (rk_test_...) instead of a secret key.';
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
