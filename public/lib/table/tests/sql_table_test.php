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

namespace core_table;

use moodle_database;

/**
 * Unit tests for the SQL table
 *
 * @package   core_table
 * @covers    \core_table\sql_table
 * @copyright 2026 Moodle Pty Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sql_table_test extends \advanced_testcase {
    /**
     * Test which database connection the table uses, depending on the read-only connection flag
     */
    public function test_get_database(): void {
        global $DB;

        $readonly = $this->createMock(moodle_database::class);
        $primary = $this->createMock(moodle_database::class);
        $primary->method('get_readonly_connection')->willReturn($readonly);
        $primary->method('has_readonly_connection')->willReturn(true);
        $noreplica = $this->createMock(moodle_database::class);
        $noreplica->method('has_readonly_connection')->willReturn(false);
        $noreplica->expects($this->never())->method('get_readonly_connection');

        $default = new class ('default') extends sql_table {
            /**
             * Expose the database connection
             *
             * @return moodle_database
             */
            public function database(): moodle_database {
                return $this->get_database();
            }
        };
        $replica = new class ('replica') extends sql_table {
            /** @var bool Use read-only connection */
            protected bool $usereadonlyconnection = true;

            /**
             * Expose the database connection
             *
             * @return moodle_database
             */
            public function database(): moodle_database {
                return $this->get_database();
            }
        };

        $originaldb = $DB;
        $DB = $primary;
        try {
            $this->assertSame($primary, $default->database());
            $this->assertSame($readonly, $replica->database());

            // Without a replica the table falls back to the primary connection.
            $DB = $noreplica;
            $this->assertSame($noreplica, $replica->database());
        } finally {
            $DB = $originaldb;
        }
    }
}
