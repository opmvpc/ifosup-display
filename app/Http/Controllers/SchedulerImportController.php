<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Room;
use App\Services\SchedulerSheetParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SchedulerImportController extends Controller
{
    public function __construct(protected SchedulerSheetParser $parser) {}

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function sessionFileKey(): string
    {
        return 'scheduler_import_pending_file';
    }

    private function sessionYearKey(): string
    {
        return 'scheduler_import_start_year';
    }

    /**
     * Returns the stored file path if it still exists on disk, or null.
     * Also cleans up stale session entries.
     */
    private function pendingPath(Request $request): ?string
    {
        $path = $request->session()->get($this->sessionFileKey());

        if ($path && Storage::exists($path)) {
            return $path;
        }

        if ($path) {
            $request->session()->forget($this->sessionFileKey());
        }

        return null;
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        return Inertia::render('SchedulerImport', [
            'hasPendingFile' => $this->pendingPath($request) !== null,
            'justUploaded' => $request->session()->pull('just_uploaded', false),
        ]);
    }

    // ─── Upload ───────────────────────────────────────────────────────────────

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
            'start_year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        // Delete any previous pending file for this session
        $existing = $request->session()->get($this->sessionFileKey());
        if ($existing) {
            Storage::delete($existing);
        }

        $path = $request->file('file')->store('scheduler-imports');

        $request->session()->put($this->sessionFileKey(), $path);
        $request->session()->put($this->sessionYearKey(), (int) $request->input('start_year'));
        $request->session()->flash('just_uploaded', true);

        return redirect()->route('scheduler.import');
    }

    // ─── Preview (JSON) ───────────────────────────────────────────────────────

    public function preview(Request $request): JsonResponse
    {
        $path = $this->pendingPath($request);

        if (! $path) {
            return response()->json(['error' => 'Aucun fichier en attente.'], 422);
        }

        $startYear = $request->session()->get($this->sessionYearKey(), (int) now()->year);
        $absolutePath = Storage::path($path);
        $parsed = $this->parser->parse($absolutePath, $startYear);

        // Date range
        $allDates = array_column($parsed, 'date');
        sort($allDates);
        $dateFrom = ! empty($allDates) ? $allDates[0] : null;
        $dateTo = ! empty($allDates) ? end($allDates) : null;
        $assignmentsInRange = ($dateFrom && $dateTo)
            ? Assignment::whereBetween('date', [$dateFrom, $dateTo])->count()
            : 0;

        $localNames = array_values(array_unique(array_column($parsed, 'local')));
        $courseCodes = array_values(array_unique(array_column($parsed, 'course')));

        $roomsByName = Room::whereIn('name', $localNames)->pluck('id', 'name');
        $coursesInDb = Course::whereIn('code', $courseCodes)->get(['code', 'name']);
        $coursesByCode = $coursesInDb->pluck('id', 'code');

        $existingRooms = array_keys($roomsByName->toArray());
        $newRooms = array_values(array_diff($localNames, $existingRooms));
        $allRooms = array_merge($existingRooms, $newRooms);

        $knownCourseCodes = $coursesInDb->pluck('code')->all();
        $knownCourses = $coursesInDb->map(fn ($c) => ['code' => $c->code, 'name' => $c->name])->values()->all();
        $unknownCourses = array_values(array_diff($courseCodes, $knownCourseCodes));

        // Conflicts: date + period + room already occupied in DB (only existing rooms)
        $conflicts = [];
        foreach ($parsed as $entry) {
            $roomId = $roomsByName[$entry['local']] ?? null;
            if (! $roomId) {
                continue; // new room → no conflict possible
            }

            $existing = Assignment::where('date', $entry['date'])
                ->where('period', $entry['period'])
                ->where('room_id', $roomId)
                ->with('course')
                ->first();

            if ($existing && $existing->course?->code !== $entry['course']) {
                $conflicts[] = [
                    'date' => $entry['date'],
                    'period' => $entry['period'],
                    'local' => $entry['local'],
                    'course_new' => $entry['course'],
                    'course_current' => $existing->course?->code,
                ];
            }
        }

        // Build a quick lookup of conflict slots: "date|period|local" => true
        $conflictSlotKeys = [];
        foreach ($conflicts as $c) {
            $conflictSlotKeys[$c['date'].'|'.$c['period'].'|'.$c['local']] = true;
        }

        // Breakdown: per (any_room × any_course) pair — frontend filters by selection
        $breakdownMap = [];
        foreach ($parsed as $entry) {
            if (! in_array($entry['local'], $allRooms)) {
                continue;
            }
            $key = $entry['local'].'|||'.$entry['course'];
            if (! isset($breakdownMap[$key])) {
                $breakdownMap[$key] = [
                    'room' => $entry['local'],
                    'course' => $entry['course'],
                    'count' => 0,
                    'conflict_count' => 0,
                ];
            }
            $breakdownMap[$key]['count']++;
            if (isset($conflictSlotKeys[$entry['date'].'|'.$entry['period'].'|'.$entry['local']])) {
                $breakdownMap[$key]['conflict_count']++;
            }
        }

        $breakdown = array_values($breakdownMap);

        // Raw counts per room / course directly from parsed data (not filtered by known courses)
        $roomCounts = array_count_values(array_column($parsed, 'local'));
        $courseCounts = array_count_values(array_column($parsed, 'course'));

        return response()->json([
            'total' => count($parsed),
            'start_year' => $startYear,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'assignments_in_range' => $assignmentsInRange,
            'existing_rooms' => $existingRooms,
            'new_rooms' => $newRooms,
            'known_courses' => $knownCourses,
            'unknown_courses' => $unknownCourses,
            'conflicts' => $conflicts,
            'breakdown' => $breakdown,
            'room_counts' => $roomCounts,
            'course_counts' => $courseCounts,
        ]);
    }

    // ─── Execute import (JSON) ────────────────────────────────────────────────

    public function executeImport(Request $request): JsonResponse
    {
        $request->validate([
            'selected_rooms' => ['required', 'array'],
            'selected_rooms.*' => ['string'],
            'selected_courses' => ['required', 'array'],
            'selected_courses.*' => ['string'],
            'purge_period' => ['boolean'],
        ]);

        $path = $this->pendingPath($request);

        if (! $path) {
            return response()->json(['error' => 'Aucun fichier en attente.'], 422);
        }

        $startYear = $request->session()->get($this->sessionYearKey(), (int) now()->year);
        $absolutePath = Storage::path($path);
        $parsed = $this->parser->parse($absolutePath, $startYear);

        $selectedRooms = $request->input('selected_rooms');
        $selectedCourses = $request->input('selected_courses');
        $purgePeriod = $request->boolean('purge_period', false);

        // La purge est définitive (`Assignment` n'a pas de SoftDeletes) : elle doit être
        // atomique avec la réinsertion, sinon un échec en cours de boucle laisse la
        // période vidée et l'import à moitié fait, sans récupération possible.
        try {
            [$purged, $imported, $replaced] = DB::transaction(function () use ($parsed, $selectedRooms, $selectedCourses, $purgePeriod): array {
                // Optionally purge all assignments in the import date range first
                $purged = 0;
                if ($purgePeriod) {
                    $allDates = array_column($parsed, 'date');
                    if (! empty($allDates)) {
                        sort($allDates);
                        $purgeDateFrom = $allDates[0];
                        $purgeDateTo = end($allDates);
                        $purged = Assignment::whereBetween('date', [$purgeDateFrom, $purgeDateTo])->delete();
                    }
                }

                // Create rooms that don't exist yet (only those selected)
                $rooms = Room::whereIn('name', $selectedRooms)->pluck('id', 'name')->toArray();
                foreach ($selectedRooms as $roomName) {
                    if (! isset($rooms[$roomName])) {
                        $room = Room::create(['name' => $roomName]);
                        $rooms[$roomName] = $room->id;
                    }
                }

                $courses = Course::whereIn('code', $selectedCourses)->pluck('id', 'code');

                $imported = 0;
                $replaced = 0;

                foreach ($parsed as $entry) {
                    $roomId = $rooms[$entry['local']] ?? null;
                    $courseId = $courses[$entry['course']] ?? null;

                    if (! $roomId || ! $courseId) {
                        continue;
                    }

                    $existing = Assignment::where('date', $entry['date'])
                        ->where('period', $entry['period'])
                        ->where('room_id', $roomId)
                        ->first();

                    if ($existing) {
                        $existing->update(['course_id' => $courseId, 'status' => 'planned']);
                        $replaced++;
                    } else {
                        Assignment::create([
                            'date' => $entry['date'],
                            'period' => $entry['period'],
                            'room_id' => $roomId,
                            'course_id' => $courseId,
                            'status' => 'planned',
                        ]);
                        $imported++;
                    }
                }

                return [$purged, $imported, $replaced];
            });
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => "L'import a échoué, aucune modification n'a été enregistrée. Le planning est intact.",
            ], 500);
        }

        // Clean up uploaded file — après le commit uniquement : en cas d'échec, le
        // fichier reste disponible pour retenter l'import.
        Storage::delete($path);
        $request->session()->forget([$this->sessionFileKey(), $this->sessionYearKey()]);

        return response()->json([
            'imported' => $imported,
            'replaced' => $replaced,
            'purged' => $purged,
        ]);
    }

    // ─── Discard ──────────────────────────────────────────────────────────────

    public function discard(Request $request): RedirectResponse
    {
        $path = $request->session()->get($this->sessionFileKey());

        if ($path) {
            Storage::delete($path);
            $request->session()->forget([$this->sessionFileKey(), $this->sessionYearKey()]);
        }

        return redirect()->route('scheduler.import');
    }
}
