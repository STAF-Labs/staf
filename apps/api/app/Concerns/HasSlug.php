<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::saving(function (Model $model): void {
            if (filled($model->getAttribute('slug'))) {
                return;
            }

            $model->setAttribute('slug', $model->makeUniqueSlug());
        });
    }

    protected function makeUniqueSlug(): string
    {
        $baseSlug = Str::slug((string) $this->getAttribute($this->slugSourceAttribute()));

        if ($baseSlug === '') {
            $baseSlug = Str::lower(Str::random(8));
        }

        $slug = $baseSlug;
        $suffix = 2;

        while ($this->newSlugQuery($slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    protected function slugSourceAttribute(): string
    {
        return match (true) {
            array_key_exists('name', $this->getAttributes()) => 'name',
            array_key_exists('title', $this->getAttributes()) => 'title',
            default => 'id',
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function uniqueSlugConstraints(): array
    {
        return [];
    }

    private function newSlugQuery(string $slug): Builder
    {
        $query = static::query()->where('slug', $slug);

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            $query->withTrashed();
        }

        foreach ($this->uniqueSlugConstraints() as $column => $value) {
            $query->where($column, $value);
        }

        if ($this->exists) {
            $query->whereKeyNot($this->getKey());
        }

        return $query;
    }
}
