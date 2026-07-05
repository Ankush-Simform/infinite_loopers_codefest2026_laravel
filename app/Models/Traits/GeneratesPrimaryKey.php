<?php

declare(strict_types=1);

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Trait to generate ULID primary keys for models.
 *
 * Each model gets a 26-character, lexicographically sortable ULID
 * instead of an auto-incrementing integer.
 */
trait GeneratesPrimaryKey
{
    /**
     * Disable incrementing ids.
     */
    public function getIncrementing(): bool
    {
        return false;
    }

    /**
     * Use string keys instead of integers.
     */
    public function getKeyType(): string
    {
        return 'string';
    }

    /**
     * Initialize the trait for a model instance.
     */
    public function initializeGeneratesPrimaryKey(): void
    {
        $this->keyType = 'string';
        $this->incrementing = false;
    }

    /**
     * Generate a new ULID for the primary key.
     */
    protected function generateId(): string
    {
        return (string) Str::ulid();
    }

    public function attemptGeneratePrimaryKey(): void
    {
        if (! $this->getKey()) {
            $this->setAttribute(
                $this->getKeyName(),
                $this->generateId()
            );
        }
    }

    /**
     * Boot the trait and set up model event listeners.
     */
    public static function bootGeneratesPrimaryKey(): void
    {
        static::creating(function (Model $model): void {
            // @phpstan-ignore-next-line - Trait is used in Models extending Model class
            $model->attemptGeneratePrimaryKey();
        });
    }
}
