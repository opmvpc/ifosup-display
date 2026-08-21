<?php

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Room;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesSchedulerFixture;

uses(CreatesSchedulerFixture::class);

// Les locaux de l'école portent des noms purement numériques (« 106 », « 204 »).
// Les fixtures d'import utilisaient toutes des noms alphanumériques (« Salle 101 »),
// ce qui masquait un défaut : `pluck('id', 'name')` produit un tableau dont les clés
// numériques sont converties en entiers par PHP, et la recherche du local existant
// échouait — l'import tentait alors de recréer un local déjà présent.

function uploadNumericRoomPlanning(): void
{
    Storage::fake();
    actingAsUser();

    $spreadsheet = test()->newWorkbook();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Planning');

    test()->fillGrid($sheet, [
        1 => [3 => 'Matin'],
        2 => [3 => '08/09'],
        4 => [2 => '106', 3 => 'MATH101'],
    ]);

    test()->post(route('scheduler.import.upload'), [
        'file' => test()->workbookToUploadedFile($spreadsheet),
        'start_year' => 2025,
    ]);
}

it('importe dans un local numérique déjà existant sans le recréer', function () {
    Course::factory()->create(['code' => 'MATH101']);
    $room = Room::factory()->create(['name' => '106']);

    uploadNumericRoomPlanning();

    $response = $this->postJson(route('scheduler.import.execute'), [
        'selected_rooms' => ['106'],
        'selected_courses' => ['MATH101'],
        'purge_period' => false,
    ]);

    $response->assertOk();

    expect(Room::where('name', '106')->count())->toBe(1)
        ->and(Assignment::where('room_id', $room->id)->count())->toBe(1);
});

it('crée un local numérique absent puis le réutilise à un second import', function () {
    Course::factory()->create(['code' => 'MATH101']);

    uploadNumericRoomPlanning();
    $this->postJson(route('scheduler.import.execute'), [
        'selected_rooms' => ['106'],
        'selected_courses' => ['MATH101'],
        'purge_period' => false,
    ])->assertOk();

    uploadNumericRoomPlanning();
    $this->postJson(route('scheduler.import.execute'), [
        'selected_rooms' => ['106'],
        'selected_courses' => ['MATH101'],
        'purge_period' => false,
    ])->assertOk();

    expect(Room::where('name', '106')->count())->toBe(1);
});
