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
        return $override + ['status' => 'success', 'currency' => 'NGN', 'amount' => 10000];
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
        $this->assertNotNull(enrolment::check_payment($this->tx(['status' => 'failed']), $this->instance(), 100.0));
    }

    public function test_check_payment_currency(): void {
        $this->assertNotNull(enrolment::check_payment($this->tx(['currency' => 'USD']), $this->instance(), 100.0));
        $this->assertNull(enrolment::check_payment($this->tx(['currency' => 'ngn']), $this->instance(), 100.0));
    }

    public function test_check_payment_amount(): void {
        $this->assertNotNull(enrolment::check_payment($this->tx(['amount' => 9999]), $this->instance(), 100.0));
        $this->assertNull(enrolment::check_payment($this->tx(), $this->instance(), 100.0));
        $this->assertNull(enrolment::check_payment($this->tx(['amount' => 10001]), $this->instance(), 100.0));
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
