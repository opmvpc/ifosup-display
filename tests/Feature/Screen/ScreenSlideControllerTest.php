<?php

use App\Models\ScreenSlide;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

it("refuse l'accès aux routes de gestion des slides sans authentification", function () {
    $slide = ScreenSlide::factory()->image()->create();

    $this->get(route('screen.slides.index'))->assertRedirect(route('login'));

    $this->post(route('screen.slides.store'), ['type' => 'schedule'])
        ->assertRedirect(route('login'));

    $this->patch(route('screen.slides.update', $slide), ['duration' => 6000])
        ->assertRedirect(route('login'));

    $this->patch(route('screen.slides.reorder'), ['slide_ids' => [$slide->id]])
        ->assertRedirect(route('login'));

    $this->delete(route('screen.slides.destroy', $slide))
        ->assertRedirect(route('login'));
});

it('liste les slides existants triés par position', function () {
    actingAsUser();

    ScreenSlide::factory()->schedule()->atPosition(2)->create();
    $welcome = ScreenSlide::factory()->welcome()->atPosition(0)->create();
    ScreenSlide::factory()->image()->atPosition(1)->create();

    $response = $this->get(route('screen.slides.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ScreenSlides')
        ->has('slides', 3)
        ->where('slides.0.id', $welcome->id)
        ->where('slides.0.position', 0)
        ->where('slides.1.position', 1)
        ->where('slides.2.position', 2)
    );
});

it('crée un slide image avec un fichier valide', function () {
    Storage::fake('public');
    actingAsUser();

    $response = $this->post(route('screen.slides.store'), [
        'type' => 'image',
        'image' => UploadedFile::fake()->image('photo.jpg'),
    ]);

    $response->assertStatus(201);

    $slide = ScreenSlide::query()->where('type', 'image')->firstOrFail();

    expect($slide->duration)->toBe(5000);
    expect($slide->image_path)->not->toBeNull();

    Storage::disk('public')->assertExists($slide->image_path);
});

it("refuse la création d'un slide image sans fichier", function () {
    actingAsUser();

    $response = $this->postJson(route('screen.slides.store'), [
        'type' => 'image',
    ]);

    $response->assertStatus(422);
    $response->assertJson(['message' => 'Une image est requise pour un slide image.']);
});

it("refuse la création d'un slide video sans fichier", function () {
    actingAsUser();

    $response = $this->postJson(route('screen.slides.store'), [
        'type' => 'video',
    ]);

    $response->assertStatus(422);
    $response->assertJson(['message' => 'Une video est requise pour un slide video.']);
});

it('refuse un type de slide invalide', function () {
    actingAsUser();

    $this->postJson(route('screen.slides.store'), ['type' => 'welcome'])
        ->assertStatus(422);

    $this->postJson(route('screen.slides.store'), ['type' => 'foo'])
        ->assertStatus(422);
});

it('refuse une durée hors bornes à la création', function () {
    actingAsUser();

    $this->postJson(route('screen.slides.store'), [
        'type' => 'schedule',
        'duration' => 500,
    ])->assertStatus(422);

    $this->postJson(route('screen.slides.store'), [
        'type' => 'schedule',
        'duration' => 200000,
    ])->assertStatus(422);
});

it('valide le type mime et la taille de la vidéo', function () {
    actingAsUser();

    $badMime = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');

    $this->postJson(route('screen.slides.store'), [
        'type' => 'video',
        'video' => $badMime,
    ])->assertStatus(422);

    $tooBig = UploadedFile::fake()->create('big.mp4', 307201, 'video/mp4');

    $this->postJson(route('screen.slides.store'), [
        'type' => 'video',
        'video' => $tooBig,
    ])->assertStatus(422);
});

it('met à jour le motd du slide welcome', function () {
    actingAsUser();

    $welcome = ScreenSlide::factory()->welcome()->create(['motd' => 'Ancien message']);

    $response = $this->patchJson(route('screen.slides.update', $welcome), [
        'motd' => 'Nouveau message',
    ]);

    $response->assertOk();

    expect($welcome->fresh()->motd)->toBe('Nouveau message');

    $tooLong = str_repeat('a', 281);

    $this->patchJson(route('screen.slides.update', $welcome), [
        'motd' => $tooLong,
    ])->assertStatus(422);
});

it("met à jour la durée et remplace l'image d'un slide image, en supprimant l'ancien fichier", function () {
    Storage::fake('public');
    actingAsUser();

    Storage::disk('public')->put('screen-slides/images/old.jpg', 'contenu');

    $slide = ScreenSlide::factory()->image('screen-slides/images/old.jpg')->create(['duration' => 5000]);

    $response = $this->patch(route('screen.slides.update', $slide), [
        'duration' => 7000,
        'image' => UploadedFile::fake()->image('new.jpg'),
    ]);

    $response->assertOk();

    $slide->refresh();

    expect($slide->duration)->toBe(7000);
    expect($slide->image_path)->not->toBe('screen-slides/images/old.jpg');

    Storage::disk('public')->assertMissing('screen-slides/images/old.jpg');
    Storage::disk('public')->assertExists($slide->image_path);
});

it('supprime l\'ancien fichier du disque public via le hook updating du modèle', function () {
    Storage::fake('public');

    Storage::disk('public')->put('screen-slides/videos/old.mp4', 'contenu');

    $slide = ScreenSlide::factory()->video('screen-slides/videos/old.mp4')->create();

    // Update direct sur le modèle, hors contrôleur : c'est le hook `updating`
    // du modèle qui doit déclencher la suppression sur le disque `public`.
    $slide->update(['video_path' => 'screen-slides/videos/new.mp4']);

    Storage::disk('public')->assertMissing('screen-slides/videos/old.mp4');
});

it('refuse de mettre à jour un slide image sans laisser au moins une image', function () {
    actingAsUser();

    $slide = ScreenSlide::factory()->create([
        'type' => ScreenSlide::TYPE_IMAGE,
        'image_path' => null,
        'duration' => 5000,
    ]);

    $response = $this->patchJson(route('screen.slides.update', $slide), [
        'duration' => 6000,
    ]);

    $response->assertStatus(422);
    $response->assertJson(['message' => 'Une image est requise pour un slide image.']);
});

it('ne modifie rien sur un update de slide schedule (no-op silencieux)', function () {
    actingAsUser();

    $slide = ScreenSlide::factory()->schedule()->create(['duration' => null]);

    $response = $this->patchJson(route('screen.slides.update', $slide), [
        'motd' => 'Peu importe',
        'duration' => 9999999,
        'foo' => 'bar',
    ]);

    $response->assertOk();

    $slide->refresh();

    expect($slide->motd)->toBeNull();
    expect($slide->duration)->toBeNull();
    expect($slide->type)->toBe(ScreenSlide::TYPE_SCHEDULE);
});

it('réordonne les slides en respectant la contrainte du slide verrouillé en premier', function () {
    actingAsUser();

    $welcome = ScreenSlide::factory()->welcome()->atPosition(0)->create();
    $schedule = ScreenSlide::factory()->schedule()->atPosition(1)->create();
    $image = ScreenSlide::factory()->image()->atPosition(2)->create();

    $response = $this->patchJson(route('screen.slides.reorder'), [
        'slide_ids' => [$welcome->id, $image->id, $schedule->id],
    ]);

    $response->assertOk();

    expect($welcome->fresh()->position)->toBe(0);
    expect($image->fresh()->position)->toBe(1);
    expect($schedule->fresh()->position)->toBe(2);
});

it('refuse un réordonnancement qui ne met pas le slide verrouillé en premier', function () {
    actingAsUser();

    $welcome = ScreenSlide::factory()->welcome()->atPosition(0)->create();
    $schedule = ScreenSlide::factory()->schedule()->atPosition(1)->create();

    $response = $this->patchJson(route('screen.slides.reorder'), [
        'slide_ids' => [$schedule->id, $welcome->id],
    ]);

    $response->assertStatus(422);
    $response->assertJson(['message' => 'Le slide de bienvenue doit rester en premiere position.']);
});

it('refuse un réordonnancement avec une liste incomplète ou avec doublons', function () {
    actingAsUser();

    $welcome = ScreenSlide::factory()->welcome()->atPosition(0)->create();
    $schedule = ScreenSlide::factory()->schedule()->atPosition(1)->create();
    ScreenSlide::factory()->image()->atPosition(2)->create();

    $this->patchJson(route('screen.slides.reorder'), [
        'slide_ids' => [$welcome->id, $schedule->id],
    ])->assertStatus(422);

    $this->patchJson(route('screen.slides.reorder'), [
        'slide_ids' => [$welcome->id, $welcome->id, $schedule->id],
    ])->assertStatus(422);
});

it('refuse de supprimer le slide verrouillé', function () {
    actingAsUser();

    $welcome = ScreenSlide::factory()->welcome()->create();

    $response = $this->deleteJson(route('screen.slides.destroy', $welcome));

    $response->assertStatus(422);
    $response->assertJson(['message' => 'Le slide de bienvenue ne peut pas etre supprime.']);

    expect(ScreenSlide::query()->find($welcome->id))->not->toBeNull();
});

it('supprime un slide non verrouillé et renumérote les positions restantes', function () {
    actingAsUser();

    $welcome = ScreenSlide::factory()->welcome()->atPosition(0)->create();
    $schedule = ScreenSlide::factory()->schedule()->atPosition(1)->create();
    $toDelete = ScreenSlide::factory()->image()->atPosition(2)->create();
    $video = ScreenSlide::factory()->video()->atPosition(3)->create();

    $response = $this->deleteJson(route('screen.slides.destroy', $toDelete));

    $response->assertOk();

    expect(ScreenSlide::query()->find($toDelete->id))->toBeNull();

    $remaining = ScreenSlide::query()->ordered()->get();

    expect($remaining->pluck('id')->all())->toBe([$welcome->id, $schedule->id, $video->id]);
    expect($remaining->pluck('position')->all())->toBe([0, 1, 2]);
});

it('supprime les fichiers physiques associés lors de la suppression d\'un slide image/video', function () {
    Storage::fake('public');
    actingAsUser();

    Storage::disk('public')->put('screen-slides/images/to-delete.jpg', 'contenu');
    Storage::disk('public')->put('screen-slides/videos/to-delete.mp4', 'contenu');

    $image = ScreenSlide::factory()->image('screen-slides/images/to-delete.jpg')->create();
    $video = ScreenSlide::factory()->video('screen-slides/videos/to-delete.mp4')->create();

    $this->deleteJson(route('screen.slides.destroy', $image))->assertOk();
    $this->deleteJson(route('screen.slides.destroy', $video))->assertOk();

    Storage::disk('public')->assertMissing('screen-slides/images/to-delete.jpg');
    Storage::disk('public')->assertMissing('screen-slides/videos/to-delete.mp4');
});
