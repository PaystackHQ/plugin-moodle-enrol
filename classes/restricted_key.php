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
 * Paystack restricted key validator.
 *
 * @package    enrol_paystack
 * @copyright  2019 Paystack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_paystack;

defined('MOODLE_INTERNAL') || die();

final class restricted_key
{
    const RK_MAX_LENGTH = 256;
    const CONCURRENCY = 5;
    const BASE_URL = 'https://api.paystack.co/';
    const SCOPE_DENIED_MESSAGE = 'This restricted key is not permitted to perform this action';
    const CHECK_AUTH_GONE_MESSAGE = 'Sorry, this feature is no longer available.';
    const IDENT_SCOPE = 'account:read';

    const PRESENT_2XX = '2xx';
    const PRESENT_2XX_OR_400 = '2xx_or_400';
    const PRESENT_GONE = 'gone';

    // Canonical source: plugin-shopify-app services/RestrictedKeyValidator.js. Divergence: transactions:write is
    // refused because Moodle never calls initialize. Never add a write-capable route. Table order matters:
    // offending scopes are reported in it.
    const PROBES = array(
        array('scope' => 'transactions:read', 'required' => true, 'method' => 'GET',
            'path' => 'transaction?perPage=1', 'present' => self::PRESENT_2XX),
        array('scope' => 'account:read', 'required' => true, 'method' => 'GET',
            'path' => 'ident', 'present' => self::PRESENT_2XX),
        array('scope' => 'transactions:write', 'sensitive' => true, 'method' => 'POST',
            'path' => 'transaction/check_authorization', 'body' => '{}', 'present' => self::PRESENT_GONE),
        array('scope' => 'disputes_refunds', 'sensitive' => true, 'method' => 'GET',
            'path' => 'refund?perPage=1', 'present' => self::PRESENT_2XX_OR_400),
        array('scope' => 'bulk_charges', 'sensitive' => true, 'method' => 'GET',
            'path' => 'bulkcharge', 'present' => self::PRESENT_2XX_OR_400),
        array('scope' => 'direct_debit', 'sensitive' => true, 'method' => 'GET',
            'path' => 'directdebit/account', 'present' => self::PRESENT_2XX_OR_400),
        array('scope' => 'preauthorizations', 'sensitive' => true, 'method' => 'GET',
            'path' => 'preauthorization', 'present' => self::PRESENT_2XX_OR_400,
            'granted_on' => array(
                'Merchant is not eligible for Preauthorization. Please contact support.',
                'This feature is not available to merchant of record integrations.',
            )),
        array('scope' => 'settlements', 'sensitive' => true, 'method' => 'GET',
            'path' => 'settlement?perPage=1', 'present' => self::PRESENT_2XX_OR_400),
        array('scope' => 'subaccounts', 'sensitive' => true, 'method' => 'GET',
            'path' => 'subaccount', 'present' => self::PRESENT_2XX_OR_400,
            'granted_on' => array('This feature is not available to merchant of record integrations.')),
        array('scope' => 'transfers', 'sensitive' => true, 'method' => 'GET',
            'path' => 'transfer/config', 'present' => self::PRESENT_2XX_OR_400),
        array('scope' => 'virtual_accounts', 'sensitive' => true, 'method' => 'GET',
            'path' => 'pay-with-transfer/custom-accounts/status', 'present' => self::PRESENT_2XX_OR_400),
        array('scope' => 'webhooks', 'sensitive' => true, 'method' => 'GET',
            'path' => 'integration/webhooks/events', 'present' => self::PRESENT_2XX_OR_400,
            'granted_on' => array('Webhook endpoint management is not available for this integration')),
        array('scope' => 'connect', 'sensitive' => true, 'method' => 'GET',
            'path' => 'connect/platforms', 'present' => self::PRESENT_2XX_OR_400),
        array('scope' => 'payouts', 'sensitive' => true, 'method' => 'GET',
            'path' => 'settlement/instant/profile', 'present' => self::PRESENT_2XX_OR_400),
    );

    public static function is_rk($key) {
        return is_string($key) && strlen($key) <= self::RK_MAX_LENGTH
            && preg_match('/^rk_(test|live)_[\x21-\x7e]{16,}\z/', $key) === 1;
    }

    public static function is_pk($key) {
        return is_string($key) && preg_match('/^pk_(test|live)_\w+\z/', $key) === 1;
    }

    public static function key_mode($key) {
        if (is_string($key) && preg_match('/^(?:rk|sk|pk)_(test|live)_/', $key, $m) === 1) {
            return $m[1];
        }
        return null;
    }

    public static function key_type($key) {
        if (self::is_rk($key)) {
            return 'rk';
        }
        if (is_string($key) && preg_match('/^sk_(test|live)_\w+\z/', $key) === 1) {
            return 'sk';
        }
        return 'other';
    }

    private static function message_of($body) {
        if (is_string($body)) {
            return $body;
        }
        if (is_array($body) && isset($body['message']) && is_string($body['message'])) {
            return $body['message'];
        }
        return null;
    }

    private static function is_present_status($probe, $status, $body) {
        switch ($probe['present']) {
            case self::PRESENT_2XX:
                return $status >= 200 && $status <= 299;
            case self::PRESENT_2XX_OR_400:
                return ($status >= 200 && $status <= 299) || $status === 400;
            case self::PRESENT_GONE:
                // An unmatched route also returns 404, so the stub's message must match too.
                return $status === 404 && self::message_of($body) === self::CHECK_AUTH_GONE_MESSAGE;
        }
        return false;
    }

    public static function classify($probe, $status, $body, $baselineoutcome) {
        $sensitive = !empty($probe['sensitive']);
        if ($status === 401) {
            if ($baselineoutcome === null) {
                return 'invalid';
            }
            // Only a 2xx baseline proves the key is valid, so a 401 elsewhere proves nothing.
            return ($baselineoutcome === 'present' && $sensitive) ? 'present' : 'indeterminate';
        }
        $message = self::message_of($body);
        // Any 403 other than the exact scope message may be another gate (IP, feature), so it fails closed.
        if ($status === 403 && $message === self::SCOPE_DENIED_MESSAGE) {
            return 'absent';
        }
        // These 403s are only reachable after the scope check passed.
        if ($status === 403 && !empty($probe['granted_on']) && in_array($message, $probe['granted_on'], true)) {
            return 'present';
        }
        return self::is_present_status($probe, $status, $body) ? 'present' : 'indeterminate';
    }

    private static function verdict($code, $scopes = array(), $sensitive = array(), $reason = null) {
        return array(
            'code' => $code,
            'scopes' => $scopes,
            'sensitive_scopes' => $sensitive,
            'reason' => $reason,
            'public_key' => null,
            'probes' => '',
        );
    }

    public static function decide($results) {
        if ($results[0]['outcome'] === 'invalid') {
            return self::verdict('invalid_key');
        }
        $missing = array();
        $sensitive = array();
        $indeterminate = false;
        foreach ($results as $r) {
            if (!empty($r['probe']['required']) && $r['outcome'] === 'absent') {
                $missing[] = $r['probe']['scope'];
            }
            if (!empty($r['probe']['sensitive']) && $r['outcome'] === 'present') {
                $sensitive[] = $r['probe']['scope'];
            }
            if ($r['outcome'] === 'indeterminate') {
                $indeterminate = true;
            }
        }
        if ($missing) {
            return self::verdict('missing_scope', $missing, $sensitive);
        }
        if ($sensitive) {
            return self::verdict('sensitive_scope', array(), $sensitive);
        }
        if ($indeterminate) {
            $blocked = $results[0]['outcome'] === 'indeterminate' && $results[0]['status'] === 403;
            return self::verdict('unverifiable', array(), array(), $blocked ? 'blocked' : 'transient');
        }
        return self::verdict('ok');
    }

    public static function validate($key, array $options = array()) {
        try {
            $baseurl = isset($options['base_url']) ? rtrim($options['base_url'], '/') . '/' : self::BASE_URL;
            $run = function (array $probes, $baselineoutcome) use ($key, $options, $baseurl) {
                $requests = array();
                foreach ($probes as $probe) {
                    $requests[] = array(
                        'method' => $probe['method'],
                        'url' => $baseurl . $probe['path'],
                        'headers' => array(
                            'Authorization' => 'Bearer ' . $key,
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json',
                        ),
                        'body' => isset($probe['body']) ? $probe['body'] : null,
                    );
                }
                $responses = self::send($requests, $options);
                $out = array();
                foreach ($probes as $i => $probe) {
                    $res = $responses[$i];
                    $out[] = array(
                        'probe' => $probe,
                        'outcome' => self::classify($probe, $res['status'], $res['body'], $baselineoutcome),
                        'status' => $res['status'],
                        'body' => $probe['scope'] === self::IDENT_SCOPE ? $res['body'] : null,
                    );
                }
                return $out;
            };

            $probes = self::PROBES;
            $results = $run(array($probes[0]), null);
            $baseline = $results[0]['outcome'];
            // A scope refusal still proves the key is valid, so the other probes can be checked.
            if ($baseline === 'present' || $baseline === 'absent') {
                $results = array_merge($results, $run(array_slice($probes, 1), $baseline));
            }

            $verdict = self::decide($results);
            if ($verdict['code'] === 'ok') {
                // A verdict of ok means every probe ran, so the ident result is the one in PROBES order.
                $ident = $results[1];
                $publickey = $ident['probe']['scope'] === self::IDENT_SCOPE && is_array($ident['body'])
                    ? ($ident['body']['data']['public_key'] ?? null) : null;
                if (self::is_pk($publickey) && self::key_mode($publickey) === self::key_mode($key)) {
                    $verdict['public_key'] = $publickey;
                } else {
                    $verdict = self::verdict('account');
                }
            }
            $summary = array();
            foreach ($results as $r) {
                $summary[] = $r['probe']['scope'] . ':' . $r['status'];
            }
            $verdict['probes'] = implode(',', $summary);
            return $verdict;
        } catch (\Throwable $e) {
            return self::verdict('unverifiable', array(), array(), 'transient');
        }
    }

    private static function send(array $requests, array $options) {
        if (isset($options['request']) && is_callable($options['request'])) {
            $responses = array();
            foreach ($requests as $req) {
                try {
                    $res = call_user_func($options['request'], $req['method'], $req['url'], $req['headers'], $req['body']);
                } catch (\Throwable $e) {
                    $res = null;
                }
                $responses[] = (is_array($res) && isset($res['status']) && is_int($res['status']))
                    ? array('status' => $res['status'], 'body' => isset($res['body']) ? $res['body'] : null)
                    : array('status' => 0, 'body' => null);
            }
            return $responses;
        }
        $curloptions = isset($options['curl_options']) && is_array($options['curl_options'])
            ? $options['curl_options'] : array();
        return self::send_curl($requests, $curloptions);
    }

    private static function send_curl(array $requests, array $curloptions) {
        $responses = array();
        foreach ($requests as $i => $req) {
            $responses[$i] = array('status' => 0, 'body' => null);
        }
        $mh = curl_multi_init();
        $queue = array_keys($requests);
        $active = array();
        do {
            while ($queue && count($active) < self::CONCURRENCY) {
                $i = array_shift($queue);
                $ch = self::make_handle($requests[$i], $curloptions);
                if ($ch === false) {
                    continue;
                }
                curl_multi_add_handle($mh, $ch);
                $active[$i] = $ch;
            }
            if (!$active) {
                break;
            }
            $running = 0;
            curl_multi_exec($mh, $running);
            while (($info = curl_multi_info_read($mh)) !== false) {
                foreach ($active as $i => $ch) {
                    if ($ch !== $info['handle']) {
                        continue;
                    }
                    if ($info['result'] === CURLE_OK) {
                        $raw = curl_multi_getcontent($ch);
                        $decoded = json_decode((string)$raw, true);
                        $responses[$i] = array(
                            'status' => (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
                            'body' => json_last_error() === JSON_ERROR_NONE ? $decoded : (string)$raw,
                        );
                    }
                    curl_multi_remove_handle($mh, $ch);
                    if (PHP_VERSION_ID < 80000) {
                        curl_close($ch);
                    }
                    unset($active[$i]);
                    break;
                }
            }
            if ($running > 0) {
                curl_multi_select($mh, 0.5);
            }
        } while ($active || $queue);
        curl_multi_close($mh);
        return $responses;
    }

    private static function make_handle(array $req, array $curloptions) {
        $ch = curl_init();
        if ($ch === false) {
            return false;
        }
        $headers = array();
        foreach ($req['headers'] as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }
        // Applied last so caller-supplied options cannot weaken the transport settings.
        $options = $curloptions;
        $options[CURLOPT_URL] = $req['url'];
        $options[CURLOPT_CUSTOMREQUEST] = $req['method'];
        $options[CURLOPT_HTTPHEADER] = $headers;
        $options[CURLOPT_RETURNTRANSFER] = true;
        $options[CURLOPT_TIMEOUT] = 5;
        $options[CURLOPT_CONNECTTIMEOUT] = 3;
        $options[CURLOPT_FOLLOWLOCATION] = false;
        $options[CURLOPT_SSL_VERIFYPEER] = true;
        $options[CURLOPT_SSL_VERIFYHOST] = 2;
        $options[CURLOPT_VERBOSE] = false;
        if (defined('CURLOPT_PROTOCOLS_STR')) {
            $options[CURLOPT_PROTOCOLS_STR] = 'https';
        } else {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        }
        if ($req['body'] !== null) {
            $options[CURLOPT_POSTFIELDS] = $req['body'];
        }
        curl_setopt_array($ch, $options);
        return $ch;
    }
}
