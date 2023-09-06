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

namespace quizaccess_latesubmission;

use core\context\course;
use DateTime;
use mod_quiz\access_manager;
use mod_quiz\plugininfo\quiz;
use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;
use quizaccess_latesubmission;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/latesubmission/rule.php');

/**
 * Unit tests for the quizaccess_latesubmission plugin.
 *
 * @package    quizaccess_latesubmission
 * @subpackage latesubmission
 * @author  Nathan Nguyen <nathannguyen@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \quizaccess_latesubmission
 */
class rule_test extends \advanced_testcase {

    /**
     * Test helper to create course and quiz.
     *
     * @param int $timeopen
     * @param int $timeclose
     *
     * @return array of course and quiz
     */
    private function create_course_and_quiz($timeopen, $timeclose) {
        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Quiz.
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(
        [
            'course' => $course->id,
            'grade' => 100.0,
            'sumgrades' => 2,
            'attempts' => 10,
            'layout' => '1,0',
            'timeopen' => $timeopen,
            'timeclose' => $timeclose,
            'timelimit' => 0
        ]);

        // Create question and add to quiz.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question1 = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));
        $question2 = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));
        quiz_add_quiz_question($question1->id, $quiz);
        quiz_add_quiz_question($question2->id, $quiz);

        // Return course and quiz.
        return [$course, $quiz];
    }

    /**
     * Test helper to create quiz object.
     *
     * @param course $course
     * @param quiz $quiz
     * @param int $timedue
     * @param bool $appliedpenalty
     * @param int $dailypercentagepenalty
     * @param bool $preventresubmission
     * @param int $maxpercentagepenalty
     *
     * @return quiz_settings
     */
    private function create_quiz_object($course, $quiz,
                                        $timedue = 0, $appliedpenalty = false,
                                        $dailypercentagepenalty = 0,  $preventresubmission = false, $maxpercentagepenalty = 100 ) {

        $quiz->timedue = $timedue;
        $quiz->appliedpenalty = $appliedpenalty;
        $quiz->dailypercentagepenalty = $dailypercentagepenalty;
        $quiz->maxpercentagepenalty = $maxpercentagepenalty;
        $quiz->preventresubmission = $preventresubmission;
        quizaccess_latesubmission::save_settings($quiz);

        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);
        $quizobj = new quiz_settings($quiz, $cm, $course);

        return $quizobj;
    }

    /**
     * Test helper to create attempt object.
     *
     * @param quiz $quiz
     * @param \stdClass $user
     * @param int $attemptnumber
     * @param int $timestart
     * @param int $timefinish
     *
     */
    private function create_attempt($quiz, $user, $attemptnumber, $timestart = 0, $timefinish = 0) {
        // Create attempt.
        $quizobj = quiz_settings::create($quiz->id, $user->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $attempt = quiz_create_attempt($quizobj, $attemptnumber, null, $timestart, false, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, $attemptnumber, $timestart);
        quiz_attempt_save_started($quizobj, $quba, $attempt);

        // Submit attempt.
        $attemptobj = quiz_attempt::create($attempt->id);
        if ($timefinish > 0) {
            $attemptobj->process_submitted_actions($timefinish, false, [1 => ['answer' => 'frog']]);
            $attemptobj = quiz_attempt::create($attempt->id);
            $attemptobj->process_finish($timefinish, false);
        }
    }

    /**
     * Tests the latesubmission_description function.
     */
    public function test_latesubmission_description() {
        $this->resetAfterTest();

        // Course and quiz.
        list($course, $quiz) = $this->create_course_and_quiz(1, 3600);

        // No time due.
        $rule = new quizaccess_latesubmission($this->create_quiz_object($course, $quiz), 1200);
        $this->assertEmpty($rule->description());

        $timeduesetting = 3600;

        // Due on a future time without penalty.
        $rule = new quizaccess_latesubmission($this->create_quiz_object($course, $quiz, $timeduesetting), 3601);
        $this->assertContains(get_string('quizdueon', 'quizaccess_latesubmission',
            userdate($timeduesetting)), $rule->description());
        $this->assertContains(get_string('quizduewithoutpenalty', 'quizaccess_latesubmission',
            userdate($timeduesetting)), $rule->description());

        // Due on a future time with penalty of 5 percent.
        $rule = new quizaccess_latesubmission($this->create_quiz_object($course, $quiz, $timeduesetting,
            true, 5), 3659);
        $this->assertContains(get_string('quizdueon', 'quizaccess_latesubmission',
            userdate($timeduesetting)), $rule->description());
        $this->assertContains(get_string('quizduewithpenalty', 'quizaccess_latesubmission', 5),
            $rule->description());

        // Already due without penalty.
        $rule = new quizaccess_latesubmission($this->create_quiz_object($course, $quiz, $timeduesetting), 3660);
        $this->assertContains(get_string('quizalreadydue', 'quizaccess_latesubmission',
            userdate($timeduesetting)), $rule->description());
        $this->assertContains(get_string('quizduewithoutpenalty', 'quizaccess_latesubmission',
            userdate($timeduesetting)), $rule->description());

        // Already due with penalty of 5 percent.
        $rule = new quizaccess_latesubmission($this->create_quiz_object($course, $quiz, $timeduesetting, true, 5), 3660);
        $this->assertContains(get_string('quizalreadydue', 'quizaccess_latesubmission',
            userdate($timeduesetting)), $rule->description());
        $this->assertContains(get_string('quizduewithpenalty', 'quizaccess_latesubmission', 5),
            $rule->description());

    }

    /**
     * Tests that latesubmission does not prevent access to quizzes.
     */
    public function test_latesubmission_does_not_prevent_access() {
        $this->resetAfterTest();

        // Course and quiz.
        list($course, $quiz) = $this->create_course_and_quiz(1000, 2000);

        // User.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // No time due.
        $rule = new quizaccess_latesubmission($this->create_quiz_object($course, $quiz), 1000);
        $this->assertFalse($rule->prevent_access());

        // Due in the future.
        $rule = new quizaccess_latesubmission($this->create_quiz_object($course, $quiz, 2000), 1500);
        $this->assertFalse($rule->prevent_access());

        // Set to do not prevent resubmission even if it is already due.
        $rule = new quizaccess_latesubmission($this->create_quiz_object($course, $quiz, 2000), 2001);
        $this->assertFalse($rule->prevent_access());

        // There is finished attempt but do not prevent resubmission.
        $quizobject = $this->create_quiz_object($course, $quiz, 2000);
        $this->create_attempt($quizobject->get_quiz(), $user, 1, 1000, 1500);
        $rule = new quizaccess_latesubmission($quizobject, 2001);
        $this->assertFalse($rule->prevent_access());

        // Set to prevent resubmission but there is unfinished attempt.
        $quizobject = $this->create_quiz_object($course, $quiz, 2000, false, 0, true);
        $this->create_attempt($quizobject->get_quiz(), $user, 2, 1000);
        $rule = new quizaccess_latesubmission($quizobject, 2001);
        $this->assertFalse($rule->prevent_access());
    }

    /**
     * Tests that latesubmission grading still functions properly.
     *
     * @param int $expected
     * @param quiz $quiz
     * @param stdClass $user
     */
    private function asssert_grade($expected, $quiz, $user) {
        $attempts = quiz_get_user_attempts($quiz->id, $user->id);
        $attempt = end($attempts);
        $this->assertEquals($expected, (int) ($attempt->sumgrades / $quiz->sumgrades * 100));
    }

    /**
     * Tests that latesubmission prevents access to already submitted/closed quizzes.
     */
    public function test_latesubmission_prevent_access() {
        $this->resetAfterTest();

        // Course and quiz.
        list($course, $quiz) = $this->create_course_and_quiz(1000, 2000);

        // User.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // There is already finished attempt (and prevent submission).
        $quizobject = $this->create_quiz_object($course, $quiz, 2000, false, 0, true);
        $this->create_attempt($quizobject->get_quiz(), $user, 1, 1000, 1500);
        $rule = new quizaccess_latesubmission($quizobject, 2001);
        $this->assertEquals($rule->prevent_access(), get_string('quizalreadysubmittedbeforeduedate', 'quizaccess_latesubmission'));
    }

    /**
     * Tests that latesubmission with no penalty.
     */
    public function test_no_penalty() {
        global $DB;

        $this->resetAfterTest();

        // Course and quiz.
        list($course, $quiz) = $this->create_course_and_quiz(1, 3600);

        // User.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Does not apply penalty.
        $quizobject = $this->create_quiz_object($course, $quiz, 3600);
        $this->create_attempt($quizobject->get_quiz(), $user, 1, 1000, 3601);
        $attempts = quiz_get_user_attempts($quiz->id, $user->id);
        $attempt = end($attempts);
        $this->assertEquals(0, quizaccess_latesubmission::calculate_percentage_penalty($attempt));
        $this->assertEquals(0, access_manager::accumulate_percentage_penalty($attempt));
        // Check grade.
        $this->asssert_grade(50, $quiz, $user);

        // No penalty if the submission is still on the grace period - 60 seconds.
        $quizobject = $this->create_quiz_object($course, $quiz, 3600, true, 5);
        $this->create_attempt($quizobject->get_quiz(), $user, 2, 1000, 3659);
        $attempts = quiz_get_user_attempts($quiz->id, $user->id);
        $attempt = end($attempts);
        $this->assertEquals(0, quizaccess_latesubmission::calculate_percentage_penalty($attempt));
        $this->assertEquals(0, access_manager::accumulate_percentage_penalty($attempt));
        $record = $DB->get_record('quizaccess_latesubmission_at', ['attemptid' => $attempt->id]);
        $this->assertFalse($record);
        // Check grade.
        $this->asssert_grade(50, $quiz, $user);
    }

    /**
     * @return array of test cases
     *
     * Combinations of base and relative parts of URL
     */
    public function quiz_attempts_provider() {
        return array(
            array(
                // Tuesday 28/11/2022 23.59.59.
                'timefinish' => DateTime::createFromFormat('d-m-y H:i:s', '28-11-22 23:59:59')->getTimestamp(),
                'expectedpenalty' => 0,
                'expectedgrade' => 50,
                'regrade' => -1,
                'late' => false,
            ),
            array(
                // Tuesday 29/11/2022 00.00.00.
                'timefinish' => DateTime::createFromFormat('d-m-y H:i:s', '29-11-22 00:00:00')->getTimestamp(),
                'expectedpenalty' => 5,
                'expectedgrade' => 45,
            ),
            array(
                // Tuesday 29/11/2022 23.59.59.
                'timefinish' => DateTime::createFromFormat('d-m-y H:i:s', '29-11-22 23:59:59')->getTimestamp(),
                'expectedpenalty' => 5,
                'expectedgrade' => 45,
            ),
            array(
                // Wednesday 30/11/2022 00.00.00.
                'timefinish' => DateTime::createFromFormat('d-m-y H:i:s', '30-11-22 00:00:00')->getTimestamp(),
                'expectedpenalty' => 10,
                'expectedgrade' => 40,
            ),
            array(
                // Wednesday 30/11/2022 23.59.59.
                'timefinish' => DateTime::createFromFormat('d-m-y H:i:s', '30-11-22 23:59:59')->getTimestamp(),
                'expectedpenalty' => 10,
                'expectedgrade' => 40,
            ),
            array(
                // Thursday 01/12/2022 00.00.00.
                'timefinish' => DateTime::createFromFormat('d-m-y H:i:s', '01-12-22 00:00:00')->getTimestamp(),
                'expectedpenalty' => 15,
                'expectedgrade' => 35,
            ),
            array(
                // Thursday 01/12/2022 23.59.59.
                'timefinish' => DateTime::createFromFormat('d-m-y H:i:s', '01-12-22 23:59:59')->getTimestamp(),
                'expectedpenalty' => 15,
                'expectedgrade' => 35,
            ),
            array(
                // Friday 02/12/2022 00.00.00.
                'timefinish' => DateTime::createFromFormat('d-m-y H:i:s', '02-12-22 00:00:00')->getTimestamp(),
                'expectedpenalty' => 20,
                'expectedgrade' => 30,
            ),
            array(
                // Friday 02/12/2022 23.59.59.
                'timefinish' => DateTime::createFromFormat('d-m-y H:i:s', '02-12-22 23:59:59')->getTimestamp(),
                'expectedpenalty' => 20,
                'expectedgrade' => 30,
            ),
            array(
                // Saturday. 03/12/2022 00.00.00.
                'timefinish' => DateTime::createFromFormat('d-m-y H:i:s', '03-12-22 00:00:00')->getTimestamp(),
                'expectedpenalty' => 20,
                'expectedgrade' => 30,
            ),
            array(
                // Saturday. 03/12/2022 23.59.59.
                'timefinish' => DateTime::createFromFormat('d-m-y H:i:s', '03-12-22 23:59:59')->getTimestamp(),
                'expectedpenalty' => 20,
                'expectedgrade' => 30,
            ),
            array(
                // Sunday. 04/12/2022 00.00.00.
                'timefinish' => DateTime::createFromFormat('d-m-y H:i:s', '04-12-22 00:00:00')->getTimestamp(),
                'expectedpenalty' => 20,
                'expectedgrade' => 30,
            ),
            array(
                // Sunday. 04/12/2022 23.59.59.
                'timefinish' => DateTime::createFromFormat('d-m-y H:i:s', '04-12-22 23:59:59')->getTimestamp(),
                'expectedpenalty' => 20,
                'expectedgrade' => 30,
            ),
            array(
                // Monday. 05/12/2022 00.00.00 - Holiday.
                'timefinish' => DateTime::createFromFormat('d-m-y H:i:s', '05-12-22 00:00:00')->getTimestamp(),
                'expectedpenalty' => 20,
                'expectedgrade' => 30,
            ),
            array(
                // Monday. 05/12/2022 23.59.59 - Holiday.
                'timefinish' => DateTime::createFromFormat('d-m-y H:i:s', '05-12-22 23:59:59')->getTimestamp(),
                'expectedpenalty' => 20,
                'expectedgrade' => 30,
            ),
            array(
                // Tuesday. 06/12/2022 00.00.00.
                'timefinish' => DateTime::createFromFormat('d-m-y H:i:s', '06-12-22 00:00:00')->getTimestamp(),
                'expectedpenalty' => 25,
                'expectedgrade' => 25,
            ),
            array(
                // Tuesday. 06/12/2022 23.59.59.
                'timefinish' => DateTime::createFromFormat('d-m-y H:i:s', '06-12-22 23:59:59')->getTimestamp(),
                'expectedpenalty' => 25,
                'expectedgrade' => 25,
            ),
            array(
                // Wednesday. 07/12/2022 00.00.00 - Holiday.
                'timefinish' => DateTime::createFromFormat('d-m-y H:i:s', '07-12-22 00:00:00')->getTimestamp(),
                'expectedpenalty' => 25,
                'expectedgrade' => 25,
            ),
            array(
                // Wednesday. 07/12/2022 23.59.59 - Holiday.
                'timefinish' => DateTime::createFromFormat('d-m-y H:i:s', '07-12-22 23:59:59')->getTimestamp(),
                'expectedpenalty' => 25,
                'expectedgrade' => 25,
            ),
            array(
                // More than 5 days overdue but max penalty is set to 25%.
                'timefinish' => DateTime::createFromFormat('d-m-y H:i:s', '08-12-22 00:00:00')->getTimestamp(),
                'expectedpenalty' => 25,
                'expectedgrade' => 25,
            ),
            array(
                // More than 5 days overdue but max penalty is set to 25%.
                'timefinish' => DateTime::createFromFormat('d-m-y H:i:s', '09-12-22 23:59:59')->getTimestamp(),
                'expectedpenalty' => 25,
                'expectedgrade' => 25,
            ),
            array(
                // Tuesday - 29/11/2022 00.00.00 - Regrade.
                'timefinish' => DateTime::createFromFormat('d-m-y H:i:s', '29-11-22 00:00:00')->getTimestamp(),
                'expectedpenalty' => 0,
                'expectedgrade' => 50,
                'regrade' => 45,
            ),
            array(
                // Tuesday - 29/11/2022 23.59.59 - Regrade.
                'timefinish' => DateTime::createFromFormat('d-m-y H:i:s', '29-11-22 23:59:59')->getTimestamp(),
                'expectedpenalty' => 0,
                'expectedgrade' => 50,
                'regrade' => 45,
            ),
        );
    }

    /**
     * @dataProvider quiz_attempts_provider
     *
     * @param int $timefinish
     * @param int $expectedpenalty
     * @param int $expectedgrade
     * @param int $regrade
     * @param bool $late
     *
     * Tests that latesubmission with various penalties from dataprovider.
     * @return void
     */
    public function test_calculate_percentage_penalty($timefinish, $expectedpenalty, $expectedgrade, $regrade = -1, $late = true) {
        global $DB;
        $this->resetAfterTest();

        // Holiday.
        set_config('holiday', "05/12/2022\r\n07/12/2022", 'quizaccess_latesubmission');

        // Course and quiz.
        $timeopen = DateTime::createFromFormat('d-m-y H:i:s', '21-11-22 9:00:00')->getTimestamp();
        $timeclose = DateTime::createFromFormat('d-m-y H:i:s', '08-12-22 23:59:00')->getTimestamp();
        list($course, $quiz) = $this->create_course_and_quiz($timeopen, $timeclose);

        // User.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Will not applied penalty for now if testing regrading.
        $willappliedpenalty = $regrade >= 0 ? false : true;
        $timedue = DateTime::createFromFormat('d-m-y H:i:s', '28-11-22 23:59:00')->getTimestamp();
        $quizobject = $this->create_quiz_object($course, $quiz,  $timedue, $willappliedpenalty, 5,
            false, 25);

        // Student attempt.
        $this->create_attempt($quizobject->get_quiz(), $user, 1, $timeopen + HOURSECS, $timefinish);
        $attempts = quiz_get_user_attempts($quiz->id, $user->id);
        $attempt = end($attempts);
        // Penalty.
        $this->assertEquals($expectedpenalty, quizaccess_latesubmission::calculate_percentage_penalty($attempt));
        $this->assertEquals($expectedpenalty, access_manager::accumulate_percentage_penalty($attempt));
        // Identify as late.
        $record = $DB->get_record('quizaccess_latesubmission_at', ['attemptid' => $attempt->id]);
        if ($late) {
            $this->assertNotEmpty($record);
        } else {
            $this->assertEmpty($record);
        }
        // Check grade.
        $this->asssert_grade($expectedgrade, $quiz, $user);

        // Test regrade with penalty applied.
        if ($regrade >= 0) {
            $this->create_quiz_object($course, $quiz, $timedue, true, 5);

            // Trigger regrade event so that the 'quizaccess_latesubmission_at' record can be updated.
            $params = array(
                'objectid' => $attempt->id,
                'relateduserid' => $attempt->userid,
                'context' => \context_module::instance($quiz->cmid),
                'other' => array(
                    'quizid' => $attempt->quiz
                )
            );
            $event = \mod_quiz\event\attempt_regraded::create($params);
            $event->trigger();

            // Run update_overall_grades function of grade report.
            $quizobj = quiz_settings::create($attempt->quiz);
            $gradecalculator = $quizobj->get_grade_calculator();
            $gradecalculator->recompute_all_attempt_sumgrades();
            $gradecalculator->recompute_attempts_sumgrades_with_penalty();
            $gradecalculator->recompute_all_final_grades();
            quiz_update_grades($quiz);
            $this->asssert_grade($regrade, $quiz, $user);
        }

    }

    public function test_negative_grade() {
        $this->resetAfterTest();

        // Course and quiz.
        list($course, $quiz) = $this->create_course_and_quiz(1, 3600);

        // User.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Late for too long that attract more penalty than the student grade.
        $quizobject = $this->create_quiz_object($course, $quiz, 3600, true, 5);
        $this->create_attempt($quizobject->get_quiz(), $user, 1, 1000, 3661 + 30 * DAYSECS);
        // Grade is 0 instead of negative number.
        $this->asssert_grade(0, $quiz, $user);
    }
}
