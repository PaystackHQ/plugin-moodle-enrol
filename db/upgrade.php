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
 * Plugin upgrade steps.
 *
 * @package    enrol_paystack
 * @copyright  2019 Paystack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_enrol_paystack_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026111800) {
        $table = new xmldb_table('enrol_paystack');
        $index = new xmldb_index('txn_id', XMLDB_INDEX_NOTUNIQUE, ['txn_id']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        upgrade_plugin_savepoint(true, 2026111800, 'enrol', 'paystack');
    }

    if ($oldversion < 2026111802) {
        $names = ['test_secretkey', 'live_secretkey', 'test_restrictedkey', 'live_restrictedkey',
            'test_webhooksecret', 'live_webhooksecret'];
        foreach (['value', 'oldvalue'] as $column) {
            list($insql, $params) = $DB->get_in_or_equal($names);
            array_unshift($params, 'enrol_paystack');
            $DB->execute("UPDATE {config_log} SET $column = '********'
                           WHERE plugin = ? AND name $insql AND " . $DB->sql_isnotempty('config_log', $column, true, true), $params);
        }
        upgrade_plugin_savepoint(true, 2026111802, 'enrol', 'paystack');
    }

    if ($oldversion < 2026111804) {
        if (get_config('enrol_paystack', 'replayguard_since') === false) {
            set_config('replayguard_since', time(), 'enrol_paystack');
        }

        \enrol_paystack\util::register_sensible_settings();

        upgrade_plugin_savepoint(true, 2026111804, 'enrol', 'paystack');
    }

    if ($oldversion < 2026111805) {
        \core\task\manager::queue_adhoc_task(new \enrol_paystack\task\purge_key_logs(), true);
        upgrade_plugin_savepoint(true, 2026111805, 'enrol', 'paystack');
    }

    return true;
}
