<?php

namespace App\Services;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SchedulerSheetParser
{
    private const PERIOD_MAP = [
        'matin' => 'morning',
        'midi' => 'afternoon',
        'soir' => 'evening',
    ];

    public function parse(string $absolutePath, int $startYear): array
    {
        ini_set('memory_limit', '512M');

        // Lecteurs restreints aux formats annoncés par la validation d'upload :
        // sans cela, IOFactory retombe sur son lecteur CSV, qui « lit » n'importe
        // quel fichier et produit un planning vide au lieu d'une erreur franche.
        $spreadsheet = IOFactory::load($absolutePath, 0, [IOFactory::READER_XLSX, IOFactory::READER_XLS]);
        $allAttributions = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $allAttributions = array_merge(
                $allAttributions,
                $this->parseSheet($sheet, $startYear)
            );
        }

        return $allAttributions;
    }

    private function parseSheet(Worksheet $sheet, int $startYear): array
    {
        $attributions = [];
        $highestRow = $sheet->getHighestRow();
        $highestColIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($currentRow = 1; $currentRow <= $highestRow; $currentRow++) {
            $header = $this->findHeaderInRow($sheet, $currentRow, $highestColIndex);

            if ($header) {
                $datesMap = $this->mapDates($sheet, $currentRow + 1, $header['colIndex'], $highestColIndex, $startYear);
                $dataStartRow = $currentRow + 3;

                $result = $this->parseDataBlock($sheet, $dataStartRow, $header, $datesMap, $highestColIndex);
                $attributions = array_merge($attributions, $result['data']);

                $currentRow = $result['lastRow'];
            }
        }

        return $attributions;
    }

    private function findHeaderInRow(Worksheet $sheet, int $row, int $maxCol): ?array
    {
        for ($c = 1; $c <= $maxCol; $c++) {
            $val = (string) $sheet->getCell([$c, $row])->getValue();
            $normalized = $this->normalize($val);

            foreach (self::PERIOD_MAP as $keyword => $periodKey) {
                if (str_contains($normalized, $keyword)) {
                    return [
                        'period' => $periodKey,
                        'colIndex' => $c,
                    ];
                }
            }
        }

        return null;
    }

    private function parseDataBlock(Worksheet $sheet, int $startRow, array $header, array $datesMap, int $maxCol): array
    {
        $data = [];
        $currentRow = $startRow;
        $localCol = $header['colIndex'] - 1;

        while ($currentRow <= $sheet->getHighestRow()) {
            $cellLocal = $sheet->getCell([$localCol, $currentRow]);
            $rawLocal = trim((string) $cellLocal->getValue());

            if (empty($rawLocal)) {
                $nextValue = trim((string) $sheet->getCell([$localCol, $currentRow + 1])->getValue());
                if (empty($nextValue)) {
                    break;
                }
                $currentRow++;

                continue;
            }

            $localValue = trim(explode("\n", $rawLocal)[0]);

            $step = 1;
            if ($cellLocal->isInMergeRange()) {
                $range = Coordinate::splitRange($cellLocal->getMergeRange());
                $endCell = $range[0][1];
                $step = (Coordinate::coordinateFromString($endCell)[1] - $currentRow) + 1;
            }

            for ($c = $header['colIndex']; $c <= $maxCol; $c++) {
                $course = trim((string) $sheet->getCell([$c, $currentRow])->getValue());
                if (! empty($course) && isset($datesMap[$c])) {
                    $data[] = [
                        'date' => $datesMap[$c],
                        'period' => $header['period'], // "morning", "afternoon" ou "evening"
                        'local' => $localValue,
                        'course' => $course,
                    ];
                }
            }
            $currentRow += $step;
        }

        return ['data' => $data, 'lastRow' => $currentRow];
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');

        return str_replace(['é', 'è', 'ê', 'ë'], 'e', $value);
    }

    private function mapDates(Worksheet $sheet, int $row, int $startCol, int $maxCol, int $year): array
    {
        $map = [];
        $currentDate = null;

        for ($c = $startCol; $c <= $maxCol; $c++) {
            $val = $sheet->getCell([$c, $row])->getCalculatedValue();
            if (empty($val)) {
                continue;
            }

            if (! $currentDate) {
                $currentDate = Carbon::createFromFormat('d/m/Y', "{$val}/{$year}")->startOfDay();
            } else {
                $currentDate->addWeek();
            }
            $map[$c] = $currentDate->toDateString();
        }

        return $map;
    }
}
