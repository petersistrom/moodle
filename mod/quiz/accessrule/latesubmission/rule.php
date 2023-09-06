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
use mod_quiz\local\access_rule_base;
use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;

/**
 * Implementaton of the quizaccess_latesubmission plugin.
 *
 * @package    quizaccess_latesubmission
 * @subpackage latesubmission
 * @author  Nathan Nguyen <nathannguyen@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_latesubmission extends access_rule_base {

    /** @var string Overdue string for sql.*/
    private const OVERDUE = "Yes";

    /** @var string Ontime string for sql.*/
    private const ONTIME = "No";

    /** Grace period to allow submission for 60 seconds after due date. */
    public const GRACE_PERIOD = MINSECS;

    /**
     * Return an appropriately configured instance of this rule, if it is applicable
     *  to the given quiz, otherwise return null.
     *
     * @param \mod_quiz\quiz_settings $quizobj information about the quiz in question.
     * @param int $timenow the time that should be considered as 'now'.
     * @param bool $canignoretimelimits whether the current user is exempt from
     *       time limits by the mod/quiz:ignoretimelimits capability.
     * @return access_rule_base|null the rule, if applicable, else null.
     *
     */
    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits) {
        // This rule is always used, even if the quiz has no open or close date.
        $quiz = $quizobj->get_quiz();
        if (empty($quiz) || empty($quiz->timedue)) {
            return null;
        } else {
            return new self($quizobj, $timenow);
        }
    }

    /**
     * Return a brief summary of this rule, to show to users, if required.
     *
     * @return array an array of messages, explaining the restriction
     */
    public function description() {
        $result = array();

        // Time due is not set.
        if ($this->quiz->timedue <= 0) {
            return $result;
        }
        $timedue = $this->quiz->timedue;
        $timeduewithgraceperiod = $this->quiz->timedue + self::GRACE_PERIOD;

        if ($this->timenow < $timeduewithgraceperiod) {
            $result[] = get_string('quizdueon', 'quizaccess_latesubmission', userdate($timedue));
        } else {
            $result[] = get_string('quizalreadydue', 'quizaccess_latesubmission', userdate($timedue));
        }

        if (empty($this->quiz->appliedpenalty) || empty($this->quiz->dailypercentagepenalty)) {
            $result[] = get_string('quizduewithoutpenalty', 'quizaccess_latesubmission', userdate($timedue));
        } else {
            $result[] = get_string('quizduewithpenalty', 'quizaccess_latesubmission', $this->quiz->dailypercentagepenalty);
        }

        return $result;
    }

    /**
     * Whether the user should be blocked from starting a new attempt or continuing
     * an attempt now.
     *
     * @return string false if access should be allowed, a message explaining the
     *      reason if access should be prevented.
     */
    public function prevent_access() {

        $timedue = $this->quiz->timedue ? $this->quiz->timedue + self::GRACE_PERIOD : 0;

        if (
            empty($timedue)
            || $timedue > time()
            || empty($this->quiz->preventresubmission)
            || !empty($this->get_user_unfinished_attempts())) {
            return false;
        }
        // Prevent access if user already had a finished submission before due date.
        $attempts = $this->get_user_finished_attempts();
        foreach ($attempts as $attempt) {
            if ($attempt->timefinish < $this->quiz->timedue) {
                return get_string('quizalreadysubmittedbeforeduedate', 'quizaccess_latesubmission');
            }
        }
        return false;
    }

    /**
     * Add any fields that this rule requires to the quiz settings form. This
     * method is called from {@see mod_quiz_mod_form::definition()}, while the
     * security section is being built.
     *
     * @param mod_quiz_mod_form $quizform the quiz settings form that is being built.
     * @param MoodleQuickForm $mform the wrapped MoodleQuickForm.
     */
    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        global $PAGE;

        // Transform holiday to timestamp for timeclose calculation.
        $holidaydates = [];
        if (!empty($holidays = get_config('quizaccess_latesubmission', 'holiday'))) {
            $holidays = explode("\r\n", $holidays);
            foreach ($holidays as $date) {
                $date = date_create_from_format('d/m/Y', $date);
                $holidaydates[] = $date->getTimestamp();
            }
        }

        $holidaydates = implode(',', $holidaydates);
        // Requires javascript for time close calculation.
        $arguments = [
            'lateperiod' => get_config('quizaccess_latesubmission', 'lateperiod'),
            'holiday' => $holidaydates,
        ];
        $PAGE->requires->js_call_amd('quizaccess_latesubmission/form', 'init', [$arguments]);

        // Time due.
        $timedue = $mform->createElement('date_time_selector', 'timedue', get_string('quizdue', 'quizaccess_latesubmission'),
            array('optional' => true));
        $mform->insertElementBefore($timedue, 'timeclose');

        // Apply penalty or not.
        $appliedpenalty = $mform->createElement('selectyesno', 'appliedpenalty',
            get_string('appliedpenalty', 'quizaccess_latesubmission'));
        $mform->insertElementBefore($appliedpenalty, 'timelimit');
        $mform->setDefault('appliedpenalty', 1);
        $mform->hideIf('appliedpenalty', 'timedue[enabled]');

        // Daily percentage penalty.
        $dailypercentage = $mform->createElement('text', 'dailypercentagepenalty',
            get_string('dailypercentagepenalty', 'quizaccess_latesubmission'));
        $mform->insertElementBefore($dailypercentage, 'timelimit');
        $mform->setType('dailypercentagepenalty', PARAM_INT);
        $mform->setDefault('dailypercentagepenalty', get_config('quizaccess_latesubmission', 'dailypercentagepenalty'));
        $mform->disabledIf('dailypercentagepenalty', 'appliedpenalty', 'eq', 0);
        $mform->hideIf('dailypercentagepenalty', 'timedue[enabled]');

        // Maximum percentage penalty.
        $maxpercentage = $mform->createElement('text', 'maxpercentagepenalty',
            get_string('maxpercentagepenalty', 'quizaccess_latesubmission'));
        $mform->insertElementBefore($maxpercentage, 'timelimit');
        $mform->setType('maxpercentagepenalty', PARAM_INT);
        $mform->setDefault('maxpercentagepenalty', get_config('quizaccess_latesubmission', 'maxpercentagepenalty'));
        $mform->disabledIf('maxpercentagepenalty', 'appliedpenalty', 'eq', 0);
        $mform->hideIf('maxpercentagepenalty', 'timedue[enabled]');

        // Prevent resubmission if user has made submission before due date.
        $preventresubmission = $mform->createElement('selectyesno', 'preventresubmission',
            get_string('preventresubmission', 'quizaccess_latesubmission'));
        $mform->insertElementBefore($preventresubmission, 'timelimit');
        $mform->setDefault('preventresubmission', 1);
        $mform->hideIf('preventresubmission', 'timedue[enabled]');
    }

    /**
     * Validate the data from any form fields added using {@see add_settings_form_fields()}.
     *
     * @param array $errors the errors found so far.
     * @param array $data the submitted form data.
     * @param array $files information about any uploaded files.
     * @param mod_quiz_mod_form $quizform the quiz form object.
     * @return array $errors the updated $errors array.
     */
    public static function validate_settings_form_fields(array $errors,
                                                         array $data, $files, mod_quiz_mod_form $quizform) : array {
        $timedue = $data['timedue'];
        if (empty($timedue)) {
            return [];
        }
        $timeopen = $data['timeopen'];
        $timeclose = $data['timeclose'];
        if ($timedue < $timeopen) {
            $errors['timedue'] = get_string('invalidtimeopen', 'quizaccess_latesubmission');
        } else if ($timedue > $timeclose) {
            $errors['timedue'] = get_string('invalidtimeclose', 'quizaccess_latesubmission');
        }
        return $errors;
    }

    /**
     * Save any submitted settings when the quiz settings form is submitted. This
     * is called from {@see quiz_after_add_or_update()} in lib.php.
     *
     * @param object $quiz the data from the quiz form, including $quiz->id
     *      which is the id of the quiz being saved.
     */
    public static function save_settings($quiz) {
        global $DB;
        $record = $DB->get_record('quizaccess_latesubmission', array('quizid' => $quiz->id));
        if (empty($record)) {
            $record = new stdClass();
            $record->quizid = $quiz->id;
            $record->timedue = isset($quiz->timedue) ? $quiz->timedue : 0;
            $record->appliedpenalty = isset($quiz->appliedpenalty) ? $quiz->appliedpenalty : 0;
            $record->dailypercentagepenalty = isset($quiz->dailypercentagepenalty) ? $quiz->dailypercentagepenalty : 0;
            $record->maxpercentagepenalty = isset($quiz->maxpercentagepenalty) ? $quiz->maxpercentagepenalty : 0;
            $record->preventresubmission = isset($quiz->preventresubmission) ? $quiz->preventresubmission : 0;
            $DB->insert_record('quizaccess_latesubmission', $record);
        } else {
            $record->timedue = isset($quiz->timedue) ? $quiz->timedue : 0;
            $record->appliedpenalty = isset($quiz->appliedpenalty) ? $quiz->appliedpenalty : 0;
            $record->dailypercentagepenalty = isset($quiz->dailypercentagepenalty) ? $quiz->dailypercentagepenalty : 0;
            $record->maxpercentagepenalty = isset($quiz->maxpercentagepenalty) ? $quiz->maxpercentagepenalty : 0;
            $record->preventresubmission = isset($quiz->preventresubmission) ? $quiz->preventresubmission : 0;
            $DB->update_record('quizaccess_latesubmission', $record);
        }
    }

    /**
     * Delete any rule-specific settings when the quiz is deleted. This is called
     * from {@see quiz_delete_instance()} in lib.php.
     *
     * @param object $quiz the data from the database, including $quiz->id
     *      which is the id of the quiz being deleted.
     */
    public static function delete_settings($quiz) {
        global $DB;
        $DB->delete_records('quizaccess_latesubmission', array('quizid' => $quiz->id));
    }

    /**
     * Return the bits of SQL needed to load all the settings from all the access
     * plugins in one DB query. The easiest way to understand what you need to do
     * here is probably to read the code of {@see quiz_access_manager::load_settings()}.
     *
     * If you have some settings that cannot be loaded in this way, then you can
     * use the {@see get_extra_settings()} method instead, but that has
     * performance implications.
     *
     * @param int $quizid the id of the quiz we are loading settings for. This
     *     can also be accessed as quiz.id in the SQL. (quiz is a table alisas for {quiz}.)
     * @return array with three elements:
     *     1. fields: any fields to add to the select list. These should be aliased
     *        if neccessary so that the field name starts with the name of the plugin.
     *     2. joins: any joins (should probably be LEFT JOINS) with other tables that
     *        are needed.
     *     3. params: array of placeholder values that are needed by the SQL. You must
     *        use named placeholders, and the placeholder names should start with the
     *        plugin name, to avoid collisions.
     */
    public static function get_settings_sql($quizid) {
        return array(
            'timedue, appliedpenalty, dailypercentagepenalty, maxpercentagepenalty, preventresubmission',
            'LEFT JOIN {quizaccess_latesubmission} ls ON ls.quizid = quiz.id',
            array());
    }

    /**
     * Returns a list of finished attempts for the current user.
     *
     * @return array
     */
    private function get_user_finished_attempts() : array {
        global $USER;

        return quiz_get_user_attempts(
            $this->quizobj->get_quizid(),
            $USER->id,
            quiz_attempt::FINISHED,
            false
        );
    }

    /**
     * Returns a list of unfinished attempts for the current user.
     *
     * @return array
     */
    private function get_user_unfinished_attempts() : array {
        global $USER;
        return quiz_get_user_attempts(
            $this->quizobj->get_quizid(),
            $USER->id,
            'unfinished',
            false
        );
    }

    /**
     * Calculate penalty
     *
     * @param mod_quiz\quiz_attempt $attempt
     *
     * @return int
     */
    public static function calculate_percentage_penalty($attempt) {
        global $DB;

        $percentage = 0;
        $lateattempt = $DB->get_record('quizaccess_latesubmission_at', ['attemptid' => $attempt->id]);
        // Return if the attempt is not an overdue one.
        if (empty($lateattempt)) {
            return $percentage;
        }

        $timedue = $lateattempt->timedue ? $lateattempt->timedue + self::GRACE_PERIOD : 0;

        // Apply penalty.
        if (!empty($lateattempt->appliedpenalty) && ($timedue <= $lateattempt->timefinish)) {
            $timeoverdue = $lateattempt->timefinish - self::GRACE_PERIOD - $lateattempt->timedue;

            $numberofdays = ceil($timeoverdue / DAYSECS);
            if ($timeoverdue % DAYSECS == 0) {
                // This already past a day period so the added 1 day.
                $numberofdays += 1;
            }
            for ($i = 0; $i < $numberofdays; $i++) {
                // Note: Use without grace period otherwise it will go past next day.
                $datetocheck = $lateattempt->timedue + DAYSECS * ($i + 1);
                // Exclude weekend and holidays.
                if (!self::is_non_working_day($datetocheck)) {
                    $percentage += $lateattempt->dailypercentagepenalty;
                }
            }
        }
        // If max penalty is zero, negative, or greater than 100 it becomes 100%.
        $maxpenalty = ($lateattempt->maxpercentagepenalty > 0 && $lateattempt->maxpercentagepenalty <= 100)
            ? $lateattempt->maxpercentagepenalty : 100;
        return min($maxpenalty, $percentage);
    }

    /**
     * Update if the submission is late or not
     *
     * @param  mod_quiz\quiz_attempt $attempt
     */
    public static function update_overdue_status($attempt) {
        global $DB;
        $quiz = $DB->get_record('quizaccess_latesubmission', ['quizid' => $attempt->quiz]);
        if (!empty($quiz)) {
            $timedue = $quiz->timedue ? $quiz->timedue + self::GRACE_PERIOD : 0;
            // The assessment is due.
            if (!empty($timedue) && $timedue <= $attempt->timefinish) {
                $record = $DB->get_record('quizaccess_latesubmission_at', ['attemptid' => $attempt->id]);
                if (empty($record)) {
                    // Create new if not exist.
                    $record = new stdClass();
                    $record->attemptid = $attempt->id;
                    $record->quizid = $attempt->quiz;
                    $record->timefinish = $attempt->timefinish;
                    $record->timedue = $quiz->timedue;
                    $record->appliedpenalty = $quiz->appliedpenalty;
                    $record->dailypercentagepenalty = $quiz->dailypercentagepenalty;
                    $record->maxpercentagepenalty = $quiz->maxpercentagepenalty;
                    $DB->insert_record('quizaccess_latesubmission_at', $record);
                } else {
                    // There should be only change in timedue setting of the quiz.
                    $record->timedue = $quiz->timedue;
                    $record->appliedpenalty = $quiz->appliedpenalty;
                    $record->dailypercentagepenalty = $quiz->dailypercentagepenalty;
                    $record->maxpercentagepenalty = $quiz->maxpercentagepenalty;
                    $DB->update_record('quizaccess_latesubmission_at', $record);
                }
            } else {
                // Remove as it is not a late submission any more.
                $DB->delete_records('quizaccess_latesubmission_at', ['attemptid' => $attempt->id]);
            }
        }
    }

    /**
     * Check if a day is holiday
     *
     * @param int $time
     *
     * @return bool
     */
    private static function is_non_working_day($time): bool {
        // Check if the date is weekend.
        $weekday = date('w', $time);
        if ($weekday == 0 || $weekday == 6) {
            return true;
        }

        // Else check if it is public holiday.
        $holidays = get_config('quizaccess_latesubmission', 'holiday');
        if (empty($holidays)) {
            return false;
        }
        $holidays = explode("\r\n", $holidays);
        foreach ($holidays as $holiday) {
            $format = 'd/m/Y';
            $holidaydate = DateTime::createFromFormat($format, $holiday)->getTimestamp();
            $holidaydate = date($format, $holidaydate);
            $date = date($format, $time);
            if ($holidaydate == $date) {
                return true;
            }
        }
        return false;
    }


    /**
     * Build query
     * @param stdClass $quiz
     *
     * @return array
     */
    public static function build_additional_columns($quiz) {
        global $DB;
        $record = $DB->get_record('quizaccess_latesubmission', array('quizid' => $quiz->id));
        if (!empty($record) && $record->timedue > 0) {
            $fields = ",\nCASE WHEN late.attemptid is not null THEN '" . self::OVERDUE . "'
                        ELSE '" . self::ONTIME . "' END AS latesubmission";
            $from = "\nLEFT JOIN {quizaccess_latesubmission_at} late ON
                                    late.attemptid = quiza.id";
            $cols = ['latesubmission' => get_string('latesubmission', 'quizaccess_latesubmission')];
            return [$fields, $from, '', [], $cols];
        } else {
            return ['', '', '', [], []];
        }
    }
}
