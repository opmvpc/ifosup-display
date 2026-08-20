<?php

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Room;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesSchedulerFixture;

uses(CreatesSchedulerFixture::class);

function uploadDefaultPlanning(int $startYear = 2025): void
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

    $response = $this->postJson(route('scheduler.import.preview'));

    $response->assertStatus(422);
    $response->assertJson(['error' => 'Aucun fichier en attente.']);
});

it('calcule correctement la plage de dates et les comptages à partir du fichier', function () {
    uploadDefaultPlanning();

    $response = $this->postJson(route('scheduler.import.preview'));

    $response->assertOk();
    $response->assertJson([
        'total' => 4,
        'date_from' => '2025-09-08',
        'date_to' => '2025-09-15',
        'room_counts' => [
            'Salle 101' => 3,
            'Salle 102' => 1,
        ],
        'course_counts' => [
            'MATH101' => 2,
            'INFO101' => 1,
            'ANGL201' => 1,
        ],
    ]);
});

it('distingue les salles existantes des nouvelles salles', function () {
    Room::factory()->create(['name' => 'Salle 101']);

    uploadDefaultPlanning();

    $response = $this->postJson(route('scheduler.import.preview'));

    $response->assertOk();
    expect($response->json('existing_rooms'))->toBe(['Salle 101']);
    expect($response->json('new_rooms'))->toBe(['Salle 102']);
});

it('distingue les cours connus des cours inconnus', function () {
    Course::factory()->create(['code' => 'MATH101']);

    uploadDefaultPlanning();

    $response = $this->postJson(route('scheduler.import.preview'));

    $response->assertOk();
    expect(collect($response->json('known_courses'))->pluck('code')->all())->toBe(['MATH101']);
    expect($response->json('unknown_courses'))->toBe(['INFO101', 'ANGL201']);
});

it('détecte un conflit quand le créneau est déjà occupé par un autre cours', function () {
    $room = Room::factory()->create(['name' => 'Salle 101']);
    $otherCourse = Course::factory()->create(['code' => 'OTHER1']);
    Assignment::factory()->forSlot($room, '2025-09-08', 'morning')->forCourse($otherCourse)->create();

    uploadDefaultPlanning();

    $response = $this->postJson(route('scheduler.import.preview'));

    $response->assertOk();
    $conflicts = $response->json('conflicts');
    expect($conflicts)->toHaveCount(1);
    expect($conflicts[0]['course_new'])->toBe('MATH101');
    expect($conflicts[0]['course_current'])->toBe('OTHER1');
    expect($conflicts[0]['local'])->toBe('Salle 101');
});

it('ne signale pas de conflit si le cours en base est identique à celui importé', function () {
    $room = Room::factory()->create(['name' => 'Salle 101']);
    $course = Course::factory()->create(['code' => 'MATH101']);
    Assignment::factory()->forSlot($room, '2025-09-08', 'morning')->forCourse($course)->create();

    uploadDefaultPlanning();

    $response = $this->postJson(route('scheduler.import.preview'));

    $response->assertOk();
    expect($response->json('conflicts'))->toBe([]);
});

it("ne signale jamais de conflit pour une salle qui n'existe pas encore en base", function () {
    Room::factory()->create(['name' => 'Salle 101']);

    uploadDefaultPlanning();

    $response = $this->postJson(route('scheduler.import.preview'));

    $response->assertOk();
    $conflictLocals = collect($response->json('conflicts'))->pluck('local')->all();
    expect($conflictLocals)->not->toContain('Salle 102');
});

it('calcule le breakdown par salle et par cours avec le compte de conflits', function () {
    $room = Room::factory()->create(['name' => 'Salle 101']);
    $otherCourse = Course::factory()->create(['code' => 'OTHER1']);
    Assignment::factory()->forSlot($room, '2025-09-08', 'morning')->forCourse($otherCourse)->create();

    uploadDefaultPlanning();

    $response = $this->postJson(route('scheduler.import.preview'));

    $response->assertOk();
    $breakdown = collect($response->json('breakdown'));
    $mathBreakdown = $breakdown->firstWhere(fn ($row) => $row['room'] === 'Salle 101' && $row['course'] === 'MATH101');

    expect($mathBreakdown)->not->toBeNull();
    expect($mathBreakdown['count'])->toBe(2);
    expect($mathBreakdown['conflict_count'])->toBe(1);
});
