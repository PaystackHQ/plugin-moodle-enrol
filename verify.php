<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * Listens for Instant Payment Notification from Paystack
 *
 * This script waits for Payment notification from Paystack,
 * then double checks that data by sending it back to Paystack.
 * If Paystack verifies this then it sets up the enrolment for that
 * user.
 *
 * @package    enrol_paystack
 * @copyright  2019 Paystack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Disable moodle specific debug messages and any errors in output,
// comment out when debugging or better look into error log!
define('NO_DEBUG_DISPLAY', true);

require('../../config.php');
require_once('lib.php');
if ($CFG->version < 2018101900) {
    require_once($CFG->libdir . '/eventslib.php');
}
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/filelib.php');

require_login();

// Paystack does not like when we return error messages here,
// the custom handler just logs exceptions and stops.
//set_exception_handler('enrol_paystack_charge_exception_handler');

set_exception_handler(array('enrol_paystack\util', 'get_exception_handler'));


// Make sure we are enabled in the first place.
if (!enrol_is_enabled('paystack')) {
    http_response_code(503);
    throw new moodle_exception('errdisabled', 'enrol_paystack');
}

// Keep out casual intruders.
if (empty($_POST) or !empty($_GET)) {
    http_response_code(400);
    throw new moodle_exception('invalidrequest', 'core_error');
}
if (empty(required_param('paystack-trxref', PARAM_RAW))) {
    throw new moodle_exception('paystack_sorry', 'enrol_paystack');
}

$data = new stdClass();

foreach ($_POST as $key => $value) {
    if ($key !== clean_param($key, PARAM_ALPHANUMEXT)) {
        throw new moodle_exception('invalidrequest', 'core_error', '', null, $key);
    }
    if (is_array($value)) {
        throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Unexpected array param: ' . $key);
    }
    $data->$key = fix_utf8($value);
}

if (empty($data->reference)) {
    throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Missing request param: reference');
}
unset($data->custom, $data->id);

$plugin = enrol_get_plugin('paystack');
$paystack = new \enrol_paystack\paystack('moodle-enrol', $plugin->get_publickey(), $plugin->get_secretkey());

// Everything below is taken from the verified transaction, never from the POST body.
$res = $paystack->verify_transaction($data->reference);
$body = $res['body'];
if ($res['http'] < 200 || $res['http'] > 299 || empty($body['status']) || !isset($body['data']) || !is_array($body['data'])) {
    \enrol_paystack\util::notify_key_failure($res['http'], $data);
    notice(get_string('paystack_sorry', 'enrol_paystack'), $CFG->wwwroot . '/');
}
$txdata = $body['data'];

$ids = \enrol_paystack\enrolment::parse_custom($txdata['metadata'] ?? null);
if ($ids === null || $ids['userid'] !== (int) $USER->id) {
    notice(get_string('paystack_sorry', 'enrol_paystack'), $CFG->wwwroot . '/');
}

$data->userid           = $ids['userid'];
$data->courseid         = $ids['courseid'];
$data->instanceid       = $ids['instanceid'];
$data->timeupdated      = time();

// Get the user and course records.
$user = $DB->get_record("user", array("id" => $data->userid), "*", MUST_EXIST);
$course = $DB->get_record("course", array("id" => $data->courseid), "*", MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);
$PAGE->set_context($context);

// Use the queried course's full name for the item_name field.
$data->item_name = $course->fullname;

$plugin_instance = $DB->get_record("enrol", array("id" => $data->instanceid, "enrol" => "paystack", "status" => 0), "*", MUST_EXIST);

// Set Course Url
$courseUrl = "$CFG->wwwroot/course/view.php?id=$course->id";

$data->payment_gross    = ((int) ($txdata['amount'] ?? 0)) / 100;
$data->payment_currency = $txdata['currency'] ?? null;
$data->tax              = ((int) ($txdata['amount'] ?? 0)) / 100;
$data->memo             = $txdata['gateway_response'] ?? null;
$data->payment_status   = $txdata['status'] ?? null;
$data->reason_code      = null;
$data->txn_id           = $data->reference;

$cost = \enrol_paystack\enrolment::resolve_cost($plugin_instance, $plugin->get_config('cost'));
$problem = \enrol_paystack\enrolment::check_payment($txdata, $plugin_instance, $cost);
if ($problem !== null) {
    \enrol_paystack\util::message_paystack_error_to_admin($problem, $data);
    notice(get_string('paystack_sorry', 'enrol_paystack'), $courseUrl);
}

$fullname = format_string($course->fullname, true, array('context' => $context));

if (is_enrolled($context, null, '', true)) {
    redirect($courseUrl, get_string('paymentthanks', '', $fullname));
}

if (\enrol_paystack\enrolment::reference_used($data->reference)) {
    \enrol_paystack\util::message_paystack_error_to_admin('Payment reference already used', $data);
    notice(get_string('paystack_sorry', 'enrol_paystack'), $courseUrl);
}

if ($data->payment_status == 'success') {
    // ALL CLEAR !
    $paystack->log_transaction_success($data->reference);
    $DB->insert_record("enrol_paystack", $data);
    if ($plugin_instance->enrolperiod) {
        $timestart = time();
        $timeend   = $timestart + $plugin_instance->enrolperiod;
    } else {
        $timestart = 0;
        $timeend   = 0;
    }
    // Enrol user.
    $plugin->enrol_user($plugin_instance, $user->id, $plugin_instance->roleid, $timestart, $timeend);
    // Pass $view=true to filter hidden caps if the user cannot see them.
    if ($users = get_users_by_capability(
        $context,
        'moodle/course:update',
        'u.*',
        'u.id ASC',
        '',
        '',
        '',
        '',
        false,
        true
    )) {
        $users = sort_by_roleassignment_authority($users, $context);
        $teacher = array_shift($users);
    } else {
        $teacher = false;
    }
    $mailstudents = $plugin->get_config('mailstudents');
    $mailteachers = $plugin->get_config('mailteachers');
    $mailadmins   = $plugin->get_config('mailadmins');
    $shortname = format_string($course->shortname, true, array('context' => $context));
    if (!empty($mailstudents)) {
        $a = new stdClass();
        $a->course = format_string($course->fullname, true, array('context' => $context));
        $a->profileurl = "$CFG->wwwroot/user/view.php?id=$user->id";
        $eventdata = new \core\message\message();
        $eventdata->modulename        = 'moodle';
        $eventdata->component         = 'enrol_paystack';
        $eventdata->name              = 'paystack_enrolment';
        $eventdata->userfrom          = empty($teacher) ? core_user::get_support_user() : $teacher;
        $eventdata->userto            = $user;
        $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
        $eventdata->fullmessage       = get_string('welcometocoursetext', '', $a);
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = '';
        $eventdata->smallmessage      = '';
        message_send($eventdata);
    }
    if (!empty($mailteachers) && !empty($teacher)) {
        $a = new stdClass();
        $a->course = format_string($course->fullname, true, array('context' => $context));
        $a->user = fullname($user);
        $eventdata = new \core\message\message();
        $eventdata->modulename        = 'moodle';
        $eventdata->component         = 'enrol_paystack';
        $eventdata->name              = 'paystack_enrolment';
        $eventdata->userfrom          = $user;
        $eventdata->userto            = $teacher;
        $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
        $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = '';
        $eventdata->smallmessage      = '';
        message_send($eventdata);
    }
    if (!empty($mailadmins)) {
        $a = new stdClass();
        $a->course = format_string($course->fullname, true, array('context' => $context));
        $a->user = fullname($user);
        $admins = get_admins();
        foreach ($admins as $admin) {
            $eventdata = new \core\message\message();
            $eventdata->modulename        = 'moodle';
            $eventdata->component         = 'enrol_paystack';
            $eventdata->name              = 'paystack_enrolment';
            $eventdata->userfrom          = $user;
            $eventdata->userto            = $admin;
            $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
            $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = '';
            $eventdata->smallmessage      = '';
            message_send($eventdata);
        }
    }
} else {
    $message = "Payment status not successful" . $data->memo;
    \enrol_paystack\util::message_paystack_error_to_admin(
        $message,
        $data
    );
    notice($message, $courseUrl);
}

if (is_enrolled($context, null, '', true)) {
    redirect($courseUrl, get_string('paymentthanks', '', $fullname));
} else {   // Somehow they aren't enrolled yet!
    $PAGE->set_url($courseUrl);
    echo $OUTPUT->header();
    $a = new stdClass();
    $a->teacher = get_string('defaultcourseteacher');
    $a->fullname = $fullname;
    notice(get_string('paymentsorry', '', $a), $courseUrl);
}