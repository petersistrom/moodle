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
 * Strings for the quizaccess_latesubmission plugin.
 *
 * @package    quizaccess_latesubmission
 * @subpackage latesubmission
 * @author  Nathan Nguyen <nathannguyen@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

$string['confirmstartheader'] = 'Late submission';
$string['pluginname'] = 'Late submission access rule';
$string['privacy:metadata'] = 'The Late submission quiz access rule plugin does not store any personal data.';
$string['lateperiod'] = 'Default late period';
$string['lateperiod_desc'] = 'Specify number late periods.';
$string['dailypercentagepenalty'] = 'Daily percentage penalty';
$string['dailypercentagepenalty_desc'] = 'Specify percentage penalty per day, this will be deducted until either the close date or the maximum percentage is reached';
$string['maxpercentagepenalty'] = 'Maximum percentage penalty';
$string['maxpercentagepenalty_desc'] = 'Specify the maximum penalty to be deducted as a percentage of the overall mark';
$string['holiday'] = 'Public and University holidays';
$string['holiday_desc'] = 'Specify each date in the following format dd/mm/yyyy. One date per line.';
$string['quizdue'] = 'Due date';
$string['appliedpenalty'] = 'Apply penalty for late submission';
$string['quizdueon'] = 'This quiz will be due on {$a}.';
$string['quizalreadydue'] = 'The quiz was due on {$a}.';
$string['quizduewithoutpenalty'] = 'Late submission does not attract penalty';
$string['quizduewithpenalty'] = 'Late submission attracts penalty: {$a}% per day';
$string['preventresubmission'] = 'Prevent re-attempt if users have already made submission before due date (User still can access in-progress attempt)';
$string['quizalreadysubmittedbeforeduedate'] = 'The submission is locked as you have made a submission before due date';
$string['invalidtimeopen'] = 'Time due must be greater than time open';
$string['invalidtimeclose'] = 'Time due must be less than time close';
$string['invalidate'] = 'Invalid date: {$a}. The date must be in "dd/mm/yyyy" format';
$string['latesubmission'] = 'Late submission';
