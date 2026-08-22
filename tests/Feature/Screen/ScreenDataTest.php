<?php

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Group;
use App\Models\Room;
use App\Models\ScreenSlide;
use App\Models\Teacher;
use Carbon\Carbon;

it('crée les slides par défaut au premier appel de /screen/data', function () {
    expect(ScreenSlide::query()->count())->toBe(0);

    $response = $this->getJson(route('screen.data'));

    $response->assertOk();

    expect(ScreenSlide::query()->count())->toBe(2);

    $welcome = ScreenSlide::query()->where('type', ScreenSlide::TYPE_WELCOME)->first();
    $schedule = ScreenSlide::query()->where('type', ScreenSlide::TYPE_SCHEDULE)->first();

    expect($welcome)->not->toBeNull()
        ->and($welcome->position)->toBe(0)
        ->and($welcome->is_locked)->toBeTrue()
        ->and($schedule)->not->toBeNull()
        ->and($schedule->position)->toBe(1);

    $types = collect($response->json('slides'))->pluck('type');

    expect($types)->toContain('welcome')
        ->and($types)->toContain('schedule');
});

it("n'ajoute pas de slides par défaut si des slides existent déjà", function () {
    ScreenSlide::factory()->image()->create();

    expect(ScreenSlide::query()->count())->toBe(1);

    $this->getJson(route('screen.data'))->assertOk();

    expect(ScreenSlide::query()->count())->toBe(1);
});

it("n'affiche que les cours du matin et de l'après-midi le matin", function () {
    $this->travelTo(Carbon::parse('today 09:00:00', 'Europe/Brussels'));

    ScreenSlide::factory()->schedule()->create();

    Assignment::factory()->onDate(today())->inPeriod('morning')->create();
    Assignment::factory()->onDate(today())->inPeriod('afternoon')->create();
    Assignment::factory()->onDate(today())->inPeriod('evening')->create();
    Assignment::factory()->onDate(today()->addDay())->inPeriod('morning')->create();

    $response = $this->getJson(route('screen.data'));

    $response->assertOk();

    $scheduleSlides = collect($response->json('slides'))->where('type', 'schedule');

    expect($scheduleSlides)->toHaveCount(2);

    $keys = $scheduleSlides->pluck('data.title');

    expect($keys)->toContain('Cours du matin')
        ->and($keys)->toContain("Cours de l'après-midi")
        ->and($keys)->not->toContain('Cours du soir');
});

it("n'affiche que les cours de l'après-midi et du soir l'après-midi", function () {
    $this->travelTo(Carbon::parse('today 14:00:00', 'Europe/Brussels'));

    ScreenSlide::factory()->schedule()->create();

    Assignment::factory()->onDate(today())->inPeriod('morning')->create();
    Assignment::factory()->onDate(today())->inPeriod('afternoon')->create();
    Assignment::factory()->onDate(today())->inPeriod('evening')->create();

    $response = $this->getJson(route('screen.data'));

    $response->assertOk();

    $scheduleSlides = collect($response->json('slides'))->where('type', 'schedule');

    expect($scheduleSlides)->toHaveCount(2);

    $titles = $scheduleSlides->pluck('data.title');

    expect($titles)->toContain("Cours de l'après-midi")
        ->and($titles)->toContain('Cours du soir')
        ->and($titles)->not->toContain('Cours du matin');
});

it("n'affiche que les cours du soir après 17h30", function () {
    $this->travelTo(Carbon::parse('today 18:00:00', 'Europe/Brussels'));

    ScreenSlide::factory()->schedule()->create();

    Assignment::factory()->onDate(today())->inPeriod('morning')->create();
    Assignment::factory()->onDate(today())->inPeriod('afternoon')->create();
    Assignment::factory()->onDate(today())->inPeriod('evening')->create();

    $response = $this->getJson(route('screen.data'));

    $response->assertOk();

    $scheduleSlides = collect($response->json('slides'))->where('type', 'schedule');

    expect($scheduleSlides)->toHaveCount(1);
    expect($scheduleSlides->first()['data']['title'])->toBe('Cours du soir');
});

it('respecte les bornes exactes des créneaux (12:30:00 et 17:30:00)', function () {
    ScreenSlide::factory()->schedule()->create();

    $this->travelTo(Carbon::parse('today 12:30:00', 'Europe/Brussels'));
    $titlesAt123000 = collect($this->getJson(route('screen.data'))->json('slides'))
        ->where('type', 'schedule')
        ->pluck('data.title');
    expect($titlesAt123000)->toContain('Cours du matin')
        ->and($titlesAt123000)->toContain("Cours de l'après-midi")
        ->and($titlesAt123000)->not->toContain('Cours du soir');

    $this->travelTo(Carbon::parse('today 12:30:01', 'Europe/Brussels'));
    $titlesAt123001 = collect($this->getJson(route('screen.data'))->json('slides'))
        ->where('type', 'schedule')
        ->pluck('data.title');
    expect($titlesAt123001)->toContain("Cours de l'après-midi")
        ->and($titlesAt123001)->toContain('Cours du soir')
        ->and($titlesAt123001)->not->toContain('Cours du matin');

    $this->travelTo(Carbon::parse('today 17:30:00', 'Europe/Brussels'));
    $titlesAt173000 = collect($this->getJson(route('screen.data'))->json('slides'))
        ->where('type', 'schedule')
        ->pluck('data.title');
    expect($titlesAt173000)->toContain("Cours de l'après-midi")
        ->and($titlesAt173000)->toContain('Cours du soir')
        ->and($titlesAt173000)->not->toContain('Cours du matin');

    $this->travelTo(Carbon::parse('today 17:30:01', 'Europe/Brussels'));
    $titlesAt173001 = collect($this->getJson(route('screen.data'))->json('slides'))
        ->where('type', 'schedule')
        ->pluck('data.title');
    expect($titlesAt173001)->toHaveCount(1)
        ->and($titlesAt173001->first())->toBe('Cours du soir');
});

it('retient bien une période pendant le creux de minuit avec microsecondes (correction du bug)', function () {
    // Avant correction, `Carbon::between()` comparait `$now` (avec microsecondes)
    // à des bornes sans microsecondes : à 23:59:59,5 aucune période ne matchait
    // et l'écran se vidait pendant ~1s chaque nuit. Le contrôleur tronque
    // désormais l'instant courant à la seconde (`startOfSecond()`) avant de
    // chercher la période : 23:59:59,5 doit donc être classé "evening".
    $this->travelTo(Carbon::parse('today 23:59:59.500000', 'Europe/Brussels'));

    ScreenSlide::factory()->schedule()->create();

    Assignment::factory()->onDate(today())->inPeriod('evening')->create();

    $response = $this->getJson(route('screen.data'));

    $response->assertOk();

    $scheduleSlides = collect($response->json('slides'))->where('type', 'schedule');

    expect($scheduleSlides)->toHaveCount(1);
    expect($scheduleSlides->first()['data']['title'])->toBe('Cours du soir');
});

it('respecte le fuseau horaire configuré pour l\'écran', function () {
    // 19:00 UTC un jour donné correspond à 09:00 (jour suivant) sur Pacific/Kiritimati (UTC+14) :
    // en UTC brut ce serait "evening" (17:30-23:59), mais converti dans le fuseau
    // configuré c'est "morning". Ça prouve que le calcul utilise bien le fuseau écran.
    config(['app.screen_timezone' => 'Pacific/Kiritimati']);

    $utcInstant = Carbon::parse('2026-08-17 19:00:00', 'UTC');
    $this->travelTo($utcInstant);

    ScreenSlide::factory()->schedule()->create();

    $localToday = $utcInstant->copy()->setTimezone('Pacific/Kiritimati')->toDateString();

    Assignment::factory()->onDate($localToday)->inPeriod('morning')->create();

    $response = $this->getJson(route('screen.data'));

    $response->assertOk();
    expect($response->json('timezone'))->toBe('Pacific/Kiritimati');

    $titles = collect($response->json('slides'))->where('type', 'schedule')->pluck('data.title');

    expect($titles)->toContain('Cours du matin')
        ->and($titles)->toContain("Cours de l'après-midi")
        ->and($titles)->not->toContain('Cours du soir');
});

it('ne montre que les assignments du jour courant', function () {
    $this->travelTo(Carbon::parse('today 09:00:00', 'Europe/Brussels'));

    ScreenSlide::factory()->schedule()->create();

    $yesterday = Assignment::factory()->onDate(today()->subDay())->inPeriod('morning')->create();
    $todayAssignment = Assignment::factory()->onDate(today())->inPeriod('morning')->create();
    $tomorrow = Assignment::factory()->onDate(today()->addDay())->inPeriod('morning')->create();

    $response = $this->getJson(route('screen.data'));

    $response->assertOk();

    $morningRows = collect($response->json('slides'))
        ->where('type', 'schedule')
        ->firstWhere('data.title', 'Cours du matin');

    // La réponse publique n'expose plus les ids de relations : on compare les codes.
    $courseCodes = collect($morningRows['data']['rows'])->pluck('course.code');

    expect($courseCodes)->toContain($todayAssignment->course->code)
        ->and($courseCodes)->not->toContain($yesterday->course->code)
        ->and($courseCodes)->not->toContain($tomorrow->course->code);
});

it('trie les groupes de périodes dans l\'ordre matin, après-midi, soir', function () {
    $this->travelTo(Carbon::parse('today 14:00:00', 'Europe/Brussels'));

    ScreenSlide::factory()->schedule()->create();

    $response = $this->getJson(route('screen.data'));

    $response->assertOk();

    $titles = collect($response->json('slides'))->where('type', 'schedule')->pluck('data.title')->values();

    expect($titles->all())->toBe(["Cours de l'après-midi", 'Cours du soir']);
});

it('inclut course.teacher, course.groups et room dans les lignes de planning', function () {
    $this->travelTo(Carbon::parse('today 09:00:00', 'Europe/Brussels'));

    ScreenSlide::factory()->schedule()->create();

    $teacher = Teacher::factory()->create(['name' => 'Jean Dupont']);
    $room = Room::factory()->create(['name' => 'Salle 101']);
    $course = Course::factory()->create(['teacher_id' => $teacher->id]);
    $groups = Group::factory()->count(2)->create();
    $course->groups()->attach($groups->pluck('id'));

    Assignment::factory()
        ->forCourse($course)
        ->inRoom($room)
        ->onDate(today())
        ->inPeriod('morning')
        ->create();

    $response = $this->getJson(route('screen.data'));

    $response->assertOk();

    $morningRows = collect($response->json('slides'))
        ->where('type', 'schedule')
        ->firstWhere('data.title', 'Cours du matin');

    $row = collect($morningRows['data']['rows'])->first();

    expect($row['course']['teacher']['name'])->toBe('Jean Dupont')
        ->and($row['course']['groups'])->toHaveCount(2)
        ->and($row['room']['name'])->toBe('Salle 101');
});

it("n'affiche pas un slide image sans fichier", function () {
    ScreenSlide::factory()->create([
        'type' => ScreenSlide::TYPE_IMAGE,
        'image_path' => null,
    ]);

    $response = $this->getJson(route('screen.data'));

    $response->assertOk();

    $imageSlides = collect($response->json('slides'))->where('type', 'image');

    expect($imageSlides)->toHaveCount(0);
});

it('affiche un slide image avec sa durée et son url', function () {
    ScreenSlide::factory()->image()->create(['duration' => 8000]);

    $response = $this->getJson(route('screen.data'));

    $response->assertOk();

    $imageSlide = collect($response->json('slides'))->firstWhere('type', 'image');

    expect($imageSlide)->not->toBeNull()
        ->and($imageSlide['data']['duration'])->toBe(8000)
        ->and($imageSlide['data']['src'])->not->toBeEmpty();
});

it("n'affiche pas un slide video sans fichier", function () {
    ScreenSlide::factory()->create([
        'type' => ScreenSlide::TYPE_VIDEO,
        'video_path' => null,
    ]);

    $response = $this->getJson(route('screen.data'));

    $response->assertOk();

    $videoSlides = collect($response->json('slides'))->where('type', 'video');

    expect($videoSlides)->toHaveCount(0);
});

it('affiche le motd du slide welcome', function () {
    ScreenSlide::factory()->welcome()->create(['motd' => 'Bienvenue chez IFOSUP']);

    $response = $this->getJson(route('screen.data'));

    $response->assertOk();

    $welcomeSlide = collect($response->json('slides'))->firstWhere('type', 'welcome');

    expect($welcomeSlide['data']['motd'])->toBe('Bienvenue chez IFOSUP');
});

it("l'écran /screen est accessible sans authentification", function () {
    $response = $this->get(route('screen'));

    $response->assertOk();
});

it('/screen/data est accessible sans authentification', function () {
    $response = $this->getJson(route('screen.data'));

    $response->assertOk();
    $response->assertJsonStructure(['now', 'timezone', 'slides']);
});
