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

namespace enrol_paystack;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the payment integrity checks.
 *
 * @package    enrol_paystack
 * @copyright  2019 Paystack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_paystack\enrolment
 */
class enrolment_test extends \advanced_testcase {

    private function instance(): \stdClass {
        return (object) ['currency' => 'NGN', 'cost' => '100.00'];
    }

    private function tx(array $override = []): array {
        return $override + [
            'status' => 'success',
            'currency' => 'NGN',
            'amount' => 10000,
            'domain' => 'test',
            'paid_at' => gmdate('Y-m-d\TH:i:s.000\Z', time() - 60),
        ];
    }

    private function check(array $tx, $guard = null): ?string {
        return enrolment::check_payment($tx, $this->instance(), 100.0, 'test', $guard);
    }

    /**
     * Course, student and paystack instance costing 100 NGN, with the plugin enabled.
     */
    private function setup_course(array $pluginconfig = []): array {
        global $DB;

        set_config('enrol_plugins_enabled', 'manual,paystack');
        $plugin = enrol_get_plugin('paystack');
        foreach ($pluginconfig as $name => $value) {
            set_config($name, $value, 'enrol_paystack');
        }
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $id = $plugin->add_instance($course, [
            'cost' => 100,
            'currency' => 'NGN',
            'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => $roleid,
        ]);
        return [$course, $user, $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST)];
    }

    private function custom($user, $course, $instance): array {
        return ['custom' => "{$user->id}-{$course->id}-{$instance->id}"];
    }

    private function client(): paystack {
        return new class('moodle-enrol', 'pk', 'sk') extends paystack {
            public $logged = [];

            public function log_transaction_success($reference) {
                $this->logged[] = $reference;
            }
        };
    }

    private function error_messages(array $messages): int {
        $count = 0;
        foreach ($messages as $message) {
            if (strpos($message->subject, 'PAYSTACK PAYMENT ERROR') === 0) {
                $count++;
            }
        }
        return $count;
    }

    public function test_parse_custom_array(): void {
        $this->assertSame(
            ['userid' => 3, 'courseid' => 4, 'instanceid' => 5],
            enrolment::parse_custom(['custom' => '3-4-5'])
        );
    }

    public function test_parse_custom_json_string(): void {
        $this->assertSame(
            ['userid' => 3, 'courseid' => 4, 'instanceid' => 5],
            enrolment::parse_custom('{"custom":"3-4-5"}')
        );
    }

    public function test_parse_custom_invalid(): void {
        $this->assertNull(enrolment::parse_custom(null));
        $this->assertNull(enrolment::parse_custom('not json'));
        $this->assertNull(enrolment::parse_custom([]));
        $this->assertNull(enrolment::parse_custom(['custom' => '3-4']));
        $this->assertNull(enrolment::parse_custom(['custom' => '3-4-5-6']));
        $this->assertNull(enrolment::parse_custom(['custom' => 'a-4-5']));
        $this->assertNull(enrolment::parse_custom(['custom' => '0-4-5']));
        $this->assertNull(enrolment::parse_custom(['custom' => '-3-4-5']));
        $this->assertNull(enrolment::parse_custom(['custom' => ['3-4-5']]));
    }

    public function test_check_payment_not_success(): void {
        $this->assertNotNull($this->check($this->tx(['status' => 'failed'])));
    }

    public function test_check_payment_currency(): void {
        $this->assertNotNull($this->check($this->tx(['currency' => 'USD'])));
        $this->assertNull($this->check($this->tx(['currency' => 'ngn'])));
    }

    public function test_check_payment_amount(): void {
        $this->assertNotNull($this->check($this->tx(['amount' => 9999])));
        $this->assertNull($this->check($this->tx()));
        $this->assertNull($this->check($this->tx(['amount' => 10001])));
    }

    public function test_check_payment_domain(): void {
        $this->assertNotNull($this->check($this->tx(['domain' => 'live'])));
        $tx = $this->tx();
        unset($tx['domain']);
        $this->assertNotNull($this->check($tx));
        $this->assertNotNull(enrolment::check_payment($this->tx(), $this->instance(), 100.0, 'live'));
    }

    public function test_check_payment_replay_guard(): void {
        $guard = time() - 3600;
        $this->assertNull($this->check($this->tx(), $guard));
        $this->assertNotNull($this->check($this->tx(['paid_at' => gmdate('c', $guard - 10)]), $guard));

        $tx = $this->tx();
        unset($tx['paid_at']);
        $this->assertNotNull($this->check($tx, $guard));
        $this->assertNotNull($this->check($this->tx(['paid_at' => 'not a date']), $guard));
        $this->assertNull($this->check($this->tx(['paid_at' => gmdate('c', $guard - 10)]), null));

        $tx['transaction_date'] = gmdate('c');
        $this->assertNull($this->check($tx, $guard));
    }

    public function test_process_not_ours(): void {
        $this->resetAfterTest();
        [$course, $user, $instance] = $this->setup_course();
        $sink = $this->redirectMessages();

        $this->assertSame('not_ours', enrolment::process($this->tx(), 'ref1', 'webhook')['status']);
        $other = $this->getDataGenerator()->create_course();
        $mismatch = $this->tx(['metadata' => ['custom' => "{$user->id}-{$other->id}-{$instance->id}"]]);
        $this->assertSame('not_ours', enrolment::process($mismatch, 'ref1', 'webhook')['status']);
        $this->assertSame(0, $this->error_messages($sink->get_messages()));
    }

    public function test_process_wrong_user(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $user, $instance] = $this->setup_course();
        $tx = $this->tx(['metadata' => $this->custom($user, $course, $instance)]);

        $this->assertSame('wrong_user', enrolment::process($tx, 'ref1', 'return', $user->id + 1)['status']);
        $this->assertSame(0, $DB->count_records('enrol_paystack'));
    }

    public function test_process_not_successful_sends_no_admin_message(): void {
        $this->resetAfterTest();
        [$course, $user, $instance] = $this->setup_course();
        $sink = $this->redirectMessages();
        $tx = $this->tx(['status' => 'abandoned', 'metadata' => $this->custom($user, $course, $instance)]);

        $this->assertSame('not_successful', enrolment::process($tx, 'ref1', 'return', $user->id, $this->client())['status']);
        $this->assertSame(0, $this->error_messages($sink->get_messages()));
    }

    public function test_process_payment_mismatch_messages_admin_on_webhook_only(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $user, $instance] = $this->setup_course();
        $sink = $this->redirectMessages();
        $tx = $this->tx(['amount' => 5000, 'metadata' => $this->custom($user, $course, $instance)]);

        $this->assertSame('payment_mismatch', enrolment::process($tx, 'ref1', 'return', $user->id, $this->client())['status']);
        $this->assertSame(0, $this->error_messages($sink->get_messages()));
        $this->assertSame('payment_mismatch', enrolment::process($tx, 'ref1', 'webhook', null, $this->client())['status']);
        $this->assertSame(1, $this->error_messages($sink->get_messages()));
        $this->assertSame(0, $DB->count_records('enrol_paystack'));
        $this->assertFalse(is_enrolled(\context_course::instance($course->id), $user));
    }

    public function test_process_replay_guard_refuses_old_payment(): void {
        $this->resetAfterTest();
        [$course, $user, $instance] = $this->setup_course(['replayguard_since' => time()]);
        $sink = $this->redirectMessages();
        $tx = $this->tx(['paid_at' => gmdate('c', time() - 86400), 'metadata' => $this->custom($user, $course, $instance)]);

        $this->assertSame('payment_mismatch', enrolment::process($tx, 'ref1', 'webhook', null, $this->client())['status']);
        $this->assertSame(1, $this->error_messages($sink->get_messages()));
    }

    public function test_process_enrols_from_verified_data_only(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $user, $instance] = $this->setup_course(['mailstudents' => 0, 'mailteachers' => 0, 'mailadmins' => 0]);
        $client = $this->client();
        $tx = $this->tx(['gateway_response' => 'Successful', 'metadata' => $this->custom($user, $course, $instance)]);
        $_POST = ['business' => 'attacker@example.com', 'receiver_email' => 'x@example.com', 'payment_status' => 'bogus'];

        $this->assertSame('enrolled', enrolment::process($tx, 'ref1', 'return', $user->id, $client)['status']);
        $_POST = [];

        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $user, '', true));
        $rows = $DB->get_records('enrol_paystack');
        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertSame('ref1', $row->txn_id);
        $this->assertSame('success', $row->payment_status);
        $this->assertSame('Successful', $row->memo);
        $this->assertSame($course->fullname, $row->item_name);
        $this->assertEquals($user->id, $row->userid);
        $this->assertNull($row->business);
        $this->assertNull($row->receiver_email);
        $this->assertSame(['ref1'], $client->logged);
    }

    public function test_process_second_call_with_same_reference_is_already_used(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $user, $instance] = $this->setup_course(['mailstudents' => 0]);
        $client = $this->client();
        $tx = $this->tx(['metadata' => $this->custom($user, $course, $instance)]);

        $first = enrolment::process($tx, 'ref1', 'return', $user->id, $client);
        $this->assertSame(['status' => 'enrolled', 'courseid' => (int) $course->id], $first);
        $sink = $this->redirectMessages();
        $this->assertSame('already_used', enrolment::process($tx, 'ref1', 'webhook', null, $client)['status']);
        $this->assertSame(1, $DB->count_records('enrol_paystack'));
        $this->assertSame(0, $this->error_messages($sink->get_messages()));
        $this->assertSame(['ref1'], $client->logged);
    }

    public function test_process_new_reference_for_enrolled_user_is_recorded_without_enrolling(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $user, $instance] = $this->setup_course(['mailstudents' => 1, 'mailadmins' => 1]);
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $client = $this->client();
        $sink = $this->redirectMessages();
        $tx = $this->tx(['metadata' => $this->custom($user, $course, $instance)]);

        $this->assertSame(['status' => 'already_enrolled', 'courseid' => (int) $course->id],
            enrolment::process($tx, 'ref2', 'webhook', null, $client));
        $this->assertTrue(enrolment::reference_used('ref2'));
        $this->assertSame(0, $DB->count_records('user_enrolments', ['enrolid' => $instance->id]));
        $this->assertCount(0, $sink->get_messages());
        $this->assertSame([], $client->logged);
    }

    public function test_process_used_reference_for_unenrolled_user_messages_admin_on_webhook_only(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $user, $instance] = $this->setup_course();
        $DB->insert_record('enrol_paystack', (object) ['txn_id' => 'ref1', 'timeupdated' => time()]);
        $sink = $this->redirectMessages();
        $tx = $this->tx(['metadata' => $this->custom($user, $course, $instance)]);

        $this->assertSame('already_used', enrolment::process($tx, 'ref1', 'return', $user->id, $this->client())['status']);
        $this->assertSame(0, $this->error_messages($sink->get_messages()));
        $this->assertSame('already_used', enrolment::process($tx, 'ref1', 'webhook', null, $this->client())['status']);
        $this->assertSame(1, $this->error_messages($sink->get_messages()));
        $this->assertFalse(is_enrolled(\context_course::instance($course->id), $user));
    }

    public function test_process_survives_a_failing_tracker(): void {
        $this->resetAfterTest();
        [$course, $user, $instance] = $this->setup_course();
        $client = new class extends paystack {
            public function __construct() {
            }

            public function log_transaction_success($reference) {
                throw new \RuntimeException('down');
            }
        };
        $tx = $this->tx(['metadata' => $this->custom($user, $course, $instance)]);
        $log = ini_set('error_log', '/dev/null');

        $this->assertSame('enrolled', enrolment::process($tx, 'ref1', 'return', $user->id, $client)['status']);
        ini_set('error_log', $log);
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $user, '', true));
    }

    public function test_process_notifies_teacher_only(): void {
        $this->resetAfterTest();
        [$course, $user, $instance] = $this->setup_course(['mailstudents' => 0, 'mailteachers' => 1, 'mailadmins' => 0]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $sink = $this->redirectMessages();
        $tx = $this->tx(['metadata' => $this->custom($user, $course, $instance)]);

        $this->assertSame('enrolled', enrolment::process($tx, 'ref1', 'return', $user->id, $this->client())['status']);
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals($teacher->id, $messages[0]->useridto);
        $this->assertStringContainsString($course->fullname, $messages[0]->fullmessage);
    }

    public function test_reference_used(): void {
        global $DB;
        $this->resetAfterTest();

        $this->assertFalse(enrolment::reference_used('ref_abc'));
        $DB->insert_record('enrol_paystack', (object) ['txn_id' => 'ref_abc', 'timeupdated' => time()]);
        $this->assertTrue(enrolment::reference_used('ref_abc'));
        $this->assertFalse(enrolment::reference_used('ref_other'));
    }
}
