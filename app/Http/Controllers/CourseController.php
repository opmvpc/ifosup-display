<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course;
use App\Models\Group;
use App\Models\Teacher;
use Inertia\Inertia;

class CourseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $courses = Course::orderBy('code')->get();

        return Inertia::render('resources/courses/Index', [
            'courses' => $courses,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $teachers = Teacher::orderBy('name')->get();
        $groups = Group::orderBy('name')->get();

        return Inertia::render('resources/courses/Create', [
            'teachers' => $teachers,
            'groups' => $groups,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCourseRequest $request)
    {
        $validated = $request->validated();

        $course = Course::create($validated);

        if ($request->has('groups')) {
            $course->groups()->sync($validated['groups']);
        }

        if ($request->boolean('_create_another')) {
            return redirect()->route('courses.create');
        }

        return redirect()->route('courses.show', $course);
    }

    /**
     * Display the specified resource.
     */
    public function show(Course $course)
    {
        $course->load(['teacher:id,name', 'groups:id,name']);

        return Inertia::render('resources/courses/Show', [
            'course' => $course,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Course $course)
    {
        $course->load('groups', 'teacher');

        $teachers = Teacher::orderBy('name')->get();
        $groups = Group::orderBy('name')->get();

        return Inertia::render('resources/courses/Edit', [
            'course' => $course,
            'teachers' => $teachers,
            'groups' => $groups,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCourseRequest $request, Course $course)
    {
        $validated = $request->validated();

        $course->update($validated);

        if ($request->has('groups')) {
            $course->groups()->sync($validated['groups']);
        } else {
            $course->groups()->sync([]);
        }

        return redirect()->route('courses.show', $course);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Course $course)
    {
        $course->delete();

        return redirect()->route('courses.index');
    }
}
