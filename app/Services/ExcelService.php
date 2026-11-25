<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Membership;
use App\Models\MonthClosure;
use App\Models\TimeEntry;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * @phpstan-type ExportDay array{date:string,planned:int,missing:bool,entry:TimeEntry|null}
 * @phpstan-type ExportRow array{membership:Membership,days:list<ExportDay>,planned:int,confirmed:int,missing:int,pending:int}
 */
class ExcelService
{
    public function build(Company $company, string $month): string
    {
        $summary = app(AttendanceService::class)->summary($company, $month);
        $approved = MonthClosure::where('company_id', $company->id)->where('month', $month)->whereNotNull('approved_at')->exists();
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Centralizator');
        $meta = [$company->name.($company->cif ? ' · CIF '.$company->cif : '').' · '.$month, ($approved ? 'APROBAT' : 'PROVIZORIU — datele incomplete sunt marcate cu ?').' · '.$company->timezone.' · '.now($company->timezone)->format('d.m.Y H:i'), 'Pontaj pentru verificare și prelucrare de către contabil. Durate în minute; totaluri în ore.'];
        foreach ($meta as $i => $value) {
            $sheet->setCellValueExplicit([1, $i + 1], $value, DataType::TYPE_STRING);
        }
        $headers = ['Nume', 'Prenume'];
        for ($d = 1; $d <= $summary['days']; $d++) {
            $headers[] = (string) $d;
        }
        $headers = array_merge($headers, ['Total ore confirmate/aprobate', 'Ore planificate', 'Diferență ore (de verificat)']);
        $sheet->fromArray($headers, null, 'A5');
        $r = 6;
        foreach ($summary['rows'] as $row) {
            $sheet->setCellValueExplicit([1, $r], $row['membership']->user->last_name, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([2, $r], $row['membership']->user->first_name, DataType::TYPE_STRING);
            foreach ($row['days'] as $i => $day) {
                $e = $day['entry'];
                $value = $e ? ($e->absence ?: $e->minutes) : ($day['missing'] ? '?' : '—');
                $sheet->setCellValueExplicit([$i + 3, $r], (string) $value, DataType::TYPE_STRING);
                if ($day['missing'] || ($e && ! $e->approved_at && ($e->absence || ! $e->confirmed_at))) {
                    $sheet->getStyle([$i + 3, $r])->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFE7AC');
                }
            }
            $sheet->fromArray([$row['confirmed'] / 60, $row['planned'] / 60, ($row['confirmed'] - $row['planned']) / 60], null, Coordinate::stringFromColumnIndex($summary['days'] + 3).$r, true);
            $r++;
        }
        $sheet->setCellValueExplicit([1, $r + 2], 'Legendă: valori zilnice = minute; ? = neconfirmat; — = fără înregistrare; CO = concediu de odihnă; CM = medical; CFP = fără plată; A = altă absență.', DataType::TYPE_STRING);
        $detail = $book->createSheet()->setTitle('Intervale detaliate');
        $detail->fromArray(['Firmă', 'Nume', 'Prenume', 'Data', 'Tip', 'Început (fus firmă)', 'Sfârșit (fus firmă)', 'Minute nete zi (prima linie)', 'Confirmat de angajat', 'Aprobat de proprietar', 'Observații', 'Minute interval (+lucru / −pauză)'], null, 'A1');
        $r = 2;
        foreach ($summary['rows'] as $row) {
            foreach ($row['days'] as $day) {
                $e = $day['entry'];
                if (! $e) {
                    continue;
                }
                $intervals = $e->intervals->isEmpty() ? [null] : $e->intervals;
                $firstInterval = true;
                foreach ($intervals as $interval) {
                    $values = [$company->name, $row['membership']->user->last_name, $row['membership']->user->first_name, $day['date'], $interval ? ($interval->kind === 'break' ? 'Pauză' : 'Lucru') : (AttendanceService::ABSENCES[$e->absence] ?? ''), $interval?->start_at->setTimezone($company->timezone)->format('d.m.Y H:i P') ?? '', $interval?->end_at->setTimezone($company->timezone)->format('d.m.Y H:i P') ?? '', $firstInterval ? $e->minutes : null, $e->confirmed_at?->toIso8601String() ?? ($row['membership']->managed ? 'Fără cont — pontaj de responsabil' : 'Neconfirmat'), $e->approved_at?->toIso8601String() ?? 'Neaprobat', $e->note ?? ''];
                    foreach ($values as $c => $value) {
                        $numeric = $c === 7 && $value !== null;
                        $detail->setCellValueExplicit([$c + 1, $r], $numeric ? $value : (string) $value, $numeric ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
                    }
                    $intervalMinutes = $interval ? (int) $interval->start_at->diffInMinutes($interval->end_at) * ($interval->kind === 'break' ? -1 : 1) : 0;
                    $detail->setCellValueExplicit([12, $r], $intervalMinutes, DataType::TYPE_NUMERIC);
                    $firstInterval = false;
                    $r++;
                }
            }
        }
        foreach ([$sheet, $detail] as $s) {
            $s->freezePane($s === $sheet ? 'C6' : 'D2');
            $s->getColumnDimension('A')->setWidth(35);
            $s->getStyle($s === $sheet ? 'A5:'.Coordinate::stringFromColumnIndex($summary['days'] + 5).'5' : 'A1:L1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $s->getStyle($s === $sheet ? 'A5:'.Coordinate::stringFromColumnIndex($summary['days'] + 5).'5' : 'A1:L1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF16775C');
        }
        foreach (['B' => 25, 'C' => 22, 'D' => 13, 'E' => 22, 'F' => 26, 'G' => 26, 'H' => 18, 'I' => 30, 'J' => 30, 'K' => 45, 'L' => 24] as $column => $width) {
            $detail->getColumnDimension($column)->setWidth($width);
        }
        $detail->getStyle('A1:L1')->getAlignment()->setWrapText(true)->setVertical('center');
        $detail->getRowDimension(1)->setRowHeight(42);
        $detail->setAutoFilter('A1:L'.max(1, $r - 1));
        $this->addAccountantSheet($book, $company, $month, $summary['rows'], $summary['days'], $approved);
        $book->setActiveSheetIndex(0);
        $path = tempnam(sys_get_temp_dir(), 'ipontaj-');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }

    /** @param list<ExportRow> $rows */
    private function addAccountantSheet(Spreadsheet $book, Company $company, string $month, array $rows, int $days, bool $approved): void
    {
        $sheet = $book->createSheet(0)->setTitle('Pentru contabil');
        $status = $approved ? 'APROBAT' : 'PROVIZORIU';
        $headers = ['Nume', 'Prenume', 'Ore înregistrate (h)', 'Ore confirmate/aprobate (h)', 'Ore de verificat (h)', 'Zile lucrate confirmate/aprobate', 'CO aprobate (zile)', 'CM aprobate (zile)', 'CFP aprobate (zile)', 'Alte absențe aprobate (zile)', 'Zile lipsă', 'Zile înregistrate neconfirmate', 'Înregistrări de verificat', 'Statut pontaj', 'Statut lună'];
        for ($day = 1; $day <= $days; $day++) {
            $headers[] = $day.' (h)';
        }
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $metadata = [
            $company->name.($company->cif ? ' · CIF '.$company->cif : '').' · '.$month,
            $status.' · '.$company->timezone.' · Exportat la '.now($company->timezone)->format('d.m.Y H:i'),
            'Ore zecimale (h): 7,50 h = 7 h 30 min. Afișare cu 2 zecimale; valorile sunt calculate din minutele exacte.',
            'Orele înregistrate includ datele de verificat. Absențele sunt totalizate separat numai după aprobarea responsabilului.',
        ];
        foreach ($metadata as $index => $text) {
            $line = $index + 1;
            $sheet->mergeCells('A'.$line.':O'.$line);
            $sheet->setCellValueExplicit('A'.$line, $text, DataType::TYPE_STRING);
        }
        $sheet->getStyle('A1:O4')->getAlignment()->setWrapText(true);
        $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true);
        $sheet->getStyle('A2')->getFont()->setBold(true)->getColor()->setARGB($approved ? 'FF166547' : 'FF986113');
        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->getRowDimension(2)->setRowHeight(24);
        $sheet->getRowDimension(3)->setRowHeight(30);
        $sheet->getRowDimension(4)->setRowHeight(30);
        $sheet->fromArray($headers, null, 'A5');
        $sheet->getRowDimension(5)->setRowHeight(66);
        $sheet->getStyle('A5:'.$lastColumn.'5')->applyFromArray(['font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF16775C']], 'alignment' => ['wrapText' => true, 'vertical' => 'center']]);
        $totals = array_fill(0, 11, 0);
        $dailyTotals = array_fill(0, $days, 0);
        $line = 6;
        foreach ($rows as $row) {
            $member = $row['membership'];
            $sheet->setCellValueExplicit([1, $line], $member->user->last_name, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([2, $line], $member->user->first_name, DataType::TYPE_STRING);
            $minutes = $acceptedMinutes = $workedDays = $unconfirmed = $entryCount = 0;
            $absenceDays = ['CO' => 0, 'CM' => 0, 'CFP' => 0, 'other' => 0];
            foreach ($row['days'] as $index => $day) {
                $entry = $day['entry'];
                $cell = $sheet->getCell([$index + 16, $line]);
                $review = $day['missing'] || ($entry && ! $entry->approved_at && ($entry->absence || ! $entry->confirmed_at));
                if (! $entry) {
                    $cell->setValueExplicit($day['missing'] ? '?' : ($day['planned'] ? '' : '—'), DataType::TYPE_STRING);
                    $explanation = $day['missing'] ? 'Zi programată scadentă fără înregistrare.' : ($day['planned'] ? 'Zi programată viitoare, fără înregistrare.' : 'Zi fără ore planificate.');
                } else {
                    $entryCount++;
                    $accepted = $entry->confirmed_at !== null || $entry->approved_at !== null;
                    if (! $accepted) {
                        $unconfirmed++;
                    }
                    if ($entry->absence) {
                        $cell->setValueExplicit($entry->absence, DataType::TYPE_STRING);
                        if ($entry->approved_at) {
                            $absenceDays[array_key_exists($entry->absence, $absenceDays) ? $entry->absence : 'other']++;
                        }
                        $explanation = (AttendanceService::ABSENCES[$entry->absence] ?? $entry->absence).($entry->approved_at ? ' — aprobată.' : ' — de verificat; nu este inclusă în totalurile de absențe aprobate.');
                    } else {
                        $cell->setValueExplicit($entry->minutes / 60, DataType::TYPE_NUMERIC);
                        $minutes += $entry->minutes;
                        $dailyTotals[$index] += $entry->minutes;
                        if ($accepted) {
                            $acceptedMinutes += $entry->minutes;
                            $workedDays += $entry->minutes > 0 ? 1 : 0;
                        }
                        $explanation = $entry->minutes.' minute nete. '.($entry->approved_at ? 'Aprobată de responsabil.' : ($entry->confirmed_at ? 'Confirmată de angajat.' : 'Neconfirmată; orele sunt de verificat.'));
                    }
                }
                $sheet->getComment($cell->getCoordinate())->getText()->createTextRun($explanation);
                $cell->getStyle()->getNumberFormat()->setFormatCode('0.00');
                if ($review) {
                    $cell->getStyle()->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFE7AC');
                }
            }
            $values = [$minutes / 60, $acceptedMinutes / 60, ($minutes - $acceptedMinutes) / 60, $workedDays, $absenceDays['CO'], $absenceDays['CM'], $absenceDays['CFP'], $absenceDays['other'], $row['missing'], $unconfirmed, $row['pending']];
            foreach ($values as $index => $value) {
                $sheet->setCellValueExplicit([$index + 3, $line], $value, DataType::TYPE_NUMERIC);
                // Sum durations as integer minutes to keep totals independent of display rounding.
                $totals[$index] += $index < 3 ? [$minutes, $acceptedMinutes, $minutes - $acceptedMinutes][$index] : $value;
            }
            $rowStatus = $row['missing'] ? 'De completat' : ($row['pending'] ? 'De verificat' : ($entryCount ? 'La zi' : 'Fără înregistrări'));
            $sheet->setCellValueExplicit([14, $line], $rowStatus, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([15, $line], $status, DataType::TYPE_STRING);
            $sheet->getStyle('C'.$line.':E'.$line)->getNumberFormat()->setFormatCode('0.00');
            $sheet->getStyle('F'.$line.':M'.$line)->getNumberFormat()->setFormatCode('0');
            if ($row['missing'] || $row['pending']) {
                $sheet->getStyle('K'.$line.':N'.$line)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFE7AC');
            }
            $line++;
        }
        $sheet->setAutoFilter('A5:'.$lastColumn.max(5, $line - 1));
        $sheet->mergeCells('A'.$line.':B'.$line);
        $sheet->setCellValueExplicit('A'.$line, 'TOTAL echipă (zile = persoane-zile)', DataType::TYPE_STRING);
        foreach ($totals as $index => $value) {
            $sheet->setCellValueExplicit([$index + 3, $line], $index < 3 ? $value / 60 : $value, DataType::TYPE_NUMERIC);
        }
        foreach ($dailyTotals as $index => $value) {
            $sheet->setCellValueExplicit([$index + 16, $line], $value / 60, DataType::TYPE_NUMERIC);
        }
        $sheet->getStyle('C'.$line.':E'.$line)->getNumberFormat()->setFormatCode('0.00');
        $sheet->getStyle('P'.$line.':'.$lastColumn.$line)->getNumberFormat()->setFormatCode('0.00');
        $sheet->getStyle('A'.$line.':'.$lastColumn.$line)->getFont()->setBold(true);
        $sheet->getStyle('A'.$line.':'.$lastColumn.$line)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFEDF4EE');
        $notes = [
            'Legendă: ? = zi lipsă; — = fără program; gol = zi viitoare planificată. Codurile CO, CM, CFP și A sunt absențe, nu ore lucrate.',
            'Totalurile de absențe numără înregistrări zilnice aprobate, nu solduri de concediu, zile calendaristice indemnizabile sau drepturi salariale.',
            'Zile înregistrate neconfirmate = fără confirmarea angajatului și fără aprobarea responsabilului. Absențele neaprobate rămân de verificat.',
            'Pentru colegii fără cont, aprobarea responsabilului este distinctă de confirmarea angajatului. Consultă foaia Intervale detaliate.',
            'Turele de noapte sunt atribuite zilei de început. Detaliile păstrează datele/orele exacte și fusul orar; nu se defalcă automat la schimbarea lunii.',
            'Diferențele față de program nu reprezintă automat ore suplimentare sau drepturi salariale. Centralizator păstrează minutele zilnice originale.',
        ];
        foreach ($notes as $index => $text) {
            $r = $line + $index + 2;
            $sheet->mergeCells('A'.$r.':O'.$r);
            $sheet->setCellValueExplicit('A'.$r, $text, DataType::TYPE_STRING);
            $sheet->getStyle('A'.$r)->getAlignment()->setWrapText(true);
            $sheet->getRowDimension($r)->setRowHeight(28);
        }
        $sheet->freezePane('C6');
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(22);
        for ($column = 3; $column <= count($headers); $column++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth($column < 16 ? 14 : 7);
        }
        $sheet->getSheetView()->setZoomScale(85);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A3)->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd(1, 5)->setPrintArea('A1:'.$lastColumn.($line + count($notes) + 1));
        $sheet->getHeaderFooter()->setOddFooter('iPontaj · &P / &N');
    }
}
