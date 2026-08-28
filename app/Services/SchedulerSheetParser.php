<?php

namespace App\Services;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SchedulerSheetParser
{
    /**
     * « samedi » en dernier : un en-tête « SAMEDI MATIN » doit rester attrapé par
     * « matin ». La feuille SAMEDI du planning réel n'a que « SAMEDI » en en-tête,
     * et les cours du samedi ont lieu le matin (8h30–13h).
     */
    private const PERIOD_MAP = [
        'matin' => 'morning',
        'midi' => 'afternoon',
        'soir' => 'evening',
        'samedi' => 'morning',
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

    /**
     * L'année du formulaire ne sert que de repli : quand une cellule de la ligne
     * de dates est une vraie date Excel, son année vient du fichier et prime.
     * Sans cela, un import du planning 2026-2027 lancé avec « 2025 » sélectionné
     * créait toutes les attributions un an dans le passé, sans aucune erreur
     * (constaté en production le 2026-08-28).
     */
    private function mapDates(Worksheet $sheet, int $row, int $startCol, int $maxCol, int $year): array
    {
        $columns = [];
        for ($c = $startCol; $c <= $maxCol; $c++) {
            $cell = $sheet->getCell([$c, $row]);
            $val = $cell->getCalculatedValue();
            if (empty($val)) {
                continue;
            }

            $anchor = is_numeric($val) && ExcelDate::isDateTime($cell)
                ? Carbon::instance(ExcelDate::excelToDateTimeObject((float) $val))->startOfDay()
                : null;

            $columns[] = ['col' => $c, 'anchor' => $anchor, 'raw' => $val];
        }

        $dates = [];
        $anchored = [];
        foreach ($columns as $i => $column) {
            if ($column['anchor']) {
                $dates[$i] = $column['anchor'];
                $anchored[$i] = true;
            } elseif ($i > 0) {
                $dates[$i] = $dates[$i - 1]->copy()->addWeek();
                $anchored[$i] = $anchored[$i - 1];
            } else {
                $dates[$i] = Carbon::createFromFormat('d/m/Y', "{$column['raw']}/{$year}")->startOfDay();
                $anchored[$i] = false;
            }
        }

        // Le planning réel commence par une cellule texte (« 24/08 ») suivie de
        // vraies dates : les colonnes texte qui précèdent une date ancrée sont
        // recalées dessus, à une semaine d'écart par colonne.
        for ($i = count($columns) - 2; $i >= 0; $i--) {
            if (! $anchored[$i] && $anchored[$i + 1]) {
                $dates[$i] = $dates[$i + 1]->copy()->subWeek();
                $anchored[$i] = true;
            }
        }

        $map = [];
        foreach ($columns as $i => $column) {
            $map[$column['col']] = $dates[$i]->toDateString();
        }

        return $map;
    }
}
