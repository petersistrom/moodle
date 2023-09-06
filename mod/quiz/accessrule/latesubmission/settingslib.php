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
 * Admin setting for quizaccess_latesubmission.
 *
 * @package    quizaccess_latesubmission
 * @subpackage latesubmission
 * @author  Nathan Nguyen <nathannguyen@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Holiday dates settings validation.
 *
 */
class admin_setting_configtextarea_datelist extends admin_setting_configtextarea {
    /**
     * Validate data before storage
     *
     * @param string $value
     * @return true|string Error message in case of error, true otherwise.
     * @throws \coding_exception
     */
    public function validate($value) {
        if (empty($value)) {
            return true;
        }
        if ($validation = parent::validate($value)) {
            $holidays = explode("\r\n", $value);
            foreach ($holidays as $date) {
                $datepart = explode("/", $date);

                $invalid = false;

                if (count($datepart) < 3) {
                    $invalid = true;
                } else {
                    // Month, day, year.
                    if (!checkdate($datepart[1], $datepart[0], $datepart[2])) {
                        $invalid = true;
                    }
                }

                if ($invalid) {
                    return get_string('invalidate', 'quizaccess_latesubmission', $date);
                }
            }
            return true;
        } else {
            return $validation;
        }
    }
}

/**
 * Percentage settings validation.
 *
 */
class admin_setting_configtext_percentage extends admin_setting_configtext {
    /** @var int $min Min value */
    private $min;

    /** @var int $max Max value */
    private $max;

    /**
     * Config text constructor
     *
     * @param string $name unique ascii name, either 'mysetting' for settings that in config,
     * or 'myplugin/mysetting' for ones in config_plugins.
     * @param string $visiblename localised
     * @param string $description long localised info
     * @param string $defaultsetting
     * @param int $min
     * @param int $max
     * @param int $size default field size
     */
    public function __construct($name, $visiblename, $description, $defaultsetting, $min=0, $max=100, $size=null) {
        $this->min = $min;
        $this->max = $max;
        parent::__construct($name, $visiblename, $description, $defaultsetting, PARAM_INT, $size);
    }

    /**
     * Validate data before storage
     *
     * @param string $value
     * @return true|string Error message in case of error, true otherwise.
     * @throws \coding_exception
     */
    public function validate($value) {
        if ($validation = parent::validate($value)) {
            if ($this->min <= $value && $this->max >= $value) {
                return true;
            } else {
                return get_string('validateerror', 'admin');
            }
        } else {
            return $validation;
        }
    }
}
