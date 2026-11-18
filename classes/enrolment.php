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
 * Payment integrity checks shared by verify.php and webhook.php.
 *
 * @package    enrol_paystack
 * @copyright  2019 Paystack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_paystack;

defined('MOODLE_INTERNAL') || die();

class enrolment {

    /**
     * Parse "<userid>-<courseid>-<instanceid>" from verified transaction metadata.
     *
     * @param array|string|null $metadata Array or JSON string from the verify response.
     * @return array|null ['userid', 'courseid', 'instanceid'] or null when invalid.
     */
    public static function parse_custom($metadata) {
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        }
        if (!is_array($metadata) || !isset($metadata['custom']) || !is_string($metadata['custom'])) {
            return null;
        }
        $parts = explode('-', $metadata['custom']);
        if (count($parts) !== 3) {
            return null;
        }
        foreach ($parts as $part) {
            if (!ctype_digit($part) || (int) $part < 1) {
                return null;
            }
        }
        return [
            'userid' => (int) $parts[0],
            'courseid' => (int) $parts[1],
            'instanceid' => (int) $parts[2],
        ];
    }

    /**
     * Resolve the course cost the same way the enrol form does.
     *
     * @param \stdClass $instance Enrol instance.
     * @param mixed $defaultcost Plugin-level cost used when the instance has none.
     * @return float
     */
    public static function resolve_cost(\stdClass $instance, $defaultcost) {
        $cost = (float) $instance->cost;
        if ($cost <= 0) {
            $cost = (float) $defaultcost;
        }
        return (float) format_float($cost, 2, false);
    }

    /**
     * Check verified Paystack transaction data against the enrol instance.
     *
     * @param array $txdata The data element of the verify response.
     * @param \stdClass $instance Enrol instance.
     * @param float $cost Resolved course cost in major units.
     * @param string $expecteddomain 'live' or 'test', matching the configured mode.
     * @param int|null $replayguardsince Transactions paid before this timestamp are refused; null disables the check.
     * @return string|null Null when acceptable, otherwise a short reason.
     */
    public static function check_payment(array $txdata, \stdClass $instance, $cost, $expecteddomain, $replayguardsince = null) {
        if (!isset($txdata['status']) || $txdata['status'] !== 'success') {
            return 'Payment status not successful';
        }
        $domain = isset($txdata['domain']) && is_string($txdata['domain']) ? $txdata['domain'] : '';
        if ($domain !== $expecteddomain) {
            return 'Transaction domain does not match the configured mode, received: ' . s($domain);
        }
        $currency = isset($txdata['currency']) && is_string($txdata['currency']) ? strtoupper($txdata['currency']) : '';
        if ($currency !== strtoupper((string) $instance->currency)) {
            return 'Currency does not match course settings, received: ' . s($currency);
        }
        if (!isset($txdata['amount']) || !is_int($txdata['amount'])) {
            return 'Missing or invalid amount';
        }
        $required = (int) round($cost * 100);
        if ($txdata['amount'] < $required) {
            return 'Amount paid is not enough (' . $txdata['amount'] . ' < ' . $required . ')';
        }
        if ($replayguardsince) {
            $paidat = $txdata['paid_at'] ?? ($txdata['transaction_date'] ?? null);
            $paid = is_string($paidat) ? strtotime($paidat) : false;
            if ($paid === false) {
                return 'Missing or unparseable payment date';
            }
            if ($paid < (int) $replayguardsince) {
                return 'Payment was made before this plugin version started verifying payments';
            }
        }
        return null;
    }

    /**
     * Whether a Paystack reference has already been recorded.
     *
     * @param string $reference Paystack transaction reference.
     * @return bool
     */
    public static function reference_used($reference) {
        global $DB;
        return $DB->record_exists('enrol_paystack', ['txn_id' => $reference]);
    }

    /**
     * Verify a transaction against its enrol instance and enrol the user once per reference.
     *
     * @param array $txdata The data element of a successful verify response.
     * @param string $reference Paystack transaction reference.
     * @param string $source 'return' or 'webhook'; only webhook failures message the admin, as students can trigger the return path at will.
     * @param int|null $expecteduserid The logged-in user on the return path, null on the webhook.
     * @param \enrol_paystack\paystack|null $client Client used for the tracker call; built from settings when null.
     * @return array ['status' => not_ours|wrong_user|not_successful|payment_mismatch|busy|already_enrolled|already_used|enrolled,
     *     'courseid' => int|null] with the course set once the metadata resolved to one.
     */
    public static function process(array $txdata, $reference, $source, $expecteduserid = null, $client = null) {
        global $DB;

        $ids = self::parse_custom($txdata['metadata'] ?? null);
        if ($ids === null) {
            return ['status' => 'not_ours', 'courseid' => null];
        }
        if ($expecteduserid !== null && $ids['userid'] !== (int) $expecteduserid) {
            return ['status' => 'wrong_user', 'courseid' => null];
        }

        $instance = $DB->get_record('enrol', [
            'id' => $ids['instanceid'],
            'courseid' => $ids['courseid'],
            'enrol' => 'paystack',
            'status' => 0,
        ], '*', IGNORE_MISSING);
        $user = $DB->get_record('user', ['id' => $ids['userid'], 'deleted' => 0], '*', IGNORE_MISSING);
        $course = $DB->get_record('course', ['id' => $ids['courseid']], '*', IGNORE_MISSING);
        if (!$instance || !$user || !$course) {
            return ['status' => 'not_ours', 'courseid' => null];
        }
        $courseid = (int) $course->id;
        $context = \context_course::instance($course->id);
        $plugin = enrol_get_plugin('paystack');

        if (!isset($txdata['status']) || $txdata['status'] !== 'success') {
            return ['status' => 'not_successful', 'courseid' => $courseid];
        }

        $amount = isset($txdata['amount']) && is_int($txdata['amount']) ? $txdata['amount'] : 0;
        $adminrecord = (object) [
            'reference' => $reference,
            'userid' => $user->id,
            'courseid' => $course->id,
            'instanceid' => $instance->id,
            'payment_status' => 'success',
            'payment_currency' => is_string($txdata['currency'] ?? null) ? $txdata['currency'] : '',
            'payment_gross' => $amount / 100,
        ];

        $cost = self::resolve_cost($instance, $plugin->get_config('cost'));
        $guard = get_config('enrol_paystack', 'replayguard_since');
        $problem = self::check_payment($txdata, $instance, $cost, $plugin->get_mode() ? 'live' : 'test',
            $guard === false || $guard === '' ? null : (int) $guard);
        if ($problem !== null) {
            if ($source === 'webhook') {
                util::message_paystack_error_to_admin($problem, $adminrecord);
            }
            return ['status' => 'payment_mismatch', 'courseid' => $courseid];
        }

        $lock = \core\lock\lock_config::get_lock_factory('enrol_paystack')->get_lock('ref_' . sha1($reference), 10);
        if (!$lock) {
            return ['status' => 'busy', 'courseid' => $courseid];
        }
        try {
            if (self::reference_used($reference)) {
                // A webhook following the return path reuses the reference legitimately; only an unenrolled user is suspicious.
                if ($source === 'webhook' && !is_enrolled($context, $user->id, '', true)) {
                    util::message_paystack_error_to_admin('Payment reference already used', $adminrecord);
                }
                return ['status' => 'already_used', 'courseid' => $courseid];
            }

            $record = (object) [
                'userid' => $user->id,
                'courseid' => $course->id,
                'instanceid' => $instance->id,
                'item_name' => \core_text::substr($course->fullname, 0, 255),
                'txn_id' => $reference,
                'payment_status' => 'success',
                'memo' => \core_text::substr((string) ($txdata['gateway_response'] ?? ''), 0, 255),
                'tax' => (string) ($amount / 100),
                'timeupdated' => time(),
            ];

            if (is_enrolled($context, $user->id, '', true)) {
                $DB->insert_record('enrol_paystack', $record);
                return ['status' => 'already_enrolled', 'courseid' => $courseid];
            }

            $transaction = $DB->start_delegated_transaction();
            try {
                $DB->insert_record('enrol_paystack', $record);
                if ($instance->enrolperiod) {
                    $timestart = time();
                    $timeend = $timestart + $instance->enrolperiod;
                } else {
                    $timestart = 0;
                    $timeend = 0;
                }
                $plugin->enrol_user($instance, $user->id, $instance->roleid, $timestart, $timeend);
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }

        // Best effort: the student is already enrolled.
        try {
            self::send_notifications($plugin, $course, $user, $context);
        } catch (\Throwable $e) {
            error_log('enrol_paystack: sending enrolment notifications failed');
        }

        try {
            if ($client === null) {
                $client = new paystack('moodle-enrol', $plugin->get_publickey(), $plugin->get_secretkey());
            }
            $client->log_transaction_success($reference);
        } catch (\Throwable $e) {
            error_log('enrol_paystack: transaction tracker call failed');
        }

        return ['status' => 'enrolled', 'courseid' => $courseid];
    }

    /**
     * Send the enrolment notifications enabled in the plugin settings.
     *
     * @param \enrol_paystack_plugin $plugin
     * @param \stdClass $course
     * @param \stdClass $user
     * @param \context_course $context
     */
    private static function send_notifications($plugin, $course, $user, $context) {
        global $CFG;

        // Pass $view=true to filter hidden caps if the user cannot see them.
        $teacher = false;
        if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
                '', '', '', '', false, true)) {
            $users = sort_by_roleassignment_authority($users, $context);
            $teacher = array_shift($users);
        }

        $coursename = format_string($course->fullname, true, ['context' => $context]);
        $subject = get_string('enrolmentnew', 'enrol', format_string($course->shortname, true, ['context' => $context]));
        $send = function($from, $to, $text) use ($subject) {
            $eventdata = new \core\message\message();
            $eventdata->modulename        = 'moodle';
            $eventdata->component         = 'enrol_paystack';
            $eventdata->name              = 'paystack_enrolment';
            $eventdata->userfrom          = $from;
            $eventdata->userto            = $to;
            $eventdata->subject           = $subject;
            $eventdata->fullmessage       = $text;
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = '';
            $eventdata->smallmessage      = '';
            message_send($eventdata);
        };

        if ($plugin->get_config('mailstudents')) {
            $a = new \stdClass();
            $a->coursename = $coursename;
            $a->profileurl = "$CFG->wwwroot/user/view.php?id=$user->id";
            $send(empty($teacher) ? \core_user::get_support_user() : $teacher, $user, get_string('welcometocoursetext', '', $a));
        }

        $newuser = new \stdClass();
        $newuser->course = $coursename;
        $newuser->user = fullname($user);
        $text = get_string('enrolmentnewuser', 'enrol', $newuser);
        if ($plugin->get_config('mailteachers') && !empty($teacher)) {
            $send($user, $teacher, $text);
        }
        if ($plugin->get_config('mailadmins')) {
            foreach (get_admins() as $admin) {
                $send($user, $admin, $text);
            }
        }
    }
}
