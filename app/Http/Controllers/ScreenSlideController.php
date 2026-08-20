<?php

namespace App\Http\Controllers;

use App\Models\ScreenSlide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ScreenSlideController extends Controller
{
    public function index(): Response
    {
        ScreenSlide::ensureDefaultSlides();

        return Inertia::render('ScreenSlides', [
            'slides' => ScreenSlide::query()
                ->ordered()
                ->get()
                ->map(fn (ScreenSlide $slide) => $this->toEditorSlide($slide))
                ->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        ScreenSlide::ensureDefaultSlides();

        $validated = $request->validate([
            'type' => 'required|in:schedule,image,video',
            'duration' => 'nullable|integer|min:1000|max:120000',
            'image' => 'nullable|file|image|max:10240',
            'video' => 'nullable|file|mimetypes:video/mp4,video/webm,video/quicktime|max:307200',
        ], [
            'type.required' => 'Le type de slide est requis.',
            'type.in' => 'Type de slide invalide.',
            'duration.integer' => 'La durée doit être un nombre entier.',
            'duration.min' => 'La durée minimale est de 1 000 ms.',
            'duration.max' => 'La durée maximale est de 120 000 ms.',
            'image.file' => 'Le fichier image est invalide.',
            'image.image' => 'Le fichier doit être une image (JPG, PNG, GIF, WebP…).',
            'image.max' => 'L\'image ne doit pas dépasser 10 Mo.',
            'video.file' => 'Le fichier vidéo est invalide.',
            'video.mimetypes' => 'La vidéo doit être au format MP4, WebM ou MOV.',
            'video.max' => 'La vidéo ne doit pas dépasser 300 Mo.',
            'video.uploaded' => 'Le fichier est trop volumineux pour être envoyé (limite serveur dépassée).',
        ]);

        $type = (string) $validated['type'];

        if ($type === ScreenSlide::TYPE_IMAGE && ! $request->hasFile('image')) {
            return response()->json([
                'message' => 'Une image est requise pour un slide image.',
            ], 422);
        }

        if ($type === ScreenSlide::TYPE_VIDEO && ! $request->hasFile('video')) {
            return response()->json([
                'message' => 'Une video est requise pour un slide video.',
            ], 422);
        }

        $slide = ScreenSlide::query()->create([
            'type' => $type,
            'position' => ((int) ScreenSlide::query()->max('position')) + 1,
            'duration' => $type === ScreenSlide::TYPE_IMAGE
                ? ((int) ($validated['duration'] ?? 5000))
                : ($validated['duration'] ?? null),
            'image_path' => $request->file('image')?->store('screen-slides/images', 'public'),
            'video_path' => $request->file('video')?->store('screen-slides/videos', 'public'),
            'is_locked' => false,
        ]);

        return response()->json([
            'slide' => $this->toEditorSlide($slide),
        ], 201);
    }

    public function update(Request $request, ScreenSlide $screenSlide): JsonResponse
    {
        if ($screenSlide->type === ScreenSlide::TYPE_WELCOME) {
            $validated = $request->validate([
                'motd' => 'nullable|string|max:280',
            ], [
                'motd.string' => 'Le message d\'accueil doit être une chaîne de caractères.',
                'motd.max' => 'Le message d\'accueil ne peut pas dépasser 280 caractères.',
            ]);

            $screenSlide->update([
                'motd' => $validated['motd'] ?? null,
            ]);

            return response()->json([
                'slide' => $this->toEditorSlide($screenSlide->fresh()),
            ]);
        }

        if ($screenSlide->type === ScreenSlide::TYPE_IMAGE) {
            $validated = $request->validate([
                'duration' => 'required|integer|min:1000|max:120000',
                'image' => 'nullable|file|image|max:10240',
            ], [
                'duration.required' => 'La durée est requise pour un slide image.',
                'duration.integer' => 'La durée doit être un nombre entier.',
                'duration.min' => 'La durée minimale est de 1 000 ms.',
                'duration.max' => 'La durée maximale est de 120 000 ms.',
                'image.file' => 'Le fichier image est invalide.',
                'image.image' => 'Le fichier doit être une image (JPG, PNG, GIF, WebP…).',
                'image.max' => 'L\'image ne doit pas dépasser 10 Mo.',
            ]);

            $nextImagePath = $screenSlide->image_path;

            if ($request->hasFile('image')) {
                $newPath = $request->file('image')->store('screen-slides/images', 'public');

                if ($screenSlide->image_path) {
                    Storage::disk('public')->delete($screenSlide->image_path);
                }

                $nextImagePath = $newPath;
            }

            if (! $nextImagePath) {
                return response()->json([
                    'message' => 'Une image est requise pour un slide image.',
                ], 422);
            }

            $screenSlide->update([
                'duration' => (int) $validated['duration'],
                'image_path' => $nextImagePath,
            ]);

            return response()->json([
                'slide' => $this->toEditorSlide($screenSlide->fresh()),
            ]);
        }

        if ($screenSlide->type === ScreenSlide::TYPE_VIDEO) {
            $validated = $request->validate([
                'duration' => 'nullable|integer|min:1000|max:120000',
                'video' => 'nullable|file|mimetypes:video/mp4,video/webm,video/quicktime|max:307200',
            ], [
                'duration.integer' => 'La durée doit être un nombre entier.',
                'duration.min' => 'La durée minimale est de 1 000 ms.',
                'duration.max' => 'La durée maximale est de 120 000 ms.',
                'video.file' => 'Le fichier vidéo est invalide.',
                'video.mimetypes' => 'La vidéo doit être au format MP4, WebM ou MOV.',
                'video.max' => 'La vidéo ne doit pas dépasser 300 Mo.',
                'video.uploaded' => 'Le fichier est trop volumineux pour être envoyé (limite serveur dépassée).',
            ]);

            $nextVideoPath = $screenSlide->video_path;

            if ($request->hasFile('video')) {
                $newPath = $request->file('video')->store('screen-slides/videos', 'public');

                if ($screenSlide->video_path) {
                    Storage::disk('public')->delete($screenSlide->video_path);
                }

                $nextVideoPath = $newPath;
            }

            if (! $nextVideoPath) {
                return response()->json([
                    'message' => 'Une video est requise pour un slide video.',
                ], 422);
            }

            $screenSlide->update([
                'duration' => $validated['duration'] ?? null,
                'video_path' => $nextVideoPath,
            ]);

            return response()->json([
                'slide' => $this->toEditorSlide($screenSlide->fresh()),
            ]);
        }

        return response()->json([
            'slide' => $this->toEditorSlide($screenSlide->fresh()),
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        ScreenSlide::ensureDefaultSlides();

        $validated = $request->validate([
            'slide_ids' => 'required|array|min:1',
            'slide_ids.*' => 'integer|exists:screen_slides,id',
        ], [
            'slide_ids.required' => 'La liste des slides à réordonner est requise.',
            'slide_ids.array' => "La liste des slides doit être un tableau d'identifiants.",
            'slide_ids.min' => 'La liste des slides ne peut pas être vide.',
            'slide_ids.*.integer' => 'Chaque identifiant de slide doit être un entier.',
            'slide_ids.*.exists' => 'Chaque slide doit correspondre à un slide existant.',
        ]);

        $orderedIds = collect($validated['slide_ids'])
            ->map(fn ($id) => (int) $id)
            ->values();

        $slides = ScreenSlide::query()->ordered()->get();

        if ($orderedIds->count() !== $slides->count() || $orderedIds->unique()->count() !== $slides->count()) {
            return response()->json([
                'message' => 'L ordre des slides est invalide.',
            ], 422);
        }

        $lockedSlide = $slides->firstWhere('is_locked', true);

        if ($lockedSlide && $orderedIds->first() !== $lockedSlide->id) {
            return response()->json([
                'message' => 'Le slide de bienvenue doit rester en premiere position.',
            ], 422);
        }

        DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $position => $id) {
                ScreenSlide::query()->whereKey($id)->update([
                    'position' => $position,
                ]);
            }
        });

        return response()->json([
            'slides' => ScreenSlide::query()
                ->ordered()
                ->get()
                ->map(fn (ScreenSlide $slide) => $this->toEditorSlide($slide))
                ->values(),
        ]);
    }

    public function destroy(ScreenSlide $screenSlide): JsonResponse
    {
        if ($screenSlide->is_locked) {
            return response()->json([
                'message' => 'Le slide de bienvenue ne peut pas etre supprime.',
            ], 422);
        }

        if ($screenSlide->image_path) {
            Storage::disk('public')->delete($screenSlide->image_path);
        }

        if ($screenSlide->video_path) {
            Storage::disk('public')->delete($screenSlide->video_path);
        }

        $screenSlide->delete();

        $remainingSlides = ScreenSlide::query()->ordered()->get();

        DB::transaction(function () use ($remainingSlides) {
            foreach ($remainingSlides as $position => $slide) {
                ScreenSlide::query()->whereKey($slide->id)->update([
                    'position' => $position,
                ]);
            }
        });

        return response()->json([
            'deleted' => true,
        ]);
    }

    private function toEditorSlide(ScreenSlide $slide): array
    {
        return [
            'id' => $slide->id,
            'key' => sprintf('%s-%d', $slide->type, $slide->id),
            'type' => $slide->type,
            'position' => $slide->position,
            'is_locked' => $slide->is_locked,
            'motd' => $slide->motd,
            'duration' => $slide->duration,
            'image_url' => $slide->imageUrl(),
            'video_url' => $slide->videoUrl(),
        ];
    }
}
