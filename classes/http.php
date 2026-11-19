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
 * Outbound HTTP helpers.
 *
 * @package    enrol_paystack
 * @copyright  2019 Paystack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_paystack;

defined('MOODLE_INTERNAL') || die();

/**
 * Curl option builder shared by every call this plugin makes to the network.
 *
 * @package   enrol_paystack
 * @copyright 2019 Paystack
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class http {

    /**
     * Hardened curl options, plus the site proxy settings unless the URL is bypassed.
     *
     * @param string $url Destination URL.
     * @param int $timeout Total timeout in seconds.
     * @param int $connecttimeout Connect timeout in seconds.
     * @return array Options for curl_setopt_array().
     */
    public static function curl_options($url, $timeout = 15, $connecttimeout = 5) {
        $options = [
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connecttimeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if (defined('CURLOPT_PROTOCOLS_STR')) {
            $options[CURLOPT_PROTOCOLS_STR] = 'https';
        } else {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        }

        return $options + self::proxy_options($url);
    }

    /**
     * The site proxy settings as curl options, empty when no proxy applies to the URL.
     *
     * @param string $url Destination URL.
     * @return array Options for curl_setopt_array().
     */
    public static function proxy_options($url) {
        global $CFG;

        $options = [];
        if (!empty($CFG->proxyhost) && !is_proxybypass($url)) {
            $options[CURLOPT_PROXY] = empty($CFG->proxyport) ? $CFG->proxyhost : $CFG->proxyhost . ':' . $CFG->proxyport;
            if (!empty($CFG->proxyuser) && !empty($CFG->proxypassword)) {
                $options[CURLOPT_PROXYAUTH] = CURLAUTH_BASIC | CURLAUTH_NTLM;
                $options[CURLOPT_PROXYUSERPWD] = $CFG->proxyuser . ':' . $CFG->proxypassword;
            }
            if (!empty($CFG->proxytype)) {
                if ($CFG->proxytype === 'SOCKS5') {
                    $options[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
                } else {
                    $options[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
                    $options[CURLOPT_HTTPPROXYTUNNEL] = false;
                }
            }
        }

        return $options;
    }
}
