<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\ScreenSlide;
use Carbon\Carbon;
use Inertia\Inertia;

class ScreenController extends Controller
{
    private const PERIOD_TIMES = [
        'morning' => ['00:00:00', '12:30:00'],
        'afternoon' => ['12:30:00', '17:30:00'],
        'evening' => ['17:30:00', '23:59:59'],
    ];

    private const PERIOD_LABELS = [
        'morning' => 'Cours du matin',
        'afternoon' => "Cours de l'après-midi",
        'evening' => 'Cours du soir',
    ];

    private const PERIODS_OF_INTEREST = [
        'morning' => ['morning', 'afternoon'],
        'afternoon' => ['afternoon', 'evening'],
        'evening' => ['evening'],
    ];

    public function index()
    {
        return Inertia::render('Screen');
    }

    public function data()
    {
        ScreenSlide::ensureDefaultSlides();

        $timezone = (string) config('app.screen_timezone', 'Europe/Brussels');
        $now = now($timezone);

        // Les bornes de PERIOD_TIMES sont à la seconde près : sans troncature,
        // un `now` à 23:59:59,5 ne tombe dans aucune période et l'écran se vide.
        $nowToTheSecond = $now->copy()->startOfSecond();

        $currentPeriodKey = collect(self::PERIOD_TIMES)->search(function (array $times) use ($now, $nowToTheSecond) {
            return $nowToTheSecond->between(
                Carbon::createFromTimeString($times[0], $now->getTimezone()),
                Carbon::createFromTimeString($times[1], $now->getTimezone())
            );
        });

        $visiblePeriods = $currentPeriodKey
            ? self::PERIODS_OF_INTEREST[$currentPeriodKey]
            : [];

        $assignmentsByPeriod = Assignment::query()
            ->where('date', $now->toDateString())
            ->when($visiblePeriods !== [], function ($query) use ($visiblePeriods) {
                $query->whereIn('period', $visiblePeriods);
            })
            ->with(['course.groups', 'course.teacher', 'room'])
            ->orderByRaw("case period when 'morning' then 1 when 'afternoon' then 2 when 'evening' then 3 end")
            ->get()
            ->groupBy('period');

        $periods = collect($visiblePeriods)
            ->map(function (string $periodKey) use ($assignmentsByPeriod) {
                return [
                    'key' => $periodKey,
                    'title' => self::PERIOD_LABELS[$periodKey],
                    // Uniquement les champs consommés par ScheduleSlide.vue : l'endpoint
                    // est public, inutile d'y exposer les modèles entiers (ids de
                    // relations, timestamps).
                    'rows' => $assignmentsByPeriod->get($periodKey, collect())
                        ->map(fn (Assignment $assignment) => [
                            'id' => $assignment->id,
                            'status' => $assignment->status,
                            'course' => $assignment->course === null ? null : [
                                'code' => $assignment->course->code,
                                'name' => $assignment->course->name,
                                'teacher' => $assignment->course->teacher === null ? null : [
                                    'name' => $assignment->course->teacher->name,
                                ],
                                'groups' => $assignment->course->groups
                                    ->map(fn ($group) => ['name' => $group->name])
                                    ->values(),
                            ],
                            'room' => $assignment->room === null ? null : [
                                'name' => $assignment->room->name,
                            ],
                        ])
                        ->values(),
                ];
            })
            ->values();

        $slides = ScreenSlide::query()
            ->ordered()
            ->get()
            ->flatMap(function (ScreenSlide $slide) use ($periods) {
                if ($slide->type === ScreenSlide::TYPE_WELCOME) {
                    return [[
                        'key' => sprintf('welcome-%d', $slide->id),
                        'type' => 'welcome',
                        'data' => [
                            'minimumDuration' => 5000,
                            'isReady' => true,
                            'motd' => $slide->motd,
                        ],
                    ]];
                }

                if ($slide->type === ScreenSlide::TYPE_SCHEDULE) {
                    return $periods->map(function (array $period) use ($slide) {
                        return [
                            'key' => sprintf('schedule-%d-%s', $slide->id, $period['key']),
                            'type' => 'schedule',
                            'data' => [
                                'title' => $period['title'],
                                'rows' => $period['rows'],
                            ],
                        ];
                    });
                }

                if ($slide->type === ScreenSlide::TYPE_IMAGE && $slide->imageUrl()) {
                    return [[
                        'key' => sprintf('image-%d', $slide->id),
                        'type' => 'image',
                        'data' => [
                            'duration' => $slide->duration ?? 5000,
                            'src' => $slide->imageUrl(),
                        ],
                    ]];
                }

                if ($slide->type === ScreenSlide::TYPE_VIDEO && $slide->videoUrl()) {
                    return [[
                        'key' => sprintf('video-%d', $slide->id),
                        'type' => 'video',
                        'data' => [
                            'duration' => $slide->duration,
                            'src' => $slide->videoUrl(),
                        ],
                    ]];
                }

                return [];
            })
            ->values();

        return response()->json([
            'now' => $now->toDateTimeString(),
            'timezone' => $timezone,
            'slides' => $slides,
        ]);
    }
}
