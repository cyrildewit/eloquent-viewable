<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\AuthorDigest;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $timezone
 * @property ?string $digested_week
 */
class Author extends Model
{
    use Notifiable;

    #[\Override]
    protected $guarded = [];

    /** @return HasMany<Essay, $this> */
    public function essays(): HasMany
    {
        return $this->hasMany(Essay::class);
    }
}
