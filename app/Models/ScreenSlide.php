<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ScreenSlide extends Model
{
    use HasFactory;

    public const TYPE_WELCOME = 'welcome';

    public const TYPE_SCHEDULE = 'schedule';

    public const TYPE_IMAGE = 'image';

    public const TYPE_VIDEO = 'video';

    protected $fillable = [
        'type',
        'position',
        'motd',
        'duration',
        'image_path',
        'video_path',
        'is_locked',
    ];

    protected $casts = [
        'position' => 'integer',
        'duration' => 'integer',
        'is_locked' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $slide): void {
            if ($slide->image_path) {
                Storage::disk('public')->delete($slide->image_path);
            }
            if ($slide->video_path) {
                Storage::disk('public')->delete($slide->video_path);
            }
        });

        static::updating(function (self $slide): void {
            if ($slide->isDirty('image_path') && $slide->getOriginal('image_path')) {
                Storage::disk('public')->delete($slide->getOriginal('image_path'));
            }
            if ($slide->isDirty('video_path') && $slide->getOriginal('video_path')) {
                Storage::disk('public')->delete($slide->getOriginal('video_path'));
            }
        });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    public static function ensureDefaultSlides(): void
    {
        if (self::query()->exists()) {
            return;
        }

        self::query()->create([
            'type' => self::TYPE_WELCOME,
            'position' => 0,
            'is_locked' => true,
        ]);

        self::query()->create([
            'type' => self::TYPE_SCHEDULE,
            'position' => 1,
            'is_locked' => false,
        ]);
    }

    /**
     * URL relative (`/storage/...`) servie via le lien symbolique `public/storage`.
     * Volontairement sur le disque par défaut : garder l'URL relative évite de
     * dépendre d'un `APP_URL` correct derrière un proxy.
     */
    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return Storage::url($this->image_path);
    }

    public function videoUrl(): ?string
    {
        if (! $this->video_path) {
            return null;
        }

        return Storage::url($this->video_path);
    }
}
