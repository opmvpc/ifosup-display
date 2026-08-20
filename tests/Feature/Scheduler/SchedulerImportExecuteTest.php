<?php

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Room;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesSchedulerFixture;

uses(CreatesSchedulerFixture::class);

function uploadDefaultPlanningForExecute(int $startYear = 2025): void
{
    Storage::fake();
    actingAsUser();

    $file = test()->workbookToUploadedFile(test()->defaultPlanningWorkbook($startYear));

    test()->post(route('scheduler.import.upload'), [
        'file' => $file,
        'start_year' => $startYear,
    ]);
}

it("retourne une erreur 422 si aucun fichier n'est en attente", function () {
    actingAsUser();

    $response = $this->postJson(route('scheduler.import.execute'), [
        'selected_rooms' => ['Salle 101'],
        'selected_courses' => ['MATH101'],
        'purge_period' => false,
    ]);

    $response->assertStatus(422);
    $response->assertJson(['error' => 'Aucun fichier en attente.']);
});

it('crée les nouvelles salles sélectionnées et importe les assignments', function () {
    Course::factory()->create(['code' => 'MATH101']);
    Course::factory()->create(['code' => 'INFO101']);
    Course::factory()->create(['code' => 'ANGL201']);

    uploadDefaultPlanningForExecute();

    $response = $this->postJson(route('scheduler.import.execute'), [
        'selected_rooms' => ['Salle 101', 'Salle 102'],
        'selected_courses' => ['MATH101', 'INFO101', 'ANGL201'],
        'purge_period' => false,
    ]);

    $response->assertOk();
    $response->assertJson(['imported' => 4, 'replaced' => 0]);

    expect(Room::where('name', 'Salle 101')->exists())->toBeTrue();
    expect(Room::where('name', 'Salle 102')->exists())->toBeTrue();
    expect(Assignment::count())->toBe(4);
});

it("ignore silencieusement les lignes dont la salle ou le cours sélectionné n'existe pas en base", function () {
    Course::factory()->create(['code' => 'MATH101']);
    Course::factory()->create(['code' => 'ANGL201']);

    uploadDefaultPlanningForExecute();

    $response = $this->postJson(route('scheduler.import.execute'), [
        'selected_rooms' => ['Salle 101', 'Salle 102'],
        'selected_courses' => ['MATH101', 'INFO101', 'ANGL201'],
        'purge_period' => false,
    ]);

    $response->assertOk();
    $response->assertJson(['imported' => 3, 'replaced' => 0]);
    expect(Assignment::count())->toBe(3);
    expect(Room::where('name', 'Salle 102')->exists())->toBeTrue();
    expect(Assignment::whereHas('room', fn ($q) => $q->where('name', 'Salle 102'))->count())->toBe(0);
});

it('remplace un assignment existant sur le même créneau (date+period+room) plutôt que d\'en créer un doublon', function () {
    $room = Room::factory()->create(['name' => 'Salle 101']);
    $otherCourse = Course::factory()->create(['code' => 'OTHER1']);
    Course::factory()->create(['code' => 'MATH101']);
    Course::factory()->create(['code' => 'INFO101']);
    Course::factory()->create(['code' => 'ANGL201']);

    $existing = Assignment::factory()->forSlot($room, '2025-09-08', 'morning')->forCourse($otherCourse)->cancelled()->create();

    uploadDefaultPlanningForExecute();

    $response = $this->postJson(route('scheduler.import.execute'), [
        'selected_rooms' => ['Salle 101', 'Salle 102'],
        'selected_courses' => ['MATH101', 'INFO101', 'ANGL201'],
        'purge_period' => false,
    ]);

    $response->assertOk();
    $response->assertJson(['imported' => 3, 'replaced' => 1]);

    $slotAssignments = Assignment::where('room_id', $room->id)
        ->whereDate('date', '2025-09-08')
        ->where('period', 'morning')
        ->get();

    expect($slotAssignments)->toHaveCount(1);
    expect($slotAssignments->first()->id)->toBe($existing->id);
    expect($slotAssignments->first()->course_id)->not->toBe($otherCourse->id);
    expect($slotAssignments->first()->status)->toBe('planned');
});

it('purge tous les assignments de la période importée quand purge_period est vrai, même hors sélection', function () {
    $unselectedRoom = Room::factory()->create(['name' => 'Salle Hors Selection']);
    $unselectedCourse = Course::factory()->create(['code' => 'HORS-SELECTION']);
    $outOfScope = Assignment::factory()
        ->forSlot($unselectedRoom, '2025-09-10', 'evening')
        ->forCourse($unselectedCourse)
        ->create();

    Course::factory()->create(['code' => 'MATH101']);
    Course::factory()->create(['code' => 'INFO101']);
    Course::factory()->create(['code' => 'ANGL201']);

    uploadDefaultPlanningForExecute();

    $response = $this->postJson(route('scheduler.import.execute'), [
        'selected_rooms' => ['Salle 101'],
        'selected_courses' => ['MATH101'],
        'purge_period' => true,
    ]);

    $response->assertOk();
    $this->assertDatabaseMissing('assignments', ['id' => $outOfScope->id]);
    expect($response->json('purged'))->toBeGreaterThanOrEqual(1);
});

it('ne purge rien quand purge_period est absent ou faux', function () {
    $unselectedRoom = Room::factory()->create(['name' => 'Salle Hors Selection']);
    $unselectedCourse = Course::factory()->create(['code' => 'HORS-SELECTION']);
    $outOfScope = Assignment::factory()
        ->forSlot($unselectedRoom, '2025-09-10', 'evening')
        ->forCourse($unselectedCourse)
        ->create();

    Course::factory()->create(['code' => 'MATH101']);

    uploadDefaultPlanningForExecute();

    $response = $this->postJson(route('scheduler.import.execute'), [
        'selected_rooms' => ['Salle 101'],
        'selected_courses' => ['MATH101'],
        'purge_period' => false,
    ]);

    $response->assertOk();
    $response->assertJson(['purged' => 0]);
    $this->assertDatabaseHas('assignments', ['id' => $outOfScope->id]);
});

it('supprime le fichier et nettoie la session après un import réussi', function () {
    Course::factory()->create(['code' => 'MATH101']);

    uploadDefaultPlanningForExecute();
    $path = session('scheduler_import_pending_file');

    $this->postJson(route('scheduler.import.execute'), [
        'selected_rooms' => ['Salle 101'],
        'selected_courses' => ['MATH101'],
        'purge_period' => false,
    ])->assertOk();

    Storage::assertMissing($path);
    expect(session()->has('scheduler_import_pending_file'))->toBeFalse();
    expect(session()->has('scheduler_import_start_year'))->toBeFalse();
});

it("ne recrée pas une salle déjà existante lors de l'exécution", function () {
    Room::factory()->create(['name' => 'Salle 101']);
    Course::factory()->create(['code' => 'MATH101']);

    uploadDefaultPlanningForExecute();

    $this->postJson(route('scheduler.import.execute'), [
        'selected_rooms' => ['Salle 101'],
        'selected_courses' => ['MATH101'],
        'purge_period' => false,
    ])->assertOk();

    expect(Room::where('name', 'Salle 101')->count())->toBe(1);
});

it("n'applique pas la purge quand la réinsertion échoue", function () {
    Course::factory()->create(['code' => 'MATH101']);
    $autreLocal = Room::factory()->create(['name' => 'B999']);
    $intact = Assignment::factory()->forSlot($autreLocal, '2025-09-10', 'morning')->create();

    uploadDefaultPlanningForExecute();

    Assignment::creating(function (): void {
        throw new RuntimeException('échec simulé pendant la réinsertion');
    });

    $response = $this->postJson(route('scheduler.import.execute'), [
        'selected_rooms' => ['Salle 101'],
        'selected_courses' => ['MATH101'],
        'purge_period' => true,
    ]);

    $response->assertStatus(500);
    $response->assertJsonPath('error', "L'import a échoué, aucune modification n'a été enregistrée. Le planning est intact.");

    expect(Assignment::whereKey($intact->id)->exists())->toBeTrue()
        ->and(Assignment::count())->toBe(1);
});

it("conserve le fichier téléversé quand l'import échoue", function () {
    Course::factory()->create(['code' => 'MATH101']);

    uploadDefaultPlanningForExecute();
    $path = session('scheduler_import_pending_file');

    Assignment::creating(function (): void {
        throw new RuntimeException('échec simulé pendant la réinsertion');
    });

    $this->postJson(route('scheduler.import.execute'), [
        'selected_rooms' => ['Salle 101'],
        'selected_courses' => ['MATH101'],
        'purge_period' => true,
    ])->assertStatus(500);

    Storage::assertExists($path);
    expect(session('scheduler_import_pending_file'))->toBe($path);
});
