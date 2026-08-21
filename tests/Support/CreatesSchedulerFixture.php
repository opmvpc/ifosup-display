<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Génère des classeurs Excel de test par code (via PhpSpreadsheet), au format attendu
 * par App\Services\SchedulerSheetParser, plutôt que de committer des fichiers .xlsx
 * binaires opaques : le contenu reste lisible et auditable dans le diff.
 */
trait CreatesSchedulerFixture
{
    public function newWorkbook(): Spreadsheet
    {
        return new Spreadsheet;
    }

    /**
     * Remplit une feuille à partir d'une grille [ligne (1-based) => [colonne (1-based) => valeur]].
     */
    public function fillGrid(Worksheet $sheet, array $grid): void
    {
        foreach ($grid as $row => $columns) {
            foreach ($columns as $col => $value) {
                if ($value === null) {
                    continue;
                }

                $sheet->setCellValue([$col, $row], $value);
            }
        }
    }

    /**
     * Fusionne une colonne donnée sur une plage de lignes (pour simuler une cellule
     * "local" fusionnée sur plusieurs lignes).
     */
    public function mergeRange(Worksheet $sheet, int $col, int $fromRow, int $toRow): void
    {
        $letter = Coordinate::stringFromColumnIndex($col);
        $sheet->mergeCells("{$letter}{$fromRow}:{$letter}{$toRow}");
    }

    public function saveWorkbook(Spreadsheet $spreadsheet, string $filename = 'planning.xlsx'): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('scheduler-fixture-', true).'-'.$filename;

        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function workbookToUploadedFile(Spreadsheet $spreadsheet, string $filename = 'planning.xlsx'): UploadedFile
    {
        $path = $this->saveWorkbook($spreadsheet, $filename);

        return new UploadedFile(
            $path,
            $filename,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }

    /**
     * Classeur "planning par défaut" utilisé par les tests Feature d'import (upload,
     * preview, execute). Un seul onglet "Planning", en-tête en colonne C (index 3),
     * colonne locale en colonne B (C-1), dataStartRow = header+3 :
     *
     * - Bloc "Matin" (ligne 1) : 2 dates (08/09 et 15/09), 2 salles
     *   ("Salle 101" avec MATH101 sur les 2 dates, "Salle 102" avec INFO101 sur la 1ère
     *   date seulement).
     * - Bloc "Midi" (ligne 9) : 1 date (08/09), "Salle 101" avec ANGL201.
     *
     * Voir defaultPlanningEntries() pour les 4 entrées exactes attendues au parsing.
     */
    public function defaultPlanningWorkbook(int $startYear = 2025): Spreadsheet
    {
        $spreadsheet = $this->newWorkbook();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Planning');

        $this->fillGrid($sheet, [
            1 => [3 => 'Matin'],
            2 => [3 => '08/09', 4 => '15/09'],
            4 => [2 => 'Salle 101', 3 => 'MATH101', 4 => 'MATH101'],
            5 => [2 => 'Salle 102', 3 => 'INFO101'],
            9 => [3 => 'Midi'],
            10 => [3 => '08/09'],
            12 => [2 => 'Salle 101', 3 => 'ANGL201'],
        ]);

        return $spreadsheet;
    }

    /**
     * Entrées exactes retournées par SchedulerSheetParser::parse() sur le classeur
     * produit par defaultPlanningWorkbook(2025).
     */
    public function defaultPlanningEntries(): array
    {
        return [
            ['date' => '2025-09-08', 'period' => 'morning', 'local' => 'Salle 101', 'course' => 'MATH101'],
            ['date' => '2025-09-15', 'period' => 'morning', 'local' => 'Salle 101', 'course' => 'MATH101'],
            ['date' => '2025-09-08', 'period' => 'morning', 'local' => 'Salle 102', 'course' => 'INFO101'],
            ['date' => '2025-09-08', 'period' => 'afternoon', 'local' => 'Salle 101', 'course' => 'ANGL201'],
        ];
    }
}
