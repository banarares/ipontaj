<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Membership;
use App\Models\MonthClosure;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\ExcelService;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class AccountantWorkbookTest extends TestCase
{
    use RefreshDatabase;

    private function team(): array
    {
        $this->travelTo(Carbon::parse('2026-11-25 12:00', 'Europe/Bucharest'));
        $owner = User::factory()->create();
        $company = Company::create(['name' => 'Atelier Exemplu SRL', 'cif' => '1234', 'timezone' => 'Europe/Bucharest']);
        $company->memberships()->create(['user_id' => $owner->id, 'role' => 'owner', 'tracked' => false, 'joined_on' => '2026-01-01']);
        $employee = User::factory()->create(['first_name' => 'Ana', 'last_name' => 'Popescu']);
        $member = $company->memberships()->create(['user_id' => $employee->id, 'joined_on' => '2026-11-01']);
        $company->schedules()->create(['effective_from' => '2026-11-01', 'weekdays' => [1, 2, 3, 4, 5], 'start_time' => '09:00', 'end_time' => '17:00']);

        return [$owner, $company, $member];
    }

    private function entry(Membership $member, string $date, int $minutes, ?string $absence = null, bool $confirmed = false, bool $approved = false): TimeEntry
    {
        return $member->entries()->create(['company_id' => $member->company_id, 'date' => $date, 'minutes' => $minutes, 'absence' => $absence, 'confirmed_at' => $confirmed ? now() : null, 'confirmed_by' => $confirmed ? $member->user_id : null, 'approved_at' => $approved ? now() : null]);
    }

    public function test_decimal_hours_statuses_and_absence_totals_do_not_count_unapproved_absence_as_approved(): void
    {
        [$owner, $company, $member] = $this->team();
        $first = $this->entry($member, '2026-11-02', 450, confirmed: true);
        $first->intervals()->createMany([
            ['kind' => 'work', 'start_at' => Carbon::parse('2026-11-02 09:00', $company->timezone)->utc(), 'end_at' => Carbon::parse('2026-11-02 17:00', $company->timezone)->utc()],
            ['kind' => 'break', 'start_at' => Carbon::parse('2026-11-02 12:00', $company->timezone)->utc(), 'end_at' => Carbon::parse('2026-11-02 12:30', $company->timezone)->utc()],
        ]);
        $this->entry($member, '2026-11-03', 485, approved: true);
        $this->entry($member, '2026-11-04', 60);
        foreach (['05' => 'CO', '06' => 'CM', '09' => 'CFP', '10' => 'A'] as $day => $absence) {
            $this->entry($member, '2026-11-'.$day, 0, $absence, approved: true);
        }
        $this->entry($member, '2026-11-11', 0, 'CO', confirmed: true);
        $this->entry($member, '2026-11-12', 0, 'CM');
        $path = app(ExcelService::class)->build($company, '2026-11');
        $book = IOFactory::load($path);
        try {
            $sheet = $book->getActiveSheet();
            $this->assertSame(['Pentru contabil', 'Centralizator', 'Intervale detaliate'], $book->getSheetNames());
            $this->assertSame('Pentru contabil', $sheet->getTitle());
            $this->assertSame('n', $sheet->getCell('Q6')->getDataType());
            $this->assertSame(7.5, $sheet->getCell('Q6')->getValue());
            $this->assertEqualsWithDelta(485 / 60, $sheet->getCell('R6')->getValue(), 0.000000001);
            $this->assertSame('0.00', $sheet->getStyle('R6')->getNumberFormat()->getFormatCode());
            $this->assertEqualsWithDelta(995 / 60, $sheet->getCell('C6')->getValue(), 0.000000001);
            $this->assertEqualsWithDelta(935 / 60, $sheet->getCell('D6')->getValue(), 0.000000001);
            $this->assertEquals(1, $sheet->getCell('E6')->getValue());
            $this->assertEquals(2, $sheet->getCell('F6')->getValue());
            foreach (['G', 'H', 'I', 'J'] as $column) {
                $this->assertEquals(1, $sheet->getCell($column.'6')->getValue());
                $this->assertSame('n', $sheet->getCell($column.'6')->getDataType());
            }
            $this->assertEquals(9, $sheet->getCell('K6')->getValue());
            $this->assertEquals(2, $sheet->getCell('L6')->getValue());
            $this->assertEquals(3, $sheet->getCell('M6')->getValue());
            $this->assertSame('De completat', $sheet->getCell('N6')->getValue());
            $this->assertSame('PROVIZORIU', $sheet->getCell('O6')->getValue());
            $this->assertSame('CO', $sheet->getCell('Z6')->getValue());
            $this->assertSame('FFFFE7AC', $sheet->getStyle('Z6')->getFill()->getStartColor()->getARGB());
            $this->assertStringContainsString('nu este inclusă', $sheet->getComment('Z6')->getText()->getPlainText());
            $this->assertSame('—', $sheet->getCell('P6')->getValue());
            $this->assertSame('?', $sheet->getCell('AB6')->getValue());
            $this->assertNull($sheet->getCell(Coordinate::stringFromColumnIndex(15 + 26).'6')->getValue());
            $this->assertEqualsWithDelta(995 / 60, $sheet->getCell('C7')->getValue(), 0.000000001);
            $this->assertEquals(450, $book->getSheetByName('Centralizator')->getCell('D6')->getValue());
            $details = $book->getSheetByName('Intervale detaliate');
            $this->assertEquals(450, $details->getCell('H2')->getValue());
            $this->assertNull($details->getCell('H3')->getValue());
            $this->assertEquals(480, $details->getCell('L2')->getValue());
            $this->assertEquals(-30, $details->getCell('L3')->getValue());
            $this->assertSame(DataType::TYPE_NUMERIC, $details->getCell('H2')->getDataType());
            if (getenv('IPONTAJ_EXPORT_SAMPLE') === '1') {
                // Opt-in, synthetic preview only; no source application database is read.
                copy($path, base_path('.runtime/ipontaj-pentru-contabil-exemplu.xlsx'));
            }
        } finally {
            $book->disconnectWorksheets();
            unlink($path);
        }
    }

    public function test_approved_month_is_visible_and_reopening_changes_the_next_export(): void
    {
        [$owner, $company, $member] = $this->team();
        $member->update(['joined_on' => '2026-11-25']);
        $this->entry($member, '2026-11-25', 480, approved: true);
        $closure = MonthClosure::create(['company_id' => $company->id, 'month' => '2026-11', 'approved_at' => now(), 'approved_by' => $owner->id]);
        foreach ([true, false] as $approved) {
            if (! $approved) {
                $closure->update(['approved_at' => null, 'approved_by' => null]);
            }
            $path = app(ExcelService::class)->build($company, '2026-11');
            $book = IOFactory::load($path);
            try {
                $status = $approved ? 'APROBAT' : 'PROVIZORIU';
                $this->assertStringStartsWith($status.' ·', $book->getActiveSheet()->getCell('A2')->getValue());
                $this->assertSame($status, $book->getActiveSheet()->getCell('O6')->getValue());
                $this->assertEquals(8, $book->getActiveSheet()->getCell('D6')->getValue());
            } finally {
                $book->disconnectWorksheets();
                unlink($path);
            }
        }
    }

    public function test_names_notes_and_unrecognized_absence_codes_cannot_become_spreadsheet_formulas(): void
    {
        [$owner, $company, $member] = $this->team();
        $company->update(['name' => '=HYPERLINK("https://example.test")']);
        $member->user->update(['first_name' => '=1+1', 'last_name' => '+CMD']);
        $entry = $this->entry($member, '2026-11-02', 0, '=SUM(1,1)', approved: true);
        $entry->update(['note' => '=WEBSERVICE("https://example.test")']);
        $path = app(ExcelService::class)->build($company, '2026-11');
        $book = IOFactory::load($path);
        try {
            $sheet = $book->getActiveSheet();
            foreach (['A1', 'A6', 'B6', 'Q6'] as $address) {
                $this->assertSame(DataType::TYPE_STRING, $sheet->getCell($address)->getDataType());
            }
            $this->assertSame('=SUM(1,1)', $sheet->getCell('Q6')->getValue());
            $this->assertEquals(1, $sheet->getCell('J6')->getValue());
            foreach ($book->getAllSheets() as $page) {
                foreach ($page->getCellCollection()->getCoordinates() as $address) {
                    $this->assertNotSame(DataType::TYPE_FORMULA, $page->getCell($address)->getDataType());
                }
            }
        } finally {
            $book->disconnectWorksheets();
            unlink($path);
        }
    }

    public function test_empty_leap_month_has_all_29_days_without_invented_work_or_absence(): void
    {
        $company = Company::create(['name' => 'Fără colegi']);
        $path = app(ExcelService::class)->build($company, '2028-02');
        $book = IOFactory::load($path);
        try {
            $sheet = $book->getActiveSheet();
            $this->assertSame('29 (h)', $sheet->getCell('AR5')->getValue());
            $this->assertEquals(0, $sheet->getCell('C6')->getValue());
            $this->assertEquals(0, $sheet->getCell('G6')->getValue());
            $this->assertEquals(0, $sheet->getCell('AR6')->getValue());
        } finally {
            $book->disconnectWorksheets();
            unlink($path);
        }
    }

    public function test_clock_change_preserves_real_minutes_despite_identical_local_clock_times(): void
    {
        [$owner, $company, $member] = $this->team();
        $member->update(['joined_on' => '2026-10-01']);
        $entry = $this->entry($member, '2026-10-25', 60, confirmed: true);
        $entry->intervals()->create(['kind' => 'work', 'start_at' => Carbon::parse('2026-10-25T03:30:00+03:00')->utc(), 'end_at' => Carbon::parse('2026-10-25T03:30:00+02:00')->utc()]);
        $path = app(ExcelService::class)->build($company, '2026-10');
        $book = IOFactory::load($path);
        try {
            $this->assertEquals(1, $book->getActiveSheet()->getCell(Coordinate::stringFromColumnIndex(15 + 25).'6')->getValue());
            $this->assertEquals(60, $book->getSheetByName('Intervale detaliate')->getCell('L2')->getValue());
            $this->assertStringEndsWith('+03:00', $book->getSheetByName('Intervale detaliate')->getCell('F2')->getValue());
            $this->assertStringEndsWith('+02:00', $book->getSheetByName('Intervale detaliate')->getCell('G2')->getValue());
        } finally {
            $book->disconnectWorksheets();
            unlink($path);
        }
    }

    public function test_team_totals_use_minutes_instead_of_adding_rounded_display_hours(): void
    {
        [$owner, $company, $member] = $this->team();
        $second = $company->memberships()->create(['user_id' => User::factory()->create()->id, 'joined_on' => '2026-11-01']);
        $this->entry($member, '2026-11-02', 1, confirmed: true);
        $this->entry($second, '2026-11-02', 1);
        $this->entry($member, '2026-11-03', 0, 'CO', approved: true);
        $this->entry($second, '2026-11-03', 0, 'CO', approved: true);
        $path = app(ExcelService::class)->build($company, '2026-11');
        $book = IOFactory::load($path);
        try {
            $sheet = $book->getActiveSheet();
            $this->assertEqualsWithDelta(2 / 60, $sheet->getCell('C8')->getValue(), 0.000000001);
            $this->assertEqualsWithDelta(1 / 60, $sheet->getCell('D8')->getValue(), 0.000000001);
            $this->assertEqualsWithDelta(1 / 60, $sheet->getCell('E8')->getValue(), 0.000000001);
            $this->assertEqualsWithDelta(2 / 60, $sheet->getCell('Q8')->getValue(), 0.000000001);
            $this->assertEquals(2, $sheet->getCell('G8')->getValue());
            $this->assertEquals(1, $sheet->getCell('F8')->getValue());
        } finally {
            $book->disconnectWorksheets();
            unlink($path);
        }
    }

    public function test_overnight_shift_keeps_its_start_month_and_exact_end_date_in_details(): void
    {
        [$owner, $company, $member] = $this->team();
        $this->travelTo(Carbon::parse('2026-12-02 12:00', $company->timezone));
        $entry = $this->entry($member, '2026-11-30', 480, confirmed: true);
        $entry->intervals()->create(['kind' => 'work', 'start_at' => Carbon::parse('2026-11-30T22:00:00+02:00')->utc(), 'end_at' => Carbon::parse('2026-12-01T06:00:00+02:00')->utc()]);
        foreach (['2026-11' => 8, '2026-12' => 0] as $month => $hours) {
            $path = app(ExcelService::class)->build($company, $month);
            $book = IOFactory::load($path);
            try {
                $this->assertEquals($hours, $book->getActiveSheet()->getCell('D6')->getValue());
                if ($hours) {
                    $this->assertSame('01.12.2026 06:00 +02:00', $book->getSheetByName('Intervale detaliate')->getCell('G2')->getValue());
                }
            } finally {
                $book->disconnectWorksheets();
                unlink($path);
            }
        }
    }
}
