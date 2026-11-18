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

// Only used to locate the transaction; everything authoritative comes from the verify response.
$reference = $values['data']['reference'] ?? null;
if (!is_string($reference) || $reference === '') {
    throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Missing reference');
}

$data = new stdClass();
$data->reference = $reference;

$plugin = enrol_get_plugin('paystack');
$paystack = new \enrol_paystack\paystack('moodle-enrol', $plugin->get_publickey(), $plugin->get_secretkey());

// validate event do all at once to avoid timing attack
if($paystack->validate_webhook($input)){
    http_response_code(400);
    throw new moodle_exception('invalidrequest', 'core_error');
}

// Verify Transaction
$res = $paystack->verify_transaction($reference);

if (!empty($res['status']) && !empty($res['data']) && is_array($res['data'])) {
    $txdata = $res['data'];

    $ids = \enrol_paystack\enrolment::parse_custom($txdata['metadata'] ?? null);
    if ($ids === null) {
        \enrol_paystack\util::message_paystack_error_to_admin("Webhook Stopped: invalid transaction metadata", $data);
        die;
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

    $data->payment_gross    = ((int) ($txdata['amount'] ?? 0)) / 100;
    $data->payment_currency = $txdata['currency'] ?? null;
    $data->tax              = ((int) ($txdata['amount'] ?? 0)) / 100;
    $data->memo             = $txdata['gateway_response'] ?? null;
    $data->payment_status   = $txdata['status'] ?? null;
    $data->txn_id           = $reference;

    if (is_enrolled($context, $data->userid, '', true)) {
        \enrol_paystack\util::message_paystack_error_to_admin(
            "Webhook Stopped: User already enrolled",
            $data
        );
        die;
    }

    $cost = \enrol_paystack\enrolment::resolve_cost($plugin_instance, $plugin->get_config('cost'));
    $problem = \enrol_paystack\enrolment::check_payment($txdata, $plugin_instance, $cost);
    if ($problem !== null) {
        \enrol_paystack\util::message_paystack_error_to_admin($problem, $data);
        die;
    }

    if (\enrol_paystack\enrolment::reference_used($reference)) {
        \enrol_paystack\util::message_paystack_error_to_admin("Webhook Stopped: reference already used", $data);
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
