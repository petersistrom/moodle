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
 * Description
 *
 * @package    quizaccess
 * @subpackage latesubmission
 * @copyright  2022 Catalyst IT Australia Pty Ltd
 * @author     Nathan Nguyen <nathannguyen@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Function to upgrade latesubmission.
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_quizaccess_latesubmission_upgrade($oldversion) {
    global $DB, $CFG;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.
    if ($oldversion < 2022112104) {

        // Define field appliedpenalty to be added to quizaccess_latesubmission_at.
        $table = new xmldb_table('quizaccess_latesubmission_at');

        // Conditionally launch add field appliedpenalty.
        $field = new xmldb_field('appliedpenalty', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'timefinish');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Conditionally launch add field dailypercentagepenalty.
        $field = new xmldb_field('dailypercentagepenalty', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'appliedpenalty');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Update current records.
        require_once($CFG->dirroot . '/mod/quiz/accessrule/latesubmission/rule.php');

        $lateattempts = $DB->get_records('quizaccess_latesubmission_at');
        foreach ($lateattempts as $attempt) {
            $attempt->quiz = $attempt->quizid;
            quizaccess_latesubmission::update_overdue_status($attempt);
        }

        // Latesubmission savepoint reached.
        upgrade_plugin_savepoint(true, 2022112104, 'quizaccess', 'latesubmission');
    }
}
