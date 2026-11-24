<?php

namespace App\Services;

use App\Models\Company;
use App\Models\MonthClosure;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
        $detail->fromArray(['Firmă', 'Nume', 'Prenume', 'Data', 'Tip', 'Început (fus firmă)', 'Sfârșit (fus firmă)', 'Minute nete zi', 'Confirmat de angajat', 'Aprobat de proprietar', 'Observații'], null, 'A1');
        $r = 2;
        foreach ($summary['rows'] as $row) {
            foreach ($row['days'] as $day) {
                $e = $day['entry'];
                if (! $e) {
                    continue;
                }
                $intervals = $e->intervals->isEmpty() ? [null] : $e->intervals;
                foreach ($intervals as $interval) {
                    $values = [$company->name, $row['membership']->user->last_name, $row['membership']->user->first_name, $day['date'], $interval ? ($interval->kind === 'break' ? 'Pauză' : 'Lucru') : (AttendanceService::ABSENCES[$e->absence] ?? ''), $interval?->start_at->setTimezone($company->timezone)->format('d.m.Y H:i P') ?? '', $interval?->end_at->setTimezone($company->timezone)->format('d.m.Y H:i P') ?? '', $e->minutes, $e->confirmed_at?->toIso8601String() ?? 'Neconfirmat', $e->approved_at?->toIso8601String() ?? 'Neaprobat', $e->note ?? ''];
                    foreach ($values as $c => $value) {
                        $detail->setCellValueExplicit([$c + 1, $r], (string) $value, DataType::TYPE_STRING);
                    }
                    $r++;
                }
            }
        }
        foreach ([$sheet, $detail] as $s) {
            $s->freezePane($s === $sheet ? 'C6' : 'D2');
            $s->getColumnDimension('A')->setWidth(35);
            $s->getStyle($s === $sheet ? 'A5:'.Coordinate::stringFromColumnIndex($summary['days'] + 5).'5' : 'A1:K1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $s->getStyle($s === $sheet ? 'A5:'.Coordinate::stringFromColumnIndex($summary['days'] + 5).'5' : 'A1:K1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF16775C');
        }
        $book->setActiveSheetIndex(0);
        $path = tempnam(sys_get_temp_dir(), 'ipontaj-');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }
}
