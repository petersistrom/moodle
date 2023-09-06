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
 * Global configuration settings for the quizaccess_latesubmission plugin.
 *
 * @package    quizaccess_latesubmission
 * @subpackage latesubmission
 * @author  Nathan Nguyen <nathannguyen@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $ADMIN;

if ($hassiteconfig) {
    require_once($CFG->dirroot.'/mod/quiz/accessrule/latesubmission/settingslib.php');

    $settings->add(new admin_setting_configduration('quizaccess_latesubmission/lateperiod',
        get_string('lateperiod', 'quizaccess_latesubmission'),
        get_string('lateperiod_desc', 'quizaccess_latesubmission'), 5 * DAYSECS, DAYSECS));

    $settings->add(new admin_setting_configtext_percentage('quizaccess_latesubmission/dailypercentagepenalty',
        get_string("dailypercentagepenalty", "quizaccess_latesubmission"),
        get_string("dailypercentagepenalty_desc", "quizaccess_latesubmission"), 5));

    $settings->add(new admin_setting_configtext_percentage('quizaccess_latesubmission/maxpercentagepenalty',
        get_string("maxpercentagepenalty", "quizaccess_latesubmission"),
        get_string("maxpercentagepenalty_desc", "quizaccess_latesubmission"), 50));

    $settings->add(new admin_setting_configtextarea_datelist('quizaccess_latesubmission/holiday',
        new lang_string('holiday', 'quizaccess_latesubmission'),
        new lang_string('holiday_desc', 'quizaccess_latesubmission'), '', PARAM_RAW, '10', '10'));
}
