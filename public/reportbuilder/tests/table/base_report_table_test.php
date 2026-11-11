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

declare(strict_types=1);

namespace core_reportbuilder\table;

use advanced_testcase;
use context_system;
use core_reportbuilder\system_report_available;
use core_reportbuilder\system_report_factory;
use core_reportbuilder_generator;
use core_user\reportbuilder\datasource\users;
use moodle_database;
use moodle_recordset;

/**
 * Unit tests for the base report table class
 *
 * @package     core_reportbuilder
 * @copyright   2026 Moodle Pty Ltd
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(base_report_table::class)]
final class base_report_table_test extends advanced_testcase {
    /**
     * Data provider for the tests, with the report type and whether the read-only connection is expected
     *
     * @return array[]
     */
    public static function connection_provider(): array {
        return [
            'custom report' => ['custom', true],
            'system report' => ['system', false],
        ];
    }

    /**
     * Create a report table of the given type
     *
     * @param string $type
     * @return base_report_table
     */
    private function create_table(string $type): base_report_table {
        global $CFG;
        require_once("{$CFG->dirroot}/reportbuilder/tests/fixtures/system_report_available.php");

        if ($type === 'custom') {
            /** @var core_reportbuilder_generator $generator */
            $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
            $report = $generator->create_report(['name' => 'Users', 'source' => users::class]);

            return custom_report_table_view::create($report->get('id'));
        }

        $report = system_report_factory::create(system_report_available::class, context_system::instance());
        return system_report_table::create($report->get_report_persistent()->get('id'), $report->get_parameters());
    }

    /**
     * Create a primary connection mock that also provides a read-only connection mock
     *
     * @return moodle_database[] Primary and read-only connection
     */
    private function create_connections(): array {
        $readonly = $this->createMock(moodle_database::class);
        $primary = $this->createMock(moodle_database::class);
        $primary->method('has_readonly_connection')->willReturn(true);
        $primary->method('get_readonly_connection')->willReturn($readonly);

        return [$primary, $readonly];
    }

    /**
     * Test the total row count is read using the expected connection
     *
     * @param string $type
     * @param bool $expectreadonly
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('connection_provider')]
    public function test_get_total_row_count(string $type, bool $expectreadonly): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $table = $this->create_table($type);
        [$primary, $readonly] = $this->create_connections();

        $used = $expectreadonly ? $readonly : $primary;
        $unused = $expectreadonly ? $primary : $readonly;
        $used->expects($this->once())->method('count_records_sql')->willReturn(5);
        $unused->expects($this->never())->method('count_records_sql');

        $originaldb = $DB;
        $DB = $primary;
        try {
            $this->assertEquals(5, $table->get_total_row_count());
        } finally {
            $DB = $originaldb;
        }
    }

    /**
     * Test the report data is read using the expected connection
     *
     * @param string $type
     * @param bool $expectreadonly
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('connection_provider')]
    public function test_query_db(string $type, bool $expectreadonly): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/');

        $table = $this->create_table($type);
        $table->guess_base_url();
        $table->setup();
        [$primary, $readonly] = $this->create_connections();

        $used = $expectreadonly ? $readonly : $primary;
        $unused = $expectreadonly ? $primary : $readonly;
        $used->expects($this->once())
            ->method('get_counted_recordset_sql')
            ->willReturn($this->createMock(moodle_recordset::class));
        $unused->expects($this->never())->method('get_counted_recordset_sql');

        $originaldb = $DB;
        $DB = $primary;
        try {
            $table->query_db(30, false);
        } finally {
            $DB = $originaldb;
        }
    }

    /**
     * Test the primary connection is used when no read-only connection is available
     */
    public function test_query_db_without_readonly_connection(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/');

        $table = $this->create_table('custom');
        $table->guess_base_url();
        $table->setup();

        $primary = $this->createMock(moodle_database::class);
        $primary->method('has_readonly_connection')->willReturn(false);
        $primary->expects($this->never())->method('get_readonly_connection');
        $primary->expects($this->once())
            ->method('get_counted_recordset_sql')
            ->willReturn($this->createMock(moodle_recordset::class));

        $originaldb = $DB;
        $DB = $primary;
        try {
            $table->query_db(30, false);
        } finally {
            $DB = $originaldb;
        }
    }
}
