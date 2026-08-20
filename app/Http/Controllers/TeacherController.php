<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeacherRequest;
use App\Http\Requests\UpdateTeacherRequest;
use App\Models\Teacher;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class TeacherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $teachers = Teacher::all();

        return Inertia::render('resources/teachers/Index', [
            'teachers' => $teachers,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('resources/teachers/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTeacherRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $teacher = Teacher::create($validated);

        if ($request->boolean('_create_another')) {
            return redirect()->route('teachers.create');
        }

        return redirect()->route('teachers.show', $teacher);
    }

    /**
     * Display the specified resource.
     */
    public function show(Teacher $teacher): Response
    {
        $teacher->load(['courses:id,name,code,teacher_id']);

        return Inertia::render('resources/teachers/Show', [
            'teacher' => $teacher,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Teacher $teacher): Response
    {
        return Inertia::render('resources/teachers/Edit', [
            'teacher' => $teacher,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTeacherRequest $request, Teacher $teacher): RedirectResponse
    {
        $validated = $request->validated();

        $teacher->update($validated);

        return redirect()->route('teachers.show', $teacher);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Teacher $teacher): RedirectResponse
    {
        $teacher->delete();

        return redirect()->route('teachers.index');
    }
}
