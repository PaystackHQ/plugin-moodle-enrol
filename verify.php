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
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/filelib.php');

require_login();

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

$sorry = get_string('paystack_sorry', 'enrol_paystack');
$home = $CFG->wwwroot . '/';

$reference = optional_param('reference', '', PARAM_RAW);
if (empty(optional_param('paystack-trxref', '', PARAM_RAW)) || !\enrol_paystack\paystack::is_valid_reference($reference)) {
    notice($sorry, $home);
}

$plugin = enrol_get_plugin('paystack');
$paystack = new \enrol_paystack\paystack('moodle-enrol', $plugin->get_publickey(), $plugin->get_secretkey());

$res = $paystack->verify_transaction($reference);
$body = $res['body'];
if ($res['http'] < 200 || $res['http'] > 299 || empty($body['status']) || !isset($body['data']) || !is_array($body['data'])) {
    \enrol_paystack\util::notify_key_failure($res['http'], (object) ['reference' => $reference]);
    notice($sorry, $home);
}
$txdata = $body['data'];

$outcome = \enrol_paystack\enrolment::process($txdata, $reference, 'return', $USER->id, $paystack);
$result = $outcome['status'];
$courseurl = $outcome['courseid'] === null ? $home : new moodle_url('/course/view.php', ['id' => $outcome['courseid']]);

if ($result === 'enrolled' || $result === 'already_enrolled') {
    $course = $DB->get_record('course', ['id' => $outcome['courseid']], '*', MUST_EXIST);
    $fullname = format_string($course->fullname, true, ['context' => context_course::instance($course->id)]);
    redirect($courseurl, get_string('paymentthanks', '', $fullname));
}

if ($result === 'not_ours' || $result === 'wrong_user') {
    $courseurl = $home;
}
notice($sorry, $courseurl);
