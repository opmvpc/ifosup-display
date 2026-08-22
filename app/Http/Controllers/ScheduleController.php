<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleIndexRequest;
use App\Http\Requests\StoreScheduleAssignmentRequest;
use App\Http\Requests\UpdateScheduleAssignmentRequest;
use App\Http\Requests\UpdateScheduleAssignmentStatusRequest;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function index(ScheduleIndexRequest $request): Response
    {
        $validated = $request->validated();

        $defaultFromDate = now()->subDay()->toDateString();
        $defaultToDate = now()->addDays(30)->toDateString();

        $fromDate = $this->normalizeDate(
            $validated['from'] ?? $request->cookie('scheduler_from_date'),
            $defaultFromDate,
        );

        $toDate = $this->normalizeDate(
            $validated['to'] ?? $request->cookie('scheduler_to_date'),
            $defaultToDate,
        );

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        Cookie::queue(Cookie::make('scheduler_from_date', $fromDate, 120, '/', null, false, false, false, 'lax'));
        Cookie::queue(Cookie::make('scheduler_to_date', $toDate, 120, '/', null, false, false, false, 'lax'));

        $assignments = Assignment::query()
            ->with(['course', 'room'])
            ->whereDate('date', '>=', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->get();

        $rooms = Room::all();
        $courses = Course::orderBy('code')->get();

        return Inertia::render('Schedule', [
            'assignments' => $assignments,
            'rooms' => $rooms,
            'courses' => $courses,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
        ]);
    }

    private function normalizeDate(?string $value, string $fallback): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->toDateString();
        } catch (\Throwable) {
            return $fallback;
        }
    }

    public function store(StoreScheduleAssignmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($this->slotIsOccupied((int) $data['room_id'], (string) $data['date'], (string) $data['period'])) {
            return response()->json([
                'message' => 'Ce créneau est déjà occupé.',
            ], 422);
        }

        // Deux écritures strictement concurrentes peuvent toutes deux passer
        // slotIsOccupied ; la contrainte d'unicité tranche alors, et mérite le
        // même message qu'un créneau occupé — pas un 500.
        try {
            $assignment = Assignment::create($data)->load(['course', 'room']);
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'Ce créneau est déjà occupé.',
            ], 422);
        }

        return response()->json([
            'assignment' => $assignment,
        ], 201);
    }

    public function update(UpdateScheduleAssignmentRequest $request, Assignment $assignment): JsonResponse
    {
        $data = $request->validated();

        if ($this->slotIsOccupied(
            (int) $data['room_id'],
            (string) $data['date'],
            (string) $data['period'],
            $assignment->id,
        )) {
            return response()->json([
                'message' => 'Ce créneau est déjà occupé.',
            ], 422);
        }

        try {
            $assignment->update($data);
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'Ce créneau est déjà occupé.',
            ], 422);
        }

        return response()->json([
            'assignment' => $assignment->load(['course', 'room']),
        ]);
    }

    public function updateStatus(
        UpdateScheduleAssignmentStatusRequest $request,
        Assignment $assignment,
    ): JsonResponse {
        $assignment->update($request->validated());

        return response()->json([
            'assignment' => $assignment->load(['course', 'room']),
        ]);
    }

    public function destroy(Assignment $assignment): JsonResponse
    {
        $assignment->delete();

        return response()->json([
            'deleted' => true,
        ]);
    }

    public function bulkPreview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'day_of_week' => ['required', 'integer', 'min:1', 'max:7'],
            'period' => ['required', 'in:morning,afternoon,evening'],
            'start_week' => ['required', 'regex:/^\d{4}-W(0[1-9]|[1-4]\d|5[0-3])$/'],
            'end_week' => [
                'required',
                'regex:/^\d{4}-W(0[1-9]|[1-4]\d|5[0-3])$/',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    $startWeek = $request->input('start_week');

                    // Une fois le format YYYY-Www garanti par la regex ci-dessus, la comparaison
                    // lexicographique correspond à l'ordre chronologique (contrairement à `gte`,
                    // qui compare la longueur des chaînes et ne rejette donc jamais rien ici).
                    if (is_string($startWeek) && $value < $startWeek) {
                        $fail('La semaine de fin doit être postérieure ou égale à la semaine de début.');
                    }
                },
            ],
        ]);

        $rangeStart = $this->isoWeekToMonday($data['start_week']);
        $rangeEnd = $this->isoWeekToMonday($data['end_week'])->addDays(6);

        $dayOffset = ($data['day_of_week'] - $rangeStart->isoWeekday() + 7) % 7;
        $firstOccurrence = $rangeStart->copy()->addDays($dayOffset);

        $dates = [];
        for ($cursor = $firstOccurrence; $cursor->lte($rangeEnd); $cursor->addWeek()) {
            $dates[] = $cursor->toDateString();
        }

        if ($dates === []) {
            return response()->json(['dates' => [], 'existing' => []]);
        }

        $existing = Assignment::query()
            ->with('course:id,name,code')
            ->whereIn('date', $dates)
            ->where('period', $data['period'])
            ->where('room_id', $data['room_id'])
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'date' => $a->date->toDateString(),
                'room_id' => $a->room_id,
                'course' => $a->course,
            ]);

        return response()->json(['dates' => $dates, 'existing' => $existing]);
    }

    public function bulkStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'period' => ['required', 'in:morning,afternoon,evening'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.date' => ['required', 'date_format:Y-m-d'],
            'rows.*.room_id' => ['required', 'integer', 'exists:rooms,id'],
        ]);

        DB::transaction(function () use ($data): void {
            $now = now();
            foreach ($data['rows'] as $row) {
                Assignment::query()
                    ->where('room_id', $row['room_id'])
                    ->whereDate('date', $row['date'])
                    ->where('period', $data['period'])
                    ->delete();

                Assignment::query()->create([
                    'course_id' => $data['course_id'],
                    'room_id' => $row['room_id'],
                    'date' => $row['date'],
                    'period' => $data['period'],
                    'status' => 'planned',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        return response()->json(['inserted' => count($data['rows'])]);
    }

    private function isoWeekToMonday(string $isoWeek): Carbon
    {
        preg_match('/^(\d{4})-W(\d{2})$/', $isoWeek, $matches);

        $year = (int) $matches[1];
        $week = (int) $matches[2];

        $jan4 = Carbon::create($year, 1, 4, 0, 0, 0, 'UTC')->startOfDay();
        $weekOneMonday = $jan4->copy()->subDays($jan4->isoWeekday() - 1);

        return $weekOneMonday->addWeeks($week - 1);
    }

    private function slotIsOccupied(
        int $roomId,
        string $date,
        string $period,
        ?int $ignoreAssignmentId = null,
    ): bool {
        return Assignment::query()
            ->when($ignoreAssignmentId !== null, fn ($query) => $query->where('id', '!=', $ignoreAssignmentId))
            ->where('room_id', $roomId)
            ->whereDate('date', $date)
            ->where('period', $period)
            ->exists();
    }
}
