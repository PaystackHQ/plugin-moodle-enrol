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
 * Tests for webhook signature verification.
 *
 * @package    enrol_paystack
 * @copyright  2019 Paystack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_paystack\webhook_signature
 */
final class webhook_signature_test extends \basic_testcase {
    const WHSEC = 'whsec_abcdefghijklmnop1234';
    const NOW = 1800000000;
    const BODY = '{"event":"charge.success","data":{"reference":"abc"}}';

    private function check($cond, $name) {
        $this->assertTrue((bool) $cond, $name);
    }

    private static function sig($t, $body, $key) {
        return hash_hmac('sha512', $t . '.' . $body, (string) $key);
    }

    private static function good() {
        return self::sig(self::NOW, self::BODY, self::WHSEC);
    }

    private static function server($header) {
        return array('HTTP_PAYSTACK_SIGNATURE' => $header);
    }

    private static function auth($header, $body = self::BODY, $now = self::NOW, $key = self::WHSEC) {
        return webhook_signature::is_authentic($body, self::server($header), $now, $key);
    }

    public function test_signature_header() {
        $this->check(self::auth('t=' . self::NOW . ',v1=sha512:' . self::good()), 'valid header');
        $this->check(self::auth('t=' . self::NOW . ', v1=sha512:' . self::good()), 'valid header with space after comma');
        $this->check(self::auth('t=' . self::NOW . ',v1=sha512:' . str_repeat('0', 128) . ',v1=sha512:' . self::good()), 'second v1 matches');
        $this->check(!self::auth('t=' . self::NOW . ',v1=' . self::good()), 'v1 without colon ignored');
        $this->check(!self::auth('t=' . self::NOW . ',v1=sha512:' . str_repeat('0', 128)), 'wrong signature');
        $this->check(!self::auth('t=' . self::NOW . ',v1=sha512:zz' . substr(self::good(), 2)), 'bad hex');
        $this->check(!self::auth('t=' . self::NOW . ',v1=sha512:' . self::good(), self::BODY . ' '), 'tampered body');
        $this->check(!self::auth('t=' . self::NOW . ',v1=sha512:' . self::good(), self::BODY, self::NOW, self::WHSEC . 'x'), 'different secret');
    }

    public function test_timestamp_skew() {
        foreach (array(-301 => false, -300 => true, 300 => true, 301 => false) as $skew => $ok) {
            $t = self::NOW + $skew;
            $this->check(self::auth('t=' . $t . ',v1=sha512:' . self::sig($t, self::BODY, self::WHSEC)) === $ok, "skew $skew");
        }
    }

    public function test_timestamp_format() {
        $this->check(!self::auth('t=12a,v1=sha512:' . self::sig('12a', self::BODY, self::WHSEC), self::BODY, 12), 'non-digit t');
        $this->check(!self::auth('t=-' . self::NOW . ',v1=sha512:' . self::sig('-' . self::NOW, self::BODY, self::WHSEC)), 'negative t');
        $this->check(!self::auth('v1=sha512:' . self::good()), 'missing t');
        $this->check(!self::auth(''), 'empty header');
    }

    public function test_rejected_secrets() {
        foreach (array(null, '', false, 'whsec_short', 'sk_test_' . 'abcdefghijklmnopqrstu', 'whsec_abcdefghijklmnop123 ', "whsec_abcdefghijklmnop1234\n") as $i => $key) {
            $h = 't=' . self::NOW . ',v1=sha512:' . self::sig(self::NOW, self::BODY, $key);
            $this->check(!self::auth($h, self::BODY, self::NOW, $key), "rejected secret #$i");
        }
    }

    public function test_missing_and_legacy_header() {
        $this->check(!webhook_signature::is_authentic(self::BODY, array(), self::NOW, self::WHSEC), 'missing header');
        $legacy = hash_hmac('sha512', self::BODY, 'sk_test_' . 'abcdefghijklmnopqrstu');
        $this->check(!webhook_signature::is_authentic(self::BODY, array('HTTP_X_PAYSTACK_SIGNATURE' => $legacy), self::NOW, self::WHSEC), 'legacy header only');
        $this->check(!webhook_signature::is_authentic(self::BODY, array('HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', self::BODY, self::WHSEC)), self::NOW, self::WHSEC), 'legacy header signed with whsec');
    }

    public function test_is_secret() {
        $this->check(webhook_signature::is_secret('whsec_abcdefghijklmnop'), 'is_secret min length');
        $this->check(!webhook_signature::is_secret('whsec_abcdefghijklmno'), 'is_secret too short');
        $this->check(!webhook_signature::is_secret('whsec_abcdefghijklmnop é'), 'is_secret non-ascii');
        $this->check(!webhook_signature::is_secret(123), 'is_secret non-string');
    }
}
