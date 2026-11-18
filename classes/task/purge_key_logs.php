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
 * Removes key values that earlier versions left in the standard log store and admin presets.
 *
 * @package    enrol_paystack
 * @copyright  2019 Paystack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_paystack\task;

defined('MOODLE_INTERNAL') || die();

class purge_key_logs extends \core\task\adhoc_task {

    /** @var int Rows of the log table examined per delete. */
    const BATCH = 10000;

    /** Setting names whose values must not stay in logs or presets. */
    const NAMES = ['test_secretkey', 'live_secretkey', 'test_restrictedkey', 'live_restrictedkey',
        'test_webhooksecret', 'live_webhooksecret'];

    public function execute() {
        $this->purge_log_store();
        $this->blank_presets();
    }

    private function purge_log_store() {
        global $DB;

        if (!$DB->get_manager()->table_exists('logstore_standard_log')) {
            return;
        }

        $names = [];
        $params = ['eventname' => '\\core\\event\\config_log_created'];
        foreach (self::NAMES as $i => $name) {
            $names[] = $DB->sql_like('other', ':name' . $i, true, true);
            $params['name' . $i] = '%' . $DB->sql_like_escape('"name":"' . $name . '"') . '%';
        }
        $params['plugin'] = '%' . $DB->sql_like_escape('"plugin":"enrol_paystack"') . '%';
        $match = 'eventname = :eventname AND ' . $DB->sql_like('other', ':plugin', true, true)
            . ' AND (' . implode(' OR ', $names) . ')';

        $range = $DB->get_record_sql('SELECT MIN(id) AS minid, MAX(id) AS maxid FROM {logstore_standard_log}');
        if (!$range || $range->minid === null) {
            return;
        }
        for ($from = (int) $range->minid; $from <= (int) $range->maxid; $from += self::BATCH) {
            $DB->delete_records_select('logstore_standard_log',
                "id >= :fromid AND id < :toid AND $match",
                $params + ['fromid' => $from, 'toid' => $from + self::BATCH]);
        }
    }

    private function blank_presets() {
        global $DB;

        if (!$DB->get_manager()->table_exists('adminpresets_it')) {
            return;
        }
        list($insql, $params) = $DB->get_in_or_equal(self::NAMES, SQL_PARAMS_NAMED);
        $params['plugin'] = 'enrol_paystack';
        $DB->execute("UPDATE {adminpresets_it} SET value = '' WHERE plugin = :plugin AND name $insql", $params);
    }
}
