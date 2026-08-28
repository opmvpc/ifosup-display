<?php

use App\Services\SchedulerSheetParser;
use Tests\Support\CreatesSchedulerFixture;

uses(CreatesSchedulerFixture::class);

function parseWorkbook(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, int $startYear = 2025): array
{
    $path = test()->saveWorkbook($spreadsheet);

    return (new SchedulerSheetParser)->parse($path, $startYear);
}

it('parse un bloc simple matin/midi/soir avec les bonnes dates, salles et cours', function () {
    $spreadsheet = test()->newWorkbook();
    $sheet = $spreadsheet->getActiveSheet();

    test()->fillGrid($sheet, [
        1 => [3 => 'Matin'],
        2 => [3 => '08/09'],
        4 => [2 => 'Salle 101', 3 => 'COURS-A'],
        5 => [2 => 'Salle 102', 3 => 'COURS-B'],
    ]);

    $result = parseWorkbook($spreadsheet);

    expect($result)->toBe([
        ['date' => '2025-09-08', 'period' => 'morning', 'local' => 'Salle 101', 'course' => 'COURS-A'],
        ['date' => '2025-09-08', 'period' => 'morning', 'local' => 'Salle 102', 'course' => 'COURS-B'],
    ]);
});

it('déduit la deuxième date du bloc par +7 jours et ignore le contenu de la cellule', function () {
    $spreadsheet = test()->newWorkbook();
    $sheet = $spreadsheet->getActiveSheet();

    test()->fillGrid($sheet, [
        1 => [3 => 'Matin'],
        2 => [3 => '08/09', 4 => '01/01'],
        4 => [2 => 'Salle 101', 3 => 'X', 4 => 'Y'],
    ]);

    $result = parseWorkbook($spreadsheet);

    expect($result)->toHaveCount(2);
    expect($result[1]['course'])->toBe('Y');
    expect($result[1]['date'])->toBe('2025-09-15');
});

it('ignore une colonne de la ligne de dates si elle est vide, sans décaler les semaines suivantes', function () {
    $spreadsheet = test()->newWorkbook();
    $sheet = $spreadsheet->getActiveSheet();

    test()->fillGrid($sheet, [
        1 => [3 => 'Matin'],
        2 => [3 => '08/09', 5 => '22/09'],
        4 => [2 => 'Salle 101', 3 => 'A', 4 => 'B', 5 => 'C'],
    ]);

    $result = parseWorkbook($spreadsheet);

    $courses = array_column($result, 'course');
    expect($courses)->not->toContain('B');
    expect($result)->toHaveCount(2);

    $entryC = collect($result)->firstWhere('course', 'C');
    expect($entryC['date'])->toBe('2025-09-15');
});

it('ne garde que la première ligne d\'un nom de salle multi-lignes', function () {
    $spreadsheet = test()->newWorkbook();
    $sheet = $spreadsheet->getActiveSheet();

    test()->fillGrid($sheet, [
        1 => [3 => 'Matin'],
        2 => [3 => '08/09'],
        4 => [2 => "Salle 101\nAnnexe", 3 => 'X'],
    ]);

    $result = parseWorkbook($spreadsheet);

    expect($result)->toHaveCount(1);
    expect($result[0]['local'])->toBe('Salle 101');
});

it('n\'importe que la première ligne d\'une cellule salle fusionnée sur plusieurs lignes et saute les lignes suivantes', function () {
    $spreadsheet = test()->newWorkbook();
    $sheet = $spreadsheet->getActiveSheet();

    test()->fillGrid($sheet, [
        1 => [3 => 'Matin'],
        2 => [3 => '08/09'],
        4 => [2 => 'Salle 101', 3 => 'A'],
        5 => [3 => 'B'],
        6 => [3 => 'C'],
    ]);
    test()->mergeRange($sheet, 2, 4, 6);

    $result = parseWorkbook($spreadsheet);

    expect($result)->toHaveCount(1);
    expect($result[0]['course'])->toBe('A');
    expect(array_column($result, 'course'))->not->toContain('B')->not->toContain('C');
});

it('arrête le bloc de données après deux lignes vides consécutives sur la colonne locale', function () {
    $spreadsheet = test()->newWorkbook();
    $sheet = $spreadsheet->getActiveSheet();

    test()->fillGrid($sheet, [
        1 => [3 => 'Matin'],
        2 => [3 => '08/09'],
        4 => [2 => 'Salle 101', 3 => 'A'],
        7 => [2 => 'Salle 999', 3 => 'Z'],
    ]);

    $result = parseWorkbook($spreadsheet);

    expect($result)->toHaveCount(1);
    expect($result[0]['local'])->toBe('Salle 101');
});

it('saute une ligne locale vide isolée si la ligne suivante contient une valeur', function () {
    $spreadsheet = test()->newWorkbook();
    $sheet = $spreadsheet->getActiveSheet();

    test()->fillGrid($sheet, [
        1 => [3 => 'Matin'],
        2 => [3 => '08/09'],
        4 => [2 => 'Salle 101', 3 => 'A'],
        6 => [2 => 'Salle 102', 3 => 'B'],
    ]);

    $result = parseWorkbook($spreadsheet);

    expect(array_column($result, 'local'))->toBe(['Salle 101', 'Salle 102']);
});

it('traite plusieurs blocs matin/midi/soir empilés dans la même feuille', function () {
    $spreadsheet = test()->newWorkbook();
    $sheet = $spreadsheet->getActiveSheet();

    test()->fillGrid($sheet, [
        1 => [3 => 'Matin'],
        2 => [3 => '08/09'],
        4 => [2 => 'Salle 101', 3 => 'A'],
        8 => [3 => 'Midi'],
        9 => [3 => '08/09'],
        11 => [2 => 'Salle 102', 3 => 'B'],
        15 => [3 => 'Soir'],
        16 => [3 => '08/09'],
        18 => [2 => 'Salle 103', 3 => 'C'],
    ]);

    $result = parseWorkbook($spreadsheet);

    expect($result)->toHaveCount(3);
    expect(collect($result)->pluck('period', 'course')->all())->toBe([
        'A' => 'morning',
        'B' => 'afternoon',
        'C' => 'evening',
    ]);
});

it('fusionne les données de plusieurs feuilles du classeur', function () {
    $spreadsheet = test()->newWorkbook();

    $sheet1 = $spreadsheet->getActiveSheet();
    $sheet1->setTitle('Feuille1');
    test()->fillGrid($sheet1, [
        1 => [3 => 'Matin'],
        2 => [3 => '08/09'],
        4 => [2 => 'Salle 101', 3 => 'A'],
    ]);

    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('Feuille2');
    test()->fillGrid($sheet2, [
        1 => [3 => 'Soir'],
        2 => [3 => '08/09'],
        4 => [2 => 'Salle 201', 3 => 'B'],
    ]);

    $result = parseWorkbook($spreadsheet);

    expect($result)->toHaveCount(2);
    expect(array_column($result, 'course'))->toBe(['A', 'B']);
});

it('reconnaît les en-têtes insensibles à la casse et aux accents é/è/ê/ë', function () {
    $spreadsheet = test()->newWorkbook();
    $sheet = $spreadsheet->getActiveSheet();

    test()->fillGrid($sheet, [
        1 => [3 => 'Matin'],
        2 => [3 => '08/09'],
        4 => [2 => 'Salle 101', 3 => 'A'],
        8 => [3 => 'MIDI'],
        9 => [3 => '08/09'],
        11 => [2 => 'Salle 102', 3 => 'B'],
        15 => [3 => 'soir'],
        16 => [3 => '08/09'],
        18 => [2 => 'Salle 103', 3 => 'C'],
        22 => [3 => 'Après-midi'],
        23 => [3 => '08/09'],
        25 => [2 => 'Salle 104', 3 => 'D'],
    ]);

    $result = parseWorkbook($spreadsheet);

    expect(collect($result)->pluck('period', 'course')->all())->toBe([
        'A' => 'morning',
        'B' => 'afternoon',
        'C' => 'evening',
        'D' => 'afternoon',
    ]);
});

it('ignore une cellule cours non vide dans une colonne sans date mappée', function () {
    $spreadsheet = test()->newWorkbook();
    $sheet = $spreadsheet->getActiveSheet();

    test()->fillGrid($sheet, [
        1 => [3 => 'Matin'],
        2 => [3 => '08/09'],
        4 => [2 => 'Salle 101', 3 => 'A', 4 => 'B'],
    ]);

    $result = parseWorkbook($spreadsheet);

    expect($result)->toHaveCount(1);
    expect($result[0]['course'])->toBe('A');
});

it('prend l\'année des cellules de date réelles du fichier, même si l\'année du formulaire est fausse', function () {
    $spreadsheet = test()->newWorkbook();
    $sheet = $spreadsheet->getActiveSheet();

    test()->fillGrid($sheet, [
        1 => [3 => 'Matin'],
        4 => [2 => 'Salle 101', 3 => 'A', 4 => 'B'],
    ]);
    test()->setDateCell($sheet, 3, 2, '2026-08-31');
    test()->setDateCell($sheet, 4, 2, '2026-09-07');

    $result = parseWorkbook($spreadsheet, 2025);

    expect(array_column($result, 'date'))->toBe(['2026-08-31', '2026-09-07']);
});

it('recale une première cellule de date texte sur la date réelle qui la suit', function () {
    // Structure du planning réel : la première colonne de dates est du texte
    // (« 24/08 ») et les suivantes de vraies dates Excel portant l'année.
    $spreadsheet = test()->newWorkbook();
    $sheet = $spreadsheet->getActiveSheet();

    test()->fillGrid($sheet, [
        1 => [3 => 'Matin'],
        2 => [3 => '24/08'],
        4 => [2 => 'Salle 101', 3 => 'A', 4 => 'B', 5 => 'C'],
    ]);
    test()->setDateCell($sheet, 4, 2, '2026-08-31');
    test()->setDateCell($sheet, 5, 2, '2026-09-07');

    $result = parseWorkbook($spreadsheet, 2025);

    expect(array_column($result, 'date'))->toBe(['2026-08-24', '2026-08-31', '2026-09-07']);
});

it('continue à déduire par +7 jours une cellule texte qui suit une date réelle', function () {
    $spreadsheet = test()->newWorkbook();
    $sheet = $spreadsheet->getActiveSheet();

    test()->fillGrid($sheet, [
        1 => [3 => 'Matin'],
        2 => [4 => '07/09'],
        4 => [2 => 'Salle 101', 3 => 'A', 4 => 'B'],
    ]);
    test()->setDateCell($sheet, 3, 2, '2026-08-31');

    $result = parseWorkbook($spreadsheet, 2025);

    expect(array_column($result, 'date'))->toBe(['2026-08-31', '2026-09-07']);
});

it('lit la valeur en cache d\'une cellule de date calculée par formule', function () {
    // Les plannings réels utilisent des formules (« =C2+7 ») : recalculer est
    // piégeux (le moteur évalue « 24/08 » comme la division 24/8 et produit des
    // dates en 1900), la valeur mise en cache dans le fichier fait foi.
    $spreadsheet = test()->newWorkbook();
    $sheet = $spreadsheet->getActiveSheet();

    test()->fillGrid($sheet, [
        1 => [3 => 'Matin'],
        4 => [2 => 'Salle 101', 3 => 'A', 4 => 'B'],
    ]);
    test()->setDateFormulaCell($sheet, 3, 2, '=DATE(2026,8,31)');
    test()->setDateFormulaCell($sheet, 4, 2, '=DATE(2026,9,7)');

    $result = parseWorkbook($spreadsheet, 2025);

    expect(array_column($result, 'date'))->toBe(['2026-08-31', '2026-09-07']);
});

it('n\'ancre pas un numérique au format date hors de toute année plausible', function () {
    // Un « 10 » au format dd/mm donnerait 1900-01-10 : on retombe sur la
    // déduction +7 jours plutôt que d'ancrer une date absurde.
    $spreadsheet = test()->newWorkbook();
    $sheet = $spreadsheet->getActiveSheet();

    test()->fillGrid($sheet, [
        1 => [3 => 'Matin'],
        2 => [3 => '08/09', 4 => 10],
        4 => [2 => 'Salle 101', 3 => 'A', 4 => 'B'],
    ]);
    $sheet->getStyle('D2')->getNumberFormat()->setFormatCode('dd/mm');

    $result = parseWorkbook($spreadsheet, 2025);

    expect(array_column($result, 'date'))->toBe(['2025-09-08', '2025-09-15']);
});

it('reconnaît l\'en-tête SAMEDI comme un bloc du matin', function () {
    // La feuille SAMEDI du planning réel n'a ni « matin », ni « midi », ni
    // « soir » dans son en-tête : elle était ignorée en silence. Les cours du
    // samedi ont lieu le matin (8h30-13h).
    $spreadsheet = test()->newWorkbook();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('SAMEDI');

    test()->fillGrid($sheet, [
        1 => [2 => 'SAMEDI'],
        2 => [2 => '29/08'],
        3 => [2 => '1', 3 => '2'],
        4 => [1 => '-103', 2 => 'CEDEG'],
    ]);

    $result = parseWorkbook($spreadsheet);

    expect($result)->toBe([
        ['date' => '2025-08-29', 'period' => 'morning', 'local' => '-103', 'course' => 'CEDEG'],
    ]);
});

it('retourne un tableau vide pour une feuille sans en-tête reconnu', function () {
    $spreadsheet = test()->newWorkbook();
    $sheet = $spreadsheet->getActiveSheet();

    test()->fillGrid($sheet, [
        1 => [1 => 'Bonjour', 2 => 'Ceci', 3 => "n'est pas un planning"],
        2 => [1 => 'Salle 101', 2 => 'X'],
    ]);

    $result = parseWorkbook($spreadsheet);

    expect($result)->toBe([]);
});
