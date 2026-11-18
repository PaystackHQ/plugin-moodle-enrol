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

// Unauthenticated callers get no detail and leave no log trace.
if ((strtoupper($_SERVER['REQUEST_METHOD']) != 'POST' ) || !array_key_exists('HTTP_PAYSTACK_SIGNATURE', $_SERVER)) {
    http_response_code(400);
    exit;
}

$plugin = enrol_get_plugin('paystack');
$input = @file_get_contents("php://input");

// Authenticate the raw body before parsing it.
if (!\enrol_paystack\webhook_signature::is_authentic($input, $_SERVER, time(), $plugin->get_webhooksecret())) {
    http_response_code(400);
    exit;
}

$values = (array) json_decode($input, true);

// Acknowledge other events (e.g. the dashboard ping) without processing them.
if (($values['event'] ?? null) !== 'charge.success') {
    http_response_code(200);
    exit;
}

// Only used to locate the transaction; everything authoritative comes from the verify response.
$reference = $values['data']['reference'] ?? null;
if (!\enrol_paystack\paystack::is_valid_reference($reference)) {
    error_log('enrol_paystack webhook: invalid transaction reference');
    http_response_code(200);
    exit;
}

$paystack = new \enrol_paystack\paystack('moodle-enrol', $plugin->get_publickey(), $plugin->get_secretkey());

$res = $paystack->verify_transaction($reference);
$body = $res['body'];

if ($res['http'] < 200 || $res['http'] > 299 || empty($body['status']) || !isset($body['data']) || !is_array($body['data'])) {
    if ($res['http'] === 401 || $res['http'] === 403) {
        \enrol_paystack\util::notify_key_failure($res['http'], (object) ['reference' => $reference]);
        http_response_code(500);
        exit;
    }
    if ($res['http'] === 0 || $res['http'] === 429 || $res['http'] >= 500) {
        // Transient: let Paystack retry.
        http_response_code(500);
        exit;
    }
    error_log('enrol_paystack webhook: transaction verification failed (HTTP ' . (int) $res['http'] . ')');
    http_response_code(200);
    exit;
}

$outcome = \enrol_paystack\enrolment::process($body['data'], $reference, 'webhook', null, $paystack);
http_response_code($outcome['status'] === 'busy' ? 500 : 200);
