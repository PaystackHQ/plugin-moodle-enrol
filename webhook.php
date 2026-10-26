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
 * Listens for Instant Payment Webhook Notification from Paystack
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

// @codingStandardsIgnoreLine This script does not require login.
require("../../config.php");
require_once("lib.php");
require_once($CFG->libdir.'/enrollib.php');
require_once($CFG->libdir . '/filelib.php');

// Paystack does not like when we return error messages here,
// the custom handler just logs exceptions and stops.
set_exception_handler(\enrol_paystack\util::get_exception_handler());

// Make sure we are enabled in the first place.
if (!enrol_is_enabled('paystack')) {
    http_response_code(503);
    throw new moodle_exception('errdisabled', 'enrol_paystack');
}

// Keep out casual intruders.
if ((strtoupper($_SERVER['REQUEST_METHOD']) != 'POST' ) || !array_key_exists('HTTP_X_PAYSTACK_SIGNATURE', $_SERVER)) {
    http_response_code(400);
    throw new moodle_exception('invalidrequest', 'core_error');
}

$input = @file_get_contents("php://input");
$values = (array) json_decode($input, true);
$metadata = $values['data']['metadata'] ?? [];
if (!is_array($metadata)) {
    $metadata = [];
}

$data = new stdClass();

foreach ($metadata as $key => $value) {
    if ($key !== clean_param($key, PARAM_ALPHANUMEXT)) {
        throw new moodle_exception('invalidrequest', 'core_error', '', null, $key);
    }
    if (is_array($value)) {
        throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Unexpected array param: ' . $key);
    }
    $data->$key = fix_utf8($value);
}
if (empty($data->custom)) {
    throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Missing request param: custom');
}
$custom = explode('-', $data->custom);
unset($data->custom);
if (empty($custom) || count($custom) < 3) {
    throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Invalid value of the request param: custom');
}
$data->userid           = (int) $custom[0];
$data->courseid         = (int) $custom[1];
$data->instanceid       = (int) $custom[2];
$data->payment_gross    = $data->amount;
$data->payment_currency = $data->currency_code;
$data->timeupdated      = time();

$plugin = enrol_get_plugin('paystack');
$paystack = new \enrol_paystack\paystack('moodle-enrol', $plugin->get_publickey(), $plugin->get_secretkey());

// validate event do all at once to avoid timing attack
if($paystack->validate_webhook($input)){
    http_response_code(400);
    throw new moodle_exception('invalidrequest', 'core_error');
}

// Get the user and course records.
$user = $DB->get_record("user", array("id" => $data->userid), "*", MUST_EXIST);
$course = $DB->get_record("course", array("id" => $data->courseid), "*", MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

$PAGE->set_context($context);

// Use the queried course's full name for the item_name field.
$data->item_name = $course->fullname;

$plugin_instance = $DB->get_record("enrol", array("id" => $data->instanceid, "enrol" => "paystack", "status" => 0), "*", MUST_EXIST);

if (is_enrolled($context, $data->userid, '', true)) {
    \enrol_paystack\util::message_paystack_error_to_admin(
        "Webhook Stopped: User already enrolled",
        $data
    );
    die;
}

// Verify Transaction 
$res = $paystack->verify_transaction($data->reference);

if (!empty($res['status'])) {
    // Send the file, this line will be reached if no error was thrown above.
    $data->tax = $res['data']['amount'] / 100;
    $data->memo = $res['data']['gateway_response'];
    $data->payment_status = $res['data']['status'];

    // If currency is incorrectly set then someone maybe trying to cheat the system
    if ($data->currency_code != $plugin_instance->currency) {
        \enrol_paystack\util::message_paystack_error_to_admin(
            "Currency does not match course settings, received: " . $data->currency_code,
            $data
        );
        die;
    }

    // Check that amount paid is the correct amount
    if ((float) $plugin_instance->cost <= 0) {
        $cost = (float) $plugin->get_config('cost');
    } else {
        $cost = (float) $plugin_instance->cost;
    }

    // Use the same rounding of floats as on the enrol form.
    $cost = format_float($cost, 2, false);

    // If cost is greater than payment_gross, then someone maybe trying to cheat the system
    if ($data->payment_gross < $cost) {
        \enrol_paystack\util::message_paystack_error_to_admin(
            "Amount paid is not enough ($data->payment_gross < $cost))",
            $data
        );
        die;
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
            $a->coursename = format_string($course->fullname, true, array('context' => $context));
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
        \enrol_paystack\util::message_paystack_error_to_admin(
            "Payment status not successful" . $data->memo,
            $data
        );
        die;
    }
}
