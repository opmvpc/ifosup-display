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
     * Clé de correspondance des noms de locaux et codes de cours.
     *
     * MySQL compare les chaînes sans tenir compte de la casse (utf8mb4_unicode_ci),
     * les tableaux PHP oui : sans clé commune, « SALLE A » passait pour un nouveau
     * local alors que « Salle A » existe (création → violation d'unicité → 500), et
     * les lignes d'un cours « abc » étaient ignorées en silence face à « ABC ».
     * Toutes les correspondances passent par cette clé ; la casse d'origine ne sert
     * qu'à l'affichage et à la création.
     */
    private function matchKey(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /**
     * Un classeur corrompu, une cellule de date malformée ou une formule que
     * PhpSpreadsheet ne digère pas levaient un 500 brut : l'utilisateur doit
     * savoir que c'est son fichier qui est en cause, et pouvoir en téléverser
     * un autre.
     */
    private function unreadableFileResponse(): JsonResponse
    {
        return response()->json([
            'error' => "Le fichier n'a pas pu être lu. Vérifiez qu'il s'agit bien d'un planning au format attendu, ou réexportez-le depuis Excel.",
        ], 422);
    }

    /**
     * Table de correspondance clé normalisée → id, à partir d'un pluck('id', nom).
     * Les tables sont petites (quelques dizaines de locaux, quelques centaines de
     * cours) : on charge tout plutôt que de dépendre de la collation du moteur SQL.
     */
    private function keyedByMatchKey(iterable $idsByName): array
    {
        $byKey = [];
        foreach ($idsByName as $name => $id) {
            $byKey[$this->matchKey((string) $name)] ??= $id;
        }

        return $byKey;
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
        ], [
            'file.required' => 'Le fichier est requis.',
            'file.file' => 'Le fichier envoyé est invalide.',
            'file.mimes' => 'Le fichier doit être au format Excel (.xlsx ou .xls).',
            'file.max' => 'Le fichier ne doit pas dépasser 20 Mo.',
            'start_year.required' => "L'année de départ est requise.",
            'start_year.integer' => "L'année de départ doit être un nombre entier.",
            'start_year.min' => "L'année de départ doit être supérieure ou égale à 2000.",
            'start_year.max' => "L'année de départ doit être inférieure ou égale à 2100.",
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

        try {
            $parsed = $this->parser->parse($absolutePath, $startYear);
        } catch (Throwable $e) {
            report($e);

            return $this->unreadableFileResponse();
        }

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

        $roomsByKey = $this->keyedByMatchKey(Room::pluck('id', 'name'));

        // Les listes affichées gardent la casse du fichier : c'est elle que le
        // frontend renvoie dans selected_rooms / selected_courses.
        $existingRooms = array_values(array_filter(
            $localNames,
            fn (string $name) => isset($roomsByKey[$this->matchKey($name)]),
        ));
        $newRooms = array_values(array_diff($localNames, $existingRooms));
        $allRooms = array_merge($existingRooms, $newRooms);

        $fileCourseKeys = array_flip(array_map($this->matchKey(...), $courseCodes));
        $coursesInDb = Course::get(['code', 'name'])
            ->filter(fn ($c) => isset($fileCourseKeys[$this->matchKey($c->code)]))
            ->values();

        $knownCourseKeys = array_flip($coursesInDb->map(fn ($c) => $this->matchKey($c->code))->all());
        $knownCourses = $coursesInDb->map(fn ($c) => ['code' => $c->code, 'name' => $c->name])->all();
        $unknownCourses = array_values(array_filter(
            $courseCodes,
            fn (string $code) => ! isset($knownCourseKeys[$this->matchKey($code)]),
        ));

        // Conflicts: date + period + room already occupied in DB (only existing rooms)
        $conflicts = [];
        foreach ($parsed as $entry) {
            $roomId = $roomsByKey[$this->matchKey($entry['local'])] ?? null;
            if (! $roomId) {
                continue; // new room → no conflict possible
            }

            $existing = Assignment::where('date', $entry['date'])
                ->where('period', $entry['period'])
                ->where('room_id', $roomId)
                ->with('course')
                ->first();

            $existingCode = $existing?->course?->code;

            if ($existing && ($existingCode === null || $this->matchKey($existingCode) !== $this->matchKey($entry['course']))) {
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
        ], [
            'selected_rooms.required' => 'Les locaux sélectionnés sont requis.',
            'selected_rooms.array' => 'Les locaux sélectionnés doivent être un tableau.',
            'selected_rooms.*.string' => 'Chaque local sélectionné doit être une chaîne de caractères.',
            'selected_courses.required' => 'Les cours sélectionnés sont requis.',
            'selected_courses.array' => 'Les cours sélectionnés doivent être un tableau.',
            'selected_courses.*.string' => 'Chaque cours sélectionné doit être une chaîne de caractères.',
            'purge_period.boolean' => 'La purge de la période doit être vraie ou fausse.',
        ]);

        $path = $this->pendingPath($request);

        if (! $path) {
            return response()->json(['error' => 'Aucun fichier en attente.'], 422);
        }

        $startYear = $request->session()->get($this->sessionYearKey(), (int) now()->year);
        $absolutePath = Storage::path($path);

        try {
            $parsed = $this->parser->parse($absolutePath, $startYear);
        } catch (Throwable $e) {
            report($e);

            return $this->unreadableFileResponse();
        }

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
                $roomsByKey = $this->keyedByMatchKey(Room::pluck('id', 'name'));
                $selectedRoomKeys = [];
                foreach ($selectedRooms as $roomName) {
                    $key = $this->matchKey($roomName);
                    $selectedRoomKeys[$key] = true;

                    if (! isset($roomsByKey[$key])) {
                        $room = Room::create(['name' => trim($roomName)]);
                        $roomsByKey[$key] = $room->id;
                    }
                }

                $coursesByKey = $this->keyedByMatchKey(Course::pluck('id', 'code'));
                $selectedCourseKeys = array_flip(array_map($this->matchKey(...), $selectedCourses));

                $imported = 0;
                $replaced = 0;

                foreach ($parsed as $entry) {
                    $localKey = $this->matchKey($entry['local']);
                    $courseKey = $this->matchKey($entry['course']);

                    if (! isset($selectedRoomKeys[$localKey]) || ! isset($selectedCourseKeys[$courseKey])) {
                        continue;
                    }

                    $roomId = $roomsByKey[$localKey] ?? null;
                    $courseId = $coursesByKey[$courseKey] ?? null;

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
