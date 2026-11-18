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
        $invalid = ['../../transfer', '..', '.', '...', 'a/b', 'a b', '', str_repeat('a', 101), "\u{00e9}clair", "abc\n", null, 123];
        foreach ($invalid as $reference) {
            $this->assertFalse(paystack::is_valid_reference($reference));
        }
    }

    public function test_verify_rejects_invalid_reference_without_request(): void {
        $paystack = new paystack('moodle-enrol', 'pk', 'sk');
        $this->assertSame(['http' => 0, 'body' => null, 'data' => null], $paystack->verify_transaction('../../transfer'));
    }

    public function test_curl_options_hardening_without_proxy(): void {
        $this->resetAfterTest();
        set_config('proxyhost', '');

        $options = http::curl_options('https://api.paystack.co/', 7, 3);
        $this->assertSame(7, $options[CURLOPT_TIMEOUT]);
        $this->assertSame(3, $options[CURLOPT_CONNECTTIMEOUT]);
        $this->assertFalse($options[CURLOPT_FOLLOWLOCATION]);
        $this->assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
        $this->assertArrayNotHasKey(CURLOPT_PROXY, $options);
    }

    public function test_curl_options_use_site_proxy(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->proxyhost = 'proxy.example.com';
        $CFG->proxyport = 3128;
        $CFG->proxytype = 'HTTP';
        $CFG->proxyuser = 'user';
        $CFG->proxypassword = 'pass';
        $CFG->proxybypass = '';

        $options = http::curl_options('https://api.paystack.co/');
        $this->assertSame('proxy.example.com:3128', $options[CURLOPT_PROXY]);
        $this->assertSame(CURLPROXY_HTTP, $options[CURLOPT_PROXYTYPE]);
        $this->assertSame('user:pass', $options[CURLOPT_PROXYUSERPWD]);

        $CFG->proxytype = 'SOCKS5';
        $options = http::curl_options('https://api.paystack.co/');
        $this->assertSame(CURLPROXY_SOCKS5, $options[CURLOPT_PROXYTYPE]);
    }

    public function test_curl_options_proxy_bypass(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->proxyhost = 'proxy.example.com';
        $CFG->proxybypass = 'api.paystack.co';

        $this->assertArrayNotHasKey(CURLOPT_PROXY, http::curl_options('https://api.paystack.co/'));
        $this->assertArrayHasKey(CURLOPT_PROXY, http::curl_options('https://plugin-tracker.paystackintegrations.com/'));
    }
}
