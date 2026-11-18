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
 * Tests for the key failure notification.
 *
 * @package    enrol_paystack
 * @copyright  2019 Paystack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_paystack\util
 */
class util_test extends \advanced_testcase {

    private const RK = 'rk_test_' . 'abcdef1234567890';
    private const SK = 'sk_test_' . 'abcdef1234567890';

    private function enable_plugin(): void {
        // Moodle hides the message providers of disabled enrol plugins.
        set_config('enrol_plugins_enabled', 'manual,paystack');
    }

    private function data(): \stdClass {
        return (object) ['reference' => 'ref1', 'userid' => 2];
    }

    private function use_rk(): void {
        $this->resetAfterTest();
        $this->enable_plugin();
        $plugin = enrol_get_plugin('paystack');
        $plugin->set_config('mode', 0);
        $plugin->set_config('test_restrictedkey', self::RK);
    }

    public function test_restricted_key_401_notifies_once_per_hour(): void {
        $this->use_rk();
        $sink = $this->redirectMessages();

        util::notify_key_failure(401, $this->data());
        $this->assertCount(1, $sink->get_messages());

        util::notify_key_failure(401, $this->data());
        $this->assertCount(1, $sink->get_messages());

        set_config('keyfailure_last', time() - 3601, 'enrol_paystack');
        util::notify_key_failure(401, $this->data());
        $this->assertCount(2, $sink->get_messages());
    }

    public function test_restricted_key_403_notifies(): void {
        $this->use_rk();
        $sink = $this->redirectMessages();
        util::notify_key_failure(403, $this->data());
        $this->assertCount(1, $sink->get_messages());
    }

    public function test_other_statuses_do_not_notify(): void {
        $this->use_rk();
        $sink = $this->redirectMessages();
        util::notify_key_failure(500, $this->data());
        util::notify_key_failure(0, $this->data());
        $this->assertCount(0, $sink->get_messages());
    }

    public function test_secret_key_does_not_notify(): void {
        $this->resetAfterTest();
        $this->enable_plugin();
        $plugin = enrol_get_plugin('paystack');
        $plugin->set_config('mode', 0);
        $plugin->set_config('test_restrictedkey', '');
        $plugin->set_config('test_secretkey', self::SK);
        $sink = $this->redirectMessages();
        util::notify_key_failure(401, $this->data());
        $this->assertCount(0, $sink->get_messages());
    }

    public function test_message_never_contains_the_key(): void {
        $this->use_rk();
        $sink = $this->redirectMessages();
        util::notify_key_failure(401, $this->data());
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertStringNotContainsString(self::RK, $messages[0]->subject);
        $this->assertStringNotContainsString(self::RK, $messages[0]->fullmessage);
    }
}
