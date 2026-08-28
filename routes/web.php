<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SchedulerImportController;
use App\Http\Controllers\ScreenController;
use App\Http\Controllers\ScreenSlideController;
use App\Http\Controllers\TeacherController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Endpoints publics interrogés en boucle par les téléviseurs. La limite est
// par adresse IP : toutes les TV de l'école sortent derrière la même IP publique,
// d'où une marge large (~4 requêtes/min par TV en régime de croisière).
Route::middleware('throttle:120,1')->group(function () {
    Route::get('screen', [ScreenController::class, 'index'])->name('screen');
    Route::get('screen/data', [ScreenController::class, 'data'])->name('screen.data');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('teachers', TeacherController::class);
    Route::resource('rooms', RoomController::class);
    Route::resource('groups', GroupController::class);
    Route::resource('courses', CourseController::class);

    Route::get('scheduler', [ScheduleController::class, 'index'])->name('schedule');
    Route::post('scheduler/assignments', [ScheduleController::class, 'store'])
        ->name('schedule.assignments.store');
    Route::patch('scheduler/assignments/{assignment}', [ScheduleController::class, 'update'])
        ->name('schedule.assignments.update');
    Route::patch('scheduler/assignments/{assignment}/status', [ScheduleController::class, 'updateStatus'])
        ->name('schedule.assignments.update-status');
    Route::delete('scheduler/assignments/{assignment}', [ScheduleController::class, 'destroy'])
        ->name('schedule.assignments.destroy');
    Route::post('scheduler/assignments/bulk/preview', [ScheduleController::class, 'bulkPreview'])
        ->name('schedule.assignments.bulk.preview');
    Route::post('scheduler/assignments/bulk', [ScheduleController::class, 'bulkStore'])
        ->name('schedule.assignments.bulk');

    Route::get('screen/slides', [ScreenSlideController::class, 'index'])->name('screen.slides.index');
    Route::post('screen/slides', [ScreenSlideController::class, 'store'])->name('screen.slides.store');
    Route::patch('screen/slides/order', [ScreenSlideController::class, 'reorder'])->name('screen.slides.reorder');
    Route::patch('screen/slides/{screenSlide}', [ScreenSlideController::class, 'update'])->name('screen.slides.update');
    Route::delete('screen/slides/{screenSlide}', [ScreenSlideController::class, 'destroy'])->name('screen.slides.destroy');

    Route::get('docs/{page?}', [DocsController::class, 'show'])->name('docs');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', AdminUserController::class);
    });

    Route::prefix('scheduler/import')->name('scheduler.import')->group(function () {
        Route::get('/', [SchedulerImportController::class, 'index'])->name('');
        Route::post('/upload', [SchedulerImportController::class, 'upload'])->name('.upload');
        Route::post('/preview', [SchedulerImportController::class, 'preview'])->name('.preview');
        Route::post('/execute', [SchedulerImportController::class, 'executeImport'])->name('.execute');
        Route::delete('/discard', [SchedulerImportController::class, 'discard'])->name('.discard');
    });
});

require __DIR__.'/settings.php';
