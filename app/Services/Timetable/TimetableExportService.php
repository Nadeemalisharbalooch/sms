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
     * Export timetable as HTML view suitable for direct browser rendering & multi-page PDF printing.
     * Each section is rendered on its own dedicated page (page-break per section).
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

        // 1. Build List of Scheduling Units (Pages) to render
        $pages = [];

        if ($type === 'class' && $class !== null) {
            if ($section !== null) {
                $pages[] = [
                    'title' => 'Class: ' . $class->name . ' (' . $section->name . ')',
                    'class' => $class,
                    'section' => $section,
                    'teacher' => null,
                ];
            } else {
                $sections = $class->sections()->where('is_active', true)->orderBy('name')->get();
                if ($sections->isNotEmpty()) {
                    foreach ($sections as $sec) {
                        $pages[] = [
                            'title' => 'Class: ' . $class->name . ' - ' . $sec->name,
                            'class' => $class,
                            'section' => $sec,
                            'teacher' => null,
                        ];
                    }
                } else {
                    $pages[] = [
                        'title' => 'Class: ' . $class->name,
                        'class' => $class,
                        'section' => null,
                        'teacher' => null,
                    ];
                }
            }
        } elseif ($type === 'teacher' && $teacher !== null) {
            $pages[] = [
                'title' => 'Teacher Schedule: ' . $teacher->name,
                'class' => null,
                'section' => null,
                'teacher' => $teacher,
            ];
        } else {
            // Master Timetable: Export each class & section on its own page
            $classes = AcademicClass::query()
                ->where('institute_id', $institute->id)
                ->where('is_active', true)
                ->with(['sections' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
                ->orderBy('display_order')
                ->get();

            foreach ($classes as $c) {
                if ($c->sections->isNotEmpty()) {
                    foreach ($c->sections as $sec) {
                        $pages[] = [
                            'title' => 'Class: ' . $c->name . ' - ' . $sec->name,
                            'class' => $c,
                            'section' => $sec,
                            'teacher' => null,
                        ];
                    }
                } else {
                    $pages[] = [
                        'title' => 'Class: ' . $c->name,
                        'class' => $c,
                        'section' => null,
                        'teacher' => null,
                    ];
                }
            }
        }

        if (empty($pages)) {
            $pages[] = [
                'title' => 'Timetable',
                'class' => $class,
                'section' => $section,
                'teacher' => $teacher,
            ];
        }

        // Fetch all relevant entries for this session
        $allEntries = TimetableEntry::query()
            ->where('session_id', $session->id)
            ->with(['academicClass', 'section', 'subject', 'teacher', 'timeSlot'])
            ->get();

        return $this->htmlMultiPageGrid($institute, $session, $slots, $pages, $allEntries, $type);
    }

    /**
     * Render Multi-Page HTML Grid with 1 Page per Section / Class.
     */
    private function htmlMultiPageGrid(
        Institute $institute,
        AcademicSession $session,
        $slots,
        array $pages,
        $allEntries,
        string $type
    ): string {
        $days = self::DAYS;
        $pagesHtml = '';

        foreach ($pages as $index => $pageInfo) {
            $pClass = $pageInfo['class'];
            $pSection = $pageInfo['section'];
            $pTeacher = $pageInfo['teacher'];
            $pTitle = $pageInfo['title'];

            // Filter entries for this specific page
            $pageEntries = $allEntries->filter(function ($entry) use ($pClass, $pSection, $pTeacher, $type) {
                if ($pTeacher !== null) {
                    return $entry->teacher_user_id === $pTeacher->id;
                }

                if ($pClass !== null) {
                    if ($entry->class_id !== $pClass->id) {
                        return false;
                    }
                    if ($pSection !== null) {
                        return $entry->section_id === $pSection->id;
                    }
                    return true;
                }

                return true;
            });

            // Build matrix grid[day][slot_id] = entry
            $grid = [];
            foreach ($pageEntries as $entry) {
                $grid[$entry->day_of_week][$entry->time_slot_id] = $entry;
            }

            // Generate Table Rows
            $rowsHtml = '';
            foreach ($slots as $slot) {
                $slotHeader = htmlspecialchars($slot->name) . '<br><span style="font-size:9.5px;color:#64748b;font-weight:normal;">' .
                    substr($slot->start_time, 0, 5) . ' - ' . substr($slot->end_time, 0, 5) . '</span>';

                if ($slot->is_break) {
                    $rowsHtml .= '<tr style="background-color:#fef3c7;font-weight:bold;text-align:center;">';
                    $rowsHtml .= '<td style="background-color:#f8fafc;font-weight:bold;padding:6px;width:14%;">' . $slotHeader . '</td>';
                    $rowsHtml .= '<td colspan="' . count($days) . '" style="color:#b45309;padding:8px;letter-spacing:1px;font-size:11px;">&#9208; ' . htmlspecialchars($slot->name) . ' (' . substr($slot->start_time, 0, 5) . ' - ' . substr($slot->end_time, 0, 5) . ')</td>';
                    $rowsHtml .= '</tr>';
                } else {
                    $rowsHtml .= '<tr>';
                    $rowsHtml .= '<td style="font-weight:bold;background-color:#f8fafc;padding:6px;width:14%;">' . $slotHeader . '</td>';

                    foreach ($days as $day) {
                        $entry = $grid[$day][$slot->id] ?? null;

                        if ($entry !== null) {
                            $subjectName = htmlspecialchars($entry->subject?->name ?? 'Subject');
                            $subMeta = ($type === 'teacher' || $pTeacher !== null)
                                ? '<div style="font-size:9.5px;color:#2563eb;font-weight:500;margin-top:2px;">' . htmlspecialchars($entry->academicClass?->name ?? '') . ($entry->section ? ' (' . htmlspecialchars($entry->section->name) . ')' : '') . '</div>'
                                : '<div style="font-size:9.5px;color:#475569;margin-top:2px;">' . htmlspecialchars($entry->teacher?->name ?? '') . '</div>';

                            $rowsHtml .= '<td style="padding:6px 4px;background-color:#f0f9ff;border:1px solid #cbd5e1;vertical-align:middle;text-align:center;">' .
                                '<div style="font-weight:bold;color:#0f172a;font-size:11px;">' . $subjectName . '</div>' .
                                $subMeta .
                                '</td>';
                        } else {
                            $rowsHtml .= '<td style="padding:6px 4px;text-align:center;color:#cbd5e1;font-size:11px;">-</td>';
                        }
                    }
                    $rowsHtml .= '</tr>';
                }
            }

            $dayHeaders = '';
            foreach ($days as $day) {
                $dayHeaders .= '<th style="padding:8px 4px;background-color:#1e293b;color:#ffffff;font-size:11px;text-transform:uppercase;text-align:center;">' . ucfirst($day) . '</th>';
            }

            $isLastPage = ($index === count($pages) - 1);
            $pageBreakClass = $isLastPage ? 'page last-page' : 'page';

            $pagesHtml .= <<<PAGE
    <div class="{$pageBreakClass}">
        <div class="header-container">
            <table class="header-table">
                <tr>
                    <td style="border:none;padding:0;text-align:left;vertical-align:top;">
                        <div class="institute-name">{$institute->name}</div>
                        <div class="schedule-title">{$pTitle} &bull; Session: {$session->name}</div>
                    </td>
                    <td style="border:none;padding:0;text-align:right;vertical-align:top;">
                        <div class="meta-badge">Page: {$institute->name}</div>
                        <div class="meta-date">Generated: {$session->updated_at?->format('Y-m-d')}</div>
                    </td>
                </tr>
            </table>
        </div>

        <table class="timetable-grid">
            <thead>
                <tr>
                    <th style="padding:8px;background-color:#0f172a;color:#ffffff;width:14%;text-align:center;">Period</th>
                    {$dayHeaders}
                </tr>
            </thead>
            <tbody>
                {$rowsHtml}
            </tbody>
        </table>
    </div>
PAGE;
        }

        $documentTitle = $institute->name . ' - Timetable';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$documentTitle}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm 8mm 8mm 8mm;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "DejaVu Sans", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #ffffff;
            color: #1e293b;
            font-size: 11px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .page {
            width: 100%;
            page-break-after: always;
            break-after: page;
            padding-bottom: 10px;
        }
        .page.last-page {
            page-break-after: avoid;
            break-after: avoid;
        }
        .header-container {
            margin-bottom: 8px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 6px;
        }
        .institute-name {
            font-size: 17px;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 0.3px;
        }
        .schedule-title {
            font-size: 12.5px;
            color: #2563eb;
            font-weight: bold;
            margin-top: 2px;
        }
        .meta-badge {
            font-size: 10px;
            color: #475569;
            font-weight: 600;
        }
        .meta-date {
            font-size: 9.5px;
            color: #64748b;
            margin-top: 2px;
        }
        table.timetable-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.timetable-grid th, table.timetable-grid td {
            border: 1px solid #cbd5e1;
            font-size: 10.5px;
            line-height: 1.25;
        }
        .print-btn {
            display: inline-block;
            background: #2563eb;
            color: #ffffff;
            padding: 8px 18px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            margin: 15px 0 15px 15px;
        }
        @media print {
            .print-btn { display: none !important; }
            body { padding: 0; background: #ffffff; }
            .page { padding-bottom: 0; }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">🖨️ Print / Save as PDF</button>
    {$pagesHtml}
</body>
</html>
HTML;
    }

    /**
     * Export timetable as Excel spreadsheet (.xlsx) with 1 Sheet / Tab per Section.
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

        $pages = [];

        if ($type === 'class' && $class !== null) {
            if ($section !== null) {
                $pages[] = ['title' => $class->name . ' - ' . $section->name, 'class' => $class, 'section' => $section, 'teacher' => null];
            } else {
                $sections = $class->sections()->where('is_active', true)->orderBy('name')->get();
                if ($sections->isNotEmpty()) {
                    foreach ($sections as $sec) {
                        $pages[] = ['title' => $class->name . ' - ' . $sec->name, 'class' => $class, 'section' => $sec, 'teacher' => null];
                    }
                } else {
                    $pages[] = ['title' => $class->name, 'class' => $class, 'section' => null, 'teacher' => null];
                }
            }
        } elseif ($type === 'teacher' && $teacher !== null) {
            $pages[] = ['title' => $teacher->name, 'class' => null, 'section' => null, 'teacher' => $teacher];
        } else {
            $classes = AcademicClass::where('institute_id', $institute->id)->where('is_active', true)->with('sections')->get();
            foreach ($classes as $c) {
                if ($c->sections->isNotEmpty()) {
                    foreach ($c->sections as $sec) {
                        $pages[] = ['title' => $c->name . ' - ' . $sec->name, 'class' => $c, 'section' => $sec, 'teacher' => null];
                    }
                } else {
                    $pages[] = ['title' => $c->name, 'class' => $c, 'section' => null, 'teacher' => null];
                }
            }
        }

        $allEntries = TimetableEntry::query()
            ->where('session_id', $session->id)
            ->with(['academicClass', 'section', 'subject', 'teacher', 'timeSlot'])
            ->get();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0); // Remove default sheet

        foreach ($pages as $sheetIdx => $pageInfo) {
            $sheetTitle = substr(preg_replace('/[\/\\\\\?\*\:\[\]]/', '_', $pageInfo['title']), 0, 30);
            $sheet = $spreadsheet->createSheet($sheetIdx);
            $sheet->setTitle($sheetTitle ?: "Sheet{$sheetIdx}");

            // Filter entries for this sheet
            $pClass = $pageInfo['class'];
            $pSection = $pageInfo['section'];
            $pTeacher = $pageInfo['teacher'];

            $pageEntries = $allEntries->filter(function ($entry) use ($pClass, $pSection, $pTeacher) {
                if ($pTeacher !== null) {
                    return $entry->teacher_user_id === $pTeacher->id;
                }
                if ($pClass !== null) {
                    if ($entry->class_id !== $pClass->id) return false;
                    if ($pSection !== null) return $entry->section_id === $pSection->id;
                    return true;
                }
                return true;
            });

            $grid = [];
            foreach ($pageEntries as $entry) {
                $grid[$entry->day_of_week][$entry->time_slot_id] = $entry;
            }

            // Header Title
            $title = $institute->name . ' - ' . $pageInfo['title'] . ' (' . $session->name . ')';
            $sheet->setCellValue('A1', $title);
            $sheet->mergeCells('A1:G1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Column Headers (Days)
            $sheet->setCellValue('A3', 'Time Slot / Period');
            $colIndex = 2;
            foreach (self::DAYS as $day) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $sheet->setCellValue($colLetter . '3', ucfirst($day));
                $colIndex++;
            }

            $sheet->getStyle('A3:G3')->getFont()->setBold(true);
            $sheet->getStyle('A3:G3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E293B');
            $sheet->getStyle('A3:G3')->getFont()->getColor()->setRGB('FFFFFF');

            $rowIndex = 4;
            foreach ($slots as $slot) {
                $sheet->setCellValue('A' . $rowIndex, $slot->name . "\n(" . substr($slot->start_time, 0, 5) . ' - ' . substr($slot->end_time, 0, 5) . ')');

                if ($slot->is_break) {
                    $sheet->mergeCells('B' . $rowIndex . ':G' . $rowIndex);
                    $sheet->setCellValue('B' . $rowIndex, '--- ' . $slot->name . ' ---');
                    $sheet->getStyle('A' . $rowIndex . ':G' . $rowIndex)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');
                    $sheet->getStyle('B' . $rowIndex)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                } else {
                    $cIndex = 2;
                    foreach (self::DAYS as $day) {
                        $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIndex);
                        $entry = $grid[$day][$slot->id] ?? null;

                        if ($entry !== null) {
                            $cellText = ($pTeacher !== null)
                                ? ($entry->subject?->name ?? 'Subject') . "\n[" . ($entry->academicClass?->name ?? '') . ($entry->section ? ' - ' . $entry->section->name : '') . ']'
                                : ($entry->subject?->name ?? 'Subject') . "\n(" . ($entry->teacher?->name ?? 'Teacher') . ')';
                            $sheet->setCellValue($cLetter . $rowIndex, $cellText);
                        } else {
                            $sheet->setCellValue($cLetter . $rowIndex, '-');
                        }
                        $cIndex++;
                    }
                }
                $rowIndex++;
            }

            $lastRow = $rowIndex - 1;
            $sheet->getStyle('A3:G' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle('A3:G' . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle('A3:G' . $lastRow)->getAlignment()->setWrapText(true);

            foreach (range(1, 7) as $col) {
                $sheet->getColumnDimensionByColumn($col)->setWidth(20);
            }
        }

        $filename = 'Timetable_' . date('Ymd_His') . '.xlsx';

        return new StreamedResponse(
            function () use ($spreadsheet) {
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            },
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'max-age=0',
            ]
        );
    }
}
