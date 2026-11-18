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
 * Tests for the Paystack API client helpers.
 *
 * @package    enrol_paystack
 * @copyright  2019 Paystack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_paystack\paystack
 */
class paystack_test extends \advanced_testcase {

    public function test_valid_references(): void {
        foreach (['abc123', 'T_123.456-789=', str_repeat('a', 100)] as $reference) {
            $this->assertTrue(paystack::is_valid_reference($reference), $reference);
        }
    }

    public function test_invalid_references(): void {
        $invalid = ['../../transfer', 'a/b', 'a b', '', str_repeat('a', 101), "\u{00e9}clair", "abc\n", null, 123];
        foreach ($invalid as $reference) {
            $this->assertFalse(paystack::is_valid_reference($reference));
        }
    }

    public function test_verify_rejects_invalid_reference_without_request(): void {
        $paystack = new paystack('moodle-enrol', 'pk', 'sk');
        $this->assertSame(['http' => 0, 'body' => null], $paystack->verify_transaction('../../transfer'));
    }
}
