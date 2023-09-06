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
 * Local functions for quizaccess_latesubmission
 *
 * @package    quizaccess_latesubmission
 * @subpackage latesubmission
 * @author  Nathan Nguyen <nathannguyen@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_quiz\quiz_settings;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/accessrule/latesubmission/rule.php');

/**
 * Handle the quiz_attempt_submitted event.
 * Apply penalty at submission time.
 *
 * @param object $event the event object.
 */
function latesubmission_quiz_attempt_submitted_handler($event) {
    $attempt = $event->get_record_snapshot('quiz_attempts', $event->objectid);
    quizaccess_latesubmission::update_overdue_status($attempt);
    $quizobj = quiz_settings::create($attempt->quiz);
    $quizobj->get_grade_calculator()->recompute_attempt_sumgrades_with_penalty($attempt);
}

/**
 * Handle the quiz_attempt_regraded event.
 * Apply penalty at submission time.
 *
 * @param object $event the event object.
 */
function latesubmission_quiz_attempt_regraded_handler($event) {
    $attempt = $event->get_record_snapshot('quiz_attempts', $event->objectid);
    quizaccess_latesubmission::update_overdue_status($attempt);
    $quizobj = quiz_settings::create($attempt->quiz);
    $quizobj->get_grade_calculator()->recompute_attempt_sumgrades_with_penalty($attempt);
}
