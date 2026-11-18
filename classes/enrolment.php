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
 * Payment integrity checks shared by verify.php and webhook.php.
 *
 * @package    enrol_paystack
 * @copyright  2019 Paystack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_paystack;

defined('MOODLE_INTERNAL') || die();

class enrolment {

    /**
     * Parse "<userid>-<courseid>-<instanceid>" from verified transaction metadata.
     *
     * @param array|string|null $metadata Array or JSON string from the verify response.
     * @return array|null ['userid', 'courseid', 'instanceid'] or null when invalid.
     */
    public static function parse_custom($metadata): ?array {
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        }
        if (!is_array($metadata) || !isset($metadata['custom']) || !is_string($metadata['custom'])) {
            return null;
        }
        $parts = explode('-', $metadata['custom']);
        if (count($parts) !== 3) {
            return null;
        }
        foreach ($parts as $part) {
            if (!ctype_digit($part) || (int) $part < 1) {
                return null;
            }
        }
        return [
            'userid' => (int) $parts[0],
            'courseid' => (int) $parts[1],
            'instanceid' => (int) $parts[2],
        ];
    }

    /**
     * Resolve the course cost the same way the enrol form does.
     *
     * @param \stdClass $instance Enrol instance.
     * @param mixed $defaultcost Plugin-level cost used when the instance has none.
     * @return float
     */
    public static function resolve_cost(\stdClass $instance, $defaultcost): float {
        $cost = (float) $instance->cost;
        if ($cost <= 0) {
            $cost = (float) $defaultcost;
        }
        return (float) format_float($cost, 2, false);
    }

    /**
     * Check verified Paystack transaction data against the enrol instance.
     *
     * @param array $txdata The data element of the verify response.
     * @param \stdClass $instance Enrol instance.
     * @param float $cost Resolved course cost in major units.
     * @return string|null Null when acceptable, otherwise a short reason.
     */
    public static function check_payment(array $txdata, \stdClass $instance, float $cost): ?string {
        if (!isset($txdata['status']) || $txdata['status'] !== 'success') {
            return 'Payment status not successful';
        }
        $currency = isset($txdata['currency']) && is_string($txdata['currency']) ? strtoupper($txdata['currency']) : '';
        if ($currency !== strtoupper((string) $instance->currency)) {
            return 'Currency does not match course settings, received: ' . $currency;
        }
        if (!isset($txdata['amount']) || !is_int($txdata['amount'])) {
            return 'Missing or invalid amount';
        }
        $required = (int) round($cost * 100);
        if ($txdata['amount'] < $required) {
            return 'Amount paid is not enough (' . $txdata['amount'] . ' < ' . $required . ')';
        }
        return null;
    }

    /**
     * Whether a Paystack reference has already been recorded.
     *
     * @param string $reference Paystack transaction reference.
     * @return bool
     */
    public static function reference_used(string $reference): bool {
        global $DB;
        return $DB->record_exists('enrol_paystack', ['txn_id' => $reference]);
    }
}
