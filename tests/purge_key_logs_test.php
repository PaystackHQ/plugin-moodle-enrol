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

namespace enrol_paystack\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the key log purge task.
 *
 * @package    enrol_paystack
 * @copyright  2019 Paystack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_paystack\task\purge_key_logs
 */
class purge_key_logs_test extends \advanced_testcase {

    private function log_row(string $eventname, array $other): int {
        global $DB;
        return $DB->insert_record('logstore_standard_log', (object) [
            'eventname' => $eventname,
            'component' => 'core',
            'action' => 'created',
            'target' => 'config_log',
            'crud' => 'c',
            'edulevel' => 0,
            'contextid' => 1,
            'contextlevel' => 10,
            'contextinstanceid' => 0,
            'userid' => 2,
            'anonymous' => 0,
            'other' => json_encode($other),
            'timecreated' => time(),
        ]);
    }

    public function test_purges_only_our_key_settings_and_blanks_presets(): void {
        global $DB;
        $this->resetAfterTest();
        $event = '\\core\\event\\config_log_created';

        $ours = [
            $this->log_row($event, ['name' => 'test_secretkey', 'oldvalue' => '', 'value' => 'sk_test_x', 'plugin' => 'enrol_paystack']),
            $this->log_row($event, ['name' => 'live_restrictedkey', 'oldvalue' => '', 'value' => 'rk_live_x', 'plugin' => 'enrol_paystack']),
            $this->log_row($event, ['name' => 'test_webhooksecret', 'oldvalue' => 'a', 'value' => 'b', 'plugin' => 'enrol_paystack']),
        ];
        $kept = [
            $this->log_row($event, ['name' => 'test_secretkey', 'oldvalue' => '', 'value' => 'x', 'plugin' => 'enrol_other']),
            $this->log_row($event, ['name' => 'test_publickey', 'oldvalue' => '', 'value' => 'pk', 'plugin' => 'enrol_paystack']),
            $this->log_row('\\core\\event\\user_created', ['name' => 'test_secretkey', 'plugin' => 'enrol_paystack']),
        ];

        $preset = $DB->insert_record('adminpresets', (object) [
            'userid' => 2, 'name' => 'p', 'comments' => '', 'comments_format' => 1, 'site' => 'x',
            'author' => 'a', 'moodleversion' => '1', 'moodlerelease' => '1', 'iscore' => 0,
            'timecreated' => time(), 'timeimported' => 0,
        ]);
        $secretitem = $DB->insert_record('adminpresets_it', (object) [
            'adminpresetid' => $preset, 'plugin' => 'enrol_paystack', 'name' => 'test_secretkey', 'value' => 'sk_test_x']);
        $otheritem = $DB->insert_record('adminpresets_it', (object) [
            'adminpresetid' => $preset, 'plugin' => 'enrol_paystack', 'name' => 'mode', 'value' => '1']);

        (new purge_key_logs())->execute();

        foreach ($ours as $id) {
            $this->assertFalse($DB->record_exists('logstore_standard_log', ['id' => $id]));
        }
        foreach ($kept as $id) {
            $this->assertTrue($DB->record_exists('logstore_standard_log', ['id' => $id]));
        }
        $this->assertSame('', $DB->get_field('adminpresets_it', 'value', ['id' => $secretitem]));
        $this->assertSame('1', $DB->get_field('adminpresets_it', 'value', ['id' => $otheritem]));
    }
}
