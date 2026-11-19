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
 * Paystack webhook signature verification.
 *
 * @package    enrol_paystack
 * @copyright  2019 Paystack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_paystack;

defined('MOODLE_INTERNAL') || die();

final class webhook_signature
{
    const TOLERANCE = 300;

    public static function is_secret($s) {
        return is_string($s) && preg_match('/^whsec_[\x21-\x7e]{16,}\z/', $s) === 1;
    }

    public static function is_authentic($input, array $server, $now, $whsec) {
        if (!self::is_secret($whsec)) {
            return false;
        }
        $header = $server['HTTP_PAYSTACK_SIGNATURE'] ?? null;
        if (!is_string($header) || !is_string($input)) {
            return false;
        }

        $timestamp = null;
        $signatures = array();
        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                continue;
            }
            if ($pair[0] === 't') {
                $timestamp = $pair[1];
            } else if ($pair[0] === 'v1') {
                $value = explode(':', $pair[1], 2);
                if (count($value) === 2) {
                    $signatures[] = $value[1];
                }
            }
        }
        if ($timestamp === null || !ctype_digit($timestamp) || abs($now - (int) $timestamp) > self::TOLERANCE) {
            return false;
        }

        $expected = hash_hmac('sha512', $timestamp . '.' . $input, $whsec);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }
        return false;
    }
}
