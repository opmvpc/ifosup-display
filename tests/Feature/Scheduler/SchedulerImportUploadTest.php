<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it("refuse l'accès aux routes d'import sans authentification", function () {
    $this->get(route('scheduler.import'))->assertRedirect(route('login'));
    $this->post(route('scheduler.import.upload'))->assertRedirect(route('login'));
    $this->post(route('scheduler.import.preview'))->assertRedirect(route('login'));
    $this->post(route('scheduler.import.execute'))->assertRedirect(route('login'));
    $this->delete(route('scheduler.import.discard'))->assertRedirect(route('login'));
});

it('upload un fichier xlsx valide et le place en session', function () {
    Storage::fake();
    actingAsUser();

    $file = UploadedFile::fake()->create('planning.xlsx', 100);

    $response = $this->post(route('scheduler.import.upload'), [
        'file' => $file,
        'start_year' => 2025,
    ]);

    $response->assertRedirect(route('scheduler.import'));
    expect(session('scheduler_import_pending_file'))->not->toBeNull();
    expect(session('scheduler_import_start_year'))->toBe(2025);
    Storage::assertExists(session('scheduler_import_pending_file'));
});

it("refuse un fichier qui n'est pas xlsx/xls", function () {
    Storage::fake();
    actingAsUser();

    $file = UploadedFile::fake()->create('planning.pdf', 100, 'application/pdf');

    $response = $this->post(route('scheduler.import.upload'), [
        'file' => $file,
        'start_year' => 2025,
    ]);

    $response->assertSessionHasErrors('file');
});

it('refuse un fichier de plus de 20 Mo', function () {
    Storage::fake();
    actingAsUser();

    $file = UploadedFile::fake()->create('planning.xlsx', 20481);

    $response = $this->post(route('scheduler.import.upload'), [
        'file' => $file,
        'start_year' => 2025,
    ]);

    $response->assertSessionHasErrors('file');
});

it('refuse une start_year hors bornes', function () {
    Storage::fake();
    actingAsUser();

    $file = UploadedFile::fake()->create('planning.xlsx', 100);

    $this->post(route('scheduler.import.upload'), ['file' => $file, 'start_year' => 1999])
        ->assertSessionHasErrors('start_year');

    $file = UploadedFile::fake()->create('planning.xlsx', 100);

    $this->post(route('scheduler.import.upload'), ['file' => $file, 'start_year' => 2101])
        ->assertSessionHasErrors('start_year');
});

it('remplace le fichier précédent lors d\'un nouvel upload dans la même session', function () {
    Storage::fake();
    actingAsUser();

    $this->post(route('scheduler.import.upload'), [
        'file' => UploadedFile::fake()->create('planning-a.xlsx', 100),
        'start_year' => 2025,
    ]);
    $pathA = session('scheduler_import_pending_file');
    Storage::assertExists($pathA);

    $this->post(route('scheduler.import.upload'), [
        'file' => UploadedFile::fake()->create('planning-b.xlsx', 100),
        'start_year' => 2025,
    ]);
    $pathB = session('scheduler_import_pending_file');

    expect($pathB)->not->toBe($pathA);
    Storage::assertMissing($pathA);
    Storage::assertExists($pathB);
});

it('indexe hasPendingFile correctement selon l\'état de la session', function () {
    Storage::fake();
    $user = actingAsUser();

    $this->get(route('scheduler.import'))
        ->assertInertia(fn ($page) => $page->where('hasPendingFile', false));

    $this->post(route('scheduler.import.upload'), [
        'file' => UploadedFile::fake()->create('planning.xlsx', 100),
        'start_year' => 2025,
    ]);

    $this->get(route('scheduler.import'))
        ->assertInertia(fn ($page) => $page->where('hasPendingFile', true));

    Storage::delete(session('scheduler_import_pending_file'));

    $this->get(route('scheduler.import'))
        ->assertInertia(fn ($page) => $page->where('hasPendingFile', false));
});
