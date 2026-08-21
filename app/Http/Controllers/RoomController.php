<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Models\Room;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class RoomController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $rooms = Room::all();

        return Inertia::render('resources/rooms/Index', [
            'rooms' => $rooms,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('resources/rooms/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoomRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $room = Room::create($validated);

        if ($request->boolean('_create_another')) {
            return redirect()->route('rooms.create');
        }

        return redirect()->route('rooms.show', $room);
    }

    /**
     * Display the specified resource.
     */
    public function show(Room $room): Response
    {
        return Inertia::render('resources/rooms/Show', [
            'room' => $room,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Room $room): Response
    {
        return Inertia::render('resources/rooms/Edit', [
            'room' => $room,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoomRequest $request, Room $room): RedirectResponse
    {
        $validated = $request->validated();

        $room->update($validated);

        return redirect()->route('rooms.show', $room);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Room $room): RedirectResponse
    {
        $room->delete();

        return redirect()->route('rooms.index');
    }
}
