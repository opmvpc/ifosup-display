<?php

use App\Models\ScreenSlide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// `tests/Pest.php` ne branche `RefreshDatabase` que sur `Feature` et
// `Unit/Services` : ce fichier vit dans `Unit` mais a besoin de la base de
// données (modèle Eloquent), donc on le lie explicitement ici.
uses(TestCase::class, RefreshDatabase::class);

it('ensureDefaultSlides crée un slide welcome verrouillé et un slide schedule quand la table est vide', function () {
    expect(ScreenSlide::query()->count())->toBe(0);

    ScreenSlide::ensureDefaultSlides();

    expect(ScreenSlide::query()->count())->toBe(2);

    $welcome = ScreenSlide::query()->where('type', ScreenSlide::TYPE_WELCOME)->first();
    $schedule = ScreenSlide::query()->where('type', ScreenSlide::TYPE_SCHEDULE)->first();

    expect($welcome)->not->toBeNull()
        ->and($welcome->position)->toBe(0)
        ->and($welcome->is_locked)->toBeTrue()
        ->and($schedule)->not->toBeNull()
        ->and($schedule->position)->toBe(1)
        ->and($schedule->is_locked)->toBeFalse();
});

it('ensureDefaultSlides ne fait rien si au moins un slide existe déjà', function () {
    ScreenSlide::factory()->image()->create();

    expect(ScreenSlide::query()->count())->toBe(1);

    ScreenSlide::ensureDefaultSlides();

    expect(ScreenSlide::query()->count())->toBe(1);
    expect(ScreenSlide::query()->where('type', ScreenSlide::TYPE_WELCOME)->exists())->toBeFalse();
});

it('le scope ordered trie par position puis par id', function () {
    $third = ScreenSlide::factory()->schedule()->atPosition(2)->create();
    $first = ScreenSlide::factory()->welcome()->atPosition(0)->create();
    $second = ScreenSlide::factory()->image()->atPosition(1)->create();
    $sameSecondPositionButLaterId = ScreenSlide::factory()->video()->atPosition(1)->create();

    $ordered = ScreenSlide::query()->ordered()->get();

    expect($ordered->pluck('id')->all())->toBe([
        $first->id,
        $second->id,
        $sameSecondPositionButLaterId->id,
        $third->id,
    ]);
});

it('imageUrl retourne null si aucun fichier n\'est associé', function () {
    $slide = ScreenSlide::factory()->create([
        'type' => ScreenSlide::TYPE_IMAGE,
        'image_path' => null,
    ]);

    expect($slide->imageUrl())->toBeNull();
});

it('videoUrl retourne null si aucun fichier n\'est associé', function () {
    $slide = ScreenSlide::factory()->create([
        'type' => ScreenSlide::TYPE_VIDEO,
        'video_path' => null,
    ]);

    expect($slide->videoUrl())->toBeNull();
});

it('imageUrl retourne une url relative /storage/... via le disque par défaut', function () {
    $slide = ScreenSlide::factory()->image('screen-slides/images/photo.jpg')->create();

    // Volontaire (cf commentaire du modèle) : `imageUrl()` passe par le disque
    // par défaut pour produire une URL relative résolue via le lien symbolique
    // `public/storage`, plutôt que de dépendre d'un `APP_URL` correct.
    expect($slide->imageUrl())->toBe('/storage/screen-slides/images/photo.jpg');
});

it('videoUrl retourne une url relative /storage/... via le disque par défaut', function () {
    $slide = ScreenSlide::factory()->video('screen-slides/videos/clip.mp4')->create();

    expect($slide->videoUrl())->toBe('/storage/screen-slides/videos/clip.mp4');
});

it('le hook deleting supprime les fichiers image et video du disque public', function () {
    Storage::fake('public');

    Storage::disk('public')->put('screen-slides/images/gone.jpg', 'contenu');
    Storage::disk('public')->put('screen-slides/videos/gone.mp4', 'contenu');

    $slide = ScreenSlide::factory()->create([
        'type' => ScreenSlide::TYPE_IMAGE,
        'image_path' => 'screen-slides/images/gone.jpg',
        'video_path' => 'screen-slides/videos/gone.mp4',
    ]);

    $slide->delete();

    Storage::disk('public')->assertMissing('screen-slides/images/gone.jpg');
    Storage::disk('public')->assertMissing('screen-slides/videos/gone.mp4');
});

it('le hook deleting ne plante pas si le slide n\'a aucun fichier associé', function () {
    Storage::fake('public');

    $slide = ScreenSlide::factory()->welcome()->create();

    $slide->delete();

    expect(ScreenSlide::query()->find($slide->id))->toBeNull();
});

it("le hook updating supprime l'ancien fichier image du disque public lors d'un remplacement", function () {
    Storage::fake('public');

    Storage::disk('public')->put('screen-slides/images/old.jpg', 'contenu');

    $slide = ScreenSlide::factory()->image('screen-slides/images/old.jpg')->create();

    $slide->update(['image_path' => 'screen-slides/images/new.jpg']);

    Storage::disk('public')->assertMissing('screen-slides/images/old.jpg');
});

it("le hook updating supprime l'ancien fichier video du disque public lors d'un remplacement", function () {
    Storage::fake('public');

    Storage::disk('public')->put('screen-slides/videos/old.mp4', 'contenu');

    $slide = ScreenSlide::factory()->video('screen-slides/videos/old.mp4')->create();

    $slide->update(['video_path' => 'screen-slides/videos/new.mp4']);

    Storage::disk('public')->assertMissing('screen-slides/videos/old.mp4');
});

it("le hook updating ne supprime pas le fichier si image_path n'est pas modifié", function () {
    Storage::fake('public');

    Storage::disk('public')->put('screen-slides/images/kept.jpg', 'contenu');

    $slide = ScreenSlide::factory()->image('screen-slides/images/kept.jpg')->create(['duration' => 5000]);

    $slide->update(['duration' => 6000]);

    Storage::disk('public')->assertExists('screen-slides/images/kept.jpg');
});
