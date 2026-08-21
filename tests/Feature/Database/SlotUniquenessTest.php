<?php

use App\Models\Assignment;
use App\Models\Room;
use Illuminate\Database\UniqueConstraintViolationException;

// Le trio (date, période, local) définit un créneau. Le code le traite partout comme
// unique par des vérifications « lire puis écrire », qui ne protègent pas de deux
// écritures concurrentes. Ces tests figent la garantie apportée par la base.

it('refuse deux attributions sur le même local, la même date et la même période', function () {
    $room = Room::factory()->create();

    Assignment::factory()->forSlot($room, '2026-09-01', 'morning')->create();

    expect(fn () => Assignment::factory()->forSlot($room, '2026-09-01', 'morning')->create())
        ->toThrow(UniqueConstraintViolationException::class);
});

it('accepte le même local et la même date sur des périodes différentes', function () {
    $room = Room::factory()->create();

    Assignment::factory()->forSlot($room, '2026-09-01', 'morning')->create();
    Assignment::factory()->forSlot($room, '2026-09-01', 'afternoon')->create();
    Assignment::factory()->forSlot($room, '2026-09-01', 'evening')->create();

    expect(Assignment::count())->toBe(3);
});

it('accepte la même période et la même date dans des locaux différents', function () {
    Assignment::factory()->forSlot(Room::factory()->create(), '2026-09-01', 'morning')->create();
    Assignment::factory()->forSlot(Room::factory()->create(), '2026-09-01', 'morning')->create();

    expect(Assignment::count())->toBe(2);
});

it('laisse coexister plusieurs attributions sans local sur le même créneau', function () {
    Assignment::factory()->withoutRoom()->onDate('2026-09-01')->inPeriod('morning')->create();
    Assignment::factory()->withoutRoom()->onDate('2026-09-01')->inPeriod('morning')->create();

    expect(Assignment::count())->toBe(2);
});

it('bloque le créneau quel que soit le statut de l\'attribution existante', function () {
    $room = Room::factory()->create();

    Assignment::factory()->cancelled()->forSlot($room, '2026-09-01', 'morning')->create();

    expect(fn () => Assignment::factory()->planned()->forSlot($room, '2026-09-01', 'morning')->create())
        ->toThrow(UniqueConstraintViolationException::class);
});
