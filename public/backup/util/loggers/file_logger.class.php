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
 * @package    moodlecore
 * @subpackage backup-logger
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Logger implementation that sends indented messages (depth option) to one file
 *
 * TODO: Finish phpdocs
 */
class file_logger extends base_logger {

    protected $fullpath; // Full path to OS file where contents will be stored
    protected $fhandle;  // File handle where all write operations happen
    /**
     * @var string Store the relative path for serialization.
     */
    protected $relativepath;

    public function __construct($level, $showdate = false, $showlevel = false, $fullpath = null) {
        if (empty($fullpath)) {
            throw new base_logger_exception('missing_fullpath_parameter', $fullpath);
        }

        // If an absolute path is provided, convert it to relative by extracting just the filename.
        if (strpos($fullpath, '/') === 0 || preg_match('/^[a-zA-Z]:[\/\\\\]/', $fullpath)) {
            // Extract just the filename from the absolute path.
            $this->relativepath = basename($fullpath);
        } else {
            // Already a relative path.
            $this->relativepath = $fullpath;
        }

        // Always construct the full path dynamically using current $CFG->backuptempdir.
        $backuptempdir = make_backup_temp_directory('');
        $this->fullpath = $backuptempdir . '/' . $this->relativepath;

        if (!is_writable(dirname($this->fullpath))) {
            throw new base_logger_exception('file_not_writable', $this->fullpath);
        }
        // Open the OS file for writing (append)
        $this->fullpath = $fullpath;
        if ($level > backup::LOG_NONE) { // Only create the file if we are going to log something
            if (! $this->fhandle = fopen($this->fullpath, 'a')) {
                throw new base_logger_exception('error_opening_file', $this->fullpath);
            }
        }
        parent::__construct($level, $showdate, $showlevel);
    }

    public function __destruct() {
        if (is_resource($this->fhandle)) {
            // Blindy close the file handler (no exceptions in destruct).
            @fclose($this->fhandle);
        }
    }

    public function __sleep() {
        if (is_resource($this->fhandle)) {
            // Blindy close the file handler before serialization.
            @fclose($this->fhandle);
            $this->fhandle = null;
        }
        // Only serialize the relative path, not the absolute path.
        // The absolute path will be reconstructed on wakeup using the current environment.
        return ['level', 'showdate', 'showlevel', 'next', 'relativepath'];
    }

    public function __wakeup() {
        // Handle both absolute and relative paths for portability.
        // If fullpath is absolute and doesn't exist, try to reconstruct it using current backup temp dir.
        if (!empty($this->fullpath)) {
            $isabsolute = (strpos($this->fullpath, '/') === 0 || preg_match('/^[a-zA-Z]:[\/\\\\]/', $this->fullpath));

            if ($isabsolute && !file_exists(dirname($this->fullpath))) {
                // Absolute path from different installation - extract filename and use current backup temp dir.
                $filename = basename($this->fullpath);
                $backuptempdir = make_backup_temp_directory('');
                $this->fullpath = $backuptempdir . '/' . $filename;
            } else if (!$isabsolute) {
                // Relative path - construct full path using current backup temp dir.
                $backuptempdir = make_backup_temp_directory('');
                $this->fullpath = $backuptempdir . '/' . $this->fullpath;
            }
        }

        if ($this->level > backup::LOG_NONE) { // Only create the file if we are going to log something
            if (! $this->fhandle = fopen($this->fullpath, 'a')) {
                throw new base_logger_exception('error_opening_file', $this->fullpath);
            }
        }
    }

    /**
     * Close the logger resources (file handle) if still open.
     *
     * @since Moodle 3.1
     */
    public function close() {
        // Close the file handle if hasn't been closed already.
        if (is_resource($this->fhandle)) {
            fclose($this->fhandle);
            $this->fhandle = null;
        }
    }

// Protected API starts here

    protected function action($message, $level, $options = null) {
        $prefix = $this->get_prefix($level, $options);
        $depth = isset($options['depth']) ? $options['depth'] : 0;
        // Depending of the type (extension of the file), format differently
        if (substr($this->fullpath, -5) !== '.html') {
            $content = $prefix . str_repeat('  ', $depth) . $message . PHP_EOL;
        } else {
            $content = $prefix . str_repeat('&nbsp;&nbsp;', $depth) . htmlentities($message, ENT_QUOTES, 'UTF-8') . '<br/>' . PHP_EOL;
        }
        if (!is_resource($this->fhandle) || (false === fwrite($this->fhandle, $content))) {
            throw new base_logger_exception('error_writing_file', $this->fullpath);
        }
        return true;
    }
}
