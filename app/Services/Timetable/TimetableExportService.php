<?php

namespace App\Services\Timetable;

use App\Models\AcademicClass;
use App\Models\AcademicSection;
use App\Models\AcademicSession;
use App\Models\Institute;
use App\Models\TimetableEntry;
use App\Models\TimetableTimeSlot;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimetableExportService
{
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

    /**
     * Export timetable as HTML view suitable for direct browser rendering & PDF printing.
     */
    public function renderHtml(
        Institute $institute,
        AcademicSession $session,
        string $type,
        ?AcademicClass $class = null,
        ?AcademicSection $section = null,
        ?User $teacher = null,
        string $template = 'classic_grid'
    ): string {
        $slots = TimetableTimeSlot::query()
            ->where('institute_id', $institute->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $entriesQuery = TimetableEntry::query()
            ->where('session_id', $session->id)
            ->with(['academicClass', 'section', 'subject', 'teacher', 'timeSlot']);

        if ($type === 'class' && $class !== null) {
            $entriesQuery->where('class_id', $class->id);
            if ($section !== null) {
                $entriesQuery->where('section_id', $section->id);
            }
        } elseif ($type === 'teacher' && $teacher !== null) {
            $entriesQuery->where('teacher_user_id', $teacher->id);
        }

        $entries = $entriesQuery->get();

        // Matrix structure: grid[day][slot_id] = entry
        $grid = [];
        foreach ($entries as $entry) {
            $grid[$entry->day_of_week][$entry->time_slot_id] = $entry;
        }

        $title = match ($type) {
            'class' => 'Class Timetable: '.($class->name ?? '').($section ? ' - '.$section->name : ''),
            'teacher' => 'Teacher Schedule: '.($teacher->name ?? ''),
            default => 'Institute Master Timetable',
        };

        return match ($template) {
            'compact_card' => $this->htmlCompactCard($institute, $session, $title, $slots, $grid, $type),
            'teacher_schedule' => $this->htmlTeacherSchedule($institute, $session, $title, $slots, $entries),
            default => $this->htmlClassicGrid($institute, $session, $title, $slots, $grid, $type),
        };
    }

    /**
     * Export timetable as Excel spreadsheet (.xlsx).
     */
    public function exportExcel(
        Institute $institute,
        AcademicSession $session,
        string $type,
        ?AcademicClass $class = null,
        ?AcademicSection $section = null,
        ?User $teacher = null
    ): StreamedResponse {
        $slots = TimetableTimeSlot::query()
            ->where('institute_id', $institute->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $entriesQuery = TimetableEntry::query()
            ->where('session_id', $session->id)
            ->with(['academicClass', 'section', 'subject', 'teacher', 'timeSlot']);

        if ($type === 'class' && $class !== null) {
            $entriesQuery->where('class_id', $class->id);
            if ($section !== null) {
                $entriesQuery->where('section_id', $section->id);
            }
        } elseif ($type === 'teacher' && $teacher !== null) {
            $entriesQuery->where('teacher_user_id', $teacher->id);
        }

        $entries = $entriesQuery->get();
        $grid = [];
        foreach ($entries as $entry) {
            $grid[$entry->day_of_week][$entry->time_slot_id] = $entry;
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Timetable');

        // Header Title
        $title = $institute->name.' - Timetable ('.$session->name.')';
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $subtitle = match ($type) {
            'class' => 'Class: '.($class->name ?? '').($section ? ' - '.$section->name : ''),
            'teacher' => 'Teacher: '.($teacher->name ?? ''),
            default => 'Master Timetable',
        };
        $sheet->setCellValue('A2', $subtitle);
        $sheet->mergeCells('A2:G2');
        $sheet->getStyle('A2')->getFont()->setSize(11)->setItalic(true);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Column Headers (Days)
        $sheet->setCellValue('A4', 'Time Slot / Period');
        $colIndex = 2; // Column B
        foreach (self::DAYS as $day) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValue($colLetter.'4', ucfirst($day));
            $colIndex++;
        }

        $sheet->getStyle('A4:G4')->getFont()->setBold(true);
        $sheet->getStyle('A4:G4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');

        $rowIndex = 5;
        foreach ($slots as $slot) {
            $sheet->setCellValue('A'.$rowIndex, $slot->name."\n(".$slot->start_time.' - '.$slot->end_time.')');

            if ($slot->is_break) {
                $sheet->mergeCells('B'.$rowIndex.':G'.$rowIndex);
                $sheet->setCellValue('B'.$rowIndex, '--- '.$slot->name.' ---');
                $sheet->getStyle('A'.$rowIndex.':G'.$rowIndex)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');
                $sheet->getStyle('B'.$rowIndex)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            } else {
                $cIndex = 2;
                foreach (self::DAYS as $day) {
                    $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIndex);
                    $entry = $grid[$day][$slot->id] ?? null;

                    if ($entry !== null) {
                        $cellText = $type === 'teacher'
                            ? ($entry->subject?->name ?? 'Subject')."\n[".($entry->academicClass?->name ?? '').($entry->section ? ' - '.$entry->section->name : '').']'
                            : ($entry->subject?->name ?? 'Subject')."\n(".($entry->teacher?->name ?? 'Teacher').')';
                        $sheet->setCellValue($cLetter.$rowIndex, $cellText);
                    } else {
                        $sheet->setCellValue($cLetter.$rowIndex, '-');
                    }
                    $cIndex++;
                }
            }
            $rowIndex++;
        }

        // Apply grid styling
        $lastRow = $rowIndex - 1;
        $sheet->getStyle('A4:G'.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A4:G'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A4:G'.$lastRow)->getAlignment()->setWrapText(true);

        foreach (range(1, 7) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setWidth(22);
        }

        $filename = 'Timetable_'.date('Ymd_His').'.xlsx';

        return new StreamedResponse(
            function () use ($spreadsheet) {
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            },
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'max-age=0',
            ]
        );
    }

    private function htmlClassicGrid(Institute $institute, AcademicSession $session, string $title, $slots, array $grid, string $type): string
    {
        $days = self::DAYS;

        $rowsHtml = '';
        foreach ($slots as $slot) {
            $slotHeader = htmlspecialchars($slot->name).'<br><span style="font-size:11px;color:#64748b;">'.
                substr($slot->start_time, 0, 5).' - '.substr($slot->end_time, 0, 5).'</span>';

            if ($slot->is_break) {
                $rowsHtml .= '<tr style="background-color:#fef3c7;font-weight:600;text-align:center;">';
                $rowsHtml .= '<td style="font-weight:600;background-color:#f8fafc;">'.$slotHeader.'</td>';
                $rowsHtml .= '<td colspan="'.count($days).'" style="color:#b45309;padding:12px;letter-spacing:1px;">&#9208; '.htmlspecialchars($slot->name).'</td>';
                $rowsHtml .= '</tr>';
            } else {
                $rowsHtml .= '<tr>';
                $rowsHtml .= '<td style="font-weight:600;background-color:#f8fafc;padding:10px;">'.$slotHeader.'</td>';
                foreach ($days as $day) {
                    $entry = $grid[$day][$slot->id] ?? null;
                    if ($entry !== null) {
                        $subjectName = htmlspecialchars($entry->subject?->name ?? 'Subject');
                        $subMeta = $type === 'teacher'
                            ? '<span style="font-size:11.5px;color:#2563eb;font-weight:500;">Class: '.htmlspecialchars($entry->academicClass?->name ?? '').($entry->section ? ' - '.htmlspecialchars($entry->section->name) : '').'</span>'
                            : '<span style="font-size:11.5px;color:#475569;">'.htmlspecialchars($entry->teacher?->name ?? 'Teacher').'</span>';

                        $rowsHtml .= '<td style="padding:10px;background-color:#f0f9ff;border:1px solid #cbd5e1;">'.
                            '<div style="font-weight:700;color:#0f172a;margin-bottom:3px;">'.$subjectName.'</div>'.
                            $subMeta.
                            '</td>';
                    } else {
                        $rowsHtml .= '<td style="padding:10px;text-align:center;color:#cbd5e1;">-</td>';
                    }
                }
                $rowsHtml .= '</tr>';
            }
        }

        $dayHeaders = '';
        foreach ($days as $day) {
            $dayHeaders .= '<th style="padding:12px;background-color:#1e293b;color:#ffffff;font-size:13px;text-transform:uppercase;">'.ucfirst($day).'</th>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$title}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; padding: 30px; background: #ffffff; color: #1e293b; }
        .print-btn { display: inline-block; background: #2563eb; color: #ffffff; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; cursor: pointer; border: none; margin-bottom: 20px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0284c7; padding-bottom: 15px; margin-bottom: 20px; }
        h1 { font-size: 22px; color: #0f172a; margin-bottom: 4px; }
        .subtitle { font-size: 14px; color: #64748b; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #cbd5e1; text-align: left; font-size: 12.5px; }
        @media print {
            body { padding: 0; }
            .print-btn { display: none; }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">🖨️ Print / Save as PDF</button>
    <div class="header">
        <div>
            <h1>{$institute->name}</h1>
            <div class="subtitle">{$title} &bull; Session: {$session->name}</div>
        </div>
        <div style="text-align: right; font-size: 12px; color: #64748b;">
            Generated: {$session->updated_at?->format('Y-m-d')}<br>
            Timetable Engine
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="padding:12px;background-color:#0f172a;color:#ffffff;width:15%;">Time Slot</th>
                {$dayHeaders}
            </tr>
        </thead>
        <tbody>
            {$rowsHtml}
        </tbody>
    </table>
</body>
</html>
HTML;
    }

    private function htmlCompactCard(Institute $institute, AcademicSession $session, string $title, $slots, array $grid, string $type): string
    {
        $days = self::DAYS;
        $cardsHtml = '';

        foreach ($days as $day) {
            $cardsHtml .= '<div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">';
            $cardsHtml .= '<h3 style="font-size:15px;color:#1e293b;border-bottom:2px solid #3b82f6;padding-bottom:6px;margin-bottom:12px;text-transform:uppercase;">'.ucfirst($day).'</h3>';
            $cardsHtml .= '<ul style="list-style:none;padding:0;margin:0;">';

            foreach ($slots as $slot) {
                if ($slot->is_break) {
                    $cardsHtml .= '<li style="padding:6px 10px;background:#fef3c7;border-radius:4px;margin-bottom:6px;font-size:12px;color:#b45309;font-weight:600;text-align:center;">☕ '.htmlspecialchars($slot->name).' ('.substr($slot->start_time, 0, 5).' - '.substr($slot->end_time, 0, 5).')</li>';
                } else {
                    $entry = $grid[$day][$slot->id] ?? null;
                    if ($entry !== null) {
                        $cardsHtml .= '<li style="padding:8px 10px;background:#f8fafc;border-left:3px solid #3b82f6;margin-bottom:6px;border-radius:0 4px 4px 0;">'.
                            '<div style="display:flex;justify-content:space-between;align-items:center;">'.
                            '<strong style="font-size:13px;color:#0f172a;">'.htmlspecialchars($entry->subject?->name ?? 'Subject').'</strong>'.
                            '<span style="font-size:11px;color:#64748b;">'.substr($slot->start_time, 0, 5).' - '.substr($slot->end_time, 0, 5).'</span>'.
                            '</div>'.
                            '<div style="font-size:11.5px;color:#475569;margin-top:2px;">'.htmlspecialchars($entry->teacher?->name ?? '').'</div>'.
                            '</li>';
                    }
                }
            }

            $cardsHtml .= '</ul></div>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$title}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; padding: 30px; background: #f8fafc; color: #1e293b; }
        .print-btn { display: inline-block; background: #2563eb; color: #ffffff; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; cursor: pointer; border: none; margin-bottom: 20px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #3b82f6; padding-bottom: 15px; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; }
        @media print { body { padding: 0; background: #fff; } .print-btn { display: none; } }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">🖨️ Print / Save as PDF</button>
    <div class="header">
        <div>
            <h1 style="font-size:20px;color:#0f172a;">{$institute->name}</h1>
            <div style="font-size:14px;color:#64748b;">{$title} &bull; Session: {$session->name}</div>
        </div>
    </div>
    <div class="grid">
        {$cardsHtml}
    </div>
</body>
</html>
HTML;
    }

    private function htmlTeacherSchedule(Institute $institute, AcademicSession $session, string $title, $slots, $entries): string
    {
        return $this->htmlClassicGrid($institute, $session, $title, $slots, [], 'teacher');
    }
}
