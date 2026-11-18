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
 * PayPal enrolment plugin utility class.
 *
 * @package    enrol_paystack
 * @copyright  2019 Paystack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_paystack;

defined('MOODLE_INTERNAL') || die();

/**
 * Paystack enrolment plugin utility class.
 *
 * @package   enrol_paystack
 * @copyright 2019 Paystack
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class util {

    /**
     * Send payment error message to the admin.
     *
     * @param string $subject
     * @param stdClass $data
     */
    public static function message_paystack_error_to_admin($subject, $data)
    {
        $admin = get_admin();
        $site = get_site();
        $message = "$site->fullname:  Transaction failed.\n\n$subject\n\n";
        foreach ($data as $key => $value) {
            $message .= s($key) . " => " . s($value) . "\n";
        }
        $eventdata = new \core\message\message();
        $eventdata->modulename        = 'moodle';
        $eventdata->component         = 'enrol_paystack';
        $eventdata->name              = 'paystack_enrolment';
        $eventdata->userfrom          = $admin;
        $eventdata->userto            = $admin;
        $eventdata->subject           = "PAYSTACK PAYMENT ERROR: " . $subject;
        $eventdata->fullmessage       = $message;
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = '';
        $eventdata->smallmessage      = '';
        message_send($eventdata);
    }

    /**
     * Tell the admin Paystack rejected the restricted key, at most once an hour.
     *
     * @param int $http HTTP status of the failed Paystack call
     * @param stdClass $data
     */
    public static function notify_key_failure(int $http, $data): void
    {
        if ($http !== 401 && $http !== 403) {
            return;
        }
        $key = enrol_get_plugin('paystack')->get_secretkey();
        if (restricted_key::key_type($key) !== 'rk') {
            return;
        }
        $last = (int) get_config('enrol_paystack', 'keyfailure_last');
        if ($last > time() - 3600) {
            return;
        }
        set_config('keyfailure_last', time(), 'enrol_paystack');
        self::message_paystack_error_to_admin(get_string("keyfailure_$http", 'enrol_paystack'), $data);
    }

    /**
     * Silent exception handler.
     *
     * @return callable exception handler
     */
    public static function get_exception_handler() {
        return function($ex) {
            $info = get_exception_info($ex);

            $logerrmsg = "enrol_paystack exception handler: ".$info->message;
            if (debugging('', DEBUG_NORMAL)) {
                $logerrmsg .= ' Debug: '.$info->debuginfo."\n".format_backtrace($info->backtrace, true);
            }
            error_log($logerrmsg);

            if (http_response_code() == 200) {
                http_response_code(500);
            }

            exit(0);
        };
    }

}
