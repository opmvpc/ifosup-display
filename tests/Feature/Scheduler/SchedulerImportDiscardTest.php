<?php

use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesSchedulerFixture;

uses(CreatesSchedulerFixture::class);

it('supprime le fichier en attente et nettoie la session', function () {
    Storage::fake();
    actingAsUser();

    $file = $this->workbookToUploadedFile($this->defaultPlanningWorkbook());

    $this->post(route('scheduler.import.upload'), [
        'file' => $file,
        'start_year' => 2025,
    ]);

    $path = session('scheduler_import_pending_file');
    Storage::assertExists($path);

    $response = $this->delete(route('scheduler.import.discard'));

    $response->assertRedirect(route('scheduler.import'));
    Storage::assertMissing($path);
    expect(session()->has('scheduler_import_pending_file'))->toBeFalse();
    expect(session()->has('scheduler_import_start_year'))->toBeFalse();
});

it("ne plante pas si aucun fichier n'est en attente", function () {
    actingAsUser();

    $response = $this->delete(route('scheduler.import.discard'));

    $response->assertRedirect(route('scheduler.import'));
});
